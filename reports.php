<?php
require_once 'config.php';
requireLogin();

$db = getDB();

$totalRooms = (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupiedRooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE status='Occupied'")->fetchColumn();
$occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

$monthlyRevenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();

$yearlyRevenue = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();

// Current guests
$current_guests = (int)$db->query("SELECT COUNT(*) FROM check_ins WHERE status='Active'")->fetchColumn();

// ADR / RevPAR
$roomNights = (int)$db->query("SELECT COALESCE(SUM(DATEDIFF(r.check_out_date, r.check_in_date)),0)
                               FROM reservations r WHERE r.status IN ('Completed','Confirmed')
                               AND MONTH(r.check_in_date)=MONTH(CURDATE())")->fetchColumn();
$adr = ($roomNights > 0) ? round($monthlyRevenue / $roomNights, 2) : 0;
$revpar = ($totalRooms > 0) ? round($monthlyRevenue / ($totalRooms * 30), 2) : 0;

// Monthly revenue trend (last 6 months)
$months = [];
$mlabels = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $row = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m')=?");
    $row->execute([$m]);
    $months[] = (float)$row->fetchColumn();
    $mlabels[] = date('M', strtotime($m . '-01'));
}

$revenueByType = $db->query("SELECT rt.type_name, COALESCE(SUM(p.amount),0) revenue
                             FROM room_types rt
                             LEFT JOIN rooms r ON r.room_type_id=rt.id
                             LEFT JOIN reservations res ON res.room_id=r.id
                             LEFT JOIN payments p ON p.reservation_id=res.id
                             GROUP BY rt.id, rt.type_name")->fetchAll(PDO::FETCH_ASSOC);

$topGuests = $db->query("SELECT g.full_name, COUNT(res.id) stays, COALESCE(SUM(res.total_amount),0) spend
                         FROM guests g
                         JOIN reservations res ON res.guest_id=g.id
                         GROUP BY g.id, g.full_name ORDER BY stays DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$rev_by_type_labels = [];
$rev_by_type_data = [];
$type_rows = $revenueByType;
foreach ($type_rows as $t) { $rev_by_type_labels[] = $t['type_name']; $rev_by_type_data[] = (float)$t['revenue']; }

$active = 'reports';
$pageTitle = 'Reports';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Reports & Analytics</h2><p class="page-subtitle mb-0">Performance overview</p></div>
</div>

<div class="row">
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-success"><i class="bi bi-graph-up-arrow"></i></div><div><div class="number"><?php echo $occupancyRate; ?>%</div><div class="label">Occupancy Rate</div><small class="text-muted"><?php echo $occupiedRooms; ?>/<?php echo $totalRooms; ?> rooms</small></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-info"><i class="bi bi-people"></i></div><div><div class="number"><?php echo $current_guests; ?></div><div class="label">Guests In-house</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-primary"><i class="bi bi-speedometer"></i></div><div><div class="number"><?php echo formatCurrency($adr); ?></div><div class="label">ADR (this month)</div><small class="text-muted">RevPAR: <?php echo formatCurrency($revpar); ?></small></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-warning"><i class="bi bi-cash-coin"></i></div><div><div class="number"><?php echo formatCurrency($monthlyRevenue); ?></div><div class="label">Revenue This Month</div><small class="text-muted">Year: <?php echo formatCurrency($yearlyRevenue); ?></small></div></div></div>
</div>

<div class="row mt-1">
    <div class="col-lg-8"><div class="card"><div class="card-header">Monthly Revenue (Last 6 Months)</div><div class="card-body"><div class="chart-container"><canvas id="revChart"></canvas></div></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header">Revenue by Room Type</div><div class="card-body"><div class="chart-container"><canvas id="typeChart"></canvas></div></div></div></div>
</div>

<div class="row mt-1">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Revenue by Room Type</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Room Type</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($revenueByType as $rt): ?><tr><td><?php echo htmlspecialchars($rt['type_name']); ?></td><td class="text-end"><?php echo formatCurrency($rt['revenue']); ?></td></tr><?php endforeach; ?>
                    <?php if (!$revenueByType): ?><tr><td colspan="2" class="text-center text-muted py-3">No data yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Top Guests</div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Guest</th><th>Stays</th><th class="text-end">Spend</th></tr></thead>
                    <tbody>
                    <?php foreach ($topGuests as $g): ?><tr><td><?php echo htmlspecialchars($g['full_name']); ?></td><td><?php echo $g['stays']; ?></td><td class="text-end"><?php echo formatCurrency($g['spend']); ?></td></tr><?php endforeach; ?>
                    <?php if (!$topGuests): ?><tr><td colspan="3" class="text-center text-muted py-3">No data yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>

<div class="card mt-2"><div class="card-body text-center text-muted small">Total Revenue (all time): <strong><?php echo formatCurrency($totalRevenue); ?></strong></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
new Chart(document.getElementById('revChart'), {
    type: 'line',
    data: { labels: <?php echo json_encode($mlabels); ?>, datasets: [{ label: 'Revenue', data: <?php echo json_encode($months); ?>, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.15)', fill: true, tension: .3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode($rev_by_type_labels); ?>, datasets: [{ data: <?php echo json_encode($rev_by_type_data); ?>, backgroundColor: ['#2563eb','#198754','#eab308','#0891b2','#dc2626'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php require 'includes/footer.php'; ?>
