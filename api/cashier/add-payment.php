<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$controller->guard();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) $controller->json(400, ['success' => false, 'message' => 'Missing statement id.']);

try {
    $reference = $controller->addPayment($id, $controller->input());
    $controller->json(201, [
        'success'   => true,
        'message'   => 'Payment recorded.',
        'reference' => $reference,
    ]);
} catch (Throwable $e) {
    $controller->json(500, ['success' => false, 'message' => $e->getMessage()]);
}