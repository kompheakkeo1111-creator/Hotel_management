<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-1">Guest Management</h2>
        <p class="page-subtitle mb-0"><?php echo count($guests); ?> guests</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuestModal"><i class="bi bi-plus-circle"></i> Add Guest</button>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Phone</th><th>Email</th><th>Stays</th><th>Active Stay</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($guests as $g): ?>
                <tr>
                    <td>#<?php echo (int)$g['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($g['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($g['gender']); ?></td>
                    <td><?php echo htmlspecialchars($g['phone']); ?></td>
                    <td><?php echo htmlspecialchars($g['email'] ?: '—'); ?></td>
                    <td><?php echo (int)$g['stays']; ?></td>
                    <td>
                        <?php if ((int)$g['active_stay'] > 0): ?><span class="status-badge bg-success text-white">Checked In</span>
                        <?php else: ?><span class="status-badge bg-secondary">—</span><?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" onclick="viewGuest(<?php echo (int)$g['id']; ?>)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="editGuest(<?php echo (int)$g['id']; ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this guest?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$guests): ?><tr><td colspan="8" class="text-center text-muted py-4">No guests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="addGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Guest</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Gender</label><select name="gender" class="form-select">
                        <?php foreach ($validGender as $g): ?><option value="<?php echo $g; ?>"><?php echo $g; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">ID Number *</label><input type="text" name="identification_number" class="form-control" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add Guest</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header"><h5 class="modal-title">Edit Guest</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" id="edit-full_name" class="form-control" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Gender</label><select name="gender" id="edit-gender" class="form-select">
                        <?php foreach ($validGender as $g): ?><option value="<?php echo $g; ?>"><?php echo $g; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="edit-date_of_birth" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Nationality</label><input type="text" name="nationality" id="edit-nationality" class="form-control"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Phone *</label><input type="text" name="phone" id="edit-phone" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">ID Number *</label><input type="text" name="identification_number" id="edit-identification_number" class="form-control" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="edit-address" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="viewGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="view-name">Guest Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="view-body">Loading...</div>
    </div></div>
</div>

<script>
const guestData = <?php echo json_encode($guests); ?>;

function viewGuest(id) {
    fetch('index.php?r=guests/view&id=' + id)
        .then(r => r.text())
        .then(html => {
            document.getElementById('view-body').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewGuestModal')).show();
        });
}

function editGuest(id) {
    const g = guestData.find(x => String(x.id) === String(id));
    if (!g) return;
    document.getElementById('edit-id').value = g.id;
    document.getElementById('edit-full_name').value = g.full_name;
    document.getElementById('edit-gender').value = g.gender;
    document.getElementById('edit-date_of_birth').value = g.date_of_birth || '';
    document.getElementById('edit-nationality').value = g.nationality || '';
    document.getElementById('edit-phone').value = g.phone;
    document.getElementById('edit-email').value = g.email || '';
    document.getElementById('edit-identification_number').value = g.identification_number || '';
    document.getElementById('edit-address').value = g.address || '';
    new bootstrap.Modal(document.getElementById('editGuestModal')).show();
}
</script>
