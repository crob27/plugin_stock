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

$plugin_stock_is_administrateur=plugin_stock_is_administrateur($_SESSION['login']);

// Tri de tableaux
$javascript_specifique[] = "lib/tablekit";
$utilisation_tablekit="ok";

//**************** EN-TETE *****************
$titre_page = "Plugin stock (1)";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class='bold'><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a></p>

<h2>Plugin stock</h2>

<p>Veuillez choisir&nbsp;:</p>
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

<?php
	$sql="SELECT pso.titre, pso.auteur, pso.code, psex.numero, psem.* 
			FROM plugin_stock_ouvrages pso,
				plugin_stock_exemplaires psex, 
				plugin_stock_emprunts psem 
			WHERE psem.date_retour<'9999-01-01 00:00:00' AND 
				psem.etat_retour!=psem.etat_initial AND 
				psem.etat_retour!='' AND 
				pso.id=psex.id_ouvrage AND 
				psex.id_ouvrage=psem.id_ouvrage AND 
				psem.id_exemplaire=psex.id 
			ORDER BY id_ouvrage, id_exemplaire, date_retour DESC;";
	plugin_stock_echo_debug("$sql<br />");
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		echo "<p>Un ou des exemplaires d'ouvrages ont vu leur état requalifié.<br />
		Ils ont pu être dégradés suite à une usure normale, ou à un usage inapproprié.<br />
		A vous d'en juger et de voir quelles suites éventuelles donner.</p>
		
		<table class='boireaus boireaus_alt sortable resizable'>
			<thead>
				<tr>
					<th>Ouvrage</th>
					<th>Auteur</th>
					<th>Numéro</th>
					<th>Élève</th>
					<th>Date prêt</th>
					<th>Date retour</th>
					<th>État initial</th>
					<th>État retour</th>
					<th>Prêteur</th>
				</tr>
			</thead>
			<tbody>";
		while($lig=mysqli_fetch_object($res)) {
			echo "
				<tr>
					<td>".$lig->titre."</td>
					<td>".$lig->auteur."</td>
					<td>".$lig->numero."</td>
					<td>".plugin_stock_get_eleve($lig->id_eleve)."</td>
					<td>".formate_date($lig->date_pret)."</td>
					<td>".formate_date($lig->date_retour)."</td>
					<td>".$lig->etat_initial."</td>
					<td>".$lig->etat_retour."</td>
					<td>".civ_nom_prenom($lig->login_preteur)."</td>
				</tr>";
		}
		echo "
			</tbody>
		</table>";
	}
?>
<pre style='color:red'>
A FAIRE : 
- Gérer les dégradations
</pre>
<?php
include("../../lib/footer.inc.php");
?>
