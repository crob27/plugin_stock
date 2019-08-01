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
if(!calcul_autorisation_plugin_stock($_SESSION['login'], 'admin.php')) {
	header("Location: ./index.php?msg=Accès non autrisé");
	die();
}

if(isset($_POST['is_posted'])) {
	// On a validé les choix des admins et users

	check_token();

	$msg='';

	$login_admin=isset($_POST['login_admin']) ? $_POST['login_admin'] : array();
	$login_user=isset($_POST['login_user']) ? $_POST['login_user'] : array();

	// Recherche des prêteurs précédemment enregistrés
	$nb_preteurs_ajoutes=0;
	$nb_preteurs_supprimes=0;
	$tab_user_sql=array();
	$sql="SELECT * FROM plugin_stock_users WHERE statut='preteur';";
	//echo "$sql<br />";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		while($lig=mysqli_fetch_object($res)) {
			$tab_user_sql[]=$lig->login;

			if(!in_array($lig->login, $login_user)) {
				// Le prêteur n'est plus dans la liste des prêteurs cochés
				$sql="DELETE FROM plugin_stock_users WHERE login='".$lig->login."';";
				//echo "$sql<br />";
				$del=mysqli_query($mysqli, $sql);
				if($del) {
					$nb_preteurs_supprimes++;
				}
				else {
					$msg.="Erreur lors de la suppression du prêteur ".civ_nom_prenom($lig->login).".<br />";
				}
			}
		}
	}

	$tab_admin_sql=array();
	$sql="SELECT * FROM plugin_stock_users WHERE statut='administrateur';";
	//echo "$sql<br />";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		while($lig=mysqli_fetch_object($res)) {
			$tab_admin_sql[]=$lig->login;
		}
	}

	foreach($login_user as $key => $current_login) {
		if(!in_array($current_login, $tab_user_sql)) {
			// Le prêteur n'était pas dans la liste des prêteurs enregistrés
			// S'il est aussi dans la liste des administrateurs, on l'enregistrera comme administrateur
			if(!in_array($current_login, $login_admin)) {
				if(in_array($current_login, $tab_admin_sql)) {
					// Il était administrateur et ne l'est plus
					$sql="UPDATE plugin_stock_users SET statut='preteur' WHERE login='".$current_login."';";
					//echo "$sql<br />";
					$update=mysqli_query($mysqli, $sql);
					if($update) {
						$nb_preteurs_ajoutes++;
					}
					else {
						$msg.="Erreur lors de l'ajout du prêteur ".civ_nom_prenom($current_login).".<br />";
					}
				}
				else {
					$sql="INSERT INTO plugin_stock_users SET login='".$current_login."', statut='preteur';";
					//echo "$sql<br />";
					$insert=mysqli_query($mysqli, $sql);
					if($insert) {
						$nb_preteurs_ajoutes++;
					}
					else {
						$msg.="Erreur lors de l'ajout du prêteur ".civ_nom_prenom($current_login).".<br />";
					}
				}
			}
		}
	}


	// Recherche des administrateurs précédemment enregistrés
	$nb_administrateurs_ajoutes=0;
	$nb_administrateurs_supprimes=0;
	$tab_admin_sql=array();
	$sql="SELECT * FROM plugin_stock_users WHERE statut='administrateur';";
	//echo "$sql<br />";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		while($lig=mysqli_fetch_object($res)) {
			$tab_admin_sql[]=$lig->login;

			if((!in_array($lig->login, $login_admin))&&
			(!in_array($lig->login, $login_user))) {
				// L'administrateur n'est plus dans la liste des administrateurs cochés
				// et il n'est pas non plus dans la liste des prêteurs 
				$sql="DELETE FROM plugin_stock_users WHERE login='".$lig->login."';";
				//echo "$sql<br />";
				$del=mysqli_query($mysqli, $sql);
				if($del) {
					$nb_administrateurs_supprimes++;
				}
				else {
					$msg.="Erreur lors de la suppression de l'administrateur ".civ_nom_prenom($lig->login).".<br />";
				}
			}
		}
	}


	foreach($login_admin as $key => $current_login) {
		if(!in_array($current_login, $tab_admin_sql)) {
			// L'administrateur n'était pas dans la liste des administrateurs enregistrés
			$sql="INSERT INTO plugin_stock_users SET login='".$current_login."', statut='administrateur';";
			//echo "$sql<br />";
			$insert=mysqli_query($mysqli, $sql);
			if($insert) {
				$nb_administrateurs_ajoutes++;
			}
			else {
				$msg.="Erreur lors de l'ajout de l'administrateur ".civ_nom_prenom($current_login).".<br />";
			}
		}
	}

	if($nb_administrateurs_ajoutes>0) {
		$msg.=$nb_administrateurs_ajoutes." administrateur(s) ajouté(s).<br />";
	}
	if($nb_administrateurs_supprimes>0) {
		$msg.=$nb_administrateurs_supprimes." administrateur(s) supprimé(s).<br />";
	}

	if($nb_preteurs_ajoutes>0) {
		$msg.=$nb_preteurs_ajoutes." prêteur(s) ajouté(s).<br />";
	}
	if($nb_preteurs_supprimes>0) {
		$msg.=$nb_preteurs_supprimes." prêteur(s) supprimé(s).<br />";
	}

}

$themessage  = 'Des informations ont été modifiées. Voulez-vous vraiment quitter sans enregistrer ?';

//**************** EN-TETE *********************
$titre_page = "Plugin stock : administration";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *****************

// Fonction destinée à afficher les données en $_POST, $_GET, $_SESSION et $_SERVER
//debug_var();

?>

<p class=bold><a href="../../accueil.php" onclick="return confirm_abandon (this, change, '<?php echo $themessage;?>')"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a> | 
<a href="./index.php" onclick="return confirm_abandon (this, change, '<?php echo $themessage;?>')">Index du plugin</a></p>

<h2>Plugin stock</h2>

<form action='admin.php' method='post'>
	<fieldset class='fieldset_opacite50'>
		<input type='hidden' name='is_posted' value='1' />

		<h3>Administrateurs</h3>
		<p class='bold'>Choisissez les utilisateurs autorisés à administrer le plugin&nbsp;: saisir/créer des ouvrages/exemplaires, à supprimer des réservations d'autres utilisateurs,...</p>

		<?php
			echo add_token_field();

			$tab_user_preselectionnes=array();
			$sql="SELECT * FROM plugin_stock_users WHERE statut='administrateur';";
			$res=mysqli_query($mysqli, $sql);
			if(mysqli_num_rows($res)>0) {
				while($lig=mysqli_fetch_object($res)) {
					$tab_user_preselectionnes[]=$lig->login;
				}
			}

			echo liste_checkbox_utilisateurs(array('professeur', 'cpe', 'scolarite'), $tab_user_preselectionnes, 'login_admin', 'cocher_decocher_admin', "y", "", "checkbox_change", 'y');

			echo "<p><a href='javascript:cocher_decocher_admin(true)'>Tout cocher</a> / <a href='javascript:cocher_decocher_admin(false)'>Tout décocher</a></p>";
			echo js_checkbox_change_style('checkbox_change', 'texte_', "y");
		?>

		<br />

		<h3>Prêteurs</h3>
		<p class='bold'>Choisissez les utilisateurs autorisés à effectuer des réservations/distributions d'exemplaires des ouvrages,...</p>


		<?php
			echo add_token_field();

			$tab_user_preselectionnes=array();
			//$sql="SELECT * FROM plugin_stock_users WHERE statut='preteur';";
			// Qui peut le plus peut le moins: Un administrateur peut prêter
			$sql="SELECT * FROM plugin_stock_users WHERE statut='preteur' OR statut='administrateur';";
			$res=mysqli_query($mysqli, $sql);
			if(mysqli_num_rows($res)>0) {
				while($lig=mysqli_fetch_object($res)) {
					$tab_user_preselectionnes[]=$lig->login;
				}
			}

			echo liste_checkbox_utilisateurs(array('professeur', 'cpe', 'scolarite'), $tab_user_preselectionnes, 'login_user', 'cocher_decocher', "y", "", "checkbox_change", 'y');

			echo "<p><a href='javascript:cocher_decocher(true)'>Tout cocher</a> / <a href='javascript:cocher_decocher(false)'>Tout décocher</a></p>";

		?>

		<p style='margin-top:1em; '><input type='submit' value='Enregistrer' /></p>
	</fieldset>
</form>

<p style='margin-top:1em; text-indent:-4em; margin-left:4em;'><em>NOTES&nbsp;:</em> Un compte déclaré administrateur sera automatiquement aussi dans la liste de prêteurs.</p>
<?php
include("../../lib/footer.inc.php");
?>
