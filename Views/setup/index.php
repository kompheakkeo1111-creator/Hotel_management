<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Management System - Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .setup-card { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-card">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-hotel fa-3x text-primary mb-3"></i>
                        <h2>Hotel Management System</h2>
                        <p class="text-muted">First-time Setup</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                            <?php if ($success): ?>
                                <br><a href="index.php?r=login/index" class="alert-link">Go to Login &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(DB_HOST); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(DB_NAME); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database User</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(DB_USER); ?>" disabled>
                        </div>
                        <p class="text-muted small">This will create all tables and a default admin account.</p>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="fas fa-download me-2"></i>Install Database
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
