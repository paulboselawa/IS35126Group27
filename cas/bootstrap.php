<?php
// CAS Learning Resources Portal — shared setup for every cas/ page
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/rbac.php';
setSecurityHeaders();

const CAS_SESSION_TIMEOUT = 1800;           // 30 minutes, same as the rest of the app
const CAS_MAX_FILE_BYTES  = 50 * 1024 * 1024; // 50 MB (textbooks can be large)
const CAS_STAFF_ROLES     = ['admin', 'teacher'];
define('CAS_STORAGE_DIR', __DIR__ . '/../uploads/cas');

const CAS_TYPES = [
    'syllabus'  => 'Syllabi',
    'textbook'  => 'Textbooks',
    'worksheet' => 'Worksheets',
];

// Allowed upload formats: extension => accepted MIME types (checked with finfo)
const CAS_ALLOWED_FILES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
    'txt'  => ['text/plain'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
];

// Session timeout for logged-in users
if (!empty($_SESSION['authenticated'])) {
    if (time() - ($_SESSION['last_active'] ?? 0) > CAS_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    } else {
        $_SESSION['last_active'] = time();
    }
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function casIsStaff(): bool {
    return !empty($_SESSION['authenticated']) && in_array($_SESSION['role'] ?? '', CAS_STAFF_ROLES, true);
}

function casIsAdmin(): bool {
    return !empty($_SESSION['authenticated']) && ($_SESSION['role'] ?? '') === 'admin';
}

function casRequireStaff(): void {
    if (empty($_SESSION['authenticated'])) {
        header('Location: ../login.php');
        exit;
    }
    if (!casIsStaff()) {
        http_response_code(403);
        die('Access Denied - only teachers and administrators can manage resources.');
    }
}

function casRequireAdmin(): void {
    casRequireStaff();
    if (!casIsAdmin()) {
        http_response_code(403);
        die('Access Denied - only administrators can edit Key Learning Areas.');
    }
}

function casFormatSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

function casGetKlas(PDO $pdo): array {
    return $pdo->query(
        "SELECT k.*,
                SUM(r.type = 'syllabus')  AS syllabus_count,
                SUM(r.type = 'textbook')  AS textbook_count,
                SUM(r.type = 'worksheet') AS worksheet_count,
                COUNT(r.id) AS total
         FROM cas_klas k
         LEFT JOIN cas_resources r ON r.kla_id = k.id
         GROUP BY k.id
         ORDER BY k.sort_order, k.name"
    )->fetchAll();
}

function casFlash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['cas_flash'] = $msg; return null; }
    $m = $_SESSION['cas_flash'] ?? null;
    unset($_SESSION['cas_flash']);
    return $m;
}

function casHeader(string $title, string $active = ''): void {
    $flash = casFlash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - CAS Resources</title>
    <link rel="stylesheet" href="assets/cas.css">
</head>
<body>
<header class="cas-top">
    <a class="cas-brand" href="index.php">CAS <span>Learning Resources</span></a>
    <nav>
        <a href="index.php" class="<?= $active === 'home' ? 'on' : '' ?>">Home</a>
        <a href="resources.php" class="<?= $active === 'browse' ? 'on' : '' ?>">All resources</a>
        <?php if (casIsStaff()): ?>
            <a href="manage.php" class="<?= $active === 'manage' ? 'on' : '' ?>">Manage</a>
            <a href="upload.php" class="<?= $active === 'upload' ? 'on' : '' ?>">Upload</a>
            <?php if (casIsAdmin()): ?>
                <a href="klas.php" class="<?= $active === 'klas' ? 'on' : '' ?>">KLAs</a>
            <?php endif; ?>
            <a href="../logout.php">Logout (<?= e($_SESSION['username']) ?>)</a>
        <?php else: ?>
            <a href="../login.php">Staff login</a>
        <?php endif; ?>
    </nav>
</header>
<main class="cas-main">
    <?php if ($flash): ?><div class="cas-flash"><?= e($flash) ?></div><?php endif; ?>
<?php
}

function casFooter(): void {
    ?>
</main>
<footer class="cas-foot">CAS Learning Resources Portal &middot; Syllabi, textbooks and worksheets for all 5 Key Learning Areas</footer>
</body>
</html>
<?php
}
