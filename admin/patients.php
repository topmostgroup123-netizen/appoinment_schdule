<?php
require_once __DIR__ . '/../config.php';
requirePermission('patients.view');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasPermission('patients.create') || hasPermission('patients.edit'))) {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($name) {
        if ($id) {
            $stmt = $db->prepare("UPDATE patients SET name=?, phone=?, email=?, notes=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $notes, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO patients (name, phone, email, notes) VALUES (?,?,?,?)");
            $stmt->execute([$name, $phone, $email, $notes]);
        }
    }
    header('Location: patients.php');
    exit;
}

if (isset($_GET['delete']) && hasPermission('patients.delete')) {
    $stmt = $db->prepare("DELETE FROM patients WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header('Location: patients.php');
    exit;
}

$patients = $db->query("SELECT * FROM patients ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Clinic Scheduler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <h1>Patients</h1>
                    <?php if (hasPermission('patients.create')): ?>
                    <button class="btn btn-primary" onclick="showModal()">+ Add Patient</button>
                    <?php endif; ?>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Notes</th>
                            <?php if (hasPermission('patients.edit') || hasPermission('patients.delete')): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                        <tr>
                            <td style="color:var(--text-secondary)"><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['phone']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars(substr($p['notes'], 0, 50)) ?></td>
                            <?php if (hasPermission('patients.edit') || hasPermission('patients.delete')): ?>
                            <td class="actions">
                                <?php if (hasPermission('patients.edit')): ?>
                                <button class="btn btn-sm" onclick="showModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= htmlspecialchars(addslashes($p['phone'])) ?>', '<?= htmlspecialchars(addslashes($p['email'])) ?>', '<?= htmlspecialchars(addslashes($p['notes'])) ?>')">Edit</button>
                                <?php endif; ?>
                                <?php if (hasPermission('patients.delete')): ?>
                                <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this patient?')">Delete</a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (hasPermission('patients.create') || hasPermission('patients.edit')): ?>
    <div class="modal-overlay" id="patient-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Add Patient</h2>
                <button class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="id" id="patient-id">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="patient-name" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" id="patient-phone">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="patient-email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="patient-notes"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn" onclick="hideModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    function showModal(id, name, phone, email, notes) {
        document.getElementById('modal-title').textContent = id ? 'Edit Patient' : 'Add Patient';
        document.getElementById('patient-id').value = id || '';
        document.getElementById('patient-name').value = name || '';
        document.getElementById('patient-phone').value = phone || '';
        document.getElementById('patient-email').value = email || '';
        document.getElementById('patient-notes').value = notes || '';
        document.getElementById('patient-modal').classList.add('open');
    }
    function hideModal() {
        document.getElementById('patient-modal').classList.remove('open');
    }
    </script>
    <?php endif; ?>
</body>
</html>
