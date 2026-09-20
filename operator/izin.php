<?php
// operator/izin.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = $currentUser['id'];
$activePage = 'op_izin';

// PROSES POST: Pengajuan Izin Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_leave') {
    $type = $_POST['type'] ?? 'sakit';
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = trim($_POST['reason'] ?? '');

    // Cek apakah jenis permohonan sedang dibekukan
    if ($type === 'cuti' && Helpers::isOperatorMenuFrozen('cuti')) {
        Helpers::setFlash('error', 'Pengajuan Cuti Tahunan saat ini sedang dibekukan oleh Manajemen HR.');
        header('Location: ' . BASE_URL . '/operator/izin.php');
        exit;
    }

    // Cari jalur approval otomatis sesuai setting divisi karyawan
    $divId = $currentUser['division_id'] ?? null;
    $approvalTarget = 'supervisor';
    $assignedApproverId = null;

    if ($divId) {
        $stDiv = $db->prepare("SELECT d.*, u.role as approver_role, u.full_name as approver_name FROM divisions d LEFT JOIN users u ON d.approval_user_id = u.id WHERE d.id = ?");
        $stDiv->execute([$divId]);
        $divInfo = $stDiv->fetch(PDO::FETCH_ASSOC);

        if ($divInfo && !empty($divInfo['approval_user_id'])) {
            $assignedApproverId = (int)$divInfo['approval_user_id'];
            $role = $divInfo['approver_role'] ?? 'supervisor';
            $approvalTarget = in_array($role, ['admin', 'superadmin']) ? 'admin' : 'supervisor';
        } elseif ($divInfo && !empty($divInfo['supervisor_id'])) {
            $assignedApproverId = (int)$divInfo['supervisor_id'];
            $approvalTarget = 'supervisor';
        } else {
            $approvalTarget = 'admin';
        }
    } else {
        $approvalTarget = 'admin';
    }

    $doctorLetterFilename = null;

    // Handle Upload Surat Dokter
    if (isset($_FILES['doctor_letter']) && $_FILES['doctor_letter']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = Helpers::handleUploadSuratDokter($_FILES['doctor_letter']);
        if (!$uploadResult['success']) {
            Helpers::setFlash('error', $uploadResult['error']);
            header('Location: ' . BASE_URL . '/operator/izin.php');
            exit;
        }
        $doctorLetterFilename = $uploadResult['filename'];
    }

    if (empty($startDate) || empty($endDate) || empty($reason)) {
        Helpers::setFlash('error', 'Harap lengkapi semua kolom yang wajib diisi.');
    } elseif ($startDate > $endDate) {
        Helpers::setFlash('error', 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
    } else {
        $stmt = $db->prepare("
            INSERT INTO leaves (user_id, type, start_date, end_date, reason, doctor_letter, approval_target, assigned_approver_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $type, $startDate, $endDate, $reason, $doctorLetterFilename, $approvalTarget, $assignedApproverId]);

        Helpers::setFlash('success', 'Permohonan ' . strtoupper($type) . ' berhasil diajukan dan otomatis diteruskan ke Penanggung Jawab Divisi.');
        header('Location: ' . BASE_URL . '/operator/izin.php');
        exit;
    }
}

// Ambil riwayat permohonan izin karyawan ini
$stmtHistory = $db->prepare("
    SELECT l.*, approver.full_name as approver_name, assigned_apv.full_name as assigned_approver_name
    FROM leaves l
    LEFT JOIN users approver ON l.approved_by = approver.id
    LEFT JOIN users assigned_apv ON l.assigned_approver_id = assigned_apv.id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
");
$stmtHistory->execute([$userId]);
$myLeaves = $stmtHistory->fetchAll();

$pageTitle = 'Pengajuan Izin & Sakit';
$pageSubtitle = 'Formulir permohonan ketidakhadiran kerja & upload surat dokter';

$prefillType = $_GET['type'] ?? 'sakit';

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 860px; margin: 0 auto; margin-bottom: 24px;">
    <!-- Top Back / Title Navigation -->
    <div class="premium-page-nav" style="margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="<?= BASE_URL ?>/operator/index.php" class="premium-back-btn" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.15rem;"></i>
            </a>
            <div>
                <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px;">Pengajuan Izin & Sakit</h2>
                <div style="font-size: 0.725rem; color: #64748b; margin-top: 1px;">
                    <i class="ti ti-file-medical"></i> Formulir Ketidakhadiran & Surat Dokter
                </div>
            </div>
        </div>
    </div>

    <!-- FORM PENGAJUAN CARD (MOBILE FRIENDLY) -->
    <div style="background: #ffffff; border-radius: 18px; padding: 18px 16px; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 20px;">
        <div style="font-size: 0.85rem; font-weight: 800; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
            <div style="width: 26px; height: 26px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;">
                <i class="ti ti-file-plus"></i>
            </div>
            <span>Formulir Pengajuan Baru</span>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_leave">

            <div class="form-group">
                <label class="form-label" style="font-size: 0.75rem;">Kategori Ketidakhadiran <span style="color: #ef4444;">*</span></label>
                <select name="type" class="form-control" id="leaveTypeSelect" required style="font-size: 0.825rem; border-radius: 10px;">
                    <option value="sakit" <?= $prefillType === 'sakit' ? 'selected' : '' ?>>🩺 Sakit (Dengan Surat Dokter)</option>
                    <option value="izin" <?= $prefillType === 'izin' ? 'selected' : '' ?>>📝 Izin Mendesak / Kepentingan Pribadi</option>
                    <?php if (Helpers::isOperatorMenuFrozen('cuti')): ?>
                        <option value="cuti" disabled style="color: #94a3b8; background: #f1f5f9;">🏖️ Cuti Tahunan (Dibekukan oleh HR)</option>
                    <?php else: ?>
                        <option value="cuti" <?= $prefillType === 'cuti' ? 'selected' : '' ?>>🏖️ Cuti Tahunan / Istirahat</option>
                    <?php endif; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 700; color: #374151;">Dari Tanggal <span style="color: #ef4444;">*</span></label>
                    <div class="premium-date-wrapper">
                        <span class="premium-date-icon"><i class="ti ti-calendar-event"></i></span>
                        <input type="date" name="start_date" class="premium-date-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 700; color: #374151;">Sampai Tanggal <span style="color: #ef4444;">*</span></label>
                    <div class="premium-date-wrapper">
                        <span class="premium-date-icon"><i class="ti ti-calendar-check"></i></span>
                        <input type="date" name="end_date" class="premium-date-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size: 0.75rem;">Alasan / Diagnosa Sakit <span style="color: #ef4444;">*</span></label>
                <textarea name="reason" class="form-control" rows="2" placeholder="Contoh: Sakit demam tinggi, dianjurkan dokter istirahat 2 hari..." required style="font-size: 0.825rem; border-radius: 10px;"></textarea>
            </div>

            <!-- UPLOAD DOKUMEN BOX (PREMIUM MOBILE DROPZONE) -->
            <div class="form-group" style="background: #fff5f5; border: 1.5px dashed #fca5a5; padding: 16px; border-radius: 16px; text-align: center;">
                <input type="file" name="doctor_letter" id="doctorLetterInput" accept="image/*,application/pdf" style="display: none;" onchange="handleDoctorLetterSelected(this)">
                <label for="doctorLetterInput" style="cursor: pointer; display: block; margin: 0;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(220,38,38,0.15);">
                        <i class="ti ti-cloud-upload"></i>
                    </div>
                    <div style="font-weight: 800; font-size: 0.875rem; color: #b91c1c; letter-spacing: -0.2px;">
                        Upload Dokumen
                    </div>
                    <div style="font-size: 0.72rem; color: #ef4444; margin-top: 3px; opacity: 0.85;">
                        Surat Dokter (JPG, PNG, PDF &bull; maks. 5MB)
                    </div>
                </label>
                <div id="doctorLetterPreview" style="margin-top: 10px; display: none;">
                    <div style="background: #ffffff; border: 1px solid #fca5a5; border-radius: 10px; padding: 8px 12px; display: inline-flex; align-items: center; gap: 8px; max-width: 100%;">
                        <i class="ti ti-file-check" style="color: #10b981; font-size: 1.1rem;"></i>
                        <span id="doctorLetterFileName" style="font-size: 0.75rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;"></span>
                        <button type="button" onclick="clearDoctorLetter()" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 0 4px;" title="Hapus file">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- INFO JALUR APPROVAL OTOMATIS -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; margin-bottom: 14px; font-size: 0.775rem; color: #475569; display: flex; align-items: center; gap: 8px;">
                <i class="ti ti-shield-check" style="color: var(--primary); font-size: 1.1rem; flex-shrink: 0;"></i>
                <span>Persetujuan akan diproses otomatis oleh <strong>Penanggung Jawab Approval Divisi <?= htmlspecialchars($currentUser['division_name'] ?? 'Umum') ?></strong>.</span>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                <i class="ti ti-send"></i> Kirim Pengajuan Izin
            </button>
        </form>
    </div>

    <!-- RIWAYAT PENGAJUAN IZIN SAYA (MOBILE CARD LIST) -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <div style="font-size: 0.85rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
            Riwayat Permohonan Saya
        </div>
        <span style="font-size: 0.7rem; color: #64748b;"><?= count($myLeaves) ?> Pengajuan</span>
    </div>

    <?php if (empty($myLeaves)): ?>
        <div style="background: #ffffff; border-radius: 16px; padding: 24px; text-align: center; color: #94a3b8; border: 1px solid #f1f5f9;">
            <i class="ti ti-notes-off" style="font-size: 2rem; display: block; margin-bottom: 6px;"></i>
            <div style="font-size: 0.8rem;">Belum ada riwayat permohonan izin.</div>
        </div>
    <?php endif; ?>

    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($myLeaves as $ml): 
            $isApproved = $ml['status'] === 'approved';
            $isRejected = $ml['status'] === 'rejected';
            $borderColor = $isApproved ? '#86efac' : ($isRejected ? '#fca5a5' : '#fde047');
        ?>
            <div style="background: #ffffff; border-radius: 16px; padding: 14px; border: 1px solid #f1f5f9; border-left: 4px solid <?= $borderColor ?>; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <?= Helpers::statusBadge($ml['type']) ?>
                        <span style="font-size: 0.675rem; color: #64748b;">
                            <?= date('d M Y', strtotime($ml['created_at'])) ?>
                        </span>
                    </div>
                    <div>
                        <?= Helpers::statusBadge($ml['status']) ?>
                    </div>
                </div>

                <div style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                    <?= htmlspecialchars($ml['reason']) ?>
                </div>

                <div style="font-size: 0.725rem; color: #64748b; margin-bottom: 10px;">
                    <i class="ti ti-calendar"></i> <?= date('d/m/Y', strtotime($ml['start_date'])) ?> s/d <?= date('d/m/Y', strtotime($ml['end_date'])) ?>
                    <?php if (!empty($ml['assigned_approver_name'])): ?>
                        <span>&bull; Verifikator: <strong><?= htmlspecialchars($ml['assigned_approver_name']) ?></strong></span>
                    <?php else: ?>
                        <span>&bull; Jalur: <?= strtoupper($ml['approval_target']) ?></span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 8px;">
                    <div>
                        <?php if ($ml['approved_by']): ?>
                            <div style="font-size: 0.675rem; color: #059669;">
                                <i class="ti ti-check"></i> Disetujui: <strong><?= htmlspecialchars($ml['approver_name']) ?></strong>
                            </div>
                        <?php elseif ($ml['rejection_note']): ?>
                            <div style="font-size: 0.675rem; color: #dc2626;">
                                <i class="ti ti-x"></i> Ditolak: <?= htmlspecialchars($ml['rejection_note']) ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.675rem; color: #d97706;">
                                <i class="ti ti-clock"></i> Menunggu review...
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <?php if ($ml['doctor_letter']): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($ml['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($currentUser['full_name'])) ?>', '<?= $ml['start_date'] ?>')" style="font-size: 0.675rem; padding: 3px 8px; color: #dc2626; border-color: #fecaca; background: #fff5f5; border-radius: 6px;">
                                <i class="ti ti-file-search"></i> Surat Dokter
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function handleDoctorLetterSelected(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        document.getElementById('doctorLetterFileName').textContent = file.name;
        document.getElementById('doctorLetterPreview').style.display = 'block';
    }
}
function clearDoctorLetter() {
    var input = document.getElementById('doctorLetterInput');
    input.value = '';
    document.getElementById('doctorLetterPreview').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

