<?php
// ============================================
// API Endpoints (AJAX for POS)
// ============================================
require_once __DIR__ . '/config.php';

// --- Security: Set JSON content type and security headers ---
header('Content-Type: application/json; charset=utf-8');
sendSecurityHeaders();

// --- Security: Require authentication ---
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? '';

// --- Security: Whitelist allowed actions ---
$allowedActions = ['products', 'variants', 'checkout', 'dashboard-stats', 'all-variants'];
if (!in_array($action, $allowedActions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Aksi tidak dikenali']);
    exit;
}

switch ($action) {
    case 'products':
        $category = trim($_GET['category'] ?? '');
        $search = trim($_GET['search'] ?? '');
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
        if ($product_id <= 0) {
            echo json_encode(['error' => 'Product ID tidak valid']);
            break;
        }
        $stmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, size");
        $stmt->execute([$product_id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'checkout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); break; }

        // --- Security: Validate CSRF token for POST requests ---
        if (!validateCSRFAjax()) {
            http_response_code(403);
            echo json_encode(['error' => 'Sesi keamanan tidak valid. Muat ulang halaman dan coba lagi.']);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // --- Security: Validate input structure ---
        if (!is_array($input)) {
            echo json_encode(['error' => 'Input tidak valid']);
            break;
        }

        $items = $input['items'] ?? [];
        $discountType = $input['discount_type'] ?? null;
        $discountValue = (float)($input['discount_value'] ?? 0);
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $paymentAmount = (float)($input['payment_amount'] ?? 0);

        // --- Security: Validate payment method whitelist ---
        $validPaymentMethods = ['cash', 'qris', 'transfer'];
        if (!in_array($paymentMethod, $validPaymentMethods)) {
            echo json_encode(['error' => 'Metode pembayaran tidak valid']);
            break;
        }

        // --- Security: Validate discount type whitelist ---
        if ($discountType !== null && !in_array($discountType, ['percent', 'nominal'])) {
            echo json_encode(['error' => 'Tipe diskon tidak valid']);
            break;
        }

        // --- Security: Validate discount value range ---
        if ($discountType === 'percent' && ($discountValue < 0 || $discountValue > 100)) {
            echo json_encode(['error' => 'Diskon persen harus 0-100']);
            break;
        }
        if ($discountValue < 0) {
            echo json_encode(['error' => 'Nilai diskon tidak valid']);
            break;
        }

        if (empty($items) || !is_array($items)) { echo json_encode(['error'=>'Keranjang kosong']); break; }

        // --- Security: Validate each item structure ---
        foreach ($items as $item) {
            if (!isset($item['variant_id']) || !isset($item['qty'])) {
                echo json_encode(['error' => 'Data item tidak valid']);
                break 2;
            }
            $vid = (int)$item['variant_id'];
            $qty = (int)$item['qty'];
            if ($vid <= 0 || $qty <= 0 || $qty > 9999) {
                echo json_encode(['error' => 'Jumlah item tidak valid']);
                break 2;
            }
        }

        if ($paymentAmount < 0 || $paymentAmount > 999999999) {
            echo json_encode(['error' => 'Jumlah pembayaran tidak valid']);
            break;
        }

        try {
            $db->beginTransaction();

            // Pre-fetch all variant data in ONE query per item (optimized from 3x)
            $variantData = [];
            $subtotal = 0;
            foreach ($items as $item) {
                $v = $db->prepare("SELECT pv.*, p.name as product_name, p.cost_price, p.sell_price FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
                $v->execute([(int)$item['variant_id']]);
                $variant = $v->fetch();
                if (!$variant) { throw new Exception('Varian tidak ditemukan'); }
                if ($variant['stock'] < (int)$item['qty']) { throw new Exception('Stok ' . $variant['product_name'] . ' (' . $variant['color'] . ' ' . $variant['size'] . ') tidak cukup'); }
                $variant['order_qty'] = (int)$item['qty'];
                $variantData[(int)$item['variant_id']] = $variant;
                $subtotal += $variant['sell_price'] * (int)$item['qty'];
            }

            // Calculate discount
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

            // Create items, reduce stock, and record stock movements
            foreach ($items as $item) {
                $variant = $variantData[(int)$item['variant_id']];
                $itemSubtotal = $variant['sell_price'] * (int)$item['qty'];
                $variantInfo = $variant['color'] . ' - ' . $variant['size'];

                // Insert transaction item
                $db->prepare("INSERT INTO transaction_items (transaction_id, variant_id, product_name, variant_info, price, cost_price, quantity, subtotal) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$txId, (int)$item['variant_id'], $variant['product_name'], $variantInfo, $variant['sell_price'], $variant['cost_price'], (int)$item['qty'], $itemSubtotal]);

                // Update stock
                $db->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?")->execute([(int)$item['qty'], (int)$item['variant_id']]);

                // Record stock movement (audit trail)
                $db->prepare("INSERT INTO stock_movements (variant_id, type, quantity, reason, user_id) VALUES (?,?,?,?,?)")
                    ->execute([(int)$item['variant_id'], 'out', (int)$item['qty'], 'Penjualan: ' . $invoice, $_SESSION['user_id']]);
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
                    'cashier' => e($_SESSION['user_name']),
                ],
                'items' => $items,
                'store' => $settings,
                'csrf_token' => getCSRFToken(), // Send new token for next request
            ]);
        } catch (Exception $ex) {
            $db->rollBack();
            echo json_encode(['error' => $ex->getMessage()]);
        }
        break;

    case 'dashboard-stats':
        // --- Security: Only admin can access dashboard stats ---
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Akses ditolak']);
            break;
        }
        // Today's sales (prepared statements for consistency)
        $todayStmt = $db->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE() AND status=?");
        $todayStmt->execute(['completed']);
        $today = $todayStmt->fetch();

        $yesterdayStmt = $db->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND status=?");
        $yesterdayStmt->execute(['completed']);
        $yesterday = $yesterdayStmt->fetch();

        // Today's profit
        $profitStmt = $db->prepare("SELECT COALESCE(SUM(ti.subtotal - (ti.cost_price * ti.quantity)), 0) as profit
            FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id
            WHERE DATE(t.created_at) = CURDATE() AND t.status=?");
        $profitStmt->execute(['completed']);
        $profitToday = $profitStmt->fetchColumn();

        $totalProducts = $db->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
        $lowStock = $db->query("SELECT COUNT(*) FROM product_variants WHERE stock <= min_stock")->fetchColumn();

        // Weekly sales for chart
        $weeklyStmt = $db->prepare("SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as count FROM transactions WHERE created_at >= CURDATE() - INTERVAL 7 DAY AND status=? GROUP BY DATE(created_at) ORDER BY date");
        $weeklyStmt->execute(['completed']);
        $weekly = $weeklyStmt->fetchAll();

        // Top products
        $topStmt = $db->prepare("SELECT ti.product_name, SUM(ti.quantity) as qty, SUM(ti.subtotal) as revenue FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE t.status=? AND t.created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY ti.product_name ORDER BY qty DESC LIMIT 5");
        $topStmt->execute(['completed']);
        $topProducts = $topStmt->fetchAll();

        // Recent transactions
        $recent = $db->query("SELECT t.*, u.name as cashier FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

        // Low stock items
        $lowStockItems = $db->query("SELECT pv.*, p.name as product_name FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.stock <= pv.min_stock ORDER BY pv.stock ASC LIMIT 10")->fetchAll();

        echo json_encode([
            'today_revenue' => (float)$today['revenue'],
            'today_count' => (int)$today['count'],
            'yesterday_revenue' => (float)$yesterday['revenue'],
            'yesterday_count' => (int)$yesterday['count'],
            'today_profit' => (float)$profitToday,
            'total_products' => (int)$totalProducts,
            'low_stock_count' => (int)$lowStock,
            'weekly' => $weekly,
            'top_products' => $topProducts,
            'recent' => $recent,
            'low_stock_items' => $lowStockItems,
        ]);
        break;

    case 'all-variants':
        // --- Security: Only admin can access all variants list ---
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Akses ditolak']);
            break;
        }
        $stmt = $db->query("SELECT pv.id, pv.sku, pv.size, pv.color, pv.stock, p.name as product_name FROM product_variants pv JOIN products p ON pv.product_id = p.id ORDER BY p.name, pv.color, pv.size");
        echo json_encode($stmt->fetchAll());
        break;
}
