# Booking journey check — 23 November 2026

## Live website observations

Read-only HTTPS checks against `https://happymobilespa.online` confirmed:

- Homepage, booking and admin login routes return HTTP 200 without the generic application error.
- Six treatments and therapist profile data are present on the booking page.
- Public availability returns HTTP 200: 30 November has no available times; 1 and 2 December each return 24 available times for the checked treatment. These counts are observations, not reserved appointments.
- The public WhatsApp link is present.
- Login response cookies have Secure, HttpOnly and SameSite=Lax; the response includes no-store caching and HSTS.
- Therapist video data is absent from the live booking page. The local video feature still needs its database migration and application deployment.

No live appointments were created or modified, no staff login was attempted, and no customer SMS was sent during these public checks.

## Repairs and isolated verification

- Public availability no longer offers past appointment times. Previously it bypassed the existing past-time check, so an unusable time could be displayed and then rejected when booking.
- Customer details validate the Ghana phone number and trimmed name before proceeding to review. Email length matches the server limit.
- Date-sensitive test appointments are always at least 48 hours ahead, so reminder tests remain valid on Mondays as well as other weekdays.
- The isolated HTTP journey creates an appointment, opens its receipt only in the owning browser, rejects wrong-phone lookup, retrieves it with correct details, reschedules and cancels it. It checks duplicate submissions, confirmation/reminder queue creation and cancellation of pending reminders.
- Existing HTTP checks also cover admin login/session handling, receptionist permissions, image upload/restore/delete and therapist video edits that preserve pictures.
- The browser fixture checks automatic step navigation, watching a therapist video without changing selection, invalid phone correction, Back, review and explicit consent.

Validation passed: 107 application regression tests, 64 isolated HTTP checks, the browser navigation fixture, changed PHP syntax checks and Git whitespace checks. Tests use isolated databases and disabled SMS/payment services; notification tests inject a fake sender.

## Remaining live checks

1. Deploy the repaired application code. Before deploying the therapist video editor, import `database/migrate_therapist_videos_hostinger.sql` into the backed-up live database, or run `php database/migrate_therapist_videos.php` privately from the server terminal. Do not replace the live database or upload folders with local copies.
2. In live **Admin → SMS notifications**, verify that SMS is enabled and the worker heartbeat is recent. The actual live status and last-run time have not been supplied. The earlier local inspection showed SMS disabled with no API key; that does not establish the live settings.
3. Confirm the provider has sufficient credit and the approved sender ID, and that hosting runs `scripts/sms-worker.php` every minute. A worker heartbeat alone does not prove provider acceptance or handset delivery.
4. With an explicitly agreed test phone and appointment, confirm actual delivery of a booking text and a due reminder using the provider history and recipient's handset. No live delivery claim can be made from queue tests alone. Bookings made less than 24 hours ahead receive only a confirmation; pending bookings wait until confirmed.
5. Authenticated staff editing, live payment settlement, live PHP patch status and hosting backup configuration still require access to their private administration or hosting controls.

See `docs/SECURITY.md` for server requirements and the existing security migration order.
