<?php
// Dashboard page
$db = getDB();
?>
<div class="kpi-grid" id="kpiGrid">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--accent-muted);color:var(--accent);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="kpi-value" id="kpiRevenue">—</div>
        <div class="kpi-label">Omzet Hari Ini</div>
        <div class="kpi-change" id="kpiRevenueChange">—</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--info-muted);color:var(--info);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        </div>
        <div class="kpi-value" id="kpiTransactions">—</div>
        <div class="kpi-label">Transaksi Hari Ini</div>
        <div class="kpi-change" id="kpiTransChange">—</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--success-muted);color:var(--success);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="kpi-value" id="kpiProducts">—</div>
        <div class="kpi-label">Total SKU Aktif</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--warning-muted);color:var(--warning);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="kpi-value" id="kpiLowStock">—</div>
        <div class="kpi-label">Low Stock Alert</div>
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📈 Grafik Penjualan (7 Hari)</h3>
        </div>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🏆 Top 5 Produk</h3>
        </div>
        <div id="topProductsList" style="margin-top:var(--sp-2);"></div>
    </div>
</div>

<div class="bottom-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🧾 Transaksi Terbaru</h3>
            <a href="<?= APP_URL ?>/index.php?page=transactions" class="btn btn-ghost btn-sm">Lihat Semua →</a>
        </div>
        <div id="recentTransactions"></div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚠️ Low Stock Alert</h3>
            <?php if(isAdmin()): ?>
            <a href="<?= APP_URL ?>/index.php?page=inventory" class="btn btn-ghost btn-sm">Kelola →</a>
            <?php endif; ?>
        </div>
        <div id="lowStockList"></div>
    </div>
</div>

<script>
const APP = '<?= APP_URL ?>';
function formatRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

async function loadDashboard() {
    try {
        const res = await fetch(APP + '/index.php?page=api&action=dashboard-stats');
        const d = await res.json();

        document.getElementById('kpiRevenue').textContent = formatRp(d.today_revenue);
        document.getElementById('kpiTransactions').textContent = d.today_count;
        document.getElementById('kpiProducts').textContent = d.total_products;
        document.getElementById('kpiLowStock').textContent = d.low_stock_count;

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

        // Sales chart
        const labels = d.weekly.map(w => { const dt = new Date(w.date); return dt.toLocaleDateString('id-ID', {day:'numeric',month:'short'}); });
        const data = d.weekly.map(w => w.revenue);
        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(99,102,241,0.20)');
        gradient.addColorStop(0.5, 'rgba(139,92,246,0.08)');
        gradient.addColorStop(1, 'rgba(99,102,241,0.01)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: data,
                    borderColor: '#6366F1',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6366F1',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#4F46E5',
                    pointHoverBorderColor: '#FFFFFF',
                    pointHoverBorderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: (c) => formatRp(c.parsed.y) },
                        backgroundColor: '#FFFFFF',
                        titleColor: '#1E293B',
                        bodyColor: '#1E293B',
                        borderColor: '#E2E8F0',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
                        titleFont: { weight: '700' },
                    }
                },
                scales: {
                    x: { grid: { color: 'rgba(226,232,240,0.6)' }, ticks: { color: '#64748B', font: { weight: '500' } } },
                    y: { grid: { color: 'rgba(226,232,240,0.6)' }, ticks: { color: '#64748B', font: { weight: '500' }, callback: (v) => formatRp(v) } }
                }
            }
        });

        // Top products
        let topHtml = '';
        d.top_products.forEach((p, i) => {
            const maxQty = d.top_products[0]?.qty || 1;
            const pct = (p.qty / maxQty * 100).toFixed(0);
            topHtml += `<div style="margin-bottom:var(--sp-3);">
                <div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);margin-bottom:4px;">
                    <span>${i+1}. ${p.product_name}</span>
                    <span class="text-accent font-bold">${p.qty} pcs</span>
                </div>
                <div style="height:6px;background:var(--bg-hover);border-radius:var(--radius-full);overflow:hidden;">
                    <div style="height:100%;width:${pct}%;background:var(--accent);border-radius:var(--radius-full);transition:width 0.6s;"></div>
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
                <td><span class="font-bold">${t.invoice_number}</span></td>
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
                    <div style="font-size:var(--fs-sm);font-weight:600;">${item.product_name}</div>
                    <div style="font-size:var(--fs-xs);color:var(--text-muted);">${item.color} - ${item.size}</div>
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

loadDashboard();
setInterval(loadDashboard, 30000);
</script>
