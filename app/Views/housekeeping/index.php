<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Housekeeping</h2><p class="page-subtitle mb-0"><?php echo $cleaningCount; ?> rooms to clean · <?php echo $maintenanceCount; ?> in maintenance</p></div>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Room #</th><th>Type</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['room_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($r['type_name'] ?? '—'); ?></td>
                <td><span class="badge bg-<?php echo $r['status']==='Cleaning'?'info':'secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td class="text-end">
                    <form method="POST" style="display:inline" onsubmit="return confirm('Mark room as cleaned and available?')">
                        <input type="hidden" name="action" value="mark_clean">
                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-sm btn-success">Mark Clean / Available</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rooms): ?><tr><td colspan="4" class="text-center text-muted py-4">No rooms need attention right now.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div>
