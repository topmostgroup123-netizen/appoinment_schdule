<?php
require_once __DIR__ . '/../config.php';
requirePermission('logs.view');

$db = getDB();

$stmt = $db->query("
    SELECT l.*, u.full_name as user_full
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 200
");
jsonResponse($stmt->fetchAll());
