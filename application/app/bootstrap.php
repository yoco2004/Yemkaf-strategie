<?php
/*
 * Yem'kaf Outils : cœur de l'application.
 * Configuration, base de données (MySQL en ligne, SQLite pour les essais), outils, droits, journal d'activité.
 */
declare(strict_types=1);

const YK_VERSION = '1.0.0';
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/schema.php';

// ---------------------------------------------------------------- configuration
function cfg(?string $k = null, $def = null)
{
    static $c = null;
    if ($c === null) {
        $f = YK_ROOT . '/app/config.php';
        $c = is_file($f) ? (require $f) : [];
        if (!is_array($c)) $c = [];
        $c += ['app_url' => '', 'base_path' => '', 'db' => [], 'mail' => [], 'admin_email' => '', 'install_key' => '',
            'max_upload_mo' => 20, 'debug' => false];
    }
    if ($k === null) return $c;
    return $c[$k] ?? $def;
}
function config_presente(): bool { return is_file(YK_ROOT . '/app/config.php') && !empty(cfg('db')['dsn']); }

function stockage(string $sous = ''): string
{
    $base = YK_ROOT . '/storage';
    foreach (['', '/uploads', '/logs'] as $d) {
        if (!is_dir($base . $d)) @mkdir($base . $d, 0750, true);
    }
    $ht = $base . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    return $base . ($sous ? '/' . ltrim($sous, '/') : '');
}
function journal_erreur(string $m): void { @file_put_contents(stockage('logs/erreurs.log'), '[' . date('c') . '] ' . $m . "\n", FILE_APPEND); }

// ---------------------------------------------------------------- base de données
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $d = cfg('db');
    if (empty($d['dsn'])) throw new RuntimeException('Base de données non configurée');
    $pdo = new PDO($d['dsn'], $d['user'] ?? null, $d['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } else {
        $pdo->exec("SET NAMES utf8mb4");
    }
    return $pdo;
}
function driver(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME); }
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute(array_values($p)); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function ins(string $table, array $data): int
{
    $cols = array_keys($data);
    q('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int) db()->lastInsertId();
}
function upd(string $table, array $data, string $where, array $wp = []): void
{
    $set = implode(', ', array_map(function ($c) { return $c . ' = ?'; }, array_keys($data)));
    q('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $wp));
}
function installee(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try { $ok = val("SELECT v FROM settings WHERE k = 'installe'") !== null; } catch (Throwable $e) { $ok = false; }
    return $ok;
}

// ---------------------------------------------------------------- réglages stockés
$GLOBALS['yk_reglages'] = [];
function reglage(string $k, $def = null)
{
    if (!array_key_exists($k, $GLOBALS['yk_reglages'])) {
        $v = val('SELECT v FROM settings WHERE k = ?', [$k]);
        $GLOBALS['yk_reglages'][$k] = $v === null ? null : json_decode((string) $v, true);
    }
    return $GLOBALS['yk_reglages'][$k] ?? $def;
}
function regler(string $k, $v): void
{
    $j = json_encode($v, JSON_UNESCAPED_UNICODE);
    if (val('SELECT COUNT(*) FROM settings WHERE k = ?', [$k])) q('UPDATE settings SET v = ? WHERE k = ?', [$j, $k]);
    else ins('settings', ['k' => $k, 'v' => $j]);
    $GLOBALS['yk_reglages'][$k] = $v;
}

// ---------------------------------------------------------------- outils
function h($s): string { return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = '/'): string { return rtrim((string) cfg('base_path'), '/') . '/' . ltrim($p, '/'); }
function url_absolue(string $p): string
{
    $base = rtrim((string) cfg('app_url'), '/');
    if ($base === '') $base = (est_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim((string) cfg('base_path'), '/');
    return $base . '/' . ltrim($p, '/');
}
function rediriger(string $p, array $qs = []): void
{
    header('Location: ' . url($p) . ($qs ? '?' . http_build_query($qs) : ''), true, 303);
    exit;
}
function maintenant(): string { return date('Y-m-d H:i:s'); }
function aujourdhui(): string { return date('Y-m-d'); }
function est_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}
function ip(): string { return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function post(string $k, $def = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $def; }
function nombre($v): ?float { $v = str_replace([' ', "\u{202f}", "\u{a0}", '€'], '', (string) $v); $v = str_replace(',', '.', $v); return $v === '' || !is_numeric($v) ? null : (float) $v; }
function date_valide($d): ?string { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null; }
const MOIS_FR = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const JOURS_FR = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
function date_fr(?string $d, bool $jour = true): string
{
    if (!$d) return '';
    $t = strtotime($d);
    if (!$t) return '';
    $s = (int) date('j', $t) . (date('j', $t) === '1' ? 'er' : '') . ' ' . MOIS_FR[(int) date('n', $t)];
    if (date('Y', $t) !== date('Y')) $s .= ' ' . date('Y', $t);
    return $jour ? JOURS_FR[(int) date('w', $t)] . ' ' . $s : $s;
}
function date_heure_fr(?string $d): string { return $d ? date_fr($d, false) . ' à ' . date('H:i', strtotime($d)) : ''; }
function euros($v): string { return $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', "\u{202f}") . "\u{a0}€"; }
function entier($v): string { return $v === null || $v === '' ? '—' : number_format((float) $v, 0, ',', "\u{202f}"); }

// ---------------------------------------------------------------- personnes et rôles
const ROLES = ['admin' => 'Administrateur', 'associe' => 'Associé', 'barista' => 'Barista'];
const PERSONNES = [
    'yousif' => 'Yousif', 'khaled' => 'Khaled', 'amer' => 'Amer',
    'chef' => 'Chef barista', 'senior' => 'Barista senior', 'vente' => 'Barista vente et web',
    'associes' => 'Les trois associés', 'equipe' => 'Toute l’équipe',
];
const PERSONNES_INDIVIDUELLES = ['yousif', 'khaled', 'amer', 'chef', 'senior', 'vente'];
function libelle_personne(?string $cle): string
{
    if (!$cle) return '—';
    static $noms = null;
    if ($noms === null) {
        $noms = [];
        if (installee()) foreach (rows("SELECT cle, prenom FROM users WHERE actif = 1 AND cle <> ''") as $u) $noms[$u['cle']] = $u['prenom'];
    }
    $base = PERSONNES[$cle] ?? $cle;
    if (in_array($cle, ['chef', 'senior', 'vente'], true) && !empty($noms[$cle])) return $base . ' (' . $noms[$cle] . ')';
    return $base;
}

// ---------------------------------------------------------------- droits configurables
const SECTIONS = [
    'taches' => 'Tâches', 'journal' => 'Journal du soir', 'planning' => 'Planning', 'commandes' => 'Commandes fournisseurs',
    'documents' => 'Documents et images', 'simulateur' => 'Simulateur financier', 'prix' => 'Simulateur de prix',
    'versions' => 'Versions des simulateurs', 'activite' => 'Journal d’activité',
    'mod_marque' => 'Module · Charte de marque', 'mod_manuel' => 'Module · Manuel barista', 'mod_lancement' => 'Module · Lancement et coffrets',
    'mod_eshop' => 'Module · Boutique en ligne', 'mod_b2b' => 'Module · Professionnels et contrats',
    'mod_regles' => 'Module · Règles de la maison', 'mod_charte' => 'Module · Charte des associés',
];
const NIVEAUX = [0 => 'Aucun accès', 1 => 'Voir', 2 => 'Voir et modifier'];
const DROITS_DEFAUT = [
    'associe' => ['taches' => 2, 'journal' => 2, 'planning' => 2, 'commandes' => 2, 'documents' => 2, 'simulateur' => 2, 'prix' => 2,
        'versions' => 2, 'activite' => 1, 'mod_marque' => 1, 'mod_manuel' => 1, 'mod_lancement' => 1, 'mod_eshop' => 1, 'mod_b2b' => 1,
        'mod_regles' => 1, 'mod_charte' => 1],
    'barista' => ['taches' => 2, 'journal' => 2, 'planning' => 1, 'commandes' => 2, 'documents' => 1, 'simulateur' => 0, 'prix' => 0,
        'versions' => 0, 'activite' => 0, 'mod_marque' => 1, 'mod_manuel' => 1, 'mod_lancement' => 1, 'mod_eshop' => 0, 'mod_b2b' => 0,
        'mod_regles' => 1, 'mod_charte' => 0],
];
function niveau(string $section, ?array $u = null): int
{
    $u = $u ?? utilisateur();
    if (!$u) return 0;
    if ($u['role'] === 'admin') return 2;
    $m = reglage('droits', DROITS_DEFAUT);
    return (int) ($m[$u['role']][$section] ?? DROITS_DEFAUT[$u['role']][$section] ?? 0);
}
function est_admin(): bool { $u = utilisateur(); return $u && $u['role'] === 'admin'; }
function exiger(string $section, int $n = 1): void
{
    if (niveau($section) < $n) erreur_page(403, 'Accès réservé', 'Votre rôle ne donne pas accès à cette partie. Demandez à l’administrateur si vous en avez besoin.');
}
function exiger_admin(): void { if (!est_admin()) erreur_page(403, 'Accès réservé', 'Cette page est réservée à l’administrateur.'); }

// ---------------------------------------------------------------- journal d'activité
function tracer(string $action, string $detail = ''): void
{
    try {
        $u = utilisateur();
        ins('activity', ['user_id' => $u['id'] ?? null, 'action' => mb_substr($action, 0, 60), 'detail' => mb_substr($detail, 0, 500), 'ip' => ip(), 'created_at' => maintenant()]);
    } catch (Throwable $e) { journal_erreur('tracer : ' . $e->getMessage()); }
}

// ---------------------------------------------------------------- erreurs
function erreur_page(int $code, string $titre, string $texte): void
{
    http_response_code($code);
    if (str_starts_with((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => $texte], JSON_UNESCAPED_UNICODE);
        exit;
    }
    page_haut($titre);
    echo '<section class="panel card"><h2>' . h($titre) . '</h2><p>' . h($texte) . '</p><p><a class="btn" href="' . h(url('/')) . '">Revenir à l’accueil</a></p></section>';
    page_bas();
    exit;
}
