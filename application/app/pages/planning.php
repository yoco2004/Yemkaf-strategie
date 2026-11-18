<?php
/*
 * Planning de la semaine : qui tient quel créneau, jour par jour. Modifiable selon les droits.
 */
declare(strict_types=1);

exiger('planning');
$u = utilisateur();
$modifiable = niveau('planning') >= 2;
$sem = date_valide($_GET['semaine'] ?? $_POST['semaine'] ?? '') ?? date('Y-m-d', strtotime('monday this week'));
$sem = date('Y-m-d', strtotime('monday this week', strtotime($sem)));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    exiger('planning', 2);
    $data = ['note' => mb_substr((string) post('note'), 0, 2000)];
    foreach (PLANNING_JOURS as $j => $_) foreach (PLANNING_CRENEAUX as $c => $_2) {
        $v = (string) ($_POST['p'][$j][$c] ?? '');
        $data[$j][$c] = in_array($v, PERSONNES_INDIVIDUELLES, true) ? $v : ($v === 'ferme' ? 'ferme' : '');
    }
    $j = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (val('SELECT COUNT(*) FROM planning WHERE semaine = ?', [$sem])) upd('planning', ['data' => $j, 'updated_by' => $u['id'], 'updated_at' => maintenant()], 'semaine = ?', [$sem]);
    else ins('planning', ['semaine' => $sem, 'data' => $j, 'updated_by' => $u['id'], 'updated_at' => maintenant()]);
    tracer('planning modifié', 'semaine du ' . date_fr($sem, false));
    rediriger('/planning', ['semaine' => $sem, 'ok' => 'enregistre']);
}

$pl = row('SELECT * FROM planning WHERE semaine = ?', [$sem]);
$modele = null;
if (!$pl) { $modele = row('SELECT * FROM planning WHERE semaine < ? ORDER BY semaine DESC', [$sem]) ?? row('SELECT * FROM planning ORDER BY semaine'); }
$src = $pl ?? $modele;
$data = $src ? (array) json_decode((string) $src['data'], true) : [];
if ($modele) $data['note'] = '';

page_haut('Planning', '/planning');
$prec = date('Y-m-d', strtotime($sem . ' -7 days'));
$suiv = date('Y-m-d', strtotime($sem . ' +7 days'));
echo '<div class="sec-row"><h1 class="h-page">Planning de la semaine</h1><span><a href="' . h(url('/planning?semaine=' . $prec)) . '">← Semaine précédente</a> · <a href="' . h(url('/planning?semaine=' . $suiv)) . '">Semaine suivante →</a></span></div>';
echo guide('Qui tient chaque créneau, jour par jour. Les créneaux suivent le Manuel barista : ouverture, midi et colis, fermeture, et renfort du matin.',
    $modifiable ? 'Choisissez une personne dans chaque case, puis « Enregistrer ». Une semaine pas encore préparée reprend la précédente comme modèle.' : 'Seuls les responsables du planning peuvent le modifier.');
echo '<p><b>Semaine du ' . h(date_fr($sem, false)) . ' au ' . h(date_fr(date('Y-m-d', strtotime($sem . ' +6 days')), false)) . '</b>'
    . ($modele ? ' <span class="tag warn">Pas encore enregistrée : modèle de la semaine du ' . h(date_fr($modele['semaine'], false)) . '</span>' : '')
    . ($pl ? ' <span class="small muted">· modifié par ' . h(nom_utilisateur($pl['updated_by'])) . ', ' . h(date_heure_fr($pl['updated_at'])) . '</span>' : '') . '</p>';

echo '<form method="post">' . champ_csrf() . '<input type="hidden" name="semaine" value="' . h($sem) . '"><div class="table-scroll"><table class="planning"><thead><tr><th>Jour</th>';
foreach (PLANNING_CRENEAUX as $c) echo '<th>' . h($c[0]) . '<small>' . h($c[1]) . '</small></th>';
echo '</tr></thead><tbody>';
$i = 0;
foreach (PLANNING_JOURS as $j => $lib) {
    $date = date('Y-m-d', strtotime($sem . ' +' . $i++ . ' days'));
    echo '<tr' . ($date === aujourdhui() ? ' class="today"' : '') . '><td><b>' . h($lib) . '</b><small>' . h(date_fr($date, false)) . '</small></td>';
    foreach (PLANNING_CRENEAUX as $c => $_) {
        $v = (string) ($data[$j][$c] ?? '');
        if ($modifiable) {
            echo '<td><select name="p[' . h($j) . '][' . h($c) . ']" aria-label="' . h($lib . ', ' . PLANNING_CRENEAUX[$c][0]) . '"><option value="">—</option>';
            foreach (PERSONNES_INDIVIDUELLES as $k) echo '<option value="' . $k . '"' . ($v === $k ? ' selected' : '') . '>' . h(libelle_personne($k)) . '</option>';
            echo '<option value="ferme"' . ($v === 'ferme' ? ' selected' : '') . '>Fermé</option></select></td>';
        } else {
            $mine = $v !== '' && $v === $u['cle'];
            echo '<td' . ($mine ? ' class="mine"' : '') . '>' . ($v === 'ferme' ? 'Fermé' : ($v ? h(libelle_personne($v)) : '—')) . '</td>';
        }
    }
    echo '</tr>';
}
echo '</tbody></table></div>';
echo '<label class="field" style="margin-top:12px">Note de la semaine (fermetures, événements, absences)<textarea name="note" rows="2"' . ($modifiable ? '' : ' disabled') . '>' . h($data['note'] ?? '') . '</textarea></label>';
if ($modifiable) echo '<div class="row-actions" style="margin-top:12px"><button class="btn primary">Enregistrer le planning</button></div>';
echo '</form>';
page_bas();
