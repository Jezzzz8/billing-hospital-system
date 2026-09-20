<?php

require_once __DIR__ . '/ChargeSyncService.php';

class CashierController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

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
                    bs.subtotal_amount, bs.tax_amount, bs.insurance_coverage_amount,
                    bs.government_discount, bs.total_amount, bs.amount_paid, bs.balance_amount,
                    bst.status_name, bst.color_code, bst.is_paid_status,
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

        $stmt = $this->pdo->prepare(
            'SELECT c.charge_id, c.charge_item_id, c.quantity, c.actual_price,
                    c.charge_datetime, c.notes, c.service_start_date, c.service_end_date,
                    ci.item_code, ci.item_name, ci.unit_of_measure, ci.is_taxable,
                    cc.category_name,
                    (c.quantity * c.actual_price) AS line_total,
                    CASE WHEN ci.is_taxable = 1 THEN ROUND((c.quantity * c.actual_price) * 0.12, 2) ELSE 0 END AS line_tax,
                    CASE WHEN ci.is_taxable = 1
                         THEN ROUND((c.quantity * c.actual_price) * 1.12, 2)
                         ELSE (c.quantity * c.actual_price) END AS line_total_with_tax
             FROM `charge` c
             INNER JOIN `charge_item` ci ON ci.charge_item_id = c.charge_item_id
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE c.statement_id = ?
             ORDER BY c.charge_id'
        );
        $stmt->execute([$statementId]);
        $statement['charges'] = $stmt->fetchAll();

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
        $stmt = $this->pdo->prepare(
            'SELECT a.admission_id, a.admission_datetime, a.chief_complaint,
                    p.first_name, p.last_name, p.patient_id,
                    ast.status_name AS admission_status_name,
                    ast.color_code  AS admission_status_color
             FROM `admission` a
             INNER JOIN `patient` p ON p.patient_id = a.patient_id
             INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
             LEFT JOIN `billing_statement` bs ON bs.admission_id = a.admission_id
             WHERE bs.statement_id IS NULL
               AND ast.status_name = "Ready for Discharge"
             ORDER BY a.admission_datetime DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function syncChargesFromAdmission(int $statementId): void
    {
        (new ChargeSyncService($this->pdo))->syncStatement($statementId);
    }

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

        (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);
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

        (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);
    }

    public function addPayment(int $statementId, array $data): string
    {
        $typeId = (int)($data['payment_type_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);

        if ($typeId <= 0 || $amount <= 0) {
            throw new Exception('Please provide a payment type and a positive amount.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT total_amount, amount_paid, balance_amount FROM `billing_statement`
             WHERE statement_id = ? LIMIT 1'
        );
        $stmt->execute([$statementId]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception('Statement not found.');

        $balance = (float)$row['balance_amount'];
        if ($amount > $balance + 0.001) {
            throw new Exception(sprintf(
                'Amount exceeds the outstanding balance of ₱%s.',
                number_format($balance, 2)
            ));
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

            (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);

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

        (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);
    }

    private function generatePaymentReference(): string
    {
        $date   = date('Ymd');
        $prefix = 'PAY-' . $date . '-';

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

            (new ChargeSyncService($this->pdo))->syncStatement($statementId);

            $this->pdo->commit();

            $this->json(201, [
                'success'      => true,
                'message'      => 'Statement created and all charges pulled in.',
                'statement_id' => $statementId,
            ]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[CashierController::createStatement] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not create statement: ' . $e->getMessage()]);
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