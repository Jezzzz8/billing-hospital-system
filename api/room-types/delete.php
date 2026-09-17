<?php


require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../controllers/RoomTypeController.php';

(new RoomTypeController($pdo))->delete();