<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();
require_once '../includes/rbac.php';
requireRole(['admin', 'hr']);

define('SESSION_TIMEOUT', 1800);
if (time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit;
}
$_SESSION['last_active'] = time();

require_once '../config/db.php';

$stmt = $pdo->prepare("SELECT a.*, j.title as job_title, u.username 
                        FROM applications a 
                        JOIN jobs j ON a.job_id = j.id 
                        JOIN users u ON a.user_id = u.id 
                        WHERE a.status = 'pending'");
$stmt->execute();
$pendingApps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>HR Dashboard</h1>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="../logout.php">Logout</a>
    </div>
    <div class="card">
        <h3>Pending Applications</h3>
        <table border="1">
            <tr><th>Applicant</th><th>Job</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($pendingApps as $app): ?>
            <tr>
                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                <td><?php echo htmlspecialchars($app['status']); ?></td>
                <td><a href="review_application.php?id=<?php echo $app['id']; ?>">Review</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>