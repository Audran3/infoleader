<?php
/* ==========================================================================
   InfoLeader — includes/bootstrap.php
   Fichier commun inclus par toutes les pages du panel (et par contact.php) :
   chemins du projet, fuseau horaire, petites fonctions utilitaires.
   Le dossier includes/ est interdit d'accès depuis le navigateur (.htaccess).
   ========================================================================== */

// ----- Chemins du projet -----
define('RACINE', dirname(__DIR__));
define('DOSSIER_CONFIG', RACINE . '/config');
define('DOSSIER_STORAGE', RACINE . '/storage');
define('DOSSIER_DATA', DOSSIER_STORAGE . '/data');
define('DOSSIER_LOGS', DOSSIER_STORAGE . '/logs');
define('DOSSIER_TMP', DOSSIER_STORAGE . '/tmp');

// ----- Fichiers de configuration (modifiés depuis le panel) -----
define('FICHIER_ADMIN', DOSSIER_CONFIG . '/admin.json');
define('FICHIER_MAIL', DOSSIER_CONFIG . '/mail.json');
define('FICHIER_SECURITE', DOSSIER_CONFIG . '/securite.json');

// ----- Données produites par l'application (pas de base de données) -----
define('FICHIER_LIMITATION', DOSSIER_DATA . '/limitation.json');
define('FICHIER_REINITIALISATION', DOSSIER_DATA . '/reinitialisation.json');
define('FICHIER_DEMANDES_CODE', DOSSIER_DATA . '/demandes_code.json');
define('FICHIER_JOURNAL', DOSSIER_DATA . '/journal_connexion.json');
define('FICHIER_CONTACTS', DOSSIER_DATA . '/messages_contact.json');
define('FICHIER_LOG_MAIL', DOSSIER_LOGS . '/mail.log');

date_default_timezone_set('Europe/Paris');

require_once __DIR__ . '/stockage.php';
require_once __DIR__ . '/journal.php';
require_once __DIR__ . '/securite.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/systeme.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/captcha.php';

// Échappe un texte avant de l'afficher dans le HTML (protection contre l'injection de code)
function e($texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

// Adresse IP du visiteur. REMOTE_ADDR est la seule valeur qu'un visiteur ne peut pas falsifier.
function ipClient(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
}

function maintenant(): string
{
    return date('Y-m-d H:i:s');
}

// « 2026-10-15 14:32:00 » => « 15/10/2026 à 14:32 »
function dateFr(?string $date): string
{
    $horodatage = $date ? strtotime($date) : false;
    return $horodatage ? date('d/m/Y', $horodatage) . ' à ' . date('H:i', $horodatage) : '—';
}
