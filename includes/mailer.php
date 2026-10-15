<?php
/* ==========================================================================
   InfoLeader — includes/mailer.php
   Envoi des e-mails selon le mode choisi dans « Configuration des e-mails » :
     - PHP mail : fonction mail() de PHP, utilisable uniquement chez un hébergeur web ;
     - SMTP     : serveur d'envoi de la messagerie (ex. mail.olivier-dutoit.fr) via la
                  bibliothèque PHPMailer, fonctionne partout, y compris en local sous WAMP.
   ========================================================================== */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as ExceptionPHPMailer;

define('DOSSIER_PHPMAILER', RACINE . '/vendor/phpmailer/phpmailer/src');

function phpMailerInstalle(): bool
{
    return is_file(DOSSIER_PHPMAILER . '/PHPMailer.php');
}

// Envoie un e-mail texte au destinataire configuré. Renvoie ['ok' => bool, 'erreur' => ?string]
function envoyerMail(string $sujet, string $texte): array
{
    $cfg = configMail();

    if (!filter_var($cfg['destinataire'], FILTER_VALIDATE_EMAIL)) {
        $resultat = ['ok' => false, 'erreur' => 'aucun destinataire valide n\'est configuré (module « Configuration des e-mails »).'];
    } elseif (!filter_var($cfg['expediteur_email'], FILTER_VALIDATE_EMAIL)) {
        $resultat = ['ok' => false, 'erreur' => 'aucune adresse d\'expéditeur valide n\'est configurée (module « Configuration des e-mails »).'];
    } elseif ($cfg['mode'] === 'smtp') {
        $resultat = envoyerViaSmtp($cfg, $sujet, $texte);
    } else {
        $resultat = envoyerViaPhpMail($cfg, $sujet, $texte);
    }

    if ($cfg['logs_debogage']) {
        journaliserMail($cfg, $sujet, $texte, $resultat);
    }
    return $resultat;
}

function envoyerViaSmtp(array $cfg, string $sujet, string $texte): array
{
    if (!phpMailerInstalle()) {
        return ['ok' => false, 'erreur' => 'PHPMailer n\'est pas installé (dossier vendor/phpmailer/phpmailer/src).'];
    }
    if (!extension_loaded('openssl')) {
        return ['ok' => false, 'erreur' => 'l\'extension OpenSSL n\'est pas activée dans php.ini (obligatoire pour le chiffrement TLS/SSL).'];
    }

    require_once DOSSIER_PHPMAILER . '/Exception.php';
    require_once DOSSIER_PHPMAILER . '/PHPMailer.php';
    require_once DOSSIER_PHPMAILER . '/SMTP.php';

    $mail = new PHPMailer(true);  // true : chaque erreur déclenche une exception
    $dialogue = [];
    if ($cfg['logs_debogage']) {
        // Dialogue avec le serveur SMTP, recopié dans mail.log pour comprendre un échec
        $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
        $mail->Debugoutput = function ($ligne) use (&$dialogue) {
            if (count($dialogue) < 300) {
                $dialogue[] = rtrim($ligne);
            }
        };
    }
    try {
        $mail->isSMTP();
        $mail->Host     = $cfg['smtp_hote'];
        $mail->Port     = (int) $cfg['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['smtp_identifiant'];
        $mail->Password = $cfg['smtp_mot_de_passe'];
        // Port 587 : connexion puis passage en chiffré (STARTTLS) — port 465 : chiffré dès le départ (SSL)
        $mail->SMTPSecure = $cfg['smtp_securite'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout  = 15;
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;

        $mail->setFrom($cfg['expediteur_email'], $cfg['expediteur_nom']);
        $mail->addAddress($cfg['destinataire']);
        $mail->Subject = $sujet;
        $mail->Body    = $texte;

        $mail->send();
        return ['ok' => true, 'erreur' => null, 'dialogue' => masquerAuthentification($dialogue)];
    } catch (ExceptionPHPMailer $exception) {
        return ['ok' => false, 'erreur' => $mail->ErrorInfo ?: $exception->getMessage(), 'dialogue' => masquerAuthentification($dialogue)];
    }
}

// Pendant la commande AUTH, l'identifiant et le mot de passe circulent encodés en base64 :
// ces lignes sont masquées avant d'être écrites dans le fichier de log.
function masquerAuthentification(array $dialogue): array
{
    $enAuthentification = false;
    foreach ($dialogue as $i => $ligne) {
        if (preg_match('/CLIENT -> SERVER: AUTH\b/i', $ligne)) {
            $enAuthentification = true;
            $dialogue[$i] = preg_replace('/(CLIENT -> SERVER: AUTH \S+).*/i', '$1 [masqué]', $ligne);
        } elseif ($enAuthentification && strpos($ligne, 'CLIENT -> SERVER:') !== false) {
            $dialogue[$i] = preg_replace('/(CLIENT -> SERVER:).*/', '$1 [masqué]', $ligne);
        } elseif ($enAuthentification && preg_match('/SERVER -> CLIENT: (235|[45]\d\d)/', $ligne)) {
            $enAuthentification = false;  // authentification terminée (réussie ou refusée)
        }
    }
    return $dialogue;
}

function envoyerViaPhpMail(array $cfg, string $sujet, string $texte): array
{
    // Les retours à la ligne sont retirés du nom : ils permettraient d'injecter des en-têtes
    $nom = str_replace(["\r", "\n"], ' ', $cfg['expediteur_nom']);
    $entetes = [
        'From'         => mb_encode_mimeheader($nom, 'UTF-8') . ' <' . $cfg['expediteur_email'] . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ];
    if (@mail($cfg['destinataire'], mb_encode_mimeheader($sujet, 'UTF-8'), $texte, $entetes)) {
        return ['ok' => true, 'erreur' => null];
    }
    // Avec html_errors activé (WAMP), le message de PHP contient du HTML (&quot;…) : on le remet en texte brut
    $derniere = error_get_last();
    $detail = $derniere ? html_entity_decode(strip_tags($derniere['message']), ENT_QUOTES, 'UTF-8') : '';
    return [
        'ok'     => false,
        'erreur' => 'la fonction mail() a échoué' . ($detail ? ' (' . $detail . ')' : '')
                  . '. C\'est normal en local sous WAMP : utilisez l\'envoi SMTP.',
    ];
}

// Logs de débogage : chaque e-mail est consigné dans storage/logs/mail.log (jamais la clé SMTP)
function journaliserMail(array $cfg, string $sujet, string $texte, array $resultat): void
{
    $entree = sprintf(
        "[%s] mode=%s | de=%s | à=%s | sujet=%s\nRésultat : %s\n%s\n%s\n",
        maintenant(),
        $cfg['mode'],
        $cfg['expediteur_email'] ?: '(vide)',
        $cfg['destinataire'] ?: '(vide)',
        $sujet,
        $resultat['ok'] ? 'ENVOYÉ' : 'ÉCHEC — ' . $resultat['erreur'],
        $texte,
        str_repeat('-', 70)
    );
    // En cas d'échec SMTP, on ajoute le dialogue avec le serveur (identifiants masqués)
    if (!$resultat['ok'] && !empty($resultat['dialogue'])) {
        $entree .= "Dialogue SMTP :\n" . implode("\n", $resultat['dialogue']) . "\n" . str_repeat('-', 70) . "\n";
    }
    if (!is_dir(DOSSIER_LOGS)) {
        mkdir(DOSSIER_LOGS, 0775, true);
    }
    file_put_contents(FICHIER_LOG_MAIL, $entree, FILE_APPEND | LOCK_EX);
}

// SEC-9 : alerte envoyée après chaque changement sensible du compte administrateur.
// Si le changement n'a pas été fait par l'administrateur, il est prévenu tout de suite.
function notifierChangementSecurite(string $titre, string $description): array
{
    $texte = "Bonjour,\n\n$description\n\n"
        . 'Date : ' . date('d/m/Y') . ' à ' . date('H:i') . "\n"
        . 'Adresse IP : ' . ipClient() . "\n\n"
        . "Si vous êtes à l'origine de cette modification, vous n'avez rien à faire.\n"
        . "Sinon, utilisez immédiatement « Mot de passe oublié » sur la page de connexion du panel, "
        . "puis consultez le journal de connexion (Maintenance > Journaux).\n\n"
        . "— InfoLeader";
    $envoi = envoyerMail('InfoLeader — Alerte de sécurité : ' . $titre, $texte);
    journaliser('evenement', 'Alerte de sécurité « ' . $titre . ' » '
        . ($envoi['ok'] ? 'envoyée par e-mail.' : 'non envoyée (' . $envoi['erreur'] . ').'));
    return $envoi;
}

// Contenu de l'e-mail « mot de passe oublié »
function texteMailCode(string $code, int $expire): string
{
    $minutes = configSecurite()['validite_code_minutes'];
    return "Bonjour,\n\n"
        . "Une réinitialisation du mot de passe du panneau d'administration InfoLeader a été demandée le "
        . date('d/m/Y') . ' à ' . date('H:i') . ' depuis l\'adresse IP ' . ipClient() . ".\n\n"
        . "Votre code de réinitialisation : $code\n\n"
        . "Ce code est valable $minutes minutes (jusqu'à " . date('H:i:s', $expire) . ").\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.\n\n"
        . "— InfoLeader";
}
