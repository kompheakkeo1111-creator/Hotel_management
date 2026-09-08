<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Check-out & Payment</h2><p class="page-subtitle mb-0">Complete checkout and issue a bill.</p></div>
    <a href="index.php?r=billing/index" class="btn btn-outline-success"><i class="bi bi-receipt"></i> Payment Billing</a>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
            <td class="fw-semibold"><?php echo htmlspecialchars($c['guest_name']); ?></td>
            <td><span class="badge bg-primary">Room <?php echo htmlspecialchars($c['room_number']); ?></span></td>
            <td><?php echo date('Y-m-d H:i', strtotime($c['check_in_time'])); ?></td>
            <td><?php echo htmlspecialchars($c['expected_check_out']); ?></td>
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
                    <div><strong><?php echo htmlspecialchars($c['guest_name']); ?></strong><br><small class="text-muted">Room <?php echo htmlspecialchars($c['room_number']); ?> · <?php echo htmlspecialchars($c['reservation_number'] ?? 'WALK-IN'); ?></small></div>
                </div>

                <div class="mb-3 border rounded p-3">
                    <h6 class="mb-3">Add Extra Charge</h6>
                    <div class="row g-2">
                        <div class="col-md-4"><select name="charge_type" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($chargeTypes as $ct): ?><option value="<?php echo $ct; ?>"><?php echo $ct; ?></option><?php endforeach; ?>
                        </select></div>
                        <div class="col-md-5"><input type="text" name="charge_desc" class="form-control" placeholder="Description"></div>
                        <div class="col-md-3"><input type="number" name="charge_amount" class="form-control" placeholder="Amount" step="0.01" min="0"></div>
                    </div>
                    <small class="text-muted">Existing extras: <?php echo $c['extra_count']; ?> (<?php echo formatCurrency($c['extra_total']); ?>)</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Final Amount</label>
                        <div class="input-group input-group-lg"><span class="input-group-text"><?php echo htmlspecialchars($currency); ?></span><input required type="number" step="0.01" min="0" name="amount" class="form-control amount-input" value="<?php echo $c['stay_total']; ?>"></div>
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
                    <div class="d-flex justify-content-between"><span>Tax (<?php echo $taxRate; ?>%)</span><span><?php echo formatCurrency($c['tax']); ?></span></div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5"><span>TOTAL</span><span class="text-success"><?php echo formatCurrency($c['stay_total']); ?></span></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Confirm & Generate Bill</button></div>
        </form></div></div></div>
        <?php endforeach; endif; ?>
        </tbody></table></div>
</div></div>

<?php if (!empty($receipt)): ?>
<div class="receipt mt-4" id="printReceipt">
    <div class="receipt-header">
        <div style="font-size:42px">🏨</div>
        <h2><?php echo htmlspecialchars($hotelName); ?></h2>
        <div>PAYMENT RECEIPT</div>
        <small class="text-muted">Invoice: <strong><?php echo htmlspecialchars($receipt['invoice_no']); ?></strong></small>
    </div>
    <div class="receipt-grid">
        <div class="info-box"><div class="label">Guest</div><div class="value"><?php echo htmlspecialchars($receipt['guest_name']); ?></div></div>
        <div class="info-box"><div class="label">Room</div><div class="value"><?php echo htmlspecialchars($receipt['room_number']); ?></div></div>
        <div class="info-box"><div class="label">Reservation</div><div class="value"><?php echo htmlspecialchars($receipt['reservation_number']); ?></div></div>
        <div class="info-box"><div class="label">Payment Method</div><div class="value"><?php echo htmlspecialchars($receipt['method']); ?></div></div>
        <div class="info-box"><div class="label">Check-in</div><div class="value"><?php echo htmlspecialchars($receipt['check_in_time']); ?></div></div>
        <div class="info-box"><div class="label">Checkout</div><div class="value"><?php echo htmlspecialchars($receipt['checkout_time']); ?></div></div>
    </div>
    <table class="table mt-4"><thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>
        <tr><td>Final hotel payment</td><td class="text-end"><?php echo formatCurrency($receipt['amount']); ?></td></tr>
    </tbody></table>
    <div class="total-box"><span>TOTAL</span><span><?php echo formatCurrency($receipt['amount']); ?></span></div>
    <div class="text-center mt-3"><span class="badge bg-success fs-6"><?php echo htmlspecialchars($receipt['status']); ?></span></div>
    <div class="text-center text-muted mt-4">Thank you for staying with us!<br>We hope to see you again.</div>
    <div class="no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-success btn-lg"><i class="bi bi-printer-fill"></i> Print Bill</button>
        <a href="index.php?r=billing/index" class="btn btn-outline-primary btn-lg">View Billing</a>
        <a href="index.php?r=checkout/index" class="btn btn-secondary btn-lg">Back</a>
    </div>
</div>
<?php endif; ?>
