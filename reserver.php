<?php
$niveau_arbo = "2";

// Fichiers d'initialisation
// (attention au chemin des fichiers en fonction de l'arborescence)
include("../../lib/initialisationsPropel.inc.php");
include("../../lib/initialisations.inc.php");
include("../plugins.class.php");

// Resume session
$resultat_session = $session_gepi->security_check();
if ($resultat_session == 'c') {
    header("Location: ../../utilisateurs/mon_compte.php?change_mdp=yes");
    die();
} else if ($resultat_session == '0') {
    header("Location: ../../logout.php?auto=1");
    die();
}

// vérification des autorisations (définies dans plugin.xml)
$user_auth = new gepiPlugIn("plugin_stock");
$user_auth->verifDroits();


//**************** EN-TETE *****************
$titre_page = "Plugin stock - Réserver";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a> | <a href="index.php">Retour</a></p>

<h2>Plugin stock</h2>
<p>Réserver des ouvrages/exemplaires pour une certaine période.<br />
A charge aux utilisateurs après ces saisies de s'entendre entre eux pour modifier éventuellement ces réservations.<br />
Permettre aux administrateurs de supprimer des réservations.<br />
Afficher la liste des ouvrages, le nombre d'exemplaires déjà réservés pour une période à venir.<br />
Une fois l'ouvrage choisi, afficher les périodes de réservation, les exemplaires restants,...<br />
Pouvoir réserver du tant au tant<br />
<br /><br /><br />
</p>

<?php
include("../../lib/footer.inc.php");
?>
