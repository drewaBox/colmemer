<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
start_app_session();
require_personnel(['admin']);

$db = db();
$error = '';
$notice = $_SESSION['document_management_notice'] ?? '';
unset($_SESSION['document_management_notice']);

function document_form_values(array $source): array
{
    $stringValue = static fn (string $key): string =>
        is_string($source[$key] ?? null) ? trim($source[$key]) : '';
    $integerValue = static function (string $key, int $default = 0) use ($source): int|false {
        $value = $source[$key] ?? $default;
        return is_string($value) || is_int($value)
            ? filter_var($value, FILTER_VALIDATE_INT)
            : false;
    };

    $name = $stringValue('name');
    $description = $stringValue('description');
    $price = $stringValue('price');
    $processingDays = $integerValue('processing_days');
    $releaseMethod = $stringValue('release_method');

    return [
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'processing_days' => $processingDays,
        'release_method' => $releaseMethod,
        'is_active' => ($source['is_active'] ?? null) === '1' ? 1 : 0,
    ];
}

function document_release_method_value(string $methods): string
{
    $selectedMethods = array_values(array_unique(array_filter(array_map('trim', explode(',', $methods)))));
    if (in_array('Both', $selectedMethods, true)
        || (in_array('Hardcopy', $selectedMethods, true) && in_array('Softcopy', $selectedMethods, true))) {
        return 'Both';
    }

    return in_array('Hardcopy', $selectedMethods, true) ? 'Hardcopy'
        : (in_array('Softcopy', $selectedMethods, true) ? 'Softcopy' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';

    if ($action === 'delete') {
        if (!is_int($documentId) || $documentId < 1) {
            $error = 'Choose a valid document.';
        } else {
            try {
                $db->beginTransaction();
                $documentLock = $db->prepare('SELECT id FROM documents WHERE id = :id FOR UPDATE');
                $documentLock->execute(['id' => $documentId]);
                if ($documentLock->fetchColumn() === false) {
                    throw new InvalidArgumentException('That document could not be found.');
                }
                $referenceCheck = $db->prepare(
                    'SELECT (
                        EXISTS (SELECT 1 FROM request_items WHERE document_id = :request_document_id)
                        OR EXISTS (SELECT 1 FROM student_request_otps WHERE document_id = :otp_document_id)
                    )'
                );
                $referenceCheck->execute([
                    'request_document_id' => $documentId,
                    'otp_document_id' => $documentId,
                ]);
                if ((int) $referenceCheck->fetchColumn() === 1) {
                    throw new InvalidArgumentException(
                        'This document is linked to request history and cannot be deleted. Set it inactive instead.'
                    );
                }
                if (!verify_personnel_password($db, $currentPassword)) {
                    throw new InvalidArgumentException(
                        $currentPassword === ''
                            ? 'Enter your current password to delete this document.'
                            : 'Your current password is incorrect.'
                    );
                }

                $requirementsTableExists = $db->query("SHOW TABLES LIKE 'document_requirements'")->fetchColumn() !== false;
                if ($requirementsTableExists) {
                    $removeRequirements = $db->prepare('DELETE FROM document_requirements WHERE document_id = :id');
                    $removeRequirements->execute(['id' => $documentId]);
                }

                $releaseMethodsTableExists = $db->query("SHOW TABLES LIKE 'document_release_methods'")->fetchColumn() !== false;
                if ($releaseMethodsTableExists) {
                    $removeReleaseMethods = $db->prepare('DELETE FROM document_release_methods WHERE document_id = :id');
                    $removeReleaseMethods->execute(['id' => $documentId]);
                }

                $removeDocument = $db->prepare('DELETE FROM documents WHERE id = :id');
                $removeDocument->execute(['id' => $documentId]);
                if ($removeDocument->rowCount() !== 1) {
                    throw new InvalidArgumentException('That document could not be deleted.');
                }

                $db->commit();
                $_SESSION['document_management_notice'] = 'Document deleted.';
                header('Location: document-management.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Document deletion database error: ' . $exception->getMessage());
                $error = 'The document could not be deleted. It may still be linked to a record.';
            }
        }
    } elseif ($action === 'toggle') {
        if (!is_int($documentId) || $documentId < 1) {
            $error = 'Choose a valid document.';
        } else {
            try {
                $db->beginTransaction();
                $documentLock = $db->prepare('SELECT is_active FROM documents WHERE id = :id FOR UPDATE');
                $documentLock->execute(['id' => $documentId]);
                $isActive = $documentLock->fetchColumn();
                if ($isActive === false) {
                    throw new InvalidArgumentException('That document could not be found.');
                }
                if (!verify_personnel_password($db, $currentPassword)) {
                    throw new InvalidArgumentException(
                        $currentPassword === ''
                            ? 'Enter your current password to change document availability.'
                            : 'Your current password is incorrect.'
                    );
                }

                $toggle = $db->prepare('UPDATE documents SET is_active = :is_active WHERE id = :id');
                $toggle->execute([
                    'is_active' => (int) $isActive === 1 ? 0 : 1,
                    'id' => $documentId,
                ]);
                if ($toggle->rowCount() !== 1) {
                    throw new InvalidArgumentException('The document availability did not change.');
                }
                $db->commit();
                $_SESSION['document_management_notice'] = 'Document availability updated.';
                header('Location: document-management.php');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('Document availability database error: ' . $exception->getMessage());
                $error = 'The document availability could not be updated. Please try again.';
            }
        }
    } elseif (in_array($action, ['create', 'update'], true)) {
        $values = document_form_values($_POST);
        $priceValid = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $values['price']) === 1;
        if ($values['name'] === '' || mb_strlen($values['name']) > 150) {
            $error = 'Enter a document name of no more than 150 characters.';
        } elseif (mb_strlen($values['description']) > 5000) {
            $error = 'Description must be no more than 5,000 characters.';
        } elseif (!$priceValid) {
            $error = 'Enter a valid non-negative price with up to two decimal places.';
        } elseif (!is_int($values['processing_days']) || $values['processing_days'] < 0
            || $values['processing_days'] > 3650) {
            $error = 'Enter processing weekdays from 0 to 3,650.';
        } elseif (!in_array($values['release_method'], ['Hardcopy', 'Softcopy', 'Both'], true)) {
            $error = 'Select a release method.';
        } elseif ($action === 'update' && (!is_int($documentId) || $documentId < 1)) {
            $error = 'Choose a valid document to update.';
        } else {
            try {
                $parameters = [
                    'name' => $values['name'],
                    'description' => $values['description'],
                    'price' => $values['price'],
                    'processing_min_days' => $values['processing_days'],
                    'processing_max_days' => $values['processing_days'],
                    'release_methods' => $values['release_method'] === 'Both'
                        ? 'Hardcopy,Softcopy'
                        : $values['release_method'],
                    'is_active' => $values['is_active'],
                ];
                if ($action === 'create') {
                    if (!verify_personnel_password($db, $currentPassword)) {
                        throw new InvalidArgumentException(
                            $currentPassword === ''
                                ? 'Enter your current password to add a document to the priced catalog.'
                                : 'Your current password is incorrect.'
                        );
                    }
                    $save = $db->prepare(
                        'INSERT INTO documents
                            (name, description, price, processing_min_days,
                             processing_max_days, release_methods, is_active)
                         VALUES
                            (:name, :description, :price, :processing_min_days,
                             :processing_max_days, :release_methods, :is_active)'
                    );
                    $save->execute($parameters);
                    $message = 'Document added to the catalog.';
                } else {
                    $db->beginTransaction();
                    $documentExists = $db->prepare(
                        'SELECT name, description, price, processing_min_days,
                                processing_max_days, release_methods, is_active
                         FROM documents
                         WHERE id = :id
                         FOR UPDATE'
                    );
                    $documentExists->execute(['id' => $documentId]);
                    $previousDocument = $documentExists->fetch();
                    if ($previousDocument === false) {
                        throw new InvalidArgumentException('That document could not be found.');
                    }
                    $previousPrice = number_format((float) $previousDocument['price'], 2, '.', '');
                    $updatedPrice = number_format((float) $values['price'], 2, '.', '');
                    $hasChanges = $previousDocument['name'] !== $values['name']
                        || $previousDocument['description'] !== $values['description']
                        || $previousPrice !== $updatedPrice
                        || (int) $previousDocument['processing_max_days'] !== $values['processing_days']
                        || document_release_method_value((string) $previousDocument['release_methods'])
                            !== $values['release_method']
                        || (int) $previousDocument['is_active'] !== $values['is_active'];
                    if (!$hasChanges) {
                        throw new InvalidArgumentException('No changes were made. Cancel editing without saving.');
                    }
                    $requiresPassword = $previousPrice !== $updatedPrice
                        || (int) $previousDocument['processing_min_days'] !== $values['processing_days']
                        || (int) $previousDocument['processing_max_days'] !== $values['processing_days']
                        || document_release_method_value((string) $previousDocument['release_methods'])
                            !== $values['release_method']
                        || ((int) $previousDocument['is_active'] === 1 && $values['is_active'] === 0);
                    if ($requiresPassword && !verify_personnel_password($db, $currentPassword)) {
                        throw new InvalidArgumentException(
                            $currentPassword === ''
                                ? 'Enter your current password to change pricing, processing, release methods, or deactivate this document.'
                                : 'Your current password is incorrect.'
                        );
                    }
                    $parameters['id'] = $documentId;
                    $save = $db->prepare(
                        'UPDATE documents
                         SET name = :name, description = :description,
                             price = :price, processing_min_days = :processing_min_days,
                             processing_max_days = :processing_max_days, release_methods = :release_methods,
                             is_active = :is_active
                         WHERE id = :id'
                    );
                    $save->execute($parameters);
                    $db->commit();
                    $message = 'Document details saved.';
                }
                $_SESSION['document_management_notice'] = $message;
                header('Location: document-management.php');
                exit;
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
                    $error = 'A document with that name already exists.';
                } else {
                    error_log('Document management database error: ' . $exception->getMessage());
                    $error = 'The document could not be saved. Check the database and try again.';
                }
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid document action.');
    }
}

$documents = $db->query(
    'SELECT id, name, description, price, processing_min_days,
            processing_max_days, release_methods, is_active
     FROM documents
     ORDER BY name ASC'
)->fetchAll();
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Document Management</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">ADMIN PORTAL</p>
                <h1>Document management</h1>
            </div>
        </header>

        <?php if ($notice !== ''): ?>
            <p class="notice notice-success toast-notification" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>

        <section class="portal-panel" aria-labelledby="catalog-title">
            <h2 id="catalog-title">Document catalog</h2>
            <p class="panel-intro">Inactive documents are hidden from new requests; request history is kept.</p>
            <?php if ($documents !== []): ?>
                <div class="table-toolbar">
                    <label class="sr-only" for="document-search">Search documents</label>
                    <input id="document-search" class="live-search-input" type="search" placeholder="Search documents"
                           data-live-search="#document-table" data-live-search-empty="document-no-results">
                </div>
            <?php endif; ?>
            <div class="table-wrap document-list-wrap">
                <table id="document-table" class="data-table editable-table document-table">
                    <thead>
                        <tr>
                            <th scope="col">Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="document-table-toolbar">
                                <button class="secondary-button" type="button" data-document-editor="new">Add document</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="document-editor-cell" hidden>
                                <form id="document-create" class="document-inline-editor" method="post" action="document-management.php" data-document-wizard>
                                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="create">
                                    <section class="document-wizard-step document-editor-fields">
                                        <label class="document-name-field"><span>Document name</span><input aria-label="New document name" name="name" type="text" maxlength="150" required data-document-name></label>
                                        <label class="document-description-field"><span>Description</span><textarea aria-label="New document description" name="description" rows="2" maxlength="5000"></textarea></label>
                                        <label><span>Price (PHP)</span><input aria-label="New document price" name="price" type="number" min="0" step="0.01" value="0.00" required data-document-price></label>
                                        <label><span>Processing time</span><input aria-label="New processing weekdays" name="processing_days" type="number" min="0" max="3650" value="0" required data-document-processing-days><small>Enter 0 for same day; otherwise enter the number of weekdays (Monday-Friday).</small></label>
                                        <label><span>Release method</span>
                                            <select name="release_method" required data-document-release-method>
                                                <option value="" selected disabled>Select a release method</option>
                                                <option value="Hardcopy">Hardcopy</option>
                                                <option value="Softcopy">Softcopy</option>
                                                <option value="Both">Both</option>
                                            </select>
                                        </label>
                                        <label class="document-active-option">
                                            <input type="hidden" name="is_active" value="0">
                                            <input aria-label="New document active" type="checkbox" name="is_active" value="1" checked>
                                            <span>Active</span>
                                        </label>
                                        <input name="current_password" type="hidden" value="">
                                        <div class="document-inline-actions">
                                            <button class="secondary-button" type="button" data-document-cancel>Cancel</button>
                                            <button class="table-action-button" type="submit">Add document</button>
                                        </div>
                                    </section>
                                </form>
                            </td>
                        </tr>
                        <?php foreach ($documents as $document): ?>
                            <?php $formId = 'document-' . (int) $document['id']; ?>
                            <?php
                            $processingMaximum = (int) $document['processing_max_days'];
                            $processingDisplay = $processingMaximum === 0
                                ? 'Same day'
                                : $processingMaximum . ' ' . ($processingMaximum === 1 ? 'weekday' : 'weekdays');
                            ?>
                            <tr data-live-search-row data-live-search-text="<?= $escape(implode(' ', [
                            $document['name'],
                            $document['description'],
                            $document['release_methods'],
                            ])) ?>">
                            <td>
                                <button class="document-card" type="button"
                                        data-document-details="<?= (int) $document['id'] ?>"
                                        data-document-name="<?= $escape($document['name']) ?>"
                                        data-document-description="<?= $escape($document['description'] !== '' ? $document['description'] : 'No description provided.') ?>"
                                        data-document-price="PHP <?= $escape(number_format((float) $document['price'], 2)) ?>"
                                        data-document-processing="<?= $escape($processingDisplay) ?>"
                                        data-document-methods="<?= $escape($document['release_methods']) ?>"
                                        data-document-availability="<?= (int) $document['is_active'] === 1 ? 'Active' : 'Inactive' ?>">
                                    <span class="document-card-name"><?= $escape($document['name']) ?></span>
                                    <span class="document-card-processing"><?= $escape($processingDisplay) ?></span>
                                </button>
                            </td>
                            </tr>
                            <tr class="document-edit-row" data-document-row="<?= (int) $document['id'] ?>" hidden>
                            <td class="document-editor-cell">
                                    <form id="<?= $escape($formId) ?>" class="document-inline-editor" method="post" action="document-management.php" data-document-wizard>
                                        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                                        <input type="hidden" name="current_password" value="">
                                        <input type="hidden" name="is_active" value="<?= (int) $document['is_active'] ?>">
                                        <section class="document-wizard-step document-editor-fields">
                                            <label class="document-name-field"><span>Document name</span><input aria-label="Document name" name="name" type="text" maxlength="150" value="<?= $escape($document['name']) ?>" required data-document-name></label>
                                            <label class="document-description-field"><span>Description</span><textarea aria-label="Document description" name="description" rows="2" maxlength="5000"><?= $escape($document['description']) ?></textarea></label>
                                            <label><span>Price (PHP)</span><input aria-label="Document price" name="price" type="number" min="0" step="0.01" value="<?= $escape($document['price']) ?>" required data-document-price></label>
                                            <label><span>Processing time</span><input aria-label="Processing weekdays" name="processing_days" type="number" min="0" max="3650" value="<?= $processingMaximum ?>" required data-document-processing-days data-original-min="<?= (int) $document['processing_min_days'] ?>" data-original-max="<?= $processingMaximum ?>"><small>Enter 0 for same day; otherwise enter the number of weekdays (Monday-Friday).</small></label>
                                            <label><span>Release method</span>
                                                <?php $releaseMethod = document_release_method_value((string) $document['release_methods']); ?>
                                                <select name="release_method" required data-document-release-method
                                                        data-original-method="<?= $escape($releaseMethod) ?>">
                                                    <option value="Hardcopy" <?= $releaseMethod === 'Hardcopy' ? 'selected' : '' ?>>Hardcopy</option>
                                                    <option value="Softcopy" <?= $releaseMethod === 'Softcopy' ? 'selected' : '' ?>>Softcopy</option>
                                                    <option value="Both" <?= $releaseMethod === 'Both' ? 'selected' : '' ?>>Both</option>
                                                </select>
                                            </label>
                                            <div class="document-inline-actions">
                                                <button class="secondary-button" type="button" data-document-cancel>Cancel</button>
                                                <button class="table-action-button" type="submit"
                                                        data-document-save-button disabled>Save changes</button>
                                            </div>
                                        </section>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($documents === []): ?>
                            <tr><td class="empty-state">No documents have been added.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <dialog class="workflow-dialog management-dialog document-details-dialog" id="document-details-dialog"
                    aria-labelledby="document-details-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">DOCUMENT DETAILS</p><h2 id="document-details-title" data-document-details-name></h2></div>
                    <button class="workflow-dialog-close" type="button" data-document-details-close aria-label="Close dialog">×</button>
                </div>
                <dl class="document-details-list">
                    <div><dt>Processing time</dt><dd data-document-details-processing></dd></div>
                    <div><dt>Price</dt><dd data-document-details-price></dd></div>
                    <div><dt>Release methods</dt><dd data-document-details-methods></dd></div>
                    <div><dt>Availability</dt><dd data-document-details-availability></dd></div>
                    <div class="document-details-description"><dt>Description</dt><dd data-document-details-description></dd></div>
                </dl>
                <div class="course-details-actions">
                    <button class="table-action-button" type="button" data-document-details-edit>Edit this document</button>
                </div>
            </dialog>
            <dialog class="workflow-dialog management-dialog document-management-dialog" id="document-editor-dialog"
                    aria-labelledby="document-editor-dialog-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">DOCUMENT CATALOG</p><h2 id="document-editor-dialog-title">Document details</h2></div>
                </div>
                <div data-document-editor-host></div>
                <div class="document-inline-actions" data-document-delete-actions hidden>
                    <button class="secondary-button" type="button" data-document-active-button>
                        Deactivate document
                    </button>
                    <button class="danger-button" type="button"
                            data-document-delete-button>Delete document</button>
                </div>
            </dialog>
            <form id="document-toggle-form" method="post" action="document-management.php" hidden
                  data-loading-source="[data-document-active-button]" data-loading-text="Saving document status…">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="document_id" value="">
                <input type="hidden" name="current_password" value="">
            </form>
            <dialog class="workflow-dialog management-dialog delete-confirmation-dialog" id="document-delete-dialog"
                    aria-labelledby="document-delete-title">
                <div class="workflow-dialog-heading">
                    <div><p class="eyebrow">CONFIRM DELETION</p><h2 id="document-delete-title">Delete document?</h2></div>
                    <button class="workflow-dialog-close" type="button" data-document-delete-cancel aria-label="Close dialog">×</button>
                </div>
                <p class="delete-confirmation-message" data-document-delete-message></p>
                <form id="document-delete-form" method="post" action="document-management.php"
                      data-navigation-guard-ignore>
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="document_id" value="">
                    <label class="document-password-confirmation">
                        <span>Current password</span>
                        <input name="current_password" type="password" autocomplete="current-password" required>
                    </label>
                    <div class="document-inline-actions delete-confirmation-actions">
                        <button class="secondary-button" type="button" data-document-delete-cancel>Cancel</button>
                        <button class="danger-button" type="submit">Confirm delete</button>
                    </div>
                </form>
            </dialog>
            <dialog class="workflow-dialog management-dialog delete-confirmation-dialog"
                    id="document-password-dialog" aria-labelledby="document-password-title">
                <div class="workflow-dialog-heading">
                    <div>
                        <p class="eyebrow" data-document-password-eyebrow>CONFIRM CHANGES</p>
                        <h2 id="document-password-title">Save document changes?</h2>
                    </div>
                    <button class="workflow-dialog-close" type="button" data-document-password-cancel
                            aria-label="Close dialog">×</button>
                </div>
                <p class="delete-confirmation-message" data-document-password-message>
                    Enter your current password to confirm these changes.
                </p>
                <form data-document-password-confirm>
                    <label class="document-password-confirmation">
                        <span>Current password</span>
                        <input type="password" autocomplete="current-password" required>
                    </label>
                    <div class="document-inline-actions delete-confirmation-actions">
                        <button class="secondary-button" type="button" data-document-password-cancel>Cancel</button>
                        <button class="table-action-button" type="submit" data-document-password-submit>
                            Confirm changes
                        </button>
                    </div>
                </form>
            </dialog>
            <?php if ($documents !== []): ?>
                <p id="document-no-results" class="empty-state live-search-empty" hidden>No matching documents.</p>
            <?php endif; ?>
        </section>
    </main>
    <script src="assets/js/form-change-feedback.js"></script>
    <script>
        const documentEditorDialog = document.getElementById('document-editor-dialog');
        const documentDeleteDialog = document.getElementById('document-delete-dialog');
        const documentPasswordDialog = document.getElementById('document-password-dialog');
        const documentDetailsDialog = document.getElementById('document-details-dialog');
        const documentDetailsTitle = documentDetailsDialog.querySelector('[data-document-details-name]');
        const documentDetailsProcessing = documentDetailsDialog.querySelector('[data-document-details-processing]');
        const documentDetailsPrice = documentDetailsDialog.querySelector('[data-document-details-price]');
        const documentDetailsMethods = documentDetailsDialog.querySelector('[data-document-details-methods]');
        const documentDetailsAvailability = documentDetailsDialog.querySelector('[data-document-details-availability]');
        const documentDetailsDescription = documentDetailsDialog.querySelector('[data-document-details-description]');
        const documentDetailsEdit = documentDetailsDialog.querySelector('[data-document-details-edit]');
        const documentEditorHost = documentEditorDialog.querySelector('[data-document-editor-host]');
        const documentDeleteActions = documentEditorDialog.querySelector('[data-document-delete-actions]');
        const documentDeleteButton = documentEditorDialog.querySelector('[data-document-delete-button]');
        const documentActiveButton = documentEditorDialog.querySelector('[data-document-active-button]');
        const documentDeleteId = documentDeleteDialog.querySelector('#document-delete-form input[name="document_id"]');
        const documentToggleForm = document.getElementById('document-toggle-form');
        const documentPasswordForm = documentPasswordDialog.querySelector('[data-document-password-confirm]');
        const documentPasswordInput = documentPasswordForm.querySelector('input[type="password"]');
        const documentPasswordEyebrow = documentPasswordDialog.querySelector('[data-document-password-eyebrow]');
        const documentPasswordTitle = documentPasswordDialog.querySelector('#document-password-title');
        const documentPasswordMessage = documentPasswordDialog.querySelector('[data-document-password-message]');
        const documentPasswordSubmit = documentPasswordDialog.querySelector('[data-document-password-submit]');
        let currentDocumentForm = null;
        let currentDocumentFormHome = null;
        let authorizedDocumentForm = null;
        let pendingDocumentOperation = null;

        function returnCurrentDocumentForm() {
            if (currentDocumentForm && currentDocumentFormHome) {
                currentDocumentForm.reset();
                window.__appNavigationGuard?.resetFormState(currentDocumentForm);
                currentDocumentFormHome.append(currentDocumentForm);
            }
            documentDeleteId.value = '';
            documentDeletePassword.value = '';
            currentDocumentForm = null;
            currentDocumentFormHome = null;
        }

        function closeDocumentEditor() {
            returnCurrentDocumentForm();
            if (documentDeleteDialog.open) {
                documentDeleteDialog.close();
            }
            if (documentPasswordDialog.open) {
                documentPasswordDialog.close();
            }
            if (documentEditorDialog.open) {
                documentEditorDialog.close();
            }
        }

        function resetDocumentEditor(form) {
            form.reset();
            form.querySelector('input[name="current_password"]').value = '';
        }

        const documentPasswordRequirements = new WeakMap();

        function setupDocumentEditor(form) {
            const name = form.querySelector('[data-document-name]');
            const description = form.querySelector('textarea[name="description"]');
            const price = form.querySelector('[data-document-price]');
            const processingDays = form.querySelector('[data-document-processing-days]');
            const releaseMethod = form.querySelector('[data-document-release-method]');
            const action = form.querySelector('input[name="action"]').value;
            const saveButton = form.querySelector('[data-document-save-button]');
            const originalName = name.defaultValue;
            const originalDescription = description.defaultValue;
            const originalPrice = Number(price.defaultValue).toFixed(2);
            const originalProcessingDays = processingDays.defaultValue;
            const originalReleaseMethod = releaseMethod.dataset.originalMethod || releaseMethod.value;

            function updatePasswordRequirement() {
                const priceChanged = Number(price.value).toFixed(2) !== originalPrice;
                const processingChanged = processingDays.value !== originalProcessingDays
                    || (processingDays.dataset.originalMin !== undefined
                        && processingDays.dataset.originalMin !== processingDays.dataset.originalMax);
                const releaseMethodChanged = action === 'update' && releaseMethod.value !== originalReleaseMethod;
                return action === 'create'
                    || priceChanged
                    || processingChanged
                    || releaseMethodChanged;
            }

            documentPasswordRequirements.set(form, updatePasswordRequirement);
            if (action === 'update' && saveButton) {
                window.AppFormChangeFeedback.watch(
                    form,
                    saveButton,
                    form.querySelector('[data-document-cancel]'),
                    () => name.value !== originalName
                        || description.value !== originalDescription
                        || Number(price.value).toFixed(2) !== originalPrice
                        || processingDays.value !== originalProcessingDays
                        || releaseMethod.value !== originalReleaseMethod
                );
            }
            [name, description, price, processingDays, releaseMethod].forEach((field) => {
                field.addEventListener('input', updatePasswordRequirement);
                field.addEventListener('change', updatePasswordRequirement);
            });

            resetDocumentEditor(form);
            updatePasswordRequirement();
        }

        const documentDeletePassword = documentDeleteDialog.querySelector('input[name="current_password"]');

        document.querySelectorAll('[data-document-wizard]').forEach((form) => {
            setupDocumentEditor(form);
            form.addEventListener('submit', (event) => {
                const action = form.querySelector('input[name="action"]').value;
                if (authorizedDocumentForm === form) {
                    authorizedDocumentForm = null;
                    return;
                }
                if (action !== 'create' && !documentPasswordRequirements.get(form)?.()) {
                    return;
                }

                event.preventDefault();
                currentDocumentForm = form;
                pendingDocumentOperation = 'save';
                const isCreate = action === 'create';
                documentPasswordEyebrow.textContent = isCreate ? 'CONFIRM DOCUMENT' : 'CONFIRM CHANGES';
                documentPasswordTitle.textContent = isCreate ? 'Add document?' : 'Save document changes?';
                documentPasswordMessage.textContent = isCreate
                    ? 'Enter your current password to add this document to the catalog.'
                    : 'Enter your current password to confirm these changes.';
                documentPasswordSubmit.textContent = isCreate ? 'Confirm and add' : 'Confirm changes';
                documentPasswordSubmit.classList.replace('danger-button', 'table-action-button');
                documentPasswordInput.value = '';
                documentPasswordDialog.showModal();
                documentPasswordInput.focus();
            });
        });

        documentActiveButton.addEventListener('click', () => {
            if (!currentDocumentForm) {
                return;
            }

            const isActive = currentDocumentForm.querySelector('input[name="is_active"]').value === '1';
            const documentName = currentDocumentForm.querySelector('[data-document-name]').value.trim();
            pendingDocumentOperation = 'toggle';
            documentPasswordEyebrow.textContent = isActive ? 'CONFIRM DEACTIVATION' : 'CONFIRM ACTIVATION';
            documentPasswordTitle.textContent = isActive ? 'Deactivate document?' : 'Activate document?';
            documentPasswordMessage.textContent = isActive
                ? `Deactivate "${documentName}"? It will no longer be available for new requests.`
                : `Activate "${documentName}"? It will be available for new requests.`;
            documentPasswordSubmit.textContent = isActive ? 'Confirm deactivation' : 'Confirm activation';
            documentPasswordSubmit.classList.toggle('danger-button', isActive);
            documentPasswordSubmit.classList.toggle('table-action-button', !isActive);
            documentPasswordInput.value = '';
            documentPasswordDialog.showModal();
            documentPasswordInput.focus();
        });

        documentPasswordForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!documentPasswordForm.reportValidity() || !pendingDocumentOperation) {
                return;
            }

            if (pendingDocumentOperation === 'toggle') {
                documentToggleForm.querySelector('input[name="document_id"]').value =
                    currentDocumentForm.querySelector('input[name="document_id"]').value;
                documentToggleForm.querySelector('input[name="current_password"]').value = documentPasswordInput.value;
                pendingDocumentOperation = null;
                documentPasswordDialog.close();
                documentToggleForm.requestSubmit();
                return;
            }

            if (!currentDocumentForm || !currentDocumentForm.reportValidity()) {
                return;
            }
            currentDocumentForm.querySelector('input[name="current_password"]').value = documentPasswordInput.value;
            authorizedDocumentForm = currentDocumentForm;
            pendingDocumentOperation = null;
            documentPasswordDialog.close();
            currentDocumentForm.requestSubmit();
        });

        documentPasswordDialog.querySelectorAll('[data-document-password-cancel]').forEach((button) => {
            button.addEventListener('click', () => documentPasswordDialog.close());
        });
        documentPasswordDialog.addEventListener('close', () => {
            documentPasswordInput.value = '';
            pendingDocumentOperation = null;
        });

        function openDocumentEditor(documentId) {
            closeDocumentEditor();
            const form = documentId === 'new'
                ? document.querySelector('#document-create')
                : document.getElementById(`document-${documentId}`);
            if (!form) {
                return;
            }

            currentDocumentForm = form;
            currentDocumentFormHome = form.parentElement;
            resetDocumentEditor(form);
            documentPasswordRequirements.get(form)?.();
            document.getElementById('document-editor-dialog-title').textContent =
                documentId === 'new' ? 'Add document' : 'Edit document';
            documentDeleteId.value = documentId === 'new' ? '' : documentId;
            documentDeleteActions.hidden = documentId === 'new';
            if (documentId !== 'new') {
                const isActive = form.querySelector('input[name="is_active"]').value === '1';
                documentActiveButton.textContent = isActive ? 'Deactivate document' : 'Activate document';
                documentActiveButton.classList.toggle('danger-button', isActive);
                documentActiveButton.classList.toggle('table-action-button', !isActive);
                documentActiveButton.classList.remove('secondary-button');
            }
            documentEditorHost.append(form);
            documentEditorDialog.showModal();
            form.querySelector('input[name="name"]')?.focus();
        }

        document.querySelectorAll('[data-document-details]').forEach((button) => {
            button.addEventListener('click', () => {
                documentDetailsTitle.textContent = button.dataset.documentName;
                documentDetailsProcessing.textContent = button.dataset.documentProcessing;
                documentDetailsPrice.textContent = button.dataset.documentPrice;
                documentDetailsMethods.textContent = button.dataset.documentMethods || 'Not specified';
                documentDetailsAvailability.textContent = button.dataset.documentAvailability;
                documentDetailsDescription.textContent = button.dataset.documentDescription;
                documentDetailsEdit.dataset.documentId = button.dataset.documentDetails;
                documentDetailsDialog.showModal();
            });
        });

        documentDetailsEdit.addEventListener('click', () => {
            const documentId = documentDetailsEdit.dataset.documentId;
            documentDetailsDialog.close();
            openDocumentEditor(documentId);
        });

        document.querySelectorAll('[data-document-editor]').forEach((button) => {
            button.addEventListener('click', () => openDocumentEditor(button.dataset.documentEditor));
        });

        documentDetailsDialog.querySelector('[data-document-details-close]').addEventListener('click', () => {
            documentDetailsDialog.close();
        });
        documentDetailsDialog.addEventListener('click', (event) => {
            if (event.target === documentDetailsDialog) {
                documentDetailsDialog.close();
            }
        });

        document.querySelectorAll('[data-document-cancel]').forEach((button) => {
            button.addEventListener('click', closeDocumentEditor);
        });

        documentDeleteButton.addEventListener('click', (event) => {
            const documentName = currentDocumentForm?.querySelector('[data-document-name]')?.value.trim();
            if (!currentDocumentForm || !documentName) {
                event.preventDefault();
                return;
            }
            documentDeleteDialog.querySelector('[data-document-delete-message]').textContent =
                `Delete "${documentName}"? This cannot be undone. Documents linked to request history cannot be deleted.`;
            documentDeleteId.value = currentDocumentForm.querySelector('input[name="document_id"]').value;
            documentDeletePassword.value = '';
            documentDeleteDialog.showModal();
            documentDeletePassword.focus();
        });

        documentDeleteDialog.querySelectorAll('[data-document-delete-cancel]').forEach((button) => {
            button.addEventListener('click', () => documentDeleteDialog.close());
        });
        documentDeleteDialog.addEventListener('close', () => {
            documentDeletePassword.value = '';
        });

        documentEditorDialog.addEventListener('close', () => {
            if (documentDeleteDialog.open) {
                documentDeleteDialog.close();
            }
        });

        documentEditorDialog.addEventListener('click', (event) => {
            if (event.target === documentEditorDialog) {
                closeDocumentEditor();
            }
        });

        documentEditorDialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDocumentEditor();
        });

        documentEditorDialog.addEventListener('close', () => {
            if (currentDocumentForm) {
                resetDocumentEditor(currentDocumentForm);
            }
            returnCurrentDocumentForm();
        });
    </script>
    <script src="assets/js/live-search.js"></script>
    <script src="assets/js/form-loading.js" defer></script>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/navigation-guard.js" defer></script>
</body>
</html>
