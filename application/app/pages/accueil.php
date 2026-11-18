<?php
/*
 * Accueil : ce qui concerne la personne aujourd'hui.
 */
declare(strict_types=1);

$u = utilisateur();
$cles = array_filter([$u['cle'], in_array($u['cle'], ['yousif', 'khaled', 'amer'], true) ? 'associes' : null, 'equipe']);
$ph = implode(',', array_fill(0, count($cles), '?'));

function objectif_du_jour(string $jour): ?array
{
    foreach ((array) reglage('objectifs', []) as $o) if ($jour >= $o['du'] && $jour <= $o['au']) return $o;
    return null;
}

page_haut('Accueil', '/');
$heure = (int) date('G');
echo '<section class="hello"><h1 class="h-page">' . ($heure < 18 ? 'Bonjour' : 'Bonsoir') . ' ' . h($u['prenom']) . '</h1><p class="muted">' . h(ucfirst(date_fr(aujourdhui()))) . '</p></section>';

echo '<section class="tiles">';
if (niveau('taches') > 0) {
    $aFaire = (int) val("SELECT COUNT(*) FROM tasks WHERE statut <> 'fait' AND cle IN ($ph)", array_values($cles));
    $retard = (int) val("SELECT COUNT(*) FROM tasks WHERE statut <> 'fait' AND cle IN ($ph) AND echeance IS NOT NULL AND echeance < ?", array_merge(array_values($cles), [aujourdhui()]));
    echo '<a class="panel tile" href="' . h(url('/taches?vue=moi')) . '"><span class="tile-k">Mes tâches à faire</span><b>' . $aFaire . '</b><span class="tile-s">' . ($retard ? '<span class="neg">' . $retard . ' en retard</span>' : 'aucune en retard') . '</span></a>';
}
if (niveau('journal') > 0) {
    $j = row('SELECT * FROM journal WHERE jour = ?', [aujourdhui()]);
    $o = objectif_du_jour(aujourdhui());
    echo '<a class="panel tile" href="' . h(url('/journal')) . '"><span class="tile-k">Journal du soir</span><b>' . ($j ? entier($j['tasses']) . ' tasses' : 'À remplir') . '</b><span class="tile-s">'
        . ($o ? 'Objectif ' . $o['mois'] . ' : ' . $o['tasses'] . ' tasses par jour' : 'Remplir à la fermeture') . '</span></a>';
}
if (niveau('commandes') > 0) {
    $c = (int) val("SELECT COUNT(*) FROM commandes WHERE statut <> 'recu'");
    $urg = (int) val("SELECT COUNT(*) FROM commandes WHERE statut = 'demande' AND urgent = 1");
    echo '<a class="panel tile" href="' . h(url('/commandes')) . '"><span class="tile-k">Commandes en cours</span><b>' . $c . '</b><span class="tile-s">' . ($urg ? '<span class="neg">' . $urg . ' urgente' . ($urg > 1 ? 's' : '') . '</span>' : 'rien d’urgent') . '</span></a>';
}
if (niveau('planning') > 0) {
    $sem = date('Y-m-d', strtotime('monday this week'));
    $pl = row('SELECT data FROM planning WHERE semaine <= ? ORDER BY semaine DESC', [$sem]);
    $data = $pl ? json_decode((string) $pl['data'], true) : [];
    $jourCle = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w')];
    $noms = []; $heures = [];
    foreach (PLANNING_CRENEAUX as $k => $c) if ($u['cle'] !== '' && ($data[$jourCle][$k] ?? '') === $u['cle']) { $noms[] = $c[0]; $heures[] = $c[1]; }
    echo '<a class="panel tile" href="' . h(url('/planning')) . '"><span class="tile-k">Mon service aujourd’hui</span><b>' . ($noms ? h(implode(' et ', $noms)) : 'Repos ou bureau') . '</b><span class="tile-s">' . ($heures ? h(implode(' · ', $heures)) : 'Voir le planning de la semaine') . '</span></a>';
}
echo '</section>';

if (niveau('taches') > 0) {
    $taches = rows("SELECT * FROM tasks WHERE statut <> 'fait' AND cle IN ($ph) ORDER BY CASE WHEN echeance IS NULL THEN 1 ELSE 0 END, echeance, ordre LIMIT 8", array_values($cles));
    echo '<section><div class="sec-row"><h2>Mes prochaines tâches</h2><a href="' . h(url('/taches?vue=moi')) . '">Toutes mes tâches</a></div>';
    if (!$taches) echo '<p class="muted">Rien à faire pour l’instant.</p>';
    else {
        echo '<div class="list">';
        foreach ($taches as $t) echo ligne_tache($t, niveau('taches') >= 2);
        echo '</div>';
    }
    echo '</section>';
}

$mods = modules_visibles();
$sims = array_filter([
    niveau('simulateur') > 0 ? ['/simulateur', 'Simulateur financier', 'Chiffre d’affaires, marges, résultat et trésorerie des six mois.'] : null,
    niveau('prix') > 0 ? ['/prix', 'Simulateur de prix', 'Coût complet, prix plancher et prix conseillé de chaque produit.'] : null,
]);
if ($mods || $sims) {
    echo '<section><h2>Outils et références</h2><div class="cards">';
    foreach ($sims as $s) echo '<a class="panel card-link" href="' . h(url($s[0])) . '"><b>' . h($s[1]) . '</b><span>' . h($s[2]) . '</span></a>';
    foreach ($mods as $slug => $m) echo '<a class="panel card-link" href="' . h(url('/module/' . $slug)) . '"><b>' . h($m[1]) . '</b><span>' . h($m[3]) . '</span></a>';
    echo '</div></section>';
}

echo guide('Cette page rassemble ce qui vous concerne aujourd’hui : vos tâches, le journal du soir, les commandes en cours et votre service.',
    'Le menu en haut mène aux autres pages. Votre prénom, en haut à droite, ouvre vos appareils connectés et la déconnexion.');
page_bas();
