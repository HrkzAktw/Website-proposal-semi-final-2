<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="students">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Registered Students</h2>
                <p class="doc-sub">Open a student to see the full profile, or delete a student from the list.</p>
                <div class="toolbar">
                    <input type="text" id="listFilter" placeholder="Filter by name or Student ID...">
                    <span class="muted" id="studentCount"></span>
                </div>
                <div class="table-frame"><div class="table-wrap"><table class="doc-table">
                    <thead><tr><th>Student</th><th>Course / Year / Section</th><th>Standing</th><th class="num">Minor</th><th class="num">Major</th><th>Last violation</th><th></th></tr></thead>
                    <tbody id="studentList"><tr><td colspan="7" class="empty">Loading students...</td></tr></tbody>
                </table></div>
                <div class="rule-fill"></div></div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
let allRecords = [];
async function loadStudents() {
    await loadPhotos();
    allRecords = await (await fetch('database.php')).json();
    renderStudents();
}

function renderStudents() {
    const q = document.getElementById('listFilter').value.trim().toLowerCase();
    const groups = new Map();
    allRecords.forEach(r => { if (!groups.has(r.studentId)) groups.set(r.studentId, []); groups.get(r.studentId).push(r); });
    const rows = Array.from(groups, ([id, recs]) => {
        const p = profileOf(recs), off = recs.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
        const minor = off.filter(r => r.violationType === 'Minor').length;
        return { id, p, minor, major: off.length - minor, last: off.length ? off[off.length - 1].timestamp : '-' };
    }).reverse().filter(s => !q || s.id.toLowerCase().includes(q) || s.p.name.toLowerCase().includes(q));
    document.getElementById('studentCount').textContent = `${rows.length} student${rows.length === 1 ? '' : 's'}`;
    document.getElementById('studentList').innerHTML = rows.length ? rows.map(s => {
        const st = s.major > 0 || s.minor >= 3 ? ['hold', '✖ Account hold'] : s.minor > 0 ? ['warn', '! Under review'] : ['clear', '✔ Good standing'];
        const href = 'profile.php?from=students&id=' + encodeURIComponent(s.id);
        return `<tr>
            <td><div class="who"><img class="thumb" src="${photoUrl(s.id)}" alt=""><div><a class="name-link" href="${href}">${esc(s.p.name)}</a><div class="muted">ID: ${esc(s.id)}</div></div></div></td>
            <td>${esc(profileInfo(s.p) || '-')}</td>
            <td><span class="pill pill-${st[0]}">${st[1]}</span></td>
            <td class="num">${s.minor}</td><td class="num">${s.major}</td><td>${esc(s.last)}</td>
            <td class="actions"><a class="btn btn-ghost btn-sm" href="${href}">View</a> <a class="btn btn-ghost btn-sm" href="entry.php?id=${encodeURIComponent(s.id)}">+ Offense</a> <button type="button" class="btn btn-danger btn-sm btn-row-delete" data-id="${esc(s.id)}">Delete</button></td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="empty">No students found.</td></tr>';
}
document.getElementById('listFilter').addEventListener('input', renderStudents);

// Delete a registered student (and all of their records) after confirming
document.getElementById('studentList').addEventListener('click', async e => {
    const btn = e.target.closest('.btn-row-delete');
    if (!btn) return;
    const id = btn.dataset.id;
    const recs = allRecords.filter(r => r.studentId === id);
    const name = recs.length ? recs[recs.length - 1].studentName : id;
    const ok = await askConfirm({
        title: 'Delete this student?',
        body: `<p>This permanently deletes <strong>${esc(name)}</strong> (ID ${esc(id)}) and all ${recs.length} record(s) on file. This cannot be undone.</p>`,
        confirmLabel: 'Delete student', danger: true
    });
    if (!ok) return;
    const result = await (await fetch('database.php?studentId=' + encodeURIComponent(id), { method: 'DELETE' })).json();
    if (result.success) { showToast(`${name} was deleted.`, 'success'); loadStudents(); }
    else showToast('Could not delete the student. Please try again.', 'error');
});

loadStudents();
autoRefresh(loadStudents);
    </script>
</body>
</html>
