<?php
// Teachers/admins: upload a new resource, or edit an existing one (?id=)
require_once __DIR__ . '/bootstrap.php';
casRequireStaff();

$klas   = casGetKlas($pdo);
$klaIds = array_map('intval', array_column($klas, 'id'));
$editId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

$res = ['kla_id' => (int)($_GET['kla'] ?? 0), 'type' => 'worksheet', 'title' => '', 'description' => '', 'grade_level' => ''];
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM cas_resources WHERE id = ?');
    $stmt->execute([$editId]);
    $res = $stmt->fetch();
    if (!$res) { http_response_code(404); die('Resource not found.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Uploads bigger than post_max_size arrive with an empty $_POST
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The file is too large. Maximum size is ' . casFormatSize(CAS_MAX_FILE_BYTES) . '.';
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $res['kla_id']      = (int)($_POST['kla_id'] ?? 0);
        $res['type']        = (string)($_POST['type'] ?? '');
        $res['title']       = trim((string)($_POST['title'] ?? ''));
        $res['description'] = trim((string)($_POST['description'] ?? ''));
        $res['grade_level'] = trim((string)($_POST['grade_level'] ?? ''));

        if (!in_array($res['kla_id'], $klaIds, true)) $errors[] = 'Please choose a Key Learning Area.';
        if (!isset(CAS_TYPES[$res['type']]))          $errors[] = 'Please choose a resource type.';
        if ($res['title'] === '' || mb_strlen($res['title']) > 200) $errors[] = 'Title is required (max 200 characters).';
        if (mb_strlen($res['grade_level']) > 50)      $errors[] = 'Grade level is too long.';

        $file = $_FILES['file'] ?? null;
        $hasFile = $file && $file['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$editId && !$hasFile) $errors[] = 'Please choose a file to upload.';

        $newFile = null;
        if ($hasFile && !$errors) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > CAS_MAX_FILE_BYTES) {
                $errors[] = 'The file is too large. Maximum size is ' . casFormatSize(CAS_MAX_FILE_BYTES) . '.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'Upload failed. Please try again.';
            } else {
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!isset(CAS_ALLOWED_FILES[$ext]) || !in_array($mime, CAS_ALLOWED_FILES[$ext], true)) {
                    $errors[] = 'File type not allowed. Accepted: ' . strtoupper(implode(', ', array_keys(CAS_ALLOWED_FILES))) . '.';
                } else {
                    if (!is_dir(CAS_STORAGE_DIR)) mkdir(CAS_STORAGE_DIR, 0750, true);
                    // Random name with no extension: never executable, never guessable
                    $stored = bin2hex(random_bytes(16));
                    if (!move_uploaded_file($file['tmp_name'], CAS_STORAGE_DIR . '/' . $stored)) {
                        $errors[] = 'Could not save the file on the server.';
                    } else {
                        $newFile = [
                            'stored_name'   => $stored,
                            'original_name' => mb_substr(basename($file['name']), 0, 255),
                            'mime_type'     => $mime === 'application/octet-stream' || $mime === 'application/zip' || $mime === 'application/vnd.ms-office'
                                               ? casMimeForExt($ext) : $mime,
                            'file_size'     => (int)$file['size'],
                        ];
                    }
                }
            }
        }

        if (!$errors) {
            if ($editId) {
                $pdo->prepare('UPDATE cas_resources SET kla_id=?, type=?, title=?, description=?, grade_level=? WHERE id=?')
                    ->execute([$res['kla_id'], $res['type'], $res['title'], $res['description'], $res['grade_level'], $editId]);
                if ($newFile) {
                    $old = $res['stored_name'];
                    $pdo->prepare('UPDATE cas_resources SET stored_name=?, original_name=?, mime_type=?, file_size=? WHERE id=?')
                        ->execute([$newFile['stored_name'], $newFile['original_name'], $newFile['mime_type'], $newFile['file_size'], $editId]);
                    @unlink(CAS_STORAGE_DIR . '/' . basename($old));
                }
                logAudit($pdo, $_SESSION['user_id'], 'CAS: updated resource #' . $editId);
                casFlash('Resource updated.');
            } else {
                $pdo->prepare('INSERT INTO cas_resources (kla_id, type, title, description, grade_level, stored_name, original_name, mime_type, file_size, uploaded_by)
                               VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$res['kla_id'], $res['type'], $res['title'], $res['description'], $res['grade_level'],
                               $newFile['stored_name'], $newFile['original_name'], $newFile['mime_type'], $newFile['file_size'], $_SESSION['user_id']]);
                logAudit($pdo, $_SESSION['user_id'], 'CAS: uploaded resource #' . $pdo->lastInsertId());
                casFlash('Resource uploaded.');
            }
            header('Location: manage.php');
            exit;
        } elseif ($newFile) {
            @unlink(CAS_STORAGE_DIR . '/' . $newFile['stored_name']);
        }
    }
}

function casMimeForExt(string $ext): string {
    return [
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt'  => 'application/vnd.oasis.opendocument.text',
    ][$ext] ?? 'application/octet-stream';
}

casHeader($editId ? 'Edit resource' : 'Upload resource', 'upload');
?>
<p class="cas-crumbs"><a href="manage.php">Manage</a> &rsaquo; <?= $editId ? 'Edit resource' : 'Upload resource' ?></p>
<h1><?= $editId ? 'Edit resource' : 'Upload a resource' ?></h1>

<?php foreach ($errors as $err): ?><div class="cas-error"><?= e($err) ?></div><?php endforeach; ?>

<form class="cas-form" method="post" enctype="multipart/form-data" action="upload.php<?= $editId ? '?id=' . $editId : '' ?>">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
    <?php if ($editId): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>

    <label for="kla_id">Key Learning Area</label>
    <select id="kla_id" name="kla_id" required>
        <option value="">Choose a KLA...</option>
        <?php foreach ($klas as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= (int)$res['kla_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <fieldset class="cas-types">
        <legend>Resource type</legend>
        <?php foreach (CAS_TYPES as $key => $label): ?>
            <label><input type="radio" name="type" value="<?= $key ?>" <?= $res['type'] === $key ? 'checked' : '' ?> required> <?= e(ucfirst($key)) ?></label>
        <?php endforeach; ?>
    </fieldset>

    <label for="title">Title</label>
    <input id="title" type="text" name="title" maxlength="200" value="<?= e($res['title']) ?>" required>

    <label for="grade_level">Grade / year level <span class="cas-muted">(optional)</span></label>
    <input id="grade_level" type="text" name="grade_level" maxlength="50" list="grades" value="<?= e($res['grade_level']) ?>" placeholder="e.g. Grade 7">
    <datalist id="grades">
        <option value="All grades">
        <?php for ($g = 1; $g <= 12; $g++): ?><option value="Grade <?= $g ?>"><?php endfor; ?>
    </datalist>

    <label for="description">Description <span class="cas-muted">(optional)</span></label>
    <textarea id="description" name="description" rows="4"><?= e($res['description']) ?></textarea>

    <label for="file"><?= $editId ? 'Replace file <span class="cas-muted">(optional — current: ' . e($res['original_name']) . ')</span>' : 'File' ?></label>
    <input id="file" type="file" name="file" <?= $editId ? '' : 'required' ?>
           accept=".<?= implode(',.', array_keys(CAS_ALLOWED_FILES)) ?>">
    <p class="cas-muted">PDF, Word, PowerPoint, Excel, ODT, TXT or images. Max <?= casFormatSize(CAS_MAX_FILE_BYTES) ?>.</p>

    <button type="submit"><?= $editId ? 'Save changes' : 'Upload' ?></button>
</form>
<?php casFooter();
