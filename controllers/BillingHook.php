<?php
// controllers/BillingHook.php

require_once __DIR__ . '/ChargeSyncService.php';

class BillingHook
{
    /**
     * Deferred queue — events that need to fire after the current
     * transaction commits. Keyed by statement_id to dedupe.
     */
    private static array $deferred = [];

    /**
     * Called whenever a billable event happens.
     * - If a statement exists AND we're not in a transaction → sync now.
     * - If a statement exists AND we're in a transaction → defer until commit.
     * - If no statement → do nothing (source tables act as the queue).
     */
    public static function emit(PDO $pdo, int $admissionId): void
    {
        if ($admissionId <= 0) return;

        try {
            $stmt = $pdo->prepare(
                'SELECT statement_id FROM `billing_statement`
                 WHERE admission_id = ? LIMIT 1'
            );
            $stmt->execute([$admissionId]);
            $statementId = (int)$stmt->fetchColumn();

            if ($statementId <= 0) return; // no statement → nothing to sync

            // Inside an active transaction? Defer to avoid nested transaction error.
            if ($pdo->inTransaction()) {
                self::$deferred[$statementId] = $pdo;
                return;
            }

            (new ChargeSyncService($pdo))->syncStatement($statementId);
        } catch (Throwable $e) {
            error_log('[BillingHook::emit] ' . $e->getMessage());
        }
    }

    /**
     * Call this after $pdo->commit() to flush any deferred syncs.
     */
    public static function flushDeferred(): void
    {
        if (empty(self::$deferred)) return;

        $queue = self::$deferred;
        self::$deferred = [];

        foreach ($queue as $statementId => $pdo) {
            try {
                (new ChargeSyncService($pdo))->syncStatement($statementId);
            } catch (Throwable $e) {
                error_log('[BillingHook::flushDeferred] ' . $e->getMessage());
            }
        }
    }
}