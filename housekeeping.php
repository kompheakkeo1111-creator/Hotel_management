<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mark_clean') {
    $id = (int)$_POST['id'];
    try {
        $db->prepare("UPDATE rooms SET status='Available' WHERE id=?")->execute([$id]);
        $message = 'Room marked as cleaned and available.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$rooms = $db->query("SELECT r.*, rt.type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id=rt.id
                     WHERE r.status IN ('Cleaning','Maintenance') ORDER BY r.room_number")->fetchAll(PDO::FETCH_ASSOC);
$cleaningCount = 0; $maintenanceCount = 0;
foreach ($rooms as $r) { if ($r['status']=='Cleaning') $cleaningCount++; else $maintenanceCount++; }

$active = 'housekeeping';
$pageTitle = 'Housekeeping';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Housekeeping</h2><p class="page-subtitle mb-0"><?php echo $cleaningCount; ?> rooms to clean · <?php echo $maintenanceCount; ?> in maintenance</p></div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Room #</th><th>Type</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rooms as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['room_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($r['type_name'] ?? '—'); ?></td>
                <td><span class="badge bg-<?php echo $r['status']=='Cleaning'?'info':'secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td class="text-end">
                    <form method="POST" style="display:inline" onsubmit="return confirm('Mark room as cleaned and available?')">
                        <input type="hidden" name="action" value="mark_clean"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-sm btn-success">Mark Clean / Available</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rooms): ?><tr><td colspan="4" class="text-center text-muted py-4">No rooms need attention right now.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div>
<?php require 'includes/footer.php'; ?>
