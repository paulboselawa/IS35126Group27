<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once 'vendor/autoload.php';
require_once 'config/db.php';
require_once 'config/security.php';
require_once 'includes/rbac.php';
setSecurityHeaders();

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;

$provider = new Google([
    'clientId'     => getenv('GOOGLE_CLIENT_ID')     ?: '',
    'clientSecret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirectUri'  => getenv('GOOGLE_REDIRECT_URI')  ?: 'http://localhost/IS35126Group27/google_login.php',
]);

// Step 1 — redirect user to Google
if (!isset($_GET['code'])) {
    $authUrl = $provider->getAuthorizationUrl(['scope' => ['openid', 'email', 'profile']]);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;
}

// Step 2 — Google redirected back; validate state to prevent CSRF
if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    die('Invalid OAuth state. Please try again.');
}
unset($_SESSION['oauth2state']);

// Step 3 — exchange code for token and get user info
try {
    $token      = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    /** @var GoogleUser $googleUser */
    $googleUser = $provider->getResourceOwner($token);
} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    header('Location: login.php?error=oauth_failed');
    exit;
}

$email = $googleUser->getEmail();
$name  = $googleUser->getName();

if (!$email) {
    header('Location: login.php?error=no_email');
    exit;
}

// Step 4 — look up user by email
$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Step 5 — auto-register if first time
if (!$user) {
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
    // Ensure username is unique
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$username]);
    if ($check->fetch()) {
        $username .= rand(100, 999);
    }
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, '', 'applicant')");
    $stmt->execute([$username, $email]);
    $userId   = $pdo->lastInsertId();
    $role     = 'applicant';
    $username = $username;
} else {
    $userId   = $user['id'];
    $role     = $user['role'];
    $username = $user['username'];
}

// Step 6 — create authenticated session
session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['user_id']       = $userId;
$_SESSION['username']      = $username;
$_SESSION['role']          = $role;
$_SESSION['last_active']   = time();

logAudit($pdo, $userId, 'User logged in via Google OAuth');

// Step 7 — redirect based on role
if ($role === 'admin') {
    header('Location: admin/dashboard.php');
} elseif ($role === 'hr') {
    header('Location: hr/dashboard.php');
} else {
    header('Location: dashboard.php');
}
exit;
