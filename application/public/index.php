<?php
/*
 * Yem'kaf Outils : point d'entrée unique. Toutes les adresses passent par ici (voir .htaccess).
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Version de PHP</title><p style="font-family:sans-serif">Yem’kaf Outils a besoin de PHP 8.0 ou plus récent. Version actuelle : '
        . htmlspecialchars(PHP_VERSION) . '. Changez-la dans LWS Panel (Configuration PHP), après avoir vérifié que la boutique et WordPress la supportent.</p>';
    exit;
}

define('YK_ROOT', dirname(__DIR__));
require YK_ROOT . '/app/bootstrap.php';
require YK_ROOT . '/app/modules.php';
require YK_ROOT . '/app/metier.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self'; "
    . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$base = rtrim((string) cfg('base_path'), '/');
if ($base !== '' && str_starts_with($chemin, $base)) $chemin = substr($chemin, strlen($base)) ?: '/';
$chemin = '/' . trim($chemin, '/');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// [motif, fichier, accès libre]
$routes = [
    ['#^/installer$#', 'installer', true],
    ['#^/connexion(?:/(code|lien))?$#', 'connexion', true],
    ['#^/deconnexion$#', 'connexion', false],
    ['#^/$#', 'accueil', false],
    ['#^/taches(?:/(\d+))?(?:/(statut|commentaire|supprimer))?$#', 'taches', false],
    ['#^/journal$#', 'journal', false],
    ['#^/planning$#', 'planning', false],
    ['#^/commandes(?:/(\d+)/(statut|supprimer))?$#', 'commandes', false],
    ['#^/documents(?:/(\d+))?(?:/(version|supprimer))?$#', 'documents', false],
    ['#^/fichier/(\d+)$#', 'documents', false],
    ['#^/simulateurs$#', 'simulateurs', false],
    ['#^/simulateur$#', 'simulateurs', false],
    ['#^/prix$#', 'simulateurs', false],
    ['#^/api/data/([a-z0-9-]+)$#', 'simulateurs', false],
    ['#^/versions(?:/(\d+)/(restaurer|telecharger|supprimer))?$#', 'versions', false],
    ['#^/modules$#', 'modules', false],
    ['#^/module/([a-z0-9-]+)$#', 'modules', false],
    ['#^/equipe(?:/(\d+))?(?:/(appareil)/(\d+))?$#', 'equipe', false],
    ['#^/mes-appareils$#', 'equipe', false],
    ['#^/activite$#', 'activite', false],
    ['#^/parametres$#', 'parametres', false],
];

try {
    if (!config_presente()) {
        $fichier = 'installer';
        $P = [];
    } else {
        $fichier = null;
        foreach ($routes as $r) {
            if (preg_match($r[0], $chemin, $m)) {
                $fichier = $r[1];
                $P = array_slice($m, 1);
                if (!$r[2]) {
                    if (!installee()) rediriger('/installer');
                    exiger_connexion();
                }
                break;
            }
        }
        if ($fichier === null) erreur_page(404, 'Page introuvable', 'Cette adresse n’existe pas.');
        if ($methode === 'POST' && !in_array($fichier, ['installer'], true) && !($fichier === 'connexion' && $chemin !== '/deconnexion')) verifier_csrf();
    }
    require YK_ROOT . '/app/pages/' . $fichier . '.php';
} catch (Throwable $e) {
    journal_erreur($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (cfg('debug')) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); echo $e; exit; }
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><p style="font-family:sans-serif">Une erreur est survenue. Elle a été notée ; réessayez dans un instant.</p>';
}
