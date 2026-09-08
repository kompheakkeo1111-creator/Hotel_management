<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-1">Rooms Management</h2>
        <p class="page-subtitle mb-0"><?php echo count($rooms); ?> rooms</p>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group" id="viewToggle">
            <button type="button" class="btn btn-outline-secondary btn-view" data-view="table"><i class="bi bi-list-ul"></i> List</button>
            <button type="button" class="btn btn-outline-secondary btn-view" data-view="board"><i class="bi bi-grid"></i> Board</button>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal"><i class="bi bi-plus-circle"></i> Add Room</button>
    </div>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="GET" class="filter-form mb-4 p-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-3"><select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($validStatus as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><select name="floor" class="form-select">
            <option value="">All Floors</option>
            <?php foreach ($floors as $fl): ?><option value="<?php echo $fl; ?>" <?php echo $filters['floor'] == $fl ? 'selected' : ''; ?>>Floor <?php echo $fl; ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><select name="type" class="form-select">
            <option value="">All Types</option>
            <?php foreach ($roomTypes as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo $filters['type'] == $t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['type_name']); ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-4">
            <button class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
            <a href="index.php?r=rooms/index" class="btn btn-secondary">Reset</a>
        </div>
    </div>
</form>

<div id="view-table">
<div class="card">
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Room</th><th>Type</th><th>Floor</th><th>Capacity</th><th>Price/Night</th><th>Status</th><th>Occupant</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($room['type_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($room['floor'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($room['capacity'] ?? '—'); ?></td>
                    <td><?php echo formatCurrency($room['price_per_night']); ?></td>
                    <td><span class="status-badge bg-<?php
                        echo $room['status']=='Available'?'success text-white':($room['status']=='Reserved'?'warning text-white':($room['status']=='Cleaning'?'info text-white':($room['status']=='Maintenance'?'dark text-white':'danger text-white')));
                    ?>"><?php echo $room['status']; ?></span></td>
                    <td><?php echo htmlspecialchars($room['occupant'] ?? '—'); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editRoom(<?php echo (int)$room['id']; ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this room?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$room['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rooms): ?><tr><td colspan="8" class="text-center text-muted py-4">No rooms found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
</div>

<div id="view-board" style="display:none">
<div class="row g-2">
    <?php foreach ($rooms as $room): ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <button class="room-cell room-st-<?php echo strtolower($room['status']); ?>" onclick="editRoom(<?php echo (int)$room['id']; ?>)">
                <div><?php echo htmlspecialchars($room['room_number']); ?></div>
                <small><?php echo htmlspecialchars($room['occupant'] ?? $room['type_name']); ?></small>
                <small style="text-transform:uppercase"><?php echo $room['status']; ?></small>
            </button>
        </div>
    <?php endforeach; ?>
</div>
</div>

<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Add Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="mb-3"><label class="form-label">Room Number</label><input type="text" name="room_number" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Room Type</label><select name="room_type_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($roomTypes as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['type_name']); ?></option><?php endforeach; ?>
                </select></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Floor</label><input type="number" name="floor" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">Price / Night</label><input type="number" name="price_per_night" class="form-control" step="0.01" value="0"></div>
                <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select">
                    <?php foreach ($validStatus as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                </select></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add Room</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header"><h5 class="modal-title">Edit Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Room Number</label><input type="text" name="room_number" id="edit-room_number" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Room Type</label><select name="room_type_id" id="edit-room_type_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($roomTypes as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['type_name']); ?></option><?php endforeach; ?>
                </select></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Floor</label><input type="number" name="floor" id="edit-floor" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Capacity</label><input type="number" name="capacity" id="edit-capacity" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">Price / Night</label><input type="number" name="price_per_night" id="edit-price" class="form-control" step="0.01"></div>
                <div class="mb-3"><label class="form-label">Status</label><select name="status" id="edit-status" class="form-select">
                    <?php foreach ($validStatus as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                </select></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit-description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </div></div>
</div>

<script>
const roomData = <?php echo json_encode($rooms); ?>;

function editRoom(id) {
    const r = roomData.find(x => String(x.id) === String(id));
    if (!r) return;
    document.getElementById('edit-id').value = r.id;
    document.getElementById('edit-room_number').value = r.room_number;
    document.getElementById('edit-room_type_id').value = r.room_type_id || '';
    document.getElementById('edit-floor').value = r.floor ?? '';
    document.getElementById('edit-capacity').value = r.capacity ?? '';
    document.getElementById('edit-price').value = r.price_per_night ?? '';
    document.getElementById('edit-status').value = r.status;
    document.getElementById('edit-description').value = r.description || '';
    new bootstrap.Modal(document.getElementById('editRoomModal')).show();
}

document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', () => {
        const view = btn.dataset.view;
        document.getElementById('view-table').style.display = view === 'table' ? '' : 'none';
        document.getElementById('view-board').style.display = view === 'board' ? '' : 'none';
        document.querySelectorAll('.btn-view').forEach(b => b.classList.toggle('active', b === btn));
    });
});
</script>
