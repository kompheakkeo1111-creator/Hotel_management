<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Reports & Analytics</h2><p class="page-subtitle mb-0">Performance overview</p></div>
</div>

<div class="row">
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-success"><i class="bi bi-graph-up-arrow"></i></div><div><div class="number"><?php echo $occupancyRate; ?>%</div><div class="label">Occupancy Rate</div><small class="text-muted"><?php echo $occupiedRooms; ?>/<?php echo $totalRooms; ?> rooms</small></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-info"><i class="bi bi-people"></i></div><div><div class="number"><?php echo $currentGuests; ?></div><div class="label">Guests In-house</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-primary"><i class="bi bi-speedometer"></i></div><div><div class="number"><?php echo formatCurrency($adr); ?></div><div class="label">ADR (this month)</div><small class="text-muted">RevPAR: <?php echo formatCurrency($revpar); ?></small></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-warning"><i class="bi bi-cash-coin"></i></div><div><div class="number"><?php echo formatCurrency($periodRevenue); ?></div><div class="label">Revenue (Selected Period)</div><small class="text-muted">All time: <?php echo formatCurrency($totalRevenue); ?></small></div></div></div>
</div>

<form method="GET" class="filter-form mb-4 p-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-3"><label class="form-label mb-0">From</label><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>"></div>
        <div class="col-md-3"><label class="form-label mb-0">To</label><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>"></div>
        <div class="col-md-6 d-flex gap-2 align-items-end">
            <button class="btn btn-primary"><i class="bi bi-filter"></i> Apply</button>
            <a href="index.php?r=reports/index" class="btn btn-secondary">Reset</a>
            <a href="index.php?r=reports/index&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv" class="btn btn-success"><i class="bi bi-download"></i> Export CSV</a>
        </div>
    </div>
</form>

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
    data: { labels: <?php echo json_encode($revByTypeLabels); ?>, datasets: [{ data: <?php echo json_encode($revByTypeData); ?>, backgroundColor: ['#2563eb','#198754','#eab308','#0891b2','#dc2626'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
