<?php
$db = getDB();
$tab = $_GET['tab'] ?? 'sales';
$period = $_GET['period'] ?? '30';
// --- Security: Whitelist allowed period values ---
$allowedPeriods = ['7', '30', '90'];
if (!in_array($period, $allowedPeriods)) { $period = '30'; }
$periodDays = (int)$period;

// Sales data
$salesData = $db->prepare("SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as count
    FROM transactions WHERE status='completed' AND created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY DATE(created_at) ORDER BY date");
$salesData->execute([$periodDays]);
$sales = $salesData->fetchAll();

$totalRevenue = array_sum(array_column($sales, 'revenue'));
$totalTx = array_sum(array_column($sales, 'count'));
$avgDaily = $totalTx > 0 ? $totalRevenue / max(1, count($sales)) : 0;

// Profit/Loss
$profitData = $db->prepare("SELECT ti.product_name, SUM(ti.quantity) as qty,
    SUM(ti.subtotal) as revenue, SUM(ti.cost_price * ti.quantity) as cost,
    SUM(ti.subtotal - (ti.cost_price * ti.quantity)) as profit
    FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id
    WHERE t.status='completed' AND t.created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY ti.product_name ORDER BY profit DESC");
$profitData->execute([$periodDays]);
$profits = $profitData->fetchAll();

$totalCost = array_sum(array_column($profits, 'cost'));
$totalProfit = array_sum(array_column($profits, 'profit'));
$grossMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue * 100) : 0;

// Best sellers
$bestSellers = $db->prepare("SELECT ti.product_name, ti.variant_info, SUM(ti.quantity) as qty, SUM(ti.subtotal) as revenue
    FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id
    WHERE t.status='completed' AND t.created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY ti.product_name, ti.variant_info ORDER BY qty DESC LIMIT 10");
$bestSellers->execute([$periodDays]);
$topItems = $bestSellers->fetchAll();

// Payment method breakdown
$paymentBreakdown = $db->prepare("SELECT payment_method, COUNT(*) as count, SUM(total) as revenue
    FROM transactions WHERE status='completed' AND created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY payment_method");
$paymentBreakdown->execute([$periodDays]);
$payments = $paymentBreakdown->fetchAll();
?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="tabs" style="border:none;margin:0;">
            <button class="tab-btn <?= $tab==='sales'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=sales&period=<?= $period ?>'"><i data-lucide="trending-up" style="width:14px;height:14px;"></i> Penjualan</button>
            <button class="tab-btn <?= $tab==='profit'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=profit&period=<?= $period ?>'"><i data-lucide="wallet" style="width:14px;height:14px;"></i> Laba/Rugi</button>
            <button class="tab-btn <?= $tab==='bestseller'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=bestseller&period=<?= $period ?>'"><i data-lucide="trophy" style="width:14px;height:14px;"></i> Best Seller</button>
        </div>
    </div>
    <div class="toolbar-right">
        <select class="form-select" style="width:160px;" onchange="location.href='<?= APP_URL ?>/index.php?page=reports&tab=<?= $tab ?>&period='+this.value">
            <option value="7" <?= $period==='7'?'selected':'' ?>>7 hari terakhir</option>
            <option value="30" <?= $period==='30'?'selected':'' ?>>30 hari terakhir</option>
            <option value="90" <?= $period==='90'?'selected':'' ?>>90 hari terakhir</option>
        </select>
    </div>
</div>

<?php if ($tab === 'sales'): ?>
<!-- Sales Report -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--accent-muted);color:var(--accent);"><i data-lucide="dollar-sign" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Total Omzet</div>
        <div class="kpi-value text-accent"><?= formatRupiah($totalRevenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--info-muted);color:var(--info);"><i data-lucide="shopping-bag" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Total Transaksi</div>
        <div class="kpi-value"><?= $totalTx ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--success-muted);color:var(--success);"><i data-lucide="calculator" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Rata-rata / Hari</div>
        <div class="kpi-value"><?= formatRupiah($avgDaily) ?></div>
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i data-lucide="bar-chart-3" style="width:16px;height:16px;"></i> Revenue & Transaksi</h3></div>
        <div id="salesChart" style="min-height:300px;"></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i data-lucide="pie-chart" style="width:16px;height:16px;"></i> Metode Pembayaran</h3></div>
        <div id="paymentChart" style="min-height:300px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const salesLabels = <?= json_encode(array_map(fn($s)=>date('d/m',strtotime($s['date'])), $sales)) ?>;
    const salesRevenue = <?= json_encode(array_map(fn($s)=>(float)$s['revenue'], $sales)) ?>;
    const salesCount = <?= json_encode(array_map(fn($s)=>(int)$s['count'], $sales)) ?>;

    new ApexCharts(document.getElementById('salesChart'), {
        series: [
            { name: 'Revenue', type: 'area', data: salesRevenue },
            { name: 'Transaksi', type: 'line', data: salesCount }
        ],
        chart: { height: 300, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
        colors: ['#6366F1', '#D97706'],
        fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
        xaxis: { categories: salesLabels, labels: { style: { colors: '#64748B', fontSize: '11px' } }, axisBorder: { show: false } },
        yaxis: [
            { labels: { style: { colors: '#64748B' }, formatter: v => 'Rp ' + Number(v).toLocaleString('id-ID') } },
            { opposite: true, labels: { style: { colors: '#64748B' } } }
        ],
        grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 3 },
        dataLabels: { enabled: false },
        tooltip: { theme: 'light', y: { formatter: (v, { seriesIndex }) => seriesIndex === 0 ? 'Rp ' + Number(v).toLocaleString('id-ID') : v + ' transaksi' } },
        legend: { labels: { colors: '#475569' } },
        markers: { size: [4, 3] }
    }).render();

    const payLabels = <?= json_encode(array_map(fn($p)=>['cash'=>'Tunai','qris'=>'QRIS','transfer'=>'Transfer'][$p['payment_method']]??$p['payment_method'], $payments)) ?>;
    const payData = <?= json_encode(array_map(fn($p)=>(float)$p['revenue'], $payments)) ?>;

    new ApexCharts(document.getElementById('paymentChart'), {
        series: payData,
        chart: { type: 'donut', height: 300, background: 'transparent', fontFamily: 'Inter, sans-serif' },
        labels: payLabels,
        colors: ['#6366F1', '#D97706', '#059669'],
        stroke: { width: 2, colors: ['#FFFFFF'] },
        dataLabels: { style: { fontSize: '12px', fontWeight: 600 } },
        legend: { position: 'bottom', labels: { colors: '#475569' } },
        tooltip: { theme: 'light', y: { formatter: v => 'Rp ' + Number(v).toLocaleString('id-ID') } },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', color: '#94A3B8', formatter: w => 'Rp ' + Number(w.globals.seriesTotals.reduce((a,b)=>a+b,0)).toLocaleString('id-ID') } } } } }
    }).render();
});
</script>

<?php elseif ($tab === 'profit'): ?>
<!-- Profit Report -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--accent-muted);color:var(--accent);"><i data-lucide="banknote" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Total Penjualan</div>
        <div class="kpi-value"><?= formatRupiah($totalRevenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--danger-muted);color:var(--danger);"><i data-lucide="arrow-down-circle" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Total HPP</div>
        <div class="kpi-value text-danger"><?= formatRupiah($totalCost) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--success-muted);color:var(--success);"><i data-lucide="arrow-up-circle" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Gross Profit</div>
        <div class="kpi-value text-success"><?= formatRupiah($totalProfit) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon" style="background:var(--warning-muted);color:var(--warning);"><i data-lucide="percent" style="width:20px;height:20px;"></i></div>
        <div class="kpi-label">Margin</div>
        <div class="kpi-value"><?= number_format($grossMargin, 1) ?>%</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i data-lucide="list" style="width:16px;height:16px;"></i> Breakdown per Produk</h3></div>
    <div class="table-wrapper" style="border:none;">
        <table class="data-table">
            <thead><tr><th>Produk</th><th>Qty</th><th>Revenue</th><th>HPP</th><th>Profit</th><th>Margin</th></tr></thead>
            <tbody>
            <?php foreach ($profits as $p):
                $m = $p['revenue'] > 0 ? ($p['profit'] / $p['revenue'] * 100) : 0;
            ?>
            <tr>
                <td class="font-bold"><?= htmlspecialchars($p['product_name']) ?></td>
                <td><?= $p['qty'] ?></td>
                <td><?= formatRupiah($p['revenue']) ?></td>
                <td class="text-secondary"><?= formatRupiah($p['cost']) ?></td>
                <td class="font-bold text-success"><?= formatRupiah($p['profit']) ?></td>
                <td><span class="badge <?= $m>=30?'badge-success':'badge-warning' ?>"><?= number_format($m,1) ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Best Seller -->
<div class="card">
    <div class="card-header"><h3 class="card-title"><i data-lucide="trophy" style="width:16px;height:16px;"></i> Top 10 Produk Terlaris</h3></div>
    <?php if (empty($topItems)): ?>
    <p class="text-muted text-sm" style="padding:var(--sp-4);">Belum ada data penjualan</p>
    <?php else: ?>
    <div id="bestSellerChart" style="min-height:400px;"></div>
    <div class="table-wrapper mt-4" style="border:none;">
        <table class="data-table">
            <thead><tr><th>#</th><th>Produk</th><th>Varian</th><th>Qty Terjual</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topItems as $i => $item): ?>
            <tr>
                <td class="font-bold text-accent"><?= $i+1 ?></td>
                <td class="font-bold"><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-sm text-secondary"><?= e($item['variant_info']) ?></td>
                <td class="font-bold"><?= $item['qty'] ?> pcs</td>
                <td class="text-accent"><?= formatRupiah($item['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new ApexCharts(document.getElementById('bestSellerChart'), {
            series: [{ name: 'Qty Terjual', data: <?= json_encode(array_map(fn($i)=>(int)$i['qty'],$topItems)) ?> }],
            chart: { type: 'bar', height: 400, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
            colors: ['#6366F1'],
            fill: { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.3, gradientToColors: ['#C084FC'], stops: [0,100] } },
            xaxis: { labels: { style: { colors: '#64748B', fontSize: '11px' } } },
            yaxis: { labels: { style: { colors: '#1E293B', fontSize: '11px', fontWeight: 600 } } },
            grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 3 },
            dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600 }, formatter: v => v + ' pcs' },
            tooltip: { theme: 'light' },
            categories: <?= json_encode(array_map(fn($i)=>$i['product_name'].' ('.$i['variant_info'].')', $topItems)) ?>
        }).render();
    });
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>
