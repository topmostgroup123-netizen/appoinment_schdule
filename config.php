<?php
// Session security config
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

define('DB_PATH', __DIR__ . '/data/clinic.db');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'");

function getDB() {
    static $db = null;
    if ($db === null) {
        if (!is_dir(dirname(DB_PATH))) {
            mkdir(dirname(DB_PATH), 0755, true);
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function hasPermission($perm) {
    if (isAdmin()) return true;
    $perms = $_SESSION['permissions'] ?? [];
    return in_array($perm, $perms);
}

function requirePermission($perm) {
    requireLogin();
    if (!hasPermission($perm)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// CSRF protection
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken() {
    $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        jsonError('Invalid or missing CSRF token', 403);
    }
}

function csrfInput() {
    echo '<input type="hidden" name="_csrf" value="' . getCsrfToken() . '">';
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($msg, $code = 400) {
    jsonResponse(['error' => $msg], $code);
}

function addLog($action, $entity_type, $entity_id = null, $detail = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO logs (user_id, username, action, entity_type, entity_id, detail) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $_SESSION['username'] ?? 'system',
        $action,
        $entity_type,
        $entity_id,
        $detail
    ]);
}

// API CSRF check for JSON endpoints (via X-Requested-With header)
function requireApiCsrf() {
    $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($header) !== 'xmlhttprequest') {
        jsonError('Invalid request origin', 403);
    }
}
