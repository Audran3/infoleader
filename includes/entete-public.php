<?php
/* ==========================================================================
   InfoLeader — includes/entete-public.php  (tâche QUAL-1)
   Début de page commun aux 3 pages publiques : <head>, en-tête et menu.
   Le menu n'existe plus qu'ici : une modification s'applique aux 3 pages.

   Variables à définir AVANT l'inclusion :
     $titrePage       : titre de l'onglet du navigateur (ex. « Nos Produits »)
     $descriptionPage : description de la page pour les moteurs de recherche
     $pageActive      : 'accueil', 'produits' ou 'contact' (lien surligné dans le menu)
   ========================================================================== */
$liensMenu = [
    'accueil'  => ['index.php', 'Accueil'],
    'produits' => ['products.php', 'Nos Produits'],
    'contact'  => ['contact.php', 'Contact'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($descriptionPage) ?>">
    <title><?= e($titrePage) ?> — InfoLeader</title>
    <link rel="stylesheet" href="style.css">
    <!-- script.js est chargé dans le <head> pour activer les animations sans clignotement -->
    <script src="script.js"></script>
</head>
<body>

    <!-- ===== En-tête fixe + menu de navigation (commun aux 3 pages) ===== -->
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">Info<span>Leader</span></a>

            <!-- Bouton « hamburger » : visible uniquement sur mobile -->
            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="menu" aria-label="Ouvrir le menu">
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>

            <nav class="main-nav" aria-label="Navigation principale">
                <ul id="menu">
                    <?php foreach ($liensMenu as $cle => [$fichier, $libelle]): ?>
                        <li><a href="<?= $fichier ?>"<?= $cle === $pageActive ? ' class="active" aria-current="page"' : '' ?>><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                    <!-- Accès au panneau d'administration (page de connexion si non connecté) -->
                    <li><a href="admin/" class="btn-admin">Administration</a></li>
                </ul>
            </nav>
        </div>
    </header>
