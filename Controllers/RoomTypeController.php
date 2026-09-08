<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\RoomType;

class RoomTypeController extends Controller
{
    protected $active = 'room_types';

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator', 'Receptionist', 'Manager']);
        $this->model = new RoomType();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $roomTypes = $this->model->all();

        $this->view('room_types/index', [
            'roomTypes' => $roomTypes,
            'message'   => $this->message ?? '',
            'error'     => $this->error ?? '',
        ], 'room_types', 'Room Types');
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'edit') {
                $type_name   = trim($_POST['type_name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price       = (float)($_POST['price_per_night'] ?? 0);
                $capacity    = (int)($_POST['capacity'] ?? 0);

                if ($type_name === '' || $capacity < 1) {
                    throw new \Exception('Type name and valid capacity are required.');
                }

                if ($action === 'add') {
                    $this->model->create($type_name, $description, $price, $capacity);
                    $this->message = 'Room type added successfully.';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $this->model->update($id, $type_name, $description, $price, $capacity);
                    $this->message = 'Room type updated successfully.';
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $this->model->delete($id);
                $this->message = 'Room type deleted successfully.';
            }
        } catch (\Exception $e) {
            $this->error = 'Error: ' . $e->getMessage();
        }
    }
}
