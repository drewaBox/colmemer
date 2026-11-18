<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
start_app_session();

$db = db();
$error = '';
$notice = '';
$otpSent = isset($_SESSION['pending_request_otp_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid form token. Please reload the request page and try again.');
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'confirm' || $action === 'confirm_guest') {
    $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
    $document = is_int($documentId) && $documentId > 0
        ? active_request_document($db, $documentId)
        : null;
    if (!$document) {
        http_response_code(400);
        exit('The selected document is not available. Return to the request page and try again.');
    }

    $userId = null;
    $guest = null;
    $academicRecordDetails = null;
    $requestType = 'guest';
    $requestStudentNumber = null;
    if ($action === 'confirm') {
        $studentNumber = trim((string) ($_POST['student_no'] ?? ''));
        if ($studentNumber === '' || strlen($studentNumber) > 64
            || ($_POST['confirm_details'] ?? '') !== 'yes') {
            http_response_code(400);
            exit('Confirm the student details and try again.');
        }

        $student = find_request_student($db, $studentNumber);
        if (!$student || !is_string($student['email'])
            || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            exit('A currently enrolled student record with a valid email is required. Return to the request page and try again.');
        }
        $requestType = $student['source'] === 'imported_roster' ? 'roster' : 'enrolled';
        $requestStudentNumber = $studentNumber;
        if ($requestType === 'enrolled') {
            $userId = (int) $student['id'];
        }
        $email = (string) $student['email'];

        if (document_requires_academic_history($document)) {
            try {
                $recordFields = validated_academic_record_fields($_POST);
            } catch (InvalidArgumentException $exception) {
                http_response_code(400);
                exit($exception->getMessage());
            }

            $studentCourse = trim((string) ($student['course'] ?? ''));
            if ($studentCourse === '' || strlen($studentCourse) > 120) {
                http_response_code(400);
                exit('The course could not be confirmed from your student record. Contact the Registrar.');
            }

            $yearLevel = (string) ($student['year_level'] ?? '');
            if ($yearLevel !== '' && ctype_digit($yearLevel)) {
                $yearLevel = 'Year ' . (int) $yearLevel;
            } elseif ($yearLevel === '') {
                $yearLevel = 'Currently enrolled';
            }
            $currentStartYear = (int) date('Y') - 1;
            $academicRecordDetails = array_merge($recordFields, [
                'requester_status' => 'currently_enrolled',
                'student_number' => $studentNumber,
                'course' => $studentCourse,
                'year_level' => $yearLevel,
                'academic_year' => isset($student['start_year'], $student['end_year'])
                    ? $student['start_year'] . '-' . $student['end_year']
                    : $currentStartYear . '-' . ($currentStartYear + 1),
                'email' => $email,
            ]);
            $guest = $academicRecordDetails;
        }

        if ($requestType === 'enrolled') {
            $verified = $db->prepare(
                'SELECT id FROM student_email_verifications
                 WHERE user_id = :user_id AND email = :email
                 LIMIT 1'
            );
            $verified->execute(['user_id' => $userId, 'email' => $email]);
            if ($verified->fetchColumn() !== false) {
                $_SESSION['request_result'] = complete_student_request(
                    $db,
                    $userId,
                    $document,
                    $academicRecordDetails
                );
                header('Location: request-complete.php');
                exit;
            }
        }
    } else {
        $requesterStatus = (string) ($_POST['requester_status'] ?? '');
        $isAlumniForm = $requesterStatus === 'alumni';
        $requiresAcademicHistory = document_requires_academic_history($document);
        $guest = [
            'requester_status' => $requesterStatus,
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'course' => trim((string) ($_POST['course'] ?? '')),
            'year_level' => trim((string) ($_POST['year_level'] ?? '')),
            'academic_year' => trim((string) ($_POST['academic_year'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ];
        if ($requiresAcademicHistory) {
            try {
                $guest = array_merge($guest, validated_academic_record_fields($_POST));
            } catch (InvalidArgumentException $exception) {
                http_response_code(400);
                exit($exception->getMessage());
            }
        }
        if ($isAlumniForm && $guest['year_level'] === '') {
            $guest['year_level'] = 'Graduated';
        }
        $courseExists = false;
        if ($guest['course'] !== '') {
            $courseQuery = $db->prepare(
                "SELECT 1 FROM school_courses
                 WHERE (name = :name OR CONCAT(acronym, ' - ', name) = :display_name)
                   AND is_active = 1
                 LIMIT 1"
            );
            $courseQuery->execute([
                'name' => $guest['course'],
                'display_name' => $guest['course'],
            ]);
            $courseExists = $courseQuery->fetchColumn() !== false;
        }
        $academicYearValid = in_array($guest['academic_year'], recent_graduation_years(), true)
            || (!$requiresAcademicHistory
                && in_array($guest['academic_year'], available_academic_years($db), true));
        if (!in_array($requesterStatus, ['alumni', 'dropped_out', 'transferred_out'], true)
            || ($requiresAcademicHistory && !$isAlumniForm)
            || $guest['first_name'] === '' || strlen($guest['first_name']) > 80
            || strlen($guest['middle_name']) > 100
            || $guest['last_name'] === '' || strlen($guest['last_name']) > 80
            || strlen($guest['course']) > 80 || !$courseExists
            || $guest['year_level'] === '' || strlen($guest['year_level']) > 40
            || !$academicYearValid
            || !filter_var($guest['email'], FILTER_VALIDATE_EMAIL) || strlen($guest['email']) > 120
            || (!$isAlumniForm && ($_POST['confirm_details'] ?? '') !== 'yes')) {
            http_response_code(400);
            exit($requiresAcademicHistory
                ? 'Complete the alumni academic-record form with valid details.'
                : 'Complete the former-student form with valid details and confirm the information.');
        }
        $email = $guest['email'];
    }

    $rateLimit = $db->prepare(
        'SELECT COUNT(*) FROM student_request_otps
         WHERE email = :email AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $rateLimit->execute(['email' => $email]);
    if ((int) $rateLimit->fetchColumn() >= 5) {
        $error = 'Too many verification codes were requested. Please wait 15 minutes or contact the Registrar.';
    } else {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $insertOtp = $db->prepare(
            'INSERT INTO student_request_otps
                (user_id, document_id, email, code_hash, expires_at)
             VALUES (:user_id, :document_id, :email, :code_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
        );
        $insertOtp->execute([
            'user_id' => $userId,
            'document_id' => (int) $document['id'],
            'email' => $email,
            'code_hash' => hash_hmac('sha256', $otp, session_id()),
        ]);
        $otpId = (int) $db->lastInsertId();

        try {
            send_registrar_template($db, 'verification_code', $email, [
                'verification_code' => $otp,
                'expiration_minutes' => '10',
            ]);
            $_SESSION['pending_request_otp_id'] = $otpId;
            $_SESSION['pending_request_user_id'] = $userId;
            $_SESSION['pending_request_document_id'] = (int) $document['id'];
            $_SESSION['pending_request_email'] = $email;
            $_SESSION['pending_request_type'] = $requestType;
            if ($requestType === 'roster') {
                $_SESSION['pending_request_student_number'] = $requestStudentNumber;
            } else {
                unset($_SESSION['pending_request_student_number']);
            }
            if ($guest !== null) {
                $_SESSION['pending_request_guest'] = $guest;
            } else {
                unset($_SESSION['pending_request_guest']);
            }
            $otpSent = true;
            $notice = 'A 6-digit verification code was sent to ' . mask_student_email($email) . '.';
        } catch (RuntimeException $exception) {
            $invalidateOtp = $db->prepare('UPDATE student_request_otps SET used_at = NOW() WHERE id = :id');
            $invalidateOtp->execute(['id' => $otpId]);
            $error = $exception->getMessage();
        }
    }
} elseif ($action === 'verify_code') {
    $otpId = filter_var($_SESSION['pending_request_otp_id'] ?? null, FILTER_VALIDATE_INT);
    $documentId = filter_var($_SESSION['pending_request_document_id'] ?? null, FILTER_VALIDATE_INT);
    $userId = $_SESSION['pending_request_user_id'] ?? null;
    $email = $_SESSION['pending_request_email'] ?? null;
    $requestType = $_SESSION['pending_request_type'] ?? null;
    $guest = $_SESSION['pending_request_guest'] ?? null;
    $studentNumber = $_SESSION['pending_request_student_number'] ?? null;
    $code = trim((string) ($_POST['verification_code'] ?? ''));

    if (!is_int($otpId) || !is_int($documentId) || !is_string($email)
        || !in_array($requestType, ['enrolled', 'roster', 'guest'], true)
        || ($requestType === 'enrolled' && !is_int($userId))
        || ($requestType === 'roster' && (!is_string($studentNumber) || $studentNumber === '' || $userId !== null))
        || ($requestType === 'guest' && (!is_array($guest) || $userId !== null))) {
        $error = 'Your verification session has expired. Start the request again.';
        $otpSent = false;
    } elseif (!preg_match('/^\d{6}$/D', $code)) {
        $error = 'Enter the 6-digit code from your email.';
        $otpSent = true;
    } else {
        $otpQuery = $db->prepare(
            'SELECT id, user_id, code_hash, attempts, expires_at, used_at,
                    expires_at > NOW() AS is_unexpired
             FROM student_request_otps
             WHERE id = :id AND document_id = :document_id AND email = :email
             LIMIT 1'
        );
        $otpQuery->execute(['id' => $otpId, 'document_id' => $documentId, 'email' => $email]);
        $otp = $otpQuery->fetch();
        $otpOwnerMatches = $otp && (
            in_array($requestType, ['roster', 'guest'], true)
                ? $otp['user_id'] === null
                : (int) $otp['user_id'] === $userId
        );

        if (!$otp || !$otpOwnerMatches || $otp['used_at'] !== null || !(bool) $otp['is_unexpired']) {
            $error = 'The verification code expired or was already used. Start the request again.';
            unset(
                $_SESSION['pending_request_otp_id'],
                $_SESSION['pending_request_user_id'],
                $_SESSION['pending_request_document_id'],
                $_SESSION['pending_request_email'],
                $_SESSION['pending_request_type'],
                $_SESSION['pending_request_student_number'],
                $_SESSION['pending_request_guest']
            );
            $otpSent = false;
        } elseif ((int) $otp['attempts'] >= 5) {
            $error = 'Too many incorrect codes. Start the request again.';
            $invalidateOtp = $db->prepare('UPDATE student_request_otps SET used_at = NOW() WHERE id = :id');
            $invalidateOtp->execute(['id' => $otpId]);
            $otpSent = false;
        } elseif (!hash_equals(
            (string) $otp['code_hash'],
            hash_hmac('sha256', $code, session_id())
        )) {
            $updateAttempts = $db->prepare(
                'UPDATE student_request_otps
                 SET attempts = attempts + 1,
                     used_at = IF(attempts + 1 >= 5, NOW(), used_at)
                 WHERE id = :id AND used_at IS NULL'
            );
            $updateAttempts->execute(['id' => $otpId]);
            $error = (int) $otp['attempts'] + 1 >= 5
                ? 'Too many incorrect codes. Start the request again.'
                : 'That code is incorrect. Check your email and try again.';
            $otpSent = (int) $otp['attempts'] + 1 < 5;
        } else {
            $document = active_request_document($db, $documentId);
            $student = null;
            if ($requestType === 'enrolled') {
                $student = find_request_student_by_id($db, $userId);
            } elseif ($requestType === 'roster') {
                $student = find_request_student($db, $studentNumber);
            }

            if (!$document || ($requestType === 'enrolled'
                && (!$student || !hash_equals($email, (string) $student['email'])))
                || ($requestType === 'roster'
                    && (!$student || $student['source'] !== 'imported_roster'
                        || !hash_equals($email, (string) $student['email'])))) {
                $error = 'The student record or document changed. Start the request again.';
                $otpSent = false;
            } else {
                try {
                    $db->beginTransaction();
                    $consumeOtp = $db->prepare(
                        'UPDATE student_request_otps
                         SET used_at = NOW()
                         WHERE id = :id AND used_at IS NULL AND expires_at > NOW() AND attempts < 5'
                    );
                    $consumeOtp->execute(['id' => $otpId]);
                    if ($consumeOtp->rowCount() !== 1) {
                        throw new RuntimeException('The verification code expired or was already used. Start the request again.');
                    }

                    if ($requestType === 'enrolled') {
                        $saveVerification = $db->prepare(
                            'INSERT INTO student_email_verifications (user_id, email, verified_at)
                             VALUES (:user_id, :email, NOW())
                             ON DUPLICATE KEY UPDATE verified_at = VALUES(verified_at)'
                        );
                        $saveVerification->execute(['user_id' => $userId, 'email' => $email]);
                    }
                    $db->commit();
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    if ($exception instanceof PDOException) {
                        error_log('Student email verification database error: ' . $exception->getMessage());
                        $error = 'Email verification could not be saved. Please try again.';
                    } else {
                        $error = $exception->getMessage();
                    }
                    $otpSent = false;
                    $document = null;
                }

                if ($document !== null) {
                    unset(
                        $_SESSION['pending_request_otp_id'],
                        $_SESSION['pending_request_user_id'],
                        $_SESSION['pending_request_document_id'],
                        $_SESSION['pending_request_email'],
                        $_SESSION['pending_request_type'],
                        $_SESSION['pending_request_student_number'],
                        $_SESSION['pending_request_guest']
                    );
                    $_SESSION['request_result'] = match ($requestType) {
                        'enrolled' => complete_student_request(
                            $db,
                            $userId,
                            $document,
                            is_array($guest) ? $guest : null
                        ),
                        'roster' => complete_imported_student_request(
                            $db,
                            $student,
                            $document,
                            is_array($guest) ? $guest : null
                        ),
                        default => complete_guest_request($db, $guest, $document),
                    };
                    header('Location: request-complete.php');
                    exit;
                }
            }
        }
    }
} else {
    http_response_code(400);
    exit('Invalid form action.');
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$maskedEmail = isset($_SESSION['pending_request_email'])
    ? mask_student_email((string) $_SESSION['pending_request_email'])
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Verify Your Email</title>
    <link rel="stylesheet" href="assets/css/login.css?v=verification-code-loading">
</head>
<body>
    <main class="page-shell">
        <section class="login-card" aria-labelledby="page-title">
            <div class="brand-mark" aria-hidden="true">R</div>
            <p class="eyebrow">EMAIL VERIFICATION</p>
            <h1 id="page-title">Verify your email</h1>
            <p class="intro"><?= $notice !== '' ? $escape($notice) : 'Complete email verification to submit your document request.' ?></p>

            <?php if ($error !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>

            <?php if ($otpSent): ?>
                <form method="post" action="verify-request.php" data-loading-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="verify_code">
                    <input type="hidden" name="verification_code" value="">
                    <div class="field">
                        <p class="verification-code-label" id="verification-code-label">6-digit verification code</p>
                        <div class="verification-code-inputs" role="group" aria-labelledby="verification-code-label">
                            <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                       aria-label="Digit <?= $digit ?> of 6"<?= $digit === 1 ? ' autocomplete="one-time-code" autofocus' : '' ?> required>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="security-note">Code sent to <?= $escape($maskedEmail) ?>. It expires in 10 minutes.</p>
                    <p class="verification-status" role="status" aria-live="polite"></p>
                </form>
            <?php endif; ?>

            <p class="setup-link"><a href="request.php">Cancel and return to documents</a></p>
        </section>
        <footer class="page-footer">Email addresses can only be updated at the Registrar.</footer>
    </main>
    <script src="assets/js/verification-code.js?v=verification-code-loading" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
