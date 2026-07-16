<?php
require_once __DIR__ . '/../config.php';
requirePermission('reports.view');

$db = getDB();

// Stats
$totalPatients = $db->query("SELECT COUNT(*) as c FROM patients")->fetch()['c'];
$totalDoctors = $db->query("SELECT COUNT(*) as c FROM doctors WHERE is_active=1")->fetch()['c'];
$totalAppointments = $db->query("SELECT COUNT(*) as c FROM appointments")->fetch()['c'];
$todayAppointments = $db->query("SELECT COUNT(*) as c FROM appointments WHERE date = date('now')")->fetch()['c'];
$pendingAppointments = $db->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'")->fetch()['c'];
$doneTreatments = $db->query("SELECT COUNT(*) as c FROM treatments WHERE status = 'done'")->fetch()['c'];
$totalTreatments = $db->query("SELECT COUNT(*) as c FROM treatments")->fetch()['c'];
$todayTreatments = $db->query("SELECT COUNT(*) as c FROM treatments WHERE date = date('now')")->fetch()['c'];
$pendingTreatments = $db->query("SELECT COUNT(*) as c FROM treatments WHERE status = 'pending'")->fetch()['c'];
$inProgressTreatments = $db->query("SELECT COUNT(*) as c FROM treatments WHERE status = 'in_progress'")->fetch()['c'];

// Appointments by status
$byStatus = $db->query("SELECT status, COUNT(*) as c FROM appointments GROUP BY status ORDER BY c DESC")->fetchAll();

// Appointments by doctor
$byDoctor = $db->query("
    SELECT d.name, COUNT(a.id) as c
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    GROUP BY a.doctor_id
    ORDER BY c DESC
")->fetchAll();

// Recent appointments
$recent = $db->query("
    SELECT a.*, p.name as patient_name, d.name as doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Clinic Scheduler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <h1>Reports</h1>
                </div>

                <div class="report-grid">
                    <div class="report-card">
                        <h3>Total Patients</h3>
                        <div class="number"><?= $totalPatients ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Active Doctors</h3>
                        <div class="number"><?= $totalDoctors ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Total Appointments</h3>
                        <div class="number"><?= $totalAppointments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Today's Appointments</h3>
                        <div class="number"><?= $todayAppointments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Pending</h3>
                        <div class="number"><?= $pendingAppointments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Treatments Done</h3>
                        <div class="number"><?= $doneTreatments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Total Treatments</h3>
                        <div class="number"><?= $totalTreatments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Today's Treatments</h3>
                        <div class="number"><?= $todayTreatments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>Treatments Pending</h3>
                        <div class="number"><?= $pendingTreatments ?></div>
                    </div>
                    <div class="report-card">
                        <h3>In Progress</h3>
                        <div class="number"><?= $inProgressTreatments ?></div>
                    </div>
                </div>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px">Appointments by Status</h2>
                <table class="data-table" style="margin-bottom:24px">
                    <thead>
                        <tr><th>Status</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byStatus as $s): ?>
                        <tr>
                            <td><span class="status-badge <?= 'status-' . $s['status'] ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                            <td><?= $s['c'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px">Appointments by Doctor</h2>
                <table class="data-table" style="margin-bottom:24px">
                    <thead>
                        <tr><th>Doctor</th><th>Appointments</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byDoctor as $d): ?>
                        <tr><td><?= htmlspecialchars($d['name']) ?></td><td><?= $d['c'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px">Recent Appointments</h2>
                <table class="data-table" style="margin-bottom:24px">
                    <thead>
                        <tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th><th>Type</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td style="color:var(--text-secondary)"><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['patient_name']) ?></td>
                            <td><?= htmlspecialchars($r['doctor_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($r['date']) ?> <?= htmlspecialchars($r['start_time']) ?></td>
                            <td><span class="status-badge <?= 'status-' . $r['status'] ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                            <td><?= htmlspecialchars(ucfirst($r['type'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Treatment Reports -->
                <?php
                $txByStatus = $db->query("SELECT status, COUNT(*) as c FROM treatments GROUP BY status ORDER BY c DESC")->fetchAll();
                $txByDoctor = $db->query("
                    SELECT d.name, COUNT(t.id) as c
                    FROM treatments t
                    JOIN doctors d ON t.doctor_id = d.id
                    GROUP BY t.doctor_id
                    ORDER BY c DESC
                ")->fetchAll();
                $recentTx = $db->query("
                    SELECT t.*, p.name as patient_name, d.name as doctor_name
                    FROM treatments t
                    LEFT JOIN patients p ON t.patient_id = p.id
                    LEFT JOIN doctors d ON t.doctor_id = d.id
                    ORDER BY t.created_at DESC
                    LIMIT 10
                ")->fetchAll();
                ?>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px;margin-top:32px">Treatments by Status</h2>
                <table class="data-table" style="margin-bottom:24px">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($txByStatus as $s): ?>
                        <tr>
                            <td><span class="status-badge <?= 'status-' . $s['status'] ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                            <td><?= $s['c'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px">Treatments by Doctor</h2>
                <table class="data-table" style="margin-bottom:24px">
                    <thead><tr><th>Doctor</th><th>Treatments</th></tr></thead>
                    <tbody>
                        <?php foreach ($txByDoctor as $d): ?>
                        <tr><td><?= htmlspecialchars($d['name']) ?></td><td><?= $d['c'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="font-size:18px;font-weight:500;margin-bottom:12px">Recent Treatments</h2>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentTx as $r): ?>
                        <tr>
                            <td style="color:var(--text-secondary)"><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['patient_name']) ?></td>
                            <td><?= htmlspecialchars($r['doctor_name'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($r['date']) ?> <?= htmlspecialchars($r['start_time']) ?></td>
                            <td><span class="status-badge <?= 'status-' . $r['status'] ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
