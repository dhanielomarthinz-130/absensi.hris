<?php
// admin/menu_operator.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_menu_operator';
$pageTitle = 'Pengaturan Menu Portal Operator';
$pageSubtitle = 'Kelola status aktif, nonaktif, dan pembekuan menu layanan mandiri untuk karyawan / operator';

// PROSES POST: Toggle Status atau Simpan Pengaturan
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_menu') {
        $menuId = (int)$_POST['menu_id'];
        $st = $db->prepare("SELECT is_active, title FROM operator_menus WHERE id = ?");
        $st->execute([$menuId]);
        $menu = $st->fetch();

        if ($menu) {
            $newStatus = $menu['is_active'] ? 0 : 1;
            $up = $db->prepare("UPDATE operator_menus SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $up->execute([$newStatus, $menuId]);

            $cleanTitle = strip_tags(str_replace('<br>', ' ', $menu['title']));
            $statusText = $newStatus ? 'diaktifkan kembali' : 'dibekukan (dinonaktifkan)';
            Helpers::setFlash('success', "Menu {$cleanTitle} berhasil {$statusText}.");
        }
        header('Location: ' . BASE_URL . '/admin/menu_operator.php');
        exit;
    }

    if ($_POST['action'] === 'update_message') {
        $menuId = (int)$_POST['menu_id'];
        $frozenMsg = trim($_POST['frozen_message'] ?? '');
        $up = $db->prepare("UPDATE operator_menus SET frozen_message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $up->execute([$frozenMsg, $menuId]);

        Helpers::setFlash('success', "Pesan pembekuan menu berhasil diperbarui.");
        header('Location: ' . BASE_URL . '/admin/menu_operator.php');
        exit;
    }
}

// Ambil semua menu operator
$menus = $db->query("SELECT * FROM operator_menus ORDER BY sort_order ASC")->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 1080px; margin: 0 auto; margin-bottom: 35px;">
    <!-- Top Bar Info Banner -->
    <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%); border-radius: 20px; padding: 22px 26px; color: #ffffff; margin-bottom: 24px; box-shadow: 0 10px 25px -4px rgba(30, 27, 75, 0.35);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="ti ti-adjustments-alt" style="color: #818cf8;"></i> Kontrol Akses Fitur Karyawan
                </div>
                <h2 style="font-size: 1.35rem; font-weight: 800; margin: 4px 0 6px; color: #ffffff;">
                    Manajemen & Pembekuan Menu Operator
                </h2>
                <div style="font-size: 0.8rem; color: #e0e7ff; max-width: 650px;">
                    Aktifkan atau bekukan menu di portal operator secara instan. Menu yang dibekukan akan ditandai dengan badge "Dibekukan" dan akses tombol akan dibatasi dengan pesan penjelasan HR.
                </div>
            </div>
            <a href="<?= BASE_URL ?>/operator/index.php" target="_blank" class="btn btn-secondary" style="background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25); color: #ffffff; border-radius: 12px; font-size: 0.8rem; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ti ti-external-link"></i> Pratinjau Portal Operator
            </a>
        </div>
    </div>

    <!-- Tabel Daftar Menu Operator -->
    <div style="background: #ffffff; border-radius: 22px; padding: 20px 24px; border: 1px solid rgba(226, 232, 240, 0.85); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <div style="font-weight: 800; font-size: 1rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="ti ti-layout-grid" style="color: var(--primary);"></i>
                <span>Daftar Menu Layanan Mandiri (Quick Actions)</span>
            </div>
            <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                Total: <?= count($menus) ?> Menu
            </span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($menus as $m): 
                $isActive = (bool)$m['is_active'];
                $cleanTitle = strip_tags(str_replace('<br>', ' ', $m['title']));
            ?>
                <div style="background: <?= $isActive ? '#ffffff' : '#f8fafc' ?>; border-radius: 18px; padding: 16px 20px; border: 1px solid <?= $isActive ? '#e2e8f0' : '#fecaca' ?>; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <!-- Kolom Ikon & Detail Menu -->
                    <div style="display: flex; align-items: center; gap: 16px; min-width: 260px;">
                        <div class="talenta-tile-icon <?= htmlspecialchars($m['color_class']) ?>" style="width: 50px; height: 50px; border-radius: 16px; font-size: 1.5rem; opacity: <?= $isActive ? '1' : '0.5' ?>;">
                            <i class="<?= strpos($m['icon'], 'ti ') === 0 ? htmlspecialchars($m['icon']) : 'ti ' . htmlspecialchars($m['icon']) ?>"></i>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 0.95rem; color: #0f172a;"><?= $cleanTitle ?></strong>
                                <?php if ($isActive): ?>
                                    <span style="font-size: 0.65rem; font-weight: 800; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 999px;">
                                        AKTIF
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 0.65rem; font-weight: 800; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; padding: 2px 8px; border-radius: 999px;">
                                        <i class="ti ti-lock"></i> DIBEKUKAN
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                <?= htmlspecialchars($m['subtitle']) ?> &bull; URL: <code style="font-size: 0.725rem; color: #4338ca;"><?= htmlspecialchars($m['url']) ?></code>
                            </div>
                            <?php if (!$isActive): ?>
                                <div style="font-size: 0.725rem; color: #b91c1c; margin-top: 4px; font-style: italic;">
                                    "<?= htmlspecialchars($m['frozen_message']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kolom Aksi Toggle & Pesan Pembekuan -->
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Form Edit Pesan Pembekuan -->
                        <button type="button" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.75rem; padding: 7px 12px;" onclick="document.getElementById('modalMsg_<?= $m['id'] ?>').style.display='flex'">
                            <i class="ti ti-message-2"></i> Edit Pesan
                        </button>

                        <!-- Form Toggle Status -->
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="action" value="toggle_menu">
                            <input type="hidden" name="menu_id" value="<?= $m['id'] ?>">
                            <?php if ($isActive): ?>
                                <button type="submit" class="btn btn-danger" style="border-radius: 10px; font-size: 0.75rem; padding: 7px 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;" onclick="return confirm('Bekukan menu <?= $cleanTitle ?> untuk operator?');">
                                    <i class="ti ti-lock"></i> Bekukan Menu
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #059669; border-radius: 10px; font-size: 0.75rem; padding: 7px 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="ti ti-lock-open"></i> Aktifkan Menu
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Modal Edit Pesan Pembekuan -->
                <div id="modalMsg_<?= $m['id'] ?>" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 16px;">
                    <div style="background: #ffffff; border-radius: 20px; max-width: 480px; width: 100%; padding: 22px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">
                                Pesan Pembekuan: <?= $cleanTitle ?>
                            </h3>
                            <button type="button" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;" onclick="document.getElementById('modalMsg_<?= $m['id'] ?>').style.display='none'">&times;</button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_message">
                            <input type="hidden" name="menu_id" value="<?= $m['id'] ?>">
                            <div style="margin-bottom: 16px;">
                                <label style="font-size: 0.75rem; font-weight: 700; color: #475569; display: block; margin-bottom: 6px;">
                                    Pesan yang Ditampilkan ke Karyawan saat Menu Dibekukan:
                                </label>
                                <textarea name="frozen_message" rows="3" class="form-control" style="font-size: 0.825rem; border-radius: 12px;" placeholder="Tuliskan alasan pembekuan menu..."><?= htmlspecialchars($m['frozen_message']) ?></textarea>
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                <button type="button" class="btn btn-secondary" style="border-radius: 10px; font-size: 0.8rem;" onclick="document.getElementById('modalMsg_<?= $m['id'] ?>').style.display='none'">Batal</button>
                                <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-size: 0.8rem; font-weight: 700;">Simpan Pesan</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
