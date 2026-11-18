<?php
/*
 * Connexion par e-mail : code à 6 chiffres ou lien, dans le même navigateur. Déconnexion.
 */
declare(strict_types=1);

$etape = $P[0] ?? '';
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($chemin === '/deconnexion') {
    if ($methode === 'POST') fermer_session();
    rediriger('/connexion', ['ok' => 'au-revoir']);
}
if (!installee()) rediriger('/installer');
if (utilisateur() && $etape !== 'lien') rediriger('/');

$retour = (string) ($_GET['retour'] ?? $_POST['retour'] ?? '');
if (!preg_match('#^/[^/\\\\]#', $retour) && $retour !== '/') $retour = '';
function aller_apres_connexion(string $retour): void
{
    header('Location: ' . ($retour !== '' ? $retour : url('/')), true, 303);
    exit;
}

$erreur = '';
$attenteCode = false;
if ($etape === 'lien') {
    $res = valider_lien((string) ($_GET['t'] ?? ''));
    if ($res === null) aller_apres_connexion('');
    $attenteCode = $res === 'autre_navigateur' && !empty($_COOKIE[COOKIE_TEMOIN]);
    $erreur = $res === 'autre_navigateur'
        ? 'Ce lien ne fonctionne que dans le navigateur où vous avez demandé la connexion. Ouvrez-le là-bas, ou tapez le code reçu dans ce navigateur-là.'
        : 'Ce lien a expiré ou a déjà servi. Demandez-en un nouveau.';
} elseif ($methode === 'POST' && $etape === 'code') {
    $res = valider_code((string) post('code'));
    if ($res === null) aller_apres_connexion($retour);
    $erreur = $res;
    $attenteCode = !str_contains($res, 'Recommencez') && !str_contains($res, 'nouveau');
} elseif ($methode === 'POST') {
    demander_connexion((string) post('email'));
    $attenteCode = true;
}

page_haut('Connexion', '', ['erreur' => $erreur]);
echo '<section class="login"><div class="panel card">';
echo '<img src="' . h(url('/assets/marque/logo-vert.png')) . '" alt="Yem’kaf" class="login-logo" width="140" height="58">';
if ($attenteCode) {
    echo '<h1 class="h-page">Vérifiez vos e-mails</h1>'
        . '<p>Si cette adresse fait partie de l’équipe, un e-mail vient de partir avec un <b>code à 6 chiffres</b> et un lien. Ils sont valables 15 minutes, une seule fois, et <b>seulement dans ce navigateur</b>.</p>'
        . '<form method="post" action="' . h(url('/connexion/code')) . '" class="form-col">'
        . '<input type="hidden" name="retour" value="' . h($retour) . '">'
        . '<label class="field">Code reçu<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="code-input"></label>'
        . '<button class="btn primary" type="submit">Me connecter</button></form>'
        . '<p class="small muted">Rien reçu ? Regardez dans les indésirables, ou <a href="' . h(url('/connexion')) . '">recommencez</a>. Une adresse qui ne fait pas partie de l’équipe ne reçoit rien : demandez à l’administrateur de vous ajouter.</p>';
} else {
    echo '<h1 class="h-page">Connexion à Yem’kaf Outils</h1>'
        . '<p>Tapez votre e-mail : vous recevrez un code de connexion. Pas de mot de passe à retenir.</p>'
        . '<form method="post" action="' . h(url('/connexion')) . '" class="form-col">'
        . '<input type="hidden" name="retour" value="' . h($retour) . '">'
        . '<label class="field">Adresse e-mail<input name="email" type="email" autocomplete="email" required autofocus></label>'
        . '<button class="btn primary" type="submit">Recevoir mon code</button></form>'
        . '<p class="small muted">Seules les personnes ajoutées par l’administrateur peuvent se connecter.</p>';
}
echo '</div></section>';
page_bas();
