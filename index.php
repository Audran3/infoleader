<?php
// ===== Page d'accueil =====
require __DIR__ . '/includes/bootstrap.php';

// En-tête et menu communs (QUAL-1)
$titrePage = 'Accueil';
$descriptionPage = 'InfoLeader, votre spécialiste des périphériques informatiques : claviers, souris, écrans, casques et webcams.';
$pageActive = 'accueil';
require __DIR__ . '/includes/entete-public.php';
?>

    <main>
        <!-- ===== Bannière d'accueil : titre, slogan et appel à l'action ===== -->
        <section class="banner" id="banniere">
            <div class="container banner-content fade-in">
                <h1>Bienvenue chez InfoLeader</h1>
                <h2 class="slogan">Des périphériques fiables pour travailler, créer et jouer sans limites.</h2>
                <p class="banner-text">Claviers, souris, écrans, casques, webcams : le meilleur du matériel informatique, sélectionné et testé par nos experts.</p>
                <a href="products.php" class="cta">Découvrir nos produits</a>
                <a href="#presentation" class="banner-link">En savoir plus &darr;</a>
            </div>
        </section>

        <!-- ===== Présentation de l'entreprise ===== -->
        <section class="section" id="presentation">
            <div class="container fade-in">
                <h2 class="section-title">Qui sommes-nous ?</h2>
                <p class="intro">Fondée en 2012 à Lyon, InfoLeader est une entreprise spécialisée dans la vente de périphériques informatiques pour les particuliers, les joueurs et les professionnels. Notre équipe sélectionne des produits de grandes marques (Logitech, Razer, LG, SteelSeries…) pour leur fiabilité, leur confort et leur rapport qualité-prix.</p>
                <p class="intro">Que vous équipiez un poste de télétravail, les bureaux de votre entreprise ou votre configuration de jeu, nous vous accompagnons de A à Z : conseil personnalisé, livraison rapide et service après-vente réactif.</p>
            </div>
        </section>

        <!-- ===== Points forts de l'entreprise ===== -->
        <section class="section section-alt">
            <div class="container fade-in">
                <h2 class="section-title">Pourquoi choisir InfoLeader ?</h2>
                <div class="features">
                    <article class="feature">
                        <h3>Livraison en 48 h</h3>
                        <p>Expédition rapide partout en France métropolitaine, offerte dès 49 € d'achat.</p>
                    </article>
                    <article class="feature">
                        <h3>Garantie 2 ans</h3>
                        <p>Tous nos produits sont garantis 2 ans, pièces et main-d'œuvre.</p>
                    </article>
                    <article class="feature">
                        <h3>Conseil d'experts</h3>
                        <p>Une équipe de passionnés vous aide à choisir le matériel adapté à votre usage.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/includes/pied-public.php'; ?>
