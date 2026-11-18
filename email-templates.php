<?php
declare(strict_types=1);

require __DIR__ . '/mailer.php';
start_app_session();
require_personnel(['admin']);

$db = db();
$definitions = registrar_email_template_definitions();
$error = '';
$editingTemplateKey = '';
$submittedSubject = '';
$submittedBody = '';
$submittedEnabled = false;
$notice = is_string($_SESSION['email_templates_notice'] ?? null)
    ? $_SESSION['email_templates_notice']
    : '';
unset($_SESSION['email_templates_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload this page and try again.');
    }

    $templateKey = is_string($_POST['template_key'] ?? null) ? $_POST['template_key'] : '';
    $subject = is_string($_POST['subject'] ?? null) ? trim($_POST['subject']) : '';
    $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
    if (isset($definitions[$templateKey])) {
        $editingTemplateKey = $templateKey;
        $submittedSubject = $subject;
        $submittedBody = $body;
        $submittedEnabled = ($_POST['is_enabled'] ?? null) === '1';
    }
    if (!isset($definitions[$templateKey])) {
        $error = 'Choose a valid email template.';
    } elseif ($subject === '' || mb_strlen($subject) > 255 || preg_match('/[\r\n]/', $subject)) {
        $error = 'Enter a subject on one line, up to 255 characters.';
    } elseif ($body === '' || mb_strlen($body) > 10000) {
        $error = 'Enter an email body of no more than 10,000 characters.';
    } else {
        $definition = $definitions[$templateKey];
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $subject . "\n" . $body, $matches);
        $usedPlaceholders = array_unique($matches[1]);
        $unknownPlaceholders = array_diff($usedPlaceholders, $definition['placeholders']);
        $missingPlaceholders = array_diff($definition['required_placeholders'], $usedPlaceholders);
        if ($unknownPlaceholders !== []) {
            $error = 'Unsupported placeholder(s): ' . implode(', ', $unknownPlaceholders) . '.';
        } elseif ($missingPlaceholders !== []) {
            $error = 'Keep these required placeholder(s): '
                . implode(', ', array_map(
                    static fn (string $placeholder): string => '{' . $placeholder . '}',
                    $missingPlaceholders
                )) . '.';
        } else {
            $enabled = $definition['required']
                ? 1
                : (($_POST['is_enabled'] ?? null) === '1' ? 1 : 0);
            try {
                $currentTemplateQuery = $db->prepare(
                    'SELECT subject, body, is_enabled
                     FROM email_templates
                     WHERE template_key = :template_key'
                );
                $currentTemplateQuery->execute(['template_key' => $templateKey]);
                $currentTemplate = $currentTemplateQuery->fetch();
                if ($currentTemplate === false) {
                    throw new RuntimeException('Email template defaults are missing. Apply the email templates database migration.');
                }
                if ($currentTemplate['subject'] === $subject
                    && $currentTemplate['body'] === $body
                    && (int) $currentTemplate['is_enabled'] === $enabled) {
                    throw new InvalidArgumentException('No changes were made. Cancel editing without saving.');
                }
                $save = $db->prepare(
                    'UPDATE email_templates
                     SET subject = :subject, body = :body, is_enabled = :is_enabled,
                         updated_by = :updated_by
                     WHERE template_key = :template_key'
                );
                $save->execute([
                    'subject' => $subject,
                    'body' => $body,
                    'is_enabled' => $enabled,
                    'updated_by' => (int) $_SESSION['personnel_id'],
                    'template_key' => $templateKey,
                ]);
                if ($save->rowCount() === 0) {
                    $exists = $db->prepare('SELECT 1 FROM email_templates WHERE template_key = :template_key');
                    $exists->execute(['template_key' => $templateKey]);
                    if ($exists->fetchColumn() === false) {
                        throw new RuntimeException('Email template defaults are missing. Apply the email templates database migration.');
                    }
                }
                $_SESSION['email_templates_notice'] = 'Email template saved.';
                header('Location: email-templates.php');
                exit;
            } catch (PDOException $exception) {
                error_log('Email template save failed: ' . $exception->getMessage());
                $error = 'The email template could not be saved. Check the database and try again.';
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
    }
}

$templateQuery = $db->query(
    'SELECT template_key, subject, body, is_enabled
     FROM email_templates'
);
$savedTemplates = [];
foreach ($templateQuery->fetchAll() as $template) {
    $savedTemplates[(string) $template['template_key']] = $template;
}
$missingTemplates = array_diff(array_keys($definitions), array_keys($savedTemplates));
if ($missingTemplates !== []) {
    $error = 'Email template defaults are missing. Apply database/migrate_email_templates.sql before editing templates.';
}
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Notifications &amp; Email</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="portal-shell email-templates-shell">
        <header class="portal-header">
            <div>
                <p class="eyebrow">ADMIN PORTAL</p>
                <h1>Notifications &amp; email</h1>
            </div>
        </header>

        <?php if ($notice !== ''): ?>
            <p class="notice notice-success toast-notification" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>

        <section class="email-template-grid" aria-label="Notification templates">
        <?php foreach ($definitions as $key => $definition): ?>
            <?php $template = $savedTemplates[$key] ?? null; ?>
            <?php if ($template === null) { continue; } ?>
            <button class="email-template-card" type="button"
                    data-email-template="<?= $escape($key) ?>"
                    data-email-label="<?= $escape($definition['label']) ?>"
                    data-email-subject="<?= $escape($template['subject']) ?>"
                    data-email-body="<?= $escape($template['body']) ?>"
                    data-email-enabled="<?= (bool) $template['is_enabled'] ? '1' : '0' ?>"
                    data-email-required="<?= $definition['required'] ? '1' : '0' ?>"
                    data-email-placeholders="<?= $escape(json_encode($definition['placeholders'], JSON_THROW_ON_ERROR)) ?>">
                <span class="email-template-card-heading">
                    <span class="email-template-card-title"><?= $escape($definition['label']) ?></span>
                    <span class="admin-role-badge"><?= $definition['required'] ? 'Required' : 'Optional' ?></span>
                </span>
                <span class="email-template-card-subject"><?= $escape($template['subject']) ?></span>
                <span class="email-template-card-status"><?= (bool) $template['is_enabled'] ? 'Sending is on' : 'Sending is off' ?></span>
            </button>
        <?php endforeach; ?>
        </section>

        <dialog class="workflow-dialog management-dialog email-template-dialog" id="email-template-dialog"
                aria-labelledby="email-template-title">
            <div class="workflow-dialog-heading">
                <div>
                    <p class="eyebrow">NOTIFICATION TEMPLATE</p>
                    <h2 id="email-template-title">Edit template</h2>
                </div>
                <button class="workflow-dialog-close" type="button" data-email-template-close aria-label="Close dialog">×</button>
            </div>
            <?php if ($error !== '' && $editingTemplateKey !== ''): ?>
                <p class="notice" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
            <form method="post" action="email-templates.php">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">
                <input type="hidden" name="template_key" id="email-template-key" value="<?= $escape($editingTemplateKey) ?>">
                <div class="field">
                    <label for="email-template-subject">Email subject</label>
                    <input id="email-template-subject" name="subject" type="text" maxlength="255"
                           value="<?= $escape($submittedSubject) ?>" required>
                </div>
                <div class="field">
                    <label for="email-template-body">Email message</label>
                    <textarea id="email-template-body" name="body" rows="7" maxlength="10000" required><?= $escape($submittedBody) ?></textarea>
                </div>
                <p class="email-template-placeholders" id="email-template-placeholders"></p>
                <label class="email-template-toggle" id="email-template-toggle-row" hidden>
                    <input type="checkbox" name="is_enabled" value="1" <?= $submittedEnabled ? 'checked' : '' ?>>
                    Send this notification
                </label>
                <p class="email-template-required" id="email-template-required" hidden>
                    This email is required for the request workflow and cannot be disabled.
                </p>
                <div class="email-template-form-actions">
                    <button class="secondary-button" type="button" data-email-template-close data-email-template-cancel>Cancel</button>
                    <button class="submit-button email-template-save" type="submit" disabled>Save template <span aria-hidden="true">→</span></button>
                </div>
            </form>
        </dialog>
    </main>
    <script src="assets/js/form-change-feedback.js"></script>
    <script>
        const emailTemplateDialog = document.getElementById('email-template-dialog');
        const emailTemplateCards = document.querySelectorAll('[data-email-template]');
        const emailTemplateTitle = document.getElementById('email-template-title');
        const emailTemplateKey = document.getElementById('email-template-key');
        const emailTemplateSubject = document.getElementById('email-template-subject');
        const emailTemplateBody = document.getElementById('email-template-body');
        const emailTemplateForm = emailTemplateDialog.querySelector('form');
        const emailTemplateSaveButton = emailTemplateForm.querySelector('button[type="submit"]');
        const emailTemplatePlaceholders = document.getElementById('email-template-placeholders');
        const emailTemplateToggle = document.getElementById('email-template-toggle-row');
        const emailTemplateEnabled = emailTemplateToggle.querySelector('input');
        const emailTemplateRequired = document.getElementById('email-template-required');
        let originalEmailTemplate = null;

        const updateEmailTemplateSaveButton = window.AppFormChangeFeedback.watch(
            emailTemplateForm,
            emailTemplateSaveButton,
            emailTemplateForm.querySelector('[data-email-template-cancel]'),
            () => {
                if (!originalEmailTemplate) {
                    return false;
                }
                const enabled = emailTemplateToggle.hidden ? 1 : (emailTemplateEnabled.checked ? 1 : 0);
                return emailTemplateSubject.value !== originalEmailTemplate.subject
                    || emailTemplateBody.value !== originalEmailTemplate.body
                    || enabled !== originalEmailTemplate.enabled;
            }
        );
        emailTemplateForm.addEventListener('submit', (event) => {
            updateEmailTemplateSaveButton(false);
            if (emailTemplateSaveButton.disabled) {
                event.preventDefault();
            }
        });

        function setEmailTemplatePlaceholders(placeholders) {
            emailTemplatePlaceholders.replaceChildren();
            const label = document.createElement('strong');
            label.textContent = 'Available placeholders:';
            emailTemplatePlaceholders.append(label);
            placeholders.forEach((placeholder) => {
                const code = document.createElement('code');
                code.textContent = `{${placeholder}}`;
                emailTemplatePlaceholders.append(code);
            });
        }

        emailTemplateCards.forEach((card) => {
            card.addEventListener('click', () => {
                emailTemplateTitle.textContent = card.dataset.emailLabel;
                emailTemplateKey.value = card.dataset.emailTemplate;
                emailTemplateSubject.value = card.dataset.emailSubject;
                emailTemplateBody.value = card.dataset.emailBody;
                const isRequired = card.dataset.emailRequired === '1';
                emailTemplateToggle.hidden = isRequired;
                emailTemplateRequired.hidden = !isRequired;
                emailTemplateEnabled.checked = card.dataset.emailEnabled === '1';
                originalEmailTemplate = {
                    subject: card.dataset.emailSubject,
                    body: card.dataset.emailBody,
                    enabled: isRequired ? 1 : (card.dataset.emailEnabled === '1' ? 1 : 0)
                };
                updateEmailTemplateSaveButton(false);
                setEmailTemplatePlaceholders(JSON.parse(card.dataset.emailPlaceholders));
                emailTemplateDialog.showModal();
                emailTemplateSubject.focus();
            });
        });

        document.querySelectorAll('[data-email-template-close]').forEach((button) => {
            button.addEventListener('click', () => emailTemplateDialog.close());
        });

        <?php if ($editingTemplateKey !== ''): ?>
        const failedTemplateCard = Array.from(emailTemplateCards).find(
            (card) => card.dataset.emailTemplate === <?= json_encode($editingTemplateKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
        );
        if (failedTemplateCard) {
            emailTemplateTitle.textContent = failedTemplateCard.dataset.emailLabel;
            emailTemplateKey.value = failedTemplateCard.dataset.emailTemplate;
            emailTemplateToggle.hidden = failedTemplateCard.dataset.emailRequired === '1';
            emailTemplateRequired.hidden = failedTemplateCard.dataset.emailRequired !== '1';
            originalEmailTemplate = {
                subject: failedTemplateCard.dataset.emailSubject,
                body: failedTemplateCard.dataset.emailBody,
                enabled: failedTemplateCard.dataset.emailRequired === '1'
                    ? 1
                    : (failedTemplateCard.dataset.emailEnabled === '1' ? 1 : 0)
            };
            emailTemplateEnabled.checked = <?= $submittedEnabled ? 'true' : 'false' ?>;
            updateEmailTemplateSaveButton(false);
            setEmailTemplatePlaceholders(JSON.parse(failedTemplateCard.dataset.emailPlaceholders));
            emailTemplateDialog.showModal();
            emailTemplateSubject.focus();
        }
        <?php endif; ?>
    </script>
    <script src="assets/js/toast-notifications.js" defer></script>
    <script src="assets/js/form-loading.js" defer></script>
</body>
</html>
