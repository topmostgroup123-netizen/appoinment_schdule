<?php
require_once __DIR__ . '/config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Clinic Scheduler</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="btn nav-btn" onclick="navigate(-1)" title="Previous">&#8249;</button>
                    <h1 id="view-title"></h1>
                    <button class="btn nav-btn" onclick="navigate(1)" title="Next">&#8250;</button>
                    <button class="btn today-btn" onclick="goToday()">Today</button>
                    <div class="view-controls">
                        <button class="btn" data-view="day" onclick="switchView('day')">Day</button>
                        <button class="btn" data-view="week" onclick="switchView('week')">Week</button>
                        <button class="btn active" data-view="month" onclick="switchView('month')">Month</button>
                    </div>
                </div>
                <div class="top-bar-right" style="display:flex;gap:8px;align-items:center">
                    <div class="view-controls" id="filter-controls">
                        <button class="btn active" data-filter="all" onclick="setFilter('all')">All</button>
                        <button class="btn" data-filter="appointment" onclick="setFilter('appointment')">Appt</button>
                        <button class="btn" data-filter="treatment" onclick="setFilter('treatment')">Tx</button>
                    </div>
                    <?php if (hasPermission('appointments.create')): ?>
                    <button class="btn btn-primary" onclick="openCreateModal()">+ New</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="calendar-container" id="calendar-container"></div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="detail-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="detail-title">Appointment Details</h2>
                <button class="modal-close" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="detail-body"></div>
        </div>
    </div>

    <!-- Appointment Edit Modal -->
    <div class="modal-overlay" id="appointment-modal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title">New Appointment</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="appointment-id">
                <div class="form-group">
                    <label>Patient</label>
                    <select id="appointment-patient" required></select>
                </div>
                <div class="form-group">
                    <label>Title (optional)</label>
                    <input type="text" id="appointment-title" placeholder="e.g. Hair consultation">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="appointment-date" required>
                    </div>
                    <div class="form-group">
                        <label>Doctor</label>
                        <select id="appointment-doctor"></select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" id="appointment-start" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" id="appointment-end" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select id="appointment-status">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="done">Done</option>
                            <option value="postponed">Postponed</option>
                            <option value="rescheduled">Rescheduled</option>
                            <option value="hold">Hold</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="appointment-type">
                            <option value="appointment">Appointment</option>
                            <option value="treatment">Treatment</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="appointment-notes" placeholder="Additional notes..."></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn" onclick="closeModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveAppointment()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Convert to Treatment Modal -->
    <div class="modal-overlay" id="convert-modal">
        <div class="modal" style="max-width:620px">
            <div class="modal-header">
                <h2>Convert to Treatment</h2>
                <button class="modal-close" onclick="closeConvertModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="convert-appointment-id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Number of Sittings</label>
                        <select id="convert-sittings" onchange="generateSittingSlots()">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Interval (days)</label>
                        <select id="convert-interval">
                            <option value="7">Weekly (7 days)</option>
                            <option value="14">Biweekly (14 days)</option>
                            <option value="1">Daily</option>
                            <option value="3">Every 3 days</option>
                            <option value="0">Custom dates</option>
                        </select>
                    </div>
                </div>
                <div id="convert-slots" style="margin-top:8px"></div>
                <div class="form-actions" style="margin-top:16px">
                    <button class="btn" onclick="closeConvertModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="saveTreatments()">Create Treatment Sessions</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conflict Modal -->
    <div class="modal-overlay" id="conflict-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>Time Conflict</h2>
                <button class="modal-close" onclick="closeConflictModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:12px;color:var(--danger)">Doctor already has an appointment at this time:</p>
                <div id="conflict-list"></div>
                <div class="form-actions" style="margin-top:16px">
                    <button class="btn btn-primary" onclick="keepEditing()">Change Time</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>
    <script>
    window.userPermissions = <?= json_encode(isAdmin() ? '__ALL__' : ($_SESSION['permissions'] ?? [])) ?>;
    window.canCreate = <?= hasPermission('appointments.create') ? 'true' : 'false' ?>;
    window.canEdit = <?= hasPermission('appointments.edit') ? 'true' : 'false' ?>;
    window.canDelete = <?= hasPermission('appointments.delete') ? 'true' : 'false' ?>;
    window.canConvert = <?= hasPermission('appointments.convert') ? 'true' : 'false' ?>;
    window.canEditTx = <?= hasPermission('treatments.edit') ? 'true' : 'false' ?>;
    </script>
    <script src="assets/js/calendar.js"></script>
</body>
</html>
