<?php
$db = getDB();
$tab = $_GET['tab'] ?? 'sales';
$period = $_GET['period'] ?? '30';
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
            <button class="tab-btn <?= $tab==='sales'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=sales&period=<?= $period ?>'">📈 Penjualan</button>
            <button class="tab-btn <?= $tab==='profit'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=profit&period=<?= $period ?>'">💰 Laba/Rugi</button>
            <button class="tab-btn <?= $tab==='bestseller'?'active':'' ?>" onclick="location.href='<?= APP_URL ?>/index.php?page=reports&tab=bestseller&period=<?= $period ?>'">🏆 Best Seller</button>
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
        <div class="kpi-label">Total Omzet</div>
        <div class="kpi-value text-accent"><?= formatRupiah($totalRevenue) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Transaksi</div>
        <div class="kpi-value"><?= $totalTx ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Rata-rata / Hari</div>
        <div class="kpi-value"><?= formatRupiah($avgDaily) ?></div>
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Revenue & Transaksi</h3></div>
        <div class="chart-container"><canvas id="salesChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Metode Pembayaran</h3></div>
        <div class="chart-container"><canvas id="paymentChart"></canvas></div>
    </div>
</div>

<script>
const salesLabels = <?= json_encode(array_map(fn($s)=>date('d/m',strtotime($s['date'])), $sales)) ?>;
const salesRevenue = <?= json_encode(array_map(fn($s)=>(float)$s['revenue'], $sales)) ?>;
const salesCount = <?= json_encode(array_map(fn($s)=>(int)$s['count'], $sales)) ?>;

const ctx1 = document.getElementById('salesChart').getContext('2d');
const g = ctx1.createLinearGradient(0,0,0,300);
g.addColorStop(0,'rgba(99,102,241,0.20)');
g.addColorStop(0.5,'rgba(139,92,246,0.08)');
g.addColorStop(1,'rgba(99,102,241,0.01)');
new Chart(ctx1,{type:'line',data:{labels:salesLabels,datasets:[
    {label:'Revenue',data:salesRevenue,borderColor:'#6366F1',backgroundColor:g,fill:true,tension:0.4,yAxisID:'y',borderWidth:2.5,pointRadius:4,pointBackgroundColor:'#6366F1',pointBorderColor:'#fff',pointBorderWidth:2},
    {label:'Transaksi',data:salesCount,borderColor:'#F97316',backgroundColor:'transparent',tension:0.4,yAxisID:'y1',borderWidth:2,pointRadius:3,borderDash:[5,5],pointBackgroundColor:'#F97316'}
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#64748B',font:{weight:'500'}}}},scales:{
    x:{grid:{color:'rgba(226,232,240,0.6)'},ticks:{color:'#64748B',font:{weight:'500'}}},
    y:{type:'linear',position:'left',grid:{color:'rgba(226,232,240,0.6)'},ticks:{color:'#64748B',font:{weight:'500'},callback:v=>'Rp '+Number(v).toLocaleString('id-ID')}},
    y1:{type:'linear',position:'right',grid:{display:false},ticks:{color:'#64748B',font:{weight:'500'}}}
}}});

const payLabels = <?= json_encode(array_map(fn($p)=>['cash'=>'Tunai','qris'=>'QRIS','transfer'=>'Transfer'][$p['payment_method']]??$p['payment_method'], $payments)) ?>;
const payData = <?= json_encode(array_map(fn($p)=>(float)$p['revenue'], $payments)) ?>;
new Chart(document.getElementById('paymentChart'),{type:'doughnut',data:{labels:payLabels,datasets:[{data:payData,backgroundColor:['#6366F1','#F97316','#10B981'],borderWidth:2,borderColor:'#FFFFFF'}]},
options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#64748B',font:{weight:'500'},padding:16}}}}});
</script>

<?php elseif ($tab === 'profit'): ?>
<!-- Profit Report -->
<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total Penjualan</div><div class="kpi-value"><?= formatRupiah($totalRevenue) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Total HPP</div><div class="kpi-value text-danger"><?= formatRupiah($totalCost) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Gross Profit</div><div class="kpi-value text-success"><?= formatRupiah($totalProfit) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Margin</div><div class="kpi-value"><?= number_format($grossMargin, 1) ?>%</div></div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Breakdown per Produk</h3></div>
    <div class="table-wrapper">
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
    <div class="card-header"><h3 class="card-title">🏆 Top 10 Produk Terlaris</h3></div>
    <?php if (empty($topItems)): ?>
    <p class="text-muted text-sm" style="padding:var(--sp-4);">Belum ada data penjualan</p>
    <?php else: ?>
    <div class="chart-container" style="height:400px;"><canvas id="bestSellerChart"></canvas></div>
    <div class="table-wrapper mt-4">
        <table class="data-table">
            <thead><tr><th>#</th><th>Produk</th><th>Varian</th><th>Qty Terjual</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topItems as $i => $item): ?>
            <tr>
                <td class="font-bold text-accent"><?= $i+1 ?></td>
                <td class="font-bold"><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-sm text-secondary"><?= $item['variant_info'] ?></td>
                <td class="font-bold"><?= $item['qty'] ?> pcs</td>
                <td class="text-accent"><?= formatRupiah($item['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    new Chart(document.getElementById('bestSellerChart'),{type:'bar',data:{
        labels:<?= json_encode(array_map(fn($i)=>$i['product_name'].' ('.$i['variant_info'].')', $topItems)) ?>,
        datasets:[{label:'Qty Terjual',data:<?= json_encode(array_map(fn($i)=>(int)$i['qty'],$topItems)) ?>,backgroundColor:'rgba(99,102,241,0.15)',borderColor:'#6366F1',borderWidth:2,borderRadius:8}]
    },options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{
        x:{grid:{color:'rgba(226,232,240,0.6)'},ticks:{color:'#64748B',font:{weight:'500'}}},
        y:{grid:{display:false},ticks:{color:'#1E293B',font:{size:11,weight:'600'}}}
    }}});
    </script>
    <?php endif; ?>
</div>
<?php endif; ?>
