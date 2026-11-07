<section class="admin-section">
    <h2>Does this client have an appointment today?</h2>
    <form method="get" action="bookings.php" class="filter-form">
        <input type="hidden" name="date" value="<?= escape($today) ?>">
        <label>Client name, phone, or booking reference
            <input name="search" required placeholder="Search today's appointments">
        </label>
        <button class="admin-button">Find appointment</button>
    </form>
</section>

<section class="metric-grid" aria-label="Today's overview">
    <div class="metric">
        <span>Appointments today</span>
        <strong><?= $summary['today'] ?></strong>
        <small><?= escape((new DateTimeImmutable($today))->format('l, j F')) ?></small>
    </div>
    <div class="metric">
        <span>Pending confirmations</span>
        <strong><?= $summary['pending'] ?></strong>
        <small><?= $summary['confirmed'] ?> confirmed and ready</small>
    </div>
    <div class="metric">
        <span>Recorded payments</span>
        <strong><?= money($summary['paid_revenue']) ?></strong>
        <small>Today's paid appointments</small>
    </div>
    <div class="metric">
        <span>Expected appointment value</span>
        <strong><?= money($summary['expected_revenue']) ?></strong>
        <small>Pending and confirmed appointments</small>
    </div>
    <?php if ($summary['unread_messages'] !== null): ?>
        <div class="metric">
            <span>Unread messages</span>
            <strong><?= $summary['unread_messages'] ?></strong>
            <small><a href="messages.php">Open customer messages →</a></small>
        </div>
    <?php endif; ?>
</section>

<div class="dashboard-grid">
    <section class="admin-section">
        <div class="section-title">
            <div>
                <p class="admin-eyebrow">TODAY'S SCHEDULE</p>
                <h2>Appointments at a glance</h2>
            </div>
            <a href="bookings.php?date=<?= $today ?>">Open calendar →</a>
        </div>
        <?php if (!$bookings): ?>
            <div class="admin-empty">
                <h3>A clear day.</h3>
                <p>No appointments are booked for today.</p>
            </div>
        <?php else: ?>
            <div class="schedule-list">
                <?php foreach ($bookings as $booking): $time = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone()); ?>
                    <a class="schedule-row" href="bookings.php?id=<?= $booking['id'] ?>">
                        <time><?= $time->format('g:i A') ?></time>
                        <span class="schedule-client"><strong><?= escape($booking['customer_name']) ?></strong><small><?= escape($booking['reference']) ?></small></span>
                        <span><?= escape($booking['service_name']) ?><small><?= escape($booking['staff_name']) ?></small></span>
                        <span class="status status-<?= escape($booking['status']) ?>"><?= escape(str_replace('_', ' ', $booking['status'])) ?></span>
                        <span class="row-arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section workload-panel">
        <div class="section-title">
            <div>
                <p class="admin-eyebrow">TEAM CAPACITY</p>
                <h2>Therapist workload</h2>
            </div>
        </div>
        <?php if (!$summary['workload']): ?>
            <div class="admin-empty workload-empty">
                <?= ui_icon('calendar') ?>
                <h3>No therapist workload today</h3>
                <p>Appointments assigned today will appear here.</p>
            </div>
        <?php else: ?>
            <ul class="workload-list">
                <?php foreach ($summary['workload'] as $workload): ?>
                    <li class="workload-row">
                        <div><strong><?= escape($workload['staff_name']) ?></strong><small><?= $workload['minutes'] ?> minutes booked</small></div>
                        <span><?= $workload['appointments'] ?> <?= $workload['appointments'] === 1 ? 'appointment' : 'appointments' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="admin-section">
    <div class="section-title">
        <div>
            <p class="admin-eyebrow">COMING UP</p>
            <h2>Next confirmed appointments</h2>
        </div>
        <a href="bookings.php?status=confirmed">All bookings →</a>
    </div>
    <div class="upcoming-grid">
        <?php foreach ($upcoming as $booking): $time = (new DateTimeImmutable($booking['slot_start'], new DateTimeZone('UTC')))->setTimezone(business_timezone()); ?>
            <a href="bookings.php?id=<?= $booking['id'] ?>" class="upcoming-item">
                <time><?= $time->format('D, j M') ?><strong><?= $time->format('g:i A') ?></strong></time>
                <span><?= escape($booking['customer_name']) ?><small><?= escape($booking['service_name']) ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
