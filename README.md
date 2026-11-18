# Yem'kaf : salle de pilotage

Les outils de direction de Yem'kaf, maison de café de spécialité yéménite au 24 rue du Renard (Paris 4e),
fondée par Yousif Al-Houmays, Amer Ibrahim et Khaled Almaweri.
Période couverte : du soft opening du **20 novembre 2026** au bilan du **19 mai 2027**.

## Comment l'ouvrir

- **En ligne (recommandé)** : ouvrez le lien de la « Salle de pilotage » partagé par Yousif. Le simulateur a son propre
  lien ; c'est la seule version où les chiffres modifiés sont enregistrés pour toute l'équipe.
- **Sur un ordinateur** : téléchargez le dossier et double-cliquez sur `index.html`. Aucun logiciel n'est nécessaire.
  Une connexion internet charge les polices, les graphiques et les schémas ; sans connexion, les tableaux restent complets.
  Dans cette version locale, les chiffres modifiés restent dans le navigateur de l'ordinateur.

Chaque page commence par un **mode d'emploi** : à quoi elle sert, pour qui, comment l'utiliser.

## Les huit modules

| N° | Module | Fichier | À quoi il sert | Responsable |
|---|---|---|---|---|
| — | Salle de pilotage | `index.html` | Point d'entrée : les modules, qui fait quoi, la feuille de route semaine par semaine, les objectifs mois par mois, les rituels d'équipe | Tous |
| 1 | Simulateur financier | `01-dashboard-financier/` | Chiffre d'affaires, marges, résultat et trésorerie sur 6 mois ; charges, prix et paramètres modifiables ; export Excel | Yousif |
| 2 | Manuel barista | `02-onboarding-baristas/` | Règles de la maison, postes du bar, recettes, planning, check-lists imprimables, hygiène | Amer, chef barista |
| 3 | Charte de marque | `00-marque/` | Promesse, noms des cafés, couleurs officielles, étiquetage des sachets, choix du 100 % Yémen, ton | Yousif |
| 4 | Lancement et coffrets | `03-marketing-lancement/` | Rétroplanning, soirée d'ouverture du samedi 12 décembre, invitations, coffrets, temps forts | Yousif |
| 5 | Boutique en ligne | `04-ecommerce/` | WordPress, Mollie, Qonto, Brevo : parcours client, schémas, textes des e-mails et SMS, conformité | Yousif |
| 6 | Professionnels et contrats | `05-b2b-juridique/` | Plaquette tarifaire, deux modèles de contrats, cupping du jeudi, traçabilité | Khaled |
| 7 | Règles de la maison | `06-regles-maison/` | Règles pour tous, tâche par tâche ; règlement intérieur des salariés ; charte des associés | Amer, Yousif |
| 8 | Simulateur de prix | `07-simulateur-prix/` | Coût complet, prix plancher, prix conseillé et comparaison avec le marché pour chaque produit ; enregistré pour l'équipe sur la page publiée | Les trois associés |

Les numéros des dossiers ne suivent pas l'ordre des modules : seul l'ordre affiché sur la salle de pilotage compte.

## Mettre à jour les chiffres

Dans le simulateur, en bas de page, « Vos chiffres » regroupe : les charges mensuelles (avec mois de début et de fin),
les dépenses ponctuelles, les prix et coûts de chaque produit, les prix pros et les paramètres (SumUp, Mollie, prix du
café vert, solde Qonto de départ). L'onglet « Sauvegarde et export » télécharge les données (JSON) pour les recharger
ailleurs, et le bouton « Exporter (Excel) » produit le compte de résultat mois par mois pour le comptable.

Repères avec les chiffres de départ (3 baristas à 2 800 €, café vert à 55 € le kilo, 15 000 € sur Qonto, aucun crédit) :

| | Plan | Scénario prudent |
|---|---|---|
| Chiffre d'affaires HT sur 6 mois | 319 741 € | 233 342 € |
| Marge brute | 186 601 € (58,4 %) | 138 118 € (59,2 %) |
| Résultat d'exploitation | 75 003 € | 22 858 € |
| Trésorerie : point bas / fin de période | 22 864 € (M1) / 90 003 € | 9 514 € (M2) / 37 858 € |
| Point mort en régime | 70 tasses par jour | 70 tasses par jour |

« Plan » suppose les objectifs atteints dès l'ouverture ; « Scénario prudent » ajoute une montée en charge progressive,
la saisonnalité, le rodage du site et des ventes pros, les frais de port et les invendus.

## Contrats, règlement intérieur, charte

Le texte de ces documents se modifie dans les fichiers `.md` (`05-b2b-juridique/` pour les contrats,
`06-regles-maison/` pour le règlement intérieur et la charte des associés), puis la commande
`python3 outils/documents-en-html.py` régénère les pages lisibles et imprimables.

## Autres dossiers

| Dossier | Contenu |
|---|---|
| `assets/` | Feuille de style commune, logos, frises et photos |
| `docs/veille-et-sources.md` | Prix des concurrents, tarifs des prestataires, réglementation, avec leurs sources |
| `04-ecommerce/architecture.md` | Les schémas de la boutique en ligne, lisibles directement sur GitHub |
| `outils/` | Scripts : pages des documents (contrats, règlement, charte), préparation des pages publiées en ligne |
