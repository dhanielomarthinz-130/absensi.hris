<?php
// supervisor/izin.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$divId = (int)($currentUser['division_id'] ?: 1);
$isSuper = Auth::isSuperAdmin();
$activePage = 'spv_izin';
$pageTitle = 'Persetujuan Izin & Sakit Tim';
$pageSubtitle = 'Verifikasi perizinan anggota tim Divisi ' . htmlspecialchars($currentUser['division_name'] ?? 'Semua Divisi');

// Handle POST actions: Approve, Reject, Alihkan ke Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leaveId = (int)$_POST['leave_id'];

    // Pastikan pemohon adalah anggota timnya, atau jika superadmin boleh proses siapa saja
    $chk = $db->prepare("SELECT l.id FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.id = ? " . ($isSuper ? "" : "AND u.division_id = ?"));
    if ($isSuper) {
        $chk->execute([$leaveId]);
    } else {
        $chk->execute([$leaveId, $divId]);
    }

    if ($chk->fetch()) {
        if ($_POST['action'] === 'approve') {
            $stmt = $db->prepare("
                UPDATE leaves 
                SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, reviewer_role = 'supervisor'
                WHERE id = ?
            ");
            $stmt->execute([Auth::id(), $leaveId]);
            Helpers::setFlash('success', 'Pengajuan izin anggota tim telah disetujui.');
        } elseif ($_POST['action'] === 'reject') {
            $note = trim($_POST['rejection_note'] ?? 'Ditolak');
            $stmt = $db->prepare("
                UPDATE leaves 
                SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, reviewer_role = 'supervisor', rejection_note = ?
                WHERE id = ?
            ");
            $stmt->execute([Auth::id(), $note, $leaveId]);
            Helpers::setFlash('warning', 'Pengajuan izin telah ditolak.');
        } elseif ($_POST['action'] === 'escalate_to_admin') {
            $stmt = $db->prepare("UPDATE leaves SET approval_target = 'admin' WHERE id = ?");
            $stmt->execute([$leaveId]);
            Helpers::setFlash('info', 'Persetujuan izin berhasil dialihkan ke Super Admin.');
        }
    }
    header('Location: ' . BASE_URL . '/supervisor/izin.php');
    exit;
}

// Ambil pengajuan izin tim divisi ini
$sql = "
    SELECT l.*, u.full_name, u.position, approver.full_name as approver_name, assigned_apv.full_name as assigned_approver_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN users approver ON l.approved_by = approver.id
    LEFT JOIN users assigned_apv ON l.assigned_approver_id = assigned_apv.id
    WHERE u.division_id = ?
    ORDER BY l.created_at DESC
";
$stmtLeaves = $db->prepare($sql);
$stmtLeaves->execute([$divId]);
$leaves = $stmtLeaves->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<?php
$pendingCount = count(array_filter($leaves, fn($l) => $l['status'] === 'pending'));
$filterStatus = $_GET['filter'] ?? 'pending';
$filteredLeaves = $filterStatus === 'all' ? $leaves : array_filter($leaves, fn($l) => $l['status'] === $filterStatus);
?>

<div style="max-width: 860px; margin: 0 auto; margin-bottom: 24px;">
    <!-- Top Back / Title Navigation -->
    <div class="premium-page-nav" style="margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="<?= BASE_URL ?>/operator/index.php" class="premium-back-btn" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.15rem;"></i>
            </a>
            <div>
                <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px;">Approval Izin Tim</h2>
                <div style="font-size: 0.725rem; color: #64748b; margin-top: 1px;">
                    <i class="ti ti-building"></i> Divisi <?= htmlspecialchars($currentUser['division_name'] ?? 'Semua') ?>
                    <?php if ($pendingCount > 0): ?>
                        &bull; <span style="color: #dc2626; font-weight: 700;"><?= $pendingCount ?> menunggu review</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div style="display: flex; gap: 8px; margin-bottom: 16px; overflow-x: auto; padding-bottom: 2px;">
        <a href="?filter=pending" style="flex-shrink: 0; padding: 7px 16px; border-radius: 99px; font-size: 0.78rem; font-weight: 700; text-decoration: none;
            background: <?= $filterStatus === 'pending' ? '#dc2626' : '#f1f5f9' ?>;
            color: <?= $filterStatus === 'pending' ? '#fff' : '#64748b' ?>;">
            <i class="ti ti-clock"></i> Menunggu (<?= $pendingCount ?>)
        </a>
        <a href="?filter=approved" style="flex-shrink: 0; padding: 7px 16px; border-radius: 99px; font-size: 0.78rem; font-weight: 700; text-decoration: none;
            background: <?= $filterStatus === 'approved' ? '#059669' : '#f1f5f9' ?>;
            color: <?= $filterStatus === 'approved' ? '#fff' : '#64748b' ?>;">
            <i class="ti ti-check"></i> Disetujui (<?= count(array_filter($leaves, fn($l) => $l['status'] === 'approved')) ?>)
        </a>
        <a href="?filter=rejected" style="flex-shrink: 0; padding: 7px 16px; border-radius: 99px; font-size: 0.78rem; font-weight: 700; text-decoration: none;
            background: <?= $filterStatus === 'rejected' ? '#7f1d1d' : '#f1f5f9' ?>;
            color: <?= $filterStatus === 'rejected' ? '#fff' : '#64748b' ?>;">
            <i class="ti ti-x"></i> Ditolak (<?= count(array_filter($leaves, fn($l) => $l['status'] === 'rejected')) ?>)
        </a>
        <a href="?filter=all" style="flex-shrink: 0; padding: 7px 16px; border-radius: 99px; font-size: 0.78rem; font-weight: 700; text-decoration: none;
            background: <?= $filterStatus === 'all' ? '#1e293b' : '#f1f5f9' ?>;
            color: <?= $filterStatus === 'all' ? '#fff' : '#64748b' ?>;">
            <i class="ti ti-list"></i> Semua (<?= count($leaves) ?>)
        </a>
    </div>

    <!-- MOBILE CARD VIEW -->
    <div class="mobile-only-view">
        <?php if (empty($filteredLeaves)): ?>
            <div style="background: #fff; border-radius: 18px; padding: 32px; text-align: center; color: #94a3b8; border: 1px solid #f1f5f9;">
                <i class="ti ti-inbox" style="font-size: 2.5rem; display: block; margin-bottom: 8px; color: #e2e8f0;"></i>
                <div style="font-size: 0.85rem; font-weight: 600;">Tidak ada pengajuan di kategori ini.</div>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($filteredLeaves as $lv): ?>

                        <div class="ios-leave-card">
                            <div class="ios-leave-top">
                                <div>
                                    <div class="ios-leave-name"><?= htmlspecialchars($lv['full_name']) ?></div>
                                    <div class="ios-leave-sub"><?= htmlspecialchars($lv['position']) ?></div>
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
                                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                        <form method="POST" action="" onsubmit="return confirm('Setujui izin ini?');" style="margin: 0;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                            <button type="submit" class="btn btn-success" style="width: 100%; border-radius: 10px; font-weight: 700; font-size: 0.8rem; padding: 8px;">
                                                <i class="ti ti-check"></i> Approve
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-danger" onclick="rejectSpv(<?= $lv['id'] ?>, '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>')" style="width: 100%; border-radius: 10px; font-weight: 700; font-size: 0.8rem; padding: 8px;">
                                            <i class="ti ti-x"></i> Reject
                                        </button>
                                    </div>

                                    <form method="POST" action="" onsubmit="return confirm('Alihkan persetujuan izin ini ke Super Admin?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="escalate_to_admin">
                                        <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="width: 100%; border-radius: 10px; font-weight: 600; font-size: 0.75rem; padding: 6px;" title="Alihkan ke Super Admin">
                                            <i class="ti ti-arrow-forward-up"></i> Alihkan Persetujuan ke Super Admin
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.75rem; color: #64748b; background: #f8fafc; padding: 6px 10px; border-radius: 8px;">
                                    Diverifikasi oleh: <strong><?= htmlspecialchars($lv['approver_name'] ?? '-') ?></strong> (<?= strtoupper($lv['reviewer_role'] ?? 'SPV') ?>)
                                </div>
                            <?php endif; ?>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- DESKTOP TABLE VIEW -->
    <div class="desktop-only-view">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="ti ti-file-certificate" style="color: var(--primary);"></i> Pengajuan Izin Tim &mdash; <?= htmlspecialchars($currentUser['division_name'] ?? 'Semua') ?></div>
                <div style="font-size: 0.8125rem; color: var(--text-muted);">Setujui, tolak, atau alihkan ke Admin.</div>
            </div>
        <div class="card-body">
        <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Anggota Tim</th>
                            <th>Kategori</th>
                            <th>Tanggal</th>
                            <th>Alasan &amp; Keluhan</th>
                            <th style="text-align: center;">Surat Dokter</th>
                            <th>Target Approval</th>
                            <th>Status</th>
                            <th style="text-align: center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filteredLeaves)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 30px;">Tidak ada pengajuan di kategori ini.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($filteredLeaves as $lv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($lv['full_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($lv['position']) ?></div>
                                </td>
                                <td><?= Helpers::statusBadge($lv['type']) ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($lv['start_date'])) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">s/d <?= date('d/m/Y', strtotime($lv['end_date'])) ?></div>
                                </td>
                                <td style="max-width: 220px;">
                                    <div style="font-size: 0.8125rem;"><?= htmlspecialchars($lv['reason']) ?></div>
                                    <?php if ($lv['rejection_note']): ?>
                                        <div style="font-size: 0.75rem; color: #dc2626; margin-top: 4px;">
                                            <strong>Catatan:</strong> <?= htmlspecialchars($lv['rejection_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($lv['doctor_letter']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($lv['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>', '<?= $lv['start_date'] ?>')" style="color: #dc2626; border-color: #fecaca; background: #fff5f5;">
                                            <i class="ti ti-file-certificate"></i> Cek Surat
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">Tidak Ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($lv['assigned_approver_name'])): ?>
                                        <span class="user-role-badge <?= htmlspecialchars($lv['approval_target']) ?>" style="font-size: 0.72rem; padding: 3px 8px;">
                                            <?= htmlspecialchars($lv['assigned_approver_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="user-role-badge <?= htmlspecialchars($lv['approval_target']) ?>">
                                            <?= strtoupper($lv['approval_target']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= Helpers::statusBadge($lv['status']) ?>
                                    <?php if ($lv['approved_by']): ?>
                                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                                            Oleh: <strong><?= htmlspecialchars($lv['approver_name']) ?></strong> (<?= strtoupper($lv['reviewer_role'] ?? '') ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($lv['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                            <form method="POST" action="" onsubmit="return confirm('Setujui izin ini?');">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="ti ti-check"></i> Approve
                                                </button>
                                            </form>

                                            <form method="POST" action="" onsubmit="return confirm('Alihkan persetujuan izin ini ke Super Admin?');">
                                                <input type="hidden" name="action" value="escalate_to_admin">
                                                <input type="hidden" name="leave_id" value="<?= $lv['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Alihkan ke Admin">
                                                    <i class="ti ti-arrow-forward-up"></i> Ke Admin
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectSpv(<?= $lv['id'] ?>, '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>')">
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
</div>

<!-- Modal Tolak Supervisor -->
<div class="modal-backdrop" id="rejectSpvModal">
    <div class="modal-content" style="max-width: 480px;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="leave_id" id="rejectSpvLeaveId">
            <div class="modal-header">
                <h3 class="modal-title" style="color: #ef4444;">Tolak Pengajuan Izin Tim</h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('rejectSpvModal')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.875rem; margin-bottom: 12px;">Menolak izin anggota: <strong id="rejectSpvName">-</strong></p>
                <div class="form-group">
                    <label class="form-label">Alasan Penolakan</label>
                    <textarea name="rejection_note" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectSpvModal')">Batal</button>
                <button type="submit" class="btn btn-danger">Tolak Izin</button>
            </div>
        </form>
    </div>
</div>

<script>
function rejectSpv(id, name) {
    document.getElementById('rejectSpvLeaveId').value = id;
    document.getElementById('rejectSpvName').textContent = name;
    openModal('rejectSpvModal');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
