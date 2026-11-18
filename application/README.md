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

Compter environ une heure. Les étapes 2, 3 et 5 se font dans l'espace client de l'hébergeur.

### Étape 1 · Le dépôt GitHub

1. Sur GitHub, dans le compte Yem'kaf : **New repository** → nom `yemkaf-outils` → **Private** → **Create repository**.
2. Donnez-moi l'accès en installant l'application GitHub « Claude » sur ce dépôt :
   https://github.com/apps/claude/installations/select_target → choisir le compte Yem'kaf → **Only select repositories**
   → `yemkaf-outils` → **Install**.
3. Envoyez-moi le nom exact du dépôt (par exemple `yemkaf/yemkaf-outils`) : j'y envoie le code sur la branche `main`.

### Étape 2 · Le sous-domaine, PHP et le certificat

1. Créez le sous-domaine **outils.yemkaf.fr**.
   - Dossier du sous-domaine : **`outils/public`** (le dossier `public` à l'intérieur de `outils`).
   - Si l'hébergeur n'accepte qu'un dossier simple, choisissez `outils` : le fichier `.htaccess` fourni protège alors le
     reste du code.
2. Activez le **certificat SSL** (Let's Encrypt, gratuit) pour `outils.yemkaf.fr`.
3. Réglez **PHP 8.1 ou plus récent** pour ce sous-domaine.

### Étape 3 · La base de données et la boîte e-mail

1. **MySQL** : créez une base (par exemple `yemkaf_outils`), un utilisateur et son mot de passe. Notez les trois, et le
   nom du serveur (souvent `localhost`).
2. **E-mail** : créez la boîte **outils@yemkaf.fr**. Notez son mot de passe, le serveur SMTP et le port (465 ou 587).
   C'est elle qui envoie les codes de connexion.
3. **FTP** : créez un compte FTP **réservé au dossier `outils/`**. N'utilisez pas le compte FTP principal : il donne
   accès au site WordPress. Notez le serveur, l'identifiant et le mot de passe.

### Étape 4 · Les secrets GitHub (les accès FTP ne passent jamais par le chat)

Dans le dépôt GitHub : **Settings → Secrets and variables → Actions**.

1. Onglet **Secrets** → **New repository secret**, trois fois :
   - `FTP_SERVEUR` : le serveur FTP (par exemple `ftp.yemkaf.fr`) ;
   - `FTP_UTILISATEUR` : l'identifiant du compte FTP de l'étape 3 ;
   - `FTP_MOT_DE_PASSE` : son mot de passe.
2. Onglet **Variables** (facultatif) :
   - `FTP_PROTOCOLE` : `ftps` (par défaut, chiffré) ou `ftp` si l'hébergeur ne propose pas FTPS ;
   - `FTP_DOSSIER` : `./` si le compte FTP s'ouvre directement dans `outils/` (par défaut), sinon par exemple `outils/`.

### Étape 5 · Première mise en ligne et configuration

1. Dans GitHub : **Actions** → **Mise en ligne** → **Run workflow**. Au bout d'une à deux minutes, la ligne devient verte.
2. Dans le **gestionnaire de fichiers** de l'hébergeur, ouvrez `outils/app/`, copiez `config.example.php` en
   **`config.php`** et remplissez-le : base de données, e-mail, votre adresse d'administrateur, une phrase d'installation.
   Ce fichier reste sur le serveur : il n'est jamais envoyé sur GitHub et la mise en ligne ne l'écrase pas.

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
- **Fichiers déposés** : dossier `outils/storage/uploads`, à copier par FTP chaque mois.
- **Simulateurs** : chaque version se télécharge en fichier depuis « Versions ».

## En cas de problème

- Les erreurs sont notées dans `outils/storage/logs/erreurs.log` (lisible par FTP ou le gestionnaire de fichiers).
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
