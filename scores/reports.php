<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports &amp; Monitoring - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="reports">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Offense Reports &amp; Monitoring</h2>
                <p class="doc-sub">Summary of the recorded offenses and the students who need attention.</p>
                <div class="stat-row n4">
                    <div class="stat"><b id="rTotal">0</b><span>Total offenses recorded</span></div>
                    <div class="stat warn"><b id="rMinor">0</b><span>Minor offenses</span></div>
                    <div class="stat bad"><b id="rMajor">0</b><span>Major offenses</span></div>
                    <div class="stat"><b id="rStudents">0</b><span>Students needing attention</span></div>
                </div>
                <div class="two-col">
                    <div><h3 class="sub-h">Offenses by category</h3><div id="catBars"></div></div>
                    <div><h3 class="sub-h">Student standing</h3><div id="standBars"></div></div>
                </div>
                <h3 class="sub-h">Students needing attention</h3>
                <div class="table-frame"><div class="table-wrap"><table class="doc-table">
                    <thead><tr><th>Student</th><th>Course / Year / Section</th><th>Standing</th><th class="num">Minor</th><th class="num">Major</th><th>Last violation</th><th></th></tr></thead>
                    <tbody id="watchList"><tr><td colspan="7" class="empty">Loading...</td></tr></tbody>
                </table></div><div class="rule-fill"></div></div>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
const RANK = { critical: 3, hold: 2, warn: 1 };
async function loadReports() {
    await loadPhotos();
    const records = await (await fetch('database.php')).json();
    const off = records.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
    const s = summarize(records), minor = off.filter(r => r.violationType === 'Minor').length;
    document.getElementById('rTotal').textContent = off.length;
    document.getElementById('rMinor').textContent = minor;
    document.getElementById('rMajor').textContent = off.length - minor;
    document.getElementById('rStudents').textContent = s.filter(x => x.level !== 'clear').length;

    const bars = (rows, cls) => { const max = Math.max(1, ...rows.map(r => r[1])); return rows.map(([label, n, c]) => `<div class="bar-row"><span>${esc(label)}</span><div class="bar"><i class="${c || cls || ''}" style="width:${Math.round(n / max * 100)}%"></i></div><b>${n}</b></div>`).join(''); };
    const counts = {};
    off.forEach(r => { const c = categoryOf(r); counts[c] = (counts[c] || 0) + 1; });
    const cats = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    document.getElementById('catBars').innerHTML = cats.length ? bars(cats) : '<p class="empty">No offenses recorded.</p>';
    const n = lv => s.filter(x => lv.includes(x.level)).length;
    document.getElementById('standBars').innerHTML = s.length ? bars([['Good standing', n(['clear']), 'g'], ['Under review', n(['warn']), 'a'], ['Account hold', n(['hold', 'critical']), 'r']]) : '<p class="empty">No students yet.</p>';

    const watch = s.filter(x => x.level !== 'clear').sort((a, b) => RANK[b.level] - RANK[a.level] || b.major - a.major || b.minor - a.minor);
    document.getElementById('watchList').innerHTML = watch.length ? watch.map(x => {
        const href = 'profile.php?from=reports&id=' + encodeURIComponent(x.id);
        return `<tr>
            <td><div class="who"><img class="thumb" src="${photoUrl(x.id)}" alt=""><div><a class="name-link" href="${href}">${esc(x.p.name)}</a><div class="muted">ID: ${esc(x.id)}</div></div></div></td>
            <td>${esc(profileInfo(x.p) || '-')}</td>
            <td><span class="pill pill-${LEVEL[x.level][0]}">${LEVEL[x.level][1]}</span></td>
            <td class="num">${x.minor}</td><td class="num">${x.major}</td><td>${esc(x.last)}</td>
            <td class="actions"><a class="btn btn-ghost btn-sm" href="${href}">View</a></td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="empty">No students need attention.</td></tr>';
}
loadReports();
autoRefresh(loadReports);
    </script>
</body>
</html>
