<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM patients ORDER BY name");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    requireApiCsrf();
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) jsonError('Invalid request body');

    $id = $_GET['id'] ?? null;
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    $notes = $data['notes'] ?? '';

    if (!$name) jsonError('Name is required');

    if ($id) {
        $stmt = $db->prepare("UPDATE patients SET name=?, phone=?, email=?, notes=? WHERE id=?");
        $stmt->execute([$name, $phone, $email, $notes, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO patients (name, phone, email, notes) VALUES (?,?,?,?)");
        $stmt->execute([$name, $phone, $email, $notes]);
    }

    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    requireApiCsrf();
    requireAdmin();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID required');
    $stmt = $db->prepare("DELETE FROM patients WHERE id=?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}
