<?php
// Browse / search resources, optionally filtered by KLA, type and grade
require_once __DIR__ . '/bootstrap.php';

$klas   = casGetKlas($pdo);
$klaId  = (int)($_GET['kla'] ?? 0);
$type   = $_GET['type'] ?? '';
$grade  = trim((string)($_GET['grade'] ?? ''));
$q      = trim((string)($_GET['q'] ?? ''));

if (!isset(CAS_TYPES[$type])) $type = '';

$currentKla = null;
foreach ($klas as $k) {
    if ((int)$k['id'] === $klaId) $currentKla = $k;
}
if (!$currentKla) $klaId = 0;

$where = [];
$params = [];
if ($klaId) { $where[] = 'r.kla_id = ?';      $params[] = $klaId; }
if ($type)  { $where[] = 'r.type = ?';        $params[] = $type; }
if ($grade !== '') { $where[] = 'r.grade_level = ?'; $params[] = $grade; }
if ($q !== '') {
    $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.grade_level LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    array_push($params, $like, $like, $like);
}
$sql = "SELECT r.*, k.name AS kla_name, k.code AS kla_code
        FROM cas_resources r JOIN cas_klas k ON k.id = r.kla_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY k.sort_order, FIELD(r.type,'syllabus','textbook','worksheet'), r.grade_level, r.title";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

$grades = $pdo->query("SELECT DISTINCT grade_level FROM cas_resources WHERE grade_level <> '' ORDER BY grade_level")
              ->fetchAll(PDO::FETCH_COLUMN);

// Group by type for display
$byType = array_fill_keys(array_keys(CAS_TYPES), []);
foreach ($resources as $r) $byType[$r['type']][] = $r;

function casFilterUrl(array $overrides): string {
    $p = array_merge([
        'kla'   => $_GET['kla'] ?? '',
        'type'  => $_GET['type'] ?? '',
        'grade' => $_GET['grade'] ?? '',
        'q'     => $_GET['q'] ?? '',
    ], $overrides);
    return 'resources.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0));
}

casHeader($currentKla ? $currentKla['name'] : 'All resources', $currentKla ? '' : 'browse');
?>
<p class="cas-crumbs"><a href="index.php">Home</a> &rsaquo; <?= $currentKla ? e($currentKla['name']) : 'All resources' ?></p>
<h1><?= $currentKla ? e($currentKla['name']) : 'All resources' ?></h1>
<?php if ($currentKla && $currentKla['description']): ?><p class="cas-muted"><?= e($currentKla['description']) ?></p><?php endif; ?>

<form class="cas-filters" method="get" action="resources.php">
    <select name="kla" aria-label="Key Learning Area">
        <option value="">All KLAs</option>
        <?php foreach ($klas as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= $klaId === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type" aria-label="Resource type">
        <option value="">All types</option>
        <?php foreach (CAS_TYPES as $key => $label): ?>
            <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <select name="grade" aria-label="Grade level">
        <option value="">All grades</option>
        <?php foreach ($grades as $g): ?>
            <option value="<?= e($g) ?>" <?= $grade === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search...">
    <button type="submit">Filter</button>
    <a class="cas-reset" href="resources.php<?= $klaId ? '?kla=' . $klaId : '' ?>">Reset</a>
</form>

<nav class="cas-tabs">
    <a href="<?= e(casFilterUrl(['type' => ''])) ?>" class="<?= $type === '' ? 'on' : '' ?>">All (<?= count($resources) ?>)</a>
    <?php foreach (CAS_TYPES as $key => $label): ?>
        <a href="<?= e(casFilterUrl(['type' => $key])) ?>" class="<?= $type === $key ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$resources): ?>
    <p class="cas-empty">No resources match your filters.</p>
<?php endif; ?>

<?php foreach ($byType as $t => $items): if (!$items) continue; ?>
<section class="cas-section">
    <h2 class="cas-h2"><?= CAS_TYPES[$t] ?> <span class="cas-muted">(<?= count($items) ?>)</span></h2>
    <div class="cas-list">
        <?php foreach ($items as $r):
            $ext = strtoupper(pathinfo($r['original_name'], PATHINFO_EXTENSION)); ?>
        <article class="cas-item">
            <div class="cas-file"><?= e($ext) ?></div>
            <div class="cas-item-body">
                <h3><?= e($r['title']) ?></h3>
                <?php if ($r['description']): ?><p><?= nl2br(e($r['description'])) ?></p><?php endif; ?>
                <p class="cas-meta">
                    <?php if (!$klaId): ?><span><?= e($r['kla_name']) ?></span><?php endif; ?>
                    <?php if ($r['grade_level']): ?><span><?= e($r['grade_level']) ?></span><?php endif; ?>
                    <span><?= casFormatSize((int)$r['file_size']) ?></span>
                    <span><?= (int)$r['downloads'] ?> downloads</span>
                </p>
            </div>
            <div class="cas-actions">
                <?php if (in_array($r['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true)): ?>
                    <a class="cas-btn ghost" href="download.php?id=<?= (int)$r['id'] ?>&amp;view=1" target="_blank" rel="noopener">View</a>
                <?php endif; ?>
                <a class="cas-btn" href="download.php?id=<?= (int)$r['id'] ?>">Download</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>
<?php casFooter();
