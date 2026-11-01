<?php
// Streams a stored resource file. Files are kept under random names, so this is
// the only way to retrieve them with their original filename.
require_once __DIR__ . '/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM cas_resources WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();

$path = $r ? CAS_STORAGE_DIR . '/' . basename($r['stored_name']) : null;
if (!$r || !is_file($path)) {
    http_response_code(404);
    casHeader('Not found');
    echo '<h1>Resource not found</h1><p><a href="index.php">Back to home</a></p>';
    casFooter();
    exit;
}

$inline = !empty($_GET['view']) && in_array($r['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true);
if (!$inline) {
    $pdo->prepare('UPDATE cas_resources SET downloads = downloads + 1 WHERE id = ?')->execute([$id]);
}

$safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $r['original_name']);
header('Content-Type: ' . $r['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($r['original_name']));
header('Cache-Control: private, max-age=0');
readfile($path);
