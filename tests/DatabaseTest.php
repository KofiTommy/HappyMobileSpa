<?php
declare(strict_types=1);

function test_database_and_escape(): void
{
    $pdo = Database::connection();
    assert_same(1, $pdo->query('SELECT 1')->fetchColumn(), 'PDO query');
    assert_same('&lt;Book&gt;', escape('<Book>'), 'HTML escaping');
    $value = Database::transaction(fn () => 'committed');
    assert_same('committed', $value, 'transaction return value');

    $source = file_get_contents(APP_ROOT . '/src/Database.php');
    assert_contains('PDO::MYSQL_ATTR_INIT_COMMAND', $source, 'MySQL connection initializes its charset and collation');
    assert_contains('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci', $source, 'connection literals match schema collation');
    assert_contains("str_starts_with(\$dsn, 'mysql:')", $source, 'SQLite test connections remain compatible');
}
