<?php
require_once __DIR__ . '/../config.php';
requirePermission('users.view');

$db = getDB();

$allPermissions = [
    'users'    => ['users.view', 'users.create', 'users.edit', 'users.delete'],
    'doctors'  => ['doctors.view', 'doctors.create', 'doctors.edit', 'doctors.delete'],
    'patients' => ['patients.view', 'patients.create', 'patients.edit', 'patients.delete'],
    'appointments' => ['appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete', 'appointments.convert'],
    'treatments'   => ['treatments.view', 'treatments.create', 'treatments.edit', 'treatments.delete'],
    'reports'  => ['reports.view'],
    'logs'     => ['logs.view']
];
$permLabels = [
    'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'convert' => 'Convert'
];
$permGroupLabels = [
    'users' => 'User Management', 'doctors' => 'Doctor Management', 'patients' => 'Patient Management',
    'appointments' => 'Appointment Management', 'treatments' => 'Treatment Management',
    'reports' => 'Reports', 'logs' => 'Activity Logs'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $id = $_POST['id'] ?? '';
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';

    if ($username) {
        if ($id) {
            if ($password) {
                $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, role=?, permissions=? WHERE id=?");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role, $permissions, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=?, permissions=? WHERE id=?");
                $stmt->execute([$username, $full_name, $role, $permissions, $id]);
            }
        } else {
            if ($password) {
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, permissions) VALUES (?,?,?,?,?)");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role, $permissions]);
            }
        }
    }
    header('Location: users.php');
    exit;
}

if (isset($_GET['delete'])) {
    if ($_GET['delete'] == $_SESSION['user_id']) {
        $error = 'Cannot delete yourself';
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$_GET['delete']]);
    }
    header('Location: users.php');
    exit;
}

$users = $db->query("SELECT id, username, full_name, role, permissions, created_at FROM users ORDER BY username")->fetchAll();
$error = $error ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Clinic Scheduler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <h1>Users</h1>
                    <button class="btn btn-primary" onclick="showModal()">+ Add User</button>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="color:var(--text-secondary)"><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><span class="status-badge <?= $u['role'] === 'admin' ? 'status-confirmed' : 'status-pending' ?>"><?= $u['role'] ?></span></td>
                            <td style="font-size:11px;color:var(--text-secondary);max-width:200px"><?php
                                $p = json_decode($u['permissions'] ?? '[]', true);
                                if ($u['role'] === 'admin') echo '<em>All</em>';
                                elseif (empty($p)) echo '<em>None</em>';
                                else echo htmlspecialchars(implode(', ', $p));
                            ?></td>
                            <td><?= $u['created_at'] ?></td>
                            <td class="actions">
                                <button class="btn btn-sm" onclick="showModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '', '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars(addslashes($u['permissions'] ?? '[]')) ?>')">Edit</button>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="user-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">Add User</h2>
                <button class="modal-close" onclick="hideModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="id" id="user-id">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="user-username" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span id="pwd-hint" style="font-weight:normal;color:var(--text-secondary);font-size:12px">(leave blank to keep)</span></label>
                        <input type="password" name="password" id="user-password">
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="user-fullname">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="user-role" onchange="togglePermSection()">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div id="perm-section" style="margin-top:16px">
                        <div style="font-size:13px;font-weight:500;margin-bottom:8px">Permissions</div>
                        <?php foreach ($allPermissions as $group => $perms): ?>
                        <div style="margin-bottom:10px">
                            <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:4px"><?= $permGroupLabels[$group] ?></div>
                            <div style="display:flex;flex-wrap:wrap;gap:4px">
                                <?php foreach ($perms as $p): ?>
                                <label style="display:flex;align-items:center;gap:4px;font-size:12px;padding:3px 8px;border:1px solid var(--border);border-radius:4px;cursor:pointer;background:var(--bg)">
                                    <input type="checkbox" name="permissions[]" value="<?= $p ?>" class="perm-checkbox">
                                    <?= $permLabels[explode('.', $p)[1]] ?? $p ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
    function togglePermSection() {
        const isAdmin = document.getElementById('user-role').value === 'admin';
        document.getElementById('perm-section').style.display = isAdmin ? 'none' : 'block';
    }
    function showModal(id, username, password, fullname, role, permsJson) {
        document.getElementById('modal-title').textContent = id ? 'Edit User' : 'Add User';
        document.getElementById('user-id').value = id || '';
        document.getElementById('user-username').value = username || '';
        document.getElementById('user-password').value = '';
        document.getElementById('user-fullname').value = fullname || '';
        document.getElementById('user-role').value = role || 'user';
        document.getElementById('pwd-hint').style.display = id ? '' : 'none';
        document.getElementById('user-password').required = !id;
        // Set permissions
        const perms = permsJson ? JSON.parse(permsJson) : [];
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            cb.checked = perms.includes(cb.value);
        });
        togglePermSection();
        document.getElementById('user-modal').classList.add('open');
    }
    function hideModal() {
        document.getElementById('user-modal').classList.remove('open');
    }
    // Init
    togglePermSection();
    </script>
</body>
</html>
