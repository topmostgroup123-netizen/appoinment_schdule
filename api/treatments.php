<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("
        SELECT t.*, p.name as patient_name, d.name as doctor_name, d.color as doctor_color
        FROM treatments t
        LEFT JOIN patients p ON t.patient_id = p.id
        LEFT JOIN doctors d ON t.doctor_id = d.id
        ORDER BY t.date, t.start_time
    ");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) jsonError('Invalid request body');

    // Bulk create treatment sessions (convert from appointment)
    if (isset($data['bulk'])) {
        if (!hasPermission('appointments.convert')) jsonError('Forbidden', 403);
        $appointment_id = $data['appointment_id'] ?? null;

        // Fetch appointment to get patient_id
        $app = null;
        if ($appointment_id) {
            $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $app = $stmt->fetch();
            if (!$app) jsonError('Appointment not found');
        }

        $created = 0;
        foreach ($data['bulk'] as $item) {
            $date = $item['date'] ?? null;
            $start_time = $item['start_time'] ?? null;
            $end_time = $item['end_time'] ?? null;
            $doctor_id = $item['doctor_id'] ?? ($app['doctor_id'] ?? null);
            if ($doctor_id === '' || $doctor_id === 'null') $doctor_id = null;
            $aid = $item['appointment_id'] ?? $appointment_id;

            if (!$date || !$start_time || !$end_time) continue;

            $stmt = $db->prepare("
                INSERT INTO treatments (appointment_id, patient_id, doctor_id, date, start_time, end_time, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$aid, $app['patient_id'], $doctor_id, $date, $start_time, $end_time, $app['notes'] ?? '', $_SESSION['user_id']]);
            $created++;
        }

        // Mark original appointment as done
        if ($appointment_id) {
            $stmt = $db->prepare("SELECT p.name as pname FROM appointments a LEFT JOIN patients p ON a.patient_id=p.id WHERE a.id=?");
            $stmt->execute([$appointment_id]);
            $bpname = $stmt->fetchColumn() ?: "appointment #{$appointment_id}";
            $stmt = $db->prepare("UPDATE appointments SET status='done', updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$appointment_id]);
            addLog('convert', 'appointment', $appointment_id, "{$bpname} — Converted to {$created} treatment sessions");
        } else {
            $bpname = 'Unknown';
        }

        addLog('create_bulk', 'treatment', $appointment_id, "{$bpname} — Created {$created} treatment sessions");

        jsonResponse(['success' => true, 'created' => $created]);
    }

    $id = $_GET['id'] ?? null;

    if ($id && !hasPermission('treatments.edit')) jsonError('Forbidden', 403);

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM treatments WHERE id=?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) jsonError('Treatment not found');
    }

    $patient_id = $data['patient_id'] ?? ($existing['patient_id'] ?? null);
    $doctor_id = $data['doctor_id'] ?? ($existing['doctor_id'] ?? null);
    if ($doctor_id === '' || $doctor_id === 'null') $doctor_id = null;
    $date = $data['date'] ?? ($existing['date'] ?? null);
    $start_time = $data['start_time'] ?? ($existing['start_time'] ?? null);
    $end_time = $data['end_time'] ?? ($existing['end_time'] ?? null);
    $status = $data['status'] ?? ($existing['status'] ?? 'pending');
    $notes = $data['notes'] ?? ($existing['notes'] ?? '');

    if (!$patient_id) jsonError('Patient is required');

    // Get patient name for logs
    if ($id) {
        $stmt = $db->prepare("SELECT p.name as pname FROM treatments t LEFT JOIN patients p ON t.patient_id=p.id WHERE t.id=?");
        $stmt->execute([$id]);
        $tname = $stmt->fetchColumn() ?: "treatment #{$id}";
    } else {
        $stmt = $db->prepare("SELECT name FROM patients WHERE id=?");
        $stmt->execute([$patient_id]);
        $tname = $stmt->fetchColumn() ?: "patient #{$patient_id}";
    }

    if ($id) {
        $oldStatus = $existing['status'] ?? null;
        $stmt = $db->prepare("UPDATE treatments SET patient_id=?, doctor_id=?, date=?, start_time=?, end_time=?, status=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute([$patient_id, $doctor_id, $date, $start_time, $end_time, $status, $notes, $id]);
        if ($oldStatus !== $status) {
            addLog('status_change', 'treatment', $id, "{$tname} — {$oldStatus} → {$status}");
        }
        addLog('update', 'treatment', $id, "{$tname} — Treatment #{$id} updated");
    } else {
        if (!hasPermission('treatments.create')) jsonError('Forbidden', 403);
        $stmt = $db->prepare("INSERT INTO treatments (patient_id, doctor_id, date, start_time, end_time, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$patient_id, $doctor_id, $date, $start_time, $end_time, $status, $notes, $_SESSION['user_id']]);
    }

    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    if (!hasPermission('treatments.delete')) jsonError('Forbidden', 403);
    $id = $_GET['id'] ?? null;
    if (!$id) jsonError('ID required');
    $stmt = $db->prepare("DELETE FROM treatments WHERE id=?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}
