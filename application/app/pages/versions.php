<?php
/*
 * Versions des simulateurs : enregistrer une version datée et nommée, la télécharger, la restaurer.
 */
declare(strict_types=1);

require_once YK_ROOT . '/app/pages/simulateurs_def.php';
exiger('versions');
$u = utilisateur();
$id = isset($P[0]) && $P[0] !== '' ? (int) $P[0] : 0;
$action = $P[1] ?? '';
$cles = array_filter(SIMULATEURS_DEF, function ($s) { return niveau($s['section']) > 0; });

if ($id) {
    $v = row('SELECT * FROM versions WHERE id = ?', [$id]);
    if (!$v || !isset($cles[$v['cle']])) erreur_page(404, 'Version introuvable', '');
    $sim = SIMULATEURS_DEF[$v['cle']];
    if ($action === 'telecharger') {
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode('yemkaf-' . $v['cle'] . '-' . substr((string) $v['created_at'], 0, 10) . '.json'));
        echo $v['data'];
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        exiger('versions', 2);
        if ($action === 'restaurer') {
            if (niveau($sim['section']) < 2) erreur_page(403, 'Accès réservé', 'Vous ne pouvez pas modifier ce simulateur.');
            $avant = row('SELECT * FROM data_docs WHERE cle = ?', [$v['cle']]);
            if ($avant) {
                ins('versions', ['cle' => $v['cle'], 'nom' => 'Avant la restauration de « ' . $v['nom'] . ' »', 'data' => $avant['data'], 'auto' => 1, 'created_by' => $u['id'], 'created_at' => maintenant()]);
                upd('data_docs', ['data' => $v['data'], 'version' => (int) $avant['version'] + 1, 'updated_by' => $u['id'], 'updated_at' => maintenant()], 'cle = ?', [$v['cle']]);
            } else {
                ins('data_docs', ['cle' => $v['cle'], 'data' => $v['data'], 'version' => 1, 'updated_by' => $u['id'], 'updated_at' => maintenant()]);
            }
            tracer('version restaurée', $sim['titre'] . ' : ' . $v['nom']);
            rediriger('/versions', ['cle' => $v['cle'], 'ok' => 'restaure']);
        }
        if ($action === 'supprimer') {
            q('DELETE FROM versions WHERE id = ?', [$id]);
            tracer('version supprimée', $sim['titre'] . ' : ' . $v['nom']);
            rediriger('/versions', ['cle' => $v['cle'], 'ok' => 'supprime']);
        }
    }
    rediriger('/versions');
}

$cle = isset($cles[$_GET['cle'] ?? $_POST['cle'] ?? '']) ? (string) ($_GET['cle'] ?? $_POST['cle']) : (string) array_key_first($cles);
if (!$cle) erreur_page(403, 'Accès réservé', 'Aucun simulateur ne vous est accessible.');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    exiger('versions', 2);
    $d = row('SELECT * FROM data_docs WHERE cle = ?', [$cle]);
    $nom = mb_substr((string) post('nom'), 0, 150) ?: 'Version du ' . date_fr(aujourdhui(), false);
    if ($d) {
        ins('versions', ['cle' => $cle, 'nom' => $nom, 'data' => $d['data'], 'auto' => 0, 'created_by' => $u['id'], 'created_at' => maintenant()]);
        tracer('version enregistrée', SIMULATEURS_DEF[$cle]['titre'] . ' : ' . $nom);
        rediriger('/versions', ['cle' => $cle, 'ok' => 'version']);
    }
    rediriger('/versions', ['cle' => $cle]);
}

$liste = rows('SELECT * FROM versions WHERE cle = ? ORDER BY created_at DESC, id DESC', [$cle]);
$actuel = row('SELECT * FROM data_docs WHERE cle = ?', [$cle]);
page_haut('Versions', '/simulateurs');
echo '<h1 class="h-page">Versions des simulateurs</h1>';
echo guide('Une version est une photo des chiffres d’un simulateur à une date : « Plan validé du 18 novembre », « Après la hausse du café vert », « Bilan de décembre ».',
    'Enregistrez une version avant un changement important. « Restaurer » remet ces chiffres dans le simulateur pour toute l’équipe ; les chiffres d’avant sont gardés dans une version automatique.');
echo '<div class="filters">';
foreach ($cles as $k => $s) echo '<a class="btn' . ($k === $cle ? ' primary' : '') . '" href="' . h(url('/versions?cle=' . $k)) . '">' . h($s['titre']) . '</a>';
echo '<a href="' . h(url(SIMULATEURS_DEF[$cle]['chemin'])) . '">Ouvrir le simulateur →</a></div>';
echo '<p class="muted">Chiffres actuels : ' . ($actuel ? 'modifiés par ' . h(nom_utilisateur($actuel['updated_by'])) . ', ' . h(date_heure_fr($actuel['updated_at'])) : 'chiffres de départ (pas encore modifiés)') . '.</p>';
if (niveau('versions') >= 2 && $actuel) {
    echo '<form method="post" class="panel card form-grid">' . champ_csrf() . '<input type="hidden" name="cle" value="' . h($cle) . '">'
        . '<label class="field wide">Nom de la version<input name="nom" maxlength="150" placeholder="Ex. Plan validé du ' . h(date_fr(aujourdhui(), false)) . '"></label>'
        . '<div class="wide"><button class="btn primary">Enregistrer les chiffres actuels comme version</button></div></form>';
}
if (!$liste) echo '<p class="muted">Aucune version enregistrée.</p>';
else {
    echo '<div class="table-scroll"><table class="text"><thead><tr><th>Version</th><th>Date</th><th>Par</th><th>Actions</th></tr></thead><tbody>';
    foreach ($liste as $v) {
        echo '<tr><td><b>' . h($v['nom']) . '</b>' . ($v['auto'] ? ' <span class="tag info">Automatique</span>' : '') . '</td><td>' . h(date_heure_fr($v['created_at'])) . '</td><td>' . h(nom_utilisateur($v['created_by'])) . '</td><td class="row-actions">'
            . '<a class="btn" href="' . h(url('/versions/' . $v['id'] . '/telecharger')) . '">Télécharger</a>';
        if (niveau('versions') >= 2 && niveau(SIMULATEURS_DEF[$cle]['section']) >= 2) {
            echo '<form method="post" action="' . h(url('/versions/' . $v['id'] . '/restaurer')) . '">' . champ_csrf() . '<button class="btn primary" data-confirm="Remettre ces chiffres dans le simulateur pour toute l’équipe ?">Restaurer</button></form>';
            echo '<form method="post" action="' . h(url('/versions/' . $v['id'] . '/supprimer')) . '">' . champ_csrf() . '<button class="btn ghost" data-confirm="Supprimer cette version ?">Supprimer</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
page_bas();
