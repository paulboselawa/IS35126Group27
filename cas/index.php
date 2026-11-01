<?php
// CAS portal home — the 5 KLAs and the latest resources
require_once __DIR__ . '/bootstrap.php';

$klas = casGetKlas($pdo);
$recent = $pdo->query(
    "SELECT r.id, r.title, r.type, r.grade_level, r.created_at, k.name AS kla_name
     FROM cas_resources r JOIN cas_klas k ON k.id = r.kla_id
     ORDER BY r.created_at DESC, r.id DESC LIMIT 8"
)->fetchAll();

casHeader('Home', 'home');
?>
<section class="cas-hero">
    <h1>Learning resources for every Key Learning Area</h1>
    <p>Find syllabi, textbooks and worksheets for all <?= count($klas) ?> KLAs in one place.</p>
    <form class="cas-search" method="get" action="resources.php">
        <input type="search" name="q" placeholder="Search by title, topic or grade..." aria-label="Search resources">
        <button type="submit">Search</button>
    </form>
</section>

<h2 class="cas-h2">Key Learning Areas</h2>
<div class="cas-grid">
    <?php foreach ($klas as $i => $k): ?>
    <a class="cas-kla kla-<?= ($i % 5) + 1 ?>" href="resources.php?kla=<?= (int)$k['id'] ?>">
        <span class="cas-kla-code"><?= e($k['code']) ?></span>
        <h3><?= e($k['name']) ?></h3>
        <p><?= e($k['description']) ?></p>
        <ul class="cas-counts">
            <li><strong><?= (int)$k['syllabus_count'] ?></strong> Syllabi</li>
            <li><strong><?= (int)$k['textbook_count'] ?></strong> Textbooks</li>
            <li><strong><?= (int)$k['worksheet_count'] ?></strong> Worksheets</li>
        </ul>
    </a>
    <?php endforeach; ?>
</div>

<h2 class="cas-h2">Recently added</h2>
<?php if (!$recent): ?>
    <p class="cas-empty">No resources have been uploaded yet.</p>
<?php else: ?>
    <ul class="cas-recent">
        <?php foreach ($recent as $r): ?>
        <li>
            <span class="cas-badge t-<?= e($r['type']) ?>"><?= e(ucfirst($r['type'])) ?></span>
            <a href="download.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a>
            <span class="cas-muted"><?= e($r['kla_name']) ?><?= $r['grade_level'] ? ' &middot; ' . e($r['grade_level']) : '' ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php casFooter();
