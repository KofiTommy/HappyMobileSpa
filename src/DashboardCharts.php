<?php
declare(strict_types=1);

final class DashboardCharts
{
    public static function load(bool $includeFinance): array
    {
        $today = new DateTimeImmutable('today', business_timezone());
        $trendStart = $today->modify('-13 days');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $monthStart->modify('+1 month');
        $queryStart = $trendStart < $monthStart ? $trendStart : $monthStart;
        $utc = new DateTimeZone('UTC');
        $query = Database::connection()->prepare(
            'SELECT b.slot_start,b.status,s.name AS service_name FROM bookings b JOIN services s ON s.id=b.service_id WHERE b.slot_start>=? AND b.slot_start<? ORDER BY b.slot_start'
        );
        $query->execute([
            $queryStart->setTimezone($utc)->format('Y-m-d H:i:s'),
            $monthEnd->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]);
        $cashTrend = [];
        $financeReady = false;
        if ($includeFinance) {
            try {
                $cashTrend = Finance::cashTrend(6);
                $financeReady = true;
            } catch (PDOException $error) {
                if ($error->getCode() !== '42S02') { throw $error; }
            }
        }
        return self::fromRows($query->fetchAll(), $cashTrend, $today, $financeReady);
    }

    public static function fromRows(array $bookings, array $cashTrend, DateTimeImmutable $today, bool $financeReady = true): array
    {
        $today = $today->setTimezone(business_timezone());
        $month = $today->format('Y-m');
        $trend = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $date = $today->modify("-{$offset} days");
            $key = $date->format('Y-m-d');
            $trend[$key] = [
                'date' => $key,
                'label' => $date->format('j M'),
                'short_label' => $date->format('d'),
                'count' => 0,
            ];
        }
        $statuses = array_fill_keys(['confirmed', 'pending', 'completed', 'no_show', 'cancelled'], 0);
        $services = [];
        $utc = new DateTimeZone('UTC');
        foreach ($bookings as $booking) {
            $local = new DateTimeImmutable((string) $booking['slot_start'], $utc);
            $local = $local->setTimezone(business_timezone());
            $date = $local->format('Y-m-d');
            if (isset($trend[$date]) && $booking['status'] !== 'cancelled') {
                $trend[$date]['count']++;
            }
            if ($local->format('Y-m') !== $month) { continue; }
            $status = (string) $booking['status'];
            if (isset($statuses[$status])) { $statuses[$status]++; }
            if ($status === 'cancelled') { continue; }
            $name = (string) $booking['service_name'];
            $services[$name] ??= ['name' => $name, 'appointments' => 0];
            $services[$name]['appointments']++;
        }
        $services = array_values($services);
        usort($services, static function (array $a, array $b): int {
            return $b['appointments'] <=> $a['appointments'] ?: strcmp($a['name'], $b['name']);
        });
        $services = array_slice($services, 0, 5);
        $trend = array_values($trend);
        return [
            'trend' => $trend,
            'trend_total' => array_sum(array_column($trend, 'count')),
            'statuses' => $statuses,
            'status_total' => array_sum($statuses),
            'services' => $services,
            'cash' => $cashTrend,
            'finance_ready' => $financeReady,
            'month_label' => $today->format('F Y'),
        ];
    }
}
