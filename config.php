<?php
session_start();

define('DB_PATH', __DIR__ . '/data/clinic.db');

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
        header('HTTP/1.1 403 Forbidden');
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
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden';
        exit;
    }
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
