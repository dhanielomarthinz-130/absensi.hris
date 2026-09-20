<?php
// templates/modal_settings.php - Settings Right Offcanvas Sidebar & Sub-Panels
$currentUser = Auth::user() ?? [];
$db = Database::getConnection();

// Ambil data user lengkap jika sedang login
$drawerUser = null;
if (!empty($currentUser['id'])) {
    $stDrawer = $db->prepare("
        SELECT u.*, d.name as division_name, d.code as division_code
        FROM users u
        LEFT JOIN divisions d ON u.division_id = d.id
        WHERE u.id = ?
    ");
    $stDrawer->execute([$currentUser['id']]);
    $drawerUser = $stDrawer->fetch();
}
if (!$drawerUser) {
    $drawerUser = $currentUser;
}
?>

<!-- RIGHT OFFCANVAS SIDEBAR SETTINGS DRAWER -->
<div class="settings-drawer-backdrop" id="settingsDrawerBackdrop" onclick="if(event.target === this) closeSettingsDrawer();">
    <div class="settings-drawer" id="settingsDrawer">
        <!-- Header Drawer with Dark Blue Gradient -->
        <div class="settings-drawer-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Back button to return to main menu from sub-panels -->
                <button type="button" id="drawerBackBtn" onclick="switchDrawerPanel('main')" class="settings-drawer-icon-btn" style="display: none;" title="Kembali ke Menu Utama">
                    <i class="ti ti-arrow-left"></i>
                </button>
                <div>
                    <h3 id="drawerTitleText" style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #ffffff; letter-spacing: -0.3px;">
                        Pengaturan Akun
                    </h3>
                    <div style="font-size: 0.725rem; color: #cbd5e1; margin-top: 1px;">
                        Portal Akses & Manajemen Diri
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeSettingsDrawer()" class="settings-drawer-icon-btn" title="Tutup">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <!-- Scrollable Drawer Body -->
        <div class="settings-drawer-body">
            
            <!-- PANEL 1: MENU UTAMA (MAIN) -->
            <div id="drawerPanel_main" class="drawer-panel active">
                <!-- Mini Profile Header Card -->
                <div class="drawer-profile-card">
                    <div class="drawer-avatar-ring">
                        <div class="drawer-avatar-inner">
                            <?php if (!empty($drawerUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $drawerUser['avatar'])): ?>
                                <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($drawerUser['avatar']) ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?= strtoupper(substr($drawerUser['full_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-weight: 800; font-size: 1rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($drawerUser['full_name'] ?? 'Pengguna') ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                            <?= htmlspecialchars($drawerUser['position'] ?? 'Operator') ?> &bull; <?= htmlspecialchars($drawerUser['division_name'] ?? 'Operasional') ?>
                        </div>
                        <div style="margin-top: 6px;">
                            <span style="font-size: 0.675rem; font-weight: 700; color: #4338ca; background: #eef2ff; padding: 2px 8px; border-radius: 6px; border: 1px solid #c7d2fe;">
                                NIK: EMP-<?= str_pad($drawerUser['id'] ?? 0, 4, '0', STR_PAD_LEFT) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Menu List Options -->
                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 14px;">
                    <?php if (in_array($drawerUser['role'] ?? '', ['supervisor', 'admin'])): ?>
                        <!-- Shortcut Khusus Supervisor: Approval Izin Tim -->
                        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="drawer-menu-card" style="border: 1.5px solid #c7d2fe; background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%); text-decoration: none;">
                            <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #4f46e5, #4338ca); color: #ffffff;">
                                <i class="ti ti-file-certificate"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 800; font-size: 0.875rem; color: #3730a3; display: flex; align-items: center; gap: 6px;">
                                    <span>Approval Izin Tim</span>
                                    <span style="font-size: 0.625rem; background: #4338ca; color: #ffffff; padding: 1px 6px; border-radius: 999px;">SPV</span>
                                </div>
                                <div style="font-size: 0.725rem; color: #6366f1;">Tinjau pengajuan izin & surat dokter tim</div>
                            </div>
                            <i class="ti ti-arrow-right" style="color: #4338ca; font-size: 1.1rem;"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Option 1: Detail Informasi Karyawan (Lihat Profil) -->
                    <div class="drawer-menu-card" onclick="switchDrawerPanel('profile')">
                        <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb;">
                            <i class="ti ti-id"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #0f172a;">Detail Informasi Karyawan</div>
                            <div style="font-size: 0.725rem; color: #64748b;">Lihat data NIK, jabatan, divisi, dan masa kerja</div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.1rem;"></i>
                    </div>

                    <!-- Option 2: Foto Profil & Kontak -->
                    <div class="drawer-menu-card" onclick="switchDrawerPanel('contact')">
                        <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #7c3aed;">
                            <i class="ti ti-camera"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #0f172a;">Foto Profil & Kontak</div>
                            <div style="font-size: 0.725rem; color: #64748b;">Perbarui foto avatar, email, dan WhatsApp</div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.1rem;"></i>
                    </div>

                    <!-- Option 3: Ganti Password Akun -->
                    <div class="drawer-menu-card" onclick="switchDrawerPanel('password')">
                        <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309;">
                            <i class="ti ti-lock"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #0f172a;">Ganti Password Akun</div>
                            <div style="font-size: 0.725rem; color: #64748b;">Perbarui kata sandi untuk keamanan akun</div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.1rem;"></i>
                    </div>

                    <!-- Option 4: About Aplikasi -->
                    <div class="drawer-menu-card" onclick="switchDrawerPanel('about')">
                        <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #059669;">
                            <i class="ti ti-info-circle"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #0f172a;">About Aplikasi</div>
                            <div style="font-size: 0.725rem; color: #64748b;">Informasi pembuat Dhanielo-marthinz | IMS</div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #94a3b8; font-size: 1.1rem;"></i>
                    </div>

                    <div style="height: 1px; background: #f1f5f9; margin: 6px 0;"></div>

                    <!-- Option 5: Logout -->
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="drawer-menu-card" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');" style="border-color: #fee2e2;">
                        <div class="drawer-menu-icon" style="background: linear-gradient(135deg, #fff1f2, #ffe4e6); color: #e11d48;">
                            <i class="ti ti-logout"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #e11d48;">Keluar / Logout</div>
                            <div style="font-size: 0.725rem; color: #f43f5e;">Selesaikan sesi kerja pada perangkat ini</div>
                        </div>
                        <i class="ti ti-chevron-right" style="color: #fda4af; font-size: 1.1rem;"></i>
                    </a>
                </div>
            </div>

            <!-- PANEL 2: DETAIL INFORMASI KARYAWAN (SESUAI SCREENSHOT 3) -->
            <div id="drawerPanel_profile" class="drawer-panel" style="display: none;">
                <div class="drawer-sub-card">
                    <div class="drawer-sub-card-header">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="ti ti-user-check" style="color: #4338ca; font-size: 1.25rem;"></i>
                            <span style="font-weight: 800; font-size: 0.875rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px;">
                                Detail Informasi Karyawan
                            </span>
                        </div>
                    </div>

                    <div class="drawer-info-list">
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Nomor Induk Karyawan (NIK)</span>
                            <strong class="drawer-info-val">EMP-<?= str_pad($drawerUser['id'] ?? 0, 4, '0', STR_PAD_LEFT) ?></strong>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Nama Lengkap</span>
                            <strong class="drawer-info-val"><?= htmlspecialchars($drawerUser['full_name'] ?? '-') ?></strong>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Username Akun</span>
                            <code class="drawer-code-pill"><?= htmlspecialchars($drawerUser['username'] ?? '-') ?></code>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Divisi</span>
                            <span class="drawer-badge-pill"><?= htmlspecialchars($drawerUser['division_name'] ?? 'Operasional') ?></span>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Jabatan / Posisi</span>
                            <strong class="drawer-info-val"><?= htmlspecialchars($drawerUser['position'] ?? 'Staff') ?></strong>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Hak Akses (Role)</span>
                            <span class="drawer-role-pill"><?= strtoupper($drawerUser['role'] ?? 'OPERATOR') ?></span>
                        </div>

                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Tipe Jam Kerja</span>
                            <strong class="drawer-info-val">Roster Shift Bergilir</strong>
                        </div>

                        <div class="drawer-info-row" style="border-bottom: none;">
                            <span class="drawer-info-label">Tanggal Bergabung</span>
                            <strong class="drawer-info-val"><?= !empty($drawerUser['created_at']) ? date('d F Y', strtotime($drawerUser['created_at'])) : date('d F Y') ?></strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 14px;">
                    <button type="button" onclick="switchDrawerPanel('contact')" class="btn btn-primary" style="flex: 1; border-radius: 12px; height: 42px; font-weight: 700; font-size: 0.825rem; background: #4338ca; border: none;">
                        <i class="ti ti-camera"></i> Ubah Foto & Kontak
                    </button>
                    <button type="button" onclick="switchDrawerPanel('password')" class="btn btn-secondary" style="border-radius: 12px; height: 42px; font-weight: 700; font-size: 0.825rem;">
                        <i class="ti ti-lock"></i> Ganti Password
                    </button>
                </div>
            </div>

            <!-- PANEL 3: FOTO PROFIL & KONTAK (SESUAI SCREENSHOT 1) -->
            <div id="drawerPanel_contact" class="drawer-panel" style="display: none;">
                <div class="drawer-sub-card">
                    <div class="drawer-sub-card-header" style="justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="ti ti-camera" style="color: #4338ca; font-size: 1.25rem;"></i>
                            <span style="font-weight: 800; font-size: 0.875rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px;">
                                Foto Profil & Kontak
                            </span>
                        </div>
                        <span style="font-size: 0.65rem; color: #64748b; font-weight: 600;">Maks 2MB (JPG, PNG)</span>
                    </div>

                    <form method="POST" action="<?= BASE_URL ?>/operator/profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">

                        <!-- Upload Photo Showcase Area (Modern, Clean & Interactive) -->
                        <div class="avatar-upload-zone" id="drawerUploadZone" onclick="triggerDrawerFileInput(event)">
                            <!-- Hidden Native File Input (Guaranteed never to render browser defaults) -->
                            <input type="file" name="avatar" id="drawerAvatarInput" accept="image/jpeg,image/png,image/webp" style="display: none !important;" onchange="handleDrawerAvatarSelected(this)">

                            <!-- Interactive Center Avatar with Camera Badge & Hover Effect -->
                            <div class="avatar-preview-wrapper" title="Klik untuk memilih foto baru">
                                <div class="avatar-preview-inner">
                                    <?php if (!empty($drawerUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $drawerUser['avatar'])): ?>
                                        <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($drawerUser['avatar']) ?>" id="drawerAvatarPreview" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <span id="drawerAvatarInitial"><?= strtoupper(substr($drawerUser['full_name'] ?? 'U', 0, 1)) ?></span>
                                        <img src="" id="drawerAvatarPreview" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <?php endif; ?>
                                    
                                    <!-- Hover Overlay -->
                                    <div class="avatar-preview-overlay">
                                        <i class="ti ti-camera" style="font-size: 1.25rem;"></i>
                                        <span>Ganti</span>
                                    </div>
                                </div>
                                <!-- Floating Camera Badge Overlay -->
                                <div class="avatar-camera-floating-badge">
                                    <i class="ti ti-camera"></i>
                                </div>
                            </div>

                            <!-- Styled Action Buttons -->
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 6px;">
                                <button type="button" class="avatar-upload-btn" onclick="document.getElementById('drawerAvatarInput').click();">
                                    <i class="ti ti-cloud-upload"></i>
                                    <span>Pilih File Foto Baru</span>
                                </button>

                                <?php if (!empty($drawerUser['avatar'])): ?>
                                    <button type="submit" name="action" value="remove_avatar" class="avatar-remove-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus foto profil ini dan kembali ke inisial nama?');" title="Hapus Foto">
                                        <i class="ti ti-trash"></i> Hapus
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Selected File Dynamic Confirmation Pill -->
                            <div id="drawerFileSelectedBox" style="display: none; margin-top: 10px;">
                                <div class="avatar-selected-pill">
                                    <i class="ti ti-circle-check-filled" style="color: #10b981; font-size: 1.1rem;"></i>
                                    <span id="drawerFileNameText" style="max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                                    <button type="button" onclick="cancelDrawerAvatarSelection(event)" style="background: none; border: none; color: #94a3b8; font-size: 0.95rem; cursor: pointer; padding: 0 4px; line-height: 1;" title="Batal pilihan file">✕</button>
                                </div>
                            </div>

                            <div style="font-size: 0.675rem; color: #64748b; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <i class="ti ti-info-circle" style="color: #6366f1;"></i>
                                <span>Format: <strong>JPG, PNG, atau WEBP</strong> &bull; Maksimal 2MB</span>
                            </div>
                        </div>

                        <!-- Email Input -->
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.775rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                                <i class="ti ti-mail" style="color: #6366f1;"></i> Alamat Email
                            </label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($drawerUser['email'] ?? '') ?>" placeholder="nama@perusahaan.com" style="border-radius: 10px; font-size: 0.825rem; height: 40px;">
                        </div>

                        <!-- WhatsApp Input -->
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label style="font-size: 0.775rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                                <i class="ti ti-brand-whatsapp" style="color: #10b981;"></i> Nomor WhatsApp / HP
                            </label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($drawerUser['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" style="border-radius: 10px; font-size: 0.825rem; height: 40px;">
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 12px; height: 42px; font-weight: 700; font-size: 0.85rem; background: #4338ca; border: none; box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);">
                                <i class="ti ti-device-floppy"></i>
                                <span>Simpan Foto & Kontak</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PANEL 4: GANTI PASSWORD AKUN (SESUAI SCREENSHOT 2) -->
            <div id="drawerPanel_password" class="drawer-panel" style="display: none;">
                <div class="drawer-sub-card">
                    <div class="drawer-sub-card-header">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="ti ti-lock" style="color: #e11d48; font-size: 1.25rem;"></i>
                            <span style="font-weight: 800; font-size: 0.875rem; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px;">
                                Ganti Password Akun
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="<?= BASE_URL ?>/operator/profile.php">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.775rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Password Saat Ini
                            </label>
                            <input type="password" name="current_password" class="form-control" required placeholder="Masukkan password lama" style="border-radius: 10px; font-size: 0.825rem; height: 40px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.775rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Password Baru
                            </label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter" style="border-radius: 10px; font-size: 0.825rem; height: 40px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 18px;">
                            <label style="font-size: 0.775rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Konfirmasi Password Baru
                            </label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Ketik ulang password baru" style="border-radius: 10px; font-size: 0.825rem; height: 40px;">
                        </div>

                        <button type="submit" class="btn btn-danger" style="width: 100%; border-radius: 12px; height: 44px; font-weight: 800; font-size: 0.85rem; background: linear-gradient(135deg, #be123c 0%, #e11d48 50%, #f43f5e 100%); border: none; box-shadow: 0 6px 18px rgba(225, 29, 72, 0.35);">
                            <i class="ti ti-shield-lock"></i>
                            <span>Perbarui Password</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- PANEL 5: ABOUT APLIKASI (DENGAN IDENTITAS PEMBUAT SESUAI PERMINTAAN) -->
            <div id="drawerPanel_about" class="drawer-panel" style="display: none;">
                <!-- Header Card About -->
                <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%); border-radius: 18px; padding: 22px 18px; color: #ffffff; text-align: center; margin-bottom: 14px; position: relative; overflow: hidden; box-shadow: 0 8px 24px rgba(30, 27, 75, 0.3);">
                    <div style="width: 54px; height: 54px; border-radius: 16px; background: linear-gradient(135deg, #ffffff, #e0e7ff); color: #3730a3; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; margin: 0 auto 10px; box-shadow: 0 6px 16px rgba(0,0,0,0.2);">
                        <i class="ti ti-calendar-time"></i>
                    </div>
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #ffffff;">
                        <?= APP_NAME ?>
                    </h4>
                    <div style="font-size: 0.725rem; color: #c7d2fe; margin-top: 2px;">
                        Enterprise Roster & Attendance Portal
                    </div>
                    <div style="margin-top: 8px;">
                        <span style="font-size: 0.65rem; font-weight: 700; background: rgba(255,255,255,0.18); padding: 2px 10px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.25);">
                            Versi 2.5.0 Enterprise
                        </span>
                    </div>
                </div>

                <!-- CARD PEMBUAT APLIKASI SESUAI PERMINTAAN USER -->
                <div style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1.5px solid #a7f3d0; border-radius: 16px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 3px 12px rgba(16, 185, 129, 0.08);">
                    <div style="font-size: 0.65rem; font-weight: 800; color: #047857; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: flex; align-items: center; gap: 5px;">
                        <i class="ti ti-code" style="font-size: 0.85rem;"></i> Pembuat Aplikasi
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #064e3b; letter-spacing: -0.2px;">
                        Dhanielo-marthinz | IMS
                    </div>
                    <div style="font-size: 0.725rem; color: #059669; margin-top: 2px;">
                        Lead Software Architecture & System Engineering
                    </div>
                </div>

                <!-- Detail Metadata Sistem -->
                <div class="drawer-sub-card" style="margin-bottom: 14px;">
                    <div class="drawer-info-list">
                        <div class="drawer-info-row">
                            <span class="drawer-info-label"><i class="ti ti-building"></i> Organisasi</span>
                            <strong class="drawer-info-val">IMS Group Operations</strong>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label"><i class="ti ti-shield-check"></i> Hak Akses</span>
                            <strong class="drawer-info-val" style="color: #059669;">Role-Based (RBAC)</strong>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label"><i class="ti ti-device-mobile"></i> Dukungan</span>
                            <strong class="drawer-info-val">Mobile & Web Responsive</strong>
                        </div>
                        <div class="drawer-info-row" style="border-bottom: none;">
                            <span class="drawer-info-label"><i class="ti ti-clock-check"></i> Status Sinkronisasi</span>
                            <strong class="drawer-info-val" style="color: #059669;">Real-Time Terkoneksi</strong>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="switchDrawerPanel('main')" class="btn btn-secondary" style="width: 100%; border-radius: 12px; height: 40px; font-weight: 700; font-size: 0.825rem;">
                    <i class="ti ti-arrow-left"></i> Kembali ke Menu Utama
                </button>
            </div>

        </div>
    </div>
</div>

<!-- JAVASCRIPT CONTROLLER UNTUK SIDEBAR OFFCANVAS -->
<script>
function openSettingsDrawer(panelName = 'main') {
    const backdrop = document.getElementById('settingsDrawerBackdrop');
    if (backdrop) {
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
        switchDrawerPanel(panelName);
    }
}

function closeSettingsDrawer() {
    const backdrop = document.getElementById('settingsDrawerBackdrop');
    if (backdrop) {
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function switchDrawerPanel(panelName) {
    const panels = ['main', 'profile', 'contact', 'password', 'about'];
    const titles = {
        'main': 'Pengaturan Akun',
        'profile': 'Informasi Karyawan',
        'contact': 'Foto Profil & Kontak',
        'password': 'Ganti Password Akun',
        'about': 'About Aplikasi'
    };
    
    panels.forEach(p => {
        const el = document.getElementById('drawerPanel_' + p);
        if (el) {
            el.style.display = (p === panelName) ? 'block' : 'none';
        }
    });

    const titleEl = document.getElementById('drawerTitleText');
    if (titleEl) {
        titleEl.textContent = titles[panelName] || 'Pengaturan Akun';
    }

    const backBtn = document.getElementById('drawerBackBtn');
    if (backBtn) {
        backBtn.style.display = (panelName === 'main') ? 'none' : 'inline-flex';
    }
}

function triggerDrawerFileInput(e) {
    // Hindari trigger ganda jika klik tombol atau pembatalan
    if (e.target.closest('button') || e.target.closest('input')) return;
    const input = document.getElementById('drawerAvatarInput');
    if (input) input.click();
}

function handleDrawerAvatarSelected(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 2 * 1024 * 1024) {
            showToast('Ukuran file foto maksimal adalah 2MB!', 'warning', 'Ukuran Berkas Terlalu Besar');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('drawerAvatarPreview');
            const initial = document.getElementById('drawerAvatarInitial');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (initial) {
                initial.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);

        // Tampilkan pill konfirmasi nama file & ukuran
        const pillBox = document.getElementById('drawerFileSelectedBox');
        const nameText = document.getElementById('drawerFileNameText');
        if (pillBox && nameText) {
            const sizeKb = Math.round(file.size / 1024);
            nameText.textContent = `${file.name} (${sizeKb} KB)`;
            pillBox.style.display = 'inline-block';
        }
    }
}

function cancelDrawerAvatarSelection(e) {
    if (e) e.stopPropagation();
    const input = document.getElementById('drawerAvatarInput');
    if (input) input.value = '';

    const pillBox = document.getElementById('drawerFileSelectedBox');
    if (pillBox) pillBox.style.display = 'none';

    // Kembalikan ke foto awal
    const preview = document.getElementById('drawerAvatarPreview');
    const initial = document.getElementById('drawerAvatarInitial');
    <?php if (!empty($drawerUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $drawerUser['avatar'])): ?>
        if (preview) {
            preview.src = '<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($drawerUser['avatar']) ?>';
            preview.style.display = 'block';
        }
        if (initial) initial.style.display = 'none';
    <?php else: ?>
        if (preview) preview.style.display = 'none';
        if (initial) initial.style.display = 'block';
    <?php endif; ?>
}

// Drag & drop support untuk drawer upload zone
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drawerUploadZone');
    const fileInput = document.getElementById('drawerAvatarInput');
    if (!dropZone || !fileInput) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            handleDrawerAvatarSelected(fileInput);
        }
    });
});

// Support tombol Escape untuk menutup drawer
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSettingsDrawer();
    }
});
</script>
