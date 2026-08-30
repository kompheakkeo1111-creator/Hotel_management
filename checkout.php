<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$settings = getSystemSettings();
$tax_rate = (float)($settings['tax_rate'] ?? 0);
$methods = ['Cash', 'Credit/Debit Card', 'QR Payment', 'Bank Transfer'];

$message = '';
$error = '';
$receipt = null;

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function nightsBetween($from, $to) {
    $d1 = strtotime(date('Y-m-d', strtotime($from)));
    $d2 = strtotime(date('Y-m-d', strtotime($to)));
    return max(0, (int)(($d2 - $d1) / 86400));
}

function computeStayTotal($db, $ci, $tax_rate = 0) {
    // Room charge = nights x rate
    $rate = (float)$ci['price_per_night'];
    $checkin = date('Y-m-d', strtotime($ci['check_in_time']));
    $checkout = date('Y-m-d');
    if (strtotime($checkout) < strtotime($checkin)) $checkout = $checkin;
    $nights = nightsBetween($checkin, $checkout);
    if ($nights < 1) $nights = 1; // minimum one night
    $room_charge = round($nights * $rate, 2);

    // Extra charges attached to this check-in
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM extra_charges WHERE check_in_id=?");
    $stmt->execute([$ci['id']]);
    $extra = (float)$stmt->fetchColumn();

    $subtotal = $room_charge + $extra;
    $tax = round($subtotal * $tax_rate / 100, 2);
    return [
        'nights' => $nights,
        'room_charge' => $room_charge,
        'extra' => $extra,
        'tax' => $tax,
        'total' => round($subtotal + $tax, 2)
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $check_in_id = filter_input(INPUT_POST, 'check_in_id', FILTER_VALIDATE_INT);
    $amount = trim($_POST['amount'] ?? '');
    $method = $_POST['method'] ?? 'Cash';
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $add_charge_type = $_POST['charge_type'] ?? '';
    $add_charge_desc = trim($_POST['charge_desc'] ?? '');
    $add_charge_amt = trim($_POST['charge_amount'] ?? '');

    if (!in_array($method, $methods, true)) $method = 'Cash';
    if ($amount === '' || !is_numeric($amount) || (float)$amount < 0) {
        $error = 'Please enter a valid payment amount.';
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                SELECT c.*,
                       g.full_name AS guest_name, g.id AS guest_id,
                       rm.room_number, rm.id AS room_id, rm.price_per_night,
                       r.reservation_number, r.check_in_date, r.check_out_date,
                       r.id AS reservation_id
                FROM check_ins c
                JOIN guests g ON c.guest_id=g.id
                JOIN rooms rm ON c.room_id=rm.id
                LEFT JOIN reservations r ON c.reservation_id=r.id
                WHERE c.id=? AND c.status='Active' LIMIT 1");
            $stmt->execute([$check_in_id]);
            $ci = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ci) throw new Exception('Check-in record not found or already checked out.');

            // Optionally add an extra charge before finalizing
            if ($add_charge_type !== '' && $add_charge_amt !== '' && is_numeric($add_charge_amt) && (float)$add_charge_amt > 0) {
                $ci_id = (int)$ci['id'];
                $db->prepare("INSERT INTO extra_charges (check_in_id, charge_type, description, amount) VALUES (?,?,?,?)")
                    ->execute([$ci_id, $add_charge_type, $add_charge_desc ?: $add_charge_type, (float)$add_charge_amt]);
                // Recompute total after adding charge
                $tot = computeStayTotal($db, $ci, $tax_rate);
                $amount = (string)$tot['total'];
            }

            $paymentAmount = (float)$amount;

            // Mark check-out
            $db->prepare("UPDATE check_ins SET status='Checked Out', actual_check_out=NOW() WHERE id=?")->execute([$ci['id']]);

            // Mark reservation Completed (real schema has no 'Checked-out')
            if ($ci['reservation_id']) {
                $db->prepare("UPDATE reservations SET status='Completed' WHERE id=?")->execute([$ci['reservation_id']]);
            }

            // Room to Cleaning
            $db->prepare("UPDATE rooms SET status='Cleaning' WHERE id=?")->execute([$ci['room_id']]);

            // Record payment with invoice number
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad((string)(int)$ci['id'], 5, '0', STR_PAD_LEFT);
            $paymentId = null;
            if ($paymentAmount > 0) {
                $finalInvoice = $invoiceNo;
                $n = (int)$ci['id'];
                $countStmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE invoice_number=?");
                while (true) {
                    $countStmt->execute([$finalInvoice]);
                    if ((int)$countStmt->fetchColumn() === 0) break;
                    $n++;
                    $finalInvoice = 'INV-' . date('Ymd') . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
                }
                $db->prepare("INSERT INTO payments (reservation_id, check_in_id, amount, payment_method, transaction_id, payment_date, invoice_number)
                              VALUES (?,?,?,?,?,NOW(),?)")
                    ->execute([
                        $ci['reservation_id'],
                        $ci['id'],
                        $paymentAmount,
                        $method,
                        $transaction_id ?: null,
                        $finalInvoice
                    ]);
                $paymentId = (int)$db->lastInsertId();
                $invoiceNo = $finalInvoice;
            }

            $db->commit();

            $receipt = [
                'invoice_no' => $invoiceNo,
                'payment_id' => $paymentId,
                'reservation_number' => $ci['reservation_number'] ?? 'WALK-IN',
                'guest_name' => $ci['guest_name'],
                'room_number' => $ci['room_number'],
                'check_in_time' => $ci['check_in_time'],
                'checkout_time' => date('Y-m-d H:i:s'),
                'amount' => $paymentAmount,
                'method' => $method,
                'transaction_id' => $transaction_id,
                'status' => $paymentAmount > 0 ? 'PAID' : 'NO PAYMENT'
            ];
            $message = 'Guest checked out successfully. Bill generated.';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Active stays with computed totals for the checkout modals
$stays = $db->query("
    SELECT c.id, c.check_in_time, c.expected_check_out,
           g.full_name guest_name, rm.room_number, rm.price_per_night,
           r.reservation_number,
           (SELECT COALESCE(SUM(amount),0) FROM extra_charges WHERE check_in_id=c.id) extra_total,
           (SELECT COUNT(*) FROM extra_charges WHERE check_in_id=c.id) extra_count
    FROM check_ins c
    JOIN guests g ON c.guest_id=g.id
    JOIN rooms rm ON c.room_id=rm.id
    LEFT JOIN reservations r ON c.reservation_id=r.id
    WHERE c.status='Active'
    ORDER BY c.check_in_time ASC")->fetchAll(PDO::FETCH_ASSOC);

// Compute per-stay totals server side
foreach ($stays as &$s) {
    $t = computeStayTotal($db, $s, $tax_rate);
    $s['nights'] = $t['nights'];
    $s['room_charge'] = $t['room_charge'];
    $s['tax'] = $t['tax'];
    $s['stay_total'] = $t['total'];
}
unset($s);

$active = 'checkout';
$pageTitle = 'Check-out & Payment';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Check-out & Payment</h2><p class="page-subtitle mb-0">Complete checkout and issue a bill.</p></div>
    <a href="billing.php" class="btn btn-outline-success"><i class="bi bi-receipt"></i> Payment Billing</a>
</div>

<?php if ($message): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($error); ?></div><?php endif; ?>

<div class="card"><div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-people-fill text-success"></i> Current Guests</h5>
        <span class="badge bg-secondary"><?php echo count($stays); ?> Active</span>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Expected Out</th><th class="text-end">Est. Total</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php if (!$stays): ?><tr><td colspan="6" class="text-center py-5 text-muted">No guests currently checked in.</td></tr>
        <?php else: foreach ($stays as $c): ?>
        <tr>
            <td class="fw-semibold"><?php echo e($c['guest_name']); ?></td>
            <td><span class="badge bg-primary">Room <?php echo e($c['room_number']); ?></span></td>
            <td><?php echo date('Y-m-d H:i', strtotime($c['check_in_time'])); ?></td>
            <td><?php echo e($c['expected_check_out']); ?></td>
            <td class="text-end fw-bold"><?php echo formatCurrency($c['stay_total']); ?></td>
            <td class="text-end"><button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#checkout<?php echo (int)$c['id']; ?>"><i class="bi bi-credit-card"></i> Payment & Check-out</button></td>
        </tr>
        <div class="modal fade" id="checkout<?php echo (int)$c['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Payment & Check-out</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="check_in_id" value="<?php echo (int)$c['id']; ?>">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div><strong><?php echo e($c['guest_name']); ?></strong><br><small class="text-muted">Room <?php echo e($c['room_number']); ?> · <?php echo e($c['reservation_number'] ?? 'WALK-IN'); ?></small></div>
                </div>

                <div class="mb-3 border rounded p-3">
                    <h6 class="mb-3">Add Extra Charge</h6>
                    <div class="row g-2">
                        <div class="col-md-4"><select name="charge_type" class="form-select">
                            <option value="">None</option>
                            <?php foreach (['Room Service','Laundry','Restaurant','Mini Bar','Damage Fee','Other'] as $ct): ?><option value="<?php echo $ct; ?>"><?php echo $ct; ?></option><?php endforeach; ?>
                        </select></div>
                        <div class="col-md-5"><input type="text" name="charge_desc" class="form-control" placeholder="Description"></div>
                        <div class="col-md-3"><input type="number" name="charge_amount" class="form-control" placeholder="Amount" step="0.01" min="0"></div>
                    </div>
                    <small class="text-muted">Existing extras: <?php echo $c['extra_count']; ?> (<?php echo formatCurrency($c['extra_total']); ?>)</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Final Amount</label>
                        <div class="input-group input-group-lg"><span class="input-group-text"><?php echo $settings['currency'] ?? 'USD'; ?></span><input required type="number" step="0.01" min="0" name="amount" class="form-control amount-input" value="<?php echo $c['stay_total']; ?>"></div>
                    </div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Payment Method</label>
                        <select name="method" class="form-select form-select-lg">
                            <?php foreach ($methods as $m): ?><option value="<?php echo $m; ?>" <?php echo $m==='Cash'?'selected':''; ?>><?php echo $m; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Transaction ID (optional)</label><input type="text" name="transaction_id" class="form-control" placeholder="e.g. card/QR reference"></div>

                <div class="payment-summary border rounded p-3">
                    <div class="d-flex justify-content-between"><span>Stay (<?php echo $c['nights']; ?> nights)</span><span><?php echo formatCurrency($c['room_charge']); ?></span></div>
                    <div class="d-flex justify-content-between"><span>Extra charges</span><span><?php echo formatCurrency($c['extra_total']); ?></span></div>
                    <div class="d-flex justify-content-between"><span>Tax (<?php echo $tax_rate; ?>%)</span><span><?php echo formatCurrency($c['tax']); ?></span></div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5"><span>TOTAL</span><span class="text-success"><?php echo formatCurrency($c['stay_total']); ?></span></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Confirm & Generate Bill</button></div>
        </form></div></div></div>
        <?php endforeach; endif; ?>
        </tbody></table></div>
</div></div>

<?php if ($receipt): ?>
<div class="receipt mt-4" id="printReceipt">
    <div class="receipt-header">
        <div style="font-size:42px">🏨</div>
        <h2><?php echo e($settings['hotel_name'] ?? 'My Hotel'); ?></h2>
        <div>PAYMENT RECEIPT</div>
        <small class="text-muted">Invoice: <strong><?php echo e($receipt['invoice_no']); ?></strong></small>
    </div>
    <div class="receipt-grid">
        <div class="info-box"><div class="label">Guest</div><div class="value"><?php echo e($receipt['guest_name']); ?></div></div>
        <div class="info-box"><div class="label">Room</div><div class="value"><?php echo e($receipt['room_number']); ?></div></div>
        <div class="info-box"><div class="label">Reservation</div><div class="value"><?php echo e($receipt['reservation_number']); ?></div></div>
        <div class="info-box"><div class="label">Payment Method</div><div class="value"><?php echo e($receipt['method']); ?></div></div>
        <div class="info-box"><div class="label">Check-in</div><div class="value"><?php echo e($receipt['check_in_time']); ?></div></div>
        <div class="info-box"><div class="label">Checkout</div><div class="value"><?php echo e($receipt['checkout_time']); ?></div></div>
    </div>
    <table class="table mt-4"><thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
        <tr><td>Final hotel payment</td><td class="text-end"><?php echo formatCurrency($receipt['amount']); ?></td></tr>
    </tbody></table>
    <div class="total-box"><span>TOTAL</span><span><?php echo formatCurrency($receipt['amount']); ?></span></div>
    <div class="text-center mt-3"><span class="badge bg-success fs-6"><?php echo e($receipt['status']); ?></span></div>
    <div class="text-center text-muted mt-4">Thank you for staying with us!<br>We hope to see you again.</div>
    <div class="no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-success btn-lg"><i class="bi bi-printer-fill"></i> Print Bill</button>
        <a href="billing.php" class="btn btn-outline-primary btn-lg">View Billing</a>
        <a href="checkout.php" class="btn btn-secondary btn-lg">Back</a>
    </div>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
