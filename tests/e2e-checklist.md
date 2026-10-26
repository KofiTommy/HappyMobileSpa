# End-to-end verification checklist

Verified against the PHP application on 26 October 2026.

## Public website and booking

- [x] Home page uses the supplied Happy Mobile Spa logo, brand palette, local photography, and database treatments.
- [x] Logo and above-the-fold photography load successfully.
- [x] Public layout has no horizontal page overflow at 360, 390, 640, 768, 1280, and 1440 pixel viewport widths.
- [x] Mobile navigation opens and reports `aria-expanded=true`.
- [x] Visible keyboard-focus styling, native form controls, labels, and reduced-motion CSS are present.
- [x] Guest booking loads real API availability, completes all four steps, and creates a confirmed MariaDB booking.
- [x] Confirmation shows the correct reference, treatment, date, time, therapist, duration, price, and pay-at-spa status.
- [x] Changing treatment clears stale therapist choices and refreshes eligible therapists and slots.
- [x] Overlapping/stale bookings, invalid Ghana phone numbers, invalid email addresses, past times, and cutoff violations are rejected by automated tests.
- [x] Cancellation releases the appointment slot in the availability engine.
- [x] Confirmation has a print action and print-specific styling.

## Customer self-service

- [x] Wrong booking phone returns a generic not-found message.
- [x] Correct reference and phone open a secure management session.
- [x] Rescheduling confirms the selected available time.
- [x] Cancellation asks for confirmation and changes the booking status to cancelled.
- [x] Finished sessions can be cleared by the customer.

## Admin

- [x] Unauthenticated dashboard access redirects to staff login.
- [x] Invalid CSRF token is rejected even with valid login credentials.
- [x] Valid administrator login opens the dashboard; logout protects it again.
- [x] Dashboard, filters, booking details, status forms, and payment forms render.
- [x] A new treatment appears publicly; archiving removes it publicly without deleting booking history.
- [x] Staff profile and weekly schedule forms save, including blank days off.
- [x] Business settings save unchanged values repeatedly under MySQL without duplicate-key errors.
- [x] Reception can create a booking with customer details, therapist, status, and payment state.
- [x] Manual conflict overrides require an explicit option and write an admin audit event; they do not bypass working hours, breaks, holidays, eligibility, or past-date rules.
- [x] Admin management panels collapse to one column at 360 pixels; booking and public pages have no horizontal page overflow.

## Automated release checks

- [x] Main isolated test suite passes with zero failures.
- [x] MySQL unchanged-settings regression passes.
- [x] All PHP files pass syntax checks.
- [x] JavaScript passes Node syntax checking.
- [x] Temporary administrator, bookings, customers, and treatment created by browser verification are removed.

## Production follow-up

- Perform a final keyboard-only and screen-reader review on the deployed production URL.
- Verify the real business phone, email, address/service area, policies, staff biographies, and opening hours before launch.
- Use the print action on a physical or hosted production browser if a branded PDF/print copy is required.
