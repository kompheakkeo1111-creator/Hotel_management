<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\CheckIn;
use App\Models\Reservation;
use App\Models\Guest;
use App\Models\Room;

class CheckInController extends Controller
{
    protected $active = 'checkin';

    public function __construct()
    {
        $this->loginRequired();
        $this->checkIn = new CheckIn();
        $this->reservation = new Reservation();
        $this->guest = new Guest();
        $this->room = new Room();
    }

    public function indexAction()
    {
        $db = getDB();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'checkin') {
                    $resId = (int)($_POST['reservation_id'] ?? 0);
                    $db->beginTransaction();
                    $stmt = $db->prepare("SELECT * FROM reservations WHERE id=? AND status IN ('Pending','Confirmed')");
                    $stmt->execute([$resId]);
                    $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (!$res) throw new \Exception('Reservation not found or not eligible for check-in.');
                    if (!$res['room_id']) throw new \Exception('Reservation has no room assigned.');
                    if ($this->checkIn->activeCountForReservation($resId) > 0) throw new \Exception('Guest is already checked in.');

                    $this->checkIn->insert($res['id'], $res['guest_id'], $res['room_id'], $res['check_out_date'], $_SESSION['user_id']);
                    $this->room->setStatus($res['room_id'], 'Occupied');
                    $db->commit();
                    $this->message = 'Guest checked in successfully.';
                } elseif ($action === 'walkin') {
                    $guestId = (int)($_POST['guest_id'] ?? 0);
                    $roomId = (int)($_POST['room_id'] ?? 0);
                    $checkIn = $_POST['check_in_date'] ?? date('Y-m-d');
                    $checkOut = $_POST['check_out_date'] ?? '';
                    $numGuests = max(1, (int)($_POST['number_of_guests'] ?? 1));

                    if (!$guestId || !$roomId || !$checkOut) throw new \Exception('Guest, room, and check-out date are required.');
                    if (strtotime($checkOut) <= strtotime($checkIn)) throw new \Exception('Check-out must be after check-in.');
                    if ($this->checkIn->roomCurrentlyOccupied($roomId)) throw new \Exception('This room is currently occupied.');
                    if (!$this->reservation->roomIsAvailable($roomId, $checkIn, $checkOut)) throw new \Exception('This room is already reserved for the selected dates.');

                    $db->beginTransaction();
                    $nightly = $this->reservation->roomNightlyRate($roomId);
                    $nights = max(1, (int)((strtotime($checkOut) - strtotime($checkIn)) / 86400));
                    $total = round($nights * $nightly, 2);
                    $resNumber = generateReservationNumber();
                    $db->prepare("INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, number_of_guests, status, total_amount, created_by)
                                  VALUES (?,?,?,?,?,?,'Confirmed',?,?)")
                        ->execute([$resNumber, $guestId, $roomId, $checkIn, $checkOut, $numGuests, $total, $_SESSION['user_id']]);
                    $reservationId = (int)$db->lastInsertId();
                    $this->checkIn->insert($reservationId, $guestId, $roomId, $checkOut, $_SESSION['user_id']);
                    $this->room->setStatus($roomId, 'Occupied');
                    $db->commit();
                    $this->message = 'Walk-in guest checked in. Reservation: ' . $resNumber;
                }
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $this->error = $e->getMessage();
            }
        }

        $this->view('checkin/index', [
            'ready'     => $this->checkIn->readyReservations(),
            'stays'     => $this->checkIn->activeStays(),
            'guests'    => $this->guest->all(),
            'availRooms'=> $this->availRooms(),
            'message'   => $this->message ?? '',
            'error'     => $this->error ?? '',
        ], 'checkin', 'Check-in');
    }

    private function availRooms()
    {
        $db = getDB();
        return $db->query("SELECT id, room_number, price_per_night FROM rooms WHERE status IN ('Available','Cleaning') ORDER BY room_number")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }
}
