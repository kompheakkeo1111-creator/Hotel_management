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
            $full_name = trim($_POST['full_name'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $dob = $_POST['date_of_birth'] ?: null;
            $nationality = trim($_POST['nationality'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $id_number = trim($_POST['identification_number'] ?? '');

            if ($full_name === '' || $phone === '' || $id_number === '') throw new Exception('Full name, phone, and ID number are required.');
            if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = 'Male';

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO guests (full_name, gender, date_of_birth, nationality, phone, email, address, identification_number) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$full_name, $gender, $dob, $nationality, $phone, $email, $address, $id_number]);
                $message = 'Guest added successfully.';
            } else {
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE guests SET full_name=?, gender=?, date_of_birth=?, nationality=?, phone=?, email=?, address=?, identification_number=? WHERE id=?");
                $stmt->execute([$full_name, $gender, $dob, $nationality, $phone, $email, $address, $id_number, $id]);
                $message = 'Guest updated successfully.';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $has = $db->prepare("SELECT COUNT(*) FROM reservations WHERE guest_id=?");
            $has->execute([$id]);
            if ((int)$has->fetchColumn() > 0) throw new Exception('Guest has reservations and cannot be deleted.');
            $db->prepare("DELETE FROM guests WHERE id=?")->execute([$id]);
            $message = 'Guest deleted successfully.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$guests = $db->query("SELECT g.*,
                      (SELECT COUNT(*) FROM reservations r WHERE r.guest_id=g.id AND r.status != 'Cancelled') stays,
                      (SELECT COUNT(*) FROM check_ins c WHERE c.guest_id=g.id AND c.status='Active') active_stay
                      FROM guests g ORDER BY g.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$active = 'guests';
$pageTitle = 'Guests';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Guest Management</h2><p class="page-subtitle mb-0"><?php echo count($guests); ?> guests</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuestModal"><i class="bi bi-person-plus"></i> Add Guest</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr>
            <th>Name</th><th>Gender</th><th>Phone</th><th>Email</th><th>Nationality</th><th>ID Number</th><th>Stays</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($guests as $guest): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($guest['full_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($guest['gender'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($guest['phone']); ?></td>
                <td><?php echo htmlspecialchars($guest['email'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($guest['nationality'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($guest['identification_number']); ?></td>
                <td><?php echo $guest['stays']; ?></td>
                <td><?php echo $guest['active_stay'] > 0 ? '<span class="badge bg-success">In-house</span>' : '<span class="badge bg-secondary">Not staying</span>'; ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="viewGuest(<?php echo (int)$guest['id']; ?>)"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="editGuest(<?php echo (int)$guest['id']; ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this guest?')">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$guest['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$guests): ?><tr><td colspan="9" class="text-center text-muted py-4">No guests found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div>

<!-- Add Guest Modal -->
<div class="modal fade" id="addGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Guest</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Identification Number (Passport/ID)</label><input type="text" name="identification_number" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add Guest</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Guest Modal -->
<div class="modal fade" id="editGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit-id">
            <div class="modal-header"><h5 class="modal-title">Edit Guest</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" id="edit-name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Gender</label><select name="gender" id="edit-gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="edit-dob" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Nationality</label><input type="text" name="nationality" id="edit-nationality" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="edit-phone" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="edit-address" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Identification Number</label><input type="text" name="identification_number" id="edit-idnum" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </div></div>
</div>

<!-- View Guest Modal -->
<div class="modal fade" id="viewGuestModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Guest Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="view-content"><div class="text-center text-muted py-3">Loading...</div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<script>
const guestData = <?php echo json_encode($guests); ?>;

function editGuest(id) {
    const g = guestData.find(x => String(x.id) === String(id));
    if (!g) return;
    document.getElementById('edit-id').value = g.id;
    document.getElementById('edit-name').value = g.full_name;
    document.getElementById('edit-gender').value = g.gender || 'Male';
    document.getElementById('edit-dob').value = g.date_of_birth || '';
    document.getElementById('edit-nationality').value = g.nationality || '';
    document.getElementById('edit-phone').value = g.phone;
    document.getElementById('edit-email').value = g.email || '';
    document.getElementById('edit-address').value = g.address || '';
    document.getElementById('edit-idnum').value = g.identification_number;
    new bootstrap.Modal(document.getElementById('editGuestModal')).show();
}

function viewGuest(id) {
    const g = guestData.find(x => String(x.id) === String(id));
    if (!g) return;
    fetch('get_guest.php?id=' + id)
        .then(r => r.text())
        .then(html => { document.getElementById('view-content').innerHTML = html; })
        .catch(() => { document.getElementById('view-content').innerHTML = '<div class="alert alert-danger">Error loading guest.</div>'; });
    new bootstrap.Modal(document.getElementById('viewGuestModal')).show();
}
</script>
<?php require 'includes/footer.php'; ?>
