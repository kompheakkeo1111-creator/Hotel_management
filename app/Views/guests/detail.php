<div class="row mb-3">
    <div class="col-md-6">
        <h6>Contact</h6>
        <p class="mb-0"><strong><?php echo htmlspecialchars($guest['full_name']); ?></strong></p>
        <p class="mb-0">Phone: <?php echo htmlspecialchars($guest['phone']); ?></p>
        <p class="mb-0">Email: <?php echo htmlspecialchars($guest['email'] ?? 'N/A'); ?></p>
        <p class="mb-0">Address: <?php echo htmlspecialchars($guest['address'] ?? 'N/A'); ?></p>
    </div>
    <div class="col-md-6">
        <h6>Identity</h6>
        <p class="mb-0">Gender: <?php echo htmlspecialchars($guest['gender'] ?? 'N/A'); ?></p>
        <p class="mb-0">DOB: <?php echo $guest['date_of_birth'] ?? 'N/A'; ?></p>
        <p class="mb-0">Nationality: <?php echo htmlspecialchars($guest['nationality'] ?? 'N/A'); ?></p>
        <p class="mb-0">ID Number: <?php echo htmlspecialchars($guest['identification_number']); ?></p>
    </div>
</div>
<h6>Stay History</h6>
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Reservation</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($history): foreach ($history as $h): ?>
            <tr>
                <td><?php echo htmlspecialchars($h['reservation_number']); ?></td>
                <td><?php echo htmlspecialchars($h['room_number'] ?? '&mdash;'); ?></td>
                <td><?php echo $h['check_in_date']; ?></td>
                <td><?php echo $h['check_out_date']; ?></td>
                <td><span class="badge bg-<?php echo $h['status']=='Confirmed'?'success':($h['status']=='Pending'?'warning':'secondary'); ?>"><?php echo $h['status']; ?></span></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-muted">No stay history.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
