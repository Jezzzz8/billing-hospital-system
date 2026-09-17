// assets/js/master/cashier-statements.js
(function () {
    const baseUrl = document.body.dataset.baseUrl;
    const alertEl = document.getElementById('alert');

    // -------- Data from PHP --------
    const dataEl = document.getElementById('cashierData');
    const chargeItems  = dataEl ? JSON.parse(dataEl.dataset.chargeItems  || '[]') : [];
    const paymentTypes = dataEl ? JSON.parse(dataEl.dataset.paymentTypes || '[]') : [];

    // -------- State --------
    let currentStatementId = null;
    let currentStatement   = null;

    // -------- Helpers --------
    function peso(n) {
        return '₱' + Number(n || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function fmtDate(v) {
        if (!v) return '—';
        const d = new Date(v.replace(' ', 'T'));   // safe for "YYYY-MM-DD HH:MM:SS"
        return isNaN(d.getTime()) ? v : d.toLocaleString();
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

    // -------- Filters --------
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

    // =========================================================
    // Manage Modal
    // =========================================================
    const manageModal = document.getElementById('manageModal');

    function openManageModal() {
        manageModal.classList.remove('hidden');
    }
    function closeManageModal() {
        manageModal.classList.add('hidden');
        currentStatementId = null;
        currentStatement   = null;
    }

    document.querySelectorAll('[data-close-manage]').forEach(el => {
        el.addEventListener('click', closeManageModal);
    });

    // Manage tabs
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

    // Manage button clicks
    document.querySelectorAll('.manage-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.statementId;
            currentStatementId = parseInt(id, 10);
            await loadStatement(id);
            openManageModal();
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
        document.getElementById('manageTitle').textContent = `Statement #${s.statement_id}`;
        document.getElementById('manageSubtitle').textContent =
            `${s.first_name} ${s.last_name} · Admission #${s.admission_id}`;

        document.getElementById('sumSubtotal').textContent = peso(s.subtotal_amount);
        document.getElementById('sumTax').textContent      = peso(s.tax_amount);
        document.getElementById('sumTotal').textContent    = peso(s.total_amount);
        document.getElementById('sumPaid').textContent     = peso(s.amount_paid);
        document.getElementById('sumBalance').textContent  = peso(s.balance_amount);

        renderCharges(s);
        renderPayments(s);
        renderRooms(s);
    }

    // -------- Charges panel --------
    function renderCharges(s) {
        const body = document.getElementById('chargesBody');
        const none = document.getElementById('noCharges');
        body.innerHTML = '';

        const charges = Array.isArray(s.charges) ? s.charges : [];
        if (!charges.length) {
            none.classList.remove('hidden');
            return;
        }
        none.classList.add('hidden');

        charges.forEach(c => {
            const isLocked = (c.notes || '').toLowerCase().includes('source=');

            const removeCell = isLocked
                ? `<td class="px-4 py-3 text-right">
                       <span class="inline-flex items-center gap-1 text-xs text-slate-400"
                             title="System-generated — cannot be removed">
                           Locked
                       </span>
                   </td>`
                : `<td class="px-4 py-3 text-right">
                       <button type="button"
                               class="remove-charge-btn text-rose-500 hover:text-rose-700"
                               data-charge-id="${c.charge_id}">
                           <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                               <path stroke-linecap="round" stroke-linejoin="round"
                                     d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                           </svg>
                       </button>
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

    // -------- Payments panel (FIX) --------
    function renderPayments(s) {
        const body = document.getElementById('paymentsBody');
        const none = document.getElementById('noPayments');
        body.innerHTML = '';

        const payments = Array.isArray(s.payments) ? s.payments : [];

        if (!payments.length) {
            none.classList.remove('hidden');
            return;
        }
        none.classList.add('hidden');

        payments.forEach(p => {
            const typeName = p.type_name || p.payment_type_name || '—';
            const ref      = p.transaction_reference || p.reference || '—';
            const when     = fmtDate(p.payment_datetime || p.payment_date);
            const amount   = peso(p.amount);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3 text-slate-700">${typeName}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${ref}</td>
                <td class="px-4 py-3 text-slate-600 text-xs">${when}</td>
                <td class="px-4 py-3 text-right font-medium text-emerald-700">${amount}</td>
                <td class="px-4 py-3 text-right">
                    <button type="button"
                            class="remove-payment-btn text-rose-500 hover:text-rose-700"
                            data-payment-id="${p.payment_id}">
                        <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </td>`;
            body.appendChild(tr);
        });

        body.querySelectorAll('.remove-payment-btn').forEach(b => {
            b.addEventListener('click', () => removePayment(b.dataset.paymentId));
        });
    }

    // -------- Rooms panel --------
    function renderRooms(s) {
        const body = document.getElementById('roomsBody');
        const none = document.getElementById('noRooms');
        body.innerHTML = '';

        const rooms = Array.isArray(s.room_history) ? s.room_history : [];
        if (!rooms.length) {
            none.classList.remove('hidden');
            return;
        }
        none.classList.add('hidden');

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

    // -------- Remove charge / payment --------
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

    // =========================================================
    // Sync Charges
    // =========================================================
    document.getElementById('syncChargesBtn')?.addEventListener('click', async function () {
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

    // =========================================================
    // Add Charge Modal
    // =========================================================
    const chargeModal = document.getElementById('chargeModal');
    const chargeItemSelect = document.getElementById('charge_item_id');
    chargeItems.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.charge_item_id;
        opt.textContent = `[${c.category}] ${c.item_name} — ₱${Number(c.default_price).toFixed(2)}`;
        opt.dataset.price = c.default_price;
        opt.dataset.taxable = c.is_taxable;
        chargeItemSelect.appendChild(opt);
    });

    document.getElementById('addChargeBtn')?.addEventListener('click', () => {
        chargeModal.classList.remove('hidden');
    });
    chargeModal?.querySelectorAll('[data-close-charge]').forEach(el => {
        el.addEventListener('click', () => chargeModal.classList.add('hidden'));
    });

    chargeItemSelect?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        document.getElementById('charge_price').value = opt.dataset.price || '';
    });

    document.getElementById('saveChargeBtn')?.addEventListener('click', async function () {
        const itemId = chargeItemSelect.value;
        const qty = parseInt(document.getElementById('charge_quantity').value, 10);
        const notes = document.getElementById('charge_notes').value;
        if (!itemId || qty <= 0) {
            showAlert('error', 'Please select an item and quantity.');
            return;
        }

        const btn = this;
        const label = document.getElementById('saveChargeLabel');
        btn.disabled = true;
        label.textContent = 'Saving…';

        try {
            const res = await axios.post(`${baseUrl}/api/cashier/add-charge.php?id=${currentStatementId}`, {
                charge_item_id: itemId,
                quantity: qty,
                notes: notes
            }, { headers: { 'Content-Type': 'application/json' } });

            if (res.data.success) {
                showAlert('success', res.data.message);
                chargeModal.classList.add('hidden');
                chargeItemSelect.value = '';
                document.getElementById('charge_quantity').value = 1;
                document.getElementById('charge_price').value = '';
                document.getElementById('charge_notes').value = '';
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not add charge.');
        } finally {
            btn.disabled = false;
            label.textContent = 'Add Charge';
        }
    });

    // =========================================================
    // Add Payment Modal
    // =========================================================
    const paymentModal = document.getElementById('paymentModal');
    const paymentTypeSelect = document.getElementById('payment_type_id');
    paymentTypes.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.payment_type_id;
        opt.textContent = p.type_name;
        paymentTypeSelect.appendChild(opt);
    });

    document.getElementById('addPaymentBtn')?.addEventListener('click', () => {
        if (currentStatement) {
            document.getElementById('paymentCurrentBalance').textContent = peso(currentStatement.balance_amount);
            document.getElementById('payment_amount').value = Number(currentStatement.balance_amount).toFixed(2);
        }
        paymentModal.classList.remove('hidden');
    });
    paymentModal?.querySelectorAll('[data-close-payment]').forEach(el => {
        el.addEventListener('click', () => paymentModal.classList.add('hidden'));
    });

    document.getElementById('savePaymentBtn')?.addEventListener('click', async function () {
        const typeId = paymentTypeSelect.value;
        const amount = parseFloat(document.getElementById('payment_amount').value);
        const notes = document.getElementById('payment_notes').value;

        if (!typeId || amount <= 0) {
            showAlert('error', 'Please select a payment type and enter a positive amount.');
            return;
        }

        const btn = this;
        const label = document.getElementById('savePaymentLabel');
        btn.disabled = true;
        label.textContent = 'Recording…';

        try {
            const res = await axios.post(`${baseUrl}/api/cashier/add-payment.php?id=${currentStatementId}`, {
                payment_type_id: typeId,
                amount: amount,
                notes: notes
            }, { headers: { 'Content-Type': 'application/json' } });

            if (res.data.success) {
                showAlert('success', res.data.message);
                paymentModal.classList.add('hidden');
                paymentTypeSelect.value = '';
                document.getElementById('payment_amount').value = '';
                document.getElementById('payment_notes').value = '';
                await loadStatement(currentStatementId);
            }
        } catch (err) {
            showAlert('error', err.response?.data?.message || 'Could not record payment.');
        } finally {
            btn.disabled = false;
            label.textContent = 'Record Payment';
        }
    });

})();   