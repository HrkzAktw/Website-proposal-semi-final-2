<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="profile">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Student Profile</h2>
                <p class="doc-sub">Full record of one student: photo, standing and violation history.</p>
                <div id="profileDisplay"><p class="empty">Loading...</p></div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
const profileId = new URLSearchParams(location.search).get('id') || '';
let currentId = profileId, currentName = '';

async function performScan() {
    const box = document.getElementById('profileDisplay');
    if (!profileId) { box.innerHTML = '<div class="empty-state"><span>👤</span><p>Open a student from the Registered Students list to see their profile.</p></div>'; return; }
    await loadPhotos();
    const allRecords = await (await fetch('database.php')).json();
    const recs = allRecords.filter(r => r.studentId === profileId);
    if (!recs.length) { box.innerHTML = `<div class="empty-state"><span>🔍</span><p>No student found with ID ${esc(profileId)}.</p></div>`; return; }
    const s = summarize(allRecords).find(x => x.id === profileId), p = s.p;
    currentName = p.name;
    const offenses = recs.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
    const [cls, icon, badge] = { clear: ['clear', '✔', 'GOOD STANDING'], warn: ['warn', '!', 'UNDER ADMINISTRATIVE REVIEW'], hold: ['hold', '✖', 'ACCOUNT HOLD / BLOCKED'], critical: ['hold', '✖', 'CRITICAL HOLD: HABITUAL OFFENDER'] }[s.level];
    const hasPhoto = !!PHOTO_MAP[safeId(profileId)];
    const rows = offenses.length ? offenses.slice().reverse().map(r => `<tr><td><span class="pill ${r.violationType === 'Major' ? 'pill-hold' : 'pill-warn'}">${esc(r.violationType)}</span></td><td>${esc(categoryOf(r))}</td><td>${esc(r.description)}</td><td>${esc(r.timestamp)}</td></tr>`).join('') : '<tr><td colspan="4" class="empty">No violations on record.</td></tr>';
    const purgeBtn = offenses.length ? '<button type="button" class="btn btn-danger" data-act="purge">🔧 Administrative Profile Override (Purge Violations)</button>' : '';
    box.innerHTML = `
        <div class="status-banner ${cls}"><span class="status-icon">${icon}</span>${badge}</div>
        <div class="profile">
            <div class="avatar-wrap"><img class="avatar" src="${photoUrl(profileId)}" alt="Student photo">
                <button type="button" class="photo-badge" data-act="photo-menu" title="Photo options" aria-label="Photo options">📷</button>
                <div class="photo-menu" hidden><button type="button" data-act="photo">📷 ${hasPhoto ? 'Upload new photo' : 'Upload photo'}</button>${hasPhoto ? '<button type="button" class="danger" data-act="photo-remove">🗑️ Remove photo</button>' : ''}</div>
                <input type="file" id="photoFile" accept="image/*" hidden>
            </div>
            <div>
                <h3 class="profile-name">${esc(p.name)}</h3>
                <table class="kv">
                    <tr><th>Student ID</th><td>${esc(profileId)}</td></tr>
                    <tr><th>Course / Year / Section</th><td>${esc(profileInfo(p) || 'Not recorded')}</td></tr>
                    <tr><th>Last violation</th><td>${esc(s.last === '-' ? 'None on record' : s.last)}</td></tr>
                    <tr><th>Active offenses</th><td>${s.minor} Minor | ${s.major} Major</td></tr>
                    <tr><th>Standing</th><td>${esc(LEVEL[s.level][1].slice(2))}</td></tr>
                </table>
            </div>
        </div>
        <h3 class="sub-h">Violation history</h3>
        <div class="table-frame"><div class="table-wrap"><table class="doc-table"><thead><tr><th>Severity</th><th>Category</th><th>Remarks</th><th>Logged</th></tr></thead><tbody>${rows}</tbody></table></div><div class="rule-fill"></div></div>
        <div class="action-row"><a class="btn" href="entry.php?id=${encodeURIComponent(profileId)}">➕ Add Offense</a><a class="btn btn-ghost" href="scanner.php?id=${encodeURIComponent(profileId)}">🖨️ Evaluate &amp; Print Clearance</a>${purgeBtn}</div>`;
}
document.getElementById('profileDisplay').addEventListener('click', e => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const act = b.dataset.act;
    if (act === 'photo-menu') { const m = document.querySelector('.photo-menu'); m.hidden = !m.hidden; }
    else if (act === 'photo') { document.querySelector('.photo-menu').hidden = true; document.getElementById('photoFile').click(); }
    else if (act === 'photo-remove') removeStudentPhoto();
    else if (act === 'purge') clearRecords(currentId);
});
document.addEventListener('click', e => {   // clicking anywhere else closes the photo menu
    const m = document.querySelector('.photo-menu');
    if (m && !e.target.closest('.avatar-wrap')) m.hidden = true;
});
async function removeStudentPhoto() {
    document.querySelector('.photo-menu').hidden = true;
    const ok = await askConfirm({
        title: 'Remove this photo?',
        body: `<p>The photo of <strong>${esc(currentName || currentId)}</strong> (ID ${esc(currentId)}) will be deleted from the server. The student profile stays.</p>`,
        confirmLabel: 'Remove photo', danger: true
    });
    if (!ok) return;
    if (await removePhoto(currentId)) { showToast('Photo removed.', 'success'); performScan(); }
    else showToast('The photo could not be removed. Please try again.', 'error');
}
document.getElementById('profileDisplay').addEventListener('change', async e => {
    if (e.target.id !== 'photoFile' || !e.target.files[0]) return;
    try {
        const blob = await shrinkImage(e.target.files[0]);
        if (await uploadPhoto(currentId, blob)) { showToast('Photo updated.', 'success'); performScan(); }
        else showToast('The photo could not be uploaded. Please try again.', 'error');
    } catch (err) { showToast('That picture could not be read. Try a different file.', 'error'); }
});

async function clearRecords(id) {
    const ok = await askConfirm({
        title: 'Purge disciplinary records?',
        body: `<p>This permanently clears all disciplinary history for Student ID <strong>${esc(id)}</strong>. The student profile stays on file in good standing.</p>`,
        confirmLabel: 'Purge records', danger: true
    });
    if (!ok) return;
    const allRecords = await (await fetch('database.php')).json();
    const p = profileOf(allRecords.filter(r => r.studentId === id));
    const updatedRecords = allRecords.filter(r => r.studentId !== id);
    // One clean baseline entry keeps the ID, name and course details on the server
    updatedRecords.push({ studentId: id, studentName: p.name || 'Student Record', course: p.course, yearLevel: p.yearLevel, section: p.section, violationType: "Clear", category: "Good Standing", description: "Disciplinary archive records purged via Administrative Profile Override.", timestamp: new Date().toLocaleString() });
    const result = await (await fetch('database.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(updatedRecords) })).json();
    if (result.success) {
        showToast(`Records purged. ${p.name || 'The student'} is back in good standing.`, 'success');
        performScan();
    }
}


performScan();
    </script>
</body>
</html>
