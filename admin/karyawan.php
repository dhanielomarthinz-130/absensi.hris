<?php
// admin/karyawan.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_karyawan';
$pageTitle = 'Manajemen Karyawan & Divisi';
$pageSubtitle = 'Kelola data staf operator, penugasan supervisor per divisi, dan akun pengguna';

// Handle Tambah Karyawan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $fullName = trim($_POST['full_name']);
        $role = $_POST['role'];
        $divisionId = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
        $position = trim($_POST['position']);
        $phone = trim($_POST['phone']);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, password_hash, full_name, role, division_id, position, phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hash, $fullName, $role, $divisionId, $position, $phone]);

            // Jika supervisor, tanyakan atau update division supervisor_id
            if ($role === 'supervisor' && $divisionId) {
                $db->prepare("UPDATE divisions SET supervisor_id = ? WHERE id = ?")->execute([$db->lastInsertId(), $divisionId]);
            }

            Helpers::setFlash('success', "Karyawan baru ({$fullName}) berhasil ditambahkan.");
        } catch (PDOException $e) {
            Helpers::setFlash('error', "Gagal menambah user: " . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/admin/karyawan.php');
        exit;
    }

    if ($_POST['action'] === 'assign_supervisor') {
        $divisionId = (int)$_POST['division_id'];
        $spvId = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

        $stmt = $db->prepare("UPDATE divisions SET supervisor_id = ? WHERE id = ?");
        $stmt->execute([$spvId, $divisionId]);
        Helpers::setFlash('success', 'Penugasan Supervisor Divisi berhasil diperbarui.');
        header('Location: ' . BASE_URL . '/admin/karyawan.php');
        exit;
    }
}

// Ambil Divisi & Supervisior
$divisions = $db->query("
    SELECT d.*, spv.full_name as supervisor_name 
    FROM divisions d 
    LEFT JOIN users spv ON d.supervisor_id = spv.id 
    ORDER BY d.id ASC
")->fetchAll();

// Ambil Karyawan
$users = $db->query("
    SELECT u.*, d.name as division_name 
    FROM users u 
    LEFT JOIN divisions d ON u.division_id = d.id 
    ORDER BY u.role ASC, d.id ASC, u.full_name ASC
")->fetchAll();

$supervisors = array_filter($users, fn($u) => $u['role'] === 'supervisor');

require_once __DIR__ . '/../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        Total: <strong style="color: var(--text-heading);"><?= count($users) ?> Karyawan</strong> di <?= count($divisions) ?> Divisi
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addUserModal')">
        <i class="ti ti-user-plus"></i> Tambah Karyawan Baru
    </button>
</div>

<!-- SECTION 1: PEMETAAN SUPERVISOR PER DIVISI -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-building" style="color: var(--primary);"></i>
            Struktur Divisi & Penanggung Jawab (Supervisor)
        </div>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE: DIVISI CARDS -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php foreach ($divisions as $d): ?>
            <div class="ios-data-card">
                <div class="ios-card-header">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-family: monospace; font-weight: 800; background: #e0e7ff; color: #4338ca; padding: 3px 8px; border-radius: 8px; font-size: 0.8rem;">
                            <?= htmlspecialchars($d['code']) ?>
                        </span>
                        <div style="font-weight: 800; font-size: 1rem; color: #0f172a;">
                            <?= htmlspecialchars($d['name']) ?>
                        </div>
                    </div>
                </div>

                <div style="font-size: 0.775rem; color: var(--text-muted);">
                    <?= htmlspecialchars($d['description']) ?>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px;">
                    <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 4px;">
                        SUPERVISOR PENANGGUNG JAWAB:
                    </div>
                    <div style="font-weight: 700; font-size: 0.85rem; color: #10b981; margin-bottom: 8px;">
                        <?php if ($d['supervisor_name']): ?>
                            <i class="ti ti-user-check"></i> <?= htmlspecialchars($d['supervisor_name']) ?>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-weight: normal; font-style: italic;">Belum ada supervisor</span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="action" value="assign_supervisor">
                        <input type="hidden" name="division_id" value="<?= $d['id'] ?>">
                        <label style="font-size: 0.68rem; font-weight: 700; color: #64748b; margin-bottom: 3px; display: block;">
                            GANTI SUPERVISOR:
                        </label>
                        <select name="supervisor_id" class="form-control" style="font-size: 0.8125rem; border-radius: 8px;" onchange="this.form.submit()">
                            <option value="">-- Pilih Supervisor --</option>
                            <?php foreach ($supervisors as $spv): ?>
                                <option value="<?= $spv['id'] ?>" <?= $d['supervisor_id'] == $spv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spv['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (TABLE) -->
    <div class="card-body desktop-only-view">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Divisi</th>
                        <th>Deskripsi</th>
                        <th>Supervisor Saat Ini</th>
                        <th style="text-align: center;">Ubah Supervisor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($divisions as $d): ?>
                        <tr>
                            <td><span style="font-family: monospace; font-weight: 700;"><?= htmlspecialchars($d['code']) ?></span></td>
                            <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($d['description']) ?></td>
                            <td>
                                <?php if ($d['supervisor_name']): ?>
                                    <span style="color: #10b981; font-weight: 600;"><i class="ti ti-user-check"></i> <?= htmlspecialchars($d['supervisor_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" action="" style="display: inline-flex; gap: 6px;">
                                    <input type="hidden" name="action" value="assign_supervisor">
                                    <input type="hidden" name="division_id" value="<?= $d['id'] ?>">
                                    <select name="supervisor_id" class="form-control" style="font-size: 0.75rem; padding: 4px 8px; width: auto;" onchange="this.form.submit()">
                                        <option value="">-- Pilih SPV --</option>
                                        <?php foreach ($supervisors as $spv): ?>
                                            <option value="<?= $spv['id'] ?>" <?= $d['supervisor_id'] == $spv['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($spv['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECTION 2: DAFTAR SELURUH KARYAWAN & ROLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-users" style="color: var(--primary);"></i>
            Daftar Seluruh Pengguna & Karyawan
        </div>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE: USER CARDS -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php foreach ($users as $u): ?>
            <div class="ios-data-card">
                <div class="ios-card-header">
                    <div class="ios-card-user">
                        <div class="ios-card-avatar">
                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                        </div>
                        <div style="min-width: 0;">
                            <div class="ios-card-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($u['full_name']) ?>
                            </div>
                            <div class="ios-card-sub" style="font-family: monospace; color: #6366f1;">
                                @<?= htmlspecialchars($u['username']) ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="user-role-badge <?= htmlspecialchars($u['role']) ?>" style="font-size: 0.65rem; padding: 2px 8px;">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </div>
                </div>

                <div class="ios-grid-2">
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">DIVISI</span>
                        <span class="ios-pill-val" style="font-size: 0.8rem;">
                            <?= htmlspecialchars($u['division_name'] ?? 'Pusat') ?>
                        </span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">JABATAN</span>
                        <span class="ios-pill-val" style="font-size: 0.8rem;">
                            <?= htmlspecialchars($u['position'] ?? '-') ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($u['phone'])): ?>
                    <div style="font-size: 0.775rem; color: #64748b; display: flex; align-items: center; gap: 6px;">
                        <i class="ti ti-phone" style="color: #10b981;"></i>
                        <a href="tel:<?= htmlspecialchars($u['phone']) ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                            <?= htmlspecialchars($u['phone']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (TABLE) -->
    <div class="card-body desktop-only-view">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Karyawan</th>
                        <th>Username</th>
                        <th>Peran (Role)</th>
                        <th>Divisi</th>
                        <th>Jabatan</th>
                        <th>Telepon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                            </td>
                            <td><span style="font-family: monospace;"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td>
                                <span class="user-role-badge <?= htmlspecialchars($u['role']) ?>">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['division_name'] ?? 'Pusat / Seluruh Divisi') ?></td>
                            <td><?= htmlspecialchars($u['position']) ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div class="modal-backdrop" id="addUserModal">
    <div class="modal-content">
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_user">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="ti ti-user-plus" style="color: var(--primary);"></i>
                    Tambah Karyawan / Akun Baru
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('addUserModal')">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Contoh: Budi Cahyono" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="username login" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kata Sandi</label>
                        <input type="password" name="password" class="form-control" placeholder="minimal 6 karakter" required>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Role Akses</label>
                        <select name="role" class="form-control" required>
                            <option value="operator">Operator (Karyawan)</option>
                            <option value="supervisor">Supervisor (Kepala Divisi)</option>
                            <option value="admin">Admin HR</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Divisi</label>
                        <select name="division_id" class="form-control">
                            <option value="">-- Pilih Divisi --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Posisi / Jabatan</label>
                        <input type="text" name="position" class="form-control" placeholder="Contoh: Operator Mesin B">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxx">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
