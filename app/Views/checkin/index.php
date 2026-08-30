<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Check-in</h2><p class="page-subtitle mb-0">Front desk arrivals</p></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#walkinModal"><i class="bi bi-person-plus"></i> Walk-in Check-in</button>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
                            <input type="hidden" name="action" value="checkin">
                            <input type="hidden" name="reservation_id" value="<?php echo (int)$r['id']; ?>">
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
                    <small class="text-muted">Need a new guest? <a href="index.php?r=guests/index">Add one in Guests</a> first.</small>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Room</label><select name="room_id" class="form-select" required>
                        <option value="">Available Rooms</option>
                        <?php foreach ($availRooms as $r): ?><option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['room_number']); ?> — <?php echo formatCurrency($r['price_per_night']); ?>/night</option><?php endforeach; ?>
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
