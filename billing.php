<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$settings = getSystemSettings();
$methods = ['Cash', 'Credit/Debit Card', 'QR Payment', 'Bank Transfer'];

$search = trim($_GET['search'] ?? '');
$method = trim($_GET['payment_method'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$export = $_GET['export'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Base query
$base = "FROM payments p
         JOIN check_ins ci ON p.check_in_id = ci.id
         JOIN guests g ON ci.guest_id = g.id
         JOIN rooms rm ON ci.room_id = rm.id
         LEFT JOIN reservations r ON p.reservation_id = r.id
         WHERE 1=1";
$params = [];

if ($search !== '') {
    $base .= " AND (g.full_name LIKE ? OR rm.room_number LIKE ? OR p.invoice_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($method !== '') { $base .= " AND p.payment_method = ?"; $params[] = $method; }
if ($start_date !== '') { $base .= " AND DATE(p.payment_date) >= ?"; $params[] = $start_date; }
if ($end_date !== '') { $base .= " AND DATE(p.payment_date) <= ?"; $params[] = $end_date; }

// Export before pagination
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="billing_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice #', 'Guest', 'Room', 'Reservation', 'Amount', 'Method', 'Transaction ID', 'Date']);
    $stmt = $db->prepare("SELECT p.*, g.full_name, rm.room_number, r.reservation_number " . $base . " ORDER BY p.payment_date DESC");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($output, [
            $row['invoice_number'],
            $row['full_name'],
            $row['room_number'],
            $row['reservation_number'] ?? 'N/A',
            number_format($row['amount'], 2),
            $row['payment_method'],
            $row['transaction_id'] ?? '',
            $row['payment_date']
        ]);
    }
    fclose($output);
    exit;
}

// Total count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) " . $base);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

$query = "SELECT p.*, g.full_name, rm.room_number, r.reservation_number, ci.check_in_time " . $base . " ORDER BY p.payment_date DESC LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary by method
$summary = [];
foreach ($db->query("SELECT payment_method, COUNT(*) cnt, COALESCE(SUM(amount),0) amt FROM payments GROUP BY payment_method") as $s) {
    $summary[$s['payment_method']] = ['count' => $s['cnt'], 'amount' => $s['amt']];
}
$grand_total = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();

$active = 'billing';
$pageTitle = 'Billing';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Billing & Invoices</h2><p class="page-subtitle mb-0"><?php echo $grand_total ? formatCurrency($grand_total) : '0.00'; ?> total collected</p></div>
    <a href="?export=csv&search=<?php echo urlencode($search); ?>&payment_method=<?php echo urlencode($method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success"><i class="bi bi-download"></i> Export CSV</a>
</div>

<!-- Summary cards -->
<div class="row">
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-success"><i class="bi bi-cash"></i></div><div><div class="number"><?php echo isset($summary['Cash']) ? formatCurrency($summary['Cash']['amount']) : '0.00'; ?></div><div class="label">Cash (<?php echo $summary['Cash']['count'] ?? 0; ?>)</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-primary"><i class="bi bi-credit-card"></i></div><div><div class="number"><?php echo isset($summary['Credit/Debit Card']) ? formatCurrency($summary['Credit/Debit Card']['amount']) : '0.00'; ?></div><div class="label">Card (<?php echo $summary['Credit/Debit Card']['count'] ?? 0; ?>)</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-info"><i class="bi bi-qr-code"></i></div><div><div class="number"><?php echo isset($summary['QR Payment']) ? formatCurrency($summary['QR Payment']['amount']) : '0.00'; ?></div><div class="label">QR (<?php echo $summary['QR Payment']['count'] ?? 0; ?>)</div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat-card"><div class="icon icon-warning"><i class="bi bi-bank"></i></div><div><div class="number"><?php echo isset($summary['Bank Transfer']) ? formatCurrency($summary['Bank Transfer']['amount']) : '0.00'; ?></div><div class="label">Bank Transfer (<?php echo $summary['Bank Transfer']['count'] ?? 0; ?>)</div></div></div></div>
</div>

<form method="GET" class="filter-form mb-4 p-3">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Search guest, room, invoice #" value="<?php echo htmlspecialchars($search); ?>"></div>
        <div class="col-md-2"><select name="payment_method" class="form-select">
            <option value="">All Methods</option>
            <?php foreach ($methods as $m): ?><option value="<?php echo $m; ?>" <?php echo $method === $m ? 'selected' : ''; ?>><?php echo $m; ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>"></div>
        <div class="col-md-2"><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button> <a href="billing.php" class="btn btn-secondary">Reset</a></div>
    </div>
</form>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr>
            <th>Invoice</th><th>Guest</th><th>Room</th><th>Reservation</th><th>Amount</th><th>Method</th><th>Date</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($p['invoice_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                <td><span class="badge bg-primary"><?php echo htmlspecialchars($p['room_number']); ?></span></td>
                <td><?php if ($p['reservation_number']): ?><span class="badge bg-info"><?php echo htmlspecialchars($p['reservation_number']); ?></span><?php else: ?><span class="text-muted">Walk-in</span><?php endif; ?></td>
                <td><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                <td><span class="badge bg-<?php echo $p['payment_method']=='Cash'?'success':($p['payment_method']=='Credit/Debit Card'?'primary':'warning'); ?>"><?php echo htmlspecialchars($p['payment_method']); ?></span></td>
                <td><?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?></td>
                <td>
                    <button onclick="window.open('print_receipt.php?id=<?php echo (int)$p['id']; ?>','_blank')" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
                    <button onclick="viewReceipt(<?php echo (int)$p['id']; ?>)" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#receiptModal"><i class="bi bi-eye"></i> View</button>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px"></i>No payment records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div></div>

<?php if ($pages > 1): ?>
<div class="card-body border-top">
    <nav><ul class="pagination justify-content-center mb-0">
        <?php
        $qs = http_build_query(array_filter(['search'=>$search,'payment_method'=>$method,'start_date'=>$start_date,'end_date'=>$end_date]));
        for ($i = 1; $i <= $pages; $i++):
            $link = 'billing.php?' . ($qs ? $qs . '&' : '') . 'page=' . $i;
        ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $link; ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
    </ul></nav>
</div>
<?php endif; ?>
</div>

<!-- Receipt View Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Payment Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="receiptContent"><div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<script>
function viewReceipt(id) {
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
    document.getElementById('receiptContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';
    fetch('get_receipt.php?id=' + id)
        .then(r => r.text())
        .then(html => { document.getElementById('receiptContent').innerHTML = html; })
        .catch(() => { document.getElementById('receiptContent').innerHTML = '<div class="alert alert-danger">Error loading receipt.</div>'; });
}
</script>
<?php require 'includes/footer.php'; ?>
