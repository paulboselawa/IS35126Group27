<?php
// FROM WEEK 11 LAB - Login with 2FA (pages 5-6)
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['role'] == 'hr') {
        header('Location: hr/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

require_once 'config/db.php';
require_once 'config/mail.php';
require_once 'config/security.php';
setSecurityHeaders();
$error = '';

// Check IP lockout
function isIPLocked($pdo, $ip) {
    $lockoutTime = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
    $stmt->execute([$ip, $lockoutTime]);
    return $stmt->fetchColumn() >= 5;
}

function recordFailedAttempt($pdo, $ip) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$ip]);
}

function clearLoginAttempts($pdo, $ip) {
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (isIPLocked($pdo, $ip)) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            clearLoginAttempts($pdo, $ip);
            
            // Delete old OTPs
            $pdo->prepare('DELETE FROM otp_tokens WHERE user_id = ?')->execute([$user['id']]);
            
            // Generate 6-digit OTP (from Week 11 Lab)
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = hash('sha256', $otp);

            $stmt = $pdo->prepare('INSERT INTO otp_tokens (user_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
            $stmt->execute([$user['id'], $otpHash]);
            
            // Store in session for 2FA step
            $_SESSION['2fa_user_id'] = $user['id'];
            $_SESSION['2fa_username'] = $user['username'];
            $_SESSION['2fa_email'] = $user['email'];
            $_SESSION['2fa_role'] = $user['role'];
            
            $sent = sendOTPEmail($user['email'], $user['username'], $otp);
            if (!$sent) {
                $error = 'Failed to send OTP email. Please try again.';
            } else {
                header('Location: verify_otp.php');
                exit;
            }
        } else {
            recordFailedAttempt($pdo, $ip);
            $error = 'Invalid username or password.';
            usleep(500000); // 0.5 second delay
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Curriculum Advisory Services</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo-icon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="card">
        <img class="brand-logo" src="assets/images/logo.svg" alt="Curriculum Advisory Services">
        <h2>Recruitment Portal Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <label>Username</label>
            <input type="text" name="username" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register</a></p>
        <p><a href="google_login.php">Login with Google</a></p>
        <p><small>After password, a 2FA code will be sent to your email</small></p>
    </div>
</body>
</html>