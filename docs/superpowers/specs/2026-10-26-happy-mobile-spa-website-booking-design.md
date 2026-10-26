# Happy Mobile Spa Website and Booking System — Design

**Date:** 2026-10-26  
**Status:** Approved design, pending written-spec review  
**Workspace:** `C:\xampp\htdocs\www.happymobilespa.com`

## 1. Purpose

Replace the static Serenity Spa mockups with one branded Happy Mobile Spa website that combines marketing pages, real appointment booking, customer self-service, and a private administration area. The first release accepts payment at the spa rather than online.

## 2. Approved approach

Build an integrated PHP and MySQL application that runs under the existing XAMPP environment. The public website, booking flow, and admin dashboard use the same services and database. A small Node development-server wrapper will preserve `npm run dev -- --host ... --port ...` preview support while forwarding requests to PHP.

Alternatives considered:

- Third-party booking embedded in a website: quicker to start, but adds external branding, subscription costs, and reduced control.
- React frontend with a separate API: unnecessary infrastructure and complexity for this single-business application.

## 3. First-release scope

Included:

- Responsive Home, Services, About, Gallery, and Contact pages.
- Real service, therapist, availability, booking, and customer data.
- Guest booking without customer accounts.
- Booking confirmation, lookup, rescheduling, and cancellation.
- Secure admin login and dashboard.
- Admin calendar, booking management, manual bookings, services, staff, schedules, and business settings.
- Desktop and mobile testing.

Excluded from this release:

- Online MoMo or card payments.
- SMS, WhatsApp, and automated email delivery.
- Customer accounts, loyalty points, gift cards, and multi-branch support.
- Online refunds and accounting integrations.

The database and booking model must not prevent adding online payments or notifications later.

## 4. Brand and visual system

Use the supplied Happy Mobile Massage logo as the primary brand asset in the header, footer, favicon, and confirmation views. Crop and optimize a web-ready copy without altering the original PDF.

Palette:

- Warm ivory `#FAF7F0`: principal background.
- Deep forest green `#173F35`: navigation, headings, primary buttons, and high-contrast surfaces.
- Fresh leaf green `#35B558`: secondary accents drawn from the logo.
- Burgundy `#BE1025`: restrained highlights and selected important details, not body text or large backgrounds.
- Charcoal `#24312C`: body text.

Use elegant display typography with a highly readable interface font, generous whitespace, restrained rounded corners, subtle motion, and locally optimized treatment imagery. Do not publish invented ratings, testimonials, awards, addresses, or other business claims. All imagery must be decorative or appropriately licensed and have meaningful alternative text where informative.

The interface must be responsive, keyboard accessible, usable at 200% zoom, and compliant with WCAG AA colour contrast. Respect reduced-motion preferences.

## 5. Public website

The header keeps **Book Now** prominent on desktop and mobile. Home introduces the business and leads visitors into services or booking. Services display current database-backed names, descriptions, durations, and prices in Ghana cedis. About, Gallery, and Contact use configurable business content. Empty optional contact fields are omitted rather than shown as placeholders.

The footer repeats essential navigation and displays only configured contact and opening-hour information.

## 6. Customer booking flow

1. Select an active service.
2. Select an eligible active therapist or **Any available**.
3. Select a date and an actually available start time.
4. Enter full name, Ghana phone number, optional email, and optional appointment notes.
5. Review the appointment and accept the cancellation policy.
6. Submit and receive a unique booking reference and a saveable/printable confirmation.

Payment is recorded as `pay_at_spa`; successful submission creates a `confirmed` booking. The confirmation clearly states that payment is due at the spa.

Availability is calculated on the server from service duration and buffer, therapist eligibility, therapist working hours and breaks, time off, business opening hours, holidays, and existing active bookings. Taken or unavailable times are never submitted as valid options.

A customer can retrieve a booking using its reference and matching phone number. After verification, the customer can view, reschedule, or cancel it. Rescheduling uses the same availability logic as new bookings. Customer cancellation and rescheduling are allowed up to a configurable cutoff, initially 24 hours before the appointment; later changes direct the customer to contact the spa. Admin users can override the cutoff.

## 7. Admin experience

All admin routes require an authenticated session.

- **Dashboard:** today's appointments, upcoming bookings, and booking totals.
- **Calendar:** day and week views filterable by therapist and status.
- **Bookings:** searchable/filterable list with detail views and actions to confirm, complete, reschedule, cancel, or mark no-show.
- **Manual booking:** receptionist flow for phone customers and walk-ins using the same availability rules, with an admin override for exceptional scheduling.
- **Services:** create/edit/archive services, descriptions, prices, durations, buffers, active state, and eligible therapists.
- **Staff:** create/edit/deactivate therapists and manage working hours, breaks, and time off.
- **Business settings:** opening hours, holidays, contact details, booking cutoff, and cancellation-policy text.

Archiving or deactivating records must not erase historical bookings.

## 8. Architecture and components

Use small, focused PHP components rather than a monolithic page:

- **Front controllers/templates:** public pages, booking routes, customer management routes, and admin routes.
- **Service catalog:** public and admin service queries and validation.
- **Availability service:** the single source of truth for slot calculation and validation.
- **Booking service:** transactional creation, therapist assignment, retrieval, rescheduling, cancellation, and status transitions.
- **Admin authentication:** password verification, session establishment, login throttling, and logout.
- **Business settings:** opening hours, contact information, and policy configuration.
- **Database layer:** PDO with prepared statements and explicit transactions.

Shared layout, form, flash-message, and pagination components keep the interface consistent. Public and admin templates remain separate while using the same design tokens.

## 9. Data model

Use a dedicated MySQL schema named `happy_mobile_spa` with InnoDB tables and UTC storage for timestamps, displayed using the configured business timezone `Africa/Accra`.

Core records:

- `services`: name, slug, description, duration, buffer, price, active/archive state, timestamps.
- `staff`: name, biography, optional photo, active state, timestamps.
- `staff_services`: staff/service eligibility.
- `staff_hours`, `staff_breaks`, `staff_time_off`: therapist availability constraints.
- `business_hours`, `holidays`, `settings`: business scheduling and public contact configuration.
- `customers`: normalized phone, name, optional email, timestamps; no passwords.
- `bookings`: unique reference, customer, service, assigned staff, start/end, price snapshot, status, payment method/status, notes, accepted policy text, timestamps.
- `booking_events`: booking ID, actor type (`customer` or `admin`), optional admin ID, event type, JSON event details, timestamp.
- `admin_users`: username, password hash, active state, last-login timestamp, timestamps.
- `rate_limits`: action, hashed client/key identifier, attempt count, window start, blocked-until timestamp.

Booking statuses are `pending`, `confirmed`, `completed`, `cancelled`, and `no_show`. New online pay-at-spa bookings start as `confirmed`; receptionist-created bookings may start as `pending` or `confirmed`. Payment method is `pay_at_spa` in this release, with payment status `unpaid` or `paid` updated by admin.

Seed the services and prices from the approved mockups as editable demonstration data. Business contact details remain unset until supplied through admin settings.

## 10. Consistency and error handling

Create and reschedule bookings inside database transactions. Lock the relevant therapist/booking rows, re-check server-side availability, and assign **Any available** atomically before committing. A competing request must receive a clear slot-no-longer-available response rather than a duplicate appointment.

Validate all input on the server and repeat essential client validation for convenience. Preserve safe form input after validation failures. Show specific, non-sensitive messages for invalid details, expired management sessions, unavailable slots, and disallowed status transitions.

Use CSRF tokens on state-changing forms, secure session cookies, `password_hash`/`password_verify`, prepared statements, output escaping, login and booking-lookup throttling, and generic lookup failures that do not disclose registered customers. Security headers must deny framing and MIME sniffing. Secrets stay outside web root or in environment configuration and are never committed.

Provide an interactive CLI script to create the first admin password; do not ship a default password.

## 11. Testing and acceptance

Automated tests cover:

- Slot boundaries, buffers, breaks, holidays, time off, and inactive staff/services.
- Any-therapist assignment and overlapping-booking prevention.
- Booking reference/phone verification and management authorization.
- Cancellation cutoff and valid/invalid status transitions.
- Validation, CSRF protection, and archived-record history.

Browser verification covers:

- Complete guest booking and confirmation.
- Lookup, reschedule, and cancellation.
- Admin login, manual booking, service edits, staff schedules, and settings.
- Conflicting submissions and expired/invalid form submissions.
- Mobile and desktop navigation, keyboard operation, focus states, and reduced motion.

The release is accepted when a visitor can discover a service and book a genuine available appointment, the appointment appears immediately in admin, the customer can manage it securely, and schedule/service changes are reflected publicly without double-booking.

## 12. Delivery

Keep the original supplied PDF unchanged. Store optimized web assets locally. Provide database migration/schema setup, seed scripts, admin creation instructions, and a preview command compatible with the workspace. Do not enable external payment or messaging credentials in this release.
