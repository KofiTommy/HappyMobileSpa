<?php http_response_code(404); $settings = []; $activePage = ''; $title = 'Page not found'; require __DIR__ . '/../src/bootstrap.php'; ob_start(); ?>
<section class="page-intro section-grid"><p class="eyebrow"><span></span> 404</p><h1>This path leads<br><em>somewhere else.</em></h1><p>The page you requested could not be found.</p><a class="button" href="index.php">Return home</a></section>
<?php $content = (string) ob_get_clean(); require __DIR__ . '/layout.php';
