<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_roster.php';
require_once __DIR__ . '/../request_helpers.php';

$dsn = getenv('STUDENT_ROSTER_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
    fwrite(STDERR, "Set STUDENT_ROSTER_TEST_DSN to an isolated MySQL test database DSN.\n");
    exit(2);
}

$db = new PDO($dsn, getenv('STUDENT_ROSTER_TEST_USER') ?: '', getenv('STUDENT_ROSTER_TEST_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$db->exec(
    "CREATE TEMPORARY TABLE academic_years (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        start_year SMALLINT UNSIGNED NOT NULL,
        end_year SMALLINT UNSIGNED NOT NULL,
        UNIQUE KEY uq_test_year (start_year, end_year)
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE academic_terms (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        academic_year_id BIGINT UNSIGNED NOT NULL,
        semester TINYINT UNSIGNED NOT NULL,
        is_current TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_test_term (academic_year_id, semester)
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE school_levels (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE school_courses (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        level_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        acronym VARCHAR(20) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE school_year_levels (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        course_id BIGINT UNSIGNED NOT NULL,
        year_number TINYINT UNSIGNED NOT NULL,
        label VARCHAR(80) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE courses (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_registry (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(64) NULL UNIQUE,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100) NULL,
        surname VARCHAR(100) NOT NULL,
        suffix VARCHAR(20) NULL,
        email VARCHAR(254) NOT NULL,
        contact_number VARCHAR(40) NULL,
        classification VARCHAR(32) NOT NULL DEFAULT 'not_currently_enrolled',
        graduation_year SMALLINT UNSIGNED NULL,
        last_attended_academic_term_id BIGINT UNSIGNED NULL
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_import_batches (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        academic_term_id BIGINT UNSIGNED NOT NULL,
        course_id BIGINT UNSIGNED NULL,
        source ENUM('csv', 'manual') NOT NULL DEFAULT 'csv',
        original_filename VARCHAR(255) NULL,
        imported_by_personnel_id BIGINT UNSIGNED NOT NULL,
        year_level TINYINT UNSIGNED NOT NULL,
        row_count INT UNSIGNED NOT NULL,
        skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
        failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
        total_rows INT UNSIGNED NOT NULL DEFAULT 0,
        import_status ENUM('completed', 'completed_with_errors', 'failed') NOT NULL DEFAULT 'completed',
        is_current TINYINT(1) NOT NULL DEFAULT 0,
        imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_import_rows (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        batch_id BIGINT UNSIGNED NOT NULL,
        row_number INT UNSIGNED NOT NULL,
        student_identifier VARCHAR(64) NULL,
        processing_status ENUM('imported', 'skipped', 'failed', 'review') NOT NULL,
        source_data LONGTEXT NULL,
        error_message VARCHAR(500) NULL,
        associated_student_id BIGINT UNSIGNED NULL,
        associated_enrollment_id BIGINT UNSIGNED NULL,
        UNIQUE KEY uq_test_import_row (batch_id, row_number)
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_profile_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id BIGINT UNSIGNED NOT NULL,
        import_batch_id BIGINT UNSIGNED NULL,
        action ENUM('created', 'updated') NOT NULL,
        source ENUM('csv', 'manual') NOT NULL,
        changed_by_name VARCHAR(150) NOT NULL,
        changes_json LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_enrollments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id BIGINT UNSIGNED NOT NULL,
        import_batch_id BIGINT UNSIGNED NOT NULL,
        course_id BIGINT UNSIGNED NOT NULL,
        year_level TINYINT UNSIGNED NOT NULL,
        imported_student_number VARCHAR(64) NULL,
        imported_first_name VARCHAR(100) NOT NULL,
        imported_middle_name VARCHAR(100) NULL,
        imported_surname VARCHAR(100) NOT NULL,
        imported_email VARCHAR(254) NOT NULL,
        enrollment_status ENUM(
            'currently_enrolled',
            'not_currently_enrolled',
            'transferred_out',
            'dropped_out',
            'alumni'
        ) NOT NULL DEFAULT 'currently_enrolled',
        UNIQUE KEY uq_test_enrollment (import_batch_id, student_id)
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_roster_memberships (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        import_batch_id BIGINT UNSIGNED NOT NULL,
        enrollment_id BIGINT UNSIGNED NOT NULL UNIQUE,
        academic_term_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        course_id BIGINT UNSIGNED NOT NULL,
        year_level TINYINT UNSIGNED NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE student_roster_corrections (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        membership_id BIGINT UNSIGNED NOT NULL,
        correction_type ENUM('delete', 'move') NOT NULL,
        from_course_id BIGINT UNSIGNED NOT NULL,
        from_year_level TINYINT UNSIGNED NOT NULL,
        to_course_id BIGINT UNSIGNED NULL,
        to_year_level TINYINT UNSIGNED NULL,
        corrected_by_name VARCHAR(150) NOT NULL,
        corrected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$db->exec(
    "CREATE TEMPORARY TABLE audit_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        actor_user_id INT UNSIGNED NULL,
        actor_name VARCHAR(150) NOT NULL,
        action VARCHAR(64) NOT NULL,
        entity_type VARCHAR(64) NOT NULL,
        entity_id VARCHAR(64) NULL,
        summary VARCHAR(500) NOT NULL,
        details_json LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};
$expectInvalid = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (InvalidArgumentException $exception) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

try {
    $db->exec("INSERT INTO school_levels (id, name) VALUES (1, 'COLLEGE')");
    $db->exec(
        "INSERT INTO school_courses (id, level_id, name, acronym) VALUES
            (1, 1, 'Computer Science', 'CS'),
            (2, 1, 'Information Technology', 'IT'),
            (3, 1, 'Business Administration', 'BA')"
    );
    $db->exec(
        "INSERT INTO school_year_levels (course_id, year_number, label) VALUES
            (1, 1, '1st year'), (1, 2, '2nd year'), (2, 1, '1st year'),
            (3, 1, '1st year')"
    );

    ensure_current_academic_periods($db);
    ensure_current_academic_periods($db);
    $currentCalendarYear = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    $automaticPeriods = $db->prepare(
        'SELECT COUNT(*), SUM(t.is_current)
         FROM academic_terms t
         JOIN academic_years ay ON ay.id = t.academic_year_id
         WHERE ay.start_year = :start_year AND ay.end_year = :end_year'
    );
    $automaticPeriods->execute([
        'start_year' => $currentCalendarYear,
        'end_year' => $currentCalendarYear + 1,
    ]);
    $automaticPeriodStatus = $automaticPeriods->fetch(PDO::FETCH_NUM);
    $assert(
        (int) $automaticPeriodStatus[0] === 2 && (int) $automaticPeriodStatus[1] === 0,
        'The current academic year semesters are created automatically once and remain inactive until selected.'
    );

    $term = open_academic_term($db, '2025-2026', 1);
    $assert(
        $term['id'] > 0 && (int) active_academic_term($db)['id'] === $term['id'],
        'A first-ever semester can be opened.'
    );

    $initialRoster = [
        ['student_number' => 'S-100', 'first_name' => 'Ava', 'middle_name' => '', 'surname' => 'Santos', 'email' => 'ava@example.test', 'year_level' => 1],
        ['student_number' => 'S-200', 'first_name' => 'Ben', 'middle_name' => '', 'surname' => 'Reyes', 'email' => 'ben@example.test', 'year_level' => 2],
    ];
    $assert(
        preview_course_roster_import($db, (int) $term['id'], 1, $initialRoster) === [],
        'The first import preview has no active-roster duplicates.'
    );
    $assert(
        import_course_roster($db, (int) $term['id'], 1, $initialRoster, 1, 'imported_by_personnel_id') === 2,
        'The first-ever course roster imports without prior roster data.'
    );
    $assert(
        count(preview_course_roster_import($db, (int) $term['id'], 1, $initialRoster)) === 2,
        'Reimporting the same roster detects its active Student IDs as duplicates.'
    );
    $partialRoster = [
        $initialRoster[0],
        ['student_number' => 'S-300', 'first_name' => 'Cam', 'middle_name' => '', 'surname' => 'Cruz', 'email' => 'cam@example.test', 'year_level' => 2],
    ];
    $assert(
        count(preview_course_roster_import($db, (int) $term['id'], 1, $partialRoster)) === 1,
        'A partial overlap with the active course roster is detected.'
    );
    $expectInvalid(
        static fn () => import_course_roster(
            $db,
            (int) $term['id'],
            1,
            $initialRoster,
            1,
            'imported_by_personnel_id'
        ),
        'An overlapping roster cannot replace the active import without confirmation.'
    );
    $assert(
        (int) $db->query('SELECT COUNT(*) FROM student_import_batches WHERE is_current = 1')->fetchColumn() === 1,
        'Canceling a duplicate roster import leaves the active roster unchanged.'
    );
    $csvPath = tempnam(sys_get_temp_dir(), 'student-roster-');
    if ($csvPath === false) {
        throw new RuntimeException('The CSV parser test file could not be created.');
    }
    try {
        file_put_contents(
            $csvPath,
            "student_id,first_name,middle_name,surname,email,year_level\nS-300,Cam,,Cruz,cam@example.test,2\n"
        );
        $parsed = parse_student_csv($csvPath);
        $assert(count($parsed) === 1 && $parsed[0]['year_level'] === 2, 'CSV imports require and parse a per-row year level.');
        file_put_contents(
            $csvPath,
            "student_id,first_name,middle_name,surname,email\nS-300,Cam,,Cruz,cam@example.test\n"
        );
        $expectInvalid(static fn () => parse_student_csv($csvPath), 'CSV files without year_level are rejected.');
    } finally {
        unlink($csvPath);
    }

    $duplicateInOtherCourse = [[
        'student_number' => 'S-100',
        'first_name' => 'Ava',
        'middle_name' => '',
        'surname' => 'Santos',
        'email' => 'ava@example.test',
        'year_level' => 1,
    ]];
    $expectInvalid(
        static fn () => import_course_roster(
            $db,
            (int) $term['id'],
            2,
            $duplicateInOtherCourse,
            1,
            'imported_by_personnel_id'
        ),
        'A Student ID already active in another course is rejected.'
    );
    $expectInvalid(
        static fn () => import_course_roster(
            $db,
            (int) $term['id'],
            999,
            $duplicateInOtherCourse,
            1,
            'imported_by_personnel_id'
        ),
        'A nonexistent course is rejected.'
    );
    $invalidYearLevel = $duplicateInOtherCourse;
    $invalidYearLevel[0]['year_level'] = 4;
    $expectInvalid(
        static fn () => import_course_roster(
            $db,
            (int) $term['id'],
            1,
            $invalidYearLevel,
            1,
            'imported_by_personnel_id'
        ),
        'A year level not offered by the selected course is rejected.'
    );
    $assert(
        (int) $db->query('SELECT COUNT(*) FROM student_import_batches WHERE is_current = 1')->fetchColumn() === 1,
        'A rejected roster does not replace the previously active course import.'
    );

    $firstBatchId = (int) $db->query('SELECT MIN(id) FROM student_import_batches')->fetchColumn();
    import_course_roster($db, (int) $term['id'], 1, $initialRoster, 1, 'imported_by_personnel_id', true);
    $assert(
        (int) $db->query("SELECT is_current FROM student_import_batches WHERE id = {$firstBatchId}")->fetchColumn() === 0,
        'Replacing a course roster archives its prior import batch.'
    );
    $membershipQuery = $db->prepare(
        'SELECT m.id, s.student_number
         FROM student_roster_memberships m
         JOIN student_registry s ON s.id = m.student_id
         JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         WHERE m.academic_term_id = :term_id'
    );
    $membershipQuery->execute(['term_id' => $term['id']]);
    $memberships = [];
    foreach ($membershipQuery->fetchAll() as $row) {
        $memberships[$row['student_number']] = (int) $row['id'];
    }

    correct_active_roster($db, $memberships['S-100'], 'move', 2, 1, 'Registrar');
    correct_active_roster($db, $memberships['S-200'], 'delete', null, null, 'Registrar');
    $profileCount = (int) $db->query('SELECT COUNT(*) FROM student_registry')->fetchColumn();
    $originalCount = (int) $db->query('SELECT COUNT(*) FROM student_enrollments')->fetchColumn();
    $correctionCount = (int) $db->query('SELECT COUNT(*) FROM student_roster_corrections')->fetchColumn();
    $assert($profileCount === 2 && $originalCount === 4, 'Move and Delete preserve student profiles and original import rows.');
    $assert($correctionCount === 2, 'Move and Delete each create an audit correction.');
    $movedMembership = $db->prepare(
        'SELECT m.is_active, m.year_level, c.name
         FROM student_roster_memberships m
         JOIN courses c ON c.id = m.course_id
         WHERE m.id = :id'
    );
    $movedMembership->execute(['id' => $memberships['S-100']]);
    $movedStudent = $movedMembership->fetch();
    $deletedMembership = $db->prepare(
        'SELECT is_active FROM student_roster_memberships WHERE id = :id'
    );
    $deletedMembership->execute(['id' => $memberships['S-200']]);
    $assert(
        (int) $movedStudent['is_active'] === 1
            && (int) $movedStudent['year_level'] === 1
            && $movedStudent['name'] === 'IT - Information Technology',
        'Move changes the active course/year without changing the imported row.'
    );
    $assert((int) $deletedMembership->fetchColumn() === 0, 'Delete only deactivates the roster membership.');
    $originalSnapshot = $db->query(
        "SELECT imported_student_number, imported_first_name, course_id, year_level
         FROM student_enrollments
         WHERE import_batch_id = (SELECT MAX(id) FROM student_import_batches)
           AND imported_student_number = 'S-100'"
    )->fetch();
    $assert(
        $originalSnapshot['imported_student_number'] === 'S-100'
            && $originalSnapshot['imported_first_name'] === 'Ava'
            && (int) $originalSnapshot['course_id'] !== 0
            && (int) $originalSnapshot['year_level'] === 1,
        'The original imported course, year, and student values remain unchanged after correction.'
    );

    $archivedTermId = (int) $term['id'];
    $archivedBatchId = (int) $db->query('SELECT MAX(id) FROM student_import_batches')->fetchColumn();
    $secondTerm = open_academic_term($db, '2025-2026', 2);
    $archived = $db->prepare(
        'SELECT is_current FROM student_import_batches WHERE id = :id'
    );
    $archived->execute(['id' => $archivedBatchId]);
    $assert(
        (int) $archived->fetchColumn() === 1,
        'Opening a later semester preserves prior import batches for period reactivation.'
    );
    $assert(find_request_student($db, 'S-100') === null, 'An inactive academic period cannot validate a student request.');
    activate_academic_term($db, $archivedTermId);
    $assert(
        find_request_student($db, 'S-100') !== null,
        'Reactivating an archived period makes its saved active roster eligible again.'
    );
    $expectInvalid(
        static fn () => activate_academic_term($db, $archivedTermId),
        'Activating the already active period is rejected as a no-op.'
    );
    activate_academic_term($db, (int) $secondTerm['id']);
    $assert(
        find_request_student($db, 'S-100') === null,
        'Switching periods immediately removes the previous period from request validation.'
    );
    deactivate_academic_term($db, (int) $secondTerm['id']);
    $assert(
        active_academic_term($db) === null && find_request_student($db, 'S-100') === null,
        'Deactivating the only active period leaves no archived roster eligible for requests.'
    );
    activate_academic_term($db, (int) $secondTerm['id']);
    $correctionCountBeforeArchivedAttempt = (int) $db->query('SELECT COUNT(*) FROM student_roster_corrections')->fetchColumn();
    $expectInvalid(
        static fn () => correct_active_roster(
            $db,
            $memberships['S-100'],
            'delete',
            null,
            null,
            'Registrar'
        ),
        'Archived roster entries are read-only.'
    );
    $snapshotAfterArchive = $db->query(
        "SELECT imported_student_number, imported_first_name, course_id, year_level
         FROM student_enrollments
         WHERE import_batch_id = {$archivedBatchId} AND imported_student_number = 'S-100'"
    )->fetch();
    $assert(
        $snapshotAfterArchive === $originalSnapshot
            && (int) $db->query('SELECT COUNT(*) FROM student_roster_corrections')->fetchColumn()
                === $correctionCountBeforeArchivedAttempt
            && $archivedTermId !== (int) active_academic_term($db)['id'],
        'Archived history and its correction log are unchanged by a rejected edit.'
    );

    $currentTermId = (int) active_academic_term($db)['id'];
    $manualStudent = [
        'student_number' => 'S-400',
        'first_name' => 'Dana',
        'middle_name' => '',
        'surname' => 'Lopez',
        'suffix' => 'Jr.',
        'email' => 'dana@example.test',
        'year_level' => 1,
    ];
    $manualRegistration = register_student_manually(
        $db,
        $currentTermId,
        1,
        $manualStudent,
        1,
        'imported_by_personnel_id'
    );
    $assert(!$manualRegistration['existing_profile'], 'Manual registration creates a new student profile.');
    $manualAudit = $db->prepare(
        'SELECT entity_type, entity_id
         FROM audit_events
         WHERE entity_type = :entity_type AND entity_id = :entity_id'
    );
    $manualAudit->execute([
        'entity_type' => 'student_enrollment',
        'entity_id' => (string) $manualRegistration['enrollment_id'],
    ]);
    $assert($manualAudit->fetch() !== false, 'Manual student enrollment writes a general audit event.');
    $manualProfile = $db->prepare(
        'SELECT suffix, contact_number FROM student_registry WHERE id = :id'
    );
    $manualProfile->execute(['id' => $manualRegistration['student_id']]);
    $manualProfileDetails = $manualProfile->fetch();
    $assert(
        $manualProfileDetails['suffix'] === 'Jr.'
            && $manualProfileDetails['contact_number'] === null,
        'Manual registration saves the suffix without collecting a contact number.'
    );
    $existingRegistration = register_student_manually(
        $db,
        $currentTermId,
        2,
        [
            'student_number' => 'S-100',
            'first_name' => 'Different',
            'middle_name' => '',
            'surname' => 'Name',
            'suffix' => '',
            'email' => 'different@example.test',
            'year_level' => 1,
        ],
        1,
        'imported_by_personnel_id'
    );
    $assert($existingRegistration['existing_profile'], 'Manual registration matches an existing student profile.');
    $preservedProfile = $db->query(
        "SELECT first_name, surname, email FROM student_registry WHERE student_number = 'S-100'"
    )->fetch();
    $assert(
        $preservedProfile['first_name'] === 'Ava'
            && $preservedProfile['surname'] === 'Santos'
            && $preservedProfile['email'] === 'ava@example.test',
        'Matching manual registration preserves existing profile details.'
    );
    $expectInvalid(
        static fn () => register_student_manually(
            $db,
            $currentTermId,
            1,
            $manualStudent,
            1,
            'imported_by_personnel_id'
        ),
        'Manual registration rejects a duplicate active semester enrollment.'
    );
    $historicalManual = register_student_manually(
        $db,
        (int) $term['id'],
        3,
        [
            'student_number' => '',
            'first_name' => 'Ari',
            'middle_name' => '',
            'surname' => 'Alumni',
            'suffix' => '',
            'email' => 'ari.alumni@example.test',
            'year_level' => 1,
        ],
        1,
        'imported_by_personnel_id',
        'Registrar',
        'alumni'
    );
    $historicalManualProfile = $db->prepare(
        'SELECT s.student_number, s.classification, b.is_current, m.is_active
         FROM student_registry s
         JOIN student_enrollments e ON e.student_id = s.id
         JOIN student_import_batches b ON b.id = e.import_batch_id
         JOIN student_roster_memberships m ON m.enrollment_id = e.id
         WHERE s.id = :student_id'
    );
    $historicalManualProfile->execute(['student_id' => $historicalManual['student_id']]);
    $historicalManualDetails = $historicalManualProfile->fetch();
    $assert(
        $historicalManualDetails['student_number'] === null
            && $historicalManualDetails['classification'] === 'alumni'
            && (int) $historicalManualDetails['is_current'] === 0
            && (int) $historicalManualDetails['is_active'] === 0,
        'Manual historical alumni registration permits a missing ID without activating current enrollment.'
    );

    $appendCsvPath = tempnam(sys_get_temp_dir(), 'student-period-import-');
    if ($appendCsvPath === false) {
        throw new RuntimeException('The period import test file could not be created.');
    }
    try {
        $templatePath = dirname(__DIR__) . '/assets/csv/student-import-template.csv';
        $templateHandle = fopen($templatePath, 'rb');
        if ($templateHandle === false) {
            throw new RuntimeException('The student import template could not be opened.');
        }
        try {
            $templateHeader = fgetcsv($templateHandle, 0, ',', '"', '\\');
        } finally {
            fclose($templateHandle);
        }
        $assert(
            $templateHeader === [
                'student_id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'email',
                'year_level',
            ],
            'The downloadable template contains only roster fields.'
        );

        $templateCsvPath = tempnam(sys_get_temp_dir(), 'student-template-import-');
        if ($templateCsvPath === false) {
            throw new RuntimeException('The template import test file could not be created.');
        }
        try {
            file_put_contents(
                $templateCsvPath,
                "student_id,first_name,middle_name,last_name,suffix,email,year_level\n"
                    . "S-299,Dee,,Santos,Jr.,dee@example.test,4\n"
            );
            $templateRows = parse_student_import_csv($templateCsvPath);
            $assert(
                $templateRows[0]['student_number'] === 'S-299'
                    && $templateRows[0]['middle_name'] === ''
                    && $templateRows[0]['surname'] === 'Santos'
                    && $templateRows[0]['suffix'] === 'Jr.'
                    && $templateRows[0]['year_level'] === '4'
                    && !array_key_exists('contact_number', $templateRows[0])
                    && !array_key_exists('graduation_year', $templateRows[0]),
                'The student template accepts roster data without contact or graduation fields.'
            );
        } finally {
            unlink($templateCsvPath);
        }

        file_put_contents(
            $appendCsvPath,
            "student_id,first_name,middle_name,last_name,suffix,email,contact_number,year_level,graduation_year\n"
                . "S-299,Dee,,Santos,Jr.,dee@example.test,ignored,4,not-a-year\n"
        );
        $legacyTemplateRows = parse_student_import_csv($appendCsvPath);
        $assert(
            $legacyTemplateRows[0]['processing_status'] === 'ready'
                && !array_key_exists('contact_number', $legacyTemplateRows[0])
                && !array_key_exists('graduation_year', $legacyTemplateRows[0]),
            'Legacy CSV columns for contact and graduation are ignored.'
        );

        file_put_contents(
            $appendCsvPath,
            "student_id,first_name,middle_name,surname,suffix,email,year_level,course,enrollment_status\n"
                . "S-300,Cam,,Cruz,,cam@example.test,2,CS,currently_enrolled\n"
                . "S-300,Other,,Person,,other@example.test,1,CS,currently_enrolled\n"
                . ",Al,,Um,,alumni@example.test,1,CS,alumni\n"
                . "S-400,Invalid,,, ,not-an-email,5,CS,currently_enrolled\n"
        );
        $periodRows = parse_student_import_csv($appendCsvPath);
        $preview = preview_student_import($db, $currentTermId, 1, $periodRows);
        $assert(
            $preview['summary'] === ['total' => 4, 'ready' => 2, 'review' => 0, 'skipped' => 1, 'failed' => 1],
            'Selected-period preview classifies valid, duplicate, and invalid rows separately.'
        );
        $importResult = commit_student_import(
            $db,
            $currentTermId,
            1,
            $periodRows,
            [],
            1,
            'imported_by_personnel_id',
            'period-import.csv',
            'Registrar'
        );
        $assert(
            $importResult['imported_rows'] === 2
                && $importResult['skipped_rows'] === 1
                && $importResult['failed_rows'] === 1,
            'Selected-period import adds eligible rows and persists skipped and failed outcomes.'
        );
        $importAudit = $db->prepare(
            'SELECT 1 FROM audit_events
             WHERE entity_type = :entity_type AND entity_id = :entity_id'
        );
        $importAudit->execute([
            'entity_type' => 'student_import_batch',
            'entity_id' => (string) $importResult['batch_id'],
        ]);
        $assert($importAudit->fetchColumn() !== false, 'Student CSV import writes a general audit event.');
        $outcomes = $db->prepare(
            'SELECT processing_status, COUNT(*) AS row_count
             FROM student_import_rows WHERE batch_id = :batch_id GROUP BY processing_status'
        );
        $outcomes->execute(['batch_id' => $importResult['batch_id']]);
        $outcomeCounts = [];
        foreach ($outcomes->fetchAll() as $outcome) {
            $outcomeCounts[$outcome['processing_status']] = (int) $outcome['row_count'];
        }
        $assert(
            ($outcomeCounts['imported'] ?? 0) === 2
                && ($outcomeCounts['skipped'] ?? 0) === 1
                && ($outcomeCounts['failed'] ?? 0) === 1,
            'Every nonblank source row has a durable import outcome.'
        );
        $failedSource = $db->prepare(
            'SELECT source_data FROM student_import_rows
             WHERE batch_id = :batch_id AND processing_status = \'failed\''
        );
        $failedSource->execute(['batch_id' => $importResult['batch_id']]);
        $failedSourceData = json_decode((string) $failedSource->fetchColumn(), true);
        $assert(
            is_array($failedSourceData) && $failedSourceData['email'] === 'not-an-email',
            'Row-level audit preserves original fields for failed CSV rows.'
        );
        $noIdProfile = $db->query(
            "SELECT s.student_number, e.enrollment_status, m.is_active
             FROM student_registry s
             JOIN student_enrollments e ON e.student_id = s.id
             JOIN student_roster_memberships m ON m.enrollment_id = e.id
             WHERE s.email = 'alumni@example.test'
             LIMIT 1"
        )->fetch();
        $assert(
            $noIdProfile['student_number'] === null
                && $noIdProfile['enrollment_status'] === 'alumni'
                && (int) $noIdProfile['is_active'] === 0,
            'Historical non-current profiles can be imported without a Student ID or active membership.'
        );
        $repeatPreview = preview_student_import($db, $currentTermId, 1, $periodRows);
        $assert(
            $repeatPreview['summary']['ready'] === 0
                && $repeatPreview['summary']['skipped'] === 3,
            'Repeating an import never duplicates a student enrollment in the same period.'
        );
        $batchCountBeforeDuplicateImport = (int) $db->query(
            'SELECT COUNT(*) FROM student_import_batches'
        )->fetchColumn();
        $expectInvalid(
            static fn () => commit_student_import(
                $db,
                $currentTermId,
                1,
                $periodRows,
                [],
                1,
                'imported_by_personnel_id',
                'period-import.csv',
                'Registrar'
            ),
            'An all-duplicate re-import is rejected instead of creating another batch.'
        );
        $assert(
            (int) $db->query('SELECT COUNT(*) FROM student_import_batches')->fetchColumn()
                === $batchCountBeforeDuplicateImport,
            'Rejecting a duplicate re-import makes no database changes.'
        );
    } finally {
        unlink($appendCsvPath);
    }

    $courseCountBeforePreview = (int) $db->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $otherCoursePreview = preview_student_import($db, $currentTermId, 3, [[
        'row_number' => 2,
        'student_number' => 'S-100',
        'first_name' => 'Ava',
        'middle_name' => '',
        'surname' => 'Santos',
        'suffix' => '',
        'email' => 'ava@example.test',
        'year_level' => '1',
        'course' => '',
        'enrollment_status' => 'currently_enrolled',
        'processing_status' => 'ready',
        'error_message' => null,
    ]]);
    $assert(
        (int) $db->query('SELECT COUNT(*) FROM courses')->fetchColumn() === $courseCountBeforePreview,
        'Previewing an import does not create or alter course mappings.'
    );
    $assert(
        $otherCoursePreview['rows'][0]['processing_status'] === 'review',
        'A student with a conflicting period enrollment requires manual resolution.'
    );
    $profileConflictRows = [[
        'row_number' => 2,
        'student_number' => 'S-400',
        'first_name' => 'Different',
        'middle_name' => '',
        'surname' => 'Name',
        'suffix' => '',
        'email' => 'different@example.test',
        'year_level' => '2',
        'course' => 'CS',
        'enrollment_status' => 'currently_enrolled',
        'processing_status' => 'ready',
        'error_message' => null,
    ]];
    $profileConflictPreview = preview_student_import($db, (int) $term['id'], 1, $profileConflictRows);
    $assert(
        $profileConflictPreview['rows'][0]['processing_status'] === 'review'
            && $profileConflictPreview['rows'][0]['can_approve'],
        'Mismatched profile details are explicitly reviewable without overwriting the profile.'
    );
    $profileAuditCountBeforeApprovedImport = (int) $db->query(
        "SELECT COUNT(*) FROM student_profile_audit WHERE student_id = {$manualRegistration['student_id']}"
    )->fetchColumn();
    $approvedImport = commit_student_import(
        $db,
        (int) $term['id'],
        1,
        $profileConflictRows,
        [2],
        1,
        'imported_by_personnel_id',
        'profile-conflict.csv',
        'Registrar'
    );
    $assert(
        $approvedImport['imported_rows'] === 1,
        'An explicitly approved profile conflict can add the selected-period enrollment.'
    );
    $preservedManualProfile = $db->prepare(
        'SELECT first_name, email FROM student_registry WHERE id = :id'
    );
    $preservedManualProfile->execute(['id' => $manualRegistration['student_id']]);
    $preservedAfterApproval = $preservedManualProfile->fetch();
    $assert(
        $preservedAfterApproval['first_name'] === 'Dana'
            && $preservedAfterApproval['email'] === 'dana@example.test'
            && (int) $db->query(
                "SELECT COUNT(*) FROM student_profile_audit WHERE student_id = {$manualRegistration['student_id']}"
            )->fetchColumn() === $profileAuditCountBeforeApprovedImport,
        'Approving a CSV profile conflict preserves personal details and audit history.'
    );

    $historicalRecord = [[
        'row_number' => 2,
        'student_number' => 'S-500',
        'first_name' => 'Historical',
        'middle_name' => '',
        'surname' => 'Student',
        'suffix' => '',
        'email' => 'historical@example.test',
        'year_level' => '1',
        'course' => 'CS',
        'enrollment_status' => 'currently_enrolled',
        'processing_status' => 'ready',
        'error_message' => null,
    ]];
    $historicalImport = commit_student_import(
        $db,
        (int) $term['id'],
        1,
        $historicalRecord,
        [],
        1,
        'imported_by_personnel_id',
        'historical-period.csv',
        'Registrar'
    );
    $historicalBatch = $db->prepare(
        'SELECT b.is_current, m.is_active
         FROM student_import_batches b
         JOIN student_enrollments e ON e.import_batch_id = b.id
         JOIN student_roster_memberships m ON m.enrollment_id = e.id
         WHERE b.id = :batch_id'
    );
    $historicalBatch->execute(['batch_id' => $historicalImport['batch_id']]);
    $historicalBatchStatus = $historicalBatch->fetch();
    $assert(
        (int) $historicalBatchStatus['is_current'] === 1
            && (int) $historicalBatchStatus['is_active'] === 1,
        'Imports for inactive periods are staged without changing the active academic period.'
    );
    $currentEligibility = $db->prepare(
        'SELECT COUNT(*)
         FROM student_roster_memberships
         WHERE student_id = (
             SELECT id FROM student_registry WHERE student_number = \'S-500\'
         )
           AND academic_term_id = :current_term_id
           AND is_active = 1'
    );
    $currentEligibility->execute(['current_term_id' => $currentTermId]);
    $assert(
        (int) $currentEligibility->fetchColumn() === 0,
        'Inactive-period imports do not change current request eligibility.'
    );
    $assert(find_request_student($db, 'S-500') === null, 'Staged imports cannot validate requests while their period is inactive.');
    activate_academic_term($db, (int) $term['id']);
    $assert(find_request_student($db, 'S-500') !== null, 'Activating a period enables its staged student roster.');
    $assert(
        (int) $db->query('SELECT COUNT(*) FROM academic_terms WHERE is_current = 1')->fetchColumn() === 1,
        'Activating a period leaves exactly one academic period active.'
    );
    activate_academic_term($db, (int) $secondTerm['id']);
    $assert(
        student_csv_safe_export_value('=1+1') === "'=1+1",
        'Downloaded row outcomes escape spreadsheet formulas.'
    );

    fwrite(STDOUT, "Student roster integration checks passed ({$assertions} assertions).\n");
} finally {
    $db = null;
}
