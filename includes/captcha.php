<?php
/* ==========================================================================
   InfoLeader — includes/captcha.php
   CAPTCHA « fait maison » du formulaire de contact : un petit calcul affiché
   dans une image brouillée, dessinée par l'extension GD de PHP.
   Aucun service externe (pas de Google reCAPTCHA) : tout reste en local.
   La réponse est gardée dans la session du visiteur ; elle ne sert qu'une
   fois et expire au bout de 15 minutes.
   ========================================================================== */

const CAPTCHA_DUREE_SECONDES = 900;
const CAPTCHA_POLICE = 'C:/Windows/Fonts/arialbd.ttf';  // police de Windows ; sinon police intégrée à GD

// Session du site public, distincte de celle du panel d'administration
function demarrerSessionPublique(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('INFOLEADER_CONTACT');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => estHttps(),
    ]);
    session_start();
}

// Tire un nouveau calcul (addition ou soustraction, résultat de 0 à 21) et renvoie son énoncé
function nouveauCaptcha(): string
{
    $a = random_int(2, 12);
    $b = random_int(1, 9);
    if (random_int(0, 1) === 1 && $a >= $b) {
        $enonce = "$a - $b";
        $reponse = $a - $b;
    } else {
        $enonce = "$a + $b";
        $reponse = $a + $b;
    }
    $_SESSION['captcha'] = ['reponse' => $reponse, 'cree' => time()];
    return "$enonce = ?";
}

// Vérifie la réponse saisie. Le calcul est effacé dans tous les cas : un seul essai par image.
function captchaValide(string $saisie): bool
{
    $captcha = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']);
    if (!$captcha || time() - $captcha['cree'] > CAPTCHA_DUREE_SECONDES) {
        return false;
    }
    $saisie = trim($saisie);
    return preg_match('/^\d{1,3}$/', $saisie) === 1 && (int) $saisie === $captcha['reponse'];
}

// Dessine l'énoncé dans une image PNG brouillée (lignes, points, caractères inclinés)
// pour gêner les robots qui lisent les images automatiquement.
function imageCaptcha(string $enonce): void
{
    $largeur = 180;
    $hauteur = 56;
    $image = imagecreatetruecolor($largeur, $hauteur);
    imagefill($image, 0, 0, imagecolorallocate($image, 244, 246, 249));

    for ($i = 0; $i < 6; $i++) {
        $couleur = imagecolorallocate($image, random_int(120, 200), random_int(120, 200), random_int(120, 200));
        imageline($image, random_int(0, $largeur), random_int(0, $hauteur), random_int(0, $largeur), random_int(0, $hauteur), $couleur);
    }
    for ($i = 0; $i < 250; $i++) {
        $couleur = imagecolorallocate($image, random_int(100, 220), random_int(100, 220), random_int(100, 220));
        imagesetpixel($image, random_int(0, $largeur - 1), random_int(0, $hauteur - 1), $couleur);
    }

    $policeTtf = function_exists('imagettftext') && is_file(CAPTCHA_POLICE);
    $x = 14;
    foreach (str_split($enonce) as $caractere) {
        if ($caractere === ' ') {
            $x += 8;
            continue;
        }
        $couleur = imagecolorallocate($image, random_int(0, 60), random_int(40, 90), random_int(120, 180));
        if ($policeTtf) {
            imagettftext($image, random_int(20, 24), random_int(-15, 15), $x, random_int(36, 44), $couleur, CAPTCHA_POLICE, $caractere);
            $x += 19;
        } else {
            imagestring($image, 5, $x, random_int(15, 25), $caractere, $couleur);
            $x += 14;
        }
    }

    header('Content-Type: image/png');
    header('Cache-Control: no-store');  // le navigateur ne doit jamais réutiliser une ancienne image
    imagepng($image);
}
