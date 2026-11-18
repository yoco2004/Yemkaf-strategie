<?php
/*
 * Mise en page commune : bandeau de marque, menu selon les droits, messages.
 */
declare(strict_types=1);

const MESSAGES = [
    'enregistre' => 'C’est enregistré.', 'ajoute' => 'Ajouté.', 'supprime' => 'Supprimé.', 'restaure' => 'Version restaurée : le simulateur reprend ces chiffres.',
    'version' => 'Version enregistrée.', 'invite' => 'Personne ajoutée. Elle peut se connecter avec son e-mail.', 'deconnecte' => 'Appareil déconnecté.',
    'au-revoir' => 'Vous êtes déconnecté.', 'fichier' => 'Fichier enregistré.', 'statut' => 'Statut mis à jour.', 'commente' => 'Commentaire ajouté.',
];

function menu_principal(): array
{
    $m = [['/', 'Accueil', true]];
    $m[] = ['/taches', 'Tâches', niveau('taches') > 0];
    $m[] = ['/journal', 'Journal du soir', niveau('journal') > 0];
    $m[] = ['/planning', 'Planning', niveau('planning') > 0];
    $m[] = ['/commandes', 'Commandes', niveau('commandes') > 0];
    $m[] = ['/documents', 'Documents', niveau('documents') > 0];
    $m[] = ['/simulateurs', 'Simulateurs', niveau('simulateur') > 0 || niveau('prix') > 0];
    $m[] = ['/modules', 'Modules', modules_visibles() !== []];
    return array_values(array_filter($m, function ($i) { return $i[2]; }));
}

function page_haut(string $titre, string $actif = '', array $opts = []): void
{
    $u = utilisateur();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . h($titre) . ' · Yem’kaf Outils</title>'
        . '<link rel="icon" href="' . h(url('/favicon.ico')) . '">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Noto+Kufi+Arabic:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap">'
        . '<link rel="stylesheet" href="' . h(url('/assets/yemkaf.css')) . '?v=' . YK_VERSION . '">'
        . '<link rel="stylesheet" href="' . h(url('/assets/app.css')) . '?v=' . YK_VERSION . '">'
        . '</head><body>';
    echo '<header class="band app-band"><div class="wrap"><a class="app-logo" href="' . h(url('/')) . '"><img src="' . h(url('/assets/marque/logo-creme.png')) . '" alt="Yem’kaf" width="120" height="50"></a>';
    if ($u) {
        echo '<div class="app-user"><details class="menu-user"><summary>' . h($u['prenom'] ?: $u['email']) . ' <span class="tag-role">' . h(ROLES[$u['role']] ?? '') . '</span></summary><div class="menu-pop">'
            . '<a href="' . h(url('/mes-appareils')) . '">Mes appareils</a>'
            . (est_admin() ? '<a href="' . h(url('/equipe')) . '">Équipe et accès</a>' : '')
            . (niveau('activite') > 0 ? '<a href="' . h(url('/activite')) . '">Journal d’activité</a>' : '')
            . (est_admin() ? '<a href="' . h(url('/parametres')) . '">Paramètres</a>' : '')
            . '<form method="post" action="' . h(url('/deconnexion')) . '">' . champ_csrf() . '<button type="submit">Se déconnecter</button></form>'
            . '</div></details></div>';
    }
    echo '</div><img class="frise" src="' . h(url('/assets/marque/frise-sable.png')) . '" alt=""></header>';
    if ($u) {
        echo '<nav class="app-nav" aria-label="Menu principal"><div class="wrap">';
        foreach (menu_principal() as $i) {
            $on = $actif === $i[0];
            echo '<a href="' . h(url($i[0])) . '"' . ($on ? ' aria-current="page"' : '') . '>' . h($i[1]) . '</a>';
        }
        echo '</div></nav>';
    }
    echo '<main class="wrap app-main">';
    $ok = (string) ($_GET['ok'] ?? '');
    if ($ok !== '' && isset(MESSAGES[$ok])) echo '<div class="flash ok" role="status">' . h(MESSAGES[$ok]) . '</div>';
    if (!empty($opts['erreur'])) echo '<div class="flash bad" role="alert">' . h($opts['erreur']) . '</div>';
}

function page_bas(): void
{
    echo '</main><footer class="wrap app-foot"><img class="frise" src="' . h(url('/assets/marque/frise-verte.png')) . '" alt="" style="opacity:.5">'
        . '<p>Yem’kaf · Berceau du café · 24 rue du Renard, 75004 Paris · Outils de l’équipe, version ' . YK_VERSION . '</p></footer>'
        . '<script src="' . h(url('/assets/app.js')) . '?v=' . YK_VERSION . '"></script></body></html>';
}

/** Bloc « mode d'emploi » en tête de page. */
function guide(string $sert, string $comment): string
{
    return '<details class="panel guide-mini"><summary>Mode d’emploi</summary><div class="guide-mini-body"><p>' . $sert . '</p><p>' . $comment . '</p></div></details>';
}

function options_personnes(?string $sel, bool $avecGroupes = true, bool $vide = false): string
{
    $o = $vide ? '<option value="">—</option>' : '';
    foreach (PERSONNES as $k => $l) {
        if (!$avecGroupes && !in_array($k, PERSONNES_INDIVIDUELLES, true)) continue;
        $o .= '<option value="' . h($k) . '"' . ($sel === $k ? ' selected' : '') . '>' . h(libelle_personne($k)) . '</option>';
    }
    return $o;
}
