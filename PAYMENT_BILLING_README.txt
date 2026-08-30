HOTEL MANAGEMENT SYSTEM - PAYMENT/BILLING UPGRADE

Added:
1. checkout.php
   - Payment entry
   - Cash/Card/Bank Transfer
   - Saves payment
   - Correctly changes check_ins to Completed
   - Correctly changes reservations to Checked-out
   - Changes room to Cleaning
   - Generates printable receipt

2. billing.php
   - Payment history
   - Search by guest, room, reservation, payment ID
   - Filter by payment method
   - Filter by date range
   - Print any payment bill

3. notifications.php
   - Today's check-ins
   - Today's check-outs
   - Cleaning rooms
   - Maintenance rooms
   - Pending reservations
   - Today's revenue

Important:
- The existing payments table is used; no payment table replacement is needed.
- Invoice number is generated from payment ID/date and does not require an invoice_no database column.
- The SQL adds an optional notifications table, but notifications.php currently uses live database information and does not require that table.

Open:
http://localhost/hotel_management2/checkout.php
http://localhost/hotel_management2/billing.php
http://localhost/hotel_management2/notifications.php
