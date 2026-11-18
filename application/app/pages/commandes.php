<?php
/*
 * Commandes fournisseurs : ce qu'il faut commander, ce qui est commandé, ce qui est reçu.
 */
declare(strict_types=1);

exiger('commandes');
$u = utilisateur();
$modifiable = niveau('commandes') >= 2;
$id = isset($P[0]) && $P[0] !== '' ? (int) $P[0] : 0;
$action = $P[1] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    exiger('commandes', 2);
    if ($id && $action === 'statut') {
        $s = (string) post('statut');
        $c = row('SELECT * FROM commandes WHERE id = ?', [$id]);
        if ($c && isset(STATUTS_COMMANDE[$s])) {
            $d = ['statut' => $s];
            if ($s === 'commande') $d += ['commande_par' => $u['id'], 'commande_le' => maintenant()];
            if ($s === 'recu') $d += ['recu_par' => $u['id'], 'recu_le' => maintenant()];
            upd('commandes', $d, 'id = ?', [$id]);
            tracer('commande : ' . mb_strtolower(STATUTS_COMMANDE[$s]), $c['produit']);
        }
        rediriger('/commandes', ['ok' => 'statut']);
    }
    if ($id && $action === 'supprimer') {
        $c = row('SELECT * FROM commandes WHERE id = ?', [$id]);
        if ($c && ($u['role'] !== 'barista' || (int) $c['demande_par'] === (int) $u['id'])) {
            q('DELETE FROM commandes WHERE id = ?', [$id]);
            tracer('commande supprimée', $c['produit']);
        }
        rediriger('/commandes', ['ok' => 'supprime']);
    }
    $produit = mb_substr((string) post('produit'), 0, 200);
    if ($produit !== '') {
        ins('commandes', ['produit' => $produit, 'quantite' => mb_substr((string) post('quantite'), 0, 60), 'fournisseur' => mb_substr((string) post('fournisseur'), 0, 120),
            'urgent' => post('urgent') ? 1 : 0, 'statut' => 'demande', 'note' => mb_substr((string) post('note'), 0, 300), 'demande_par' => $u['id'], 'demande_le' => maintenant()]);
        tracer('commande demandée', $produit . (post('urgent') ? ' (urgent)' : ''));
    }
    rediriger('/commandes', ['ok' => 'ajoute']);
}

$enCours = rows("SELECT * FROM commandes WHERE statut <> 'recu' ORDER BY urgent DESC, demande_le");
$recues = rows("SELECT * FROM commandes WHERE statut = 'recu' AND recu_le >= ? ORDER BY recu_le DESC", [date('Y-m-d H:i:s', strtotime('-30 days'))]);
$fournisseurs = array_column(rows("SELECT DISTINCT fournisseur FROM commandes WHERE fournisseur <> '' ORDER BY fournisseur"), 'fournisseur');

page_haut('Commandes', '/commandes');
echo '<h1 class="h-page">Commandes fournisseurs</h1>';
echo guide('La liste partagée de ce qu’il faut commander : lait, pâtisseries, emballages, consommables, café.',
    'Chacun ajoute ce qui manque. La personne qui commande passe la ligne en « Commandé », et celle qui réceptionne en « Reçu » après avoir vérifié les dates et les températures.');

function carte_commande(array $c, bool $modifiable, array $u): string
{
    $h = '<div class="panel order' . ($c['urgent'] ? ' urgent' : '') . '"><div><b>' . h($c['produit']) . '</b>' . ($c['quantite'] ? ' · ' . h($c['quantite']) : '')
        . ($c['urgent'] ? ' <span class="tag ko">Urgent</span>' : '') . '<div class="meta">' . ($c['fournisseur'] ? h($c['fournisseur']) . ' · ' : '')
        . 'demandé par ' . h(nom_utilisateur($c['demande_par'])) . ', ' . h(date_heure_fr($c['demande_le']))
        . ($c['commande_le'] ? ' · commandé par ' . h(nom_utilisateur($c['commande_par'])) . ', ' . h(date_heure_fr($c['commande_le'])) : '')
        . ($c['recu_le'] ? ' · reçu par ' . h(nom_utilisateur($c['recu_par'])) . ', ' . h(date_heure_fr($c['recu_le'])) : '') . '</div>'
        . ($c['note'] ? '<div class="small">' . h($c['note']) . '</div>' : '') . '</div>';
    if ($modifiable) {
        $h .= '<div class="order-actions">';
        $suite = ['demande' => ['commande', 'Marquer commandé'], 'commande' => ['recu', 'Marquer reçu'], 'recu' => ['demande', 'Recommander']][$c['statut']];
        $h .= '<form method="post" action="' . h(url('/commandes/' . $c['id'] . '/statut')) . '">' . champ_csrf() . '<input type="hidden" name="statut" value="' . $suite[0] . '"><button class="btn' . ($c['statut'] !== 'recu' ? ' primary' : '') . '">' . $suite[1] . '</button></form>';
        if ($c['statut'] === 'demande') $h .= '<form method="post" action="' . h(url('/commandes/' . $c['id'] . '/statut')) . '">' . champ_csrf() . '<input type="hidden" name="statut" value="recu"><button class="btn">Déjà reçu</button></form>';
        if ($u['role'] !== 'barista' || (int) $c['demande_par'] === (int) $u['id'])
            $h .= '<form method="post" action="' . h(url('/commandes/' . $c['id'] . '/supprimer')) . '">' . champ_csrf() . '<button class="btn ghost" data-confirm="Supprimer cette ligne ?" aria-label="Supprimer">Supprimer</button></form>';
        $h .= '</div>';
    }
    return $h . '</div>';
}

if ($modifiable) {
    echo '<section class="panel card"><h2>Ajouter à la liste</h2><form method="post" class="form-grid">' . champ_csrf()
        . '<label class="field wide">Produit<input name="produit" required maxlength="200" placeholder="Ex. Lait entier"></label>'
        . '<label class="field">Quantité<input name="quantite" maxlength="60" placeholder="Ex. 12 bouteilles"></label>'
        . '<label class="field">Fournisseur<input name="fournisseur" maxlength="120" list="fournisseurs"><datalist id="fournisseurs">'
        . implode('', array_map(function ($f) { return '<option value="' . h($f) . '">'; }, $fournisseurs)) . '</datalist></label>'
        . '<label class="field">Note<input name="note" maxlength="300"></label>'
        . '<label class="field chk"><input type="checkbox" name="urgent" value="1"> Urgent</label>'
        . '<div class="wide"><button class="btn primary">Ajouter</button></div></form></section>';
}
foreach (['demande', 'commande'] as $s) {
    $ls = array_filter($enCours, function ($c) use ($s) { return $c['statut'] === $s; });
    echo '<section><h2>' . h(STATUTS_COMMANDE[$s]) . ' <span class="count">' . count($ls) . '</span></h2>';
    if (!$ls) echo '<p class="muted">Rien pour l’instant.</p>';
    foreach ($ls as $c) echo carte_commande($c, $modifiable, $u);
    echo '</section>';
}
echo '<section><details><summary><h2 style="display:inline">Reçu ces 30 derniers jours <span class="count">' . count($recues) . '</span></h2></summary>';
foreach ($recues as $c) echo carte_commande($c, $modifiable, $u);
echo '</details></section>';
page_bas();
