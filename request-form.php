<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
start_app_session();

$db = db();
$academicYears = available_academic_years($db);
$graduationYears = recent_graduation_years();
$courses = $db->query(
    "SELECT CASE WHEN acronym = '' THEN name ELSE CONCAT(acronym, ' - ', name) END
     FROM school_courses
     WHERE is_active = 1
     ORDER BY sort_order, name"
)->fetchAll(PDO::FETCH_COLUMN);
$selectedDocumentId = filter_var($_GET['document_id'] ?? null, FILTER_VALIDATE_INT);
$requesterType = (string) ($_GET['requester_type'] ?? '');
$showRequesterChoice = $requesterType === '';
$document = is_int($selectedDocumentId) && $selectedDocumentId > 0
    ? active_request_document($db, $selectedDocumentId)
    : null;
$requiresAcademicHistory = $document !== null && document_requires_academic_history($document);
if (!$document || (!$showRequesterChoice
    && !in_array($requesterType, ['currently_enrolled', 'not_currently_enrolled'], true))) {
    http_response_code(404);
    exit('The selected document or requester type is not available.');
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <meta name="csrf-token" content="<?= $escape(csrf_token()) ?>">
    <title>Request a Document</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="request-form-page">
    <main class="page-shell request-form-shell">
        <section class="login-card request-card" aria-labelledby="page-title">
            <div class="brand-mark" aria-hidden="true">R</div>
            <p class="eyebrow">REQUEST A DOCUMENT</p>
            <h1 id="page-title"><?= $escape($document['name']) ?></h1>
            <?php if ($showRequesterChoice): ?>
                <p class="intro">Choose the option that describes your current student status.</p>
                <nav class="requester-choice" aria-label="Choose student status">
                    <a href="request-form.php?document_id=<?= (int) $document['id'] ?>&amp;requester_type=currently_enrolled">
                        Currently Enrolled
                    </a>
                    <span>or</span>
                    <a href="request-form.php?document_id=<?= (int) $document['id'] ?>&amp;requester_type=not_currently_enrolled">
                        Alumni
                    </a>
                </nav>
            <?php elseif ($requesterType === 'currently_enrolled'): ?>
                <p class="intro">Enter your Student ID to verify your currently enrolled student record.</p>
                <div class="field">
                    <label for="student_no">Student ID</label>
                    <input id="student_no" type="text" autocomplete="off" maxlength="64"
                           data-document-id="<?= (int) $document['id'] ?>" autofocus>
                </div>
                <p class="lookup-status" id="lookup-status" role="status" aria-live="polite"></p>
                <div id="student-result" hidden></div>
                <?php if ($requiresAcademicHistory): ?>
                    <form id="enrolled-record-form" class="alumni-record-fields" method="post"
                          action="verify-request.php" data-loading-form hidden>
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                        <input type="hidden" name="student_no">
                        <h2>Complete the request details</h2>
                        <p class="intro">Your Student ID is verified. Complete these details to request the document.</p>
                        <div class="alumni-record-grid">
                            <div class="field">
                                <label for="enrolled-birthdate">Birthdate</label>
                                <input id="enrolled-birthdate" name="birthdate" type="date" required>
                            </div>
                            <div class="field">
                                <label for="enrolled-gender">Gender</label>
                                <select id="enrolled-gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="enrolled-mobile-number">Mobile number</label>
                                <input id="enrolled-mobile-number" name="mobile_number" type="tel" maxlength="30" autocomplete="tel" required>
                            </div>
                            <div class="field">
                                <label for="enrolled-address-line">House no. / street / subdivision</label>
                                <input id="enrolled-address-line" name="address_line" type="text" maxlength="255" autocomplete="street-address" required>
                            </div>
                        </div>
                        <div class="alumni-record-grid" data-psgc-location>
                            <div class="field">
                                <label for="enrolled-province">Province</label>
                                <select id="enrolled-province" name="province" data-location="province" required>
                                    <option value="">Loading provinces…</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="enrolled-city">City / municipality</label>
                                <select id="enrolled-city" name="city_municipality" data-location="city" required disabled>
                                    <option value="">Select city/municipality</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="enrolled-barangay">Barangay</label>
                                <select id="enrolled-barangay" name="barangay" data-location="barangay" required disabled>
                                    <option value="">Select barangay</option>
                                </select>
                            </div>
                            <p class="location-status" data-location-status role="status" hidden></p>
                        </div>
                        <?php foreach ([
                            'elementary' => 'Elementary',
                            'junior_high' => 'Junior high',
                            'senior_high' => 'Senior high',
                        ] as $level => $label): ?>
                            <div class="alumni-record-grid">
                                <div class="field">
                                    <label for="enrolled-<?= $level ?>-school"><?= $label ?> school</label>
                                    <input id="enrolled-<?= $level ?>-school" name="<?= $level ?>_school" type="text" maxlength="180" required>
                                </div>
                                <div class="field">
                                    <label for="enrolled-<?= $level ?>-year"><?= $label ?> year graduated</label>
                                    <select id="enrolled-<?= $level ?>-year" name="<?= $level ?>_year" data-graduation-year required>
                                        <option value="">Select graduation year</option>
                                        <?php foreach ($graduationYears as $graduationYear): ?>
                                            <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="field">
                            <label for="enrolled-request-purpose">Purpose</label>
                            <select id="enrolled-request-purpose" name="request_purpose" data-purpose-select required>
                                <option value="">Select purpose</option>
                                <option value="Evaluation">Evaluation</option>
                                <option value="Employment">Employment</option>
                                <option value="Scholarship">Scholarship</option>
                                <option value="Board Examination">Board Examination</option>
                                <option value="Transfer Out">Transfer Out</option>
                                <option value="Further Studies">Further Studies</option>
                                <option value="Personal Records">Personal Records</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="field" data-purpose-other-field hidden>
                            <label for="enrolled-purpose-other">Please specify</label>
                            <input id="enrolled-purpose-other" name="purpose_other" type="text" maxlength="80">
                        </div>
                        <label class="confirm-check">
                            <input type="checkbox" name="confirm_details" value="yes" required>
                            <span>I confirm that the information I entered is correct.</span>
                        </label>
                        <button class="submit-button" type="submit" data-loading-button
                                data-loading-text="Sending verification code…">
                            Continue to email verification <span aria-hidden="true">→</span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="intro"><?= $requiresAcademicHistory
                    ? 'Complete the alumni details below.'
                    : 'Choose your previous student status and enter your details.' ?></p>
                <form id="former-form" method="post" action="verify-request.php" data-loading-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="confirm_guest">
                    <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">

                    <?php if ($requiresAcademicHistory): ?>
                        <input type="hidden" name="requester_status" value="alumni">
                    <?php else: ?>
                    <div class="field">
                        <label for="requester_status">Previous student status</label>
                        <select id="requester_status" name="requester_status" required>
                            <option value="">Choose your previous status</option>
                            <option value="alumni">Alumni</option>
                            <option value="dropped_out">Dropped out</option>
                            <option value="transferred_out">Transferred out</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="field">
                        <label for="first_name">First name</label>
                        <input id="first_name" name="first_name" type="text" autocomplete="given-name" maxlength="80" required>
                    </div>
                    <?php if ((string) $document['name'] !== 'Transcript of Records (TOR)'): ?>
                        <div class="field">
                            <label for="middle_name">Middle name (if applicable)</label>
                            <input id="middle_name" name="middle_name" type="text" autocomplete="additional-name" maxlength="100">
                        </div>
                    <?php endif; ?>
                    <div class="field">
                        <label for="last_name">Surname</label>
                        <input id="last_name" name="last_name" type="text" autocomplete="family-name" maxlength="80" required>
                    </div>
                    <div class="field">
                        <label for="course">Course</label>
                        <select id="course" name="course" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $escape($course) ?>"><?= $escape($course) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($requiresAcademicHistory): ?>
                        <div class="field">
                            <label for="birthdate">Birthdate</label>
                            <input id="birthdate" name="birthdate" type="date" required>
                        </div>
                        <div class="field">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" required>
                                <option value="">Select gender</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="mobile_number">Mobile number</label>
                            <input id="mobile_number" name="mobile_number" type="tel" maxlength="30" autocomplete="tel" required>
                        </div>
                        <div class="field">
                            <label for="address_line">House no. / street / subdivision</label>
                            <input id="address_line" name="address_line" type="text" maxlength="255" autocomplete="street-address" required>
                        </div>
                        <div data-psgc-location>
                            <div class="field">
                                <label for="province">Province</label>
                                <select id="province" name="province" data-location="province" required>
                                    <option value="">Loading provinces…</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="city">City / municipality</label>
                                <select id="city" name="city_municipality" data-location="city" required disabled>
                                    <option value="">Select city/municipality</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="barangay">Barangay</label>
                                <select id="barangay" name="barangay" data-location="barangay" required disabled>
                                    <option value="">Select barangay</option>
                                </select>
                            </div>
                            <p class="location-status" data-location-status role="status" hidden></p>
                        </div>
                        <?php foreach ([
                            'elementary' => 'Elementary',
                            'junior_high' => 'Junior high',
                            'senior_high' => 'Senior high',
                        ] as $level => $label): ?>
                            <div class="field">
                                <label for="<?= $level ?>_school"><?= $label ?> school</label>
                                <input id="<?= $level ?>_school" name="<?= $level ?>_school" type="text" maxlength="180" required>
                            </div>
                            <div class="field">
                                <label for="<?= $level ?>_year"><?= $label ?> year graduated</label>
                                <select id="<?= $level ?>_year" name="<?= $level ?>_year" data-graduation-year required>
                                    <option value="">Select graduation year</option>
                                    <?php foreach ($graduationYears as $graduationYear): ?>
                                        <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        <div class="field">
                            <label for="request_purpose">Purpose</label>
                            <select id="request_purpose" name="request_purpose" data-purpose-select required>
                                <option value="">Select purpose</option>
                                <option value="Evaluation">Evaluation</option>
                                <option value="Employment">Employment</option>
                                <option value="Scholarship">Scholarship</option>
                                <option value="Board Examination">Board Examination</option>
                                <option value="Transfer Out">Transfer Out</option>
                                <option value="Further Studies">Further Studies</option>
                                <option value="Personal Records">Personal Records</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="field" data-purpose-other-field hidden>
                            <label for="purpose_other">Please specify</label>
                            <input id="purpose_other" name="purpose_other" type="text" maxlength="80">
                        </div>
                    <?php endif; ?>
                    <?php if (!$requiresAcademicHistory): ?>
                        <div class="field">
                            <label for="year_level">Year level attended</label>
                            <input id="year_level" name="year_level" type="text" placeholder="e.g. 2nd year" maxlength="40" required>
                        </div>
                    <?php endif; ?>
                    <div class="field">
                        <label for="academic_year"><?= $requiresAcademicHistory ? 'College year graduated' : 'Year graduated / last academic year attended' ?></label>
                        <select id="academic_year" name="academic_year" required>
                            <option value="">Select graduation year</option>
                            <?php foreach (($requiresAcademicHistory ? $graduationYears : $academicYears) as $academicYear): ?>
                                <option value="<?= $escape($academicYear) ?>"><?= $escape($academicYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="email">Email address for verification</label>
                        <input id="email" name="email" type="email" autocomplete="email" maxlength="120" required>
                    </div>
                    <label class="confirm-check">
                        <input type="checkbox" name="confirm_details" value="yes" required>
                        <span>I confirm that the information I entered is correct.</span>
                    </label>
                    <button class="submit-button" type="submit" data-loading-button
                            data-loading-text="Sending verification code…">
                        Continue to email verification <span aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>

            <p class="setup-link"><a href="request.php">Back to documents</a></p>
        </section>
        <footer class="page-footer">Email addresses can only be updated at the Registrar.</footer>
    </main>
    <script>
        const studentInput = document.querySelector('#student_no');
        const lookupStatus = document.querySelector('#lookup-status');
        const resultContainer = document.querySelector('#student-result');
        const enrolledRecordForm = document.querySelector('#enrolled-record-form');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        if (studentInput && lookupStatus && resultContainer) {
            let lookupTimer;
            let activeLookup;

            function showMessage(message, isError = false) {
                lookupStatus.textContent = message;
                lookupStatus.classList.toggle('lookup-error', isError);
            }

            function addDetail(list, label, value) {
                const row = document.createElement('div');
                const term = document.createElement('dt');
                const description = document.createElement('dd');
                term.textContent = label;
                description.textContent = value;
                row.append(term, description);
                list.append(row);
            }

            function showStudent(student) {
                const details = document.createElement('dl');
                details.className = 'student-details';
                addDetail(details, 'Student ID', student.student_no);
                addDetail(details, 'Full name', student.full_name);
                addDetail(details, 'Course', student.course);
                addDetail(details, 'Year level', student.year_level);
                addDetail(details, 'Email on file', student.masked_email);

                if (enrolledRecordForm) {
                    enrolledRecordForm.elements.student_no.value = student.student_no;
                    resultContainer.replaceChildren(details);
                    resultContainer.hidden = false;
                    enrolledRecordForm.hidden = false;
                    return;
                }

                const form = document.createElement('form');
                form.method = 'post';
                form.action = 'verify-request.php';
                form.dataset.loadingForm = '';
                const hiddenFields = {
                    csrf_token: csrfToken,
                    action: 'confirm',
                    document_id: studentInput.dataset.documentId,
                    student_no: student.student_no
                };
                for (const [name, value] of Object.entries(hiddenFields)) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    hidden.value = value;
                    form.append(hidden);
                }

                const label = document.createElement('label');
                label.className = 'confirm-check';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'confirm_details';
                checkbox.value = 'yes';
                checkbox.required = true;
                const text = document.createElement('span');
                text.textContent = 'Yes, these details are correct and belong to me.';
                label.append(checkbox, text);

                const button = document.createElement('button');
                button.className = 'submit-button';
                button.type = 'submit';
                button.dataset.loadingButton = '';
                button.dataset.loadingText = 'Sending verification code…';
                button.append(document.createTextNode('Continue to email verification '));
                const arrow = document.createElement('span');
                arrow.setAttribute('aria-hidden', 'true');
                arrow.textContent = '→';
                button.append(arrow);
                form.append(label, button);

                resultContainer.replaceChildren(details, form);
                resultContainer.hidden = false;
            }

            if (enrolledRecordForm) {
                const purposeSelect = enrolledRecordForm.querySelector('[data-purpose-select]');
                const otherField = enrolledRecordForm.querySelector('[data-purpose-other-field]');
                const otherInput = otherField.querySelector('input');
                const locationStatus = enrolledRecordForm.querySelector('[data-location-status]');

                purposeSelect.addEventListener('change', () => {
                    const needsOther = purposeSelect.value === 'Others';
                    otherField.hidden = !needsOther;
                    otherInput.required = needsOther;
                    if (!needsOther) otherInput.value = '';
                });
                enrolledRecordForm.addEventListener('reset', () => {
                    otherField.hidden = true;
                    otherInput.required = false;
                    locationStatus.hidden = true;
                    locationStatus.textContent = '';
                });
                otherInput.addEventListener('input', () => {
                    const match = [...purposeSelect.options].find(option =>
                        option.value && option.value !== 'Others'
                        && option.value.toLocaleLowerCase() === otherInput.value.trim().toLocaleLowerCase());
                    if (match) {
                        purposeSelect.value = match.value;
                        otherInput.value = '';
                        otherInput.required = false;
                        otherField.hidden = true;
                    }
                });
                enrolledRecordForm.addEventListener('submit', event => {
                    const locationsReady = [...enrolledRecordForm.querySelectorAll('[data-location]')]
                        .every(select => !select.disabled && select.value !== '');
                    if (!locationsReady) {
                        event.preventDefault();
                        const locationStatus = enrolledRecordForm.querySelector('[data-location-status]');
                        locationStatus.textContent = 'Select a province, city/municipality, and barangay before continuing.';
                        locationStatus.hidden = false;
                    }
                });
            }

            studentInput.addEventListener('input', () => {
                clearTimeout(lookupTimer);
                if (activeLookup) {
                    activeLookup.abort();
                }
                resultContainer.replaceChildren();
                resultContainer.hidden = true;
                if (enrolledRecordForm) {
                    enrolledRecordForm.hidden = true;
                    enrolledRecordForm.reset();
                }

                const studentNumber = studentInput.value.trim();
                if (studentNumber.length < 3) {
                    showMessage('');
                    return;
                }
                showMessage('Checking student ID…');
                lookupTimer = setTimeout(async () => {
                    activeLookup = new AbortController();
                    try {
                        const response = await fetch('student-lookup.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                            body: new URLSearchParams({
                                csrf_token: csrfToken,
                                document_id: studentInput.dataset.documentId,
                                student_no: studentNumber
                            }),
                            signal: activeLookup.signal
                        });
                        const result = await response.json();
                        if (studentInput.value.trim() !== studentNumber) {
                            return;
                        }
                        if (!response.ok || !result.ok) {
                            showMessage(result.message || 'Student ID could not be checked. Try again.', true);
                            return;
                        }
                        showMessage('Student record found. Confirm the details before continuing.');
                        showStudent(result.student);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            showMessage('Could not check the student ID. Check your connection and try again.', true);
                        }
                    }
                }, 400);
            });
        }
    </script>
    <?php if ($requiresAcademicHistory && $requesterType === 'not_currently_enrolled'): ?>
        <script>
            const formerForm = document.querySelector('#former-form');
            const purposeSelect = formerForm.querySelector('[data-purpose-select]');
            const otherField = formerForm.querySelector('[data-purpose-other-field]');
            const otherInput = otherField.querySelector('input');

            purposeSelect.addEventListener('change', () => {
                const needsOther = purposeSelect.value === 'Others';
                otherField.hidden = !needsOther;
                otherInput.required = needsOther;
                if (!needsOther) otherInput.value = '';
            });
            otherInput.addEventListener('input', () => {
                const match = [...purposeSelect.options].find(option =>
                    option.value && option.value !== 'Others'
                    && option.value.toLocaleLowerCase() === otherInput.value.trim().toLocaleLowerCase());
                if (match) {
                    purposeSelect.value = match.value;
                    otherInput.value = '';
                    otherInput.required = false;
                    otherField.hidden = true;
                }
            });
            formerForm.addEventListener('submit', event => {
                const locationsReady = [...formerForm.querySelectorAll('[data-location]')]
                    .every(select => !select.disabled && select.value !== '');
                if (!locationsReady) {
                    event.preventDefault();
                    const locationStatus = formerForm.querySelector('[data-location-status]');
                    locationStatus.textContent = 'Select a province, city/municipality, and barangay before continuing.';
                    locationStatus.hidden = false;
                }
            });
        </script>
    <?php endif; ?>
    <?php if ($requiresAcademicHistory): ?>
        <script src="assets/js/psgc-location-selects.js" defer></script>
    <?php endif; ?>
    <script src="assets/js/form-loading.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
</body>
</html>
