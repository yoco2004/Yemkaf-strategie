<?php
/*
 * Documents et images : stratégies, résultats, graphisme, photos, contrats. Chaque document garde ses versions datées.
 * Les fichiers sont rangés hors de la partie publique (storage/uploads) et servis seulement aux personnes autorisées.
 */
declare(strict_types=1);

exiger('documents');
$u = utilisateur();
$modifiable = niveau('documents') >= 2;
$voitTout = $u['role'] !== 'barista';
$id = isset($P[0]) && $P[0] !== '' ? (int) $P[0] : 0;
$action = $P[1] ?? '';

function doc_visible(array $d, bool $voitTout): bool { return $voitTout || $d['visibilite'] === 'tous'; }

// ---------------------------------------------------------------- servir un fichier
if (str_starts_with($chemin, '/fichier/')) {
    $d = row('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$d || !doc_visible($d, $voitTout)) erreur_page(404, 'Fichier introuvable', 'Ce fichier n’existe pas ou ne vous est pas accessible.');
    $f = stockage('uploads/' . $d['fichier']);
    if (!is_file($f)) erreur_page(404, 'Fichier introuvable', 'Le fichier a disparu du serveur.');
    $ext = strtolower(pathinfo($d['nom_original'], PATHINFO_EXTENSION));
    $enLigne = (in_array($ext, EXTENSIONS_IMAGES, true) || $ext === 'pdf') && !isset($_GET['telecharger']);
    header_remove('Content-Security-Policy');
    // Les PDF s'affichent dans la visionneuse du navigateur ; tout le reste est isolé (sandbox).
    if ($ext !== 'pdf') header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
    header('Content-Type: ' . ($enLigne ? $d['mime'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($f));
    header('Content-Disposition: ' . ($enLigne ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($d['nom_original']));
    header('Cache-Control: private, max-age=3600');
    readfile($f);
    exit;
}

// ---------------------------------------------------------------- envoi d'un fichier
function recevoir_fichier(): array
{
    $f = $_FILES['fichier'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['erreur' => 'Choisissez un fichier.'];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) return ['erreur' => 'Fichier trop lourd pour l’hébergement (limite : ' . ini_get('upload_max_filesize') . ').'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return ['erreur' => 'L’envoi a échoué. Réessayez.'];
    $max = (int) cfg('max_upload_mo', 20) * 1024 * 1024;
    if ($f['size'] > $max) return ['erreur' => 'Fichier trop lourd (maximum ' . cfg('max_upload_mo', 20) . ' Mo).'];
    $nom = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', basename((string) $f['name']));
    $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    if (!in_array($ext, EXTENSIONS_DOCS, true)) return ['erreur' => 'Type de fichier non accepté (.' . $ext . ').'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
    if (in_array($ext, EXTENSIONS_IMAGES, true) && !str_starts_with($mime, 'image/')) return ['erreur' => 'Ce fichier n’est pas une image valide.'];
    $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = stockage('uploads/' . $rel);
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0750, true);
    if (!move_uploaded_file($f['tmp_name'], $dest)) return ['erreur' => 'Impossible d’enregistrer le fichier sur le serveur.'];
    return ['fichier' => $rel, 'nom_original' => $nom, 'mime' => $mime, 'taille' => (int) $f['size']];
}

$erreur = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    exiger('documents', 2);
    if ($id && $action === 'supprimer') {
        $d = row('SELECT * FROM documents WHERE id = ?', [$id]);
        if ($d) {
            foreach (rows('SELECT * FROM documents WHERE groupe = ?', [$d['groupe']]) as $v) { @unlink(stockage('uploads/' . $v['fichier'])); }
            q('DELETE FROM documents WHERE groupe = ?', [$d['groupe']]);
            tracer('document supprimé', $d['titre']);
        }
        rediriger('/documents', ['ok' => 'supprime']);
    }
    $r = recevoir_fichier();
    if (isset($r['erreur'])) $erreur = $r['erreur'];
    else {
        if ($id && $action === 'version') {
            $base = row('SELECT * FROM documents WHERE id = ?', [$id]);
            if (!$base) erreur_page(404, 'Document introuvable', '');
            $v = (int) val('SELECT MAX(version) FROM documents WHERE groupe = ?', [$base['groupe']]) + 1;
            $nid = ins('documents', $r + ['groupe' => $base['groupe'], 'titre' => $base['titre'], 'categorie' => $base['categorie'],
                'date_doc' => date_valide(post('date_doc')) ?? aujourdhui(), 'version' => $v, 'visibilite' => $base['visibilite'],
                'note' => mb_substr((string) post('note'), 0, 2000), 'created_by' => $u['id'], 'created_at' => maintenant()]);
            tracer('nouvelle version', $base['titre'] . ' (v' . $v . ')');
            rediriger('/documents/' . $nid, ['ok' => 'fichier']);
        }
        $cat = isset(CATEGORIES_DOCS[post('categorie')]) ? (string) post('categorie') : 'autre';
        $titre = mb_substr((string) post('titre'), 0, 200) ?: pathinfo($r['nom_original'], PATHINFO_FILENAME);
        $nid = ins('documents', $r + ['groupe' => bin2hex(random_bytes(8)), 'titre' => $titre, 'categorie' => $cat,
            'date_doc' => date_valide(post('date_doc')) ?? aujourdhui(), 'version' => 1, 'visibilite' => post('visibilite') === 'associes' ? 'associes' : 'tous',
            'note' => mb_substr((string) post('note'), 0, 2000), 'created_by' => $u['id'], 'created_at' => maintenant()]);
        tracer('document ajouté', $titre);
        rediriger('/documents/' . $nid, ['ok' => 'fichier']);
    }
}

function taille_lisible(int $o): string { return $o >= 1048576 ? number_format($o / 1048576, 1, ',', '') . ' Mo' : max(1, (int) round($o / 1024)) . ' Ko'; }
function vignette(array $d): string
{
    $ext = strtolower(pathinfo($d['nom_original'], PATHINFO_EXTENSION));
    if (in_array($ext, EXTENSIONS_IMAGES, true)) return '<img src="' . h(url('/fichier/' . $d['id'])) . '" alt="" loading="lazy">';
    return '<span class="ext">' . h(strtoupper($ext)) . '</span>';
}

// ---------------------------------------------------------------- un document et ses versions
if ($id) {
    $d = row('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$d || !doc_visible($d, $voitTout)) erreur_page(404, 'Document introuvable', 'Ce document n’existe pas ou ne vous est pas accessible.');
    $versions = rows('SELECT * FROM documents WHERE groupe = ? ORDER BY version DESC', [$d['groupe']]);
    page_haut($d['titre'], '/documents', ['erreur' => $erreur]);
    echo '<p><a href="' . h(url('/documents')) . '">← Tous les documents</a></p>';
    echo '<section class="panel card doc-detail"><div class="doc-thumb">' . vignette($d) . '</div><div><h1 class="h-page">' . h($d['titre']) . '</h1>'
        . '<p class="meta">' . h(CATEGORIES_DOCS[$d['categorie']] ?? '') . ' · version ' . (int) $d['version'] . ' du ' . h(date_fr($d['date_doc'], false))
        . ' · ' . h($d['nom_original']) . ', ' . taille_lisible((int) $d['taille']) . ($d['visibilite'] === 'associes' ? ' · <span class="tag warn">Associés seulement</span>' : '') . '</p>'
        . ($d['note'] ? '<p>' . nl2br(h($d['note'])) . '</p>' : '')
        . '<p class="row-actions"><a class="btn primary" href="' . h(url('/fichier/' . $d['id'])) . '" target="_blank" rel="noopener">Ouvrir</a> '
        . '<a class="btn" href="' . h(url('/fichier/' . $d['id'] . '?telecharger=1')) . '">Télécharger</a></p></div></section>';
    echo '<section><h2>Versions</h2><div class="table-scroll"><table class="text"><thead><tr><th>Version</th><th>Date</th><th>Fichier</th><th>Ajoutée par</th><th>Note</th></tr></thead><tbody>';
    foreach ($versions as $v) echo '<tr' . ((int) $v['id'] === $id ? ' class="sel"' : '') . '><td><a href="' . h(url('/documents/' . $v['id'])) . '">v' . (int) $v['version'] . '</a></td><td>' . h(date_fr($v['date_doc'], false)) . '</td><td><a href="' . h(url('/fichier/' . $v['id'] . '?telecharger=1')) . '">' . h($v['nom_original']) . '</a></td><td>' . h(nom_utilisateur($v['created_by'])) . ', ' . h(date_heure_fr($v['created_at'])) . '</td><td>' . h($v['note']) . '</td></tr>';
    echo '</tbody></table></div></section>';
    if ($modifiable) {
        echo '<section class="panel card"><h2>Déposer une nouvelle version</h2><form method="post" enctype="multipart/form-data" action="' . h(url('/documents/' . $id . '/version')) . '" class="form-grid">' . champ_csrf()
            . '<label class="field wide">Fichier<input type="file" name="fichier" required></label>'
            . '<label class="field">Date du document<input type="date" name="date_doc" value="' . aujourdhui() . '"></label>'
            . '<label class="field">Ce qui a changé<input name="note" maxlength="2000"></label>'
            . '<div class="wide"><button class="btn primary">Déposer la version</button></div></form>'
            . '<form method="post" action="' . h(url('/documents/' . $id . '/supprimer')) . '" style="margin-top:14px">' . champ_csrf() . '<button class="btn danger" data-confirm="Supprimer ce document et toutes ses versions ?">Supprimer le document et ses versions</button></form></section>';
    }
    page_bas();
    exit;
}

// ---------------------------------------------------------------- liste
$cat = isset(CATEGORIES_DOCS[$_GET['categorie'] ?? '']) ? (string) $_GET['categorie'] : '';
$rech = trim((string) ($_GET['q'] ?? ''));
$where = ['d.version = (SELECT MAX(d2.version) FROM documents d2 WHERE d2.groupe = d.groupe)'];
$params = [];
if (!$voitTout) $where[] = "d.visibilite = 'tous'";
if ($cat) { $where[] = 'd.categorie = ?'; $params[] = $cat; }
if ($rech !== '') { $where[] = '(d.titre LIKE ? OR d.note LIKE ? OR d.nom_original LIKE ?)'; $l = '%' . $rech . '%'; array_push($params, $l, $l, $l); }
$docs = rows('SELECT d.* FROM documents d WHERE ' . implode(' AND ', $where) . ' ORDER BY d.date_doc DESC, d.id DESC', $params);

page_haut('Documents', '/documents', ['erreur' => $erreur]);
echo '<div class="sec-row"><h1 class="h-page">Documents et images</h1>' . ($modifiable ? '<a class="btn primary" href="#deposer">Déposer un fichier</a>' : '') . '</div>';
echo guide('L’armoire de l’équipe : stratégies, résultats et bilans, graphisme (logos, sachets, affiches), photos, contrats, fiches fournisseurs. Chaque document garde toutes ses versions, datées.',
    'Pour mettre à jour un document, ouvrez-le et déposez une nouvelle version : l’ancienne reste consultable. Un document marqué « Associés seulement » n’est pas visible des baristas.');
echo '<form method="get" class="filters"><label class="field inline">Catégorie<select name="categorie" onchange="this.form.submit()"><option value="">Toutes</option>';
foreach (CATEGORIES_DOCS as $k => $l) echo '<option value="' . $k . '"' . ($cat === $k ? ' selected' : '') . '>' . h($l) . '</option>';
echo '</select></label><label class="field inline">Rechercher<input name="q" value="' . h($rech) . '"></label><button class="btn">Chercher</button></form>';
if (!$docs) echo '<p class="muted">Aucun document' . ($cat || $rech ? ' avec ces filtres' : ' pour l’instant') . '.</p>';
echo '<div class="doc-grid">';
foreach ($docs as $d) {
    echo '<a class="panel doc-card" href="' . h(url('/documents/' . $d['id'])) . '"><div class="doc-thumb">' . vignette($d) . '</div><div class="doc-info"><b>' . h($d['titre']) . '</b>'
        . '<span>' . h(CATEGORIES_DOCS[$d['categorie']] ?? '') . ' · v' . (int) $d['version'] . ' · ' . h(date_fr($d['date_doc'], false)) . '</span>'
        . ($d['visibilite'] === 'associes' ? '<span class="tag warn">Associés</span>' : '') . '</div></a>';
}
echo '</div>';
if ($modifiable) {
    echo '<section id="deposer" class="panel card"><h2>Déposer un fichier</h2><form method="post" enctype="multipart/form-data" action="' . h(url('/documents')) . '" class="form-grid">' . champ_csrf()
        . '<label class="field wide">Fichier (images, PDF, documents, fichiers de graphisme ; ' . (int) cfg('max_upload_mo', 20) . ' Mo maximum)<input type="file" name="fichier" required></label>'
        . '<label class="field wide">Titre<input name="titre" maxlength="200" placeholder="Ex. Stratégie de lancement, Bilan de décembre, Maquette sachet vert"></label>'
        . '<label class="field">Catégorie<select name="categorie">' . implode('', array_map(function ($k, $l) { return '<option value="' . $k . '">' . h($l) . '</option>'; }, array_keys(CATEGORIES_DOCS), CATEGORIES_DOCS)) . '</select></label>'
        . '<label class="field">Date du document<input type="date" name="date_doc" value="' . aujourdhui() . '"></label>'
        . '<label class="field">Visible par<select name="visibilite"><option value="tous">Toute l’équipe</option><option value="associes">Les associés seulement</option></select></label>'
        . '<label class="field wide">Note<textarea name="note" rows="2"></textarea></label>'
        . '<div class="wide"><button class="btn primary">Déposer</button></div></form></section>';
}
page_bas();
