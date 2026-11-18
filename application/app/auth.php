<?php
/*
 * Connexion sans mot de passe, réservée aux personnes inscrites par l'administrateur.
 *
 * 1. La personne tape son e-mail. Si l'adresse est sur la liste de l'équipe (et le compte actif), elle reçoit un e-mail
 *    avec un code à 6 chiffres et un lien, valables 15 minutes et une seule fois.
 * 2. Code et lien ne marchent QUE dans le navigateur qui a fait la demande (cookie témoin) : un lien transféré à quelqu'un
 *    d'autre ne lui ouvre rien.
 * 3. Chaque appareil connecté est enregistré. Nombre d'appareils limité par rôle ; au-delà, le plus ancien est déconnecté.
 *    Un nouvel appareil déclenche un e-mail d'alerte à la personne. L'administrateur voit et coupe les appareils.
 * 4. Toute action est tracée au nom de la personne : un accès partagé se verrait.
 */
declare(strict_types=1);

const COOKIE_SESSION = 'yk_session';
const COOKIE_TEMOIN = 'yk_temoin';
const COOKIE_APPAREIL = 'yk_appareil';
const DUREE_JETON = 900;            // 15 minutes
const INACTIVITE_MAX = 30 * 86400;  // déconnexion après 30 jours sans visite
const APPAREILS_DEFAUT = ['admin' => 3, 'associe' => 3, 'barista' => 2];

function poser_cookie(string $nom, string $valeur, int $duree): void
{
    setcookie($nom, $valeur, [
        'expires' => $duree > 0 ? time() + $duree : ($duree < 0 ? time() - 3600 : 0),
        'path' => rtrim((string) cfg('base_path'), '/') . '/',
        'secure' => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($duree < 0) unset($_COOKIE[$nom]); else $_COOKIE[$nom] = $valeur;
}
function hacher(string $s): string { return hash('sha256', $s); }
function secret_app(): string { return (string) reglage('secret', 'yemkaf'); }

// ---------------------------------------------------------------- personne connectée
$GLOBALS['yk_utilisateur'] = false;
function utilisateur(): ?array
{
    if ($GLOBALS['yk_utilisateur'] !== false) return $GLOBALS['yk_utilisateur'];
    $GLOBALS['yk_utilisateur'] = null;
    $t = (string) ($_COOKIE[COOKIE_SESSION] ?? '');
    if (strlen($t) < 40 || !installee()) return null;
    $r = row('SELECT s.id AS session_id, s.last_seen AS session_vue, u.* FROM sessions s JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = ? AND s.revoked_at IS NULL AND u.actif = 1', [hacher($t)]);
    if (!$r) return null;
    if (strtotime((string) $r['session_vue']) < time() - INACTIVITE_MAX) {
        upd('sessions', ['revoked_at' => maintenant()], 'id = ?', [$r['session_id']]);
        return null;
    }
    if (strtotime((string) $r['session_vue']) < time() - 300) upd('sessions', ['last_seen' => maintenant(), 'ip' => ip()], 'id = ?', [$r['session_id']]);
    $GLOBALS['yk_utilisateur'] = $r;
    return $r;
}
function exiger_connexion(): array
{
    $u = utilisateur();
    if (!$u) {
        if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), url('/api/'))) erreur_page(401, 'Connexion requise', 'Connectez-vous pour continuer.');
        $retour = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        rediriger('/connexion', $retour !== url('/') ? ['retour' => $retour] : []);
    }
    return $u;
}

// ---------------------------------------------------------------- protection des formulaires
function jeton_csrf(): string { return hash_hmac('sha256', 'csrf|' . ($_COOKIE[COOKIE_SESSION] ?? ''), secret_app()); }
function champ_csrf(): string { return '<input type="hidden" name="_csrf" value="' . h(jeton_csrf()) . '">'; }
function verifier_csrf(): void
{
    $recu = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
    if ($recu === '' || !hash_equals(jeton_csrf(), $recu)) erreur_page(400, 'Formulaire expiré', 'La page était ouverte depuis trop longtemps. Rechargez-la et recommencez.');
}

// ---------------------------------------------------------------- demande de connexion
function libelle_appareil(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = preg_match('/iPhone/i', $ua) ? 'iPhone' : (preg_match('/iPad/i', $ua) ? 'iPad' : (preg_match('/Android/i', $ua) ? 'Android' :
        (preg_match('/Mac OS X/i', $ua) ? 'Mac' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Appareil')))));
    $nav = preg_match('/Edg\//i', $ua) ? 'Edge' : (preg_match('/Firefox\//i', $ua) ? 'Firefox' : (preg_match('/Chrome\//i', $ua) ? 'Chrome' :
        (preg_match('/Safari\//i', $ua) ? 'Safari' : 'navigateur')));
    return $os . ' · ' . $nav;
}

/** Retourne toujours le même message, que l'adresse soit connue ou non. */
function demander_connexion(string $email): void
{
    $email = mb_strtolower(trim($email));
    $temoin = bin2hex(random_bytes(16));
    poser_cookie(COOKIE_TEMOIN, $temoin, DUREE_JETON);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $limite = date('Y-m-d H:i:s', time() - DUREE_JETON);
    if ((int) val('SELECT COUNT(*) FROM login_tokens WHERE ip = ? AND created_at > ?', [ip(), $limite]) >= 15) return;
    $u = row('SELECT * FROM users WHERE email = ? AND actif = 1', [$email]);
    if (!$u) { tracer('connexion refusée', 'adresse inconnue : ' . $email); return; }
    if ((int) val('SELECT COUNT(*) FROM login_tokens WHERE user_id = ? AND created_at > ?', [$u['id'], $limite]) >= 5) return;
    $jeton = bin2hex(random_bytes(32));
    $code = (string) random_int(100000, 999999);
    ins('login_tokens', ['user_id' => $u['id'], 'token_hash' => hacher($jeton), 'code_hash' => hacher($code . '|' . $temoin),
        'nonce_hash' => hacher($temoin), 'tentatives' => 0, 'expires_at' => date('Y-m-d H:i:s', time() + DUREE_JETON),
        'ip' => ip(), 'created_at' => maintenant()]);
    $lien = url_absolue('/connexion/lien?t=' . $jeton);
    $texte = "Bonjour {$u['prenom']},\n\nVotre code de connexion à Yem'kaf Outils : {$code}\n\nOu cliquez sur ce lien : {$lien}\n\n"
        . "Le code et le lien sont valables 15 minutes, une seule fois, et seulement dans le navigateur où vous avez demandé la connexion.\n"
        . "Vous n'avez rien demandé ? Ignorez cet e-mail et prévenez l'administrateur.\n\nYem'kaf · Berceau du café";
    $html = '<p>Bonjour ' . h($u['prenom']) . ',</p><p>Votre code de connexion à Yem’kaf Outils :</p>'
        . '<p style="font-size:28px;font-weight:700;letter-spacing:6px;color:#305640">' . h($code) . '</p>'
        . '<p>Ou cliquez sur ce bouton :</p><p><a href="' . h($lien) . '" style="background:#305640;color:#F1E8CD;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700">Me connecter</a></p>'
        . '<p style="color:#5f6e64;font-size:13px">Valable 15 minutes, une seule fois, et seulement dans le navigateur où vous avez demandé la connexion. Vous n’avez rien demandé ? Ignorez cet e-mail et prévenez l’administrateur.</p>';
    envoyer_mail($u['email'], 'Votre code de connexion Yem’kaf : ' . $code, $texte, $html);
}

/** Connexion par le code tapé dans le navigateur qui a fait la demande. */
function valider_code(string $code): ?string
{
    $temoin = (string) ($_COOKIE[COOKIE_TEMOIN] ?? '');
    if ($temoin === '') return 'La demande a expiré. Recommencez en tapant votre e-mail.';
    $t = row('SELECT * FROM login_tokens WHERE nonce_hash = ? AND used_at IS NULL ORDER BY id DESC', [hacher($temoin)]);
    if (!$t || strtotime((string) $t['expires_at']) < time()) return 'Le code a expiré. Demandez-en un nouveau.';
    if ((int) $t['tentatives'] >= 5) return 'Trop d’essais. Demandez un nouveau code.';
    $code = preg_replace('/\D/', '', $code);
    if (!hash_equals((string) $t['code_hash'], hacher($code . '|' . $temoin))) {
        upd('login_tokens', ['tentatives' => (int) $t['tentatives'] + 1], 'id = ?', [$t['id']]);
        return 'Code incorrect.';
    }
    ouvrir_session($t);
    return null;
}

/** Connexion par le lien : uniquement dans le navigateur qui a fait la demande. */
function valider_lien(string $jeton): ?string
{
    $t = row('SELECT * FROM login_tokens WHERE token_hash = ? AND used_at IS NULL', [hacher($jeton)]);
    if (!$t || strtotime((string) $t['expires_at']) < time()) return 'expire';
    $temoin = (string) ($_COOKIE[COOKIE_TEMOIN] ?? '');
    if ($temoin === '' || !hash_equals((string) $t['nonce_hash'], hacher($temoin))) return 'autre_navigateur';
    ouvrir_session($t);
    return null;
}

function ouvrir_session(array $t): void
{
    upd('login_tokens', ['used_at' => maintenant()], 'id = ?', [$t['id']]);
    $u = row('SELECT * FROM users WHERE id = ?', [$t['user_id']]);
    $appareil = (string) ($_COOKIE[COOKIE_APPAREIL] ?? '');
    if (strlen($appareil) < 32) $appareil = bin2hex(random_bytes(16));
    poser_cookie(COOKIE_APPAREIL, $appareil, 365 * 86400);
    $connu = (int) val('SELECT COUNT(*) FROM sessions WHERE user_id = ? AND device_hash = ?', [$u['id'], hacher($appareil)]) > 0;
    $jeton = bin2hex(random_bytes(32));
    ins('sessions', ['user_id' => $u['id'], 'token_hash' => hacher($jeton), 'device_hash' => hacher($appareil), 'device' => libelle_appareil(),
        'ip' => ip(), 'created_at' => maintenant(), 'last_seen' => maintenant()]);
    poser_cookie(COOKIE_SESSION, $jeton, INACTIVITE_MAX);
    poser_cookie(COOKIE_TEMOIN, '', -1);
    // Nombre d'appareils limité : on déconnecte les plus anciens.
    $max = (int) (reglage('appareils_max', APPAREILS_DEFAUT)[$u['role']] ?? 2);
    $actives = rows('SELECT id FROM sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen DESC, id DESC', [$u['id']]);
    foreach (array_slice($actives, max(1, $max)) as $s) upd('sessions', ['revoked_at' => maintenant()], 'id = ?', [$s['id']]);
    $GLOBALS['yk_utilisateur'] = false;
    utilisateur();
    tracer('connexion', libelle_appareil() . ($connu ? '' : ' (nouvel appareil)'));
    if (!$connu && (int) val('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$u['id']]) > 1) {
        envoyer_mail($u['email'], 'Nouvelle connexion à Yem’kaf Outils',
            "Bonjour {$u['prenom']},\n\nUne connexion à votre compte vient d'avoir lieu depuis un nouvel appareil : " . libelle_appareil() . ', le ' . date_heure_fr(maintenant()) . ".\n\n"
            . "Si c'est vous, rien à faire. Sinon, prévenez tout de suite l'administrateur : il peut couper cet accès.\n\nYem'kaf Outils");
    }
}

function fermer_session(): void
{
    $u = utilisateur();
    if ($u) { upd('sessions', ['revoked_at' => maintenant()], 'id = ?', [$u['session_id']]); tracer('déconnexion'); }
    poser_cookie(COOKIE_SESSION, '', -1);
    $GLOBALS['yk_utilisateur'] = null;
}
