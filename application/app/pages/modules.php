<?php
/*
 * Modules de référence : liste et affichage, selon les droits de chacun.
 */
declare(strict_types=1);

$slug = $P[0] ?? '';
if ($slug !== '') {
    if (!isset(MODULES[$slug])) erreur_page(404, 'Module introuvable', 'Ce module n’existe pas.');
    $m = MODULES[$slug];
    exiger($m[2]);
    $f = YK_ROOT . '/app/contenu/' . $m[0];
    if (!is_file($f)) erreur_page(404, 'Module absent', 'Le contenu de ce module n’a pas été déployé.');
    $html = str_replace('%%BASE%%', rtrim((string) cfg('base_path'), '/'), (string) file_get_contents($f));
    $barre = '<div class="yk-retour"><a href="' . h(url('/modules')) . '">← Modules</a><span>' . h($m[1]) . '</span></div>';
    $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $barre, $html, 1);
    $html = str_replace('</head>', '<link rel="icon" href="' . h(url('/favicon.ico')) . '"><link rel="stylesheet" href="' . h(url('/assets/retour.css')) . '?v=' . YK_VERSION . '"></head>', $html);
    tracer('module consulté', $m[1]);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

$mods = modules_visibles();
if (!$mods) erreur_page(403, 'Accès réservé', 'Aucun module ne vous est ouvert.');
page_haut('Modules', '/modules');
echo '<h1 class="h-page">Modules de référence</h1>';
echo guide('Les documents de référence de Yem’kaf : charte de marque, manuel barista, plan de lancement, boutique en ligne, professionnels et contrats, règles de la maison.',
    'Chaque module commence par son propre mode d’emploi. Pour imprimer une check-list ou un contrat, utilisez le menu Imprimer du navigateur.');
echo '<div class="cards">';
foreach ($mods as $s => $m) echo '<a class="panel card-link" href="' . h(url('/module/' . $s)) . '"><b>' . h($m[1]) . '</b><span>' . h($m[3]) . '</span></a>';
echo '</div>';
page_bas();
