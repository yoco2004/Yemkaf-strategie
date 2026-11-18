<?php
/*
 * Configuration de Yem'kaf Outils.
 *
 * Sur le serveur : copiez ce fichier en « app/config.php » (avec le gestionnaire de fichiers de l'hébergeur),
 * puis remplissez-le. Le fichier config.php ne doit JAMAIS être envoyé sur GitHub : il contient des mots de passe.
 */
return [
    // Adresse de l'application, sans « / » à la fin.
    'app_url' => 'https://outils.yemkaf.fr',
    // Laisser vide si l'application est à la racine du sous-domaine.
    'base_path' => '',

    // Base MySQL créée dans l'espace client de l'hébergeur (nom de la base, utilisateur, mot de passe, serveur).
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=NOM_DE_LA_BASE;charset=utf8mb4',
        'user' => 'UTILISATEUR_DE_LA_BASE',
        'pass' => 'MOT_DE_PASSE_DE_LA_BASE',
    ],

    // Envoi des e-mails de connexion.
    // Le plus fiable : une boîte e-mail créée chez l'hébergeur (par exemple outils@yemkaf.fr) et ses réglages SMTP.
    // Laisser « smtp_host » vide pour utiliser la fonction mail() de l'hébergeur (souvent classée en indésirables).
    'mail' => [
        'de' => 'outils@yemkaf.fr',
        'de_nom' => "Yem'kaf Outils",
        'smtp_host' => 'SERVEUR_SMTP_DE_L_HEBERGEUR',
        'smtp_port' => 465,          // 465 avec 'ssl', ou 587 avec 'tls'
        'smtp_secure' => 'ssl',
        'smtp_user' => 'outils@yemkaf.fr',
        'smtp_pass' => 'MOT_DE_PASSE_DE_LA_BOITE',
        'journal_seulement' => false, // true : n'envoie rien, écrit les e-mails dans storage/logs/mails.log (essais)
    ],

    // Adresse du premier administrateur, créé par l'installateur.
    'admin_email' => 'VOTRE_ADRESSE@exemple.fr',

    // Phrase secrète demandée une seule fois par l'installateur (12 caractères minimum). Inventez-la.
    'install_key' => 'UNE_PHRASE_LONGUE_QUE_VOUS_SEUL_CONNAISSEZ',

    // Taille maximale d'un fichier déposé, en Mo (l'hébergeur peut imposer moins).
    'max_upload_mo' => 20,

    // true seulement pour chercher une erreur : affiche les détails techniques.
    'debug' => false,
];
