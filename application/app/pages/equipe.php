<?php
/*
 * Équipe et accès (administrateur) : ajouter une personne, changer son rôle, la désactiver, voir et couper ses appareils.
 * Mes appareils (tout le monde) : voir ses connexions et en couper.
 */
declare(strict_types=1);

$u = utilisateur();
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function appareils_de(int $uid): array { return rows('SELECT * FROM sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen DESC', [$uid]); }
function table_appareils(array $ss, callable $action, ?int $courant): string
{
    if (!$ss) return '<p class="muted">Aucun appareil connecté.</p>';
    $h = '<div class="table-scroll"><table class="text"><thead><tr><th>Appareil</th><th>Connecté le</th><th>Dernière visite</th><th>Adresse IP</th><th></th></tr></thead><tbody>';
    foreach ($ss as $s) {
        $h .= '<tr><td>' . h($s['device']) . ((int) $s['id'] === $courant ? ' <span class="tag ok">Cet appareil</span>' : '') . '</td><td>' . h(date_heure_fr($s['created_at'])) . '</td><td>' . h(date_heure_fr($s['last_seen'])) . '</td><td>' . h($s['ip']) . '</td><td>'
            . '<form method="post" action="' . h(url($action((int) $s['id']))) . '">' . champ_csrf() . '<input type="hidden" name="session" value="' . (int) $s['id'] . '"><button class="btn ghost" data-confirm="Déconnecter cet appareil ?">Déconnecter</button></form></td></tr>';
    }
    return $h . '</tbody></table></div>';
}

// ---------------------------------------------------------------- mes appareils
if ($chemin === '/mes-appareils') {
    if ($methode === 'POST') {
        $sid = (int) post('session');
        q('UPDATE sessions SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [maintenant(), $sid, $u['id']]);
        tracer('appareil déconnecté', 'par la personne elle-même');
        if ($sid === (int) $u['session_id']) { poser_cookie(COOKIE_SESSION, '', -1); rediriger('/connexion', ['ok' => 'au-revoir']); }
        rediriger('/mes-appareils', ['ok' => 'deconnecte']);
    }
    page_haut('Mes appareils');
    echo '<h1 class="h-page">Mes appareils connectés</h1>';
    echo guide('Les téléphones, tablettes et ordinateurs sur lesquels votre compte est ouvert. Votre accès est personnel : ne le prêtez pas.',
        'Un appareil que vous ne reconnaissez pas ? Déconnectez-le et prévenez l’administrateur. Un appareil inutilisé pendant 30 jours est déconnecté automatiquement.');
    echo '<p>Compte : <b>' . h($u['email']) . '</b> · ' . h(ROLES[$u['role']] ?? '') . ' · ' . h(libelle_personne($u['cle'])) . '</p>';
    echo table_appareils(appareils_de((int) $u['id']), function () { return '/mes-appareils'; }, (int) $u['session_id']);
    page_bas();
    exit;
}

// ---------------------------------------------------------------- équipe (administrateur)
exiger_admin();
$id = isset($P[0]) && $P[0] !== '' ? (int) $P[0] : 0;
$erreur = '';
if ($methode === 'POST') {
    if ($id && ($P[1] ?? '') === 'appareil') {
        q('UPDATE sessions SET revoked_at = ? WHERE id = ? AND user_id = ?', [maintenant(), (int) ($P[2] ?? 0), $id]);
        tracer('appareil déconnecté', 'de ' . nom_utilisateur($id) . ' par l’administrateur');
        rediriger('/equipe', ['ok' => 'deconnecte']);
    }
    $email = mb_strtolower(trim((string) post('email')));
    $role = isset(ROLES[post('role')]) ? (string) post('role') : 'barista';
    $cle = isset(PERSONNES[post('cle')]) && in_array(post('cle'), PERSONNES_INDIVIDUELLES, true) ? (string) post('cle') : '';
    $d = ['prenom' => mb_substr((string) post('prenom'), 0, 100), 'poste' => mb_substr((string) post('poste'), 0, 100), 'cle' => $cle, 'role' => $role,
        'actif' => post('actif', '1') === '1' ? 1 : 0, 'updated_at' => maintenant()];
    if ($id) {
        $cible = row('SELECT * FROM users WHERE id = ?', [$id]);
        $adminsActifs = (int) val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND actif = 1 AND id <> ?", [$id]);
        if ($cible && $cible['role'] === 'admin' && ($d['role'] !== 'admin' || !$d['actif']) && $adminsActifs === 0) {
            $erreur = 'Il doit rester au moins un administrateur actif.';
        } else {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== $cible['email'] && !val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $id])) $d['email'] = $email;
            upd('users', $d, 'id = ?', [$id]);
            if (!$d['actif']) q('UPDATE sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [maintenant(), $id]);
            tracer('accès modifié', $d['prenom'] . ' : ' . ROLES[$role] . ($d['actif'] ? '' : ', désactivé'));
            rediriger('/equipe', ['ok' => 'enregistre']);
        }
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreur = 'Adresse e-mail invalide.';
        elseif (val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) $erreur = 'Cette adresse est déjà dans l’équipe.';
        elseif ($d['prenom'] === '') $erreur = 'Indiquez le prénom.';
        else {
            ins('users', $d + ['email' => $email, 'actif' => 1, 'created_at' => maintenant()]);
            tracer('personne ajoutée', $d['prenom'] . ' (' . ROLES[$role] . ')');
            if (post('prevenir')) envoyer_mail($email, 'Votre accès à Yem’kaf Outils',
                "Bonjour {$d['prenom']},\n\nVous avez maintenant accès aux outils de l'équipe Yem'kaf : " . url_absolue('/connexion') . "\n\n"
                . "Tapez votre adresse e-mail ({$email}) : vous recevrez un code de connexion. Pas de mot de passe à retenir.\n"
                . "Votre accès est personnel : ne le partagez pas.\n\nÀ très vite,\n{$u['prenom']}");
            rediriger('/equipe', ['ok' => 'invite']);
        }
    }
}

$users = rows('SELECT * FROM users ORDER BY actif DESC, CASE role WHEN \'admin\' THEN 0 WHEN \'associe\' THEN 1 ELSE 2 END, prenom');
page_haut('Équipe et accès', '', ['erreur' => $erreur]);
echo '<h1 class="h-page">Équipe et accès</h1>';
echo guide('Seules les personnes de cette liste peuvent se connecter. Le rôle fixe ce que chacun voit et modifie (réglable dans Paramètres). « Qui dans l’équipe » relie le compte à ses tâches et au planning.',
    'Quand quelqu’un quitte l’équipe, désactivez son compte : ses appareils sont déconnectés aussitôt et son historique reste.');
echo '<section class="panel card"><h2>Ajouter une personne</h2><form method="post" action="' . h(url('/equipe')) . '" class="form-grid">' . champ_csrf()
    . '<label class="field">Prénom<input name="prenom" required maxlength="100"></label>'
    . '<label class="field">E-mail<input name="email" type="email" required></label>'
    . '<label class="field">Rôle<select name="role">' . implode('', array_map(function ($k, $l) { return '<option value="' . $k . '"' . ($k === 'barista' ? ' selected' : '') . '>' . h($l) . '</option>'; }, array_keys(ROLES), ROLES)) . '</select></label>'
    . '<label class="field">Qui dans l’équipe<select name="cle"><option value="">—</option>' . options_personnes(null, false) . '</select></label>'
    . '<label class="field">Poste<input name="poste" maxlength="100" placeholder="Ex. Chef barista"></label>'
    . '<label class="field chk"><input type="checkbox" name="prevenir" value="1" checked> Lui envoyer un e-mail d’accueil</label>'
    . '<div class="wide"><button class="btn primary">Ajouter</button></div></form></section>';

foreach ($users as $p) {
    $ss = appareils_de((int) $p['id']);
    $der = val('SELECT MAX(last_seen) FROM sessions WHERE user_id = ?', [$p['id']]);
    echo '<details class="panel card person' . ($p['actif'] ? '' : ' off') . '"><summary><b>' . h($p['prenom'] ?: $p['email']) . '</b> <span class="tag-role">' . h(ROLES[$p['role']] ?? '') . '</span> '
        . h(libelle_personne($p['cle'] ?: null)) . ' · ' . h($p['email']) . ' · ' . count($ss) . ' appareil' . (count($ss) > 1 ? 's' : '') . ($der ? ' · vu ' . h(date_heure_fr($der)) : ' · jamais connecté')
        . ($p['actif'] ? '' : ' <span class="tag ko">Désactivé</span>') . '</summary>'
        . '<form method="post" action="' . h(url('/equipe/' . $p['id'])) . '" class="form-grid">' . champ_csrf()
        . '<label class="field">Prénom<input name="prenom" maxlength="100" value="' . h($p['prenom']) . '"></label>'
        . '<label class="field">E-mail<input name="email" type="email" value="' . h($p['email']) . '"></label>'
        . '<label class="field">Rôle<select name="role">' . implode('', array_map(function ($k, $l) use ($p) { return '<option value="' . $k . '"' . ($p['role'] === $k ? ' selected' : '') . '>' . h($l) . '</option>'; }, array_keys(ROLES), ROLES)) . '</select></label>'
        . '<label class="field">Qui dans l’équipe<select name="cle"><option value="">—</option>' . options_personnes($p['cle'] ?: null, false) . '</select></label>'
        . '<label class="field">Poste<input name="poste" maxlength="100" value="' . h($p['poste']) . '"></label>'
        . '<label class="field">Compte<select name="actif"><option value="1"' . ($p['actif'] ? ' selected' : '') . '>Actif</option><option value="0"' . ($p['actif'] ? '' : ' selected') . '>Désactivé</option></select></label>'
        . '<div class="wide"><button class="btn primary">Enregistrer</button></div></form>'
        . '<h3>Appareils connectés</h3>' . table_appareils($ss, function ($sid) use ($p) { return '/equipe/' . $p['id'] . '/appareil/' . $sid; }, (int) $p['id'] === (int) $u['id'] ? (int) $u['session_id'] : null)
        . '</details>';
}
page_bas();
