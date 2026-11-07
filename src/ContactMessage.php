<?php
declare(strict_types=1);

final class ContactMessage
{
    public static function send(array $input, string $senderKey): void
    {
        if (trim((string) ($input['website'] ?? '')) !== '') { return; }
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) { throw new InvalidArgumentException('Enter your name (2–160 characters).'); }
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid email address so we can reply.'); }
        if (mb_strlen($phone) > 30 || ($phone !== '' && !preg_match('/^[+0-9() .-]{7,30}$/D', $phone))) { throw new InvalidArgumentException('Enter a valid phone number or leave it blank.'); }
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 160) { throw new InvalidArgumentException('Enter a subject (2–160 characters).'); }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) { throw new InvalidArgumentException('Your message must be between 10 and 5000 characters.'); }
        RateLimit::check('contact-message', $senderKey, 5, 3600);
        Database::connection()->prepare("INSERT INTO contact_messages (name,email,phone,subject,message,status,created_at) VALUES (?,?,?,?,?,'new',?)")
            ->execute([$name, $email, $phone, $subject, $message, gmdate('Y-m-d H:i:s')]);
    }

    public static function setStatus(array $actor, int $id, string $status): void
    {
        Auth::assertAdmin($actor);
        if (!in_array($status, ['new','read','replied','archived'], true)) { throw new InvalidArgumentException('Choose a valid message status.'); }
        $query = Database::connection()->prepare('SELECT id FROM contact_messages WHERE id=?');
        $query->execute([$id]);
        if (!$query->fetch()) { throw new DomainException('Message not found.'); }
        Database::connection()->prepare('UPDATE contact_messages SET status=? WHERE id=?')->execute([$status, $id]);
    }

    public static function inbox(string $status, int $page): array
    {
        if (!in_array($status, ['', 'new','read','replied','archived'], true)) { $status = ''; }
        $where = $status === '' ? '' : ' WHERE status=?';
        $params = $status === '' ? [] : [$status];
        $count = Database::connection()->prepare('SELECT COUNT(*) FROM contact_messages' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min(max(1, $page), $pages);
        $query = Database::connection()->prepare('SELECT * FROM contact_messages' . $where . ' ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
        $query->execute($params);
        return ['messages'=>$query->fetchAll(), 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'status'=>$status];
    }
}
