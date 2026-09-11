<?php
// REGISTRATION FORM - WITH XSS PREVENTION
session_start();
require_once 'config/db.php';
require_once 'config/security.php';
setSecurityHeaders();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $phone = trim($_POST['phone']);

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, phone) VALUES (?, ?, ?, 'applicant', ?)");
                if ($stmt->execute([$username, $email, $hashed, $phone])) {
                    $success = 'Registration successful! <a href="login.php">Login here</a>';
                } else {
                    $error = 'Registration failed';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - Curriculum Advisory Services</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/logo-icon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="card">
        <img class="brand-logo" src="assets/images/logo.svg" alt="Curriculum Advisory Services">
        <h2>Applicant Registration</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <label>Username*</label>
            <input type="text" name="username" required>
            
            <label>Email*</label>
            <input type="email" name="email" required>
            
            <label>Password* (min 8 characters)</label>
            <input type="password" name="password" required>
            
            <label>Confirm Password*</label>
            <input type="password" name="confirm_password" required>
            
            <label>Phone Number</label>
            <input type="tel" name="phone">
            
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>