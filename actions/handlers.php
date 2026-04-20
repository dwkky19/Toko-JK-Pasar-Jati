<?php
// POST action router
function handlePostAction($page, $action) {
    $db = getDB();

    switch ($page) {
        case 'products':
            handleProducts($db, $action);
            break;
        case 'products-form':
            handleProductForm($db, $action);
            break;
        case 'inventory':
            handleInventory($db, $action);
            break;
        case 'users':
            handleUsers($db, $action);
            break;
        case 'settings':
            handleSettings($db);
            break;
        case 'transactions':
            handleTransactions($db, $action);
            break;
        default:
            header('Location: ' . APP_URL . '/index.php?page=dashboard');
            exit;
    }
}

function handleProductForm($db, $action) {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $kode_barang = trim($_POST['kode_barang'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $sell_price = (float)($_POST['sell_price'] ?? 0);
    $sizes = $_POST['sizes'] ?? [];
    $colors = $_POST['colors'] ?? [];

    if (empty($name) || empty($kode_barang) || $category_id === 0 || $cost_price <= 0 || $sell_price <= 0) {
        setFlash('error', 'Semua field wajib harus diisi (termasuk Kode Barang).');
        header('Location: ' . APP_URL . '/index.php?page=products-form' . ($id ? '&id=' . $id : ''));
        exit;
    }

    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'product_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
            $image = $filename;
        }
    }

    try {
        $db->beginTransaction();

        if ($id) {
            // Update product
            $sql = "UPDATE products SET name=?, kode_barang=?, category_id=?, brand=?, description=?, cost_price=?, sell_price=?";
            $params = [$name, $kode_barang, $category_id, $brand, $description, $cost_price, $sell_price];
            if ($image) {
                $sql .= ", image=?";
                $params[] = $image;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
        } else {
            // Insert product
            $stmt = $db->prepare("INSERT INTO products (name, kode_barang, category_id, brand, description, cost_price, sell_price, image) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $kode_barang, $category_id, $brand, $description, $cost_price, $sell_price, $image]);
            $id = $db->lastInsertId();
        }

        // Get existing variants
        $existingVariants = [];
        $stmt = $db->prepare("SELECT id, size, color FROM product_variants WHERE product_id = ?");
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $v) {
            $existingVariants[$v['size'] . '-' . $v['color']] = $v['id'];
        }

        // Generate SKU prefix
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        $catStmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $catStmt->execute([$category_id]);
        $catName = strtoupper(substr($catStmt->fetchColumn() ?: 'XXX', 0, 2));

        // Create new variants
        if (!empty($sizes) && !empty($colors)) {
            foreach ($sizes as $size) {
                foreach ($colors as $color) {
                    $key = $size . '-' . $color;
                    if (isset($existingVariants[$key])) {
                        unset($existingVariants[$key]);
                        continue;
                    }
                    $sku = $prefix . '-' . $catName . '-' . strtoupper(substr($color, 0, 3)) . '-' . strtoupper($size);
                    // Ensure unique SKU
                    $checkSku = $db->prepare("SELECT COUNT(*) FROM product_variants WHERE sku = ?");
                    $checkSku->execute([$sku]);
                    if ($checkSku->fetchColumn() > 0) {
                        $sku .= '-' . rand(100, 999);
                    }
                    $stock = (int)($_POST['stock_' . $size . '_' . $color] ?? 0);
                    $db->prepare("INSERT INTO product_variants (product_id, sku, size, color, stock) VALUES (?,?,?,?,?)")
                        ->execute([$id, $sku, $size, $color, $stock]);
                }
            }
        }

        $db->commit();
        setFlash('success', $id ? 'Produk berhasil diupdate.' : 'Produk berhasil ditambahkan.');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Gagal menyimpan produk: ' . $e->getMessage());
    }

    header('Location: ' . APP_URL . '/index.php?page=products');
    exit;
}

function handleProducts($db, $action) {
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            setFlash('success', 'Produk berhasil dihapus.');
        }
    }
    header('Location: ' . APP_URL . '/index.php?page=products');
    exit;
}

function handleInventory($db, $action) {
    requireAdmin();
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $type = $_POST['type'] ?? 'in';
    $reason = trim($_POST['reason'] ?? '');
    $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;

    if ($variant_id <= 0 || $quantity <= 0) {
        setFlash('error', 'Varian dan jumlah harus diisi.');
        header('Location: ' . APP_URL . '/index.php?page=inventory');
        exit;
    }

    try {
        $db->beginTransaction();

        // Insert movement
        $db->prepare("INSERT INTO stock_movements (variant_id, type, quantity, reason, supplier_id, user_id) VALUES (?,?,?,?,?,?)")
            ->execute([$variant_id, $type, $quantity, $reason, $supplier_id, $_SESSION['user_id']]);

        // Update stock
        $op = $type === 'in' ? '+' : '-';
        $db->prepare("UPDATE product_variants SET stock = GREATEST(0, stock {$op} ?) WHERE id = ?")
            ->execute([$quantity, $variant_id]);

        $db->commit();
        setFlash('success', 'Stok berhasil di' . ($type === 'in' ? 'tambahkan' : 'kurangkan') . '.');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Gagal update stok: ' . $e->getMessage());
    }

    header('Location: ' . APP_URL . '/index.php?page=inventory');
    exit;
}

function handleUsers($db, $action) {
    requireAdmin();

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== $_SESSION['user_id']) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            setFlash('success', 'Pengguna berhasil dihapus.');
        }
    } elseif ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'kasir';
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($username)) {
            setFlash('error', 'Nama dan username harus diisi.');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        if ($id > 0) {
            // Update
            $sql = "UPDATE users SET name=?, username=?, role=?, status=?";
            $params = [$name, $username, $role, $status];
            if (!empty($password)) {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $db->prepare($sql)->execute($params);
            setFlash('success', 'Pengguna berhasil diupdate.');
        } else {
            // Insert
            if (empty($password)) {
                setFlash('error', 'Password harus diisi untuk pengguna baru.');
                header('Location: ' . APP_URL . '/index.php?page=users');
                exit;
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (name, username, password, role, status) VALUES (?,?,?,?,?)")
                ->execute([$name, $username, $hashed, $role, $status]);
            setFlash('success', 'Pengguna berhasil ditambahkan.');
        }
    }

    header('Location: ' . APP_URL . '/index.php?page=users');
    exit;
}

function handleSettings($db) {
    $keys = ['store_name', 'store_address', 'store_phone', 'receipt_footer', 'default_min_stock'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$key, $_POST[$key], $_POST[$key]]);
        }
    }
    setFlash('success', 'Pengaturan berhasil disimpan.');
    header('Location: ' . APP_URL . '/index.php?page=settings');
    exit;
}

function handleTransactions($db, $action) {
    if ($action === 'void' && isAdmin()) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Restore stock
            $items = $db->prepare("SELECT variant_id, quantity FROM transaction_items WHERE transaction_id = ?");
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                $db->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?")->execute([$item['quantity'], $item['variant_id']]);
            }
            $db->prepare("UPDATE transactions SET status = 'voided' WHERE id = ?")->execute([$id]);
            setFlash('success', 'Transaksi berhasil dibatalkan dan stok dikembalikan.');
        }
    }
    header('Location: ' . APP_URL . '/index.php?page=transactions');
    exit;
}
