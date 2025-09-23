<?php
require_once __DIR__ . '/../includes/vite_helper.php';
$page = $page ?? 'home';
$title = $title ?? '__PROJECT_NAME__';
$isAdmin = $isAdmin ?? false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <?php echo vite('src/js/app.js'); ?>
</head>
<body data-page="<?= htmlspecialchars($page) ?>" data-path="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>" data-is-admin="<?= $isAdmin ? '1' : '0' ?>">
<header class="site-header universal-page-header">
  <div class="inner">
    <a href="/" class="brand">__PROJECT_NAME__</a>
  </div>
</header>
