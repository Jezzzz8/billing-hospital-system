<?php
// controllers/CashierController.php
require_once __DIR__ . '/ChargeSyncService.php';

class CashierController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    // =========================================================
    // DASHBOARD
    // =========================================================

    public function getDashboardStats(): array
    {
        $totalStatements = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM `billing_statement`'
        )->fetchColumn();

        $pendingStatements = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM `billing_statement` bs
             INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
             WHERE bs.balance_amount > 0 AND bst.is_paid_status = 0'
        )->fetchColumn();

        $overdue = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM `billing_statement`
             WHERE balance_amount > 0
               AND due_date IS NOT NULL
               AND due_date < CURDATE()'
        )->fetchColumn();

        $collectedToday = (float)$this->pdo->query(
            'SELECT COALESCE(SUM(amount), 0) FROM `payment`
             WHERE DATE(payment_datetime) = CURDATE()'
        )->fetchColumn();

        $totalOutstanding = (float)$this->pdo->query(
            'SELECT COALESCE(SUM(balance_amount), 0) FROM `billing_statement`'
        )->fetchColumn();

        $totalCollected = (float)$this->pdo->query(
            'SELECT COALESCE(SUM(amount), 0) FROM `payment`'
        )->fetchColumn();

        return [
            'total_statements'   => $totalStatements,
            'pending_statements' => $pendingStatements,
            'overdue'            => $overdue,
            'collected_today'    => $collectedToday,
            'total_outstanding'  => $totalOutstanding,
            'total_collected'    => $totalCollected,
        ];
    }

    public function getRecentStatements(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bs.statement_id, bs.admission_id, bs.statement_date, bs.due_date,
                    bs.total_amount, bs.amount_paid, bs.balance_amount,
                    bst.status_name, bst.color_code, bst.is_paid_status,
                    p.first_name, p.last_name
             FROM `billing_statement` bs
             INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
             INNER JOIN `admission` a ON a.admission_id = bs.admission_id
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             ORDER BY bs.statement_id DESC
             LIMIT ' . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================
    // STATEMENTS
    // =========================================================

    public function getAllStatements(): array
    {
        return $this->pdo->query(
            'SELECT bs.statement_id, bs.admission_id, bs.status_id, bs.statement_date,
                    bs.due_date, bs.subtotal_amount, bs.insurance_coverage_amount,
                    bs.government_discount, bs.tax_amount, bs.total_amount,
                    bs.amount_paid, bs.balance_amount, bs.created_at, bs.notes,
                    bst.status_name, bst.color_code, bst.is_paid_status,
                    p.patient_id, p.first_name, p.last_name,
                    a.admission_datetime
             FROM `billing_statement` bs
             INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
             INNER JOIN `admission` a ON a.admission_id = bs.admission_id
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             ORDER BY bs.statement_id DESC'
        )->fetchAll();
    }

    public function getUnpaidStatements(): array
    {
        return $this->pdo->query(
            'SELECT bs.statement_id, bs.admission_id, bs.statement_date, bs.due_date,
                    bs.subtotal_amount, bs.insurance_coverage_amount, bs.government_discount,
                    bs.tax_amount, bs.total_amount, bs.amount_paid, bs.balance_amount,
                    bst.status_id, bst.status_name, bst.color_code, bst.is_paid_status,
                    p.patient_id, p.first_name, p.last_name, p.contact_number, p.email,
                    a.admission_datetime
            FROM `billing_statement` bs
            INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
            INNER JOIN `admission` a ON a.admission_id = bs.admission_id
            INNER JOIN `patient` p ON p.patient_id = a.patient_id
            WHERE bs.balance_amount > 0
            AND bst.is_paid_status = 0
            ORDER BY (bs.due_date IS NULL) ASC, bs.due_date ASC, bs.statement_id ASC'
        )->fetchAll();
    }

    public function getStatementSummary(int $statementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bs.statement_id, bs.admission_id, bs.statement_date, bs.due_date,
                    bs.total_amount, bs.amount_paid, bs.balance_amount,
                    bst.status_name, bst.color_code,
                    p.patient_id, p.first_name, p.last_name, p.contact_number
            FROM `billing_statement` bs
            INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
            INNER JOIN `admission` a ON a.admission_id = bs.admission_id
            INNER JOIN `patient` p ON p.patient_id = a.patient_id
            WHERE bs.statement_id = ?
            LIMIT 1'
        );
        $stmt->execute([$statementId]);
        return $stmt->fetch() ?: null;
    }

    public function getStatementDetails(int $statementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bs.*, bst.status_name, bst.color_code, bst.is_paid_status,
                    p.first_name, p.last_name, p.patient_id, p.contact_number, p.email, p.address,
                    a.admission_datetime, a.discharge_datetime, a.chief_complaint
             FROM `billing_statement` bs
             INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
             INNER JOIN `admission` a ON a.admission_id = bs.admission_id
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             WHERE bs.statement_id = ?
             LIMIT 1'
        );
        $stmt->execute([$statementId]);
        $statement = $stmt->fetch();
        if (!$statement) return null;

        // Charges
        $stmt = $this->pdo->prepare(
            'SELECT c.charge_id, c.charge_item_id, c.quantity, c.actual_price,
                    c.charge_datetime, c.notes, c.service_start_date, c.service_end_date,
                    ci.item_code, ci.item_name, ci.unit_of_measure, ci.is_taxable,
                    cc.category_name,
                    (c.quantity * c.actual_price) AS line_total
             FROM `charge` c
             INNER JOIN `charge_item` ci ON ci.charge_item_id = c.charge_item_id
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE c.statement_id = ?
             ORDER BY c.charge_id'
        );
        $stmt->execute([$statementId]);
        $statement['charges'] = $stmt->fetchAll();

        // Payments — explicit columns so JS always has what it needs
        $stmt = $this->pdo->prepare(
            'SELECT p.payment_id, p.payment_type_id, p.amount,
                    DATE_FORMAT(p.payment_datetime, "%Y-%m-%d %H:%i:%s") AS payment_datetime,
                    p.transaction_reference, p.notes,
                    pt.type_name
             FROM `payment` p
             INNER JOIN `payment_type` pt ON pt.payment_type_id = p.payment_type_id
             WHERE p.statement_id = ?
             ORDER BY p.payment_id DESC'
        );
        $stmt->execute([$statementId]);
        $statement['payments'] = $stmt->fetchAll();

        // Room history
        $stmt = $this->pdo->prepare(
            'SELECT ra.room_assignment_id, ra.start_datetime, ra.end_datetime,
                    ra.daily_rate_at_assignment, ra.transfer_reason, ra.is_active,
                    r.room_number, rt.room_type_name
             FROM `room_assignment` ra
             INNER JOIN `room` r ON r.room_id = ra.room_id
             INNER JOIN `room_type` rt ON rt.room_type_id = r.room_type_id
             WHERE ra.admission_id = ?
             ORDER BY ra.start_datetime ASC'
        );
        $stmt->execute([$statement['admission_id']]);
        $statement['room_history'] = $stmt->fetchAll();

        return $statement;
    }

    // =========================================================
    // DROPDOWN DATA
    // =========================================================

    public function getBillingStatuses(): array
    {
        return $this->pdo->query(
            'SELECT status_id, status_name, color_code, is_paid_status
             FROM `billing_status` ORDER BY status_id'
        )->fetchAll();
    }

    public function getPaymentTypes(): array
    {
        return $this->pdo->query(
            'SELECT payment_type_id, type_name, description
             FROM `payment_type` ORDER BY type_name'
        )->fetchAll();
    }

    public function getChargeItems(): array
    {
        return $this->pdo->query(
            'SELECT ci.charge_item_id, ci.item_code, ci.item_name, ci.default_price,
                    ci.is_taxable, ci.unit_of_measure,
                    cc.category_id, cc.category_name
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE ci.is_active = 1
             ORDER BY cc.category_name, ci.item_name'
        )->fetchAll();
    }

    public function getAdmissionsWithoutStatement(): array
    {
        return $this->pdo->query(
            'SELECT a.admission_id, a.admission_datetime, a.chief_complaint,
                    p.first_name, p.last_name, p.patient_id
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             LEFT JOIN `billing_statement` bs ON bs.admission_id = a.admission_id
             WHERE bs.statement_id IS NULL
             ORDER BY a.admission_datetime DESC'
        )->fetchAll();
    }

    // =========================================================
    // SYNC CHARGES FROM ADMISSION
    // =========================================================

    public function syncChargesFromAdmission(int $statementId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT admission_id FROM `billing_statement` WHERE statement_id = ? LIMIT 1'
        );
        $stmt->execute([$statementId]);
        $admissionId = (int)$stmt->fetchColumn();
        if ($admissionId <= 0) return;

        $userId = (int)($_SESSION['user']['user_id'] ?? 0);

        $this->pdo->beginTransaction();

        try {
            // 1. ROOM CHARGES
            $stmt = $this->pdo->prepare(
                'SELECT ra.room_assignment_id, ra.room_id, ra.start_datetime, ra.end_datetime,
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
                $itemId = $this->findRoomChargeItem((int)$a['room_type_id'], $a['room_type_name']);
                if ($itemId <= 0) continue;

                $stmtChk = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `charge`
                     WHERE statement_id = ? AND notes LIKE ?'
                );
                $stmtChk->execute([
                    $statementId,
                    '%room_assignment_id=' . $a['room_assignment_id'] . '%'
                ]);
                if ((int)$stmtChk->fetchColumn() > 0) continue;

                $start = new DateTime($a['start_datetime']);
                $end   = $a['end_datetime'] ? new DateTime($a['end_datetime']) : new DateTime();
                $days  = max(1, (int)$start->diff($end)->days);

                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO `charge`
                        (statement_id, charge_item_id, quantity, actual_price,
                         charge_datetime, processed_by_user_id, notes,
                         service_start_date, service_end_date)
                     VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
                );
                $stmtIns->execute([
                    $statementId,
                    $itemId,
                    $days,
                    (float)$a['daily_rate_at_assignment'],
                    $userId,
                    'Room ' . $a['room_number'] . ' (' . $a['room_type_name'] . ') — room_assignment_id=' . $a['room_assignment_id'],
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                ]);
            }

            // 2. SERVICE REQUESTS
            $stmt = $this->pdo->prepare(
                'SELECT sr.request_id, sr.charge_item_id, sr.quantity,
                        ci.default_price, ci.item_name
                 FROM `service_request` sr
                 INNER JOIN `charge_item` ci ON ci.charge_item_id = sr.charge_item_id
                 WHERE sr.admission_id = ? AND sr.status = "Pending"'
            );
            $stmt->execute([$admissionId]);
            $requests = $stmt->fetchAll();

            foreach ($requests as $r) {
                $stmtChk = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM `charge`
                     WHERE statement_id = ? AND notes LIKE ?'
                );
                $stmtChk->execute([
                    $statementId,
                    '%service_request_id=' . $r['request_id'] . '%'
                ]);
                if ((int)$stmtChk->fetchColumn() > 0) {
                    $stmtUpd = $this->pdo->prepare(
                        'UPDATE `service_request` SET status = "Completed" WHERE request_id = ?'
                    );
                    $stmtUpd->execute([(int)$r['request_id']]);
                    continue;
                }

                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO `charge`
                        (statement_id, charge_item_id, quantity, actual_price,
                         charge_datetime, processed_by_user_id, notes)
                     VALUES (?, ?, ?, ?, NOW(), ?, ?)'
                );
                $stmtIns->execute([
                    $statementId,
                    (int)$r['charge_item_id'],
                    (int)$r['quantity'],
                    (float)$r['default_price'],
                    $userId,
                    'Service request — service_request_id=' . $r['request_id'] . ' (' . $r['item_name'] . ')',
                ]);

                $stmtUpd = $this->pdo->prepare(
                    'UPDATE `service_request` SET status = "Completed" WHERE request_id = ?'
                );
                $stmtUpd->execute([(int)$r['request_id']]);
            }

            // 3. DOCTOR CONSULTATION FEES
            $stmt = $this->pdo->prepare(
                'SELECT ad.admission_doctor_id, ad.consultation_fee_charged, ad.doctor_id,
                        u.first_name, u.last_name
                 FROM `admission_doctor` ad
                 INNER JOIN `doctor` d ON d.doctor_id = ad.doctor_id
                 INNER JOIN `user` u ON u.user_id = d.user_id
                 WHERE ad.admission_id = ?
                   AND ad.consultation_fee_charged > 0
                   AND ad.ended_datetime IS NULL'
            );
            $stmt->execute([$admissionId]);
            $doctors = $stmt->fetchAll();

            $stmtCI = $this->pdo->prepare(
                'SELECT charge_item_id FROM `charge_item`
                 WHERE item_code = "PROF-CONSULT" AND is_active = 1 LIMIT 1'
            );
            $stmtCI->execute();
            $consultItemId = (int)$stmtCI->fetchColumn();

            if ($consultItemId > 0) {
                foreach ($doctors as $d) {
                    $stmtChk = $this->pdo->prepare(
                        'SELECT COUNT(*) FROM `charge`
                         WHERE statement_id = ? AND charge_item_id = ? AND notes LIKE ?'
                    );
                    $stmtChk->execute([
                        $statementId,
                        $consultItemId,
                        '%admission_doctor_id=' . $d['admission_doctor_id'] . '%'
                    ]);
                    if ((int)$stmtChk->fetchColumn() > 0) continue;

                    $stmtIns = $this->pdo->prepare(
                        'INSERT INTO `charge`
                            (statement_id, charge_item_id, quantity, actual_price,
                             charge_datetime, processed_by_user_id, notes)
                         VALUES (?, ?, 1, ?, NOW(), ?, ?)'
                    );
                    $stmtIns->execute([
                        $statementId,
                        $consultItemId,
                        (float)$d['consultation_fee_charged'],
                        $userId,
                        'Doctor consultation — admission_doctor_id=' . $d['admission_doctor_id']
                            . ' (Dr. ' . $d['first_name'] . ' ' . $d['last_name'] . ')',
                    ]);
                }
            }

            $this->recomputeStatement($statementId);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[CashierController::syncChargesFromAdmission] ' . $e->getMessage());
            throw $e;
        }
    }

    private function findRoomChargeItem(int $roomTypeId, string $roomTypeName): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT ci.charge_item_id
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE cc.category_name = "Room Charges"
               AND ci.is_active = 1
               AND ci.item_name LIKE ?
             LIMIT 1'
        );
        $stmt->execute(['%' . $roomTypeName . '%']);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) return $id;

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

    // =========================================================
    // CHARGES
    // =========================================================

    public function addCharge(int $statementId, array $data): void
    {
        $itemId   = (int)($data['charge_item_id'] ?? 0);
        $quantity = (int)($data['quantity'] ?? 1);
        if ($itemId <= 0 || $quantity <= 0) throw new Exception('Invalid charge data.');

        $stmt = $this->pdo->prepare(
            'SELECT charge_item_id, default_price, item_name FROM `charge_item`
            WHERE charge_item_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) throw new Exception('Charge item not found or inactive.');

        $price = isset($data['actual_price']) && is_numeric($data['actual_price'])
            ? (float)$data['actual_price']
            : (float)$item['default_price'];

        $note = 'manual-entry';
        if (!empty($data['notes'])) {
            $note .= ': ' . trim((string)$data['notes']);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `charge`
                (statement_id, charge_item_id, quantity, actual_price,
                charge_datetime, processed_by_user_id, notes)
            VALUES (?, ?, ?, ?, NOW(), ?, ?)'
        );
        $stmt->execute([
            $statementId,
            $itemId,
            $quantity,
            $price,
            (int)($_SESSION['user']['user_id'] ?? 0),
            $note,
        ]);

        $this->recomputeStatement($statementId);
    }

    public function removeCharge(int $statementId, int $chargeId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT charge_id, notes FROM `charge`
            WHERE charge_id = ? AND statement_id = ? LIMIT 1'
        );
        $stmt->execute([$chargeId, $statementId]);
        $charge = $stmt->fetch();

        if (!$charge) {
            throw new Exception('Charge not found.');
        }

        $notes = (string)($charge['notes'] ?? '');

        if (stripos($notes, 'source=') !== false) {
            throw new Exception(
                'This charge is system-generated (from admission, service, or room) '
                . 'and cannot be removed. It is managed automatically.'
            );
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM `charge` WHERE charge_id = ? AND statement_id = ?'
        );
        $stmt->execute([$chargeId, $statementId]);

        $this->recomputeStatement($statementId);
    }

    // =========================================================
    // PAYMENTS
    // =========================================================

    /**
     * Records a payment and returns the generated reference.
     * Reference format: PAY-YYYYMMDD-#### (daily sequence).
     */
    public function addPayment(int $statementId, array $data): string
    {
        $typeId = (int)($data['payment_type_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);

        if ($typeId <= 0 || $amount <= 0) {
            throw new Exception('Please provide a payment type and a positive amount.');
        }

        $this->pdo->beginTransaction();
        try {
            $reference = $this->generatePaymentReference();

            $stmt = $this->pdo->prepare(
                'INSERT INTO `payment`
                    (statement_id, payment_type_id, amount, payment_datetime,
                     transaction_reference, received_by_user_id, notes)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)'
            );
            $stmt->execute([
                $statementId,
                $typeId,
                $amount,
                $reference,
                (int)($_SESSION['user']['user_id'] ?? 0),
                !empty($data['notes']) ? $data['notes'] : null,
            ]);

            $this->recomputeStatement($statementId);

            $this->pdo->commit();
            return $reference;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function removePayment(int $statementId, int $paymentId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM `payment` WHERE payment_id = ? AND statement_id = ?'
        );
        $stmt->execute([$paymentId, $statementId]);

        $this->recomputeStatement($statementId);
    }

    /**
     * Generates the next daily payment reference.
     * Format: PAY-YYYYMMDD-####
     * MUST be called inside an open transaction.
     */
    private function generatePaymentReference(): string
    {
        $date   = date('Ymd');
        $prefix = 'PAY-' . $date . '-';

        // Lock today's highest reference row for the duration of the txn.
        $stmt = $this->pdo->prepare(
            'SELECT transaction_reference
             FROM `payment`
             WHERE transaction_reference LIKE ?
             ORDER BY payment_id DESC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$prefix . '%']);
        $last = (string)$stmt->fetchColumn();

        $next = 1;
        if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
            $next = ((int)$m[1]) + 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================
    // RECOMPUTE STATEMENT TOTALS
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
        $current = $stmt->fetch();
        if (!$current) return;

        $insurance = (float)$current['insurance_coverage_amount'];
        $discount  = (float)$current['government_discount'];
        $statusId  = (int)$current['status_id'];

        $total   = max(0, $subtotal + $tax - $insurance - $discount);
        $balance = max(0, $total - $paid);

        $stmtStatus = $this->pdo->prepare(
            'SELECT status_id, is_paid_status, status_name FROM `billing_status`'
        );
        $stmtStatus->execute();
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

        $stmt = $this->pdo->prepare(
            'UPDATE `billing_statement`
             SET subtotal_amount = ?, tax_amount = ?, total_amount = ?,
                 amount_paid = ?, balance_amount = ?, status_id = ?
             WHERE statement_id = ?'
        );
        $stmt->execute([
            $subtotal,
            $tax,
            $total,
            $paid,
            $balance,
            $statusId,
            $statementId,
        ]);
    }

    // =========================================================
    // REPORTS
    // =========================================================

    public function getReportsData(): array
    {
        $byType = $this->pdo->query(
            'SELECT pt.type_name, COUNT(p.payment_id) AS count,
                    COALESCE(SUM(p.amount), 0) AS total
             FROM `payment` p
             INNER JOIN `payment_type` pt ON pt.payment_type_id = p.payment_type_id
             GROUP BY pt.payment_type_id
             ORDER BY total DESC'
        )->fetchAll();

        $daily = $this->pdo->query(
            'SELECT DATE(payment_datetime) AS day,
                    COUNT(*) AS count,
                    COALESCE(SUM(amount), 0) AS total
             FROM `payment`
             WHERE payment_datetime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(payment_datetime)
             ORDER BY day ASC'
        )->fetchAll();

        $topBalances = $this->pdo->query(
            'SELECT bs.statement_id, p.first_name, p.last_name,
                    bs.total_amount, bs.amount_paid, bs.balance_amount,
                    bst.status_name, bst.color_code
             FROM `billing_statement` bs
             INNER JOIN `admission` a ON a.admission_id = bs.admission_id
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             INNER JOIN `billing_status` bst ON bst.status_id = bs.status_id
             WHERE bs.balance_amount > 0
             ORDER BY bs.balance_amount DESC
             LIMIT 10'
        )->fetchAll();

        return [
            'by_type'       => $byType,
            'daily'         => $daily,
            'top_balances'  => $topBalances,
        ];
    }

    public function syncStatement(int $statementId): void
    {
        (new ChargeSyncService($this->pdo))->syncStatement($statementId);
    }

    public function createStatement(): void
    {
        $this->guard();

        $data = $this->input();
        $admissionId = (int)($data['admission_id'] ?? 0);
        if ($admissionId <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Please select an admission.']);
        }

        $stmt = $this->pdo->prepare(
            'SELECT statement_id FROM `billing_statement` WHERE admission_id = ? LIMIT 1'
        );
        $stmt->execute([$admissionId]);
        if ($stmt->fetch()) {
            $this->json(409, ['success' => false, 'message' => 'This admission already has a statement.']);
        }

        try {
            $this->pdo->beginTransaction();

            $defaultStatusId = (int)$this->pdo->query(
                'SELECT status_id FROM `billing_status` WHERE is_paid_status = 0 ORDER BY status_id LIMIT 1'
            )->fetchColumn();

            $stmt = $this->pdo->prepare(
                'INSERT INTO `billing_statement`
                    (admission_id, status_id, statement_date, due_date,
                    subtotal_amount, insurance_coverage_amount, government_discount,
                    tax_amount, total_amount, amount_paid, balance_amount,
                    created_by_user_id, notes)
                VALUES (?, ?, NOW(), ?, 0, ?, ?, 0, 0, 0, 0, ?, ?)'
            );
            $stmt->execute([
                $admissionId,
                $defaultStatusId ?: 1,
                !empty($data['due_date']) ? $data['due_date'] : null,
                (float)($data['insurance_coverage_amount'] ?? 0),
                (float)($data['government_discount'] ?? 0),
                (int)$_SESSION['user']['user_id'],
                !empty($data['notes']) ? $data['notes'] : null,
            ]);

            $statementId = (int)$this->pdo->lastInsertId();

            $this->pdo->commit();

            (new ChargeSyncService($this->pdo))->syncStatement($statementId);

            $this->json(201, [
                'success'      => true,
                'message'      => 'Statement created and all charges pulled in.',
                'statement_id' => $statementId,
            ]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CashierController::createStatement] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not create statement.']);
        }
    }

    // =========================================================
    // GUARD + JSON
    // =========================================================

    public function guard(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405, ['success' => false, 'message' => 'Method not allowed.']);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user']) || !in_array((int)$_SESSION['user']['role_id'], [1, 4], true)) {
            $this->json(403, ['success' => false, 'message' => 'Access denied.']);
        }
    }

    public function input(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: $_POST;
    }

    public function json(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}