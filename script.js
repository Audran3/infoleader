/* ==========================================================================
   InfoLeader — script.js
   Fichier JavaScript unique, chargé sur les 3 pages.
   JavaScript natif uniquement : aucun framework, aucune bibliothèque, aucun AJAX.

   1. Menu mobile (bouton « hamburger »)
   2. Défilement fluide vers les ancres internes (#...)
   3. Apparition en fondu (fade-in) des sections au chargement
   4. Filtre des produits en temps réel (page Nos Produits)
   5. Validation du formulaire de contact (page Contact)
   ========================================================================== */

// Signale au CSS que JavaScript est actif : les sections .fade-in ne sont
// masquées au départ que dans ce cas (sans JavaScript, tout reste visible).
document.documentElement.classList.add('js');

// Le script est chargé dans le <head> : on attend que la page soit construite.
document.addEventListener('DOMContentLoaded', function () {
    initMenuMobile();
    initScrollFluide();
    initApparition();
    initFiltreProduits();
    initCorrectionErreurs();
});


/* ----- 1. Menu mobile ----- */
function initMenuMobile() {
    const bouton = document.querySelector('.nav-toggle');
    const menu = document.getElementById('menu');
    if (!bouton || !menu) return;

    function fermerMenu() {
        menu.classList.remove('open');
        bouton.setAttribute('aria-expanded', 'false');
        bouton.setAttribute('aria-label', 'Ouvrir le menu');
    }

    bouton.addEventListener('click', function () {
        const ouvert = menu.classList.toggle('open');
        bouton.setAttribute('aria-expanded', String(ouvert));
        bouton.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
    });

    // Referme le menu après un clic sur un lien...
    menu.addEventListener('click', function (event) {
        if (event.target.closest('a')) fermerMenu();
    });

    // ... ou avec la touche Échap
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menu.classList.contains('open')) {
            fermerMenu();
            bouton.focus();
        }
    });
}


/* ----- 2. Défilement fluide ----- */
function initScrollFluide() {
    const animationsReduites = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('a[href^="#"]').forEach(function (lien) {
        lien.addEventListener('click', function (event) {
            const cible = document.querySelector(lien.getAttribute('href'));
            if (!cible) return;

            event.preventDefault();
            cible.scrollIntoView({ behavior: animationsReduites ? 'auto' : 'smooth' });
        });
    });
}


/* ----- 3. Apparition des sections ----- */
function initApparition() {
    document.querySelectorAll('.fade-in').forEach(function (element, i) {
        // Léger décalage entre chaque section (effet « cascade »)
        setTimeout(function () {
            element.classList.add('visible');
        }, 100 + i * 150);
    });
}


/* ----- 4. Filtre des produits ----- */
function initFiltreProduits() {
    const champ = document.getElementById('search');
    if (!champ) return; // on n'est pas sur la page Nos Produits

    const compteur = document.getElementById('products-count');
    const aucunResultat = document.getElementById('no-results');

    // Optimisation : le texte de chaque carte est préparé une seule fois,
    // au lieu d'être relu dans la page à chaque touche frappée.
    const produits = Array.from(document.querySelectorAll('.product-card')).map(function (carte) {
        return { carte: carte, texte: normaliser(carte.dataset.search) };
    });

    // Optimisation : on filtre après 150 ms sans frappe, pas à chaque lettre
    let minuterie = null;
    champ.addEventListener('input', function () {
        clearTimeout(minuterie);
        minuterie = setTimeout(filtrer, 150);
    });

    function filtrer() {
        // Chaque mot tapé doit être présent : « clavier logitech » fonctionne
        const mots = normaliser(champ.value).split(/\s+/).filter(Boolean);
        let nbVisibles = 0;

        produits.forEach(function (produit) {
            const correspond = mots.every(function (mot) {
                return produit.texte.includes(mot);
            });
            produit.carte.hidden = !correspond;
            if (correspond) nbVisibles++;
        });

        compteur.textContent = nbVisibles + (nbVisibles > 1 ? ' produits' : ' produit');
        aucunResultat.hidden = nbVisibles > 0;
    }
}

// Minuscules + suppression des accents : « Écran » et « ecran » correspondent
function normaliser(texte) {
    return texte.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}


/* ----- 5. Validation du formulaire de contact ----- */
// Appelée par onsubmit="return validateForm()" dans contact.php.
// Renvoie true (le formulaire part vers PHP) ou false (envoi bloqué).
function validateForm() {
    const formulaire = document.getElementById('contact-form');
    const nom = formulaire.elements.nom;
    const email = formulaire.elements.email;
    const message = formulaire.elements.message;
    const formatEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    const erreurs = [];

    [nom, email, message].forEach(effacerErreur);

    if (nom.value.trim() === '') {
        erreurs.push([nom, 'Veuillez indiquer votre nom.']);
    }
    if (email.value.trim() === '') {
        erreurs.push([email, 'Veuillez indiquer votre adresse e-mail.']);
    } else if (!formatEmail.test(email.value.trim())) {
        erreurs.push([email, "L'adresse e-mail n'est pas valide (ex. : nom@domaine.fr)."]);
    }
    if (message.value.trim() === '') {
        erreurs.push([message, 'Veuillez écrire votre message.']);
    }

    erreurs.forEach(function (erreur) {
        afficherErreur(erreur[0], erreur[1]);
    });

    if (erreurs.length > 0) {
        erreurs[0][0].focus(); // place le curseur sur le premier champ à corriger
        return false;
    }
    return true;
}

function afficherErreur(champ, texte) {
    champ.classList.add('invalid');
    champ.setAttribute('aria-invalid', 'true');
    document.getElementById(champ.id + '-error').textContent = texte;
}

function effacerErreur(champ) {
    champ.classList.remove('invalid');
    champ.removeAttribute('aria-invalid');
    document.getElementById(champ.id + '-error').textContent = '';
}

// Efface l'erreur d'un champ dès que l'utilisateur le corrige
function initCorrectionErreurs() {
    const formulaire = document.getElementById('contact-form');
    if (!formulaire) return;

    formulaire.addEventListener('input', function (event) {
        if (event.target.classList.contains('invalid')) effacerErreur(event.target);
    });
}
