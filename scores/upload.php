<?php
// Student photo endpoint. GET = list of photos on the server, POST = save one photo as photos/<studentId>.jpg
header('Content-Type: application/json');
header('Cache-Control: no-store');
$dir = __DIR__ . '/photos';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

function safe_id($id) { return preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$id); }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $map = [];
    foreach (is_dir($dir) ? scandir($dir) : [] as $f) {
        if (preg_match('/^(.+)\.(jpe?g|png|webp)$/i', $f, $m)) {
            $map[$m[1]] = 'photos/' . rawurlencode($f) . '?v=' . filemtime($dir . '/' . $f);
        }
    }
    echo json_encode((object)$map);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = safe_id($_POST['studentId'] ?? '');
    if (($_POST['action'] ?? '') === 'delete') {   // remove this student's photo
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            if ($id !== '' && is_file("$dir/$id.$ext")) { @unlink("$dir/$id.$ext"); }
        }
        echo json_encode(['success' => $id !== '']);
        exit;
    }
    $file = $_FILES['photo'] ?? null;
    if ($id === '' || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No photo received.']);
        exit;
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $file['size'] > 4 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Please upload a JPG picture under 4 MB.']);
        exit;
    }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {   // replace any older photo of this student
        if (is_file("$dir/$id.$ext")) { @unlink("$dir/$id.$ext"); }
    }
    $dest = "$dir/$id.jpg";
    $ok = false;
    if (function_exists('imagecreatefromjpeg')) {   // re-encode so only real picture data is kept
        $img = @imagecreatefromjpeg($file['tmp_name']);
        if ($img) { $ok = imagejpeg($img, $dest, 88); imagedestroy($img); }
    }
    if (!$ok) { $ok = move_uploaded_file($file['tmp_name'], $dest); }
    echo json_encode(['success' => $ok]);
    exit;
}

echo json_encode(['success' => false]);
?>
