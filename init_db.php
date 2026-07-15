<?php
require_once __DIR__ . '/config.php';

$db = getDB();

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin','user')),
    permissions TEXT DEFAULT '[]',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

try { $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT '[]'"); } catch (Exception $e) {}

$db->exec("
CREATE TABLE IF NOT EXISTS doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    specialization TEXT NOT NULL DEFAULT '',
    color TEXT NOT NULL DEFAULT '#4A90D9',
    phone TEXT DEFAULT '',
    email TEXT DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("
CREATE TABLE IF NOT EXISTS patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT DEFAULT '',
    email TEXT DEFAULT '',
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("
CREATE TABLE IF NOT EXISTS appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER,
    title TEXT NOT NULL DEFAULT '',
    date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','done','postponed','rescheduled','hold','cancelled')),
    type TEXT NOT NULL DEFAULT 'appointment' CHECK(type IN ('appointment','treatment')),
    notes TEXT DEFAULT '',
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS treatments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER,
    date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','in_progress','done','postponed','hold')),
    notes TEXT DEFAULT '',
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    username TEXT,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    detail TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

$stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
$row = $stmt->fetch();
if ($row['cnt'] == 0) {
    $defaultAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'admin')")
       ->execute(['admin', $defaultAdmin, 'Administrator']);

    $db->prepare("INSERT INTO doctors (name, specialization, color) VALUES (?, ?, ?)")
       ->execute(['Dr. Smith', 'General', '#4A90D9']);
    $db->prepare("INSERT INTO doctors (name, specialization, color) VALUES (?, ?, ?)")
       ->execute(['Dr. Johnson', 'Hair Transplant', '#50C878']);

    $db->prepare("INSERT INTO patients (name, phone) VALUES (?, ?)")
       ->execute(['John Doe', '555-0101']);
    $db->prepare("INSERT INTO patients (name, phone) VALUES (?, ?)")
       ->execute(['Jane Roe', '555-0102']);
}

echo "Database initialized. Default admin: admin / admin123\n";
