<?php
// operator/karyawan.php - Talenta Style Employee Directory
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$activePage = 'op_karyawan';
$pageTitle = 'Employees';
$pageSubtitle = 'Direktori Karyawan & Kontak';

$today = date('Y-m-d');
$todayFormatted = Helpers::formatTanggalIndo($today);

// Filter Parameters
$search = trim($_GET['q'] ?? '');
$filterDiv = $_GET['division'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';

// Ambil daftar divisi untuk dropdown filter
$divisions = $db->query("SELECT * FROM divisions ORDER BY name ASC")->fetchAll();

// Query semua karyawan (operator & supervisor) beserta jadwal & presensi hari ini
$sql = "
    SELECT 
        u.id, u.full_name, u.username, u.email, u.phone, u.role, u.position, u.division_id, u.avatar,
        d.name as division_name, d.code as division_code,
        sc.id as schedule_id,
        s.code as shift_code, s.name as shift_name, s.start_time as shift_start, s.end_time as shift_end,
        a.id as attendance_id, a.clock_in, a.clock_out, a.status as attendance_status,
        l.id as leave_id, l.type as leave_type, l.status as leave_status
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN schedules sc ON sc.user_id = u.id AND sc.date = :today
    LEFT JOIN shifts s ON sc.shift_id = s.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = :today
    LEFT JOIN leaves l ON l.user_id = u.id AND :today BETWEEN l.start_date AND l.end_date AND l.status = 'approved'
    WHERE u.role IN ('operator', 'supervisor')
";

$params = [':today' => $today];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE :search OR u.username LIKE :search OR u.position LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($filterDiv !== 'all') {
    $sql .= " AND u.division_id = :divId";
    $params[':divId'] = $filterDiv;
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$allEmployees = $stmt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 540px; margin: 0 auto; padding-bottom: 24px;">

    <!-- 1. TOP HEADER (EXACT TALENTA: Employees Count + Hierarchy + Filter) -->
    <div class="talenta-emp-topbar">
        <div style="width: 32px;">
            <a href="<?= BASE_URL ?>/operator/index.php" style="color: #64748b; text-decoration: none;" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.25rem;"></i>
            </a>
        </div>
        <div class="talenta-emp-title" style="text-align: center;">
            Employees <span style="font-weight: 500; color: #64748b;"><?= count($allEmployees) ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/operator/jadwal.php" style="color: #64748b; text-decoration: none;" title="Bagan / Roster Kerja">
                <i class="ti ti-sitemap" style="font-size: 1.3rem;"></i>
            </a>
            <button type="button" onclick="toggleEmpFilter()" style="background: none; border: none; padding: 0; color: #64748b; cursor: pointer;" title="Filter Divisi">
                <i class="ti ti-filter" style="font-size: 1.3rem;"></i>
            </button>
        </div>
    </div>

    <!-- Collapsible Filter Drawer/Box -->
    <div id="filterEmpBox" style="display: <?= $filterDiv !== 'all' ? 'block' : 'none' ?>; background: #ffffff; border-radius: 16px; padding: 12px 16px; border: 1px solid #e2e8f0; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
        <form method="GET" action="" style="display: flex; gap: 8px;">
            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            <select name="division" class="form-control" style="border-radius: 10px; font-size: 0.8rem; height: 38px; border: 1.5px solid #e2e8f0; background: #f8fafc; flex: 1;" onchange="this.form.submit()">
                <option value="all">Semua Divisi (<?= count($divisions) ?>)</option>
                <?php foreach ($divisions as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDiv == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterDiv !== 'all' || !empty($search)): ?>
                <a href="<?= BASE_URL ?>/operator/karyawan.php" class="btn btn-secondary" style="border-radius: 10px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0 12px; font-size: 0.8rem;">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 2. SEARCH BAR (PILL INPUT: Cari Karyawan) -->
    <div class="talenta-emp-search-box">
        <i class="ti ti-search talenta-emp-search-icon"></i>
        <form method="GET" action="" style="margin: 0;">
            <input type="hidden" name="division" value="<?= htmlspecialchars($filterDiv) ?>">
            <input 
                type="text" 
                name="q" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="Cari Karyawan" 
                class="talenta-emp-search-input"
                autocomplete="off"
            >
        </form>
    </div>

    <!-- 3. EMPLOYEE LIST (WHITE CARD CONTAINER WITH CALL, EMAIL, WA BUTTONS) -->
    <?php if (empty($allEmployees)): ?>
        <div style="background: #ffffff; border-radius: 20px; padding: 36px 20px; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 2rem; color: #94a3b8; margin-bottom: 8px;">
                <i class="ti ti-users-off"></i>
            </div>
            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">Tidak Ada Karyawan Ditemukan</div>
            <div style="font-size: 0.775rem; color: #64748b; margin-top: 4px;">Coba gunakan kata kunci pencarian yang lain.</div>
        </div>
    <?php else: ?>
        <div class="talenta-emp-list">
            <?php foreach ($allEmployees as $emp): 
                $empPhone = trim($emp['phone'] ?? '');
                $cleanPhone = preg_replace('/[^0-9]/', '', $empPhone);
                if (substr($cleanPhone, 0, 1) === '0') {
                    $waNumber = '62' . substr($cleanPhone, 1);
                } else {
                    $waNumber = $cleanPhone;
                }
                $empEmail = trim($emp['email'] ?? '');
                $position = !empty($emp['position']) ? $emp['position'] : ($emp['division_code'] ?? 'SPO MT');
            ?>
                <div class="talenta-emp-row">
                    <!-- Left: Circular Avatar -->
                    <div class="talenta-emp-avatar">
                        <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $emp['avatar'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($emp['avatar']) ?>" alt="Avatar">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #64748b, #475569); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Middle: Name & Subtitle Position -->
                    <div class="talenta-emp-info">
                        <div class="talenta-emp-name">
                            <?= htmlspecialchars($emp['full_name']) ?>
                        </div>
                        <div class="talenta-emp-role">
                            <?= htmlspecialchars($position) ?>
                        </div>
                    </div>

                    <!-- Right: Call, Email, WhatsApp Buttons -->
                    <div class="talenta-emp-actions">
                        <!-- Phone Call -->
                        <?php if (!empty($empPhone)): ?>
                            <a href="tel:<?= htmlspecialchars($empPhone) ?>" class="talenta-emp-action-btn" title="Telepon">
                                <i class="ti ti-phone"></i>
                            </a>
                        <?php else: ?>
                            <span class="talenta-emp-action-btn" style="opacity: 0.35; cursor: not-allowed;" title="Nomor telepon tidak tersedia">
                                <i class="ti ti-phone"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Email -->
                        <?php if (!empty($empEmail)): ?>
                            <a href="mailto:<?= htmlspecialchars($empEmail) ?>" class="talenta-emp-action-btn" title="Kirim Email">
                                <i class="ti ti-mail"></i>
                            </a>
                        <?php else: ?>
                            <span class="talenta-emp-action-btn" style="opacity: 0.35; cursor: not-allowed;" title="Email tidak tersedia">
                                <i class="ti ti-mail"></i>
                            </span>
                        <?php endif; ?>

                        <!-- WhatsApp -->
                        <?php if (!empty($waNumber)): ?>
                            <a href="https://wa.me/<?= htmlspecialchars($waNumber) ?>" target="_blank" class="talenta-emp-action-btn wa" title="Chat WhatsApp">
                                <i class="ti ti-brand-whatsapp"></i>
                            </a>
                        <?php else: ?>
                            <span class="talenta-emp-action-btn" style="opacity: 0.35; cursor: not-allowed;" title="WhatsApp tidak tersedia">
                                <i class="ti ti-brand-whatsapp"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleEmpFilter() {
    const box = document.getElementById('filterEmpBox');
    if (box) {
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
