<?php
/*
 * Installation, à faire une seule fois après la première mise en ligne :
 * crée les tables, le compte administrateur, les droits par défaut, la feuille de route et le planning de départ.
 * Protégée par la phrase d'installation écrite dans app/config.php.
 */
declare(strict_types=1);

$verifs = [
    ['PHP 8.0 ou plus récent', version_compare(PHP_VERSION, '8.0.0', '>='), 'Version actuelle : ' . PHP_VERSION . '. Changez la version de PHP dans l’espace client de l’hébergeur.'],
    ['Fichier app/config.php présent', is_file(YK_ROOT . '/app/config.php'), 'Copiez app/config.example.php en app/config.php sur le serveur et remplissez-le.'],
    ['Base de données renseignée', !empty(cfg('db')['dsn']), 'Remplissez la partie « db » de app/config.php.'],
    ['Adresse de l’administrateur renseignée', filter_var(cfg('admin_email'), FILTER_VALIDATE_EMAIL) !== false, 'Remplissez « admin_email » dans app/config.php.'],
    ['Phrase d’installation renseignée', strlen((string) cfg('install_key')) >= 12, 'Choisissez une phrase d’au moins 12 caractères dans « install_key ».'],
    ['Dossier storage/ inscriptible', is_writable(dirname(stockage('logs'))) && is_writable(stockage('uploads')), 'Donnez les droits d’écriture au dossier storage/ (755 ou 775).'],
    ['Connexion sécurisée (https)', est_https() || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1:') || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:'), 'Activez le certificat SSL (Let’s Encrypt) du sous-domaine chez l’hébergeur.'],
];
$dbOk = false;
$dbErreur = '';
if (!empty(cfg('db')['dsn'])) {
    try { db(); $dbOk = true; } catch (Throwable $e) { $dbErreur = $e->getMessage(); }
}
$verifs[] = ['Connexion à la base de données', $dbOk, $dbErreur ? 'Erreur : ' . $dbErreur : 'Vérifiez l’hôte, le nom de la base, l’utilisateur et le mot de passe.'];
$tousOk = !in_array(false, array_column($verifs, 1), true);

if ($dbOk && installee()) erreur_page(404, 'Déjà installé', 'L’application est déjà installée. Connectez-vous depuis l’accueil.');

$erreur = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $tousOk) {
    if (!hash_equals((string) cfg('install_key'), (string) post('cle_installation'))) {
        $erreur = 'Phrase d’installation incorrecte.';
    } elseif (post('prenom') === '' || !isset(PERSONNES[post('personne')]) || !in_array(post('personne'), PERSONNES_INDIVIDUELLES, true)) {
        $erreur = 'Indiquez votre prénom et qui vous êtes dans l’équipe.';
    } else {
        $pdo = db();
        foreach (schema_sql(driver()) as $sql) $pdo->exec($sql);
        $pdo->beginTransaction();
        regler('secret', bin2hex(random_bytes(32)));
        regler('schema_version', SCHEMA_VERSION);
        regler('droits', DROITS_DEFAUT);
        regler('appareils_max', APPAREILS_DEFAUT);
        regler('objectifs', json_decode((string) file_get_contents(YK_ROOT . '/app/seed/objectifs.json'), true));
        $adminId = ins('users', ['email' => mb_strtolower((string) cfg('admin_email')), 'prenom' => post('prenom'), 'poste' => 'Associé',
            'cle' => post('personne'), 'role' => 'admin', 'actif' => 1, 'created_at' => maintenant(), 'updated_at' => maintenant()]);
        $ordre = 0;
        foreach (json_decode((string) file_get_contents(YK_ROOT . '/app/seed/taches.json'), true) as $t) {
            ins('tasks', ['titre' => $t['titre'], 'detail' => $t['detail'], 'cle' => $t['cle'], 'echeance' => $t['echeance'],
                'echeance_libelle' => $t['libelle'], 'periode' => $t['periode'], 'module' => $t['module'], 'statut' => 'a_faire',
                'ordre' => $ordre++, 'created_by' => $adminId, 'created_at' => maintenant(), 'updated_at' => maintenant()]);
        }
        $lundi = date('Y-m-d', strtotime('monday this week'));
        ins('planning', ['semaine' => $lundi, 'data' => file_get_contents(YK_ROOT . '/app/seed/planning.json'), 'updated_by' => $adminId, 'updated_at' => maintenant()]);
        regler('installe', maintenant());
        $pdo->commit();
        ins('activity', ['user_id' => $adminId, 'action' => 'installation', 'detail' => 'version ' . YK_VERSION, 'ip' => ip(), 'created_at' => maintenant()]);
        page_haut('Installation terminée');
        echo '<section class="panel card"><h2>Installation terminée</h2><p>Les tables, votre compte administrateur, la feuille de route et le planning de départ sont créés.</p>'
            . '<p>Connectez-vous avec <b>' . h(cfg('admin_email')) . '</b> : vous recevrez un code par e-mail. Ensuite, ajoutez l’équipe dans « Équipe et accès ».</p>'
            . '<p><a class="btn primary" href="' . h(url('/connexion')) . '">Me connecter</a></p></section>';
        page_bas();
        exit;
    }
}

page_haut('Installation', '', ['erreur' => $erreur]);
echo '<section class="sec-head"><h1 class="h-page">Installation de Yem’kaf Outils</h1><p>À faire une seule fois, juste après la première mise en ligne.</p></section>';
echo '<section class="panel card"><h2>Vérifications</h2><ul class="checks">';
foreach ($verifs as $v) echo '<li class="' . ($v[1] ? 'ok' : 'ko') . '"><b>' . ($v[1] ? '✓' : '✗') . ' ' . h($v[0]) . '</b>' . ($v[1] ? '' : '<span>' . h($v[2]) . '</span>') . '</li>';
echo '</ul></section>';
if ($tousOk) {
    echo '<form method="post" class="panel card form-grid">'
        . '<h2 style="grid-column:1/-1">Créer le compte administrateur</h2>'
        . '<p style="grid-column:1/-1" class="muted">Adresse de l’administrateur (dans app/config.php) : <b>' . h(cfg('admin_email')) . '</b></p>'
        . '<label class="field">Votre prénom<input name="prenom" required value="' . h(post('prenom')) . '"></label>'
        . '<label class="field">Qui êtes-vous dans l’équipe ?<select name="personne">' . options_personnes(post('personne') ?: null, false) . '</select></label>'
        . '<label class="field" style="grid-column:1/-1">Phrase d’installation (celle de app/config.php)<input name="cle_installation" type="password" required autocomplete="off"></label>'
        . '<div style="grid-column:1/-1"><button class="btn primary" type="submit">Installer</button></div></form>';
}
page_bas();
