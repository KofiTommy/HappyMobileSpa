<section class="manager-insights" aria-labelledby="manager-insights-title">
    <div class="section-title manager-insights-heading">
        <div><p class="admin-eyebrow">MANAGEMENT INSIGHTS</p><h2 id="manager-insights-title">See how the business is performing</h2><p>Live trends from appointments<?= $admin['role'] === 'admin' ? ', payments, payroll, and expenses' : ' and the appointment calendar' ?>.</p></div>
    </div>
    <div class="manager-chart-grid">
        <section class="admin-section manager-chart-card">
            <div class="section-title"><div><p class="admin-eyebrow">LAST 14 DAYS</p><h3>Appointment activity</h3><p>Booked appointments, excluding cancellations</p></div><span class="chart-total"><?= (int) $charts['trend_total'] ?><small>appointments</small></span></div>
            <?php
            $trendMaximum = max(1, ...array_column($charts['trend'], 'count'));
            $trendScale = max(4, (int) (ceil($trendMaximum / 4) * 4));
            $trendPoints = [];
            $trendCoordinates = [];
            $trendCount = count($charts['trend']);
            foreach ($charts['trend'] as $index => $point) {
                $x = 48 + ($index * (644 / max(1, $trendCount - 1)));
                $y = 190 - (((int) $point['count'] / $trendScale) * 150);
                $trendPoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
                $trendCoordinates[] = [$x, $y, $point];
            }
            ?>
            <div class="chart-canvas">
                <svg class="manager-line-chart" viewBox="0 0 720 232" role="img" aria-labelledby="booking-trend-chart-title booking-trend-chart-desc">
                    <title id="booking-trend-chart-title">Appointment activity for the last 14 days</title>
                    <desc id="booking-trend-chart-desc"><?= (int) $charts['trend_total'] ?> non-cancelled appointments. Use the daily values in the chart points for details.</desc>
                    <defs><linearGradient id="booking-area-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3A8B68" stop-opacity=".28"></stop><stop offset="100%" stop-color="#3A8B68" stop-opacity=".02"></stop></linearGradient></defs>
                    <?php for ($line = 0; $line <= 4; $line++): $y = 190 - ($line * 37.5); $value = (int) round($trendScale * $line / 4); ?>
                        <line class="chart-grid-line" x1="48" y1="<?= $y ?>" x2="692" y2="<?= $y ?>"></line><text class="chart-axis-value" x="38" y="<?= $y + 4 ?>" text-anchor="end"><?= $value ?></text>
                    <?php endfor; ?>
                    <polygon class="chart-area" points="48,190 <?= escape(implode(' ', $trendPoints)) ?> 692,190"></polygon>
                    <polyline class="chart-line" points="<?= escape(implode(' ', $trendPoints)) ?>"></polyline>
                    <?php foreach ($trendCoordinates as $index => [$x, $y, $point]): ?>
                        <circle tabindex="0" class="chart-point" cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($y, 2, '.', '') ?>" r="4"><title><?= escape($point['label']) ?>: <?= (int) $point['count'] ?> appointments</title></circle>
                        <?php if ($index % 2 === 0 || $index === $trendCount - 1): ?><text class="chart-axis-label" x="<?= number_format($x, 2, '.', '') ?>" y="216" text-anchor="middle"><?= escape($point['short_label']) ?></text><?php endif; ?>
                    <?php endforeach; ?>
                </svg>
            </div>
            <p class="chart-footnote">Dates are shown in Ghana time. Hover or focus on chart points for daily totals.</p>
        </section>

        <section class="admin-section manager-chart-card">
            <div class="section-title"><div><p class="admin-eyebrow"><?= escape(strtoupper($charts['month_label'])) ?></p><h3>Appointment status</h3><p>Current-month booking mix</p></div></div>
            <?php
            $statusLabels = ['confirmed' => 'Confirmed', 'pending' => 'Pending', 'completed' => 'Completed', 'no_show' => 'No-show', 'cancelled' => 'Cancelled'];
            $statusColors = ['confirmed' => '#247052', 'pending' => '#D4A044', 'completed' => '#72A98B', 'no_show' => '#B85C68', 'cancelled' => '#A8B0AA'];
            $gradient = [];
            $cursor = 0.0;
            foreach ($statusLabels as $status => $label) {
                if (!$charts['statuses'][$status]) { continue; }
                $end = $cursor + ($charts['statuses'][$status] / max(1, $charts['status_total']) * 100);
                $gradient[] = $statusColors[$status] . ' ' . number_format($cursor, 2, '.', '') . '% ' . number_format($end, 2, '.', '') . '%';
                $cursor = $end;
            }
            $statusDescription = [];
            foreach ($statusLabels as $status => $label) { $statusDescription[] = $label . ': ' . $charts['statuses'][$status]; }
            ?>
            <div class="status-chart-layout">
                <div class="status-donut<?= !$gradient ? ' is-empty' : '' ?>" style="<?= $gradient ? 'background:conic-gradient(' . escape(implode(',', $gradient)) . ')' : '' ?>" role="img" aria-label="<?= escape(implode('. ', $statusDescription)) ?>"><div><strong><?= (int) $charts['status_total'] ?></strong><span>total</span></div></div>
                <ul class="chart-legend status-legend">
                    <?php foreach ($statusLabels as $status => $label): $count = (int) $charts['statuses'][$status]; $percentage = $charts['status_total'] ? round($count / $charts['status_total'] * 100) : 0; ?>
                        <li><span class="legend-swatch" style="--swatch:<?= $statusColors[$status] ?>"></span><span><?= $label ?></span><strong><?= $count ?></strong><small><?= $percentage ?>%</small></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <?php if ($admin['role'] === 'admin' && $charts['cash']): ?>
            <section class="admin-section manager-chart-card">
                <div class="section-title"><div><p class="admin-eyebrow">LAST 6 MONTHS</p><h3>Income vs spending</h3><p>Money received compared with paid salaries and expenses</p></div><a href="finance.php">Open finance →</a></div>
                <?php
                $cashMaximum = 1.0;
                foreach ($charts['cash'] as $cash) { $cashMaximum = max($cashMaximum, (float) $cash['income'], (float) $cash['spending']); }
                $power = 10 ** floor(log10($cashMaximum));
                $fraction = $cashMaximum / $power;
                $niceFraction = $fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10));
                $cashScale = $niceFraction * $power;
                $axisMoney = static function (float $value): string {
                    if ($value >= 1000000) { return '₵' . rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . 'M'; }
                    if ($value >= 1000) { return '₵' . rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'k'; }
                    return '₵' . number_format($value, 0);
                };
                ?>
                <div class="chart-legend chart-legend-inline"><span><i class="legend-swatch" style="--swatch:#247052"></i>Income received</span><span><i class="legend-swatch" style="--swatch:#D4A044"></i>Paid salaries &amp; expenses</span></div>
                <div class="chart-canvas">
                    <svg class="manager-bar-chart" viewBox="0 0 720 256" role="img" aria-labelledby="cash-chart-title cash-chart-desc">
                        <title id="cash-chart-title">Income received versus spending for the last six months</title>
                        <desc id="cash-chart-desc">Grouped monthly bars. Full values are available in each bar label and the Finance area.</desc>
                        <?php for ($line = 0; $line <= 4; $line++): $y = 202 - ($line * 40); $value = $cashScale * $line / 4; ?>
                            <line class="chart-grid-line" x1="62" y1="<?= $y ?>" x2="704" y2="<?= $y ?>"></line><text class="chart-axis-value" x="52" y="<?= $y + 4 ?>" text-anchor="end"><?= escape($axisMoney($value)) ?></text>
                        <?php endfor; ?>
                        <?php foreach ($charts['cash'] as $index => $cash): $x = 74 + ($index * 104); $incomeHeight = ((float) $cash['income'] / $cashScale) * 160; $spendingHeight = ((float) $cash['spending'] / $cashScale) * 160; ?>
                            <rect tabindex="0" class="chart-bar chart-bar-income" x="<?= $x ?>" y="<?= 202 - $incomeHeight ?>" width="30" height="<?= $incomeHeight ?>" rx="5"><title><?= escape($cash['month']) ?> income: <?= money($cash['income']) ?></title></rect>
                            <rect tabindex="0" class="chart-bar chart-bar-spending" x="<?= $x + 34 ?>" y="<?= 202 - $spendingHeight ?>" width="30" height="<?= $spendingHeight ?>" rx="5"><title><?= escape($cash['month']) ?> spending: <?= money($cash['spending']) ?></title></rect>
                            <text class="chart-axis-label" x="<?= $x + 32 ?>" y="229" text-anchor="middle"><?= escape($cash['label']) ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
                <p class="chart-footnote">Only actual payments and paid Finance records are included. Pending values are excluded.</p>
            </section>
        <?php elseif ($admin['role'] === 'admin' && empty($charts['finance_ready'])): ?>
            <section class="admin-section manager-chart-card finance-setup-card">
                <p class="admin-eyebrow">FINANCE SETUP REQUIRED</p>
                <h3>Finance charts are not connected yet</h3>
                <p>Your booking dashboard is still available. Run the Finance database migration to enable income, salary, and expense charts. Existing appointment records are not affected.</p>
            </section>
        <?php endif; ?>

        <section class="admin-section manager-chart-card">
            <div class="section-title"><div><p class="admin-eyebrow"><?= escape(strtoupper($charts['month_label'])) ?></p><h3>Popular treatments</h3><p>Most-booked services, excluding cancellations</p></div><a href="bookings.php">View bookings →</a></div>
            <?php if (!$charts['services']): ?>
                <div class="admin-empty chart-empty"><h3>No treatment data yet</h3><p>Service rankings will appear as appointments are booked this month.</p></div>
            <?php else: $serviceMaximum = max(1, ...array_column($charts['services'], 'appointments')); ?>
                <ol class="service-ranking">
                    <?php foreach ($charts['services'] as $index => $service): $width = $service['appointments'] / $serviceMaximum * 100; ?>
                        <li><div class="service-rank-number"><?= $index + 1 ?></div><div class="service-rank-content"><div><strong><?= escape($service['name']) ?></strong><span><?= (int) $service['appointments'] ?> <?= $service['appointments'] === 1 ? 'booking' : 'bookings' ?></span></div><div class="service-bar-track" aria-hidden="true"><span style="width:<?= number_format($width, 2, '.', '') ?>%"></span></div></div></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</section>
