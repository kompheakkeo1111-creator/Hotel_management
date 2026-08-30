<?php
require_once 'config.php';
requireLogin();

$db = getDB();

$total_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$available_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
$occupied_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Occupied'")->fetchColumn();
$reserved_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Reserved'")->fetchColumn();
$cleaning_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Cleaning'")->fetchColumn();
$maintenance_rooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Maintenance'")->fetchColumn();

$occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0;

$current_guests = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE status='Active'")->fetchColumn();
$today_checkins = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE DATE(check_in_time)=CURDATE()")->fetchColumn();
$today_checkouts = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE DATE(actual_check_out)=CURDATE()")->fetchColumn();

$today_arrivals = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE check_in_date=CURDATE() AND status IN ('Pending','Confirmed')")->fetchColumn();

$today_revenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(payment_date)=CURDATE()")->fetchColumn();
$monthly_revenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();
$total_revenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();

// Last 7 days revenue
$revenue_days = [];
$revenue_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $row = $db->prepare("SELECT COALESCE(SUM(amount),0) v FROM payments WHERE DATE(payment_date)=?");
    $row->execute([$d]);
    $revenue_days[] = (float)$row->fetchColumn();
    $revenue_labels[] = date('M d', strtotime($d));
}

// Revenue by room type (via reservations -> rooms -> room_types)
$rev_by_type_labels = [];
$rev_by_type_data = [];
foreach ($db->query("SELECT rt.type_name, COALESCE(SUM(p.amount),0) revenue
                     FROM room_types rt
                     LEFT JOIN rooms r ON r.room_type_id=rt.id
                     LEFT JOIN reservations res ON res.room_id=r.id
                     LEFT JOIN payments p ON p.reservation_id=res.id
                     GROUP BY rt.id, rt.type_name ORDER BY revenue DESC") as $t) {
    $rev_by_type_labels[] = $t['type_name'];
    $rev_by_type_data[] = (float)$t['revenue'];
}

// Today arrivals list
$arrivals = $db->query("SELECT r.reservation_number, g.full_name, rm.room_number
                        FROM reservations r
                        JOIN guests g ON r.guest_id=g.id
                        LEFT JOIN rooms rm ON r.room_id=rm.id
                        WHERE r.check_in_date=CURDATE() AND r.status IN ('Pending','Confirmed')
                        ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);

// Due/overdue departures
$departures = $db->query("SELECT g.full_name, rm.room_number, c.expected_check_out
                          FROM check_ins c
                          JOIN guests g ON c.guest_id=g.id
                          LEFT JOIN rooms rm ON c.room_id=rm.id
                          WHERE c.status='Active' AND c.expected_check_out <= CURDATE()
                          ORDER BY c.expected_check_out")->fetchAll(PDO::FETCH_ASSOC);

// Recent reservations
$recent_reservations = $db->query("SELECT r.*, g.full_name guest_name, rm.room_number
                                   FROM reservations r
                                   JOIN guests g ON r.guest_id=g.id
                                   LEFT JOIN rooms rm ON r.room_id=rm.id
                                   ORDER BY r.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Room status board with current occupant
$rooms = $db->query("SELECT r.id, r.room_number, r.status, rt.type_name,
                            (SELECT g.full_name FROM check_ins c JOIN guests g ON g.id=c.guest_id
                              WHERE c.room_id=r.id AND c.status='Active' LIMIT 1) occupant
                     FROM rooms r
                     LEFT JOIN room_types rt ON r.room_type_id=rt.id
                     ORDER BY r.room_number")->fetchAll(PDO::FETCH_ASSOC);

$active = 'dashboard';
$pageTitle = 'Dashboard';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-1">Dashboard</h2>
        <p class="page-subtitle mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></p>
    </div>
    <span class="text-muted"><?php echo date('l, F j, Y'); ?></span>
</div>

<div class="row">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-primary"><i class="bi bi-buildings"></i></div>
            <div><div class="number"><?php echo $total_rooms; ?></div><div class="label">Total Rooms</div><small class="text-muted"><?php echo $available_rooms; ?> available</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-success"><i class="bi bi-graph-up-arrow"></i></div>
            <div><div class="number"><?php echo $occupancy_rate; ?>%</div><div class="label">Occupancy</div><small class="text-muted"><?php echo $occupied_rooms; ?> occupied now</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-warning"><i class="bi bi-box-arrow-in-right"></i></div>
            <div><div class="number"><?php echo $today_checkins; ?></div><div class="label">Check-ins Today</div><small class="text-muted"><?php echo $today_checkouts; ?> check-outs</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-info"><i class="bi bi-cash-stack"></i></div>
            <div><div class="number"><?php echo formatCurrency($today_revenue); ?></div><div class="label">Revenue Today</div><small class="text-muted">Month: <?php echo formatCurrency($monthly_revenue); ?></small></div>
        </div>
    </div>
</div>

<div class="row mt-1">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Revenue - Last 7 Days</div>
            <div class="card-body"><div class="chart-container"><canvas id="revenueChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Revenue by Room Type</div>
            <div class="card-body"><div class="chart-container"><canvas id="typeChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row mt-1">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-box-arrow-in-right text-success"></i> Today's Arrivals</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Guest</th><th>Room</th><th>Reservation</th></tr></thead>
                    <tbody>
                    <?php foreach ($arrivals as $a): ?>
                        <tr><td><?php echo htmlspecialchars($a['full_name']); ?></td><td><?php echo htmlspecialchars($a['room_number'] ?? '—'); ?></td><td><span class="badge bg-info"><?php echo htmlspecialchars($a['reservation_number']); ?></span></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$arrivals): ?><tr><td colspan="3" class="text-center text-muted py-3">No arrivals today.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-box-arrow-right text-danger"></i> Due / Overdue Departures</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Guest</th><th>Room</th><th>Check-out</th></tr></thead>
                    <tbody>
                    <?php foreach ($departures as $d): ?>
                        <tr><td><?php echo htmlspecialchars($d['full_name']); ?></td><td><?php echo htmlspecialchars($d['room_number']); ?></td><td><span class="badge bg-danger"><?php echo $d['expected_check_out']; ?></span></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$departures): ?><tr><td colspan="3" class="text-center text-muted py-3">No departures due.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><i class="bi bi-door-open"></i> Room Status Board</div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach ($rooms as $room): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <button class="room-cell room-st-<?php echo strtolower($room['status']); ?>" onclick="location.href='rooms.php'">
                    <div><?php echo htmlspecialchars($room['room_number']); ?></div>
                    <small><?php echo htmlspecialchars($room['occupant'] ?? $room['type_name']); ?></small>
                    <small style="text-transform:uppercase"><?php echo $room['status']; ?></small>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><i class="bi bi-calendar-check"></i> Recent Reservations</div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Reservation</th><th>Guest</th><th>Room</th><th>Dates</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent_reservations as $res): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($res['reservation_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($res['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($res['room_number'] ?? '—'); ?></td>
                    <td><?php echo $res['check_in_date']; ?> → <?php echo $res['check_out_date']; ?></td>
                    <td><span class="badge bg-<?php echo $res['status']=='Confirmed'?'success':($res['status']=='Pending'?'warning':($res['status']=='Completed'?'info':'danger')); ?>"><?php echo $res['status']; ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent_reservations): ?><tr><td colspan="5" class="text-center text-muted py-3">No reservations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels: <?php echo json_encode($revenue_labels); ?>, datasets: [{ label: 'Revenue', data: <?php echo json_encode($revenue_days); ?>, backgroundColor: 'rgba(25,135,84,.7)', borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode($rev_by_type_labels); ?>, datasets: [{ data: <?php echo json_encode($rev_by_type_data); ?>, backgroundColor: ['#2563eb','#198754','#eab308','#0891b2','#dc2626'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php require 'includes/footer.php'; ?>
