<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$message = '';
$error = '';

// ---------- Check in an existing reservation ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') === 'checkin') {
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT * FROM reservations WHERE id=? AND status IN ('Pending','Confirmed')");
        $stmt->execute([$reservation_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) throw new Exception('Reservation not found or not eligible for check-in.');
        if (!$res['room_id']) throw new Exception('Reservation has no room assigned.');

        $already = $db->prepare("SELECT COUNT(*) FROM check_ins WHERE reservation_id=? AND status='Active'");
        $already->execute([$reservation_id]);
        if ((int)$already->fetchColumn() > 0) throw new Exception('Guest is already checked in.');

        $db->prepare("INSERT INTO check_ins (reservation_id, guest_id, room_id, check_in_time, expected_check_out, status, created_by)
                      VALUES (?,?,?,NOW(),?, 'Active', ?)")
            ->execute([$res['id'], $res['guest_id'], $res['room_id'], $res['check_out_date'], $_SESSION['user_id']]);

        $db->prepare("UPDATE rooms SET status='Occupied' WHERE id=?")->execute([$res['room_id']]);
        $db->commit();
        $message = 'Guest checked in successfully.';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ---------- Walk-in check in (creates a reservation for a walk-in guest) ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') === 'walkin') {
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $check_in = $_POST['check_in_date'] ?? date('Y-m-d');
    $check_out = $_POST['check_out_date'] ?? '';
    $num_guests = max(1, (int)($_POST['number_of_guests'] ?? 1));

    try {
        if (!$guest_id || !$room_id || !$check_out) throw new Exception('Guest, room, and check-out date are required.');
        if (strtotime($check_out) <= strtotime($check_in)) throw new Exception('Check-out must be after check-in.');

        $overlap = $db->prepare("SELECT COUNT(*) FROM check_ins WHERE room_id=? AND status='Active'");
        $overlap->execute([$room_id]);
        if ((int)$overlap->fetchColumn() > 0) throw new Exception('This room is currently occupied.');

        $booked = $db->prepare("SELECT COUNT(*) FROM reservations
                                WHERE room_id=? AND status NOT IN ('Cancelled')
                                  AND check_in_date < ? AND check_out_date > ?");
        $booked->execute([$room_id, $check_out, $check_in]);
        if ((int)$booked->fetchColumn() > 0) throw new Exception('This room is already reserved for the selected dates.');

        $db->beginTransaction();
        $price = $db->prepare("SELECT price_per_night FROM rooms WHERE id=?");
        $price->execute([$room_id]);
        $rate = (float)$price->fetchColumn();
        $nights = max(1, (int)((strtotime($check_out) - strtotime($check_in)) / 86400));
        $total = round($nights * $rate, 2);

        $res_number = generateReservationNumber();
        $db->prepare("INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, number_of_guests, status, total_amount, created_by)
                      VALUES (?,?,?,?,?,?,'Confirmed',?,?)")
            ->execute([$res_number, $guest_id, $room_id, $check_in, $check_out, $num_guests, $total, $_SESSION['user_id']]);
        $reservation_id = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO check_ins (reservation_id, guest_id, room_id, check_in_time, expected_check_out, status, created_by)
                      VALUES (?,?,?,NOW(),?,'Active',?)")
            ->execute([$reservation_id, $guest_id, $room_id, $check_out, $_SESSION['user_id']]);
        $db->prepare("UPDATE rooms SET status='Occupied' WHERE id=?")->execute([$room_id]);

        $db->commit();
        $message = 'Walk-in guest checked in. Reservation: ' . $res_number;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// Reservations eligible for check-in (have room, not already active)
$ready = $db->query("SELECT r.id, r.reservation_number, g.full_name guest_name, rm.room_number, r.check_in_date, r.check_out_date, r.status
                     FROM reservations r
                     JOIN guests g ON r.guest_id=g.id
                     LEFT JOIN rooms rm ON r.room_id=rm.id
                     WHERE r.status IN ('Pending','Confirmed') AND r.room_id IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM check_ins c WHERE c.reservation_id=r.id AND c.status='Active')
                     ORDER BY r.check_in_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Current active stays
$stays = $db->query("SELECT c.id, g.full_name guest_name, rm.room_number, c.check_in_time, c.expected_check_out, r.reservation_number
                      FROM check_ins c
                      JOIN guests g ON c.guest_id=g.id
                      JOIN rooms rm ON c.room_id=rm.id
                      LEFT JOIN reservations r ON c.reservation_id=r.id
                      WHERE c.status='Active' ORDER BY c.check_in_time DESC")->fetchAll(PDO::FETCH_ASSOC);

$guests = $db->query("SELECT id, full_name FROM guests ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$avail_rooms = $db->query("SELECT id, room_number, price_per_night FROM rooms WHERE status IN ('Available','Cleaning') ORDER BY room_number")->fetchAll(PDO::FETCH_ASSOC);

$active = 'checkin';
$pageTitle = 'Check-in';
require 'includes/header.php';
// Note: $stays holds the checked-in guest list (see below in markup)
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Check-in</h2><p class="page-subtitle mb-0">Front desk arrivals</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#walkinModal"><i class="bi bi-person-plus"></i> Walk-in Check-in</button>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Reservations ready to check in -->
<div class="card">
    <div class="card-header"><i class="bi bi-calendar-check text-primary"></i> Reservations Ready for Check-in</div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Reservation</th><th>Guest</th><th>Room</th><th>Arrival</th><th>Departure</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ready as $r): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($r['reservation_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['guest_name']); ?></td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($r['room_number']); ?></span></td>
                    <td><?php echo $r['check_in_date']; ?></td>
                    <td><?php echo $r['check_out_date']; ?></td>
                    <td><span class="badge bg-info"><?php echo $r['status']; ?></span></td>
                    <td class="text-end">
                        <form method="POST" onsubmit="return confirm('Check in this guest?')">
                            <input type="hidden" name="action" value="checkin"><input type="hidden" name="reservation_id" value="<?php echo (int)$r['id']; ?>">
                            <button class="btn btn-success btn-sm"><i class="bi bi-box-arrow-in-right"></i> Check In</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ready): ?><tr><td colspan="7" class="text-center text-muted py-4">No reservations waiting for check-in.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<!-- Currently checked in -->
<div class="card mt-4">
    <div class="card-header"><i class="bi bi-people text-success"></i> Currently Checked In (<?php echo count($stays); ?>)</div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Guest</th><th>Room</th><th>Checked In</th><th>Expected Out</th></tr></thead>
            <tbody>
            <?php foreach ($stays as $c): ?>
                <tr><td><?php echo htmlspecialchars($c['guest_name']); ?></td><td><span class="badge bg-primary"><?php echo htmlspecialchars($c['room_number']); ?></span></td><td><?php echo $c['check_in_time']; ?></td><td><?php echo $c['expected_check_out']; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$stays): ?><tr><td colspan="4" class="text-center text-muted py-3">No guests currently checked in.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<!-- Walk-in Modal -->
<div class="modal fade" id="walkinModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="action" value="walkin">
            <div class="modal-header"><h5 class="modal-title">Walk-in Check-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Guest</label>
                    <select name="guest_id" class="form-select" required>
                        <option value="">Select Existing Guest</option>
                        <?php foreach ($guests as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['full_name']); ?></option><?php endforeach; ?>
                    </select>
                    <small class="text-muted">Need a new guest? <a href="guests.php">Add one in Guests</a> first.</small>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Room</label><select name="room_id" class="form-select" required>
                        <option value="">Available Rooms</option>
                        <?php foreach ($avail_rooms as $r): ?><option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_number']); ?> — <?php echo formatCurrency($r['price_per_night']); ?>/night</option><?php endforeach; ?>
                    </select></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Guests</label><input type="number" name="number_of_guests" class="form-control" value="1" min="1"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Arrival</label><input type="date" name="check_in_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Departure</label><input type="date" name="check_out_date" class="form-control" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-right"></i> Check In</button></div>
        </form>
    </div></div>
</div>
<?php require 'includes/footer.php'; ?>
