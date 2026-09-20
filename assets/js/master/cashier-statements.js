(function () {
    const baseUrl = document.body.dataset.baseUrl || "";

    const dataEl = document.getElementById('cashierData');
    const chargeItems  = dataEl ? JSON.parse(dataEl.dataset.chargeItems  || '[]') : [];
    const paymentTypes = dataEl ? JSON.parse(dataEl.dataset.paymentTypes || '[]') : [];

    let currentStatementId = null;
    let currentStatement   = null;

    function peso(n) {
        return '₱' + Number(n || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function fmtDate(v) {
        if (!v) return '—';
        const d = new Date(String(v).replace(' ', 'T'));
        return isNaN(d.getTime()) ? v : d.toLocaleString();
    }

    function showAlert(type, msg) {
        const el = document.getElementById('alert');
        if (!el) return;
        el.className = 'mb-5 rounded-lg px-4 py-3 text-sm border ' +
            (type === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-rose-200 bg-rose-50 text-rose-800');
        el.textContent = msg;
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 5000);
    }

    const searchInput  = document.getElementById('filterSearch');
    const statusFilter = document.getElementById('filterStatus');
    const clearBtn     = document.getElementById('filterClear');
    const rows         = document.querySelectorAll('.statement-row');
    const summary      = document.getElementById('filterSummary');
    const filteredCount= document.getElementById('filteredCount');
    const totalCount   = document.getElementById('totalCount');
    const emptyState   = document.getElementById('emptyState');

    function applyFilters() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        const status = statusFilter?.value || '';
        let visible = 0;

        rows.forEach(row => {
            const search    = row.dataset.search || '';
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

    const newStatementModal   = document.getElementById('newStatementModal');
    const newStatementForm    = document.getElementById('newStatementForm');
    const saveNewStatementBtn = document.getElementById('saveNewStatementBtn');
    const saveNewStatementLbl = document.getElementById('saveNewStatementLabel');

    function openNewStatementModal() {
        if (!newStatementModal) return;
        newStatementForm?.reset();
        newStatementModal.classList.remove('hidden');
    }
    function closeNewStatementModal() {
        newStatementModal?.classList.add('hidden');
    }

    document.getElementById('openCreateBtn')?.addEventListener('click', openNewStatementModal);
    document.getElementById('openCreateBtnBanner')?.addEventListener('click', openNewStatementModal);
    newStatementModal?.querySelectorAll('[data-close-new-statement]').forEach(el => {
        el.addEventListener('click', closeNewStatementModal);
    });

    saveNewStatementBtn?.addEventListener('click', async function () {
        const admissionId = document.getElementById('new_admission_id')?.value;
        if (!admissionId) { showAlert('error', 'Please select an admission.'); return; }

        saveNewStatementBtn.disabled = true;
        if (saveNewStatementLbl) saveNewStatementLbl.textContent = 'Creating…';

        try {
            const res = await axios.post(`${baseUrl}/api/cashier/create-statement.php`, {
                admission_id:              parseInt(admissionId, 10),
                due_date:                  document.getElementById('new_due_date')?.value || null,
                insurance_coverage_amount: document.getElementById('new_insurance_coverage_amount')?.value || 0,
                government_discount:       document.getElementById('new_government_discount')?.value || 0,
                notes:                     document.getElementById('new_notes')?.value.trim() || ''
            }, { headers: { 'Content-Type': 'application/json' } });

            if (res.data.success) {
                showAlert('success', res.data.message || 'Statement created.');
                closeNewStatementModal();
                setTimeout(() => location.reload(), 800);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not create statement.');
            saveNewStatementBtn.disabled = false;
            if (saveNewStatementLbl) saveNewStatementLbl.textContent = 'Create Statement';
        }
    });

    const manageModal = document.getElementById('manageModal');

    function openManageModal()  { manageModal?.classList.remove('hidden'); }
    function closeManageModal() {
        manageModal?.classList.add('hidden');
        currentStatementId = null;
        currentStatement   = null;
    }

    document.querySelectorAll('[data-close-manage]').forEach(el => {
        el.addEventListener('click', closeManageModal);
    });

    document.querySelectorAll('.manage-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.mtab;
            document.querySelectorAll('.manage-tab-btn').forEach(b => {
                b.classList.remove('border-blue-600', 'text-blue-600');
                b.classList.add('border-transparent', 'text-slate-500');
            });
            btn.classList.remove('border-transparent', 'text-slate-500');
            btn.classList.add('border-blue-600', 'text-blue-600');
            document.querySelectorAll('.manage-tab-panel').forEach(p => {
                p.classList.toggle('hidden', p.dataset.mpanel !== tab);
            });
        });
    });

    document.querySelectorAll('.manage-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.statementId;
            currentStatementId = parseInt(id, 10);
            openManageModal();
            await loadStatement(id);
        });
    });

    async function loadStatement(id) {
        try {
            const res = await axios.get(`${baseUrl}/api/cashier/get-statement.php?id=${id}`);
            if (!res.data.success) throw new Error(res.data.message || 'Failed to load');
            currentStatement = res.data.statement;
            renderStatement(currentStatement);
        } catch (err) {
            showAlert('error', err.response?.data?.message || err.message);
        }
    }

    function renderStatement(s) {
        const titleEl = document.getElementById('manageTitle');
        const subEl   = document.getElementById('manageSubtitle');
        if (titleEl) titleEl.textContent = `Statement #${s.statement_id}`;
        if (subEl)   subEl.textContent   = `${s.first_name} ${s.last_name} · Admission #${s.admission_id}`;

        const elSubtotal = document.getElementById('sumSubtotal');
        const elTax      = document.getElementById('sumTax');
        const elTotal    = document.getElementById('sumTotal');
        const elPaid     = document.getElementById('sumPaid');
        const elBalance  = document.getElementById('sumBalance');

        if (elSubtotal) elSubtotal.textContent = peso(s.subtotal_amount);
        if (elTax)      elTax.textContent      = peso(s.tax_amount);
        if (elTotal)    elTotal.textContent    = peso(s.total_amount);
        if (elPaid)     elPaid.textContent     = peso(s.amount_paid);
        if (elBalance)  elBalance.textContent  = peso(s.balance_amount);

        renderCharges(s);
        renderPayments(s);
        renderRooms(s);
    }

    function renderCharges(s) {
        const body = document.getElementById('chargesBody');
        const none = document.getElementById('noCharges');
        if (!body) return;
        body.innerHTML = '';

        const charges = Array.isArray(s.charges) ? s.charges : [];
        if (!charges.length) { none?.classList.remove('hidden'); return; }
        none?.classList.add('hidden');

        charges.forEach(c => {
            const isLocked = (c.notes || '').toLowerCase().includes('source=');
            const removeCell = isLocked
                ? `<td class="px-4 py-3 text-right">
                       <span class="inline-flex items-center text-xs text-slate-400">Locked</span>
                   </td>`
                : `<td class="px-4 py-3 text-right">
                       <button type="button"
                               class="remove-charge-btn text-rose-500 hover:text-rose-700"
                               data-charge-id="${c.charge_id}">Remove</button>
                   </td>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3">
                    <p class="font-medium text-slate-900">${c.item_name}</p>
                    <p class="text-xs text-slate-500">${c.item_code}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">${c.category_name}</td>
                <td class="px-4 py-3 text-center text-slate-700">${c.quantity}</td>
                <td class="px-4 py-3 text-right text-slate-700">${peso(c.actual_price)}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-900">${peso(c.line_total)}</td>
                ${removeCell}`;
            body.appendChild(tr);
        });

        body.querySelectorAll('.remove-charge-btn').forEach(b => {
            b.addEventListener('click', () => removeCharge(b.dataset.chargeId));
        });
    }

    function renderPayments(s) {
        const body = document.getElementById('paymentsBody');
        const none = document.getElementById('noPayments');
        if (!body) return;
        body.innerHTML = '';

        const payments = Array.isArray(s.payments) ? s.payments : [];
        if (!payments.length) { none?.classList.remove('hidden'); return; }
        none?.classList.add('hidden');

        payments.forEach(p => {
            const typeName = p.type_name || p.payment_type_name || '—';
            const ref      = p.transaction_reference || p.reference || '—';
            const when     = fmtDate(p.payment_datetime || p.payment_date);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3 text-slate-700">${typeName}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${ref}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${when}</td>
                <td class="px-4 py-3 text-right font-medium text-emerald-700">${peso(p.amount)}</td>
                <td class="px-4 py-3 text-right">
                    <button type="button"
                            class="remove-payment-btn text-rose-500 hover:text-rose-700"
                            data-payment-id="${p.payment_id}">Remove</button>
                </td>`;
            body.appendChild(tr);
        });

        body.querySelectorAll('.remove-payment-btn').forEach(b => {
            b.addEventListener('click', () => removePayment(b.dataset.paymentId));
        });
    }

    function renderRooms(s) {
        const body = document.getElementById('roomsBody');
        const none = document.getElementById('noRooms');
        if (!body) return;
        body.innerHTML = '';

        const rooms = Array.isArray(s.room_history) ? s.room_history : [];
        if (!rooms.length) { none?.classList.remove('hidden'); return; }
        none?.classList.add('hidden');

        rooms.forEach(r => {
            const end = r.end_datetime ? fmtDate(r.end_datetime) : 'Present';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3 font-medium text-slate-900">${r.room_number}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${r.room_type_name}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${fmtDate(r.start_datetime)}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${end}</td>
                <td class="px-4 py-3 text-right text-slate-700">${peso(r.daily_rate_at_assignment)}</td>`;
            body.appendChild(tr);
        });
    }

    async function removeCharge(chargeId) {
        if (!confirm('Remove this charge?')) return;
        try {
            const res = await axios.post(
                `${baseUrl}/api/cashier/remove-charge.php?id=${currentStatementId}&charge_id=${chargeId}`
            );
            if (res.data.success) {
                showAlert('success', res.data.message);
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not remove.');
        }
    }

    async function removePayment(paymentId) {
        if (!confirm('Remove this payment?')) return;
        try {
            const res = await axios.post(
                `${baseUrl}/api/cashier/remove-payment.php?id=${currentStatementId}&payment_id=${paymentId}`
            );
            if (res.data.success) {
                showAlert('success', res.data.message);
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not remove.');
        }
    }

    document.getElementById('syncChargesBtn')?.addEventListener('click', async function () {
        if (!currentStatementId) return;
        const btn = this;
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = 'Syncing…';
        try {
            const res = await axios.post(`${baseUrl}/api/cashier/sync-charges.php?id=${currentStatementId}`);
            if (res.data.success) {
                showAlert('success', res.data.message);
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Sync failed.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    const chargeModal      = document.getElementById('chargeModal');
    const chargeItemSelect = document.getElementById('charge_item_id');

    if (chargeItemSelect) {
        chargeItemSelect.innerHTML = '<option value="">— Select item —</option>';
        chargeItems.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.charge_item_id;
            opt.textContent = `[${c.category}] ${c.item_name} — ₱${Number(c.default_price).toFixed(2)}`;
            opt.dataset.price = c.default_price;
            chargeItemSelect.appendChild(opt);
        });
    }

    document.getElementById('addChargeBtn')?.addEventListener('click', () => {
        chargeModal?.classList.remove('hidden');
    });
    chargeModal?.querySelectorAll('[data-close-charge]').forEach(el => {
        el.addEventListener('click', () => chargeModal.classList.add('hidden'));
    });

    chargeItemSelect?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const priceEl = document.getElementById('charge_price');
        if (priceEl) priceEl.value = opt?.dataset?.price || '';
    });

    document.getElementById('saveChargeBtn')?.addEventListener('click', async function () {
        const itemId = chargeItemSelect?.value;
        const qty    = parseInt(document.getElementById('charge_quantity')?.value, 10);
        const notes  = document.getElementById('charge_notes')?.value;

        if (!itemId || !qty || qty <= 0) {
            showAlert('error', 'Please select an item and quantity.');
            return;
        }

        const btn = this;
        const label = document.getElementById('saveChargeLabel');
        btn.disabled = true;
        if (label) label.textContent = 'Saving…';

        try {
            const res = await axios.post(`${baseUrl}/api/cashier/add-charge.php?id=${currentStatementId}`, {
                charge_item_id: itemId,
                quantity: qty,
                notes: notes
            }, { headers: { 'Content-Type': 'application/json' } });

            if (res.data.success) {
                showAlert('success', res.data.message);
                chargeModal?.classList.add('hidden');
                if (chargeItemSelect) chargeItemSelect.value = '';
                const qEl = document.getElementById('charge_quantity');
                const pEl = document.getElementById('charge_price');
                const nEl = document.getElementById('charge_notes');
                if (qEl) qEl.value = 1;
                if (pEl) pEl.value = '';
                if (nEl) nEl.value = '';
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not add charge.');
        } finally {
            btn.disabled = false;
            if (label) label.textContent = 'Add Charge';
        }
    });

    const paymentModal      = document.getElementById('paymentModal');
    const paymentTypeSelect = document.getElementById('payment_type_id');

    if (paymentTypeSelect) {
        paymentTypeSelect.innerHTML = '<option value="">— Select type —</option>';
        paymentTypes.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.payment_type_id;
            opt.textContent = p.type_name;
            paymentTypeSelect.appendChild(opt);
        });
    }

    document.getElementById('addPaymentBtn')?.addEventListener('click', () => {
        if (currentStatement) {
            const balEl = document.getElementById('paymentCurrentBalance');
            const amtEl = document.getElementById('payment_amount');
            if (balEl) balEl.textContent = peso(currentStatement.balance_amount);
            if (amtEl) amtEl.value = Number(currentStatement.balance_amount).toFixed(2);
        }
        paymentModal?.classList.remove('hidden');
    });
    paymentModal?.querySelectorAll('[data-close-payment]').forEach(el => {
        el.addEventListener('click', () => paymentModal.classList.add('hidden'));
    });

    document.getElementById('savePaymentBtn')?.addEventListener('click', async function () {
        const typeId = paymentTypeSelect?.value;
        const amount = parseFloat(document.getElementById('payment_amount')?.value);
        const notes  = document.getElementById('payment_notes')?.value;

        if (!typeId || !amount || amount <= 0) {
            showAlert('error', 'Please select a payment type and enter a positive amount.');
            return;
        }

        const btn = this;
        const label = document.getElementById('savePaymentLabel');
        btn.disabled = true;
        if (label) label.textContent = 'Recording…';

        try {
            const res = await axios.post(`${baseUrl}/api/cashier/add-payment.php?id=${currentStatementId}`, {
                payment_type_id: typeId,
                amount: amount,
                notes: notes
            }, { headers: { 'Content-Type': 'application/json' } });

            if (res.data.success) {
                showAlert('success', res.data.message + (res.data.reference ? ` Ref: ${res.data.reference}` : ''));
                paymentModal?.classList.add('hidden');
                if (paymentTypeSelect) paymentTypeSelect.value = '';
                const amtEl = document.getElementById('payment_amount');
                const nEl   = document.getElementById('payment_notes');
                if (amtEl) amtEl.value = '';
                if (nEl)   nEl.value = '';
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not record payment.');
        } finally {
            btn.disabled = false;
            if (label) label.textContent = 'Record Payment';
        }
    });

    applyFilters();
})();