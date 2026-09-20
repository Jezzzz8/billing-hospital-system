<?php

require_once __DIR__ . '/BillingHook.php';

class NurseController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    private function statusId(string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT status_id FROM `admission_status` WHERE status_name = ? LIMIT 1');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn();
    }

    private function activeStatusIds(): array
    {
        $ids = [];
        foreach (['Admitted', 'Transferred', 'Ready for Discharge'] as $n) {
            $id = $this->statusId($n);
            if ($id > 0) $ids[] = $id;
        }
        return $ids ?: [1, 3, 4];
    }

    private function readyStatusId(): int
    {
        return $this->statusId('Ready for Discharge');
    }

    private function admittedStatusId(): int
    {
        return $this->statusId('Admitted');
    }

    private function dischargedStatusId(): int
    {
        return $this->statusId('Discharged');
    }

    public function getDashboardStats(): array
    {
        $activeIds = $this->activeStatusIds();
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `admission` WHERE status_id IN ($placeholders)"
        );
        $stmt->execute($activeIds);
        $activeAdmissions = (int)$stmt->fetchColumn();

        $availableRooms = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM `room` r
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE r.is_active = 1 AND rs.status_name = "Available"'
        )->fetchColumn();

        $occupiedRooms = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM `room` r
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE r.is_active = 1 AND rs.status_name = "Occupied"'
        )->fetchColumn();

        $readyId = $this->readyStatusId();
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `admission` WHERE status_id = ?');
        $stmt->execute([$readyId]);
        $readyForDischarge = (int)$stmt->fetchColumn();

        return [
            'active_admissions'   => $activeAdmissions,
            'available_rooms'     => $availableRooms,
            'occupied_rooms'      => $occupiedRooms,
            'ready_for_discharge' => $readyForDischarge,
        ];
    }

    public function getActiveAdmissions(): array
    {
        $activeIds = $this->activeStatusIds();
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT a.admission_id, a.admission_datetime, a.chief_complaint,
                    p.first_name, p.last_name, p.patient_id,
                    ast.status_name, ast.color_code AS status_color,
                    r.room_id, r.room_number,
                    rt.room_type_name,
                    rs.status_name AS room_status_name, rs.color_code AS room_status_color
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
             LEFT JOIN `room_assignment` ra ON ra.admission_id = a.admission_id AND ra.is_active = 1
             LEFT JOIN `room` r ON r.room_id = ra.room_id
             LEFT JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             LEFT JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE a.status_id IN ($placeholders)
             ORDER BY a.admission_datetime DESC
             LIMIT 10"
        );
        $stmt->execute($activeIds);
        return $stmt->fetchAll();
    }

    public function getRoomsNeedingAttention(): array
    {
        return $this->pdo->query(
            'SELECT r.room_id, r.room_number, rs.status_name, rs.color_code
             FROM `room` r
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE r.is_active = 1
               AND rs.status_name IN ("Maintenance", "Reserved")
             ORDER BY r.room_number'
        )->fetchAll();
    }

    public function searchPatients(string $query): array
    {
        $activeIds = $this->activeStatusIds();
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

        $sql = "SELECT p.patient_id, p.first_name, p.last_name, p.birth_date,
                       p.contact_number, p.email, p.address,
                       g.gender_name,
                       a.admission_id, a.admission_datetime,
                       ast.status_name AS admission_status_name, ast.color_code AS admission_status_color
                FROM `patient` p
                INNER JOIN `gender` g ON g.gender_id = p.gender_id
                LEFT JOIN `admission` a ON a.patient_id = p.patient_id AND a.status_id IN ($placeholders)
                LEFT JOIN `admission_status` ast ON ast.status_id = a.status_id
                WHERE p.is_active = 1
                  AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.contact_number LIKE ? OR p.email LIKE ?)
                ORDER BY p.last_name, p.first_name
                LIMIT 50";

        $like = '%' . $query . '%';
        $params = array_merge($activeIds, [$like, $like, $like, $like]);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPatientDetails(int $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, g.gender_name
             FROM `patient` p
             INNER JOIN `gender` g ON g.gender_id = p.gender_id
             WHERE p.patient_id = ? LIMIT 1'
        );
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        if (!$patient) return null;

        $activeIds = $this->activeStatusIds();
        $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT a.*, ast.status_name, ast.color_code
             FROM `admission` a
             INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
             WHERE a.patient_id = ? AND a.status_id IN ($placeholders)
             ORDER BY a.admission_datetime DESC LIMIT 1"
        );
        $stmt->execute(array_merge([$patientId], $activeIds));
        $patient['current_admission'] = $stmt->fetch() ?: null;

        return $patient;
    }

    public function getPatientByAdmission(int $admissionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.admission_id, a.status_id, a.admission_datetime, a.chief_complaint,
                    a.admission_type, a.notes, a.total_room_transfers,
                    p.patient_id, p.first_name, p.last_name, p.birth_date,
                    p.contact_number, p.email, p.address,
                    g.gender_name,
                    ast.status_name, ast.color_code
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             INNER JOIN `gender` g ON g.gender_id = p.gender_id
             INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
             WHERE a.admission_id = ? LIMIT 1'
        );
        $stmt->execute([$admissionId]);
        return $stmt->fetch() ?: null;
    }

    public function registerPatient(): void
    {
        $data = $this->input();
        $errors = $this->validatePatient($data);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `patient`
                    (gender_id, first_name, last_name, birth_date, contact_number, address, email,
                     emergency_contact, emergency_contact_number, medical_history, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                (int)$data['gender_id'],
                $data['first_name'],
                $data['last_name'],
                !empty($data['birth_date']) ? $data['birth_date'] : null,
                !empty($data['contact_number']) ? $data['contact_number'] : null,
                !empty($data['address']) ? $data['address'] : null,
                !empty($data['email']) ? $data['email'] : null,
                !empty($data['emergency_contact']) ? $data['emergency_contact'] : null,
                !empty($data['emergency_contact_number']) ? $data['emergency_contact_number'] : null,
                !empty($data['medical_history']) ? $data['medical_history'] : null,
            ]);

            $patientId = (int)$this->pdo->lastInsertId();

            $this->json(201, [
                'success'    => true,
                'message'    => 'Patient registered successfully.',
                'patient_id' => $patientId,
            ]);
        } catch (Throwable $e) {
            error_log('[NurseController::registerPatient] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not register patient.']);
        }
    }

    public function createAdmission(): void
    {
        $data = $this->input();
        $errors = $this->validateAdmission($data);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $this->pdo->beginTransaction();

            $patientId = (int)$data['patient_id'];
            $activeIds = $this->activeStatusIds();
            $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT admission_id FROM `admission`
                 WHERE patient_id = ? AND status_id IN ($placeholders) LIMIT 1"
            );
            $stmt->execute(array_merge([$patientId], $activeIds));
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                $this->json(409, ['success' => false, 'message' => 'This patient already has an active admission.']);
            }

            $statusId = (int)($data['admission_status_id'] ?? 0);
            if ($statusId <= 0) $statusId = $this->admittedStatusId();
            if ($statusId <= 0) $statusId = 1;

            $stmt = $this->pdo->prepare(
                'INSERT INTO `admission`
                    (patient_id, status_id, admission_datetime, chief_complaint,
                     admission_type, total_room_transfers, admitted_by_user_id, notes)
                 VALUES (?, ?, NOW(), ?, ?, 0, ?, ?)'
            );
            $stmt->execute([
                $patientId,
                $statusId,
                !empty($data['chief_complaint']) ? $data['chief_complaint'] : null,
                !empty($data['admission_type']) ? $data['admission_type'] : 'Emergency',
                (int)$_SESSION['user']['user_id'],
                !empty($data['admission_notes']) ? $data['admission_notes'] : null,
            ]);

            $admissionId = (int)$this->pdo->lastInsertId();

            if (!empty($data['room_id'])) {
                $this->assignRoom($admissionId, (int)$data['room_id'], $data);
            }

            $doctorIds   = $data['doctor_ids']   ?? [];
            $doctorRoles = $data['doctor_roles'] ?? [];

            if (is_array($doctorIds) && $doctorIds) {
                $this->saveAdmissionDoctors($admissionId, $doctorIds, $doctorRoles);
                BillingHook::emit($this->pdo, $admissionId);
            }

            $this->pdo->commit();

            try { BillingHook::flushDeferred(); } catch (Throwable $e) {
                error_log('[NurseController::createAdmission::flushDeferred] ' . $e->getMessage());
            }

            $this->json(201, [
                'success'      => true,
                'message'      => 'Patient admitted successfully.',
                'admission_id' => $admissionId,
            ]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[NurseController::createAdmission] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not create admission.']);
        }
    }

    public function getAdmissions(): array
    {
        return $this->pdo->query(
            'SELECT a.admission_id, a.admission_datetime, a.discharge_datetime,
                    a.chief_complaint, a.admission_type, a.total_room_transfers,
                    a.status_id AS admission_status_id,
                    p.patient_id, p.first_name, p.last_name,
                    ast.status_name AS admission_status_name, ast.color_code AS admission_status_color,
                    r.room_id, r.room_number,
                    rt.room_type_name,
                    rs.status_name AS room_status_name, rs.color_code AS room_status_color
            FROM `admission` a
            INNER JOIN `patient` p ON p.patient_id = a.patient_id
            INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
            LEFT JOIN `room_assignment` ra ON ra.admission_id = a.admission_id AND ra.is_active = 1
            LEFT JOIN `room` r ON r.room_id = ra.room_id
            LEFT JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
            LEFT JOIN `room_status` rs ON rs.status_id = r.status_id
            ORDER BY a.admission_datetime DESC'
        )->fetchAll();
    }

    public function getAvailableRooms(?int $roomTypeId = null): array
    {
        $sql = 'SELECT r.room_id, r.room_number,
                    rt.room_type_id, rt.room_type_name, rt.rate_per_day, rt.capacity,
                    rs.status_name, rs.color_code,
                    fl.floor_level_id,
                    fl.floor_level_name,
                    fl.floor_level_name AS floor_level,
                    b.building_id,
                    b.building_name,
                    (
                        SELECT ra.admission_id
                        FROM `room_assignment` ra
                        WHERE ra.room_id = r.room_id
                          AND ra.is_active = 1
                          AND ra.end_datetime IS NULL
                        ORDER BY ra.start_datetime DESC
                        LIMIT 1
                    ) AS occupied_by_admission_id
                FROM `room` r
                INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
                INNER JOIN `room_status` rs ON rs.status_id = r.status_id
                INNER JOIN `floor_level` fl ON fl.floor_level_id = r.floor_level_id
                INNER JOIN `building` b ON b.building_id = fl.building_id
                WHERE r.is_active = 1 AND rs.status_name IN ("Available", "Occupied")';

        $params = [];

        if ($roomTypeId) {
            $sql .= ' AND rt.room_type_id = ?';
            $params[] = $roomTypeId;
        }

        $sql .= ' ORDER BY
                    CASE WHEN rs.status_name = "Available" THEN 0 ELSE 1 END,
                    b.building_name, fl.floor_level_name, rt.room_type_name, r.room_number';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRoomsByType(): array
    {
        return $this->pdo->query(
            'SELECT rt.room_type_id, rt.room_type_name, rt.rate_per_day,
                    COUNT(r.room_id) AS total_rooms,
                    SUM(CASE WHEN rs.status_name = "Available" THEN 1 ELSE 0 END) AS available_rooms
             FROM `room_type` rt
             LEFT JOIN `room` r ON r.room_type_id = rt.room_type_id AND r.is_active = 1
             LEFT JOIN `room_status` rs ON rs.status_id = r.status_id
             GROUP BY rt.room_type_id
             ORDER BY rt.room_type_name'
        )->fetchAll();
    }

    public function getAllRooms(): array
    {
        return $this->pdo->query(
            'SELECT r.room_id, r.room_number, r.is_active,
                    rt.room_type_id, rt.room_type_name, rt.rate_per_day, rt.capacity,
                    rs.status_id, rs.status_name, rs.color_code,
                    fl.floor_level_id, fl.floor_level_name,
                    b.building_id, b.building_name
             FROM `room` r
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             INNER JOIN `floor_level` fl ON fl.floor_level_id = r.floor_level_id
             INNER JOIN `building` b ON b.building_id = fl.building_id
             WHERE r.is_active = 1
             ORDER BY b.building_name, fl.floor_level_name, rt.room_type_name, r.room_number'
        )->fetchAll();
    }

    public function getRoomAssignments(?int $admissionId = null): array
    {
        $sql = 'SELECT ra.room_assignment_id, ra.admission_id, ra.start_datetime, ra.end_datetime,
                       ra.daily_rate_at_assignment, ra.transfer_reason, ra.is_active,
                       p.patient_id, p.first_name, p.last_name,
                       r.room_id, r.room_number,
                       rt.room_type_name, rt.rate_per_day,
                       rs.status_name AS room_status_name, rs.color_code AS room_status_color,
                       a.admission_datetime, a.chief_complaint,
                       ast.status_name AS admission_status_name,
                       fl.floor_level_name, b.building_name
                FROM `room_assignment` ra
                INNER JOIN `admission` a ON a.admission_id = ra.admission_id
                INNER JOIN `patient` p ON p.patient_id = a.patient_id
                INNER JOIN `room` r ON r.room_id = ra.room_id
                INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
                INNER JOIN `room_status` rs ON rs.status_id = r.status_id
                INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
                INNER JOIN `floor_level` fl ON fl.floor_level_id = r.floor_level_id
                INNER JOIN `building` b ON b.building_id = fl.building_id';

        $params = [];

        if ($admissionId) {
            $sql .= ' WHERE ra.admission_id = ?';
            $params[] = $admissionId;
        }

        $sql .= ' ORDER BY ra.start_datetime DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRoomTransferHistory(int $admissionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.room_assignment_id, ra.start_datetime, ra.end_datetime,
                    ra.daily_rate_at_assignment, ra.transfer_reason,
                    r.room_number, rt.room_type_name,
                    fl.floor_level_name, b.building_name
             FROM `room_assignment` ra
             INNER JOIN `room` r ON r.room_id = ra.room_id
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             INNER JOIN `floor_level` fl ON fl.floor_level_id = r.floor_level_id
             INNER JOIN `building` b ON b.building_id = fl.building_id
             WHERE ra.admission_id = ?
             ORDER BY ra.start_datetime ASC'
        );
        $stmt->execute([$admissionId]);
        return $stmt->fetchAll();
    }

    public function assignRoom(int $admissionId, int $roomId, array $data = []): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT room_assignment_id FROM `room_assignment`
             WHERE admission_id = ? AND room_id = ? AND is_active = 1 AND end_datetime IS NULL
             LIMIT 1'
        );
        $stmt->execute([$admissionId, $roomId]);
        if ($stmt->fetch()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT r.room_id, rt.rate_per_day
             FROM `room` r
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             WHERE r.room_id = ? AND r.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room) {
            throw new Exception('Room is not available.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT room_assignment_id FROM `room_assignment`
             WHERE room_id = ?
               AND is_active = 1
               AND end_datetime IS NULL
               AND admission_id <> ?
             LIMIT 1'
        );
        $stmt->execute([$roomId, $admissionId]);
        if ($stmt->fetch()) {
            throw new Exception('This room is already occupied by another patient.');
        }

        $rate = (float)$room['rate_per_day'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO `room_assignment`
                (admission_id, room_id, start_datetime, end_datetime,
                 daily_rate_at_assignment, transfer_reason, transferred_by_user_id, is_active)
             VALUES (?, ?, NOW(), NULL, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $admissionId,
            $roomId,
            $rate,
            !empty($data['transfer_reason']) ? $data['transfer_reason'] : null,
            (int)$_SESSION['user']['user_id'],
        ]);

        $stmt = $this->pdo->prepare(
            'UPDATE `room` SET status_id = (SELECT status_id FROM `room_status` WHERE status_name = "Occupied" LIMIT 1)
             WHERE room_id = ?'
        );
        $stmt->execute([$roomId]);

        BillingHook::emit($this->pdo, $admissionId);
    }

    public function assignRoomApi(): void
    {
        $data = $this->input();
        $admissionId = (int)($data['admission_id'] ?? 0);
        $roomId = (int)($data['room_id'] ?? 0);

        if ($admissionId <= 0 || $roomId <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Admission and room are required.']);
        }

        try {
            $this->pdo->beginTransaction();

            $activeIds = $this->activeStatusIds();
            $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT admission_id FROM `admission`
                 WHERE admission_id = ? AND status_id IN ($placeholders) LIMIT 1"
            );
            $stmt->execute(array_merge([$admissionId], $activeIds));
            if (!$stmt->fetch()) {
                $this->pdo->rollBack();
                $this->json(404, ['success' => false, 'message' => 'Active admission not found.']);
            }

            $stmt = $this->pdo->prepare(
                'SELECT room_assignment_id, room_id
                 FROM `room_assignment`
                 WHERE admission_id = ? AND is_active = 1 AND end_datetime IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $currentAssignment = $stmt->fetch();

            if ($currentAssignment) {
                $stmt = $this->pdo->prepare(
                    'UPDATE `room_assignment`
                     SET is_active = 0, end_datetime = NOW()
                     WHERE room_assignment_id = ?'
                );
                $stmt->execute([$currentAssignment['room_assignment_id']]);

                $stmt = $this->pdo->prepare(
                    'UPDATE `room` SET status_id = (SELECT status_id FROM `room_status` WHERE status_name = "Available" LIMIT 1)
                     WHERE room_id = ?'
                );
                $stmt->execute([$currentAssignment['room_id']]);
            }

            $this->assignRoom($admissionId, $roomId, $data);

            $this->pdo->commit();
            try { BillingHook::flushDeferred(); } catch (Throwable $e) {
                error_log('[NurseController::assignRoomApi::flushDeferred] ' . $e->getMessage());
            }
            $this->json(200, ['success' => true, 'message' => 'Room assigned successfully.']);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[NurseController::assignRoomApi] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => $e->getMessage() ?: 'Could not assign room.']);
        }
    }

    public function transferRoom(): void
    {
        $data = $this->input();
        $admissionId = (int)($data['admission_id'] ?? 0);
        $newRoomId = (int)($data['new_room_id'] ?? 0);

        if ($admissionId <= 0 || $newRoomId <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Admission and new room are required.']);
        }

        try {
            $this->pdo->beginTransaction();

            $activeIds = $this->activeStatusIds();
            $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT admission_id FROM `admission`
                 WHERE admission_id = ? AND status_id IN ($placeholders) LIMIT 1"
            );
            $stmt->execute(array_merge([$admissionId], $activeIds));
            if (!$stmt->fetch()) {
                $this->pdo->rollBack();
                $this->json(404, ['success' => false, 'message' => 'Active admission not found.']);
            }

            $stmt = $this->pdo->prepare(
                'SELECT room_assignment_id, room_id
                 FROM `room_assignment`
                 WHERE admission_id = ? AND is_active = 1 AND end_datetime IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $currentAssignment = $stmt->fetch();

            if ($currentAssignment) {
                $stmt = $this->pdo->prepare(
                    'UPDATE `room_assignment`
                     SET is_active = 0, end_datetime = NOW(), transfer_reason = ?
                     WHERE room_assignment_id = ?'
                );
                $stmt->execute([
                    !empty($data['transfer_reason']) ? $data['transfer_reason'] : 'Room transfer',
                    $currentAssignment['room_assignment_id'],
                ]);

                $newStatus = !empty($data['set_old_room_maintenance']) ? 'Maintenance' : 'Available';
                $stmt = $this->pdo->prepare(
                    'UPDATE `room` SET status_id = (SELECT status_id FROM `room_status` WHERE status_name = ? LIMIT 1)
                     WHERE room_id = ?'
                );
                $stmt->execute([$newStatus, $currentAssignment['room_id']]);
            }

            $this->assignRoom($admissionId, $newRoomId, $data);

            $stmt = $this->pdo->prepare(
                'UPDATE `admission` SET total_room_transfers = total_room_transfers + 1
                 WHERE admission_id = ?'
            );
            $stmt->execute([$admissionId]);

            BillingHook::emit($this->pdo, $admissionId);

            $this->pdo->commit();
            try { BillingHook::flushDeferred(); } catch (Throwable $e) {
                error_log('[NurseController::transferRoom::flushDeferred] ' . $e->getMessage());
            }
            $this->json(200, ['success' => true, 'message' => 'Room transfer completed successfully.']);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[NurseController::transferRoom] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => $e->getMessage() ?: 'Could not transfer room.']);
        }
    }

    public function getDoctors(): array
    {
        return $this->pdo->query(
            'SELECT d.doctor_id, d.consultation_fee,
                    u.first_name, u.last_name
             FROM `doctor` d
             INNER JOIN `user` u ON u.user_id = d.user_id
             WHERE d.is_active = 1 AND u.is_active = 1
             ORDER BY u.last_name, u.first_name'
        )->fetchAll();
    }

    public function getAdmissionDoctors(int $admissionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ad.admission_doctor_id, ad.doctor_id, ad.doctor_role,
                    ad.consultation_fee_charged, ad.assigned_datetime,
                    u.first_name, u.last_name
             FROM `admission_doctor` ad
             INNER JOIN `doctor` d ON d.doctor_id = ad.doctor_id
             INNER JOIN `user` u ON u.user_id = d.user_id
             WHERE ad.admission_id = ? AND ad.ended_datetime IS NULL
             ORDER BY ad.assigned_datetime'
        );
        $stmt->execute([$admissionId]);
        return $stmt->fetchAll();
    }

    private function saveAdmissionDoctors(int $admissionId, array $doctorIds, array $doctorRoles): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO `admission_doctor`
                (admission_id, doctor_id, doctor_role, assigned_datetime,
                 ended_datetime, consultation_fee_charged)
             VALUES (?, ?, ?, NOW(), NULL, ?)'
        );

        $feeLookup = $this->pdo->prepare(
            'SELECT consultation_fee FROM `doctor` WHERE doctor_id = ? LIMIT 1'
        );

        $exists = $this->pdo->prepare(
            'SELECT admission_doctor_id FROM `admission_doctor`
             WHERE admission_id = ? AND doctor_id = ? AND ended_datetime IS NULL
             LIMIT 1'
        );

        foreach ($doctorIds as $doctorId) {
            $doctorId = (int)$doctorId;
            if ($doctorId <= 0) continue;

            $exists->execute([$admissionId, $doctorId]);
            if ($exists->fetch()) continue;

            $role = $doctorRoles[$doctorId] ?? 'Attending';

            $feeLookup->execute([$doctorId]);
            $fee = (float)($feeLookup->fetchColumn() ?: 0);

            $insert->execute([$admissionId, $doctorId, $role, $fee]);
        }
    }

    public function assignDoctorApi(): void
    {
        $data = $this->input();
        $admissionId = (int)($data['admission_id'] ?? 0);
        $doctorId    = (int)($data['doctor_id'] ?? 0);
        $role        = !empty($data['doctor_role']) ? $data['doctor_role'] : 'Attending';

        if ($admissionId <= 0 || $doctorId <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Admission and doctor are required.']);
        }

        try {
            $this->pdo->beginTransaction();

            $activeIds = $this->activeStatusIds();
            $placeholders = implode(',', array_fill(0, count($activeIds), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT admission_id FROM `admission`
                 WHERE admission_id = ? AND status_id IN ($placeholders) LIMIT 1"
            );
            $stmt->execute(array_merge([$admissionId], $activeIds));
            if (!$stmt->fetch()) {
                $this->pdo->rollBack();
                $this->json(404, ['success' => false, 'message' => 'Active admission not found.']);
            }

            $stmt = $this->pdo->prepare(
                'SELECT d.doctor_id, d.consultation_fee
                 FROM `doctor` d
                 INNER JOIN `user` u ON u.user_id = d.user_id
                 WHERE d.doctor_id = ? AND d.is_active = 1 AND u.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([$doctorId]);
            $doctor = $stmt->fetch();
            if (!$doctor) {
                $this->pdo->rollBack();
                $this->json(404, ['success' => false, 'message' => 'Doctor not found or inactive.']);
            }

            $dup = $this->pdo->prepare(
                'SELECT admission_doctor_id FROM `admission_doctor`
                 WHERE admission_id = ? AND doctor_id = ? AND ended_datetime IS NULL
                 LIMIT 1'
            );
            $dup->execute([$admissionId, $doctorId]);
            if ($dup->fetch()) {
                $this->pdo->rollBack();
                $this->json(409, ['success' => false, 'message' => 'This doctor is already assigned to this admission.']);
            }

            $this->saveAdmissionDoctors($admissionId, [$doctorId], [$doctorId => $role]);

            BillingHook::emit($this->pdo, $admissionId);

            $this->pdo->commit();
            try { BillingHook::flushDeferred(); } catch (Throwable $e) {
                error_log('[NurseController::assignDoctorApi::flushDeferred] ' . $e->getMessage());
            }

            $this->json(200, ['success' => true, 'message' => 'Doctor assigned successfully.']);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[NurseController::assignDoctorApi] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not assign doctor: ' . $e->getMessage()]);
        }
    }

    public function removeDoctorApi(): void
    {
        $admissionDoctorId = (int)($_GET['id'] ?? 0);
        if ($admissionDoctorId <= 0) {
            $this->json(400, ['success' => false, 'message' => 'Missing assignment id.']);
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE `admission_doctor`
                 SET ended_datetime = NOW()
                 WHERE admission_doctor_id = ? AND ended_datetime IS NULL'
            );
            $stmt->execute([$admissionDoctorId]);

            $this->json(200, ['success' => true, 'message' => 'Doctor removed from admission.']);
        } catch (Throwable $e) {
            error_log('[NurseController::removeDoctorApi] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not remove doctor.']);
        }
    }

    public function getReadyForDischarge(): array
    {
        $readyId = $this->readyStatusId();

        $stmt = $this->pdo->prepare(
            'SELECT a.admission_id, a.admission_datetime, a.chief_complaint,
                    a.discharge_datetime,
                    p.patient_id, p.first_name, p.last_name,
                    r.room_number, rt.room_type_name,
                    ast.status_name AS admission_status_name,
                    ast.color_code  AS admission_status_color,
                    bs.statement_id,
                    bs.total_amount,
                    bs.amount_paid,
                    bs.balance_amount,
                    bst.status_name AS billing_status_name,
                    bst.color_code  AS billing_status_color
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
             LEFT JOIN `room_assignment` ra ON ra.admission_id = a.admission_id AND ra.is_active = 1
             LEFT JOIN `room` r ON r.room_id = ra.room_id
             LEFT JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             LEFT JOIN `billing_statement` bs ON bs.admission_id = a.admission_id
             LEFT JOIN `billing_status` bst ON bst.status_id = bs.status_id
             WHERE a.status_id = ?
             ORDER BY a.admission_datetime ASC'
        );
        $stmt->execute([$readyId]);
        return $stmt->fetchAll();
    }

    public function dischargePatient(): void
    {
        $data = $this->input();
        $admissionId = (int)($data['admission_id'] ?? 0);

        if ($admissionId <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Admission ID is required.']);
        }

        $userId = (int)($_SESSION['user']['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(403, ['success' => false, 'message' => 'Not authenticated.']);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT a.admission_id, a.patient_id, a.status_id, ast.status_name
                 FROM `admission` a
                 INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
                 WHERE a.admission_id = ? LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $admission = $stmt->fetch();

            if (!$admission) {
                $this->pdo->rollBack();
                $this->json(404, ['success' => false, 'message' => 'Admission not found.']);
            }

            $readyId = $this->readyStatusId();
            if ($readyId <= 0 || (int)$admission['status_id'] !== $readyId) {
                $this->pdo->rollBack();
                $this->json(409, [
                    'success' => false,
                    'message' => 'This patient must be marked Ready for Discharge by the attending doctor first. '
                        . 'Current status: ' . $admission['status_name'] . '.',
                ]);
            }

            $stmt = $this->pdo->prepare(
                'SELECT bs.statement_id, bs.balance_amount, bs.total_amount,
                        bs.amount_paid, bst.status_name, bst.color_code
                 FROM `billing_statement` bs
                 INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
                 WHERE bs.admission_id = ?
                 ORDER BY bs.statement_id DESC
                 LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $statement = $stmt->fetch();

            if (!$statement) {
                $this->pdo->rollBack();
                $this->json(409, [
                    'success' => false,
                    'message' => 'No billing statement exists for this admission yet. '
                        . 'The cashier must create a statement before the patient can be discharged.',
                ]);
            }

            $allowedStatuses = ['paid', 'partially paid'];
            $currentBillingStatus = strtolower($statement['status_name']);

            if (!in_array($currentBillingStatus, $allowedStatuses, true)) {
                $this->pdo->rollBack();
                $this->json(409, [
                    'success' => false,
                    'message' => 'Patient cannot be discharged — the billing statement is still "'
                        . $statement['status_name']
                        . '". Only Paid or Partially Paid patients can be discharged.',
                ]);
            }

            $stmt = $this->pdo->prepare(
                'SELECT room_assignment_id, room_id
                 FROM `room_assignment`
                 WHERE admission_id = ? AND is_active = 1 AND end_datetime IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $currentAssignment = $stmt->fetch();

            if ($currentAssignment) {
                $stmt = $this->pdo->prepare(
                    'UPDATE `room_assignment`
                     SET is_active = 0, end_datetime = NOW()
                     WHERE room_assignment_id = ?'
                );
                $stmt->execute([$currentAssignment['room_assignment_id']]);

                $stmt = $this->pdo->prepare(
                    'UPDATE `room`
                     SET status_id = (SELECT status_id FROM `room_status` WHERE status_name = "Available" LIMIT 1)
                     WHERE room_id = ?'
                );
                $stmt->execute([$currentAssignment['room_id']]);
            }

            $dischargedId = $this->dischargedStatusId();
            if ($dischargedId <= 0) $dischargedId = 2;

            $stmt = $this->pdo->prepare(
                'UPDATE `admission`
                 SET status_id = ?, discharge_datetime = NOW(),
                     discharged_by_user_id = ?, notes = CONCAT(COALESCE(notes, ""), ?)
                 WHERE admission_id = ?'
            );
            $stmt->execute([
                $dischargedId,
                $userId,
                !empty($data['discharge_notes']) ? "\n\nDischarge notes: " . $data['discharge_notes'] : '',
                $admissionId,
            ]);

            $this->pdo->commit();

            try {
                BillingHook::emit($this->pdo, $admissionId);
                BillingHook::flushDeferred();
            } catch (Throwable $e) {
                error_log('[NurseController::dischargePatient::billing] ' . $e->getMessage());
            }

            $this->json(200, ['success' => true, 'message' => 'Patient discharged successfully.']);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[NurseController::dischargePatient] ' . $e->getMessage());
            $this->json(500, [
                'success' => false,
                'message' => 'Could not discharge patient: ' . $e->getMessage(),
            ]);
        }
    }

    public function getDischargeRecords(): array
    {
        $dischargedId = $this->dischargedStatusId();

        $stmt = $this->pdo->prepare(
            'SELECT a.admission_id, a.admission_datetime, a.discharge_datetime,
                    a.chief_complaint, a.notes,
                    p.patient_id, p.first_name, p.last_name,
                    r.room_number, rt.room_type_name,
                    u.first_name AS discharged_by_first, u.last_name AS discharged_by_last
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             LEFT JOIN `room_assignment` ra ON ra.admission_id = a.admission_id
             LEFT JOIN `room` r ON r.room_id = ra.room_id
             LEFT JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             LEFT JOIN `user` u ON u.user_id = a.discharged_by_user_id
             WHERE a.status_id = ?
             ORDER BY a.discharge_datetime DESC
             LIMIT 100'
        );
        $stmt->execute([$dischargedId]);
        return $stmt->fetchAll();
    }

    public function getGenders(): array
    {
        return $this->pdo->query('SELECT gender_id, gender_name FROM `gender` ORDER BY gender_id')->fetchAll();
    }

    public function getAdmissionStatuses(): array
    {
        return $this->pdo->query('SELECT status_id, status_name, color_code FROM `admission_status` ORDER BY status_id')->fetchAll();
    }

    public function getRoomTypes(): array
    {
        return $this->pdo->query('SELECT room_type_id, room_type_name, rate_per_day FROM `room_type` ORDER BY room_type_name')->fetchAll();
    }

    public function getBuildings(): array
    {
        return $this->pdo->query('SELECT building_id, building_name FROM `building` ORDER BY building_name')->fetchAll();
    }

    public function getFloorLevels(?int $buildingId = null): array
    {
        $sql = 'SELECT fl.floor_level_id, fl.building_id, fl.floor_level_name, b.building_name
                FROM `floor_level` fl
                INNER JOIN `building` b ON b.building_id = fl.building_id';
        $params = [];

        if ($buildingId) {
            $sql .= ' WHERE fl.building_id = ?';
            $params[] = $buildingId;
        }

        $sql .= ' ORDER BY b.building_name, fl.floor_level_name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: $_POST;
    }

    private function validatePatient(array $data): array
    {
        $errors = [];

        if (empty($data['gender_id']) || (int)$data['gender_id'] <= 0) $errors['gender_id'] = 'Please select a gender.';
        if (trim((string)($data['first_name'] ?? '')) === '') $errors['first_name'] = 'First name is required.';
        if (trim((string)($data['last_name'] ?? '')) === '') $errors['last_name'] = 'Last name is required.';

        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is not valid.';
        }

        return $errors;
    }

    private function validateAdmission(array $data): array
    {
        $errors = [];

        if (empty($data['patient_id']) || (int)$data['patient_id'] <= 0) {
            $errors['patient_id'] = 'Please select a patient.';
        }

        if (empty($data['admission_status_id'])) {
            $errors['admission_status_id'] = 'Please select an admission status.';
        }

        if (empty($data['admission_type'])) {
            $errors['admission_type'] = 'Please select an admission type.';
        }

        return $errors;
    }

    private function json(int $status, array $payload): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}