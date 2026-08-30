<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Notifications</h2><p class="page-subtitle mb-0">Hotel activities that need attention</p></div>
    <span class="badge bg-success fs-6">Today Revenue: <?php echo formatCurrency($todayRevenue); ?></span>
</div>

<?php if (!$notifications): ?>
    <div class="card p-5 text-center">
        <i class="bi bi-check-circle text-success" style="font-size:50px"></i>
        <h4 class="mt-3">All Clear</h4>
        <p class="text-muted mb-0">There are no urgent notifications right now.</p>
    </div>
<?php else: ?>
    <div class="notification-list">
    <?php foreach ($notifications as $n): ?>
        <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-white rounded border-start border-4 border-<?php echo $n['type']=='danger'?'danger':($n['type']=='warning'?'warning':'success'); ?> shadow-sm">
            <div class="n-icon"><i class="bi <?php echo $n['icon']; ?>"></i></div>
            <div class="flex-grow-1">
                <h6 class="mb-1"><?php echo $n['title']; ?></h6>
                <div class="text-muted small"><?php echo $n['text']; ?></div>
            </div>
            <a class="btn btn-outline-dark btn-sm" href="<?php echo $n['link']; ?>">View</a>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
