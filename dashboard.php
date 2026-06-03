<?php
// APPLICANT DASHBOARD
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'applicant') {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: hr/dashboard.php');
    }
    exit;
}

require_once 'config/db.php';
require_once 'includes/rbac.php';

// Session timeout
define('SESSION_TIMEOUT', 1800);
if (time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_active'] = time();

// Get user's applications
$stmt = $pdo->prepare("SELECT a.*, j.title as job_title 
                        FROM applications a 
                        JOIN jobs j ON a.job_id = j.id 
                        WHERE a.user_id = ? 
                        ORDER BY a.submitted_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();

// Get available jobs
$jobs = $pdo->query("SELECT id, title, department FROM jobs WHERE status = 'open'")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Applicant Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <div>
            <a href="apply.php">Apply for Job</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            My Applications
            <span><?php echo count($applications); ?></span>
        </div>
        <div class="stat-card">
            Available Jobs
            <span><?php echo count($jobs); ?></span>
        </div>
    </div>
    
    <div class="card">
        <h2>Available Positions</h2>
        <?php if (empty($jobs)): ?>
            <p>No jobs available at this time.</p>
        <?php else: ?>
            <table>
                <tr><th>Job Title</th><th>Department</th><th>Action</th></tr>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                    <td><?php echo htmlspecialchars($job['department']); ?></td>
                    <td><a href="apply.php?job_id=<?php echo $job['id']; ?>">Apply Now</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>My Applications</h2>
        <?php if (empty($applications)): ?>
            <p>You haven't submitted any applications yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Job Title</th><th>Status</th><th>Submitted Date</th></tr>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                    <td><?php echo htmlspecialchars($app['status']); ?></td>
                    <td><?php echo $app['submitted_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>