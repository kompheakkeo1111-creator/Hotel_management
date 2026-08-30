<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) { echo '<div class="alert alert-danger">Invalid payment ID</div>'; exit; }

$stmt = $db->prepare("
    SELECT p.*,
           g.full_name, g.phone, g.email,
           rm.room_number, rm.floor,
           rt.type_name AS room_type,
           ci.check_in_time,
           r.reservation_number, r.check_out_date
    FROM payments p
    JOIN check_ins ci ON p.check_in_id = ci.id
    JOIN guests g ON ci.guest_id = g.id
    JOIN rooms rm ON ci.room_id = rm.id
    LEFT JOIN room_types rt ON rm.room_type_id = rt.id
    LEFT JOIN reservations r ON p.reservation_id = r.id
    WHERE p.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) { echo '<div class="alert alert-danger">Payment record not found</div>'; exit; }

$settings = getSystemSettings();
$tax_rate = (float)($settings['tax_rate'] ?? 0);

// Extra charges attached to the check-in
$chars = $db->prepare("SELECT * FROM extra_charges WHERE check_in_id=?");
$chars->execute([$payment['check_in_id']]);
$extra_charges = $chars->fetchAll(PDO::FETCH_ASSOC);
$extra_total = 0;
foreach ($extra_charges as $c) $extra_total += (float)$c['amount'];

// Nights, room charge and tax breakdown from the live stay data
$bill = computeCheckinBill($db, $payment['check_in_id'], $tax_rate);
$nights = $bill['nights'] ?? 0;
$room_charge = $bill['room_charge'] ?? 0;
$tax_amount = $bill['tax'] ?? 0;

$method_badge = $payment['payment_method'] == 'Cash' ? 'success' : ($payment['payment_method'] == 'Credit/Debit Card' ? 'primary' : 'warning');
?>
<div class="receipt-container p-3">
    <div class="text-center border-bottom pb-3 mb-3">
        <h3><?php echo htmlspecialchars($settings['hotel_name'] ?? 'My Hotel'); ?></h3>
        <h5>Payment Receipt</h5>
        <p class="mb-0"><strong>Invoice #:</strong> <?php echo htmlspecialchars($payment['invoice_number']); ?></p>
        <p class="text-muted small">Date: <?php echo date('Y-m-d H:i:s', strtotime($payment['payment_date'])); ?></p>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Guest Information</h6>
            <p class="mb-0"><strong><?php echo htmlspecialchars($payment['full_name']); ?></strong></p>
            <p class="mb-0">Phone: <?php echo htmlspecialchars($payment['phone'] ?? 'N/A'); ?></p>
            <p class="mb-0">Email: <?php echo htmlspecialchars($payment['email'] ?? 'N/A'); ?></p>
        </div>
        <div class="col-md-6">
            <h6>Reservation Details</h6>
            <p class="mb-0">Reservation: <?php echo htmlspecialchars($payment['reservation_number'] ?? 'WALK-IN'); ?></p>
            <p class="mb-0">Room: <?php echo htmlspecialchars($payment['room_number'] . ' (Floor ' . $payment['floor'] . ')'); ?></p>
            <p class="mb-0">Room Type: <?php echo htmlspecialchars($payment['room_type'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <h6>Stay Period</h6>
            <p class="mb-0">Check-in: <?php echo $payment['check_in_time']; ?></p>
            <p class="mb-0">Check-out: <?php echo $payment['check_out_date']; ?></p>
        </div>
        <div class="col-md-6">
            <h6>Payment Details</h6>
            <p class="mb-0">Method: <span class="badge bg-<?php echo $method_badge; ?>"><?php echo htmlspecialchars($payment['payment_method']); ?></span></p>
            <?php if ($payment['transaction_id']): ?><p class="mb-0">Transaction ID: <?php echo htmlspecialchars($payment['transaction_id']); ?></p><?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light"><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <tr><td>Room Charges — <?php echo htmlspecialchars($payment['room_number']); ?> (<?php echo $nights; ?> night<?php echo $nights == 1 ? '' : 's'; ?> &times; <?php echo formatCurrency($bill['rate'] ?? 0); ?>)</td><td class="text-end"><?php echo formatCurrency($room_charge); ?></td></tr>
                <?php foreach ($extra_charges as $charge): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($charge['charge_type'] . ($charge['description'] ? ': ' . $charge['description'] : '')); ?></td>
                        <td class="text-end"><?php echo formatCurrency($charge['amount']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tax_amount > 0): ?>
                    <tr><td>Tax (<?php echo $tax_rate; ?>%)</td><td class="text-end"><?php echo formatCurrency($tax_amount); ?></td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-success"><th class="text-end">TOTAL</th><th class="text-end"><?php echo formatCurrency($payment['amount']); ?></th></tr>
                <tr><td colspan="2" class="text-center text-muted small">Payment Status: <span class="badge bg-success">PAID</span></td></tr>
            </tfoot>
        </table>
    </div>

    <div class="text-center mt-3 text-muted">
        <small>Thank you for staying with us!</small><br>
        <small>This is a computer-generated receipt</small>
    </div>
</div>
