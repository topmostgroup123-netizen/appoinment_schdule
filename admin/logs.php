<?php
require_once __DIR__ . '/../config.php';
requirePermission('logs.view');

$db = getDB();
$logs = $db->query("
    SELECT l.*, u.full_name as user_full
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 200
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Clinic Scheduler</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../sidebar.php'; ?>
        <div class="main-content">
            <div class="page-content">
                <div class="page-header">
                    <h1>Activity Log</h1>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Type</th>
                            <th>Entity</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="color:var(--text-secondary);font-size:12px"><?= $l['id'] ?></td>
                            <td style="white-space:nowrap;font-size:12px"><?= $l['created_at'] ?></td>
                            <td><?= htmlspecialchars($l['user_full'] ?: $l['username']) ?></td>
                            <td><span class="status-badge" style="background:#E8F0FE;color:#1A73E8;font-size:11px"><?= htmlspecialchars($l['action']) ?></span></td>
                            <td><?= htmlspecialchars($l['entity_type']) ?></td>
                            <td><?= $l['entity_id'] ?: '—' ?></td>
                            <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($l['detail'] ?: '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-secondary)">No activity logged yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
