<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, username, full_name, role, permissions, created_at FROM users ORDER BY username");
    $users = $stmt->fetchAll();
    foreach ($users as &$u) {
        $u['permissions'] = json_decode($u['permissions'] ?? '[]', true);
    }
    jsonResponse($users);
}

if ($method === 'POST') {
    requireApiCsrf();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) jsonError('Invalid request body');

    $id = $_GET['id'] ?? null;
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $role = $data['role'] ?? 'user';
    $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : '[]';

    if (!$username) jsonError('Username is required');

    if ($id) {
        if ($password) {
            $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, role=?, permissions=? WHERE id=?");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role, $permissions, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=?, permissions=? WHERE id=?");
            $stmt->execute([$username, $full_name, $role, $permissions, $id]);
        }
    } else {
        if (!$password) jsonError('Password is required for new users');
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, permissions) VALUES (?,?,?,?,?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role, $permissions]);
    }

    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    requireApiCsrf();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID required');
    if ($id == $_SESSION['user_id']) jsonError('Cannot delete yourself');

    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}
