<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$settings = getSystemSettings();
$tax_rate = (float)($settings['tax_rate'] ?? 0);

$message = '';
$error = '';
$validStatus = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

function roomIsAvailable($db, $room_id, $check_in, $check_out, $exclude_res_id = 0) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM reservations
        WHERE room_id=? AND status NOT IN ('Cancelled')
        AND id <> ? AND check_in_date < ? AND check_out_date > ?");
    $stmt->execute([$room_id, $exclude_res_id, $check_out, $check_in]);
    return (int)$stmt->fetchColumn() === 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        // ---------- CREATE ----------
        if ($action === 'add') {
            $guest_id = (int)($_POST['guest_id'] ?? 0);
            $room_id = (int)($_POST['room_id'] ?? 0);
            $check_in = $_POST['check_in_date'] ?? '';
            $check_out = $_POST['check_out_date'] ?? '';
            $num_guests = max(1, (int)($_POST['number_of_guests'] ?? 1));
            $status = in_array($_POST['status'] ?? '', $validStatus, true) ? $_POST['status'] : 'Pending';
            $requests = trim($_POST['special_requests'] ?? '');

            if (!$guest_id || !$room_id || !$check_in || !$check_out) throw new Exception('Guest, room, and dates are required.');
            if (strtotime($check_out) <= strtotime($check_in)) throw new Exception('Check-out must be after check-in.');
            if (!roomIsAvailable($db, $room_id, $check_in, $check_out)) throw new Exception('This room is already booked for the selected dates.');

            $reservation_number = generateReservationNumber();
            $sql = "INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, number_of_guests, status, special_requests, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$reservation_number, $guest_id, $room_id, $check_in, $check_out, $num_guests, $status, $requests, $_SESSION['user_id']]);
            $message = 'Reservation ' . $reservation_number . ' created.';
        }

        // ---------- EDIT ----------
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $check_in = $_POST['check_in_date'] ?? '';
            $check_out = $_POST['check_out_date'] ?? '';
            $num_guests = max(1, (int)($_POST['number_of_guests'] ?? 1));
            $status = in_array($_POST['status'] ?? '', $validStatus, true) ? $_POST['status'] : 'Pending';
            $requests = trim($_POST['special_requests'] ?? '');

            if (!$id || !$check_in || !$check_out) throw new Exception('Invalid data.');
            if (strtotime($check_out) <= strtotime($check_in)) throw new Exception('Check-out must be after check-in.');

            $stmt = $db->prepare("UPDATE reservations SET check_in_date=?, check_out_date=?, number_of_guests=?, status=?, special_requests=? WHERE id=?");
            $stmt->execute([$check_in, $check_out, $num_guests, $status, $requests, $id]);
            $message = 'Reservation updated.';
        }

        // ---------- CANCEL (frees room) ----------
        elseif ($action === 'cancel') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE reservations SET status='Cancelled' WHERE id=?")->execute([$id]);
            $free = $db->prepare("UPDATE rooms r JOIN reservations res ON res.room_id=r.id
                                  SET r.status='Available'
                                  WHERE res.id=? AND r.status='Reserved'");
            $free->execute([$id]);
            $message = 'Reservation cancelled and room released.';
        }

        // ---------- DELETE (only if not checked in) ----------
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $ci = $db->prepare("SELECT COUNT(*) FROM check_ins WHERE reservation_id=? AND status='Active'");
            $ci->execute([$id]);
            if ((int)$ci->fetchColumn() > 0) throw new Exception('Cannot delete an active stay.');
            $room_id = $db->prepare("SELECT room_id FROM reservations WHERE id=?");
            $room_id->execute([$id]);
            $rid = $room_id->fetchColumn();
            $db->prepare("DELETE FROM reservations WHERE id=?")->execute([$id]);
            if ($rid) {
                $still = $db->prepare("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status NOT IN ('Cancelled')");
                $still->execute([$rid]);
                if ((int)$still->fetchColumn() === 0) {
                    $db->prepare("UPDATE rooms SET status='Available' WHERE id=? AND status='Reserved'")->execute([$rid]);
                }
            }
            $message = 'Reservation deleted.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Filters
$f_status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($f_status !== '') { $where[] = 'r.status=?'; $params[] = $f_status; }
if ($q !== '') { $where[] = '(r.reservation_number LIKE ? OR g.full_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$reservations = $db->query("SELECT r.*, g.full_name guest_name, rm.room_number, rm.price_per_night, rt.type_name,
                            DATEDIFF(r.check_out_date, r.check_in_date) nights
                            FROM reservations r
                            JOIN guests g ON r.guest_id=g.id
                            LEFT JOIN rooms rm ON r.room_id=rm.id
                            LEFT JOIN room_types rt ON rm.room_type_id=rt.id
                            ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$guests = $db->query("SELECT id, full_name FROM guests ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $db->query("SELECT id, room_number, price_per_night FROM rooms ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);

$active = 'reservations';
$pageTitle = 'Reservations';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-1">Reservations</h2>
        <p class="page-subtitle mb-0"><?php echo count($reservations); ?> bookings</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReservationModal"><i class="bi bi-plus-circle"></i> Create Reservation</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<form method="GET" class="filter-form mb-4 p-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search name or reservation #" value="<?php echo htmlspecialchars($q); ?>"></div>
        <div class="col-md-3"><select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($validStatus as $s): ?><option value="<?php echo $s; ?>" <?php echo $f_status === $s ? 'selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-5"><button class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
        <a href="reservations.php" class="btn btn-secondary">Reset</a></div>
    </div>
</form>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr>
            <th>Reservation</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Guests</th><th>Nights</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($reservations as $res): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($res['reservation_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($res['guest_name']); ?></td>
                <td><?php echo htmlspecialchars($res['room_number'] ?? '—'); ?></td>
                <td><?php echo $res['check_in_date']; ?></td>
                <td><?php echo $res['check_out_date']; ?></td>
                <td><?php echo $res['number_of_guests']; ?></td>
                <td><?php echo $res['nights']; ?></td>
                <td><?php echo formatCurrency($res['total_amount'] ?? 0); ?></td>
                <td><span class="badge bg-<?php echo $res['status']=='Confirmed'?'success':($res['status']=='Pending'?'warning':'danger'); ?>"><?php echo $res['status']; ?></span></td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="viewReservation(<?php echo (int)$res['id']; ?>)"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="editReservation(<?php echo (int)$res['id']; ?>)"><i class="bi bi-pencil"></i></button>
                    <?php if ($res['status'] !== 'Cancelled' && $res['status'] !== 'Completed'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this reservation?')">
                        <input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?php echo (int)$res['id']; ?>">
                        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this reservation?')">
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$res['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reservations): ?><tr><td colspan="10" class="text-center text-muted py-4">No reservations found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div>

<!-- Add Reservation Modal -->
<div class="modal fade" id="addReservationModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Create Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Guest</label><select name="guest_id" class="form-select" required>
                        <option value="">Select Guest</option>
                        <?php foreach ($guests as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['full_name']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Room</label><select name="room_id" class="form-select" required id="add-room">
                        <option value="">Select Room (Available)</option>
                        <?php foreach ($rooms as $rm): ?><option value="<?php echo $rm['id']; ?>" data-price="<?php echo $rm['price_per_night']; ?>"><?php echo htmlspecialchars($rm['room_number']); ?> — <?php echo formatCurrency($rm['price_per_night']); ?> / night</option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Check-in</label><input type="date" name="check_in_date" class="form-control" required id="add-in"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Check-out</label><input type="date" name="check_out_date" class="form-control" required id="add-out"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Guests</label><input type="number" name="number_of_guests" class="form-control" value="1" min="1" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Status</label><select name="status" class="form-select">
                        <option value="Pending">Pending</option><option value="Confirmed">Confirmed</option>
                    </select></div>
                </div>
                <div class="mb-3"><label class="form-label">Estimated Total</label>
                    <div class="input-group"><span class="input-group-text"><?php echo $settings['currency'] ?? 'USD'; ?></span><input type="text" class="form-control" id="add-total" readonly value="0.00"></div>
                    <small class="text-muted">Auto-calculated: nights × room rate (+<?php echo $tax_rate; ?>% tax)</small>
                </div>
                <div class="mb-3"><label class="form-label">Special Requests</label><textarea name="special_requests" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create Reservation</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Reservation Modal -->
<div class="modal fade" id="editReservationModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit-id">
            <div class="modal-header"><h5 class="modal-title">Edit Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Guest</label><input type="text" class="form-control" id="edit-guest" readonly></div>
                <div class="mb-3"><label class="form-label">Room</label><input type="text" class="form-control" id="edit-room" readonly></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Check-in</label><input type="date" name="check_in_date" id="edit-in" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Check-out</label><input type="date" name="check_out_date" id="edit-out" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Guests</label><input type="number" name="number_of_guests" id="edit-guests" class="form-control" min="1" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Status</label><select name="status" id="edit-status" class="form-select">
                        <?php foreach ($validStatus as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="mb-3"><label class="form-label">Special Requests</label><textarea name="special_requests" id="edit-requests" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </div></div>
</div>

<!-- View Reservation Modal -->
<div class="modal fade" id="viewReservationModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Reservation Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="view-content"><div class="text-center text-muted py-3">Loading...</div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<script>
const resData = <?php echo json_encode($reservations); ?>;
const taxRate = <?php echo $tax_rate; ?>;
const currency = '<?php echo $settings['currency'] ?? 'USD'; ?>';

// Auto total in add modal
function calcAddTotal() {
    const room = document.getElementById('add-room').selectedOptions[0];
    const price = room && room.dataset.price ? parseFloat(room.dataset.price) : 0;
    const inD = new Date(document.getElementById('add-in').value);
    const outD = new Date(document.getElementById('add-out').value);
    if (!isNaN(inD) && !isNaN(outD) && outD > inD) {
        const nights = Math.round((outD - inD) / 86400000);
        const sub = nights * price;
        const total = sub + (sub * taxRate / 100);
        document.getElementById('add-total').value = total.toFixed(2);
    } else {
        document.getElementById('add-total').value = '0.00';
    }
}
['add-room','add-in','add-out'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', calcAddTotal);
});

function editReservation(id) {
    const r = resData.find(x => String(x.id) === String(id));
    if (!r) return;
    document.getElementById('edit-id').value = r.id;
    document.getElementById('edit-guest').value = r.guest_name;
    document.getElementById('edit-room').value = r.room_number || '—';
    document.getElementById('edit-in').value = r.check_in_date;
    document.getElementById('edit-out').value = r.check_out_date;
    document.getElementById('edit-guests').value = r.number_of_guests;
    document.getElementById('edit-status').value = r.status;
    document.getElementById('edit-requests').value = r.special_requests || '';
    new bootstrap.Modal(document.getElementById('editReservationModal')).show();
}

function viewReservation(id) {
    const r = resData.find(x => String(x.id) === String(id));
    if (!r) return;
    const rows = [
        ['Reservation', r.reservation_number],
        ['Guest', r.guest_name],
        ['Room', r.room_number || '—'],
        ['Room Type', r.type_name || '—'],
        ['Check-in', r.check_in_date],
        ['Check-out', r.check_out_date],
        ['Guests', r.number_of_guests],
        ['Nights', r.nights],
        ['Total', currency + ' ' + (r.total_amount ? Number(r.total_amount).toFixed(2) : '0.00')],
        ['Status', r.status],
        ['Special Requests', r.special_requests || '—']
    ];
    let html = '<div class="table-responsive"><table class="table table-sm">';
    rows.forEach(([k, v]) => { html += `<tr><th class="text-muted">${k}</th><td>${v}</td></tr>`; });
    html += '</table></div>';
    document.getElementById('view-content').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewReservationModal')).show();
}
</script>
<?php require 'includes/footer.php'; ?>
