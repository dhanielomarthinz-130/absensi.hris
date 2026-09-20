<?php
// operator/profile.php - Talenta Style Account Profile & Settings
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin', 'superadmin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = (int)$currentUser['id'];

$errorMsg = null;
$successMsg = null;

// PROSES POST: Update Foto & Kontak atau Ganti Password
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Ganti Foto Profil & Kontak Pribadi
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $avatarFilename = $currentUser['avatar'] ?? null;

        // Cek Upload Foto
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $errorMsg = 'Format foto tidak didukung. Harap gunakan format JPG, PNG, atau WEBP.';
            } elseif ($file['size'] > $maxSize) {
                $errorMsg = 'Ukuran foto maksimal 2MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (empty($ext)) {
                    $ext = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/webp') ? 'webp' : 'jpg');
                }
                $avatarDir = __DIR__ . '/../uploads/avatars';
                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0777, true);
                }

                $newFilename = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);
                $destination = $avatarDir . '/' . $newFilename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Hapus foto lama jika ada
                    if (!empty($currentUser['avatar']) && file_exists($avatarDir . '/' . $currentUser['avatar'])) {
                        @unlink($avatarDir . '/' . $currentUser['avatar']);
                    }
                    $avatarFilename = $newFilename;
                } else {
                    $errorMsg = 'Gagal menyimpan file foto ke server.';
                }
            }
        }

        if (!$errorMsg) {
            $stUp = $db->prepare("UPDATE users SET avatar = ?, email = ?, phone = ? WHERE id = ?");
            $stUp->execute([$avatarFilename, $email, $phone, $userId]);

            // Sinkronkan ke Session
            $_SESSION['user']['avatar'] = $avatarFilename;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $currentUser = Auth::user();

            Helpers::setFlash('success', 'Profil dan foto Anda berhasil diperbarui!');
            header('Location: ' . BASE_URL . '/operator/profile.php');
            exit;
        }
    }

    // 2. Hapus Foto Profil
    elseif ($action === 'remove_avatar') {
        $avatarDir = __DIR__ . '/../uploads/avatars';
        if (!empty($currentUser['avatar']) && file_exists($avatarDir . '/' . $currentUser['avatar'])) {
            @unlink($avatarDir . '/' . $currentUser['avatar']);
        }
        $stUp = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $stUp->execute([$userId]);

        $_SESSION['user']['avatar'] = null;
        $currentUser = Auth::user();

        Helpers::setFlash('success', 'Foto profil berhasil dikembalikan ke inisial nama.');
        header('Location: ' . BASE_URL . '/operator/profile.php');
        exit;
    }

    // 3. Ganti Password
    elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stUser = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stUser->execute([$userId]);
        $userDb = $stUser->fetch();

        if (!$userDb || !password_verify($currentPass, $userDb['password_hash'])) {
            $errorMsg = 'Password saat ini yang Anda masukkan salah!';
        } elseif (strlen($newPass) < 6) {
            $errorMsg = 'Password baru minimal harus 6 karakter!';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'Konfirmasi password baru tidak cocok!';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stUp = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stUp->execute([$newHash, $userId]);

            Helpers::setFlash('success', 'Password Anda berhasil diperbarui!');
            header('Location: ' . BASE_URL . '/operator/profile.php');
            exit;
        }
    }
}

// Ambil data user lengkap
$stFull = $db->prepare("
    SELECT u.*, d.name as division_name, d.code as division_code
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    WHERE u.id = ?
");
$stFull->execute([$userId]);
$userData = $stFull->fetch();

$positionTitle = !empty($userData['position']) ? $userData['position'] : 'PRODUCT REPRESENTATIVE GT';
$companyTitle = !empty($userData['division_name']) ? 'Divisi ' . $userData['division_name'] : 'Divisi Operasional';

$pageTitle = 'Akun';
$pageSubtitle = 'Pengaturan Akun & Informasi Karyawan';
$activePage = 'op_profile';

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 540px; margin: 0 auto; padding-bottom: 24px;">

    <?php if ($errorMsg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(<?= json_encode($errorMsg) ?>, 'error', 'Perhatian');
            });
        </script>
    <?php endif; ?>

    <!-- 1. TOP HEADER (EXACT TALENTA: Name, Subtitle, Company + Circular Avatar Right) -->
    <div class="talenta-account-header">
        <div>
            <div class="talenta-account-name">
                <?= htmlspecialchars($userData['full_name']) ?>
            </div>
            <div class="talenta-account-sub">
                <?= htmlspecialchars($positionTitle) ?>
            </div>
            <div class="talenta-account-company">
                <?= htmlspecialchars($companyTitle) ?>
            </div>
        </div>

        <!-- Avatar Circle Right -->
        <div class="talenta-account-avatar" onclick="openPhotoModal()" style="cursor: pointer;" title="Klik untuk mengubah foto profil">
            <?php if (!empty($userData['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $userData['avatar'])): ?>
                <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($userData['avatar']) ?>" alt="Foto">
            <?php else: ?>
                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #4f46e5, #4338ca); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem;">
                    <?= strtoupper(substr($userData['full_name'] ?? 'U', 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. SECTION: Info saya -->
    <div class="talenta-group-title">Info saya</div>
    <div class="talenta-card-group">
        <!-- 1. Info personal -->
        <a href="javascript:void(0)" onclick="openInfoModal('personal')" class="talenta-card-item">
            <i class="ti ti-user"></i>
            <span class="item-label">Info personal</span>
            <i class="ti ti-chevron-right item-arrow"></i>
        </a>

        <!-- 2. Info pekerjaan -->
        <a href="javascript:void(0)" onclick="openInfoModal('pekerjaan')" class="talenta-card-item">
            <i class="ti ti-id-badge"></i>
            <span class="item-label">Info pekerjaan</span>
            <i class="ti ti-chevron-right item-arrow"></i>
        </a>

        <!-- 3. Riwayat Kehadiran -->
        <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-card-item">
            <i class="ti ti-calendar-stats"></i>
            <span class="item-label">Riwayat presensi saya</span>
            <i class="ti ti-chevron-right item-arrow"></i>
        </a>

        <!-- 4. Pengajuan Izin / Cuti -->
        <a href="<?= BASE_URL ?>/operator/izin.php" class="talenta-card-item">
            <i class="ti ti-file-text"></i>
            <span class="item-label">Pengajuan izin & cuti</span>
            <i class="ti ti-chevron-right item-arrow"></i>
        </a>
    </div>

    <!-- 3. SECTION: Pengaturan -->
    <div class="talenta-group-title">Pengaturan</div>
    <div class="talenta-card-group">
        <!-- Ubah kata sandi -->
        <a href="javascript:void(0)" onclick="openPasswordModal()" class="talenta-card-item">
            <i class="ti ti-lock"></i>
            <span class="item-label">Ubah kata sandi</span>
            <i class="ti ti-chevron-right item-arrow"></i>
        </a>
    </div>

    <!-- 4. LOGOUT BUTTON -->
    <div style="margin-top: 24px; text-align: center;">
        <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun?')" style="display: inline-flex; align-items: center; gap: 8px; color: #ef4444; font-weight: 700; font-size: 0.9rem; text-decoration: none; padding: 12px 24px; background: #fff; border-radius: 999px; border: 1.5px solid #fecdd3; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.08);">
            <i class="ti ti-logout" style="font-size: 1.15rem;"></i>
            <span>Keluar dari Akun</span>
        </a>
    </div>

</div>

<!-- MODAL: GANTI / UPLOAD FOTO PROFIL (ULTRA PREMIUM UI) -->
<div id="modalPhoto" class="talenta-sheet-backdrop" onclick="closePhotoModal(event)">
    <div class="talenta-sheet-content" onclick="event.stopPropagation()" style="max-width: 480px; border-radius: 28px 28px 0 0; padding: 14px 22px 28px;">
        <div class="talenta-sheet-handle"></div>
        <div class="talenta-sheet-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 10px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="ti ti-user-edit"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">Foto Profil & Kontak</h3>
                    <p style="font-size: 0.725rem; color: #64748b; margin: 0;">Perbarui foto profil, alamat email, dan kontak WhatsApp Anda</p>
                </div>
            </div>
            <button type="button" class="talenta-sheet-close" onclick="closePhotoModal(event)" aria-label="Tutup"><i class="ti ti-x"></i></button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            
            <!-- Hidden Native File Input -->
            <input type="file" name="avatar" id="profileAvatarInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleProfileAvatarSelected(this)">

            <!-- Premium Centered Avatar Showcase -->
            <div class="premium-photo-modal-card">
                <div class="premium-avatar-box" onclick="document.getElementById('profileAvatarInput').click()" title="Klik untuk memilih foto baru">
                    <div class="premium-avatar-ring">
                        <div class="premium-avatar-inner">
                            <?php 
                            $hasAvatar = !empty($userData['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $userData['avatar']);
                            $avatarSrc = $hasAvatar ? BASE_URL . '/uploads/avatars/' . htmlspecialchars($userData['avatar']) : '';
                            ?>
                            <img src="<?= $avatarSrc ?>" id="profileAvatarPreview" data-original-src="<?= $avatarSrc ?>" alt="Foto Profil" style="<?= $hasAvatar ? 'display: block;' : 'display: none;' ?>">
                            <span id="profileAvatarInitial" class="premium-avatar-initial" style="<?= $hasAvatar ? 'display: none;' : 'display: flex;' ?>">
                                <?= strtoupper(substr($userData['full_name'] ?? 'U', 0, 1)) ?>
                            </span>
                        </div>
                    </div>
                    <div class="premium-camera-badge" title="Ganti Foto">
                        <i class="ti ti-camera"></i>
                    </div>
                </div>

                <!-- Selected File Pill Indicator (Dynamic) -->
                <div id="profileFileIndicator" class="file-selected-indicator" style="display: none;">
                    <i class="ti ti-circle-check-filled" style="color: #10b981;"></i>
                    <span id="profileFileName">Foto terpilih</span>
                    <button type="button" onclick="cancelProfileAvatarSelected(event)" style="background: none; border: none; color: #065f46; cursor: pointer; font-weight: 800; padding: 0 2px; margin-left: 4px;" title="Batalkan">✕</button>
                </div>

                <!-- Action Button Pill: Pilih File Baru & Hapus -->
                <div class="premium-upload-actions">
                    <button type="button" class="btn-upload-avatar-trigger" onclick="document.getElementById('profileAvatarInput').click()">
                        <i class="ti ti-upload"></i> Unggah Foto Baru
                    </button>
                    <?php if (!empty($userData['avatar'])): ?>
                        <button type="submit" name="action" value="remove_avatar" class="btn-remove-avatar-trigger" onclick="return confirm('Apakah Anda yakin ingin menghapus foto profil ini dan kembali ke inisial nama?');" title="Hapus Foto">
                            <i class="ti ti-trash"></i> Hapus
                        </button>
                    <?php endif; ?>
                </div>

                <div style="font-size: 0.7rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 4px;">
                    <i class="ti ti-info-circle"></i> Format JPG, PNG, WEBP &bull; Maksimal 2MB
                </div>
            </div>

            <!-- Email Input Field -->
            <div class="premium-form-group">
                <label class="premium-input-label">
                    <i class="ti ti-mail" style="color: #6366f1;"></i> Alamat Email Perusahaan / Pribadi
                </label>
                <div class="premium-input-wrapper">
                    <i class="ti ti-at premium-input-icon"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" class="premium-input-control" placeholder="nama@perusahaan.com" required>
                </div>
            </div>

            <!-- Phone / WhatsApp Input Field -->
            <div class="premium-form-group">
                <label class="premium-input-label">
                    <i class="ti ti-brand-whatsapp" style="color: #10b981;"></i> Nomor Telepon / WhatsApp
                </label>
                <div class="premium-input-wrapper">
                    <i class="ti ti-phone premium-input-icon" style="color: #10b981;"></i>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" class="premium-input-control" placeholder="08xxxxxxxxxx">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit-premium">
                <i class="ti ti-device-floppy"></i> Simpan Perubahan Profil
            </button>
        </form>
    </div>
</div>

<!-- MODAL: UBAH KATA SANDI -->
<div id="modalPassword" class="talenta-sheet-backdrop" onclick="closePasswordModal(event)">
    <div class="talenta-sheet-content" onclick="event.stopPropagation()">
        <div class="talenta-sheet-handle"></div>
        <div class="talenta-sheet-header">
            <h3>Ubah Kata Sandi</h3>
            <button type="button" class="talenta-sheet-close" onclick="closePasswordModal(event)"><i class="ti ti-x"></i></button>
        </div>
        <form method="POST" action="" style="margin-top: 12px;">
            <input type="hidden" name="action" value="change_password">
            <div style="margin-bottom: 14px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" required class="form-control" style="font-size: 0.85rem;" placeholder="Masukkan kata sandi lama">
            </div>
            <div style="margin-bottom: 14px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Kata Sandi Baru (Min. 6 Karakter)</label>
                <input type="password" name="new_password" required class="form-control" style="font-size: 0.85rem;" placeholder="Masukkan kata sandi baru">
            </div>
            <div style="margin-bottom: 18px;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Konfirmasi Kata Sandi Baru</label>
                <input type="password" name="confirm_password" required class="form-control" style="font-size: 0.85rem;" placeholder="Ulangi kata sandi baru">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 12px; padding: 11px; font-weight: 700; font-size: 0.875rem; background: #2563eb;">
                Perbarui Kata Sandi
            </button>
        </form>
    </div>
</div>

<!-- MODAL: INFO MODAL DINAMIS (Personal, Pekerjaan, Payroll, dll) -->
<div id="modalInfoSheet" class="talenta-sheet-backdrop" onclick="closeInfoModal(event)">
    <div class="talenta-sheet-content" onclick="event.stopPropagation()">
        <div class="talenta-sheet-handle"></div>
        <div class="talenta-sheet-header">
            <h3 id="infoModalTitle">Info Karyawan</h3>
            <button type="button" class="talenta-sheet-close" onclick="closeInfoModal(event)"><i class="ti ti-x"></i></button>
        </div>
        <div id="infoModalBody" style="margin-top: 12px; font-size: 0.875rem;">
            <!-- Dinamis diisi JS -->
        </div>
    </div>
</div>

<script>
function openPhotoModal() {
    const m = document.getElementById('modalPhoto');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closePhotoModal(e) {
    if (e) e.stopPropagation();
    const m = document.getElementById('modalPhoto');
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

function openPasswordModal() {
    const m = document.getElementById('modalPassword');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closePasswordModal(e) {
    if (e) e.stopPropagation();
    const m = document.getElementById('modalPassword');
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

const userInfoData = {
    personal: {
        title: 'Info Personal',
        html: `
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Nama Lengkap</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($userData['full_name']) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">NIK Karyawan</span>
                    <strong style="color:#0f172a;">EMP-<?= str_pad($userData['id'], 4, '0', STR_PAD_LEFT) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Email</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($userData['email'] ?? '-') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">No. HP / WA</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($userData['phone'] ?? '-') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                    <span style="color:#64748b;">Username</span>
                    <code style="color:#2563eb;"><?= htmlspecialchars($userData['username']) ?></code>
                </div>
            </div>
            <button onclick="closeInfoModal(); openPhotoModal();" class="btn btn-primary" style="width:100%;margin-top:16px;border-radius:12px;padding:10px;font-weight:700;">Ubah Kontak / Foto</button>
        `
    },
    pekerjaan: {
        title: 'Info Pekerjaan',
        html: `
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Jabatan</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($positionTitle) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Unit Kerja</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($companyTitle) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <span style="color:#64748b;">Divisi</span>
                    <strong style="color:#0f172a;"><?= htmlspecialchars($userData['division_name'] ?? 'Operasional') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                    <span style="color:#64748b;">Status Pegawai</span>
                    <span style="color:#10b981;font-weight:700;">Karyawan Tetap (Aktif)</span>
                </div>
            </div>
        `
    }
};

function openInfoModal(type) {
    const d = userInfoData[type];
    if (!d) return;
    document.getElementById('infoModalTitle').innerText = d.title;
    document.getElementById('infoModalBody').innerHTML = d.html;
    const m = document.getElementById('modalInfoSheet');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}

function closeInfoModal(e) {
    if (e) e.stopPropagation();
    const m = document.getElementById('modalInfoSheet');
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
