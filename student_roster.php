<?php
declare(strict_types=1);

require_once __DIR__ . '/audit_helpers.php';

function student_csv_text_length(string $value): int
{
    $count = preg_match_all('/./us', $value);
    return $count === false ? PHP_INT_MAX : $count;
}

function student_csv_safe_export_value(string $value): string
{
    return preg_match('/^\s*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
}

function student_profile_audit_json(array $changes): string
{
    $encoded = json_encode($changes, JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($encoded)) {
        throw new RuntimeException('Student profile audit details could not be encoded.');
    }

    return $encoded;
}

function parse_student_csv(string $path): array
{
    $file = fopen($path, 'rb');
    if ($file === false) {
        throw new RuntimeException('The uploaded CSV file could not be opened.');
    }

    try {
        $header = fgetcsv($file, 0, ',', '"', '\\');
        if ($header === false || $header === [null]) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(static fn ($value): string => strtolower(trim((string) $value)), $header);
        $expected = ['student_id', 'first_name', 'middle_name', 'surname', 'email', 'year_level'];
        if ($header !== $expected) {
            throw new InvalidArgumentException(
                'The CSV headers must match the template: student_id, first_name, middle_name, surname, email, year_level.'
            );
        }

        $students = [];
        $seenStudentNumbers = [];
        $lineNumber = 1;
        while (($row = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }
            if (count($row) !== count($expected)) {
                throw new InvalidArgumentException("CSV line {$lineNumber} must contain exactly six columns.");
            }

            $student = [
                'student_number' => trim((string) $row[0]),
                'first_name' => trim((string) $row[1]),
                'middle_name' => trim((string) $row[2]),
                'surname' => trim((string) $row[3]),
                'email' => strtolower(trim((string) $row[4])),
                'year_level' => filter_var(trim((string) $row[5]), FILTER_VALIDATE_INT),
            ];

            foreach (['student_number', 'first_name', 'middle_name', 'surname', 'email'] as $field) {
                if (preg_match('//u', $student[$field]) !== 1
                    || preg_match('/[\x00-\x1F\x7F]/', $student[$field]) === 1) {
                    throw new InvalidArgumentException("CSV line {$lineNumber} contains invalid text.");
                }
            }

            if ($student['student_number'] === '' || student_csv_text_length($student['student_number']) > 64) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid student ID.");
            }
            if ($student['first_name'] === '' || student_csv_text_length($student['first_name']) > 100) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid first name.");
            }
            if (student_csv_text_length($student['middle_name']) > 100) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid middle name.");
            }
            if ($student['surname'] === '' || student_csv_text_length($student['surname']) > 100) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid surname.");
            }
            if (student_csv_text_length($student['email']) > 254
                || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid email address.");
            }
            if (!is_int($student['year_level']) || !in_array($student['year_level'], [1, 2, 3, 4], true)) {
                throw new InvalidArgumentException("CSV line {$lineNumber} has an invalid year level.");
            }

            $duplicateKey = strtolower($student['student_number']);
            if (isset($seenStudentNumbers[$duplicateKey])) {
                throw new InvalidArgumentException("Student ID is duplicated on CSV line {$lineNumber}.");
            }
            $seenStudentNumbers[$duplicateKey] = true;
            $students[] = $student;

            if (count($students) > 5000) {
                throw new InvalidArgumentException('A single CSV import cannot exceed 5,000 students.');
            }
        }

        if ($students === []) {
            throw new InvalidArgumentException('The CSV file contains no student rows.');
        }

        return $students;
    } finally {
        fclose($file);
    }
}

function parse_student_import_csv(string $path): array
{
    $file = fopen($path, 'rb');
    if ($file === false) {
        throw new RuntimeException('The uploaded CSV file could not be opened.');
    }

    try {
        $header = fgetcsv($file, 0, ',', '"', '\\');
        if ($header === false || $header === [null]) {
            throw new InvalidArgumentException('The CSV file is empty.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $aliases = [
            'student_id' => 'student_number',
            'student_number' => 'student_number',
            'id' => 'student_number',
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'surname' => 'surname',
            'last_name' => 'surname',
            'suffix' => 'suffix',
            'email' => 'email',
            'year_level' => 'year_level',
            'course' => 'course',
            'course_name' => 'course',
            'enrollment_status' => 'enrollment_status',
            'status' => 'enrollment_status',
            'classification' => 'enrollment_status',
        ];
        $headerMap = [];
        foreach ($header as $index => $column) {
            $normalized = strtolower(trim((string) $column));
            $key = $aliases[$normalized] ?? null;
            if ($key !== null) {
                if (isset($headerMap[$key])) {
                    throw new InvalidArgumentException('The CSV contains duplicate columns for ' . $key . '.');
                }
                $headerMap[$key] = $index;
            }
        }

        foreach (['student_number', 'first_name', 'surname', 'email', 'year_level'] as $requiredColumn) {
            if (!isset($headerMap[$requiredColumn])) {
                throw new InvalidArgumentException(
                    'The CSV must include Student ID, first name, last name, email, and year level columns.'
                );
            }
        }

        $rows = [];
        $lineNumber = 1;
        while (($cells = fgetcsv($file, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
                continue;
            }
            if (count($rows) >= 5000) {
                throw new InvalidArgumentException('A single CSV import cannot exceed 5,000 rows.');
            }

            $student = [
                'row_number' => $lineNumber,
                'student_number' => '',
                'first_name' => '',
                'middle_name' => '',
                'surname' => '',
                'suffix' => '',
                'email' => '',
                'year_level' => '',
                'course' => '',
                'enrollment_status' => 'currently_enrolled',
                'processing_status' => 'ready',
                'error_message' => null,
            ];
            if (count($cells) > count($header)) {
                $student['processing_status'] = 'failed';
                $student['error_message'] = 'Row contains more cells than the header.';
            }
            foreach ($headerMap as $field => $index) {
                $student[$field] = trim((string) ($cells[$index] ?? ''));
            }

            $student['email'] = strtolower($student['email']);
            if ($student['enrollment_status'] === '') {
                $student['enrollment_status'] = 'currently_enrolled';
            }
            foreach ([
                'student_number',
                'first_name',
                'middle_name',
                'surname',
                'suffix',
                'email',
                'year_level',
                'course',
                'enrollment_status',
            ] as $field) {
                if (preg_match('//u', $student[$field]) !== 1
                    || preg_match('/[\x00-\x1F\x7F]/', $student[$field]) === 1) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = $student['error_message'] ?? 'Row contains invalid text.';
                    break;
                }
            }

            if ($student['processing_status'] === 'ready') {
                $status = strtolower(str_replace([' ', '-'], '_', $student['enrollment_status']));
                if (!in_array($status, [
                    'currently_enrolled',
                    'not_currently_enrolled',
                    'transferred_out',
                    'dropped_out',
                    'alumni',
                ], true)) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Enrollment status is not recognized.';
                } else {
                    $student['enrollment_status'] = $status;
                }
            }

            if ($student['processing_status'] === 'ready') {
                if ($student['student_number'] === ''
                    && $student['enrollment_status'] === 'currently_enrolled') {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Student ID is required for currently enrolled students.';
                } elseif ($student['student_number'] !== ''
                    && student_csv_text_length($student['student_number']) > 64) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Student ID must be no more than 64 characters.';
                } elseif ($student['first_name'] === ''
                    || student_csv_text_length($student['first_name']) > 100) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'First name is required and must be no more than 100 characters.';
                } elseif ($student['middle_name'] !== ''
                    && student_csv_text_length($student['middle_name']) > 100) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Middle name must be no more than 100 characters.';
                } elseif ($student['surname'] === ''
                    || student_csv_text_length($student['surname']) > 100) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Surname is required and must be no more than 100 characters.';
                } elseif (student_csv_text_length($student['suffix']) > 20) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Suffix must be no more than 20 characters.';
                } elseif (student_csv_text_length($student['email']) > 254
                    || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'A valid email address is required.';
                } elseif ($student['year_level'] === '') {
                    $student['processing_status'] = 'failed';
                    $student['error_message'] = 'Year level is required.';
                }
            }

            $rows[] = $student;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The CSV file contains no student rows.');
        }

        return $rows;
    } finally {
        fclose($file);
    }
}

function student_year_level_label(int $yearLevel): string
{
    $labels = [
        1 => '1st year',
        2 => '2nd year',
        3 => '3rd year',
        4 => '4th year',
    ];

    return $labels[$yearLevel] ?? "{$yearLevel}th year";
}

function active_academic_term(PDO $db): ?array
{
    $term = $db->query(
        'SELECT t.id, ay.start_year, ay.end_year, t.semester
         FROM academic_terms t
         JOIN academic_years ay ON ay.id = t.academic_year_id
         WHERE t.is_current = 1
         LIMIT 1'
    )->fetch();

    return $term ?: null;
}

function with_student_roster_lock(PDO $db, callable $operation): mixed
{
    if ((int) $db->query("SELECT GET_LOCK('emer_student_roster_import', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another student roster change is running. Please try again shortly.');
    }

    try {
        return $operation();
    } finally {
        $db->query("SELECT RELEASE_LOCK('emer_student_roster_import')");
    }
}

function save_academic_term(PDO $db, string $academicYear, int $semester, bool $activate): array
{
    if (!preg_match('/^(\d{4})-(\d{4})$/D', $academicYear, $yearMatch)
        || (int) $yearMatch[2] !== (int) $yearMatch[1] + 1
        || !in_array($semester, [1, 2], true)) {
        throw new InvalidArgumentException('Choose a valid academic year and semester.');
    }

    return with_student_roster_lock($db, static function () use ($db, $academicYear, $semester, $yearMatch, $activate): array {
        $db->beginTransaction();
        try {
            $yearInsert = $db->prepare(
                'INSERT INTO academic_years (start_year, end_year)
                 VALUES (:start_year, :end_year)
                 ON DUPLICATE KEY UPDATE end_year = VALUES(end_year)'
            );
            $yearInsert->execute([
                'start_year' => (int) $yearMatch[1],
                'end_year' => (int) $yearMatch[2],
            ]);
            $yearLookup = $db->prepare(
                'SELECT id FROM academic_years WHERE start_year = :start_year AND end_year = :end_year'
            );
            $yearLookup->execute([
                'start_year' => (int) $yearMatch[1],
                'end_year' => (int) $yearMatch[2],
            ]);
            $academicYearId = $yearLookup->fetchColumn();
            if ($academicYearId === false) {
                throw new RuntimeException('The academic year could not be retrieved after saving.');
            }

            $termLookup = $db->prepare(
                'SELECT id, is_current
                 FROM academic_terms
                 WHERE academic_year_id = :academic_year_id AND semester = :semester
                 FOR UPDATE'
            );
            $termLookup->execute([
                'academic_year_id' => (int) $academicYearId,
                'semester' => $semester,
            ]);
            $term = $termLookup->fetch();
            if ($term === false) {
                $termInsert = $db->prepare(
                    'INSERT INTO academic_terms (academic_year_id, semester, is_current)
                     VALUES (:academic_year_id, :semester, 0)'
                );
                $termInsert->execute([
                    'academic_year_id' => (int) $academicYearId,
                    'semester' => $semester,
                ]);
                $termId = (int) $db->lastInsertId();
                $wasCurrent = false;
            } else {
                $termId = (int) $term['id'];
                $wasCurrent = (int) $term['is_current'] === 1;
            }

            if ($activate) {
                activate_academic_term_locked($db, $termId, $wasCurrent);
            }
            $db->commit();

            return [
                'id' => $termId,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'is_current' => $activate ? 1 : (int) ($term['is_current'] ?? 0),
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function create_academic_term(PDO $db, string $academicYear, int $semester): array
{
    return save_academic_term($db, $academicYear, $semester, false);
}

function ensure_current_academic_periods(PDO $db): void
{
    $year = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    $academicYear = $year . '-' . ($year + 1);
    create_academic_term($db, $academicYear, 1);
    create_academic_term($db, $academicYear, 2);
}

function open_academic_term(PDO $db, string $academicYear, int $semester): array
{
    return save_academic_term($db, $academicYear, $semester, true);
}

function activate_academic_term_locked(PDO $db, int $termId, bool $wasCurrent): array
{
    $termQuery = $db->prepare(
        'SELECT t.id, ay.start_year, ay.end_year, t.semester
         FROM academic_terms t
         JOIN academic_years ay ON ay.id = t.academic_year_id
         WHERE t.id = :id
         FOR UPDATE'
    );
    $termQuery->execute(['id' => $termId]);
    $term = $termQuery->fetch();
    if ($term === false) {
        throw new InvalidArgumentException('Choose an existing academic period.');
    }

    if (!$wasCurrent) {
        $currentBatchQuery = $db->prepare(
            'SELECT COUNT(*)
             FROM student_import_batches
             WHERE academic_term_id = :term_id AND is_current = 1'
        );
        $currentBatchQuery->execute(['term_id' => $termId]);
        if ((int) $currentBatchQuery->fetchColumn() === 0) {
            $restoreBatches = $db->prepare(
                "UPDATE student_import_batches
                 SET is_current = 1
                 WHERE academic_term_id = :term_id AND import_status <> 'failed'"
            );
            $restoreBatches->execute(['term_id' => $termId]);
        }
    }

    $db->exec('UPDATE academic_terms SET is_current = 0');
    $activate = $db->prepare('UPDATE academic_terms SET is_current = 1 WHERE id = :id');
    $activate->execute(['id' => $termId]);

    return [
        'id' => $termId,
        'academic_year' => $term['start_year'] . '-' . $term['end_year'],
        'semester' => (int) $term['semester'],
    ];
}

function activate_academic_term(PDO $db, int $termId): array
{
    if ($termId < 1) {
        throw new InvalidArgumentException('Choose a valid academic period.');
    }

    return with_student_roster_lock($db, static function () use ($db, $termId): array {
        $db->beginTransaction();
        try {
            $isCurrentQuery = $db->prepare(
                'SELECT is_current FROM academic_terms WHERE id = :id FOR UPDATE'
            );
            $isCurrentQuery->execute(['id' => $termId]);
            $isCurrent = $isCurrentQuery->fetchColumn();
            if ($isCurrent === false) {
                throw new InvalidArgumentException('Choose an existing academic period.');
            }
            if ((int) $isCurrent === 1) {
                throw new InvalidArgumentException('That academic period is already active. No changes were made.');
            }
            $term = activate_academic_term_locked($db, $termId, (int) $isCurrent === 1);
            $db->commit();

            return $term;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function deactivate_academic_term(PDO $db, int $termId): void
{
    if ($termId < 1) {
        throw new InvalidArgumentException('Choose a valid academic period.');
    }

    with_student_roster_lock($db, static function () use ($db, $termId): void {
        $db->beginTransaction();
        try {
            $termQuery = $db->prepare(
                'SELECT is_current FROM academic_terms WHERE id = :id FOR UPDATE'
            );
            $termQuery->execute(['id' => $termId]);
            $isCurrent = $termQuery->fetchColumn();
            if ($isCurrent === false || (int) $isCurrent !== 1) {
                throw new InvalidArgumentException('That academic period is not currently active.');
            }
            $deactivate = $db->prepare('UPDATE academic_terms SET is_current = 0 WHERE id = :id');
            $deactivate->execute(['id' => $termId]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function roster_course(PDO $db, int $schoolCourseId, bool $createRosterCourse = true): array
{
    $courseQuery = $db->prepare(
        "SELECT c.id, c.acronym, c.name AS course_title,
                CONCAT(c.acronym, ' - ', c.name) AS name
         FROM school_courses c
         JOIN school_levels l ON l.id = c.level_id
         WHERE c.id = :course_id AND c.is_active = 1 AND l.name = 'COLLEGE'
         LIMIT 1"
    );
    $courseQuery->execute(['course_id' => $schoolCourseId]);
    $course = $courseQuery->fetch();
    if (!$course) {
        throw new InvalidArgumentException('Choose an active course.');
    }

    $levelsQuery = $db->prepare(
        'SELECT year_number, label
         FROM school_year_levels
         WHERE course_id = :course_id AND is_active = 1
         ORDER BY year_number'
    );
    $levelsQuery->execute(['course_id' => $schoolCourseId]);
    $levelRows = $levelsQuery->fetchAll();
    $levels = array_map('intval', array_column($levelRows, 'year_number'));
    if ($levels === []) {
        throw new InvalidArgumentException('The selected course has no active year levels.');
    }

    $courseLookup = $db->prepare('SELECT id FROM courses WHERE name = :name');
    $courseLookup->execute(['name' => $course['name']]);
    $course['roster_course_id'] = (int) ($courseLookup->fetchColumn() ?: 0);
    if ($course['roster_course_id'] < 1 && $createRosterCourse) {
        $courseInsert = $db->prepare(
            'INSERT INTO courses (name) VALUES (:name)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $courseInsert->execute(['name' => $course['name']]);
        $course['roster_course_id'] = (int) $db->lastInsertId();
        if ($course['roster_course_id'] < 1) {
            $courseLookup->execute(['name' => $course['name']]);
            $course['roster_course_id'] = (int) $courseLookup->fetchColumn();
        }
        if ($course['roster_course_id'] < 1) {
            throw new RuntimeException('The selected course could not be retrieved.');
        }
    }

    return [
        'school_course_id' => (int) $course['id'],
        'roster_course_id' => (int) $course['roster_course_id'],
        'name' => (string) $course['name'],
        'acronym' => (string) $course['acronym'],
        'course_title' => (string) $course['course_title'],
        'year_levels' => $levels,
        'year_labels' => array_column($levelRows, 'label', 'year_number'),
    ];
}

function inspect_course_roster_import(
    PDO $db,
    int $termId,
    int $schoolCourseId,
    array $students
): array {
    $termQuery = $db->prepare(
        'SELECT id FROM academic_terms WHERE id = :id FOR UPDATE'
    );
    $termQuery->execute(['id' => $termId]);
    if ($termQuery->fetchColumn() === false) {
        throw new InvalidArgumentException('Choose an existing academic period before importing.');
    }

    $course = roster_course($db, $schoolCourseId);
    foreach ($students as $student) {
        if (!in_array((int) $student['year_level'], $course['year_levels'], true)) {
            throw new InvalidArgumentException(
                'Student ID ' . $student['student_number'] . ' has a year level that is not active for '
                . $course['name'] . '. No roster changes were made.'
            );
        }
    }

    $otherCourseStudents = $db->prepare(
        'SELECT LOWER(s.student_number)
         FROM student_roster_memberships m
         JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         JOIN student_registry s ON s.id = m.student_id
         WHERE m.academic_term_id = :term_id AND m.is_active = 1
           AND m.course_id <> :course_id'
    );
    $otherCourseStudents->execute([
        'term_id' => $termId,
        'course_id' => $course['roster_course_id'],
    ]);
    $alreadyEnrolled = array_fill_keys($otherCourseStudents->fetchAll(PDO::FETCH_COLUMN), true);
    foreach ($students as $student) {
        if (isset($alreadyEnrolled[strtolower($student['student_number'])])) {
            throw new InvalidArgumentException(
                'Student ID ' . $student['student_number']
                . ' is already in another course for this semester. No roster changes were made.'
            );
        }
    }

    $currentCourseStudents = $db->prepare(
        'SELECT LOWER(s.student_number)
         FROM student_roster_memberships m
         JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
         JOIN student_registry s ON s.id = m.student_id
         WHERE m.academic_term_id = :term_id AND m.course_id = :course_id AND m.is_active = 1'
    );
    $currentCourseStudents->execute([
        'term_id' => $termId,
        'course_id' => $course['roster_course_id'],
    ]);
    $currentStudentNumbers = array_fill_keys($currentCourseStudents->fetchAll(PDO::FETCH_COLUMN), true);
    $duplicateStudentNumbers = [];
    foreach ($students as $student) {
        if (isset($currentStudentNumbers[strtolower($student['student_number'])])) {
            $duplicateStudentNumbers[] = $student['student_number'];
        }
    }

    return [
        'course' => $course,
        'duplicate_student_numbers' => $duplicateStudentNumbers,
    ];
}

function preview_course_roster_import(PDO $db, int $termId, int $schoolCourseId, array $students): array
{
    return with_student_roster_lock($db, static function () use (
        $db,
        $termId,
        $schoolCourseId,
        $students
    ): array {
        $db->beginTransaction();
        try {
            $inspection = inspect_course_roster_import($db, $termId, $schoolCourseId, $students);
            $db->rollBack();

            return $inspection['duplicate_student_numbers'];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function import_course_roster(
    PDO $db,
    int $termId,
    int $schoolCourseId,
    array $students,
    int $importerId,
    string $importerColumn,
    bool $overwriteConfirmed = false,
    ?string $originalFilename = null
): int {
    if (!in_array($importerColumn, ['imported_by_personnel_id', 'imported_by_user_id'], true)) {
        throw new InvalidArgumentException('The roster importer account is unavailable.');
    }
    if ($students === [] || count($students) > 5000) {
        throw new InvalidArgumentException('A course roster must contain between 1 and 5,000 students.');
    }

    $seenStudentNumbers = [];
    foreach ($students as $student) {
        if (!is_array($student)
            || !isset($student['student_number'], $student['first_name'], $student['surname'], $student['email'])
            || !array_key_exists('middle_name', $student)
            || !isset($student['year_level'])
            || !is_int($student['year_level'])
            || !in_array($student['year_level'], [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('The uploaded course roster contains an invalid student row.');
        }
        $duplicateKey = strtolower((string) $student['student_number']);
        if (isset($seenStudentNumbers[$duplicateKey])) {
            throw new InvalidArgumentException('The uploaded course roster contains a duplicate Student ID.');
        }
        $seenStudentNumbers[$duplicateKey] = true;
    }

    return with_student_roster_lock($db, static function () use (
        $db,
        $termId,
        $schoolCourseId,
        $students,
        $importerId,
        $importerColumn,
        $overwriteConfirmed,
        $originalFilename
    ): int {
        $db->beginTransaction();
        try {
            $inspection = inspect_course_roster_import($db, $termId, $schoolCourseId, $students);
            $course = $inspection['course'];
            $duplicateCount = count($inspection['duplicate_student_numbers']);
            if ($duplicateCount > 0 && !$overwriteConfirmed) {
                throw new InvalidArgumentException(
                    'Found duplicates: ' . $duplicateCount . ' Student ID(s) are already in this course. '
                    . 'Confirm overwrite to replace the entire active roster.'
                );
            }

            $archiveCourseBatches = $db->prepare(
                'UPDATE student_import_batches
                 SET is_current = 0
                 WHERE academic_term_id = :term_id AND course_id = :course_id AND is_current = 1'
            );
            $archiveCourseBatches->execute([
                'term_id' => $termId,
                'course_id' => $course['roster_course_id'],
            ]);

            $yearLevels = array_column($students, 'year_level');
            $batchInsert = $db->prepare(
                'INSERT INTO student_import_batches
                    (academic_term_id, course_id, source, original_filename, ' . $importerColumn . ',
                     year_level, row_count, total_rows, is_current)
                 VALUES (:term_id, :course_id, \'csv\', :original_filename, :importer_id,
                         :year_level, :row_count, :total_rows, 1)'
            );
            $batchInsert->execute([
                'term_id' => $termId,
                'course_id' => $course['roster_course_id'],
                'original_filename' => $originalFilename !== null
                    ? substr(basename($originalFilename), 0, 255)
                    : null,
                'importer_id' => $importerId,
                'year_level' => min($yearLevels),
                'row_count' => count($students),
                'total_rows' => count($students),
            ]);
            $batchId = (int) $db->lastInsertId();

            $studentInsert = $db->prepare(
                'INSERT INTO student_registry (student_number, first_name, middle_name, surname, email)
                 VALUES (:student_number, :first_name, :middle_name, :surname, :email)
                 ON DUPLICATE KEY UPDATE
                     first_name = VALUES(first_name),
                     middle_name = VALUES(middle_name),
                     surname = VALUES(surname),
                     email = VALUES(email)'
            );
            $studentLookup = $db->prepare(
                'SELECT id FROM student_registry WHERE student_number = :student_number'
            );
            $enrollmentInsert = $db->prepare(
                'INSERT INTO student_enrollments
                    (student_id, import_batch_id, course_id, year_level, imported_student_number,
                     imported_first_name, imported_middle_name, imported_surname, imported_email)
                 VALUES (:student_id, :batch_id, :course_id, :year_level, :student_number,
                         :first_name, :middle_name, :surname, :email)'
            );
            $membershipInsert = $db->prepare(
                'INSERT INTO student_roster_memberships
                    (import_batch_id, enrollment_id, academic_term_id, student_id, course_id, year_level, is_active)
                 VALUES (:batch_id, :enrollment_id, :term_id, :student_id, :course_id, :year_level, 1)'
            );
            $importRowInsert = $db->prepare(
                "INSERT INTO student_import_rows
                    (batch_id, row_number, student_identifier, processing_status,
                     associated_student_id, associated_enrollment_id)
                 VALUES (:batch_id, :row_number, :student_number, 'imported', :student_id, :enrollment_id)"
            );

            foreach ($students as $index => $student) {
                $studentInsert->execute([
                    'student_number' => $student['student_number'],
                    'first_name' => $student['first_name'],
                    'middle_name' => $student['middle_name'] !== '' ? $student['middle_name'] : null,
                    'surname' => $student['surname'],
                    'email' => $student['email'],
                ]);
                $studentLookup->execute(['student_number' => $student['student_number']]);
                $studentId = $studentLookup->fetchColumn();
                if ($studentId === false) {
                    throw new RuntimeException('An imported student record could not be retrieved.');
                }

                $enrollmentInsert->execute([
                    'student_id' => (int) $studentId,
                    'batch_id' => $batchId,
                    'course_id' => $course['roster_course_id'],
                    'year_level' => (int) $student['year_level'],
                    'student_number' => $student['student_number'],
                    'first_name' => $student['first_name'],
                    'middle_name' => $student['middle_name'] !== '' ? $student['middle_name'] : null,
                    'surname' => $student['surname'],
                    'email' => $student['email'],
                ]);
                $enrollmentId = (int) $db->lastInsertId();
                $membershipInsert->execute([
                    'batch_id' => $batchId,
                    'enrollment_id' => $enrollmentId,
                    'term_id' => $termId,
                    'student_id' => (int) $studentId,
                    'course_id' => $course['roster_course_id'],
                    'year_level' => (int) $student['year_level'],
                ]);
                $importRowInsert->execute([
                    'batch_id' => $batchId,
                    'row_number' => $index + 2,
                    'student_number' => $student['student_number'],
                    'student_id' => (int) $studentId,
                    'enrollment_id' => $enrollmentId,
                ]);
            }

            $db->commit();
            return count($students);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function register_student_manually(
    PDO $db,
    int $termId,
    int $schoolCourseId,
    array $student,
    int $importerId,
    string $importerColumn,
    string $changedByName = 'Registrar',
    string $enrollmentStatus = 'currently_enrolled'
): array {
    if (!in_array($importerColumn, ['imported_by_personnel_id', 'imported_by_user_id'], true)) {
        throw new InvalidArgumentException('The student registrar account is unavailable.');
    }
    if (!array_key_exists('student_number', $student)
        || !isset($student['first_name'], $student['surname'], $student['email'])
        || !array_key_exists('middle_name', $student)
        || !isset($student['year_level'])
        || !is_int($student['year_level'])) {
        throw new InvalidArgumentException('Complete the required student and enrollment details.');
    }
    if (!in_array($enrollmentStatus, [
        'currently_enrolled',
        'not_currently_enrolled',
        'transferred_out',
        'dropped_out',
        'alumni',
    ], true)) {
        throw new InvalidArgumentException('Choose a valid enrollment status.');
    }
    $studentNumber = trim((string) $student['student_number']);
    if ($studentNumber === '' && $enrollmentStatus === 'currently_enrolled') {
        throw new InvalidArgumentException('Student ID is required for currently enrolled students.');
    }

    return with_student_roster_lock($db, static function () use (
        $db,
        $termId,
        $schoolCourseId,
        $student,
        $importerId,
        $importerColumn,
        $changedByName,
        $enrollmentStatus,
        $studentNumber
    ): array {
        $db->beginTransaction();
        try {
            $termQuery = $db->prepare('SELECT is_current FROM academic_terms WHERE id = :id FOR UPDATE');
            $termQuery->execute(['id' => $termId]);
            $termIsCurrent = $termQuery->fetchColumn();
            if ($termIsCurrent === false) {
                throw new InvalidArgumentException('Choose an existing academic period.');
            }
            $course = roster_course($db, $schoolCourseId);
            if (!in_array($student['year_level'], $course['year_levels'], true)) {
                throw new InvalidArgumentException('Choose an active year level for the selected course.');
            }

            if ($studentNumber !== '') {
                $profileQuery = $db->prepare(
                    'SELECT id FROM student_registry WHERE LOWER(student_number) = LOWER(:student_number) LIMIT 1 FOR UPDATE'
                );
                $profileQuery->execute(['student_number' => $studentNumber]);
                $profileId = $profileQuery->fetchColumn();
                if ($profileId === false) {
                    $emailProfileQuery = $db->prepare(
                        'SELECT id, student_number
                         FROM student_registry
                         WHERE LOWER(email) = LOWER(:email)
                         ORDER BY id
                         LIMIT 2
                         FOR UPDATE'
                    );
                    $emailProfileQuery->execute(['email' => $student['email']]);
                    $emailProfiles = $emailProfileQuery->fetchAll();
                    if (count($emailProfiles) > 1) {
                        throw new InvalidArgumentException('That email matches multiple profiles. Enter the matching Student ID or resolve the profile first.');
                    }
                    if ($emailProfiles !== []) {
                        throw new InvalidArgumentException('That email is already associated with a different Student ID.');
                    }
                }
            } else {
                $profileQuery = $db->prepare(
                    'SELECT id FROM student_registry WHERE LOWER(email) = LOWER(:email) ORDER BY id LIMIT 2 FOR UPDATE'
                );
                $profileQuery->execute(['email' => $student['email']]);
                $profileMatches = $profileQuery->fetchAll(PDO::FETCH_COLUMN);
                if (count($profileMatches) > 1) {
                    throw new InvalidArgumentException('That email matches multiple profiles. Enter the Student ID or resolve the profile first.');
                }
                $profileId = $profileMatches[0] ?? false;
            }
            $profileExists = $profileId !== false;
            if (!$profileExists) {
                $profileInsert = $db->prepare(
                    'INSERT INTO student_registry
                        (student_number, first_name, middle_name, surname, suffix, email,
                         classification, last_attended_academic_term_id)
                     VALUES (:student_number, :first_name, :middle_name, :surname, :suffix, :email,
                             :classification, :last_attended_term_id)'
                );
                $profileInsert->execute([
                    'student_number' => $studentNumber !== '' ? $studentNumber : null,
                    'first_name' => $student['first_name'],
                    'middle_name' => $student['middle_name'] !== '' ? $student['middle_name'] : null,
                    'surname' => $student['surname'],
                    'suffix' => $student['suffix'] !== '' ? $student['suffix'] : null,
                    'email' => $student['email'],
                    'classification' => $enrollmentStatus === 'currently_enrolled'
                        ? 'not_currently_enrolled'
                        : $enrollmentStatus,
                    'last_attended_term_id' => $enrollmentStatus === 'currently_enrolled' ? null : $termId,
                ]);
                $profileId = (int) $db->lastInsertId();
                $auditInsert = $db->prepare(
                    'INSERT INTO student_profile_audit
                        (student_id, action, source, changed_by_name, changes_json)
                     VALUES (:student_id, \'created\', \'manual\', :changed_by_name, :changes_json)'
                );
                $auditInsert->execute([
                    'student_id' => $profileId,
                    'changed_by_name' => substr($changedByName, 0, 150),
                    'changes_json' => student_profile_audit_json([
                        'student_number' => $studentNumber !== '' ? $studentNumber : null,
                        'first_name' => $student['first_name'],
                        'middle_name' => $student['middle_name'],
                        'surname' => $student['surname'],
                        'suffix' => $student['suffix'] ?? '',
                        'email' => $student['email'],
                        'classification' => $enrollmentStatus === 'currently_enrolled'
                            ? 'not_currently_enrolled'
                            : $enrollmentStatus,
                    ]),
                ]);
            }
            $profileId = (int) $profileId;

            $existingEnrollment = $db->prepare(
                'SELECT e.id
                 FROM student_enrollments e
                 JOIN student_import_batches b ON b.id = e.import_batch_id
                 WHERE b.academic_term_id = :term_id AND e.student_id = :student_id
                 LIMIT 1 FOR UPDATE'
            );
            $existingEnrollment->execute(['term_id' => $termId, 'student_id' => $profileId]);
            if ($existingEnrollment->fetchColumn() !== false) {
                throw new InvalidArgumentException('This student already has an enrollment for the selected academic period.');
            }

            $batchInsert = $db->prepare(
                'INSERT INTO student_import_batches
                    (academic_term_id, course_id, source, ' . $importerColumn . ',
                     year_level, row_count, total_rows, import_status, is_current)
                 VALUES (:term_id, :course_id, \'manual\', :importer_id, :year_level, 1, 1, \'completed\', :is_current)'
            );
            $batchInsert->execute([
                'term_id' => $termId,
                'course_id' => $course['roster_course_id'],
                'importer_id' => $importerId,
                'year_level' => $student['year_level'],
                'is_current' => ((int) $termIsCurrent === 1
                    || $enrollmentStatus === 'currently_enrolled') ? 1 : 0,
            ]);
            $batchId = (int) $db->lastInsertId();

            $enrollmentInsert = $db->prepare(
                'INSERT INTO student_enrollments
                    (student_id, import_batch_id, course_id, year_level, imported_student_number,
                     imported_first_name, imported_middle_name, imported_surname, imported_email, enrollment_status)
                 VALUES (:student_id, :batch_id, :course_id, :year_level, :student_number,
                         :first_name, :middle_name, :surname, :email, :enrollment_status)'
            );
            $enrollmentInsert->execute([
                'student_id' => $profileId,
                'batch_id' => $batchId,
                'course_id' => $course['roster_course_id'],
                'year_level' => $student['year_level'],
                'student_number' => $studentNumber !== '' ? $studentNumber : null,
                'first_name' => $student['first_name'],
                'middle_name' => $student['middle_name'] !== '' ? $student['middle_name'] : null,
                'surname' => $student['surname'],
                'email' => $student['email'],
                'enrollment_status' => $enrollmentStatus,
            ]);
            $enrollmentId = (int) $db->lastInsertId();

            $membershipInsert = $db->prepare(
                'INSERT INTO student_roster_memberships
                    (import_batch_id, enrollment_id, academic_term_id, student_id, course_id, year_level, is_active)
                 VALUES (:batch_id, :enrollment_id, :term_id, :student_id, :course_id, :year_level, :is_active)'
            );
            $membershipInsert->execute([
                'batch_id' => $batchId,
                'enrollment_id' => $enrollmentId,
                'term_id' => $termId,
                'student_id' => $profileId,
                'course_id' => $course['roster_course_id'],
                'year_level' => $student['year_level'],
                'is_active' => $enrollmentStatus === 'currently_enrolled' ? 1 : 0,
            ]);

            $rowInsert = $db->prepare(
                "INSERT INTO student_import_rows
                    (batch_id, row_number, student_identifier, processing_status,
                     associated_student_id, associated_enrollment_id)
                 VALUES (:batch_id, 1, :student_number, 'imported', :student_id, :enrollment_id)"
            );
            $rowInsert->execute([
                'batch_id' => $batchId,
                'student_number' => $studentNumber !== '' ? $studentNumber : null,
                'student_id' => $profileId,
                'enrollment_id' => $enrollmentId,
            ]);

            record_audit_event(
                $db,
                $importerId,
                $changedByName,
                'created',
                'student_enrollment',
                (string) $enrollmentId,
                'Manually registered a student enrollment.',
                [
                    'batch_id' => $batchId,
                    'academic_term_id' => $termId,
                    'course_id' => (int) $course['roster_course_id'],
                    'year_level' => $student['year_level'],
                ]
            );
            $db->commit();
            return [
                'student_id' => $profileId,
                'enrollment_id' => $enrollmentId,
                'existing_profile' => $profileExists,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function preview_student_import(PDO $db, int $termId, int $schoolCourseId, array $rows): array
{
    $termQuery = $db->prepare(
        'SELECT t.id
         FROM academic_terms t
         WHERE t.id = :id
         LIMIT 1'
    );
    $termQuery->execute(['id' => $termId]);
    $term = $termQuery->fetch();
    if (!$term) {
        throw new InvalidArgumentException('Choose an existing academic period.');
    }

    $course = roster_course($db, $schoolCourseId, false);
    $seenStudentNumbers = [];
    $seenEmails = [];
    $studentNumbers = [];
    $emailAddresses = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || !isset($row['row_number'], $row['processing_status'])) {
            throw new InvalidArgumentException('The CSV preview contains an invalid row.');
        }
        $rows[$index]['profile_id'] = null;
        $rows[$index]['can_approve'] = false;
        $rows[$index]['year_level_number'] = null;
        if ($row['processing_status'] !== 'ready') {
            continue;
        }

        $courseValue = strtolower(preg_replace('/\s+/', ' ', trim((string) ($row['course'] ?? ''))));
        $acceptedCourseNames = [
            strtolower(preg_replace('/\s+/', ' ', $course['name'])),
            strtolower(preg_replace('/\s+/', ' ', $course['course_title'])),
            strtolower(trim($course['acronym'])),
        ];
        if ($courseValue !== '' && !in_array($courseValue, $acceptedCourseNames, true)) {
            $rows[$index]['processing_status'] = 'failed';
            $rows[$index]['error_message'] = 'Course does not match the selected course.';
            continue;
        }

        $yearValue = trim((string) ($row['year_level'] ?? ''));
        $yearNumber = filter_var($yearValue, FILTER_VALIDATE_INT);
        if (!is_int($yearNumber)) {
            foreach ($course['year_labels'] as $candidateNumber => $label) {
                if (strcasecmp(trim((string) $label), $yearValue) === 0) {
                    $yearNumber = (int) $candidateNumber;
                    break;
                }
            }
        }
        if (!is_int($yearNumber) || !in_array($yearNumber, $course['year_levels'], true)) {
            $rows[$index]['processing_status'] = 'failed';
            $rows[$index]['error_message'] = 'Year level is not active for the selected course.';
            continue;
        }
        $rows[$index]['year_level_number'] = $yearNumber;

        $studentNumber = trim((string) $row['student_number']);
        $email = strtolower(trim((string) $row['email']));
        $emailAddresses[$email] = $email;
        $studentNumberKey = strtolower($studentNumber);
        if ($studentNumber !== '' && isset($seenStudentNumbers[$studentNumberKey])) {
            $rows[$index]['processing_status'] = 'skipped';
            $rows[$index]['error_message'] = 'Duplicate identity in this file; first appears on row '
                . $seenStudentNumbers[$studentNumberKey] . '.';
            continue;
        }
        if (isset($seenEmails[$email])) {
            $rows[$index]['processing_status'] = 'review';
            $rows[$index]['error_message'] = 'Email is duplicated in this file; resolve the profile match manually.';
            continue;
        }
        $seenEmails[$email] = (int) $row['row_number'];
        if ($studentNumber !== '') {
            $seenStudentNumbers[$studentNumberKey] = (int) $row['row_number'];
            $studentNumbers[$studentNumberKey] = $studentNumber;
        }
    }

    $profilesByStudentNumber = [];
    foreach (array_chunk(array_values($studentNumbers), 300) as $chunkIndex => $chunk) {
        $placeholders = [];
        $parameters = [];
        foreach ($chunk as $index => $value) {
            $name = 'student_number_' . $chunkIndex . '_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = strtolower($value);
        }
        $profileQuery = $db->prepare(
            'SELECT id, student_number, first_name, middle_name, surname, suffix, email, classification
             FROM student_registry
             WHERE LOWER(student_number) IN (' . implode(', ', $placeholders) . ')'
        );
        $profileQuery->execute($parameters);
        foreach ($profileQuery->fetchAll() as $profile) {
            $profilesByStudentNumber[strtolower((string) $profile['student_number'])] = $profile;
        }
    }

    $profilesByEmail = [];
    foreach (array_chunk(array_values($emailAddresses), 300) as $chunkIndex => $chunk) {
        $placeholders = [];
        $parameters = [];
        foreach ($chunk as $index => $value) {
            $name = 'email_' . $chunkIndex . '_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = strtolower($value);
        }
        $profileQuery = $db->prepare(
            'SELECT id, student_number, first_name, middle_name, surname, suffix, email, classification
             FROM student_registry
             WHERE LOWER(email) IN (' . implode(', ', $placeholders) . ')'
        );
        $profileQuery->execute($parameters);
        foreach ($profileQuery->fetchAll() as $profile) {
            $profilesByEmail[strtolower((string) $profile['email'])][] = $profile;
        }
    }

    $profileIds = [];
    foreach ($rows as $index => $row) {
        if ($row['processing_status'] !== 'ready') {
            continue;
        }
        $studentNumber = trim((string) $row['student_number']);
        $email = strtolower(trim((string) $row['email']));
        if ($studentNumber !== '') {
            $profile = $profilesByStudentNumber[strtolower($studentNumber)] ?? null;
            if ($profile === null) {
                $emailMatches = $profilesByEmail[$email] ?? [];
                if (count($emailMatches) > 1) {
                    $rows[$index]['processing_status'] = 'review';
                    $rows[$index]['error_message'] = 'Email matches more than one profile; resolve the profile match manually.';
                    continue;
                }
                $profile = $emailMatches[0] ?? null;
            }
        } else {
            $emailMatches = $profilesByEmail[$email] ?? [];
            if (count($emailMatches) > 1) {
                $rows[$index]['processing_status'] = 'review';
                $rows[$index]['error_message'] = 'Email matches more than one profile; resolve the profile match manually.';
                continue;
            }
            $profile = $emailMatches[0] ?? null;
        }

        if ($profile !== null) {
            $rows[$index]['profile_id'] = (int) $profile['id'];
            $profileIds[(int) $profile['id']] = (int) $profile['id'];
            $conflicts = [];
            if ($studentNumber !== ''
                && strcasecmp((string) ($profile['student_number'] ?? ''), $studentNumber) !== 0) {
                $conflicts[] = 'student_number';
            }
            foreach ([
                'first_name',
                'middle_name',
                'surname',
                'suffix',
                'email',
            ] as $field) {
                $incoming = trim((string) ($row[$field] ?? ''));
                $existing = trim((string) ($profile[$field] ?? ''));
                if ($incoming !== '' && strcasecmp($incoming, $existing) !== 0) {
                    $conflicts[] = $field;
                }
            }
            if ($row['enrollment_status'] !== 'currently_enrolled'
                && $profile['classification'] !== $row['enrollment_status']) {
                $conflicts[] = 'classification';
            }
            if ($conflicts !== []) {
                $rows[$index]['processing_status'] = 'review';
                $rows[$index]['can_approve'] = !in_array('student_number', $conflicts, true);
                $rows[$index]['error_message'] = 'Existing profile differs in: '
                    . implode(', ', $conflicts) . '. '
                    . ($rows[$index]['can_approve']
                        ? 'Approving preserves existing profile details.'
                        : 'Resolve the Student ID conflict manually before importing.');
            }
        }
    }

    $enrollmentsByStudent = [];
    foreach (array_chunk(array_values($profileIds), 300) as $chunkIndex => $chunk) {
        $placeholders = [];
        $parameters = ['term_id' => $termId];
        foreach ($chunk as $index => $value) {
            $name = 'profile_id_' . $chunkIndex . '_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $value;
        }
        $enrollmentQuery = $db->prepare(
            'SELECT e.student_id, e.course_id, e.year_level, e.enrollment_status, c.name AS course_name
             FROM student_enrollments e
             JOIN student_import_batches b ON b.id = e.import_batch_id
             JOIN courses c ON c.id = e.course_id
             WHERE b.academic_term_id = :term_id
               AND e.student_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY e.id'
        );
        $enrollmentQuery->execute($parameters);
        foreach ($enrollmentQuery->fetchAll() as $enrollment) {
            $enrollmentsByStudent[(int) $enrollment['student_id']][] = $enrollment;
        }
    }

    foreach ($rows as $index => $row) {
        if (!in_array($row['processing_status'], ['ready', 'review'], true)
            || $row['profile_id'] === null) {
            continue;
        }
        $existingEnrollments = $enrollmentsByStudent[(int) $row['profile_id']] ?? [];
        if ($existingEnrollments === []) {
            continue;
        }
        $sameEnrollment = false;
        foreach ($existingEnrollments as $enrollment) {
            if (strcasecmp((string) $enrollment['course_name'], $course['name']) === 0
                && (int) $enrollment['year_level'] === (int) $row['year_level_number']
                && $enrollment['enrollment_status'] === $row['enrollment_status']) {
                $sameEnrollment = true;
                break;
            }
        }
        $rows[$index]['processing_status'] = $sameEnrollment ? 'skipped' : 'review';
        $rows[$index]['can_approve'] = false;
        $rows[$index]['error_message'] = $sameEnrollment
            ? 'An enrollment for this student already exists in the selected academic period.'
            : 'A conflicting enrollment exists in the selected academic period; resolve it manually.';
    }

    $summary = [
        'total' => count($rows),
        'ready' => 0,
        'review' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];
    foreach ($rows as $row) {
        if (isset($summary[$row['processing_status']])) {
            $summary[$row['processing_status']]++;
        }
    }

    return [
        'course' => $course,
        'rows' => $rows,
        'summary' => $summary,
    ];
}

function commit_student_import(
    PDO $db,
    int $termId,
    int $schoolCourseId,
    array $rows,
    array $approvedRowNumbers,
    int $importerId,
    string $importerColumn,
    ?string $originalFilename,
    string $changedByName = 'Registrar'
): array {
    if (!in_array($importerColumn, ['imported_by_personnel_id', 'imported_by_user_id'], true)) {
        throw new InvalidArgumentException('The student registrar account is unavailable.');
    }
    $approved = [];
    foreach ($approvedRowNumbers as $rowNumber) {
        $validated = filter_var($rowNumber, FILTER_VALIDATE_INT);
        if (!is_int($validated) || $validated < 1) {
            throw new InvalidArgumentException('The approved import rows are invalid. Preview the file again.');
        }
        $approved[$validated] = true;
    }

    return with_student_roster_lock($db, static function () use (
        $db,
        $termId,
        $schoolCourseId,
        $rows,
        $approved,
        $importerId,
        $importerColumn,
        $originalFilename,
        $changedByName
    ): array {
        $db->beginTransaction();
        try {
            $preview = preview_student_import($db, $termId, $schoolCourseId, $rows);
            $eligibleRowCount = 0;
            foreach ($preview['rows'] as $row) {
                if ($row['processing_status'] === 'ready'
                    || ($row['processing_status'] === 'review'
                        && $row['can_approve']
                        && isset($approved[(int) $row['row_number']]))) {
                    $eligibleRowCount++;
                }
            }
            if ($eligibleRowCount === 0) {
                throw new InvalidArgumentException(
                    'This file has no new eligible enrollments. Remove students already enrolled in this course and period, or approve eligible profile reviews, then preview again.'
                );
            }
            $course = roster_course($db, $schoolCourseId);
            $minYearLevel = 5;
            foreach ($preview['rows'] as $row) {
                if (is_int($row['year_level_number'] ?? null)) {
                    $minYearLevel = min($minYearLevel, $row['year_level_number']);
                }
            }
            $batchInsert = $db->prepare(
                'INSERT INTO student_import_batches
                    (academic_term_id, course_id, source, original_filename, ' . $importerColumn . ',
                     year_level, row_count, total_rows, skipped_rows, failed_rows, import_status, is_current)
                 VALUES (:term_id, :course_id, \'csv\', :original_filename, :importer_id,
                         :year_level, 0, :total_rows, 0, 0, \'completed\', :is_current)'
            );
            $batchInsert->execute([
                'term_id' => $termId,
                'course_id' => $course['roster_course_id'],
                'original_filename' => $originalFilename !== null
                    ? substr(basename($originalFilename), 0, 255)
                    : null,
                'importer_id' => $importerId,
                'year_level' => $minYearLevel === 5 ? 1 : $minYearLevel,
                'total_rows' => count($preview['rows']),
                'is_current' => 1,
            ]);
            $batchId = (int) $db->lastInsertId();

            $profileLookupByNumber = $db->prepare(
                'SELECT id FROM student_registry WHERE LOWER(student_number) = LOWER(:student_number) LIMIT 1 FOR UPDATE'
            );
            $profileLookupByEmail = $db->prepare(
                'SELECT id FROM student_registry WHERE LOWER(email) = LOWER(:email) ORDER BY id LIMIT 2 FOR UPDATE'
            );
            $profileInsert = $db->prepare(
                'INSERT INTO student_registry
                    (student_number, first_name, middle_name, surname, suffix, email,
                     classification, last_attended_academic_term_id)
                 VALUES (:student_number, :first_name, :middle_name, :surname, :suffix, :email,
                         :classification, :last_attended_term_id)'
            );
            $profileAuditInsert = $db->prepare(
                'INSERT INTO student_profile_audit
                    (student_id, import_batch_id, action, source, changed_by_name, changes_json)
                 VALUES (:student_id, :batch_id, \'created\', \'csv\', :changed_by_name, :changes_json)'
            );
            $enrollmentInsert = $db->prepare(
                'INSERT INTO student_enrollments
                    (student_id, import_batch_id, course_id, year_level, imported_student_number,
                     imported_first_name, imported_middle_name, imported_surname, imported_email, enrollment_status)
                 VALUES (:student_id, :batch_id, :course_id, :year_level, :student_number,
                         :first_name, :middle_name, :surname, :email, :enrollment_status)'
            );
            $membershipInsert = $db->prepare(
                'INSERT INTO student_roster_memberships
                    (import_batch_id, enrollment_id, academic_term_id, student_id, course_id, year_level, is_active)
                 VALUES (:batch_id, :enrollment_id, :term_id, :student_id, :course_id, :year_level, :is_active)'
            );
            $rowInsert = $db->prepare(
                'INSERT INTO student_import_rows
                    (batch_id, row_number, student_identifier, source_data, processing_status, error_message,
                     associated_student_id, associated_enrollment_id)
                 VALUES (:batch_id, :row_number, :student_number, :source_data, :processing_status, :error_message,
                         :student_id, :enrollment_id)'
            );
            $successCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            foreach ($preview['rows'] as $row) {
                $status = (string) $row['processing_status'];
                $approvedReview = $status === 'review'
                    && $row['can_approve']
                    && isset($approved[(int) $row['row_number']]);
                $enrollmentId = null;
                $studentId = $row['profile_id'] === null ? null : (int) $row['profile_id'];
                $errorMessage = $row['error_message'];

                if ($status === 'ready' || $approvedReview) {
                    if ($studentId === null) {
                        $createdProfile = false;
                        $studentNumber = trim((string) $row['student_number']);
                        if ($studentNumber !== '') {
                            $profileLookupByNumber->execute(['student_number' => $studentNumber]);
                            $studentIdValue = $profileLookupByNumber->fetchColumn();
                        } else {
                            $profileLookupByEmail->execute(['email' => $row['email']]);
                            $matches = $profileLookupByEmail->fetchAll(PDO::FETCH_COLUMN);
                            $studentIdValue = count($matches) === 1 ? $matches[0] : false;
                        }

                        if ($studentIdValue !== false) {
                            $studentId = (int) $studentIdValue;
                        } else {
                            $profileInsert->execute([
                                'student_number' => $studentNumber !== '' ? $studentNumber : null,
                                'first_name' => $row['first_name'],
                                'middle_name' => $row['middle_name'] !== '' ? $row['middle_name'] : null,
                                'surname' => $row['surname'],
                                'suffix' => $row['suffix'] !== '' ? $row['suffix'] : null,
                                'email' => $row['email'],
                                'classification' => $row['enrollment_status'] === 'currently_enrolled'
                                    ? 'not_currently_enrolled'
                                    : $row['enrollment_status'],
                                'last_attended_term_id' => $row['enrollment_status'] === 'currently_enrolled'
                                    ? null
                                    : $termId,
                            ]);
                            $studentId = (int) $db->lastInsertId();
                            $createdProfile = true;
                        }
                        if ($createdProfile) {
                            $changesJson = student_profile_audit_json([
                                'student_number' => $studentNumber !== '' ? $studentNumber : null,
                                'first_name' => $row['first_name'],
                                'middle_name' => $row['middle_name'],
                                'surname' => $row['surname'],
                                'suffix' => $row['suffix'],
                                'email' => $row['email'],
                                'classification' => $row['enrollment_status'] === 'currently_enrolled'
                                    ? 'not_currently_enrolled'
                                    : $row['enrollment_status'],
                                'last_attended_academic_term_id' => $row['enrollment_status'] === 'currently_enrolled'
                                    ? null
                                    : $termId,
                            ]);
                            $profileAuditInsert->execute([
                                'student_id' => $studentId,
                                'batch_id' => $batchId,
                                'changed_by_name' => substr($changedByName, 0, 150),
                                'changes_json' => $changesJson,
                            ]);
                        }
                    }

                    $enrollmentInsert->execute([
                        'student_id' => $studentId,
                        'batch_id' => $batchId,
                        'course_id' => $course['roster_course_id'],
                        'year_level' => $row['year_level_number'],
                        'student_number' => trim((string) $row['student_number']) !== ''
                            ? trim((string) $row['student_number'])
                            : null,
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'] !== '' ? $row['middle_name'] : null,
                        'surname' => $row['surname'],
                        'email' => $row['email'],
                        'enrollment_status' => $row['enrollment_status'],
                    ]);
                    $enrollmentId = (int) $db->lastInsertId();
                    $membershipInsert->execute([
                        'batch_id' => $batchId,
                        'enrollment_id' => $enrollmentId,
                        'term_id' => $termId,
                        'student_id' => $studentId,
                        'course_id' => $course['roster_course_id'],
                        'year_level' => $row['year_level_number'],
                        'is_active' => $row['enrollment_status'] === 'currently_enrolled' ? 1 : 0,
                    ]);
                    $status = 'imported';
                    $successCount++;
                    if ($approvedReview) {
                        $errorMessage = 'Approved after review; existing profile details were preserved.';
                    } else {
                        $errorMessage = null;
                    }
                } elseif ($status === 'review') {
                    $status = 'skipped';
                    $skippedCount++;
                } elseif ($status === 'failed') {
                    $failedCount++;
                } else {
                    $skippedCount++;
                }

                $rowInsert->execute([
                    'batch_id' => $batchId,
                    'row_number' => $row['row_number'],
                    'student_number' => trim((string) $row['student_number']) !== ''
                        ? trim((string) $row['student_number'])
                        : null,
                    'source_data' => student_profile_audit_json([
                        'student_number' => $row['student_number'],
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'],
                        'surname' => $row['surname'],
                        'suffix' => $row['suffix'],
                        'email' => $row['email'],
                        'year_level' => $row['year_level'],
                        'course' => $row['course'],
                        'enrollment_status' => $row['enrollment_status'],
                    ]),
                    'processing_status' => $status,
                    'error_message' => $errorMessage,
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId,
                ]);
            }

            $batchStatus = $failedCount === 0 && $skippedCount === 0
                ? 'completed'
                : ($successCount === 0 && $failedCount > 0 ? 'failed' : 'completed_with_errors');
            $batchUpdate = $db->prepare(
                'UPDATE student_import_batches
                 SET row_count = :row_count, skipped_rows = :skipped_rows,
                     failed_rows = :failed_rows, import_status = :import_status
                 WHERE id = :id'
            );
            $batchUpdate->execute([
                'row_count' => $successCount,
                'skipped_rows' => $skippedCount,
                'failed_rows' => $failedCount,
                'import_status' => $batchStatus,
                'id' => $batchId,
            ]);

            record_audit_event(
                $db,
                $importerId,
                $changedByName,
                'imported',
                'student_import_batch',
                (string) $batchId,
                'Imported student enrollment batch.',
                [
                    'academic_term_id' => $termId,
                    'course_id' => (int) $course['roster_course_id'],
                    'imported_rows' => $successCount,
                    'skipped_rows' => $skippedCount,
                    'failed_rows' => $failedCount,
                ]
            );
            $db->commit();
            return [
                'batch_id' => $batchId,
                'imported_rows' => $successCount,
                'skipped_rows' => $skippedCount,
                'failed_rows' => $failedCount,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}

function correct_active_roster(
    PDO $db,
    int $membershipId,
    string $action,
    ?int $targetSchoolCourseId,
    ?int $targetYearLevel,
    string $correctedBy,
    int $actorId = 0
): array {
    if (!in_array($action, ['delete', 'move'], true)) {
        throw new InvalidArgumentException('Choose a valid roster correction.');
    }

    return with_student_roster_lock($db, static function () use (
        $db,
        $membershipId,
        $action,
        $targetSchoolCourseId,
        $targetYearLevel,
        $correctedBy,
        $actorId
    ): array {
        $db->beginTransaction();
        try {
            $membershipQuery = $db->prepare(
                'SELECT m.id, m.academic_term_id, m.student_id, m.course_id, m.year_level,
                        s.student_number, b.id AS batch_id
                 FROM student_roster_memberships m
                 JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
                 JOIN academic_terms t ON t.id = m.academic_term_id AND t.is_current = 1
                 JOIN student_registry s ON s.id = m.student_id
                 WHERE m.id = :id AND m.is_active = 1
                 LIMIT 1
                 FOR UPDATE'
            );
            $membershipQuery->execute(['id' => $membershipId]);
            $membership = $membershipQuery->fetch();
            if (!$membership) {
                throw new InvalidArgumentException('Archived or inactive roster entries cannot be changed.');
            }

            $newCourseId = null;
            $newYearLevel = null;
            if ($action === 'move') {
                if (!is_int($targetSchoolCourseId) || !is_int($targetYearLevel)) {
                    throw new InvalidArgumentException('Choose an active course and year level.');
                }
                $targetCourse = roster_course($db, $targetSchoolCourseId);
                if (!in_array($targetYearLevel, $targetCourse['year_levels'], true)) {
                    throw new InvalidArgumentException('Choose an active year level for the selected course.');
                }
                $newCourseId = $targetCourse['roster_course_id'];
                $newYearLevel = $targetYearLevel;
                if ($newCourseId === (int) $membership['course_id']
                    && $newYearLevel === (int) $membership['year_level']) {
                    throw new InvalidArgumentException('The student is already in that course and year level.');
                }

                $duplicate = $db->prepare(
                    'SELECT 1
                     FROM student_roster_memberships m
                     JOIN student_import_batches b ON b.id = m.import_batch_id AND b.is_current = 1
                     WHERE m.academic_term_id = :term_id AND m.student_id = :student_id
                       AND m.is_active = 1 AND m.id <> :membership_id
                     LIMIT 1'
                );
                $duplicate->execute([
                    'term_id' => (int) $membership['academic_term_id'],
                    'student_id' => (int) $membership['student_id'],
                    'membership_id' => $membershipId,
                ]);
                if ($duplicate->fetchColumn() !== false) {
                    throw new InvalidArgumentException('This student already has an active roster entry for the semester.');
                }

                $update = $db->prepare(
                    'UPDATE student_roster_memberships
                     SET course_id = :course_id, year_level = :year_level
                     WHERE id = :id AND is_active = 1'
                );
                $update->execute([
                    'course_id' => $newCourseId,
                    'year_level' => $newYearLevel,
                    'id' => $membershipId,
                ]);
            } else {
                $update = $db->prepare(
                    'UPDATE student_roster_memberships SET is_active = 0 WHERE id = :id AND is_active = 1'
                );
                $update->execute(['id' => $membershipId]);
                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException('This student is no longer in the active roster.');
                }
            }

            $correctionInsert = $db->prepare(
                'INSERT INTO student_roster_corrections
                    (membership_id, correction_type, from_course_id, from_year_level,
                     to_course_id, to_year_level, corrected_by_name)
                 VALUES (:membership_id, :correction_type, :from_course_id, :from_year_level,
                         :to_course_id, :to_year_level, :corrected_by_name)'
            );
            $correctionInsert->execute([
                'membership_id' => $membershipId,
                'correction_type' => $action,
                'from_course_id' => (int) $membership['course_id'],
                'from_year_level' => (int) $membership['year_level'],
                'to_course_id' => $newCourseId,
                'to_year_level' => $newYearLevel,
                'corrected_by_name' => mb_substr(trim($correctedBy), 0, 150),
            ]);
            record_audit_event(
                $db,
                $actorId,
                $correctedBy,
                $action,
                'student_roster_membership',
                (string) $membershipId,
                $action === 'delete'
                    ? 'Removed a student from the active roster.'
                    : 'Moved a student to a different course or year level.',
                [
                    'from_course_id' => (int) $membership['course_id'],
                    'from_year_level' => (int) $membership['year_level'],
                    'to_course_id' => $newCourseId,
                    'to_year_level' => $newYearLevel,
                ]
            );
            $db->commit();

            return [
                'student_number' => (string) $membership['student_number'],
                'action' => $action,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    });
}
