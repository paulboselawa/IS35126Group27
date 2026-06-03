<?php
// FROM WEEK 11 LAB - Page 4 (exact structure)
define('DB_HOST', getenv('MYSQLHOST')  ?: 'localhost');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'IS35126Group27');
define('DB_USER', getenv('MYSQLUSER')  ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT', getenv('MYSQLPORT')  ?: '3306');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,  // Prevents SQL injection
        ]
    );
} catch (PDOException $e) {
    error_log('DB Connection failed: ' . $e->getMessage());
    die('Database connection error. Please try again later.');
}
?>