<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h2 class="page-title mb-1">Settings</h2><p class="page-subtitle mb-0">Hotel & property configuration</p></div>
</div>

<?php if (!empty($message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <h6 class="text-muted mb-3">Hotel Information</h6>
            <div class="mb-3"><label class="form-label">Hotel Name</label><input type="text" name="hotel_name" class="form-control" value="<?php echo htmlspecialchars($settings['hotel_name'] ?? ''); ?>" required></div>
            <div class="mb-3"><label class="form-label">Address</label><input type="text" name="hotel_address" class="form-control" value="<?php echo htmlspecialchars($settings['hotel_address'] ?? ''); ?>"></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="hotel_phone" class="form-control" value="<?php echo htmlspecialchars($settings['hotel_phone'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="hotel_email" class="form-control" value="<?php echo htmlspecialchars($settings['hotel_email'] ?? ''); ?>"></div>
            </div>
            <h6 class="text-muted mb-3 mt-4">Billing</h6>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Currency Code</label><input type="text" name="currency" class="form-control" value="<?php echo htmlspecialchars($settings['currency'] ?? 'USD'); ?>" maxlength="10" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '10'); ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Settings</button>
        </form>
    </div>
</div>
