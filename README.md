# YEM'KAF : salle de pilotage

Outils de direction de Yem'kaf, maison de café de spécialité yéménite au 24 rue du Renard (Paris 4e),
fondée par Yousif Al-Houmays, Amer Ibrahim et Khaled Almaweri.
Période couverte : du soft opening du **20 novembre 2026** au bilan du **19 mai 2027**.

## Ouvrir les outils

Double-cliquez sur `index.html` : c'est la feuille de route, qui mène à tous les modules.
Aucun serveur n'est nécessaire. Une connexion internet charge les polices et les graphiques (Chart.js) ;
hors connexion, les tableaux restent complets.

## Structure

| Dossier | Contenu | Responsable |
|---|---|---|
| `index.html` | Feuille de route : changements, urgences, décisions, paliers d'objectifs, tâches par personne, rituels | Tous |
| `00-marque/` | Plateforme de marque, corrections de charte et d'étiquetage, étude « 100 % Yémen ou autres cafés » | Yousif |
| `01-dashboard-financier/` | Simulateur 6 mois. Charges, dépenses ponctuelles, prix, grille pro et paramètres (SumUp, Mollie, café vert, trésorerie) **modifiables et exportables** ; enregistrés pour l'équipe sur la page publiée | Yousif |
| `01-dashboard-financier/modele.js` | Modèle de calcul, testable seul : `node -e "require('./01-dashboard-financier/modele.js')"` | — |
| `02-onboarding-baristas/` | Manuel : bar d'après le plan, recettes, boissons yéménites, planning 3 baristas + associés, check-lists imprimables | Amer, chef barista |
| `03-marketing-lancement/` | Rétroplanning, soirée VIP du jeudi 26 novembre, invitations par segment, coffrets, temps forts | Yousif |
| `04-ecommerce/` | WordPress, WooCommerce, Mollie, Qonto, Brevo : tunnel QR, relances, textes des e-mails et SMS, conformité ; schémas Mermaid dans `architecture.md` | Yousif |
| `05-b2b-juridique/` | Plaquette tarifaire, cupping du jeudi, EUDR, deux contrats-cadres en Markdown (à faire relire par un avocat) | Khaled |
| `assets/` | Feuille de style commune et éléments de marque optimisés (logos, frises, photos) | — |
| `docs/recherches-2026-11-06.md` | Recherches en ligne et sources | — |
| `outils/construire-artifacts.py` | Prépare les fichiers des pages publiées sur claude.ai | — |

## Les données modifiables du simulateur

Onglet « Vos chiffres » en bas du simulateur : charges mensuelles (avec mois de début et de fin), dépenses ponctuelles,
prix, coûts et parts de ventes de chaque produit. Sur la page publiée, chaque modification est enregistrée pour toute
l'équipe et Claude peut la relire. En local, elle reste dans le navigateur ; « Sauvegarde » permet de copier les données
d'une version à l'autre.

## Chiffres clés au 12 novembre 2026

3 baristas à 2 800 € (coût employeur), bureau de stockage à 630 €/mois, soirée VIP à 800 €, contrat SumUp (90 €/mois + 0,49 %),
café vert à 55 €/kg, grille « Premium Yémen », grille pro relevée, 15 000 € sur Qonto, aucun crédit.

| | Plan des associés | Avec les 5 corrections proposées |
|---|---|---|
| Chiffre d'affaires HT sur 6 mois | 319 741 € | 233 342 € |
| Marge brute | 186 601 € (58,4 %) | 138 118 € (59,2 %) |
| Résultat d'exploitation | 75 003 € | 22 858 € |
| Trésorerie : point bas / fin de période | 22 864 € (M1) / 90 003 € | 9 514 € (M2) / 37 858 € |
| Point mort en régime | 70 tasses par jour | 70 tasses par jour |

Avec un vert à 50 € puis 60 € le kilo, le résultat réaliste va de 28 249 € à 17 468 €. Tout est modifiable dans le simulateur
(onglets « Vos chiffres » et « Paramètres ») et exportable en Excel (CSV) ou JSON. Les décisions sont consignées dans `docs/decisions.md`.
