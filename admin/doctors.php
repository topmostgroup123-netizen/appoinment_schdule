<?php
require_once __DIR__ . '/../config.php';
requirePermission('doctors.view');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasPermission('doctors.create') || hasPermission('doctors.edit'))) {
    $name = $_POST['name'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $color = $_POST['color'] ?? '#4A90D9';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($name) {
        requireCsrfToken();
        if ($id) {
            $stmt = $db->prepare("UPDATE doctors SET name=?, specialization=?, color=?, phone=?, email=? WHERE id=?");
            $stmt->execute([$name, $specialization, $color, $phone, $email, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO doctors (name, specialization, color, phone, email) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $specialization, $color, $phone, $email]);
        }
    }
    header('Location: doctors.php');
    exit;
}

if (isset($_GET['delete']) && hasPermission('doctors.delete')) {
    requireCsrfToken();
    $stmt = $db->prepare("UPDATE doctors SET is_active=0 WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header('Location: doctors.php');
    exit;
}

$doctors = $db->query("SELECT * FROM doctors WHERE is_active=1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors - Clinic Scheduler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <h1>Doctors</h1>
                    <?php if (hasPermission('doctors.create')): ?>
                    <button class="btn btn-primary" onclick="showModal()">+ Add Doctor</button>
                    <?php endif; ?>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Color</th>
                            <?php if (hasPermission('doctors.edit') || hasPermission('doctors.delete')): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td style="color:var(--text-secondary)"><?= $d['id'] ?></td>
                            <td><?= htmlspecialchars($d['name']) ?></td>
                            <td><?= htmlspecialchars($d['specialization']) ?></td>
                            <td><?= htmlspecialchars($d['phone']) ?></td>
                            <td><?= htmlspecialchars($d['email']) ?></td>
                            <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($d['color']) ?>;vertical-align:middle"></span></td>
                            <?php if (hasPermission('doctors.edit') || hasPermission('doctors.delete')): ?>
                            <td class="actions">
                                <?php if (hasPermission('doctors.edit')): ?>
                                <button class="btn btn-sm" onclick="showModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['name'])) ?>', '<?= htmlspecialchars(addslashes($d['specialization'])) ?>', '<?= htmlspecialchars(addslashes($d['color'])) ?>', '<?= htmlspecialchars(addslashes($d['phone'])) ?>', '<?= htmlspecialchars(addslashes($d['email'])) ?>')">Edit</button>
                                <?php endif; ?>
                                <?php if (hasPermission('doctors.delete')): ?>
                                <a href="?delete=<?= $d['id'] ?>&_csrf=<?= getCsrfToken() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this doctor?')">Delete</a>
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

    <?php if (hasPermission('doctors.create') || hasPermission('doctors.edit')): ?>
    <div class="modal-overlay" id="doctor-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Add Doctor</h2>
                <button class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <?php csrfInput(); ?>
                    <input type="hidden" name="id" id="doctor-id">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="doctor-name" required>
                    </div>
                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" id="doctor-spec">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" id="doctor-phone">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="doctor-email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Calendar Color</label>
                        <input type="color" name="color" id="doctor-color" value="#4A90D9">
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
    function showModal(id, name, spec, color, phone, email) {
        document.getElementById('modal-title').textContent = id ? 'Edit Doctor' : 'Add Doctor';
        document.getElementById('doctor-id').value = id || '';
        document.getElementById('doctor-name').value = name || '';
        document.getElementById('doctor-spec').value = spec || '';
        document.getElementById('doctor-color').value = color || '#4A90D9';
        document.getElementById('doctor-phone').value = phone || '';
        document.getElementById('doctor-email').value = email || '';
        document.getElementById('doctor-modal').classList.add('open');
    }
    function hideModal() {
        document.getElementById('doctor-modal').classList.remove('open');
    }
    </script>
    <?php endif; ?>
</body>
</html>
