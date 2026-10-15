<?php
/* ==========================================================================
   InfoLeader — includes/systeme.php
   Fonctions du module Maintenance :
     - fichiers temporaires (storage/tmp) : liste et purge ;
     - diagnostic « Système » : environnement, droits d'écriture, configuration ;
     - données de la « Sauvegarde » JSON (sans aucun secret).
   ========================================================================== */

/* ----- Fichiers temporaires ----- */

function fichiersTemporaires(): array
{
    $fichiers = [];
    // glob('*') ignore les fichiers cachés comme .htaccess
    foreach (glob(DOSSIER_TMP . '/*') ?: [] as $chemin) {
        if (is_file($chemin)) {
            $fichiers[] = [
                'nom'     => basename($chemin),
                'taille'  => filesize($chemin),
                'modifie' => filemtime($chemin),
            ];
        }
    }
    usort($fichiers, function ($a, $b) {
        return $b['modifie'] <=> $a['modifie'];
    });
    return $fichiers;
}

// Supprime définitivement les fichiers listés ci-dessus
function purgerTemporaires(): array
{
    $resultat = ['nombre' => 0, 'octets' => 0];
    foreach (fichiersTemporaires() as $fichier) {
        if (unlink(DOSSIER_TMP . '/' . $fichier['nom'])) {
            $resultat['nombre']++;
            $resultat['octets'] += $fichier['taille'];
        }
    }
    return $resultat;
}

function tailleLisible(int $octets): string
{
    if ($octets < 1024) {
        return $octets . ' o';
    }
    if ($octets < 1048576) {
        return number_format($octets / 1024, 1, ',', ' ') . ' Ko';
    }
    return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
}

/* ----- Diagnostic « Système » ----- */

function verification(string $libelle, string $valeur, string $statut): array
{
    return ['libelle' => $libelle, 'valeur' => $valeur, 'statut' => $statut];
}

function diagnosticSysteme(): array
{
    $sections = [];

    // 1. Fichiers temporaires
    $temporaires = fichiersTemporaires();
    $tailleTotale = array_sum(array_column($temporaires, 'taille'));
    $sections['tmp'] = [
        'titre'         => 'Fichiers temporaires (storage/tmp)',
        'fichiers'      => $temporaires,
        'verifications' => [
            verification(
                'Contenu du dossier',
                count($temporaires) . ' fichier(s) — ' . tailleLisible($tailleTotale),
                count($temporaires) > 50 || $tailleTotale > 10 * 1048576 ? 'attention' : 'ok'
            ),
        ],
    ];

    // 2. Environnement
    $sections['environnement'] = [
        'titre'         => 'Environnement',
        'verifications' => [
            verification('Version de PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'attention'),
            verification('Serveur web', $_SERVER['SERVER_SOFTWARE'] ?? 'inconnu', 'ok'),
            verification('Système d\'exploitation', PHP_OS_FAMILY, 'ok'),
            verification('Fuseau horaire', date_default_timezone_get(), 'ok'),
            verification('Extension OpenSSL (envoi SMTP)', extension_loaded('openssl') ? 'activée' : 'désactivée dans php.ini', extension_loaded('openssl') ? 'ok' : 'erreur'),
            verification('Extension mbstring', extension_loaded('mbstring') ? 'activée' : 'désactivée dans php.ini', extension_loaded('mbstring') ? 'ok' : 'erreur'),
            verification('Bibliothèque PHPMailer', phpMailerInstalle() ? 'installée (vendor/phpmailer)' : 'absente', phpMailerInstalle() ? 'ok' : 'attention'),
            verification('Connexion HTTPS', estHttps() ? 'oui' : 'non (normal en local)', 'ok'),
            verificationLogsMail(),
        ],
    ];

    // 3. Droits d'écriture
    $dossiers = [
        'config/'       => DOSSIER_CONFIG,
        'storage/data/' => DOSSIER_DATA,
        'storage/logs/' => DOSSIER_LOGS,
        'storage/tmp/'  => DOSSIER_TMP,
    ];
    $droits = [];
    foreach ($dossiers as $nom => $chemin) {
        $ok = is_dir($chemin) && is_writable($chemin);
        $droits[] = verification($nom, $ok ? 'écriture autorisée' : 'écriture impossible', $ok ? 'ok' : 'erreur');
    }
    $sections['droits'] = ['titre' => 'Droits d\'écriture', 'verifications' => $droits];

    // 4. Fichiers de configuration et protections
    $fichiers = [
        'config/admin.json'    => [FICHIER_ADMIN, 'erreur'],
        'config/mail.json'     => [FICHIER_MAIL, 'attention'],
        'config/securite.json' => [FICHIER_SECURITE, 'attention'],
    ];
    $configs = [];
    foreach ($fichiers as $nom => [$chemin, $gravite]) {
        if (!is_file($chemin)) {
            $configs[] = verification($nom, 'absent', $gravite);
        } elseif (!is_array(json_decode((string) file_get_contents($chemin), true))) {
            $configs[] = verification($nom, 'contenu JSON invalide', 'erreur');
        } else {
            $configs[] = verification($nom, 'valide — modifié le ' . date('d/m/Y à H:i', filemtime($chemin)), 'ok');
        }
    }
    // Protection réelle des dossiers sensibles (SEC-2) : on demande à Apache un fichier
    // de chaque dossier, comme le ferait un visiteur. Seule une réponse 403 est acceptable.
    $fichiersTemoins = [
        'config'   => 'config/securite.json',
        'includes' => 'includes/bootstrap.php',
        'storage'  => 'storage/data/.gitkeep',
        'vendor'   => 'vendor/phpmailer/phpmailer/LICENSE',
    ];
    foreach ($fichiersTemoins as $dossier => $temoin) {
        $configs[] = verifierAccesWeb($dossier, $temoin);
    }
    $sections['configuration'] = ['titre' => 'Fichiers de configuration', 'verifications' => $configs];

    // État global et état de chaque section
    $total = 0;
    $problemes = 0;
    $global = 'ok';
    foreach ($sections as $cle => $section) {
        $statut = 'ok';
        foreach ($section['verifications'] as $verif) {
            $total++;
            if ($verif['statut'] !== 'ok') {
                $problemes++;
                $statut = ($statut === 'erreur' || $verif['statut'] === 'erreur') ? 'erreur' : 'attention';
            }
        }
        $sections[$cle]['statut'] = $statut;
        if ($statut === 'erreur' || ($statut === 'attention' && $global === 'ok')) {
            $global = $statut;
        }
    }

    return ['sections' => $sections, 'global' => $global, 'total' => $total, 'problemes' => $problemes];
}

/* ----- Logs de débogage des e-mails (tâche SEC-5) ----- */

function tailleLogMail(): int
{
    return is_file(FICHIER_LOG_MAIL) ? (int) filesize(FICHIER_LOG_MAIL) : 0;
}

// Activés = point d'attention : mail.log contient le contenu des e-mails, dont les codes de réinitialisation
function verificationLogsMail(): array
{
    $taille = tailleLisible(tailleLogMail());
    return configMail()['logs_debogage']
        ? verification('Logs de débogage des e-mails', "activés (mail.log : $taille) — à désactiver après les tests", 'attention')
        : verification('Logs de débogage des e-mails', "désactivés (mail.log : $taille)", 'ok');
}

/* ----- Protection réelle des dossiers sensibles (tâche SEC-2) ----- */

// Code HTTP renvoyé par Apache pour un fichier du site, demandé comme le ferait un visiteur.
// Renvoie null si la requête est impossible (appel hors navigateur, serveur injoignable).
function codeHttpFichier(string $cheminRelatif): ?int
{
    if (empty($_SERVER['HTTP_HOST']) || empty($_SERVER['SCRIPT_NAME'])) {
        return null;
    }
    // Racine web du site : « /infoleader » via localhost, vide via infoleader.local
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    $url = (estHttps() ? 'https' : 'http') . '://127.0.0.1:' . $port . $base . '/' . $cheminRelatif;

    $contexte = stream_context_create([
        'http' => [
            'method'          => 'HEAD',                              // seul le code de réponse nous intéresse
            'header'          => 'Host: ' . $_SERVER['HTTP_HOST'],    // même site (virtual host) que la page en cours
            'timeout'         => 3,
            'ignore_errors'   => true,                                // récupérer aussi les réponses 403 ou 404
            'follow_location' => 0,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],  // connexion locale à 127.0.0.1
    ]);
    $resultat = @file_get_contents($url, false, $contexte);
    $entetes = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?? [])
        : ($http_response_header ?? []);
    if ($resultat === false && !$entetes) {
        return null;
    }
    return preg_match('#^HTTP/\S+ (\d{3})#', $entetes[0] ?? '', $code) ? (int) $code[1] : null;
}

function verifierAccesWeb(string $dossier, string $fichierTemoin): array
{
    $libelle = "Accès web à $dossier/";
    if (!is_file(RACINE . '/' . $fichierTemoin)) {
        return verification($libelle, "non testé : fichier témoin $fichierTemoin absent", 'attention');
    }
    $code = codeHttpFichier($fichierTemoin);
    if ($code === 403) {
        return verification($libelle, 'refusé (HTTP 403) : dossier protégé', 'ok');
    }
    if ($code === null) {
        return verification($libelle, 'test impossible : le serveur web ne répond pas', 'attention');
    }
    if ($code >= 200 && $code < 400) {
        // Le fichier est lisible par n'importe quel visiteur : risque de fuite de secrets
        $cause = is_file(RACINE . "/$dossier/.htaccess")
            ? 'le .htaccess existe mais Apache l\'ignore (vérifier AllowOverride)'
            : 'fichier .htaccess absent';
        return verification($libelle, "OUVERT (HTTP $code) : $cause", 'erreur');
    }
    $aide = $code === 404 ? ' : site introuvable à cette adresse (virtual host pas encore actif ? redémarrer Apache)' : '';
    return verification($libelle, "réponse inattendue (HTTP $code)$aide", 'attention');
}

/* ----- Sauvegarde JSON ----- */

// Les secrets (mot de passe administrateur, clé SMTP, jeton d'API) ne sont jamais exportés
function donneesSauvegarde(): array
{
    $retire = '[retiré de l\'export]';
    $admin = lireAdmin();
    $mail = configMail();
    $mail['smtp_mot_de_passe'] = $mail['smtp_mot_de_passe'] !== '' ? $retire : '';

    return [
        'export' => [
            'genere_le' => maintenant(),
            'format'    => 'Sauvegarde InfoLeader (JSON)',
            'remarque'  => 'Les secrets (mot de passe administrateur, mot de passe SMTP, jeton d\'API) sont retirés de ce fichier.',
        ],
        'application' => [
            'nom'             => 'InfoLeader',
            'adresse'         => (estHttps() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'version_php'     => PHP_VERSION,
            'fuseau_horaire'  => date_default_timezone_get(),
            'nombre_produits' => count(lireJson(RACINE . '/products.json')),
        ],
        'compte_administrateur' => [
            'email'                   => $admin['email'] ?? null,
            'mot_de_passe'            => $retire,
            'cree_le'                 => $admin['cree_le'] ?? null,
            'mot_de_passe_modifie_le' => $admin['mot_de_passe_modifie_le'] ?? null,
            'email_modifie_le'        => $admin['email_modifie_le'] ?? null,
            'derniere_connexion'      => $admin['derniere_connexion'] ?? null,
        ],
        'envoi_mails' => $mail,
        'api' => [
            'active'   => false,
            'jeton'    => null,
            'remarque' => 'Aucune API n\'est utilisée dans cette version du site.',
        ],
        'limitation_debit_securite' => array_merge(configSecurite(), [
            'ip_bloquees_actuellement' => count(ipsBloquees()),
        ]),
    ];
}
