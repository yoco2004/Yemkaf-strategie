# Yem'kaf Outils

L'application de l'équipe Yem'kaf, à l'adresse **https://outils.yemkaf.fr** : tâches, journal du soir, planning,
commandes fournisseurs, documents et images avec leurs versions, les deux simulateurs (financier et prix) et tous les
modules de référence (charte, manuel barista, lancement, boutique en ligne, pros, règles de la maison).

- **Trois rôles** : administrateur, associé, barista. Ce que chaque rôle voit et modifie se règle dans « Paramètres ».
- **Connexion sans mot de passe**, par code envoyé par e-mail, réservée aux personnes ajoutées par l'administrateur.
- **Tout est enregistré** pour l'équipe, avec qui a fait quoi et quand ; les simulateurs gardent des versions datées.
- **Mise en ligne automatique** : chaque modification envoyée sur GitHub part sur l'hébergement en une à deux minutes.

---

## Comment on est sûr qu'un accès est personnel

1. **Liste fermée** : seules les adresses ajoutées par l'administrateur reçoivent un code. Une autre adresse ne reçoit
   rien, et la page affiche le même message dans les deux cas.
2. **Code et lien valables 15 minutes, une seule fois, et seulement dans le navigateur qui a fait la demande** : un lien
   transféré à quelqu'un d'autre ne lui ouvre rien. Cinq essais de code au maximum.
3. **Appareils comptés** : 2 par barista, 3 par associé (réglable). Au-delà, le plus ancien est déconnecté. Chaque
   **nouvel appareil** déclenche un e-mail d'alerte à la personne.
4. **Tout est tracé au nom de la personne** (« Journal d'activité ») : un accès prêté se verrait.
5. **L'administrateur coupe un accès en un clic** : désactiver le compte déconnecte tous ses appareils aussitôt.
6. Un appareil inutilisé pendant 30 jours est déconnecté automatiquement.

Ce que l'application ne peut pas empêcher : qu'une personne donne l'accès à sa propre boîte e-mail. C'est une règle de
la maison (« Votre accès est personnel »), écrite dans le règlement intérieur.

---

## Mise en place, pas à pas (une seule fois)

Hébergement : **LWS**, formule Standard de yemeni-market.com. Il contient la boutique PrestaShop (yemeni-market.com) et,
dans le dossier `htdocs/yemkaf.fr`, le site WordPress de yemkaf.fr. Les outils vont dans **leur propre dossier**, sans
toucher ni à l'un ni à l'autre.

### Étape 1 · Le dépôt GitHub (fait)

Dépôt `yemkaf/yemkaf-outils`, avec l'application GitHub « Claude » installée. Le code est sur la branche `main`.

### Étape 2 · Trouver le dossier du sous-domaine outils.yemkaf.fr

Le sous-domaine est créé ; il faut savoir quel dossier il affiche.

1. **LWS Panel** → votre hébergement yemeni-market.com → **Sous-domaines** (ou « Gestion des sous-domaines ») : la ligne
   `outils.yemkaf.fr` indique son **répertoire**. Notez-le (par exemple `htdocs/outils`).
2. **Vérification** : dans le **Gestionnaire de fichiers**, ouvrez ce dossier → **+ Fichier** → nommez-le
   `test-outils.txt` → écrivez `ici` → enregistrez. Ouvrez **https://outils.yemkaf.fr/test-outils.txt** : si le mot
   « ici » s'affiche, c'est le bon dossier. Supprimez ensuite le fichier.
3. Si aucun répertoire n'est indiqué ou si le test échoue : supprimez le sous-domaine et recréez-le depuis
   **Sous-domaines** de l'hébergement en choisissant le répertoire **`outils`** : LWS crée alors `htdocs/outils`.
   **Ne choisissez pas un dossier à l'intérieur de `htdocs/yemkaf.fr`** (c'est WordPress).
4. Toujours dans LWS Panel : activez le **certificat SSL** (Let's Encrypt) pour `outils.yemkaf.fr`, et regardez la
   **version de PHP** de l'hébergement. Il faut 8.0 ou plus. **Ne changez pas la version sans vérifier la boutique** :
   elle s'applique aussi à PrestaShop et WordPress.

### Étape 3 · La base de données et l'e-mail

1. **LWS Panel → Bases de données MySQL → Créer** : par exemple `yemkaf_outils`. Notez le nom de la base, l'utilisateur,
   le mot de passe et le **serveur MySQL** affiché par LWS.
2. **E-mail** : créez une adresse pour envoyer les codes, de préférence `outils@yemkaf.fr` (sinon
   `outils@yemeni-market.com`). Notez son mot de passe et les réglages **SMTP** indiqués par LWS (serveur, port 465 en
   SSL ou 587 en TLS).

### Étape 4 · Les accès FTP dans GitHub (jamais dans le chat)

Dans **LWS Panel → FTP** : notez le **serveur FTP**, l'**identifiant** et le **mot de passe** du compte FTP.
Puis, sur GitHub, dans `yemkaf/yemkaf-outils` : **Settings → Secrets and variables → Actions**.

1. Onglet **Secrets** → **New repository secret**, trois fois : `FTP_SERVEUR`, `FTP_UTILISATEUR`, `FTP_MOT_DE_PASSE`.
2. Onglet **Variables** → **New repository variable** :
   - `FTP_DOSSIER` : le dossier de l'étape 2 tel qu'on le voit en se connectant en FTP, **terminé par `/`**
     (par exemple `htdocs/outils/`). La mise en ligne refuse de partir si ce chemin ne contient pas « outils » :
     la boutique et WordPress sont protégés d'une erreur de frappe.
   - `FTP_PROTOCOLE` (facultatif) : laissez vide (FTP chiffré) ; mettez `ftp` seulement si l'envoi échoue en FTPS.

### Étape 5 · Première mise en ligne et configuration

1. Sur GitHub : **Actions** → **Mise en ligne** → **Run workflow**. En une à deux minutes, la ligne devient verte et les
   fichiers apparaissent dans le dossier des outils (`app`, `public`, `.htaccess`…).
2. Dans le **Gestionnaire de fichiers** LWS, ouvrez le dossier des outils → `app` → copiez `config.example.php` en
   **`config.php`** et remplissez-le (base de données de l'étape 3, e-mail, votre adresse d'administrateur, une phrase
   d'installation). Ce fichier reste sur le serveur : il n'est jamais envoyé sur GitHub et la mise en ligne ne l'écrase pas.

### Étape 6 · Installation

1. Ouvrez **https://outils.yemkaf.fr/installer** : chaque ligne doit être verte (sinon, la page dit quoi corriger).
2. Tapez votre prénom, qui vous êtes dans l'équipe et la phrase d'installation → **Installer**.
   Les tables, votre compte administrateur, la feuille de route (79 tâches), les objectifs et le planning de départ
   sont créés. L'installateur se verrouille ensuite.
3. Connectez-vous avec votre adresse : vous recevez un code par e-mail.

### Étape 7 · Inviter l'équipe

**Votre prénom (en haut à droite) → Équipe et accès → Ajouter une personne** : prénom, e-mail, rôle, « qui dans
l'équipe » (relie la personne à ses tâches et au planning). Cochez « Lui envoyer un e-mail d'accueil ».

---

## Au quotidien

| Qui | Quoi |
|---|---|
| Barista de fermeture | **Journal du soir** (ticket Z, tasses, sachets, invendus, remarque) ; cocher ses tâches |
| Toute l'équipe | Ajouter ce qui manque dans **Commandes** ; consulter le **Planning** et les modules |
| Amer | Planning de la semaine, commandes, réceptions |
| Khaled | Tâches café et pros, simulateur de prix (café vert, prix pros) |
| Yousif | Simulateur financier, documents, versions |
| Administrateur | Équipe et accès, Paramètres, Journal d'activité |

Avant un changement important dans un simulateur : **Simulateurs → Versions enregistrées → Enregistrer**. Une
sauvegarde automatique est faite chaque jour avant la première modification.

---

## Faire évoluer l'application

- **Une modification de l'application** : je la prépare, vous la relisez sur GitHub, elle part en ligne dès qu'elle
  est fusionnée sur `main`. Rien d'autre à faire.
- **Les modules de référence** viennent du dépôt `Yemkaf-strategie`. Après une mise à jour de la stratégie :
  `node outils/importer-contenu.js <chemin du dépôt Yemkaf-strategie>`, puis envoi sur `main`.
- **Les données** (tâches, journal, documents, chiffres des simulateurs) ne sont jamais touchées par une mise en ligne.

## Sauvegardes

- **Base MySQL** : activez les sauvegardes automatiques de l'hébergeur et faites un export (phpMyAdmin) chaque mois.
- **Fichiers déposés** : dossier `storage/uploads` du dossier des outils, à copier par FTP chaque mois.
- **Simulateurs** : chaque version se télécharge en fichier depuis « Versions ».

## En cas de problème

- Les erreurs sont notées dans `storage/logs/erreurs.log` du dossier des outils (lisible avec le gestionnaire de fichiers LWS).
- Les codes de connexion n'arrivent pas : vérifiez la partie « mail » de `app/config.php` et les indésirables. Pour un
  essai sans envoi, mettez `'journal_seulement' => true` : les e-mails sont alors écrits dans `storage/logs/mails.log`.
- Pour voir le détail d'une erreur pendant un essai : `'debug' => true` dans `app/config.php`, puis remettez `false`.

## Essais en local (développement)

```
php -S 127.0.0.1:8099 -t public outils/routeur-dev.php
```
avec un `app/config.php` d'essai utilisant SQLite (`'dsn' => 'sqlite:/chemin/essai.db'`) et `'journal_seulement' => true`.

## Organisation du code

| Dossier | Contenu |
|---|---|
| `public/` | Racine du site : `index.php` (toutes les adresses passent par lui), styles, images, scripts |
| `app/` | Code (connexion, droits, pages), contenu des modules et des simulateurs, données de départ |
| `app/config.php` | Configuration du serveur, **jamais sur GitHub** |
| `storage/` | Fichiers déposés et journaux, **jamais sur GitHub**, inaccessible depuis le web |
| `outils/` | Import du contenu de la stratégie, serveur d'essai local |
| `.github/workflows/` | Mise en ligne automatique |

Aucune bibliothèque externe à installer : PHP 8.1 et MySQL suffisent.
