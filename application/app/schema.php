<?php
/*
 * Structure de la base, écrite une fois pour MySQL/MariaDB (hébergement) et SQLite (essais).
 */
declare(strict_types=1);

const SCHEMA_VERSION = 1;

function schema_sql(string $driver): array
{
    $my = $driver === 'mysql';
    $T = [
        'ID' => $my ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'REF' => $my ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
        'INT' => $my ? 'INT NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'INTN' => $my ? 'INT NULL' : 'INTEGER NULL',
        'DT' => $my ? 'DATETIME NULL' : 'TEXT NULL',
        'DATE' => $my ? 'DATE NULL' : 'TEXT NULL',
        'TXT' => 'TEXT NULL',
        'BIG' => $my ? 'MEDIUMTEXT NULL' : 'TEXT NULL',
        'DEC' => $my ? 'DECIMAL(10,2) NULL' : 'NUMERIC NULL',
    ];
    $s = function (int $n) { return "VARCHAR($n) NOT NULL DEFAULT ''"; };
    $fin = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $t = [];
    $t[] = "CREATE TABLE settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v {$T['BIG']})$fin";
    $t[] = "CREATE TABLE users (id {$T['ID']}, email VARCHAR(191) NOT NULL UNIQUE, prenom {$s(100)}, poste {$s(100)}, cle {$s(30)},
        role {$s(20)}, actif {$T['INT']}, created_at {$T['DT']}, updated_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE login_tokens (id {$T['ID']}, user_id {$T['REF']}, token_hash {$s(64)}, code_hash {$s(64)}, nonce_hash {$s(64)},
        tentatives {$T['INT']}, expires_at {$T['DT']}, used_at {$T['DT']}, ip {$s(45)}, created_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE sessions (id {$T['ID']}, user_id {$T['REF']}, token_hash VARCHAR(64) NOT NULL UNIQUE, device_hash {$s(64)},
        device {$s(120)}, ip {$s(45)}, created_at {$T['DT']}, last_seen {$T['DT']}, revoked_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE activity (id {$T['ID']}, user_id {$T['REF']}, action {$s(60)}, detail {$s(500)}, ip {$s(45)}, created_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE tasks (id {$T['ID']}, titre {$s(200)}, detail {$T['TXT']}, cle {$s(30)}, echeance {$T['DATE']},
        echeance_libelle {$s(60)}, periode {$s(120)}, module {$s(30)}, statut {$s(12)}, ordre {$T['INT']},
        done_by {$T['REF']}, done_at {$T['DT']}, created_by {$T['REF']}, created_at {$T['DT']}, updated_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE task_comments (id {$T['ID']}, task_id {$T['REF']}, user_id {$T['REF']}, texte {$T['TXT']}, created_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE journal (id {$T['ID']}, jour VARCHAR(10) NOT NULL UNIQUE, ca_ttc {$T['DEC']}, tasses {$T['INTN']},
        sachets {$T['INTN']}, invendus {$T['INTN']}, remarque {$T['TXT']}, incident {$T['TXT']}, user_id {$T['REF']}, updated_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE planning (id {$T['ID']}, semaine VARCHAR(10) NOT NULL UNIQUE, data {$T['BIG']}, updated_by {$T['REF']}, updated_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE commandes (id {$T['ID']}, produit {$s(200)}, quantite {$s(60)}, fournisseur {$s(120)}, urgent {$T['INT']},
        statut {$s(12)}, note {$s(300)}, demande_par {$T['REF']}, demande_le {$T['DT']}, commande_par {$T['REF']}, commande_le {$T['DT']},
        recu_par {$T['REF']}, recu_le {$T['DT']})$fin";
    $t[] = "CREATE TABLE data_docs (id {$T['ID']}, cle VARCHAR(60) NOT NULL UNIQUE, data {$T['BIG']}, version {$T['INT']}, updated_by {$T['REF']}, updated_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE versions (id {$T['ID']}, cle {$s(60)}, nom {$s(150)}, data {$T['BIG']}, auto {$T['INT']}, created_by {$T['REF']}, created_at {$T['DT']})$fin";
    $t[] = "CREATE TABLE documents (id {$T['ID']}, groupe {$s(40)}, titre {$s(200)}, categorie {$s(30)}, date_doc {$T['DATE']}, version {$T['INT']},
        fichier {$s(255)}, nom_original {$s(255)}, mime {$s(120)}, taille {$T['INT']}, visibilite {$s(12)}, note {$T['TXT']},
        created_by {$T['REF']}, created_at {$T['DT']})$fin";
    $t[] = "CREATE INDEX idx_tasks_cle ON tasks (cle)";
    $t[] = "CREATE INDEX idx_tasks_echeance ON tasks (echeance)";
    $t[] = "CREATE INDEX idx_comments_task ON task_comments (task_id)";
    $t[] = "CREATE INDEX idx_activity_date ON activity (created_at)";
    $t[] = "CREATE INDEX idx_sessions_user ON sessions (user_id)";
    $t[] = "CREATE INDEX idx_tokens_nonce ON login_tokens (nonce_hash)";
    $t[] = "CREATE INDEX idx_versions_cle ON versions (cle)";
    $t[] = "CREATE INDEX idx_documents_groupe ON documents (groupe)";
    return $t;
}
