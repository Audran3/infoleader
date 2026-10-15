# Cahier de recettes — Site InfoLeader

> Liste des tests à refaire **après chaque modification** du site (tâche QUAL-2).
> Une « recette », c'est la vérification que tout fonctionne encore comme prévu.

## Avant de commencer

- WAMP est lancé (icône **verte**).
- Le site s'ouvre sur `http://localhost/infoleader/`.
- Vous connaissez vos identifiants du panel (`/admin/`).
- Pour les tests qui envoient des e-mails, la configuration SMTP fonctionne (page E-mails, « Tester l'envoi »).

Notez le résultat de chaque test (OK / KO + date). Un test KO doit être corrigé avant de livrer.

---

## 1. Site public

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| T01 | Ouvrir l'accueil | La bannière « Bienvenue chez InfoLeader » et le menu s'affichent | OK — 15/10/2026 |
| T02 | Ouvrir « Nos Produits » | 6 produits avec image, nom, prix et description | OK — 15/10/2026 |
| T03 | Taper « 4K » puis « ecran » (sans accent) dans le filtre | Seul l'écran reste affiché, le compteur indique « 1 produit » | OK — 15/10/2026 (structure) ; voir M01 |
| T04 | Envoyer un message complet depuis Contact | « Merci pour votre message — il a bien été envoyé. » ; le message apparaît dans Maintenance > Journaux > Messages de contact | OK — 15/10/2026 |
| T05 | Envoyer le formulaire avec des champs vides / un e-mail « abc » | Messages d'erreur sous les champs, rien n'est enregistré | OK — 15/10/2026 |
| T06 | Envoyer 4 messages en moins de 10 minutes | Le 4e est refusé : « Vous avez déjà envoyé 3 messages… » | OK — 15/10/2026 |
| T07 | (Test technique) Envoyer le formulaire avec le champ caché `site_web` rempli | Faux message de succès, rien n'est enregistré, ligne « champ piège » dans le journal | OK — 15/10/2026 |
| T08 | Regarder sous le formulaire de contact | La mention d'information (données, 12 mois, droits, CNIL) est visible | OK — 15/10/2026 |
| T09 | F12 > Réseau > cliquer sur la page > En-têtes de réponse | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin` | OK — 15/10/2026 |
| T10 | Ouvrir `/infoleader/config/admin.json`, `/infoleader/includes/bootstrap.php`, `/infoleader/.htaccess` | Erreur 403 (Forbidden) à chaque fois | OK — 15/10/2026 |
| T37 | Provoquer une erreur PHP (variable inexistante dans une page de test) | Aucun détail technique à l'écran ; l'erreur est dans `C:\wamp64\logs\php_error.log` | OK — 15/10/2026 |
| T39 | Contact : remplir le formulaire avec un mauvais résultat de calcul | « Le résultat du calcul est incorrect… », le texte saisi est conservé, une nouvelle image est proposée, rien n'est enregistré | OK — 15/10/2026 |
| T40 | Contact : bon résultat de calcul | Message enregistré ; un e-mail « Nouveau message de contact de … » part vers la boîte configurée (voir E04) | OK — 15/10/2026 (sans envoi réel) |
| T41 | Contact : renvoyer le formulaire avec la même réponse | Refusé : chaque calcul ne sert qu'une fois | OK — 15/10/2026 |
| T42 | Contact : cliquer sur « Autre calcul » | L'image change, le champ se vide | OK — 15/10/2026 |

## 2. Connexion et sécurité du panel

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| T11 | Ouvrir `/infoleader/admin/dashboard.php` sans être connecté | Redirection vers la page de connexion | OK — 15/10/2026 |
| T12 | Se connecter avec un mauvais mot de passe | « Identifiant ou mot de passe incorrect » + bouton bloqué 3 secondes | OK — 15/10/2026 |
| T13 | Retenter pendant les 3 secondes (même avec le bon mot de passe) | Refusé | OK — 15/10/2026 |
| T14 | Se connecter avec les bons identifiants | Tableau de bord : e-mail, date et IP de connexion, 4 outils | OK — 15/10/2026 |
| T27 | Maintenance > Journaux > Échecs / blocages | L'identifiant saisi lors de l'échec est masqué (`ad***@…`) | OK — 15/10/2026 |
| T33 | (Test technique) Envoyer un formulaire du panel sans jeton CSRF | « Le formulaire a expiré » ; rien n'est modifié | OK — 15/10/2026 |
| T34 | Cliquer sur « Déconnexion » | Retour à la connexion ; le tableau de bord n'est plus accessible | OK — 15/10/2026 |
| T35 | Faire 10 erreurs de connexion d'affilée | « Accès temporairement bloqué » (connexion et mot de passe oublié) | OK — 15/10/2026 |
| T36 | Dans `includes/`, lancer `php debloquer-ip.php` puis `php debloquer-ip.php ::1` | L'adresse est listée puis débloquée ; la connexion redevient possible | OK — 15/10/2026 |

## 3. Modules du panel

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| T15 | Ouvrir chaque page du panel (tableau de bord, E-mails ×2, Mot de passe, Utilisateur, Maintenance ×6) | Toutes s'affichent, aucune erreur | OK — 15/10/2026 |
| T16 | E-mails : enregistrer avec un expéditeur « pas-valide » | Refusé : « n'est pas une adresse valide » | OK — 15/10/2026 |
| T17 | E-mails (onglet SMTP) : expéditeur différent de l'identifiant SMTP | Refusé : l'expéditeur doit être l'adresse du compte SMTP | OK — 15/10/2026 |
| T18 | Mot de passe : saisir un mot de passe actuel faux | « Le mot de passe actuel est incorrect » | OK — 15/10/2026 |
| T19 | Mot de passe : nouveau mot de passe sans majuscule (désactiver le contrôle du navigateur n'est pas nécessaire : PHP vérifie aussi) | Refusé avec la règle manquante | OK — 15/10/2026 |
| T20 | Mot de passe : changement valide | « Votre mot de passe a été modifié », on reste connecté | OK — 15/10/2026 |
| T38 | Après T20, regarder le journal (et la boîte mail, voir E02) | Ligne « Alerte de sécurité … envoyée » | OK — 15/10/2026 (sans envoi réel) |
| T21 | Utilisateur : mot de passe faux / adresse invalide / adresse identique à l'actuelle / deux adresses différentes | 4 messages d'erreur distincts | OK — 15/10/2026 |
| T22 | Utilisateur : changement valide | « Votre adresse de connexion est désormais … » | OK — 15/10/2026 |
| T28 | Maintenance > Système > Fichiers de configuration | 4 lignes « refusé (HTTP 403) » | OK — 15/10/2026 |
| T31 | Maintenance > Journaux : cliquer sur un compteur | Liste filtrée + encart « Filtre actif » | OK — 15/10/2026 |
| T29 | Maintenance > Sauvegarde > Exporter | Un fichier JSON est téléchargé ; il ne contient aucun mot de passe ni hash | OK — 15/10/2026 |
| T30 | Maintenance > Purger storage/tmp | La copie de la sauvegarde est listée, puis supprimée après confirmation | OK — 15/10/2026 |
| T32 | Maintenance > Journaux : vider messages, mail.log et journal (avec confirmation) | Chaque liste est vide ; l'action est notée dans le journal | OK — 15/10/2026 |

## 4. Mot de passe oublié

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| T23 | Connexion > « Mot de passe oublié ? » > Envoyer un code | Écran de saisie du code avec minuteur 5:00 | OK — 15/10/2026 |
| T24 | Saisir un code faux | « Code incorrect. Il vous reste 4 essai(s). » | OK — 15/10/2026 |
| T25 | Saisir le bon code et un nouveau mot de passe valide, puis se connecter | Mot de passe modifié, connexion réussie | OK — 15/10/2026 |
| T26 | Demander un 4e code dans la même heure | « Trop de demandes de code… » ; le code déjà envoyé reste valable | OK — 15/10/2026 |

## 5. Tests dans le navigateur (JavaScript et affichage)

Ces tests demandent de regarder l'écran : ils se font à la main.

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| M01 | Nos Produits : taper « sans fil », puis « imprimante » | 2 produits, puis « Aucun produit ne correspond » | OK — 15/10/2026 (avant QUAL-1 ; rendu HTML identique depuis) |
| M02 | Réduire la fenêtre (ou F12 > mode téléphone) et cliquer sur ☰ | Le menu s'ouvre et se ferme ; le bouton Administration est en bas du menu | OK — 15/10/2026 |
| M03 | Contact : cliquer « Envoyer » avec les champs vides | Erreurs affichées par JavaScript sans recharger la page | OK — 15/10/2026 |
| M04 | Panel > Mot de passe : taper un nouveau mot de passe | Les règles se cochent en vert au fur et à mesure | OK — 15/10/2026 |
| M05 | Connexion : mot de passe faux | Le bouton reste grisé 3 s avec le compte à rebours | OK — 15/10/2026 |
| M06 | Connexion > « Mot de passe oublié ? » | Une fenêtre s'ouvre avec le bouton « Envoyer un code de réinitialisation » | OK — 15/10/2026 |
| M07 | Maintenance > Purger storage/tmp | Une fenêtre de confirmation s'ouvre avant la suppression | À faire |
| M08 | Les messages colorés (succès, erreur) | Ils disparaissent au bout de 5 à 9 secondes | À faire |

## 6. Tests avec une vraie boîte mail

| N° | Action | Résultat attendu | Dernier résultat |
|----|--------|------------------|------------------|
| E01 | E-mails (onglet SMTP) > Tester l'envoi | E-mail de test reçu (vérifier aussi les Spam) | OK — 15/10/2026 (confirmé par l'étudiant) |
| E02 | Changer son mot de passe (module Mot de passe) | E-mail « Alerte de sécurité : mot de passe modifié » reçu | À faire |
| E03 | Mot de passe oublié > Envoyer un code | Le code à 8 chiffres arrive par e-mail | À faire |
| E04 | Envoyer un message depuis la page Contact, puis cliquer sur « Répondre » dans la boîte de réception | L'e-mail arrive avec le nom, l'e-mail et le message du visiteur ; « Répondre » écrit à l'adresse du visiteur | À faire |

---

## Historique des recettes

| Date | Type | Résultat | Remarques |
|------|------|----------|-----------|
| 15/10/2026 | Tests du CAPTCHA et de l'envoi des messages de contact (T39 à T42) | **OK** | Envoi en mode PHP mail pendant le test (aucun e-mail réel) ; en-tête « Répondre à » vérifié sur un message SMTP préparé sans être envoyé. |
| 15/10/2026 | Recette automatisée (tests T01 à T38) | **39 / 39 OK** | Exécutée avec un compte administrateur de test temporaire et l'envoi en mode PHP mail (aucun e-mail réel) ; le vrai compte, la configuration et toutes les données ont été restaurés à l'identique ensuite. |
