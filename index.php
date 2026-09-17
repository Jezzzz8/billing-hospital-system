<?php


session_start();
require_once __DIR__ . '/config/config.php';

if (empty($_SESSION['user'])) {
    require __DIR__ . '/views/login.php';
    exit;
}


$currentUser = $_SESSION['user'];
$fullName    = trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$roleId      = (int)$currentUser['role_id'];


$defaultPage = match ($roleId) {
    1 => 'dashboard',
    2 => 'doctor',
    3 => 'nurse',
    4 => 'cashier',
    default => 'dashboard',
};

$page = $_GET['page'] ?? $defaultPage;


$allowed = [
    1 => [
        
        'dashboard',

        
        'users', 'users-create',
        'doctors', 'patients',

        
        'billing', 'reports',

        
        'gender',
        'role',
        'room-status',
        'admission-status',
        'billing-status',
        'payment-type',
        'specialization',
        'diagnosis',
        'charge-category',
        'charge-item',
        'room-type',
        'room',
    ],
    2 => [
        'doctor',
        'doctor-patients',
        'doctor-consultations',
        'doctor-diagnosis-record',
        'doctor-service-requests',
        'doctor-discharge-ready',
    ],
    3 => [
        'nurse',
        'nurse-patient-search',
        'nurse-patient-register',
        'nurse-admission-create',
        'nurse-admissions',
        'nurse-room-assignments',
        'nurse-room-board',
        'nurse-discharge-ready',
        'nurse-discharge-records',
    ],
    4 => [
        'cashier',
        'cashier-payment-counter',
        'cashier-statements',
        'cashier-payments',
        'cashier-reports',
        'cashier-receipt',
    ],
];

if (!in_array($page, $allowed[$roleId] ?? [], true)) {
    http_response_code(403);
    echo 'Access denied. Requested page: ' . htmlspecialchars($page);
    exit;
}


$layout = match ($roleId) {
    1 => 'admin',
    2 => 'doctor',
    3 => 'nurse',
    4 => 'cashier',
    default => 'admin',
};





$contentFile = null;

if ($roleId === 1) {
    $contentFile = __DIR__ . '/views/admin/' . $page . '.php';

} elseif ($roleId === 2) {
    if ($page === 'doctor') {
        $contentFile = __DIR__ . '/views/doctor.php';
    } elseif (str_starts_with($page, 'doctor-')) {
        $viewName = substr($page, 7);
        $contentFile = __DIR__ . '/views/doctor/' . $viewName . '.php';
    }

} elseif ($roleId === 3) {
    if ($page === 'nurse') {
        $contentFile = __DIR__ . '/views/nurse.php';
    } elseif (str_starts_with($page, 'nurse-')) {
        $viewName = substr($page, 6);
        $contentFile = __DIR__ . '/views/nurse/' . $viewName . '.php';
    }

} elseif ($roleId === 4) {
    
    if ($page === 'cashier') {
        $contentFile = __DIR__ . '/views/cashier.php';
    } elseif (str_starts_with($page, 'cashier-')) {
        $viewName = substr($page, 8); 
        $contentFile = __DIR__ . '/views/cashier/' . $viewName . '.php';
    }

} else {
    $contentFile = __DIR__ . '/views/' . $page . '.php';
}

if (!$contentFile || !file_exists($contentFile)) {
    http_response_code(404);
    echo 'Content file not found: ' . htmlspecialchars($contentFile ?? 'unknown');
    exit;
}


$pageTitle = ucwords(str_replace(['-', '/'], [' ', ' · '], $page));



$jsCandidates = [
    '/assets/js/master/' . $page . '.js',
    '/assets/js/'        . $page . '.js',
];

$pageScript = null;
foreach ($jsCandidates as $relPath) {
    if (file_exists(__DIR__ . $relPath)) {
        $pageScript = BASE_URL . $relPath;
        break;
    }
}


$currentPage = $page;


require_once __DIR__ . '/config/connection.php';


require __DIR__ . '/views/layouts/' . $layout . '.php';