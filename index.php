<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/includes/bootstrap.php';
$s = Store::get('settings', []);
$name = htmlspecialchars($s['site_name'] ?? 'OfferHub', ENT_QUOTES, 'UTF-8');
$tag = htmlspecialchars($s['tagline'] ?? 'স্মার্ট অফার ও রিচার্জ', ENT_QUOTES, 'UTF-8');
$ohOffers = catalog_offers();
$ohJson = json_encode($ohOffers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
if ($ohJson === false) {
    $ohJson = '[]';
}
$vCss = (int)@filemtime(__DIR__ . '/assets/app.css');
$vJs = (int)@filemtime(__DIR__ . '/assets/app.js');
$vCat = (int)@filemtime(__DIR__ . '/assets/catalog.js');
?><!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="theme-color" content="#0f766e" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-title" content="<?= $name ?>" />
  <title><?= $name ?> · <?= $tag ?></title>
  <link rel="manifest" href="manifest.json" />
  <link rel="apple-touch-icon" href="assets/icons/icon-192.png" />
  <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png" />
  <link rel="stylesheet" href="assets/app.css?v=<?= $vCss ?>" />
</head>
<body>
  <div id="app"></div>
  <div id="root" style="display:none"></div>
  <script src="assets/catalog.js?v=<?= $vCat ?>"></script>
  <script>if (!Array.isArray(window.__OH_OFFERS) || !window.__OH_OFFERS.length) window.__OH_OFFERS=<?= $ohJson ?>;</script>
  <script src="assets/app.js?v=<?= $vJs ?>"></script>
</body>
</html>
