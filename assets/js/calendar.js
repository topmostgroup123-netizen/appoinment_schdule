let currentView = 'month';
let currentDate = new Date();
let appointments = [];
let doctors = [];
let patients = [];

function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function formatTime(d) {
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function parseTimeStr(t) {
    const [h, m] = t.split(':').map(Number);
    return { h, m };
}

function getWeekStart(d) {
    const copy = new Date(d);
    const day = copy.getDay();
    const diff = copy.getDate() - day + (day === 0 ? -6 : 1);
    copy.setDate(diff);
    copy.setHours(0,0,0,0);
    return copy;
}

function getMonthStart(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
}

function addDays(d, n) {
    const r = new Date(d);
    r.setDate(r.getDate() + n);
    return r;
}

function addMonths(d, n) {
    const r = new Date(d);
    r.setMonth(r.getMonth() + n);
    return r;
}

function addWeeks(d, n) {
    return addDays(d, n * 7);
}

async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('Network error');
    return res.json();
}

async function postJSON(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    if (!res.ok) {
        const err = await res.json();
        const ex = new Error(err.error || 'Request failed');
        if (err.conflicts) ex.conflicts = err.conflicts;
        throw ex;
    }
    return res.json();
}

async function delJSON(url) {
    const res = await fetch(url, { method: 'DELETE' });
    if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || 'Delete failed');
    }
    return res.json();
}

let treatments = [];
let currentFilter = 'all';

function setFilter(f) {
    currentFilter = f;
    document.querySelectorAll('#filter-controls .btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`#filter-controls [data-filter="${f}"]`).classList.add('active');
    render();
}

async function loadData() {
    try {
        const [apps, docs, pats, treats] = await Promise.all([
            fetchJSON('api/appointments.php'),
            fetchJSON('api/doctors.php'),
            fetchJSON('api/patients.php'),
            fetchJSON('api/treatments.php')
        ]);
        appointments = apps;
        doctors = docs;
        patients = pats;
        treatments = treats;
    } catch (e) {
        console.error('Load failed:', e);
    }
}

function getEventsForDate(dateStr) {
    const apps = currentFilter !== 'treatment' ? appointments.filter(a => a.date === dateStr).map(a => ({ ...a, _type: 'appointment' })) : [];
    const treats = currentFilter !== 'appointment' ? treatments.filter(t => t.date === dateStr).map(t => ({ ...t, title: t.title || t.patient_name + ' (Tx)', _type: 'treatment' })) : [];
    return [...apps, ...treats].sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));
}

function eventStyle(e) {
    const color = e._type === 'treatment' ? getTxColor(e.status) : getStatusColor(e.status);
    return `background:${color}`;
}

function getTxColor(status) {
    const colors = {
        pending: '#7B1FA2',
        in_progress: '#4A148C',
        done: '#2E7D32',
        postponed: '#E91E63',
        hold: '#78909C'
    };
    return colors[status] || '#7B1FA2';
}

function getStatusColor(status) {
    const colors = {
        pending: '#E37400',
        confirmed: '#1A73E8',
        done: '#1E8E3E',
        postponed: '#9334E6',
        rescheduled: '#E65100',
        hold: '#616161',
        cancelled: '#D93025',
        in_progress: '#0D47A1'
    };
    return colors[status] || '#4A90D9';
}

function getStatusClass(status) {
    return 'status-' + status;
}

function getStatusLabel(status) {
    const labels = {
        pending: 'Pending',
        confirmed: 'Confirmed',
        done: 'Done',
        postponed: 'Postponed',
        rescheduled: 'Rescheduled',
        hold: 'Hold',
        cancelled: 'Cancelled',
        in_progress: 'In Progress'
    };
    return labels[status] || status;
}

function render() {
    if (currentView === 'month') renderMonth();
    else if (currentView === 'week') renderWeek();
    else renderDay();
}

function navigate(direction) {
    if (currentView === 'month') currentDate = addMonths(currentDate, direction);
    else if (currentView === 'week') currentDate = addWeeks(currentDate, direction);
    else currentDate = addDays(currentDate, direction);
    render();
    updateTitle();
}

function goToday() {
    currentDate = new Date();
    render();
    updateTitle();
}

function switchView(view) {
    currentView = view;
    document.querySelectorAll('.view-controls .btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-view="${view}"]`).classList.add('active');
    render();
    updateTitle();
}

function updateTitle() {
    const title = document.getElementById('view-title');
    const opts = { month: 'long', year: 'numeric' };
    if (currentView === 'month') {
        title.textContent = currentDate.toLocaleDateString('en-US', opts);
    } else if (currentView === 'week') {
        const start = getWeekStart(currentDate);
        const end = addDays(start, 6);
        const s = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const e = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        title.textContent = `${s} – ${e}`;
    } else {
        title.textContent = currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }
}

function renderMonth() {
    const container = document.getElementById('calendar-container');
    const start = getMonthStart(currentDate);
    const startDay = start.getDay();
    const startDate = addDays(start, -startDay);
    const endDate = addDays(startDate, 41);

    let html = '<div class="calendar-header" style="grid-template-columns:repeat(7,1fr)">';
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const todayStr = formatDate(new Date());
    days.forEach((d, i) => {
        const isToday = formatDate(addDays(startDate, i)) === todayStr;
        html += `<div class="calendar-header-cell${isToday ? ' today' : ''}">${d}</div>`;
    });
    html += '</div><div class="month-grid">';

    let d = new Date(startDate);
    for (let i = 0; i < 42; i++) {
        const dateStr = formatDate(d);
        const isToday = dateStr === todayStr;
        const isOther = d.getMonth() !== currentDate.getMonth();
        const dayEvents = getEventsForDate(dateStr);

        html += `<div class="month-day${isOther ? ' other-month' : ''}${isToday ? ' today' : ''}" data-date="${dateStr}"${window.canCreate ? ` onclick="openCreateModal('${dateStr}')"` : ''}>`;
        html += `<div class="day-number">${d.getDate()}</div>`;
        dayEvents.forEach(ev => {
            html += `<div class="month-event" style="${eventStyle(ev)}" onclick="event.stopPropagation();openEventDetail(${ev.id},'${ev._type}')">`;
            html += `<span class="event-time">${ev.start_time}</span> ${ev.title || ev.patient_name}`;
            if (ev._type === 'treatment') html += ' <span style="font-size:9px;opacity:0.8">[Tx]</span>';
            html += '</div>';
        });
        html += '</div>';
        d = addDays(d, 1);
    }

    html += '</div>';
    container.innerHTML = html;
}

function renderWeek() {
    const container = document.getElementById('calendar-container');
    const start = getWeekStart(currentDate);
    const todayStr = formatDate(new Date());
    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const hours = 12; // 8am - 7pm
    const startHour = 8;
    const hourPx = 48;

    // Header
    let html = '<div class="wv-container"><div class="wv-header"><div class="wv-gutter"></div>';
    for (let i = 0; i < 7; i++) {
        const d = addDays(start, i);
        const dateStr = formatDate(d);
        const isToday = dateStr === todayStr;
        html += `<div class="wv-day-label${isToday ? ' today' : ''}">${dayNames[i]} ${d.getDate()}</div>`;
    }
    html += '</div><div class="wv-body"><div class="wv-gutter">';
    for (let h = startHour; h < startHour + hours; h++) {
        html += `<div class="wv-hour">${String(h).padStart(2,'0')}:00</div>`;
    }
    html += '</div><div class="wv-days">';
    for (let i = 0; i < 7; i++) {
        const d = addDays(start, i);
        const dateStr = formatDate(d);
        const isToday = dateStr === todayStr;
        html += `<div class="wv-day-col${isToday ? ' today' : ''}" data-date="${dateStr}">`;
        for (let h = startHour; h < startHour + hours; h++) {
            const timeStr = `${String(h).padStart(2,'0')}:00`;
            const clickAttr = window.canCreate ? ` onclick="openCreateModal('${dateStr}','${timeStr}')"` : '';
            html += `<div style="height:${hourPx}px;border-bottom:1px solid var(--border);${window.canCreate ? 'cursor:pointer' : ''}"${clickAttr}></div>`;
        }
        html += '</div>';
    }
    html += '</div></div></div>';
    container.innerHTML = html;

    const startOfWeek = formatDate(start);
    const endOfWeek = formatDate(addDays(start, 6));
    const dayCols = container.querySelectorAll('.wv-day-col');

    function placeEvent(ev) {
        if (ev.date < startOfWeek || ev.date > endOfWeek) return;
        const evDate = new Date(ev.date + 'T' + (ev.start_time || '09:00'));
        const evEnd = new Date(ev.date + 'T' + (ev.end_time || '10:00'));
        const startH = evDate.getHours() + evDate.getMinutes() / 60;
        const endH = evEnd.getHours() + evEnd.getMinutes() / 60;
        const top = ((startH - startHour) / hours) * 100;
        const heightPct = Math.max(((endH - startH) / hours) * 100, 2.5);
        const dayIndex = new Date(ev.date).getDay();
        const col = dayCols[dayIndex];
        if (!col) return;
        const el = document.createElement('div');
        el.className = 'wv-event';
        el.style.cssText = `top:${top}%;height:${heightPct}%;${eventStyle(ev)}`;
        el.innerHTML = `<div class="event-title">${ev.title || ev.patient_name}${ev._type === 'treatment' ? ' [Tx]' : ''}</div><div class="event-time">${ev.start_time}</div>`;
        el.onclick = (e) => { e.stopPropagation(); openEventDetail(ev.id, ev._type); };
        col.appendChild(el);
    }

    appointments.forEach(a => placeEvent({ ...a, _type: 'appointment' }));
    treatments.forEach(t => placeEvent({ ...t, title: t.title || t.patient_name + ' (Tx)', _type: 'treatment' }));
}

function renderDay() {
    const container = document.getElementById('calendar-container');
    const dateStr = formatDate(currentDate);
    const dayEvents = getEventsForDate(dateStr);
    const hours = 16; // 6am - 9pm
    const startHour = 6;
    const hourPx = 48;

    let html = '<div class="wv-container"><div class="wv-header"><div class="wv-gutter"></div>';
    const d = new Date(currentDate);
    const isToday = dateStr === formatDate(new Date());
    html += `<div class="wv-day-label${isToday ? ' today' : ''}" style="text-transform:none">${d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'})}</div>`;
    html += '</div><div class="wv-body"><div class="wv-gutter">';
    for (let h = startHour; h < startHour + hours; h++) {
        html += `<div class="wv-hour">${String(h).padStart(2,'0')}:00</div>`;
    }
    html += '</div><div class="wv-days">';
    html += `<div class="wv-day-col${isToday ? ' today' : ''}" data-date="${dateStr}" style="min-height:${hours * hourPx}px">`;
    for (let h = startHour; h < startHour + hours; h++) {
        const timeStr = `${String(h).padStart(2,'0')}:00`;
        const clickAttr = window.canCreate ? ` onclick="openCreateModal('${dateStr}','${timeStr}')"` : '';
        html += `<div style="height:${hourPx}px;border-bottom:1px solid var(--border);${window.canCreate ? 'cursor:pointer' : ''}"${clickAttr}></div>`;
    }
    html += '</div></div></div></div>';
    container.innerHTML = html;

    const dayCol = container.querySelector('.wv-day-col');
    dayEvents.forEach(ev => {
        const evDate = new Date(ev.date + 'T' + (ev.start_time || '09:00'));
        const evEnd = new Date(ev.date + 'T' + (ev.end_time || '10:00'));
        const startH = evDate.getHours() + evDate.getMinutes() / 60;
        const endH = evEnd.getHours() + evEnd.getMinutes() / 60;

        const top = ((startH - startHour) / hours) * 100;
        const heightPct = Math.max(((endH - startH) / hours) * 100, 2.5);

        const el = document.createElement('div');
        el.className = 'wv-event';
        el.style.cssText = `top:${top}%;height:${heightPct}%;${eventStyle(ev)}`;
        el.innerHTML = `<div class="event-title">${ev.title || ev.patient_name}${ev._type === 'treatment' ? ' [Tx]' : ''}</div><div class="event-time">${ev.start_time} – ${ev.end_time}</div><span class="status-badge ${getStatusClass(ev.status)}">${getStatusLabel(ev.status)}</span>`;
        el.onclick = (e) => { e.stopPropagation(); openEventDetail(ev.id, ev._type); };
        dayCol.appendChild(el);
    });
}

// Detail modal
function openEventDetail(id, type) {
    if (type === 'treatment') {
        const t = treatments.find(tx => tx.id == id);
        if (!t) return;
        const doctor = doctors.find(d => d.id == t.doctor_id);
        const patient = patients.find(p => p.id == t.patient_id);
        const isAdmin = window.canEditTx || false;
        const statuses = ['pending','in_progress','done','postponed','hold'];
        const isPostponed = t.status === 'postponed';
        document.getElementById('detail-title').textContent = 'Treatment Session';
        document.getElementById('detail-body').innerHTML = `
            <div style="margin-bottom:16px">
                <span class="status-badge ${getStatusClass(t.status)}" style="font-size:13px;padding:4px 12px">${getStatusLabel(t.status)}</span>
                <span class="status-badge" style="background:#F3E8FD;color:#9334E6;font-size:13px;padding:4px 12px">Treatment</span>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><div class="detail-label">Patient</div><div class="detail-value">${t.patient_name || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Doctor</div><div class="detail-value">${doctor ? doctor.name : '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Date</div><div class="detail-value">${t.date}</div></div>
                <div class="detail-item"><div class="detail-label">Time</div><div class="detail-value">${t.start_time} – ${t.end_time}</div></div>
                ${t.notes ? `<div class="detail-item" style="grid-column:1/-1"><div class="detail-label">Notes</div><div class="detail-value">${t.notes}</div></div>` : ''}
            </div>
            ${isAdmin ? `
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div class="form-group">
                    <label>Status</label>
                    <select id="tx-status" onchange="toggleTxReschedule()" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px">
                        ${statuses.map(s => `<option value="${s}"${s === t.status ? ' selected' : ''}>${getStatusLabel(s)}</option>`).join('')}
                    </select>
                </div>
                <div id="tx-reschedule" style="display:${isPostponed ? 'block' : 'none'};margin-top:12px">
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Date</label>
                            <input type="date" id="tx-new-date" value="${t.date}" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px">
                        </div>
                        <div class="form-group">
                            <label>Doctor</label>
                            <select id="tx-new-doctor" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px">
                                <option value="">Same</option>
                                ${doctors.map(d => `<option value="${d.id}"${d.id == t.doctor_id ? ' selected' : ''}>${d.name}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" id="tx-new-start" value="${t.start_time}" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" id="tx-new-end" value="${t.end_time}" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;font-size:14px">
                        </div>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:12px">
                    <button class="btn btn-primary" onclick="updateTreatmentStatus(${t.id})">Done</button>
                </div>
            </div>` : ''}
            <div class="form-actions" style="margin-top:12px">
                <button class="btn" onclick="closeDetailModal()">Close</button>
            </div>
        `;
        document.getElementById('detail-modal').classList.add('open');
        return;
    }
    // Appointment detail
    const a = appointments.find(app => app.id == id);

    const doctor = doctors.find(d => d.id == a.doctor_id);
    const patient = patients.find(p => p.id == a.patient_id);
    const isAdmin = window.canEdit || window.canDelete || window.canConvert || false;

    document.getElementById('detail-title').textContent = a.title || 'Appointment';
    document.getElementById('detail-body').innerHTML = `
        <div style="margin-bottom:16px">
            <span class="status-badge ${getStatusClass(a.status)}" style="font-size:13px;padding:4px 12px">${getStatusLabel(a.status)}</span>
            <span class="status-badge" style="background:#E8F0FE;color:#1A73E8;font-size:13px;padding:4px 12px">${a.type}</span>
        </div>
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Patient</div>
                <div class="detail-value">${a.patient_name || '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Doctor</div>
                <div class="detail-value">${doctor ? doctor.name : '—'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Date</div>
                <div class="detail-value">${a.date}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Time</div>
                <div class="detail-value">${a.start_time} – ${a.end_time}</div>
            </div>
            ${a.patient_phone ? `<div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">${a.patient_phone}</div></div>` : ''}
            ${a.notes ? `<div class="detail-item" style="grid-column:1/-1"><div class="detail-label">Notes</div><div class="detail-value">${a.notes}</div></div>` : ''}
        </div>
        ${function(){
            const txList = treatments.filter(t => t.appointment_id == a.id);
            if (!txList.length) return '';
            return `<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <div style="font-size:13px;font-weight:500;margin-bottom:8px">Linked Treatments (${txList.length})</div>
                ${txList.map(t => `<div style="display:flex;justify-content:space-between;padding:6px 8px;border-radius:4px;background:var(--bg);margin-bottom:4px;font-size:13px">
                    <span>${t.date} ${t.start_time}–${t.end_time}</span>
                    <span class="status-badge ${getStatusClass(t.status)}" style="font-size:11px">${getStatusLabel(t.status)}</span>
                </div>`).join('')}
            </div>`;
        }()}
        <div class="form-actions" style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            ${window.canEdit ? `<button class="btn btn-primary" onclick="closeDetailModal();openEditModal(${a.id})">Edit</button>` : ''}
            ${window.canConvert && a.type === 'appointment' ? `<button class="btn" onclick="closeDetailModal();openConvertModal(${a.id})">Convert to Treatment</button>` : ''}
            ${window.canDelete ? `<button class="btn btn-danger" onclick="closeDetailModal();deleteAppointment(${a.id})">Delete</button>` : ''}
            <button class="btn" onclick="closeDetailModal()">Close</button>
        </div>
    `;
    document.getElementById('detail-modal').classList.add('open');
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.remove('open');
}

// Create/Edit modal
function openCreateModal(date, time) {
    const modal = document.getElementById('appointment-modal');
    document.getElementById('modal-title').textContent = 'New Appointment';
    document.getElementById('appointment-id').value = '';
    document.getElementById('appointment-date').value = date || formatDate(currentDate);
    document.getElementById('appointment-start').value = time || '09:00';

    const endH = time ? parseInt(time) + 1 : 10;
    document.getElementById('appointment-end').value = `${String(endH).padStart(2, '0')}:00`;

    document.getElementById('appointment-title').value = '';
    document.getElementById('appointment-notes').value = '';
    document.getElementById('appointment-status').value = 'pending';
    document.getElementById('appointment-type').value = 'appointment';

    populateSelects();
    modal.classList.add('open');
}

function openEditModal(id) {
    const a = appointments.find(app => app.id == id);
    if (!a) return;

    const modal = document.getElementById('appointment-modal');
    document.getElementById('modal-title').textContent = 'Edit Appointment';
    document.getElementById('appointment-id').value = a.id;
    document.getElementById('appointment-title').value = a.title || '';
    document.getElementById('appointment-date').value = a.date;
    document.getElementById('appointment-start').value = a.start_time;
    document.getElementById('appointment-end').value = a.end_time;
    document.getElementById('appointment-notes').value = a.notes || '';
    document.getElementById('appointment-status').value = a.status;
    document.getElementById('appointment-type').value = a.type;

    populateSelects(a.patient_id, a.doctor_id);
    modal.classList.add('open');
}

function populateSelects(selectedPatient, selectedDoctor) {
    const patientSel = document.getElementById('appointment-patient');
    patientSel.innerHTML = '<option value="">Select patient...</option>';
    patients.forEach(p => {
        patientSel.innerHTML += `<option value="${p.id}"${p.id == selectedPatient ? ' selected' : ''}>${p.name}</option>`;
    });

    const doctorSel = document.getElementById('appointment-doctor');
    doctorSel.innerHTML = '<option value="">Select doctor...</option>';
    doctors.forEach(d => {
        doctorSel.innerHTML += `<option value="${d.id}"${d.id == selectedDoctor ? ' selected' : ''}>${d.name}</option>`;
    });
}

function closeModal() {
    document.getElementById('appointment-modal').classList.remove('open');
}

async function saveAppointment() {
    const id = document.getElementById('appointment-id').value;
    const data = {
        patient_id: document.getElementById('appointment-patient').value,
        doctor_id: document.getElementById('appointment-doctor').value,
        title: document.getElementById('appointment-title').value,
        date: document.getElementById('appointment-date').value,
        start_time: document.getElementById('appointment-start').value,
        end_time: document.getElementById('appointment-end').value,
        status: document.getElementById('appointment-status').value,
        type: document.getElementById('appointment-type').value,
        notes: document.getElementById('appointment-notes').value
    };

    if (!data.patient_id) { alert('Please select a patient'); return; }
    if (!data.date || !data.start_time || !data.end_time) { alert('Please fill date/time'); return; }

    try {
        const url = id ? 'api/appointments.php?id=' + id : 'api/appointments.php';
        const result = await postJSON(url, data);
        closeModal();
        await loadData();
        render();
    } catch (e) {
        if (e.conflicts) {
            showConflictModal(e.conflicts);
        } else {
            showToast('Error: ' + e.message, true);
        }
    }
}

function showConflictModal(conflicts) {
    const list = document.getElementById('conflict-list');
    list.innerHTML = conflicts.map(c =>
        `<div style="padding:10px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px;background:#FFF8E1">
           <strong>${c.start_time} – ${c.end_time}</strong>
           <div style="font-size:13px;color:var(--text-secondary)">${c.patient_name} — ${c.title || 'no title'}</div>
         </div>`
    ).join('');
    document.getElementById('conflict-modal').classList.add('open');
}

function closeConflictModal() {
    document.getElementById('conflict-modal').classList.remove('open');
}

function keepEditing() {
    closeConflictModal();
}

function openConvertModal(id) {
    const a = appointments.find(app => app.id == id);
    if (!a) return;
    document.getElementById('convert-appointment-id').value = id;
    document.getElementById('convert-sittings').value = 1;
    document.getElementById('convert-interval').value = 7;
    generateSittingSlots();
    document.getElementById('convert-modal').classList.add('open');
}

function closeConvertModal() {
    document.getElementById('convert-modal').classList.remove('open');
}

function generateSittingSlots() {
    const a = appointments.find(app => app.id == document.getElementById('convert-appointment-id').value);
    if (!a) return;

    const count = parseInt(document.getElementById('convert-sittings').value);
    const interval = parseInt(document.getElementById('convert-interval').value);
    const container = document.getElementById('convert-slots');

    const baseDate = new Date(a.date + 'T' + (a.start_time || '09:00'));
    let html = '<div style="font-size:13px;font-weight:500;margin-bottom:8px;color:var(--text-secondary)">Treatment Sessions</div>';

    html += `<div style="display:grid;grid-template-columns:30px 1fr 80px 80px 1fr;gap:6px;font-size:12px;font-weight:600;color:var(--text-secondary);padding:4px 0;border-bottom:1px solid var(--border)">`;
    html += '<div>#</div><div>Date</div><div>Start</div><div>End</div><div>Doctor</div></div>';

    for (let i = 0; i < count; i++) {
        const d = new Date(baseDate);
        if (interval > 0) d.setDate(d.getDate() + i * interval);
        const dateStr = formatDate(d);
        const timeStr = a.start_time || '09:00';
        const endStr = a.end_time || '10:00';
        const num = i + 1;

        html += `<div style="display:grid;grid-template-columns:30px 1fr 80px 80px 1fr;gap:6px;align-items:center;padding:4px 0;border-bottom:1px solid var(--border)">`;
        html += `<div style="font-size:13px;color:var(--text-secondary)">${num}</div>`;
        html += `<div><input type="date" class="sit-date" value="${dateStr}" style="width:100%;padding:6px;border:1px solid var(--border);border-radius:4px;font-size:13px"></div>`;
        html += `<div><input type="time" class="sit-start" value="${timeStr}" style="width:100%;padding:6px;border:1px solid var(--border);border-radius:4px;font-size:13px"></div>`;
        html += `<div><input type="time" class="sit-end" value="${endStr}" style="width:100%;padding:6px;border:1px solid var(--border);border-radius:4px;font-size:13px"></div>`;
        html += `<div><select class="sit-doctor" style="width:100%;padding:6px;border:1px solid var(--border);border-radius:4px;font-size:13px">`;
        html += `<option value="">Same doctor</option>`;
        doctors.forEach(doc => {
            html += `<option value="${doc.id}"${doc.id == a.doctor_id ? ' selected' : ''}>${doc.name}</option>`;
        });
        html += `</select></div>`;
        html += `</div>`;
    }

    container.innerHTML = html;
}

async function saveTreatments() {
    const id = document.getElementById('convert-appointment-id').value;
    if (!id) return;

    const dateInputs = document.querySelectorAll('.sit-date');
    const startInputs = document.querySelectorAll('.sit-start');
    const endInputs = document.querySelectorAll('.sit-end');
    const doctorInputs = document.querySelectorAll('.sit-doctor');

    const treatments = [];
    for (let i = 0; i < dateInputs.length; i++) {
        treatments.push({
            appointment_id: i === 0 ? parseInt(id) : null,
            date: dateInputs[i].value,
            start_time: startInputs[i].value,
            end_time: endInputs[i].value,
            doctor_id: doctorInputs[i].value || null
        });
    }

    try {
        const result = await postJSON('api/treatments.php', { bulk: treatments, appointment_id: parseInt(id) });
        if (result.success) {
            closeConvertModal();
            closeDetailModal();
            await loadData();
            render();
        }
    } catch (e) {
        showToast('Error: ' + e.message, true);
    }
}

async function deleteAppointment(id) {
    if (!id) return;
    if (!confirm('Delete this appointment?')) return;
    try {
        const result = await delJSON('api/appointments.php?id=' + id);
        if (result.success) {
            await loadData();
            render();
            showToast('Deleted ✓');
        }
    } catch (e) {
        showToast('Error: ' + e.message, true);
    }
}

function showToast(msg, isError) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (isError ? ' error' : '');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function toggleTxReschedule() {
    const show = document.getElementById('tx-status').value === 'postponed';
    document.getElementById('tx-reschedule').style.display = show ? 'block' : 'none';
}

async function updateTreatmentStatus(id) {
    const status = document.getElementById('tx-status').value;
    const data = { status: status };
    if (status === 'postponed') {
        data.date = document.getElementById('tx-new-date').value;
        data.start_time = document.getElementById('tx-new-start').value;
        data.end_time = document.getElementById('tx-new-end').value;
        const newDoc = document.getElementById('tx-new-doctor').value;
        if (newDoc) data.doctor_id = newDoc;
    }
    try {
        const result = await postJSON('api/treatments.php?id=' + id, data);
        if (result.success) {
            await loadData();
            render();
            openEventDetail(id, 'treatment');
            showToast(status === 'done' ? 'Treatment marked Done ✓' : 'Status updated ✓');
        }
    } catch (e) {
        showToast('Error: ' + e.message, true);
    }
}

// Init
document.addEventListener('DOMContentLoaded', async () => {
    await loadData();
    render();
    updateTitle();
});
