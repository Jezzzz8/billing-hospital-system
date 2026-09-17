// assets/js/master/cashier-payment-counter.js
(function () {
    const baseUrl = document.body.dataset.baseUrl;
    const alertEl = document.getElementById('counterAlert');

    function peso(n) {
        return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showAlert(type, msg) {
        alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border ' +
            (type === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-rose-200 bg-rose-50 text-rose-800');
        alertEl.textContent = msg;
        alertEl.classList.remove('hidden');
        setTimeout(() => alertEl.classList.add('hidden'), 4000);
    }

    // =========================================================
    // FILTERS
    // =========================================================
    const searchInput = document.getElementById('counterSearch');
    const statusFilter = document.getElementById('counterStatus');
    const clearBtn = document.getElementById('counterClear');
    const rows = document.querySelectorAll('.counter-row');
    const summary = document.getElementById('counterSummary');
    const filteredCount = document.getElementById('counterFilteredCount');
    const totalCount = document.getElementById('counterTotalCount');
    const emptyState = document.getElementById('counterEmpty');

    function applyFilters() {
        const q = (searchInput.value || '').toLowerCase().trim();
        const status = statusFilter.value;
        let visible = 0;

        rows.forEach(row => {
            const search = row.dataset.search || '';
            const rowStatus = row.dataset.status || '';
            const matchQ = !q || search.includes(q);
            const matchS = !status || rowStatus === status;
            if (matchQ && matchS) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        const filtering = q || status;
        if (filtering) {
            summary.classList.remove('hidden');
            clearBtn.classList.remove('hidden');
            clearBtn.classList.add('flex');
            filteredCount.textContent = visible;
            totalCount.textContent = rows.length;
        } else {
            summary.classList.add('hidden');
            clearBtn.classList.add('hidden');
            clearBtn.classList.remove('flex');
        }
        emptyState.classList.toggle('hidden', visible > 0);
    }

    searchInput?.addEventListener('input', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    clearBtn?.addEventListener('click', () => {
        searchInput.value = '';
        statusFilter.value = '';
        applyFilters();
    });

    // =========================================================
    // COLLECT MODAL
    // =========================================================
    const modal = document.getElementById('collectModal');
    const receiptModal = document.getElementById('receiptModal');

    let currentStatement = null;

    function openCollectModal() {
        modal.classList.remove('hidden');
    }
    function closeCollectModal() {
        modal.classList.add('hidden');
        currentStatement = null;
    }

    modal?.querySelectorAll('[data-close-collect]').forEach(el => {
        el.addEventListener('click', closeCollectModal);
    });
    receiptModal?.querySelectorAll('[data-close-receipt]').forEach(el => {
        el.addEventListener('click', () => receiptModal.classList.add('hidden'));
    });

    document.querySelectorAll('.collect-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.statementId;
            await loadStatement(id);
            openCollectModal();
        });
    });

    async function loadStatement(id) {
        try {
            const res = await axios.get(`${baseUrl}/api/cashier/get-statement-summary.php?id=${id}`);
            if (!res.data.success) throw new Error(res.data.message || 'Failed to load.');
            currentStatement = res.data.statement;
            renderStatement(currentStatement);
        } catch (err) {
            showAlert('error', err.response?.data?.message || err.message);
        }
    }

    function renderStatement(s) {
        document.getElementById('collectSubtitle').textContent =
            `${s.first_name} ${s.last_name} — Statement #${s.statement_id}`;
        document.getElementById('colStatement').textContent = '#' + s.statement_id;
        document.getElementById('colPatient').textContent = `${s.first_name} ${s.last_name}`;
        document.getElementById('colContact').textContent = s.contact_number || '—';
        document.getElementById('colTotal').textContent = peso(s.total_amount);
        document.getElementById('colPaid').textContent = peso(s.amount_paid);
        document.getElementById('colBalance').textContent = peso(s.balance_amount);

        const amountInput = document.getElementById('col_amount');
        amountInput.value = Number(s.balance_amount).toFixed(2);
        amountInput.max = Number(s.balance_amount).toFixed(2);

        document.getElementById('col_payment_type_id').value = '';
        document.getElementById('col_notes').value = '';

        updatePaymentPreview();
    }

    function updatePaymentPreview() {
        if (!currentStatement) return;
        const amount = parseFloat(document.getElementById('col_amount').value) || 0;
        const balance = parseFloat(currentStatement.balance_amount) || 0;
        const after = Math.max(0, balance - amount);

        document.getElementById('colBalanceBefore').textContent = peso(balance);
        document.getElementById('colThisPayment').textContent = peso(amount);
        document.getElementById('colBalanceAfter').textContent = peso(after);

        const hint = document.getElementById('colAmountHint');
        if (amount <= 0) {
            hint.textContent = '';
        } else if (amount >= balance) {
            hint.textContent = 'Full payment — the statement will be marked as Paid.';
            hint.className = 'mt-1 text-xs text-emerald-600';
        } else {
            hint.textContent = `Partial payment — ${peso(after)} will remain.`;
            hint.className = 'mt-1 text-xs text-amber-600';
        }
    }

    document.getElementById('col_amount')?.addEventListener('input', updatePaymentPreview);
    document.getElementById('colFullBtn')?.addEventListener('click', () => {
        if (!currentStatement) return;
        document.getElementById('col_amount').value = Number(currentStatement.balance_amount).toFixed(2);
        updatePaymentPreview();
    });

    // =========================================================
    // SUBMIT
    // =========================================================
    document.getElementById('confirmCollectBtn')?.addEventListener('click', async function () {
        if (!currentStatement) return;
        if (this.dataset.busy === '1') return;
        this.dataset.busy = '1';

        const btn = this;
        const label = document.getElementById('confirmCollectLabel');
        const paymentTypeId = document.getElementById('col_payment_type_id').value;
        const amount = parseFloat(document.getElementById('col_amount').value) || 0;
        const notes = document.getElementById('col_notes').value.trim();

        if (!paymentTypeId) { showAlert('error', 'Please select a payment type.'); btn.dataset.busy = '0'; return; }
        if (amount <= 0) { showAlert('error', 'Enter a positive amount.'); btn.dataset.busy = '0'; return; }

        const balance = parseFloat(currentStatement.balance_amount) || 0;
        if (amount > balance + 0.001) {
            showAlert('error', `Amount exceeds the outstanding balance of ${peso(balance)}.`);
            btn.dataset.busy = '0';
            return;
        }

        const statementId = currentStatement.statement_id;
        const patientName = `${currentStatement.first_name} ${currentStatement.last_name}`;
        const previousBalance = balance;
        const newBalance = Math.max(0, previousBalance - amount);

        btn.disabled = true;
        label.textContent = 'Recording…';

        try {
            const res = await axios.post(
                `${baseUrl}/api/cashier/add-payment.php?id=${statementId}`,
                {
                    payment_type_id: parseInt(paymentTypeId, 10),
                    amount: amount,
                    notes: notes,
                },
                { headers: { 'Content-Type': 'application/json' } }
            );

            if (res.data.success) {
                closeCollectModal();
                showAlert('success', res.data.message);

                document.getElementById('receiptSubtitle').textContent =
                    `Statement #${statementId} — ${patientName}`;
                document.getElementById('receiptReference').textContent =
                    res.data.reference || '—';
                document.getElementById('receiptAmount').textContent = peso(amount);
                document.getElementById('receiptNewBalance').textContent = peso(newBalance);
                document.getElementById('receiptPrintBtn').href =
                    `${baseUrl}/index.php?page=cashier-receipt&id=${statementId}`;
                receiptModal.classList.remove('hidden');

                setTimeout(() => location.reload(), 1500);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not record payment.');
            btn.disabled = false;
            label.textContent = 'Record Payment';
            btn.dataset.busy = '0';
        }
    });
})();