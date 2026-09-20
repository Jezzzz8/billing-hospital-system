(function () {
    const baseUrl = document.body.dataset.baseUrl || "";
    const alertEl = document.getElementById('counterAlert');

    function peso(n) {
        return '₱' + Number(n || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showAlert(type, msg) {
        if (!alertEl) return;
        alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border ' +
            (type === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-rose-200 bg-rose-50 text-rose-800');
        alertEl.textContent = msg;
        alertEl.classList.remove('hidden');
        setTimeout(() => alertEl.classList.add('hidden'), 5000);
    }

    const searchInput   = document.getElementById('counterSearch');
    const statusFilter  = document.getElementById('counterStatus');
    const clearBtn      = document.getElementById('counterClear');
    const rows          = document.querySelectorAll('.counter-row');
    const summary       = document.getElementById('counterSummary');
    const filteredCount = document.getElementById('counterFilteredCount');
    const totalCount    = document.getElementById('counterTotalCount');
    const emptyState    = document.getElementById('counterEmpty');

    function applyFilters() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        const status = statusFilter?.value || '';
        let visible = 0;

        rows.forEach(row => {
            const search    = row.dataset.search || '';
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const matchQ = !q || search.includes(q);
            const matchS = !status || rowStatus === status.toLowerCase();

            if (matchQ && matchS) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        const filtering = q || status;
        if (filtering) {
            summary?.classList.remove('hidden');
            clearBtn?.classList.remove('hidden');
            clearBtn?.classList.add('flex');
            if (filteredCount) filteredCount.textContent = visible;
            if (totalCount)    totalCount.textContent    = rows.length;
        } else {
            summary?.classList.add('hidden');
            clearBtn?.classList.add('hidden');
            clearBtn?.classList.remove('flex');
        }
        emptyState?.classList.toggle('hidden', visible > 0);
    }

    searchInput?.addEventListener('input', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    clearBtn?.addEventListener('click', () => {
        if (searchInput)  searchInput.value  = '';
        if (statusFilter) statusFilter.value = '';
        applyFilters();
    });

    const modal        = document.getElementById('collectModal');
    const receiptModal = document.getElementById('receiptModal');

    let currentStatement = null;

    function openCollectModal()  { modal?.classList.remove('hidden'); }
    function closeCollectModal() {
        modal?.classList.add('hidden');
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
            if (currentStatement) openCollectModal();
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
        if (!s) return;

        const elSubtitle  = document.getElementById('collectSubtitle');
        const elStatement = document.getElementById('colStatement');
        const elPatient   = document.getElementById('colPatient');
        const elContact   = document.getElementById('colContact');
        const elSubtotal  = document.getElementById('colSubtotal');
        const elTax       = document.getElementById('colTax');
        const elTotal     = document.getElementById('colTotal');
        const elPaid      = document.getElementById('colPaid');
        const elBalance   = document.getElementById('colBalance');

        if (elSubtitle)  elSubtitle.textContent  = `${s.first_name} ${s.last_name} — Statement #${s.statement_id}`;
        if (elStatement) elStatement.textContent = '#' + s.statement_id;
        if (elPatient)   elPatient.textContent   = `${s.first_name} ${s.last_name}`;
        if (elContact)   elContact.textContent   = s.contact_number || '—';
        if (elSubtotal)  elSubtotal.textContent  = peso(s.subtotal_amount);
        if (elTax)       elTax.textContent       = peso(s.tax_amount);
        if (elTotal)     elTotal.textContent     = peso(s.total_amount);
        if (elPaid)      elPaid.textContent      = peso(s.amount_paid);
        if (elBalance)   elBalance.textContent   = peso(s.balance_amount);

        const amountInput = document.getElementById('col_amount');
        if (amountInput) {
            amountInput.value = Number(s.balance_amount).toFixed(2);
            amountInput.max   = Number(s.balance_amount).toFixed(2);
        }

        const ptSel = document.getElementById('col_payment_type_id');
        if (ptSel) ptSel.value = '';
        const notesEl = document.getElementById('col_notes');
        if (notesEl) notesEl.value = '';

        updatePaymentPreview();
    }

    function updatePaymentPreview() {
        if (!currentStatement) return;

        const amount  = parseFloat(document.getElementById('col_amount')?.value) || 0;
        const balance = parseFloat(currentStatement.balance_amount) || 0;
        const after   = Math.max(0, balance - amount);

        const beforeEl = document.getElementById('colBalanceBefore');
        const thisEl   = document.getElementById('colThisPayment');
        const afterEl  = document.getElementById('colBalanceAfter');

        if (beforeEl) beforeEl.textContent = peso(balance);
        if (thisEl)   thisEl.textContent   = peso(amount);
        if (afterEl)  afterEl.textContent  = peso(after);

        const hint = document.getElementById('colAmountHint');
        if (!hint) return;
        if (amount <= 0) {
            hint.textContent = '';
            hint.className = 'mt-1 text-xs text-slate-500';
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
        const amt = document.getElementById('col_amount');
        if (amt) amt.value = Number(currentStatement.balance_amount).toFixed(2);
        updatePaymentPreview();
    });

    document.getElementById('confirmCollectBtn')?.addEventListener('click', async function () {
        if (!currentStatement) return;
        if (this.dataset.busy === '1') return;
        this.dataset.busy = '1';

        const btn   = this;
        const label = document.getElementById('confirmCollectLabel');
        const paymentTypeId = document.getElementById('col_payment_type_id')?.value;
        const amount = parseFloat(document.getElementById('col_amount')?.value) || 0;
        const notes  = document.getElementById('col_notes')?.value.trim() || '';

        if (!paymentTypeId) { showAlert('error', 'Please select a payment type.'); btn.dataset.busy = '0'; return; }
        if (amount <= 0)    { showAlert('error', 'Enter a positive amount.');     btn.dataset.busy = '0'; return; }

        const balance = parseFloat(currentStatement.balance_amount) || 0;
        if (amount > balance + 0.001) {
            showAlert('error', `Amount exceeds the outstanding balance of ${peso(balance)}.`);
            btn.dataset.busy = '0';
            return;
        }

        const statementId     = currentStatement.statement_id;
        const patientName     = `${currentStatement.first_name} ${currentStatement.last_name}`;
        const previousBalance = balance;
        const newBalance      = Math.max(0, previousBalance - amount);

        btn.disabled = true;
        if (label) label.textContent = 'Recording…';

        try {
            const res = await axios.post(
                `${baseUrl}/api/cashier/add-payment.php?id=${statementId}`,
                {
                    payment_type_id: parseInt(paymentTypeId, 10),
                    amount: amount,
                    notes: notes
                },
                { headers: { 'Content-Type': 'application/json' } }
            );

            if (res.data.success) {
                closeCollectModal();
                showAlert('success', res.data.message);

                const subEl = document.getElementById('receiptSubtitle');
                const refEl = document.getElementById('receiptReference');
                const amtEl = document.getElementById('receiptAmount');
                const balEl = document.getElementById('receiptNewBalance');
                const prnEl = document.getElementById('receiptPrintBtn');

                if (subEl) subEl.textContent = `Statement #${statementId} — ${patientName}`;
                if (refEl) refEl.textContent = res.data.reference || '—';
                if (amtEl) amtEl.textContent = peso(amount);
                if (balEl) balEl.textContent = peso(newBalance);
                if (prnEl) prnEl.href = `${baseUrl}/index.php?page=cashier-receipt&id=${statementId}`;

                receiptModal?.classList.remove('hidden');

                setTimeout(() => location.reload(), 1500);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not record payment.');
            btn.disabled = false;
            if (label) label.textContent = 'Record Payment';
            btn.dataset.busy = '0';
        }
    });

    applyFilters();
})();