<?php
namespace App\Controllers;

use App\Core\Controller;

class SettingController extends Controller
{
    protected $active = 'settings';
    protected $pageTitle = 'Settings';

    private $settingFields = ['hotel_name', 'hotel_address', 'hotel_phone', 'hotel_email', 'currency', 'tax_rate'];

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator']);
        $this->db = getDB();
    }

    public function indexAction()
    {
        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
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
        }

        $settings = getSystemSettings();

        $this->view('settings/index', [
            'settings' => $settings,
            'message'  => $message,
            'error'    => $error,
        ], 'settings', 'Settings');
    }
}
