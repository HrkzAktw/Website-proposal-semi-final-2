<?php
// Centralized Data Endpoint File Handler
header('Content-Type: application/json');
header('Cache-Control: no-store');
$dataFile = 'database.json';

// Good Moral clearance print log (GET = list, POST = add one entry, DELETE = reset the count)
if (($_GET['type'] ?? '') === 'prints') {
    $printFile = 'prints.json';
    if (!file_exists($printFile)) file_put_contents($printFile, '[]');
    $prints = json_decode(file_get_contents($printFile), true) ?: [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        if (!empty($in['studentId'])) {
            $prints[] = [
                'studentId' => $in['studentId'],
                'studentName' => $in['studentName'] ?? '',
                'status' => $in['status'] ?? '',
                'timestamp' => date('m/d/Y, g:i:s A')
            ];
            file_put_contents($printFile, json_encode($prints, JSON_PRETTY_PRINT), LOCK_EX);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        file_put_contents($printFile, '[]', LOCK_EX);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode($prints);
    exit;
}

// Automatically inject mock prototype records if the file doesn't exist yet
if (!file_exists($dataFile)) {
    $seedData = [
        ["studentId" => "2024-0002", "studentName" => "Dela Cruz, Juan A.", "violationType" => "Minor", "description" => "Caught wearing incomplete uniform during morning inspection.", "timestamp" => "10/12/2026, 8:30 AM"],
        ["studentId" => "2024-0003", "studentName" => "Santos, Maria Clara L.", "violationType" => "Minor", "description" => "Unexcused absence during department orientation assembly.", "timestamp" => "09/15/2026, 1:15 PM"],
        ["studentId" => "2024-0003", "studentName" => "Santos, Maria Clara L.", "violationType" => "Minor", "description" => "Loitering and causing loud disturbance near examination rooms.", "timestamp" => "10/05/2026, 3:45 PM"],
        ["studentId" => "2024-0004", "studentName" => "Rizal, Jose P.", "violationType" => "Major", "description" => "Gross misconduct: Caught forging a faculty member's signature on a department clearance document.", "timestamp" => "10/10/2026, 11:00 AM"]
    ];
    file_put_contents($dataFile, json_encode($seedData, JSON_PRETTY_PRINT));
}

// Fetch all saved data from the flat-file database repository
$currentData = json_decode(file_get_contents($dataFile), true);
$method = $_SERVER['REQUEST_METHOD'];

// ROUTE 1: GET REQUEST (Fetch all database files to read them in the client browser)
if ($method === 'GET') {
    echo json_encode($currentData);
    exit;
}

// ROUTE 2: POST REQUEST (Add a new violation infraction log to central storage)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $currentData = $input;
        file_put_contents($dataFile, json_encode($currentData, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true, "message" => "Violation logged on server backend."]);
    }
    exit;
}

// ROUTE 3: DELETE REQUEST (Purge/Clear a student disciplinary history archive file)
if ($method === 'DELETE' && isset($_GET['studentId'])) {
    $targetId = $_GET['studentId'];
    $filteredData = array_filter($currentData, function($record) use ($targetId) {
        return $record['studentId'] !== $targetId;
    });
    file_put_contents($dataFile, json_encode(array_values($filteredData), JSON_PRETTY_PRINT));
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $targetId);   // remove the student's photo too
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $photo = __DIR__ . "/photos/$safe.$ext";
        if (is_file($photo)) { @unlink($photo); }
    }
    echo json_encode(["success" => true, "message" => "Records purged from central storage."]);
    exit;
}
?>
