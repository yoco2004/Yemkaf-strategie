<?php
// Démarrer depuis le dossier de l’application : php -S 127.0.0.1:8099 -t public outils/routeur-dev.php
// Essais en local uniquement : php -S 127.0.0.1:8099 outils/routeur-dev.php
$f = __DIR__ . '/../public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($f)) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir(__DIR__ . '/../public');
require __DIR__ . '/../public/index.php';
