<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$controller->guard();

$id = (int)($_GET['id'] ?? 0);
$chargeId = (int)($_GET['charge_id'] ?? 0);
if ($id <= 0 || $chargeId <= 0) $controller->json(400, ['success' => false, 'message' => 'Missing ids.']);

try {
    $controller->removeCharge($id, $chargeId);
    $controller->json(200, ['success' => true, 'message' => 'Charge removed.']);
} catch (Throwable $e) {
    $controller->json(500, ['success' => false, 'message' => 'Could not remove charge.']);
}