<?php
// api/cashier/create-statement.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$controller->createStatement();