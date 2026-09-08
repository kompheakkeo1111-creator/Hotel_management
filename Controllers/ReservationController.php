<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Reservation;
use App\Models\Guest;
use App\Models\Room;

class ReservationController extends Controller
{
    protected $active = 'reservations';

    private $validStatus = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

    public function __construct()
    {
        $this->loginRequired();
        $this->roleRequired(['Administrator', 'Receptionist', 'Manager']);
        $this->loginRequired();
        $this->reservation = new Reservation();
        $this->guest = new Guest();
        $this->room = new Room();
        $this->settings = getSystemSettings();
        $this->taxRate = (float)($this->settings['tax_rate'] ?? 0);
    }

    public function indexAction()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $fStatus = $_GET['status'] ?? '';
        $q = trim($_GET['q'] ?? '');

        $this->view('reservations/index', [
            'reservations' => $this->reservation->all($fStatus, $q),
            'guests'       => $this->guest->all(),
            'rooms'        => $this->dbAllRooms(),
            'validStatus'  => $this->validStatus,
            'taxRate'      => $this->taxRate,
            'currency'     => $this->settings['currency'] ?? 'USD',
            'fStatus'      => $fStatus,
            'q'            => $q,
            'message'      => $this->message ?? '',
            'error'        => $this->error ?? '',
        ], 'reservations', 'Reservations');
    }

    private function dbAllRooms()
    {
        $db = getDB();
        return $db->query("SELECT id, room_number, price_per_night FROM rooms ORDER BY room_number")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function handlePost()
    {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add') {
                $guestId = (int)($_POST['guest_id'] ?? 0);
                $roomId = (int)($_POST['room_id'] ?? 0);
                $checkIn = $_POST['check_in_date'] ?? '';
                $checkOut = $_POST['check_out_date'] ?? '';
                $numGuests = max(1, (int)($_POST['number_of_guests'] ?? 1));
                $status = in_array($_POST['status'] ?? '', $this->validStatus, true) ? $_POST['status'] : 'Pending';
                $requests = trim($_POST['special_requests'] ?? '');

                if (!$guestId || !$roomId || !$checkIn || !$checkOut) throw new \Exception('Guest, room, and dates are required.');
                if (strtotime($checkOut) <= strtotime($checkIn)) throw new \Exception('Check-out must be after check-in.');
                if (!$this->reservation->roomIsAvailable($roomId, $checkIn, $checkOut)) throw new \Exception('This room is already booked for the selected dates.');

                $nightly = $this->reservation->roomNightlyRate($roomId);
                $nights = $this->computeNights($checkIn, $checkOut);
                $total = $this->computeTotal($nights, $nightly);
                $number = generateReservationNumber();

                $this->reservation->create([
                    'reservation_number' => $number, 'guest_id' => $guestId, 'room_id' => $roomId,
                    'check_in_date' => $checkIn, 'check_out_date' => $checkOut, 'number_of_guests' => $numGuests,
                    'status' => $status, 'special_requests' => $requests, 'total_amount' => $total,
                    'created_by' => $_SESSION['user_id'],
                ]);
                $this->message = 'Reservation ' . $number . ' created.';

            } elseif ($action === 'edit') {
                $id = (int)($_POST['id'] ?? 0);
                $checkIn = $_POST['check_in_date'] ?? '';
                $checkOut = $_POST['check_out_date'] ?? '';
                $numGuests = max(1, (int)($_POST['number_of_guests'] ?? 1));
                $status = in_array($_POST['status'] ?? '', $this->validStatus, true) ? $_POST['status'] : 'Pending';
                $requests = trim($_POST['special_requests'] ?? '');

                if (!$id || !$checkIn || !$checkOut) throw new \Exception('Invalid data.');
                if (strtotime($checkOut) <= strtotime($checkIn)) throw new \Exception('Check-out must be after check-in.');

                $roomId = (int)$this->reservation->roomIdFor($id);
                $nightly = $this->reservation->roomNightlyRate($roomId);
                $nights = $this->computeNights($checkIn, $checkOut);
                $total = $this->computeTotal($nights, $nightly);

                $this->reservation->updateDatesAndStatus($id, $checkIn, $checkOut, $numGuests, $status, $requests, $total);
                $this->message = 'Reservation updated.';

            } elseif ($action === 'cancel') {
                $id = (int)($_POST['id'] ?? 0);
                $this->reservation->setStatus($id, 'Cancelled');
                $rid = (int)$this->reservation->roomIdFor($id);
                if ($rid) $this->reservation->setRoomStatus($rid, 'Available');
                $this->message = 'Reservation cancelled and room released.';

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($this->reservation->hasActiveCheckIn($id)) throw new \Exception('Cannot delete an active stay.');
                $rid = (int)$this->reservation->roomIdFor($id);
                $this->reservation->delete($id);
                if ($rid && !$this->reservation->hasOtherNonCancelledForRoom($rid)) {
                    $this->reservation->setRoomStatus($rid, 'Available');
                }
                $this->message = 'Reservation deleted.';
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    private function computeNights($in, $out)
    {
        return max(1, (int)((strtotime($out) - strtotime($in)) / 86400));
    }

    private function computeTotal($nights, $nightly)
    {
        $subtotal = $nights * $nightly;
        return round($subtotal + ($subtotal * $this->taxRate / 100), 2);
    }
}
