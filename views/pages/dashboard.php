<?php
// Dashboard page
$db = getDB();
$user = currentUser();
$hour = (int)date('H');
if ($hour < 11) $greeting = 'Selamat Pagi';
elseif ($hour < 15) $greeting = 'Selamat Siang';
elseif ($hour < 18) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';
?>
<div style="margin-bottom:var(--sp-5);">
    <h2 style="font-size:var(--fs-xl);font-weight:800;color:var(--text-primary);letter-spacing:-0.02em;">👋 <?= $greeting ?>, <?= htmlspecialchars($user['name']) ?>!</h2>
    <p class="text-secondary" style="font-size:var(--fs-sm);">Berikut ringkasan bisnis Anda hari ini</p>
</div>

<div class="kpi-grid" id="kpiGrid" style="grid-template-columns:repeat(5,1fr);">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--accent-muted);color:var(--accent);">
            <i data-lucide="dollar-sign" style="width:22px;height:22px;"></i>
        </div>
        <div class="kpi-value" id="kpiRevenue">
            <div class="skeleton" style="height:32px;width:140px;"></div>
        </div>
        <div class="kpi-label">Omzet Hari Ini</div>
        <div class="kpi-change" id="kpiRevenueChange">—</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--success-muted);color:var(--success);">
            <i data-lucide="trending-up" style="width:22px;height:22px;"></i>
        </div>
        <div class="kpi-value" id="kpiProfit" style="color:var(--success);">
            <div class="skeleton" style="height:32px;width:120px;"></div>
        </div>
        <div class="kpi-label">Profit Hari Ini</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--info-muted);color:var(--info);">
            <i data-lucide="shopping-bag" style="width:22px;height:22px;"></i>
        </div>
        <div class="kpi-value" id="kpiTransactions">
            <div class="skeleton" style="height:32px;width:60px;"></div>
        </div>
        <div class="kpi-label">Transaksi Hari Ini</div>
        <div class="kpi-change" id="kpiTransChange">—</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--secondary-muted);color:var(--secondary);">
            <i data-lucide="package" style="width:22px;height:22px;"></i>
        </div>
        <div class="kpi-value" id="kpiProducts">
            <div class="skeleton" style="height:32px;width:60px;"></div>
        </div>
        <div class="kpi-label">Total SKU Aktif</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--warning-muted);color:var(--warning);">
            <i data-lucide="alert-triangle" style="width:22px;height:22px;"></i>
        </div>
        <div class="kpi-value" id="kpiLowStock">
            <div class="skeleton" style="height:32px;width:40px;"></div>
        </div>
        <div class="kpi-label">Low Stock Alert</div>
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="trending-up" style="width:18px;height:18px;"></i> Grafik Penjualan (7 Hari)</h3>
        </div>
        <div class="chart-container" id="salesChartContainer">
            <div class="skeleton" style="height:280px;width:100%;"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="trophy" style="width:18px;height:18px;"></i> Top 5 Produk</h3>
        </div>
        <div id="topProductsList" style="margin-top:var(--sp-2);">
            <div class="skeleton" style="height:20px;width:100%;margin-bottom:12px;"></div>
            <div class="skeleton" style="height:20px;width:80%;margin-bottom:12px;"></div>
            <div class="skeleton" style="height:20px;width:60%;"></div>
        </div>
    </div>
</div>

<div class="bottom-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="receipt" style="width:18px;height:18px;"></i> Transaksi Terbaru</h3>
            <a href="<?= APP_URL ?>/index.php?page=transactions" class="btn btn-ghost btn-sm">Lihat Semua →</a>
        </div>
        <div id="recentTransactions">
            <div class="skeleton" style="height:16px;width:100%;margin-bottom:10px;"></div>
            <div class="skeleton" style="height:16px;width:90%;margin-bottom:10px;"></div>
            <div class="skeleton" style="height:16px;width:85%;"></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-lucide="alert-triangle" style="width:18px;height:18px;"></i> Low Stock Alert</h3>
            <?php if(isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=inventory" class="btn btn-ghost btn-sm">Kelola →</a>
            <?php endif; ?>
        </div>
        <div id="lowStockList">
            <div class="skeleton" style="height:16px;width:100%;margin-bottom:10px;"></div>
            <div class="skeleton" style="height:16px;width:80%;margin-bottom:10px;"></div>
            <div class="skeleton" style="height:16px;width:70%;"></div>
        </div>
    </div>
</div>

<script>
const APP = '<?= APP_URL ?>';
function formatRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function animateValue(el, start, end, duration, formatter) {
    if (start === end) { el.textContent = formatter ? formatter(end) : end; return; }
    const startTime = performance.now();
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + (end - start) * eased);
        el.textContent = formatter ? formatter(current) : current;
        if (progress < 1) requestAnimationFrame(update);
        else el.textContent = formatter ? formatter(end) : end;
    }
    requestAnimationFrame(update);
}

let salesChart = null;

async function loadDashboard() {
    try {
        const res = await fetch(APP + '/index.php?page=api&action=dashboard-stats');
        const d = await res.json();

        animateValue(document.getElementById('kpiRevenue'), 0, d.today_revenue, 800, formatRp);
        animateValue(document.getElementById('kpiProfit'), 0, d.today_profit || 0, 800, formatRp);
        animateValue(document.getElementById('kpiTransactions'), 0, d.today_count, 600);
        animateValue(document.getElementById('kpiProducts'), 0, d.total_products, 600);
        animateValue(document.getElementById('kpiLowStock'), 0, d.low_stock_count, 600);

        // Revenue change
        const revEl = document.getElementById('kpiRevenueChange');
        if (d.yesterday_revenue > 0) {
            const pct = ((d.today_revenue - d.yesterday_revenue) / d.yesterday_revenue * 100).toFixed(0);
            revEl.className = 'kpi-change ' + (pct >= 0 ? 'up' : 'down');
            revEl.textContent = (pct >= 0 ? '↑ ' : '↓ ') + Math.abs(pct) + '% vs kemarin';
        } else { revEl.textContent = '—'; revEl.className = 'kpi-change'; }

        const tEl = document.getElementById('kpiTransChange');
        if (d.yesterday_count > 0) {
            const pct = ((d.today_count - d.yesterday_count) / d.yesterday_count * 100).toFixed(0);
            tEl.className = 'kpi-change ' + (pct >= 0 ? 'up' : 'down');
            tEl.textContent = (pct >= 0 ? '↑ ' : '↓ ') + Math.abs(pct) + '%';
        } else { tEl.textContent = '—'; tEl.className = 'kpi-change'; }

        // === ApexCharts: Sales chart ===
        const labels = d.weekly.map(w => { const dt = new Date(w.date); return dt.toLocaleDateString('id-ID', {day:'numeric',month:'short'}); });
        const data = d.weekly.map(w => parseFloat(w.revenue));

        const chartOpts = {
            series: [{ name: 'Revenue', data: data }],
            chart: { type: 'area', height: 280, toolbar: { show: false }, background: 'transparent', animations: { enabled: true, easing: 'easeinout', speed: 800 }, fontFamily: 'Inter, sans-serif' },
            colors: ['#6366F1'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
            stroke: { curve: 'smooth', width: 3 },
            xaxis: { categories: labels, labels: { style: { colors: '#64748B', fontSize: '11px', fontWeight: 500 } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: '#64748B', fontSize: '11px', fontWeight: 500 }, formatter: v => formatRp(v) } },
            grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 3 },
            dataLabels: { enabled: false },
            tooltip: { theme: 'light', y: { formatter: v => formatRp(v) }, style: { fontSize: '12px' } },
            markers: { size: 5, colors: ['#6366F1'], strokeColors: '#FFFFFF', strokeWidth: 3, hover: { size: 7 } },
        };

        const container = document.getElementById('salesChartContainer');
        container.innerHTML = '';
        if (salesChart) salesChart.destroy();
        salesChart = new ApexCharts(container, chartOpts);
        salesChart.render();

        // Top products
        let topHtml = '';
        d.top_products.forEach((p, i) => {
            const maxQty = d.top_products[0]?.qty || 1;
            const pct = (p.qty / maxQty * 100).toFixed(0);
            const colors = ['#6366F1','#8B5CF6','#A855F7','#3B82F6','#059669'];
            topHtml += `<div style="margin-bottom:var(--sp-3);">
                <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);margin-bottom:4px;">
                    <span style="color:var(--text-secondary);">${i+1}. ${escapeHtml(p.product_name)}</span>
                    <span style="color:${colors[i]};font-weight:700;">${p.qty} pcs</span>
                </div>
                <div style="height:6px;background:var(--bg-elevated);border-radius:var(--radius-full);overflow:hidden;">
                    <div style="height:100%;width:${pct}%;background:${colors[i]};border-radius:var(--radius-full);transition:width 0.8s var(--ease);"></div>
                </div>
            </div>`;
        });
        document.getElementById('topProductsList').innerHTML = topHtml || '<p class="text-muted text-sm">Belum ada data</p>';

        // Recent transactions
        let txHtml = '<table class="data-table"><tbody>';
        d.recent.forEach(t => {
            const dt = new Date(t.created_at);
            const method = {cash:'💵 Tunai', qris:'📱 QRIS', transfer:'🏦 Transfer'}[t.payment_method] || t.payment_method;
            const statusBadge = t.status === 'voided' ? '<span class="badge badge-danger">Void</span>' : '';
            txHtml += `<tr>
                <td><span class="font-bold">${escapeHtml(t.invoice_number)}</span></td>
                <td class="text-secondary text-sm">${dt.toLocaleDateString('id-ID')} ${dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})}</td>
                <td class="text-accent font-bold">${formatRp(t.total)}</td>
                <td>${method} ${statusBadge}</td>
            </tr>`;
        });
        txHtml += '</tbody></table>';
        document.getElementById('recentTransactions').innerHTML = d.recent.length ? txHtml : '<p class="text-muted text-sm" style="padding:var(--sp-4);">Belum ada transaksi</p>';

        // Low stock
        let lsHtml = '';
        d.low_stock_items.forEach(item => {
            const statusClass = item.stock === 0 ? 'badge-danger pulse' : 'badge-warning';
            const statusText = item.stock === 0 ? '⛔ Habis' : '⚠️ Low';
            lsHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:var(--sp-2) 0;border-bottom:1px solid var(--border);">
                <div>
                    <div style="font-size:var(--fs-sm);font-weight:600;">${escapeHtml(item.product_name)}</div>
                    <div style="font-size:var(--fs-xs);color:var(--text-muted);">${escapeHtml(item.color)} - ${escapeHtml(item.size)}</div>
                </div>
                <div style="display:flex;align-items:center;gap:var(--sp-2);">
                    <span class="font-bold" style="font-size:var(--fs-sm);">${item.stock} pcs</span>
                    <span class="badge ${statusClass}">${statusText}</span>
                </div>
            </div>`;
        });
        document.getElementById('lowStockList').innerHTML = lsHtml || '<p class="text-muted text-sm" style="padding:var(--sp-4);">Semua stok aman 👍</p>';

    } catch(e) {
        console.error('Dashboard load error:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    setInterval(loadDashboard, 30000);
});
</script>
