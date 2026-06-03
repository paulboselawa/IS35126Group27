<?php
// FROM PROJECT SPEC - Page 2 & 3 (RBAC and Least Privilege)

// Check if user has required role
function requireRole($allowedRoles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: /IS35126Group27/login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header('HTTP/1.0 403 Forbidden');
        die('Access Denied - You do not have permission to view this page.');
    }
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Log audit actions (from Project.pdf page 5)
function logAudit($pdo, $user_id, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip]);
}
?>