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

$mes_id_groupes=array();
if($_SESSION['statut']=='professeur') {
	//$mes_groupes=get_groups_for_prof($_SESSION['login'], NULL, array('classes'));
	$sql = "SELECT jgp.id_groupe, jgm.id_matiere, jgc.id_classe
				FROM j_groupes_professeurs jgp, j_groupes_matieres jgm, j_groupes_classes jgc, classes c
				WHERE (" .
				"login = '" . $_SESSION['login'] . "'
				AND jgp.id_groupe=jgm.id_groupe
				AND jgp.id_groupe=jgc.id_groupe
				AND jgc.id_classe=c.id) " .
				"GROUP BY jgp.id_groupe ".
				"ORDER BY jgm.id_matiere, c.classe" ;
	$res=mysqli_query($mysqli, $sql);
	while($lig=mysqli_fetch_object($res)) {
		$mes_id_groupes[]=$lig->id_groupe;
	}
}

$msg='';

$id_classe=isset($_POST['id_classe']) ? $_POST['id_classe'] : (isset($_GET['id_classe']) ? $_GET['id_classe'] : NULL);
$id_groupe=isset($_POST['id_groupe']) ? $_POST['id_groupe'] : (isset($_GET['id_groupe']) ? $_GET['id_groupe'] : NULL);
$id_ouvrage=isset($_POST['id_ouvrage']) ? $_POST['id_ouvrage'] : (isset($_GET['id_ouvrage']) ? $_GET['id_ouvrage'] : NULL);

$login_reservation=isset($_POST['login_reservation']) ? $_POST['login_reservation'] : (isset($_GET['login_reservation']) ? $_GET['login_reservation'] : $_SESSION['login']);
if(!$plugin_stock_is_administrateur) {
	$login_reservation=$_SESSION['login'];
	$statut_login_reservation=$_SESSION['statut'];
}
else {
	$statut_login_reservation=get_valeur_champ("utilisateurs", "login='".$login_reservation."'", "statut");
}

if(isset($id_ouvrage)) {
	if((!preg_match('/^[0-9]{1,}$/', $id_ouvrage))||($id_ouvrage<1)) {
		$msg="L 'identifiant d'ouvrage $id_ouvrage est invalide.<br />";
		unset($id_ouvrage);
	}
	else {
		$sql="SELECT * FROM plugin_stock_ouvrages WHERE id='".$id_ouvrage."';";
		$res=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res)==0) {
			$msg="L'ouvrage d'identifiant $id_ouvrage n'existe pas.<br />";
			unset($id_ouvrage);
		}
		else {
			$current_ouvrage=mysqli_fetch_assoc($res);
		}
	}
}

if(isset($id_classe)) {
	if((!preg_match('/^[0-9]{1,}$/', $id_classe))||($id_classe<1)) {
		$msg="L 'identifiant de classe $id_classe est invalide.<br />";
		unset($id_classe);
	}
	else {
		$nom_classe=get_nom_classe($id_classe);
		if($nom_classe=='') {
			$msg="L 'identifiant de classe $id_classe est invalide.<br />";
			unset($id_classe);
		}
	}
}

if(isset($id_groupe)) {
	if((!preg_match('/^[0-9]{1,}$/', $id_groupe))||($id_groupe<1)) {
		$msg="L 'identifiant d'enseignement $id_groupe est invalide.<br />";
		unset($id_groupe);
	}
	else {
		$nom_groupe=get_info_grp($id_groupe);
		if($nom_groupe=='') {
			$msg="L 'identifiant d'enseignement $id_groupe est invalide.<br />";
			unset($id_groupe);
		}
	}
}

$mysql_date_courante=strftime("%Y-%m-%d %H:%M:%S");
$mysql_date_courante_debut_journee=strftime("%Y-%m-%d")." 00:00:00";

$date_debut=isset($_POST['date_debut']) ? $_POST['date_debut'] : (isset($_GET['date_debut']) ? $_GET['date_debut'] : strftime("%d/%m/%Y"));
$date_fin=isset($_POST['date_fin']) ? $_POST['date_fin'] : (isset($_GET['date_fin']) ? $_GET['date_fin'] : strftime("%d/%m/%Y", time()+30*24*3600));

if(!check_date($date_debut)) {
	$msg.="La date de début de réservation proposée ($date_debut) est invalide.<br />";
	$date_debut=strftime("%d/%m/%Y");
	$msg.="Modification pour $date_debut.<br />";
}

if(!check_date($date_fin)) {
	$msg.="La date de fin de réservation proposée ($date_debut) est invalide.<br />";
	$date_fin=strftime("%d/%m/%Y", time()+30*24*3600);
	$msg.="Modification pour $date_fin.<br />";
}

$mysql_date_debut=get_mysql_date_from_slash_date($date_debut);
//if($date_debut<strftime("%d/%m/%Y")) {
//if($mysql_date_debut<$mysql_date_courante) {
if($mysql_date_debut<$mysql_date_courante_debut_journee) {
	$msg.="La date de début de réservation ($date_debut)";
	$msg.="($mysql_date_debut)";
	$msg.=" est antérieure à la date courante (".strftime("%d/%m/%Y").")";
	$msg.="($mysql_date_courante)";
	$msg.=".<br />Modification de la date de début pour ".strftime("%d/%m/%Y").".<br />";
	$date_debut=strftime("%d/%m/%Y");
	$mysql_date_debut=get_mysql_date_from_slash_date($date_debut);
}

$mysql_date_fin=get_mysql_date_from_slash_date($date_fin);
//if($date_fin<strftime("%d/%m/%Y")) {
//if($mysql_date_fin<$mysql_date_courante) {
if($mysql_date_fin<$mysql_date_courante_debut_journee) {
	$msg.="La date de fin de réservation ($date_fin)";
	$msg.="($mysql_date_fin)";
	$msg.=" est antérieure à la date courante (".strftime("%d/%m/%Y").")";
	$msg.="($mysql_date_courante)";
	$msg.=".<br />Modification de la date de fin pour ".strftime("%d/%m/%Y").".<br />";
	$date_fin=strftime("%d/%m/%Y");
	$mysql_date_fin=get_mysql_date_from_slash_date($date_fin);
}

$date_debut_annee_scolaire=strftime('%d/%m/%Y', getSettingValue('begin_bookings'));
$date_fin_annee_scolaire=strftime('%d/%m/%Y', getSettingValue('end_bookings'));
$mysql_date_debut_annee_scolaire=strftime('%Y-%m-%d %H:%M:%S', getSettingValue('begin_bookings'));
$mysql_date_fin_annee_scolaire=strftime('%Y-%m-%d %H:%M:%S', getSettingValue('end_bookings'));
//if($date_debut<$date_debut_annee_scolaire) {
if($mysql_date_debut<$mysql_date_debut_annee_scolaire) {
	$msg.="La date de début de réservation ($date_debut)";
	$msg.="($mysql_date_debut)";
	$msg.=" est antérieure à la date de début d'année (".$date_debut_annee_scolaire.")";
	$msg.="($mysql_date_debut_annee_scolaire)";
	$msg.=".<br />Modification de la date de début pour ".strftime("%d/%m/%Y").".<br />";
	$date_debut=strftime('%d/%m/%Y');
	$mysql_date_debut=get_mysql_date_from_slash_date($date_debut);
}

//if($date_debut>$date_fin_annee_scolaire) {
if($mysql_date_debut>$mysql_date_fin_annee_scolaire) {
	$msg.="La date de début de réservation ($date_debut)";
	$msg.="($mysql_date_debut)";
	$msg.=" est postérieure à la date de fin d'année (".$date_fin_annee_scolaire.")";
	$msg.="($mysql_date_fin_annee_scolaire)";
	$msg.=".<br />Modification de la date de début pour ".strftime("%d/%m/%Y").".<br />";
	$date_debut=strftime('%d/%m/%Y');
	$mysql_date_debut=get_mysql_date_from_slash_date($date_debut);
}

//if($date_fin<$date_debut_annee_scolaire) {
if($mysql_date_fin<$mysql_date_debut_annee_scolaire) {
	$msg.="La date de fin de réservation ($date_fin)";
	$msg.="($mysql_date_fin)";
	$msg.=" est antérieure à la date de début d'année (".$date_debut_annee_scolaire.")";
	$msg.="($mysql_date_debut_annee_scolaire)";
	$msg.=".<br />Modification de la date de fin pour ".strftime("%d/%m/%Y").".<br />";
	$date_fin=strftime('%d/%m/%Y');
	$mysql_date_fin=get_mysql_date_from_slash_date($date_fin);
}

//if($date_fin>$date_fin_annee_scolaire) {
if($mysql_date_fin>$mysql_date_fin_annee_scolaire) {
	$msg.="La date de fin de réservation ($date_fin)";
	$msg.="($mysql_date_fin)";
	$msg.=" est postérieure à la date de fin d'année (".$date_fin_annee_scolaire.")";
	$msg.="($mysql_date_fin_annee_scolaire)";
	$msg.=".<br />Modification de la date de fin pour ".strftime("%d/%m/%Y").".<br />";
	$date_fin=strftime('%d/%m/%Y');
	$mysql_date_fin=get_mysql_date_from_slash_date($date_fin);
}

//if($date_debut>$date_fin) {
if($mysql_date_debut>$mysql_date_fin) {
	$msg.="La date de début de réservation ($date_debut) est postérieure à la date de fin ($date_fin).<br />Interversion des dates.<br />";
	$tmp_date_debut=$date_debut;
	$date_debut=$date_fin;
	$date_fin=$tmp_date_debut;
}

$mysql_date_debut=get_mysql_date_from_slash_date($date_debut);
$mysql_date_fin=get_mysql_date_from_slash_date($date_fin);

//$mysql_date_courante=strftime("%Y-%m-%d %H:%M:%S");
$annee_scolaire=getSettingValue('gepiYear');

$id_reservation=isset($_POST['id_reservation']) ? $_POST['id_reservation'] : (isset($_GET['id_reservation']) ? $_GET['id_reservation'] : NULL);

if(isset($id_reservation)) {
	if((!preg_match('/^[0-9]{1,}$/', $id_reservation))||($id_reservation<1)) {
		$msg.="Identifiant de réservation ($id_reservation) invalide.<br />";
		unset($id_reservation);
	}

	if(!$plugin_stock_is_administrateur) {
		//$sql="SELECT 1=1 FROM plugin_stock_reservations WHERE id='".$id_reservation."' AND login_preteur='".$_SESSION['login']."';";
		$sql="SELECT 1=1 FROM plugin_stock_reservations WHERE id='".$id_reservation."' AND login_preteur='".$login_reservation."';";
		plugin_stock_echo_debug("$sql<br />");
		$test=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($test)==0) {
			$msg.="Vous n'êtes pas le propriétaire de la réservation d'identifiant $id_reservation.<br />";
			unset($id_reservation);
		}
	}
}

$nb_exemplaires=isset($_POST['nb_exemplaires']) ? $_POST['nb_exemplaires'] : (isset($_GET['nb_exemplaires']) ? $_GET['nb_exemplaires'] : NULL);
if(isset($nb_exemplaires)) {
	if((!preg_match('/^[0-9]{1,}$/', $nb_exemplaires))||($nb_exemplaires<1)) {
		$msg.="Nombre d'exemplaires ($nb_exemplaires) invalide.<br />";
		unset($nb_exemplaires);
	}
}

if((isset($_POST['valider_reservation']))&&(isset($date_debut))&&(isset($date_fin))&&(isset($id_ouvrage))&&(isset($nb_exemplaires))&&
((isset($id_classe))||(isset($id_groupe)))) {
	check_token();

	// Vérifier le nombre d'exemplaires dispo

	$sql="SELECT DISTINCT psex.id AS id_exemplaire FROM plugin_stock_exemplaires psex 
			WHERE psex.id_ouvrage='".$id_ouvrage."' AND 
				psex.statut!='perdu' AND 
				psex.date_de_retrait>='".$mysql_date_fin."' AND 
				psex.id NOT IN (SELECT id_exemplaire FROM plugin_stock_emprunts 
									WHERE id_ouvrage='".$id_ouvrage."' AND 
										(
											(date_pret<='$mysql_date_debut' AND 
											date_previsionnelle_retour>='$mysql_date_debut')
											 OR 
											(date_pret<='$mysql_date_fin' AND 
											date_previsionnelle_retour>='$mysql_date_fin')
											 OR 
											(date_pret>='".$mysql_date_debut."' AND 
											date_previsionnelle_retour<='".$mysql_date_fin."')
										)
							);";
	plugin_stock_echo_debug("$sql<br />");
	$res=mysqli_query($mysqli, $sql);
	$nb_exemplaires_non_pretes=mysqli_num_rows($res);

	$sql="SELECT nb_exemplaires FROM plugin_stock_reservations 
						WHERE id_ouvrage='".$id_ouvrage."' AND 
							(
								(date_previsionnelle_pret<='$mysql_date_debut' AND 
								date_previsionnelle_retour>='$mysql_date_debut')
								 OR 
								(date_previsionnelle_pret<='$mysql_date_fin' AND 
								date_previsionnelle_retour>='$mysql_date_fin')
								 OR 
								(date_previsionnelle_pret>='".$mysql_date_debut."' AND 
								date_previsionnelle_retour<='".$mysql_date_fin."')
							);";
	plugin_stock_echo_debug("$sql<br />");
	$res=mysqli_query($mysqli, $sql);
	$nb_exemplaires_reserves=0;
	if(mysqli_num_rows($res)>0) {
		while($lig=mysqli_fetch_object($res)) {
			$nb_exemplaires_reserves+=$lig->nb_exemplaires;
		}
	}
	$nb_exemplaires_dispo=$nb_exemplaires_non_pretes-$nb_exemplaires_reserves;

	if($nb_exemplaires>$nb_exemplaires_dispo) {
		$msg.="Le nombre d'exemplaires demandés ".$nb_exemplaires." est supérieur au nombre d'exemplaires disponibles (".$nb_exemplaires_dispo.") dans l'intervalle de dates choisie.<br />";
	}
	else {

		if(isset($id_reservation)) {
			/*
			$sql="UPDATE plugin_stock_reservations SET id_ouvrage='".$id_ouvrage."', 
										date_previsionnelle_pret='".$mysql_date_debut."', 
										date_previsionnelle_retour='".$mysql_date_fin."', 
										nb_exemplaires='".$nb_exemplaires."' 
							WHERE id='".$id_reservation."' AND 
								login_preteur='".$_SESSION['login']."';";
			*/
			$sql="UPDATE plugin_stock_reservations SET id_ouvrage='".$id_ouvrage."', 
										date_previsionnelle_pret='".$mysql_date_debut."', 
										date_previsionnelle_retour='".$mysql_date_fin."', 
										nb_exemplaires='".$nb_exemplaires."' 
							WHERE id='".$id_reservation."' AND 
								login_preteur='".$login_reservation."';";
			plugin_stock_echo_debug("$sql<br />");
			$update=mysqli_query($mysqli, $sql);
			if(!$update) {
				$msg.="Erreur lors de la mise à jour de la réservation n°$id_reservation.<br />";
			}
			else {
				$msg.="Réservation n°$id_reservation mise à jour.<br />";
			}
		}
		else {
			/*
			$sql="INSERT INTO plugin_stock_reservations 
					SET id_ouvrage='".$id_ouvrage."', 
						date_previsionnelle_pret='".$mysql_date_debut."', 
						date_previsionnelle_retour='".$mysql_date_fin."', 
						nb_exemplaires='".$nb_exemplaires."', 
						annee_scolaire='".$annee_scolaire."', 
						login_preteur='".$_SESSION['login']."', ";
			*/
			$sql="INSERT INTO plugin_stock_reservations 
					SET id_ouvrage='".$id_ouvrage."', 
						date_previsionnelle_pret='".$mysql_date_debut."', 
						date_previsionnelle_retour='".$mysql_date_fin."', 
						nb_exemplaires='".$nb_exemplaires."', 
						annee_scolaire='".$annee_scolaire."', 
						login_preteur='".$login_reservation."', ";
			if(isset($id_classe)) {
				$sql.="
						id_classe='".$id_classe."'";
			}
			elseif(isset($id_groupe)) {
				$sql.="
						id_groupe='".$id_groupe."'";
			}
			$sql.=";";
			plugin_stock_echo_debug("$sql<br />");
			$insert=mysqli_query($mysqli, $sql);
			if(!$insert) {
				$msg.="Erreur lors de l'enregistrement de la réservation.<br />";
			}
			else {
				$msg.="Réservation enregistrée.<br />";
				$id_reservation=mysqli_insert_id($mysqli);
			}
		}
	}
}

if((isset($_GET['supprimer_reservation']))&&(isset($id_reservation))) {
	check_token();

	$sql="SELECT * FROM plugin_stock_reservations WHERE id='".$id_reservation."';";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		$lig=mysqli_fetch_object($res);
		//if(($lig->login_preteur==$_SESSION['login'])||($plugin_stock_is_administrateur)) {
		if(($lig->login_preteur==$login_reservation)||($plugin_stock_is_administrateur)) {
			$sql="DELETE FROM plugin_stock_reservations WHERE id='".$id_reservation."';";
			$del=mysqli_query($mysqli, $sql);
			if($del) {
				$msg.="Réservation n°$id_reservation supprimée.<br />";
			}
			else {
				$msg.="Erreur lors de la suppression de la réservation n°$id_reservation.<br />";
			}
		}
		else {
			$msg.="Vous ne pouvez pas supprimer la réservation n°$id_reservation.<br />";
		}
	}

	unset($id_reservation);
}

if(isset($id_reservation)) {
	//$sql="SELECT * FROM plugin_stock_reservations WHERE id='".$id_reservation."' AND login_preteur='".$_SESSION['login']."';";
	$sql="SELECT * FROM plugin_stock_reservations WHERE id='".$id_reservation."';";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		$lig=mysqli_fetch_object($res);

		$date_debut=formate_date($lig->date_previsionnelle_pret);
		$date_fin=formate_date($lig->date_previsionnelle_retour);

		$mysql_date_debut=$lig->date_previsionnelle_pret;
		$mysql_date_fin=$lig->date_previsionnelle_retour;

		$id_ouvrage=$lig->id_ouvrage;
		$nb_exemplaires=$lig->nb_exemplaires;

		$id_classe=$lig->id_classe;
		if($id_classe==0) {
			unset($id_classe);
		}
		else {
			$nom_classe=get_nom_classe($id_classe);
		}

		$id_groupe=$lig->id_groupe;
		if($id_groupe==0) {
			unset($id_groupe);
		}
		else {
			$nom_groupe=get_info_grp($id_groupe);
		}
	}
}

// Configuration du calendrier
$style_specifique[] = "lib/DHTMLcalendar/calendarstyle";
$javascript_specifique[] = "lib/DHTMLcalendar/calendar";
$javascript_specifique[] = "lib/DHTMLcalendar/lang/calendar-fr";
$javascript_specifique[] = "lib/DHTMLcalendar/calendar-setup";

//**************** EN-TETE *****************
$titre_page = "Plugin stock - Réserver";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************
//debug_var();

echo "<p class='bold'>
	<a href=\"../../accueil.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/back.png' alt='Retour' class='back_link'/> Retour à l'accueil</a>
	 | <a href=\"index.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Retour à l'index du plugin</a>";

	if(isset($id_ouvrage)) {
		echo "
	 | <a href=\"".$_SERVER['PHP_SELF']."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Choisir un autre ouvrage</a>";

		if((isset($id_classe))||(isset($id_groupe))) {
			echo "
		 | <a href=\"".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Choisir une autre classe/enseignement</a>";
		}

		if($plugin_stock_is_administrateur) {
			echo "
			 | <a href=\"saisir_ouvrage.php?mode=saisir_ouvrage&id_ouvrage=".$id_ouvrage."\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Éditer/modifier l'ouvrage</a>";
		}
	}

	if($plugin_stock_is_administrateur) {
		echo "
		 | <a href=\"saisir_ouvrage.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Saisir des ouvrages/exemplaires</a>";
	}

	echo "
	 | <a href=\"preter.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Prêter des ouvrages/exemplaires</a>
	 | <a href=\"historique.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Historique</a>";

echo "</p>";

?>

<h2>Plugin stock</h2>
<p>La présente page est destinée à permettre de réserver des ouvrages/exemplaires pour une certaine période.<br />
A charge aux utilisateurs après ces saisies de s'entendre entre eux pour modifier éventuellement ces réservations.<br />
Outre la personne qui a fait une réservation, les administrateurs du plugin peuvent supprimer des réservations.<br />
Lors de la réalisation effective du prêt, la réservation sera supprimée.<br />
<!--
<span style='color:red'>A FAIRE&nbsp;: Permettre à un administrateur du plugin de réserver pour le compte d'un collègue.</span><br />
-->
</p>

<?php
if(!isset($id_ouvrage)) {

	$sql="SELECT * FROM plugin_stock_ouvrages;";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		echo "
		<p class='bold'>Liste des ouvrages&nbsp;:</p>
		<table class='boireaus boireaus_alt sortable resizable'>
			<thead>
				<tr>
					<th class='text'>Titre</th>
					<th class='text'>Auteur</th>
					<th class='text'>Code</th>
					<th class='number'>Exemplaires</th>
					<th class='nosort'>Emprunts en cours</th>
					<th class='nosort'>Réservations</th>
				</tr>
			</thead>
			<tbody>";

		while($lig=mysqli_fetch_object($res)){
			$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$lig->id."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb_exemplaires=mysqli_num_rows($res2);

			echo "
				<tr>
					<td>
						".($plugin_stock_is_administrateur ? "<div style='float:right; width:16px;'><a href='saisir_ouvrage.php?id_ouvrage=".$lig->id."' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Éditer l'ouvrage\"><img src='../../images/edit16.png' class='icone16' /></a></div>" : '')."
						".($nb_exemplaires>0 ? "<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."' title=\"Réserver l'ouvrage\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig->titre."</a>" : $lig->titre)."
					</td>
					<td>".$lig->auteur."</td>
					<td>".$lig->code."</td>
					<td>
						<span style='display:none;'>".$nb_exemplaires."</span>
						".($nb_exemplaires>0 ? "<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."' onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb_exemplaires."</a>" : 0)."
					</td>
					<td>";

			//==================================
			// Afficher le nombre d'exemplaires actuellement empruntés et un lien vers l'emprunt.
			$sql="select 1=1 from plugin_stock_emprunts where id_ouvrage='".$lig->id."' AND 
						date_pret<='".$mysql_date_courante."' AND 
						date_retour>='".$mysql_date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb=mysqli_num_rows($res2);

			if($nb_exemplaires==0) {
				// En principe 0
				echo $nb;
			}
			else {
				echo "<span style='display:none;'>".$nb."</span>
			<a href='preter.php?id_ouvrage=".$lig->id."' title=\"Consulter les emprunts ou effectuer un prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb." <img src='../../images/icons/edit16_ele.png' class='icone16' /></a>";
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
						date_previsionnelle_pret<='".$mysql_date_courante."' AND 
						date_previsionnelle_retour>='".$mysql_date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			//$nb=mysqli_num_rows($res2);

			while($lig2=mysqli_fetch_object($res2)) {
				echo "
						<a href='reserver.php?id_ouvrage=".$lig->id."&id_reservation=".$lig2->id."' title=\"Consulter la réservation n°".$lig2->id.".\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig2->nb_exemplaires." (".$lig2->date_previsionnelle_pret."-&gt;".$lig2->date_previsionnelle_retour.")</a><br />";
			}

			//==================================
			echo "
					</td>
				</tr>";
		}

		echo "
			</tbody>
		</table>";
	}
	else {
		echo "<p style='color:red'>Il n'existe encore aucun ouvrage.</p>";
	}

	include("../../lib/footer.inc.php");
	die();
}

//==============================================================
//==============================================================
//==============================================================
// L'ouvrage est choisi : $current_ouvrage

if($_SESSION['login']!=$login_reservation) {
	echo "<p style='color:red; text-indent:-5.6em; margin-left:5.6em;'><em>Attention&nbsp;:</em> Réservation pour le compte de ".civ_nom_prenom($login_reservation).".<br />
	<a href='reserver.php?id_ouvrage=".$id_ouvrage."' onclick=\"return confirm_abandon (this, change, '$themessage')\">Revenir à mon propre compte.</a></p>";
}

if(($plugin_stock_is_administrateur)&&(!isset($id_classe))&&(!isset($id_groupe))) {
	$sql="SELECT DISTINCT u.login, u.nom, u.prenom, u.statut FROM plugin_stock_users psu, utilisateurs u WHERE u.login!='".$_SESSION['login']."' AND u.login=psu.login ORDER BY u.nom, u.prenom;";
	$res=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res)>0) {
		echo "
<div style='float:right; width:15em; text-align:center;padding:0.3em;' class='fieldset_opacite50' title=\"Réserver pour mon propre compte ou pour le compte d'un collègue.\">
	<form action='reserver.php' method='post'>
		<input type='hidden' name='id_ouvrage' value='$id_ouvrage' />
		<select name='login_reservation'>
			<option value='".$_SESSION['login']."'>".$_SESSION['nom']." ".$_SESSION['prenom']."</option>";
		while($lig=mysqli_fetch_object($res)) {
			if((isset($login_reservation))&&($login_reservation==$lig->login)) {
				$selected=' selected';
			}
			else {
				$selected='';
			}
			echo "
			<option value='".$lig->login."' title=\"".$lig->statut."\"".$selected.">".$lig->nom." ".$lig->prenom."</option>";
		}
		echo "
		</select>
		<p><input type='submit' value='Réserver pour ce compte' /></p>
	</form>
</div>";
	}
}

echo "<h3>".plugin_stock_afficher_ouvrage($id_ouvrage)."</h3>";

//==================================

// AFFICHER LE NOMBRE D'EXEMPLAIRES DISPO, LES RESA A VENIR

$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."' ORDER BY numero;";
$res2=mysqli_query($mysqli, $sql);
$nb_exemplaires=mysqli_num_rows($res2);
echo "<p>L'ouvrage compte ".$nb_exemplaires." exemplaire(s)";
	if($plugin_stock_is_administrateur) {
		echo " <a href='saisir_ouvrage.php?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Ajouter/supprimer des exemplaires, saisir leur état,...\"><img src='../../images/edit16.png' class='icone16' /></a>";
	}
	echo ".</p>";
if($nb_exemplaires==0) {
	echo "<p style='color:red'>Aucune réservation n'est possible.</p>";
	include("../../lib/footer.inc.php");
	die();
}
$tab_exemplaires=array();
$tab_exemplaires2=array();
while($lig2=mysqli_fetch_assoc($res2)) {
	$tab_exemplaires[]=$lig2;
	$tab_exemplaires2[$lig2['id']]=$lig2;
}

//==================================
// Afficher le nombre d'exemplaires actuellement empruntés et un lien vers l'emprunt.
$tab_exemplaires_empruntes=array();
$sql="select 1=1 from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."';";
$res2=mysqli_query($mysqli, $sql);
$nb_emprunts=mysqli_num_rows($res2);
if($nb_emprunts>0) {
	echo "<p style='margin-left:3em; text-indent:-3em;'><strong>Emprunts&nbsp;:</strong> ".$nb_emprunts." exemplaire(s) de l'ouvrage sont actuellement emprunté(s)&nbsp;:<br />";

	$sql="select *, COUNT(id_exemplaire) AS nb_exemplaires from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."' 
			GROUP BY login_preteur, DATE(date_pret), date_previsionnelle_retour, id_classe, id_groupe;";
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_object($res2)) {
		// Afficher les emprunts en cours
		echo $lig2->nb_exemplaires." exemplaire(s) (".$lig2->classe.") ".formate_date($lig2->date_pret)."-&gt;";
		if($lig2->date_retour<'9999-01-01 00:00:00') {
			echo formate_date($lig2->date_retour);
		}
		else {
			echo formate_date($lig2->date_previsionnelle_retour);
		}
		echo " <em>(".civ_nom_prenom($lig2->login_preteur).")</em>";

		if($lig2->id_groupe!=0) {
			if(($plugin_stock_is_administrateur)||(in_array($lig2->id_groupe, $mes_id_groupes))) {
				echo "
				<a href='preter.php?id_ouvrage=".$id_ouvrage."&id_groupe=".$lig2->id_groupe."' title=\"Consulter le prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">
					<img src='../../images/edit16.png' class='icone16' />
				</a>";
			}
		}
		elseif($lig2->id_classe!=0) {
			if(($plugin_stock_is_administrateur)||($_SESSION['statut']!='professeur')) {
				echo "
				<a href='preter.php?id_ouvrage=".$id_ouvrage."&id_classe=".$lig2->id_classe."' title=\"Consulter le prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">
					<img src='../../images/edit16.png' class='icone16' />
				</a>";
			}
		}

		echo "<br />";
	}

	echo "</p>";

	echo "<p>Classes concernées par ces prêts&nbsp;: ";
	$sql="select DISTINCT classe from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."' 
			ORDER BY classe;";
	$res2=mysqli_query($mysqli, $sql);
	$cpt_tmp=0;
	while($lig2=mysqli_fetch_object($res2)) {
		if($cpt_tmp>0) {
			echo ", ";
		}
		echo $lig2->classe;
		$cpt_tmp++;
	}
	echo "</p>";

	$sql="select * from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."';";
	//plugin_stock_echo_debug("$sql<br />");
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_assoc($res2)) {
		$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
	}

	echo "<p>Hors réservation éventuelle, il reste actuellement ".($nb_exemplaires-$nb_emprunts)." exemplaire(s) disponible(s) pour l'emprunt.</p>";
}

//==================================
// Afficher les réservations à venir avec le nombre d'exemplaires.
/*
echo "
			<div style='float:right;width:16px'>
				<a href='reserver.php?id_ouvrage=".$id_ouvrage."&login_reservation=".$login_reservation."' title=\"Réserver des exemplaires.\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/calendrier.gif' class='icone16' /></a>
			</div>";
*/
/*
$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
			date_previsionnelle_pret<='".$mysql_date_courante."' AND 
			date_previsionnelle_retour>='".$mysql_date_courante."';";
*/
$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
			date_previsionnelle_retour>='".$mysql_date_courante."';";
$res2=mysqli_query($mysqli, $sql);

if(mysqli_num_rows($res2)>0) {
	echo "<p style='margin-left:3em; text-indent:-3em;'><strong>Réservations&nbsp;:</strong><br />";
	while($lig2=mysqli_fetch_assoc($res2)) {
		//if(($lig2['login_preteur']==$_SESSION['login'])||($plugin_stock_is_administrateur)) {
		//echo "\$lig2['login_preteur']=".$lig2['login_preteur']." et \$login_reservation=$login_reservation ";
		if(($lig2['login_preteur']==$login_reservation)||($plugin_stock_is_administrateur)) {
			// $plugin_stock_is_administrateur est calculé sur $_SESSION['login']
			// Dans le cas d'une réservation pour un autre compte, on garde le droit $plugin_stock_is_administrateur
			echo "
			<a href='reserver.php?id_ouvrage=".$id_ouvrage."&id_reservation=".$lig2['id']."&login_reservation=".$login_reservation."' title=\"Consulter/modifier la réservation n°".$lig2['id'].".\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig2['nb_exemplaires']." exemplaire(s) réservé(s) (".formate_date($lig2['date_previsionnelle_pret'])."-&gt;".formate_date($lig2['date_previsionnelle_retour']).") par ".civ_nom_prenom($lig2['login_preteur'])." pour ".($lig2['id_classe']!=0 ? get_nom_classe($lig2['id_classe']) : get_info_grp($lig2['id_groupe']))."</a>";

			// A FAIRE : Pouvoir supprimer la réservation si on est administrateur ou si on est l'auteur de la réservation
			echo "
			<a href='reserver.php?id_ouvrage=".$id_ouvrage."&id_reservation=".$lig2['id']."&supprimer_reservation=y&login_reservation=".$login_reservation.add_token_in_url()."' title=\"Supprimer la réservation n°".$lig2['id'].".\" onclick=\"return confirm('Étes-vous sûr de vouloir supprimer cette réservation ?')\">
				<img src='../../images/delete16.png' class='icone16' />
			</a>";

			echo "<br />";
		}
		else {
			echo "
			".$lig2['nb_exemplaires']." exemplaire(s) réservé(s) (".formate_date($lig2['date_previsionnelle_pret'])."-&gt;".formate_date($lig2['date_previsionnelle_retour']).") par ".civ_nom_prenom($lig2['login_preteur'])." pour ".($lig2['id_classe']!=0 ? get_nom_classe($lig2['id_classe']) : get_info_grp($lig2['id_groupe']))."<br />";
		}
	}
}

if((isset($id_classe))||(isset($id_groupe))) {
	if(isset($id_classe)) {

		echo "<h3>Classe de $nom_classe <a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Choisir une autre classe\"><img src='../../images/icons/edit16_ele.png' class='icone16' /></a></h3>";


		// Afficher les prêts en cours s'il y en a
		// Permettre de prêter si il y a des exemplaires dispo... avec date de retour prévisionnel... à contrôler lors de la saisie pour ne pas couvrir des dates réservées par un autre que celui pour le compte de qui le prêt est fait

		// Afficher la liste des élèves

		// S'il n'y a pas d'exemplaires pris, afficher les correspondances proposées
		// Sinon, permettre de cocher les exemplaires à prendre avant de faire correspondre?

		// AFFINER : RECUPERER LES DATES DE PERIODES ET CONTROLER QUE L'ELEVE EST DANS LA CLASSE A LA PERIODE INDIQUEE
		//get_eleves_from_classe($id_classe, $periode="")
		//get_dates_debut_fin_classe_periode($id_classe, $num_periode, $mode=1)
		//get_periode_from_classe_d_apres_date($id_classe, $timestamp="")
		$num_periode=get_periode_from_classe_d_apres_date($id_classe, time());
		if($num_periode=='') {
			$sql="SELECT DISTINCT e.* FROM eleves e, 
								j_eleves_classes jec 
							WHERE e.login=jec.login AND 
								jec.id_classe='".$id_classe."' AND 
								(e.date_sortie IS NULL OR 
								(e.date_sortie<='".strftime('%Y-%m-%d %H:%M:%S')."' AND 
								e.date_sortie!='0000-00-00 00:00:00')) 
				ORDER BY e.nom, e.prenom;";
		}
		else {
			$sql="SELECT DISTINCT e.* FROM eleves e, 
								j_eleves_classes jec 
							WHERE e.login=jec.login AND 
								jec.id_classe='".$id_classe."' AND 
								jec.periode='".$num_periode."' AND 
								(e.date_sortie IS NULL OR 
								(e.date_sortie<='".strftime('%Y-%m-%d %H:%M:%S')."' AND 
								e.date_sortie!='0000-00-00 00:00:00')) 
				ORDER BY e.nom, e.prenom;";
		}
		$res_ele=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res_ele)==0) {
			echo "<p style='color:red'>Il n'y a aucun élève dans la classe de ".$nom_classe.".</p>";
			include("../../lib/footer.inc.php");
			die();
		}

		$tab_ele=array();
		while($lig_ele=mysqli_fetch_assoc($res_ele)) {
			$tab_ele[$lig_ele['id_eleve']]=$lig_ele;
			$tab_ele[$lig_ele['id_eleve']]['classe']=$nom_classe;
		}

	}
	elseif(isset($id_groupe)) {

		// C'est un groupe
		$current_group=get_group($id_groupe);

		echo "<h3>".$nom_groupe." <a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Choisir un autre enseignement\"><img src='../../images/icons/edit16_ele.png' class='icone16' /></a></h3>";

		if(isset($current_group['classes']['list'][0])) {
			$num_periode=get_periode_from_classe_d_apres_date($current_group['classes']['list'][0], time());
		}
		else {
			$num_periode='';
		}
		if($num_periode=='') {
			$sql="SELECT DISTINCT e.* FROM eleves e, 
								j_eleves_groupes jeg 
							WHERE e.login=jeg.login AND 
								jeg.id_groupe='".$id_groupe."' AND 
								(e.date_sortie IS NULL OR 
								(e.date_sortie<='".strftime('%Y-%m-%d %H:%M:%S')."' AND 
								e.date_sortie!='0000-00-00 00:00:00')) 
				ORDER BY e.nom, e.prenom;";
		}
		else {
			$sql="SELECT DISTINCT e.* FROM eleves e, 
								j_eleves_groupes jeg 
							WHERE e.login=jeg.login AND 
								jeg.id_groupe='".$id_groupe."' AND 
								jeg.periode='".$num_periode."' AND 
								(e.date_sortie IS NULL OR 
								(e.date_sortie<='".strftime('%Y-%m-%d %H:%M:%S')."' AND 
								e.date_sortie!='0000-00-00 00:00:00')) 
				ORDER BY e.nom, e.prenom;";
		}
		$res_ele=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res_ele)>0) {
			while($lig_ele=mysqli_fetch_assoc($res_ele)) {
				$tab_ele[$lig_ele['id_eleve']]=$lig_ele;

				// $nom_classe à récupérer
				if($num_periode=='') {
					$tab_ele[$lig_ele['id_eleve']]['classe']=get_chaine_liste_noms_classes_from_ele_login($lig_ele['login']);
				}
				else {
					$tmp_clas=get_clas_ele_telle_date($lig_ele['login'], $mysql_date_courante);
					if(isset($tmp_clas['classe'])) {
						//$tab_ele[$lig_ele['id_eleve']]['classe']=get_nom_classe($current_group['classes']['list'][0]);
						$tab_ele[$lig_ele['id_eleve']]['classe']=$tmp_clas['classe'];
					}
					else {
						$tab_ele[$lig_ele['id_eleve']]['classe']=get_chaine_liste_noms_classes_from_ele_login($lig_ele['login']);
					}
				}
			}
		}

	}

	echo "<p>Le groupe ou classe choisi compte <strong>".count($tab_ele)." élève(s)</strong>.</p>";

	if(count($tab_ele)>$nb_exemplaires) {
		echo "<p style='color:red'><em>Attention&nbsp;:</em> Il y a plus d'élèves (".count($tab_ele).") que d'exemplaires dans le stock (".$nb_exemplaires.").</p>";
	}


	// Choisir les dates

	echo "
	<form action='".$_SERVER['PHP_SELF']."' method='post'>
		<fieldset class='fieldset_opacite50'>
			<h3>Formulaire de choix des dates</h3>
			".add_token_field();
	if(isset($id_reservation)) {
		echo "<p class='bold'>Recherche de dates afin de modifier la réservation n°".$id_reservation."</p>
			<input type='hidden' name='id_reservation' value='$id_reservation' />";
	}
	echo "
			<input type='hidden' name='login_reservation' value=\"$login_reservation\" />
			<input type='hidden' name='id_ouvrage' value='$id_ouvrage' />
			<p>Vous souhaitez réserver des exemplaires du 
						<input type='text' name='date_debut' id='date_debut' size='10' value=\"".$date_debut."\" onKeyDown=\"clavier_date(this.id,event);\" onchange='changement()' AutoComplete=\"off\" onblur=\"check_debut_fin()\" />
						".img_calendrier_js("date_debut", "img_bouton_date_debut")."
					au
						<input type='text' name='date_fin' id='date_fin' size='10' value=\"".$date_fin."\" onKeyDown=\"clavier_date(this.id,event);\" onchange='changement()' AutoComplete=\"off\" />
						".img_calendrier_js("date_fin", "img_bouton_date_fin")."
			</p>";

	if(isset($id_classe)) {
		echo "
			<input type='hidden' name='id_classe' value='$id_classe' />";
	}
	else {
		echo "
			<input type='hidden' name='id_groupe' value='$id_groupe' />";
	}

	/*
	// La date de retour, tant que le retour n'est pas effectif est fixé à 9999-12-31 ou quelque chose de ce genre si bien qu'en testant sur date_retour, le prêt est toujours en cours tant que l'exemplaire n'est pas rendu, même si il sera rendu avant la date choisie
	$sql="SELECT DISTINCT psex.id AS id_exemplaire FROM plugin_stock_exemplaires psex 
			WHERE psex.id_ouvrage='".$id_ouvrage."' AND 
				psex.statut!='perdu' AND 
				psex.date_de_retrait>='".$mysql_date_fin."' AND 
				psex.id NOT IN (SELECT id_exemplaire FROM plugin_stock_emprunts 
									WHERE id_ouvrage='".$id_ouvrage."' AND 
										(
											(date_pret<='$mysql_date_debut' AND 
											date_retour>='$mysql_date_debut')
											 OR 
											(date_pret<='$mysql_date_debut' AND 
											date_previsionnelle_retour>='$mysql_date_debut')
											 OR 
											(date_pret<='$mysql_date_fin' AND 
											date_previsionnelle_retour>='$mysql_date_fin')
											 OR 
											(date_pret<='$mysql_date_fin' AND 
											date_retour>='$mysql_date_fin')
										)
							);";
	*/
	$sql="SELECT DISTINCT psex.id AS id_exemplaire FROM plugin_stock_exemplaires psex 
			WHERE psex.id_ouvrage='".$id_ouvrage."' AND 
				psex.statut!='perdu' AND 
				psex.date_de_retrait>='".$mysql_date_fin."' AND 
				psex.id NOT IN (SELECT id_exemplaire FROM plugin_stock_emprunts 
									WHERE id_ouvrage='".$id_ouvrage."' AND 
										(
											(date_pret<='$mysql_date_debut' AND 
											date_previsionnelle_retour>='$mysql_date_debut')
											 OR 
											(date_pret<='$mysql_date_fin' AND 
											date_previsionnelle_retour>='$mysql_date_fin')
											 OR 
											(date_pret>='".$mysql_date_debut."' AND 
											date_previsionnelle_retour<='".$mysql_date_fin."')
										)
							);";
	plugin_stock_echo_debug("$sql<br />");
	$res=mysqli_query($mysqli, $sql);
	$nb_exemplaires_non_pretes=mysqli_num_rows($res);

	echo "<p>En déduisant les éventuels exemplaires actuellement prêtés, le stock compte ".$nb_exemplaires_non_pretes." exemplaires ";

	$sql="SELECT nb_exemplaires FROM plugin_stock_reservations 
						WHERE id_ouvrage='".$id_ouvrage."' AND 
							(
								(date_previsionnelle_pret<='$mysql_date_debut' AND 
								date_previsionnelle_retour>='$mysql_date_debut')
								 OR 
								(date_previsionnelle_pret<='$mysql_date_fin' AND 
								date_previsionnelle_retour>='$mysql_date_fin')
								 OR 
								(date_previsionnelle_pret>='".$mysql_date_debut."' AND 
								date_previsionnelle_retour<='".$mysql_date_fin."')
							);";
	plugin_stock_echo_debug("$sql<br />");
	$res=mysqli_query($mysqli, $sql);
	$nb_exemplaires_reserves=0;
	if(mysqli_num_rows($res)>0) {
		while($lig=mysqli_fetch_object($res)) {
			$nb_exemplaires_reserves+=$lig->nb_exemplaires;
		}
	}
	$nb_exemplaires_dispo=$nb_exemplaires_non_pretes-$nb_exemplaires_reserves;

	echo "dont ".$nb_exemplaires_reserves." sont réservés dans l'intervalle de dates choisi.<br />
	Il reste donc sur la période choisie <strong>".$nb_exemplaires_dispo." exemplaires</strong> disponibles pour une réservation.</p>";

	if(count($tab_ele)>$nb_exemplaires_dispo) {
		echo "<p style='color:red'><em>Attention&nbsp;:</em> Il y a plus d'élèves (".count($tab_ele).") que d'exemplaires disponibles (".$nb_exemplaires_dispo.").</p>";
	}

	echo "
			<p><input type='submit' value=\"Tester la disponibilité\" /></p>
		</fieldset>
	</form>";

	//========================================================================
	// Un 2è formulaire avec des valeurs en input hidden
	// et seulement le nombre d'exemplaires

	echo "
	<form action='".$_SERVER['PHP_SELF']."' method='post' style='margin-top:1em;'>
		<fieldset class='fieldset_opacite50'>
			<h3>Formulaire de validation de la réservation</h3>
			".add_token_field()."
			<input type='hidden' name='login_reservation' value=\"$login_reservation\" />
			<input type='hidden' name='id_ouvrage' value='$id_ouvrage' />
			<input type='hidden' name='date_debut' value='$date_debut' />
			<input type='hidden' name='date_fin' value='$date_fin' />
			<input type='hidden' name='valider_reservation' value='y' />";

	if(isset($id_reservation)) {
		echo "
			<p class='bold'>Modification de la réservation n°".$id_reservation."</p>
			<input type='hidden' name='id_reservation' value='$id_reservation' />";
	}
	echo "

			<p>Vous souhaitez réserver pour ";
	if(isset($id_classe)) {
		echo "
			la classe de ".$nom_classe."
			<input type='hidden' name='id_classe' value='$id_classe' />";
	}
	else {
		echo "
			l'enseignement ".$nom_groupe."
			<input type='hidden' name='id_groupe' value='$id_groupe' />";
	}
	echo "
			<input type='text' name='nb_exemplaires' size='4' value='".(min($nb_exemplaires_dispo, count($tab_ele)))."' onkeydown=\"clavier_2(this.id,event,0,$nb_exemplaires_dispo); changement();\" autocomplete=\"off\" />
			exemplaires du ".$date_debut." au ".$date_fin."</p>";

	echo "
			<p><input type='submit' value=\"Valider la réservation\" /></p>
		</fieldset>
	</form>

	<script type='text/javascript'>
		function check_debut_fin() {
			date_debut=document.getElementById('date_debut').value;
			date_fin=document.getElementById('date_fin').value;
			/*
			if(date_fin<date_debut) {
				document.getElementById('date_fin').value=date_debut;
			}
			*/

			cur_date=date_debut.split('/');
			cur_jour=cur_date[0];
			if(cur_jour.substr(0,1)=='0') {cur_jour=cur_jour.substr(1);}
			cur_mois=cur_date[1];
			if(cur_mois.substr(0,1)=='0') {cur_mois=cur_mois.substr(1);}
			cur_an=cur_date[2];
			if(cur_an<1900) {cur_an=eval(cur_an+1900);}

			if(!checkdate(cur_mois, cur_jour, cur_an)) {
				cur_date=new Date();
				cur_jour=cur_date.getDate();
				cur_mois=eval(cur_date.getMonth()+1);
				cur_an=cur_date.getYear();
				if(cur_an<1900) {cur_an=eval(cur_an+1900);}

				document.getElementById('date_debut').value=cur_jour+'/'+cur_mois+'/'+cur_an;
			}
			tmp_date_debut=eval(eval(cur_an*10000)+eval(cur_mois*100)+eval(cur_jour));


			cur_date=date_fin.split('/');
			cur_jour=cur_date[0];
			if(cur_jour.substr(0,1)=='0') {cur_jour=cur_jour.substr(1);}
			cur_mois=cur_date[1];
			if(cur_mois.substr(0,1)=='0') {cur_mois=cur_mois.substr(1);}
			cur_an=cur_date[2];
			if(cur_an<1900) {cur_an=eval(cur_an+1900);}

			if(!checkdate(cur_mois, cur_jour, cur_an)) {
				cur_date=new Date();
				cur_jour=cur_date.getDate();
				cur_mois=eval(cur_date.getMonth()+1);
				cur_an=cur_date.getYear();
				if(cur_an<1900) {cur_an=eval(cur_an+1900);}

				document.getElementById('date_fin').value=cur_jour+'/'+cur_mois+'/'+cur_an;
			}
			tmp_date_fin=eval(eval(cur_an*10000)+eval(cur_mois*100)+eval(cur_jour));

			//alert('Comparaison de date_debut='+tmp_date_debut+' et date_fin='+tmp_date_fin);

			if(tmp_date_fin<tmp_date_debut) {
				document.getElementById('date_fin').value=date_debut;
			}

		}
	</script>";

	include("../../lib/footer.inc.php");
	die();
}
else {
	// Choisir le groupe ou la classe
	//if($_SESSION['statut']=='professeur') {
	if($statut_login_reservation=='professeur') {
		// Choix groupe

		// Dans le cas d'un administrateur, pouvoir faire le prêt pour le compte d'un autre utilisateur
		//$_login=$_SESSION['login'];
		$_login=$login_reservation;
		$groups=get_groups_for_prof($_login);

		if(count($groups)==0) {
			echo "<p style='color:red'>Il n'existe aucun enseignement associé à ".civ_nom_prenom($_login).".</p>";
			include("../../lib/footer.inc.php");
			die();
		}

		echo "<p style='margin-top:1em;'>Aux élèves de quel enseignement souhaitez-vous prêter des exemplaires de l'ouvrage&nbsp;?</p>";
		$tab_txt=array();
		$tab_lien=array();
		$nbcol=2;
		foreach($groups as $key => $current_group) {
			$tab_txt[]=$current_group['name']." <em>(".$current_group['description'].")</em> <em>(".$current_group['classlist_string'].")</em>";
			$tab_lien[]=$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_groupe=".$current_group['id']."&login_reservation=".$login_reservation;
		}

		echo tab_liste($tab_txt,$tab_lien,$nbcol);


	}
	else {
		// Choix classe


		// Afficher les prêts en cours s'il y en a
		// Permettre de prêter si il y a des exemplaires dispo... avec date de retour prévisionnel... à contrôler lors de la saisie pour ne pas couvrir des dates réservées par un autre que celui pour le compte de qui le prêt est fait

		// Dans le cas d'un administrateur, pouvoir faire le prêt pour le compte d'un autre utilisateur

		$sql="SELECT DISTINCT c.* FROM classes c, j_eleves_classes jec WHERE c.id=jec.id_classe ORDER BY c.classe;";
		$res_clas=mysqli_query($mysqli, $sql);

		if(mysqli_num_rows($res_clas)==0) {
			echo "<p style='color:red'>Il n'existe aucune classe à laquelle prêter des exemplaires de l'ouvrage.</p>";
			include("../../lib/footer.inc.php");
			die();
		}

		echo "<p style='margin-top:1em;'>A quelle classe souhaitez-vous prêter des exemplaires de l'ouvrage&nbsp;?</p>";
		$tab_txt=array();
		$tab_lien=array();
		$nbcol=3;
		while($lig_clas=mysqli_fetch_object($res_clas)) {
			$tab_txt[]=$lig_clas->classe;
			$tab_lien[]=$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_classe=".$lig_clas->id."&login_reservation=".$login_reservation;
		}

		echo tab_liste($tab_txt,$tab_lien,$nbcol);


	}

}

include("../../lib/footer.inc.php");
?>
