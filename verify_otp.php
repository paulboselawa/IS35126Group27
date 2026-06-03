<?php
// FROM WEEK 11 LAB - Pages 6-7 (exact logic)
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
require_once 'config/security.php';
require_once 'includes/rbac.php';
setSecurityHeaders();
define('MAX_OTP_ATTEMPTS', 5);
$error = '';
$userId = $_SESSION['2fa_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $submitted = trim($_POST['otp'] ?? '');
    
    if (!preg_match('/^\d{6}$/', $submitted)) {
        $error = 'Please enter the 6-digit code.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, otp_hash, attempts FROM otp_tokens
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $token = $stmt->fetch();
        
        if (!$token) {
            $error = 'Your code has expired. <a href="login.php">Request a new one</a>.';
        } elseif ($token['attempts'] >= MAX_OTP_ATTEMPTS) {
            $error = 'Too many failed attempts. <a href="login.php">Start over</a>.';
        } else {
            $submittedHash = hash('sha256', $submitted);
            if (hash_equals($token['otp_hash'], $submittedHash)) {
                // SUCCESS - Delete used OTP
                $pdo->prepare('DELETE FROM otp_tokens WHERE id = ?')->execute([$token['id']]);
                
                // Regenerate session ID (prevents fixation)
                session_regenerate_id(true);
                
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $_SESSION['2fa_username'];
                $_SESSION['role'] = $_SESSION['2fa_role'];
                $_SESSION['last_active'] = time();
                
                // Log audit
                logAudit($pdo, $userId, 'User logged in with 2FA');
                
                // Clean up
                unset($_SESSION['2fa_user_id'], $_SESSION['2fa_username'], $_SESSION['2fa_email'], $_SESSION['2fa_role']);
                
                // Redirect based on role
                if ($_SESSION['role'] == 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($_SESSION['role'] == 'hr') {
                    header('Location: hr/dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $pdo->prepare('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?')
                    ->execute([$token['id']]);
                $remaining = MAX_OTP_ATTEMPTS - ($token['attempts'] + 1);
                $error = 'Incorrect code. ' . max(0, $remaining) . ' attempt(s) remaining.';
            }
        }
    }
    } // end else (csrf valid)
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Code</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="card">
        <h2>Check Your Email</h2>
        <p>We sent a 6-digit code to <strong><?php echo htmlspecialchars($_SESSION['2fa_email']); ?></strong></p>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="text" name="otp" maxlength="6" placeholder="000000" required>
            <button type="submit">Verify Code</button>
        </form>
        <p><a href="login.php">Back to login</a></p>
    </div>
</body>
</html>