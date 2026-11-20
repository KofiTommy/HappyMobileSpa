<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
SecurityMigration::apply(Database::connection());
echo "Security schema installed; existing booking contact details preserved.\n";
