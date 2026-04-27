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
    // --- Security: Only admin can add/edit products ---
    requireAdmin();

    $id = $_POST['id'] ?? null;
    if ($id !== null) { $id = (int)$id; }
    $isEdit = !empty($id);
    $name = trim($_POST['name'] ?? '');
    $kode_barang = trim($_POST['kode_barang'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $sell_price = (float)($_POST['sell_price'] ?? 0);
    $sizes = $_POST['sizes'] ?? [];
    $colors = $_POST['colors'] ?? [];

    // --- Security: Validate arrays ---
    if (!is_array($sizes)) $sizes = [];
    if (!is_array($colors)) $colors = [];

    if (empty($name) || empty($kode_barang) || $category_id === 0 || $cost_price <= 0 || $sell_price <= 0) {
        setFlash('error', 'Semua field wajib harus diisi (termasuk Kode Barang).');
        header('Location: ' . APP_URL . '/index.php?page=products-form' . ($id ? '&id=' . $id : ''));
        exit;
    }

    // --- Security: Validate name length ---
    if (strlen($name) > 200 || strlen($kode_barang) > 50 || strlen($brand) > 100) {
        setFlash('error', 'Panjang input melebihi batas.');
        header('Location: ' . APP_URL . '/index.php?page=products-form' . ($id ? '&id=' . $id : ''));
        exit;
    }

    // Handle image upload with security hardening
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // --- Security: Use centralized secure upload processor ---
        $uploadDir = __DIR__ . '/../uploads/products/';
        $result = processUploadedImage($_FILES['image']['tmp_name'], $uploadDir, 'product');
        if (isset($result['error'])) {
            setFlash('error', $result['error']);
            header('Location: ' . APP_URL . '/index.php?page=products-form' . ($id ? '&id=' . $id : ''));
            exit;
        }
        $image = $result['filename'];
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
                    // --- Security: Sanitize size and color values ---
                    $size = trim($size);
                    $color = trim($color);
                    if (empty($size) || empty($color) || strlen($size) > 10 || strlen($color) > 50) continue;

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
                    if ($stock < 0) $stock = 0;
                    $db->prepare("INSERT INTO product_variants (product_id, sku, size, color, stock) VALUES (?,?,?,?,?)")
                        ->execute([$id, $sku, $size, $color, $stock]);
                }
            }
        }

        // Remove variants that are no longer selected (cleanup orphans)
        if ($isEdit && !empty($existingVariants)) {
            foreach ($existingVariants as $variantKey => $variantId) {
                // Only delete if it has zero stock (don't delete variants with stock)
                $stockCheck = $db->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $stockCheck->execute([$variantId]);
                $currentStock = (int)$stockCheck->fetchColumn();
                if ($currentStock <= 0) {
                    $db->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$variantId]);
                }
            }
        }

        $db->commit();
        setFlash('success', $isEdit ? 'Produk berhasil diupdate.' : 'Produk berhasil ditambahkan.');
    } catch (Exception $e) {
        $db->rollBack();
        // --- Security: Don't expose internal error details ---
        error_log('Product save error: ' . $e->getMessage());
        setFlash('error', 'Gagal menyimpan produk. Silakan coba lagi.');
    }

    header('Location: ' . APP_URL . '/index.php?page=products');
    exit;
}

function handleProducts($db, $action) {
    // --- Security: Only admin can delete products ---
    requireAdmin();

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

    // --- Security: Validate inventory type whitelist ---
    if (!in_array($type, ['in', 'out'])) {
        setFlash('error', 'Tipe stok tidak valid.');
        header('Location: ' . APP_URL . '/index.php?page=inventory');
        exit;
    }

    if ($variant_id <= 0 || $quantity <= 0) {
        setFlash('error', 'Varian dan jumlah harus diisi.');
        header('Location: ' . APP_URL . '/index.php?page=inventory');
        exit;
    }

    // --- Security: Validate quantity range ---
    if ($quantity > 99999) {
        setFlash('error', 'Jumlah tidak valid.');
        header('Location: ' . APP_URL . '/index.php?page=inventory');
        exit;
    }

    // --- Security: Validate reason length ---
    if (strlen($reason) > 255) {
        $reason = substr($reason, 0, 255);
    }

    try {
        $db->beginTransaction();

        // --- Security: Verify variant exists before updating ---
        $varCheck = $db->prepare("SELECT id, stock FROM product_variants WHERE id = ?");
        $varCheck->execute([$variant_id]);
        $variantRecord = $varCheck->fetch();
        if (!$variantRecord) {
            throw new Exception('Varian tidak ditemukan.');
        }

        // --- Security: For stock out, verify sufficient stock ---
        if ($type === 'out' && $variantRecord['stock'] < $quantity) {
            throw new Exception('Stok tidak mencukupi.');
        }

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
        setFlash('error', 'Gagal update stok. Silakan coba lagi.');
        error_log('Inventory error: ' . $e->getMessage());
    }

    header('Location: ' . APP_URL . '/index.php?page=inventory&tab=history');
    exit;
}

function handleUsers($db, $action) {
    requireAdmin();

    // --- Security: Whitelist valid actions ---
    if (!in_array($action, ['delete', 'save'])) {
        header('Location: ' . APP_URL . '/index.php?page=users');
        exit;
    }

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

        // --- Security: Validate role whitelist ---
        if (!in_array($role, ['admin', 'kasir'])) {
            setFlash('error', 'Role tidak valid.');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        // --- Security: Validate status whitelist ---
        if (!in_array($status, ['active', 'inactive'])) {
            setFlash('error', 'Status tidak valid.');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        if (empty($name) || empty($username)) {
            setFlash('error', 'Nama dan username harus diisi.');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        // --- Security: Validate username format ---
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            setFlash('error', 'Username hanya boleh huruf, angka, dan underscore (3-50 karakter).');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        // --- Security: Validate name length ---
        if (strlen($name) > 100) {
            setFlash('error', 'Nama terlalu panjang (maks 100 karakter).');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        // --- Security: Check for duplicate username ---
        $dupCheck = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $dupCheck->execute([$username, $id]);
        if ($dupCheck->fetch()) {
            setFlash('error', 'Username sudah digunakan.');
            header('Location: ' . APP_URL . '/index.php?page=users');
            exit;
        }

        if ($id > 0) {
            // Update
            $sql = "UPDATE users SET name=?, username=?, role=?, status=?";
            $params = [$name, $username, $role, $status];
            if (!empty($password)) {
                // --- Security: Password complexity validation ---
                $pwdError = validatePasswordComplexity($password);
                if ($pwdError !== null) {
                    setFlash('error', $pwdError);
                    header('Location: ' . APP_URL . '/index.php?page=users');
                    exit;
                }
                $sql .= ", password=?";
                $params[] = securePasswordHash($password);
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
            // --- Security: Password complexity validation ---
            $pwdError = validatePasswordComplexity($password);
            if ($pwdError !== null) {
                setFlash('error', $pwdError);
                header('Location: ' . APP_URL . '/index.php?page=users');
                exit;
            }
            $hashed = securePasswordHash($password);
            $db->prepare("INSERT INTO users (name, username, password, role, status) VALUES (?,?,?,?,?)")
                ->execute([$name, $username, $hashed, $role, $status]);
            setFlash('success', 'Pengguna berhasil ditambahkan.');
        }
    }

    header('Location: ' . APP_URL . '/index.php?page=users');
    exit;
}

function handleSettings($db) {
    requireAdmin();
    $keys = ['store_name', 'store_address', 'store_phone', 'receipt_footer', 'default_min_stock'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            // --- Security: Limit setting value length ---
            if (strlen($val) > 500) { $val = substr($val, 0, 500); }
            $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([$key, $val, $val]);
        }
    }
    setFlash('success', 'Pengaturan berhasil disimpan.');
    header('Location: ' . APP_URL . '/index.php?page=settings');
    exit;
}

function handleTransactions($db, $action) {
    // --- Security: Whitelist action ---
    if ($action !== 'void') {
        header('Location: ' . APP_URL . '/index.php?page=transactions');
        exit;
    }

    if ($action === 'void' && isAdmin()) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->beginTransaction();

                // Check transaction exists and is not already voided
                $txCheck = $db->prepare("SELECT status FROM transactions WHERE id = ?");
                $txCheck->execute([$id]);
                $txStatus = $txCheck->fetchColumn();

                if ($txStatus !== 'completed') {
                    throw new Exception('Transaksi sudah dibatalkan atau tidak ditemukan.');
                }

                // Restore stock
                $items = $db->prepare("SELECT variant_id, quantity FROM transaction_items WHERE transaction_id = ?");
                $items->execute([$id]);
                foreach ($items->fetchAll() as $item) {
                    $db->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?")
                        ->execute([$item['quantity'], $item['variant_id']]);
                }

                // Mark as voided
                $db->prepare("UPDATE transactions SET status = 'voided' WHERE id = ?")->execute([$id]);

                $db->commit();
                setFlash('success', 'Transaksi berhasil dibatalkan dan stok dikembalikan.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Gagal membatalkan transaksi: ' . e($e->getMessage()));
            }
        }
    }
    header('Location: ' . APP_URL . '/index.php?page=transactions');
    exit;
}
