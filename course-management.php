<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/student_roster.php';
start_app_session();
require_personnel(['admin']);

$db = db();
$collegeLevelQuery = $db->prepare('SELECT id FROM school_levels WHERE name = :name LIMIT 1');
$collegeLevelQuery->execute(['name' => 'COLLEGE']);
$collegeLevelId = (int) $collegeLevelQuery->fetchColumn();
if ($collegeLevelId < 1) {
    throw new RuntimeException('The COLLEGE school level is not configured.');
}

$error = '';
$notice = $_SESSION['course_management_notice'] ?? '';
unset($_SESSION['course_management_notice']);
$periodActions = ['activate_period'];
$periodAction = $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', $periodActions, true);
$openAcademicPeriods = false;

if ($periodAction) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    try {
        $action = (string) $_POST['action'];
        $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($termId)) {
            throw new InvalidArgumentException('Select an academic period to activate.');
        }
        $term = activate_academic_term($db, $termId);
        $notice = $term['academic_year'] . ' · '
            . ($term['semester'] === 1 ? '1st' : '2nd')
            . ' semester is now active. Only this period validates currently enrolled students.';

        $_SESSION['course_management_notice'] = $notice;
        header('Location: course-management.php');
        exit;
    } catch (PDOException $exception) {
        error_log('Academic period management database error: ' . $exception->getMessage());
        $error = 'The academic period could not be saved. Check the database and try again.';
        $openAcademicPeriods = true;
    } catch (InvalidArgumentException | RuntimeException $exception) {
        $error = $exception->getMessage();
        $openAcademicPeriods = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$periodAction) {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $courseId = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $acronym = is_string($_POST['acronym'] ?? null) ? strtoupper(trim($_POST['acronym'])) : '';
    $durationYears = filter_var($_POST['duration_years'] ?? null, FILTER_VALIDATE_INT);
    $isActive = ($_POST['is_active'] ?? null) === '1' ? 1 : 0;
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';

    if (!in_array($action, ['save', 'delete'], true)) {
        http_response_code(400);
        exit('Invalid course action.');
    }

    if ($action === 'delete') {
        if (!is_int($courseId) || $courseId < 1) {
            http_response_code(400);
            exit('Choose a valid course.');
        }

        $lockAcquired = false;
        try {
            $lockAcquired = (int) $db->query("SELECT GET_LOCK('emer_student_roster_import', 10)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new InvalidArgumentException('A student roster import is running. Please try again shortly.');
            }

            $db->beginTransaction();
            $courseLookup = $db->prepare(
                'SELECT name, acronym
                 FROM school_courses
                 WHERE id = :id AND level_id = :level_id
                 FOR UPDATE'
            );
            $courseLookup->execute([
                'id' => $courseId,
                'level_id' => $collegeLevelId,
            ]);
            $course = $courseLookup->fetch();
            if ($course === false) {
                throw new InvalidArgumentException('That course could not be found.');
            }
            $legacyCourseName = trim($course['acronym'] . ' - ' . $course['name']);

            $recordCheck = $db->prepare(
                'SELECT EXISTS (
                    SELECT 1
                    FROM courses c
                    WHERE c.name = :legacy_name
                      AND (
                          EXISTS (SELECT 1 FROM student_import_batches b WHERE b.course_id = c.id)
                          OR EXISTS (SELECT 1 FROM student_enrollments e WHERE e.course_id = c.id)
                      )
                )'
            );
            $recordCheck->execute(['legacy_name' => $legacyCourseName]);
            if ((int) $recordCheck->fetchColumn() === 1) {
                throw new InvalidArgumentException(
                    'This course cannot be deleted because student roster records exist. Set it inactive instead.'
                );
            }
            if (!verify_personnel_password($db, $currentPassword)) {
                throw new InvalidArgumentException(
                    $currentPassword === ''
                        ? 'Enter your current password to delete this course.'
                        : 'Your current password is incorrect.'
                );
            }

            $removeLegacyCourse = $db->prepare('DELETE FROM courses WHERE name = :name');
            $removeLegacyCourse->execute(['name' => $legacyCourseName]);
            $removeYearLevels = $db->prepare('DELETE FROM school_year_levels WHERE course_id = :course_id');
            $removeYearLevels->execute(['course_id' => $courseId]);
            $removeCourse = $db->prepare(
                'DELETE FROM school_courses WHERE id = :id AND level_id = :level_id'
            );
            $removeCourse->execute([
                'id' => $courseId,
                'level_id' => $collegeLevelId,
            ]);
            if ($removeCourse->rowCount() !== 1) {
                throw new InvalidArgumentException('That course could not be found.');
            }

            $db->commit();
            $_SESSION['course_management_notice'] = 'Course deleted.';
        } catch (InvalidArgumentException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Course deletion database error: ' . $exception->getMessage());
            $error = 'The course could not be deleted. Check its records and try again.';
        } finally {
            if ($lockAcquired) {
                $lockReleased = (int) $db->query("SELECT RELEASE_LOCK('emer_student_roster_import')")->fetchColumn() === 1;
                if (!$lockReleased) {
                    error_log('Course deletion could not release the student roster import lock.');
                    $error = 'The course operation finished, but the roster lock could not be released. Please contact the administrator.';
                }
            }
        }

        if ($error === '' && isset($_SESSION['course_management_notice'])) {
            header('Location: course-management.php');
            exit;
        }
    } elseif ($name === '' || mb_strlen($name) > 120) {
        $error = 'Enter a course name of no more than 120 characters.';
    } elseif (!preg_match('/^[A-Z0-9](?:[A-Z0-9 .-]{0,18}[A-Z0-9])$/D', $acronym)) {
        $error = 'Enter a course acronym using 2 to 20 letters, numbers, spaces, periods, or hyphens.';
    } elseif (mb_strlen($acronym . ' - ' . $name) > 120) {
        $error = 'The course name and acronym together must be no more than 120 characters.';
    } elseif (!is_int($durationYears) || $durationYears < 1 || $durationYears > 4) {
        $error = 'Choose a course duration from 1 to 4 years.';
    } elseif (($courseId === false || $courseId === null) && isset($_POST['course_id'])) {
        $error = 'Choose a valid course.';
    } elseif ($courseId !== false && $courseId !== null && $courseId < 1) {
        $error = 'Choose a valid course.';
    } else {
        $lockAcquired = false;
        $successMessage = '';
        try {
            $lockAcquired = (int) $db->query("SELECT GET_LOCK('emer_student_roster_import', 10)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                throw new InvalidArgumentException('A student roster import is running. Please try again shortly.');
            }

            $db->beginTransaction();
            if ($courseId === false || $courseId === null) {
                if (!verify_personnel_password($db, $currentPassword)) {
                    throw new InvalidArgumentException(
                        $currentPassword === ''
                            ? 'Enter your current password to confirm this course and duration.'
                            : 'Your current password is incorrect.'
                    );
                }
                $saveCourse = $db->prepare(
                    'INSERT INTO school_courses (level_id, name, acronym, duration_years, is_active)
                     VALUES (:level_id, :name, :acronym, :duration_years, :is_active)'
                );
                $saveCourse->execute([
                    'level_id' => $collegeLevelId,
                    'name' => $name,
                    'acronym' => $acronym,
                    'duration_years' => $durationYears,
                    'is_active' => $isActive,
                ]);
                $courseId = (int) $db->lastInsertId();
                $successMessage = 'Course added.';
            } else {
                $currentCourse = $db->prepare(
                    'SELECT name, acronym, duration_years, is_active
                     FROM school_courses
                     WHERE id = :id AND level_id = :level_id
                     FOR UPDATE'
                );
                $currentCourse->execute([
                    'id' => $courseId,
                    'level_id' => $collegeLevelId,
                ]);
                $previousCourse = $currentCourse->fetch();
                if ($previousCourse === false) {
                    throw new InvalidArgumentException('That course could not be found.');
                }
                if ($previousCourse['name'] === $name
                    && $previousCourse['acronym'] === $acronym
                    && (int) $previousCourse['duration_years'] === $durationYears
                    && (int) $previousCourse['is_active'] === $isActive) {
                    throw new InvalidArgumentException('No changes were made. Cancel editing without saving.');
                }
                $requiresPassword = (int) $previousCourse['duration_years'] !== $durationYears
                    || ((int) $previousCourse['is_active'] === 1 && $isActive === 0);
                if ($requiresPassword && !verify_personnel_password($db, $currentPassword)) {
                    throw new InvalidArgumentException(
                        $currentPassword === ''
                            ? 'Enter your current password to change the course duration or deactivate it.'
                            : 'Your current password is incorrect.'
                    );
                }

                $saveCourse = $db->prepare(
                    'UPDATE school_courses
                     SET name = :name, acronym = :acronym,
                         duration_years = :duration_years, is_active = :is_active
                     WHERE id = :id AND level_id = :level_id'
                );
                $saveCourse->execute([
                    'name' => $name,
                    'acronym' => $acronym,
                    'duration_years' => $durationYears,
                    'is_active' => $isActive,
                    'id' => $courseId,
                    'level_id' => $collegeLevelId,
                ]);
                $previousLegacyName = trim($previousCourse['acronym'] . ' - ' . $previousCourse['name']);
                $updatedLegacyName = trim($acronym . ' - ' . $name);
                if ($previousLegacyName !== $updatedLegacyName) {
                    $renameRecordCourse = $db->prepare('UPDATE courses SET name = :name WHERE name = :previous_name');
                    $renameRecordCourse->execute([
                        'name' => $updatedLegacyName,
                        'previous_name' => $previousLegacyName,
                    ]);
                }
                $successMessage = 'Course details saved.';
            }

            $legacyCourse = $db->prepare(
                'INSERT INTO courses (name) VALUES (:name)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $legacyCourse->execute(['name' => trim($acronym . ' - ' . $name)]);

            $findYearLevel = $db->prepare(
                'SELECT id FROM school_year_levels
                 WHERE course_id = :course_id AND year_number = :year_number
                 FOR UPDATE'
            );
            $insertYearLevel = $db->prepare(
                'INSERT INTO school_year_levels (course_id, year_number, label, is_active)
                 VALUES (:course_id, :year_number, :label, :is_active)'
            );
            $updateYearLevel = $db->prepare(
                'UPDATE school_year_levels
                 SET label = :label, is_active = :is_active
                 WHERE id = :id AND course_id = :course_id'
            );
            $yearLabels = [1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year'];
            for ($year = 1; $year <= 4; $year++) {
                $findYearLevel->execute(['course_id' => $courseId, 'year_number' => $year]);
                $existingYearLevelId = $findYearLevel->fetchColumn();
                $yearParameters = [
                    'course_id' => $courseId,
                    'year_number' => $year,
                    'label' => $yearLabels[$year],
                    'is_active' => $year <= $durationYears ? 1 : 0,
                ];
                if ($existingYearLevelId === false) {
                    $insertYearLevel->execute($yearParameters);
                } else {
                    $updateYearLevel->execute([
                        'id' => (int) $existingYearLevelId,
                        'course_id' => $courseId,
                        'label' => $yearLabels[$year],
                        'is_active' => $year <= $durationYears ? 1 : 0,
                    ]);
                }
            }

            $db->commit();
        } catch (InvalidArgumentException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $error = 'A course with that name already exists.';
            } else {
                error_log('Course management database error: ' . $exception->getMessage());
                $error = 'The course could not be saved. Check the database and try again.';
            }
        } finally {
            if ($lockAcquired) {
                $lockReleased = (int) $db->query("SELECT RELEASE_LOCK('emer_student_roster_import')")->fetchColumn() === 1;
                if (!$lockReleased) {
                    error_log('Course save could not release the student roster import lock.');
                    $error = 'The course operation finished, but the roster lock could not be released. Please contact the administrator.';
                }
            }
        }

        if ($error === '' && $successMessage !== '') {
            $_SESSION['course_management_notice'] = $successMessage;
            header('Location: course-management.php');
            exit;
        }
    }
}

$courseQuery = $db->prepare(
    'SELECT id, name, acronym, duration_years, is_active
     FROM school_courses
     WHERE level_id = :level_id
     ORDER BY sort_order, name'
);
$courseQuery->execute(['level_id' => $collegeLevelId]);
$courses = $courseQuery->fetchAll();
foreach ($courses as &$course) {
    $course['id'] = (int) $course['id'];
    $course['duration_years'] = (int) $course['duration_years'];
    $course['is_active'] = (int) $course['is_active'];
}
unset($course);
ensure_current_academic_periods($db);
$academicTermOptions = $db->query(
    'SELECT t.id, t.is_current, ay.start_year, ay.end_year, t.semester
     FROM academic_terms t
     JOIN academic_years ay ON ay.id = t.academic_year_id
     ORDER BY ay.start_year DESC, t.semester DESC'
)->fetchAll();
$currentAcademicTerm = null;
foreach ($academicTermOptions as $termOption) {
    if ((int) $termOption['is_current'] === 1) {
        $currentAcademicTerm = $termOption;
        break;
    }
}
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Course Management</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">ADMIN PORTAL</p>
                <h1>Course management</h1>
            </div>
            <button class="secondary-button" type="button"
                    data-open-course-dialog="academic-period-dialog">
                Manage semesters
            </button>
        </header>

        <?php if ($notice !== ''): ?>
            <p class="notice notice-success toast-notification" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>

        <section class="portal-panel" aria-labelledby="add-course-title">
            <h2 id="add-course-title">Course catalog</h2>
            <?php if ($courses !== []): ?>
                <div class="table-toolbar">
                    <label class="sr-only" for="course-search">Search courses</label>
                    <input id="course-search" class="live-search-input" type="search" placeholder="Search courses"
                           data-live-search="#course-table" data-live-search-empty="course-no-results">
                </div>
            <?php endif; ?>
            <div class="table-wrap course-list-wrap">
                <table id="course-table" class="data-table editable-table course-table">
                    <thead>
                        <tr>
                            <th scope="col">Course</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="course-table-toolbar">
                                <button class="secondary-button course-add-button" type="button"
                                        data-open-course-dialog="course-create-dialog">
                                    Add course
                                </button>
                            </td>
                        </tr>
                        <?php if ($courses === []): ?>
                            <tr><td class="empty-state">No college courses are configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                                <tr data-live-search-row data-live-search-text="<?= $escape(implode(' ', [
                                    $course['name'],
                                    $course['acronym'],
                                    $course['duration_years'] . '-year course',
                                    $course['is_active'] === 1 ? 'Active' : 'Inactive',
                                ])) ?>">
                                    <td>
                                        <button class="course-card" type="button"
                                                data-course-details="<?= $course['id'] ?>"
                                                data-course-title="<?= $escape($course['acronym'] . ' - ' . $course['name']) ?>"
                                                data-course-duration="<?= $course['duration_years'] ?>-year course"
                                                title="View course details">
                                            <span class="course-card-title"><?= $escape($course['acronym'] . ' - ' . $course['name']) ?></span>
                                            <span class="course-card-duration"><?= $course['duration_years'] ?>-year course</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <dialog class="workflow-dialog management-dialog academic-period-dialog" id="academic-period-dialog"
                    aria-labelledby="academic-period-title"
                    data-open-on-load="<?= $openAcademicPeriods || (($_GET['periods'] ?? '') === '1') ? 'true' : 'false' ?>">
                <div class="workflow-dialog-heading">
                    <div>
                        <p class="eyebrow">ACADEMIC PERIODS</p>
                        <h2 id="academic-period-title">Manage semesters</h2>
                    </div>
                    <button class="workflow-dialog-close" type="button" data-close-course-dialog
                            aria-label="Close academic periods" title="Close">×</button>
                </div>
                <p class="intro">
                    Semester records for the current academic year are added automatically. Select one period below
                    and activate it to validate currently enrolled students.
                </p>
                <?php if ($currentAcademicTerm !== null): ?>
                    <p class="academic-period-current">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="m8 12 2.5 2.5L16 9"></path>
                        </svg>
                        <span>Currently active</span>
                        <strong>
                            <?= $escape($currentAcademicTerm['start_year'] . '-' . $currentAcademicTerm['end_year']
                                . ' · ' . ((int) $currentAcademicTerm['semester'] === 1 ? '1st' : '2nd')
                                . ' semester') ?>
                        </strong>
                    </p>
                <?php else: ?>
                    <p class="academic-period-current is-inactive">
                        <span>No semester is currently active</span>
                    </p>
                <?php endif; ?>
                <?php if ($academicTermOptions === []): ?>
                    <p class="empty-state">No academic periods have been created yet.</p>
                <?php else: ?>
                    <form method="post" action="course-management.php" data-loading-form
                          data-confirm-period-activation>
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="activate_period">
                        <fieldset class="academic-period-list">
                            <legend>Existing periods</legend>
                        <?php foreach ($academicTermOptions as $termOption): ?>
                            <?php
                            $termLabel = $termOption['start_year'] . '-' . $termOption['end_year'] . ' · '
                                . ((int) $termOption['semester'] === 1 ? '1st' : '2nd') . ' semester';
                            $isCurrentTerm = (int) $termOption['is_current'] === 1;
                            ?>
                            <label class="academic-period-option">
                                <input type="radio" name="term_id" value="<?= (int) $termOption['id'] ?>"
                                       <?= $isCurrentTerm ? 'checked' : '' ?> required>
                                <span class="academic-period-label"><?= $escape($termLabel) ?></span>
                                <span class="academic-period-status <?= $isCurrentTerm ? 'is-active' : '' ?>">
                                    <?= $isCurrentTerm ? 'Active' : 'Inactive' ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        </fieldset>
                        <div class="course-inline-actions">
                            <button class="secondary-button" type="button" data-close-course-dialog>Cancel</button>
                            <button class="table-action-button" type="submit" disabled>
                                Activate selected semester
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </dialog>
            <dialog class="workflow-dialog management-dialog course-details-dialog" id="course-details-dialog"
                    aria-labelledby="course-details-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">COURSE DETAILS</p><h2 id="course-details-title" data-course-details-title></h2></div>
                    <button class="workflow-dialog-close" type="button" data-close-course-dialog
                            aria-label="Close dialog" title="Close">×</button>
                </div>
                <p class="course-details-duration" data-course-details-duration></p>
                <div class="course-details-actions">
                    <button class="table-action-button" type="button" data-course-details-edit>
                        Edit this course
                    </button>
                </div>
            </dialog>
            <dialog class="workflow-dialog management-dialog" id="course-create-dialog" aria-labelledby="course-create-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">COURSE SETUP</p><h2 id="course-create-title">Add course</h2></div>
                    <button class="workflow-dialog-close" type="button" data-close-course-dialog
                            aria-label="Close dialog" title="Close">×</button>
                </div>
                <form class="course-inline-editor course-form" method="post" action="course-management.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save">
                    <label><span>Course name</span><input name="name" type="text" maxlength="120" required></label>
                    <label><span>Acronym</span><input name="acronym" type="text" maxlength="20" required></label>
                    <label><span>Program duration</span>
                        <select name="duration_years" required>
                            <option value="" selected disabled>Select duration</option>
                            <?php for ($duration = 1; $duration <= 4; $duration++): ?>
                                <option value="<?= $duration ?>"><?= $duration ?>-year course</option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label class="course-active-option">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked><span>Active</span>
                    </label>
                    <label class="course-password-confirmation">
                        <span>Current password (required to add this course)</span>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </label>
                    <div class="course-inline-actions">
                        <button class="secondary-button" type="button" data-close-course-dialog>Cancel</button>
                        <button class="table-action-button" type="submit">Add course</button>
                        </button>
                    </div>
                </form>
            </dialog>
            <?php foreach ($courses as $course): ?>
                <dialog class="workflow-dialog management-dialog" id="course-edit-<?= $course['id'] ?>"
                        aria-labelledby="course-edit-title-<?= $course['id'] ?>">
                    <div class="workflow-dialog-heading">
                        <div><p class="eyebrow">COURSE SETUP</p><h2 id="course-edit-title-<?= $course['id'] ?>">Edit course</h2></div>
                        <button class="workflow-dialog-close" type="button" data-close-course-dialog
                                aria-label="Close dialog" title="Close">×</button>
                    </div>
                    <form id="<?= $escape('course-' . $course['id']) ?>" class="course-inline-editor course-form" method="post" action="course-management.php"
                          data-original-duration="<?= $course['duration_years'] ?>"
                          data-original-active="<?= $course['is_active'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        <label><span>Course name</span><input name="name" type="text" maxlength="120"
                               value="<?= $escape($course['name']) ?>" required></label>
                        <label><span>Acronym</span><input name="acronym" type="text" maxlength="20"
                               value="<?= $escape($course['acronym']) ?>" required></label>
                        <label><span>Program duration</span>
                            <select name="duration_years" required>
                                <?php for ($duration = 1; $duration <= 4; $duration++): ?>
                                    <option value="<?= $duration ?>" <?= $course['duration_years'] === $duration ? 'selected' : '' ?>>
                                        <?= $duration ?>-year course
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="course-active-option">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" <?= $course['is_active'] === 1 ? 'checked' : '' ?>>
                            <span>Active</span>
                        </label>
                        <label class="course-password-confirmation" data-course-edit-password hidden>
                            <span>Current password</span>
                            <input name="current_password" type="password" autocomplete="current-password">
                        </label>
                        <div class="course-inline-actions">
                            <button class="secondary-button" type="button" data-close-course-dialog>Cancel</button>
                            <button class="danger-button course-icon-button" type="button"
                                    data-course-delete-confirm="<?= $escape($course['name']) ?>"
                                    data-course-id="<?= $course['id'] ?>"
                                    aria-label="Delete course" title="Delete course">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path>
                                </svg>
                            </button>
                            <button class="table-action-button" type="submit">Save changes</button>
                        </div>
                    </form>
                </dialog>
            <?php endforeach; ?>
            <dialog class="workflow-dialog management-dialog delete-confirmation-dialog"
                    id="course-deactivate-dialog" aria-labelledby="course-deactivate-title">
                <div class="workflow-dialog-heading">
                    <div>
                        <p class="eyebrow">CONFIRM DEACTIVATION</p>
                        <h2 id="course-deactivate-title">Deactivate course?</h2>
                    </div>
                    <button class="workflow-dialog-close" type="button" data-course-deactivate-cancel
                            aria-label="Close dialog" title="Close">×</button>
                </div>
                <p class="delete-confirmation-message" data-course-deactivate-message></p>
                <form data-course-deactivate-confirm>
                    <label class="course-password-confirmation">
                        <span>Current password</span>
                        <input type="password" autocomplete="current-password" required>
                    </label>
                    <div class="course-inline-actions delete-confirmation-actions">
                        <button class="secondary-button" type="button" data-course-deactivate-cancel>Cancel</button>
                        <button class="danger-button" type="submit">Deactivate course</button>
                    </div>
                </form>
            </dialog>
            <dialog class="workflow-dialog management-dialog delete-confirmation-dialog" id="course-delete-dialog"
                    aria-labelledby="course-delete-dialog-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">CONFIRM DELETION</p><h2 id="course-delete-dialog-title">Delete course?</h2></div>
                    <button class="workflow-dialog-close" type="button" data-course-delete-cancel
                            aria-label="Close dialog" title="Close">×</button>
                </div>
                <p class="delete-confirmation-message" data-course-delete-message></p>
                <form method="post" action="course-management.php" data-course-delete>
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="course_id" value="">
                    <label class="course-password-confirmation">
                        <span>Current password</span>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </label>
                    <div class="course-inline-actions delete-confirmation-actions">
                        <button class="secondary-button" type="button" data-course-delete-cancel>Cancel</button>
                        <button class="danger-button course-icon-button" type="submit"
                                aria-label="Confirm course deletion" title="Confirm delete">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </dialog>
            <?php if ($courses !== []): ?>
                <p id="course-no-results" class="empty-state live-search-empty" hidden>No matching courses.</p>
            <?php endif; ?>
        </section>
    </main>
    <script src="assets/js/form-change-feedback.js"></script>
    <script>
        const academicPeriodDialog = document.getElementById('academic-period-dialog');
        const academicPeriodForm = academicPeriodDialog.querySelector('[data-confirm-period-activation]');
        if (academicPeriodForm) {
            const periodSubmitButton = academicPeriodForm.querySelector('button[type="submit"]');
            const periodCancelButton = academicPeriodForm.querySelector('[data-close-course-dialog]');
            window.AppFormChangeFeedback.watch(
                academicPeriodForm,
                periodSubmitButton,
                periodCancelButton,
                () => {
                    const selectedPeriod = academicPeriodForm.querySelector('input[name="term_id"]:checked');
                    return selectedPeriod !== null && !selectedPeriod.defaultChecked;
                }
            );
            academicPeriodDialog.addEventListener('close', () => academicPeriodForm.reset());
        }
        if (academicPeriodDialog?.dataset.openOnLoad === 'true') {
            academicPeriodDialog.showModal();
        }

        document.querySelectorAll('[data-confirm-period-activation]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const selectedPeriod = form.querySelector('input[name="term_id"]:checked');
                if (!selectedPeriod) {
                    return;
                }

                const periodLabel = selectedPeriod.closest('label')
                    ?.querySelector('span')
                    ?.textContent
                    ?.trim();
                if (!periodLabel || !window.confirm(
                    `Activate ${periodLabel}?\n\nOnly this period will validate currently enrolled students.`
                )) {
                    event.preventDefault();
                }
            });
        });

        const courseDetailsDialog = document.getElementById('course-details-dialog');
        const courseDetailsTitle = courseDetailsDialog.querySelector('[data-course-details-title]');
        const courseDetailsDuration = courseDetailsDialog.querySelector('[data-course-details-duration]');
        const courseDetailsEdit = courseDetailsDialog.querySelector('[data-course-details-edit]');

        document.querySelectorAll('[data-course-details]').forEach((button) => {
            button.addEventListener('click', () => {
                courseDetailsTitle.textContent = button.dataset.courseTitle;
                courseDetailsDuration.textContent = button.dataset.courseDuration;
                courseDetailsEdit.dataset.editCourseDialog = `course-edit-${button.dataset.courseDetails}`;
                courseDetailsDialog.showModal();
            });
        });

        courseDetailsEdit.addEventListener('click', () => {
            const editDialog = document.getElementById(courseDetailsEdit.dataset.editCourseDialog);
            courseDetailsDialog.close();
            editDialog?.showModal();
            editDialog?.querySelector('input[name="name"]')?.focus();
        });

        document.querySelectorAll('[data-open-course-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.getElementById(button.dataset.openCourseDialog);
                dialog?.showModal();
                dialog?.querySelector('input[name="name"]')?.focus();
            });
        });

        document.querySelectorAll('[data-course-delete-confirm]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = button.closest('.management-dialog');
                const editPassword = dialog.querySelector('[data-course-edit-password]');
                if (editPassword) {
                    editPassword.hidden = true;
                    editPassword.querySelector('input[name="current_password"]').required = false;
                }
                courseDeleteDialog.querySelector('[data-course-delete-message]').textContent =
                    `Delete "${button.dataset.courseDeleteConfirm}"? Courses with existing student records cannot be deleted.`;
                courseDeleteId.value = button.dataset.courseId;
                courseDeletePassword.value = '';
                courseDeleteDialog.showModal();
                courseDeletePassword.focus();
            });
        });

        const courseDeactivateDialog = document.getElementById('course-deactivate-dialog');
        const courseDeactivateForm = courseDeactivateDialog.querySelector('[data-course-deactivate-confirm]');
        const courseDeactivatePassword = courseDeactivateForm.querySelector('input[type="password"]');
        let pendingCourseDeactivation = null;

        document.querySelectorAll('.course-form[data-original-active="1"]').forEach((form) => {
            const active = form.querySelector('input[type="checkbox"][name="is_active"]');
            const password = form.querySelector('[name="current_password"]');
            const courseName = form.querySelector('input[name="name"]');

            active.addEventListener('change', () => {
                if (active.checked) {
                    return;
                }

                active.checked = true;
                pendingCourseDeactivation = { form, active, password };
                courseDeactivateDialog.querySelector('[data-course-deactivate-message]').textContent =
                    `Deactivate "${courseName.value}"? This course will no longer be available for new student rosters.`;
                courseDeactivatePassword.value = '';
                courseDeactivateDialog.showModal();
                courseDeactivatePassword.focus();
            });
        });

        courseDeactivateForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!courseDeactivateForm.reportValidity() || !pendingCourseDeactivation) {
                return;
            }

            const { form, active, password } = pendingCourseDeactivation;
            active.checked = false;
            password.value = courseDeactivatePassword.value;
            courseDeactivateDialog.close();
            pendingCourseDeactivation = null;
            form.requestSubmit();
        });

        courseDeactivateDialog.querySelectorAll('[data-course-deactivate-cancel]').forEach((button) => {
            button.addEventListener('click', () => courseDeactivateDialog.close());
        });
        courseDeactivateDialog.addEventListener('close', () => {
            courseDeactivatePassword.value = '';
            pendingCourseDeactivation = null;
        });

        const courseDeleteDialog = document.getElementById('course-delete-dialog');
        const courseDeleteId = courseDeleteDialog.querySelector('input[name="course_id"]');
        const courseDeletePassword = courseDeleteDialog.querySelector('input[name="current_password"]');
        courseDeleteDialog.querySelectorAll('[data-course-delete-cancel]').forEach((button) => {
            button.addEventListener('click', () => courseDeleteDialog.close());
        });
        courseDeleteDialog.addEventListener('close', () => {
            courseDeletePassword.value = '';
            const sourceEditDialog = document.getElementById(`course-edit-${courseDeleteId.value}`);
            const form = sourceEditDialog?.querySelector('.course-form[data-original-duration]');
            if (form) {
                coursePasswordRequirements.get(form)?.();
            }
        });

        const coursePasswordRequirements = new WeakMap();
        document.querySelectorAll('.course-form[data-original-duration]').forEach((form) => {
            const duration = form.querySelector('[name="duration_years"]');
            const active = form.querySelector('input[type="checkbox"][name="is_active"]');
            const password = form.querySelector('[name="current_password"]');
            const name = form.querySelector('[name="name"]');
            const acronym = form.querySelector('[name="acronym"]');
            const saveButton = form.querySelector('button[type="submit"]');
            const originalValues = {
                name: name.defaultValue,
                acronym: acronym.defaultValue,
                duration: duration.defaultValue,
                active: active.defaultChecked
            };
            const hasChanges = () => name.value !== originalValues.name
                || acronym.value !== originalValues.acronym
                || duration.value !== originalValues.duration
                || active.checked !== originalValues.active;
            const updatePasswordRequirement = () => {
                const passwordRequired = duration.value !== form.dataset.originalDuration;
                password.required = passwordRequired;
                password.closest('[data-course-edit-password]').hidden = !passwordRequired;
            };

            coursePasswordRequirements.set(form, updatePasswordRequirement);
            updatePasswordRequirement();
            duration.addEventListener('change', updatePasswordRequirement);
            active.addEventListener('change', updatePasswordRequirement);
            window.AppFormChangeFeedback.watch(
                form,
                saveButton,
                form.querySelector('[data-close-course-dialog]'),
                hasChanges
            );
            form.addEventListener('submit', (event) => {
                if (!hasChanges()) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.management-dialog').forEach((dialog) => {
            dialog.querySelectorAll('[data-close-course-dialog]').forEach((button) => {
                button.addEventListener('click', () => dialog.close());
            });
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
            dialog.addEventListener('close', () => {
                const form = dialog.querySelector('.course-form');
                form?.reset();
                const password = form?.querySelector('[name="current_password"]');
                if (password) {
                    const isCreateForm = !form.querySelector('[name="course_id"]');
                    password.required = isCreateForm;
                    password.closest('.course-password-confirmation').hidden = !isCreateForm;
                }
                const editPassword = dialog.querySelector('[data-course-edit-password]');
                if (editPassword) {
                    editPassword.hidden = true;
                }
            });
        });
    </script>
    <script src="assets/js/live-search.js"></script>
    <script src="assets/js/form-loading.js" defer></script>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
</body>
</html>
