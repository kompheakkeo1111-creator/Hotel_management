<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Room;
use App\Models\RoomType;

class RoomController extends Controller
{
    protected $active = 'rooms';

    private $validStatus = ['Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance'];

    public function __construct()
    {
        $this->loginRequired();
        $this->room = new Room();
        $this->roomType = new RoomType();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $filters = [
            'status' => $_GET['status'] ?? '',
            'floor'  => $_GET['floor'] ?? '',
            'type'   => $_GET['type'] ?? '',
        ];

        $rooms = $this->room->all($filters);
        $roomTypes = $this->roomType->all();
        $floors = $this->room->distinctFloors();

        $this->view('rooms/index', [
            'rooms'      => $rooms,
            'roomTypes'  => $roomTypes,
            'floors'     => $floors,
            'validStatus'=> $this->validStatus,
            'filters'    => $filters,
            'message'    => $this->message ?? '',
            'error'      => $this->error ?? '',
        ], 'rooms', 'Rooms');
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'edit') {
                $data = [
                    'room_number'     => trim($_POST['room_number'] ?? ''),
                    'room_type_id'    => ($_POST['room_type_id'] ?? '') !== '' ? (int)$_POST['room_type_id'] : null,
                    'floor'           => ($_POST['floor'] ?? '') !== '' ? (int)$_POST['floor'] : null,
                    'capacity'        => ($_POST['capacity'] ?? '') !== '' ? (int)$_POST['capacity'] : null,
                    'price_per_night' => ($_POST['price_per_night'] ?? '') !== '' ? (float)$_POST['price_per_night'] : 0,
                    'description'     => trim($_POST['description'] ?? ''),
                    'status'          => in_array($_POST['status'] ?? '', $this->validStatus, true) ? $_POST['status'] : 'Available',
                ];

                if ($data['room_number'] === '') throw new \Exception('Room number is required.');

                if ($action === 'add') {
                    $this->room->create($data);
                    $this->message = 'Room added successfully.';
                } else {
                    $id = (int)$_POST['id'];
                    $this->room->update($id, $data);
                    $this->message = 'Room updated successfully.';
                }
            } elseif ($action === 'delete') {
                $id = (int)$_POST['id'];
                if ($this->room->hasReservations($id)) {
                    throw new \Exception('This room has reservations and cannot be deleted.');
                }
                $this->room->delete($id);
                $this->message = 'Room deleted successfully.';
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }
}
