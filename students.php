<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/audit_helpers.php';
require_once __DIR__ . '/student_roster.php';
start_app_session();
require_personnel();

$db = db();
$isAdmin = ($_SESSION['personnel_role'] ?? '') === 'admin';
$existingTables = array_flip($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
$requiredTables = [
    'student_registry',
    'student_import_batches',
    'student_enrollments',
    'student_roster_memberships',
    'student_roster_corrections',
    'student_import_rows',
    'student_profile_audit',
];
$missingSchema = array_values(array_diff($requiredTables, array_keys($existingTables)));
$requiredColumns = [
    'student_registry' => [
        'suffix',
        'classification',
        'last_attended_academic_term_id',
    ],
    'student_import_batches' => [
        'course_id',
        'source',
        'original_filename',
        'skipped_rows',
        'failed_rows',
        'total_rows',
        'import_status',
    ],
    'student_enrollments' => [
        'year_level',
        'imported_student_number',
        'imported_first_name',
        'imported_middle_name',
        'imported_surname',
        'imported_email',
        'enrollment_status',
    ],
    'student_import_rows' => [
        'source_data',
    ],
];
foreach ($requiredColumns as $table => $columns) {
    if (!isset($existingTables[$table])) {
        continue;
    }
    $existingColumns = $db->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN);
    $missingSchema = array_merge($missingSchema, array_map(
        static fn (string $column): string => $table . '.' . $column,
        array_diff($columns, $existingColumns)
    ));
}
if ($missingSchema !== []) {
    $rosterMigrationItems = [
        'student_roster_memberships',
        'student_roster_corrections',
        'student_enrollments.year_level',
        'student_enrollments.imported_student_number',
        'student_enrollments.imported_first_name',
        'student_enrollments.imported_middle_name',
        'student_enrollments.imported_surname',
        'student_enrollments.imported_email',
    ];
    $studentManagementMigrationItems = [
        'student_import_rows',
        'student_registry.suffix',
        'student_registry.classification',
        'student_registry.last_attended_academic_term_id',
        'student_import_batches.source',
        'student_import_batches.original_filename',
        'student_import_batches.skipped_rows',
        'student_import_batches.failed_rows',
    ];
    $periodImportMigrationItems = [
        'student_profile_audit',
        'student_import_batches.total_rows',
        'student_import_batches.import_status',
        'student_enrollments.enrollment_status',
    ];
    $importSourceDataMigrationItems = ['student_import_rows.source_data'];
    $requiresRosterMigration = array_intersect($missingSchema, $rosterMigrationItems) !== [];
    $hasStudentManagementPrerequisites = isset(
        $existingTables['student_registry'],
        $existingTables['student_import_batches'],
        $existingTables['student_enrollments'],
        $existingTables['academic_terms']
    );
    $requiresStudentManagementMigration = $hasStudentManagementPrerequisites
        && array_intersect($missingSchema, $studentManagementMigrationItems) !== [];
    $requiresPeriodImportMigration = $hasStudentManagementPrerequisites
        && array_intersect($missingSchema, $periodImportMigrationItems) !== [];
    $requiresImportSourceDataMigration = $hasStudentManagementPrerequisites
        && array_intersect($missingSchema, $importSourceDataMigrationItems) !== [];
    $requiresEarlierMigration = array_intersect($missingSchema, [
        'student_import_batches.course_id',
    ]) !== [];
    $knownMigrationItems = array_merge(
        $rosterMigrationItems,
        $studentManagementMigrationItems,
        $periodImportMigrationItems,
        $importSourceDataMigrationItems,
        ['student_import_batches.course_id']
    );
    $missingPrerequisites = array_diff($missingSchema, $knownMigrationItems);
    $databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    http_response_code(503);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Student roster database update required</title>
        <link rel="stylesheet" href="assets/css/login.css">
    </head>
    <body>
        <main class="portal-shell">
            <section class="portal-panel" aria-labelledby="roster-schema-title">
                <p class="eyebrow">REGISTRAR PORTAL</p>
                <h1 id="roster-schema-title">Database update required</h1>
                <p class="notice" role="alert">
                    Student-management schema is missing required items in
                    <code><?= htmlspecialchars($databaseName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>.
                    Back up the database before applying only the migration steps listed below.
                </p>
                <?php if ($requiresEarlierMigration): ?>
                    <p>For older databases without course-scoped imports, first run
                        <code>database/migrate_course_scoped_student_imports.sql</code>.</p>
                <?php endif; ?>
                <?php if ($requiresRosterMigration): ?>
                    <p>Run <code>database/migrate_student_course_rosters.sql</code> to add the missing roster tables and enrollment-history fields.</p>
                <?php endif; ?>
                <?php if ($requiresStudentManagementMigration): ?>
                    <p>Run <code>database/migrate_student_management.sql</code> after
                        <code>database/migrate_student_course_rosters.sql</code> has been applied to add profile metadata and import auditing.</p>
                <?php endif; ?>
                <?php if ($requiresPeriodImportMigration): ?>
                    <p>Run <code>database/migrate_student_period_imports.sql</code> after
                        <code>database/migrate_student_management.sql</code> to enable selected-period imports and profile audit history.</p>
                <?php endif; ?>
                <?php if ($requiresImportSourceDataMigration): ?>
                    <p>Run <code>database/migrate_student_import_row_source_data.sql</code> after
                        <code>database/migrate_student_management.sql</code> to retain submitted values for each audited CSV row.</p>
                <?php endif; ?>
                <?php if ($missingPrerequisites !== []): ?>
                    <p>Some required base student-management tables are missing. See
                        <code>SETUP.md</code> for the correct migration sequence before applying later migrations.</p>
                <?php endif; ?>
                <p>Missing schema items: <?= htmlspecialchars(implode(', ', $missingSchema), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

ensure_current_academic_periods($db);
$activeTerm = active_academic_term($db);
$academicTermOptions = $db->query(
    'SELECT t.id, t.is_current, ay.start_year, ay.end_year, t.semester
     FROM academic_terms t
     JOIN academic_years ay ON ay.id = t.academic_year_id
     ORDER BY ay.start_year DESC, t.semester DESC'
)->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$success = $_SESSION['students_success'] ?? '';
unset($_SESSION['students_success']);
$error = '';
$showImportWizard = false;
$showUploadForm = false;
$selectedCourseId = '';
$selectedTermId = '';
$reopenManualStudentForm = false;
$reopenStudentEditId = null;
$studentEditFormValues = [];
$reopenRosterRemoval = false;
$rosterRemovalValues = ['membership_id' => '', 'student_name' => ''];
$reopenRosterMove = false;
$rosterMoveValues = [
    'membership_id' => '',
    'student_name' => '',
    'target_course_id' => '',
    'target_year_level' => '',
];
$manualStudentFormValues = [
    'student_number' => '',
    'first_name' => '',
    'middle_name' => '',
    'surname' => '',
    'suffix' => '',
    'email' => '',
    'course_id' => '',
    'year_level' => '',
    'term_id' => (string) ($activeTerm['id'] ?? ''),
];
if ($isAdmin && ($_GET['import_action'] ?? '') === 'start_import' && $academicTermOptions !== []) {
    $showImportWizard = true;
    $selectedTermId = (string) ($activeTerm['id'] ?? $academicTermOptions[0]['id']);
}

$courseRows = $db->query(
    "SELECT c.id, CONCAT(c.acronym, ' - ', c.name) AS name
     FROM school_courses c
     JOIN school_levels l ON l.id = c.level_id
     WHERE c.is_active = 1 AND l.name = 'COLLEGE'
     ORDER BY c.sort_order, c.name"
)->fetchAll();
$courseNamesById = [];
foreach ($courseRows as $courseRow) {
    $courseNamesById[(int) $courseRow['id']] = (string) $courseRow['name'];
}

$yearLevelRows = $db->query(
    'SELECT y.course_id, y.year_number, y.label
     FROM school_year_levels y
     JOIN school_courses c ON c.id = y.course_id AND c.is_active = 1
     JOIN school_levels l ON l.id = c.level_id AND l.name = \'COLLEGE\'
     WHERE y.is_active = 1
     ORDER BY c.sort_order, c.name, y.year_number'
)->fetchAll();
$courseYearLevels = [];
foreach ($yearLevelRows as $yearLevelRow) {
    $courseId = (int) $yearLevelRow['course_id'];
    $yearLevel = (int) $yearLevelRow['year_number'];
    $label = (string) $yearLevelRow['label'];
    $courseYearLevels[$courseId][$yearLevel] = $label;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        http_response_code(403);
        exit('Student records are read-only for registrar staff.');
    }

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $action = is_string($_POST['import_action'] ?? null)
        ? $_POST['import_action']
        : (is_string($_POST['action'] ?? null) ? $_POST['action'] : '');
    $selectedCourseId = is_string($_POST['course_id'] ?? null) ? $_POST['course_id'] : '';
    $selectedTermId = is_string($_POST['term_id'] ?? null) ? $_POST['term_id'] : '';
    $showImportWizard = in_array(
        $action,
        ['start_import', 'continue_import', 'preview_import', 'confirm_import'],
        true
    );
    $showUploadForm = in_array($action, ['preview_import', 'confirm_import'], true);

    if ($action === 'start_import') {
        $selectedTermId = is_string($_POST['term_id'] ?? null) ? $_POST['term_id'] : '';
    } elseif ($action === 'continue_import') {
        $courseId = filter_var($selectedCourseId, FILTER_VALIDATE_INT);
        $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
        $termExists = false;
        foreach ($academicTermOptions as $termOption) {
            if (is_int($termId) && (int) $termOption['id'] === $termId) {
                $termExists = true;
                break;
            }
        }
        if (!$termExists || !is_int($courseId) || !isset($courseNamesById[$courseId])) {
            $showUploadForm = false;
            $error = 'Choose a valid course and academic period.';
        } else {
            $selectedTermId = (string) $termId;
            $showUploadForm = true;
        }
    } elseif ($action === 'add_student') {
        $reopenManualStudentForm = true;
        if (!$isAdmin) {
            http_response_code(403);
            exit('Only administrators can register students.');
        }

        $studentNumber = is_string($_POST['student_number'] ?? null) ? trim($_POST['student_number']) : '';
        $firstName = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
        $middleName = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
        $surname = is_string($_POST['surname'] ?? null) ? trim($_POST['surname']) : '';
        $suffix = is_string($_POST['suffix'] ?? null) ? trim($_POST['suffix']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
        $yearLevel = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT);
        $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
        $manualStudentFormValues = [
            'student_number' => $studentNumber,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'surname' => $surname,
            'suffix' => $suffix,
            'email' => $email,
            'course_id' => is_int($courseId) ? (string) $courseId : '',
            'year_level' => is_int($yearLevel) ? (string) $yearLevel : '',
            'term_id' => is_int($termId) ? (string) $termId : '',
        ];

        $selectedTermExists = false;
        foreach ($academicTermOptions as $termOption) {
            if (is_int($termId) && (int) $termOption['id'] === $termId) {
                $selectedTermExists = true;
                break;
            }
        }
        if (!$selectedTermExists) {
            $error = 'Choose a valid academic period.';
        } elseif ($studentNumber === '') {
            $error = 'Student ID is required for currently enrolled students.';
        } elseif ($studentNumber !== '' && (student_csv_text_length($studentNumber) > 64
            || preg_match('/[\x00-\x1F\x7F]/', $studentNumber) === 1)) {
            $error = 'Enter a valid Student ID (maximum 64 characters).';
        } elseif ($firstName === '' || student_csv_text_length($firstName) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $firstName) === 1) {
            $error = 'Enter a valid first name (maximum 100 characters).';
        } elseif (student_csv_text_length($middleName) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $middleName) === 1) {
            $error = 'Middle name must be no more than 100 characters.';
        } elseif ($surname === '' || student_csv_text_length($surname) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $surname) === 1) {
            $error = 'Enter a valid last name (maximum 100 characters).';
        } elseif (student_csv_text_length($suffix) > 20 || preg_match('/[\x00-\x1F\x7F]/', $suffix) === 1) {
            $error = 'Suffix must be no more than 20 characters.';
        } elseif (student_csv_text_length($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!is_int($courseId) || !isset($courseNamesById[$courseId])
            || !is_int($yearLevel) || !isset($courseYearLevels[$courseId][$yearLevel])) {
            $error = 'Choose an active course and year level.';
        } else {
            try {
                $usesUserAccounts = $db->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
                $importerColumn = $usesUserAccounts ? 'imported_by_user_id' : 'imported_by_personnel_id';
                $registration = register_student_manually(
                    $db,
                    $termId,
                    $courseId,
                    [
                        'student_number' => $studentNumber,
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'surname' => $surname,
                        'suffix' => $suffix,
                        'email' => $email,
                        'year_level' => $yearLevel,
                    ],
                    (int) $_SESSION['personnel_id'],
                    $importerColumn,
                    (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username'])
                );
                $_SESSION['students_success'] = $registration['existing_profile']
                    ? 'Existing student profile matched and enrolled for the selected period. Existing personal details were preserved.'
                    : 'Student profile and enrollment registered for the selected period.';
                header('Location: students.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That student already has an enrollment for the selected academic period.';
                } else {
                    error_log('Manual student registration database error: ' . $exception->getMessage());
                    $error = 'The student could not be registered. Check the database and try again.';
                }
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    } elseif (in_array($action, ['preview_import', 'confirm_import'], true)) {
        $courseId = filter_var($selectedCourseId, FILTER_VALIDATE_INT);
        $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
        $upload = $_FILES['csv_file'] ?? null;
        $errorMessage = '';
        $checkStatus = 422;

        if (!is_int($courseId) || !isset($courseNamesById[$courseId]) || !is_int($termId) || $termId < 1) {
            $errorMessage = 'Choose a valid course and academic period.';
            $showUploadForm = false;
        } elseif (!is_array($upload) || !isset($upload['error'], $upload['tmp_name'], $upload['name'])
            || !is_int($upload['error']) || !is_string($upload['tmp_name']) || !is_string($upload['name'])) {
            $errorMessage = 'Choose a CSV file to import.';
        } elseif ($upload['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = match ($upload['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The CSV file is too large.',
                UPLOAD_ERR_NO_FILE => 'Choose a CSV file to import.',
                default => 'The CSV upload failed. Please try again.',
            };
        } elseif (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'csv'
            || !is_uploaded_file($upload['tmp_name'])
            || filesize($upload['tmp_name']) > 5 * 1024 * 1024) {
            $errorMessage = 'Upload a valid CSV file no larger than 5 MB.';
        } else {
            try {
                $rows = parse_student_import_csv($upload['tmp_name']);
                if ($action === 'preview_import') {
                    $preview = preview_student_import(
                        $db,
                        $termId,
                        $courseId,
                        $rows
                    );
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'ok' => true,
                        'preview' => $preview,
                        'fingerprint' => hash_file('sha256', $upload['tmp_name']),
                    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    exit;
                } else {
                    $fingerprint = is_string($_POST['preview_fingerprint'] ?? null)
                        ? $_POST['preview_fingerprint']
                        : '';
                    if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)
                        || !hash_equals($fingerprint, (string) hash_file('sha256', $upload['tmp_name']))) {
                        throw new InvalidArgumentException('The selected CSV changed after preview. Preview it again.');
                    }
                    $usesUserAccounts = $db->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
                    $importerColumn = $usesUserAccounts ? 'imported_by_user_id' : 'imported_by_personnel_id';
                    $result = commit_student_import(
                        $db,
                        $termId,
                        $courseId,
                        $rows,
                        is_array($_POST['approved_rows'] ?? null) ? $_POST['approved_rows'] : [],
                        (int) $_SESSION['personnel_id'],
                        $importerColumn,
                        basename($upload['name']),
                        (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username'])
                    );
                    $activeTermAfterImport = active_academic_term($db);
                    $_SESSION['students_success'] = $result['imported_rows'] . ' enrollment(s) imported for '
                        . $courseNamesById[$courseId] . '. '
                        . $result['skipped_rows'] . ' row(s) skipped and '
                        . $result['failed_rows'] . ' row(s) failed.'
                        . ((int) ($activeTermAfterImport['id'] ?? 0) === $termId
                            ? ''
                            : ' This period is inactive; its roster is staged until the period is activated.');
                    header('Location: students.php');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Student course roster import database error: ' . $exception->getMessage());
                $errorMessage = 'The import could not be saved. Check the database and try again.';
                $checkStatus = 500;
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $errorMessage = $exception->getMessage();
            }
        }

        if ($action === 'preview_import') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($errorMessage === '' ? 200 : $checkStatus);
            echo json_encode(
                $errorMessage === ''
                    ? ['ok' => false, 'message' => 'The CSV preview could not be created.']
                    : ['ok' => false, 'message' => $errorMessage],
                JSON_INVALID_UTF8_SUBSTITUTE
            );
            exit;
        }

        $error = $errorMessage;
    } elseif ($action === 'edit_student') {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
        $studentNumber = is_string($_POST['student_number'] ?? null) ? trim($_POST['student_number']) : '';
        $firstName = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
        $middleName = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
        $surname = is_string($_POST['surname'] ?? null) ? trim($_POST['surname']) : '';
        $suffix = is_string($_POST['suffix'] ?? null) ? trim($_POST['suffix']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $classification = is_string($_POST['classification'] ?? null)
            ? $_POST['classification']
            : 'not_currently_enrolled';
        $reopenStudentEditId = is_int($studentId) && $studentId > 0 ? $studentId : null;
        $studentEditFormValues = [
            'student_id' => is_int($studentId) ? (string) $studentId : '',
            'student_number' => $studentNumber,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'surname' => $surname,
            'suffix' => $suffix,
            'email' => $email,
            'classification' => $classification,
        ];

        if (!is_int($studentId) || $studentId < 1) {
            $error = 'Choose a valid student profile.';
        } elseif ($studentNumber !== '' && (student_csv_text_length($studentNumber) > 64
            || preg_match('/[\x00-\x1F\x7F]/', $studentNumber) === 1)) {
            $error = 'Student ID must be no more than 64 characters.';
        } elseif ($firstName === '' || student_csv_text_length($firstName) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $firstName) === 1) {
            $error = 'Enter a valid first name (maximum 100 characters).';
        } elseif (student_csv_text_length($middleName) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $middleName) === 1) {
            $error = 'Middle name must be no more than 100 characters.';
        } elseif ($surname === '' || student_csv_text_length($surname) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $surname) === 1) {
            $error = 'Enter a valid surname (maximum 100 characters).';
        } elseif (student_csv_text_length($suffix) > 20 || preg_match('/[\x00-\x1F\x7F]/', $suffix) === 1) {
            $error = 'Suffix must be no more than 20 characters.';
        } elseif (student_csv_text_length($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!in_array($classification, ['not_currently_enrolled', 'transferred_out', 'dropped_out', 'alumni'], true)) {
            $error = 'Choose a valid student classification.';
        } else {
            try {
                $changedByName = (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']);
                $actorId = (int) $_SESSION['personnel_id'];
                with_student_roster_lock($db, static function () use (
                    $db,
                    $studentId,
                    $studentNumber,
                    $firstName,
                    $middleName,
                    $surname,
                    $suffix,
                    $email,
                    $classification,
                    $changedByName,
                    $actorId
                ): void {
                    $db->beginTransaction();
                    try {
                        $activeStudent = $db->prepare('SELECT * FROM student_registry WHERE id = :id FOR UPDATE');
                        $activeStudent->execute(['id' => $studentId]);
                        $existingProfile = $activeStudent->fetch();
                        if (!$existingProfile) {
                            throw new InvalidArgumentException('That student profile could not be found.');
                        }
                        $submittedProfileValues = [
                            'student_number' => $studentNumber !== '' ? $studentNumber : null,
                            'first_name' => $firstName,
                            'middle_name' => $middleName !== '' ? $middleName : null,
                            'surname' => $surname,
                            'suffix' => $suffix !== '' ? $suffix : null,
                            'email' => $email,
                            'classification' => $classification,
                        ];
                        $hasProfileChanges = false;
                        foreach ($submittedProfileValues as $field => $newValue) {
                            if ((string) ($existingProfile[$field] ?? '') !== (string) ($newValue ?? '')) {
                                $hasProfileChanges = true;
                                break;
                            }
                        }
                        if (!$hasProfileChanges) {
                            throw new InvalidArgumentException('No changes were made. Cancel editing without saving.');
                        }
                        if ($studentNumber === '') {
                            $currentEnrollment = $db->prepare(
                                'SELECT 1
                                 FROM student_roster_memberships m
                                 JOIN academic_terms t ON t.id = m.academic_term_id AND t.is_current = 1
                                 WHERE m.student_id = :student_id AND m.is_active = 1
                                 LIMIT 1'
                            );
                            $currentEnrollment->execute(['student_id' => $studentId]);
                            if ($currentEnrollment->fetchColumn() !== false) {
                                throw new InvalidArgumentException('Student ID is required while the student has an active enrollment.');
                            }
                        }
                        $updateStudent = $db->prepare(
                            'UPDATE student_registry
                             SET student_number = :student_number, first_name = :first_name,
                                 middle_name = :middle_name, surname = :surname, suffix = :suffix,
                                 email = :email, classification = :classification
                             WHERE id = :id'
                        );
                        $updateStudent->execute([
                            'student_number' => $studentNumber !== '' ? $studentNumber : null,
                            'first_name' => $firstName,
                            'middle_name' => $middleName !== '' ? $middleName : null,
                            'surname' => $surname,
                            'suffix' => $suffix !== '' ? $suffix : null,
                            'email' => $email,
                            'classification' => $classification,
                            'id' => $studentId,
                        ]);
                        $updatedValues = [
                            'student_number' => $studentNumber !== '' ? $studentNumber : null,
                            'first_name' => $firstName,
                            'middle_name' => $middleName !== '' ? $middleName : null,
                            'surname' => $surname,
                            'suffix' => $suffix !== '' ? $suffix : null,
                            'email' => $email,
                            'classification' => $classification,
                        ];
                        $changes = [];
                        foreach ($updatedValues as $field => $newValue) {
                            $oldValue = $existingProfile[$field];
                            if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
                                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
                            }
                        }
                        if ($changes !== []) {
                            $auditInsert = $db->prepare(
                                'INSERT INTO student_profile_audit
                                    (student_id, action, source, changed_by_name, changes_json)
                                 VALUES (:student_id, \'updated\', \'manual\', :changed_by_name, :changes_json)'
                            );
                            $auditInsert->execute([
                                'student_id' => $studentId,
                                'changed_by_name' => substr($changedByName, 0, 150),
                                'changes_json' => student_profile_audit_json($changes),
                            ]);
                            record_audit_event(
                                $db,
                                $actorId,
                                $changedByName,
                                'updated',
                                'student_profile',
                                (string) $studentId,
                                'Updated student profile details.',
                                ['changed_fields' => array_keys($changes)]
                            );
                        }
                        $db->commit();
                    } catch (Throwable $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $exception;
                    }
                });
                $_SESSION['students_success'] = 'Student profile updated. Original import records were preserved.';
                header('Location: students.php');
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That Student ID is already assigned to another student.';
                } else {
                    error_log('Student profile update database error: ' . $exception->getMessage());
                    $error = 'The student profile could not be updated. Check the database and try again.';
                }
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    } elseif (in_array($action, ['delete_student', 'move_student'], true)) {
        $membershipId = filter_var($_POST['membership_id'] ?? null, FILTER_VALIDATE_INT);
        $password = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $studentName = is_string($_POST['student_name'] ?? null) ? trim($_POST['student_name']) : '';
        if ($action === 'delete_student') {
            $reopenRosterRemoval = true;
            $rosterRemovalValues = [
                'membership_id' => is_int($membershipId) ? (string) $membershipId : '',
                'student_name' => $studentName,
            ];
        } else {
            $reopenRosterMove = true;
            $rosterMoveValues = [
                'membership_id' => is_int($membershipId) ? (string) $membershipId : '',
                'student_name' => $studentName,
                'target_course_id' => is_string($_POST['target_course_id'] ?? null)
                    ? $_POST['target_course_id']
                    : '',
                'target_year_level' => is_string($_POST['target_year_level'] ?? null)
                    ? $_POST['target_year_level']
                    : '',
            ];
        }
        $targetCourseId = null;
        $targetYearLevel = null;
        if ($action === 'move_student') {
            $targetCourseId = filter_var($_POST['target_course_id'] ?? null, FILTER_VALIDATE_INT);
            $targetYearLevel = filter_var($_POST['target_year_level'] ?? null, FILTER_VALIDATE_INT);
        }

        if (!is_int($membershipId) || $membershipId < 1) {
            $error = 'Choose an active roster entry.';
        } elseif ($action === 'move_student'
            && (!is_int($targetCourseId) || !is_int($targetYearLevel)
                || !isset($courseYearLevels[$targetCourseId][$targetYearLevel]))) {
            $error = 'Choose an active course and year level.';
        } elseif ($action === 'delete_student' && $password === '') {
            $error = 'Enter your current password to confirm this roster correction.';
        } else {
            try {
                if ($action === 'delete_student' && !verify_personnel_password($db, $password)) {
                    throw new InvalidArgumentException('Your current password is incorrect.');
                }
                $correction = correct_active_roster(
                    $db,
                    $membershipId,
                    $action === 'delete_student' ? 'delete' : 'move',
                    $targetCourseId,
                    $targetYearLevel,
                    (string) ($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']),
                    (int) $_SESSION['personnel_id']
                );
                $_SESSION['students_success'] = $correction['action'] === 'delete'
                    ? 'Student ' . $correction['student_number'] . ' was removed from the active roster. The profile and original import were preserved.'
                    : 'Student ' . $correction['student_number'] . ' was moved. The correction was recorded and the original import was preserved.';
                header('Location: students.php');
                exit;
            } catch (PDOException $exception) {
                error_log('Student roster correction database error: ' . $exception->getMessage());
                $error = 'The roster correction could not be saved. Check the database and try again.';
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid student roster action.');
    }
}

$studentSearch = trim(is_string($_GET['student_q'] ?? null) ? $_GET['student_q'] : '');
$studentFilterError = '';
$studentFilterCourseId = '';
$studentFilterYearLevel = '';
$latestEnrollmentSql = "
    SELECT ranked_enrollments.*
    FROM (
        SELECT e.student_id, e.course_id, c.name AS course_name, e.year_level,
               m.id AS membership_id,
               ay.start_year, ay.end_year, t.semester,
               CASE WHEN t.is_current = 1 AND b.is_current = 1 AND m.is_active = 1
                    THEN 1 ELSE 0 END AS is_current_enrollment,
               ROW_NUMBER() OVER (
                   PARTITION BY e.student_id
                   ORDER BY CASE WHEN t.is_current = 1 AND b.is_current = 1 AND m.is_active = 1
                                 THEN 1 ELSE 0 END DESC,
                            ay.start_year DESC, t.semester DESC,
                            b.imported_at DESC, e.id DESC
               ) AS enrollment_rank
        FROM student_enrollments e
        JOIN student_import_batches b ON b.id = e.import_batch_id
        JOIN academic_terms t ON t.id = b.academic_term_id
        JOIN academic_years ay ON ay.id = t.academic_year_id
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN student_roster_memberships m
               ON m.enrollment_id = e.id AND m.is_active = 1
    ) ranked_enrollments
    WHERE ranked_enrollments.enrollment_rank = 1
";
$studentFilterOptions = $db->query(
    "SELECT sc.id AS course_id, CONCAT(sc.acronym, ' - ', sc.name) AS course_name,
            yl.year_number
     FROM school_courses sc
     JOIN school_levels sl ON sl.id = sc.level_id AND sl.name = 'COLLEGE'
     JOIN school_year_levels yl ON yl.course_id = sc.id
     ORDER BY sc.sort_order, sc.name, yl.year_number"
)->fetchAll();
$studentCourseNamesById = [];
$studentYearsByCourse = [];
foreach ($studentFilterOptions as $studentFilterOption) {
    $courseId = (string) $studentFilterOption['course_id'];
    $studentCourseNamesById[$courseId] = (string) $studentFilterOption['course_name'];
    $yearLevel = (int) $studentFilterOption['year_number'];
    $studentYearsByCourse[$courseId][] = [
        'value' => $yearLevel,
        'label' => student_year_level_label($yearLevel),
    ];
}
$requestedFilterCourseId = is_string($_GET['filter_course_id'] ?? null)
    ? $_GET['filter_course_id']
    : '';
$requestedFilterYearLevel = is_string($_GET['filter_year_level'] ?? null)
    ? $_GET['filter_year_level']
    : '';
if ($requestedFilterCourseId !== '' || $requestedFilterYearLevel !== '') {
    $validCourseId = filter_var($requestedFilterCourseId, FILTER_VALIDATE_INT);
    $validYearLevel = filter_var($requestedFilterYearLevel, FILTER_VALIDATE_INT);
    if (!is_int($validCourseId) || !is_int($validYearLevel)
        || !isset($studentYearsByCourse[(string) $validCourseId])
        || !in_array(
            $validYearLevel,
            array_column($studentYearsByCourse[(string) $validCourseId] ?? [], 'value'),
            true
        )) {
        $studentFilterError = 'Choose a course and year level from the available student records.';
    } else {
        $studentFilterCourseId = (string) $validCourseId;
        $studentFilterYearLevel = (string) $validYearLevel;
    }
}
$studentConditions = ['latest.is_current_enrollment = 1'];
$studentParameters = [];
if ($studentSearch !== '') {
    $searchConditions = [];
    foreach (['student_number', 'first_name', 'middle_name', 'surname'] as $index => $column) {
        $parameter = 'student_search_' . $index;
        $searchConditions[] = "s.{$column} LIKE :{$parameter}";
        $studentParameters[$parameter] = '%' . $studentSearch . '%';
    }
    $studentConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
}
$studentConditions = array_merge($studentConditions, [
    ...($studentFilterError === '' && $studentFilterCourseId !== ''
        ? ['latest.course_name = :filter_course_name', 'latest.year_level = :filter_year_level']
        : []),
]);
if ($studentFilterCourseId !== '') {
    $studentParameters['filter_course_name'] = $studentCourseNamesById[$studentFilterCourseId];
    $studentParameters['filter_year_level'] = (int) $studentFilterYearLevel;
}
$studentWhereSql = $studentConditions === [] ? '' : ' WHERE ' . implode(' AND ', $studentConditions);
$studentListQuery = $db->prepare(
    "SELECT s.id, s.student_number, s.first_name, s.middle_name, s.surname, s.suffix,
            s.email, s.classification,
            latest.course_id, latest.course_name, latest.year_level, latest.start_year, latest.end_year,
            latest.semester, latest.membership_id,
            CASE WHEN latest.is_current_enrollment = 1
                 THEN 'currently_enrolled' ELSE s.classification END AS effective_classification
     FROM student_registry s
     LEFT JOIN ({$latestEnrollmentSql}) latest ON latest.student_id = s.id"
        . $studentWhereSql
        . ' ORDER BY s.surname, s.first_name, s.middle_name, s.student_number'
);
$studentListQuery->execute($studentParameters);
$studentRecords = $studentListQuery->fetchAll();

$personnelName = $escape($_SESSION['personnel_name'] ?? $_SESSION['personnel_username']);
$classificationLabels = [
    'currently_enrolled' => 'Currently enrolled',
    'not_currently_enrolled' => 'Not currently enrolled',
    'transferred_out' => 'Transferred out',
    'dropped_out' => 'Dropped out',
    'alumni' => 'Alumni',
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Students</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">REGISTRAR PORTAL</p>
                <h1>Students</h1>
                <p class="intro">Signed in as <?= $personnelName ?> · <?= $escape($_SESSION['personnel_role']) ?></p>
            </div>
            <?php if ($isAdmin && $academicTermOptions !== []): ?>
                <div class="student-management-actions">
                    <button class="secondary-button" type="button" id="open-manual-student">
                        Add student
                    </button>
                    <a class="submit-button import-submit" href="students.php?import_action=start_import">
                        Import student records
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($success !== ''): ?>
            <p class="notice notice-success toast-notification" role="status"><?= $escape($success) ?></p>
        <?php endif; ?>
        <?php if ($error !== '' && !$showImportWizard): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>
        <?php if ($studentFilterError !== ''): ?>
            <p class="notice" role="alert"><?= $escape($studentFilterError) ?></p>
        <?php endif; ?>

        <section class="portal-panel student-list-panel" aria-labelledby="student-list-title">
            <div class="student-roster-heading">
                <div>
                    <h2 id="student-list-title">Student list</h2>
                    <p class="panel-intro">
                        Active period:
                        <?= $activeTerm === null
                            ? 'None — no academic period is currently active.'
                            : $escape($activeTerm['start_year'] . '-' . $activeTerm['end_year'] . ' · '
                                . ((int) $activeTerm['semester'] === 1 ? '1st' : '2nd') . ' semester') ?>
                    </p>
                </div>
                <span class="student-roster-count" id="student-list-count" aria-live="polite">
                    <?= number_format(count($studentRecords)) ?> students
                </span>
            </div>
            <form class="student-list-controls" method="get" action="students.php">
                <label class="field" for="student-search">
                    <span>Search students</span>
                    <input id="student-search" name="student_q" type="search"
                           value="<?= $escape($studentSearch) ?>"
                           placeholder="Name or Student ID"
                           aria-label="Search by first name, middle name, last name, or Student ID">
                </label>
                <?php if ($studentFilterCourseId !== ''): ?>
                    <input type="hidden" name="filter_course_id" value="<?= $escape($studentFilterCourseId) ?>">
                    <input type="hidden" name="filter_year_level" value="<?= $escape($studentFilterYearLevel) ?>">
                <?php endif; ?>
                <button class="secondary-button" type="button" id="open-student-filter">Filter by course and year</button>
                <?php if ($studentFilterCourseId !== ''): ?>
                    <a class="student-filter-clear" href="students.php<?= $studentSearch !== ''
                        ? '?student_q=' . rawurlencode($studentSearch)
                        : '' ?>">Clear filter</a>
                <?php endif; ?>
            </form>

            <?php if ($studentRecords === []): ?>
                <p class="empty-state">
                    <?= $studentSearch === '' && $studentFilterCourseId === ''
                        ? ($activeTerm === null
                            ? 'No academic period is active, so no student roster information is shown.'
                            : 'No students are enrolled in the active academic period.')
                        : 'No students match the selected search and filters.' ?>
                </p>
            <?php else: ?>
                <div class="student-list-grid">
                    <?php foreach ($studentRecords as $studentRecord): ?>
                        <?php
                        $studentFullName = trim(implode(' ', array_filter([
                            (string) $studentRecord['first_name'],
                            (string) ($studentRecord['middle_name'] ?? ''),
                            (string) $studentRecord['surname'],
                            (string) ($studentRecord['suffix'] ?? ''),
                        ], static fn (string $part): bool => $part !== '')));
                        $studentAcademicPeriod = $studentRecord['start_year'] !== null
                            ? $studentRecord['start_year'] . '-' . $studentRecord['end_year'] . ' · '
                                . ((int) $studentRecord['semester'] === 1 ? '1st' : '2nd') . ' semester'
                            : 'Not recorded';
                        $studentRecordCourseId = $studentRecord['course_name'] !== null
                            ? array_search($studentRecord['course_name'], $studentCourseNamesById, true)
                            : false;
                        $studentTargetSelection = is_int($studentRecordCourseId)
                            && $studentRecord['year_level'] !== null
                            ? $studentRecordCourseId . ':' . (int) $studentRecord['year_level']
                            : '';
                        ?>
                        <button class="document-card student-document-card" type="button"
                                data-student-details
                                data-student-record-id="<?= (int) $studentRecord['id'] ?>"
                                data-student-membership-id="<?= (int) ($studentRecord['membership_id'] ?? 0) ?>"
                                data-student-search="<?= $escape(implode(' ', array_filter([
                                    (string) ($studentRecord['student_number'] ?? ''),
                                    (string) $studentRecord['first_name'],
                                    (string) ($studentRecord['middle_name'] ?? ''),
                                    (string) $studentRecord['surname'],
                                ], static fn (string $part): bool => $part !== ''))) ?>"
                                data-student-id="<?= $escape($studentRecord['student_number'] ?? 'No Student ID') ?>"
                                data-student-name="<?= $escape($studentFullName) ?>"
                                data-student-first-name="<?= $escape($studentRecord['first_name']) ?>"
                                data-student-middle-name="<?= $escape($studentRecord['middle_name'] ?? '') ?>"
                                data-student-surname="<?= $escape($studentRecord['surname']) ?>"
                                data-student-suffix="<?= $escape($studentRecord['suffix'] ?? '') ?>"
                                data-student-course="<?= $escape($studentRecord['course_name'] ?? 'Course not recorded') ?>"
                                data-student-target="<?= $escape($studentTargetSelection) ?>"
                                data-student-year="<?= $studentRecord['year_level'] !== null
                                    ? $escape(student_year_level_label((int) $studentRecord['year_level']))
                                    : 'Year not recorded' ?>"
                                data-student-email="<?= $escape($studentRecord['email']) ?>"
                                data-student-classification="<?= $escape($studentRecord['classification']) ?>"
                                data-student-status="<?= $escape($classificationLabels[$studentRecord['effective_classification']] ?? 'Unknown') ?>"
                                data-student-period="<?= $escape($studentAcademicPeriod) ?>"
                                aria-haspopup="dialog">
                            <span class="document-card-heading">
                                <span class="student-document-card-name"><?= $escape($studentFullName) ?></span>
                                <span class="document-card-processing"><?= $escape($studentRecord['student_number'] ?? 'No Student ID') ?></span>
                            </span>
                            <span class="student-document-card-course"><?= $escape($studentRecord['course_name'] ?? 'Course not recorded') ?></span>
                            <span class="student-document-card-year">
                                <?= $studentRecord['year_level'] !== null
                                    ? $escape(student_year_level_label((int) $studentRecord['year_level']))
                                    : 'Year not recorded' ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="empty-state" id="student-search-empty" hidden>No students match that name or Student ID.</p>
            <?php endif; ?>
        </section>

        <dialog class="workflow-dialog management-dialog student-details-dialog" id="student-details-dialog"
                aria-labelledby="student-details-title">
            <div class="workflow-dialog-heading">
                <div><p class="eyebrow">STUDENT DETAILS</p><h2 id="student-details-title"></h2></div>
                <button class="workflow-dialog-close" type="button" id="close-student-details"
                        aria-label="Close dialog">×</button>
            </div>
            <dl class="student-list-details">
                <div><dt>Student ID</dt><dd data-student-detail="id"></dd></div>
                <div><dt>Course</dt><dd data-student-detail="course"></dd></div>
                <div><dt>Year level</dt><dd data-student-detail="year"></dd></div>
                <div><dt>Email</dt><dd data-student-detail="email"></dd></div>
                <div><dt>Enrollment status</dt><dd data-student-detail="status"></dd></div>
                <div><dt>Academic period</dt><dd data-student-detail="period"></dd></div>
            </dl>
            <?php if ($isAdmin): ?>
                <div class="course-details-actions student-details-actions">
                    <button class="table-action-button" type="button" data-student-edit>Edit details</button>
                    <button class="secondary-button" type="button" data-student-move>Change course/year</button>
                    <button class="danger-button" type="button" data-student-remove>Remove from active roster</button>
                </div>
            <?php endif; ?>
        </dialog>

        <?php if ($isAdmin): ?>
        <dialog class="workflow-dialog management-dialog student-edit-dialog" id="student-edit-dialog"
                aria-labelledby="student-edit-title">
            <div class="workflow-dialog-heading">
                <div><p class="eyebrow">STUDENT PROFILE</p><h2 id="student-edit-title">Edit student details</h2></div>
                <button class="workflow-dialog-close" type="button" data-student-edit-cancel
                        aria-label="Close dialog">×</button>
            </div>
            <?php if ($reopenStudentEditId !== null && $error !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
            <form class="import-form" method="post" action="students.php" data-student-edit-form>
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" name="student_id" value="<?= $escape($studentEditFormValues['student_id'] ?? '') ?>">
                <div class="alumni-name-fields">
                    <div class="field">
                        <label for="edit-student-number">Student ID</label>
                        <input id="edit-student-number" name="student_number" type="text" maxlength="64"
                               value="<?= $escape($studentEditFormValues['student_number'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="edit-student-email">Email address</label>
                        <input id="edit-student-email" name="email" type="email" maxlength="254"
                               value="<?= $escape($studentEditFormValues['email'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="edit-student-first-name">First name</label>
                        <input id="edit-student-first-name" name="first_name" type="text" maxlength="100"
                               value="<?= $escape($studentEditFormValues['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="edit-student-middle-name">Middle name</label>
                        <input id="edit-student-middle-name" name="middle_name" type="text" maxlength="100"
                               value="<?= $escape($studentEditFormValues['middle_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="edit-student-surname">Last name</label>
                        <input id="edit-student-surname" name="surname" type="text" maxlength="100"
                               value="<?= $escape($studentEditFormValues['surname'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="edit-student-suffix">Suffix</label>
                        <input id="edit-student-suffix" name="suffix" type="text" maxlength="20"
                               value="<?= $escape($studentEditFormValues['suffix'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="edit-student-classification">Enrollment status</label>
                        <select id="edit-student-classification" name="classification" required>
                            <?php foreach (['not_currently_enrolled', 'transferred_out', 'dropped_out', 'alumni'] as $classification): ?>
                                <option value="<?= $escape($classification) ?>"
                                    <?= ($studentEditFormValues['classification'] ?? '') === $classification ? 'selected' : '' ?>>
                                    <?= $escape($classificationLabels[$classification]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="student-edit-actions">
                    <button class="secondary-button" type="button" data-student-edit-cancel>Cancel</button>
                    <button class="table-action-button" type="submit" disabled>Save changes</button>
                </div>
            </form>
        </dialog>

        <dialog class="workflow-dialog management-dialog student-correction-dialog" id="student-move-dialog"
                aria-labelledby="student-move-title">
            <div class="workflow-dialog-heading">
                <div><p class="eyebrow">ROSTER CORRECTION</p><h2 id="student-move-title">Change course/year</h2></div>
                <button class="workflow-dialog-close" type="button" data-student-move-cancel
                        aria-label="Close dialog">×</button>
            </div>
            <?php if ($reopenRosterMove && $error !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
            <p class="intro">The correction is recorded while the original import history is preserved.</p>
            <form class="import-form" method="post" action="students.php" data-student-move-form>
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="move_student">
                <input type="hidden" name="membership_id" value="<?= $escape($rosterMoveValues['membership_id']) ?>">
                <input type="hidden" name="student_name" value="<?= $escape($rosterMoveValues['student_name']) ?>">
                <label class="field" for="edit-student-course">
                    <span>Course</span>
                    <select id="edit-student-course" name="target_course_id"
                            data-selected-value="<?= $escape($rosterMoveValues['target_course_id']) ?>" required>
                        <option value="">Select course</option>
                        <?php foreach ($courseNamesById as $availableCourseId => $courseName): ?>
                            <option value="<?= (int) $availableCourseId ?>"
                                <?= $rosterMoveValues['target_course_id'] === (string) $availableCourseId
                                    ? 'selected'
                                    : '' ?>>
                                <?= $escape($courseName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" for="edit-student-year-level">
                    <span>Year level</span>
                    <select id="edit-student-year-level" name="target_year_level"
                            data-selected-value="<?= $escape($rosterMoveValues['target_year_level']) ?>"
                            required disabled>
                        <option value="">Select year level</option>
                    </select>
                </label>
                <div class="student-edit-actions">
                    <button class="secondary-button" type="button" data-student-move-cancel>Cancel</button>
                    <button class="table-action-button" type="submit">Save correction</button>
                </div>
            </form>
        </dialog>

        <dialog class="workflow-dialog management-dialog delete-confirmation-dialog" id="student-remove-dialog"
                aria-labelledby="student-remove-title">
            <div class="workflow-dialog-heading">
                <div><p class="eyebrow">CONFIRM REMOVAL</p><h2 id="student-remove-title">Remove student?</h2></div>
                <button class="workflow-dialog-close" type="button" data-student-remove-cancel
                        aria-label="Close dialog">×</button>
            </div>
            <?php if ($reopenRosterRemoval && $error !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
            <p class="delete-confirmation-message" data-student-remove-message>
                The student will be removed from the active roster. Their profile and import history will be preserved.
            </p>
            <form method="post" action="students.php" data-student-remove-form data-navigation-guard-ignore>
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_student">
                <input type="hidden" name="membership_id" value="<?= $escape($rosterRemovalValues['membership_id']) ?>">
                <input type="hidden" name="student_name" value="<?= $escape($rosterRemovalValues['student_name']) ?>">
                <label class="field" for="remove-student-password">
                    <span>Current password</span>
                    <input id="remove-student-password" name="current_password" type="password"
                           autocomplete="current-password" required>
                </label>
                <div class="student-edit-actions">
                    <button class="secondary-button" type="button" data-student-remove-cancel>Cancel</button>
                    <button class="danger-button" type="submit">Confirm removal</button>
                </div>
            </form>
        </dialog>
        <?php endif; ?>

        <dialog class="workflow-dialog management-dialog student-filter-dialog" id="student-filter-dialog"
                aria-labelledby="student-filter-title">
            <div class="workflow-dialog-heading">
                <div>
                    <p class="eyebrow">STUDENT FILTER</p>
                    <h2 id="student-filter-title">Choose course and year level</h2>
                    <p class="workflow-step-label">First choose a course, then choose a year level.</p>
                </div>
                <button class="workflow-dialog-close" type="button" id="close-student-filter"
                        aria-label="Close filter">×</button>
            </div>
            <form class="import-form" method="get" action="students.php">
                <input type="hidden" name="student_q" value="<?= $escape($studentSearch) ?>">
                <label class="field" for="student-filter-course">
                    <span>Course</span>
                    <select id="student-filter-course" name="filter_course_id" required>
                        <option value="">Select course</option>
                        <?php $renderedFilterCourses = []; ?>
                        <?php foreach ($studentFilterOptions as $studentFilterOption): ?>
                            <?php $availableFilterCourseId = (string) $studentFilterOption['course_id']; ?>
                            <?php if (!isset($renderedFilterCourses[$availableFilterCourseId])): ?>
                                <option value="<?= $escape($availableFilterCourseId) ?>"
                                    <?= $studentFilterCourseId === $availableFilterCourseId ? 'selected' : '' ?>>
                                    <?= $escape($studentFilterOption['course_name']) ?>
                                </option>
                                <?php $renderedFilterCourses[$availableFilterCourseId] = true; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" for="student-filter-year">
                    <span>Year level</span>
                    <select id="student-filter-year" name="filter_year_level" required
                            <?= $studentFilterCourseId === '' ? 'disabled' : '' ?>>
                        <option value="">Select year level</option>
                        <?php foreach ($studentYearsByCourse as $courseId => $yearLevels): ?>
                            <?php foreach ($yearLevels as $yearOption): ?>
                                <option value="<?= (int) $yearOption['value'] ?>"
                                        data-course-id="<?= $escape($courseId) ?>"
                                    <?= $studentFilterCourseId === $courseId
                                        && $studentFilterYearLevel === (string) $yearOption['value'] ? 'selected' : '' ?>>
                                    <?= $escape($yearOption['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="student-filter-actions">
                    <button class="secondary-button" type="button" id="cancel-student-filter">Cancel</button>
                    <button class="submit-button" type="submit">Show students</button>
                </div>
            </form>
        </dialog>

        <?php if ($isAdmin && $academicTermOptions !== []): ?>
            <dialog class="workflow-dialog" id="manual-student-dialog" aria-labelledby="manual-student-title">
                <div class="workflow-dialog-heading">
                    <div>
                        <p class="eyebrow">MANUAL REGISTRATION</p>
                        <h2 id="manual-student-title">Add student</h2>
                        <p class="workflow-step-label">Choose an academic period, course, and year level below.</p>
                    </div>
                    <button class="workflow-dialog-close" type="button" id="close-manual-student"
                            aria-label="Close student registration">×</button>
                </div>
                <?php if ($reopenManualStudentForm && $error !== ''): ?>
                    <p class="notice" role="alert"><?= $escape($error) ?></p>
                <?php endif; ?>
                <form class="import-form" method="post" action="students.php" data-loading-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="import_action" value="add_student">
                    <h3>Personal information</h3>
                    <div class="alumni-name-fields">
                        <div class="field">
                            <label for="manual-student-number">Student ID</label>
                            <input id="manual-student-number" name="student_number" type="text" maxlength="64"
                                   value="<?= $escape($manualStudentFormValues['student_number']) ?>">
                        </div>
                        <div class="field">
                            <label for="manual-student-email">Email address</label>
                            <input id="manual-student-email" name="email" type="email" maxlength="254"
                                   value="<?= $escape($manualStudentFormValues['email']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="manual-student-first-name">First name</label>
                            <input id="manual-student-first-name" name="first_name" type="text" maxlength="100"
                                   value="<?= $escape($manualStudentFormValues['first_name']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="manual-student-middle-name">Middle name</label>
                            <input id="manual-student-middle-name" name="middle_name" type="text" maxlength="100"
                                   value="<?= $escape($manualStudentFormValues['middle_name']) ?>">
                        </div>
                        <div class="field">
                            <label for="manual-student-surname">Last name</label>
                            <input id="manual-student-surname" name="surname" type="text" maxlength="100"
                                   value="<?= $escape($manualStudentFormValues['surname']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="manual-student-suffix">Suffix</label>
                            <input id="manual-student-suffix" name="suffix" type="text" maxlength="20"
                                   value="<?= $escape($manualStudentFormValues['suffix']) ?>">
                        </div>
                    </div>
                    <h3>Current academic enrollment</h3>
                    <div class="import-fields">
                        <div class="field">
                            <label for="manual-student-term">Academic period</label>
                            <select id="manual-student-term" name="term_id" required>
                                <option value="">Select academic period</option>
                                <?php foreach ($academicTermOptions as $termOption): ?>
                                    <option value="<?= (int) $termOption['id'] ?>"
                                        <?= $manualStudentFormValues['term_id'] === (string) $termOption['id'] ? 'selected' : '' ?>>
                                        <?= $escape($termOption['start_year'] . '-' . $termOption['end_year']) ?> ·
                                        <?= (int) $termOption['semester'] === 1 ? '1st' : '2nd' ?> semester
                                        <?= (int) $termOption['is_current'] === 1 ? ' · Active' : ' · Inactive' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="manual-student-course">Course</label>
                            <select id="manual-student-course" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courseNamesById as $availableCourseId => $courseName): ?>
                                    <option value="<?= (int) $availableCourseId ?>"
                                        <?= $manualStudentFormValues['course_id'] === (string) $availableCourseId ? 'selected' : '' ?>>
                                        <?= $escape($courseName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="manual-student-year-level">Year level</label>
                            <select id="manual-student-year-level" name="year_level"
                                    data-selected-value="<?= $escape($manualStudentFormValues['year_level']) ?>" required>
                                <option value="">Select year level</option>
                            </select>
                        </div>
                    </div>
                    <button class="submit-button" type="submit" data-loading-button
                            data-loading-text="Adding student…">Add student</button>
                </form>
            </dialog>
        <?php endif; ?>

        <?php if ($isAdmin && $showImportWizard): ?>
            <dialog class="workflow-dialog import-workflow-dialog" id="import-wizard" aria-labelledby="import-wizard-title">
                <div class="workflow-dialog-heading">
                    <div>
                        <p class="eyebrow">STUDENT RECORD IMPORT</p>
                        <h2 id="import-wizard-title">Import student records</h2>
                        <p class="workflow-step-label"><?= $showUploadForm ? 'Step 2 of 2 · Preview and confirm' : 'Step 1 of 2 · Choose academic period and course' ?></p>
                    </div>
                    <button class="workflow-dialog-close" type="button" id="close-import-wizard" aria-label="Close import dialog">×</button>
                </div>
                <?php if ($error !== ''): ?>
                    <p class="notice" role="alert"><?= $escape($error) ?></p>
                <?php endif; ?>
                <?php if (!$showUploadForm): ?>
                    <form class="import-form" method="post" action="students.php">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="import_action" value="continue_import">
                        <div class="field">
                            <label for="import-term">Academic period</label>
                            <select id="import-term" name="term_id" required>
                                <option value="">Select academic period</option>
                                <?php foreach ($academicTermOptions as $termOption): ?>
                                    <option value="<?= (int) $termOption['id'] ?>"
                                        <?= $selectedTermId === (string) $termOption['id'] ? 'selected' : '' ?>>
                                        <?= $escape($termOption['start_year'] . '-' . $termOption['end_year']) ?> ·
                                        <?= (int) $termOption['semester'] === 1 ? '1st' : '2nd' ?> semester
                                        <?= (int) $termOption['is_current'] === 1 ? ' · Active' : ' · Inactive (staged)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="import-course">Course</label>
                            <select id="import-course" name="course_id" required>
                                <option value="">Select course</option>
                                <?php foreach ($courseNamesById as $availableCourseId => $courseName): ?>
                                    <option value="<?= (int) $availableCourseId ?>" <?= $selectedCourseId === (string) $availableCourseId ? 'selected' : '' ?>>
                                        <?= $escape($courseName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a class="template-link template-download-link"
                           href="assets/csv/student-import-template.csv"
                           download="emer-student-import-template.csv"
                           title="Download the student import CSV template">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"></path>
                            </svg>
                            Download student CSV template
                        </a>
                        <p class="csv-help">Fill in the template, then upload it on the next step. Course and academic period are selected here and apply to every student.</p>
                        <p class="csv-help">Existing enrollments in this course and period will be skipped. Only new eligible students will be imported.</p>
                        <button class="submit-button import-submit" type="submit">Continue to CSV <span aria-hidden="true">→</span></button>
                    </form>
                <?php else: ?>
                    <form class="import-form" id="student-import-form" method="post" action="students.php" enctype="multipart/form-data"
                          data-loading-source="#confirm-import" data-loading-text="Saving import…">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="import_action" value="confirm_import">
                        <input type="hidden" name="course_id" value="<?= $escape($selectedCourseId) ?>">
                        <input type="hidden" name="term_id" value="<?= $escape($selectedTermId) ?>">
                        <input type="hidden" name="preview_fingerprint" id="preview-fingerprint" value="">
                        <p class="csv-help">
                            Add records for <strong><?= $escape($courseNamesById[(int) $selectedCourseId] ?? '') ?></strong>
                            in the selected academic period. Existing enrollments are skipped and never replaced.
                        </p>
                        <p class="setup-link"><a href="students.php?import_action=start_import">Choose a different course or period</a></p>
                        <a class="template-link template-download-link"
                           href="assets/csv/student-import-template.csv"
                           download="emer-student-import-template.csv"
                           title="Download the student import CSV template">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"></path>
                            </svg>
                            Download student CSV template
                        </a>
                        <div class="field file-field">
                            <label for="csv_file">Student CSV <span class="hint">(maximum 5 MB)</span></label>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
                        </div>
                        <p class="csv-help">Use the template columns for student ID, name, suffix, email, and year level. Leave optional values blank. The selected course and academic period apply to every row. Use a year level available for that course. Existing enrollments will be skipped; a file with no new eligible rows cannot be imported. Maximum 5,000 rows.</p>
                        <p class="csv-help" id="import-duplicate-warning" role="status" hidden></p>
                        <p class="csv-feedback" id="csv-feedback" role="status" aria-live="polite"></p>
                        <button class="submit-button import-submit" id="preview-import" type="button" disabled>
                            Preview import <span aria-hidden="true">→</span>
                        </button>
                        <section id="import-preview" aria-live="polite" hidden>
                            <h3>Import preview</h3>
                            <p id="import-preview-summary" class="csv-help"></p>
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead><tr><th>Row</th><th>Student ID</th><th>Name</th><th>Outcome</th><th>Details</th></tr></thead>
                                    <tbody id="import-preview-rows"></tbody>
                                </table>
                            </div>
                            <p class="csv-help">Existing profile details are never overwritten by an import. Review and approve allowed profile conflicts individually.</p>
                            <label class="field">
                                <span><input type="checkbox" id="confirm-import-review"> I reviewed this preview and confirm importing eligible rows.</span>
                            </label>
                            <button class="submit-button import-submit" id="confirm-import" type="button" disabled>
                                Confirm and import
                            </button>
                        </section>
                    </form>
                <?php endif; ?>
            </dialog>
        <?php endif; ?>

    </main>
    <script src="assets/js/form-change-feedback.js"></script>
    <script>
        const studentSearchInput = document.getElementById('student-search');
        const studentSearchForm = studentSearchInput?.closest('form');
        const studentCards = Array.from(document.querySelectorAll('[data-student-search]'));
        const studentSearchEmpty = document.getElementById('student-search-empty');
        const studentListCount = document.getElementById('student-list-count');
        if (studentSearchInput && studentSearchForm && studentCards.length > 0) {
            const filterStudentCards = () => {
                const query = studentSearchInput.value.trim().toLocaleLowerCase();
                let visibleCount = 0;
                studentCards.forEach((card) => {
                    const searchText = (card.dataset.studentSearch || '').toLocaleLowerCase();
                    const matches = searchText.includes(query);
                    card.hidden = !matches;
                    visibleCount += matches ? 1 : 0;
                });
                studentSearchEmpty.hidden = visibleCount > 0;
                studentListCount.textContent = `${visibleCount.toLocaleString()} students`;
            };
            studentSearchInput.addEventListener('input', filterStudentCards);
            studentSearchForm.addEventListener('submit', (event) => {
                event.preventDefault();
                filterStudentCards();
            });
            filterStudentCards();
        }

        const studentDetailsDialog = document.getElementById('student-details-dialog');
        let activeStudentCard = null;
        const moveCourseSelect = document.getElementById('edit-student-course');
        const moveYearLevelSelect = document.getElementById('edit-student-year-level');
        const courseYearLevels = <?= json_encode(
            $courseYearLevels,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?>;
        const populateMoveYearLevels = (selectedValue = moveYearLevelSelect?.dataset.selectedValue || '') => {
            if (!moveCourseSelect || !moveYearLevelSelect) {
                return;
            }
            moveYearLevelSelect.replaceChildren(new Option('Select year level', ''));
            Object.entries(courseYearLevels[moveCourseSelect.value] || {}).forEach(([value, label]) => {
                moveYearLevelSelect.add(new Option(label, value));
            });
            moveYearLevelSelect.disabled = !moveCourseSelect.value || moveYearLevelSelect.options.length < 2;
            if (selectedValue) {
                moveYearLevelSelect.value = selectedValue;
                moveYearLevelSelect.dataset.selectedValue = '';
            }
        };
        if (moveCourseSelect && moveYearLevelSelect) {
            populateMoveYearLevels();
            moveCourseSelect.addEventListener('change', () => {
                moveYearLevelSelect.dataset.selectedValue = '';
                populateMoveYearLevels('');
            });
        }

        if (studentDetailsDialog && !document.getElementById('student-edit-dialog')) {
            const studentDetailsTitle = document.getElementById('student-details-title');
            const studentDetailFields = studentDetailsDialog.querySelectorAll('[data-student-detail]');
            const studentDetailClose = document.getElementById('close-student-details');
            document.querySelectorAll('[data-student-details]').forEach((card) => {
                card.addEventListener('click', () => {
                    studentDetailsTitle.textContent = card.dataset.studentName;
                    studentDetailFields.forEach((field) => {
                        const key = field.dataset.studentDetail;
                        const value = key === 'id' ? card.dataset.studentId : card.dataset[`student${key[0].toUpperCase()}${key.slice(1)}`];
                        field.textContent = value || 'Not recorded';
                    });
                    studentDetailsDialog.showModal();
                });
            });
            studentDetailClose.addEventListener('click', () => studentDetailsDialog.close());
            studentDetailsDialog.addEventListener('click', (event) => {
                if (event.target === studentDetailsDialog) {
                    studentDetailsDialog.close();
                }
            });
        }

        if (studentDetailsDialog && document.getElementById('student-edit-dialog')) {
            const studentDetailsTitle = document.getElementById('student-details-title');
            const studentDetailFields = studentDetailsDialog.querySelectorAll('[data-student-detail]');
            const studentDetailClose = document.getElementById('close-student-details');
            const studentEditButton = studentDetailsDialog.querySelector('[data-student-edit]');
            const studentMoveButton = studentDetailsDialog.querySelector('[data-student-move]');
            const studentRemoveButton = studentDetailsDialog.querySelector('[data-student-remove]');
            const studentEditDialog = document.getElementById('student-edit-dialog');
            const studentEditForm = studentEditDialog.querySelector('[data-student-edit-form]');
            const studentEditTitle = document.getElementById('student-edit-title');
            const studentEditSaveButton = studentEditForm.querySelector('button[type="submit"]');
            const studentMoveDialog = document.getElementById('student-move-dialog');
            const studentMoveForm = studentMoveDialog.querySelector('[data-student-move-form]');
            const studentMoveCourse = studentMoveForm.elements.namedItem('target_course_id');
            const studentMoveYear = studentMoveForm.elements.namedItem('target_year_level');
            const studentMoveSaveButton = studentMoveForm.querySelector('button[type="submit"]');
            const studentRemoveDialog = document.getElementById('student-remove-dialog');
            const studentRemoveForm = studentRemoveDialog.querySelector('[data-student-remove-form]');
            const studentRemoveMessage = studentRemoveDialog.querySelector('[data-student-remove-message]');
            const studentProfileDataKeys = {
                student_number: 'studentId',
                first_name: 'studentFirstName',
                middle_name: 'studentMiddleName',
                surname: 'studentSurname',
                suffix: 'studentSuffix',
                email: 'studentEmail',
                classification: 'studentClassification'
            };
            let originalMoveTarget = null;
            const currentMoveTarget = () => studentMoveCourse.value && studentMoveYear.value
                ? `${studentMoveCourse.value}:${studentMoveYear.value}`
                : '';
            const studentEditUpdate = window.AppFormChangeFeedback.watch(
                studentEditForm,
                studentEditSaveButton,
                studentEditForm.querySelector('[data-student-edit-cancel]'),
                () => {
                    if (!activeStudentCard) {
                        return false;
                    }
                    return Object.entries(studentProfileDataKeys).some(([name, dataKey]) => {
                        const originalValue = activeStudentCard.dataset[dataKey] || '';
                        const normalizedOriginal = name === 'student_number' && originalValue === 'No Student ID'
                            ? ''
                            : originalValue;
                        return studentEditForm.elements.namedItem(name).value !== normalizedOriginal;
                    });
                }
            );
            const studentMoveUpdate = window.AppFormChangeFeedback.watch(
                studentMoveForm,
                studentMoveSaveButton,
                studentMoveForm.querySelector('[data-student-move-cancel]'),
                () => originalMoveTarget !== null && currentMoveTarget() !== originalMoveTarget
            );

            const openStudentEdit = (card, useSubmittedValues = false) => {
                activeStudentCard = card;
                studentEditTitle.textContent = `Edit ${card.dataset.studentName}`;
                studentEditForm.elements.namedItem('student_id').value = card.dataset.studentRecordId;
                if (!useSubmittedValues) {
                    const values = {
                        student_number: card.dataset.studentId === 'No Student ID' ? '' : card.dataset.studentId,
                        first_name: card.dataset.studentFirstName,
                        middle_name: card.dataset.studentMiddleName,
                        surname: card.dataset.studentSurname,
                        suffix: card.dataset.studentSuffix,
                        email: card.dataset.studentEmail,
                        classification: card.dataset.studentClassification
                    };
                    Object.entries(values).forEach(([name, value]) => {
                        studentEditForm.elements.namedItem(name).value = value || '';
                    });
                }
                studentEditUpdate(false);
                studentEditDialog.showModal();
                studentEditForm.elements.namedItem('student_number').focus();
            };

            document.querySelectorAll('[data-student-details]').forEach((card) => {
                card.addEventListener('click', () => {
                    activeStudentCard = card;
                    studentDetailsTitle.textContent = card.dataset.studentName;
                    studentDetailFields.forEach((field) => {
                        const key = field.dataset.studentDetail;
                        const value = key === 'id' ? card.dataset.studentId : card.dataset[`student${key[0].toUpperCase()}${key.slice(1)}`];
                        field.textContent = value || 'Not recorded';
                    });
                    const hasActiveMembership = Number(card.dataset.studentMembershipId) > 0;
                    studentMoveButton.hidden = !hasActiveMembership;
                    studentRemoveButton.hidden = !hasActiveMembership;
                    studentDetailsDialog.showModal();
                });
            });
            studentEditButton.addEventListener('click', () => {
                const card = activeStudentCard;
                studentDetailsDialog.close();
                if (card) {
                    openStudentEdit(card);
                }
            });
            studentMoveButton.addEventListener('click', () => {
                if (!activeStudentCard) {
                    return;
                }
                studentMoveForm.elements.namedItem('membership_id').value =
                    activeStudentCard.dataset.studentMembershipId;
                studentMoveForm.elements.namedItem('student_name').value = activeStudentCard.dataset.studentName;
                originalMoveTarget = activeStudentCard.dataset.studentTarget || '';
                const [courseId, yearLevel] = originalMoveTarget.split(':');
                studentMoveCourse.value = courseId || '';
                populateMoveYearLevels(yearLevel || '');
                studentMoveUpdate(false);
                studentDetailsDialog.close();
                studentMoveDialog.showModal();
                studentMoveCourse.focus();
            });
            studentRemoveButton.addEventListener('click', () => {
                if (!activeStudentCard) {
                    return;
                }
                studentRemoveForm.elements.namedItem('membership_id').value =
                    activeStudentCard.dataset.studentMembershipId;
                studentRemoveForm.elements.namedItem('student_name').value = activeStudentCard.dataset.studentName;
                studentRemoveForm.elements.namedItem('current_password').value = '';
                studentRemoveMessage.textContent = `Remove ${activeStudentCard.dataset.studentName} from the active roster? Their profile and import history will be preserved.`;
                studentDetailsDialog.close();
                studentRemoveDialog.showModal();
                studentRemoveForm.elements.namedItem('current_password').focus();
            });
            studentDetailClose.addEventListener('click', () => studentDetailsDialog.close());
            studentDetailsDialog.addEventListener('click', (event) => {
                if (event.target === studentDetailsDialog) {
                    studentDetailsDialog.close();
                }
            });
            studentEditDialog.querySelectorAll('[data-student-edit-cancel]').forEach((button) => {
                button.addEventListener('click', () => studentEditDialog.close());
            });
            studentEditDialog.addEventListener('close', () => {
                studentEditForm.reset();
                activeStudentCard = null;
                studentEditUpdate(false);
            });
            studentMoveDialog.querySelectorAll('[data-student-move-cancel]').forEach((button) => {
                button.addEventListener('click', () => studentMoveDialog.close());
            });
            studentMoveDialog.addEventListener('close', () => {
                studentMoveForm.reset();
                moveYearLevelSelect.dataset.selectedValue = '';
                populateMoveYearLevels('');
                originalMoveTarget = null;
                studentMoveUpdate(false);
            });
            studentRemoveDialog.querySelectorAll('[data-student-remove-cancel]').forEach((button) => {
                button.addEventListener('click', () => studentRemoveDialog.close());
            });
            studentRemoveDialog.addEventListener('close', () => {
                studentRemoveForm.reset();
            });
            [studentEditDialog, studentMoveDialog, studentRemoveDialog].forEach((dialog) => {
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) {
                        dialog.close();
                    }
                });
            });

            const reopenStudentEditId = <?= json_encode($reopenStudentEditId) ?>;
            if (reopenStudentEditId !== null) {
                const card = Array.from(document.querySelectorAll('[data-student-details]'))
                    .find((item) => item.dataset.studentRecordId === String(reopenStudentEditId));
                if (card) {
                    openStudentEdit(card, true);
                }
            }
            if (<?= $reopenRosterMove ? 'true' : 'false' ?>) {
                const reopenMembershipId = <?= json_encode($rosterMoveValues['membership_id']) ?>;
                activeStudentCard = Array.from(document.querySelectorAll('[data-student-details]'))
                    .find((card) => card.dataset.studentMembershipId === reopenMembershipId) || null;
                originalMoveTarget = activeStudentCard?.dataset.studentTarget ?? null;
                studentMoveUpdate(false);
                studentMoveDialog.showModal();
            }
            if (<?= $reopenRosterRemoval ? 'true' : 'false' ?>) {
                studentRemoveMessage.textContent = `Remove ${studentRemoveForm.elements.namedItem('student_name').value} from the active roster? Their profile and import history will be preserved.`;
                studentRemoveDialog.showModal();
            }
        }

        const studentFilterDialog = document.getElementById('student-filter-dialog');
        const studentFilterCourse = document.getElementById('student-filter-course');
        const studentFilterYear = document.getElementById('student-filter-year');
        const studentYearsByCourse = <?= json_encode(
            $studentYearsByCourse,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?>;
        const initialStudentFilterCourse = <?= json_encode($studentFilterCourseId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const initialStudentFilterYear = <?= json_encode($studentFilterYearLevel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        if (studentFilterDialog && studentFilterCourse && studentFilterYear) {
            const populateStudentFilterYears = (courseId, selectedYear = '') => {
                studentFilterYear.replaceChildren(new Option('Select year level', ''));
                (studentYearsByCourse[courseId] || []).forEach((yearOption) => {
                    studentFilterYear.add(new Option(yearOption.label, String(yearOption.value)));
                });
                studentFilterYear.disabled = !courseId || studentFilterYear.options.length < 2;
                studentFilterYear.value = selectedYear;
            };
            const openStudentFilter = document.getElementById('open-student-filter');
            const closeStudentFilter = document.getElementById('close-student-filter');
            const cancelStudentFilter = document.getElementById('cancel-student-filter');
            openStudentFilter.addEventListener('click', () => {
                const courseId = studentFilterCourse.value;
                populateStudentFilterYears(
                    courseId,
                    courseId === initialStudentFilterCourse ? initialStudentFilterYear : ''
                );
                studentFilterDialog.showModal();
                studentFilterCourse.focus();
            });
            studentFilterCourse.addEventListener('change', () => {
                populateStudentFilterYears(studentFilterCourse.value);
            });
            [closeStudentFilter, cancelStudentFilter].forEach((button) => {
                button.addEventListener('click', () => studentFilterDialog.close());
            });
            studentFilterDialog.addEventListener('click', (event) => {
                if (event.target === studentFilterDialog) {
                    studentFilterDialog.close();
                }
            });
        }

        const manualStudentDialog = document.getElementById('manual-student-dialog');
        const manualCourseSelect = document.getElementById('manual-student-course');
        const manualYearLevelSelect = document.getElementById('manual-student-year-level');

        if (manualStudentDialog && manualCourseSelect && manualYearLevelSelect) {
            const populateManualYearLevels = () => {
                const selectedValue = manualYearLevelSelect.dataset.selectedValue;
                manualYearLevelSelect.replaceChildren(new Option('Select year level', ''));
                Object.entries(courseYearLevels[manualCourseSelect.value] || {}).forEach(([value, label]) => {
                    manualYearLevelSelect.add(new Option(label, value));
                });
                if (selectedValue) {
                    manualYearLevelSelect.value = selectedValue;
                    manualYearLevelSelect.dataset.selectedValue = '';
                }
            };
            populateManualYearLevels();
            manualCourseSelect.addEventListener('change', () => {
                manualYearLevelSelect.dataset.selectedValue = '';
                populateManualYearLevels();
            });
            document.getElementById('open-manual-student')?.addEventListener('click', () => {
                manualStudentDialog.showModal();
            });
            document.getElementById('close-manual-student')?.addEventListener('click', () => {
                manualStudentDialog.close();
            });
            manualStudentDialog.addEventListener('click', (event) => {
                if (event.target === manualStudentDialog) {
                    manualStudentDialog.close();
                }
            });
            if (<?= $reopenManualStudentForm ? 'true' : 'false' ?>) {
                manualStudentDialog.showModal();
            }
        }

        const importWizard = document.getElementById('import-wizard');
        const closeImportWizard = document.getElementById('close-import-wizard');
        if (importWizard && closeImportWizard) {
            importWizard.showModal();
            closeImportWizard.addEventListener('click', () => importWizard.close());
            importWizard.addEventListener('click', (event) => {
                if (event.target === importWizard) {
                    importWizard.close();
                }
            });
        }

        const csvInput = document.getElementById('csv_file');
        const csvFeedback = document.getElementById('csv-feedback');
        const previewButton = document.getElementById('preview-import');
        const originalPreviewButtonContent = previewButton?.innerHTML ?? '';
        const confirmButton = document.getElementById('confirm-import');
        const importForm = document.getElementById('student-import-form');
        const previewPanel = document.getElementById('import-preview');
        const previewSummary = document.getElementById('import-preview-summary');
        const previewRows = document.getElementById('import-preview-rows');
        const fingerprintInput = document.getElementById('preview-fingerprint');
        const confirmReview = document.getElementById('confirm-import-review');
        const duplicateWarning = document.getElementById('import-duplicate-warning');
        if (csvInput && csvFeedback && previewButton && importForm && previewPanel
            && previewSummary && previewRows && fingerprintInput && confirmButton && confirmReview
            && duplicateWarning) {
            let previewing = false;
            let readyRows = 0;
            const updateConfirmButton = () => {
                const approvedReviewRows = previewRows.querySelector(
                    'input[name="approved_rows[]"]:checked'
                );
                confirmButton.disabled = !confirmReview.checked || fingerprintInput.value === ''
                    || (readyRows === 0 && !approvedReviewRows);
            };
            const clearPreview = () => {
                fingerprintInput.value = '';
                previewRows.replaceChildren();
                previewPanel.hidden = true;
                confirmReview.checked = false;
                confirmReview.disabled = false;
                duplicateWarning.hidden = true;
                duplicateWarning.textContent = '';
                readyRows = 0;
                confirmButton.disabled = true;
            };

            csvInput.addEventListener('change', () => {
                clearPreview();
                previewButton.disabled = true;
                csvFeedback.className = 'csv-feedback';
                csvFeedback.textContent = '';
                const file = csvInput.files[0];
                if (!file) {
                    return;
                }
                if (!file.name.toLowerCase().endsWith('.csv') || file.size > 5 * 1024 * 1024) {
                    csvFeedback.classList.add('is-error');
                    csvFeedback.textContent = 'Choose a .csv file no larger than 5 MB.';
                    csvInput.value = '';
                    return;
                }
                previewButton.disabled = false;
                csvFeedback.classList.add('is-valid');
                csvFeedback.textContent = 'File selected. Preview it to validate each row before saving.';
            });

            previewButton.addEventListener('click', async () => {
                if (previewing || !importForm.reportValidity()) {
                    return;
                }
                previewing = true;
                previewButton.disabled = true;
                previewButton.classList.add('is-loading');
                previewButton.setAttribute('aria-busy', 'true');
                previewButton.textContent = 'Checking CSV…';
                csvFeedback.className = 'csv-feedback';
                csvFeedback.textContent = 'Checking student profiles, enrollments, and row conflicts…';

                try {
                    const previewData = new FormData(importForm);
                    previewData.set('import_action', 'preview_import');
                    const response = await fetch(importForm.action, {
                        method: 'POST',
                        body: previewData,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' }
                    });
                    const result = await response.json();
                    if (!response.ok || result.ok !== true || !result.preview) {
                        throw new Error(result.message || 'The CSV preview could not be created.');
                    }

                    const summary = result.preview.summary;
                    readyRows = summary.ready;
                    previewSummary.textContent =
                        `${summary.total} row(s): ${summary.ready} ready, ${summary.review} need review, ` +
                        `${summary.skipped} already present or duplicate, ${summary.failed} invalid.`;
                    previewRows.replaceChildren();
                    result.preview.rows.forEach((row) => {
                        const tr = document.createElement('tr');
                        const name = [row.first_name, row.middle_name, row.surname, row.suffix]
                            .filter((part) => String(part || '').trim() !== '')
                            .join(' ');
                        const values = [
                            row.row_number,
                            row.student_number || '—',
                            name || '—',
                            row.processing_status === 'ready' ? 'Ready' : row.processing_status,
                            row.error_message || 'Will be added without overwriting an existing profile.'
                        ];
                        values.forEach((value) => {
                            const td = document.createElement('td');
                            td.textContent = String(value);
                            tr.append(td);
                        });
                        if (row.processing_status === 'review' && row.can_approve) {
                            const details = tr.lastElementChild;
                            const label = document.createElement('label');
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.name = 'approved_rows[]';
                            checkbox.value = String(row.row_number);
                            label.append(checkbox, document.createTextNode(' Approve and keep existing profile details'));
                            details.append(document.createElement('br'), label);
                        }
                        previewRows.append(tr);
                    });
                    const approvableReviewRows = result.preview.rows.filter(
                        (row) => row.processing_status === 'review' && row.can_approve
                    ).length;
                    const hasEligibleRows = readyRows > 0 || approvableReviewRows > 0;
                    if (!hasEligibleRows) {
                        duplicateWarning.textContent =
                            'No new eligible students were found. Remove students already enrolled in this course and period, then choose the updated CSV to import.';
                        duplicateWarning.hidden = false;
                        confirmReview.disabled = true;
                    } else {
                        duplicateWarning.hidden = true;
                        confirmReview.disabled = false;
                    }
                    fingerprintInput.value = result.fingerprint;
                    previewPanel.hidden = false;
                    updateConfirmButton();
                    csvFeedback.classList.add('is-valid');
                    csvFeedback.textContent = 'Preview complete. No student records have been changed yet.';
                } catch (error) {
                    clearPreview();
                    csvFeedback.classList.add('is-error');
                    csvFeedback.textContent = error instanceof Error
                        ? error.message
                        : 'The CSV preview could not be created. Please try again.';
                } finally {
                    previewing = false;
                    previewButton.disabled = false;
                    previewButton.classList.remove('is-loading');
                    previewButton.removeAttribute('aria-busy');
                    previewButton.innerHTML = originalPreviewButtonContent;
                }
            });

            confirmReview.addEventListener('change', () => {
                updateConfirmButton();
            });
            previewRows.addEventListener('change', updateConfirmButton);
            confirmButton.addEventListener('click', () => {
                if (!confirmReview.checked || !fingerprintInput.value
                    || (readyRows === 0
                        && !previewRows.querySelector('input[name="approved_rows[]"]:checked'))) {
                    return;
                }
                if (!importForm.reportValidity()) {
                    return;
                }
                importForm.requestSubmit();
            });
        }
    </script>
    <script src="assets/js/live-search.js"></script>
    <script src="assets/js/form-loading.js" defer></script>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
</body>
</html>
