<?php
$db = getDB();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div class="toolbar">
    <div class="toolbar-left"><h3 style="font-size:var(--fs-md);display:flex;align-items:center;gap:var(--sp-2);"><i data-lucide="users" style="width:20px;height:20px;color:var(--accent);"></i> Daftar Pengguna</h3></div>
    <div class="toolbar-right">
        <button class="btn btn-primary" onclick="openUserModal()"><i data-lucide="user-plus" style="width:16px;height:16px;"></i> Tambah Pengguna</button>
    </div>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th></th><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <div class="user-avatar" style="width:32px;height:32px;font-size:var(--fs-xs);">
                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
            </td>
            <td class="font-bold"><?= htmlspecialchars($u['name']) ?></td>
            <td class="text-secondary"><?= htmlspecialchars($u['username']) ?></td>
            <td><span class="badge <?= $u['role']==='admin'?'badge-gold':'badge-info' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><span class="badge <?= $u['status']==='active'?'badge-success':'badge-danger' ?>"><?= $u['status']==='active'?'🟢 Aktif':'🔴 Nonaktif' ?></span></td>
            <td class="text-sm text-secondary"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:var(--sp-2);">
                    <?php
                    // Security: Remove password hash before sending to client
                    $safeUser = ['id' => $u['id'], 'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role'], 'status' => $u['status']];
                    ?>
                    <button class="btn btn-ghost btn-sm" onclick='editUser(<?= json_encode($safeUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i data-lucide="pencil" style="width:14px;height:14px;"></i></button>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" action="<?= APP_URL ?>/index.php?page=users&action=delete" onsubmit="return confirm('Yakin hapus pengguna ini?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- User Modal -->
<div class="modal-backdrop" id="userModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Tambah Pengguna</h3>
            <button class="modal-close" onclick="closeUserModal()">✕</button>
        </div>
        <form method="POST" action="<?= APP_URL ?>/index.php?page=users&action=save">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="userId" value="">
            <div class="form-group">
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="name" id="userName" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username *</label>
                <input type="text" name="username" id="userUsername" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password <span id="pwdHint" class="text-muted">(wajib untuk user baru)</span></label>
                <input type="password" name="password" id="userPassword" class="form-input" placeholder="Kosongkan jika tidak ingin mengubah">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" id="userRole" class="form-select" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" id="userStatus" class="form-select" required>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save" style="width:16px;height:16px;"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userModalTitle').textContent = 'Tambah Pengguna';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = 'kasir';
    document.getElementById('userStatus').value = 'active';
    document.getElementById('pwdHint').textContent = '(wajib untuk user baru)';
    document.getElementById('userModal').classList.add('active');
}

function editUser(user) {
    document.getElementById('userModalTitle').textContent = 'Edit Pengguna';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userUsername').value = user.username;
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = user.role;
    document.getElementById('userStatus').value = user.status;
    document.getElementById('pwdHint').textContent = '(kosongkan jika tidak mengubah)';
    document.getElementById('userModal').classList.add('active');
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
}
</script>
