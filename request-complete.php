<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
start_app_session();

$result = $_SESSION['request_result'] ?? null;
unset($_SESSION['request_result']);
if (!is_array($result) || !isset($result['tracking_number'], $result['document_name'], $result['email_sent'])) {
    header('Location: request.php');
    exit;
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#146c43">
    <title>Request Submitted</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <main class="page-shell">
        <section class="login-card" aria-labelledby="page-title">
            <div class="brand-mark" aria-hidden="true">✓</div>
            <p class="eyebrow">REQUEST SUBMITTED</p>
            <h1 id="page-title">Request submitted successfully!</h1>
            <p class="intro">Your request for <?= $escape($result['document_name']) ?> is pending Registrar review.</p>
            <?php if ($result['email_sent']): ?>
                <p class="security-note">
                    A confirmation email was sent to <?= $escape(mask_student_email((string) $result['email'])) ?>.
                    Check your inbox and, if you don't see it, your Spam or Junk folder.
                </p>
            <?php else: ?>
                <p class="notice" role="status">Your request was submitted successfully, but the confirmation email could not be sent. Please contact the Registrar if needed.</p>
            <?php endif; ?>
            <a class="secondary-button" href="index.php">Go to Home <span aria-hidden="true">→</span></a>
        </section>
    </main>
</body>
</html>
