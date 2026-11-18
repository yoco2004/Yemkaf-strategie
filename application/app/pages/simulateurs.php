<?php
/*
 * Simulateurs : page d'accès, affichage des deux simulateurs et API de leurs données partagées.
 * Les simulateurs sont les pages de la stratégie, inchangées ; un petit « pont » (public/assets/pont.js)
 * remplace l'enregistrement de claude.ai par celui de l'application.
 */
declare(strict_types=1);

require_once YK_ROOT . '/app/pages/simulateurs_def.php';
const SIMULATEURS = SIMULATEURS_DEF;
$u = utilisateur();

// ---------------------------------------------------------------- API des données
if (str_starts_with($chemin, '/api/data/')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $cle = $P[0] ?? '';
    if (!isset(SIMULATEURS[$cle])) { http_response_code(404); echo '{"erreur":"inconnu"}'; exit; }
    $sim = SIMULATEURS[$cle];
    if (niveau($sim['section']) < 1) { http_response_code(403); echo '{"erreur":"acces"}'; exit; }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'PUT') {
        if (niveau($sim['section']) < 2) { http_response_code(403); echo '{"erreur":"lecture_seule"}'; exit; }
        $recu = (string) ($_SERVER['HTTP_X_CSRF'] ?? '');
        if ($recu === '' || !hash_equals(jeton_csrf(), $recu)) { http_response_code(400); echo '{"erreur":"csrf"}'; exit; }
        $corps = (string) file_get_contents('php://input');
        if (strlen($corps) > 1024 * 1024) { http_response_code(413); echo '{"erreur":"trop_gros"}'; exit; }
        $data = json_decode($corps, true);
        if (!is_array($data)) { http_response_code(400); echo '{"erreur":"json"}'; exit; }
        $avant = row('SELECT * FROM data_docs WHERE cle = ?', [$cle]);
        // Sauvegarde automatique : une version par jour, avec les chiffres d'avant la première modification du jour.
        if ($avant && !val('SELECT COUNT(*) FROM versions WHERE cle = ? AND auto = 1 AND created_at >= ?', [$cle, aujourdhui() . ' 00:00:00'])) {
            ins('versions', ['cle' => $cle, 'nom' => 'Sauvegarde automatique du ' . date_fr(aujourdhui(), false), 'data' => $avant['data'], 'auto' => 1,
                'created_by' => $avant['updated_by'], 'created_at' => maintenant()]);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($avant) upd('data_docs', ['data' => $json, 'version' => (int) $avant['version'] + 1, 'updated_by' => $u['id'], 'updated_at' => maintenant()], 'cle = ?', [$cle]);
        else ins('data_docs', ['cle' => $cle, 'data' => $json, 'version' => 1, 'updated_by' => $u['id'], 'updated_at' => maintenant()]);
        if (!val('SELECT COUNT(*) FROM activity WHERE user_id = ? AND action = ? AND created_at >= ?', [$u['id'], 'simulateur modifié', date('Y-m-d H:i:s', time() - 900)])) tracer('simulateur modifié', $sim['titre']);
        echo json_encode(['ok' => true, 'version' => $avant ? (int) $avant['version'] + 1 : 1]);
        exit;
    }
    $d = row('SELECT * FROM data_docs WHERE cle = ?', [$cle]);
    echo json_encode(['data' => $d ? json_decode((string) $d['data'], true) : null, 'version' => $d ? (int) $d['version'] : 0,
        'par' => $d ? nom_utilisateur($d['updated_by']) : null, 'le' => $d['updated_at'] ?? null], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- un simulateur
foreach (SIMULATEURS as $cle => $sim) {
    if ($chemin !== $sim['chemin']) continue;
    exiger($sim['section']);
    $f = YK_ROOT . '/app/contenu/' . $sim['fichier'];
    if (!is_file($f)) erreur_page(404, 'Simulateur absent', 'Le fichier du simulateur n’a pas été déployé.');
    $html = str_replace('%%BASE%%', rtrim((string) cfg('base_path'), '/'), (string) file_get_contents($f));
    $conf = ['base' => rtrim((string) cfg('base_path'), '/'), 'csrf' => jeton_csrf(), 'docs' => [$sim['doc'] => $cle],
        'ecriture' => niveau($sim['section']) >= 2, 'prenom' => $u['prenom']];
    $pont = '<script>window.YK = ' . json_encode($conf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script><script src="' . h(url('/assets/pont.js')) . '?v=' . YK_VERSION . '"></script>';
    $barre = '<div class="yk-retour"><a href="' . h(url('/')) . '">← Yem’kaf Outils</a><span>' . h($sim['titre']) . '</span>'
        . (niveau('versions') > 0 ? '<a href="' . h(url('/versions?cle=' . $cle)) . '">Versions enregistrées</a>' : '') . '</div>';
    $html = preg_replace('/<head>/i', '<head>' . $pont, $html, 1);
    $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $barre, $html, 1);
    $html = str_replace('</head>', '<link rel="icon" href="' . h(url('/favicon.ico')) . '"><link rel="stylesheet" href="' . h(url('/assets/retour.css')) . '?v=' . YK_VERSION . '"></head>', $html);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// ---------------------------------------------------------------- page d'accès
if (niveau('simulateur') < 1 && niveau('prix') < 1) exiger('simulateur');
page_haut('Simulateurs', '/simulateurs');
echo '<h1 class="h-page">Simulateurs</h1>';
echo guide('Deux simulateurs partagés : le simulateur financier (six mois de chiffres) et le simulateur de prix (prix de chaque produit). Les chiffres modifiés sont enregistrés pour toute l’équipe.',
    'Avant un changement important, enregistrez une version dans « Versions » : vous pourrez la comparer ou y revenir. Une sauvegarde automatique est faite chaque jour avant la première modification.');
echo '<div class="cards">';
foreach (SIMULATEURS as $cle => $sim) {
    if (niveau($sim['section']) < 1) continue;
    $d = row('SELECT updated_by, updated_at FROM data_docs WHERE cle = ?', [$cle]);
    echo '<a class="panel card-link" href="' . h(url($sim['chemin'])) . '"><b>' . h($sim['titre']) . '</b><span>' . h($sim['texte']) . '</span>'
        . '<span class="small muted">' . ($d ? 'Modifié par ' . h(nom_utilisateur($d['updated_by'])) . ', ' . h(date_heure_fr($d['updated_at'])) : 'Chiffres de départ') . (niveau($sim['section']) < 2 ? ' · lecture seule' : '') . '</span></a>';
}
if (niveau('versions') > 0) echo '<a class="panel card-link" href="' . h(url('/versions')) . '"><b>Versions enregistrées</b><span>Les versions datées des deux simulateurs : enregistrer, télécharger, restaurer.</span></a>';
echo '</div>';
page_bas();
