<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM doctors WHERE is_active = 1 ORDER BY name");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) jsonError('Invalid request body');

    $id = $_GET['id'] ?? null;
    $name = $data['name'] ?? '';
    $specialization = $data['specialization'] ?? '';
    $color = $data['color'] ?? '#4A90D9';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';

    if (!$name) jsonError('Name is required');

    if ($id) {
        $stmt = $db->prepare("UPDATE doctors SET name=?, specialization=?, color=?, phone=?, email=? WHERE id=?");
        $stmt->execute([$name, $specialization, $color, $phone, $email, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO doctors (name, specialization, color, phone, email) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $specialization, $color, $phone, $email]);
    }

    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    requireAdmin();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID required');
    $stmt = $db->prepare("UPDATE doctors SET is_active=0 WHERE id=?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}
