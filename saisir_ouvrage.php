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

$mode=isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : NULL);

if(isset($_POST['valider_saisie_ouvrage'])) {
	check_token();

	// Récupérer ce qui a été passé en $_POST
	// Contrôler qu'il n'existe pas déjà un ouvrage du même titre/auteur
	// Enregistrer l'ouvrage s'il n'existe pas déjà, sinon informer de l'existence en renseignant un message $msg="Un ouvrage du même titre/auteur existe déjà<br />";


	// En fin d'insertion (avec succès), on récupère l'identifiant correspondant au champ auto_increment
	$id_ouvrage=mysqli_insert_id($mysqli);
}

//**************** EN-TETE *****************
$titre_page = "Plugin stock - Saisie ouvrages";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************

?>

<p class=bold><a href="../../accueil.php"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a> | <a href="index.php">Retour</a></p>

<h2>Plugin stock</h2>
<p>Saisir des ouvrages, le nombre d'exemplaires, l'état des livres, les livres perdus.<br />
Afficher un tableau des ouvrages existants avec des liens vers la modification du nombre, de l'état, du statut (perdu) des exemplaires, de ceux qui sont prếtés, à qui,... avec lien vers l'historique des prêts pour les ouvrages et exemplaires.<br />
<br />
<br />
<br />
<br />
</p>

<?php
if(!isset($mode)) {
	// Afficher un tableau de la liste des ouvrages avec des colonnes titre, auteur, nombre d'exemplaires, nombre d'exemplaires prêtés, nombre d'exemplaires perdus.
	// A FAIRE par la suite dans le tableau: des liens vers le prêt, la réservation

	// Afficher un lien pour ajouter un ouvrage

}
elseif($mode=='saisir_ouvrage') {
	// Formulaire avec les champ Titre, Auteur, Code, nombre d'exemplaires

	echo "
	<form action='admin.php' method='post'>
		<fieldset class='fieldset_opacite50'>
			".add_token_field()."
			<input type='hidden' name='mode' value='saisir_ouvrage' />
			<input type='hidden' name='valider_saisie_ouvrage' value='y' />";

	if(isset($id_ouvrage)) {
		// On a un identifiant d'ouvrage existant
		// Récupérer les infos sur l'ouvrage
		$sql="SELECT * FROM plugin_stock_ouvrages WHERE id='".$id_ouvrage."';";
		$res=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res)>0) {
			// Affecter les valeurs $titre, $auteur, $code




			// Récupérer avec une 2è requête sql le nombre d'exemplaires de l'ouvrage pour renseigner $nb_exemplaires
			// La valeur $nb_exemplaires est obtenue en comptant le nombre d'exemplaires avec id_ouvrage='$id_ouvrage' dans plugin_stock_exemplaires




		}
		else {
			echo "<p style='color:red'>Ouvrage n°".$id_ouvrage." inconnu.</p>";
			unset($id_ouvrage);
		}
	}
	else {
		// Valeurs par défaut
		$titre='';
		$auteur='';
		$code='';
		$nb_exemplaires=0;
	}

	// Champs de saisie à mettre ici






	echo "
			<p><input type='submit' value='Enregistrer' /></p>
		</fieldset>
	</form>";


}
elseif($mode=='saisir_exemplaire') {
	// Tableau de la liste des exemplaires, avec des champs de saisie de l'état (champ select) (Très bon, Bon, Mauvais) et du statut (champ checkbox) (perdu ou non)
	// Plus une case checkbox pour supprimer un exemplaire (lors de la validation de cette suppression de plugin_stock_exemplaires, il ne faudra pas oublier de supprimer les emprunts correspondant à l'exemplaire dans plugin_stock_emprunts)



	// Formulaire pour ajouter N exemplaires



}
else {
	echo "<p style='color:red'>Mode non implémenté.</p>";
}

include("../../lib/footer.inc.php");
?>
