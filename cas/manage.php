<?php
// Teachers/admins: list, edit and delete resources
require_once __DIR__ . '/bootstrap.php';
casRequireStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        casFlash('Invalid request. Please try again.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT stored_name, title FROM cas_resources WHERE id = ?');
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare('DELETE FROM cas_resources WHERE id = ?')->execute([$id]);
            @unlink(CAS_STORAGE_DIR . '/' . basename($row['stored_name']));
            logAudit($pdo, $_SESSION['user_id'], 'CAS: deleted resource #' . $id);
            casFlash('Deleted "' . $row['title'] . '".');
        }
    }
    header('Location: manage.php');
    exit;
}

$confirmId = (int)($_GET['delete'] ?? 0);
$klas = casGetKlas($pdo);
$klaId = (int)($_GET['kla'] ?? 0);

$sql = "SELECT r.*, k.name AS kla_name, u.username AS uploader
        FROM cas_resources r
        JOIN cas_klas k ON k.id = r.kla_id
        LEFT JOIN users u ON u.id = r.uploaded_by"
     . ($klaId ? ' WHERE r.kla_id = ?' : '')
     . ' ORDER BY r.created_at DESC, r.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($klaId ? [$klaId] : []);
$resources = $stmt->fetchAll();

$totals = ['syllabus' => 0, 'textbook' => 0, 'worksheet' => 0];
foreach ($klas as $k) {
    $totals['syllabus']  += (int)$k['syllabus_count'];
    $totals['textbook']  += (int)$k['textbook_count'];
    $totals['worksheet'] += (int)$k['worksheet_count'];
}

casHeader('Manage resources', 'manage');
?>
<div class="cas-row">
    <h1>Manage resources</h1>
    <a class="cas-btn" href="upload.php">+ Upload resource</a>
</div>

<div class="cas-stats">
    <div><strong><?= array_sum($totals) ?></strong>Total</div>
    <?php foreach (CAS_TYPES as $key => $label): ?>
        <div><strong><?= $totals[$key] ?></strong><?= $label ?></div>
    <?php endforeach; ?>
</div>

<table class="cas-table">
    <thead><tr><th>KLA</th><?php foreach (CAS_TYPES as $label): ?><th><?= $label ?></th><?php endforeach; ?><th>Total</th></tr></thead>
    <tbody>
    <?php foreach ($klas as $k): ?>
        <tr>
            <td><a href="manage.php?kla=<?= (int)$k['id'] ?>"><?= e($k['name']) ?></a></td>
            <td><?= (int)$k['syllabus_count'] ?></td>
            <td><?= (int)$k['textbook_count'] ?></td>
            <td><?= (int)$k['worksheet_count'] ?></td>
            <td><?= (int)$k['total'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2 class="cas-h2"><?= $klaId ? 'Resources in this KLA' : 'All resources' ?>
    <?php if ($klaId): ?> <a class="cas-reset" href="manage.php">show all</a><?php endif; ?></h2>

<?php if (!$resources): ?>
    <p class="cas-empty">No resources yet. <a href="upload.php">Upload the first one.</a></p>
<?php else: ?>
<table class="cas-table">
    <thead><tr><th>Title</th><th>KLA</th><th>Type</th><th>Grade</th><th>File</th><th>Downloads</th><th>Uploaded</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($resources as $r): ?>
        <tr>
            <td><a href="download.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a></td>
            <td><?= e($r['kla_name']) ?></td>
            <td><span class="cas-badge t-<?= e($r['type']) ?>"><?= e(ucfirst($r['type'])) ?></span></td>
            <td><?= e($r['grade_level']) ?></td>
            <td><?= e($r['original_name']) ?> <span class="cas-muted">(<?= casFormatSize((int)$r['file_size']) ?>)</span></td>
            <td><?= (int)$r['downloads'] ?></td>
            <td><?= e(substr($r['created_at'], 0, 10)) ?><br><span class="cas-muted"><?= e($r['uploader'] ?? '—') ?></span></td>
            <td class="cas-nowrap">
                <?php if ($confirmId === (int)$r['id']): ?>
                    <form method="post" action="manage.php" class="cas-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="cas-btn danger">Confirm delete</button>
                    </form>
                    <a href="manage.php">Cancel</a>
                <?php else: ?>
                    <a href="upload.php?id=<?= (int)$r['id'] ?>">Edit</a> &middot;
                    <a href="manage.php?delete=<?= (int)$r['id'] ?><?= $klaId ? '&amp;kla=' . $klaId : '' ?>">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php casFooter();
