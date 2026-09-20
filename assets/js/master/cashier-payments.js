(function () {
    const search = document.getElementById('paymentSearch');
    const rows   = document.querySelectorAll('.payment-row');

    if (!search) return;

    search.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        rows.forEach(r => {
            r.style.display = (!q || (r.dataset.search || '').includes(q)) ? '' : 'none';
        });
    });
})();