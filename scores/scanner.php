<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Violation Scanner &amp; Good Moral Evaluation - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="scanner">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Multi-Violation Scanner &amp; Good Moral Evaluation</h2>
                <p class="doc-sub">Type or scan a Student ID to check eligibility and print a clearance.</p>
                <div class="scan-row"><input type="text" id="searchId" placeholder="Scan ID / Type Student ID Number..."><button type="button" id="scanBtn" class="btn">Scan Student</button></div>
                <div id="scanResultDisplay"><div class="empty-state"><span>🔍</span><p>Awaiting student lookup or ID scan input...</p></div></div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <div id="clearanceSheet"></div>
    <script src="app.js?v=4"></script>
    <script>
// Evaluate a student's clearance eligibility
let currentId = '';
document.getElementById('scanBtn').addEventListener('click', performScan);
document.getElementById('searchId').addEventListener('keypress', e => { if (e.key === 'Enter') performScan(); });
const RULES = [
    ['clear', 'Good Standing', 'No Minor or Major offense', 'Good Moral Clearance can be printed'],
    ['warn', 'Under Review', '1-2 Minor offenses and no Major offense', 'Conditional Clearance only, after counselor verification'],
    ['hold', 'Account Hold', '3 or more Minor offenses, or any Major offense', 'Printing is blocked'],
    ['critical', 'Critical Hold', '5 or more Minor offenses, or 3 or more Major offenses', 'Printing is blocked and the case goes to the Student Disciplinary Board']
];

async function performScan() {
    const searchId = document.getElementById('searchId').value.trim();
    if (!searchId) return showToast('Please enter or scan a Student ID.', 'error');
    currentId = searchId;
    await loadPhotos();
    const allRecords = await (await fetch('database.php')).json();
    const recs = allRecords.filter(r => r.studentId === searchId);
    const offenses = recs.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
    const minor = offenses.filter(r => r.violationType === 'Minor').length, major = offenses.length - minor;
    const p = profileOf(recs);
    const last = offenses.length ? offenses[offenses.length - 1].timestamp : 'None on record';
    const level = offenses.length === 0 ? 'clear' : (minor >= 5 || major >= 3) ? 'critical' : (minor >= 3 || major > 0) ? 'hold' : 'warn';
    const BANNER = { clear: ['clear', '✔', 'CLEAR / APPROVED FOR ISSUANCE'], warn: ['warn', '!', 'UNDER ADMINISTRATIVE REVIEW'], hold: ['hold', '✖', 'ACCOUNT HOLD / BLOCKED'], critical: ['hold', '✖', 'CRITICAL HOLD: HABITUAL OFFENDER'] }[level];
    const NOTE = {
        clear: 'System Action: Document generation is fully authorized. No active disciplinary incidents were found.',
        warn: 'System Action: Document generation is conditional. Guidance Counselor verification is required before a manual print override.',
        hold: 'System Action: Print locking active. Document printing is restricted due to uncompleted community service, recurring issues, or an active Major incident file.',
        critical: `System Action: Critical lockout active. This student has accumulated an extreme frequency of infractions (${minor} Minor, ${major} Major). The profile is escalated to the Student Disciplinary Board for review.`
    }[level];
    const printBtn = level === 'clear' ? '<button type="button" class="btn" data-act="print" data-status="Good Moral">🖨️ Print Good Moral Clearance Form</button>'
        : level === 'warn' ? '<button type="button" class="btn" data-act="print" data-status="Conditional">🖨️ Print Conditional Clearance Approval</button>'
        : '<button type="button" class="btn" disabled>🚫 Printing blocked for this standing</button>';
    const rules = RULES.map(r => `<tr class="${r[0] === level ? 'rule-on' : ''}"><td>${r[1]}</td><td>${r[2]}</td><td>${r[3]}</td></tr>`).join('');

    document.getElementById('scanResultDisplay').innerHTML = `
        <div class="status-banner ${BANNER[0]}"><span class="status-icon">${BANNER[1]}</span>${BANNER[2]}</div>
        <div class="summary">
            <img class="thumb-lg" src="${photoUrl(searchId)}" alt="Student photo">
            <div class="summary-text">
                <h3 class="profile-name">${esc(p.name || 'Unregistered Student')}</h3>
                <div class="muted">ID ${esc(searchId)} | ${esc(profileInfo(p) || 'Course / year / section not recorded')}</div>
                <div class="muted">${minor} Minor | ${major} Major offenses | Last violation: ${esc(last)}</div>
            </div>
            ${recs.length ? `<a class="btn btn-ghost btn-sm" href="profile.php?from=scanner&id=${encodeURIComponent(searchId)}">View full profile</a>` : ''}
        </div>
        <h3 class="sub-h">Clearance evaluation rules</h3>
        <div class="table-frame"><div class="table-wrap"><table class="doc-table"><thead><tr><th>Standing</th><th>Condition</th><th>Result</th></tr></thead><tbody>${rules}</tbody></table></div><div class="rule-fill"></div></div>
        <p class="sys-note">${NOTE}</p>
        <div class="action-row">${printBtn}</div>`;
}
document.getElementById('scanResultDisplay').addEventListener('click', e => {
    const b = e.target.closest('[data-act="print"]');
    if (b) printClearance(currentId, b.dataset.status);
});

// Print the clearance sheet only, then log the print so the student is counted
async function printClearance(id, status) {
    const records = await (await fetch('database.php')).json();
    const recs = records.filter(r => r.studentId === id);
    const p = profileOf(recs);
    const info = profileInfo(p).split(' | ').join(', ');
    const minor = recs.filter(r => r.violationType === 'Minor').length;
    const good = status === 'Good Moral';
    await loadPhotos();
    const photo = PHOTO_MAP[safeId(id)] ? `<img class="sheet-photo" src="${PHOTO_MAP[safeId(id)]}" alt="">` : '';
    const date = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('clearanceSheet').innerHTML = `
        <div class="sheet-head"><img src="images/school-logo.png" alt="" class="sheet-logo"><div><div class="sheet-school">Pampanga State U</div><div class="sheet-office">Guidance Office</div></div></div>
        <h1 class="sheet-title">${good ? 'Certificate of Good Moral Character' : 'Conditional Clearance'}</h1>
        ${photo}<p>This is to certify that <strong>${esc(p.name || 'the student')}</strong>, Student ID <strong>${esc(id)}</strong>${info ? ', ' + esc(info) : ''}, ${good ? 'has no disciplinary offense on record with this office as of the date below.' : `has ${minor} minor offense(s) on file and no major offense. This clearance is conditional and subject to verification by the Guidance Counselor.`}</p>
        <p>This certification is issued upon the student's request for whatever lawful purpose it may serve.</p>
        <p>Issued on ${date}.</p>
        <div class="sheet-signature"><div class="sheet-line"></div>Guidance Counselor</div>`;
    const sheetPhoto = document.querySelector('#clearanceSheet .sheet-photo');
    if (sheetPhoto) await sheetPhoto.decode().catch(() => {});
    window.print();
    await fetch('database.php?type=prints', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ studentId: id, studentName: p.name, status })
    });
}


const presetId = new URLSearchParams(location.search).get('id');
if (presetId) {
    document.getElementById('searchId').value = presetId;
    performScan();
}
    </script>
</body>
</html>
