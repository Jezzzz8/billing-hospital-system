<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$controller->guard();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) $controller->json(400, ['success' => false, 'message' => 'Missing statement id.']);

try {
    $controller->syncChargesFromAdmission($id);
    $controller->json(200, ['success' => true, 'message' => 'Charges synced from admission.']);
} catch (Throwable $e) {
    $controller->json(500, ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()]);
}