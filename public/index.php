<?php
$page = $_GET['page'] ?? 'training';
$allowed = ['training'];
if (!in_array($page, $allowed, true)) { http_response_code(404); $page = 'training'; }
require __DIR__ . '/../app/partials/layout-start.php';
require __DIR__ . '/../app/partials/sidebar.php';
echo '<div class="main-shell">';
require __DIR__ . '/../app/partials/header.php';
require __DIR__ . '/../app/pages/training.php';
echo '</div>';
require __DIR__ . '/../app/partials/layout-end.php';
