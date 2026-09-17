<?php
// controllers/PatientController.php

class PatientController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // =========================================================
    // READ
    // =========================================================

    public function getAll(): array
    {
        return $this->pdo->query(
            'SELECT p.patient_id, p.gender_id, p.first_name, p.last_name, p.birth_date,
                    p.contact_number, p.address, p.email,
                    p.emergency_contact, p.emergency_contact_number,
                    p.medical_history, p.is_active,
                    g.gender_name,
                    a.admission_id, a.status_id AS admission_status_id,
                    a.admission_datetime, a.discharge_datetime,
                    a.chief_complaint, a.admission_type,
                    ast.status_name AS admission_status_name,
                    ast.color_code  AS admission_status_color,
                    r.room_id, r.room_number,
                    rt.room_type_name
             FROM `patient` p
             INNER JOIN `gender` g ON g.gender_id = p.gender_id
             LEFT JOIN `admission` a
                    ON a.patient_id = p.patient_id
                   AND a.status_id IN (1, 3)
             LEFT JOIN `admission_status` ast ON ast.status_id = a.status_id
             LEFT JOIN `room_assignment` ra
                    ON ra.admission_id = a.admission_id
                   AND ra.is_active = 1
             LEFT JOIN `room` r ON r.room_id = ra.room_id
             LEFT JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             ORDER BY p.patient_id'
        )->fetchAll();
    }

    public function getDetails(int $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*,
                    a.admission_id, a.status_id AS admission_status_id,
                    a.admission_datetime, a.discharge_datetime,
                    a.chief_complaint, a.admission_type, a.notes AS admission_notes,
                    a.total_room_transfers
             FROM `patient` p
             LEFT JOIN `admission` a
                    ON a.patient_id = p.patient_id
                   AND a.status_id IN (1, 3)
             WHERE p.patient_id = ?
             ORDER BY a.admission_datetime DESC
             LIMIT 1'
        );
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        if (!$patient) return null;

        $admissionId = (int)($patient['admission_id'] ?? 0);

        $patient['room_assignment'] = null;
        $patient['doctors']         = [];
        $patient['diagnoses']       = [];

        if ($admissionId > 0) {
            // Room assignment
            $stmt = $this->pdo->prepare(
                'SELECT ra.room_assignment_id, ra.room_id, ra.start_datetime,
                        r.room_number, rt.room_type_name
                 FROM `room_assignment` ra
                 INNER JOIN `room` r ON r.room_id = ra.room_id
                 INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
                 WHERE ra.admission_id = ? AND ra.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $patient['room_assignment'] = $stmt->fetch() ?: null;

            // Doctors — include the doctor's own consultation_fee (authoritative)
            $stmt = $this->pdo->prepare(
                'SELECT ad.admission_doctor_id, ad.doctor_id, ad.doctor_role,
                        ad.consultation_fee_charged,
                        d.consultation_fee AS doctor_default_fee,
                        u.first_name, u.last_name
                 FROM `admission_doctor` ad
                 INNER JOIN `doctor` d ON d.doctor_id = ad.doctor_id
                 INNER JOIN `user` u ON u.user_id = d.user_id
                 WHERE ad.admission_id = ?'
            );
            $stmt->execute([$admissionId]);
            $patient['doctors'] = $stmt->fetchAll();

            // Diagnoses
            $stmt = $this->pdo->prepare(
                'SELECT adi.admission_diagnosis_id, adi.diagnosis_id, adi.diagnosis_type,
                        adi.diagnosed_by_doctor_id,
                        d.diagnosis_name, d.icd_code
                 FROM `admission_diagnosis` adi
                 INNER JOIN `diagnosis` d ON d.diagnosis_id = adi.diagnosis_id
                 WHERE adi.admission_id = ?'
            );
            $stmt->execute([$admissionId]);
            $patient['diagnoses'] = $stmt->fetchAll();
        }

        return $patient;
    }

    // Dropdown data
    public function getGenders(): array
    {
        return $this->pdo->query('SELECT gender_id, gender_name FROM `gender` ORDER BY gender_id')->fetchAll();
    }

    public function getAdmissionStatuses(): array
    {
        return $this->pdo->query('SELECT status_id, status_name, color_code FROM `admission_status` ORDER BY status_id')->fetchAll();
    }

    // ---- Available rooms: only is_active = 1 AND Available status ----
    public function getAvailableRooms(): array
    {
        return $this->pdo->query(
            'SELECT r.room_id, r.room_number, r.floor_level, r.building,
                    rt.room_type_name, rt.rate_per_day,
                    rs.status_name, rs.color_code
             FROM `room` r
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE r.is_active = 1
               AND rs.status_name = "Available"
             ORDER BY r.room_number'
        )->fetchAll();
    }

    // ---- Doctors: fee comes from the doctor record ----
    public function getDoctors(): array
    {
        return $this->pdo->query(
            'SELECT d.doctor_id, d.consultation_fee, u.first_name, u.last_name
             FROM `doctor` d
             INNER JOIN `user` u ON u.user_id = d.user_id
             WHERE d.is_active = 1 AND u.is_active = 1
             ORDER BY u.last_name'
        )->fetchAll();
    }

    public function getDiagnoses(): array
    {
        return $this->pdo->query(
            'SELECT diagnosis_id, icd_code, diagnosis_name
             FROM `diagnosis`
             WHERE is_active = 1
             ORDER BY diagnosis_name'
        )->fetchAll();
    }

    // =========================================================
    // CREATE
    // =========================================================

    public function create(): void
    {
        $this->guard();

        $data = $this->input();
        $errors = $this->validate($data, null, true);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $this->pdo->beginTransaction();

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

            $admissionId = null;
            if (!empty($data['create_admission'])) {
                $admissionId = $this->createAdmission($patientId, $data);
                $this->saveRoomAssignment($admissionId, $data);
                $this->saveAdmissionDoctors($admissionId, $data);
                $this->saveAdmissionDiagnoses($admissionId, $data);
            }

            $this->pdo->commit();

            $this->json(201, [
                'success'      => true,
                'message'      => 'Patient created successfully.',
                'patient_id'   => $patientId,
                'admission_id' => $admissionId,
            ]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[PatientController::create] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not create patient.']);
        }
    }

    // =========================================================
    // UPDATE
    // =========================================================

    public function update(): void
    {
        $this->guard();

        $patientId = (int)($_GET['id'] ?? 0);
        if ($patientId <= 0) $this->json(400, ['success' => false, 'message' => 'Missing patient id.']);

        $stmt = $this->pdo->prepare('SELECT patient_id FROM `patient` WHERE patient_id = ? LIMIT 1');
        $stmt->execute([$patientId]);
        if (!$stmt->fetch()) $this->json(404, ['success' => false, 'message' => 'Patient not found.']);

        $data = $this->input();
        $errors = $this->validate($data, $patientId, false);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE `patient`
                 SET gender_id = ?, first_name = ?, last_name = ?, birth_date = ?,
                     contact_number = ?, address = ?, email = ?,
                     emergency_contact = ?, emergency_contact_number = ?, medical_history = ?
                 WHERE patient_id = ?'
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
                $patientId,
            ]);

            $admissionId = (int)($data['admission_id'] ?? 0);

            if ($admissionId > 0) {
                $stmt = $this->pdo->prepare(
                    'UPDATE `admission`
                     SET status_id = ?, chief_complaint = ?, admission_type = ?, notes = ?
                     WHERE admission_id = ?'
                );
                $stmt->execute([
                    (int)$data['admission_status_id'],
                    !empty($data['chief_complaint']) ? $data['chief_complaint'] : null,
                    !empty($data['admission_type']) ? $data['admission_type'] : null,
                    !empty($data['admission_notes']) ? $data['admission_notes'] : null,
                    $admissionId,
                ]);

                $stmt = $this->pdo->prepare('UPDATE `room_assignment` SET is_active = 0, end_datetime = NOW() WHERE admission_id = ? AND is_active = 1');
                $stmt->execute([$admissionId]);
                $this->saveRoomAssignment($admissionId, $data);

                $stmt = $this->pdo->prepare('DELETE FROM `admission_doctor` WHERE admission_id = ?');
                $stmt->execute([$admissionId]);
                $this->saveAdmissionDoctors($admissionId, $data);

                $stmt = $this->pdo->prepare('DELETE FROM `admission_diagnosis` WHERE admission_id = ?');
                $stmt->execute([$admissionId]);
                $this->saveAdmissionDiagnoses($admissionId, $data);
            } else if (!empty($data['create_admission'])) {
                $admissionId = $this->createAdmission($patientId, $data);
                $this->saveRoomAssignment($admissionId, $data);
                $this->saveAdmissionDoctors($admissionId, $data);
                $this->saveAdmissionDiagnoses($admissionId, $data);
            }

            $this->pdo->commit();

            $this->json(200, ['success' => true, 'message' => 'Patient updated successfully.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[PatientController::update] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not update patient.']);
        }
    }

    // =========================================================
    // TOGGLE ACTIVE
    // =========================================================

    public function toggleActive(): void
    {
        $this->guard();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing patient id.']);

        $stmt = $this->pdo->prepare('SELECT is_active FROM `patient` WHERE patient_id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) $this->json(404, ['success' => false, 'message' => 'Patient not found.']);

        $newState = ((int)$row['is_active'] === 1) ? 0 : 1;

        $stmt = $this->pdo->prepare('UPDATE `patient` SET is_active = ? WHERE patient_id = ?');
        $stmt->execute([$newState, $id]);

        $this->json(200, [
            'success'   => true,
            'message'   => $newState === 1 ? 'Patient reactivated.' : 'Patient archived.',
            'is_active' => $newState,
        ]);
    }

    // =========================================================
    // NESTED WRITES
    // =========================================================

    private function createAdmission(int $patientId, array $data): int
    {
        $statusId = (int)($data['admission_status_id'] ?? 1);

        $stmt = $this->pdo->prepare(
            'INSERT INTO `admission`
                (patient_id, status_id, admission_datetime, discharge_datetime,
                 chief_complaint, admission_type, total_room_transfers,
                 admitted_by_user_id, discharged_by_user_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
        );
        $stmt->execute([
            $patientId,
            $statusId,
            !empty($data['admission_datetime']) ? $data['admission_datetime'] : date('Y-m-d H:i:s'),
            !empty($data['discharge_datetime']) ? $data['discharge_datetime'] : null,
            !empty($data['chief_complaint']) ? $data['chief_complaint'] : null,
            !empty($data['admission_type']) ? $data['admission_type'] : null,
            (int)$_SESSION['user']['user_id'],
            $statusId === 2 ? (int)$_SESSION['user']['user_id'] : null,
            !empty($data['admission_notes']) ? $data['admission_notes'] : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function saveRoomAssignment(int $admissionId, array $data): void
    {
        $roomId = (int)($data['room_id'] ?? 0);
        if ($roomId <= 0) return;

        // Only allow active + Available rooms
        $stmt = $this->pdo->prepare(
            'SELECT rt.rate_per_day
             FROM `room` r
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             INNER JOIN `room_status` rs ON rs.status_id = r.status_id
             WHERE r.room_id = ?
               AND r.is_active = 1
               AND rs.status_name = "Available"
             LIMIT 1'
        );
        $stmt->execute([$roomId]);
        $rate = $stmt->fetchColumn();
        if ($rate === false) {
            // Room not available anymore — skip silently
            return;
        }

        $rate = (float)$rate;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `room_assignment`
                (admission_id, room_id, start_datetime, end_datetime,
                 daily_rate_at_assignment, transfer_reason, transferred_by_user_id, is_active)
             VALUES (?, ?, NOW(), NULL, ?, NULL, ?, 1)'
        );
        $stmt->execute([
            $admissionId,
            $roomId,
            $rate,
            (int)$_SESSION['user']['user_id'],
        ]);

        // Update the room's status to Occupied
        $stmt = $this->pdo->prepare('UPDATE `room` SET status_id = 2 WHERE room_id = ?');
        $stmt->execute([$roomId]);
    }

    private function saveAdmissionDoctors(int $admissionId, array $data): void
    {
        $doctors = $data['doctors'] ?? [];
        if (!is_array($doctors) || !$doctors) return;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `admission_doctor`
                (admission_id, doctor_id, doctor_role, assigned_datetime,
                ended_datetime, consultation_fee_charged)
            VALUES (?, ?, ?, NOW(), NULL, ?)'
        );

        foreach ($doctors as $d) {
            $doctorId = (int)($d['doctor_id'] ?? 0);
            if ($doctorId <= 0) continue;

            $role = !empty($d['doctor_role']) ? $d['doctor_role'] : 'Attending';

            // ALWAYS use the doctor's own consultation_fee
            $stmtFee = $this->pdo->prepare('SELECT consultation_fee FROM `doctor` WHERE doctor_id = ? LIMIT 1');
            $stmtFee->execute([$doctorId]);
            $fee = (float)($stmtFee->fetchColumn() ?: 0);

            $stmt->execute([$admissionId, $doctorId, $role, $fee]);

            // ── Fire billing event: doctor assigned ──
            require_once __DIR__ . '/BillingHook.php';
            BillingHook::emit($this->pdo, $admissionId);
        }
    }

    private function saveAdmissionDiagnoses(int $admissionId, array $data): void
    {
        $diagnoses = $data['diagnoses'] ?? [];
        if (!is_array($diagnoses) || !$diagnoses) return;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `admission_diagnosis`
                (admission_id, diagnosis_id, diagnosis_type, diagnosed_datetime, diagnosed_by_doctor_id)
             VALUES (?, ?, ?, NOW(), ?)'
        );

        foreach ($diagnoses as $d) {
            $diagId = (int)($d['diagnosis_id'] ?? 0);
            if ($diagId <= 0) continue;

            $type = !empty($d['diagnosis_type']) ? $d['diagnosis_type'] : 'Primary';
            $doctorId = !empty($d['diagnosed_by_doctor_id']) ? (int)$d['diagnosed_by_doctor_id'] : null;

            $stmt->execute([$admissionId, $diagId, $type, $doctorId]);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================

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

    private function validate(array $data, ?int $ignoreId, bool $requireAdmission): array
    {
        $errors = [];

        if (empty($data['gender_id']) || (int)$data['gender_id'] <= 0) $errors['gender_id'] = 'Please select a gender.';
        if (trim((string)($data['first_name'] ?? '')) === '') $errors['first_name'] = 'First name is required.';
        if (trim((string)($data['last_name']  ?? '')) === '') $errors['last_name']  = 'Last name is required.';

        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is not valid.';
        }

        if (!empty($data['create_admission'])) {
            if (empty($data['admission_status_id'])) $errors['admission_status_id'] = 'Please select an admission status.';
            if (empty($data['admission_type']))      $errors['admission_type']      = 'Please select an admission type.';
        }

        return $errors;
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}