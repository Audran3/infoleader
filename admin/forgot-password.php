<?php
/* ==========================================================================
   InfoLeader — admin/forgot-password.php
   Mot de passe oublié :
     étape 1 : envoi d'un code à 8 chiffres valable 5 minutes ;
     étape 2 (?etape=code) : saisie du code et du nouveau mot de passe.
   Le code n'est jamais stocké en clair : seule son empreinte (hash) est conservée.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
demarrerSession();

if (!empty($_SESSION['admin'])) {
    rediriger('dashboard.php');
}

$cfg = configSecurite();
$ip = ipClient();
$restriction = restrictionEnCours($ip);
$ipBloquee = $restriction && $restriction['bloque'];
$demande = demandeReinitialisation();

// ----- Traitement des formulaires -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
        rediriger('forgot-password.php');
    }
    if ($ipBloquee) {
        journaliser('blocage', 'Réinitialisation du mot de passe refusée : adresse IP bloquée.');
        rediriger('forgot-password.php');
    }

    // Étape 1 : génération et envoi du code
    if ($action === 'envoyer') {
        if ($demande && time() - $demande['cree'] < $cfg['delai_renvoi_code_secondes']) {
            flash('info', 'Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un nouveau.');
            rediriger('forgot-password.php?etape=code');
        }

        // SEC-4 : 3 demandes au plus par heure et par IP. Au-delà, la demande est refusée
        // SANS remplacer le code en cours : un inconnu ne peut plus annuler le code de l'administrateur.
        $fenetre = (int) $cfg['demandes_code_fenetre_minutes'];
        $recentes = demandesCodeRecentes($ip, $fenetre);
        if (count($recentes) >= $cfg['demandes_code_max']) {
            $attente = (int) ceil((min($recentes) + $fenetre * 60 - time()) / 60);
            journaliser('blocage', 'Demande de code de réinitialisation refusée : ' . count($recentes)
                . " demandes en $fenetre minutes depuis cette adresse IP.");
            flash('erreur', 'Trop de demandes de code depuis votre connexion. Réessayez dans ' . max(1, $attente) . ' minute(s).'
                . ($demande ? ' Le code déjà envoyé reste valable.' : ''));
            rediriger($demande ? 'forgot-password.php?etape=code' : 'forgot-password.php');
        }
        enregistrerDemandeCode($ip, $fenetre);

        $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $expire = time() + $cfg['validite_code_minutes'] * 60;
        ecrireJson(FICHIER_REINITIALISATION, [
            'hash'   => password_hash($code, PASSWORD_DEFAULT),
            'cree'   => time(),
            'expire' => $expire,
            'essais' => 0,
            'ip'     => $ip,
        ]);

        $envoi = envoyerMail('InfoLeader — Code de réinitialisation du mot de passe', texteMailCode($code, $expire));
        journaliser('evenement', 'Demande de réinitialisation du mot de passe (e-mail ' . ($envoi['ok'] ? 'envoyé' : 'non envoyé') . ').');

        if ($envoi['ok']) {
            flash('info', 'Un code à 8 chiffres vient de vous être envoyé par e-mail. Il est valable ' . $cfg['validite_code_minutes'] . ' minutes.');
        } else {
            flash('attention', 'Le code a été généré, mais l\'e-mail n\'a pas pu être envoyé : ' . $envoi['erreur'], [
                'Configurez l\'envoi SMTP dans le module « Configuration des e-mails ».',
                'En local, si les logs de débogage sont activés, le code figure dans storage/logs/mail.log.',
            ]);
        }
        rediriger('forgot-password.php?etape=code');
    }

    // Étape 2 : vérification du code et changement du mot de passe
    if ($action === 'changer') {
        if (!$demande) {
            flash('erreur', 'Aucune demande en cours ou code expiré. Demandez un nouveau code.');
            rediriger('forgot-password.php');
        }

        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!password_verify($code, $demande['hash'])) {
            $demande['essais']++;
            journaliser('echec', 'Code de réinitialisation incorrect (essai ' . $demande['essais'] . '/' . $cfg['essais_code_max'] . ').');

            if ($demande['essais'] >= $cfg['essais_code_max']) {
                ecrireJson(FICHIER_REINITIALISATION, []);
                enregistrerEchec($ip);
                flash('erreur', 'Trop de codes incorrects : la demande est annulée. Demandez un nouveau code.');
                rediriger('forgot-password.php');
            }
            ecrireJson(FICHIER_REINITIALISATION, $demande);
            flash('erreur', 'Code incorrect. Il vous reste ' . ($cfg['essais_code_max'] - $demande['essais']) . ' essai(s).');
            rediriger('forgot-password.php?etape=code');
        }

        $admin = lireAdmin();
        $nouveau = (string) ($_POST['nouveau'] ?? '');
        $erreurs = erreursMotDePasse($nouveau, (string) ($_POST['confirmation'] ?? ''));
        if (!$erreurs && password_verify($nouveau, $admin['password_hash'] ?? '')) {
            $erreurs[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        }
        if ($erreurs) {
            flash('erreur', 'Le code est correct, mais le nouveau mot de passe ne respecte pas les règles :', $erreurs);
            rediriger('forgot-password.php?etape=code');
        }

        $admin['password_hash'] = hacherMotDePasse($nouveau);
        $admin['mot_de_passe_modifie_le'] = maintenant();
        enregistrerAdmin($admin);
        ecrireJson(FICHIER_REINITIALISATION, []);
        reinitialiserLimitation($ip);
        journaliser('evenement', 'Mot de passe réinitialisé avec un code reçu par e-mail.');
        notifierChangementSecurite('mot de passe réinitialisé',
            'Le mot de passe du panneau d\'administration InfoLeader vient d\'être réinitialisé avec un code reçu par e-mail (« Mot de passe oublié »).');
        flash('succes', 'Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.');
        rediriger('login.php');
    }

    rediriger('forgot-password.php');
}

// ----- Affichage -----
$etape = ($_GET['etape'] ?? '') === 'code' ? 'code' : 'demande';
if ($etape === 'code' && !$demande) {
    flash('info', 'Aucune demande en cours (ou le code a expiré). Demandez un nouveau code.');
    rediriger('forgot-password.php');
}

afficherHautAdmin('Mot de passe oublié');
?>
        <div class="auth-carte auth-carte-large">
            <p class="auth-logo">Info<span>Leader</span></p>

            <?php if ($ipBloquee): ?>
                <h1 class="auth-titre">Mot de passe oublié</h1>
                <div class="encart encart-erreur" role="alert">
                    <p>Trop de tentatives ont échoué depuis votre adresse IP. Réessayez après le
                        <?= date('d/m/Y', $restriction['jusqua']) ?> à <?= date('H:i', $restriction['jusqua']) ?>.</p>
                </div>

            <?php elseif ($etape === 'demande'): ?>
                <h1 class="auth-titre">Mot de passe oublié</h1>
                <?php if ($demande): ?>
                    <div class="encart encart-info" data-masquer-apres="6000">
                        <p>Une demande de réinitialisation est déjà en cours : le code envoyé est valable jusqu'à <?= date('H:i', $demande['expire']) ?>.</p>
                    </div>
                    <p><a href="forgot-password.php?etape=code" class="btn btn-secondaire btn-bloc">J'ai reçu le code : le saisir</a></p>
                <?php endif; ?>
                <p>Un code à 8 chiffres, valable <?= $cfg['validite_code_minutes'] ?> minutes, va être envoyé à l'adresse de réception
                    définie dans le module « Configuration des e-mails ». Il vous permettra de créer un nouveau mot de passe.</p>
                <form method="post" action="forgot-password.php">
                    <?= champCsrf() ?>
                    <input type="hidden" name="action" value="envoyer">
                    <button type="submit" class="btn btn-plein">Envoyer un code de réinitialisation</button>
                </form>

            <?php else: ?>
                <h1 class="auth-titre">Nouveau mot de passe</h1>
                <form method="post" action="forgot-password.php" class="formulaire" id="form-reinitialisation">
                    <?= champCsrf() ?>
                    <input type="hidden" name="action" value="changer">

                    <p class="minuteur">
                        Code valable encore
                        <strong data-compte-a-rebours="<?= $demande['expire'] - time() ?>" data-format="mmss"
                                data-a-expiration="#form-reinitialisation button[type=submit]"
                                data-texte-fin="0:00 — code expiré, demandez-en un nouveau"></strong>
                    </p>

                    <div class="champ">
                        <label for="code">Code à 8 chiffres reçu par e-mail</label>
                        <input type="text" id="code" name="code" class="champ-code" required inputmode="numeric"
                               pattern="[0-9]{8}" maxlength="8" autocomplete="one-time-code" placeholder="12345678" autofocus>
                    </div>
                    <?php champMotDePasse('nouveau', 'Nouveau mot de passe', 'new-password', true); ?>
                    <?php champMotDePasse('confirmation', 'Confirmer le nouveau mot de passe', 'new-password', true); ?>
                    <?php afficherReglesMotDePasse('nouveau', 'confirmation'); ?>

                    <button type="submit" class="btn btn-plein">Changer le mot de passe</button>
                </form>

                <form method="post" action="forgot-password.php" class="auth-lien">
                    <?= champCsrf() ?>
                    <input type="hidden" name="action" value="envoyer">
                    <button type="submit" class="btn-lien">Je n'ai rien reçu : envoyer un nouveau code</button>
                </form>
            <?php endif; ?>

            <p class="auth-retour"><a href="login.php">&larr; Retour à la connexion</a></p>
        </div>
<?php
afficherBasAdmin();
