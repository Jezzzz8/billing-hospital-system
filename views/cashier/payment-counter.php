<?php
// views/cashier/payment-counter.php

require_once __DIR__ . '/../../controllers/CashierController.php';

$controller = new CashierController($pdo);
$statements = $controller->getUnpaidStatements();
$paymentTypes = $controller->getPaymentTypes();

$totalUnpaid = 0;
foreach ($statements as $s) $totalUnpaid += (float)$s['balance_amount'];
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Payment Counter</h1>
    <p class="mt-1 text-sm text-slate-500">
        Search for a patient or statement, then collect the payment against their existing bill.
    </p>
</div>

<div id="counterAlert" class="hidden mb-5 rounded-lg px-4 py-3 text-sm border"></div>

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Unpaid Statements</p>
        <p class="mt-2 text-3xl font-bold text-slate-900"><?= count($statements) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Outstanding</p>
        <p class="mt-2 text-2xl font-bold text-rose-600">₱<?= number_format($totalUnpaid, 2) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Today</p>
        <?php
        $todayCount = 0;
        foreach ($statements as $s) {
            if (date('Y-m-d', strtotime($s['statement_date'])) === date('Y-m-d')) $todayCount++;
        }
        ?>
        <p class="mt-2 text-3xl font-bold text-blue-600"><?= $todayCount ?></p>
    </div>
</div>

<!-- Search + filter bar -->
<div class="bg-white rounded-xl border border-slate-200 p-4 mb-4">
    <div class="flex flex-col lg:flex-row lg:items-end gap-3">
        <div class="flex-1">
            <label class="block text-xs font-medium text-slate-500 mb-1.5">Search</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="counterSearch" placeholder="Search patient name, contact, or statement #…"
                       class="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1.5">Status</label>
            <select id="counterStatus"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All unpaid</option>
                <?php
                $seen = [];
                foreach ($statements as $s) {
                    if (isset($seen[$s['status_name']])) continue;
                    $seen[$s['status_name']] = true;
                    echo '<option value="' . htmlspecialchars($s['status_name']) . '">'
                        . htmlspecialchars($s['status_name']) . '</option>';
                }
                ?>
            </select>
        </div>

        <button type="button" id="counterClear"
                class="hidden items-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Clear
        </button>
    </div>

    <div id="counterSummary" class="hidden mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500">
        Showing <strong id="counterFilteredCount" class="text-slate-900">0</strong> of
        <strong id="counterTotalCount" class="text-slate-900">0</strong> statements
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Statement</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Patient</th>
                    <th class="text-right px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Total</th>
                    <th class="text-right px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Paid</th>
                    <th class="text-right px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Balance</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Due</th>
                    <th class="text-left px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Status</th>
                    <th class="text-right px-6 py-3.5 font-semibold text-slate-700 text-xs uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($statements as $s):
                    $search = strtolower(
                        $s['first_name'] . ' ' . $s['last_name'] . ' #' . $s['statement_id']
                        . ' ' . ($s['contact_number'] ?? '')
                        . ' ' . ($s['email'] ?? '')
                    );
                    $isOverdue = !empty($s['due_date'])
                        && $s['due_date'] < date('Y-m-d')
                        && (float)$s['balance_amount'] > 0;
                ?>
                    <tr class="hover:bg-slate-50 counter-row"
                        data-statement-id="<?= (int)$s['statement_id'] ?>"
                        data-search="<?= htmlspecialchars($search) ?>"
                        data-status="<?= htmlspecialchars($s['status_name']) ?>">

                        <td class="px-6 py-4">
                            <p class="font-semibold text-slate-900">#<?= (int)$s['statement_id'] ?></p>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars(date('M j, Y', strtotime($s['statement_date']))) ?></p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></p>
                            <p class="text-xs text-slate-500">
                                <?= htmlspecialchars($s['contact_number'] ?? $s['email'] ?? '') ?>
                            </p>
                        </td>
                        <td class="px-6 py-4 text-right text-slate-700 whitespace-nowrap">
                            ₱<?= number_format((float)$s['total_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 text-right text-emerald-700 whitespace-nowrap">
                            ₱<?= number_format((float)$s['amount_paid'], 2) ?>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-rose-700 whitespace-nowrap">
                            ₱<?= number_format((float)$s['balance_amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!empty($s['due_date'])): ?>
                                <span class="text-xs <?= $isOverdue ? 'text-rose-700 font-semibold' : 'text-slate-600' ?>">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($s['due_date']))) ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                    <p class="text-xs text-rose-500">Overdue</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                  style="background-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>1A;
                                         color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>;
                                         border-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>40;">
                                <span class="w-1.5 h-1.5 rounded-full" style="background-color: <?= htmlspecialchars($s['color_code'] ?? '#6b7280') ?>"></span>
                                <?= htmlspecialchars($s['status_name']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <button type="button"
                                    class="collect-btn inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                                    data-statement-id="<?= (int)$s['statement_id'] ?>">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/>
                                </svg>
                                Collect Payment
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="counterEmpty" class="hidden text-center py-16">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 text-slate-400 mb-3">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-sm font-medium text-slate-700">No unpaid statements match your filters</p>
        <p class="text-xs text-slate-500 mt-1">Try adjusting your search.</p>
    </div>
</div>

<!-- Collect Payment Modal -->
<div id="collectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60" data-close-collect></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[95vh] flex flex-col">

        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Collect Payment</h3>
                    <p id="collectSubtitle" class="text-xs text-slate-500">Select a payment type and enter the amount.</p>
                </div>
            </div>
            <button type="button" data-close-collect class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="collectForm" class="flex-1 overflow-y-auto p-6 space-y-4">

            <!-- Statement summary -->
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Statement</span>
                    <span id="colStatement" class="font-semibold text-slate-900">#—</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Patient</span>
                    <span id="colPatient" class="font-medium text-slate-800">—</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Contact</span>
                    <span id="colContact" class="text-slate-700">—</span>
                </div>
                <div class="border-t border-slate-200 my-2"></div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Total</span>
                    <span id="colTotal" class="text-slate-700">₱0.00</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500">Already Paid</span>
                    <span id="colPaid" class="text-emerald-700">₱0.00</span>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                    <span class="text-sm font-semibold text-slate-900">Balance Due</span>
                    <span id="colBalance" class="text-xl font-bold text-rose-700">₱0.00</span>
                </div>
            </div>

            <!-- Payment type -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Payment Type</label>
                <select id="col_payment_type_id" required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm bg-white
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Select type —</option>
                    <?php foreach ($paymentTypes as $pt): ?>
                        <option value="<?= (int)$pt['payment_type_id'] ?>"><?= htmlspecialchars($pt['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Amount -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-slate-700">Amount (₱)</label>
                    <button type="button" id="colFullBtn" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                        Pay full balance
                    </button>
                </div>
                <input type="number" id="col_amount" required min="0.01" step="0.01"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-lg font-semibold
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p id="colAmountHint" class="mt-1 text-xs text-slate-500"></p>
            </div>

            <!-- Auto-generated reference -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Reference</label>
                <input type="text" id="col_reference" readonly
                       value="Will be generated automatically"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-mono text-slate-500 cursor-not-allowed">
                <p class="mt-1 text-xs text-slate-500">Reference is generated by the system on save.</p>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Notes <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <input type="text" id="col_notes"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Live payment summary -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 space-y-1.5">
                <div class="flex justify-between text-xs text-slate-600">
                    <span>Balance before</span>
                    <span id="colBalanceBefore" class="font-medium text-slate-900">₱0.00</span>
                </div>
                <div class="flex justify-between text-xs text-slate-600">
                    <span>This payment</span>
                    <span id="colThisPayment" class="font-medium text-emerald-700">₱0.00</span>
                </div>
                <div class="flex justify-between border-t border-blue-100 pt-2">
                    <span class="text-sm font-semibold text-slate-900">Balance after</span>
                    <span id="colBalanceAfter" class="text-lg font-bold text-blue-700">₱0.00</span>
                </div>
            </div>
        </form>

        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex items-center justify-end gap-3">
            <button type="button" data-close-collect
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </button>
            <button type="button" id="confirmCollectBtn"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span id="confirmCollectLabel">Record Payment</span>
            </button>
        </div>
    </div>
</div>

<!-- Receipt Preview Modal (shown after success) -->
<div id="receiptModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60" data-close-receipt></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 mb-3">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900">Payment Recorded</h3>
            <p id="receiptSubtitle" class="text-sm text-slate-500 mt-1">—</p>
        </div>

        <div class="bg-slate-50 rounded-lg p-4 space-y-1.5 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Reference</span>
                <span id="receiptReference" class="font-mono text-slate-900 text-xs">—</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Amount</span>
                <span id="receiptAmount" class="font-semibold text-slate-900">₱0.00</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">New Balance</span>
                <span id="receiptNewBalance" class="font-bold text-emerald-700">₱0.00</span>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="button" data-close-receipt
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Close
            </button>
            <a id="receiptPrintBtn" href="#" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print Receipt
            </a>
        </div>
    </div>
</div>