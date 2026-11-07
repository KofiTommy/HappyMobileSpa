<?php if ($error): ?>
    <div class="admin-error" role="alert"><?= escape($error) ?></div>
<?php endif; ?>
<?php
$navigationFilters = array_intersect_key($filters, array_flip(['id', 'status', 'staff_id', 'search']));
$agendaUrl = static function (string $navigationView, ?string $date = null) use ($navigationFilters): string {
    $query = $navigationFilters;
    $query['view'] = $navigationView;
    if ($date !== null) { $query['date'] = $date; }
    return 'bookings.php?' . http_build_query($query);
};
?>
<section class="admin-section filter-section">
    <nav class="agenda-tabs" aria-label="Appointment agenda view">
        <a href="<?= escape($agendaUrl('all')) ?>" <?= $view === 'all' ? 'aria-current="page"' : '' ?>>All appointments</a>
        <a href="<?= escape($agendaUrl('day', $agendaDate)) ?>" <?= $view === 'day' ? 'aria-current="page"' : '' ?>>Day</a>
        <a href="<?= escape($agendaUrl('week', $agendaDate)) ?>" <?= $view === 'week' ? 'aria-current="page"' : '' ?>>Week</a>
    </nav>
    <?php if ($view === 'day'): ?>
        <form method="get" class="agenda-date-form">
            <?php foreach ($navigationFilters as $name => $value): ?>
                <input type="hidden" name="<?= escape($name) ?>" value="<?= escape($value) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="view" value="day">
            <label>Agenda date<input type="date" name="date" value="<?= escape($agendaDate) ?>"></label>
            <button class="admin-button" type="submit">View day</button>
        </form>
    <?php elseif ($view === 'week'): ?>
        <nav class="agenda-week-navigation" aria-label="Week navigation">
            <a class="admin-button" href="<?= escape($agendaUrl('week', $weekStart->modify('-7 days')->format('Y-m-d'))) ?>">Previous week</a>
            <span><?= $weekStart->format('j M Y') ?> – <?= $weekEnd->format('j M Y') ?></span>
            <a class="admin-button" href="<?= escape($agendaUrl('week', $weekStart->modify('+7 days')->format('Y-m-d'))) ?>">Next week</a>
        </nav>
    <?php endif; ?>
    <form method="get" class="filter-form">
        <?php if ($view !== 'all'): ?><input type="hidden" name="view" value="<?= escape($view) ?>"><?php endif; ?>
        <a class="admin-button" href="bookings.php?date=<?= (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d') ?>">Today</a>
        <label>Date<input type="date" name="date" value="<?= escape($filters['date'] ?? ($view !== 'all' ? $agendaDate : '')) ?>"></label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['pending','confirmed','completed','cancelled','no_show'] as $status): ?>
                    <option value="<?= $status ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Therapist
            <select name="staff_id">
                <option value="">All therapists</option>
                <?php foreach ($staff as $person): ?>
                    <option value="<?= $person['id'] ?>" <?= (string) ($filters['staff_id'] ?? '') === (string) $person['id'] ? 'selected' : '' ?>><?= escape($person['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="filter-search">Search<input name="search" value="<?= escape($filters['search'] ?? '') ?>" placeholder="Reference, client, or phone"></label>
        <button class="admin-button" type="submit">Apply filters</button>
        <a class="clear-filter" href="bookings.php">Clear</a>
    </form>
</section>
<section class="admin-section">
    <div class="section-title">
        <div><p class="admin-eyebrow">BOOKINGS & CALENDAR</p><h2><?= count($bookings) ?> appointments</h2></div>
        <a class="admin-button" href="manual-booking.php">+ New booking</a>
    </div>
    <?php if (!$bookings): ?>
        <div class="admin-empty"><h3>No appointments found.</h3><p>Try clearing the filters or choosing another date.</p></div>
    <?php else: ?>
        <div class="booking-admin-list">
            <?php $currentAgendaDay = null; ?>
            <?php foreach ($bookings as $booking): ?>
                <?php $time = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone()); ?>
                <?php if (in_array($view, ['day', 'week'], true) && $currentAgendaDay !== $time->format('Y-m-d')): ?>
                    <?php if ($currentAgendaDay !== null): ?></section><?php endif; ?>
                    <?php $currentAgendaDay = $time->format('Y-m-d'); ?>
                    <section class="agenda-day"><h2><?= $time->format('l, j F Y') ?></h2>
                <?php endif; ?>
                <article class="appointment-card-admin">
                    <time datetime="<?= escape($booking['slot_start']) ?>"><?= escape($time->format('D, j M · g:i A')) ?></time>
                    <div class="booking-person">
                        <h3><?= escape($booking['customer_name']) ?></h3>
                        <a href="tel:<?= escape($booking['customer_phone']) ?>"><?= escape($booking['customer_phone']) ?></a>
                        <small><?= escape($booking['reference']) ?></small>
                    </div>
                    <div class="booking-service">
                        <strong><?= escape($booking['service_name']) ?></strong>
                        <span><?= escape($booking['staff_name']) ?> · <?= (int) $booking['duration_min'] ?> min</span>
                        <b><?= money($booking['price']) ?> · <?= $booking['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></b>
                    </div>
                    <div class="booking-controls">
                        <?php if ($booking['checked_in_at']): ?>
                            <strong>Client checked in</strong>
                        <?php elseif ($booking['status'] === 'confirmed' && $time->format('Y-m-d') === (new DateTimeImmutable('today', business_timezone()))->format('Y-m-d')): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <button name="action" value="check_in">Check client in</button>
                            </form>
                        <?php endif; ?>
                        <span class="status status-<?= escape($booking['status']) ?>"><?= escape(str_replace('_', ' ', $booking['status'])) ?></span>
                        <?php if (in_array($booking['status'], ['pending','confirmed'], true)): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <select name="status" aria-label="Change appointment status">
                                    <option value="">Change status…</option>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <option value="confirmed">Confirm</option>
                                    <?php else: ?>
                                        <option value="completed">Complete</option>
                                        <option value="no_show">No-show</option>
                                    <?php endif; ?>
                                    <option value="cancelled">Cancel</option>
                                </select>
                                <button type="submit">Apply</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($booking['payment_method'] !== 'paystack_momo'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="action" value="payment">
                                <input type="hidden" name="payment_status" value="<?= $booking['payment_status'] === 'paid' ? 'unpaid' : 'paid' ?>">
                                <button type="submit">Mark <?= $booking['payment_status'] === 'paid' ? 'unpaid' : 'paid' ?></button>
                            </form>
                        <?php else: ?>
                            <small><?= $booking['payment_status'] === 'paid' ? 'Paid via Paystack' : 'Paystack · unpaid' ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ($booking['notes']): ?>
                        <p class="booking-notes"><strong>Client note:</strong> <?= escape($booking['notes']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($currentAgendaDay !== null): ?></section><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
