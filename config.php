<?php
// config.php - Database configuration and connection

// Database configuration
define('DB_HOST', '127.0.0.1:3308');
define('DB_NAME', 'student_portal');
define('DB_USER', 'root');
define('DB_PASS', '');     

// Application configuration
define('APP_NAME', 'Chitova Academy Student Portal');
define('BASE_URL', 'http://localhost/student-portal/');

// Session configuration
session_start();

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function requireUserType($allowedTypes) {
    requireLogin();
    $userType = getUserType();
    if (!in_array($userType, (array)$allowedTypes)) {
        header('Location: unauthorized.php');
        exit;
    }
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    return date($format, strtotime($datetime));
}
// Error and success message functions
function setMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Include this at the top of every page to handle common setup
$pdo = getDBConnection();
?>