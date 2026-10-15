<?php
// ===== Image du CAPTCHA du formulaire de contact =====
// Chaque affichage tire un nouveau calcul : seul le dernier calcul affiché est valable.
require __DIR__ . '/includes/bootstrap.php';
demarrerSessionPublique();
imageCaptcha(nouveauCaptcha());
