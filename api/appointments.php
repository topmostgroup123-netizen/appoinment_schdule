<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("
        SELECT a.*, p.name as patient_name, p.phone as patient_phone,
               d.name as doctor_name, d.color as doctor_color
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.date, a.start_time
    ");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    requireApiCsrf();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) jsonError('Invalid request body');

    $id = $_GET['id'] ?? null;

    // On update, merge with existing data so partial updates work
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM appointments WHERE id=?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) jsonError('Appointment not found');
    }

    $patient_id = $data['patient_id'] ?? ($existing['patient_id'] ?? null);
    $doctor_id = $data['doctor_id'] ?? ($existing['doctor_id'] ?? null);
    if ($doctor_id === '' || $doctor_id === 'null') $doctor_id = null;
    $title = $data['title'] ?? ($existing['title'] ?? '');
    $date = $data['date'] ?? ($existing['date'] ?? null);
    $start_time = $data['start_time'] ?? ($existing['start_time'] ?? null);
    $end_time = $data['end_time'] ?? ($existing['end_time'] ?? null);
    $status = $data['status'] ?? ($existing['status'] ?? 'pending');
    $type = $data['type'] ?? ($existing['type'] ?? 'appointment');
    $notes = $data['notes'] ?? ($existing['notes'] ?? '');

    if (!$patient_id) jsonError('Patient is required');
    if (!$date || !$start_time || !$end_time) jsonError('Date and time are required');

    // Get patient name for logs
    $stmt = $db->prepare("SELECT name FROM patients WHERE id=?");
    $stmt->execute([$patient_id]);
    $pname = $stmt->fetchColumn() ?: "patient #{$patient_id}";

    // Check for time conflicts BEFORE saving
    $exclude = $id ?: 0;
    if ($doctor_id) {
        $stmt = $db->prepare("
            SELECT a.*, p.name as patient_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE a.doctor_id = ?
              AND a.date = ?
              AND a.id != ?
              AND a.start_time < ?
              AND a.end_time > ?
            ORDER BY a.start_time
        ");
        $stmt->execute([$doctor_id, $date, $exclude, $end_time, $start_time]);
        $conflicts = $stmt->fetchAll();
        if ($conflicts) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Time conflict with existing appointment', 'conflicts' => $conflicts]);
            exit;
        }
    }

    if ($id) {
        if (!hasPermission('appointments.edit')) jsonError('Forbidden', 403);
        $oldStatus = $existing['status'] ?? null;
        $stmt = $db->prepare("
            UPDATE appointments SET patient_id=?, doctor_id=?, title=?, date=?, start_time=?, end_time=?, status=?, type=?, notes=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ");
        $stmt->execute([$patient_id, $doctor_id, $title, $date, $start_time, $end_time, $status, $type, $notes, $id]);
        addLog('update', 'appointment', $id, "{$pname} — Appointment #{$id} updated");
        if ($oldStatus !== $status) {
            addLog('status_change', 'appointment', $id, "{$pname} — {$oldStatus} → {$status}");
        }
    } else {
        if (!hasPermission('appointments.create')) jsonError('Forbidden', 403);
        $stmt = $db->prepare("
            INSERT INTO appointments (patient_id, doctor_id, title, date, start_time, end_time, status, type, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $doctor_id, $title, $date, $start_time, $end_time, $status, $type, $notes, $_SESSION['user_id']]);
        $newId = $db->lastInsertId();
        addLog('create', 'appointment', $newId, "{$pname} — Appointment #{$newId} created");
    }
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    requireApiCsrf();
    if (!hasPermission('appointments.delete')) jsonError('Forbidden', 403);

    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID required');

    $stmt = $db->prepare("SELECT a.*, p.name as pname FROM appointments a LEFT JOIN patients p ON a.patient_id=p.id WHERE a.id=?");
    $stmt->execute([$id]);
    $delApp = $stmt->fetch();
    $delName = $delApp ? ($delApp['pname'] ?: "patient #{$delApp['patient_id']}") : "appointment #{$id}";
    $stmt = $db->prepare("DELETE FROM appointments WHERE id=?");
    $stmt->execute([$id]);
    addLog('delete', 'appointment', $id, "{$delName} — Appointment #{$id} deleted");
    jsonResponse(['success' => true]);
}
