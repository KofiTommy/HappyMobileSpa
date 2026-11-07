<?php
declare(strict_types=1);

final class Gallery
{
    public static function images(bool $hero = false): array
    {
        $rows = Database::connection()->query('SELECT * FROM gallery_images WHERE published=1' . ($hero ? ' AND show_in_hero=1' : '') . ' ORDER BY display_order,id')->fetchAll();
        if ($rows) { return $rows; }
        return self::defaults();
    }

    public static function defaults(): array
    {
        return [
            ['path'=>'assets/images/hero-spa.jpg', 'caption'=>'Thoughtful touch', 'alt_text'=>'A relaxing oil massage treatment'],
            ['path'=>'assets/images/massage-treatment.jpg', 'caption'=>'Simple comforts', 'alt_text'=>'Towels, flowers, and treatment essentials'],
            ['path'=>'assets/images/spa-ritual.jpg', 'caption'=>'Deep restoration', 'alt_text'=>'A restorative hot stone ritual'],
        ];
    }

    public static function manage(array $actor, array $input, ?array $file = null): void
    {
        Auth::assertAdmin($actor);
        $pdo = Database::connection();
        $id = (int) ($input['id'] ?? 0);
        $action = (string) ($input['action'] ?? '');
        $existing = null;
        if ($id) {
            $query = $pdo->prepare('SELECT * FROM gallery_images WHERE id=?');
            $query->execute([$id]);
            $existing = $query->fetch();
            if (!$existing) { throw new DomainException('Gallery photo not found.'); }
        }
        if ($action === 'delete' && $existing) {
            $pdo->prepare('DELETE FROM gallery_images WHERE id=?')->execute([$id]);
            TeamPhoto::discard($existing['path']);
            return;
        }
        if ($action !== 'save') { throw new InvalidArgumentException('Choose a valid gallery action.'); }
        $caption = trim((string) ($input['caption'] ?? ''));
        $alt = trim((string) ($input['alt_text'] ?? ''));
        $order = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($caption === '' || mb_strlen($caption) > 160 || $alt === '' || mb_strlen($alt) > 255) {
            throw new InvalidArgumentException('Enter a caption (up to 160 characters) and image description (up to 255 characters).');
        }
        if ($order === false || $order < 0 || $order > 9999) { throw new InvalidArgumentException('Display order must be between 0 and 9999.'); }
        $uploaded = TeamPhoto::upload($file, 'gallery');
        $path = $uploaded ?? ($existing['path'] ?? null);
        if (!$path) { throw new InvalidArgumentException('Choose a photo to upload.'); }
        $values = [$path, $caption, $alt, !empty($input['published']) ? 1 : 0, !empty($input['show_in_hero']) ? 1 : 0, $order];
        try {
            if ($existing) {
                $pdo->prepare('UPDATE gallery_images SET path=?,caption=?,alt_text=?,published=?,show_in_hero=?,display_order=? WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO gallery_images (path,caption,alt_text,published,show_in_hero,display_order) VALUES (?,?,?,?,?,?)')->execute($values);
            }
        } catch (Throwable $error) {
            TeamPhoto::discard($uploaded);
            throw $error;
        }
        if ($uploaded && $existing) { TeamPhoto::discard($existing['path']); }
    }
}
