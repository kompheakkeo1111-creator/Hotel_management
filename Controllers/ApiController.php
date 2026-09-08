<?php
namespace App\Controllers;

use App\Core\Controller;
use PDO;

class ApiController extends Controller
{
    public $authRequired = false;

    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * GET ?r=api/rooms
     * Returns all room types with their available rooms count.
     */
    public function roomsAction()
    {
        $stmt = $this->db->query("
            SELECT rt.id, rt.type_name, rt.description, rt.price_per_night, rt.capacity,
                   COUNT(r.id) as total_rooms,
                   SUM(CASE WHEN r.status = 'Available' THEN 1 ELSE 0 END) as available_rooms
            FROM room_types rt
            LEFT JOIN rooms r ON r.room_type_id = rt.id
            GROUP BY rt.id
            ORDER BY rt.price_per_night ASC
        ");
        $roomTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roomTypes as &$type) {
            $type['price_per_night'] = (float) $type['price_per_night'];
            $type['total_rooms'] = (int) $type['total_rooms'];
            $type['available_rooms'] = (int) $type['available_rooms'];
            $type['image'] = $this->getRoomTypeImage($type['type_name']);
            if (empty($type['description'])) {
                $type['description'] = $this->getRoomDescription($type['type_name'], $type['capacity']);
            }
        }

        $this->jsonResponse(['success' => true, 'data' => $roomTypes]);
    }

    /**
     * GET ?r=api/rooms/view&id=X
     * Returns a single room type with its rooms.
     */
    public function viewAction()
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid room type ID'], 400);
        }

        $stmt = $this->db->prepare("SELECT * FROM room_types WHERE id = ?");
        $stmt->execute([$id]);
        $roomType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$roomType) {
            $this->jsonResponse(['success' => false, 'message' => 'Room type not found'], 404);
        }

        $roomType['price_per_night'] = (float) $roomType['price_per_night'];
        $roomType['image'] = $this->getRoomTypeImage($roomType['type_name']);

        $stmt = $this->db->prepare("SELECT id, room_number, floor, capacity, price_per_night, status FROM rooms WHERE room_type_id = ? ORDER BY room_number");
        $stmt->execute([$id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $room['price_per_night'] = (float) $room['price_per_night'];
        }

        $roomType['rooms'] = $rooms;
        $this->jsonResponse(['success' => true, 'data' => $roomType]);
    }

    /**
     * GET ?r=api/available-rooms&check_in=YYYY-MM-DD&check_out=YYYY-MM-DD&guests=N
     * Returns rooms available for the given dates.
     */
    public function availableRoomsAction()
    {
        $checkIn = $_GET['check_in'] ?? '';
        $checkOut = $_GET['check_out'] ?? '';
        $guests = (int) ($_GET['guests'] ?? 1);

        if (empty($checkIn) || empty($checkOut)) {
            $this->jsonResponse(['success' => false, 'message' => 'check_in and check_out dates are required'], 400);
        }

        if (strtotime($checkOut) <= strtotime($checkIn)) {
            $this->jsonResponse(['success' => false, 'message' => 'check_out must be after check_in'], 400);
        }

        $stmt = $this->db->prepare("
            SELECT r.id, r.room_number, r.floor, r.capacity, r.price_per_night, r.description, r.image,
                   rt.type_name,
                   (SELECT COUNT(*) FROM reservations res
                    WHERE res.room_id = r.id
                    AND res.status IN ('Pending','Confirmed')
                    AND res.check_in_date < ? AND res.check_out_date > ?
                   ) as reserved,
                   (SELECT COUNT(*) FROM check_ins ci
                    WHERE ci.room_id = r.id
                    AND ci.status = 'Active'
                    AND ci.expected_check_out > ?
                   ) as occupied
            FROM rooms r
            JOIN room_types rt ON r.room_type_id = rt.id
            WHERE r.status NOT IN ('Maintenance')
            AND r.capacity >= ?
            ORDER BY r.price_per_night ASC
        ");
        $stmt->execute([$checkOut, $checkIn, $checkIn, $guests]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available = [];
        foreach ($rooms as $room) {
            if ($room['reserved'] == 0 && $room['occupied'] == 0) {
                $room['price_per_night'] = (float) $room['price_per_night'];
                unset($room['reserved'], $room['occupied']);
                $available[] = $room;
            }
        }

        $this->jsonResponse([
            'success' => true,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => $guests,
            'data' => $available
        ]);
    }

    /**
     * POST ?r=api/contact
     * Body: { full_name, email, phone, subject, message }
     */
    public function contactAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');

        if (empty($fullName) || empty($email) || empty($subject) || empty($message)) {
            $this->jsonResponse(['success' => false, 'message' => 'full_name, email, subject, and message are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid email address'], 400);
        }

        $stmt = $this->db->prepare("INSERT INTO contacts (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$fullName, $email, $phone, $subject, $message]);

        $this->jsonResponse(['success' => true, 'message' => 'Thank you! Your message has been sent.']);
    }

    /**
     * POST ?r=api/reservation
     * Body: { room_type_id, check_in_date, check_out_date, full_name, email, phone, guests, special_requests }
     */
    public function reservationAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $roomTypeId = (int) ($input['room_type_id'] ?? 0);
        $checkIn = trim($input['check_in_date'] ?? '');
        $checkOut = trim($input['check_out_date'] ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $numGuests = (int) ($input['guests'] ?? 1);
        $specialRequests = trim($input['special_requests'] ?? '');

        if ($roomTypeId <= 0 || empty($checkIn) || empty($checkOut) || empty($fullName) || empty($email) || empty($phone)) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        if (strtotime($checkOut) <= strtotime($checkIn)) {
            $this->jsonResponse(['success' => false, 'message' => 'check_out must be after check_in'], 400);
        }

        // Find an available room
        $stmt = $this->db->prepare("
            SELECT r.id, r.price_per_night
            FROM rooms r
            WHERE r.room_type_id = ? AND r.status NOT IN ('Maintenance','Cleaning')
            AND r.capacity >= ?
            AND NOT EXISTS (
                SELECT 1 FROM reservations res
                WHERE res.room_id = r.id AND res.status IN ('Pending','Confirmed')
                AND res.check_in_date < ? AND res.check_out_date > ?
            )
            AND NOT EXISTS (
                SELECT 1 FROM check_ins ci
                WHERE ci.room_id = r.id AND ci.status = 'Active'
                AND ci.expected_check_out > ?
            )
            LIMIT 1
        ");
        $stmt->execute([$roomTypeId, $numGuests, $checkOut, $checkIn, $checkIn]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            $this->jsonResponse(['success' => false, 'message' => 'No rooms available for the selected dates'], 400);
        }

        // Create or find guest
        $stmt = $this->db->prepare("SELECT id, full_name FROM guests WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($guest) {
            $guestId = $guest['id'];
            if ($guest['full_name'] !== $fullName) {
                $stmt = $this->db->prepare("UPDATE guests SET full_name = ? WHERE id = ?");
                $stmt->execute([$fullName, $guestId]);
            }
        } else {
            $stmt = $this->db->prepare("INSERT INTO guests (full_name, email, phone, identification_number) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fullName, $email, $phone, 'PENDING-' . rand(1000, 9999)]);
            $guestId = $this->db->lastInsertId();
        }

        // Calculate total
        $nights = max(1, (int) ((strtotime($checkOut) - strtotime($checkIn)) / 86400));
        $total = round($nights * (float) $room['price_per_night'], 2);

        // Create reservation
        $resNumber = 'RES-' . date('Ymd') . '-' . rand(1000, 9999);
        $stmt = $this->db->prepare("
            INSERT INTO reservations (reservation_number, guest_id, room_id, check_in_date, check_out_date, number_of_guests, status, special_requests, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
        ");
        $stmt->execute([$resNumber, $guestId, $room['id'], $checkIn, $checkOut, $numGuests, $specialRequests, $total]);

        $this->jsonResponse([
            'success' => true,
            'message' => 'Reservation created successfully!',
            'data' => [
                'reservation_number' => $resNumber,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'total' => $total
            ]
        ]);
    }

    /**
     * GET ?r=api/settings
     * Returns hotel settings (public info).
     */
    public function settingsAction()
    {
        $settings = getSystemSettings();
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'hotel_name' => $settings['hotel_name'] ?? 'Arizu Arimato',
                'hotel_address' => $settings['hotel_address'] ?? 'Siem Reap, Cambodia',
                'hotel_phone' => $settings['hotel_phone'] ?? '+855 12 345 678',
                'hotel_email' => $settings['hotel_email'] ?? 'info@arizuhotel.com',
                'currency' => $settings['currency'] ?? 'USD',
            ]
        ]);
    }

    /**
     * POST ?r=api/addRoom
     * Body: { type_name, description, price_per_night, capacity, room_number, floor }
     */
    public function addRoomAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $typeName = trim($input['type_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $price = (float) ($input['price_per_night'] ?? 0);
        $capacity = (int) ($input['capacity'] ?? 2);
        $roomNumber = trim($input['room_number'] ?? '');
        $floor = (int) ($input['floor'] ?? 1);

        if (empty($typeName) || $price <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Room type name and price are required'], 400);
        }

        // Check if room type exists, if not create it
        $stmt = $this->db->prepare("SELECT id FROM room_types WHERE type_name = ?");
        $stmt->execute([$typeName]);
        $roomType = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($roomType) {
            $typeId = $roomType['id'];
        } else {
            $stmt = $this->db->prepare("INSERT INTO room_types (type_name, description, price_per_night, capacity) VALUES (?, ?, ?, ?)");
            $stmt->execute([$typeName, $description ?: $this->getRoomDescription($typeName, $capacity), $price, $capacity]);
            $typeId = $this->db->lastInsertId();
        }

        // Add room if room_number provided
        if (!empty($roomNumber)) {
            $stmt = $this->db->prepare("SELECT id FROM rooms WHERE room_number = ?");
            $stmt->execute([$roomNumber]);
            if ($stmt->fetch()) {
                $this->jsonResponse(['success' => false, 'message' => "Room number $roomNumber already exists"], 400);
            }
            $stmt = $this->db->prepare("INSERT INTO rooms (room_number, room_type_id, floor, capacity, price_per_night, status) VALUES (?, ?, ?, ?, ?, 'Available')");
            $stmt->execute([$roomNumber, $typeId, $floor, $capacity, $price]);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Room added successfully!',
            'data' => ['type_id' => $typeId, 'type_name' => $typeName, 'price' => $price]
        ]);
    }

    /**
     * POST ?r=api/editRoom
     * Body: { id, type_name, description, price_per_night, capacity }
     */
    public function editRoomAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $id = (int) ($input['id'] ?? 0);
        $typeName = trim($input['type_name'] ?? '');
        $description = trim($input['description'] ?? '');
        $price = (float) ($input['price_per_night'] ?? 0);
        $capacity = (int) ($input['capacity'] ?? 2);

        if ($id <= 0 || empty($typeName) || $price <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        $stmt = $this->db->prepare("UPDATE room_types SET type_name=?, description=?, price_per_night=?, capacity=? WHERE id=?");
        $stmt->execute([$typeName, $description, $price, $capacity, $id]);

        // Update all rooms of this type
        $stmt = $this->db->prepare("UPDATE rooms SET price_per_night=?, capacity=? WHERE room_type_id=?");
        $stmt->execute([$price, $capacity, $id]);

        $this->jsonResponse(['success' => true, 'message' => 'Room type updated!']);
    }

    /**
     * POST ?r=api/deleteRoom
     * Body: { id }
     */
    public function deleteRoomAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'POST required'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid ID'], 400);
        }

        // Check if rooms exist for this type
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $this->jsonResponse(['success' => false, 'message' => "Cannot delete: $count room(s) still use this type. Delete rooms first."], 400);
        }

        $stmt = $this->db->prepare("DELETE FROM room_types WHERE id = ?");
        $stmt->execute([$id]);

        $this->jsonResponse(['success' => true, 'message' => 'Room type deleted!']);
    }

    /**
     * Map room type names to frontend images.
     */
    private function getRoomTypeImage($typeName)
    {
        $lower = strtolower($typeName);
        if (strpos($lower, 'vip') !== false || strpos($lower, 'presidential') !== false) {
            return 'images/room-vip.jpg';
        }
        if (strpos($lower, 'suite') !== false || strpos($lower, 'family') !== false) {
            return 'images/room-suite.jpg';
        }
        if (strpos($lower, 'premier') !== false || strpos($lower, 'deluxe') !== false) {
            return 'images/room-deluxe.jpg';
        }
        return 'images/room-deluxe.jpg';
    }

    /**
     * Get default description for room types.
     */
    private function getRoomDescription($typeName, $capacity)
    {
        $lower = strtolower($typeName);
        if (strpos($lower, 'vip') !== false) {
            return 'Full luxury apartment with kitchen, dining area, living room, and separate bedroom. Perfect for families.';
        }
        if (strpos($lower, 'family') !== false) {
            return 'Spacious family room with multiple beds, garden views, and en-suite bathroom. Ideal for families.';
        }
        if (strpos($lower, 'suite') !== false) {
            return 'Luxurious suite with handcrafted wooden furniture, en-suite bathroom with bathtub, and elegant decor.';
        }
        if (strpos($lower, 'premier') !== false) {
            return 'Premium deluxe room with king bed, private balcony, city views, and luxurious bathroom with rain shower.';
        }
        if (strpos($lower, 'deluxe') !== false) {
            return 'Elegant room featuring a comfortable bed, modern amenities, and colonial-style decor.';
        }
        if (strpos($lower, 'sweet') !== false) {
            return 'Cozy and romantic room perfect for couples, with queen bed and beautiful interior design.';
        }
        return 'Comfortable room with modern amenities and elegant colonial-style decor.';
    }
}
