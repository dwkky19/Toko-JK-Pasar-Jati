<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale — Toko JK Pasar Jati</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pos.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.min.js"></script>
</head>
<body>
<?php $user = currentUser(); $db = getDB(); $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(); ?>

<div class="pos-layout">
    <div class="pos-header">
        <div class="pos-header-left">
            <a href="<?= APP_URL ?>/index.php?page=dashboard" class="pos-back">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
                Kembali
            </a>
            <span class="pos-brand">Toko JK POS</span>
        </div>
        <div class="pos-header-right">
            <div style="display:flex;gap:var(--sp-3);font-size:var(--fs-xs);color:var(--text-muted);">
                <span><span class="kbd">F1</span> Cari</span>
                <span><span class="kbd">F2</span> Clear</span>
                <span><span class="kbd">F12</span> Bayar</span>
            </div>
            <span style="color:var(--border-active);">|</span>
            <span>👤 <?= htmlspecialchars($user['name']) ?></span>
            <span>🕐 <span id="clock"></span></span>
        </div>
    </div>

    <div class="pos-body">
        <!-- Product Panel -->
        <div class="pos-products">
            <div class="pos-search">
                <div class="pos-search-input">
                    <i data-lucide="search" style="width:18px;height:18px;"></i>
                    <input type="text" id="searchInput" placeholder="Cari produk atau scan barcode..." autofocus>
                </div>
                <div class="pos-categories">
                    <button class="cat-btn active" data-cat="">Semua</button>
                    <?php foreach ($categories as $cat): ?>
                    <button class="cat-btn" data-cat="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pos-product-grid" id="productGrid">
                <div class="empty-state"><p>Memuat produk...</p></div>
            </div>
        </div>

        <!-- Cart Panel -->
        <div class="pos-cart">
            <div class="pos-cart-header">
                <span>🛒 Keranjang</span>
                <span class="cart-count" id="cartCount">0</span>
            </div>
            <div class="pos-cart-items" id="cartItems">
                <div class="cart-empty">
                    <i data-lucide="shopping-cart" style="width:48px;height:48px;opacity:0.3;margin-bottom:var(--sp-3);"></i>
                    <p style="font-size:var(--fs-sm);">Keranjang kosong</p>
                    <p style="font-size:var(--fs-xs);">Pilih produk untuk mulai transaksi</p>
                </div>
            </div>
            <div class="pos-cart-footer">
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalDisplay">Rp 0</span>
                </div>
                <div class="discount-row">
                    <span style="font-size:var(--fs-sm);color:var(--text-secondary);margin-right:auto;">Diskon</span>
                    <div class="discount-type-toggle">
                        <button class="active" id="discPercent" onclick="setDiscountType('percent')">%</button>
                        <button id="discNominal" onclick="setDiscountType('nominal')">Rp</button>
                    </div>
                    <input type="number" class="discount-input" id="discountInput" value="0" min="0" oninput="updateCart()">
                </div>
                <div class="cart-summary-row" id="discountRow" style="display:none;">
                    <span>Potongan</span>
                    <span class="text-danger" id="discountDisplay">-Rp 0</span>
                </div>
                <div class="cart-summary-row total">
                    <span>TOTAL</span>
                    <span id="totalDisplay">Rp 0</span>
                </div>

                <div class="payment-methods">
                    <button class="pay-method-btn active" data-method="cash" onclick="selectPayment('cash')">
                        <i data-lucide="banknote" style="width:20px;height:20px;margin:0 auto 4px;display:block;"></i>
                        Tunai
                    </button>
                    <button class="pay-method-btn" data-method="qris" onclick="selectPayment('qris')">
                        <i data-lucide="smartphone" style="width:20px;height:20px;margin:0 auto 4px;display:block;"></i>
                        QRIS
                    </button>
                    <button class="pay-method-btn" data-method="transfer" onclick="selectPayment('transfer')">
                        <i data-lucide="landmark" style="width:20px;height:20px;margin:0 auto 4px;display:block;"></i>
                        Transfer
                    </button>
                </div>

                <button class="pay-btn" id="payBtn" onclick="openPayment()" disabled>
                    🛒 BAYAR — <span id="payBtnTotal">Rp 0</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Variant Modal -->
<div class="modal-backdrop" id="variantModal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title" id="variantProductName">Pilih Varian</h3>
            <button class="modal-close" onclick="closeVariantModal()">✕</button>
        </div>
        <div id="variantContent"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeVariantModal()">Batal</button>
            <button class="btn btn-primary" id="addToCartBtn" onclick="addSelectedToCart()" disabled>Tambah ke Keranjang</button>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal-backdrop" id="paymentModal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3 class="modal-title">💳 Pembayaran</h3>
            <button class="modal-close" onclick="closePaymentModal()">✕</button>
        </div>
        <div style="text-align:center;margin-bottom:var(--sp-4);">
            <div style="font-size:var(--fs-sm);color:var(--text-secondary);">Total yang harus dibayar</div>
            <div style="font-size:var(--fs-2xl);font-weight:700;color:var(--accent);" id="payModalTotal">Rp 0</div>
        </div>
        <div id="cashPayment">
            <div class="payment-input-row">
                <label>Bayar</label>
                <input type="number" id="payAmountInput" oninput="calcChange()" placeholder="0">
            </div>
            <div class="change-display" id="changeDisplay" style="display:none;">
                <div class="change-label">Kembalian</div>
                <div class="change-amount" id="changeAmount">Rp 0</div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:var(--sp-2);margin-top:var(--sp-3);" id="quickAmounts"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePaymentModal()">Batal</button>
            <button class="btn btn-primary btn-lg" id="confirmPayBtn" onclick="processPayment()" disabled>Proses Pembayaran</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal-backdrop" id="successModal">
    <div class="success-card">
        <div class="success-icon">
            <i data-lucide="check" style="width:32px;height:32px;"></i>
        </div>
        <h2 style="font-size:var(--fs-xl);margin-bottom:var(--sp-2);color:var(--text-primary);">Transaksi Berhasil!</h2>
        <p id="successInvoice" style="color:var(--text-secondary);margin-bottom:var(--sp-4);"></p>
        <div id="successDetails" style="text-align:left;margin-bottom:var(--sp-5);"></div>
        <div style="display:flex;gap:var(--sp-3);justify-content:center;">
            <button class="btn btn-secondary" onclick="closeSuccess()">Transaksi Baru</button>
            <button class="btn btn-primary" onclick="printReceipt()">🖨️ Cetak Struk</button>
        </div>
    </div>
</div>

<script>
const APP = '<?= APP_URL ?>';
let products = [];
let cart = [];
let selectedVariant = null;
let selectedProduct = null;
let discountType = 'percent';
let paymentMethod = 'cash';
let lastTransaction = null;
let lastCartItems = [];

function formatRp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

// Init lucide
document.addEventListener('DOMContentLoaded', function() {
    if(window.lucide) lucide.createIcons();
    loadProducts();
});

// Clock
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
}
updateClock();
setInterval(updateClock, 1000);

// Load products
async function loadProducts(category = '', search = '') {
    try {
        const res = await fetch(`${APP}/index.php?page=api&action=products&category=${encodeURIComponent(category)}&search=${encodeURIComponent(search)}`);
        products = await res.json();
        renderProducts();
    } catch (e) { console.error(e); }
}

function renderProducts() {
    const grid = document.getElementById('productGrid');
    if (!products.length) {
        grid.innerHTML = '<div class="empty-state"><p>Tidak ada produk ditemukan</p></div>';
        return;
    }
    grid.innerHTML = products.map(p => {
        const outOfStock = (p.total_stock || 0) <= 0;
        const imgSrc = p.image ? `${APP}/uploads/products/${p.image}` : '';
        const kodeBarang = p.kode_barang || '-';
        return `<div class="product-card ${outOfStock ? 'out-of-stock' : ''}" onclick="openVariantModal(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.sell_price})">
            <div class="product-card-img">${imgSrc ? `<img src="${imgSrc}" alt="${p.name}" loading="lazy">` : '👕'}</div>
            <div class="product-card-info">
                <div class="product-card-code" style="font-size:var(--fs-xs);color:var(--text-muted);font-family:monospace;">${kodeBarang}</div>
                <div class="product-card-name">${p.name}</div>
                <div class="product-card-price">${formatRp(p.sell_price)}</div>
                <div class="product-card-stock">${outOfStock ? '⛔ Habis' : 'Stok: ' + p.total_stock}</div>
            </div>
        </div>`;
    }).join('');
}

// Category filter
document.querySelectorAll('.cat-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        loadProducts(this.dataset.cat, document.getElementById('searchInput').value);
    });
});

// Search with debounce
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const activeCat = document.querySelector('.cat-btn.active')?.dataset.cat || '';
        loadProducts(activeCat, this.value);
    }, 300);
});

// Variant modal
async function openVariantModal(productId, productName, sellPrice) {
    selectedProduct = { id: productId, name: productName, price: sellPrice };
    selectedVariant = null;
    document.getElementById('variantProductName').textContent = productName;
    document.getElementById('addToCartBtn').disabled = true;

    try {
        const res = await fetch(`${APP}/index.php?page=api&action=variants&product_id=${productId}`);
        const variants = await res.json();

        const colors = {};
        variants.forEach(v => {
            if (!colors[v.color]) colors[v.color] = [];
            colors[v.color].push(v);
        });

        let html = '';
        for (const [color, sizes] of Object.entries(colors)) {
            html += `<div style="margin-bottom:var(--sp-4);">
                <div style="font-size:var(--fs-sm);font-weight:600;margin-bottom:var(--sp-2);display:flex;align-items:center;gap:var(--sp-2);">
                    <span class="color-dot" style="background:${getColorHex(color)}"></span> ${color}
                </div>
                <div class="variant-grid">
                    ${sizes.map(s => `<button class="variant-btn ${s.stock <= 0 ? 'disabled' : ''}" 
                        data-id="${s.id}" data-stock="${s.stock}" data-size="${s.size}" data-color="${s.color}" data-sku="${s.sku}"
                        onclick="selectVariant(this)" ${s.stock <= 0 ? 'disabled' : ''}>
                        ${s.size}<span class="variant-stock">${s.stock} pcs</span>
                    </button>`).join('')}
                </div>
            </div>`;
        }
        document.getElementById('variantContent').innerHTML = html;
    } catch (e) { console.error(e); }

    document.getElementById('variantModal').classList.add('active');
}

function getColorHex(color) {
    const map = {Red:'#e74c3c',Blue:'#3498db',Black:'#4a5568',White:'#e2e8f0',Pink:'#e91e90',Cream:'#f5e6ca',Grey:'#718096',Brown:'#8b4513',Green:'#27ae60',Yellow:'#f1c40f',Navy:'#1e3a5f'};
    return map[color] || '#64748B';
}

function selectVariant(btn) {
    document.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedVariant = {
        id: parseInt(btn.dataset.id),
        size: btn.dataset.size,
        color: btn.dataset.color,
        stock: parseInt(btn.dataset.stock),
        sku: btn.dataset.sku,
    };
    document.getElementById('addToCartBtn').disabled = false;
}

function closeVariantModal() { document.getElementById('variantModal').classList.remove('active'); }

function addSelectedToCart() {
    if (!selectedVariant || !selectedProduct) return;
    const existing = cart.find(c => c.variant_id === selectedVariant.id);
    if (existing) {
        if (existing.qty < selectedVariant.stock) {
            existing.qty++;
        } else {
            Swal.fire({ icon:'warning', title:'Stok tidak cukup!', background:'#FFFFFF', color:'#1E293B', confirmButtonColor:'#6366F1' });
            return;
        }
    } else {
        cart.push({
            variant_id: selectedVariant.id,
            product_name: selectedProduct.name,
            variant_info: selectedVariant.color + ' - ' + selectedVariant.size,
            price: selectedProduct.price,
            qty: 1,
            max_stock: selectedVariant.stock,
        });
    }
    closeVariantModal();
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    if (!cart.length) {
        container.innerHTML = `<div class="cart-empty">
            <i data-lucide="shopping-cart" style="width:48px;height:48px;opacity:0.3;margin-bottom:var(--sp-3);"></i>
            <p style="font-size:var(--fs-sm);">Keranjang kosong</p></div>`;
        if(window.lucide) lucide.createIcons();
        updateCart();
        return;
    }
    container.innerHTML = cart.map((item, i) => `<div class="cart-item">
        <div class="cart-item-info">
            <div class="cart-item-name">${item.product_name}</div>
            <div class="cart-item-variant">${item.variant_info}</div>
            <div class="cart-item-price">${formatRp(item.price * item.qty)}</div>
        </div>
        <div class="cart-item-actions">
            <button class="qty-btn" onclick="changeQty(${i},-1)">−</button>
            <span class="cart-item-qty">${item.qty}</span>
            <button class="qty-btn" onclick="changeQty(${i},1)">+</button>
            <button class="cart-item-remove" onclick="removeItem(${i})">
                <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
            </button>
        </div>
    </div>`).join('');
    if(window.lucide) lucide.createIcons();
    updateCart();
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    if (newQty <= 0) { removeItem(index); return; }
    if (newQty > item.max_stock) {
        Swal.fire({ icon:'warning', title:'Stok tidak cukup!', background:'#FFFFFF', color:'#1E293B', confirmButtonColor:'#6366F1' });
        return;
    }
    item.qty = newQty;
    renderCart();
}

function removeItem(index) { cart.splice(index, 1); renderCart(); }

function updateCart() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    let discountAmt = 0;
    if (discountType === 'percent' && discountVal > 0) {
        discountAmt = subtotal * (discountVal / 100);
    } else if (discountType === 'nominal') {
        discountAmt = discountVal;
    }
    const total = Math.max(0, subtotal - discountAmt);

    document.getElementById('cartCount').textContent = totalQty;
    document.getElementById('subtotalDisplay').textContent = formatRp(subtotal);
    document.getElementById('totalDisplay').textContent = formatRp(total);
    document.getElementById('payBtnTotal').textContent = formatRp(total);
    document.getElementById('payBtn').disabled = cart.length === 0;

    if (discountAmt > 0) {
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountDisplay').textContent = '-' + formatRp(discountAmt);
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }
}

function setDiscountType(type) {
    discountType = type;
    document.getElementById('discPercent').classList.toggle('active', type === 'percent');
    document.getElementById('discNominal').classList.toggle('active', type === 'nominal');
    updateCart();
}

function selectPayment(method) {
    paymentMethod = method;
    document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === method));
    updateCart();
}

// Payment
function openPayment() {
    if (!cart.length) return;
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    let discountAmt = discountType === 'percent' ? subtotal * (discountVal / 100) : discountVal;
    const total = Math.max(0, subtotal - discountAmt);

    document.getElementById('payModalTotal').textContent = formatRp(total);
    document.getElementById('payAmountInput').value = '';
    document.getElementById('changeDisplay').style.display = 'none';
    document.getElementById('confirmPayBtn').disabled = true;

    if (paymentMethod === 'cash') {
        document.getElementById('cashPayment').style.display = 'block';
        const quickAmts = [total, Math.ceil(total/50000)*50000, Math.ceil(total/100000)*100000].filter((v,i,a)=>a.indexOf(v)===i);
        document.getElementById('quickAmounts').innerHTML = quickAmts.map(a => 
            `<button class="btn btn-secondary btn-sm" onclick="document.getElementById('payAmountInput').value=${a};calcChange()">${formatRp(a)}</button>`
        ).join('');
    } else {
        document.getElementById('cashPayment').style.display = 'none';
        document.getElementById('confirmPayBtn').disabled = false;
    }

    document.getElementById('paymentModal').classList.add('active');
    if (paymentMethod === 'cash') {
        setTimeout(() => document.getElementById('payAmountInput').focus(), 200);
    }
}

function calcChange() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    let discountAmt = discountType === 'percent' ? subtotal * (discountVal / 100) : discountVal;
    const total = Math.max(0, subtotal - discountAmt);
    const paid = parseFloat(document.getElementById('payAmountInput').value) || 0;
    const change = paid - total;

    if (paid > 0) {
        document.getElementById('changeDisplay').style.display = 'block';
        document.getElementById('changeAmount').textContent = formatRp(Math.max(0, change));
        document.getElementById('changeAmount').style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
        document.getElementById('confirmPayBtn').disabled = change < 0;
    } else {
        document.getElementById('changeDisplay').style.display = 'none';
        document.getElementById('confirmPayBtn').disabled = true;
    }
}

function closePaymentModal() { document.getElementById('paymentModal').classList.remove('active'); }

async function processPayment() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discountVal = parseFloat(document.getElementById('discountInput').value) || 0;
    let discountAmt = discountType === 'percent' ? subtotal * (discountVal / 100) : discountVal;
    const total = Math.max(0, subtotal - discountAmt);
    let payAmount = total;
    if (paymentMethod === 'cash') {
        payAmount = parseFloat(document.getElementById('payAmountInput').value) || 0;
    }

    document.getElementById('confirmPayBtn').disabled = true;
    document.getElementById('confirmPayBtn').textContent = 'Memproses...';

    try {
        const res = await fetch(`${APP}/index.php?page=api&action=checkout`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                items: cart.map(c => ({variant_id: c.variant_id, qty: c.qty})),
                discount_type: discountVal > 0 ? discountType : null,
                discount_value: discountVal,
                payment_method: paymentMethod,
                payment_amount: payAmount,
            }),
        });
        const data = await res.json();

        if (data.error) {
            Swal.fire({ icon:'error', title:'Gagal', text:data.error, background:'#FFFFFF', color:'#1E293B', confirmButtonColor:'#6366F1' });
            document.getElementById('confirmPayBtn').disabled = false;
            document.getElementById('confirmPayBtn').textContent = 'Proses Pembayaran';
            return;
        }

        lastTransaction = data;
        closePaymentModal();
        showSuccess(data);
    } catch (e) {
        Swal.fire({ icon:'error', title:'Error', text:e.message, background:'#FFFFFF', color:'#1E293B', confirmButtonColor:'#6366F1' });
        document.getElementById('confirmPayBtn').disabled = false;
        document.getElementById('confirmPayBtn').textContent = 'Proses Pembayaran';
    }
}

function showSuccess(data) {
    const tx = data.transaction;
    const methodLabel = {cash:'💵 Tunai', qris:'📱 QRIS', transfer:'🏦 Transfer'}[tx.payment_method];
    document.getElementById('successInvoice').textContent = tx.invoice;

    lastCartItems = [...cart];

    let html = `<div style="background:var(--bg-elevated);border-radius:var(--radius-md);padding:var(--sp-4);border:1px solid var(--border);">`;
    cart.forEach(item => {
        html += `<div style="display:flex;justify-content:space-between;padding:4px 0;font-size:var(--fs-sm);">
            <span style="color:var(--text-secondary);">${item.product_name} (${item.variant_info}) x${item.qty}</span>
            <span>${formatRp(item.price * item.qty)}</span>
        </div>`;
    });
    html += `<div style="border-top:1px dashed var(--border);margin-top:var(--sp-2);padding-top:var(--sp-2);">`;
    if (tx.discount_amount > 0) {
        html += `<div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);color:var(--danger);"><span>Diskon</span><span>-${formatRp(tx.discount_amount)}</span></div>`;
    }
    html += `<div style="display:flex;justify-content:space-between;font-weight:700;font-size:var(--fs-md);margin-top:4px;"><span>Total</span><span class="text-accent">${formatRp(tx.total)}</span></div>`;
    html += `<div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);color:var(--text-secondary);margin-top:4px;"><span>Bayar (${methodLabel})</span><span>${formatRp(tx.payment_amount)}</span></div>`;
    if (tx.change_amount > 0) {
        html += `<div style="display:flex;justify-content:space-between;font-size:var(--fs-sm);color:var(--success);"><span>Kembalian</span><span>${formatRp(tx.change_amount)}</span></div>`;
    }
    html += `</div></div>`;
    document.getElementById('successDetails').innerHTML = html;
    document.getElementById('successModal').classList.add('active');
}

function closeSuccess() {
    document.getElementById('successModal').classList.remove('active');
    cart = [];
    document.getElementById('discountInput').value = 0;
    renderCart();
    loadProducts();
}

function printReceipt() {
    if (!lastTransaction) return;
    const tx = lastTransaction.transaction;
    const store = lastTransaction.store || {};
    const receiptItems = lastCartItems.length > 0 ? lastCartItems : cart;
    let receipt = `<html><head><title>Struk</title><style>
        body{font-family:monospace;font-size:12px;width:280px;margin:0 auto;padding:20px;}
        .center{text-align:center;} .line{border-top:1px dashed #000;margin:8px 0;}
        .row{display:flex;justify-content:space-between;} .bold{font-weight:bold;}
    </style></head><body>
    <div class="center"><h3>${store.store_name || 'Toko JK Pasar Jati'}</h3><p>${store.store_address || ''}</p><p>${store.store_phone || ''}</p></div>
    <div class="line"></div>
    <p>${tx.invoice}<br>${tx.date}<br>Kasir: ${tx.cashier}</p>
    <div class="line"></div>`;
    receiptItems.forEach(item => {
        receipt += `<div class="row"><span>${item.product_name}</span></div>
        <div class="row"><span>&nbsp; ${item.variant_info} x${item.qty}</span><span>${formatRp(item.price * item.qty)}</span></div>`;
    });
    receipt += `<div class="line"></div>`;
    if (tx.discount_amount > 0) receipt += `<div class="row"><span>Diskon</span><span>-${formatRp(tx.discount_amount)}</span></div>`;
    receipt += `<div class="row bold"><span>TOTAL</span><span>${formatRp(tx.total)}</span></div>`;
    const ml = {cash:'Tunai',qris:'QRIS',transfer:'Transfer'}[tx.payment_method];
    receipt += `<div class="row"><span>Bayar (${ml})</span><span>${formatRp(tx.payment_amount)}</span></div>`;
    if (tx.change_amount > 0) receipt += `<div class="row"><span>Kembali</span><span>${formatRp(tx.change_amount)}</span></div>`;
    receipt += `<div class="line"></div><p class="center">${store.receipt_footer || 'Terima kasih!'}</p></body></html>`;

    const win = window.open('', '_blank', 'width=320,height=600');
    win.document.write(receipt);
    win.document.close();
    win.print();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') {
        e.preventDefault();
        if (cart.length > 0) {
            Swal.fire({
                title: 'Hapus Keranjang?',
                text: 'Semua item akan dihapus',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                background: '#FFFFFF',
                color: '#1E293B',
                confirmButtonColor: '#DC2626',
            }).then(r => {
                if (r.isConfirmed) {
                    cart = [];
                    document.getElementById('discountInput').value = 0;
                    renderCart();
                }
            });
        }
    }
    if (e.key === 'F12') { e.preventDefault(); if (cart.length > 0) openPayment(); }
    if (e.key === 'Escape') { closeVariantModal(); closePaymentModal(); if (document.getElementById('successModal').classList.contains('active')) closeSuccess(); }
    if (e.key === 'F1') { e.preventDefault(); document.getElementById('searchInput').focus(); }
});
</script>
</body>
</html>
