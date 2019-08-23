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

if(!plugin_stock_is_administrateur($_SESSION['login'])) {
	header("Location: ./index.php?msg=Accès non autorisé");
	die();
}

$date_courante=strftime("%Y-%m-%d %H:%M:%S");

$mode=isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : NULL);
$id_ouvrage=isset($_POST['id_ouvrage']) ? $_POST['id_ouvrage'] : (isset($_GET['id_ouvrage']) ? $_GET['id_ouvrage'] : NULL);

$afficher_tous_les_exemplaires=isset($_POST['afficher_tous_les_exemplaires']) ? $_POST['afficher_tous_les_exemplaires'] : (isset($_GET['afficher_tous_les_exemplaires']) ? $_GET['afficher_tous_les_exemplaires'] : 'n');

// ...
$ajout_date_mise_en_service=isset($_POST['ajout_date_mise_en_service']) ? $_POST['ajout_date_mise_en_service'] : (isset($_GET['ajout_date_mise_en_service']) ? $_GET['ajout_date_mise_en_service'] : NULL);

$msg='';

//======================================

if(isset($id_ouvrage)) {
	if(!preg_match('/^[0-9]{1,}$/', $id_ouvrage)) {
		$msg.="Identifiant d'ouvrage ($id_ouvrage) invalide.<br />";
		unset($id_ouvrage);
	}
	else {
		$sql="SELECT 1=1 FROM plugin_stock_ouvrages WHERE id='".$id_ouvrage."';";
		$res=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res)==0) {
			$msg.="L'ouvrage n°".$id_ouvrage." n'existe pas.<br />";
			unset($id_ouvrage);
		}
	}
}

//======================================

if(isset($_POST['valider_saisie_ouvrage'])) {
	check_token();

	// Récupérer ce qui a été passé en $_POST
	$titre=isset($_POST['titre']) ? $_POST['titre'] : NULL;
	$auteur=isset($_POST['auteur']) ? $_POST['auteur'] : NULL;
	$code=isset($_POST['code']) ? $_POST['code'] : NULL;

	// Contrôler qu'il n'existe pas déjà un ouvrage du même titre/auteur
	// Enregistrer l'ouvrage s'il n'existe pas déjà, sinon informer de l'existence en renseignant un message $msg="Un ouvrage du même titre/auteur existe déjà<br />";
	if($titre!="" && $auteur!="" && $code !="") {
		if(isset($id_ouvrage)) {
			$sql="UPDATE plugin_stock_ouvrages SET titre='".$titre."', auteur='".$auteur."', code='".$code."' WHERE id='".$id_ouvrage."';";
			//plugin_stock_echo_debug("$sql<br />");
			$res=mysqli_query($mysqli, $sql);
			if($res)
			{
				$msg.="Ouvrage n°$id_ouvrage mis à jour.<br />";
			}
			else {
				$msg.="Erreur lors de la mise à jour de l'ouvrage.<br />";
			}
		}
		else {
			$sql='select 1=1 From plugin_stock_ouvrages where titre="'.$titre.'" and auteur="'.$auteur.'" and code="'.$code.'";';
			$res=mysqli_query($mysqli, $sql);
			if(mysqli_num_rows($res)>0) {
				$msg.="L'ouvrage existe déjà.<br />";
			}
			else{
				$sql='insert into plugin_stock_ouvrages set titre="'.$titre.'", auteur="'.$auteur.'", code="'.$code.'";';
				$res=mysqli_query($mysqli, $sql);
				if($res)
				{
					$msg.="Ouvrage ajouté.<br />";
					// En fin d'insertion (avec succès), on récupère l'identifiant correspondant au champ auto_increment
					$id_ouvrage=mysqli_insert_id($mysqli);
				}
				else {
					$msg.="Erreur lors de l'ajout de l'ouvrage.<br />";
				}
			}
		}
	}
	else {
		$msg.="Titre, auteur et code ne peuvent pas être vide.<br />";
	}

}

//======================================

if(isset($mode)) {
	if(($mode=='saisir_exemplaire')&&(!isset($id_ouvrage))) {
		$msg.="Il n'est pas possible de saisir des exemplaires sans préciser pour quel ouvrage.<br />";
		unset($mode);
	}
}

//======================================

if(isset($_POST['valider_saisie_exemplaires'])) {
	check_token();

	if(!isset($id_ouvrage)) {
		$msg.="Il n'est pas possible de saisir des exemplaires sans préciser pour quel ouvrage.<br />";
	}
	else {
		// Récupérer ce qui a été passé en $_POST

		$suppr=isset($_POST['suppr']) ? $_POST['suppr'] : array();
		$etat=isset($_POST['etat']) ? $_POST['etat'] : array();
		$statut=isset($_POST['statut']) ? $_POST['statut'] : array();
		$date_mise_en_service=isset($_POST['date_mise_en_service']) ? $_POST['date_mise_en_service'] : array();

		$nb_update=0;
		foreach($etat as $id_exemplaire => $value_etat) {
			$sql="UPDATE plugin_stock_exemplaires SET etat='".mysqli_real_escape_string($mysqli, $value_etat)."'";
			if(isset($statut[$id_exemplaire])) {
				$sql.=", statut='".mysqli_real_escape_string($mysqli, $statut[$id_exemplaire])."'";
			}

			if(isset($date_mise_en_service[$id_exemplaire])) {
				//!preg_match("#^[0-9]{1,}/[0-9]{1,}/[0-9]{4}$#", ...)
				if(!check_date($date_mise_en_service[$id_exemplaire])) {
					$msg.="La date de mise en service de l'exemplaire d'identifiant ".$id_exemplaire." (".$date_mise_en_service[$id_exemplaire].") n'est pas au format jj/mm/aaaa.<br />";
				}
				else {
					$sql.=", date_mise_en_service='".get_mysql_date_from_slash_date($date_mise_en_service[$id_exemplaire])."'";
				}
			}

			$sql.=" WHERE id='".$id_exemplaire."';";
			//plugin_stock_echo_debug("$sql<br />");
			$update=mysqli_query($mysqli, $sql);
			if(!$update) {
				$msg.="Erreur lors de la mise à jour de l'exemplaire d'identifiant ".$id_exemplaire.".<br />";
			}
			else {
				$nb_update++;
			}
		}
		if($nb_update>0) {
			$msg.=$nb_update." exemplaire(s) mis à jour.<br />";
		}

		$nb_suppr=0;
		foreach($suppr as $key => $id_exemplaire) {
			$sql="UPDATE plugin_stock_exemplaires SET date_de_retrait='".strftime("%Y-%m-%d %H:%M:%S")."' WHERE id='".$id_exemplaire."';";
			$update=mysqli_query($mysqli, $sql);
			if(!$update) {
				$msg.="Erreur lors de l'ajout de l'exemplaire n°$numero.<br />";
			}
			else {
				$nb_suppr++;
			}
		}
		if($nb_suppr>0) {
			$msg.=$nb_suppr." exemplaire(s) supprimé(s).<br />";
		}

		$ajout_nb_exemplaires=isset($_POST['ajout_nb_exemplaires']) ? $_POST['ajout_nb_exemplaires'] : NULL;
		$ajout_etat=isset($_POST['ajout_etat']) ? $_POST['ajout_etat'] : 'neuf';
		$ajout_date_mise_en_service=isset($_POST['ajout_date_mise_en_service']) ? $_POST['ajout_date_mise_en_service'] : NULL;

		// Nombre d'exemplaires ajoutés
		if((isset($ajout_nb_exemplaires))&&(preg_match('/^[0-9]{1,}$/', $ajout_nb_exemplaires))&&($ajout_nb_exemplaires>0)&&(isset($ajout_date_mise_en_service))&&(check_date($ajout_date_mise_en_service))) {
			// On ajoute les exemplaires

			$numero=1;
			$sql="SELECT MAX(numero) AS nummax FROM plugin_stock_exemplaires WHERE id_ouvrage='".$id_ouvrage."';";
			$res=mysqli_query($mysqli, $sql);
			if(mysqli_num_rows($res)>0) {
				$lig=mysqli_fetch_object($res);
				$numero=$lig->nummax+1;
			}

			$nb_insert=0;
			for($i=0;$i<$ajout_nb_exemplaires;$i++) {
				$sql="INSERT INTO plugin_stock_exemplaires SET id_ouvrage='".$id_ouvrage."', 
												numero='".$numero."', 
												etat='".$ajout_etat."', 
												statut='', 
												date_mise_en_service='".get_mysql_date_from_slash_date($ajout_date_mise_en_service)."', 
												date_de_retrait='9999-12-01 00:00:00';";
				$insert=mysqli_query($mysqli, $sql);
				if(!$insert) {
					$msg.="Erreur lors de l'ajout de l'exemplaire n°$numero.<br />";
				}
				else {
					$nb_insert++;
					$numero++;
				}
			}

			if($nb_insert>0) {
				$msg.=$nb_insert." exemplaire(s) ajouté(s).<br />";
			}
		}

	}
}

//======================================

if((isset($_POST['suppr_ouvrage']))&&(is_array($_POST['suppr_ouvrage']))) {
	check_token();

	$nb_suppr=0;
	foreach($_POST['suppr_ouvrage'] as $key => $tmp_id_ouvrage) {
		if(!preg_match('/^[0-9]{1,}$/', $tmp_id_ouvrage)) {
			$msg.="Identifiant d'ouvrage ($tmp_id_ouvrage) invalide.<br />";
		}
		else {
			$sql="DELETE FROM plugin_stock_exemplaires WHERE id_ouvrage='".$tmp_id_ouvrage."';";
			$del=mysqli_query($mysqli, $sql);
			if(!$del) {
				$msg.="Erreur lors de la suppression des exemplaires de l'ouvrage d'identifiant $tmp_id_ouvrage.<br />";
			}
			else {
				$sql="DELETE FROM plugin_stock_emprunts WHERE id_ouvrage='".$tmp_id_ouvrage."';";
				$del=mysqli_query($mysqli, $sql);
				if(!$del) {
					$msg.="Erreur lors de la suppression des emprunts d'exemplaires de l'ouvrage d'identifiant $tmp_id_ouvrage.<br />";
				}
				else {
					$sql="DELETE FROM plugin_stock_reservations WHERE id_ouvrage='".$tmp_id_ouvrage."';";
					$del=mysqli_query($mysqli, $sql);
					if(!$del) {
						$msg.="Erreur lors de la suppression des réservations d'exemplaires de l'ouvrage d'identifiant $tmp_id_ouvrage.<br />";
					}
					else {

						$sql="DELETE FROM plugin_stock_ouvrages WHERE id='".$tmp_id_ouvrage."';";
						$del=mysqli_query($mysqli, $sql);
						if(!$del) {
							$msg.="Erreur lors de la suppression de l'ouvrage d'identifiant $tmp_id_ouvrage.<br />";
						}
						else {
							$nb_suppr++;
						}
					}
				}
			}
		}
	}

	if($nb_suppr>0) {
		$msg.=$nb_suppr." ouvrage(s) supprimé(s).<br />";
	}
}

//======================================

// Configuration du calendrier
$style_specifique[] = "lib/DHTMLcalendar/calendarstyle";
$javascript_specifique[] = "lib/DHTMLcalendar/calendar";
$javascript_specifique[] = "lib/DHTMLcalendar/lang/calendar-fr";
$javascript_specifique[] = "lib/DHTMLcalendar/calendar-setup";

// Tri de tableaux
$javascript_specifique[] = "lib/tablekit";
$utilisation_tablekit="ok";

$themessage  = 'Des informations ont été modifiées. Voulez-vous vraiment quitter sans enregistrer ?';

//**************** EN-TETE *****************
$titre_page = "Plugin stock - Saisie ouvrages";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************
//debug_var();

echo "<p class=bold><a href=\"../../accueil.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a> | 
<a href=\"index.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Retour à l'index</a>";

//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
if(!isset($mode)) {
	echo "</p>

<h2>Plugin stock</h2>";

	// Afficher un tableau de la liste des ouvrages avec des colonnes titre, auteur, nombre d'exemplaires, nombre d'exemplaires prêtés, nombre d'exemplaires perdus.
	// A FAIRE par la suite dans le tableau: des liens vers le prêt, la réservation

	$sql="SELECT * FROM plugin_stock_ouvrages;";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		echo "
<form action='".$_SERVER['PHP_SELF']."' method='post'>
	<fieldset class='fieldset_opacite50'>
		".add_token_field()."
		<table class='boireaus boireaus_alt sortable resizable'>
			<thead>
				<tr>
					<th class='text'>Titre</th>
					<th class='text'>Auteur</th>
					<th class='text'>Code</th>
					<th class='number'>Exemplaires</th>
					<th class='nosort'>Emprunts en cours</th>
					<th class='nosort'>Réservations</th>
					<th class='nosort'>Supprimer</th>
				</tr>
			</thead>
			<tbody>";

		while($lig=mysqli_fetch_object($res)){
			$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$lig->id."' AND statut!='perdu' AND date_de_retrait>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb_exemplaires=mysqli_num_rows($res2);

			echo "
				<tr>
					<td><a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."&mode=saisir_ouvrage' title=\"Éditer l'ouvrage\">".$lig->titre."</a></td>
					<td>".$lig->auteur."</td>
					<td>".$lig->code."</td>
					<td><span style='display:none;'>".$nb_exemplaires."</span><a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."&mode=saisir_exemplaire' onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb_exemplaires."</a></td>
					<td>";

			//==================================
			// Afficher le nombre d'exemplaires actuellement empruntés et un lien vers l'emprunt.
			$sql="select 1=1 from plugin_stock_emprunts where id_ouvrage='".$lig->id."' AND 
						date_pret<='".$date_courante."' AND 
						date_retour>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb=mysqli_num_rows($res2);

			if($nb_exemplaires==0) {
				// En principe 0
				echo $nb;
			}
			else {
				echo "<span style='display:none;'>".$nb."</span>
			<a href='preter.php?id_ouvrage=".$lig->id."' title=\"Consulter les emprunts ou effectuer un prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb." <img src='../../images/edit16.png' class='icone16' /></a>";
			}
			echo "
					</td>
					<td>";
			//==================================
			// Afficher les réservations à venir avec le nombre d'exemplaires.

			if($nb_exemplaires>0) {
				echo "
						<div style='float:right;width:16px'>
							<a href='reserver.php?id_ouvrage=".$lig->id."' title=\"Réserver des exemplaires.\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/edit16.png' class='icone16' /></a>
						</div>";
			}

			$sql="select * from plugin_stock_reservations where id_ouvrage='".$lig->id."' AND 
						date_previsionnelle_pret<='".$date_courante."' AND 
						date_previsionnelle_retour>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			//$nb=mysqli_num_rows($res2);

			while($lig2=mysqli_fetch_object($res2)) {
				echo "
						<a href='reserver.php?id_ouvrage=".$lig->id."&id_reservation=".$lig2->id."' title=\"Consulter la réservation n°".$lig2->id.".\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig2->nb_exemplaires." (".$lig2->date_previsionnelle_pret."-&gt;".$lig2->date_previsionnelle_retour.")</a><br />";
			}

			//==================================
			echo "
					</td>
					<td>
						<input type='checkbox' name='suppr_ouvrage[]' value='".$lig->id."' />
					</td>
				</tr>";
		}

		echo "
			</tbody>
		</table>
		<p><input type='submit' value=\"Valider les suppressions d'ouvrages cochées\" /></p>
	</fieldset>
</form>";
	}

	// Afficher un lien pour ajouter un ouvrage
	echo "<p style='margin-top:1em'><a href='".$_SERVER['PHP_SELF']."?mode=saisir_ouvrage' onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/add.png' class='icone16' /> Ajouter un ouvrage</a></p>";

}
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
elseif($mode=='saisir_ouvrage') {

	// A FAIRE : Ajouter un formulaire de choix d'ouvrage

	echo "
 | <a href=\"".$_SERVER['PHP_SELF']."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Liste des ouvrages</a>
</p>

<h2>Plugin stock&nbsp;: Saisie/modification d'un ouvrage</h2>";

	// Formulaire avec les champ Titre, Auteur, Code, nombre d'exemplaires

	$sql="SELECT * FROM plugin_stock_ouvrages;";
	$res=mysqli_query($mysqli, $sql);
	echo "Il y a ".mysqli_num_rows($res)." ouvrages.";

	echo "<p><a href='".$_SERVER['PHP_SELF']."' onclick=\"return confirm_abandon (this, change, '$themessage')\">Gérer les ouvrages</a>";
	if(isset($id_ouvrage)) {
		echo "
		 - <a href='".$_SERVER['PHP_SELF']."?mode=saisir_ouvrage' onclick=\"return confirm_abandon (this, change, '$themessage')\">Ajouter un autre ouvrage</a>";
	}
	echo "</p>";

	echo "
	<form action='".$_SERVER['PHP_SELF']."' method='post'>
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
			$lig=mysqli_fetch_object($res);

			$titre=$lig->titre;
			$auteur=$lig->auteur;
			$code=$lig->code;

			echo "
			<input type='hidden' name='id_ouvrage' value='$id_ouvrage' />";

			// Récupérer avec une 2è requête sql le nombre d'exemplaires de l'ouvrage pour renseigner $nb_exemplaires
			// La valeur $nb_exemplaires est obtenue en comptant le nombre d'exemplaires avec id_ouvrage='$id_ouvrage' dans plugin_stock_exemplaires

			$sql="select 1=1 from plugin_stock_exemplaires where id_ouvrage='".$lig->id."' AND statut!='perdu' AND date_de_retrait>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb_exemplaires=mysqli_num_rows($res2);

		}
		else {
			echo "<p style='color:red'>Ouvrage n°".$id_ouvrage." inconnu.</p>";
			unset($id_ouvrage);

			// Valeurs par défaut
			$titre='';
			$auteur='';
			$code='';
			$nb_exemplaires=0;
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
			<table border='0'>
				<tr>
					<th>Titre&nbsp;: </th>
					<td><input type='text' name='titre' id='titre' value=\"$titre\" onchange='changement()' /></td>
				</tr>
				<tr>
					<th>Auteur&nbsp;: </th>
					<td><input type='text' name='auteur' value=\"$auteur\" onchange='changement()' /></td>
				</tr>
				<tr>
					<th>Code&nbsp;: </th>
					<td><input type='text' name='code' value=\"$code\" onchange='changement()' /></td>
				</tr>
				<tr>
					<th>Nombre d'exemplaires&nbsp;: </th>
					<td>
						<!--
							<input type='text' name='nb_exemplaires' value=\"$nb_exemplaires\" onchange='changement()' />
						-->
						$nb_exemplaires ".((($titre!='')&&(isset($id_ouvrage))) ? "
						<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Ajouter/supprimer des ouvrages.\">
							<img src='../../images/icons/plus_moins.png' class='icone16' />
						</a>" : "")."
					</td>
				</tr>";

	// S'il n'y a pas déjà des exemplaires saisis, permettre de saisir un nombre d'exemplaires.
	// Sinon mettre un rappel du nombre d'exemplaires... permettre d'en ajouter? Il pourront être renumérotés dans la saisie/gestion des exemplaires

	// Il n'est pas possible de réduire le nombre d'exemplaires sans indiquer ceux supprimés
	// ni d'en ajouter sans indiquer une date de mise en service

	if(isset($id_ouvrage)) {
		$valeur_submit='Mettre à jour l\'ouvrage existant';
	}
	else {
		$valeur_submit='Ajouter cet ouvrage';
	}

	echo "
			</table>
			<p><input type='submit' value=\"".$valeur_submit."\" /></p>
		</fieldset>
	</form>
	<script type='text/javascript'>
		document.getElementById('titre').focus();
	</script>";


}
//++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
elseif($mode=='saisir_exemplaire') {

	// A FAIRE : Ajouter un formulaire de choix d'ouvrage

	echo "
 | <a href=\"".$_SERVER['PHP_SELF']."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Liste des ouvrages</a>
</p>";

	echo "<h2>Exemplaires de ".plugin_stock_afficher_ouvrage($id_ouvrage);
	echo " <a href='preter.php?id_ouvrage=".$id_ouvrage."' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Prêter des exemplaires de l'ouvrage à des élèves.\"><img src='../../images/icons/edit16_ele.png' class='icone16' /></a>";
	echo "</h2>";
	// Tableau de la liste des exemplaires, avec des champs de saisie de l'état (champ select) (Très bon, Bon, Mauvais) et du statut (champ checkbox) (perdu ou non)
	// Plus une case checkbox pour supprimer un exemplaire (lors de la validation de cette suppression de plugin_stock_exemplaires, il ne faudra pas oublier de supprimer les emprunts correspondant à l'exemplaire dans plugin_stock_emprunts)

	$sql="SELECT 1=1 FROM plugin_stock_exemplaires WHERE id_ouvrage='".$id_ouvrage."' AND date_de_retrait>'".$date_courante." 23:59:59';";
	$test=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($test)>0) {
		if((!isset($afficher_tous_les_exemplaires))||($afficher_tous_les_exemplaires!='y')) {
			echo "
	<div style='float:right; width:8em; text-align:center; margin-left:0.5em;padding:0.2em;' class='fieldset_opacite50'>
		<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire&afficher_tous_les_exemplaires=y' onclick=\"return confirm_abandon (this, change, '$themessage')\">
			Afficher tous les exemplaires, même ceux qui ont été retirés du prêt.
		</a>
	</div>";
		}
		else {
			echo "
	<div style='float:right; width:8em; text-align:center; margin-left:0.5em;padding:0.2em;' class='fieldset_opacite50'>
		<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire&afficher_tous_les_exemplaires=n' onclick=\"return confirm_abandon (this, change, '$themessage')\">
			Afficher seulement les exemplaires non retirés du prêt.
		</a>
	</div>";
		}
	}

	echo "
	<form action='".$_SERVER['PHP_SELF']."' method='post'>
		<fieldset class='fieldset_opacite50'>
			".add_token_field()."
			<input type='hidden' name='id_ouvrage' value='".$id_ouvrage."' />
			<input type='hidden' name='mode' value='saisir_exemplaire' />
			<input type='hidden' name='valider_saisie_exemplaires' value='y' />";

	if(isset($afficher_tous_les_exemplaires)) {
		echo "
			<input type='hidden' name='afficher_tous_les_exemplaires' value='".$afficher_tous_les_exemplaires."' />";
	}

	// Exemplaires existants
	$cpt_suppr=0;
	$sql="SELECT * FROM plugin_stock_exemplaires WHERE id_ouvrage='".$id_ouvrage."'";
	if((!isset($afficher_tous_les_exemplaires))||($afficher_tous_les_exemplaires!='y')) {
		$sql.=" AND statut!='perdu' AND date_de_retrait>'".$date_courante."'";
	}
	$sql.=";";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		// Chercher les emprunts en court
		$tab_emprunts=array();
		$sql="select * from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
					date_pret<='".$date_courante."' AND 
					date_retour>='".$date_courante."';";
		$res2=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res2)>0) {
			while($lig2=mysqli_fetch_assoc($res2)) {
				$tab_emprunts[$lig2['id']]=$lig2;
			}
		}

		echo "
			<p class='bold'>Exemplaires existants de l'ouvrage&nbsp;:</p>
			<table class='boireaus boireaus_alt sortable resizable'>
				<thead>
					<tr>
						<th class='text'>Numéro</th>
						<th class='text'>État</th>
						<th class='text'>Statut</th>
						<th class='nosort'>Emprunts en cours</th>
						<th class='number'>Date de mise en service</th>
						<th class='nosort' title=\"L'exemplaire sera noté comme retiré/supprimé à la date du ".formate_date($date_courante)."\">
							Supprimer<br />
							<a href=\"javascript:CocheSuppr(true)\"><img src='../../images/enabled.png' class='icone16' alt='Tout cocher' /></a> / 
							<a href=\"javascript:CocheSuppr(false)\"><img src='../../images/disabled.png' class='icone16' alt='Tout décocher' /></a>
						</th>
					</tr>
				</thead>
				<tbody>";
		while($lig=mysqli_fetch_object($res)) {
			$title_tr="";
			$style_tr='';
			if($lig->date_de_retrait<=$date_courante) {
				$title_tr=" title=\"Exemplaire retiré du prêt.\"";
				$style_tr=" style='color:red'";
			}
			echo "
					<tr".$style_tr.$title_tr.">
						<td>".$lig->numero."</td>
						<td><input type='text' name='etat[".$lig->id."]' value=\"".$lig->etat."\" onchange='changement()' /></td>
						<td>
							<!--
							<input type='text' name='statut[".$lig->id."]' value=\"".$lig->statut."\" onchange='changement()' />
							-->
							<select name='statut[".$lig->id."]' onchange='changement()'>";
			foreach($tab_statuts_exemplaires_ouvrages as $key => $statut) {
				echo "
								<option value='".$statut."'".($lig->statut==$statut ? " selected" : "").">".$statut."</option>";
			}
			echo "
							</select>
						</td>
						<td>".(array_key_exists($lig->id, $tab_emprunts) ? 
							plugin_stock_get_eleve($lig->id_eleve).
							" (".formate_date($lig->date_pret)."-&gt;
							<span title=\"Date prévisionnelle de retour.\">".formate_date($lig->date_previsionnelle_retour)."</span>)" : 
							"")."
						</td>
						<td>
							<input type='text' name='date_mise_en_service[".$lig->id."]' id='date_mise_en_service_".$lig->id."' size='10' value=\"".formate_date($lig->date_mise_en_service)."\" onKeyDown=\"clavier_date(this.id,event);\" onchange='changement()' AutoComplete=\"off\" />
							".img_calendrier_js("date_mise_en_service_".$lig->id, "img_bouton_ajout_date_mise_en_service_".$lig->id)."
						</td>
						<td><input type='checkbox' name='suppr[]' id='suppr_$cpt_suppr' value='".$lig->id."' onchange='changement()' ".($lig->date_de_retrait<=$date_courante ? "checked" : "")."/>".($lig->date_de_retrait<=$date_courante ? " <span title=\"Exemplaire retiré du prêt le ".formate_date($lig->date_de_retrait)."\">".formate_date($lig->date_de_retrait)."</span>" : "")."</td>
					</tr>";
					$cpt_suppr++;

			// Attention à ne pas supprimer sans y prendre garde un livre non rendu?
			// Demander confirmation?
			// Ou juste pouvoir afficher tous les livres en cours de prêt même ceux déclarés supprimés?
		}
		echo "
				</tbody>
			</table>";
	}

	// Date de mise en service, date de retrait.

	// Formulaire pour ajouter N exemplaires
	// Pouvoir supprimer des exemplaires
	// N'afficher que les exemplaires non supprimés, mais permettre d'accéder à ceux supprimés via un lien

	echo "
			<p class='bold' style='margin-top:2em;'>Ajouter des exemplaires de l'ouvrage&nbsp;:</p>
			<table border='0'>
				<tr>
					<th>Nombre d'exemplaires à ajouter</th>
					<td><input type='text' name='ajout_nb_exemplaires' id='ajout_nb_exemplaires' value=\"0\" onkeydown=\"clavier_2(this.id,event,0,2000); changement();\" autocomplete=\"off\" /></td>
				</tr>
				<tr>
					<th>État des exemplaires à ajouter</th>
					<td><input type='text' name='ajout_etat' value=\"".(isset($ajout_etat) ? $ajout_etat : 'neuf')."\" /></td>
				</tr>
				<tr>
					<th>Date de mise en service</th>
					<td>
						<input type='text' name='ajout_date_mise_en_service' id='ajout_date_mise_en_service' size='10' value=\"".(isset($ajout_date_mise_en_service) ? $ajout_date_mise_en_service : strftime('%d/%m/%Y'))."\" onKeyDown=\"clavier_date(this.id,event);\" AutoComplete=\"off\" />
						".img_calendrier_js("ajout_date_mise_en_service", "img_bouton_ajout_date_mise_en_service")."
					</td>
				</tr>
			</table>

			<p><input type='submit' value='Valider' /></p>

			<div id='fixe'><input type='submit' value='Valider' /></div>

		</fieldset>
	</form>

	<script type='text/javascript'>
		function CocheSuppr(mode) {
			for(i=0;i<$cpt_suppr;i++) {
				if(document.getElementById('suppr_'+i)) {
					document.getElementById('suppr_'+i).checked=mode;
				}
			}
		}
	</script>";



}
else {
	echo "<p style='color:red'>Mode non implémenté.</p>";
}

include("../../lib/footer.inc.php");
?>
