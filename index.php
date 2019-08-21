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

include("./functions_plugin_stock.php");

//**************** EN-TETE *****************
$titre_page = "Plugin stock (1)";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a> | <a href="autre_script.php">Autre script</a></p>

<h2>Plugin stock</h2>

<p>Choisissez&nbsp;:</p>
<ul>
<?php

if(calcul_autorisation_plugin_stock($_SESSION['login'], 'admin.php')) {
	echo "
	<li><a href='admin.php'>Administrer le plugin</a></li>
	<li><a href='saisir_ouvrage.php'>Ajouter/supprimer des ouvrages</a></li>";
}
?>
	<li><a href='reserver.php'>Réserver des ouvrages pour une période</a></li>
	<li><a href='preter.php'>Prêter des exemplaires d'ouvrages, consulter les prêts</a></li>
	<li><a href='historique.php'>Historique des prêts</a></li>
	<!--
	<li><a href=''></a></li>
	-->
</ul>
<pre>
A FAIRE : 
Pour chaque exemplaire: pouvoir accéder à un historique des prêts
</pre>
<?php
include("../../lib/footer.inc.php");
?>
