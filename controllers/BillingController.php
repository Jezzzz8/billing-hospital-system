<?php

require_once __DIR__ . '/ChargeSyncService.php';

class BillingController
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function getAll(): array
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

    public function getDetails(int $statementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bs.*, bst.status_name, bst.color_code, bst.is_paid_status,
                    p.first_name, p.last_name, p.patient_id,
                    a.admission_datetime, a.chief_complaint
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
                    c.charge_datetime, c.notes,
                    ci.item_code, ci.item_name, ci.unit_of_measure,
                    (c.quantity * c.actual_price) AS line_total
             FROM `charge` c
             INNER JOIN `charge_item` ci ON ci.charge_item_id = c.charge_item_id
             WHERE c.statement_id = ?
             ORDER BY c.charge_id'
        );
        $stmt->execute([$statementId]);
        $statement['charges'] = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT p.payment_id, p.payment_type_id, p.amount, p.payment_datetime,
                    p.transaction_reference, p.notes,
                    pt.type_name
             FROM `payment` p
             INNER JOIN `payment_type` pt ON pt.payment_type_id = p.payment_type_id
             WHERE p.statement_id = ?
             ORDER BY p.payment_id'
        );
        $stmt->execute([$statementId]);
        $statement['payments'] = $stmt->fetchAll();

        return $statement;
    }

    public function getBillingStatuses(): array
    {
        return $this->pdo->query('SELECT status_id, status_name, color_code FROM `billing_status` ORDER BY status_id')->fetchAll();
    }

    public function getPaymentTypes(): array
    {
        return $this->pdo->query('SELECT payment_type_id, type_name FROM `payment_type` ORDER BY type_name')->fetchAll();
    }

    public function getChargeItems(): array
    {
        return $this->pdo->query(
            'SELECT ci.charge_item_id, ci.item_code, ci.item_name, ci.default_price,
                    ci.is_taxable, ci.unit_of_measure, cc.category_name
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE ci.is_active = 1
             ORDER BY ci.item_name'
        )->fetchAll();
    }

    public function getAdmissionsWithoutStatement(): array
    {
        return $this->pdo->query(
            'SELECT a.admission_id, a.admission_datetime, a.chief_complaint,
                    p.first_name, p.last_name
            FROM `admission` a
            INNER JOIN `patient` p ON p.patient_id = a.patient_id
            INNER JOIN `admission_status` ast ON ast.status_id = a.status_id
            LEFT JOIN `billing_statement` bs ON bs.admission_id = a.admission_id
            WHERE bs.statement_id IS NULL
            AND ast.status_name = "Ready for Discharge"
            ORDER BY a.admission_datetime DESC'
        )->fetchAll();
    }

    public function create(): void
    {
        $data   = $this->input();
        $errors = $this->validateStatement($data, null);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO `billing_statement`
                    (admission_id, status_id, statement_date, due_date,
                     subtotal_amount, insurance_coverage_amount, government_discount,
                     tax_amount, total_amount, amount_paid, balance_amount,
                     created_by_user_id, notes)
                 VALUES (?, ?, ?, ?, 0, ?, ?, 0, 0, 0, 0, ?, ?)'
            );

            $stmt->execute([
                (int)$data['admission_id'],
                (int)($data['status_id'] ?? 1),
                !empty($data['statement_date']) ? $data['statement_date'] : date('Y-m-d H:i:s'),
                !empty($data['due_date']) ? $data['due_date'] : null,
                (float)($data['insurance_coverage_amount'] ?? 0),
                (float)($data['government_discount'] ?? 0),
                (int)$_SESSION['user']['user_id'],
                !empty($data['notes']) ? $data['notes'] : null,
            ]);

            $statementId = (int)$this->pdo->lastInsertId();

            (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);

            $this->pdo->commit();

            $this->json(201, [
                'success'      => true,
                'message'      => 'Statement created successfully.',
                'statement_id' => $statementId,
            ]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[BillingController::create] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not create statement.']);
        }
    }

    public function update(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing statement id.']);

        $stmt = $this->pdo->prepare('SELECT statement_id FROM `billing_statement` WHERE statement_id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) $this->json(404, ['success' => false, 'message' => 'Statement not found.']);

        $data   = $this->input();
        $errors = $this->validateStatement($data, $id);
        if ($errors) $this->json(422, ['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors]);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE `billing_statement`
                 SET insurance_coverage_amount = ?,
                     government_discount       = ?,
                     due_date                  = ?,
                     notes                     = ?
                 WHERE statement_id = ?'
            );
            $stmt->execute([
                (float)($data['insurance_coverage_amount'] ?? 0),
                (float)($data['government_discount'] ?? 0),
                !empty($data['due_date']) ? $data['due_date'] : null,
                !empty($data['notes']) ? $data['notes'] : null,
                $id,
            ]);

            (new ChargeSyncService($this->pdo))->recomputeStatement($id);

            if (isset($data['status_id']) && (int)$data['status_id'] > 0) {
                $stmt = $this->pdo->prepare(
                    'UPDATE `billing_statement` SET status_id = ? WHERE statement_id = ?'
                );
                $stmt->execute([(int)$data['status_id'], $id]);
            }

            $this->pdo->commit();

            $this->json(200, ['success' => true, 'message' => 'Statement updated successfully.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[BillingController::update] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not update statement.']);
        }
    }

    public function delete(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(400, ['success' => false, 'message' => 'Missing statement id.']);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `payment` WHERE statement_id = ?');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $this->json(409, [
                'success' => false,
                'message' => 'Cannot delete — this statement has recorded payments.',
            ]);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `charge` WHERE statement_id = ?');
            $stmt->execute([$id]);

            $stmt = $this->pdo->prepare('DELETE FROM `billing_statement` WHERE statement_id = ?');
            $stmt->execute([$id]);

            $this->pdo->commit();
            $this->json(200, ['success' => true, 'message' => 'Statement deleted.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->json(500, ['success' => false, 'message' => 'Could not delete statement.']);
        }
    }

    public function addCharge(): void
    {
        $id   = (int)($_GET['id'] ?? 0);
        $data = $this->input();

        $itemId   = (int)($data['charge_item_id'] ?? 0);
        $quantity = (int)($data['quantity'] ?? 1);

        if ($id <= 0 || $itemId <= 0 || $quantity <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Missing or invalid charge data.']);
        }

        $stmt = $this->pdo->prepare(
            'SELECT ci.charge_item_id, ci.default_price, ci.item_name, ci.is_taxable,
                    cc.is_recurring
             FROM `charge_item` ci
             INNER JOIN `charge_category` cc ON cc.category_id = ci.category_id
             WHERE ci.charge_item_id = ? AND ci.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) $this->json(404, ['success' => false, 'message' => 'Charge item not found or inactive.']);

        $price = isset($data['actual_price']) && is_numeric($data['actual_price'])
            ? (float)$data['actual_price']
            : (float)$item['default_price'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO `charge`
                    (statement_id, charge_item_id, quantity, actual_price,
                     charge_datetime, processed_by_user_id, notes,
                     service_start_date, service_end_date)
                 VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
            );
            $stmt->execute([
                $id,
                $itemId,
                $quantity,
                $price,
                (int)$_SESSION['user']['user_id'],
                !empty($data['notes']) ? $data['notes'] : null,
                !empty($data['service_start_date']) ? $data['service_start_date'] : null,
                !empty($data['service_end_date'])   ? $data['service_end_date']   : null,
            ]);

            (new ChargeSyncService($this->pdo))->recomputeStatement($id);

            $this->pdo->commit();
            $this->json(201, ['success' => true, 'message' => 'Charge added.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[addCharge] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not add charge.']);
        }
    }

    public function removeCharge(): void
    {
        $statementId = (int)($_GET['id'] ?? 0);
        $chargeId    = (int)($_GET['charge_id'] ?? 0);
        if ($statementId <= 0 || $chargeId <= 0) {
            $this->json(400, ['success' => false, 'message' => 'Missing ids.']);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `charge` WHERE charge_id = ? AND statement_id = ?');
            $stmt->execute([$chargeId, $statementId]);

            (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);

            $this->pdo->commit();
            $this->json(200, ['success' => true, 'message' => 'Charge removed.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->json(500, ['success' => false, 'message' => 'Could not remove charge.']);
        }
    }

    public function addPayment(): void
    {
        $id   = (int)($_GET['id'] ?? 0);
        $data = $this->input();

        $typeId = (int)($data['payment_type_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);

        if ($id <= 0 || $typeId <= 0 || $amount <= 0) {
            $this->json(422, ['success' => false, 'message' => 'Please provide a payment type and a positive amount.']);
        }

        try {
            $this->pdo->beginTransaction();

            $reference = $this->generatePaymentReference();

            $stmt = $this->pdo->prepare(
                'INSERT INTO `payment`
                    (statement_id, payment_type_id, amount, payment_datetime,
                    transaction_reference, received_by_user_id, notes)
                VALUES (?, ?, ?, NOW(), ?, ?, ?)'
            );
            $stmt->execute([
                $id,
                $typeId,
                $amount,
                $reference,
                (int)$_SESSION['user']['user_id'],
                !empty($data['notes']) ? $data['notes'] : null,
            ]);

            (new ChargeSyncService($this->pdo))->recomputeStatement($id);

            $this->pdo->commit();

            $this->json(201, [
                'success'   => true,
                'message'   => 'Payment recorded.',
                'reference' => $reference,
            ]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[addPayment] ' . $e->getMessage());
            $this->json(500, ['success' => false, 'message' => 'Could not record payment.']);
        }
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

    public function removePayment(): void
    {
        $statementId = (int)($_GET['id'] ?? 0);
        $paymentId   = (int)($_GET['payment_id'] ?? 0);
        if ($statementId <= 0 || $paymentId <= 0) {
            $this->json(400, ['success' => false, 'message' => 'Missing ids.']);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('DELETE FROM `payment` WHERE payment_id = ? AND statement_id = ?');
            $stmt->execute([$paymentId, $statementId]);

            (new ChargeSyncService($this->pdo))->recomputeStatement($statementId);

            $this->pdo->commit();
            $this->json(200, ['success' => true, 'message' => 'Payment removed.']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->json(500, ['success' => false, 'message' => 'Could not remove payment.']);
        }
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: $_POST;
    }

    private function validateStatement(array $data, ?int $ignoreId): array
    {
        $errors = [];

        if (empty($data['admission_id']) || (int)$data['admission_id'] <= 0) {
            $errors['admission_id'] = 'Please select an admission.';
        } else {
            $sql = 'SELECT statement_id FROM `billing_statement` WHERE admission_id = ?';
            $args = [(int)$data['admission_id']];
            if ($ignoreId !== null) { $sql .= ' AND statement_id <> ?'; $args[] = $ignoreId; }
            $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($args);
            if ($stmt->fetch()) {
                $errors['admission_id'] = 'This admission already has a billing statement.';
            }
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