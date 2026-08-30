# Hotel Management System — Design Plan

## 1. Overview

A full hotel management system (HMS) covering the operational lifecycle of a hotel: 
reservations, 
front-desk operations, 
billing, staff/HR, 
housekeeping, and 
reporting. Designed to be built as a web application with a central database and role-based access.

---

## 2. Core Modules

### 2.1 Reservations & Bookings
- Search room availability by date range, room type, occupancy
- Create/modify/cancel bookings
- Walk-in bookings vs. advance bookings
- Booking sources (direct, phone, OTA/agent — optional integration later)
- Booking statuses: Pending, Confirmed, Checked-in, Checked-out, Cancelled, No-show

### 2.2 Front Desk Operations
- Check-in / check-out workflow
- Room assignment (auto or manual)
- Room status board (Vacant, Occupied, Dirty, Clean, Out-of-order, Maintenance)
- Guest folio (running charges during stay)

### 2.3 Room & Inventory Management
- Room types (Single, Double, Suite, etc.) with base pricing
- Individual room records (floor, number, status, amenities)
- Rate plans / seasonal pricing
- Blocking rooms for maintenance

### 2.4 Billing & Invoicing
- Auto-generate charges (room rate, taxes, service charges)
- Add extra charges (minibar, room service, laundry, etc.)
- Multiple payment methods (cash, card, bank transfer)
- Split billing / group billing
- Invoice generation (PDF)
- Refunds and adjustments

### 2.5 Guest Management (CRM)
- Guest profiles (contact info, ID/passport, preferences)
- Stay history
- Loyalty/repeat guest tracking (optional)
- Blacklist flag

### 2.6 Staff & HR
- Staff records, roles, shifts
- Role-based access control (Admin, Manager, Front Desk, Housekeeping, Accountant)
- Attendance (optional, phase 2)

### 2.7 Housekeeping
- Task assignment per room (clean, inspect, maintenance)
- Status updates linked to room status board

### 2.8 Reports & Analytics
- Occupancy rate
- Revenue reports (daily/monthly, by room type)
- ADR (Average Daily Rate) and RevPAR
- Staff performance / task completion
- Export to CSV/PDF

### 2.9 Admin Settings
- Hotel/property info
- Room types & rate configuration
- Tax rules
- User & role management

---

## 3. Data Model (Core Entities)

```
Hotel
 └─ id, name, address, contact_info

RoomType
 └─ id, hotel_id, name, base_price, capacity, amenities

Room
 └─ id, hotel_id, room_type_id, number, floor, status

Guest
 └─ id, name, contact, id_number, address, notes

Reservation
 └─ id, guest_id, room_id, check_in_date, check_out_date,
    status, source, rate_applied, created_at

Folio (Guest Bill)
 └─ id, reservation_id, charges[], payments[], balance

Charge
 └─ id, folio_id, description, amount, tax, date

Payment
 └─ id, folio_id, amount, method, date

Staff
 └─ id, hotel_id, name, role, contact, shift

User
 └─ id, staff_id, username, password_hash, role_permissions

HousekeepingTask
 └─ id, room_id, assigned_to, status, date
```

---

## 4. Suggested Tech Stack

| Layer | Recommendation | Notes |
|---|---|---|
| Frontend | React (with Tailwind) | Fast to build, works well as SPA |
| Backend | Node.js (Express) or Python (FastAPI) | REST API |
| Database | PostgreSQL | Relational data fits bookings/billing well |
| Auth | JWT-based sessions, role-based middleware | |
| PDF/Invoices | PDF generation library on backend | |
| Hosting | Any cloud VM / Docker container | |

*(If you have a stack preference — e.g. Django, PHP/Laravel, or you want it purely as a frontend prototype with mock data — let me know and I'll adjust.)*

---

## 5. Build Phases (Recommended Order)

1. **Phase 1 — Core:** Rooms, Room Types, Reservations, Availability search, Check-in/out, basic Room status board
2. **Phase 2 — Billing:** Folios, charges, payments, invoice generation
3. **Phase 3 — Guests & Staff:** Guest CRM, staff accounts, role-based access
4. **Phase 4 — Housekeeping & Reports:** Task board, occupancy/revenue reports
5. **Phase 5 — Polish:** Dashboards, exports, settings/admin panel

---

## 6. Open Questions Before Building

- Single hotel or multi-property?
- Expected number of rooms (affects UI design for room board)?
- Do you need multi-user login with roles from day one, or single-admin to start?
- Any specific currency/tax rules to bake in?
- Should this run fully in-browser (demo/prototype with mock data) or with a real backend + database?
