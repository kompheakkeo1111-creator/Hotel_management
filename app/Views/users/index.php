<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-1">User Management</h2>
        <p class="page-subtitle mb-0"><?php echo count($users); ?> users</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> Add User</button>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?php echo (int)$u['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['phone'] ?: '—'); ?></td>
                    <td><span class="status-badge bg-<?php echo $u['role']==='Administrator'?'primary':($u['role']==='Manager'?'dark':'secondary'); ?> text-white"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td><span class="status-badge bg-<?php echo $u['status']==='Active'?'success':'danger'; ?> text-white"><?php echo $u['status']; ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo (int)$u['id']; ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="4"></div>
                </div>
                <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Role</label><select name="role" class="form-select">
                        <?php foreach ($roles as $r): ?><option value="<?php echo $r; ?>"><?php echo $r; ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Status</label><select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add User</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Username *</label><input type="text" name="username" id="edit-username" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">New Password</label><input type="password" name="password" id="edit-password" class="form-control" autocomplete="new-password"></div>
                </div>
                <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" name="full_name" id="edit-full_name" class="form-control" required></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Email *</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="edit-phone" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Role</label><select name="role" id="edit-role" class="form-select">
                        <?php foreach ($roles as $r): ?><option value="<?php echo $r; ?>"><?php echo $r; ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Status</label><select name="status" id="edit-status" class="form-select">
                        <?php foreach ($statuses as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </div></div>
</div>

<script>
const userData = <?php echo json_encode($users); ?>;
const roles = <?php echo json_encode($roles); ?>;
function editUser(id) {
    const u = userData.find(x => String(x.id) === String(id));
    if (!u) return;
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-username').value = u.username;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-full_name').value = u.full_name;
    document.getElementById('edit-email').value = u.email;
    document.getElementById('edit-phone').value = u.phone || '';
    document.getElementById('edit-role').value = u.role;
    document.getElementById('edit-status').value = u.status;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
