<?php
/* ==========================================================================
   InfoLeader — admin/dashboard.php
   Tableau de bord : informations du compte connecté + accès aux 4 outils.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
$admin = exigerConnexion();

$session = $_SESSION['admin'];
$precedente = $session['connexion_precedente'] ?? null;
$mail = configMail();
$diagnostic = diagnosticSysteme();
$dureeSession = configSecurite()['duree_session_minutes'];

$etatsSysteme = [
    'ok'        => 'Tout fonctionne correctement',
    'attention' => $diagnostic['problemes'] . ' point(s) d\'attention',
    'erreur'    => $diagnostic['problemes'] . ' problème(s) à corriger',
];

afficherHautAdmin('Tableau de bord', 'dashboard', $admin);
?>
        <h1>Tableau de bord</h1>

        <?php if ($mail['logs_debogage']): ?>
            <?php // Avertissement permanent (SEC-5) : mail.log contient le contenu des e-mails, dont les codes de réinitialisation
            afficherEncart('attention', 'Les logs de débogage des e-mails sont activés : storage/logs/mail.log enregistre le contenu de chaque e-mail envoyé, '
                . 'y compris les codes « Mot de passe oublié ». Désactivez-les dans « Configuration des e-mails » une fois vos tests terminés, '
                . 'puis videz le fichier dans Maintenance > Journaux > Logs e-mails.'); ?>
        <?php endif; ?>

        <section class="carte" aria-labelledby="titre-compte">
            <h2 id="titre-compte">Compte administrateur connecté</h2>
            <dl class="infos-compte">
                <div><dt>Adresse e-mail</dt><dd><?= e($admin['email']) ?></dd></div>
                <div><dt>Rôle</dt><dd>Administrateur</dd></div>
                <div><dt>Connecté depuis</dt><dd><?= dateFr($session['connecte_le']) ?> (IP <?= e($session['ip']) ?>)</dd></div>
                <div>
                    <dt>Connexion précédente</dt>
                    <dd><?= $precedente ? dateFr($precedente['date']) . ' (IP ' . e($precedente['ip']) . ')' : 'Première connexion' ?></dd>
                </div>
                <div><dt>Mot de passe modifié le</dt><dd><?= dateFr($admin['mot_de_passe_modifie_le'] ?? null) ?></dd></div>
                <div><dt>Compte créé le</dt><dd><?= dateFr($admin['cree_le'] ?? null) ?></dd></div>
                <div><dt>Session</dt><dd>Déconnexion automatique après <?= $dureeSession ?> minutes d'inactivité</dd></div>
            </dl>
        </section>

        <h2 class="titre-section">Outils</h2>
        <div class="outils">
            <a class="outil" href="email.php">
                <h3>Configuration des e-mails</h3>
                <p>Expéditeur, destinataire, serveur SMTP et test d'envoi.</p>
                <p class="outil-etat">
                    <?php if ($mail['mode'] === 'smtp' && $mail['smtp_mot_de_passe'] !== ''): ?>
                        <?= badgeStatut('ok') ?> Envoi SMTP via <?= e($mail['smtp_hote']) ?>
                    <?php elseif ($mail['mode'] === 'smtp'): ?>
                        <?= badgeStatut('attention') ?> SMTP à compléter (mot de passe)
                    <?php else: ?>
                        <?= badgeStatut('attention') ?> PHP mail (SMTP non configuré)
                    <?php endif; ?>
                </p>
            </a>
            <a class="outil" href="password.php">
                <h3>Mot de passe</h3>
                <p>Modifier le mot de passe du panel (20 caractères minimum).</p>
                <p class="outil-etat">Dernière modification : <?= dateFr($admin['mot_de_passe_modifie_le'] ?? null) ?></p>
            </a>
            <a class="outil" href="user.php">
                <h3>Utilisateur</h3>
                <p>Modifier l'adresse e-mail utilisée pour se connecter.</p>
                <p class="outil-etat">Adresse actuelle : <?= e($admin['email']) ?></p>
            </a>
            <a class="outil" href="maintenance.php">
                <h3>Maintenance</h3>
                <p>État du système, journaux, sauvegarde et fichiers temporaires.</p>
                <p class="outil-etat"><?= badgeStatut($diagnostic['global']) ?> <?= e($etatsSysteme[$diagnostic['global']]) ?></p>
            </a>
        </div>
<?php
afficherBasAdmin();
