<?php
/* InfoLeader — admin/logout.php : déconnexion (formulaire POST protégé par le jeton CSRF) */
require __DIR__ . '/../includes/bootstrap.php';
demarrerSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValide() && !empty($_SESSION['admin'])) {
    journaliser('evenement', 'Déconnexion.');
    deconnecterAdmin();
    flash('succes', 'Vous êtes déconnecté.');
}
rediriger('login.php');
