<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Room;

class MaintenanceController extends Controller
{
    protected $active = 'maintenance';

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator', 'Housekeeping Staff', 'Manager']);
        $this->room = new Room();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $id = (int)$_POST['id'];
            $newStatus = $_POST['action'] === 'set_maintenance' ? 'Maintenance' : 'Available';
            $room = $this->room->find($id);
            if ($newStatus === 'Maintenance' && $room && $room['status'] === 'Occupied') {
                $this->error = 'Occupied rooms cannot be set to maintenance.';
            } else {
                $this->room->setStatus($id, $newStatus);
                $this->message = 'Room status updated.';
            }
        }

        $rooms = $this->room->all(['status' => '']);
        $mtCount = 0;
        foreach ($rooms as $r) { if ($r['status'] === 'Maintenance') $mtCount++; }

        $this->view('maintenance/index', [
            'rooms'   => $rooms,
            'mtCount' => $mtCount,
            'message' => $this->message ?? '',
            'error'   => $this->error ?? '',
        ], 'maintenance', 'Maintenance');
    }
}
