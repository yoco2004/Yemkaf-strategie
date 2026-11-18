<?php
/*
 * Tâches : la feuille de route vivante. Cocher, ajouter, réattribuer, dater, commenter.
 */
declare(strict_types=1);

exiger('taches');
$u = utilisateur();
$id = isset($P[0]) && $P[0] !== '' ? (int) $P[0] : 0;
$action = $P[1] ?? '';
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$modifiable = niveau('taches') >= 2;

function lire_tache_form(): array
{
    $cle = (string) post('cle');
    return [
        'titre' => mb_substr((string) post('titre'), 0, 200),
        'detail' => (string) post('detail'),
        'cle' => isset(PERSONNES[$cle]) ? $cle : 'equipe',
        'echeance' => date_valide(post('echeance')),
        'echeance_libelle' => mb_substr((string) post('echeance_libelle'), 0, 60),
        'module' => isset(MODULES[post('module')]) || in_array(post('module'), ['simulateur', 'prix'], true) ? (string) post('module') : '',
        'periode' => mb_substr((string) post('periode'), 0, 120),
    ];
}

// ---------------------------------------------------------------- actions
if ($methode === 'POST') {
    exiger('taches', 2);
    if ($id && $action === 'statut') {
        $s = (string) post('statut');
        if (!isset(STATUTS_TACHE[$s])) $s = 'a_faire';
        $t = row('SELECT titre FROM tasks WHERE id = ?', [$id]);
        if ($t) {
            upd('tasks', ['statut' => $s, 'done_by' => $s === 'fait' ? $u['id'] : null, 'done_at' => $s === 'fait' ? maintenant() : null, 'updated_at' => maintenant()], 'id = ?', [$id]);
            tracer('tâche ' . mb_strtolower(STATUTS_TACHE[$s]), $t['titre']);
        }
        rediriger_retour('/taches', 'statut');
    }
    if ($id && $action === 'commentaire') {
        $texte = trim((string) post('texte'));
        if ($texte !== '') {
            ins('task_comments', ['task_id' => $id, 'user_id' => $u['id'], 'texte' => mb_substr($texte, 0, 4000), 'created_at' => maintenant()]);
            tracer('commentaire', (string) val('SELECT titre FROM tasks WHERE id = ?', [$id]));
        }
        rediriger('/taches/' . $id, ['ok' => 'commente']);
    }
    if ($id && $action === 'supprimer') {
        $t = row('SELECT titre FROM tasks WHERE id = ?', [$id]);
        q('DELETE FROM task_comments WHERE task_id = ?', [$id]);
        q('DELETE FROM tasks WHERE id = ?', [$id]);
        if ($t) tracer('tâche supprimée', $t['titre']);
        rediriger('/taches', ['ok' => 'supprime']);
    }
    $d = lire_tache_form();
    if ($d['titre'] === '') rediriger($id ? '/taches/' . $id : '/taches');
    if ($id) {
        $statut = (string) post('statut');
        $d['statut'] = isset(STATUTS_TACHE[$statut]) ? $statut : 'a_faire';
        $d['updated_at'] = maintenant();
        upd('tasks', $d, 'id = ?', [$id]);
        tracer('tâche modifiée', $d['titre']);
        rediriger('/taches/' . $id, ['ok' => 'enregistre']);
    }
    $d += ['statut' => 'a_faire', 'ordre' => (int) val('SELECT COALESCE(MAX(ordre), 0) + 1 FROM tasks'), 'created_by' => $u['id'], 'created_at' => maintenant(), 'updated_at' => maintenant()];
    ins('tasks', $d);
    tracer('tâche ajoutée', $d['titre']);
    rediriger_retour('/taches', 'ajoute');
}

function select_module(string $sel): string
{
    $o = '<option value="">Aucun</option>';
    foreach (['simulateur' => 'Simulateur financier', 'prix' => 'Simulateur de prix'] as $k => $l) $o .= '<option value="' . $k . '"' . ($sel === $k ? ' selected' : '') . '>' . h($l) . '</option>';
    foreach (MODULES as $k => $m) if ($m[4]) $o .= '<option value="' . h($k) . '"' . ($sel === $k ? ' selected' : '') . '>' . h($m[1]) . '</option>';
    return $o;
}
function lien_module(string $m): string
{
    if ($m === '') return '';
    if ($m === 'simulateur' || $m === 'prix') return niveau($m) ? '<a href="' . h(url('/' . $m)) . '">' . ($m === 'prix' ? 'Simulateur de prix' : 'Simulateur financier') . '</a>' : '';
    if (!isset(MODULES[$m]) || niveau(MODULES[$m][2]) < 1) return '';
    return '<a href="' . h(url('/module/' . $m)) . '">' . h(MODULES[$m][1]) . '</a>';
}

// ---------------------------------------------------------------- une tâche
if ($id) {
    $t = row('SELECT * FROM tasks WHERE id = ?', [$id]);
    if (!$t) erreur_page(404, 'Tâche introuvable', 'Elle a peut-être été supprimée.');
    page_haut($t['titre'], '/taches');
    echo '<p><a href="' . h(url('/taches')) . '">← Toutes les tâches</a></p>';
    echo '<section class="panel card"><div class="sec-row"><h1 class="h-page">' . h($t['titre']) . '</h1><span class="tag ' . ($t['statut'] === 'fait' ? 'ok' : ($t['statut'] === 'en_cours' ? 'warn' : 'info')) . '">' . h(STATUTS_TACHE[$t['statut']] ?? '') . '</span></div>'
        . '<p class="meta">' . h($t['echeance'] ? date_fr($t['echeance']) : ($t['echeance_libelle'] ?: 'Sans date')) . ' · ' . h(libelle_personne($t['cle'])) . ($t['periode'] ? ' · ' . h($t['periode']) : '') . '</p>'
        . ($t['detail'] ? '<p>' . nl2br(h($t['detail'])) . '</p>' : '')
        . ($t['module'] ? '<p>Voir : ' . lien_module($t['module']) . '</p>' : '')
        . ($t['statut'] === 'fait' && $t['done_by'] ? '<p class="small muted">Fait par ' . h(nom_utilisateur($t['done_by'])) . ', ' . h(date_heure_fr($t['done_at'])) . '</p>' : '')
        . '</section>';
    echo '<section class="panel card"><h2>Commentaires</h2>';
    $cs = rows('SELECT * FROM task_comments WHERE task_id = ? ORDER BY id', [$id]);
    if (!$cs) echo '<p class="muted">Aucun commentaire.</p>';
    foreach ($cs as $c) echo '<div class="comment"><b>' . h(nom_utilisateur($c['user_id'])) . '</b> <span class="small muted">' . h(date_heure_fr($c['created_at'])) . '</span><p>' . nl2br(h($c['texte'])) . '</p></div>';
    if ($modifiable) echo '<form method="post" action="' . h(url('/taches/' . $id . '/commentaire')) . '" class="form-col">' . champ_csrf()
        . '<label class="field">Ajouter un commentaire<textarea name="texte" rows="3" required></textarea></label><div><button class="btn primary">Publier</button></div></form>';
    echo '</section>';
    if ($modifiable) {
        echo '<details class="panel card"><summary><b>Modifier la tâche</b></summary><form method="post" action="' . h(url('/taches/' . $id)) . '" class="form-grid">' . champ_csrf()
            . '<label class="field wide">Titre<input name="titre" required maxlength="200" value="' . h($t['titre']) . '"></label>'
            . '<label class="field">Responsable<select name="cle">' . options_personnes($t['cle']) . '</select></label>'
            . '<label class="field">Échéance<input type="date" name="echeance" value="' . h($t['echeance']) . '"></label>'
            . '<label class="field">Ou fréquence (ex. « chaque lundi »)<input name="echeance_libelle" maxlength="60" value="' . h($t['echeance_libelle']) . '"></label>'
            . '<label class="field">Statut<select name="statut">' . implode('', array_map(function ($k, $l) use ($t) { return '<option value="' . $k . '"' . ($t['statut'] === $k ? ' selected' : '') . '>' . h($l) . '</option>'; }, array_keys(STATUTS_TACHE), STATUTS_TACHE)) . '</select></label>'
            . '<label class="field">Période<input name="periode" maxlength="120" value="' . h($t['periode']) . '"></label>'
            . '<label class="field">Module lié<select name="module">' . select_module($t['module']) . '</select></label>'
            . '<label class="field wide">Détail<textarea name="detail" rows="4">' . h($t['detail']) . '</textarea></label>'
            . '<div class="wide row-actions"><button class="btn primary">Enregistrer</button></div></form>'
            . '<form method="post" action="' . h(url('/taches/' . $id . '/supprimer')) . '" class="confirm-form">' . champ_csrf() . '<button class="btn danger" data-confirm="Supprimer cette tâche et ses commentaires ?">Supprimer la tâche</button></form></details>';
    }
    page_bas();
    exit;
}

// ---------------------------------------------------------------- liste
$vue = (string) ($_GET['vue'] ?? 'moi');
$statut = (string) ($_GET['statut'] ?? 'ouvert');
$where = [];
$params = [];
if ($vue === 'moi') {
    $cles = array_values(array_filter([$u['cle'], in_array($u['cle'], ['yousif', 'khaled', 'amer'], true) ? 'associes' : null, 'equipe']));
    $where[] = 'cle IN (' . implode(',', array_fill(0, count($cles), '?')) . ')';
    $params = array_merge($params, $cles);
} elseif (isset(PERSONNES[$vue])) {
    $where[] = 'cle = ?';
    $params[] = $vue;
}
if ($statut === 'ouvert') $where[] = "statut <> 'fait'";
elseif ($statut === 'fait') $where[] = "statut = 'fait'";
$taches = rows('SELECT * FROM tasks' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ordre, id', $params);

page_haut('Tâches', '/taches');
echo '<div class="sec-row"><h1 class="h-page">Tâches</h1>' . ($modifiable ? '<a class="btn primary" href="#ajouter">Ajouter une tâche</a>' : '') . '</div>';
echo guide('La feuille de route de l’équipe : chaque tâche a un responsable et une échéance. Cochez le rond quand c’est fait ; ouvrez une tâche pour la modifier ou la commenter.',
    '« Mes tâches » montre les vôtres, celles des trois associés (si vous êtes associé) et celles de toute l’équipe.');
echo '<form method="get" class="filters">';
$vues = ['moi' => 'Mes tâches', 'tout' => 'Tout le monde'] + array_map(function ($k) { return libelle_personne($k); }, array_combine(array_keys(PERSONNES), array_keys(PERSONNES)));
echo '<label class="field inline">Voir<select name="vue" onchange="this.form.submit()">';
foreach ($vues as $k => $l) echo '<option value="' . h($k) . '"' . ($vue === $k ? ' selected' : '') . '>' . h($l) . '</option>';
echo '</select></label><label class="field inline">Statut<select name="statut" onchange="this.form.submit()">';
foreach (['ouvert' => 'À faire et en cours', 'fait' => 'Faites', 'tout' => 'Toutes'] as $k => $l) echo '<option value="' . $k . '"' . ($statut === $k ? ' selected' : '') . '>' . h($l) . '</option>';
echo '</select></label><noscript><button class="btn">Filtrer</button></noscript></form>';

if (!$taches) echo '<p class="muted">Aucune tâche avec ces filtres.</p>';
$groupes = [];
foreach ($taches as $t) $groupes[$t['periode'] ?: 'Autres tâches'][] = $t;
foreach ($groupes as $g => $ts) {
    echo '<section class="task-group"><h2>' . h($g) . ' <span class="count">' . count($ts) . '</span></h2><div class="list">';
    foreach ($ts as $t) echo ligne_tache($t, $modifiable);
    echo '</div></section>';
}

if ($modifiable) {
    echo '<section id="ajouter" class="panel card"><h2>Ajouter une tâche</h2><form method="post" action="' . h(url('/taches')) . '" class="form-grid">' . champ_csrf()
        . '<input type="hidden" name="retour" value="' . h($_SERVER['REQUEST_URI'] ?? '/taches') . '">'
        . '<label class="field wide">Titre<input name="titre" required maxlength="200" placeholder="Ex. Commander les sacs kraft pour Noël"></label>'
        . '<label class="field">Responsable<select name="cle">' . options_personnes($u['cle'] ?: 'equipe') . '</select></label>'
        . '<label class="field">Échéance<input type="date" name="echeance"></label>'
        . '<label class="field">Période<input name="periode" maxlength="120" placeholder="Ex. Noël et soirée d’ouverture"></label>'
        . '<label class="field">Module lié<select name="module">' . select_module('') . '</select></label>'
        . '<label class="field wide">Détail<textarea name="detail" rows="3"></textarea></label>'
        . '<div class="wide"><button class="btn primary">Ajouter</button></div></form></section>';
}
page_bas();
