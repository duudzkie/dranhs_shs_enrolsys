<?php
/**
 * upload_photo.php — Student ID photo upload endpoint
 *
 * Accepts a multipart/form-data POST with:
 *   - id_photo  : image file (JPG, PNG, GIF) ≤ 5 MB, min 100×100 px
 *   - lrn       : 12-digit Learner Reference Number (identifies the student)
 *
 * Uploads the image to the configured S3-compatible bucket and stores the
 * resulting object URL in students.photo_path.
 *
 * Returns JSON: { "success": true, "url": "..." }
 *            or { "success": false, "error": "..." }
 *
 * Environment variables required:
 *   BUCKET, REGION, ENDPOINT, ACCESS_KEY_ID, SECRET_ACCESS_KEY
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function json_error(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function json_ok(array $payload): never {
    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $payload));
    exit;
}

/**
 * Sign and execute an AWS Signature Version 4 PUT request.
 * Returns the public URL on success, or throws RuntimeException on failure.
 */
function s3_put_object(
    string $bucket,
    string $region,
    string $endpoint,
    string $access_key,
    string $secret_key,
    string $object_key,
    string $body,
    string $content_type
): string {
    $host        = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
    $scheme      = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';
    $url         = rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($object_key, '/');

    $now         = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $date_stamp  = $now->format('Ymd');
    $amz_date    = $now->format('Ymd\THis\Z');

    $payload_hash = hash('sha256', $body);

    // ── Canonical request ─────────────────────────────────────────────────────
    $canonical_headers =
        'content-type:' . $content_type . "\n" .
        'host:' . $host . "\n" .
        'x-amz-content-sha256:' . $payload_hash . "\n" .
        'x-amz-date:' . $amz_date . "\n";

    $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

    $canonical_uri = '/' . $bucket . '/' . ltrim($object_key, '/');

    $canonical_request = implode("\n", [
        'PUT',
        $canonical_uri,
        '',                   // query string
        $canonical_headers,
        $signed_headers,
        $payload_hash,
    ]);

    // ── String to sign ────────────────────────────────────────────────────────
    $credential_scope = $date_stamp . '/' . $region . '/s3/aws4_request';
    $string_to_sign   = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amz_date,
        $credential_scope,
        hash('sha256', $canonical_request),
    ]);

    // ── Signing key ───────────────────────────────────────────────────────────
    $signing_key = hash_hmac('sha256', 'aws4_request',
        hash_hmac('sha256', 's3',
            hash_hmac('sha256', $region,
                hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true),
            true),
        true),
    true);

    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

    $authorization =
        'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $credential_scope .
        ', SignedHeaders=' . $signed_headers .
        ', Signature=' . $signature;

    // ── HTTP PUT ──────────────────────────────────────────────────────────────
    $context = stream_context_create([
        'http' => [
            'method'        => 'PUT',
            'header'        =>
                "Content-Type: {$content_type}\r\n" .
                "x-amz-date: {$amz_date}\r\n" .
                "x-amz-content-sha256: {$payload_hash}\r\n" .
                "Authorization: {$authorization}\r\n" .
                "Content-Length: " . strlen($body) . "\r\n",
            'content'       => $body,
            'ignore_errors' => true,
            'timeout'       => 30,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);

    // $http_response_header is set by file_get_contents
    $status_line = $http_response_header[0] ?? '';
    preg_match('/HTTP\/\S+\s+(\d+)/', $status_line, $m);
    $http_code = (int)($m[1] ?? 0);

    if ($http_code < 200 || $http_code >= 300) {
        throw new RuntimeException(
            "S3 PUT failed (HTTP {$http_code}): " . substr((string)$result, 0, 300)
        );
    }

    return $url;
}

// ── Ensure photo_path column exists ──────────────────────────────────────────

$conn = db_connect();

$col_check = $conn->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'students'
       AND COLUMN_NAME  = 'photo_path'"
);
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN photo_path VARCHAR(500) NULL AFTER avatar_path");
}

// ── Only accept POST ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST with multipart/form-data.', 405);
}

// ── Validate LRN ─────────────────────────────────────────────────────────────

$lrn = trim($_POST['lrn'] ?? '');
if (!preg_match('/^\d{12}$/', $lrn)) {
    json_error('A valid 12-digit LRN is required.');
}

// Verify student exists
$stu_stmt = $conn->prepare("SELECT id FROM students WHERE lrn = ? LIMIT 1");
if (!$stu_stmt) {
    json_error('Database error.', 500);
}
$stu_stmt->bind_param('s', $lrn);
$stu_stmt->execute();
$stu_stmt->store_result();
if ($stu_stmt->num_rows === 0) {
    $stu_stmt->close();
    json_error('No student found with that LRN.');
}
$stu_stmt->close();

// ── Validate uploaded file ────────────────────────────────────────────────────

if (!isset($_FILES['id_photo'])) {
    json_error('No file uploaded. Use field name "id_photo".');
}

$file = $_FILES['id_photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    json_error($upload_errors[$file['error']] ?? 'Unknown upload error.');
}

// Max 5 MB
const MAX_FILE_SIZE = 5 * 1024 * 1024;
if ($file['size'] > MAX_FILE_SIZE) {
    json_error('File is too large. Maximum allowed size is 5 MB.');
}

// Server-side MIME check (do NOT trust $_FILES['type'])
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$allowed  = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
];
if (!array_key_exists($mime, $allowed)) {
    json_error('Invalid file type. Only JPG, PNG, and GIF images are accepted.');
}
$ext = $allowed[$mime];

// Minimum dimensions: 100×100 px
$img_info = @getimagesize($file['tmp_name']);
if ($img_info === false) {
    json_error('Could not read image dimensions. The file may be corrupt.');
}
[$img_w, $img_h] = $img_info;
if ($img_w < 100 || $img_h < 100) {
    json_error("Image is too small ({$img_w}×{$img_h} px). Minimum size is 100×100 px.");
}

// ── Read S3 config from environment ──────────────────────────────────────────

$s3_bucket     = (string)getenv('BUCKET');
$s3_region     = (string)getenv('REGION');
$s3_endpoint   = (string)getenv('ENDPOINT');
$s3_access_key = (string)getenv('ACCESS_KEY_ID');
$s3_secret_key = (string)getenv('SECRET_ACCESS_KEY');

if ($s3_bucket === '' || $s3_region === '' || $s3_endpoint === '' ||
    $s3_access_key === '' || $s3_secret_key === '') {
    error_log('upload_photo.php: S3 environment variables are not fully configured.');
    json_error('Storage is not configured on this server. Please contact the administrator.', 503);
}

// ── Build object key and upload ───────────────────────────────────────────────

$object_key  = 'id_photos/' . $lrn . '_' . time() . '.' . $ext;
$file_body   = file_get_contents($file['tmp_name']);

if ($file_body === false) {
    json_error('Failed to read uploaded file.', 500);
}

try {
    $public_url = s3_put_object(
        $s3_bucket,
        $s3_region,
        $s3_endpoint,
        $s3_access_key,
        $s3_secret_key,
        $object_key,
        $file_body,
        $mime
    );
} catch (RuntimeException $e) {
    error_log('upload_photo.php S3 error: ' . $e->getMessage());
    json_error('Failed to upload image to storage. Please try again later.', 502);
}

// ── Persist URL in database ───────────────────────────────────────────────────

$upd = $conn->prepare("UPDATE students SET photo_path = ? WHERE lrn = ?");
if (!$upd) {
    json_error('Database error while saving photo path.', 500);
}
$upd->bind_param('ss', $public_url, $lrn);
if (!$upd->execute()) {
    $upd->close();
    $conn->close();
    json_error('Failed to save photo path to database.', 500);
}
$upd->close();
$conn->close();

json_ok(['url' => $public_url, 'lrn' => $lrn]);
