<div class="sidebar">
    <div class="sidebar-header">
        <h2>Clinic</h2>
        <span>Appointment Scheduler</span>
    </div>
    <?php $script = $_SERVER['SCRIPT_NAME']; ?>
    <nav class="sidebar-nav">
        <a href="/" class="<?= $script === '/index.php' || $script === '/dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span>
            <span>Calendar</span>
        </a>
        <?php if (hasPermission('doctors.view')): ?>
        <a href="/admin/doctors.php" class="<?= strpos($script, 'doctors.php') !== false ? 'active' : '' ?>">
            <span class="nav-icon">👨‍⚕️</span>
            <span>Doctors</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('patients.view')): ?>
        <a href="/admin/patients.php" class="<?= strpos($script, 'patients.php') !== false ? 'active' : '' ?>">
            <span class="nav-icon">👤</span>
            <span>Patients</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('users.view')): ?>
        <a href="/admin/users.php" class="<?= strpos($script, 'users.php') !== false ? 'active' : '' ?>">
            <span class="nav-icon">🔐</span>
            <span>Users</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('reports.view')): ?>
        <a href="/admin/reports.php">
            <span class="nav-icon">📊</span>
            <span>Reports</span>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('logs.view')): ?>
        <a href="/admin/logs.php" class="<?= strpos($script, 'logs.php') !== false ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span>Activity Log</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']) ?></div>
            <div class="user-role"><?= $_SESSION['role'] ?></div>
        </div>
        <a href="/logout.php" class="btn btn-sm">Logout</a>
    </div>
</div>
