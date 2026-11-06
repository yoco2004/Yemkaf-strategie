# YEM'KAF : salle de pilotage

Outils de direction de YEM'KAF, maison de café de spécialité yéménite au 24 rue du Renard (Paris 4e).
Période couverte : du soft opening du **20 novembre 2026** au bilan du **19 mai 2027**.

## Ouvrir les outils

Double-cliquez sur `index.html` (feuille de route) ou sur `01-dashboard-financier/index.html` (simulateur).
Aucun serveur n'est nécessaire. Une connexion internet charge les polices et les graphiques (Chart.js) ;
hors connexion, les tableaux restent complets.

## Structure

| Dossier | Contenu | État |
|---|---|---|
| `index.html` | Feuille de route 6 mois : urgences de la semaine, décisions à prendre, paliers d'objectifs, tâches par personne, rituels | Prêt |
| `01-dashboard-financier/` | Simulateur financier : leviers, 8 corrections proposées, compte de résultat, café vert, grille de marges vérifiée, prix concurrents | Prêt |
| `01-dashboard-financier/modele.js` | Modèle de calcul, testable seul (`node -e "require('./01-dashboard-financier/modele.js')"`) | Prêt |
| `02-onboarding-baristas/` | Manuel d'onboarding, fiches recettes, planning, check-lists | Prochaine étape |
| `03-marketing-lancement/` | Rétroplanning, invitations VIP, scripts de vente, coffrets | À venir |
| `04-ecommerce/` | Tunnel QR code, relances, e-mails et SMS | À venir |
| `05-b2b-juridique/` | Contrats café vert et café torréfié, plaquette, protocole de cupping | À venir |
| `docs/recherches-2026-11-06.md` | Recherches en ligne et sources (concurrents, SumUp, Qonto, Mokha, Pompidou, AGEC…) | Prêt |

## Principe des corrections

Le simulateur démarre **exactement sur les chiffres du brief**. Les huit corrections que je propose
(montée en charge, saisonnalité, rodage web et B2B, commissions SumUp, frais de port, invendus,
équipe de 3 baristas, frais sociaux annexes) sont désactivées par défaut. Chacune s'active d'une case
et affiche son effet en euros. Rien n'est appliqué sans votre accord.

## Chiffres clés au 6 novembre 2026

| | Brief (reconstitué) | Avec les 8 corrections |
|---|---|---|
| Chiffre d'affaires HT sur 6 mois | 305 210 € | 223 040 € |
| Marge brute | 179 479 € (58,8 %) | 132 849 € (59,6 %) |
| Résultat d'exploitation | 63 979 € | 24 146 € |
| Point bas de trésorerie (hors solde de départ) | aucun | −3 575 € en M2 |
| Point mort en régime | 76 tasses/jour | 71 tasses/jour |

Votre plan annonce 278 383 € de CA et 49 123 € de résultat. Je ne retrouve pas ces montants à partir
des objectifs quotidiens (135 tasses, 55 pâtisseries…) : l'écart vient du mix de ventes, que j'ai dû supposer.
Le fichier source permettra de caler le modèle.
