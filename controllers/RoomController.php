<?php


class RoomController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    
    
    

    
    public function getAll(): array
    {
        return $this->pdo->query(
            'SELECT r.room_id, r.room_type_id, r.status_id, r.room_number,
                    r.floor_level, r.building, r.is_active,
                    rt.room_type_name,
                    rs.status_name, rs.color_code
             FROM `room` r
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             ORDER BY r.room_id'
        )->fetchAll();
    }

    
    public function getRoomTypes(): array
    {
        return $this->pdo->query(
            'SELECT room_type_id, room_type_name
             FROM `room_type`
             ORDER BY room_type_name'
        )->fetchAll();
    }

    
    public function getStatuses(): array
    {
        return $this->pdo->query(
            'SELECT status_id, status_name, color_code
             FROM `room_status`
             ORDER BY status_name'
        )->fetchAll();
    }

    
    
    

    public function create(): void
    {
        $this->guard();

        $data   = $this->input();
        $errors = $this->validate($data);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        if ($this->roomNumberExists($data['room_number'], $data['building'] ?? null, null)) {
            $this->json(409, ['success' => false, 'errors' => ['room_number' => 'This room number already exists in this building.']]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `room`
                (room_type_id, status_id, room_number, floor_level, building, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            (int)$data['room_type_id'],
            (int)$data['status_id'],
            $data['room_number'],
            (isset($data['floor_level']) && $data['floor_level'] !== '') ? (int)$data['floor_level'] : null,
            !empty($data['building']) ? $data['building'] : null,
        ]);

        $this->json(201, [
            'success' => true,
            'message' => 'Room created successfully.',
            'id'      => (int)$this->pdo->lastInsertId(),
        ]);
    }

    
    
    

    public function update(): void
    {
        $this->guard();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing room id.']);

        $data   = $this->input();
        $errors = $this->validate($data);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        if ($this->roomNumberExists($data['room_number'], $data['building'] ?? null, $id)) {
            $this->json(409, ['success' => false, 'errors' => ['room_number' => 'This room number already exists in this building.']]);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `room`
             SET room_type_id = ?, status_id = ?, room_number = ?, floor_level = ?, building = ?
             WHERE room_id = ?'
        );
        $stmt->execute([
            (int)$data['room_type_id'],
            (int)$data['status_id'],
            $data['room_number'],
            (isset($data['floor_level']) && $data['floor_level'] !== '') ? (int)$data['floor_level'] : null,
            !empty($data['building']) ? $data['building'] : null,
            $id,
        ]);

        $this->json(200, ['success' => true, 'message' => 'Room updated successfully.']);
    }

    
    
    

    public function toggleActive(): void
    {
        $this->guard();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing room id.']);

        $stmt = $this->pdo->prepare('SELECT is_active FROM `room` WHERE room_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) $this->json(404, ['success' => false, 'message' => 'Room not found.']);

        $newState = ((int)$row['is_active'] === 1) ? 0 : 1;

        
        if ($newState === 0) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `room_assignment`
                 WHERE room_id = ? AND is_active = 1 AND end_datetime IS NULL'
            );
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $this->json(409, [
                    'success' => false,
                    'message' => 'Cannot archive — this room is currently assigned to an active admission.',
                ]);
            }
        }

        $stmt = $this->pdo->prepare('UPDATE `room` SET is_active = ? WHERE room_id = ?');
        $stmt->execute([$newState, $id]);

        $this->json(200, [
            'success'   => true,
            'message'   => $newState === 1 ? 'Room reactivated.' : 'Room archived.',
            'is_active' => $newState,
        ]);
    }

    
    
    

    public function delete(): void
    {
        $this->guard();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing room id.']);

        
        $stmt = $this->pdo->prepare('SELECT is_active FROM `room` WHERE room_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) $this->json(404, ['success' => false, 'message' => 'Room not found.']);

        if ((int)$row['is_active'] === 1) {
            $this->json(409, ['success' => false, 'message' => 'Archive the room first before deleting.']);
        }

        
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `room_assignment` WHERE room_id = ?');
        $stmt->execute([$id]);
        $refCount = (int)$stmt->fetchColumn();

        if ($refCount > 0) {
            $this->json(409, [
                'success'    => false,
                'message'    => 'This item is currently being used and cannot be deleted.',
                'references' => $refCount,
            ]);
        }

        $stmt = $this->pdo->prepare('DELETE FROM `room` WHERE room_id = ?');
        $stmt->execute([$id]);

        $this->json(200, ['success' => true, 'message' => 'Room permanently deleted.']);
    }

    
    
    

    private function guard(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405, ['success' => false, 'message' => 'Method not allowed.']);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user']) || (int)$_SESSION['user']['role_id'] !== 1) {
            $this->json(403, ['success' => false, 'message' => 'Access denied.']);
        }
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: $_POST;
    }

    private function validate(array $data): array
    {
        $errors = [];

        
        $typeId = (int)($data['room_type_id'] ?? 0);
        if ($typeId <= 0) {
            $errors['room_type_id'] = 'Please select a room type.';
        } else {
            $stmt = $this->pdo->prepare('SELECT room_type_id FROM `room_type` WHERE room_type_id = ? LIMIT 1');
            $stmt->execute([$typeId]);
            if (!$stmt->fetch()) {
                $errors['room_type_id'] = 'Selected room type is not valid.';
            }
        }

        
        $statusId = (int)($data['status_id'] ?? 0);
        if ($statusId <= 0) {
            $errors['status_id'] = 'Please select a status.';
        } else {
            $stmt = $this->pdo->prepare('SELECT status_id FROM `room_status` WHERE status_id = ? LIMIT 1');
            $stmt->execute([$statusId]);
            if (!$stmt->fetch()) {
                $errors['status_id'] = 'Selected status is not valid.';
            }
        }

        
        $num = trim((string)($data['room_number'] ?? ''));
        if ($num === '') {
            $errors['room_number'] = 'Room number is required.';
        } elseif (strlen($num) > 50) {
            $errors['room_number'] = 'Room number must be 50 characters or fewer.';
        }

        return $errors;
    }

    private function roomNumberExists(string $number, ?string $building, ?int $ignoreId): bool
    {
        $sql  = 'SELECT room_id FROM `room` WHERE room_number = ?';
        $args = [$number];

        if (!empty($building)) {
            $sql .= ' AND building = ?';
            $args[] = $building;
        } else {
            $sql .= ' AND (building IS NULL OR building = "")';
        }

        if ($ignoreId !== null) {
            $sql .= ' AND room_id <> ?';
            $args[] = $ignoreId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return (bool)$stmt->fetch();
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}