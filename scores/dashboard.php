<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="dashboard">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Dashboard</h2>
                <p class="doc-sub">A quick overview of the students on file and the clearances issued.</p>
                <div class="stat-row n5">
                    <div class="stat"><b id="dTotal">0</b><span>Registered students</span></div>
                    <div class="stat good"><b id="dClear">0</b><span>Good standing</span></div>
                    <div class="stat warn"><b id="dWarn">0</b><span>Under review</span></div>
                    <div class="stat bad"><b id="dHold">0</b><span>On hold</span></div>
                    <div class="stat"><b id="dPrints">0</b><span>Clearances issued</span></div>
                </div>
                <div class="dash-grid">
                    <div class="dash-col"><h3 class="sub-h">Recent offenses logged</h3>
                        <div class="table-frame"><div class="table-wrap"><table class="doc-table"><thead><tr><th>Student</th><th>Severity</th><th>Category</th><th>Logged</th></tr></thead><tbody id="recentOffenses"><tr><td colspan="4" class="empty">Loading...</td></tr></tbody></table></div><div class="rule-fill"></div></div></div>
                    <div class="dash-col"><h3 class="sub-h">Recent clearances printed</h3>
                        <div class="table-frame"><div class="table-wrap"><table class="doc-table"><thead><tr><th>Student</th><th>Type</th><th>Printed</th></tr></thead><tbody id="recentClearances"><tr><td colspan="3" class="empty">Loading...</td></tr></tbody></table></div><div class="rule-fill"></div></div></div>
                </div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
async function loadDashboard() {
    const [records, prints] = await Promise.all([fetch('database.php').then(r => r.json()), fetch('database.php?type=prints').then(r => r.json())]);
    const s = summarize(records), n = lv => s.filter(x => lv.includes(x.level)).length;
    document.getElementById('dTotal').textContent = s.length;
    document.getElementById('dClear').textContent = n(['clear']);
    document.getElementById('dWarn').textContent = n(['warn']);
    document.getElementById('dHold').textContent = n(['hold', 'critical']);
    document.getElementById('dPrints').textContent = prints.length;
    const off = records.filter(r => r.violationType === 'Minor' || r.violationType === 'Major').slice(-6).reverse();
    document.getElementById('recentOffenses').innerHTML = off.length ? off.map(r => `<tr><td><a class="name-link" href="profile.php?from=dashboard&id=${encodeURIComponent(r.studentId)}">${esc(r.studentName)}</a></td><td><span class="pill ${r.violationType === 'Major' ? 'pill-hold' : 'pill-warn'}">${esc(r.violationType)}</span></td><td>${esc(categoryOf(r))}</td><td>${esc(r.timestamp)}</td></tr>`).join('') : '<tr><td colspan="4" class="empty">No offenses recorded.</td></tr>';
    const pr = prints.slice(-6).reverse();
    document.getElementById('recentClearances').innerHTML = pr.length ? pr.map(p => `<tr><td>${esc(p.studentName || 'Unregistered Student')}</td><td>${esc(p.status)}</td><td>${esc(p.timestamp)}</td></tr>`).join('') : '<tr><td colspan="3" class="empty">No clearances printed yet.</td></tr>';
}
loadDashboard();
autoRefresh(loadDashboard);
    </script>
</body>
</html>
