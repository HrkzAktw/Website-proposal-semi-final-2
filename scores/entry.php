<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Entry &amp; Incident Recording - S.C.O.R.E.S.</title>
    <link rel="stylesheet" href="style.css?v=5">
</head>
<body data-page="entry">
    <div id="mainWorkspace" class="system-hidden-state">
        <main class="sheet">
            <div id="pageHeader"></div>
            <div class="doc">
                <h2 class="doc-title">Student Entry &amp; Incident Recording</h2>
                <p class="doc-sub">Register a student, or type the ID of a registered student to add another offense to their record.</p>
                <form id="violationForm">
                    <div id="existingNotice" class="notice" hidden></div>
                    <div class="form-grid">
                        <div class="field"><label for="studentId">Student ID Number</label><input type="text" id="studentId" placeholder="e.g., 2024101043" required></div>
                        <div class="field"><label for="studentName">Student Full Name</label><input type="text" id="studentName" placeholder="Last Name, First Name, M.I." required></div>
                        <div class="field"><label for="course">Course / Program</label><input type="text" id="course" placeholder="e.g., BSIT"></div>
                        <div class="pair">
                            <div class="field"><label for="yearLevel">Year Level</label><select id="yearLevel"><option value="">Select</option><option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option></select></div>
                            <div class="field"><label for="section">Section</label><input type="text" id="section" placeholder="e.g., 3A"></div>
                        </div>
                        <div class="field full photo-field">
                            <label for="studentPhoto">Student Photo (optional)</label>
                            <div class="photo-row">
                                <img id="photoPreview" src="images/default-avatar.png" alt="Photo preview">
                                <div><input type="file" id="studentPhoto" accept="image/*"><p class="muted">JPG or PNG. The picture is shrunk automatically and saved on the server computer.</p></div>
                            </div>
                        </div>
                        <div class="field"><label for="violationType">Violation Severity</label><select id="violationType" required><option value="Clear">No Violation / Good Standing</option><option value="Minor">Minor Offense</option><option value="Major">Major Offense</option></select></div>
                        <div class="field"><label for="offensePreset">Common Offenses</label><select id="offensePreset"></select></div>
                        <div class="field full"><label for="description">Remarks / Details</label><textarea id="description" rows="3" placeholder="Pick a common offense above or type your own details (optional for Good Standing)..."></textarea></div>
                    </div>
                    <div class="action-row"><button type="submit" class="btn" id="saveBtn">🔒 Save Entry</button></div>
                </form>
            </div>
            <div id="pageFooter"></div>
        </main>
    </div>
    <script src="app.js?v=4"></script>
    <script>
// Ready-made offenses per severity: [category, remark]. The remark box can still be typed freely.
const OFFENSES = {
    Clear: [
        ['Good Standing', 'Good standing - no violations on record'],
        ['Good Standing', 'Student record initialized in clear behavioral standing.'],
        ['Good Standing', 'Community service completed - record cleared']
    ],
    Minor: [
        ['Attendance & Punctuality', 'Habitual tardiness or unexcused absence'],
        ['Policy Breaches', 'Incomplete uniform or ID not worn'],
        ['Policy Breaches', 'Disruptive or noisy behavior in class areas'],
        ['Policy Breaches', 'Using a mobile phone during class'],
        ['Campus Safety & Traffic', 'Traffic or parking violation on campus'],
        ['Property & Cleanliness', 'Littering or minor damage to school property']
    ],
    Major: [
        ['Academic Dishonesty', 'Cheating, plagiarism or exam misconduct'],
        ['Falsification', 'Falsifying documents, signatures or records'],
        ['Violence & Physical Harm', 'Fighting, bullying or physical harm to others'],
        ['Substance Abuse & Vices', 'Possession or use of prohibited substances'],
        ['Theft & Property Damage', 'Theft or serious damage to school property'],
        ['Gross Misconduct', 'Harassment, threats or other serious misconduct']
    ]
};
const typeEl = document.getElementById('violationType');
const presetEl = document.getElementById('offensePreset');
const descEl = document.getElementById('description');
let presetFilled = false;

function fillPresets() {
    presetEl.innerHTML = '<option value="">✍️ Other / type my own below</option>' +
        OFFENSES[typeEl.value].map((o, i) => `<option value="${i}">${esc(o[0])} - ${esc(o[1])}</option>`).join('');
    if (presetFilled) descEl.value = '';
    presetFilled = false;
}
typeEl.addEventListener('change', fillPresets);
presetEl.addEventListener('change', () => {
    const o = OFFENSES[typeEl.value][presetEl.value];
    if (o) { descEl.value = o[1]; presetFilled = true; } else { descEl.focus(); }
});
descEl.addEventListener('input', () => { presetFilled = false; });
fillPresets();


// Backend POST Connection: Save New Violation Infraction with Security Validation
document.getElementById('violationForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const studentId = document.getElementById('studentId').value.trim();
    const studentName = document.getElementById('studentName').value.trim();
    const course = document.getElementById('course').value.trim();
    const yearLevel = document.getElementById('yearLevel').value;
    const section = document.getElementById('section').value.trim();
    const violationType = typeEl.value;
    let description = descEl.value.trim();
    const preset = OFFENSES[violationType][presetEl.value];
    const category = preset ? preset[0] : (violationType === 'Clear' ? 'Good Standing' : 'Other');
    const timestamp = new Date().toLocaleString();

    // Offenses need details; Good Standing is auto-filled if left blank
    if (!description) {
        if (violationType === "Minor" || violationType === "Major") {
            showToast(`Add details or pick a common offense for a ${violationType} offense.`, 'error');
            presetEl.focus();
            return;
        }
        description = "Student record initialized in clear behavioral standing.";
    }

    const currentRecords = await (await fetch('database.php')).json();

    // Same ID with a different name = possible typo; ask before renaming every past log
    const existing = currentRecords.find(r => r.studentId === studentId);
    let renamed = false;
    if (existing && existing.studentName.toLowerCase() !== studentName.toLowerCase()) {
        const ok = await askConfirm({
            title: 'Name does not match this ID',
            body: `<p>Student ID <strong>${esc(studentId)}</strong> is already saved under another name.</p>
                <div class="data-comparison-container">
                    <div class="data-row"><span class="data-label">On file:</span> <strong class="highlight-red">${esc(existing.studentName)}</strong></div>
                    <div class="data-row"><span class="data-label">You typed:</span> <strong class="highlight-yellow">${esc(studentName)}</strong></div>
                </div>
                <p>Updating renames this student on all past records.</p>`,
            confirmLabel: 'Update name everywhere', cancelLabel: 'Cancel and review'
        });
        if (!ok) { document.getElementById('studentName').focus(); return; }
        currentRecords.forEach(r => { if (r.studentId === studentId) r.studentName = studentName; });
        renamed = true;
    }

    currentRecords.push({ studentId, studentName, course, yearLevel, section, violationType, category, description, timestamp });

    const response = await fetch('database.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(currentRecords)
    });
    const result = await response.json();
    if (result.success) {
        let photoNote = '';
        if (photoBlob) photoNote = (await uploadPhoto(studentId, photoBlob)) ? '\nPhoto uploaded.' : '\nThe photo could not be uploaded. Use Change photo on the profile page.';
        showToast((existing ? `Added to ${studentName}'s record: ${violationType === 'Clear' ? 'Good Standing' : violationType + ' offense'}.` : `Saved: ${studentName} (${violationType === 'Clear' ? 'Good Standing' : violationType + ' offense'}).`) + (renamed ? '\nName updated on all past records.' : '') + photoNote, 'success');
        this.reset();
        fillPresets();
        clearPhoto();
        noticeEl.hidden = true; saveBtn.textContent = '🔒 Save Entry'; autoFilled = false;
        if (idParam) setTimeout(() => { location.href = 'profile.php?from=students&id=' + encodeURIComponent(studentId); }, 1000);
    } else {
        showToast('Could not save the record. Please try again.', 'error');
    }
});


// Student photo: pick, shrink, preview (uploaded after the entry is saved)
let photoBlob = null;
const photoInput = document.getElementById('studentPhoto'), photoPreview = document.getElementById('photoPreview');
function clearPhoto() { photoBlob = null; photoInput.value = ''; photoPreview.src = 'images/default-avatar.png'; }
photoInput.addEventListener('change', async () => {
    const f = photoInput.files[0];
    if (!f) return clearPhoto();
    if (!f.type.startsWith('image/')) { clearPhoto(); return showToast('Please choose an image file (JPG or PNG).', 'error'); }
    try { photoBlob = await shrinkImage(f); photoPreview.src = URL.createObjectURL(photoBlob); }
    catch (e) { clearPhoto(); showToast('That picture could not be read. Try a different file.', 'error'); }
});

// Add an offense to a student who is already registered: type the ID (or arrive from a "+ Offense" / "Add Offense" button) and the details fill in
const idEl = document.getElementById('studentId'), nameEl = document.getElementById('studentName'), courseEl = document.getElementById('course');
const yearEl = document.getElementById('yearLevel'), sectionEl = document.getElementById('section');
const noticeEl = document.getElementById('existingNotice'), saveBtn = document.getElementById('saveBtn');
const idParam = new URLSearchParams(location.search).get('id') || '';
let autoFilled = false, lookupTimer = null;
[nameEl, courseEl, yearEl, sectionEl].forEach(el => el.addEventListener('input', () => { autoFilled = false; }));

async function lookupStudent() {
    const id = idEl.value.trim();
    const records = id ? await (await fetch('database.php')).json() : [];
    const recs = records.filter(r => r.studentId === id);
    if (!recs.length) {
        noticeEl.hidden = true; saveBtn.textContent = '🔒 Save Entry';
        if (autoFilled) {
            nameEl.value = courseEl.value = sectionEl.value = ''; yearEl.value = ''; autoFilled = false;
            if (!photoBlob) photoPreview.src = 'images/default-avatar.png';
        }
        return;
    }
    const p = profileOf(recs), off = recs.filter(r => r.violationType === 'Minor' || r.violationType === 'Major');
    const minor = off.filter(r => r.violationType === 'Minor').length;
    if (!nameEl.value || autoFilled) { nameEl.value = p.name; courseEl.value = p.course; yearEl.value = p.yearLevel; sectionEl.value = p.section; autoFilled = true; }
    await loadPhotos();
    if (!photoBlob) photoPreview.src = photoUrl(id);
    noticeEl.innerHTML = `<b>Student already registered:</b> ${esc(p.name)} has ${minor} Minor and ${off.length - minor} Major offense(s) on file. This entry will be <b>added to their record</b>, so there is no need to register again.`;
    noticeEl.hidden = false; saveBtn.textContent = '➕ Add to This Student';
}
idEl.addEventListener('input', () => { clearTimeout(lookupTimer); lookupTimer = setTimeout(lookupStudent, 300); });
idEl.addEventListener('change', lookupStudent);
if (idParam) { idEl.value = idParam; lookupStudent().then(() => typeEl.focus()); }
    </script>
</body>
</html>
