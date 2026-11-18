<?php
/* Les deux simulateurs partagés et leurs droits. */
declare(strict_types=1);

const SIMULATEURS_DEF = [
    'simulateur-financier' => ['section' => 'simulateur', 'chemin' => '/simulateur', 'fichier' => 'simulateur-financier.html', 'titre' => 'Simulateur financier',
        'doc' => 'config/donnees', 'texte' => 'Chiffre d’affaires, marges, résultat et trésorerie des six mois ; charges, prix et paramètres modifiables ; export Excel.'],
    'simulateur-prix' => ['section' => 'prix', 'chemin' => '/prix', 'fichier' => 'simulateur-prix.html', 'titre' => 'Simulateur de prix',
        'doc' => 'config/prix', 'texte' => 'Coût complet, prix plancher, prix conseillé et comparaison avec le marché, produit par produit.'],
];
