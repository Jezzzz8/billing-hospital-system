<?php
// views/cashier/receipt.php

require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$statementId = (int)($_GET['id'] ?? 0);
$statement = $statementId ? $controller->getStatementDetails($statementId) : null;

if (!$statement) {
    echo '<div class="p-12 text-center"><p class="text-lg font-semibold">Statement not found.</p></div>';
    return;
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Statement #<?= (int)$statement['statement_id'] ?></h1>
        <p class="text-sm text-slate-500">Generated <?= htmlspecialchars(date('M j, Y g:i A', strtotime($statement['statement_date']))) ?></p>
    </div>
    <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 print:hidden">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Print Receipt
    </button>
</div>

<div class="bg-white rounded-xl border border-slate-200 p-8 max-w-3xl mx-auto">

    <!-- Header -->
    <div class="text-center mb-8 pb-6 border-b border-slate-200">
        <h1 class="text-2xl font-bold text-slate-900">Billing Hospital</h1>
        <p class="text-sm text-slate-500 mt-1">Official Billing Statement</p>
    </div>

    <!-- Patient Info -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Patient</p>
            <p class="font-semibold text-slate-900"><?= htmlspecialchars($statement['first_name'] . ' ' . $statement['last_name']) ?></p>
            <?php if ($statement['contact_number']): ?>
                <p class="text-sm text-slate-600"><?= htmlspecialchars($statement['contact_number']) ?></p>
            <?php endif; ?>
            <?php if ($statement['address']): ?>
                <p class="text-sm text-slate-600"><?= htmlspecialchars($statement['address']) ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Statement</p>
            <p class="font-semibold text-slate-900">#<?= (int)$statement['statement_id'] ?></p>
            <p class="text-sm text-slate-600">Admission #<?= (int)$statement['admission_id'] ?></p>
            <p class="text-sm text-slate-600">Admitted: <?= htmlspecialchars(date('M j, Y', strtotime($statement['admission_datetime']))) ?></p>
            <?php if ($statement['discharge_datetime']): ?>
                <p class="text-sm text-slate-600">Discharged: <?= htmlspecialchars(date('M j, Y', strtotime($statement['discharge_datetime']))) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charges Table -->
    <div class="mb-8">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Charges</p>
        <?php if (empty($statement['charges'])): ?>
            <p class="text-center text-sm text-slate-400 py-6">No charges on this statement.</p>
        <?php else: ?>
            <table class="min-w-full text-sm">
                <thead class="border-b-2 border-slate-200">
                    <tr>
                        <th class="text-left py-2 font-medium text-slate-600">Item</th>
                        <th class="text-left py-2 font-medium text-slate-600">Category</th>
                        <th class="text-center py-2 font-medium text-slate-600">Qty</th>
                        <th class="text-right py-2 font-medium text-slate-600">Unit</th>
                        <th class="text-right py-2 font-medium text-slate-600">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($statement['charges'] as $c): ?>
                        <tr>
                            <td class="py-2">
                                <p class="font-medium text-slate-900"><?= htmlspecialchars($c['item_name']) ?></p>
                                <?php if (!empty($c['service_start_date'])): ?>
                                    <p class="text-xs text-slate-500">
                                        <?= htmlspecialchars($c['service_start_date']) ?>
                                        <?= $c['service_end_date'] ? ' to ' . htmlspecialchars($c['service_end_date']) : '' ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-slate-600 text-xs"><?= htmlspecialchars($c['category_name']) ?></td>
                            <td class="py-2 text-center text-slate-700"><?= (int)$c['quantity'] ?></td>
                            <td class="py-2 text-right text-slate-700">₱<?= number_format((float)$c['actual_price'], 2) ?></td>
                            <td class="py-2 text-right font-medium text-slate-900">₱<?= number_format((float)$c['line_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Totals -->
    <div class="border-t-2 border-slate-200 pt-4 mb-8">
        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Subtotal</span>
                    <span class="text-slate-900">₱<?= number_format((float)$statement['subtotal_amount'], 2) ?></span>
                </div>
                <?php if ((float)$statement['tax_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Tax (12%)</span>
                        <span class="text-slate-900">₱<?= number_format((float)$statement['tax_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$statement['insurance_coverage_amount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Insurance Coverage</span>
                        <span class="text-emerald-700">- ₱<?= number_format((float)$statement['insurance_coverage_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$statement['government_discount'] > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">Government Discount</span>
                        <span class="text-emerald-700">- ₱<?= number_format((float)$statement['government_discount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-base font-bold border-t border-slate-200 pt-2">
                    <span class="text-slate-900">Total</span>
                    <span class="text-slate-900">₱<?= number_format((float)$statement['total_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600">Amount Paid</span>
                    <span class="text-emerald-700">₱<?= number_format((float)$statement['amount_paid'], 2) ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold">
                    <span class="text-slate-900">Balance Due</span>
                    <span class="<?= (float)$statement['balance_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' ?>">
                        ₱<?= number_format((float)$statement['balance_amount'], 2) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments -->
    <?php if (!empty($statement['payments'])): ?>
        <div class="mb-8">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Payments</p>
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200">
                    <tr>
                        <th class="text-left py-2 font-medium text-slate-600">Date</th>
                        <th class="text-left py-2 font-medium text-slate-600">Type</th>
                        <th class="text-left py-2 font-medium text-slate-600">Reference</th>
                        <th class="text-right py-2 font-medium text-slate-600">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($statement['payments'] as $p): ?>
                        <tr>
                            <td class="py-2 text-slate-700 text-xs"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($p['payment_datetime']))) ?></td>
                            <td class="py-2 text-slate-700"><?= htmlspecialchars($p['type_name']) ?></td>
                            <td class="py-2 text-slate-600 text-xs"><?= htmlspecialchars($p['transaction_reference'] ?? '—') ?></td>
                            <td class="py-2 text-right font-medium text-emerald-700">₱<?= number_format((float)$p['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center pt-6 border-t border-slate-200 text-xs text-slate-500">
        <p>Thank you for choosing Billing Hospital.</p>
        <p class="mt-1">Statement generated <?= htmlspecialchars(date('M j, Y g:i A', strtotime($statement['statement_date']))) ?></p>
    </div>
</div>

<style media="print">
    body { background: white !important; }
    .lg\:pl-64 { padding-left: 0 !important; }
    aside, header, nav, button, [data-close-manage], #sidebarToggle, #sidebarOverlay { display: none !important; }
    main { padding: 0 !important; max-width: none !important; }
    .print\:hidden { display: none !important; }
</style>