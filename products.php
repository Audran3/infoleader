<?php
// ===== Page « Nos Produits » =====
require __DIR__ . '/includes/bootstrap.php';  // fournit e() (QUAL-1 : plus de fonction en double ici)

// ===== Lecture du catalogue =====
// Les produits sont stockés dans le fichier à plat products.json (pas de base de données).
$produits = [];
$fichierCatalogue = __DIR__ . '/products.json';

if (is_readable($fichierCatalogue)) {
    $produits = json_decode(file_get_contents($fichierCatalogue), true);
}
$catalogueDisponible = is_array($produits) && count($produits) > 0;

// Formate un prix à la française : 129.99 => « 129,99 € »
function formaterPrix($prix)
{
    return number_format((float) $prix, 2, ',', ' ') . "\u{00A0}€";
}

// En-tête et menu communs (QUAL-1)
$titrePage = 'Nos Produits';
$descriptionPage = 'Découvrez les périphériques informatiques InfoLeader : claviers, souris, écrans 4K, casques, webcams et hubs USB-C.';
$pageActive = 'produits';
require __DIR__ . '/includes/entete-public.php';
?>

    <main>
        <!-- ===== Titre de la page ===== -->
        <section class="page-header">
            <div class="container fade-in">
                <h1>Nos Produits</h1>
                <p>Une sélection de périphériques de grandes marques, choisis pour leur qualité et leur fiabilité.</p>
            </div>
        </section>

        <section class="page-content">
            <div class="container">

                <?php if ($catalogueDisponible): ?>
                    <!-- ===== Filtre de recherche (géré en JavaScript, sans rechargement) ===== -->
                    <div class="search-bar fade-in">
                        <label for="search">Rechercher un produit</label>
                        <input type="search" id="search" placeholder="Ex. : clavier, 4K, sans fil…" autocomplete="off">
                        <p class="search-count" id="products-count" aria-live="polite"><?= count($produits) ?> produits</p>
                    </div>

                    <!-- ===== Grille des produits (générée par PHP à partir de products.json) ===== -->
                    <div class="products-grid fade-in">
                        <?php foreach ($produits as $produit): ?>
                            <article class="product-card" data-search="<?= e($produit['nom'] . ' ' . $produit['categorie'] . ' ' . $produit['description']) ?>">
                                <img src="<?= e($produit['image']) ?>" alt="<?= e($produit['nom']) ?>" width="600" height="400" loading="lazy">
                                <div class="product-body">
                                    <p class="product-category"><?= e($produit['categorie']) ?></p>
                                    <h3 class="product-name"><?= e($produit['nom']) ?></h3>
                                    <p class="product-description"><?= e($produit['description']) ?></p>
                                    <p class="product-price"><?= formaterPrix($produit['prix']) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <p class="no-results" id="no-results" hidden>Aucun produit ne correspond à votre recherche.</p>
                <?php else: ?>
                    <p class="alert alert-error">Le catalogue est momentanément indisponible. Merci de réessayer plus tard.</p>
                <?php endif; ?>

            </div>
        </section>
    </main>
<?php require __DIR__ . '/includes/pied-public.php'; ?>
