<?php
declare(strict_types=1);

require __DIR__ . '/request_helpers.php';
start_app_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function lookup_response(int $status, array $response): never
{
    http_response_code($status);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    lookup_response(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
    lookup_response(400, ['ok' => false, 'message' => 'Refresh the page and try again.']);
}

$studentNumber = trim((string) ($_POST['student_no'] ?? ''));
$documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
if ($studentNumber === '' || strlen($studentNumber) > 64 || !is_int($documentId) || $documentId < 1) {
    lookup_response(400, ['ok' => false, 'message' => 'Enter a valid student ID.']);
}

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$bucket = 'student-lookup:' . hash('sha256', $clientIp);
$db = db();
$limit = $db->prepare(
    'INSERT INTO rate_limits (bucket, window_start, hits)
     VALUES (:bucket, NOW(), 1)
     ON DUPLICATE KEY UPDATE
         hits = IF(window_start < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, hits + 1),
         window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_start)'
);
$limit->execute(['bucket' => $bucket]);
$hitCount = $db->prepare('SELECT hits FROM rate_limits WHERE bucket = :bucket');
$hitCount->execute(['bucket' => $bucket]);
if ((int) $hitCount->fetchColumn() > 30) {
    lookup_response(429, ['ok' => false, 'message' => 'Too many lookups. Please wait a few minutes and try again.']);
}

if (!active_request_document($db, $documentId)) {
    lookup_response(404, ['ok' => false, 'message' => 'This document is no longer available.']);
}

$student = find_request_student($db, $studentNumber);
if (!$student) {
    lookup_response(404, ['ok' => false, 'message' => 'No currently enrolled student record matches that ID. Check the ID or contact the Registrar.']);
}
if (!is_string($student['email']) || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
    lookup_response(422, ['ok' => false, 'message' => 'There is no valid email address on file. Please visit the Registrar to update it.']);
}

$yearLevel = $student['year_level'] === null ? 'Not available' : 'Year ' . (int) $student['year_level'];
$course = $student['course'] === null ? 'Not available' : (string) $student['course'];
$enrollmentStatus = match ($student['status']) {
    'currently_enrolled' => 'Currently enrolled',
    'alumni' => 'Alumni',
    'undergrad' => 'Not currently enrolled',
    default => 'Not available',
};

lookup_response(200, [
    'ok' => true,
    'student' => [
        'student_no' => (string) $student['student_no'],
        'full_name' => (string) $student['full_name'],
        'course' => $course,
        'year_level' => $yearLevel,
        'enrollment_status' => $enrollmentStatus,
        'masked_email' => mask_student_email((string) $student['email']),
    ],
]);
