<?php

// l'utilisateur a-t-il accès au script ?
function calcul_autorisation_plugin_example($login,$chemin_script)
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
		// Ici seul l'administrateur ayant installé le plugin peut l'administrer
		case "admin.php":
			return ($_SESSION['login']==getSettingValue("admin_plugin_example"));
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

function ante_installation_plugin_example()
	{
	echo "<p>Nombre de tables avant installation de plugin_example ".nb_tables()."</p>";
	return "";
	}

function post_installation_plugin_example()
	{
	echo "<p>Nombre de tables après installation de plugin_example ".nb_tables()."</p>";
	if (saveSetting("admin_plugin_example",$_SESSION['login'])) return "";
		else return "Impossible d'enregistrer dans la table 'setting'";
	}

function ante_desinstallation_plugin_example()
	{
	echo "<p>Nombre de tables avant désinstallation de plugin_example ".nb_tables()."</p>";
	return "";
	}

function post_desinstallation_plugin_example()
	{
	global $mysqli;
	echo "<p>Nombre de tables après désinstallation de plugin_example ".nb_tables()."</p>";
	if (mysqli_query($mysqli, "DELETE FROM `setting` WHERE `NAME`='admin_plugin_example'")) return "";
		else return "Impossible de supprimer dans la table 'setting' : ".((is_object($mysqli)) ? mysqli_error($mysqli) : (($___mysqli_res = mysqli_connect_error()) ? $___mysqli_res : false));
	}

?>