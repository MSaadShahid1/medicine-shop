/* Dashboard Studio: local interactions over a server-rendered, read-only snapshot. */
(() => {
    'use strict';
    if (!document.body.classList.contains('cx-dashboard')) return;
    const body = document.body;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const motionAllowed = () => !reduced.matches && body.dataset.wxMotion !== 'paused' && !document.hidden;
    const formatMoney = value => 'Rs. ' + Number(value || 0).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // All original content stays visible if JavaScript or the chart CDN is unavailable.
    let snapshot = null;
    try { snapshot = JSON.parse(document.getElementById('cxDashboardData').textContent); }
    catch (_) { /* Search, navigation and real server-rendered totals still work. */ }

    // Search, type filters and sorting share a single state; no network requests.
    const tbody = document.querySelector('#txnTable tbody');
    const rows = Array.from(document.querySelectorAll('#txnTable tbody tr[data-type]'));
    const empty = document.getElementById('txnFilterEmpty');
    const status = document.getElementById('txnFilterStatus');
    const search = document.getElementById('cxTxnSearch');
    const sort = document.getElementById('cxTxnSort');
    const buttons = Array.from(document.querySelectorAll('.filter-pill[data-filter]'));
    const originalOrder = new Map(rows.map((row, index) => [row, index]));
    const searchText = new Map(rows.map(row => [row, row.textContent.toLowerCase()]));
    let selectedType = 'all';
    function refreshTransactions() {
        if (!tbody) return;
        const query = (search ? search.value.trim().toLowerCase() : '');
        const ordered = rows.slice().sort((a, b) => {
            const delta = Number(a.dataset.amount) - Number(b.dataset.amount);
            if (sort && sort.value === 'amount-desc' && delta) return -delta;
            if (sort && sort.value === 'amount-asc' && delta) return delta;
            return originalOrder.get(a) - originalOrder.get(b);
        });
        let matches = 0;
        ordered.forEach(row => {
            row.hidden = !((selectedType === 'all' || row.dataset.type === selectedType) && searchText.get(row).includes(query));
            if (!row.hidden) matches++;
            tbody.insertBefore(row, empty);
        });
        if (empty) empty.hidden = matches > 0 || rows.length === 0;
        if (status) status.textContent = matches + ' of ' + rows.length + ' recent transactions match.';
        buttons.forEach(button => {
            const active = button.dataset.filter === selectedType;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    }
    buttons.forEach(button => button.addEventListener('click', () => { selectedType = button.dataset.filter; refreshTransactions(); }));
    if (search) search.addEventListener('input', refreshTransactions);
    if (sort) sort.addEventListener('change', refreshTransactions);
    document.querySelectorAll('.cx-table-toolbar, .ld-filter-pills').forEach(element => element.hidden = false);

    // Three/six-month views use the same exact source data as the accessible table.
    const chartElement = document.getElementById('trendChart');
    const fallback = document.getElementById('trendFallback');
    const periodButtons = Array.from(document.querySelectorAll('[data-chart-months]'));
    const tableRows = Array.from(document.querySelectorAll('[data-trend-row]'));
    let chart = null;
    let chartReady = false;
    let period = 6;
    let printing = false;
    let fallbackWasOpen = true;
    const trend = snapshot && snapshot.trend;
    const validTrend = trend && ['labels', 'sales', 'purchases'].every(key => Array.isArray(trend[key]) && trend[key].length === 6)
        && trend.sales.concat(trend.purchases).every(value => Number.isFinite(Number(value)));
    function showFallback() {
        chartReady = false;
        if (chartElement) chartElement.hidden = true;
        if (fallback) fallback.open = true;
    }
    function applyPeriod() {
        if (!validTrend) return;
        const sales = trend.sales.slice(-period).map(Number);
        const purchases = trend.purchases.slice(-period).map(Number);
        const labels = trend.labels.slice(-period);
        document.getElementById('cxTrendSales').textContent = formatMoney(sales.reduce((a, b) => a + b, 0));
        document.getElementById('cxTrendPurchases').textContent = formatMoney(purchases.reduce((a, b) => a + b, 0));
        document.getElementById('cxTrendSalesLabel').textContent = 'Sales · ' + period + ' months';
        document.getElementById('cxTrendPurchasesLabel').textContent = 'Purchases · ' + period + ' months';
        tableRows.forEach((row, index) => row.hidden = index < tableRows.length - period);
        periodButtons.forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.chartMonths) === period)));
        const caption = fallback && fallback.querySelector('caption');
        if (caption) caption.textContent = 'Actual totals for the last ' + period + ' calendar months, including this month';
        if (chartReady) {
            try {
                Promise.resolve(chart.updateOptions({ xaxis: { categories: labels }, series: [{ name: 'Sales', data: sales }, { name: 'Purchases', data: purchases }] }, false, false)).catch(showFallback);
            } catch (_) { showFallback(); }
        }
    }
    if (validTrend) {
        periodButtons.forEach(button => button.addEventListener('click', () => { period = Number(button.dataset.chartMonths) === 3 ? 3 : 6; applyPeriod(); }));
        document.querySelectorAll('.cx-chart-controls').forEach(element => element.hidden = false);
        if (chartElement && fallback && typeof window.ApexCharts === 'function') {
            try {
                chartElement.hidden = false;
                chart = new window.ApexCharts(chartElement, {
                    chart: { type: 'area', height: 230, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, animations: { enabled: motionAllowed(), speed: 650 }, dropShadow: { enabled: false } },
                    series: [{ name: 'Sales', data: trend.sales }, { name: 'Purchases', data: trend.purchases }],
                    colors: ['#8965b5', '#d6b559'],
                    stroke: { curve: 'straight', width: 2.5 },
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.22, opacityTo: 0.015, stops: [0, 100] } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#ece5f2', strokeDashArray: 3, padding: { left: 0, right: 8 } },
                    xaxis: { categories: trend.labels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#766681', fontSize: '10px' }, rotate: -35, hideOverlappingLabels: true } },
                    yaxis: { labels: { style: { colors: '#766681', fontSize: '10px' }, formatter: value => 'Rs. ' + Number(value).toLocaleString('en-PK', { maximumFractionDigits: 0 }) } },
                    legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px', markers: { radius: 12 } },
                    tooltip: { y: { formatter: formatMoney } }
                });
                const renderChart = () => Promise.resolve(chart.render()).then(() => { chartReady = true; fallback.open = false; applyPeriod(); }).catch(showFallback);
                // Draw on arrival so the line animation is seen rather than spent off screen.
                if (motionAllowed() && 'IntersectionObserver' in window) {
                    const chartObserver = new IntersectionObserver(entries => {
                        if (!entries.some(entry => entry.isIntersecting)) return;
                        chartObserver.disconnect();
                        renderChart();
                    }, { rootMargin: '0px 0px -10% 0px' });
                    chartObserver.observe(chartElement.closest('.ld-chart-card') || chartElement);
                } else renderChart();
            } catch (_) { showFallback(); }
        }
    }

    // Scroll arrivals live in js/dashboard-motion.js (GSAP), which also settles them when motion is off.

    // Pointer paint is limited to one scheduled frame and one hovered card.
    let pointerFrame = 0;
    let pendingPointer = null;
    const cards = Array.from(document.querySelectorAll('.cx-kpi-card, .ld-card'));
    function clearPointer() {
        if (pointerFrame) cancelAnimationFrame(pointerFrame);
        pointerFrame = 0;
        pendingPointer = null;
        cards.forEach(card => { card.style.removeProperty('--cx-pointer-x'); card.style.removeProperty('--cx-pointer-y'); });
    }
    cards.forEach(card => {
        card.addEventListener('pointermove', event => {
            if (!motionAllowed() || !finePointer.matches) return;
            pendingPointer = { card, x: event.clientX, y: event.clientY };
            if (pointerFrame) return;
            pointerFrame = requestAnimationFrame(() => {
                pointerFrame = 0;
                if (!pendingPointer || !motionAllowed()) return;
                const { card: target, x, y } = pendingPointer;
                const rect = target.getBoundingClientRect();
                target.style.setProperty('--cx-pointer-x', (x - rect.left) + 'px');
                target.style.setProperty('--cx-pointer-y', (y - rect.top) + 'px');
            });
        }, { passive: true });
        card.addEventListener('pointerleave', clearPointer, { passive: true });
    });
    function syncMotion() {
        if (!motionAllowed() || printing) clearPointer();
    }
    window.addEventListener('workspace:preferences', syncMotion);
    document.addEventListener('visibilitychange', syncMotion);
    if (reduced.addEventListener) reduced.addEventListener('change', syncMotion);
    window.addEventListener('beforeprint', () => {
        printing = true;
        syncMotion();
        if (fallback) { fallbackWasOpen = fallback.open; fallback.open = true; }
    });
    window.addEventListener('afterprint', () => {
        printing = false;
        if (fallback) fallback.open = fallbackWasOpen;
    });
})();
