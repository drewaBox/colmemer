<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function request_processing_delay_notice(): string
{
    return 'Estimated dates are based on weekdays (Monday-Friday). Public holidays, class suspensions, office closures, or other unforeseen circumstances may still cause delays.';
}

function registrar_email_template_definitions(): array
{
    return [
        'verification_code' => [
            'label' => 'Email verification code',
            'description' => 'Required to verify an email before a request can be submitted. This notification cannot be disabled.',
            'placeholders' => ['verification_code', 'expiration_minutes'],
            'required_placeholders' => ['verification_code'],
            'required' => true,
            'default_subject' => 'Your registrar email verification code',
            'default_body' => "Your verification code is: {verification_code}\n\n"
                . "This code expires in {expiration_minutes} minutes and can only be used once.\n"
                . "If you did not request a document, you can ignore this email.",
        ],
        'request_submitted' => [
            'label' => 'Request submitted',
            'description' => 'Sent after a document request is successfully submitted. This notification cannot be disabled.',
            'placeholders' => ['document_name', 'tracking_number', 'status', 'tracking_url'],
            'required_placeholders' => ['tracking_number', 'tracking_url'],
            'required' => true,
            'default_subject' => 'Document request received · {tracking_number}',
            'default_body' => "Your request for {document_name} has been submitted.\n\n"
                . "Tracking reference: {tracking_number}\n"
                . "Current status: {status}\n\n"
                . "Track your request: {tracking_url}\n\n"
                . "Keep this reference to check your request status.",
        ],
        'processing' => [
            'label' => 'Request processing',
            'description' => 'Sent when staff starts processing a request. The release date is calculated from the configured weekday processing time, and a delay notice is always included.',
            'placeholders' => ['document_name', 'tracking_number', 'expected_release_date'],
            'required_placeholders' => ['tracking_number', 'expected_release_date'],
            'required' => false,
            'default_subject' => 'Your document request is being processed · {tracking_number}',
            'default_body' => "Your request for {document_name} is now being processed.\n\n"
                . "Estimated release date: {expected_release_date}\n"
                . "Tracking reference: {tracking_number}\n\n"
                . request_processing_delay_notice(),
        ],
        'ready' => [
            'label' => 'Ready for pickup',
            'description' => 'Sent when the document is ready for pickup.',
            'placeholders' => ['document_name', 'tracking_number'],
            'required_placeholders' => ['tracking_number'],
            'required' => false,
            'default_subject' => 'Your document is ready for pickup · {tracking_number}',
            'default_body' => "Your request for {document_name} is ready for pickup.\n\n"
                . "Tracking reference: {tracking_number}",
        ],
        'rejected' => [
            'label' => 'Request rejected',
            'description' => 'Sent when a request is rejected. Include the reason so the student knows why.',
            'placeholders' => ['document_name', 'tracking_number', 'rejection_reason'],
            'required_placeholders' => ['tracking_number', 'rejection_reason'],
            'required' => false,
            'default_subject' => 'Update about your document request · {tracking_number}',
            'default_body' => "Your request for {document_name} was rejected.\n\n"
                . "Reason: {rejection_reason}\n\n"
                . "Tracking reference: {tracking_number}",
        ],
    ];
}

function registrar_email_tracking_url(string $trackingNumber): string
{
    $baseUrl = app_setting('APP_BASE_URL', '');
    if (!is_string($baseUrl) || trim($baseUrl) === '') {
        $host = $_SERVER['SERVER_NAME'] ?? '';
        $port = filter_var($_SERVER['SERVER_PORT'] ?? null, FILTER_VALIDATE_INT);
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        if (!is_string($host)
            || (filter_var($host, FILTER_VALIDATE_IP) === false
                && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false)) {
            throw new RuntimeException('Set APP_BASE_URL to the public registrar site URL before sending tracking links.');
        }
        $baseUrl = $scheme . '://' . $host;
        if (is_int($port) && $port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $baseUrl .= ':' . $port;
        }
        $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        if ($scriptDirectory !== '/' && $scriptDirectory !== '.') {
            $baseUrl .= '/' . trim($scriptDirectory, '/');
        }
    } else {
        $baseUrl = rtrim(trim($baseUrl), '/');
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException('APP_BASE_URL must be a valid public HTTP or HTTPS URL without credentials or query parameters.');
    }

    return $baseUrl . '/index.php?view=track&reference=' . rawurlencode($trackingNumber);
}

function render_registrar_template(PDO $db, string $templateKey, array $values): ?array
{
    $definitions = registrar_email_template_definitions();
    if (!isset($definitions[$templateKey])) {
        throw new InvalidArgumentException('Unknown registrar email template.');
    }

    $definition = $definitions[$templateKey];
    $statement = $db->prepare(
        'SELECT subject, body, is_enabled
         FROM email_templates
         WHERE template_key = :template_key
         LIMIT 1'
    );
    $statement->execute(['template_key' => $templateKey]);
    $template = $statement->fetch();
    if (!$template) {
        throw new RuntimeException('Email templates have not been initialized. Apply the email templates database migration.');
    }
    if (!(bool) $template['is_enabled']) {
        if ($definition['required']) {
            throw new RuntimeException('A required email notification is disabled. Enable it in Notifications & email.');
        }
        return null;
    }

    $replacements = [];
    foreach ($definition['placeholders'] as $placeholder) {
        if (!isset($values[$placeholder]) || !is_string($values[$placeholder])) {
            throw new RuntimeException('A required value for the email template is missing.');
        }
        $replacements['{' . $placeholder . '}'] = $values[$placeholder];
    }
    $subject = strtr((string) $template['subject'], $replacements);
    $body = strtr((string) $template['body'], $replacements);
    if ($templateKey === 'processing') {
        $delayNotice = request_processing_delay_notice();
        if (strpos($body, $delayNotice) === false) {
            $body = rtrim($body) . "\n\n" . $delayNotice;
        }
    }
    if (preg_match('/\{[a-z][a-z0-9_]*\}/', $subject . "\n" . $body) === 1) {
        throw new RuntimeException('The email template contains a placeholder that was not provided.');
    }

    return ['subject' => $subject, 'body' => $body];
}

function send_registrar_template(PDO $db, string $templateKey, string $recipient, array $values): bool
{
    $rendered = render_registrar_template($db, $templateKey, $values);
    if ($rendered === null) {
        return false;
    }

    $trackingUrl = is_string($values['tracking_url'] ?? null) ? $values['tracking_url'] : null;
    send_registrar_email($recipient, $rendered['subject'], $rendered['body'], $trackingUrl);
    return true;
}

function registrar_email_html_body(string $body, ?string $trackingUrl = null): string
{
    $htmlBody = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($trackingUrl !== null && $trackingUrl !== '') {
        $safeUrl = htmlspecialchars($trackingUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $button = '<a href="' . $safeUrl . '" style="display:inline-block;padding:12px 18px;'
            . 'border-radius:8px;background:#167447;color:#ffffff;font-weight:700;text-decoration:none">'
            . 'Track my Request</a>';
        $htmlBody = str_replace($safeUrl, $button, $htmlBody);
    }

    return '<!doctype html><html><body style="margin:0;padding:24px;background:#f5f8f5;'
        . 'font-family:Arial,sans-serif;color:#17251d;line-height:1.6"><div style="max-width:600px;'
        . 'margin:0 auto;padding:24px;border:1px solid #dce5de;border-radius:12px;background:#ffffff">'
        . nl2br($htmlBody, false) . '</div></body></html>';
}

function send_registrar_email(
    string $recipient,
    string $subject,
    string $body,
    ?string $trackingUrl = null
): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PHPMailer is not installed. Run composer install in the project folder.');
    }

    require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('PHPMailer could not be loaded. Run composer install in the project folder.');
    }

    $host = app_setting('SMTP_HOST', '');
    $port = filter_var(app_setting('SMTP_PORT', 587), FILTER_VALIDATE_INT);
    $username = app_setting('SMTP_USERNAME', '');
    $password = app_setting('SMTP_PASSWORD', '');
    $encryption = app_setting('SMTP_ENCRYPTION', 'tls');
    $fromEmail = app_setting('SMTP_FROM_EMAIL', '');
    $fromName = app_setting('SMTP_FROM_NAME', 'Registrar Document Services');

    if (!is_string($host) || trim($host) === ''
        || !is_int($port) || $port < 1 || $port > 65535
        || !is_string($username) || trim($username) === ''
        || !is_string($password) || $password === ''
        || !is_string($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
        || !is_string($encryption) || !in_array($encryption, ['tls', 'ssl'], true)
        || !is_string($fromName) || trim($fromName) === '') {
        throw new RuntimeException('SMTP is not configured. Set SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL, and SMTP_ENCRYPTION in config.local.php.');
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = registrar_email_html_body($body, $trackingUrl);
        $mail->AltBody = $body;
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        error_log('Registrar email delivery failed: ' . $exception->getMessage());
        throw new RuntimeException('The email could not be sent. Check the SMTP settings and try again.');
    }
}
