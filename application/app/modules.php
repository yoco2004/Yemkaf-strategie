<?php
/*
 * Les modules de référence (pages de la stratégie), servis seulement aux personnes qui y ont droit.
 * Les fichiers sont dans app/contenu/, préparés par outils/importer-contenu.js depuis le dépôt de stratégie.
 */
declare(strict_types=1);

// slug => [fichier, titre, section de droits, description, carte visible dans la liste]
const MODULES = [
    'marque' => ['00-marque.html', 'Charte de marque', 'mod_marque', 'La promesse, les noms des cafés, les couleurs, l’étiquetage des sachets, la façon de parler de Yem’kaf.', true],
    'manuel' => ['02-manuel.html', 'Manuel barista', 'mod_manuel', 'Règles de la maison, postes du bar, recettes, check-lists, phrases de comptoir, hygiène.', true],
    'lancement' => ['03-lancement.html', 'Lancement et coffrets', 'mod_lancement', 'Rétroplanning, soirée d’ouverture, invitations, coffrets, temps forts des 6 mois.', true],
    'eshop' => ['04-eshop.html', 'Boutique en ligne', 'mod_eshop', 'Parcours client, outils, e-mails et SMS prêts à copier, conformité du site.', true],
    'b2b' => ['05-b2b.html', 'Professionnels et contrats', 'mod_b2b', 'Plaquette tarifaire, modèles de contrats, cupping du jeudi, traçabilité.', true],
    'contrat-vert' => ['05-contrat-vert.html', 'Contrat café vert', 'mod_b2b', '', false],
    'contrat-torrefie' => ['05-contrat-torrefie.html', 'Contrat café torréfié', 'mod_b2b', '', false],
    'regles' => ['06-regles.html', 'Règles de la maison', 'mod_regles', 'Règles pour tous, tâche par tâche, règlement intérieur, accusé de lecture.', true],
    'reglement' => ['06-reglement.html', 'Règlement intérieur', 'mod_regles', '', false],
    'charte' => ['06-charte.html', 'Charte des associés', 'mod_charte', 'Domaines, plafonds de dépense, décisions, désaccords entre associés.', true],
];

function modules_visibles(): array
{
    $v = [];
    foreach (MODULES as $slug => $m) if ($m[4] && niveau($m[2]) > 0) $v[$slug] = $m;
    return $v;
}
