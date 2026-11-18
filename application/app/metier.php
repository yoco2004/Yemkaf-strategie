<?php
/*
 * Éléments partagés par plusieurs pages : créneaux du planning, statuts, catégories de documents, ligne de tâche.
 */
declare(strict_types=1);

const PLANNING_JOURS = ['lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche'];
const PLANNING_CRENEAUX = [
    'ouverture' => ['Ouverture', '7 h 30 – 14 h 30'],
    'midi' => ['Midi et colis', '10 h 30 – 17 h 30'],
    'fermeture' => ['Fermeture', '13 h – 20 h'],
    'renfort' => ['Renfort', '8 h – 10 h 30'],
];
const STATUTS_TACHE = ['a_faire' => 'À faire', 'en_cours' => 'En cours', 'fait' => 'Fait'];
const STATUTS_COMMANDE = ['demande' => 'À commander', 'commande' => 'Commandé', 'recu' => 'Reçu'];
const CATEGORIES_DOCS = [
    'strategie' => 'Stratégie', 'resultats' => 'Résultats et bilans', 'graphisme' => 'Graphisme et images', 'photos' => 'Photos',
    'contrats' => 'Contrats et administratif', 'fournisseurs' => 'Fournisseurs', 'autre' => 'Autre',
];
const EXTENSIONS_DOCS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    'csv', 'txt', 'md', 'json', 'zip', 'ai', 'psd', 'eps', 'svg', 'indd', 'fig', 'sketch', 'mp4', 'mov', 'heic'];
const EXTENSIONS_IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

/** Adresse de retour sûre (chemin interne uniquement). */
function retour_sur(string $def): string
{
    $r = (string) ($_POST['retour'] ?? $_GET['retour'] ?? '');
    return preg_match('#^/[^/\\\\]#', $r) ? $r : url($def);
}
function rediriger_retour(string $def, string $ok): void
{
    $r = retour_sur($def);
    $r = preg_replace('/([?&])ok=[^&]*&?/', '$1', $r);
    $r = rtrim($r, '?&');
    header('Location: ' . $r . (str_contains($r, '?') ? '&' : '?') . 'ok=' . $ok, true, 303);
    exit;
}

function ligne_tache(array $t, bool $modifiable): string
{
    $fait = $t['statut'] === 'fait';
    $retard = !$fait && $t['echeance'] && $t['echeance'] < aujourdhui();
    $n = (int) val('SELECT COUNT(*) FROM task_comments WHERE task_id = ?', [$t['id']]);
    $quand = $t['echeance'] ? date_fr($t['echeance']) : ($t['echeance_libelle'] ?: 'sans date');
    $h = '<div class="task-line' . ($fait ? ' done' : '') . ($t['statut'] === 'en_cours' ? ' doing' : '') . '">';
    if ($modifiable) {
        $h .= '<form method="post" action="' . h(url('/taches/' . $t['id'] . '/statut')) . '">' . champ_csrf()
            . '<input type="hidden" name="statut" value="' . ($fait ? 'a_faire' : 'fait') . '">'
            . '<input type="hidden" name="retour" value="' . h($_SERVER['REQUEST_URI'] ?? '/') . '">'
            . '<button type="submit" class="check' . ($fait ? ' on' : '') . '" title="' . ($fait ? 'Remettre à faire' : 'Marquer comme fait') . '" aria-label="' . ($fait ? 'Remettre à faire' : 'Marquer comme fait') . ' : ' . h($t['titre']) . '">' . ($fait ? '✓' : '') . '</button></form>';
    } else {
        $h .= '<span class="check' . ($fait ? ' on' : '') . '" aria-hidden="true">' . ($fait ? '✓' : '') . '</span>';
    }
    $h .= '<div class="task-body"><a class="t" href="' . h(url('/taches/' . $t['id'])) . '">' . h($t['titre']) . '</a>'
        . '<div class="meta"><span class="' . ($retard ? 'neg' : '') . '">' . h($quand) . ($retard ? ' · en retard' : '') . '</span> · ' . h(libelle_personne($t['cle']))
        . ($t['statut'] === 'en_cours' ? ' · <span class="tag warn">En cours</span>' : '')
        . ($n ? ' · ' . $n . ' commentaire' . ($n > 1 ? 's' : '') : '') . '</div></div></div>';
    return $h;
}

function nom_utilisateur($id): string
{
    static $cache = [];
    if (!$id) return '—';
    if (!isset($cache[$id])) $cache[$id] = (string) (val('SELECT prenom FROM users WHERE id = ?', [$id]) ?? '—');
    return $cache[$id] ?: '—';
}
