<?php
// Admin only: rename / describe / reorder the 5 Key Learning Areas
require_once __DIR__ . '/bootstrap.php';
casRequireAdmin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $rows = $_POST['kla'] ?? [];
        $stmt = $pdo->prepare('UPDATE cas_klas SET code=?, name=?, description=?, sort_order=? WHERE id=?');
        foreach ($rows as $id => $k) {
            $code = strtoupper(trim((string)($k['code'] ?? '')));
            $name = trim((string)($k['name'] ?? ''));
            if ($code === '' || $name === '' || strlen($code) > 10 || mb_strlen($name) > 100) {
                $errors[] = 'Each KLA needs a code (max 10 chars) and a name (max 100 chars).';
                break;
            }
            $stmt->execute([$code, $name, trim((string)($k['description'] ?? '')), (int)($k['sort_order'] ?? 0), (int)$id]);
        }
        if (!$errors) {
            logAudit($pdo, $_SESSION['user_id'], 'CAS: updated Key Learning Areas');
            casFlash('Key Learning Areas saved.');
            header('Location: klas.php');
            exit;
        }
    }
}

$klas = casGetKlas($pdo);
casHeader('Key Learning Areas', 'klas');
?>
<h1>Key Learning Areas</h1>
<p class="cas-muted">Edit the names and descriptions of the KLAs to match your curriculum.</p>
<?php foreach ($errors as $err): ?><div class="cas-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" action="klas.php" class="cas-form wide">
    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
    <table class="cas-table">
        <thead><tr><th>Order</th><th>Code</th><th>Name</th><th>Description</th><th>Resources</th></tr></thead>
        <tbody>
        <?php foreach ($klas as $k): $id = (int)$k['id']; ?>
            <tr>
                <td><input type="number" name="kla[<?= $id ?>][sort_order]" value="<?= (int)$k['sort_order'] ?>" class="cas-num"></td>
                <td><input type="text" name="kla[<?= $id ?>][code]" value="<?= e($k['code']) ?>" maxlength="10" required></td>
                <td><input type="text" name="kla[<?= $id ?>][name]" value="<?= e($k['name']) ?>" maxlength="100" required></td>
                <td><input type="text" name="kla[<?= $id ?>][description]" value="<?= e($k['description']) ?>"></td>
                <td><?= (int)$k['total'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit">Save KLAs</button>
</form>
<?php casFooter();
