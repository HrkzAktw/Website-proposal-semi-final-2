// Always ask the server for fresh data (never reuse a cached copy)
const _nativeFetch = window.fetch.bind(window);
window.fetch = (url, opts = {}) => _nativeFetch(url, { cache: 'no-store', ...opts });

// Re-run a page's loader every few seconds and when the tab comes back into view
function autoRefresh(fn, ms = 5000) {
    setInterval(() => { if (!document.hidden) fn(); }, ms);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) fn(); });
}

// Shared by every S.C.O.R.E.S. page: login gate, in-page messages and confirm dialogs
(function () {
    const workspace = document.getElementById('mainWorkspace');
    const gate = document.createElement('div');
    gate.id = 'authGateway';
    gate.className = 'auth-fullscreen-container';
    gate.innerHTML = `
        <div class="auth-card">
            <img src="images/school-logo.png" alt="School Emblem" class="auth-logo">
            <h2>S.C.O.R.E.S. Portal Log In</h2>
            <p class="auth-subtitle">Authorized Guidance & Faculty Terminal Access Only</p>
            <form id="loginForm">
                <div class="auth-input-group">
                    <label for="authUsername">Username</label>
                    <input type="text" id="authUsername" placeholder="Enter administrative username..." required>
                </div>
                <div class="auth-input-group">
                    <label for="authPassword">Password</label>
                    <input type="password" id="authPassword" placeholder="Enter secure password..." required>
                </div>
                <button type="submit" class="btn btn-auth">🔓 Authenticate and Enter Terminal</button>
            </form>
            <div class="auth-footer-notice"> Protected by Institutional Data Privacy Protocols & RA 10173 </div>
        </div>`;
    document.body.prepend(gate);
    const unlock = () => { gate.style.display = 'none'; workspace.classList.remove('system-hidden-state'); };
    if (sessionStorage.getItem('scoresAuth') === '1') unlock();
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (document.getElementById('authUsername').value === 'admin' && document.getElementById('authPassword').value === 'password123') {
            sessionStorage.setItem('scoresAuth', '1');
            unlock();
        } else {
            showToast('Authentication failed: invalid administrative credentials.', 'error');
        }
    });
})();

// Small in-page message (type: success | error | info)
function showToast(message, type = 'info') {
    let stack = document.getElementById('toastStack');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'toastStack';
        stack.className = 'toast-stack';
        document.body.appendChild(stack);
    }
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = message;
    stack.appendChild(t);
    setTimeout(() => t.remove(), type === 'error' ? 6000 : 4000);
}

// In-page confirm dialog; resolves true (confirm) or false (cancel). title/body may contain HTML, so escape user text first.
function askConfirm({ icon = '⚠️', title, body, confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false }) {
    return new Promise(resolve => {
        const o = document.createElement('div');
        o.className = 'modal-overlay';
        o.innerHTML = `<div class="modal-box"><div class="modal-icon">${icon}</div><h2 class="modal-title">${title}</h2><div class="modal-body">${body}</div><div class="modal-actions"><button type="button" class="btn btn-modal-cancel">${cancelLabel}</button><button type="button" class="btn ${danger ? 'btn-modal-danger' : 'btn-modal-confirm'}">${confirmLabel}</button></div></div>`;
        document.body.appendChild(o);
        const done = v => { o.remove(); resolve(v); };
        o.querySelector('.btn-modal-cancel').onclick = () => done(false);
        o.querySelector('.modal-actions button:last-child').onclick = () => done(true);
        o.addEventListener('click', e => { if (e.target === o) done(false); });
    });
}

// ---- Shared helpers used by every page
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// Latest known profile details for one student's records
function profileOf(recs) {
    const p = { name: '', course: '', yearLevel: '', section: '' };
    recs.forEach(r => { p.name = r.studentName || p.name; p.course = r.course || p.course; p.yearLevel = r.yearLevel || p.yearLevel; p.section = r.section || p.section; });
    return p;
}
const profileInfo = p => [p.course, p.yearLevel, p.section ? 'Section ' + p.section : ''].filter(Boolean).join(' | ');

// Older records have no category field; if the remark is exactly a known category name, count it under that category
const KNOWN_CATEGORIES = ['Attendance & Punctuality', 'Policy Breaches', 'Campus Safety & Traffic', 'Property & Cleanliness', 'Academic Dishonesty', 'Falsification', 'Violence & Physical Harm', 'Substance Abuse & Vices', 'Theft & Property Damage', 'Gross Misconduct'];
function categoryOf(r) {
    if (r.category) return r.category;
    const remark = (r.description || '').trim().toLowerCase();
    return KNOWN_CATEGORIES.find(c => c.toLowerCase() === remark) || 'Uncategorized';
}

// ---- Page chrome: compact header (a different style on each page), back button and footer
const PAGES = {
    home:      { cls: 'h-center', sub: 'Student Conduct, Offense Recording, and Evaluation System' },
    entry:     { cls: 'h-solid',  sub: 'Student Entry &amp; Incident Recording' },
    students:  { cls: 'h-letter', sub: 'Registered Students' },
    clearance: { cls: 'h-tint',   sub: 'Good Moral Clearance Count' },
    scanner:   { cls: 'h-stripe', sub: 'Multi-Violation Scanner &amp; Good Moral Evaluation' },
    dashboard: { cls: 'h-slate',  sub: 'Dashboard | Overview of students and clearances' },
    profile:   { cls: 'h-gold',   sub: 'Student Profile' },
    reports:   { cls: 'h-outline', sub: 'Reports | Offense Monitoring' }
};
const FROM = { students: ['students.php', 'Students'], reports: ['reports.php', 'Reports'], scanner: ['scanner.php', 'Scanner'], dashboard: ['dashboard.php', 'Dashboard'] };
const NAV = [['home', 'index.php', 'Menu'], ['dashboard', 'dashboard.php', 'Dashboard'], ['entry', 'entry.php', 'Student Entry'], ['students', 'students.php', 'Registered Students'], ['scanner', 'scanner.php', 'Scanner'], ['clearance', 'clearance.php', 'Clearance Count'], ['reports', 'reports.php', 'Reports']];

// One summary row per student, from the flat records list
function summarize(records) {
    const groups = new Map();
    records.forEach(r => { if (!groups.has(r.studentId)) groups.set(r.studentId, []); groups.get(r.studentId).push(r); });
    return Array.from(groups, ([id, recs]) => {
        const off = recs.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
        const minor = off.filter(r => r.violationType === 'Minor').length, major = off.length - minor;
        const level = off.length === 0 ? 'clear' : (minor >= 5 || major >= 3) ? 'critical' : (minor >= 3 || major > 0) ? 'hold' : 'warn';
        return { id, p: profileOf(recs), minor, major, last: off.length ? off[off.length - 1].timestamp : '-', level };
    });
}
const LEVEL = { clear: ['clear', '✔ Good standing'], warn: ['warn', '! Under review'], hold: ['hold', '✖ Account hold'], critical: ['hold', '✖ Critical hold'] };
(function () {
    const key = document.body.dataset.page, cfg = PAGES[key];
    if (!cfg) return;
    const home = key === 'home';
    const from = FROM[new URLSearchParams(location.search).get('from')];
    const right = home ? '<img class="ph-logo" src="images/department-logo.png" alt="">'
        : `<a class="ph-back" href="${from ? from[0] : 'index.php'}">← Back to ${from ? from[1] : 'Menu'}</a>`;
    const here = key === 'profile' ? 'students' : key;
    const nav = '<nav class="main-nav">' + NAV.map(n => `<a href="${n[1]}"${n[0] === here ? ' class="active"' : ''}>${n[2]}</a>`).join('') + '</nav>';
    document.getElementById('pageHeader').innerHTML = `<header class="page-head ${cfg.cls}">
        <img class="ph-logo" src="images/school-logo.png" alt="">
        <div class="ph-title"><strong>S.C.O.R.E.S. Portal</strong><span>${cfg.sub}</span></div>
        ${right}
    </header>${nav}`;
    document.getElementById('pageFooter').innerHTML = `<footer class="doc-footer">
        <div class="foot-links"><a href="https://pampangastateu.edu.ph" target="_blank">🌐 Official Pampanga State U Website</a><a href="https://www.facebook.com/PampangaStateU/" target="_blank">🔵 Official Facebook Page</a></div>
        <p>S.C.O.R.E.S. LAN Environment v1.0.0 | Powered by PHP Backend Server Framework</p>
        ${home ? '<p><a href="#" id="logoutLink">Log out</a></p>' : ''}
    </footer>`;
    const out = document.getElementById('logoutLink');
    if (out) out.addEventListener('click', e => { e.preventDefault(); sessionStorage.removeItem('scoresAuth'); location.reload(); });
})();

// ---- Student photos (saved on the server computer by upload.php)
const safeId = id => String(id).replace(/[^A-Za-z0-9_-]/g, '_');
let PHOTO_MAP = {};
async function loadPhotos() {
    try { PHOTO_MAP = await (await fetch('upload.php')).json(); } catch (e) { PHOTO_MAP = {}; }
}
const photoUrl = id => PHOTO_MAP[safeId(id)] || 'images/default-avatar.png';

// Shrink a chosen picture to a small JPEG before sending it (fast uploads, small photos folder)
function shrinkImage(file, maxSide = 600) {
    return new Promise((resolve, reject) => {
        const img = new Image(), url = URL.createObjectURL(file);
        img.onload = () => {
            const k = Math.min(1, maxSide / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob(b => b ? resolve(b) : reject(new Error('encode')), 'image/jpeg', 0.85);
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
        img.src = url;
    });
}

async function uploadPhoto(id, blob) {
    const fd = new FormData();
    fd.append('studentId', id);
    fd.append('photo', blob, 'photo.jpg');
    try {
        const r = await (await fetch('upload.php', { method: 'POST', body: fd })).json();
        if (r.success) await loadPhotos();
        return !!r.success;
    } catch (e) { return false; }
}

async function removePhoto(id) {
    const fd = new FormData();
    fd.append('studentId', id);
    fd.append('action', 'delete');
    try {
        const r = await (await fetch('upload.php', { method: 'POST', body: fd })).json();
        if (r.success) await loadPhotos();
        return !!r.success;
    } catch (e) { return false; }
}
