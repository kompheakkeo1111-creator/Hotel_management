<?php
namespace App\Controllers;

use App\Core\Controller;

class SettingController extends Controller
{
    protected $active = 'settings';
    protected $pageTitle = 'Settings';

    private $settingFields = ['hotel_name', 'hotel_address', 'hotel_phone', 'hotel_email', 'currency', 'tax_rate'];
    private $backupDir;

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator']);
        $this->db = getDB();
        $this->backupDir = __DIR__ . '/../../backups/';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function indexAction()
    {
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'save') {
                try {
                    $stmt = $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value)
                                                VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($this->settingFields as $key) {
                        $val = trim($_POST[$key] ?? '');
                        $stmt->execute([$key, $val]);
                    }
                    $message = 'Settings updated successfully.';
                } catch (\Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            } elseif ($action === 'backup') {
                try {
                    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $filepath = $this->backupDir . $filename;

                    $tables = [];
                    $result = $this->db->query("SHOW TABLES");
                    while ($row = $result->fetch(\PDO::FETCH_NUM)) {
                        $tables[] = $row[0];
                    }

                    $sql = "-- Hotel Management System Backup\n";
                    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
                    $sql .= "SET AUTOCOMMIT = 0;\n";
                    $sql .= "START TRANSACTION;\n\n";

                    foreach ($tables as $table) {
                        $sql .= "-- Table: $table\n";
                        $sql .= "DROP TABLE IF EXISTS `$table`;\n";

                        $create = $this->db->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM);
                        $sql .= $create[1] . ";\n\n";

                        $rows = $this->db->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_NUM);
                        if ($rows) {
                            $cols = $this->db->query("SHOW COLUMNS FROM `$table`")->fetchAll(\PDO::FETCH_NUM);
                            $colNames = array_map(function($c) { return '`' . $c[0] . '`'; }, $cols);
                            $sql .= "INSERT INTO `$table` (" . implode(', ', $colNames) . ") VALUES\n";

                            $valueRows = [];
                            foreach ($rows as $row) {
                                $vals = array_map(function($v) {
                                    return $v === null ? 'NULL' : "'" . addslashes($v) . "'";
                                }, $row);
                                $valueRows[] = '(' . implode(', ', $vals) . ')';
                            }
                            $sql .= implode(",\n", $valueRows) . ";\n\n";
                        }
                    }

                    $sql .= "COMMIT;\n";
                    file_put_contents($filepath, $sql);
                    $message = "Backup created: $filename";
                } catch (\Exception $e) {
                    $error = 'Backup failed: ' . $e->getMessage();
                }
            } elseif ($action === 'restore') {
                try {
                    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new \Exception('Please select a backup file.');
                    }

                    $content = file_get_contents($_FILES['backup_file']['tmp_name']);
                    if (empty($content)) {
                        throw new \Exception('Backup file is empty.');
                    }

                    $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $statements = array_filter(array_map('trim', explode(';', $content)));
                    foreach ($statements as $stmt) {
                        if (!empty($stmt) && preg_match('/^(DROP|CREATE|INSERT|ALTER|SET|START|COMMIT)/i', $stmt)) {
                            $this->db->exec($stmt);
                        }
                    }
                    $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");

                    $message = 'Database restored successfully.';
                } catch (\Exception $e) {
                    $error = 'Restore failed: ' . $e->getMessage();
                }
            } elseif ($action === 'delete_backup') {
                $file = $_POST['file'] ?? '';
                $filepath = $this->backupDir . basename($file);
                if (file_exists($filepath)) {
                    unlink($filepath);
                    $message = 'Backup deleted.';
                }
            }
        }

        $backups = [];
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir . '*.sql') as $f) {
                $backups[] = [
                    'name' => basename($f),
                    'size' => round(filesize($f) / 1024, 1),
                    'date' => date('Y-m-d H:i', filemtime($f)),
                ];
            }
        }
        usort($backups, function($a, $b) { return strcmp($b['name'], $a['name']); });

        $settings = getSystemSettings();

        $this->view('settings/index', [
            'settings' => $settings,
            'backups'  => $backups,
            'message'  => $message,
            'error'    => $error,
        ], 'settings', 'Settings');
    }
}
