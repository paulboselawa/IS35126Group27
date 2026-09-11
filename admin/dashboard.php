<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();
require_once '../includes/rbac.php';
requireRole(['admin']);  // Only admin can access

define('SESSION_TIMEOUT', 1800); // 30 minutes
if (time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?reason=timeout');
    exit;
}
$_SESSION['last_active'] = time();

require_once '../config/db.php';

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$jobCount = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$appCount = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Curriculum Advisory Services</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="header-brand">
            <img src="../assets/images/logo-icon.svg" alt="Curriculum Advisory Services">
            <h1>Admin Dashboard</h1>
        </div>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    <div class="dashboard-stats">
        <div class="stat-card">Users: <?php echo $userCount; ?></div>
        <div class="stat-card">Jobs: <?php echo $jobCount; ?></div>
        <div class="stat-card">Applications: <?php echo $appCount; ?></div>
    </div>
    <div class="admin-menu">
        <h3>Admin Functions</h3>
        <ul>
            <li><a href="manage_users.php">Manage Users</a></li>
            <li><a href="manage_jobs.php">Manage Jobs</a></li>
            <li><a href="view_applications.php">View All Applications</a></li>
            <li><a href="audit_logs.php">View Audit Logs</a></li>
        </ul>
    </div>
</body>
</html>