<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/academic_years.php';
start_app_session();

$db = db();
$graduationYears = recent_graduation_years();
$courses = $db->query(
    "SELECT CASE WHEN acronym = '' THEN name ELSE CONCAT(acronym, ' - ', name) END
     FROM school_courses
     WHERE is_active = 1
     ORDER BY sort_order, name"
)->fetchAll(PDO::FETCH_COLUMN);
$documents = $db->query(
    'SELECT id, name, description, price, processing_min_days,
            processing_max_days, release_methods
     FROM documents
     WHERE is_active = 1
     ORDER BY name ASC'
)->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

function document_processing_time(array $document): string
{
    $minimum = (int) $document['processing_min_days'];
    $maximum = (int) $document['processing_max_days'];

    if ($maximum === 0) {
        return 'Same day';
    }

    return $maximum . ' ' . ($maximum === 1 ? 'weekday' : 'weekdays');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Request a Document</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">REGISTRAR SERVICES</p>
                <h1>Request a document</h1>
                <p class="intro">Browse the available documents and choose the one you need.</p>
                <p class="request-date-disclaimer"><?= $escape(request_processing_delay_notice()) ?></p>
            </div>
            <nav class="public-service-nav" aria-label="Registrar services">
                <a href="index.php" data-public-view="home">Home</a>
                <a href="index.php?view=request" data-public-view="request" aria-current="page">Request</a>
                <a href="index.php?view=track" data-public-view="track">Track</a>
            </nav>
        </header>

        <?php if ($documents === []): ?>
            <section class="portal-panel">
                <p class="empty-state">No documents are available at this time.</p>
            </section>
        <?php else: ?>
            <div class="table-toolbar document-search-toolbar">
                <label class="sr-only" for="public-document-search">Search available documents</label>
                <input id="public-document-search" class="live-search-input" type="search" placeholder="Search documents"
                       data-live-search="#public-document-grid" data-live-search-empty="public-document-no-results">
            </div>
            <div id="public-document-grid" class="document-grid">
                <?php foreach ($documents as $document): ?>
                    <?php $documentId = (int) $document['id']; ?>
                    <article class="document-card" data-live-search-row data-live-search-text="<?= $escape(implode(' ', [
                        $document['name'],
                        $document['description'],
                        $document['release_methods'],
                    ])) ?>">
                        <div class="document-card-heading">
                            <h2><?= $escape($document['name']) ?></h2>
                            <p class="document-price">PHP <?= $escape(number_format((float) $document['price'], 2)) ?></p>
                        </div>
                        <p class="document-description"><?= $escape($document['description']) ?></p>
                        <dl class="document-details">
                            <div>
                                <dt>Processing time</dt>
                                <dd><?= $escape(document_processing_time($document)) ?></dd>
                            </div>
                            <div>
                                <dt>Released by</dt>
                                <dd><?= $escape(implode(', ', array_filter(array_map('trim', explode(',', (string) $document['release_methods']))))) ?></dd>
                            </div>
                        </dl>
                        <button class="submit-button document-request-button" type="button"
                                data-document-id="<?= $documentId ?>"
                                data-document-name="<?= $escape($document['name']) ?>"
                                data-document-price="₱<?= $escape(number_format((float) $document['price'], ((float) $document['price'] === (float) (int) $document['price']) ? 0 : 2)) ?>">
                            Request <span aria-hidden="true">→</span>
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
            <p id="public-document-no-results" class="empty-state live-search-empty" hidden>No matching documents.</p>
        <?php endif; ?>

        <dialog class="request-dialog" id="request-dialog" aria-labelledby="request-dialog-title">
            <div class="request-dialog-heading">
                <span class="request-document-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M13 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9l-7-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="M13 3v7h7M12 13v6m-3-3h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <div class="request-dialog-title-group">
                    <h2 id="request-dialog-title">Request a Document</h2>
                    <p id="request-dialog-document"></p>
                </div>
                <button class="request-dialog-close" type="button" aria-label="Close dialog">×</button>
            </div>
            <div class="request-dialog-options">
                <button type="button" data-requester-type="currently_enrolled">Currently Enrolled</button>
                <span>or</span>
                <button type="button" id="alumni-option">Alumni</button>
            </div>
            <form class="alumni-request-form" id="alumni-request-form" method="post" action="verify-request.php" hidden>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="action" value="confirm_guest">
                <input type="hidden" name="requester_status" value="alumni">
                <input type="hidden" name="document_id" id="alumni-document-id">

                <div class="alumni-name-fields">
                    <div class="alumni-field">
                        <label for="alumni-first-name">FIRST NAME</label>
                        <input id="alumni-first-name" name="first_name" type="text" placeholder="First name" autocomplete="given-name" maxlength="80" required>
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-last-name">LAST NAME</label>
                        <input id="alumni-last-name" name="last_name" type="text" placeholder="Last name" autocomplete="family-name" maxlength="80" required>
                    </div>
                </div>
                <div class="alumni-field">
                    <label for="alumni-course">COURSE</label>
                    <select id="alumni-course" name="course" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= htmlspecialchars((string) $course, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $course, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alumni-field">
                    <label for="alumni-academic-year">ACADEMIC YEAR GRADUATED</label>
                    <select id="alumni-academic-year" name="academic_year" required>
                        <option value="">Select graduation year</option>
                        <?php foreach ($graduationYears as $graduationYear): ?>
                            <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <section class="alumni-record-fields" id="alumni-record-fields" data-record-fields hidden>
                    <h3>Personal information</h3>
                    <div class="alumni-name-fields">
                        <div class="alumni-field">
                            <label for="alumni-middle-name">MIDDLE NAME</label>
                            <input id="alumni-middle-name" name="middle_name" type="text" maxlength="100" autocomplete="additional-name">
                        </div>
                        <div class="alumni-field">
                            <label for="alumni-birthdate">BIRTHDATE</label>
                            <input id="alumni-birthdate" name="birthdate" type="date">
                        </div>
                        <div class="alumni-field">
                            <label for="alumni-gender">GENDER</label>
                            <select id="alumni-gender" name="gender">
                                <option value="">Select gender</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="alumni-field">
                            <label for="alumni-mobile-number">MOBILE NUMBER</label>
                            <input id="alumni-mobile-number" name="mobile_number" type="tel" maxlength="30" autocomplete="tel">
                        </div>
                    </div>
                    <h3>Address</h3>
                    <div class="alumni-field">
                        <label for="alumni-address-line">HOUSE NO. / STREET / SUBDIVISION</label>
                        <input id="alumni-address-line" name="address_line" type="text" maxlength="255" autocomplete="street-address">
                    </div>
                    <div class="alumni-record-grid" data-psgc-location>
                        <div class="alumni-field">
                            <label for="alumni-province">PROVINCE</label>
                            <select id="alumni-province" name="province" data-location="province">
                                <option value="">Loading provinces…</option>
                            </select>
                        </div>
                        <div class="alumni-field">
                            <label for="alumni-city">CITY / MUNICIPALITY</label>
                            <select id="alumni-city" name="city_municipality" data-location="city" disabled>
                                <option value="">Select city/municipality</option>
                            </select>
                        </div>
                        <div class="alumni-field">
                            <label for="alumni-barangay">BARANGAY</label>
                            <select id="alumni-barangay" name="barangay" data-location="barangay" disabled>
                                <option value="">Select barangay</option>
                            </select>
                        </div>
                        <p class="location-status" data-location-status role="status" hidden></p>
                    </div>
                    <h3>Schools attended</h3>
                    <div class="alumni-field">
                        <label for="alumni-elementary-school">ELEMENTARY SCHOOL</label>
                        <input id="alumni-elementary-school" name="elementary_school" type="text" maxlength="180">
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-elementary-year">ELEMENTARY YEAR GRADUATED</label>
                        <select id="alumni-elementary-year" name="elementary_year" data-graduation-year>
                            <option value="">Select graduation year</option>
                            <?php foreach ($graduationYears as $graduationYear): ?>
                                <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-junior-high-school">JUNIOR HIGH SCHOOL</label>
                        <input id="alumni-junior-high-school" name="junior_high_school" type="text" maxlength="180">
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-junior-high-year">JUNIOR HIGH YEAR GRADUATED</label>
                        <select id="alumni-junior-high-year" name="junior_high_year" data-graduation-year>
                            <option value="">Select graduation year</option>
                            <?php foreach ($graduationYears as $graduationYear): ?>
                                <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-senior-high-school">SENIOR HIGH SCHOOL</label>
                        <input id="alumni-senior-high-school" name="senior_high_school" type="text" maxlength="180">
                    </div>
                    <div class="alumni-field">
                        <label for="alumni-senior-high-year">SENIOR HIGH YEAR GRADUATED</label>
                        <select id="alumni-senior-high-year" name="senior_high_year" data-graduation-year>
                            <option value="">Select graduation year</option>
                            <?php foreach ($graduationYears as $graduationYear): ?>
                                <option value="<?= $escape($graduationYear) ?>"><?= $escape($graduationYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <h3>Purpose of request</h3>
                    <div class="alumni-field">
                        <label for="alumni-request-purpose">PURPOSE</label>
                        <select id="alumni-request-purpose" name="request_purpose" data-purpose-select>
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
                    <div class="alumni-field" data-purpose-other-field hidden>
                        <label for="alumni-purpose-other">PLEASE SPECIFY</label>
                        <input id="alumni-purpose-other" name="purpose_other" type="text" maxlength="80">
                    </div>
                </section>
                <div class="alumni-field">
                    <label for="alumni-email">EMAIL ADDRESS</label>
                    <input id="alumni-email" name="email" type="email" placeholder="name@example.com" autocomplete="email" maxlength="120" required>
                </div>
                <div class="alumni-form-actions">
                    <button class="alumni-cancel-button" type="button">Cancel</button>
                    <button class="alumni-continue-button" type="submit">Continue</button>
                </div>
            </form>
        </dialog>

        <footer class="page-footer"><a href="index.php?view=track" data-public-view="track">Track my Request</a></footer>
    </main>
    <script>
        const requestDialog = document.querySelector('#request-dialog');
        const requestDialogDocument = document.querySelector('#request-dialog-document');
        const requestDialogOptions = requestDialog.querySelector('.request-dialog-options');
        const alumniForm = document.querySelector('#alumni-request-form');
        const alumniDocumentId = document.querySelector('#alumni-document-id');
        const alumniOption = document.querySelector('#alumni-option');
        const recordFields = document.querySelector('#alumni-record-fields');
        const purposeSelect = alumniForm.querySelector('[data-purpose-select]');
        const purposeOtherField = alumniForm.querySelector('[data-purpose-other-field]');
        const purposeOtherInput = alumniForm.querySelector('#alumni-purpose-other');
        let selectedDocumentId;
        let isAcademicRecordRequest = false;

        function setParentDialogScrollLock(locked) {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'public-dialog-scroll-lock', locked }, window.location.origin);
            }
        }

        function closeRequestDialog() {
            requestDialog.close();
            setParentDialogScrollLock(false);
        }

        function resetRequestDialog() {
            requestDialogOptions.hidden = false;
            alumniForm.hidden = true;
            alumniForm.reset();
            if (recordFields) {
                recordFields.hidden = true;
                recordFields.querySelectorAll('input, select').forEach(field => {
                    field.required = false;
                });
            }
            purposeOtherField.hidden = true;
            purposeOtherInput.required = false;
            const provinceSelect = alumniForm.querySelector('[data-location="province"]');
            if (provinceSelect) {
                setTimeout(() => provinceSelect.dispatchEvent(new Event('change')), 0);
            }
        }

        document.querySelectorAll('.document-request-button').forEach(button => {
            button.addEventListener('click', () => {
                selectedDocumentId = button.dataset.documentId;
                alumniDocumentId.value = selectedDocumentId;
                isAcademicRecordRequest = ['transcript of records (tor)', 'honorable dismissal']
                    .includes(button.dataset.documentName.trim().toLocaleLowerCase());
                requestDialogDocument.textContent =
                    `${button.dataset.documentName} · ${button.dataset.documentPrice}`;
                resetRequestDialog();
                requestDialog.showModal();
                setParentDialogScrollLock(true);
            });
        });

        requestDialog.querySelector('.request-dialog-close').addEventListener('click', () => {
            closeRequestDialog();
        });
        requestDialog.addEventListener('close', () => setParentDialogScrollLock(false));
        requestDialog.addEventListener('cancel', () => setParentDialogScrollLock(false));
        window.addEventListener('pagehide', () => setParentDialogScrollLock(false));

        requestDialog.querySelector('[data-requester-type="currently_enrolled"]').addEventListener('click', () => {
                const url = new URL('request-form.php', window.location.href);
                url.searchParams.set('document_id', selectedDocumentId);
                url.searchParams.set('requester_type', 'currently_enrolled');
                window.location.assign(url);
        });

        alumniOption.addEventListener('click', () => {
            requestDialogOptions.hidden = true;
            alumniForm.hidden = false;
            recordFields.hidden = !isAcademicRecordRequest;
            if (isAcademicRecordRequest) {
                recordFields.querySelectorAll('input, select').forEach(field => {
                    field.required = !field.matches('[data-purpose-other-field] input, #alumni-middle-name');
                });
                purposeOtherInput.required = false;
            }
            document.querySelector('#alumni-first-name').focus();
        });

        purposeSelect.addEventListener('change', () => {
            const needsOther = purposeSelect.value === 'Others';
            purposeOtherField.hidden = !needsOther;
            purposeOtherInput.required = needsOther;
            if (!needsOther) {
                purposeOtherInput.value = '';
            }
        });
        purposeOtherInput.addEventListener('input', () => {
            const matchedPurpose = [...purposeSelect.options].find(option =>
                option.value && option.value !== 'Others'
                && option.value.toLocaleLowerCase() === purposeOtherInput.value.trim().toLocaleLowerCase());
            if (matchedPurpose) {
                purposeSelect.value = matchedPurpose.value;
                purposeOtherInput.value = '';
                purposeOtherField.hidden = true;
                purposeOtherInput.required = false;
            }
        });

        alumniForm.addEventListener('submit', event => {
            const locationsReady = !isAcademicRecordRequest
                || [...recordFields.querySelectorAll('[data-location]')].every(select => !select.disabled && select.value !== '');
            if (!locationsReady) {
                event.preventDefault();
                const locationStatus = recordFields.querySelector('[data-location-status]');
                locationStatus.textContent = 'Select a province, city/municipality, and barangay before continuing.';
                locationStatus.hidden = false;
            }
        });

        if (window.parent !== window) {
            document.body.classList.add('public-shell-embedded');
        }
        document.querySelectorAll('[data-public-view]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (window.parent === window) {
                    return;
                }
                event.preventDefault();
                window.parent.postMessage({
                    type: 'public-navigation',
                    view: link.dataset.publicView,
                }, window.location.origin);
            });
        });

        alumniForm.querySelector('.alumni-cancel-button').addEventListener('click', () => {
            resetRequestDialog();
            alumniOption.focus();
        });

        requestDialog.addEventListener('click', event => {
            if (event.target === requestDialog) {
                closeRequestDialog();
            }
        });
    </script>
    <script src="assets/js/psgc-location-selects.js" defer></script>
    <script src="assets/js/live-search.js"></script>
    <script src="assets/js/navigation-guard.js" defer></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
