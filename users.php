<?php
require_once 'config.php';
requireRole(['Administrator']);

$db = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'Receptionist';
            if ($username === '' || $password === '') throw new Exception('Username and password are required.');
            $db->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?,?,?,?,?,?)")
               ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $email, $phone, $role]);
            $message = 'User added successfully.';
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'Receptionist';
            $db->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?")
               ->execute([$full_name, $email, $phone, $role, $id]);
            if (!empty($_POST['new_password'])) {
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $id]);
            }
            $message = 'User updated successfully.';
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id == $_SESSION['user_id']) throw new Exception('You cannot delete your own account.');
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            $message = 'User deleted successfully.';
        } elseif ($action === 'toggle_status') {
            $id = (int)$_POST['id'];
            $status = ($_POST['status'] ?? 'Active') == 'Active' ? 'Inactive' : 'Active';
            $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$status, $id]);
            $message = 'User status updated.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$active = 'users';
$pageTitle = 'User Management';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">User Management</h2><p class="page-subtitle mb-0"><?php echo count($users); ?> accounts</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-circle"></i> Add User</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                <td><span class="badge bg-info"><?php echo htmlspecialchars($u['role']); ?></span></td>
                <td><span class="badge bg-<?php echo $u['status']=='Active'?'success':'danger'; ?>"><?php echo htmlspecialchars($u['status']); ?></span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo (int)$u['id']; ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Toggle status?')">
                        <input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><input type="hidden" name="status" value="<?php echo htmlspecialchars($u['status']); ?>">
                        <button class="btn btn-sm btn-<?php echo $u['status']=='Active'?'warning':'success'; ?>" title="<?php echo $u['status']=='Active'?'Deactivate':'Activate'; ?>"><i class="bi bi-<?php echo $u['status']=='Active'?'pause':'play'; ?>"></i></button>
                    </form>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            </div>
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
            </div>
            <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select" required>
                <option>Receptionist</option><option>Manager</option><option>Housekeeping Staff</option><option>Accountant</option><option>Administrator</option>
            </select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add User</button></div>
    </form>
</div></div></div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit-id">
        <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Username</label><input type="text" id="edit-username" class="form-control" disabled></div>
            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" id="edit-name" class="form-control" required></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="edit-phone" class="form-control"></div>
            </div>
            <div class="mb-3"><label class="form-label">Role</label><select name="role" id="edit-role" class="form-select" required>
                <option>Receptionist</option><option>Manager</option><option>Housekeeping Staff</option><option>Accountant</option><option>Administrator</option>
            </select></div>
            <div class="mb-3"><label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label><input type="password" name="new_password" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
    </form>
</div></div></div>

<script>
const userData = <?php echo json_encode($users); ?>;
function editUser(id) {
    const u = userData.find(x => String(x.id) === String(id));
    if (!u) return;
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-username').value = u.username;
    document.getElementById('edit-name').value = u.full_name;
    document.getElementById('edit-email').value = u.email || '';
    document.getElementById('edit-phone').value = u.phone || '';
    document.getElementById('edit-role').value = u.role;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
<?php require 'includes/footer.php'; ?>
