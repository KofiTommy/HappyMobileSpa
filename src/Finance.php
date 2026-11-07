<?php
declare(strict_types=1);

final class Finance
{
    public static function categories(): array
    {
        return [
            'supplies' => 'Supplies',
            'transport' => 'Transport',
            'rent' => 'Rent',
            'utilities' => 'Utilities',
            'marketing' => 'Marketing',
            'equipment' => 'Equipment',
            'sms_charges' => 'SMS charges',
            'payment_fees' => 'Payment fees',
            'maintenance' => 'Maintenance',
            'other' => 'Other',
        ];
    }

    public static function paymentMethods(): array
    {
        return ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'other' => 'Other'];
    }

    public static function month(?string $month = null): string
    {
        if ($month === null || trim($month) === '') {
            return (new DateTimeImmutable('now', business_timezone()))->format('Y-m');
        }
        $month = trim($month);
        $date = DateTimeImmutable::createFromFormat('!Y-m', $month, business_timezone());
        if (!$date || $date->format('Y-m') !== $month) {
            throw new InvalidArgumentException('Choose a valid finance month.');
        }
        return $month;
    }

    public static function amount(mixed $value, string $label = 'Amount', bool $allowZero = false): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException($label . ' must be a valid amount with at most two decimal places.');
        }
        $cents = self::subunits($value);
        if (!$allowZero && $cents <= 0) {
            throw new InvalidArgumentException($label . ' must be greater than zero.');
        }
        return self::decimal($cents);
    }

    public static function spreadsheetCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^\s*[=+\-@]/', $value) ? "'" . $value : $value;
    }

    public static function salary(int $id): ?array
    {
        $query = Database::connection()->prepare('SELECT sp.*,s.name AS staff_name FROM salary_payments sp JOIN staff s ON s.id=sp.staff_id WHERE sp.id=?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public static function salaries(string $month): array
    {
        $month = self::month($month);
        $query = Database::connection()->prepare('SELECT sp.*,s.name AS staff_name,s.active AS staff_active FROM salary_payments sp JOIN staff s ON s.id=sp.staff_id WHERE sp.salary_month=? ORDER BY s.name,sp.id');
        $query->execute([$month]);
        return $query->fetchAll();
    }

    public static function expense(int $id): ?array
    {
        $query = Database::connection()->prepare('SELECT * FROM expenses WHERE id=?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public static function expenses(string $month): array
    {
        $month = self::month($month);
        $query = Database::connection()->prepare("SELECT * FROM expenses WHERE substr(expense_date,1,7)=? ORDER BY expense_date DESC,id DESC");
        $query->execute([$month]);
        return $query->fetchAll();
    }

    public static function budget(string $month): array
    {
        $month = self::month($month);
        $query = Database::connection()->prepare('SELECT * FROM monthly_budgets WHERE salary_month=?');
        $query->execute([$month]);
        $row = $query->fetch();
        if ($row) {
            $row['expense_budgets'] = array_merge(array_fill_keys(array_keys(self::categories()), '0.00'), self::decodeBudgets($row['expense_budgets']));
            return $row;
        }
        $salaryBudget = Database::connection()->query('SELECT COALESCE(SUM(monthly_salary),0) FROM staff WHERE active=1')->fetchColumn();
        return [
            'salary_month' => $month,
            'expected_income' => '0.00',
            'salary_budget' => self::decimal(self::subunits((string) $salaryBudget)),
            'expense_budgets' => array_fill_keys(array_keys(self::categories()), '0.00'),
            'notes' => '',
        ];
    }

    public static function overview(string $month): array
    {
        $month = self::month($month);
        $salaries = self::salaries($month);
        $expenses = self::expenses($month);
        $budget = self::budget($month);
        $salaryPaid = $salaryOutstanding = $expensesPaid = $expensesPending = 0;
        foreach ($salaries as $salary) {
            if ($salary['status'] === 'paid') { $salaryPaid += self::subunits($salary['net_salary']); }
            if ($salary['status'] === 'pending') { $salaryOutstanding += self::subunits($salary['net_salary']); }
        }
        $categoryPaid = array_fill_keys(array_keys(self::categories()), 0);
        foreach ($expenses as $expense) {
            if ($expense['status'] === 'paid') {
                $expensesPaid += self::subunits($expense['amount']);
                $categoryPaid[$expense['category']] += self::subunits($expense['amount']);
            }
            if ($expense['status'] === 'pending') { $expensesPending += self::subunits($expense['amount']); }
        }
        $income = self::incomeReceived($month);
        $categoryRows = [];
        $expenseBudget = 0;
        foreach (self::categories() as $key => $label) {
            $planned = self::subunits($budget['expense_budgets'][$key] ?? '0');
            $expenseBudget += $planned;
            $categoryRows[] = [
                'category' => $key,
                'label' => $label,
                'budget' => self::decimal($planned),
                'paid' => self::decimal($categoryPaid[$key]),
                'remaining' => self::decimal($planned - $categoryPaid[$key]),
            ];
        }
        return [
            'month' => $month,
            'income_received' => self::decimal($income),
            'expected_appointments' => self::expectedAppointments($month),
            'salary_paid' => self::decimal($salaryPaid),
            'salary_outstanding' => self::decimal($salaryOutstanding),
            'expenses_paid' => self::decimal($expensesPaid),
            'expenses_pending' => self::decimal($expensesPending),
            'remaining_cash' => self::decimal($income - $salaryPaid - $expensesPaid),
            'expected_income_budget' => $budget['expected_income'],
            'salary_budget' => $budget['salary_budget'],
            'expense_budget' => self::decimal($expenseBudget),
            'expense_categories' => $categoryRows,
            'salary_count' => count($salaries),
            'expense_count' => count($expenses),
        ];
    }

    public static function cashTrend(int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $current = new DateTimeImmutable('first day of this month', business_timezone());
        $rows = [];
        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $date = $current->modify("-{$offset} months");
            $month = $date->format('Y-m');
            $salaryPaid = 0;
            foreach (self::salaries($month) as $salary) {
                if ($salary['status'] === 'paid') { $salaryPaid += self::subunits($salary['net_salary']); }
            }
            $expensesPaid = 0;
            foreach (self::expenses($month) as $expense) {
                if ($expense['status'] === 'paid') { $expensesPaid += self::subunits($expense['amount']); }
            }
            $income = self::incomeReceived($month);
            $rows[] = [
                'month' => $month,
                'label' => $date->format('M'),
                'income' => self::decimal($income),
                'salaries' => self::decimal($salaryPaid),
                'expenses' => self::decimal($expensesPaid),
                'spending' => self::decimal($salaryPaid + $expensesPaid),
                'net' => self::decimal($income - $salaryPaid - $expensesPaid),
            ];
        }
        return $rows;
    }

    public static function saveSalary(array $input, int $adminId): int
    {
        $id = !empty($input['id']) ? (int) $input['id'] : null;
        $staffId = filter_var($input['staff_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$staffId) { throw new InvalidArgumentException('Choose a valid staff member.'); }
        $month = self::month((string) ($input['salary_month'] ?? ''));
        $basic = self::amount($input['basic_salary'] ?? '', 'Basic salary', true);
        $bonus = self::amount($input['bonus'] ?? '0', 'Bonus', true);
        $deduction = self::amount($input['deduction'] ?? '0', 'Deduction', true);
        $net = self::subunits($basic) + self::subunits($bonus) - self::subunits($deduction);
        if ($net <= 0) { throw new InvalidArgumentException('Net salary must be greater than zero.'); }
        $status = (string) ($input['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'paid'], true)) { throw new InvalidArgumentException('Choose a valid salary status.'); }
        $paidAt = $status === 'paid' ? self::date((string) ($input['paid_at'] ?? ''), 'Payment date') : null;
        $method = self::paymentMethod((string) ($input['payment_method'] ?? ''), $status === 'paid');
        $reference = self::shortText($input['reference'] ?? '', 'Transaction reference', 120);
        $notes = self::shortText($input['notes'] ?? '', 'Notes', 1000);
        $net = self::decimal($net);
        $now = gmdate('Y-m-d H:i:s');
        try {
            return Database::transaction(function (PDO $pdo) use ($id, $staffId, $month, $basic, $bonus, $deduction, $net, $status, $paidAt, $method, $reference, $notes, $adminId, $now): int {
                self::assertAdministrator($adminId, $pdo);
                $staff = $pdo->prepare('SELECT id FROM staff WHERE id=?');
                $staff->execute([$staffId]);
                if (!$staff->fetch()) { throw new InvalidArgumentException('Staff member not found.'); }
                $existing = null;
                if ($id !== null) {
                    $query = $pdo->prepare('SELECT * FROM salary_payments WHERE id=?' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
                    $query->execute([$id]);
                    $existing = $query->fetch();
                    if (!$existing) { throw new InvalidArgumentException('Salary record not found.'); }
                } else {
                    $query = $pdo->prepare('SELECT id FROM salary_payments WHERE staff_id=? AND salary_month=?');
                    $query->execute([$staffId, $month]);
                    if ($query->fetch()) { throw new DomainException('This worker already has a salary record for the selected month. Edit the existing record instead.'); }
                }
                if ($existing) {
                    $id = (int) $existing['id'];
                    $query = $pdo->prepare('UPDATE salary_payments SET staff_id=?,salary_month=?,basic_salary=?,bonus=?,deduction=?,net_salary=?,status=?,paid_at=?,payment_method=?,reference=?,notes=?,updated_by=?,updated_at=?,cancelled_at=NULL WHERE id=?');
                    $query->execute([$staffId, $month, $basic, $bonus, $deduction, $net, $status, $paidAt, $method, $reference, $notes, $adminId, $now, $id]);
                    $action = $existing['status'] === 'cancelled' ? 'restored' : 'updated';
                } else {
                    $query = $pdo->prepare('INSERT INTO salary_payments (staff_id,salary_month,basic_salary,bonus,deduction,net_salary,status,paid_at,payment_method,reference,notes,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $query->execute([$staffId, $month, $basic, $bonus, $deduction, $net, $status, $paidAt, $method, $reference, $notes, $adminId, $adminId, $now, $now]);
                    $id = (int) $pdo->lastInsertId();
                    $action = 'created';
                }
                self::event($pdo, 'salary', $id, $action, $adminId, ['month' => $month, 'status' => $status, 'net_salary' => $net]);
                return $id;
            });
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') { throw new DomainException('This worker already has a salary record for the selected month.'); }
            throw $error;
        }
    }

    public static function generateSalaries(string $month, int $adminId): int
    {
        $month = self::month($month);
        return Database::transaction(function (PDO $pdo) use ($month, $adminId): int {
            self::assertAdministrator($adminId, $pdo);
            $staff = $pdo->query('SELECT id,monthly_salary FROM staff WHERE active=1 AND monthly_salary>0 ORDER BY name')->fetchAll();
            $existing = $pdo->prepare('SELECT id FROM salary_payments WHERE staff_id=? AND salary_month=?');
            $insert = $pdo->prepare("INSERT INTO salary_payments (staff_id,salary_month,basic_salary,bonus,deduction,net_salary,status,payment_method,reference,notes,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,'0.00','0.00',?,'pending','','','',?,?,?,?)");
            $created = 0;
            $now = gmdate('Y-m-d H:i:s');
            foreach ($staff as $person) {
                $existing->execute([$person['id'], $month]);
                if ($existing->fetch()) { continue; }
                $salary = self::amount($person['monthly_salary'], 'Monthly salary');
                $insert->execute([$person['id'], $month, $salary, $salary, $adminId, $adminId, $now, $now]);
                $id = (int) $pdo->lastInsertId();
                self::event($pdo, 'salary', $id, 'generated', $adminId, ['month' => $month, 'status' => 'pending', 'net_salary' => $salary]);
                $created++;
            }
            return $created;
        });
    }

    public static function saveExpense(array $input, int $adminId, ?array $receiptUpload = null): int
    {
        $id = !empty($input['id']) ? (int) $input['id'] : null;
        $existing = $id !== null ? self::expense($id) : null;
        if ($id !== null && !$existing) { throw new InvalidArgumentException('Expense record not found.'); }
        $expenseDate = self::date((string) ($input['expense_date'] ?? ''), 'Expense date');
        $category = (string) ($input['category'] ?? '');
        if (!isset(self::categories()[$category])) { throw new InvalidArgumentException('Choose a valid expense category.'); }
        $vendor = self::shortText($input['vendor'] ?? '', 'Vendor or payee', 160);
        $description = self::shortText($input['description'] ?? '', 'Description', 500, 2);
        $amount = self::amount($input['amount'] ?? '', 'Expense amount');
        $status = (string) ($input['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'paid'], true)) { throw new InvalidArgumentException('Choose a valid expense status.'); }
        $paidAt = $status === 'paid' ? self::date((string) ($input['paid_at'] ?? $expenseDate), 'Payment date') : null;
        $method = self::paymentMethod((string) ($input['payment_method'] ?? ''), $status === 'paid');
        $reference = self::shortText($input['reference'] ?? '', 'Transaction reference', 120);
        $notes = self::shortText($input['notes'] ?? '', 'Notes', 1000);
        $upload = ExpenseReceipt::upload($receiptUpload);
        $removeReceipt = !empty($input['remove_receipt']);
        $receiptPath = $upload['path'] ?? ($removeReceipt ? null : ($existing['receipt_path'] ?? null));
        $receiptName = $upload['name'] ?? ($removeReceipt ? null : ($existing['receipt_name'] ?? null));
        $now = gmdate('Y-m-d H:i:s');
        try {
            $result = Database::transaction(function (PDO $pdo) use ($id, $existing, $expenseDate, $category, $vendor, $description, $amount, $status, $paidAt, $method, $reference, $notes, $receiptPath, $receiptName, $adminId, $now): int {
                self::assertAdministrator($adminId, $pdo);
                if ($existing) {
                    $query = $pdo->prepare('UPDATE expenses SET expense_date=?,category=?,vendor=?,description=?,amount=?,status=?,paid_at=?,payment_method=?,reference=?,notes=?,receipt_path=?,receipt_name=?,updated_by=?,updated_at=?,cancelled_at=NULL WHERE id=?');
                    $query->execute([$expenseDate, $category, $vendor, $description, $amount, $status, $paidAt, $method, $reference, $notes, $receiptPath, $receiptName, $adminId, $now, $id]);
                    $action = $existing['status'] === 'cancelled' ? 'restored' : 'updated';
                } else {
                    $query = $pdo->prepare('INSERT INTO expenses (expense_date,category,vendor,description,amount,status,paid_at,payment_method,reference,notes,receipt_path,receipt_name,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $query->execute([$expenseDate, $category, $vendor, $description, $amount, $status, $paidAt, $method, $reference, $notes, $receiptPath, $receiptName, $adminId, $adminId, $now, $now]);
                    $id = (int) $pdo->lastInsertId();
                    $action = 'created';
                }
                self::event($pdo, 'expense', (int) $id, $action, $adminId, ['month' => substr($expenseDate, 0, 7), 'status' => $status, 'amount' => $amount]);
                return (int) $id;
            });
        } catch (Throwable $error) {
            ExpenseReceipt::discard($upload['path'] ?? null);
            throw $error;
        }
        if (($existing['receipt_path'] ?? null) !== $receiptPath) { ExpenseReceipt::discard($existing['receipt_path'] ?? null); }
        return $result;
    }

    public static function cancel(string $type, int $id, int $adminId): void
    {
        $tables = ['salary' => 'salary_payments', 'expense' => 'expenses'];
        if (!isset($tables[$type])) { throw new InvalidArgumentException('Invalid finance record type.'); }
        Database::transaction(function (PDO $pdo) use ($tables, $type, $id, $adminId): void {
            self::assertAdministrator($adminId, $pdo);
            $query = $pdo->prepare("SELECT status FROM {$tables[$type]} WHERE id=?" . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $query->execute([$id]);
            $status = $query->fetchColumn();
            if (!$status) { throw new InvalidArgumentException('Finance record not found.'); }
            if ($status === 'cancelled') { throw new DomainException('This finance record is already cancelled.'); }
            $now = gmdate('Y-m-d H:i:s');
            $pdo->prepare("UPDATE {$tables[$type]} SET status='cancelled',cancelled_at=?,updated_by=?,updated_at=? WHERE id=?")
                ->execute([$now, $adminId, $now, $id]);
            self::event($pdo, $type, $id, 'cancelled', $adminId, ['previous_status' => $status]);
        });
    }

    public static function saveBudget(array $input, int $adminId): void
    {
        $month = self::month((string) ($input['salary_month'] ?? ''));
        $expected = self::amount($input['expected_income'] ?? '0', 'Expected income', true);
        $salary = self::amount($input['salary_budget'] ?? '0', 'Salary budget', true);
        $categories = [];
        foreach (self::categories() as $key => $label) {
            $categories[$key] = self::amount($input['expense_budgets'][$key] ?? '0', $label . ' budget', true);
        }
        $notes = self::shortText($input['notes'] ?? '', 'Notes', 1000);
        Database::transaction(function (PDO $pdo) use ($month, $expected, $salary, $categories, $notes, $adminId): void {
            self::assertAdministrator($adminId, $pdo);
            $json = json_encode($categories, JSON_THROW_ON_ERROR);
            $now = gmdate('Y-m-d H:i:s');
            $query = $pdo->prepare('SELECT salary_month FROM monthly_budgets WHERE salary_month=?');
            $query->execute([$month]);
            if ($query->fetch()) {
                $pdo->prepare('UPDATE monthly_budgets SET expected_income=?,salary_budget=?,expense_budgets=?,notes=?,updated_by=?,updated_at=? WHERE salary_month=?')
                    ->execute([$expected, $salary, $json, $notes, $adminId, $now, $month]);
            } else {
                $pdo->prepare('INSERT INTO monthly_budgets (salary_month,expected_income,salary_budget,expense_budgets,notes,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$month, $expected, $salary, $json, $notes, $adminId, $adminId, $now, $now]);
            }
            self::event($pdo, 'budget', 0, 'saved', $adminId, ['month' => $month, 'expected_income' => $expected, 'salary_budget' => $salary, 'expense_budgets' => $categories]);
        });
    }

    public static function events(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return Database::connection()->query("SELECT fe.*,a.username FROM finance_events fe LEFT JOIN admin_users a ON a.id=fe.admin_id ORDER BY fe.id DESC LIMIT {$limit}")->fetchAll();
    }

    private static function assertAdministrator(int $adminId, PDO $pdo): void
    {
        $query = $pdo->prepare('SELECT id,role FROM admin_users WHERE id=? AND active=1');
        $query->execute([$adminId]);
        $admin = $query->fetch();
        if (!$admin) { throw new DomainException('Administrator account not found.'); }
        Auth::assertAdmin($admin);
    }

    private static function event(PDO $pdo, string $type, int $id, string $action, int $adminId, array $details): void
    {
        $pdo->prepare('INSERT INTO finance_events (entity_type,entity_id,action,details,admin_id,created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$type, $id, $action, json_encode($details, JSON_THROW_ON_ERROR), $adminId, gmdate('Y-m-d H:i:s')]);
    }

    private static function incomeReceived(string $month): int
    {
        $pdo = Database::connection();
        $bookings = $pdo->query("SELECT b.id,b.price,b.created_at,p.paid_at FROM bookings b LEFT JOIN paystack_payments p ON p.booking_id=b.id WHERE b.payment_status='paid'")->fetchAll();
        $transitions = [];
        $events = $pdo->query("SELECT booking_id,details,created_at FROM booking_events WHERE event_type='payment_changed' ORDER BY id")->fetchAll();
        foreach ($events as $event) {
            $details = json_decode((string) $event['details'], true);
            if (($details['to'] ?? '') === 'paid' && !isset($transitions[$event['booking_id']])) {
                $transitions[$event['booking_id']] = $event['created_at'];
            }
        }
        $total = 0;
        foreach ($bookings as $booking) {
            $paidAt = $booking['paid_at'] ?: ($transitions[$booking['id']] ?? $booking['created_at']);
            $local = (new DateTimeImmutable((string) $paidAt, new DateTimeZone('UTC')))->setTimezone(business_timezone());
            if ($local->format('Y-m') === $month) { $total += self::subunits($booking['price']); }
        }
        return $total;
    }

    private static function expectedAppointments(string $month): string
    {
        $start = new DateTimeImmutable($month . '-01 00:00:00', business_timezone());
        $end = $start->modify('+1 month');
        $query = Database::connection()->prepare("SELECT COALESCE(SUM(price),0) FROM bookings WHERE status IN ('pending','confirmed') AND slot_start>=? AND slot_start<?");
        $query->execute([$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        return self::decimal(self::subunits((string) $query->fetchColumn()));
    }

    private static function decodeBudgets(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) { return []; }
        $result = [];
        foreach (self::categories() as $key => $label) {
            if (isset($decoded[$key])) { $result[$key] = self::amount($decoded[$key], $label . ' budget', true); }
        }
        return $result;
    }

    private static function paymentMethod(string $method, bool $required): string
    {
        if ($method === '' && !$required) { return ''; }
        if (!isset(self::paymentMethods()[$method])) { throw new InvalidArgumentException('Choose a valid payment method.'); }
        return $method;
    }

    private static function date(string $date, string $label): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, business_timezone());
        if (!$parsed || $parsed->format('Y-m-d') !== $date) { throw new InvalidArgumentException($label . ' must be a valid date.'); }
        return $date;
    }

    private static function shortText(mixed $value, string $label, int $maximum, int $minimum = 0): string
    {
        $value = trim((string) $value);
        $length = mb_strlen($value);
        if ($length < $minimum || $length > $maximum) { throw new InvalidArgumentException($label . " must be between {$minimum} and {$maximum} characters."); }
        return $value;
    }

    private static function subunits(mixed $amount): int
    {
        $amount = number_format((float) $amount, 2, '.', '');
        return (int) round(((float) $amount) * 100);
    }

    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
