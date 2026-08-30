<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $type_name = trim($_POST['type_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['price_per_night'] ?? 0);
            $capacity = (int)($_POST['capacity'] ?? 0);
            if ($type_name === '' || $capacity < 1) throw new Exception('Type name and valid capacity are required.');

            if ($action === 'add') {
                $db->prepare("INSERT INTO room_types (type_name, description, price_per_night, capacity) VALUES (?,?,?,?)")
                   ->execute([$type_name, $description, $price, $capacity]);
                $message = 'Room type added successfully.';
            } else {
                $id = (int)$_POST['id'];
                $db->prepare("UPDATE room_types SET type_name=?, description=?, price_per_night=?, capacity=? WHERE id=?")
                   ->execute([$type_name, $description, $price, $capacity, $id]);
                $message = 'Room type updated successfully.';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $db->prepare("DELETE FROM room_types WHERE id=?")->execute([$id]);
            $message = 'Room type deleted successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$room_types = $db->query("SELECT rt.*,
                          (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id=rt.id) room_count
                          FROM room_types rt ORDER BY rt.id")->fetchAll(PDO::FETCH_ASSOC);

$active = 'room_types';
$pageTitle = 'Room Types';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Room Types</h2><p class="page-subtitle mb-0"><?php echo count($room_types); ?> room categories</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeModal"><i class="bi bi-plus-circle"></i> Add Room Type</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>ID</th><th>Type Name</th><th>Description</th><th class="text-end">Price/Night</th><th class="text-center">Capacity</th><th class="text-center">Rooms</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($room_types as $t): ?>
            <tr>
                <td><?php echo $t['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($t['type_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                <td class="text-end"><?php echo formatCurrency($t['price_per_night']); ?></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo $t['capacity']; ?></span></td>
                <td class="text-center"><?php echo $t['room_count']; ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick="editType(<?php echo (int)$t['id']; ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this room type?')">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$room_types): ?><tr><td colspan="7" class="text-center text-muted py-4">No room types found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div>

<!-- Add Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Room Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Type Name</label><input type="text" name="type_name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Price per Night</label><input type="number" name="price_per_night" class="form-control" step="0.01" min="0" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" min="1" required></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add Room Type</button></div>
    </form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit-id">
        <div class="modal-header"><h5 class="modal-title">Edit Room Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Type Name</label><input type="text" name="type_name" id="edit-name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit-desc" class="form-control" rows="2"></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Price per Night</label><input type="number" name="price_per_night" id="edit-price" class="form-control" step="0.01" min="0" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Capacity</label><input type="number" name="capacity" id="edit-capacity" class="form-control" min="1" required></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
    </form>
</div></div></div>

<script>
const typeData = <?php echo json_encode($room_types); ?>;
function editType(id) {
    const t = typeData.find(x => String(x.id) === String(id));
    if (!t) return;
    document.getElementById('edit-id').value = t.id;
    document.getElementById('edit-name').value = t.type_name;
    document.getElementById('edit-desc').value = t.description || '';
    document.getElementById('edit-price').value = t.price_per_night;
    document.getElementById('edit-capacity').value = t.capacity;
    new bootstrap.Modal(document.getElementById('editTypeModal')).show();
}
</script>
<?php require 'includes/footer.php'; ?>
