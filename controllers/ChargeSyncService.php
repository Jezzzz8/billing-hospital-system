<?php
// controllers/ChargeSyncService.php

class ChargeSyncService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Idempotent full sync of a statement.
     * Scans all three source tables and materializes missing charges.
     */
    public function syncStatement(int $statementId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT admission_id FROM `billing_statement`
             WHERE statement_id = ? LIMIT 1'
        );
        $stmt->execute([$statementId]);
        $admissionId = (int)$stmt->fetchColumn();
        if ($admissionId <= 0) return;

        $this->pdo->beginTransaction();
        try {
            $this->syncLiveRooms($statementId, $admissionId);
            $this->syncLiveServices($statementId, $admissionId);
            $this->syncLiveDoctors($statementId, $admissionId);
            $this->recomputeStatement($statementId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================
    // SOURCE 1: ROOM ASSIGNMENTS
    // =========================================================

    private function syncLiveRooms(int $statementId, int $admissionId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.room_assignment_id, ra.start_datetime, ra.end_datetime,
                    ra.daily_rate_at_assignment,
                    r.room_number, rt.room_type_id, rt.room_type_name
             FROM `room_assignment` ra
             INNER JOIN `room` r ON r.room_id = ra.room_id
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             WHERE ra.admission_id = ?
             ORDER BY ra.start_datetime ASC'
        );
        $stmt->execute([$admissionId]);
        $assignments = $stmt->fetchAll();

        foreach ($assignments as $a) {
            $marker = 'source=room:' . $a['room_assignment_id'];

            // Find the charge item for this room type
            $itemId = $this->findRoomChargeItem($a['room_type_name']);
            if ($itemId <= 0) continue;

            // Already materialized?
            $chk = $this->pdo->prepare(
                'SELECT charge_id FROM `charge`
                 WHERE statement_id = ? AND notes LIKE ? LIMIT 1'
            );
            $chk->execute([$statementId, '%' . $marker . '%']);
            $existing = $chk->fetch();

            // Compute billable days
            $start = new DateTime($a['start_datetime']);
            $end   = $a['end_datetime'] ? new DateTime($a['end_datetime']) : new DateTime();
            $days  = max(1, (int)$start->diff($end)->days);

            if ($existing) {
                // Update quantity (days) and end date — room still active
                $upd = $this->pdo->prepare(
                    'UPDATE `charge`
                     SET quantity = ?, service_end_date = ?
                     WHERE charge_id = ?'
                );
                $upd->execute([
                    $days,
                    $end->format('Y-m-d'),
                    (int)$existing['charge_id'],
                ]);
            } else {
                $ins = $this->pdo->prepare(
                    'INSERT INTO `charge`
                        (statement_id, charge_item_id, quantity, actual_price,
                         charge_datetime, processed_by_user_id, notes,
                         service_start_date, service_end_date)
                     VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
                );
                $ins->execute([
                    $statementId,
                    $itemId,
                    $days,
                    (float)$a['daily_rate_at_assignment'],
                    (int)($_SESSION['user']['user_id'] ?? 0),
                    'Room ' . $a['room_number'] . ' (' . $a['room_type_name'] . ') — ' . $marker,
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                ]);
            }
        }
    }

    // =========================================================
    // SOURCE 2: SERVICE REQUESTS
    // =========================================================

    private function syncLiveServices(int $statementId, int $admissionId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT sr.request_id, sr.charge_item_id, sr.quantity,
                    ci.default_price, ci.item_name
             FROM `service_request` sr
             INNER JOIN `charge_item` ci ON ci.charge_item_id = sr.charge_item_id
             WHERE sr.admission_id = ? AND sr.status = "Pending"'
        );
        $stmt->execute([$admissionId]);
        $requests = $stmt->fetchAll();

        if (!$requests) return;

        $insert = $this->pdo->prepare(
            'INSERT INTO `charge`
                (statement_id, charge_item_id, quantity, actual_price,
                 charge_datetime, processed_by_user_id, notes)
             VALUES (?, ?, ?, ?, NOW(), ?, ?)'
        );
        $markComplete = $this->pdo->prepare(
            'UPDATE `service_request` SET status = "Completed" WHERE request_id = ?'
        );
        $userId = (int)($_SESSION['user']['user_id'] ?? 0);

        foreach ($requests as $r) {
            $marker = 'source=service:' . $r['request_id'];

            $chk = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `charge`
                 WHERE statement_id = ? AND notes LIKE ?'
            );
            $chk->execute([$statementId, '%' . $marker . '%']);

            if ((int)$chk->fetchColumn() === 0) {
                $insert->execute([
                    $statementId,
                    (int)$r['charge_item_id'],
                    (int)$r['quantity'],
                    (float)$r['default_price'],
                    $userId,
                    'Service request #' . $r['request_id'] . ' (' . $r['item_name'] . ') — ' . $marker,
                ]);
            }
            $markComplete->execute([(int)$r['request_id']]);
        }
    }

    // =========================================================
    // SOURCE 3: DOCTOR CONSULTATION FEES
    // =========================================================

    private function syncLiveDoctors(int $statementId, int $admissionId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT ad.admission_doctor_id, ad.consultation_fee_charged,
                    u.first_name, u.last_name
             FROM `admission_doctor` ad
             INNER JOIN `doctor` d ON d.doctor_id = ad.doctor_id
             INNER JOIN `user` u ON u.user_id = d.user_id
             WHERE ad.admission_id = ?
               AND ad.consultation_fee_charged > 0'
        );
        $stmt->execute([$admissionId]);
        $doctors = $stmt->fetchAll();

        if (!$doctors) return;

        $itemId = $this->findConsultChargeItem();
        if ($itemId <= 0) return;

        $insert = $this->pdo->prepare(
            'INSERT INTO `charge`
                (statement_id, charge_item_id, quantity, actual_price,
                 charge_datetime, processed_by_user_id, notes)
             VALUES (?, ?, 1, ?, NOW(), ?, ?)'
        );
        $userId = (int)($_SESSION['user']['user_id'] ?? 0);

        foreach ($doctors as $d) {
            $marker = 'source=doctor:' . $d['admission_doctor_id'];

            $chk = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `charge`
                 WHERE statement_id = ? AND charge_item_id = ? AND notes LIKE ?'
            );
            $chk->execute([$statementId, $itemId, '%' . $marker . '%']);
            if ((int)$chk->fetchColumn() > 0) continue;

            $insert->execute([
                $statementId,
                $itemId,
                (float)$d['consultation_fee_charged'],
                $userId,
                'Consultation — Dr. ' . $d['first_name'] . ' ' . $d['last_name'] . ' — ' . $marker,
            ]);
        }
    }

    // =========================================================
    // RECOMPUTE TOTALS
    // =========================================================

    public function recomputeStatement(int $statementId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(c.quantity * c.actual_price), 0) AS subtotal,
                COALESCE(SUM(CASE WHEN ci.is_taxable = 1 THEN c.quantity * c.actual_price ELSE 0 END), 0) AS taxable
             FROM `charge` c
             INNER JOIN `charge_item` ci ON ci.charge_item_id = c.charge_item_id
             WHERE c.statement_id = ?'
        );
        $stmt->execute([$statementId]);
        $row = $stmt->fetch();

        $subtotal = (float)$row['subtotal'];
        $taxable  = (float)$row['taxable'];
        $tax      = round($taxable * 0.12, 2);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM `payment` WHERE statement_id = ?'
        );
        $stmt->execute([$statementId]);
        $paid = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT insurance_coverage_amount, government_discount, status_id
             FROM `billing_statement` WHERE statement_id = ? LIMIT 1'
        );
        $stmt->execute([$statementId]);
        $cur = $stmt->fetch();
        if (!$cur) return;

        $insurance = (float)$cur['insurance_coverage_amount'];
        $discount  = (float)$cur['government_discount'];
        $statusId  = (int)$cur['status_id'];

        $total   = max(0, $subtotal + $tax - $insurance - $discount);
        $balance = max(0, $total - $paid);

        // Auto-status
        $stmtStatus = $this->pdo->query(
            'SELECT status_id, is_paid_status, status_name FROM `billing_status`'
        );
        $statuses = $stmtStatus->fetchAll();
        $paidId = null; $partialId = null;
        foreach ($statuses as $s) {
            if ((int)$s['is_paid_status'] === 1) $paidId = (int)$s['status_id'];
            if (strtolower($s['status_name']) === 'partially paid') $partialId = (int)$s['status_id'];
        }
        if ($paid > 0 && $balance <= 0 && $paidId) {
            $statusId = $paidId;
        } elseif ($paid > 0 && $partialId && $statusId !== 4) {
            $statusId = $partialId;
        }

        $upd = $this->pdo->prepare(
            'UPDATE `billing_statement`
             SET subtotal_amount = ?, tax_amount = ?, total_amount = ?,
                 amount_paid = ?, balance_amount = ?, status_id = ?
             WHERE statement_id = ?'
        );
        $upd->execute([
            $subtotal, $tax, $total, $paid, $balance, $statusId, $statementId,
        ]);
    }

    // =========================================================
    // CHARGE ITEM LOOKUPS
    // =========================================================

    private function findRoomChargeItem(string $roomTypeName): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT ci.charge_item_id
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE cc.category_name = "Room Charges" AND ci.is_active = 1
               AND ci.item_name LIKE ?
             LIMIT 1'
        );
        $stmt->execute(['%' . $roomTypeName . '%']);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) return $id;

        // Fallback: any room charge item
        $stmt = $this->pdo->prepare(
            'SELECT ci.charge_item_id
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE cc.category_name = "Room Charges" AND ci.is_active = 1
             LIMIT 1'
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function findConsultChargeItem(): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT charge_item_id FROM `charge_item`
             WHERE item_code = "PROF-CONSULT" AND is_active = 1 LIMIT 1'
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}