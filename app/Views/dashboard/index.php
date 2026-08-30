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
            <div><div class="number"><?php echo $totalRooms; ?></div><div class="label">Total Rooms</div><small class="text-muted"><?php echo $availableRooms; ?> available</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-success"><i class="bi bi-graph-up-arrow"></i></div>
            <div><div class="number"><?php echo $occupancyRate; ?>%</div><div class="label">Occupancy</div><small class="text-muted"><?php echo $occupiedRooms; ?> occupied now</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-warning"><i class="bi bi-box-arrow-in-right"></i></div>
            <div><div class="number"><?php echo $todayCheckins; ?></div><div class="label">Check-ins Today</div><small class="text-muted"><?php echo $todayCheckouts; ?> check-outs</small></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="icon icon-info"><i class="bi bi-cash-stack"></i></div>
            <div><div class="number"><?php echo formatCurrency($todayRevenue); ?></div><div class="label">Revenue Today</div><small class="text-muted">Month: <?php echo formatCurrency($monthlyRevenue); ?></small></div>
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
                <button class="room-cell room-st-<?php echo strtolower($room['status']); ?>" onclick="location.href='index.php?r=rooms/index'">
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
            <?php foreach ($recentReservations as $res): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($res['reservation_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($res['guest_name']); ?></td>
                    <td><?php echo htmlspecialchars($res['room_number'] ?? '—'); ?></td>
                    <td><?php echo $res['check_in_date']; ?> → <?php echo $res['check_out_date']; ?></td>
                    <td><span class="badge bg-<?php echo $res['status']=='Confirmed'?'success':($res['status']=='Pending'?'warning':($res['status']=='Completed'?'info':'danger')); ?>"><?php echo $res['status']; ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentReservations): ?><tr><td colspan="5" class="text-center text-muted py-3">No reservations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels: <?php echo json_encode($revenueLabels); ?>, datasets: [{ label: 'Revenue', data: <?php echo json_encode($revenueDays); ?>, backgroundColor: 'rgba(25,135,84,.7)', borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode($revByTypeLabels); ?>, datasets: [{ data: <?php echo json_encode($revByTypeData); ?>, backgroundColor: ['#2563eb','#198754','#eab308','#0891b2','#dc2626'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
