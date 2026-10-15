<?php
/* ==========================================================================
   InfoLeader — admin/email.php
   Module « Configuration des e-mails » :
     - PHP mail : fonction mail() du serveur, utilisable uniquement chez un hébergeur web ;
     - SMTP     : envoi par un serveur SMTP (ici mail.olivier-dutoit.fr) via PHPMailer,
                  fonctionne aussi en local sous WAMP.
   Le mot de passe SMTP n'est jamais réaffiché dans la page.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
$admin = exigerConnexion();

$cfg = configMail();

// ----- Traitement du formulaire (enregistrer ou enregistrer + tester) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
        rediriger('email.php');
    }
    $mode = ($_POST['mode'] ?? '') === 'smtp' ? 'smtp' : 'phpmail';
    $saisie = $cfg;
    $saisie['expediteur_nom']   = trim(str_replace(["\r", "\n"], ' ', (string) ($_POST['expediteur_nom'] ?? '')));
    $saisie['expediteur_email'] = trim((string) ($_POST['expediteur_email'] ?? ''));
    $saisie['destinataire']     = trim((string) ($_POST['destinataire'] ?? ''));
    $saisie['logs_debogage']    = isset($_POST['logs_debogage']);

    $erreurs = [];
    if ($saisie['expediteur_nom'] === '' || mb_strlen($saisie['expediteur_nom']) > 100) {
        $erreurs[] = 'Le nom de l\'expéditeur est obligatoire (100 caractères au maximum).';
    }
    if (!filter_var($saisie['expediteur_email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'e-mail expéditeur (From) n\'est pas une adresse valide.';
    }
    if (!filter_var($saisie['destinataire'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Le destinataire des messages (To) n\'est pas une adresse valide.';
    }

    if ($mode === 'smtp') {
        $saisie['smtp_hote']        = trim((string) ($_POST['smtp_hote'] ?? ''));
        $saisie['smtp_port']        = (int) ($_POST['smtp_port'] ?? 0);
        $saisie['smtp_securite']    = ($_POST['smtp_securite'] ?? '') === 'ssl' ? 'ssl' : 'tls';
        $saisie['smtp_identifiant'] = trim((string) ($_POST['smtp_identifiant'] ?? ''));
        $motDePasse = (string) ($_POST['smtp_mot_de_passe'] ?? '');
        if ($motDePasse !== '') {
            $saisie['smtp_mot_de_passe'] = $motDePasse;  // champ vide = on garde le mot de passe déjà enregistré
        }

        if (!preg_match('/^[a-z0-9.-]+$/i', $saisie['smtp_hote'])) {
            $erreurs[] = 'Le serveur SMTP n\'est pas valide (ex. : mail.olivier-dutoit.fr).';
        }
        // Une combinaison incohérente échoue toujours : 465 = SSL dès la connexion, 587 = STARTTLS
        if (($saisie['smtp_port'] === 465 && $saisie['smtp_securite'] !== 'ssl')
            || ($saisie['smtp_port'] === 587 && $saisie['smtp_securite'] !== 'tls')) {
            $erreurs[] = 'Le port et la sécurité ne correspondent pas : choisissez 465 avec SSL, ou 587 avec TLS (STARTTLS).';
        }
        if ($saisie['smtp_identifiant'] === '') {
            $erreurs[] = 'L\'identifiant SMTP est obligatoire.';
        }
        if ($saisie['smtp_mot_de_passe'] === '') {
            $erreurs[] = 'Le mot de passe SMTP est obligatoire.';
        }
        // La plupart des serveurs refusent d'envoyer au nom d'une autre adresse que celle du compte
        if (filter_var($saisie['smtp_identifiant'], FILTER_VALIDATE_EMAIL)
            && strcasecmp($saisie['expediteur_email'], $saisie['smtp_identifiant']) !== 0) {
            $erreurs[] = 'L\'e-mail expéditeur doit être l\'adresse du compte SMTP (' . $saisie['smtp_identifiant'] . ') : '
                . 'le serveur refuse d\'envoyer au nom d\'une autre adresse. Le destinataire, lui, peut être n\'importe quelle adresse.';
        }
    }

    if ($erreurs) {
        // Pas de redirection : la page est réaffichée avec les valeurs saisies
        flash('erreur', 'La configuration n\'a pas été enregistrée :', $erreurs);
        $cfg = $saisie;
        $onglet = $mode;
    } else {
        $saisie['mode'] = $mode;
        if ($mode === 'smtp') {
            $saisie['smtp_configure'] = true;
        }
        $saisie['modifie_le'] = maintenant();
        ecrireJson(FICHIER_MAIL, $saisie);
        $libelleMode = $mode === 'smtp' ? 'SMTP (' . $saisie['smtp_hote'] . ')' : 'PHP mail';
        journaliser('evenement', "Configuration des e-mails enregistrée (mode : $libelleMode).");

        if (($_POST['action'] ?? '') === 'tester') {
            $envoi = envoyerMail(
                'InfoLeader — E-mail de test',
                "Bonjour,\n\nCet e-mail de test confirme que l'envoi depuis le panneau d'administration InfoLeader fonctionne"
                . " (mode : $libelleMode).\n\nEnvoyé le " . date('d/m/Y') . ' à ' . date('H:i:s') . ".\n\n— InfoLeader"
            );
            journaliser('evenement', 'Test d\'envoi d\'e-mail (' . $libelleMode . ') : ' . ($envoi['ok'] ? 'réussi' : 'échec') . '.');
            if ($envoi['ok']) {
                flash('succes', 'Configuration enregistrée et e-mail de test envoyé à ' . $saisie['destinataire']
                    . '. Vérifiez la boîte de réception (et le dossier « Spam »).');
            } elseif ($mode === 'phpmail') {
                // Échec attendu en local (WAMP n'a pas de serveur d'e-mails) : on bascule
                // directement sur l'onglet SMTP, le seul qui peut fonctionner ici
                flash('info', 'Le test PHP mail a échoué, comme prévu sous WAMP. Vous êtes maintenant sur l\'onglet « SMTP » : aucun test SMTP n\'a encore été fait.', [
                    'Saisissez le mot de passe SMTP en bas du formulaire, puis cliquez sur « Tester l\'envoi » de cet onglet.',
                    'Détail technique de l\'échec PHP mail : ' . $envoi['erreur'],
                ]);
                rediriger('email.php?onglet=smtp');
            } else {
                flash('erreur', 'Configuration enregistrée, mais l\'envoi de test a échoué : ' . $envoi['erreur'], [
                    'Vérifiez l\'identifiant et le mot de passe SMTP (« Could not authenticate » = identifiants refusés).',
                    'L\'e-mail expéditeur doit être l\'adresse du compte SMTP.',
                    'Le pare-feu ou l\'antivirus ne doit pas bloquer le port ' . $saisie['smtp_port'] . ' en sortie (« Could not connect »).',
                    'Le dialogue complet avec le serveur (identifiants masqués) est dans storage/logs/mail.log.',
                ]);
            }
        } else {
            flash('succes', "Configuration enregistrée : les e-mails seront envoyés avec $libelleMode.");
        }
        rediriger('email.php?onglet=' . $mode);
    }
}

// Onglet affiché : celui demandé (l'ancien nom « brevo » mène à l'onglet SMTP), sinon le mode actif
$demande = ($_GET['onglet'] ?? '') === 'brevo' ? 'smtp' : ($_GET['onglet'] ?? '');
$onglet = $onglet ?? (in_array($demande, ['phpmail', 'smtp'], true) ? $demande : $cfg['mode']);

afficherHautAdmin('Configuration des e-mails', 'email', $admin);
?>
        <h1>Configuration des e-mails</h1>
        <p class="intro">Ces réglages servent à tous les e-mails envoyés par le site, notamment le code de l'outil « Mot de passe oublié ».</p>

        <nav class="onglets" aria-label="Mode d'envoi">
            <a href="email.php?onglet=phpmail"<?= $onglet === 'phpmail' ? ' class="actif" aria-current="page"' : '' ?>>
                PHP mail<?= $cfg['mode'] === 'phpmail' ? ' <span class="badge badge-ok">actif</span>' : '' ?>
            </a>
            <a href="email.php?onglet=smtp"<?= $onglet === 'smtp' ? ' class="actif" aria-current="page"' : '' ?>>
                SMTP<?= $cfg['mode'] === 'smtp' ? ' <span class="badge badge-ok">actif</span>' : '' ?>
            </a>
        </nav>

        <form method="post" action="email.php" class="carte formulaire">
            <?= champCsrf() ?>
            <input type="hidden" name="mode" value="<?= e($onglet) ?>">

            <?php if ($onglet === 'phpmail'): ?>
                <div class="encart encart-info" data-masquer-apres="7000">
                    <p>PHP mail utilise la fonction mail() du serveur : elle ne fonctionne que chez un hébergeur web.
                        En local avec WAMP, utilisez l'onglet SMTP.</p>
                </div>
                <p class="passer-smtp">
                    En local, seul l'envoi SMTP permet d'envoyer de vrais e-mails.
                    <a href="email.php?onglet=smtp" class="btn btn-plein btn-petit">Configurer l'envoi SMTP &rarr;</a>
                </p>
            <?php else: ?>
                <div class="prerequis">
                    <p><strong>Pré-requis pour l'envoi SMTP :</strong></p>
                    <ul>
                        <li><?= badgeStatut(extension_loaded('openssl') ? 'ok' : 'erreur') ?> Extension OpenSSL activée dans php.ini</li>
                        <li><?= badgeStatut(phpMailerInstalle() ? 'ok' : 'erreur') ?> Bibliothèque PHPMailer installée (vendor/phpmailer)</li>
                        <li><?= badgeStatut('info') ?> Port <?= (int) $cfg['smtp_port'] ?> autorisé en sortie par le pare-feu : vérifié par « Tester l'envoi »</li>
                    </ul>
                </div>
            <?php endif; ?>

            <fieldset>
                <legend>Le message</legend>
                <div class="champ">
                    <label for="expediteur_nom">Nom de l'expéditeur</label>
                    <input type="text" id="expediteur_nom" name="expediteur_nom" required maxlength="100" value="<?= e($cfg['expediteur_nom']) ?>">
                    <p class="aide">Nom affiché dans la boîte de réception, par exemple « InfoLeader ».</p>
                </div>
                <div class="champ">
                    <label for="expediteur_email">E-mail expéditeur (champ « From »)</label>
                    <input type="email" id="expediteur_email" name="expediteur_email" required value="<?= e($cfg['expediteur_email']) ?>" placeholder="contact@votre-domaine.fr">
                    <p class="aide">Adresse qui envoie les messages.<?= $onglet === 'smtp' ? ' En SMTP, ce doit être l\'adresse du compte SMTP (même valeur que l\'identifiant).' : '' ?></p>
                </div>
                <div class="champ">
                    <label for="destinataire">Destinataire des messages (champ « To »)</label>
                    <input type="email" id="destinataire" name="destinataire" required value="<?= e($cfg['destinataire']) ?>" placeholder="vous@exemple.fr">
                    <p class="aide">Boîte qui reçoit les e-mails du site, dont le code « Mot de passe oublié ». N'importe quelle adresse convient.</p>
                </div>
            </fieldset>

            <?php if ($onglet === 'smtp'): ?>
                <fieldset>
                    <legend>Serveur d'envoi (SMTP)</legend>
                    <div class="champ">
                        <label for="smtp_hote">Serveur SMTP</label>
                        <input type="text" id="smtp_hote" name="smtp_hote" required value="<?= e($cfg['smtp_hote']) ?>">
                        <p class="aide">Adresse du serveur d'envoi de la messagerie, par exemple mail.olivier-dutoit.fr.</p>
                    </div>
                    <div class="champ-ligne">
                        <div class="champ">
                            <label for="smtp_port">Port</label>
                            <select id="smtp_port" name="smtp_port">
                                <option value="465"<?= (int) $cfg['smtp_port'] === 465 ? ' selected' : '' ?>>465</option>
                                <option value="587"<?= (int) $cfg['smtp_port'] === 587 ? ' selected' : '' ?>>587</option>
                            </select>
                            <p class="aide">Porte d'entrée du serveur SMTP (le 587 est bloqué sur ce réseau : utilisez 465).</p>
                        </div>
                        <div class="champ">
                            <label for="smtp_securite">Sécurité</label>
                            <select id="smtp_securite" name="smtp_securite">
                                <option value="ssl"<?= $cfg['smtp_securite'] === 'ssl' ? ' selected' : '' ?>>SSL / TLS (port 465)</option>
                                <option value="tls"<?= $cfg['smtp_securite'] === 'tls' ? ' selected' : '' ?>>STARTTLS (port 587)</option>
                            </select>
                            <p class="aide">Chiffrement de la connexion : SSL avec 465, STARTTLS avec 587.</p>
                        </div>
                    </div>
                    <div class="champ">
                        <label for="smtp_identifiant">Identifiant SMTP</label>
                        <input type="text" id="smtp_identifiant" name="smtp_identifiant" required autocomplete="off" value="<?= e($cfg['smtp_identifiant']) ?>">
                        <p class="aide">En général l'adresse e-mail complète du compte.</p>
                    </div>
                    <div class="champ">
                        <label for="smtp_mot_de_passe">Mot de passe SMTP</label>
                        <div class="champ-mdp">
                            <input type="password" id="smtp_mot_de_passe" name="smtp_mot_de_passe" autocomplete="new-password"
                                   <?= $cfg['smtp_mot_de_passe'] === '' ? 'required placeholder="Collez ici le mot de passe du compte"' : 'placeholder="Mot de passe enregistré — laisser vide pour le conserver"' ?>>
                            <button type="button" class="btn-afficher-mdp" data-afficher-mdp="smtp_mot_de_passe" aria-pressed="false">Afficher</button>
                        </div>
                        <p class="aide">Le mot de passe de la messagerie. PHP en a besoin pour se connecter au serveur : il est stocké dans config/mail.json
                            (dossier interdit d'accès depuis le navigateur), n'est jamais réaffiché ici et n'apparaît jamais dans les logs.</p>
                    </div>
                </fieldset>
            <?php endif; ?>

            <fieldset>
                <legend>Débogage</legend>
                <label class="case">
                    <input type="checkbox" name="logs_debogage" value="1"<?= $cfg['logs_debogage'] ? ' checked' : '' ?>>
                    Activer les logs de débogage d'envoi de mails
                </label>
                <p class="aide">Chaque e-mail envoyé par l'application (destinataire, sujet, contenu, résultat) est enregistré dans storage/logs/mail.log.
                    Le mot de passe SMTP n'y figure jamais. À désactiver une fois le site en production.</p>
            </fieldset>

            <div class="actions">
                <button type="submit" name="action" value="enregistrer" class="btn btn-plein">Enregistrer</button>
                <button type="submit" name="action" value="tester" class="btn btn-secondaire">
                    <?= $onglet === 'phpmail' ? 'Tester l\'envoi avec PHP mail' : 'Tester l\'envoi' ?>
                </button>
                <span class="aide">« Tester l'envoi » enregistre la configuration puis envoie un e-mail au destinataire.</span>
            </div>
            <?php if ($cfg['modifie_le']): ?>
                <p class="aide">Dernière modification : <?= dateFr($cfg['modifie_le']) ?>.</p>
            <?php endif; ?>
        </form>
<?php
afficherBasAdmin();
