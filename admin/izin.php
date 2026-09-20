<?php
// admin/izin.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_izin';
$pageTitle = 'Pusat Persetujuan Izin & Sakit';
$pageSubtitle = 'Review perizinan, pratinjau surat keterangan dokter, dan pengaturan jalur persetujuan (Supervisor / Admin)';

// Handle POST actions: Approve, Reject, Ganti Jalur Approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leaveId = (int)$_POST['leave_id'];

    if ($_POST['action'] === 'approve') {
        $stmt = $db->prepare("
            UPDATE leaves 
            SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, reviewer_role = 'admin'
            WHERE id = ?
        ");
        $stmt->execute([Auth::id(), $leaveId]);
        Helpers::setFlash('success', 'Pengajuan izin/sakit berhasil disetujui oleh Admin.');
        header('Location: ' . BASE_URL . '/admin/izin.php');
        exit;
    }

    if ($_POST['action'] === 'reject') {
        $rejectionNote = trim($_POST['rejection_note'] ?? 'Ditolak oleh Admin');
        $stmt = $db->prepare("
            UPDATE leaves 
            SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, reviewer_role = 'admin', rejection_note = ?
            WHERE id = ?
        ");
        $stmt->execute([Auth::id(), $rejectionNote, $leaveId]);
        Helpers::setFlash('warning', 'Pengajuan izin telah ditolak.');
        header('Location: ' . BASE_URL . '/admin/izin.php');
        exit;
    }

    // Fitur: Ganti Pengaturan Approval Per Divisi
    if ($_POST['action'] === 'update_division_approval') {
        $divId = (int)$_POST['division_id'];
        $approverUserId = !empty($_POST['approval_user_id']) ? (int)$_POST['approval_user_id'] : null;

        if ($approverUserId) {
            $stU = $db->prepare("SELECT role, full_name FROM users WHERE id = ?");
            $stU->execute([$approverUserId]);
            $uRow = $stU->fetch();
            $targetRole = in_array($uRow['role'] ?? '', ['admin', 'superadmin']) ? 'admin' : 'supervisor';
        } else {
            $targetRole = 'supervisor';
        }

        $stUp = $db->prepare("UPDATE divisions SET approval_user_id = ?, approval_target_role = ? WHERE id = ?");
        $stUp->execute([$approverUserId, $targetRole, $divId]);

        if (!empty($_POST['sync_pending'])) {
            $stSync = $db->prepare("
                UPDATE leaves 
                SET assigned_approver_id = ?, approval_target = ? 
                WHERE status = 'pending' AND user_id IN (SELECT id FROM users WHERE division_id = ?)
            ");
            $stSync->execute([$approverUserId, $targetRole, $divId]);
        }

        Helpers::setFlash('success', 'Pengaturan penanggung jawab approval divisi berhasil diperbarui.');
        header('Location: ' . BASE_URL . '/admin/izin.php#settingApprovalDivisi');
        exit;
    }

    // Fitur: Ganti Jalur / Verifikator Approval untuk permohonan spesifik
    if ($_POST['action'] === 'switch_approval_target') {
        $assignedApproverId = !empty($_POST['assigned_approver_id']) ? (int)$_POST['assigned_approver_id'] : null;
        if ($assignedApproverId) {
            $stU = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stU->execute([$assignedApproverId]);
            $uRole = $stU->fetchColumn();
            $newTarget = in_array($uRole, ['admin', 'superadmin']) ? 'admin' : 'supervisor';
        } else {
            $newTarget = 'admin';
        }
        $stmt = $db->prepare("UPDATE leaves SET approval_target = ?, assigned_approver_id = ? WHERE id = ?");
        $stmt->execute([$newTarget, $assignedApproverId, $leaveId]);
        Helpers::setFlash('info', "Verifikator approval untuk permohonan ini berhasil dialihkan.");
        header('Location: ' . BASE_URL . '/admin/izin.php');
        exit;
    }
}

// Ambil data divisi & penanggung jawab approval saat ini
$divisions = $db->query("
    SELECT 
        d.*, 
        spv.full_name as supervisor_name,
        approver.full_name as approver_name,
        approver.role as approver_role
    FROM divisions d
    LEFT JOIN users spv ON d.supervisor_id = spv.id
    LEFT JOIN users approver ON d.approval_user_id = approver.id
    ORDER BY d.id ASC
")->fetchAll();

// Ambil daftar seluruh user yang berhak menjadi approver (Superadmin, Admin, Supervisor)
$eligibleApprovers = $db->query("
    SELECT id, full_name, username, role, position, division_id
    FROM users 
    WHERE role IN ('superadmin', 'admin', 'supervisor')
    ORDER BY CASE WHEN role IN ('superadmin', 'admin') THEN 0 ELSE 1 END, full_name ASC
")->fetchAll();

// Ambil semua daftar pengajuan izin
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT 
        l.*, 
        u.full_name, u.position,
        d.id as division_id, d.name as division_name,
        spv.full_name as supervisor_name,
        approver.full_name as approver_name,
        assigned_apv.full_name as assigned_approver_name,
        assigned_apv.role as assigned_approver_role
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN users spv ON d.supervisor_id = spv.id
    LEFT JOIN users approver ON l.approved_by = approver.id
    LEFT JOIN users assigned_apv ON l.assigned_approver_id = assigned_apv.id
";

if ($statusFilter !== 'all') {
    $sql .= " WHERE l.status = " . $db->quote($statusFilter);
}
$sql .= " ORDER BY l.created_at DESC";

$leaves = $db->query($sql)->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Penjelasan Alur Approval & Pengaturan Divisi -->
<div class="card" id="settingApprovalDivisi" style="margin-bottom: 24px; border: 1px solid #e0e7ff; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.06);">
    <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div class="card-title" style="color: #312e81; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
            <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="ti ti-settings-cog"></i>
            </div>
            <div>
                <strong>Pengaturan Penanggung Jawab Approval Per Divisi</strong>
                <div style="font-size: 0.75rem; color: #64748b; font-weight: normal;">
                    Persetujuan izin karyawan kini ditentukan otomatis per divisi: dapat diarahkan ke <strong>User Admin</strong> atau <strong>Supervisor</strong> mana pun. Karyawan tidak perlu memilih manual.
                </div>
            </div>
        </div>
        <span class="badge" style="background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: 0.725rem; padding: 5px 12px; border-radius: 20px;">
            <?= count($divisions) ?> Divisi Terkonfigurasi
        </span>
    </div>
    <div class="card-body" style="padding: 16px 20px;">
        <!-- TAMPILAN MOBILE: NATIVE IOS / ANDROID CARDS -->
        <div class="mobile-only-view">
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($divisions as $div): ?>
                    <div class="ios-app-card">
                        <div class="ios-card-top">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="ios-card-icon">
                                    <i class="ti ti-building"></i>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <h4 class="ios-card-title"><?= htmlspecialchars($div['name']) ?></h4>
                                        <span class="ios-card-code"><?= htmlspecialchars($div['code']) ?></span>
                                    </div>
                                    <div class="ios-card-desc"><?= htmlspecialchars($div['description']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="ios-info-row">
                            <span class="ios-info-label">Supervisor Bawaan:</span>
                            <span class="ios-info-val">
                                <?php if ($div['supervisor_name']): ?>
                                    <i class="ti ti-user-check" style="color: #10b981;"></i> <?= htmlspecialchars($div['supervisor_name']) ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Belum ada SPV</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <form method="POST" action="" class="ios-card-form">
                            <input type="hidden" name="action" value="update_division_approval">
                            <input type="hidden" name="division_id" value="<?= $div['id'] ?>">

                            <label class="ios-form-label">
                                <i class="ti ti-shield-check" style="color: #4f46e5;"></i> Penanggung Jawab Approval:
                            </label>

                            <div class="ios-select-box">
                                <select name="approval_user_id" class="form-control ios-native-select">
                                    <optgroup label="👑 Super Administrator & Admin HR">
                                        <?php foreach ($eligibleApprovers as $ea): if (in_array($ea['role'], ['superadmin', 'admin'])): ?>
                                            <option value="<?= $ea['id'] ?>" <?= (int)$div['approval_user_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                🛡️ <?= htmlspecialchars($ea['full_name']) ?> (<?= strtoupper($ea['role']) ?>)
                                            </option>
                                        <?php endif; endforeach; ?>
                                    </optgroup>
                                    <optgroup label="👤 Supervisor Divisi">
                                        <?php foreach ($eligibleApprovers as $ea): if ($ea['role'] === 'supervisor'): ?>
                                            <option value="<?= $ea['id'] ?>" <?= (int)$div['approval_user_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                👤 <?= htmlspecialchars($ea['full_name']) ?> (SPV)
                                            </option>
                                        <?php endif; endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <label class="ios-checkbox-row">
                                <input type="checkbox" name="sync_pending" value="1" checked class="ios-checkbox">
                                <span>Terapkan juga ke izin pending divisi ini</span>
                            </label>

                            <button type="submit" class="btn btn-primary ios-save-btn">
                                <i class="ti ti-device-floppy"></i> Simpan Pengaturan Divisi
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TAMPILAN DESKTOP: TABLE LEBAR BERSIH -->
        <div class="desktop-only-view">
            <div class="table-responsive">
                <table class="data-table" style="font-size: 0.825rem;">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Divisi</th>
                            <th style="width: 20%;">Supervisor Bawaan</th>
                            <th style="width: 40%;">Penanggung Jawab Approval (Approver Terpilih)</th>
                            <th style="width: 15%; text-align: center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisions as $div): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($div['name']) ?></strong>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 0.65rem; margin-left: 4px;"><?= htmlspecialchars($div['code']) ?></span>
                                    <div style="font-size: 0.725rem; color: var(--text-muted);"><?= htmlspecialchars($div['description']) ?></div>
                                </td>
                                <td>
                                    <?php if ($div['supervisor_name']): ?>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <i class="ti ti-user-check" style="color: #10b981;"></i>
                                            <span><?= htmlspecialchars($div['supervisor_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic;">(Belum ada SPV)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="" id="formDivApv_<?= $div['id'] ?>" style="display: flex; flex-direction: column; gap: 6px;">
                                        <input type="hidden" name="action" value="update_division_approval">
                                        <input type="hidden" name="division_id" value="<?= $div['id'] ?>">
                                        <select name="approval_user_id" class="form-control" style="font-size: 0.8rem; padding: 6px 10px; border-radius: 8px;">
                                            <optgroup label="👑 Super Administrator & Admin HR">
                                                <?php foreach ($eligibleApprovers as $ea): if (in_array($ea['role'], ['superadmin', 'admin'])): ?>
                                                    <option value="<?= $ea['id'] ?>" <?= (int)$div['approval_user_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                        🛡️ <?= htmlspecialchars($ea['full_name']) ?> (<?= strtoupper($ea['role']) ?>)
                                                    </option>
                                                <?php endif; endforeach; ?>
                                            </optgroup>
                                            <optgroup label="👤 Supervisor Divisi">
                                                <?php foreach ($eligibleApprovers as $ea): if ($ea['role'] === 'supervisor'): ?>
                                                    <option value="<?= $ea['id'] ?>" <?= (int)$div['approval_user_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                        👤 <?= htmlspecialchars($ea['full_name']) ?> (SPV)
                                                    </option>
                                                <?php endif; endforeach; ?>
                                            </optgroup>
                                        </select>
                                        <label style="font-size: 0.7rem; color: #64748b; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                                            <input type="checkbox" name="sync_pending" value="1" checked> Terapkan juga ke izin pending divisi ini
                                        </label>
                                    </form>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <button type="submit" form="formDivApv_<?= $div['id'] ?>" class="btn btn-primary btn-sm" style="font-size: 0.75rem; padding: 6px 14px; border-radius: 8px;">
                                        <i class="ti ti-device-floppy"></i> Simpan
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Status Filter -->
<div class="filter-chips-scroll" style="margin-bottom: 20px;">
    <a href="<?= BASE_URL ?>/admin/izin.php?status=all" class="chip-item" style="<?= $statusFilter === 'all' ? 'background: #4f46e5; color: #fff; font-weight: 700;' : 'background: #fff; border-color: #cbd5e1; color: #334155;' ?>">
        Semua (<?= count($leaves) ?>)
    </a>
    <a href="<?= BASE_URL ?>/admin/izin.php?status=pending" class="chip-item" style="<?= $statusFilter === 'pending' ? 'background: #ef4444; color: #fff; font-weight: 700;' : 'background: #fef2f2; border-color: #fecaca; color: #991b1b;' ?>">
        <i class="ti ti-hourglass"></i> Menunggu Persetujuan
    </a>
    <a href="<?= BASE_URL ?>/admin/izin.php?status=approved" class="chip-item" style="<?= $statusFilter === 'approved' ? 'background: #10b981; color: #fff; font-weight: 700;' : 'background: #ecfdf5; border-color: #a7f3d0; color: #065f46;' ?>">
        <i class="ti ti-check"></i> Disetujui
    </a>
    <a href="<?= BASE_URL ?>/admin/izin.php?status=rejected" class="chip-item" style="<?= $statusFilter === 'rejected' ? 'background: #dc2626; color: #fff; font-weight: 700;' : 'background: #fff1f2; border-color: #fecdd3; color: #be123c;' ?>">
        <i class="ti ti-x"></i> Ditolak
    </a>
</div>

<!-- Table / Card List Permohonan Izin -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-files" style="color: var(--primary);"></i>
            Daftar Permohonan Izin & Bukti Surat Dokter
        </div>
    </div>
    <div class="card-body">
        <!-- TAMPILAN MOBILE: NATIVE IOS / ANDROID LEAVE CARDS -->
        <div class="mobile-only-view">
            <?php if (empty($leaves)): ?>
                <div style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 0.85rem;">
                    <i class="ti ti-inbox" style="font-size: 2rem; display: block; margin-bottom: 6px; color: #cbd5e1;"></i>
                    Tidak ada data pengajuan izin untuk status ini.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($leaves as $lv): ?>
                        <div class="ios-leave-card">
                            <div class="ios-leave-top">
                                <div>
                                    <div class="ios-leave-name"><?= htmlspecialchars($lv['full_name']) ?></div>
                                    <div class="ios-leave-sub">
                                        <?= htmlspecialchars($lv['division_name'] ?? 'Divisi') ?> &bull; <?= htmlspecialchars($lv['position']) ?>
                                    </div>
                                </div>
                                <div>
                                    <?= Helpers::statusBadge($lv['status']) ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?= Helpers::statusBadge($lv['type']) ?>
                                <span class="ios-leave-date-badge">
                                    <i class="ti ti-calendar"></i>
                                    <?= date('d/m/Y', strtotime($lv['start_date'])) ?> s/d <?= date('d/m/Y', strtotime($lv['end_date'])) ?>
                                </span>
                            </div>

                            <div class="ios-leave-reason">
                                <strong style="font-size: 0.725rem; color: #64748b; display: block; margin-bottom: 2px;">Alasan Pengajuan:</strong>
                                <?= htmlspecialchars($lv['reason']) ?>
                            </div>

                            <?php if ($lv['doctor_letter']): ?>
                                <div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($lv['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>', '<?= $lv['start_date'] ?>')" style="color: #dc2626; border-color: #fecaca; background: #fff5f5; width: 100%; border-radius: 10px; font-weight: 700;">
                                        <i class="ti ti-file-certificate"></i> Buka Surat Dokter
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if ($lv['status'] === 'pending'): ?>
                                <div style="background: #f8fafc; padding: 10px; border-radius: 10px; border: 1px solid #f1f5f9;">
                                    <div style="font-size: 0.7rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                                        <i class="ti ti-arrows-transfer-up"></i> Jalur Verifikator Saat Ini:
                                    </div>
                                    <form method="POST" action="" style="margin: 0;">
                                        <input type="hidden" name="action" value="switch_approval_target">
                                        <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                        <select name="assigned_approver_id" class="form-control ios-native-select" style="padding: 6px 10px !important; font-size: 0.75rem !important;" onchange="this.form.submit()">
                                            <optgroup label="👑 Superadmin & Admin">
                                                <?php foreach ($eligibleApprovers as $ea): if (in_array($ea['role'], ['superadmin', 'admin'])): ?>
                                                    <option value="<?= $ea['id'] ?>" <?= (int)$lv['assigned_approver_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                        🛡️ <?= htmlspecialchars($ea['full_name']) ?>
                                                    </option>
                                                <?php endif; endforeach; ?>
                                            </optgroup>
                                            <optgroup label="👤 Supervisor">
                                                <?php foreach ($eligibleApprovers as $ea): if ($ea['role'] === 'supervisor'): ?>
                                                    <option value="<?= $ea['id'] ?>" <?= (int)$lv['assigned_approver_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                        👤 <?= htmlspecialchars($ea['full_name']) ?>
                                                    </option>
                                                <?php endif; endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </form>
                                </div>

                                <div class="ios-leave-actions">
                                    <form method="POST" action="" onsubmit="return confirm('Setujui pengajuan izin ini?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                        <button type="submit" class="btn btn-success" style="width: 100%; border-radius: 10px; font-weight: 700; font-size: 0.8rem; padding: 8px;">
                                            <i class="ti ti-check"></i> Approve
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger" onclick="rejectLeave(<?= $lv['id'] ?>, '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>')" style="width: 100%; border-radius: 10px; font-weight: 700; font-size: 0.8rem; padding: 8px;">
                                        <i class="ti ti-x"></i> Reject
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.75rem; color: #64748b; background: #f8fafc; padding: 6px 10px; border-radius: 8px;">
                                    Diverifikasi oleh: <strong><?= htmlspecialchars($lv['approver_name'] ?? '-') ?></strong> (<?= strtoupper($lv['reviewer_role'] ?? 'HR') ?>)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAMPILAN DESKTOP: TABLE DATA LENGKAP -->
        <div class="desktop-only-view">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pemohon & Divisi</th>
                            <th>Kategori</th>
                            <th>Periode Tanggal</th>
                            <th>Alasan & Keluhan</th>
                            <th style="text-align: center;">Surat Dokter</th>
                            <th>Jalur Approval Saat Ini</th>
                            <th>Status</th>
                            <th style="text-align: center;">Tindakan Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Tidak ada data pengajuan izin untuk filter ini.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($leaves as $lv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($lv['full_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($lv['division_name'] ?? 'Divisi') ?> &bull; <?= htmlspecialchars($lv['position']) ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #64748b;">
                                        SPV: <?= htmlspecialchars($lv['supervisor_name'] ?? '-') ?>
                                    </div>
                                </td>
                                <td><?= Helpers::statusBadge($lv['type']) ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($lv['start_date'])) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        s/d <?= date('d/m/Y', strtotime($lv['end_date'])) ?>
                                    </div>
                                </td>
                                <td style="max-width: 220px;">
                                    <div style="font-size: 0.8125rem; line-height: 1.4;">
                                        <?= htmlspecialchars($lv['reason']) ?>
                                    </div>
                                    <?php if ($lv['rejection_note']): ?>
                                        <div style="font-size: 0.75rem; color: #dc2626; margin-top: 4px;">
                                            <strong>Catatan Penolakan:</strong> <?= htmlspecialchars($lv['rejection_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($lv['doctor_letter']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($lv['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>', '<?= $lv['start_date'] ?>')" style="color: #dc2626; border-color: #fecaca; background: #fff5f5;">
                                            <i class="ti ti-file-certificate"></i> Buka Surat
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">Tidak Ada Lampiran</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Fitur Mengganti Verifikator Approval -->
                                    <?php if ($lv['status'] === 'pending'): ?>
                                        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 4px;">
                                            <input type="hidden" name="action" value="switch_approval_target">
                                            <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                            <select name="assigned_approver_id" class="form-control" style="font-size: 0.725rem; padding: 4px 6px; border-radius: 6px;" onchange="this.form.submit()">
                                                <optgroup label="👑 Superadmin & Admin">
                                                    <?php foreach ($eligibleApprovers as $ea): if (in_array($ea['role'], ['superadmin', 'admin'])): ?>
                                                        <option value="<?= $ea['id'] ?>" <?= (int)$lv['assigned_approver_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                            🛡️ <?= htmlspecialchars($ea['full_name']) ?>
                                                        </option>
                                                    <?php endif; endforeach; ?>
                                                </optgroup>
                                                <optgroup label="👤 Supervisor">
                                                    <?php foreach ($eligibleApprovers as $ea): if ($ea['role'] === 'supervisor'): ?>
                                                        <option value="<?= $ea['id'] ?>" <?= (int)$lv['assigned_approver_id'] === (int)$ea['id'] ? 'selected' : '' ?>>
                                                            👤 <?= htmlspecialchars($ea['full_name']) ?>
                                                        </option>
                                                    <?php endif; endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </form>
                                        <div style="font-size: 0.675rem; color: #64748b; margin-top: 2px;">
                                            (Pilih untuk dialihkan)
                                        </div>
                                    <?php else: ?>
                                        <span class="user-role-badge <?= htmlspecialchars($lv['approval_target']) ?>" style="font-size: 0.72rem; padding: 3px 8px;">
                                            <?= htmlspecialchars($lv['assigned_approver_name'] ?? strtoupper($lv['approval_target'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= Helpers::statusBadge($lv['status']) ?>
                                    <?php if ($lv['approved_by']): ?>
                                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                                            Oleh: <strong><?= htmlspecialchars($lv['approver_name']) ?></strong> (<?= strtoupper($lv['reviewer_role'] ?? 'HR') ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($lv['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <form method="POST" action="" onsubmit="return confirm('Setujui pengajuan izin ini?');">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Setujui Langsung">
                                                    <i class="ti ti-check"></i> Approve
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectLeave(<?= $lv['id'] ?>, '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>')" title="Tolak">
                                                <i class="ti ti-x"></i> Reject
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tolak Izin -->
<div class="modal-backdrop" id="rejectModal">
    <div class="modal-content" style="max-width: 480px;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="leave_id" id="rejectLeaveId">
            <div class="modal-header">
                <h3 class="modal-title" style="color: #ef4444;">
                    <i class="ti ti-alert-triangle"></i> Konfirmasi Penolakan Izin
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('rejectModal')">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.875rem; margin-bottom: 12px;">
                    Anda akan menolak pengajuan izin dari: <strong id="rejectEmployeeName">-</strong>
                </p>
                <div class="form-group">
                    <label class="form-label">Alasan Penolakan</label>
                    <textarea name="rejection_note" class="form-control" rows="3" placeholder="Contoh: Bukti surat dokter kurang jelas / kuota izin habis" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Batal</button>
                <button type="submit" class="btn btn-danger">
                    <i class="ti ti-x"></i> Tolak Permohonan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function rejectLeave(id, name) {
    document.getElementById('rejectLeaveId').value = id;
    document.getElementById('rejectEmployeeName').textContent = name;
    openModal('rejectModal');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
