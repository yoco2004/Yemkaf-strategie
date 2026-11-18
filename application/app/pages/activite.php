<?php
/*
 * Journal d'activité : qui a fait quoi, quand. Utile pour s'assurer que chaque accès reste personnel.
 */
declare(strict_types=1);

exiger('activite');
$qui = (int) ($_GET['qui'] ?? 0);
$params = [];
$where = '';
if ($qui) { $where = ' WHERE a.user_id = ?'; $params[] = $qui; }
$lignes = rows('SELECT a.*, u.prenom FROM activity a LEFT JOIN users u ON u.id = a.user_id' . $where . ' ORDER BY a.id DESC LIMIT 300', $params);
page_haut('Journal d’activité');
echo '<h1 class="h-page">Journal d’activité</h1>';
echo guide('Les 300 dernières actions : connexions, tâches cochées, journal du soir, commandes, documents, modifications des simulateurs.',
    'Une connexion depuis un appareil inhabituel ou à une heure étrange ? Vérifiez dans « Équipe et accès » et déconnectez l’appareil.');
echo '<form method="get" class="filters"><label class="field inline">Personne<select name="qui" onchange="this.form.submit()"><option value="0">Tout le monde</option>';
foreach (rows('SELECT id, prenom FROM users ORDER BY prenom') as $p) echo '<option value="' . (int) $p['id'] . '"' . ($qui === (int) $p['id'] ? ' selected' : '') . '>' . h($p['prenom']) . '</option>';
echo '</select></label></form><div class="table-scroll"><table class="text"><thead><tr><th>Quand</th><th>Qui</th><th>Action</th><th>Détail</th>' . (est_admin() ? '<th>IP</th>' : '') . '</tr></thead><tbody>';
foreach ($lignes as $l) echo '<tr><td>' . h(date_heure_fr($l['created_at'])) . '</td><td>' . h($l['prenom'] ?: '—') . '</td><td>' . h($l['action']) . '</td><td class="wrap-cell">' . h($l['detail']) . '</td>' . (est_admin() ? '<td>' . h($l['ip']) . '</td>' : '') . '</tr>';
echo '</tbody></table></div>';
page_bas();
