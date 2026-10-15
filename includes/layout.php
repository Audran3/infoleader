<?php
/* ==========================================================================
   InfoLeader — includes/layout.php
   Gabarit HTML commun aux pages du panel : en-tête, menu, messages,
   champs de mot de passe, liste des règles, fenêtres modales.
   ========================================================================== */

function pagesPanel(): array
{
    return [
        'dashboard'   => ['dashboard.php', 'Tableau de bord'],
        'email'       => ['email.php', 'E-mails'],
        'password'    => ['password.php', 'Mot de passe'],
        'user'        => ['user.php', 'Utilisateur'],
        'maintenance' => ['maintenance.php', 'Maintenance'],
    ];
}

// $admin vaut null sur les pages accessibles sans connexion (connexion, mot de passe oublié)
function afficherHautAdmin(string $titre, string $pageActive = '', ?array $admin = null): void
{
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre) ?> — Administration InfoLeader</title>
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body class="<?= $admin ? 'page-panel' : 'page-auth' ?>">
<?php if ($admin): ?>
    <header class="panel-entete">
        <div class="panel-entete-inner">
            <a href="dashboard.php" class="panel-logo">Info<span>Leader</span> <small>Administration</small></a>
            <nav class="panel-nav" aria-label="Menu du panel">
                <ul>
                    <?php foreach (pagesPanel() as $cle => [$fichier, $libelle]): ?>
                        <li><a href="<?= $fichier ?>"<?= $cle === $pageActive ? ' class="actif" aria-current="page"' : '' ?>><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="panel-compte">
                <span class="panel-compte-email" title="Compte connecté"><?= e($admin['email'] ?? '') ?></span>
                <a href="../index.php" class="lien-site">Voir le site</a>
                <form method="post" action="logout.php">
                    <?= champCsrf() ?>
                    <button type="submit" class="btn btn-petit btn-contour-clair">Déconnexion</button>
                </form>
            </div>
        </div>
    </header>
<?php endif; ?>
    <main class="<?= $admin ? 'panel-contenu' : 'auth-contenu' ?>">
<?php
    afficherFlashs();
}

function afficherBasAdmin(): void
{
    ?>
    </main>
</body>
</html>
<?php
}

function afficherFlashs(): void
{
    foreach (lireFlashs() as $flash) {
        afficherEncart($flash['type'], $flash['texte'], $flash['details'] ?? [], true);
    }
}

// Encart coloré (succes, erreur, info, attention).
// $autoMasquer : l'encart disparaît au bout de quelques secondes (géré par admin.js).
function afficherEncart(string $type, string $texte, array $details = [], bool $autoMasquer = false): void
{
    $role = $type === 'erreur' ? 'alert' : 'status';
    $duree = $details ? 9000 : 6000;
    ?>
    <div class="encart encart-<?= e($type) ?>" role="<?= $role ?>"<?= $autoMasquer ? ' data-masquer-apres="' . $duree . '"' : '' ?>>
        <p><?= e($texte) ?></p>
        <?php if ($details): ?>
            <ul>
                <?php foreach ($details as $detail): ?><li><?= e($detail) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($autoMasquer): ?>
            <button type="button" class="encart-fermer" aria-label="Fermer ce message">&times;</button>
        <?php endif; ?>
    </div>
    <?php
}

// Champ mot de passe avec bouton « Afficher »
function champMotDePasse(string $id, string $libelle, string $autocomplete, bool $regles = false, string $aide = ''): void
{
    $limites = $regles ? ' minlength="' . MDP_LONGUEUR_MIN . '" maxlength="' . MDP_LONGUEUR_MAX . '"' : '';
    ?>
    <div class="champ">
        <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
        <div class="champ-mdp">
            <input type="password" id="<?= e($id) ?>" name="<?= e($id) ?>" required autocomplete="<?= e($autocomplete) ?>"<?= $limites ?>>
            <button type="button" class="btn-afficher-mdp" data-afficher-mdp="<?= e($id) ?>" aria-pressed="false">Afficher</button>
        </div>
        <?php if ($aide): ?><p class="aide"><?= e($aide) ?></p><?php endif; ?>
    </div>
    <?php
}

// Règles du mot de passe, cochées en direct par admin.js pendant la saisie
function afficherReglesMotDePasse(string $idChamp, string $idConfirmation): void
{
    ?>
    <div class="regles-bloc">
        <p class="regles-titre">Règles de sécurité du nouveau mot de passe :</p>
        <ul class="regles-mdp" data-regles-pour="<?= e($idChamp) ?>" data-confirmation="<?= e($idConfirmation) ?>">
            <?php foreach (reglesMotDePasse() as $cle => $libelle): ?>
                <li data-regle="<?= e($cle) ?>"><?= e($libelle) ?></li>
            <?php endforeach; ?>
            <li data-regle="identique">Les deux saisies sont identiques</li>
            <li>Différent du mot de passe actuel (vérifié à l'envoi)</li>
        </ul>
    </div>
    <?php
}

// Fenêtre modale de confirmation (<dialog>), ouverte par un bouton portant data-modale="id".
// Options : champs (champs cachés), style (classe du bouton), note, fermer_apres_envoi.
function afficherModale(string $id, string $titre, string $texte, string $action, string $libelleConfirmer, array $options = []): void
{
    $style = $options['style'] ?? 'btn-danger';
    ?>
    <dialog class="modale" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-titre">
        <form method="post" class="modale-contenu"<?= !empty($options['fermer_apres_envoi']) ? ' data-fermer-apres-envoi' : '' ?>>
            <?= champCsrf() ?>
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <?php foreach ($options['champs'] ?? [] as $nom => $valeur): ?>
                <input type="hidden" name="<?= e($nom) ?>" value="<?= e($valeur) ?>">
            <?php endforeach; ?>
            <h2 id="<?= e($id) ?>-titre"><?= e($titre) ?></h2>
            <p><?= e($texte) ?></p>
            <?php if (!empty($options['note'])): ?>
                <p class="modale-note"><?= e($options['note']) ?></p>
            <?php endif; ?>
            <div class="modale-actions">
                <button type="button" class="btn btn-secondaire" data-fermer-modale>Annuler</button>
                <button type="submit" class="btn <?= e($style) ?>"><?= e($libelleConfirmer) ?></button>
            </div>
        </form>
    </dialog>
    <?php
}

function badgeStatut(string $statut): string
{
    $libelles = ['ok' => 'OK', 'attention' => 'Attention', 'erreur' => 'Erreur', 'info' => 'À vérifier'];
    return '<span class="badge badge-' . e($statut) . '">' . e($libelles[$statut] ?? $statut) . '</span>';
}
