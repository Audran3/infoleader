<?php
/* ==========================================================================
   InfoLeader — admin/login.php
   Connexion au panel d'administration, protégée par un anti-brute force :
   attente de 3 s après une erreur, puis attente progressive,
   puis blocage de l'adresse IP pendant plusieurs heures.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
demarrerSession();

if (!empty($_SESSION['admin'])) {
    rediriger('dashboard.php');
}

$ip = ipClient();
$cfg = configSecurite();

// ----- Traitement du formulaire -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restriction = restrictionEnCours($ip);
    $email = trim((string) ($_POST['email'] ?? ''));
    $_SESSION['email_saisi'] = mb_substr($email, 0, 254);

    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
    } elseif ($restriction && $restriction['bloque']) {
        journaliser('blocage', 'Tentative de connexion refusée : adresse IP bloquée.');
    } elseif ($restriction) {
        // Nouvel essai avant la fin de l'attente : typique d'un robot, compté comme un échec
        $nouvelle = enregistrerEchec($ip);
        journaliser($nouvelle['bloque'] ? 'blocage' : 'echec', 'Tentative de connexion avant la fin du délai d\'attente.');
        flash('erreur', 'Veuillez attendre la fin du délai avant une nouvelle tentative.');
    } else {
        $admin = lireAdmin();
        // password_verify est toujours exécuté : la durée de réponse ne révèle pas si l'e-mail est le bon
        $motDePasseCorrect = password_verify((string) ($_POST['mot_de_passe'] ?? ''), $admin['password_hash'] ?? '');
        $emailCorrect = isset($admin['email']) && strcasecmp($email, $admin['email']) === 0;

        if ($motDePasseCorrect && $emailCorrect) {
            reinitialiserLimitation($ip);
            connecterAdmin($admin);
            $admin['derniere_connexion'] = ['date' => maintenant(), 'ip' => $ip];
            enregistrerAdmin($admin);
            unset($_SESSION['email_saisi']);
            journaliser('succes', 'Connexion réussie (' . $admin['email'] . ').');
            rediriger('dashboard.php');
        }

        $nouvelle = enregistrerEchec($ip);
        // L'identifiant saisi est masqué (SEC-6) : il peut contenir un mot de passe tapé par erreur
        journaliser('echec', 'Échec de connexion (identifiant saisi : ' . masquerIdentifiant($email) . ').');
        if ($nouvelle['bloque']) {
            journaliser('blocage', 'Adresse IP bloquée pendant ' . $cfg['duree_blocage_heures'] . ' h après '
                . $cfg['echecs_avant_blocage'] . ' échecs de connexion.');
        } else {
            flash('erreur', 'Identifiant ou mot de passe incorrect.');
        }
    }
    // Redirection après POST : actualiser la page ne renvoie pas le formulaire
    rediriger('login.php');
}

// ----- Affichage -----
$restriction = restrictionEnCours($ip);
$demande = demandeReinitialisation();

afficherHautAdmin('Connexion');
?>
        <div class="auth-carte">
            <p class="auth-logo">Info<span>Leader</span></p>
            <h1 class="auth-titre">Panneau d'administration</h1>

            <?php if ($restriction && $restriction['bloque']): ?>
                <div class="encart encart-erreur" role="alert">
                    <p><strong>Accès temporairement bloqué.</strong></p>
                    <p>Trop de tentatives de connexion ont échoué depuis votre adresse IP. Réessayez après le
                        <?= date('d/m/Y', $restriction['jusqua']) ?> à <?= date('H:i', $restriction['jusqua']) ?>.</p>
                </div>
            <?php else: ?>
                <form method="post" action="login.php" class="formulaire">
                    <?= champCsrf() ?>
                    <div class="champ">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" required autocomplete="username"
                               value="<?= e($_SESSION['email_saisi'] ?? '') ?>" autofocus>
                    </div>
                    <?php champMotDePasse('mot_de_passe', 'Mot de passe', 'current-password'); ?>

                    <button type="submit" class="btn btn-plein"<?= $restriction ? ' data-attente="' . $restriction['secondes'] . '"' : '' ?>>Se connecter</button>
                    <?php if ($restriction): ?>
                        <p class="attente" data-attente-message>
                            Nouvelle tentative possible dans <strong data-compte-a-rebours="<?= $restriction['secondes'] ?>"><?= $restriction['secondes'] ?></strong> s.
                        </p>
                    <?php endif; ?>
                </form>

                <p class="auth-lien">
                    <a href="forgot-password.php" data-modale="modale-oubli">Mot de passe oublié ?</a>
                </p>
            <?php endif; ?>

            <p class="auth-retour"><a href="../index.php">&larr; Retour au site</a></p>
        </div>

        <!-- Fenêtre « Mot de passe oublié » (sans JavaScript, le lien ouvre la page forgot-password.php) -->
        <dialog class="modale" id="modale-oubli" aria-labelledby="modale-oubli-titre">
            <form method="post" action="forgot-password.php" class="modale-contenu">
                <?= champCsrf() ?>
                <input type="hidden" name="action" value="envoyer">
                <h2 id="modale-oubli-titre">Mot de passe oublié</h2>
                <?php if ($demande): ?>
                    <div class="encart encart-info" data-masquer-apres="6000">
                        <p>Une demande de réinitialisation est déjà en cours : le code reçu est valable jusqu'à <?= date('H:i', $demande['expire']) ?>.</p>
                    </div>
                    <p><a href="forgot-password.php?etape=code">J'ai reçu le code : le saisir</a></p>
                <?php endif; ?>
                <p>Un code à 8 chiffres, valable <?= $cfg['validite_code_minutes'] ?> minutes, va être envoyé à l'adresse de réception
                    définie dans le module « Configuration des e-mails ». Il vous permettra de créer un nouveau mot de passe.</p>
                <div class="modale-actions">
                    <button type="button" class="btn btn-secondaire" data-fermer-modale>Annuler</button>
                    <button type="submit" class="btn btn-plein">Envoyer un code de réinitialisation</button>
                </div>
            </form>
        </dialog>
<?php
afficherBasAdmin();
