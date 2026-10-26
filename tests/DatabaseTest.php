<?php
declare(strict_types=1);

function test_database_and_escape(): void
{
    $pdo = Database::connection();
    assert_same(1, $pdo->query('SELECT 1')->fetchColumn(), 'PDO query');
    assert_same('&lt;Book&gt;', escape('<Book>'), 'HTML escaping');
    $value = Database::transaction(fn () => 'committed');
    assert_same('committed', $value, 'transaction return value');
}
