<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die('Invalid payment ID'); }

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
if (!$payment) { die('Payment record not found'); }

$settings = getSystemSettings();
$chars = $db->prepare("SELECT * FROM extra_charges WHERE check_in_id=?");
$chars->execute([$payment['check_in_id']]);
$extra_charges = $chars->fetchAll(PDO::FETCH_ASSOC);
$extra_total = 0;
foreach ($extra_charges as $c) $extra_total += (float)$c['amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt - <?php echo htmlspecialchars($payment['invoice_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fb} .receipt{max-width:820px;margin:30px auto;background:#fff;padding:40px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.receipt-header{text-align:center;border-bottom:2px solid #198754;padding-bottom:20px;margin-bottom:25px}
.receipt-header h2{color:#198754;font-weight:700}
.receipt-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.info-box{background:#f8f9fa;padding:14px;border-radius:8px}.label{font-size:11px;text-transform:uppercase;color:#6b7280}.value{font-weight:600;margin-top:4px}
.total-box{margin-top:22px;background:#198754;color:#fff;padding:18px;border-radius:10px;display:flex;justify-content:space-between;font-size:22px;font-weight:700}
.no-print{margin-top:25px;text-align:center}
@media print{.no-print{display:none!important}}
</style>
</head>
<body>
<div class="receipt">
    <div class="receipt-header">
        <div style="font-size:42px">🏨</div>
        <h2><?php echo htmlspecialchars($settings['hotel_name'] ?? 'My Hotel'); ?></h2>
        <div>PAYMENT RECEIPT</div>
        <small class="text-muted">Invoice: <strong><?php echo htmlspecialchars($payment['invoice_number']); ?></strong></small>
        <div><small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($payment['payment_date'])); ?></small></div>
    </div>
    <div class="receipt-grid">
        <div class="info-box"><div class="label">Guest</div><div class="value"><?php echo htmlspecialchars($payment['full_name']); ?></div></div>
        <div class="info-box"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($payment['room_number']); ?></div></div>
        <div class="info-box"><div class="label">Reservation</div><div class="value"><?php echo htmlspecialchars($payment['reservation_number'] ?? 'WALK-IN'); ?></div></div>
        <div class="info-box"><div class="label">Payment Method</div><div class="value"><?php echo htmlspecialchars($payment['payment_method']); ?></div></div>
        <div class="info-box"><div class="label">Check-in</div><div class="value"><?php echo htmlspecialchars($payment['check_in_time']); ?></div></div>
        <div class="info-box"><div class="label">Checkout</div><div class="value"><?php echo htmlspecialchars($payment['check_out_date'] ?? ''); ?></div></div>
    </div>
    <table class="table mt-4">
        <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            <tr><td>Room Charges</td><td class="text-end"><?php echo formatCurrency($payment['amount'] - $extra_total); ?></td></tr>
            <?php foreach ($extra_charges as $charge): ?>
                <tr><td><?php echo htmlspecialchars($charge['charge_type'] . ($charge['description'] ? ': ' . $charge['description'] : '')); ?></td><td class="text-end"><?php echo formatCurrency($charge['amount']); ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="total-box"><span>TOTAL</span><span><?php echo formatCurrency($payment['amount']); ?></span></div>
    <div class="text-center mt-3"><span class="badge bg-success fs-6">PAID</span></div>
    <div class="text-center text-muted mt-4">Thank you for staying with us!</div>
    <div class="no-print"><button onclick="window.print()" class="btn btn-success btn-lg"><i class="bi bi-printer"></i> Print</button> <a href="billing.php" class="btn btn-secondary btn-lg">Back</a></div>
</div>
</body>
</html>
