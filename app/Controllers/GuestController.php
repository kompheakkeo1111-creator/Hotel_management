<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Guest;

class GuestController extends Controller
{
    protected $active = 'guests';

    private $validGender = ['Male', 'Female', 'Other'];

    public function __construct()
    {
        $this->loginRequired();
        $this->guest = new Guest();
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $guests = $this->guest->all();

        $this->view('guests/index', [
            'guests'   => $guests,
            'validGender' => $this->validGender,
            'message'  => $this->message ?? '',
            'error'    => $this->error ?? '',
        ], 'guests', 'Guests');
    }

    /**
     * AJAX: return the guest detail + stay history fragment (supports ?id=N).
     */
    public function detailAction()
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            echo '<div class="alert alert-danger">Invalid guest ID</div>';
            return;
        }

        $guest = $this->guest->find($id);
        if (!$guest) {
            echo '<div class="alert alert-danger">Guest not found</div>';
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.reservation_number, r.check_in_date, r.check_out_date, r.status, rm.room_number,
                   (SELECT COUNT(*) FROM payments p WHERE p.reservation_id = r.id) payments
            FROM reservations r
            LEFT JOIN rooms rm ON r.room_id = rm.id
            WHERE r.guest_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$id]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('guests/detail', compact('guest', 'history'), 'guests');
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add' || $action === 'edit') {
                $data = [
                    'full_name'            => trim($_POST['full_name'] ?? ''),
                    'gender'               => in_array($_POST['gender'] ?? '', $this->validGender, true) ? $_POST['gender'] : 'Male',
                    'date_of_birth'        => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                    'nationality'          => trim($_POST['nationality'] ?? ''),
                    'phone'                => trim($_POST['phone'] ?? ''),
                    'email'                => trim($_POST['email'] ?? ''),
                    'address'              => trim($_POST['address'] ?? ''),
                    'identification_number'=> trim($_POST['identification_number'] ?? ''),
                ];

                if ($data['full_name'] === '' || $data['phone'] === '' || $data['identification_number'] === '') {
                    throw new \Exception('Full name, phone, and ID number are required.');
                }

                if ($action === 'add') {
                    $this->guest->create($data);
                    $this->message = 'Guest added successfully.';
                } else {
                    $id = (int)$_POST['id'];
                    $this->guest->update($id, $data);
                    $this->message = 'Guest updated successfully.';
                }
            } elseif ($action === 'delete') {
                $id = (int)$_POST['id'];
                if ($this->guest->hasReservations($id)) {
                    throw new \Exception('Guest has reservations and cannot be deleted.');
                }
                $this->guest->delete($id);
                $this->message = 'Guest deleted successfully.';
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }
}
