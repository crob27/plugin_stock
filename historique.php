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
$titre_page = "Plugin stock - Historique";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

debug_var();

echo "<p class='bold'>
	<a href=\"../../accueil.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a>
	 | <a href=\"index.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Retour à l'index du plugin</a>";
	/*
	if(isset($id_ouvrage)) {
		echo "
	 | <a href=\"".$_SERVER['PHP_SELF']."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Choisir un autre ouvrage</a>";

		if((isset($id_classe))||(isset($id_groupe))) {
			echo "
		 | <a href=\"".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Choisir une autre classe/enseignement</a>";
		}
	}
	*/
echo "</p>";
?>

<h2>Plugin stock</h2>
<p>Choisir un MEF, une ou des classes, une ou des années scolaires</p>
<?php
	$sql="SELECT DISTINCT annee_scolaire FROM plugin_stock_emprunts ORDER BY annee_scolaire;";
	// Afficher un tableau des MEF/classes et ouvrages
?>
<p>Historique des prêts de tel exemplaire<br />
Historique: quels ouvrages pour quelles classes, mef<br />
<br />
<br />

<br /><br /><br />
</p>

<?php
include("../../lib/footer.inc.php");
?>
