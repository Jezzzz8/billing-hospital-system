<?php
// api/nurses/patient-register.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/NurseController.php';

$controller = new NurseController($pdo);
$controller->registerPatient();