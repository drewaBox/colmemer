<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic_years.php';
require_once __DIR__ . '/mailer.php';

function mask_student_email(string $email): string
{
    [$localPart, $domain] = explode('@', $email, 2);
    $visibleStart = substr($localPart, 0, 1);
    $visibleEnd = strlen($localPart) > 3 ? substr($localPart, -1) : '';

    return $visibleStart . str_repeat('*', max(3, strlen($localPart) - strlen($visibleStart) - strlen($visibleEnd))) . $visibleEnd . '@' . $domain;
}

function find_request_student(PDO $db, string $studentNumber): ?array
{
    $rosterStatement = $db->prepare(
        "SELECT NULL AS id, s.student_number AS student_no,
                TRIM(CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.surname)) AS full_name,
                s.first_name, s.middle_name, s.surname, s.email, c.name AS course,
                m.year_level, ay.start_year, ay.end_year, t.semester, b.imported_at,
                'currently_enrolled' AS status, 'imported_roster' AS source
         FROM student_registry s
         JOIN student_roster_memberships m ON m.student_id = s.id AND m.is_active = 1
         JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         JOIN academic_terms t ON t.id = m.academic_term_id AND t.is_current = 1
         JOIN academic_years ay ON ay.id = t.academic_year_id
         JOIN courses c ON c.id = m.course_id
         WHERE s.student_number = :student_no
         ORDER BY b.imported_at DESC, b.id DESC
         LIMIT 1"
    );
    $rosterStatement->execute(['student_no' => $studentNumber]);
    $student = $rosterStatement->fetch();

    return $student ?: null;
}

function find_request_student_by_id(PDO $db, int $userId): ?array
{
    $statement = $db->prepare(
        "SELECT u.id, s.student_number AS student_no,
                TRIM(CONCAT_WS(' ', s.first_name, NULLIF(s.middle_name, ''), s.surname)) AS full_name,
                s.email, c.name AS course, m.year_level, 'currently_enrolled' AS status,
                'account' AS source
         FROM users u
         INNER JOIN student_registry s ON s.student_number = u.student_no
         INNER JOIN student_roster_memberships m ON m.student_id = s.id AND m.is_active = 1
         INNER JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         INNER JOIN academic_terms t ON t.id = m.academic_term_id AND t.is_current = 1
         INNER JOIN courses c ON c.id = m.course_id
         WHERE u.id = :id
           AND u.role = 'student'
           AND u.is_active = 1
           AND u.is_approved = 1
         ORDER BY b.imported_at DESC, b.id DESC
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $student = $statement->fetch();

    return $student ?: null;
}

function active_request_document(PDO $db, int $documentId): ?array
{
    $statement = $db->prepare(
        'SELECT id, name, release_methods
         FROM documents
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['id' => $documentId]);
    $document = $statement->fetch();

    return $document ?: null;
}

function estimate_document_release_date(
    int $processingDays,
    ?DateTimeImmutable $processingDate = null
): DateTimeImmutable {
    if ($processingDays < 0) {
        throw new InvalidArgumentException('Processing days cannot be negative.');
    }

    $startDate = ($processingDate ?? new DateTimeImmutable('today'))->setTime(0, 0);
    while ((int) $startDate->format('N') > 5) {
        $startDate = $startDate->modify('+1 day');
    }

    while ($processingDays > 0) {
        $startDate = $startDate->modify('+1 day');
        if ((int) $startDate->format('N') < 6) {
            $processingDays--;
        }
    }

    return $startDate;
}

function archive_document_request(
    PDO $db,
    int $requestId,
    string $adminName
): string {
    $db->beginTransaction();
    try {
        $requestQuery = $db->prepare(
            'SELECT r.id, r.tracking_no, r.status, r.created_at, r.purpose,
                    COALESCE(d.name, r.purpose, \'Document request\') AS document_name
             FROM requests r
             LEFT JOIN request_items i ON i.request_id = r.id
             LEFT JOIN documents d ON d.id = i.document_id
             WHERE r.id = :id AND r.deleted_at IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $requestQuery->execute(['id' => $requestId]);
        $request = $requestQuery->fetch();
        if (!$request) {
            throw new InvalidArgumentException('That active request could not be found.');
        }

        $historyQuery = $db->prepare(
            'SELECT h.old_status, h.new_status, h.created_at,
                    COALESCE(NULLIF(u.full_name, \'\'), NULLIF(u.username, \'\'), \'Registrar staff\') AS changed_by_name
             FROM request_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.request_id = :request_id
             ORDER BY h.created_at, h.id'
        );
        $historyQuery->execute(['request_id' => $requestId]);
        $statusHistory = json_encode($historyQuery->fetchAll(), JSON_THROW_ON_ERROR);

        $auditInsert = $db->prepare(
            'INSERT INTO request_deletion_audit
                (tracking_no, document_name, final_status, requested_at, deleted_at,
                 deleted_by_name, status_history)
             VALUES (:tracking_no, :document_name, :final_status, :requested_at, NOW(),
                     :deleted_by_name, :status_history)'
        );
        $auditInsert->execute([
            'tracking_no' => $request['tracking_no'],
            'document_name' => $request['document_name'],
            'final_status' => $request['status'],
            'requested_at' => $request['created_at'],
            'deleted_by_name' => mb_substr(trim($adminName), 0, 150),
            'status_history' => $statusHistory,
        ]);
        $auditId = (int) $db->lastInsertId();

        $archiveUpdate = $db->prepare(
            'UPDATE requests
             SET deleted_at = NOW(), deletion_audit_id = :audit_id
             WHERE id = :id AND deleted_at IS NULL'
        );
        $archiveUpdate->execute([
            'audit_id' => $auditId,
            'id' => $requestId,
        ]);
        if ($archiveUpdate->rowCount() !== 1) {
            throw new RuntimeException('The request could not be moved to the deleted archive.');
        }

        $db->commit();

        return (string) $request['tracking_no'];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function restore_archived_document_request(
    PDO $db,
    int $requestId,
    int $adminId,
    string $adminName
): string {
    $db->beginTransaction();
    try {
        $requestQuery = $db->prepare(
            'SELECT id, tracking_no, status, deletion_audit_id
             FROM requests
             WHERE id = :id
               AND deleted_at IS NOT NULL
               AND deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 1
             FOR UPDATE'
        );
        $requestQuery->execute(['id' => $requestId]);
        $request = $requestQuery->fetch();
        if (!$request || (int) $request['deletion_audit_id'] < 1) {
            throw new InvalidArgumentException(
                'That request is not in the recoverable archive; its 30-day restore period may have expired.'
            );
        }

        $restoreAudit = $db->prepare(
            'UPDATE request_deletion_audit
             SET restored_at = NOW(), restored_by_name = :admin_name
             WHERE id = :id AND restored_at IS NULL AND purged_at IS NULL'
        );
        $restoreAudit->execute([
            'admin_name' => mb_substr(trim($adminName), 0, 150),
            'id' => (int) $request['deletion_audit_id'],
        ]);
        if ($restoreAudit->rowCount() !== 1) {
            throw new RuntimeException('The archive history could not be updated for this restoration.');
        }

        $restoreRequest = $db->prepare(
            'UPDATE requests
             SET deleted_at = NULL, deletion_audit_id = NULL
             WHERE id = :id AND deletion_audit_id = :audit_id AND deleted_at IS NOT NULL'
        );
        $restoreRequest->execute([
            'id' => $requestId,
            'audit_id' => (int) $request['deletion_audit_id'],
        ]);
        if ($restoreRequest->rowCount() !== 1) {
            throw new RuntimeException('The request could not be restored to the active list.');
        }

        $historyInsert = $db->prepare(
            'INSERT INTO request_history
                (request_id, old_status, new_status, changed_by, remarks)
             VALUES (:request_id, :old_status, :new_status, :changed_by, :remarks)'
        );
        $historyInsert->execute([
            'request_id' => $requestId,
            'old_status' => $request['status'],
            'new_status' => $request['status'],
            'changed_by' => $adminId,
            'remarks' => 'Restored from the deleted archive.',
        ]);
        $db->commit();

        return (string) $request['tracking_no'];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function document_requires_academic_history(array $document): bool
{
    return in_array((string) $document['name'], [
        'Transcript of Records (TOR)',
        'Honorable Dismissal',
    ], true);
}

function validated_academic_record_fields(array $input): array
{
    $purposeOptions = [
        'Evaluation',
        'Employment',
        'Scholarship',
        'Board Examination',
        'Transfer Out',
        'Further Studies',
        'Personal Records',
    ];
    $purpose = trim((string) ($input['request_purpose'] ?? ''));
    $otherPurpose = trim((string) ($input['purpose_other'] ?? ''));
    $purposeWasOthers = $purpose === 'Others';

    if ($purposeWasOthers) {
        foreach ($purposeOptions as $purposeOption) {
            if (mb_strtolower($otherPurpose) === mb_strtolower($purposeOption)) {
                $purpose = $purposeOption;
                $otherPurpose = '';
                break;
            }
        }
        if ($purpose === 'Others') {
            $purpose = $otherPurpose;
        }
    }

    $details = [
        'address_line' => trim((string) ($input['address_line'] ?? '')),
        'province' => trim((string) ($input['province'] ?? '')),
        'city_municipality' => trim((string) ($input['city_municipality'] ?? '')),
        'barangay' => trim((string) ($input['barangay'] ?? '')),
        'birthdate' => trim((string) ($input['birthdate'] ?? '')),
        'gender' => trim((string) ($input['gender'] ?? '')),
        'mobile_number' => trim((string) ($input['mobile_number'] ?? '')),
        'elementary_school' => trim((string) ($input['elementary_school'] ?? '')),
        'elementary_year' => trim((string) ($input['elementary_year'] ?? '')),
        'junior_high_school' => trim((string) ($input['junior_high_school'] ?? '')),
        'junior_high_year' => trim((string) ($input['junior_high_year'] ?? '')),
        'senior_high_school' => trim((string) ($input['senior_high_school'] ?? '')),
        'senior_high_year' => trim((string) ($input['senior_high_year'] ?? '')),
        'request_purpose' => $purpose,
    ];
    $validYears = recent_graduation_years();
    $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $details['birthdate']);
    $validBirthdate = $birthdate !== false
        && $birthdate->format('Y-m-d') === $details['birthdate']
        && $birthdate <= new DateTimeImmutable('today');
    $validPurpose = in_array($purpose, $purposeOptions, true)
        || ($purposeWasOthers && $purpose !== '' && strlen($purpose) <= 80);

    if ($details['address_line'] === '' || strlen($details['address_line']) > 255
        || $details['province'] === '' || strlen($details['province']) > 120
        || $details['city_municipality'] === '' || strlen($details['city_municipality']) > 120
        || $details['barangay'] === '' || strlen($details['barangay']) > 120
        || !$validBirthdate
        || !in_array($details['gender'], ['Female', 'Male', 'Prefer not to say'], true)
        || preg_match('/^[0-9+().\-\s]{7,30}$/D', $details['mobile_number']) !== 1
        || $details['elementary_school'] === '' || strlen($details['elementary_school']) > 180
        || !in_array($details['elementary_year'], $validYears, true)
        || $details['junior_high_school'] === '' || strlen($details['junior_high_school']) > 180
        || !in_array($details['junior_high_year'], $validYears, true)
        || $details['senior_high_school'] === '' || strlen($details['senior_high_school']) > 180
        || !in_array($details['senior_high_year'], $validYears, true)
        || !$validPurpose
        || strlen($otherPurpose) > 80) {
        throw new InvalidArgumentException(
            'Complete the academic-record form with valid details and choose graduation years from the available options.'
        );
    }

    return $details;
}

function create_student_request(PDO $db, int $studentId, array $document): string
{
    return create_document_request($db, $studentId, $document);
}

function create_guest_request(PDO $db, array $guest, array $document): string
{
    return create_document_request($db, null, $document, $guest);
}

function find_request_registry_profile(PDO $db, ?int $studentId, ?array $guest): ?int
{
    if ($studentId !== null) {
        $profileQuery = $db->prepare(
            'SELECT s.id
             FROM users u
             JOIN student_registry s ON s.student_number = u.student_no
             WHERE u.id = :user_id AND u.role = \'student\'
             LIMIT 1'
        );
        $profileQuery->execute(['user_id' => $studentId]);
        $profileId = $profileQuery->fetchColumn();

        return $profileId === false ? null : (int) $profileId;
    }

    if ($guest === null) {
        return null;
    }
    $studentNumber = trim((string) ($guest['student_number'] ?? ''));
    if ($studentNumber !== '') {
        $profileQuery = $db->prepare(
            'SELECT id FROM student_registry
             WHERE LOWER(student_number) = LOWER(:student_number)
             LIMIT 1'
        );
        $profileQuery->execute(['student_number' => $studentNumber]);
        $profileId = $profileQuery->fetchColumn();
        if ($profileId !== false) {
            return (int) $profileId;
        }
    }

    $email = strtolower(trim((string) ($guest['email'] ?? '')));
    if ($email === '') {
        return null;
    }
    $profileQuery = $db->prepare(
        'SELECT id FROM student_registry
         WHERE LOWER(email) = LOWER(:email)
         ORDER BY id
         LIMIT 2'
    );
    $profileQuery->execute(['email' => $email]);
    $profileIds = $profileQuery->fetchAll(PDO::FETCH_COLUMN);

    return count($profileIds) === 1 ? (int) $profileIds[0] : null;
}

function create_document_request(PDO $db, ?int $studentId, array $document, ?array $guest = null): string
{
    $releaseMethods = array_filter(array_map('trim', explode(',', (string) $document['release_methods'])));
    $releaseMethod = in_array('Hardcopy', $releaseMethods, true)
        ? 'Hardcopy'
        : (in_array('Softcopy', $releaseMethods, true) ? 'Softcopy' : null);

    if ($releaseMethod === null) {
        throw new RuntimeException('This document has no supported release method configured.');
    }

    $db->beginTransaction();
    try {
        $hasRegistryProfileLink = $db->query(
            "SHOW COLUMNS FROM requests LIKE 'student_registry_id'"
        )->fetchColumn() !== false;
        $registryProfileId = $hasRegistryProfileLink
            ? find_request_registry_profile($db, $studentId, $guest)
            : null;
        $trackingNumber = '';
        $requestId = 0;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $trackingNumber = 'REQ-' . strtoupper(bin2hex(random_bytes(12)));
            try {
                if ($hasRegistryProfileLink) {
                    $insertRequest = $db->prepare(
                        'INSERT INTO requests
                            (tracking_no, submission_token, student_id, student_registry_id, purpose, release_method)
                         VALUES (:tracking_no, :submission_token, :student_id, :student_registry_id, :purpose, :release_method)'
                    );
                    $requestParameters = [
                        'tracking_no' => $trackingNumber,
                        'submission_token' => bin2hex(random_bytes(32)),
                        'student_id' => $studentId,
                        'student_registry_id' => $registryProfileId,
                        'purpose' => (string) $document['name'],
                        'release_method' => $releaseMethod,
                    ];
                } else {
                    $insertRequest = $db->prepare(
                        'INSERT INTO requests (tracking_no, submission_token, student_id, purpose, release_method)
                         VALUES (:tracking_no, :submission_token, :student_id, :purpose, :release_method)'
                    );
                    $requestParameters = [
                        'tracking_no' => $trackingNumber,
                        'submission_token' => bin2hex(random_bytes(32)),
                        'student_id' => $studentId,
                        'purpose' => (string) $document['name'],
                        'release_method' => $releaseMethod,
                    ];
                }
                $insertRequest->execute($requestParameters);
                $requestId = (int) $db->lastInsertId();
                break;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000' || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        if ($requestId === 0) {
            throw new RuntimeException('A unique request reference could not be created.');
        }

        $insertItem = $db->prepare(
            'INSERT INTO request_items (request_id, document_id, quantity, purpose)
             VALUES (:request_id, :document_id, 1, :purpose)'
        );
        $insertItem->execute([
            'request_id' => $requestId,
            'document_id' => (int) $document['id'],
            'purpose' => (string) $document['name'],
        ]);

        if ($guest !== null) {
            if (array_key_exists('request_purpose', $guest)) {
                $insertGuest = $db->prepare(
                    'INSERT INTO guest_request_details
                        (request_id, requester_status, student_number, first_name, middle_name, last_name,
                         course, year_level, academic_year, email, address_line, province, city_municipality,
                         barangay, birthdate, gender, mobile_number, elementary_school, elementary_year,
                         junior_high_school, junior_high_year, senior_high_school, senior_high_year, request_purpose)
                     VALUES (:request_id, :requester_status, :student_number, :first_name, :middle_name, :last_name,
                             :course, :year_level, :academic_year, :email, :address_line, :province, :city_municipality,
                             :barangay, :birthdate, :gender, :mobile_number, :elementary_school, :elementary_year,
                             :junior_high_school, :junior_high_year, :senior_high_school, :senior_high_year, :request_purpose)'
                );
                $insertGuest->execute([
                    'request_id' => $requestId,
                    'requester_status' => (string) $guest['requester_status'],
                    'student_number' => $guest['student_number'] ?? null,
                    'first_name' => $guest['first_name'] ?? null,
                    'middle_name' => $guest['middle_name'] ?? null,
                    'last_name' => $guest['last_name'] ?? null,
                    'course' => (string) $guest['course'],
                    'year_level' => (string) $guest['year_level'],
                    'academic_year' => (string) $guest['academic_year'],
                    'email' => (string) $guest['email'],
                    'address_line' => $guest['address_line'] ?? null,
                    'province' => $guest['province'] ?? null,
                    'city_municipality' => $guest['city_municipality'] ?? null,
                    'barangay' => $guest['barangay'] ?? null,
                    'birthdate' => $guest['birthdate'] ?? null,
                    'gender' => $guest['gender'] ?? null,
                    'mobile_number' => $guest['mobile_number'] ?? null,
                    'elementary_school' => $guest['elementary_school'] ?? null,
                    'elementary_year' => $guest['elementary_year'] ?? null,
                    'junior_high_school' => $guest['junior_high_school'] ?? null,
                    'junior_high_year' => $guest['junior_high_year'] ?? null,
                    'senior_high_school' => $guest['senior_high_school'] ?? null,
                    'senior_high_year' => $guest['senior_high_year'] ?? null,
                    'request_purpose' => $guest['request_purpose'],
                ]);
            } else {
                $insertGuest = $db->prepare(
                    'INSERT INTO guest_request_details
                        (request_id, requester_status, student_number, first_name, last_name,
                         course, year_level, academic_year, email)
                     VALUES (:request_id, :requester_status, :student_number, :first_name, :last_name,
                             :course, :year_level, :academic_year, :email)'
                );
                $insertGuest->execute([
                    'request_id' => $requestId,
                    'requester_status' => (string) $guest['requester_status'],
                    'student_number' => $guest['student_number'] ?? null,
                    'first_name' => (string) $guest['first_name'],
                    'last_name' => (string) $guest['last_name'],
                    'course' => (string) $guest['course'],
                    'year_level' => (string) $guest['year_level'],
                    'academic_year' => (string) $guest['academic_year'],
                    'email' => (string) $guest['email'],
                ]);
            }
        }

        $insertHistory = $db->prepare(
            "INSERT INTO request_history (request_id, old_status, new_status, changed_by, remarks)
             VALUES (:request_id, NULL, 'SUBMITTED', NULL, 'Student submitted request.')"
        );
        $insertHistory->execute(['request_id' => $requestId]);

        $db->commit();
        return $trackingNumber;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function complete_student_request(PDO $db, int $studentId, array $document, ?array $academicRecordDetails = null): array
{
    $student = find_request_student_by_id($db, $studentId);
    if (!$student) {
        throw new RuntimeException('The student account is no longer available.');
    }

    $trackingNumber = $academicRecordDetails === null
        ? create_student_request($db, $studentId, $document)
        : create_document_request($db, $studentId, $document, $academicRecordDetails);
    $emailSent = true;
    try {
        send_registrar_template($db, 'request_submitted', (string) $student['email'], [
            'document_name' => (string) $document['name'],
            'tracking_number' => $trackingNumber,
            'status' => 'Pending',
            'tracking_url' => registrar_email_tracking_url($trackingNumber),
        ]);
    } catch (RuntimeException $exception) {
        error_log('Request confirmation email could not be sent for ' . $trackingNumber . ': ' . $exception->getMessage());
        $emailSent = false;
    }

    return [
        'tracking_number' => $trackingNumber,
        'document_name' => (string) $document['name'],
        'email' => (string) $student['email'],
        'email_sent' => $emailSent,
    ];
}

function complete_guest_request(PDO $db, array $guest, array $document): array
{
    $trackingNumber = create_guest_request($db, $guest, $document);
    $emailSent = true;
    try {
        send_registrar_template($db, 'request_submitted', (string) $guest['email'], [
            'document_name' => (string) $document['name'],
            'tracking_number' => $trackingNumber,
            'status' => 'Pending',
            'tracking_url' => registrar_email_tracking_url($trackingNumber),
        ]);
    } catch (RuntimeException $exception) {
        error_log('Request confirmation email could not be sent for ' . $trackingNumber . ': ' . $exception->getMessage());
        $emailSent = false;
    }

    return [
        'tracking_number' => $trackingNumber,
        'document_name' => (string) $document['name'],
        'email' => (string) $guest['email'],
        'email_sent' => $emailSent,
    ];
}

function complete_imported_student_request(
    PDO $db,
    array $student,
    array $document,
    ?array $academicRecordDetails = null
): array
{
    $guest = [
        'requester_status' => 'currently_enrolled',
        'student_number' => (string) $student['student_no'],
        'first_name' => (string) $student['first_name'],
        'last_name' => (string) $student['surname'],
        'course' => (string) $student['course'],
        'year_level' => 'Year ' . (int) $student['year_level'],
        'academic_year' => $student['start_year'] . '-' . $student['end_year'],
        'email' => (string) $student['email'],
    ];
    if ($academicRecordDetails !== null) {
        $guest = array_replace($guest, $academicRecordDetails);
    }

    return complete_guest_request($db, $guest, $document);
}
