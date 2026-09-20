<?php
// operator/jadwal.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = $currentUser['id'];
$divId = (int)($currentUser['division_id'] ?: 1);
$todayStr = date('Y-m-d');
$activePage = 'op_jadwal';

// Parameter Navigasi Minggu & Tahun
$week = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validasi batas week
if ($week < 1) {
    $week = 52;
    $year--;
} elseif ($week > 52) {
    $week = 1;
    $year++;
}

// Hitung rentang 7 hari dalam minggu tersebut (Senin s/d Minggu)
$dto = new DateTime();
$dto->setISODate($year, $week, 1); // Senin
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $cur = clone $dto;
    $cur->modify("+{$i} days");
    $dateKey = $cur->format('Y-m-d');
    $weekDates[$dateKey] = [
        'date' => $dateKey,
        'day_name' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][$i],
        'day_num' => $cur->format('d'),
        'month_name' => $cur->format('M'),
        'full_formatted' => Helpers::formatTanggalIndo($dateKey),
        'is_today' => ($dateKey === $todayStr),
        'is_past' => ($dateKey < $todayStr)
    ];
}
$firstDay = array_key_first($weekDates);
$lastDay = array_key_last($weekDates);

// Ambil Jadwal Karyawan untuk minggu ini
$sqlMySched = "
    SELECT s.date, s.shift_id, s.notes as sched_notes,
           sh.name as shift_name, sh.code as shift_code, sh.start_time, sh.end_time, sh.color as shift_color,
           a.clock_in, a.clock_out, a.status as attendance_status, a.notes as att_notes,
           l.type as leave_type, l.reason as leave_reason, l.status as leave_status
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = s.user_id AND a.date = s.date
    LEFT JOIN leaves l ON l.user_id = s.user_id AND l.status = 'approved' AND s.date BETWEEN l.start_date AND l.end_date
    WHERE s.user_id = ? AND s.date BETWEEN ? AND ?
    ORDER BY s.date ASC
";
$stmtMy = $db->prepare($sqlMySched);
$stmtMy->execute([$userId, $firstDay, $lastDay]);
$mySchedules = [];
while ($row = $stmtMy->fetch()) {
    $mySchedules[$row['date']] = $row;
}

// Ambil Rekan Kerja Satu Divisi per Tanggal & Shift
$sqlTeammates = "
    SELECT s.date, s.shift_id, u.id as teammate_id, u.full_name, u.position, a.clock_in, a.status as att_status
    FROM schedules s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = s.date
    WHERE u.division_id = ? AND u.role = 'operator' AND u.id != ? AND s.date BETWEEN ? AND ?
    ORDER BY u.full_name ASC
";
$stmtTeam = $db->prepare($sqlTeammates);
$stmtTeam->execute([$divId, $userId, $firstDay, $lastDay]);
$teammatesByDate = [];
while ($row = $stmtTeam->fetch()) {
    $teammatesByDate[$row['date']][$row['shift_id']][] = $row;
}

// Hitung Statistik Minggu Ini
$totalWorkDays = 0;
$totalOffDays = 0;
$totalWorkHours = 0;
foreach ($weekDates as $dt => $w) {
    $sc = $mySchedules[$dt] ?? null;
    if ($sc) {
        if ($sc['shift_id'] == 3) {
            $totalOffDays++;
        } else {
            $totalWorkDays++;
            $totalWorkHours += 8; // Rata-rata shift operasional 8 jam
        }
    }
}

// Hitung navigasi previous & next week
$prevWeek = $week - 1;
$prevYear = $year;
if ($prevWeek < 1) {
    $prevWeek = 52;
    $prevYear--;
}

$nextWeek = $week + 1;
$nextYear = $year;
if ($nextWeek > 52) {
    $nextWeek = 1;
    $nextYear++;
}

$pageTitle = 'Jadwal & Roster Kerja';
$pageSubtitle = 'Roster shift mingguan & rekan satu tim divisi ' . htmlspecialchars($currentUser['division_name'] ?? 'Operasional');

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 1080px; margin: 0 auto; margin-bottom: 30px;">
    <!-- 0. Header Top Navigation Back & Week Switcher -->
    <div class="premium-page-nav">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/operator/index.php" class="premium-back-btn" title="Kembali ke Beranda">
                <i class="ti ti-arrow-left" style="font-size: 1.15rem;"></i>
            </a>
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.3px;">Detail Jadwal Shift</h2>
                <div style="font-size: 0.725rem; color: #64748b; margin-top: 1px;">
                    <i class="ti ti-calendar"></i> Minggu ke-<?= $week ?> (<?= date('d M', strtotime($firstDay)) ?> - <?= date('d M Y', strtotime($lastDay)) ?>)
                </div>
            </div>
        </div>

        <!-- Week Navigator Buttons (Kompak) -->
        <div style="display: flex; align-items: center; gap: 6px; background: #ffffff; padding: 4px 6px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <a href="<?= BASE_URL ?>/operator/jadwal.php?week=<?= $prevWeek ?>&year=<?= $prevYear ?>" class="btn btn-secondary" style="padding: 5px 8px; font-size: 0.75rem; border-radius: 8px;">
                <i class="ti ti-chevron-left"></i>
            </a>
            <span style="font-size: 0.775rem; font-weight: 800; color: #1e1b4b; padding: 0 6px;">
                Week <?= $week ?>, <?= $year ?>
            </span>
            <a href="<?= BASE_URL ?>/operator/jadwal.php?week=<?= $nextWeek ?>&year=<?= $nextYear ?>" class="btn btn-secondary" style="padding: 5px 8px; font-size: 0.75rem; border-radius: 8px;">
                <i class="ti ti-chevron-right"></i>
            </a>
            <?php if ($week != (int)date('W') || $year != (int)date('Y')): ?>
                <a href="<?= BASE_URL ?>/operator/jadwal.php" class="btn btn-primary" style="padding: 5px 8px; font-size: 0.725rem; border-radius: 8px;">
                    Minggu Ini
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 1. STATISTIC METRIC CARDS (3 Kolom Berwarna Gradient Premium) -->
    <div class="premium-stat-grid-3">
        <!-- Card 1: Jam Kerja (Indigo) -->
        <div class="stat-card-gradient-indigo">
            <div class="stat-card-icon-glass">
                <i class="ti ti-clock-hour-4"></i>
            </div>
            <div style="font-size: 0.65rem; color: #c7d2fe; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Jam Kerja</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;"><?= $totalWorkHours ?> Jam</div>
        </div>

        <!-- Card 2: Hari Kerja (Emerald) -->
        <div class="stat-card-gradient-emerald">
            <div class="stat-card-icon-glass">
                <i class="ti ti-briefcase"></i>
            </div>
            <div style="font-size: 0.65rem; color: #a7f3d0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Hari Kerja</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;"><?= $totalWorkDays ?> Hari</div>
        </div>

        <!-- Card 3: Hari Libur (Slate) -->
        <div class="stat-card-gradient-slate">
            <div class="stat-card-icon-glass">
                <i class="ti ti-bed"></i>
            </div>
            <div style="font-size: 0.65rem; color: #cbd5e1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px;">Hari Libur</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff; line-height: 1.15; margin-top: 2px;"><?= $totalOffDays ?> Hari</div>
        </div>
    </div>

    <!-- 2. DETAIL ROSTER PER HARI (SENIN S/D MINGGU - DENGAN WARNA PREMIUM) -->
    <div style="display: flex; flex-direction: column; gap: 9px;">
        <?php foreach ($weekDates as $dt => $w): 
            $sc = $mySchedules[$dt] ?? null;
            $shiftId = $sc['shift_id'] ?? null;
            $isShift1 = ($shiftId == 1);
            $isShift2 = ($shiftId == 2);
            $isOff = ($shiftId == 3);
            $isToday = $w['is_today'];

            // Tentukan style warna kartu & tanggal
            $cardClass = 'roster-card';
            $dateBoxClass = 'roster-date-box';
            if ($isToday) {
                $cardClass .= ' is-today';
            } elseif ($isShift1) {
                $cardClass .= ' shift-1';
                $dateBoxClass .= ' shift-1-date';
            } elseif ($isShift2) {
                $cardClass .= ' shift-2';
                $dateBoxClass .= ' shift-2-date';
            } else {
                $cardClass .= ' shift-off';
            }

            // Rekan shift di hari tersebut
            $teammatesToday = ($shiftId && isset($teammatesByDate[$dt][$shiftId])) ? $teammatesByDate[$dt][$shiftId] : [];
        ?>
            <div class="<?= $cardClass ?>">
                <?php if ($isToday): ?>
                    <div style="position: absolute; top: 11px; right: 14px; font-size: 0.625rem; background: linear-gradient(135deg, #4338ca, #312e81); color: #ffffff; font-weight: 800; padding: 3px 10px; border-radius: 999px; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(67, 56, 202, 0.3); display: inline-flex; align-items: center; gap: 4px;">
                        <span style="width: 5px; height: 5px; border-radius: 50%; background: #34d399;"></span> HARI INI
                    </div>
                <?php endif; ?>

                <div class="roster-card-inner">
                    <!-- Bagian Tanggal & Shift -->
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="<?= $dateBoxClass ?>">
                            <span style="font-size: 0.6rem; font-weight: 700; text-transform: uppercase;"><?= substr($w['day_name'], 0, 3) ?></span>
                            <span style="font-size: 1.15rem; font-weight: 800; line-height: 1;"><?= $w['day_num'] ?></span>
                        </div>

                        <div>
                            <div style="font-weight: 800; font-size: 0.925rem; color: #0f172a;">
                                <?= $w['full_formatted'] ?>
                            </div>
                            <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <!-- Shift Badge dengan Warna Khas -->
                                <?php if ($isShift1): ?>
                                    <span class="badge-talenta-shift1" style="font-size: 0.725rem; padding: 3px 10px; font-weight: 700; background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #1d4ed8; border: 1px solid #bfdbfe;">
                                        <i class="ti ti-sun" style="color: #f59e0b; font-size: 0.85rem;"></i> Shift 1 (07:00 - 15:00 WIB)
                                    </span>
                                <?php elseif ($isShift2): ?>
                                    <span class="badge-talenta-shift2" style="font-size: 0.725rem; padding: 3px 10px; font-weight: 700; background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #6d28d9; border: 1px solid #ddd6fe;">
                                        <i class="ti ti-moon" style="color: #8b5cf6; font-size: 0.85rem;"></i> Shift 2 (15:00 - 23:00 WIB)
                                    </span>
                                <?php elseif ($isOff): ?>
                                    <span class="badge-talenta-shift-off" style="font-size: 0.725rem; padding: 3px 10px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                                        <i class="ti ti-bed" style="color: #64748b; font-size: 0.85rem;"></i> Bebas Tugas / Off Day
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-alpha" style="font-size: 0.7rem;">Belum Terjadwal</span>
                                <?php endif; ?>

                                <!-- Attendance Status Badge Berwarna Jelas -->
                                <?php if (!empty($sc['leave_type'])): ?>
                                    <span class="badge-talenta-izin" style="font-size: 0.7rem; padding: 3px 9px;">
                                        <i class="ti ti-file-medical"></i> <?= strtoupper($sc['leave_type']) ?>
                                    </span>
                                <?php elseif (!empty($sc['attendance_status'])): ?>
                                    <span class="badge-talenta-masuk" style="font-size: 0.7rem; padding: 3px 9px;">
                                        <i class="ti ti-circle-check-filled" style="color: #10b981;"></i>
                                        <span><?= ucfirst($sc['attendance_status']) ?></span>
                                        <?php if (!empty($sc['clock_in'])): ?>
                                            <span>(<?= substr($sc['clock_in'], 0, 5) ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                <?php elseif ($w['is_past'] && !$isOff): ?>
                                    <span style="font-size: 0.675rem; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="ti ti-alert-triangle" style="color: #ef4444;"></i> Alpha
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rekan Satu Shift di Hari Tersebut -->
                    <div class="roster-teammates-box" style="background: <?= $isToday ? '#f4f6fd' : '#f8fafc' ?>; border-color: <?= $isToday ? '#e0e7ff' : '#f1f5f9' ?>;">
                        <div style="font-size: 0.65rem; font-weight: 700; color: #64748b; display: flex; align-items: center; gap: 4px;">
                            <i class="ti ti-users" style="color: #6366f1;"></i>
                            <span>Rekan (<?= count($teammatesToday) ?>):</span>
                        </div>

                        <?php if (empty($teammatesToday)): ?>
                            <span style="font-size: 0.7rem; color: #94a3b8; font-style: italic;">
                                <?= $isOff ? 'Libur / Bebas Tugas' : 'Tidak ada rekan lain' ?>
                            </span>
                        <?php else: ?>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
                                <?php foreach (array_slice($teammatesToday, 0, 3) as $tm): ?>
                                    <span style="font-size: 0.675rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.03);" title="<?= htmlspecialchars($tm['position'] ?? '') ?>">
                                        <span style="width: 5px; height: 5px; border-radius: 50%; background: <?= !empty($tm['clock_in']) ? '#10b981' : '#cbd5e1' ?>;"></span>
                                        <?= htmlspecialchars(explode(' ', $tm['full_name'])[0]) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($teammatesToday) > 3): ?>
                                    <span style="font-size: 0.65rem; background: #e0e7ff; color: #4338ca; border-radius: 6px; padding: 2px 5px; font-weight: 700;">
                                        +<?= count($teammatesToday) - 3 ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
