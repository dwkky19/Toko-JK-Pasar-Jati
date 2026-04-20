<?php
// ============================================
// API Endpoints (AJAX for POS)
// ============================================
require_once __DIR__ . '/config.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'products':
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $sql = "SELECT p.*, c.name as category_name,
                (SELECT SUM(pv.stock) FROM product_variants pv WHERE pv.product_id = p.id) as total_stock
                FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
        $params = [];
        if ($category) { $sql .= " AND c.name = ?"; $params[] = $category; }
        if ($search) { $sql .= " AND (p.name LIKE ? OR p.brand LIKE ? OR p.kode_barang LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= " ORDER BY p.name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'variants':
        $product_id = (int)($_GET['product_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, size");
        $stmt->execute([$product_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'checkout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); break; }
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];
        $discountType = $input['discount_type'] ?? null;
        $discountValue = (float)($input['discount_value'] ?? 0);
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $paymentAmount = (float)($input['payment_amount'] ?? 0);

        if (empty($items)) { echo json_encode(['error'=>'Keranjang kosong']); break; }

        try {
            $db->beginTransaction();
            $subtotal = 0;

            // Validate stock
            foreach ($items as $item) {
                $v = $db->prepare("SELECT pv.*, p.name as product_name, p.cost_price FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
                $v->execute([$item['variant_id']]);
                $variant = $v->fetch();
                if (!$variant) { throw new Exception('Varian tidak ditemukan'); }
                if ($variant['stock'] < $item['qty']) { throw new Exception('Stok ' . $variant['product_name'] . ' (' . $variant['color'] . ' ' . $variant['size'] . ') tidak cukup'); }
                $subtotal += $variant['stock']; // will recalc below
            }

            // Calculate
            $subtotal = 0;
            foreach ($items as $item) {
                $v = $db->prepare("SELECT pv.*, p.name as product_name, p.cost_price, p.sell_price FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
                $v->execute([$item['variant_id']]);
                $variant = $v->fetch();
                $subtotal += $variant['sell_price'] * $item['qty'];
            }

            $discountAmount = 0;
            if ($discountType === 'percent' && $discountValue > 0) {
                $discountAmount = $subtotal * ($discountValue / 100);
            } elseif ($discountType === 'nominal' && $discountValue > 0) {
                $discountAmount = $discountValue;
            }
            $total = $subtotal - $discountAmount;
            $changeAmount = max(0, $paymentAmount - $total);

            if ($paymentAmount < $total) { throw new Exception('Jumlah pembayaran kurang'); }

            // Create transaction
            $invoice = generateInvoice();
            $db->prepare("INSERT INTO transactions (invoice_number, user_id, subtotal, discount_type, discount_value, discount_amount, total, payment_method, payment_amount, change_amount) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$invoice, $_SESSION['user_id'], $subtotal, $discountType, $discountValue, $discountAmount, $total, $paymentMethod, $paymentAmount, $changeAmount]);
            $txId = $db->lastInsertId();

            // Create items & reduce stock
            foreach ($items as $item) {
                $v = $db->prepare("SELECT pv.*, p.name as product_name, p.cost_price, p.sell_price FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
                $v->execute([$item['variant_id']]);
                $variant = $v->fetch();
                $itemSubtotal = $variant['sell_price'] * $item['qty'];
                $variantInfo = $variant['color'] . ' - ' . $variant['size'];

                $db->prepare("INSERT INTO transaction_items (transaction_id, variant_id, product_name, variant_info, price, cost_price, quantity, subtotal) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$txId, $item['variant_id'], $variant['product_name'], $variantInfo, $variant['sell_price'], $variant['cost_price'], $item['qty'], $itemSubtotal]);

                $db->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?")->execute([$item['qty'], $item['variant_id']]);
            }

            $db->commit();

            // Get settings for receipt
            $storeStmt = $db->prepare("SELECT setting_key, setting_value FROM settings");
            $storeStmt->execute();
            $settings = [];
            foreach ($storeStmt->fetchAll() as $s) { $settings[$s['setting_key']] = $s['setting_value']; }

            echo json_encode([
                'success' => true,
                'transaction' => [
                    'id' => $txId,
                    'invoice' => $invoice,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_amount' => $paymentAmount,
                    'change_amount' => $changeAmount,
                    'date' => date('d/m/Y H:i'),
                    'cashier' => $_SESSION['user_name'],
                ],
                'items' => $items,
                'store' => $settings,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'dashboard-stats':
        // Today's sales
        $today = $db->query("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE() AND status='completed'")->fetch();
        $yesterday = $db->query("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND status='completed'")->fetch();

        $totalProducts = $db->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
        $lowStock = $db->query("SELECT COUNT(*) FROM product_variants WHERE stock <= min_stock")->fetchColumn();

        // Weekly sales for chart
        $weekly = $db->query("SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as count FROM transactions WHERE created_at >= CURDATE() - INTERVAL 7 DAY AND status='completed' GROUP BY DATE(created_at) ORDER BY date")->fetchAll();

        // Top products
        $topProducts = $db->query("SELECT ti.product_name, SUM(ti.quantity) as qty, SUM(ti.subtotal) as revenue FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE t.status='completed' AND t.created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY ti.product_name ORDER BY qty DESC LIMIT 5")->fetchAll();

        // Recent transactions
        $recent = $db->query("SELECT t.*, u.name as cashier FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

        // Low stock items
        $lowStockItems = $db->query("SELECT pv.*, p.name as product_name FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.stock <= pv.min_stock ORDER BY pv.stock ASC LIMIT 10")->fetchAll();

        echo json_encode([
            'today_revenue' => (float)$today['revenue'],
            'today_count' => (int)$today['count'],
            'yesterday_revenue' => (float)$yesterday['revenue'],
            'yesterday_count' => (int)$yesterday['count'],
            'total_products' => (int)$totalProducts,
            'low_stock_count' => (int)$lowStock,
            'weekly' => $weekly,
            'top_products' => $topProducts,
            'recent' => $recent,
            'low_stock_items' => $lowStockItems,
        ]);
        break;

    case 'all-variants':
        $stmt = $db->query("SELECT pv.id, pv.sku, pv.size, pv.color, pv.stock, p.name as product_name FROM product_variants pv JOIN products p ON pv.product_id = p.id ORDER BY p.name, pv.color, pv.size");
        echo json_encode($stmt->fetchAll());
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
