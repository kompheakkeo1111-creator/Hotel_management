<?php
namespace App\Controllers;

class SetupController extends \Controller {
    public $authRequired = false;

    public function indexAction() {
        $message = '';
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Connect without DB name to create it
                $pdo = new \PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // Create database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $pdo->exec("USE `" . DB_NAME . "`");

                // Read and execute SQL file
                $sqlFile = __DIR__ . '/../../database/hotel_management.sql';
                $sql = file_get_contents($sqlFile);
                // Remove CREATE DATABASE and USE statements since we already created it
                $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                $sql = preg_replace('/USE `?.*?`?;/i', '', $sql);

                // Split by semicolons and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && preg_match('/^[A-Z]/i', $stmt)) {
                        $pdo->exec($stmt);
                    }
                }

                // Generate fresh password hash
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $pdo->exec("UPDATE users SET password = '" . $hash . "' WHERE username = 'admin'");

                $success = true;
                $message = "Installation complete! Default login: admin / admin123";

            } catch (\PDOException $e) {
                $message = "Error: " . $e->getMessage();
            }
        }

        require __DIR__ . '/../Views/setup/index.php';
    }
}
