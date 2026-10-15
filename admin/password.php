<?php
/* ==========================================================================
   InfoLeader — admin/password.php
   Module « Mot de passe » : le mot de passe actuel est exigé, le nouveau doit
   faire au moins 20 caractères et respecter les règles affichées.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
$admin = exigerConnexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
        rediriger('password.php');
    }
    $nouveau = (string) ($_POST['nouveau'] ?? '');

    if (!verifierMotDePasseActuel($admin, (string) ($_POST['actuel'] ?? ''), 'Mot de passe')) {
        flash('erreur', 'Le mot de passe actuel est incorrect. Aucune modification n\'a été faite.');
        rediriger('password.php');
    }

    // Contrôle côté serveur : indispensable même si le navigateur a déjà vérifié
    $erreurs = erreursMotDePasse($nouveau, (string) ($_POST['confirmation'] ?? ''));
    if (!$erreurs && password_verify($nouveau, $admin['password_hash'])) {
        $erreurs[] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
    }
    if ($erreurs) {
        flash('erreur', 'Le mot de passe n\'a pas été modifié :', $erreurs);
        rediriger('password.php');
    }

    $admin['password_hash'] = hacherMotDePasse($nouveau);
    $admin['mot_de_passe_modifie_le'] = maintenant();
    enregistrerAdmin($admin);
    actualiserEmpreinte($admin);  // la session en cours reste ouverte, les autres sont fermées
    journaliser('evenement', 'Mot de passe administrateur modifié depuis le panel.');
    notifierChangementSecurite('mot de passe modifié',
        'Le mot de passe du panneau d\'administration InfoLeader vient d\'être modifié depuis le module « Mot de passe ».');
    flash('succes', 'Votre mot de passe a été modifié.');
    rediriger('password.php');
}

afficherHautAdmin('Mot de passe', 'password', $admin);
?>
        <h1>Mot de passe</h1>
        <p class="intro">Modifiez le mot de passe qui protège l'accès au panneau d'administration.</p>

        <form method="post" action="password.php" class="carte formulaire formulaire-etroit">
            <?= champCsrf() ?>
            <?php champMotDePasse('actuel', 'Mot de passe actuel', 'current-password'); ?>
            <?php champMotDePasse('nouveau', 'Nouveau mot de passe', 'new-password', true); ?>
            <?php champMotDePasse('confirmation', 'Confirmer le mot de passe', 'new-password', true); ?>
            <?php afficherReglesMotDePasse('nouveau', 'confirmation'); ?>
            <p class="aide">Astuce : une phrase de passe comme « Mon-clavier-a-104-touches! » est longue et facile à retenir.</p>
            <button type="submit" class="btn btn-plein">Modifier le mot de passe</button>
        </form>
<?php
afficherBasAdmin();
