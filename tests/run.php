<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$tests = array_filter(
    get_defined_functions()['user'],
    fn (string $name): bool => str_starts_with($name, 'test_')
);
$failed = 0;
foreach ($tests as $test) {
    try {
        $test();
        echo "PASS $test\n";
    } catch (Throwable $error) {
        $failed++;
        echo "FAIL $test: {$error->getMessage()}\n";
    }
}
exit($failed ? 1 : 0);
