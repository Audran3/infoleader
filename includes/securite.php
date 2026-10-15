<?php
/* ==========================================================================
   InfoLeader — includes/securite.php
   Sécurité du panel :
     1. Session (cookie protégé, en-têtes de sécurité)
     2. Jeton CSRF et messages flash
     3. Authentification de l'administrateur
     4. Anti-brute force : attente progressive puis blocage de l'adresse IP
     5. Règles des mots de passe
     6. Demande de réinitialisation (code à 8 chiffres)
   ========================================================================== */

/* ----- 1. Session ----- */

function estHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');  // refuse un identifiant de session inventé
    ini_set('session.use_only_cookies', '1');
    session_name('INFOLEADER_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie supprimé à la fermeture du navigateur
        'path'     => '/',
        'httponly' => true,       // illisible par JavaScript
        'samesite' => 'Strict',   // jamais envoyé depuis un autre site
        'secure'   => estHttps(),
    ]);
    session_start();

    // En-têtes de sécurité communs à toutes les pages du panel
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

function rediriger(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ----- 2. Jeton CSRF et messages flash ----- */

// Jeton secret ajouté à chaque formulaire : un autre site ne peut pas le deviner
function jetonCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jetonCsrf()) . '">';
}

function csrfValide(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals(jetonCsrf(), $_POST['csrf']);
}

// Message affiché une seule fois sur la page suivante. $type : succes, erreur, info, attention
function flash(string $type, string $texte, array $details = []): void
{
    $_SESSION['flash'][] = ['type' => $type, 'texte' => $texte, 'details' => $details];
}

function lireFlashs(): array
{
    $flashs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashs;
}

/* ----- 3. Authentification ----- */

// Empreinte du compte : elle change si le mot de passe ou l'e-mail change,
// ce qui déconnecte automatiquement les autres sessions ouvertes.
function empreinteAdmin(array $admin): string
{
    return hash('sha256', ($admin['password_hash'] ?? '') . '|' . ($admin['email'] ?? ''));
}

function hacherMotDePasse(string $motDePasse): string
{
    // Argon2id (recommandé par l'OWASP) si PHP le propose, sinon bcrypt
    return password_hash($motDePasse, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

function connecterAdmin(array $admin): void
{
    session_regenerate_id(true);  // nouvel identifiant : empêche la fixation de session
    $_SESSION['admin'] = [
        'connecte_le'         => maintenant(),
        'ip'                  => ipClient(),
        'empreinte'           => empreinteAdmin($admin),
        'connexion_precedente' => $admin['derniere_connexion'] ?? null,
    ];
    $_SESSION['derniere_activite'] = time();
}

function deconnecterAdmin(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// À appeler en haut de chaque page protégée. Renvoie le compte administrateur.
function exigerConnexion(): array
{
    demarrerSession();
    $admin = lireAdmin();

    if (empty($_SESSION['admin'])) {
        rediriger('login.php');
    }
    $inactivite = time() - ($_SESSION['derniere_activite'] ?? 0);
    if ($inactivite > configSecurite()['duree_session_minutes'] * 60) {
        deconnecterAdmin();
        flash('info', 'Votre session a expiré après une période d\'inactivité. Veuillez vous reconnecter.');
        rediriger('login.php');
    }
    if (!hash_equals($_SESSION['admin']['empreinte'], empreinteAdmin($admin))) {
        deconnecterAdmin();
        flash('info', 'Les identifiants du compte ont été modifiés. Veuillez vous reconnecter.');
        rediriger('login.php');
    }
    $_SESSION['derniere_activite'] = time();
    return $admin;
}

// Après un changement de mot de passe ou d'e-mail, la session en cours reste valide
function actualiserEmpreinte(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin']['empreinte'] = empreinteAdmin($admin);
}

// Vérifie le mot de passe actuel dans les modules Mot de passe et Utilisateur.
// Après 5 erreurs, la session est fermée (protection si la session a été volée).
function verifierMotDePasseActuel(array $admin, string $saisie, string $module): bool
{
    if (password_verify($saisie, $admin['password_hash'] ?? '')) {
        unset($_SESSION['echecs_mdp_actuel']);
        return true;
    }
    $_SESSION['echecs_mdp_actuel'] = ($_SESSION['echecs_mdp_actuel'] ?? 0) + 1;
    journaliser('echec', "Module $module : mot de passe actuel incorrect.");

    if ($_SESSION['echecs_mdp_actuel'] >= 5) {
        journaliser('blocage', "Déconnexion forcée après 5 erreurs de mot de passe (module $module).");
        deconnecterAdmin();
        flash('erreur', 'Trop d\'erreurs de mot de passe : vous avez été déconnecté par sécurité.');
        rediriger('login.php');
    }
    return false;
}

/* ----- 4. Anti-brute force (limitation par adresse IP) ----- */

function etatLimitation(string $ip): array
{
    $vide = ['echecs' => 0, 'dernier_echec' => 0, 'prochain_essai' => 0, 'bloque_jusqua' => 0];
    $etat = array_merge($vide, lireJson(FICHIER_LIMITATION)[$ip] ?? []);

    // Hors blocage, les échecs anciens sont oubliés
    $oubli = configSecurite()['oubli_echecs_minutes'] * 60;
    if ($etat['bloque_jusqua'] <= time() && time() - $etat['dernier_echec'] > $oubli) {
        return $vide;
    }
    return $etat;
}

// null si une tentative est autorisée, sinon ['bloque' => bool, 'secondes' => restant, 'jusqua' => horodatage]
function restrictionEnCours(string $ip): ?array
{
    $etat = etatLimitation($ip);
    $maintenant = time();
    if ($etat['bloque_jusqua'] > $maintenant) {
        return ['bloque' => true, 'secondes' => $etat['bloque_jusqua'] - $maintenant, 'jusqua' => $etat['bloque_jusqua']];
    }
    if ($etat['prochain_essai'] > $maintenant) {
        return ['bloque' => false, 'secondes' => $etat['prochain_essai'] - $maintenant, 'jusqua' => $etat['prochain_essai']];
    }
    return null;
}

// Enregistre un échec et renvoie la restriction qui s'applique désormais
function enregistrerEchec(string $ip): array
{
    $cfg = configSecurite();
    $etat = etatLimitation($ip);
    $etat['echecs']++;
    $etat['dernier_echec'] = time();

    if ($etat['echecs'] >= $cfg['echecs_avant_blocage']) {
        // Persistance dans l'erreur (robot ou pirate) : blocage de plusieurs heures
        $etat['bloque_jusqua'] = time() + $cfg['duree_blocage_heures'] * 3600;
        $restriction = ['bloque' => true, 'secondes' => $cfg['duree_blocage_heures'] * 3600, 'jusqua' => $etat['bloque_jusqua']];
    } else {
        // Attente progressive : 3 s, puis 10 s, 30 s, 60 s…
        $attente = 0;
        foreach ($cfg['paliers_attente'] as $palier) {
            if ($etat['echecs'] >= $palier['a_partir_de']) {
                $attente = (int) $palier['secondes'];
            }
        }
        $etat['prochain_essai'] = time() + $attente;
        $restriction = ['bloque' => false, 'secondes' => $attente, 'jusqua' => $etat['prochain_essai']];
    }

    $toutes = lireJson(FICHIER_LIMITATION);
    $toutes[$ip] = $etat;
    ecrireJson(FICHIER_LIMITATION, $toutes);
    return $restriction;
}

function reinitialiserLimitation(string $ip): void
{
    $toutes = lireJson(FICHIER_LIMITATION);
    unset($toutes[$ip]);
    ecrireJson(FICHIER_LIMITATION, $toutes);
}

function ipsBloquees(): array
{
    $bloquees = array_filter(lireJson(FICHIER_LIMITATION), function ($etat) {
        return ($etat['bloque_jusqua'] ?? 0) > time();
    });
    return array_keys($bloquees);
}

/* ----- 5. Règles des mots de passe ----- */

const MDP_LONGUEUR_MIN = 20;
const MDP_LONGUEUR_MAX = 128;

// Règles affichées dans les formulaires (les clés sont reprises par admin.js)
function reglesMotDePasse(): array
{
    return [
        'longueur'  => 'Au moins ' . MDP_LONGUEUR_MIN . ' caractères',
        'majuscule' => 'Au moins une lettre majuscule (A à Z)',
        'minuscule' => 'Au moins une lettre minuscule (a à z)',
        'chiffre'   => 'Au moins un chiffre (0 à 9)',
        'special'   => 'Au moins un caractère spécial (! ? @ # $ % & * - _ …)',
    ];
}

// Contrôle côté serveur : renvoie la liste des règles non respectées
function erreursMotDePasse(string $motDePasse, string $confirmation): array
{
    $longueur = mb_strlen($motDePasse, 'UTF-8');
    $erreurs = [];
    if ($longueur < MDP_LONGUEUR_MIN) {
        $erreurs[] = 'Le mot de passe doit contenir au moins ' . MDP_LONGUEUR_MIN . ' caractères (actuellement : ' . $longueur . ').';
    }
    if ($longueur > MDP_LONGUEUR_MAX) {
        $erreurs[] = 'Le mot de passe ne doit pas dépasser ' . MDP_LONGUEUR_MAX . ' caractères.';
    }
    if (!preg_match('/[A-Z]/', $motDePasse)) {
        $erreurs[] = 'Il doit contenir au moins une lettre majuscule.';
    }
    if (!preg_match('/[a-z]/', $motDePasse)) {
        $erreurs[] = 'Il doit contenir au moins une lettre minuscule.';
    }
    if (!preg_match('/[0-9]/', $motDePasse)) {
        $erreurs[] = 'Il doit contenir au moins un chiffre.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $motDePasse)) {
        $erreurs[] = 'Il doit contenir au moins un caractère spécial.';
    }
    if ($motDePasse !== $confirmation) {
        $erreurs[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
    }
    return $erreurs;
}

/* ----- 6. Demande de réinitialisation (mot de passe oublié) ----- */

// SEC-4 : horodatages des demandes de code faites par cette adresse IP pendant les $minutes dernières minutes
function demandesCodeRecentes(string $ip, int $minutes): array
{
    $depuis = time() - $minutes * 60;
    return array_values(array_filter(lireJson(FICHIER_DEMANDES_CODE)[$ip] ?? [], function ($horodatage) use ($depuis) {
        return $horodatage >= $depuis;
    }));
}

// SEC-4 : enregistre une demande de code (et oublie les demandes de plus d'une fenêtre, toutes IP confondues)
function enregistrerDemandeCode(string $ip, int $minutes): void
{
    $depuis = time() - $minutes * 60;
    $toutes = [];
    foreach (lireJson(FICHIER_DEMANDES_CODE) as $adresse => $horodatages) {
        $recents = array_values(array_filter((array) $horodatages, function ($h) use ($depuis) {
            return $h >= $depuis;
        }));
        if ($recents) {
            $toutes[$adresse] = $recents;
        }
    }
    $toutes[$ip][] = time();
    ecrireJson(FICHIER_DEMANDES_CODE, $toutes);
}

// Renvoie la demande en cours, ou null s'il n'y en a pas (ou si le code a expiré)
function demandeReinitialisation(): ?array
{
    $demande = lireJson(FICHIER_REINITIALISATION);
    return (!empty($demande['expire']) && $demande['expire'] > time()) ? $demande : null;
}
