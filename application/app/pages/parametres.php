<?php
/*
 * Paramètres (administrateur) : droits de chaque rôle, nombre d'appareils, objectifs de tasses par mois.
 */
declare(strict_types=1);

exiger_admin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $droits = [];
    foreach (['associe', 'barista'] as $r) foreach (SECTIONS as $s => $_) {
        $n = (int) ($_POST['d'][$r][$s] ?? 0);
        $droits[$r][$s] = max(0, min(2, $n));
    }
    regler('droits', $droits);
    $app = [];
    foreach (ROLES as $r => $_) $app[$r] = max(1, min(10, (int) ($_POST['appareils'][$r] ?? 2)));
    regler('appareils_max', $app);
    $obj = (array) reglage('objectifs', []);
    foreach ($obj as $i => $o) { $v = (int) ($_POST['objectif'][$i] ?? $o['tasses']); $obj[$i]['tasses'] = max(0, $v); }
    regler('objectifs', $obj);
    tracer('paramètres modifiés');
    rediriger('/parametres', ['ok' => 'enregistre']);
}

$droits = reglage('droits', DROITS_DEFAUT);
page_haut('Paramètres');
echo '<h1 class="h-page">Paramètres</h1>';
echo guide('Ce que chaque rôle peut voir et modifier, combien d’appareils chacun peut connecter, et les objectifs du journal du soir. L’administrateur a toujours tous les droits.',
    '« Voir » permet de consulter ; « Voir et modifier » permet aussi d’ajouter, de cocher et d’enregistrer. Les changements s’appliquent dès l’enregistrement.');
echo '<form method="post">' . champ_csrf() . '<section><h2>Droits par rôle</h2><div class="table-scroll"><table class="text droits"><thead><tr><th>Partie</th><th>Associés</th><th>Baristas</th></tr></thead><tbody>';
foreach (SECTIONS as $s => $lib) {
    echo '<tr><td>' . h($lib) . '</td>';
    foreach (['associe', 'barista'] as $r) {
        $v = (int) ($droits[$r][$s] ?? DROITS_DEFAUT[$r][$s] ?? 0);
        echo '<td><select name="d[' . $r . '][' . h($s) . ']" aria-label="' . h($lib . ', ' . ROLES[$r]) . '">';
        foreach (NIVEAUX as $n => $l) echo '<option value="' . $n . '"' . ($v === $n ? ' selected' : '') . '>' . h($l) . '</option>';
        echo '</select></td>';
    }
    echo '</tr>';
}
echo '</tbody></table></div></section>';
$app = reglage('appareils_max', APPAREILS_DEFAUT);
echo '<section><h2>Appareils par personne</h2><p class="muted">Au-delà, le plus ancien appareil est déconnecté à chaque nouvelle connexion.</p><div class="form-grid">';
foreach (ROLES as $r => $l) echo '<label class="field">' . h($l) . '<input type="number" min="1" max="10" name="appareils[' . $r . ']" value="' . (int) ($app[$r] ?? 2) . '"></label>';
echo '</div></section><section><h2>Objectifs de tasses par jour</h2><div class="form-grid">';
foreach ((array) reglage('objectifs', []) as $i => $o) echo '<label class="field">' . h($o['mois'] . ' · ' . date_fr($o['du'], false) . ' → ' . date_fr($o['au'], false)) . '<input type="number" min="0" name="objectif[' . $i . ']" value="' . (int) $o['tasses'] . '"></label>';
echo '</div></section><div class="row-actions" style="margin-top:16px"><button class="btn primary">Enregistrer les paramètres</button></div></form>';
page_bas();
