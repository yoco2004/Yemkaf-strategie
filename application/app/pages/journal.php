<?php
/*
 * Journal du soir : ticket Z, tasses, sachets, invendus, remarque client. Historique du mois et objectif.
 */
declare(strict_types=1);

exiger('journal');
$u = utilisateur();
$modifiable = niveau('journal') >= 2;
$jour = date_valide($_GET['jour'] ?? $_POST['jour'] ?? '') ?? aujourdhui();
if ($jour > aujourdhui()) $jour = aujourdhui();
// Les baristas complètent le jour même et la veille ; les associés et l'administrateur, n'importe quel jour.
$peutCeJour = $modifiable && ($u['role'] !== 'barista' || $jour >= date('Y-m-d', strtotime('-1 day')));

function objectif_pour(string $jour): ?array
{
    foreach ((array) reglage('objectifs', []) as $o) if ($jour >= $o['du'] && $jour <= $o['au']) return $o;
    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$peutCeJour) erreur_page(403, 'Accès réservé', 'Vous pouvez remplir le journal du jour et de la veille. Pour un autre jour, demandez à un associé.');
    $d = [
        'ca_ttc' => nombre(post('ca_ttc')), 'tasses' => ($v = nombre(post('tasses'))) === null ? null : (int) $v,
        'sachets' => ($v = nombre(post('sachets'))) === null ? null : (int) $v, 'invendus' => ($v = nombre(post('invendus'))) === null ? null : (int) $v,
        'remarque' => mb_substr((string) post('remarque'), 0, 2000), 'incident' => mb_substr((string) post('incident'), 0, 2000),
        'user_id' => $u['id'], 'updated_at' => maintenant(),
    ];
    if (val('SELECT COUNT(*) FROM journal WHERE jour = ?', [$jour])) upd('journal', $d, 'jour = ?', [$jour]);
    else ins('journal', $d + ['jour' => $jour]);
    tracer('journal du soir', date_fr($jour) . ' : ' . ($d['tasses'] ?? '?') . ' tasses, ' . ($d['ca_ttc'] !== null ? euros($d['ca_ttc']) : '? €'));
    rediriger('/journal', ['jour' => $jour, 'ok' => 'enregistre']);
}

$e = row('SELECT * FROM journal WHERE jour = ?', [$jour]) ?? [];
$mois = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['mois'] ?? '')) ? $_GET['mois'] : substr($jour, 0, 7);
$debut = $mois . '-01';
$fin = date('Y-m-t', strtotime($debut));
$lignes = rows('SELECT * FROM journal WHERE jour BETWEEN ? AND ? ORDER BY jour', [$debut, $fin]);

page_haut('Journal du soir', '/journal');
echo '<h1 class="h-page">Journal du soir</h1>';
echo guide('Chaque soir à la fermeture, en cinq minutes : le total du ticket Z SumUp, le nombre de tasses, de sachets et d’invendus, et une remarque client.',
    'Ces chiffres permettent de comparer chaque jour à l’objectif du mois et de mettre à jour le simulateur. Les baristas remplissent le jour même ou la veille.');

$o = objectif_pour($jour);
echo '<section class="panel card"><div class="sec-row"><h2>' . h(ucfirst(date_fr($jour))) . '</h2>'
    . '<form method="get" class="inline-form"><label class="field inline">Autre jour<input type="date" name="jour" value="' . h($jour) . '" max="' . aujourdhui() . '" onchange="this.form.submit()"></label></form></div>';
if ($o) echo '<p class="muted">Objectif ' . h($o['mois']) . ' (' . h(date_fr($o['du'], false)) . ' → ' . h(date_fr($o['au'], false)) . ') : <b>' . (int) $o['tasses'] . ' tasses par jour</b>.</p>';
if ($e) echo '<p class="small muted">Rempli par ' . h(nom_utilisateur($e['user_id'])) . ', ' . h(date_heure_fr($e['updated_at'])) . '.</p>';
$dis = $peutCeJour ? '' : ' disabled';
echo '<form method="post" class="form-grid">' . champ_csrf() . '<input type="hidden" name="jour" value="' . h($jour) . '">'
    . '<label class="field">Ticket Z, total TTC<span class="unit"><input name="ca_ttc" inputmode="decimal" value="' . h(isset($e['ca_ttc']) && $e['ca_ttc'] !== null ? number_format((float) $e['ca_ttc'], 2, ',', '') : '') . '"' . $dis . '><span>€</span></span></label>'
    . '<label class="field">Tasses vendues<input name="tasses" type="number" min="0" value="' . h($e['tasses'] ?? '') . '"' . $dis . '></label>'
    . '<label class="field">Sachets vendus<input name="sachets" type="number" min="0" value="' . h($e['sachets'] ?? '') . '"' . $dis . '></label>'
    . '<label class="field">Invendus (pièces)<input name="invendus" type="number" min="0" value="' . h($e['invendus'] ?? '') . '"' . $dis . '></label>'
    . '<label class="field wide">Remarque d’un client<textarea name="remarque" rows="2"' . $dis . '>' . h($e['remarque'] ?? '') . '</textarea></label>'
    . '<label class="field wide">Incident ou point à régler (machine, stock, équipe)<textarea name="incident" rows="2"' . $dis . '>' . h($e['incident'] ?? '') . '</textarea></label>'
    . ($peutCeJour ? '<div class="wide"><button class="btn primary">Enregistrer le journal</button></div>' : '<p class="wide small muted">Lecture seule pour ce jour.</p>')
    . '</form></section>';

// ---------------------------------------------------------------- historique du mois
$prec = date('Y-m', strtotime($debut . ' -1 month'));
$suiv = date('Y-m', strtotime($debut . ' +1 month'));
echo '<section><div class="sec-row"><h2>' . h(ucfirst(MOIS_FR[(int) substr($mois, 5, 2)]) . ' ' . substr($mois, 0, 4)) . '</h2><span>'
    . '<a href="' . h(url('/journal?mois=' . $prec . '&jour=' . $jour)) . '">← Mois précédent</a> · <a href="' . h(url('/journal?mois=' . $suiv . '&jour=' . $jour)) . '">Mois suivant →</a></span></div>';
if (!$lignes) echo '<p class="muted">Aucun journal ce mois-ci.</p>';
else {
    $n = count($lignes);
    $tasses = array_sum(array_map(function ($l) { return (int) $l['tasses']; }, $lignes));
    $ca = array_sum(array_map(function ($l) { return (float) $l['ca_ttc']; }, $lignes));
    echo '<div class="tiles"><div class="panel tile"><span class="tile-k">Jours remplis</span><b>' . $n . '</b></div>'
        . '<div class="panel tile"><span class="tile-k">Moyenne de tasses par jour</span><b>' . entier($tasses / $n) . '</b></div>'
        . '<div class="panel tile"><span class="tile-k">Ticket Z cumulé (TTC)</span><b>' . euros($ca) . '</b></div></div>';
    // Graphique : tasses par jour et objectif du jour (une série, l'objectif en trait).
    $max = 10;
    foreach ($lignes as $l) { $max = max($max, (int) $l['tasses']); $ob = objectif_pour($l['jour']); if ($ob) $max = max($max, (int) $ob['tasses']); }
    $max = (int) (ceil($max / 20) * 20);
    $w = 720; $hgt = 200; $pad = 28; $nb = (int) date('t', strtotime($debut)); $bw = ($w - $pad) / $nb;
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . ($hgt + 24) . '" class="chart" role="img" aria-label="Tasses vendues par jour en ' . h(MOIS_FR[(int) substr($mois, 5, 2)]) . '">';
    for ($g = 0; $g <= $max; $g += $max / 4) { $y = $hgt - $g / $max * ($hgt - 10); $svg .= '<line x1="' . $pad . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" class="grid"/><text x="0" y="' . ($y + 4) . '" class="ax">' . (int) $g . '</text>'; }
    $parJour = [];
    foreach ($lignes as $l) $parJour[(int) substr($l['jour'], 8, 2)] = $l;
    for ($d = 1; $d <= $nb; $d++) {
        $x = $pad + ($d - 1) * $bw;
        $date = $mois . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $ob = objectif_pour($date);
        if ($ob) { $y = $hgt - $ob['tasses'] / $max * ($hgt - 10); $svg .= '<line x1="' . ($x + 1) . '" x2="' . ($x + $bw - 1) . '" y1="' . $y . '" y2="' . $y . '" class="target"/>'; }
        if (isset($parJour[$d]) && $parJour[$d]['tasses'] !== null) {
            $v = (int) $parJour[$d]['tasses']; $bh = $v / $max * ($hgt - 10);
            $svg .= '<rect x="' . ($x + 2) . '" y="' . ($hgt - $bh) . '" width="' . max(2, $bw - 4) . '" height="' . $bh . '" rx="3" class="bar' . ($ob && $v < $ob['tasses'] ? ' under' : '') . '"><title>' . h(date_fr($date)) . ' : ' . $v . ' tasses' . ($ob ? ' (objectif ' . $ob['tasses'] . ')' : '') . '</title></rect>';
        }
        if ($d === 1 || $d % 5 === 0) $svg .= '<text x="' . ($x + $bw / 2) . '" y="' . ($hgt + 16) . '" class="ax" text-anchor="middle">' . $d . '</text>';
    }
    echo '<div class="panel card"><p class="small muted">Tasses vendues par jour. Le trait horizontal marque l’objectif du jour ; une barre plus claire est sous l’objectif.</p>' . $svg . '</svg></div>';
    echo '<div class="table-scroll"><table><thead><tr><th>Jour</th><th>Ticket Z TTC</th><th>Tasses</th><th>Objectif</th><th>Sachets</th><th>Invendus</th><th>Remarque</th><th>Par</th></tr></thead><tbody>';
    foreach (array_reverse($lignes) as $l) {
        $ob = objectif_pour($l['jour']);
        echo '<tr><td><a href="' . h(url('/journal?jour=' . $l['jour'])) . '">' . h(date_fr($l['jour'])) . '</a></td><td>' . euros($l['ca_ttc']) . '</td><td>' . entier($l['tasses']) . '</td><td>' . ($ob ? (int) $ob['tasses'] : '—') . '</td><td>' . entier($l['sachets']) . '</td><td>' . entier($l['invendus']) . '</td>'
            . '<td class="wrap-cell">' . h(mb_strimwidth((string) $l['remarque'], 0, 90, '…')) . '</td><td>' . h(nom_utilisateur($l['user_id'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';
page_bas();
