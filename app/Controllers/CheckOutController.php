<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\CheckIn;

class CheckOutController extends Controller
{
    protected $active = 'checkout';

    private $methods = ['Cash', 'Credit/Debit Card', 'QR Payment', 'Bank Transfer'];
    private $chargeTypes = ['Room Service', 'Laundry', 'Restaurant', 'Mini Bar', 'Damage Fee', 'Other'];

    public function __construct()
    {
        $this->loginRequired();
        $this->checkIn = new CheckIn();
        $this->settings = getSystemSettings();
        $this->taxRate = (float)($this->settings['tax_rate'] ?? 0);
    }

    public function indexAction()
    {
        $db = getDB();
        $receipt = null;
        $message = $this->message ?? '';
        $error = $this->error ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
            $result = $this->processCheckout($_POST);
            $db = getDB(); // helper may open its own connection via computeCheckinBill
            if ($result['error']) {
                $error = $result['error'];
            } else {
                $message = $result['message'];
                $receipt = $result['receipt'];
            }
        }

        $stays = $this->checkIn->activeStaysForCheckout();
        foreach ($stays as &$s) {
            $t = computeCheckinBill(getDB(), (int)$s['id'], $this->taxRate);
            $s['nights'] = $t['nights'] ?? 0;
            $s['room_charge'] = $t['room_charge'] ?? 0;
            $s['tax'] = $t['tax'] ?? 0;
            $s['stay_total'] = $t['total'] ?? 0;
        }
        unset($s);

        $this->view('checkout/index', [
            'stays'    => $stays,
            'methods'  => $this->methods,
            'chargeTypes' => $this->chargeTypes,
            'taxRate'  => $this->taxRate,
            'currency' => $this->settings['currency'] ?? 'USD',
            'hotelName'=> $this->settings['hotel_name'] ?? 'My Hotel',
            'message'  => $message,
            'error'    => $error,
            'receipt'  => $receipt,
        ], 'checkout', 'Check-out & Payment');
    }

    private function processCheckout(array $post)
    {
        $db = getDB();
        $checkInId = (int)($post['check_in_id'] ?? 0);
        $amount = trim($post['amount'] ?? '');
        $method = $post['method'] ?? 'Cash';
        $transactionId = trim($post['transaction_id'] ?? '');
        $addChargeType = $post['charge_type'] ?? '';
        $addChargeDesc = trim($post['charge_desc'] ?? '');
        $addChargeAmt = trim($post['charge_amount'] ?? '');

        if (!in_array($method, $this->methods, true)) $method = 'Cash';
        if ($amount === '' || !is_numeric($amount) || (float)$amount < 0) {
            return ['error' => 'Please enter a valid payment amount.', 'message' => '', 'receipt' => null];
        }

        try {
            $db->beginTransaction();
            $ci = $this->checkIn->checkoutDetail($checkInId);
            if (!$ci) throw new \Exception('Check-in record not found or already checked out.');

            if ($addChargeType !== '' && $addChargeAmt !== '' && is_numeric($addChargeAmt) && (float)$addChargeAmt > 0) {
                $this->checkIn->addExtraCharge($ci['id'], $addChargeType, $addChargeDesc ?: $addChargeType, (float)$addChargeAmt);
                $tot = computeCheckinBill($db, (int)$ci['id'], $this->taxRate);
                $amount = (string)$tot['total'];
            }

            $paymentAmount = (float)$amount;
            $this->checkIn->markCheckedOut($ci['id']);

            if ($ci['reservation_id']) {
                $db->prepare("UPDATE reservations SET status='Completed' WHERE id=?")->execute([$ci['reservation_id']]);
            }
            $db->prepare("UPDATE rooms SET status='Cleaning' WHERE id=?")->execute([$ci['room_id']]);

            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad((string)(int)$ci['id'], 5, '0', STR_PAD_LEFT);
            $paymentId = null;
            if ($paymentAmount > 0) {
                $finalInvoice = $invoiceNo;
                $n = (int)$ci['id'];
                $countStmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE invoice_number=?");
                while (true) {
                    $countStmt->execute([$finalInvoice]);
                    if ((int)$countStmt->fetchColumn() === 0) break;
                    $n++;
                    $finalInvoice = 'INV-' . date('Ymd') . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
                }
                $db->prepare("INSERT INTO payments (reservation_id, check_in_id, amount, payment_method, transaction_id, payment_date, invoice_number)
                              VALUES (?,?,?,?,?,NOW(),?)")
                    ->execute([$ci['reservation_id'], $ci['id'], $paymentAmount, $method, $transactionId ?: null, $finalInvoice]);
                $paymentId = (int)$db->lastInsertId();
                $invoiceNo = $finalInvoice;
            }

            $db->commit();
            return [
                'error' => '',
                'message' => 'Guest checked out successfully. Bill generated.',
                'receipt' => [
                    'invoice_no' => $invoiceNo,
                    'payment_id' => $paymentId,
                    'reservation_number' => $ci['reservation_number'] ?? 'WALK-IN',
                    'guest_name' => $ci['guest_name'],
                    'room_number' => $ci['room_number'],
                    'check_in_time' => $ci['check_in_time'],
                    'checkout_time' => date('Y-m-d H:i:s'),
                    'amount' => $paymentAmount,
                    'method' => $method,
                    'transaction_id' => $transactionId,
                    'status' => $paymentAmount > 0 ? 'PAID' : 'NO PAYMENT',
                ],
            ];
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['error' => 'Error: ' . $e->getMessage(), 'message' => '', 'receipt' => null];
        }
    }
}
