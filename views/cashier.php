<?php
// views/cashier.php

require_once __DIR__ . '/../controllers/CashierController.php';

$controller = new CashierController($pdo);
$stats      = $controller->getDashboardStats();
$recent     = $controller->getRecentStatements(10);
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Statements, payments, and collections at a glance.</p>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Collected Today</p>
        <p class="mt-2 text-2xl font-bold text-emerald-600">₱<?= number_format($stats['collected_today'], 2) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Pending Statements</p>
        <p class="mt-2 text-2xl font-bold text-amber-600"><?= $stats['pending_statements'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Overdue</p>
        <p class="mt-2 text-2xl font-bold text-rose-600"><?= $stats['overdue'] ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Outstanding Balance</p>
        <p class="mt-2 text-xl font-bold text-rose-700">₱<?= number_format($stats['total_outstanding'], 2) ?></p>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <a href="<?= BASE_URL ?>/index.php?page=cashier-statements"
       class="flex items-center gap-4 bg-white rounded-xl border border-slate-200 p-5 hover:border-blue-300 hover:shadow-sm transition">
        <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-slate-900">View Statements</p>
            <p class="text-xs text-slate-500">Manage all billing statements</p>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/index.php?page=cashier-payments"
       class="flex items-center gap-4 bg-white rounded-xl border border-slate-200 p-5 hover:border-emerald-300 hover:shadow-sm transition">
        <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-slate-900">Payments</p>
            <p class="text-xs text-slate-500">Record and view payments</p>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/index.php?page=cashier-reports"
       class="flex items-center gap-4 bg-white rounded-xl border border-slate-200 p-5 hover:border-violet-300 hover:shadow-sm transition">
        <div class="w-12 h-12 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-slate-900">Reports</p>
            <p class="text-xs text-slate-500">Collections and balances</p>
        </div>
    </a>
</div>

<!-- Recent Statements -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Recent Statements</h3>
            <p class="text-xs text-slate-500 mt-0.5">Latest 10 billing statements</p>
        </div>
        <a href="<?= BASE_URL ?>/index.php?page=cashier-statements" class="text-sm font-medium text-blue-600 hover:text-blue-700">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left px-6 py-3 font-medium">Statement</th>
                    <th class="text-left px-6 py-3 font-medium">Patient</th>
                    <th class="text-right px-6 py-3 font-medium">Total</th>
                    <th class="text-right px-6 py-3 font-medium">Paid</th>
                    <th class="text-right px-6 py-3 font-medium">Balance</th>
                    <th class="text-left px-6 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($recent)): ?>
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No statements yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $s): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3">
                                <p class="font-medium text-slate-900">#<?= (int)$s['statement_id'] ?></p>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars(date('M j, Y', strtotime($s['statement_date']))) ?></p>
                            </td>
                            <td class="px-6 py-3">
                                <p class="font-medium text-slate-900"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></p>
                                <p class="text-xs text-slate-500">Admission #<?= (int)$s['admission_id'] ?></p>
                            </td>
                            <td class="px-6 py-3 text-right font-medium text-slate-900 whitespace-nowrap">
                                ₱<?= number_format((float)$s['total_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-3 text-right text-emerald-700 whitespace-nowrap">
                                ₱<?= number_format((float)$s['amount_paid'], 2) ?>
                            </td>
                            <td class="px-6 py-3 text-right font-semibold whitespace-nowrap <?= (float)$s['balance_amount'] > 0 ? 'text-rose-700' : 'text-slate-400' ?>">
                                ₱<?= number_format((float)$s['balance_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                      style="background-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>1A;
                                             color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>;
                                             border-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>40;">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>"></span>
                                    <?= htmlspecialchars($s['status_name']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>