<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Room;

class HousekeepingController extends Controller
{
    protected $active = 'housekeeping';

    public function __construct()
    {
        $this->loginRequired();
        $this->room = new Room();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_clean') {
            $id = (int)$_POST['id'];
            $this->room->setStatus($id, 'Available');
            $this->message = 'Room marked as cleaned and available.';
        }

        $rooms = $this->room->byStatuses(['Cleaning', 'Maintenance']);
        $cleaningCount = $maintenanceCount = 0;
        foreach ($rooms as $r) { if ($r['status'] === 'Cleaning') $cleaningCount++; else $maintenanceCount++; }

        $this->view('housekeeping/index', [
            'rooms'         => $rooms,
            'cleaningCount' => $cleaningCount,
            'maintenanceCount' => $maintenanceCount,
            'message'       => $this->message ?? '',
            'error'         => $this->error ?? '',
        ], 'housekeeping', 'Housekeeping');
    }
}
