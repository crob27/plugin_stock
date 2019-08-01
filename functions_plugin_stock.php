<?php

// l'utilisateur a-t-il accès au script ?
function calcul_autorisation_plugin_stock($login,$chemin_script)
	{
	global $mysqli;
	$script=basename($chemin_script);
	switch ($script)
		{
		// Dès lors que la fonction calcul_autorisation_... est définie
		// il faut y confirmer les autorisations définies dans plugin.xml
		// (ou les affiner) pour les scripts devant être accessibles
		// depuis la page d'accueil et depuis la barre de menu.
		// Ici tous les utilisateurs ont accès à index.php
		case "index.php":
			return true;
			break;
		case "admin.php":
			//return ($_SESSION['login']==getSettingValue("admin_plugin_stock"));
			// Autoriser les administrateurs et des utilisateurs particuliers
			$retour=false;
			if($_SESSION['statut']=='administrateur') {
				$retour=true;
			}
			else {
				$sql="SELECT 1=1 FROM plugin_stock_users WHERE statut='administrateur';";
				//echo "$sql<br />";
				$test=mysqli_query($mysqli, $sql);
				if(mysqli_num_rows($test)>0) {
					$retour=true;
				}
			}
			return $retour;
			break;
		default:
			return false;
		}
	}
	
function nb_tables()
	{
	global $mysqli;
	$r_sql="SHOW TABLES";
	$R_tables=mysqli_query($mysqli, $r_sql);
	return ($R_tables==false)?0:mysqli_num_rows($R_tables);
	}

function ante_installation_plugin_stock()
	{
	global $msg;

	if(!isset($msg)) {
		$msg='';
	}

	//echo "<p>Nombre de tables avant installation de plugin_stock ".nb_tables()."</p>";
	$msg.="<p>Nombre de tables avant installation de plugin_stock ".nb_tables()."</p>";
	return "";
	}

function post_installation_plugin_stock()
	{
	global $msg;

	if(!isset($msg)) {
		$msg='';
	}

	//echo "<p>Nombre de tables après installation de plugin_stock ".nb_tables()."</p>";
	$msg.="<p>Nombre de tables après installation de plugin_stock ".nb_tables()."</p>";
	if (saveSetting("admin_plugin_stock",$_SESSION['login'])) return "";
		else return "Impossible d'enregistrer dans la table 'setting'";
	}

function ante_desinstallation_plugin_stock()
	{
	global $msg;

	if(!isset($msg)) {
		$msg='';
	}

	//echo "<p>Nombre de tables avant désinstallation de plugin_stock ".nb_tables()."</p>";
	$msg.="<p>Nombre de tables avant désinstallation de plugin_stock ".nb_tables()."</p>";
	return "";
	}

function post_desinstallation_plugin_stock()
	{
	global $mysqli;
	global $msg;

	if(!isset($msg)) {
		$msg='';
	}

	//echo "<p>Nombre de tables après désinstallation de plugin_stock ".nb_tables()."</p>";
	$msg.="<p>Nombre de tables après désinstallation de plugin_stock ".nb_tables()."</p>";
	if (mysqli_query($mysqli, "DELETE FROM `setting` WHERE `NAME`='admin_plugin_stock'")) return "";
		else return "Impossible de supprimer dans la table 'setting' : ".((is_object($mysqli)) ? mysqli_error($mysqli) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false));
	}

?>
