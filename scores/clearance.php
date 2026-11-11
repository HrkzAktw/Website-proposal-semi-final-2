<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Good Moral Clearance Count - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="clearance">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Good Moral Clearance Count</h2>
                <p class="doc-sub">Every clearance printed from the Scanner page is counted here.</p>
                <div class="stat-row">
                    <div class="stat"><b id="statStudents">0</b><span>Students issued clearance</span></div>
                    <div class="stat"><b id="statPrints">0</b><span>Total clearance prints</span></div>
                </div>
                <div class="section-bar"><h3 class="sub-h">Clearance log</h3><button type="button" id="clearCountBtn" class="btn btn-danger btn-sm">🧹 Reset clearance count</button></div>
                <div class="table-frame"><div class="table-wrap tall"><table class="doc-table">
                    <thead><tr><th>Date printed</th><th>Student</th><th>Student ID</th><th>Type</th></tr></thead>
                    <tbody id="recentPrints"><tr><td colspan="4" class="empty">No clearances printed yet.</td></tr></tbody>
                </table></div>
                <div class="rule-fill"></div></div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
async function loadStats() {
    const prints = await fetch('database.php?type=prints').then(r => r.json());
    document.getElementById('statStudents').textContent = new Set(prints.map(p => p.studentId)).size;
    document.getElementById('statPrints').textContent = prints.length;
    document.getElementById('recentPrints').innerHTML = prints.length ? prints.slice().reverse().map(p => `<tr><td>${esc(p.timestamp)}</td><td>${esc(p.studentName || 'Unregistered Student')}</td><td>${esc(p.studentId)}</td><td>${esc(p.status)}</td></tr>`).join('') : '<tr><td colspan="4" class="empty">No clearances printed yet.</td></tr>';
}

// Reset the clearance count (clears the print log only; student records are not touched)
document.getElementById('clearCountBtn').addEventListener('click', async () => {
    const ok = await askConfirm({
        title: 'Reset the clearance count?',
        body: '<p>This sets <strong>Students issued clearance</strong> and <strong>Total clearance prints</strong> back to 0 and clears the recently printed list. Student records are not affected.</p>',
        confirmLabel: 'Reset count', danger: true
    });
    if (!ok) return;
    const result = await (await fetch('database.php?type=prints', { method: 'DELETE' })).json();
    if (result.success) { showToast('Clearance count reset to 0.', 'success'); loadStats(); }
    else showToast('Could not reset the count. Please try again.', 'error');
});

loadStats();
autoRefresh(loadStats);
    </script>
</body>
</html>
