<?php
/* ==========================================================================
   InfoLeader — admin/user.php
   Module « Utilisateur » : modification de l'adresse e-mail de connexion.
   Contrôles : mot de passe actuel, syntaxe valide, adresse différente
   de l'actuelle, double saisie identique.
   ========================================================================== */
require __DIR__ . '/../includes/bootstrap.php';
$admin = exigerConnexion();

$nouvelEmail = '';
$confirmationEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValide()) {
        flash('erreur', 'Le formulaire a expiré. Veuillez réessayer.');
        rediriger('user.php');
    }
    $nouvelEmail = trim((string) ($_POST['nouvel_email'] ?? ''));
    $confirmationEmail = trim((string) ($_POST['confirmation_email'] ?? ''));

    $erreurs = [];
    if (!verifierMotDePasseActuel($admin, (string) ($_POST['actuel'] ?? ''), 'Utilisateur')) {
        $erreurs[] = 'Le mot de passe actuel est incorrect.';
    } else {
        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL) || strlen($nouvelEmail) > 254) {
            $erreurs[] = 'La nouvelle adresse e-mail n\'est pas valide (exemple attendu : prenom.nom@domaine.fr).';
        } elseif (strcasecmp($nouvelEmail, $admin['email']) === 0) {
            $erreurs[] = 'La nouvelle adresse doit être différente de l\'adresse actuelle.';
        }
        if ($nouvelEmail !== $confirmationEmail) {
            $erreurs[] = 'Les deux adresses saisies ne sont pas identiques.';
        }
    }

    if ($erreurs) {
        // Pas de redirection : les adresses saisies restent dans le formulaire
        flash('erreur', 'L\'adresse e-mail n\'a pas été modifiée :', $erreurs);
    } else {
        $ancienEmail = $admin['email'];
        $admin['email'] = $nouvelEmail;
        $admin['email_modifie_le'] = maintenant();
        enregistrerAdmin($admin);
        actualiserEmpreinte($admin);
        journaliser('evenement', "Adresse e-mail de connexion modifiée ($ancienEmail → $nouvelEmail).");
        notifierChangementSecurite('adresse de connexion modifiée',
            "L'adresse e-mail de connexion au panneau d'administration InfoLeader vient d'être modifiée : $ancienEmail → $nouvelEmail.");
        flash('succes', "Votre adresse de connexion est désormais $nouvelEmail. Utilisez-la lors de votre prochaine connexion.");
        rediriger('user.php');
    }
}

afficherHautAdmin('Utilisateur', 'user', $admin);
?>
        <h1>Utilisateur</h1>
        <p class="intro">Modifiez l'adresse e-mail utilisée pour vous connecter au panneau d'administration.</p>

        <form method="post" action="user.php" class="carte formulaire formulaire-etroit">
            <?= champCsrf() ?>
            <div class="champ">
                <label for="email_actuel">Adresse e-mail actuelle</label>
                <input type="email" id="email_actuel" value="<?= e($admin['email']) ?>" readonly>
            </div>
            <?php champMotDePasse('actuel', 'Mot de passe actuel', 'current-password', false, 'Obligatoire pour confirmer qu\'il s\'agit bien de vous.'); ?>
            <div class="champ">
                <label for="nouvel_email">Nouvelle adresse e-mail</label>
                <input type="email" id="nouvel_email" name="nouvel_email" required maxlength="254" autocomplete="email"
                       value="<?= e($nouvelEmail) ?>" data-different-de="<?= e($admin['email']) ?>">
            </div>
            <div class="champ">
                <label for="confirmation_email">Confirmer la nouvelle adresse e-mail</label>
                <input type="email" id="confirmation_email" name="confirmation_email" required maxlength="254" autocomplete="off"
                       value="<?= e($confirmationEmail) ?>" data-identique-a="nouvel_email">
            </div>
            <button type="submit" class="btn btn-plein">Modifier l'adresse e-mail</button>
        </form>
<?php
afficherBasAdmin();
