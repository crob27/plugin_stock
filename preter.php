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

$id_classe=isset($_POST['id_classe']) ? $_POST['id_classe'] : (isset($_GET['id_classe']) ? $_GET['id_classe'] : NULL);
$id_groupe=isset($_POST['id_groupe']) ? $_POST['id_groupe'] : (isset($_GET['id_groupe']) ? $_GET['id_groupe'] : NULL);
$id_ouvrage=isset($_POST['id_ouvrage']) ? $_POST['id_ouvrage'] : (isset($_GET['id_ouvrage']) ? $_GET['id_ouvrage'] : NULL);

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

$date_courante=strftime("%Y-%m-%d %H:%M:%S");
$annee_scolaire=getSettingValue('gepiYear');

if((isset($_GET['export']))&&($_GET['export']=='pdf')&&(isset($id_ouvrage))&&
((isset($id_classe))||(isset($id_groupe)))) {
	// A FAIRE : Générer un export PDF des élèves et du numéro attribué






	die();
}

if((isset($_POST['valider_pret']))&&(isset($id_ouvrage))&&
((isset($id_classe))||(isset($id_groupe)))) {
	$msg='';
	/*
	$_POST['id_ouvrage']=	4
	$_POST['id_classe']=	33
	$_POST['valider_pret']=	y
	$_POST['date_previsionnelle_retour']=	19/08/2019
	$_POST['pret']=	Array (*)
	$_POST[pret]['6503']=	62
	$_POST[pret]['6504']=	63
	$_POST[pret]['6505']=	
	$_POST[pret]['6506']=	
	$_POST[pret]['6507']=	
	$_POST[pret]['6508']=	74
	$_POST[pret]['6509']=	
	*/
	$date_previsionnelle_retour=isset($_POST['date_previsionnelle_retour']) ? $_POST['date_previsionnelle_retour'] : NULL;
	if(!isset($date_previsionnelle_retour)) {
		$msg="Aucune date prévisionnelle de retour n'a été proposée.<br />";
	}
	elseif(!check_date($date_previsionnelle_retour)) {
		$msg="Date prévisionnelle de retour ".$date_previsionnelle_retour." non valide.<br />";
	}
	else {
		$pret=isset($_POST['pret']) ? $_POST['pret'] : array();
		// Tester les nouveaux prets et les prets annulés

		$tab_ele=array();
		if(isset($id_classe)) {
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
			if(mysqli_num_rows($res_ele)>0) {
				while($lig_ele=mysqli_fetch_assoc($res_ele)) {
					$tab_ele[$lig_ele['id_eleve']]=$lig_ele;
					$tab_ele[$lig_ele['id_eleve']]['classe']=$nom_classe;

					if(!plugin_stock_enregistre_eleve($lig_ele)) {
						$msg.="Erreur lors de l'enregistrement de l'élève dans 'plugin_stock_eleves'.<br />";
					}
				}
			}
		}
		else {
			// C'est un groupe
			$current_group=get_group($id_groupe);
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
						$tmp_clas=get_clas_ele_telle_date($lig_ele['login'], $date_courante);
						if(isset($tmp_clas['classe'])) {
							//$tab_ele[$lig_ele['id_eleve']]['classe']=get_nom_classe($current_group['classes']['list'][0]);
							$tab_ele[$lig_ele['id_eleve']]['classe']=$tmp_clas['classe'];
						}
						else {
							$tab_ele[$lig_ele['id_eleve']]['classe']=get_chaine_liste_noms_classes_from_ele_login($lig_ele['login']);
						}
					}

					if(!plugin_stock_enregistre_eleve($lig_ele)) {
						$msg.="Erreur lors de l'enregistrement de l'élève dans 'plugin_stock_eleves'.<br />";
					}
				}
			}
		}

		$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$date_courante."' ORDER BY numero;";
		$res2=mysqli_query($mysqli, $sql);
		$nb_exemplaires=mysqli_num_rows($res2);
		if($nb_exemplaires==0) {
			$msg="Aucun exemplaire de l'ouvrage n'existe.<br />";
		}
		else {
			$tab_exemplaires_pretes_lors_de_cet_enregistrement=array();

			$tab_exemplaires=array();
			$tab_exemplaires2=array();
			while($lig2=mysqli_fetch_assoc($res2)) {
				$tab_exemplaires[]=$lig2;
				$tab_exemplaires2[$lig2['id']]=$lig2;
			}

			//==================================
			$tab_exemplaires_empruntes=array();

			$sql="select * from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
					date_pret<='".$date_courante."' AND 
					date_retour>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			while($lig2=mysqli_fetch_assoc($res2)) {
				$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
			}

			$tab_reservations=array();
			$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
					date_previsionnelle_retour>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);

			if(mysqli_num_rows($res2)>0) {
				while($lig2=mysqli_fetch_assoc($res2)) {
				$tab_reservations[$lig2['id_exemplaire']]=$lig2;
				}
			}

			/*
			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_exemplaires<pre>";
			print_r($tab_exemplaires);
			echo "
			</pre>
			</div>";

			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_exemplaires_empruntes<pre>";
			print_r($tab_exemplaires_empruntes);
			echo "
			</pre>
			</div>";

			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_reservations<pre>";
			print_r($tab_reservations);
			echo "
			</pre>
			</div>";
			*/

			$tab_exemplaires_pretes_a_ces_eleves=array();
			$tab_exemplaires_disponibles=array();
			//$chaine_options_select_exemplaire='';
			for($loop=0;$loop<count($tab_exemplaires);$loop++) {
				if(array_key_exists($tab_exemplaires[$loop]['id'], $tab_exemplaires_empruntes)) {

					// Est-ce un emprunt par la personne courante? et pour un élève de la classe/groupe choisi?
					// On aura ça après la première validation du prêt

					if(array_key_exists($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['id_eleve'], $tab_ele)) {
						$tab_exemplaires_pretes_a_ces_eleves[$tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['id_eleve']]=$tab_exemplaires[$loop]['id'];
					}


				}
				elseif(array_key_exists($tab_exemplaires[$loop]['id'], $tab_reservations)) {
					// Voir si c'est réservé par $_SESSION['login']
					//$_login=$_SESSION['login'];



					// A MODIFIER / CORRIGER

					$tab_exemplaires_disponibles[]=$tab_exemplaires[$loop]['id'];



					// Remplir un tableau javascript des réservations pour information dans 'td_reservation_<CPT_ELEVE>'



				}
				else {
					$tab_exemplaires_disponibles[]=$tab_exemplaires[$loop]['id'];
				}

			}

			/*
			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_exemplaires_disponibles<pre>";
			print_r($tab_exemplaires_disponibles);
			echo "
			</pre>
			</div>";

			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_exemplaires_pretes_a_ces_eleves<pre>";
			print_r($tab_exemplaires_pretes_a_ces_eleves);
			echo "
			</pre>
			</div>";
			*/

			$nb_insert=0;
			$nb_update=0;
			$nb_suppr=0;
			foreach($tab_ele as $id_eleve => $current_eleve) {
				// Existe-t-il déjà un emprunt?
				// Est-il toujours présent avec le même exemplaire?
				if(isset($pret[$id_eleve])) {
					if($pret[$id_eleve]=='') {
						if(array_key_exists($id_eleve, $tab_exemplaires_pretes_a_ces_eleves)) {
							// On doit supprimer un pret
							// Est-ce que $_SESSION['login'] est bien le prêteur... ou administrateur du plugin?

							if($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']!=$_SESSION['login']) {
								$msg.="Suppression de prêt impossible pour ".plugin_stock_get_eleve($id_eleve)."&nbsp;: vous n'êtes pas le prêteur (".civ_nom_prenom($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']).")<br />";
							}
							else {

								$sql="DELETE FROM plugin_stock_emprunts 
											WHERE id_ouvrage='".$id_ouvrage."' AND 
												id_exemplaire='".$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]."';";
								echo "$sql<br />";
								$del=mysqli_query($mysqli, $sql);
								if(!$del) {
									$msg.="Erreur lors de $sql<br />";
								}
								else {
									$nb_suppr++;
								}
							}

						}
						// Sinon, il n'y avait pas de prêt, on ne change rien
					}
					else {
						if(array_key_exists($id_eleve, $tab_exemplaires_pretes_a_ces_eleves)) {
							// Est-ce toujours le même prêt?
							if($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]==$pret[$id_eleve]) {
								// Le prêt est déjà enregistré et ne change pas

								// Mettre à jour la date de retour?
								if($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']==$_SESSION['login']) {

									$sql="UPDATE plugin_stock_emprunts SET date_previsionnelle_retour='".get_mysql_date_from_slash_date($date_previsionnelle_retour)."' 
												WHERE id_ouvrage='".$id_ouvrage."' AND 
													id_exemplaire='".$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]."';";
									echo "$sql<br />";
									$update=mysqli_query($mysqli, $sql);
									if(!$update) {
										$msg.="Erreur lors de $sql<br />";
									}
									else {
										$nb_update++;
									}
								}

								// Pour ne pas prêter deux fois le même exemplaire lors d'un enregistrement.
								// Le tableau des exemplaires prêtés précédemment est rempli une seule fois, avant les enregistrements.
								$tab_exemplaires_pretes_lors_de_cet_enregistrement[$pret[$id_eleve]]=$id_eleve;
							}
							else {
								// Faut-il journaliser ces changements?

								// Est-ce le même prêteur?
								if($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']!=$_SESSION['login']) {
									$msg.="Changement d'exemplaire impossible pour ".plugin_stock_get_eleve($id_eleve)."&nbsp;: vous n'êtes pas le prêteur (".civ_nom_prenom($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']).")<br />";
								}
								else {

									if(array_key_exists($pret[$id_eleve], $tab_exemplaires_pretes_lors_de_cet_enregistrement)) {
										$msg.="L'exemplaire n°".$tab_exemplaires2[$pret[$id_eleve]]['numero']." est déjà prêté à ".plugin_stock_get_eleve($tab_exemplaires_pretes_lors_de_cet_enregistrement[$pret[$id_eleve]])." et ne peut donc être prêté simultanément à ".plugin_stock_get_eleve($id_eleve).".<br />";
									}
									else {
										$sql="UPDATE plugin_stock_emprunts SET id_exemplaire='".$pret[$id_eleve]."', 
																	date_pret='".$date_courante." ', 
																	date_previsionnelle_retour='".get_mysql_date_from_slash_date($date_previsionnelle_retour)."' 
													WHERE id_ouvrage='".$id_ouvrage."' AND 
														id_exemplaire='".$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]."';";
										echo "$sql<br />";
										$update=mysqli_query($mysqli, $sql);
										if(!$update) {
											$msg.="Erreur lors de $sql<br />";
										}
										else {
											// Pour ne pas prêter deux fois le même exemplaire lors d'un enregistrement.
											// Le tableau des exemplaires prêtés précédemment est rempli une seule fois, avant les enregistrements.
											$tab_exemplaires_pretes_lors_de_cet_enregistrement[$pret[$id_eleve]]=$id_eleve;

											$nb_update++;
										}
									}
								}
							}
						}
						else {
							// C'est un nouveau prêt
							if(!in_array($pret[$id_eleve], $tab_exemplaires_disponibles)) {
								$msg.="Exemplaire d'identifiant ".$pret[$id_eleve]." non disponible pour prêter à ".plugin_stock_get_eleve($id_eleve).".<br />";
							}
							else {
								if(array_key_exists($pret[$id_eleve], $tab_exemplaires_pretes_lors_de_cet_enregistrement)) {
									$msg.="L'exemplaire n°".$tab_exemplaires2[$pret[$id_eleve]]['numero']." est déjà prêté à ".plugin_stock_get_eleve($tab_exemplaires_pretes_lors_de_cet_enregistrement[$pret[$id_eleve]])." et ne peut donc être prêté simultanément à ".plugin_stock_get_eleve($id_eleve).".<br />";
								}
								else {
									$sql="INSERT INTO plugin_stock_emprunts 
										SET id_exemplaire='".$pret[$id_eleve]."', 
											date_pret='".$date_courante." ', 
											date_previsionnelle_retour='".get_mysql_date_from_slash_date($date_previsionnelle_retour)."', 
											date_retour='9999-12-30 00:00:00', 
											id_ouvrage='".$id_ouvrage."', 
											id_eleve='".$id_eleve."', 
											".(isset($id_groupe) ? "id_groupe='".$id_groupe."', " : "")."
											".(isset($id_classe) ? "id_classe='".$id_classe."', " : "")."
											classe='".$tab_ele[$id_eleve]['classe']."', 
											annee_scolaire='".$annee_scolaire."', 
											mef_code='".$tab_ele[$id_eleve]['mef_code']."', 
											login_preteur='".$_SESSION['login']."';";
									echo "$sql<br />";
									$insert=mysqli_query($mysqli, $sql);
									if(!$insert) {
										$msg.="Erreur lors de $sql<br />";
									}
									else {
										$nb_insert++;

										// Pour ne pas prêter deux fois le même exemplaire lors d'un enregistrement.
										// Le tableau des exemplaires prêtés précédemment est rempli une seule fois, avant les enregistrements.
										$tab_exemplaires_pretes_lors_de_cet_enregistrement[$pret[$id_eleve]]=$id_eleve;
									}
								}
							}
						}
					}
				}
				elseif(array_key_exists($id_eleve, $tab_exemplaires_pretes_a_ces_eleves)) {
					// BIZARRE


				}

			}

			$nb_retours=0;
			if(isset($_POST['retour_pret'])) {
				$date_retour=strftime("%Y-%m-%d %H:%M:%S", time()-10);

				foreach($_POST['retour_pret'] as $id_eleve => $value) {
					// Vérifier que le prêt est bien à ce prêteur

					$sql="SELECT * FROM plugin_stock_emprunts WHERE id_ouvrage='".$id_ouvrage."' AND 
									id_eleve='".$id_eleve."' AND 
									login_preteur='".$_SESSION['login']."';";
					echo "$sql<br />";
					$res=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res)==0) {
						$msg.="Retour d'exemplaire impossible&nbsp;: Vous n'avez prêté aucun exemplaire à ".plugin_stock_get_eleve($id_eleve)."<br />";
					}
					else {
						$sql="UPDATE  plugin_stock_emprunts SET date_retour='".$date_retour."', 
											date_previsionnelle_retour='".$date_retour."' 
										WHERE id_ouvrage='".$id_ouvrage."' AND 
											id_eleve='".$id_eleve."' AND 
											login_preteur='".$_SESSION['login']."';";
						echo "$sql<br />";
						$update=mysqli_query($mysqli, $sql);
						if(!$update) {
							$msg.="Erreur lors de $sql<br />";
						}
						else {
							$nb_retours++;
						}
					}

				}
			}

			if($nb_insert>0) {
				$msg.=$nb_insert." prêt(s) enregistré(s).<br />";
			}
			if($nb_update>0) {
				$msg.=$nb_update." prêt(s) modifié(s) ou mis à jour.<br />";
			}
			if($nb_suppr>0) {
				$msg.=$nb_suppr." prêt(s) supprimé(s).<br />";
			}
			if($nb_retours>0) {
				$msg.=$nb_retours." exemplaire(s) rendu(s).<br />";
			}

			//if(($nb_insert>0)||($nb_update>0)) {
				if(isset($id_classe)) {
					$sql="SELECT * FROM plugin_stock_reservations WHERE id_classe='".$id_classe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
					echo "$sql<br />";
					$res_resa=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res_resa)>0) {
						$sql="DELETE FROM plugin_stock_reservations WHERE id_classe='".$id_classe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
						echo "$sql<br />";
						$del=mysqli_query($mysqli, $sql);
						if($del) {
							$msg.="Réservation des exemplaires de l'ouvrage supprimée.<br />";
						}
						else {
							$msg.="Erreur lors de la suppression de la réservation des exemplaires de l'ouvrage.<br />";
						}
					}
				}

				if(isset($id_groupe)) {
					$sql="SELECT * FROM plugin_stock_reservations WHERE id_groupe='".$id_groupe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
					echo "$sql<br />";
					$res_resa=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res_resa)>0) {
						$sql="DELETE FROM plugin_stock_reservations WHERE id_groupe='".$id_groupe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
						echo "$sql<br />";
						$del=mysqli_query($mysqli, $sql);
						if($del) {
							$msg.="Réservation des exemplaires de l'ouvrage supprimée.<br />";
						}
						else {
							$msg.="Erreur lors de la suppression de la réservation des exemplaires de l'ouvrage.<br />";
						}
					}
				}
			//}

			// Voir si l'emprunt a une date_previsionnelle_retour qui entre en collision avec une réservation
		}
	}

}


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
$titre_page = "Plugin stock - Prêt";
require_once("../../lib/header.inc.php");
//**************** FIN EN-TETE *************
debug_var();

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
	}
?></p>

<h2>Plugin stock&nbsp;: Prêt</h2>
<p></p>

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
					<th class='nosort'>Prêts/Emprunts en cours</th>
					<th class='nosort'>Réservations</th>
				</tr>
			</thead>
			<tbody>";

		while($lig=mysqli_fetch_object($res)){
			$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$lig->id."' AND statut!='perdu' AND date_de_retrait>='".$date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			$nb_exemplaires=mysqli_num_rows($res2);

			echo "
				<tr>
					<td><a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."' title=\"Éditer l'ouvrage\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig->titre."</a></td>
					<td>".$lig->auteur."</td>
					<td>".$lig->code."</td>
					<td><span style='display:none;'>".$nb_exemplaires."</span><a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."' onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb_exemplaires."</a></td>
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
			<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$lig->id."' title=\"Consulter les emprunts ou effectuer un prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$nb." <img src='../../images/edit16.png' class='icone16' /></a>";
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

echo "<h3>".plugin_stock_afficher_ouvrage($id_ouvrage)."</h3>";

//==================================

// AFFICHER LE NOMBRE D'EXEMPLAIRES DISPO, LES RESA A VENIR

$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$date_courante."' ORDER BY numero;";
$res2=mysqli_query($mysqli, $sql);
$nb_exemplaires=mysqli_num_rows($res2);
echo "<p>L'ouvrage compte ".$nb_exemplaires." exemplaire(s)";
	if(plugin_stock_is_administrateur($_SESSION['login'])) {
		echo " <a href='saisir_ouvrage.php?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire' title=\"Ajouter/supprimer des exemplaires, saisir leur état,...\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/edit16.png' class='icone16' /></a>";
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
			date_pret<='".$date_courante."' AND 
			date_retour>='".$date_courante."';";
$res2=mysqli_query($mysqli, $sql);
$nb_emprunts=mysqli_num_rows($res2);
if($nb_emprunts>0) {
	echo "<p style='margin-left:3em; text-indent:-3em;'><strong>Emprunts&nbsp;:</strong> ".$nb_emprunts." exemplaire(s) de l'ouvrage sont actuellement emprunté(s)&nbsp;:<br />";

	/*
	$sql="select *, COUNT(id_exemplaire) AS nb_exemplaires from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$date_courante."' AND 
			date_retour>='".$date_courante."' 
			GROUP BY login_preteur;";
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_object($res2)) {
		// Afficher les emprunts en cours
		echo $lig2->nb_exemplaires." exemplaire(s) ".formate_date($lig2->date_pret)."-&gt;";
		if($lig2->date_retour<'9999-01-01 00:00:00') {
			echo formate_date($lig2->date_retour);
		}
		else {
			echo formate_date($lig2->date_previsionnelle_retour);
		}
		echo " <em>(".civ_nom_prenom($lig2->login_preteur).")</em><br />";
	}
	*/

	$sql="select *, COUNT(id_exemplaire) AS nb_exemplaires from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$date_courante."' AND 
			date_retour>='".$date_courante."' 
			GROUP BY login_preteur, date_pret, date_previsionnelle_retour, id_classe, id_groupe;";
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
			if((plugin_stock_is_administrateur($_SESSION['login']))||(in_array($lig2->id_groupe, $mes_id_groupes))) {
				echo "
				<a href='preter.php?id_ouvrage=".$id_ouvrage."&id_groupe=".$lig2->id_groupe."' title=\"Consulter le prêt.\" onclick=\"return confirm_abandon (this, change, '$themessage')\">
					<img src='../../images/edit16.png' class='icone16' />
				</a>";
			}
		}
		elseif($lig2->id_classe!=0) {
			if((plugin_stock_is_administrateur($_SESSION['login']))||($_SESSION['statut']!='professeur')) {
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
			date_pret<='".$date_courante."' AND 
			date_retour>='".$date_courante."' 
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
			date_pret<='".$date_courante."' AND 
			date_retour>='".$date_courante."';";
	//echo "$sql<br />";
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_assoc($res2)) {
		$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
	}

	echo "<p>Hors réservation éventuelle, il reste ".($nb_exemplaires-$nb_emprunts)." exemplaire(s) disponible(s) pour l'emprunt.</p>";
}

//==================================
// Afficher les réservations à venir avec le nombre d'exemplaires.

echo "
			<div style='float:right;width:16px'>
				<a href='reserver.php?id_ouvrage=".$id_ouvrage."' title=\"Réserver des exemplaires.\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/calendrier.gif' class='icone16' /></a>
			</div>";

/*
$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
			date_previsionnelle_pret<='".$date_courante."' AND 
			date_previsionnelle_retour>='".$date_courante."';";
*/
$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
			date_previsionnelle_retour>='".$date_courante."';";
$res2=mysqli_query($mysqli, $sql);

if(mysqli_num_rows($res2)>0) {
	echo "<p style='margin-left:3em; text-indent:-3em;'><strong>Réservations&nbsp;:</strong><br />";
	while($lig2=mysqli_fetch_assoc($res2)) {
		if(($lig2['login_preteur']==$_SESSION['login'])||(plugin_stock_is_administrateur($_SESSION['login']))) {
			echo "
			<a href='reserver.php?id_ouvrage=".$id_ouvrage."&id_reservation=".$lig2['id']."' title=\"Consulter/modifier la réservation n°".$lig2['id'].".\" onclick=\"return confirm_abandon (this, change, '$themessage')\">".$lig2['nb_exemplaires']." exemplaire(s) réservé(s) (".formate_date($lig2['date_previsionnelle_pret'])."-&gt;".formate_date($lig2['date_previsionnelle_retour']).") par ".civ_nom_prenom($lig2['login_preteur'])." pour ".($lig2['id_classe']!=0 ? get_nom_classe($lig2['id_classe']) : get_info_grp($lig2['id_groupe']))."</a>";

			// A FAIRE : Pouvoir supprimer la réservation si on est administrateur ou si on est l'auteur de la réservation
			echo "
			<a href='reserver.php?id_ouvrage=".$id_ouvrage."&id_reservation=".$lig2['id']."&supprimer_reservation=y' title=\"Supprimer la réservation n°".$lig2['id'].".\" onclick=\"return confirm('Étes-vous sûr de vouloir supprimer cette réservation ?')\">
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

		$msg_reservation='';
		$sql="SELECT * FROM plugin_stock_reservations WHERE id_classe='".$id_classe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
		echo "$sql<br />";
		$res_resa=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res_resa)>0) {
			$msg_reservation.="<p>";
			while($lig_resa=mysqli_fetch_object($res_resa)) {
				$msg_reservation.="La réservation de ".$lig_resa->nb_exemplaires." exemplaire(s) du ".formate_date($lig_resa->date_previsionnelle_pret)." au ".formate_date($lig_resa->date_previsionnelle_retour)." pour la classe de ".$nom_classe." sera supprimée lors de la validation du prêt.<br />";
			}
			$msg_reservation.="Le prêt remplacera la réservation libérant le cas échéant pour réservation/prêt les exemplaires non effectivement validés lors du prêt.</p>";
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
					$tmp_clas=get_clas_ele_telle_date($lig_ele['login'], $date_courante);
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

		$msg_reservation='';
		$sql="SELECT * FROM plugin_stock_reservations WHERE id_groupe='".$id_groupe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
		echo "$sql<br />";
		$res_resa=mysqli_query($mysqli, $sql);
		if(mysqli_num_rows($res_resa)>0) {
			$msg_reservation.="<p>";
			while($lig_resa=mysqli_fetch_object($res_resa)) {
				$msg_reservation.="La réservation de ".$lig_resa->nb_exemplaires." exemplaire(s) du ".formate_date($lig_resa->date_previsionnelle_pret)." au ".formate_date($lig_resa->date_previsionnelle_retour)." pour l'enseignement ".$nom_groupe." sera supprimée lors de la validation du prêt.<br />";
			}
			$msg_reservation.="Le prêt remplacera la réservation libérant le cas échéant pour réservation/prêt les exemplaires non effectivement validés lors du prêt.</p>";
		}

	}




	//$date_previsionnelle_retour_exemplaires_pretes_par_moi='0000-00-00 00:00:00';
	$date_previsionnelle_retour_exemplaires_pretes_par_moi='00/00/0000';
	$tab_exemplaires_preteur=array();
	$tab_exemplaires_pretes_a_ces_eleves=array();
	$tab_exemplaires_disponibles=array();
	//$chaine_options_select_exemplaire='';
	for($loop=0;$loop<count($tab_exemplaires);$loop++) {
		if(array_key_exists($tab_exemplaires[$loop]['id'], $tab_exemplaires_empruntes)) {
			// L'exemplaire courant $tab_exemplaires[$loop]['id'] est actuellement emprunté

			// Est-ce un emprunt par la personne courante? et pour un élève de la classe/groupe choisi?
			// On aura ça après la première validation du prêt

			if(array_key_exists($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['id_eleve'], $tab_ele)) {
				// L'exemplaire courant $tab_exemplaires[$loop]['id'] est actuellement emprunté par des élèves de la classe ou du groupe affiché
				$tab_exemplaires_pretes_a_ces_eleves[$tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['id_eleve']]=$tab_exemplaires[$loop]['id'];
			}

			// On rempli le tableau $tab_exemplaires_preteur des identifiants exemplaires prêtés avec pour indice du tableau longin_preteur
			$tab_exemplaires_preteur[$tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['login_preteur']][]=$tab_exemplaires[$loop]['id'];

			// On recherche la date_previsionnelle_retour la plus élevée pour l'utilisateur courant
			if($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['login_preteur']==$_SESSION['login']) {
				//echo "Exemplaire \$tab_exemplaires[$loop]['id']=".$tab_exemplaires[$loop]['id']." (n°".$tab_exemplaires[$loop]['numero'].") prêté à ".plugin_stock_get_eleve($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['id_eleve'])." a une date prévisionnelle de retour au ".$tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour']."<br />";
				if(formate_date($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour'])>$date_previsionnelle_retour_exemplaires_pretes_par_moi) {
					$date_previsionnelle_retour_exemplaires_pretes_par_moi=formate_date($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour']);
					//echo "\$date_previsionnelle_retour_exemplaires_pretes_par_moi=formate_date(\$tab_exemplaires_empruntes[".$tab_exemplaires[$loop]['id']."]['date_previsionnelle_retour'])=formate_date(".$tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour'].")=".formate_date($tab_exemplaires_empruntes[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour'])."<br />";
				}
			}

		}
		/*
		// Il n'y a pas de $tab_reservations, mais seulement un nombre d'exemplaires réservés
		elseif(array_key_exists($tab_exemplaires[$loop]['id'], $tab_reservations)) {
			// Voir si c'est réservé par $_SESSION['login']
			//$_login=$_SESSION['login'];

			// A MODIFIER / CORRIGER... prendre en compte les réservations

			$tab_exemplaires_disponibles[]=$tab_exemplaires[$loop]['id'];

			// Remplir un tableau javascript des réservations pour information dans 'td_reservation_<CPT_ELEVE>'

		}
		*/
		else {
			$tab_exemplaires_disponibles[]=$tab_exemplaires[$loop]['id'];
		}

	}

	/*
	echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
	tab_exemplaires_empruntes<pre>";
	print_r($tab_exemplaires_empruntes);
	echo "
	</pre>
	</div>";

	echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
	tab_exemplaires_pretes_a_ces_eleves<pre>";
	print_r($tab_exemplaires_pretes_a_ces_eleves);
	echo "
	</pre>
	</div>";
	*/


	$cpt_exemplaire=0;

	if(!isset($date_previsionnelle_retour)) {
		//if((isset($date_previsionnelle_retour_exemplaires_pretes_par_moi))&&($date_previsionnelle_retour_exemplaires_pretes_par_moi>'0000-00-00 00:00:00')){
		
if((isset($date_previsionnelle_retour_exemplaires_pretes_par_moi))&&($date_previsionnelle_retour_exemplaires_pretes_par_moi>'00/00/0000')){
			$date_previsionnelle_retour=$date_previsionnelle_retour_exemplaires_pretes_par_moi;
		}
		else {
			$date_previsionnelle_retour=strftime('%d/%m/%Y');
		}
	}

	echo "
	<!--
	<p style='color:red'>
		si \$date_previsionnelle_retour n'est pas défini, affecter la valeur de \$date_previsionnelle_retour à la date max des prêts effectués par l'utilisateur courant.<br />
		sinon prendre strftime('%d/%m/%Y') ou strftime('%d/%m/%Y')+ un mois?
	</p>
	-->

	<form action='".$_SERVER['PHP_SELF']."' method='post'>
		<fieldset class='fieldset_opacite50'>
			".add_token_field()."
			<input type='hidden' name='id_ouvrage' value='$id_ouvrage' />";
	if(isset($id_classe)) {
		echo "
			<input type='hidden' name='id_classe' value='$id_classe' />

			<div style='float:right; margin:0.5em; width:16px;'>
				<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_classe=".$id_classe."&export=pdf' target='_blank' title=\"Exporter la liste en PDF\">PDF</a>
			</div>";
	}
	elseif(isset($id_groupe)) {
		echo "
			<input type='hidden' name='id_groupe' value='$id_groupe' />

			<div style='float:right; margin:0.5em; width:16px;'>
				<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_groupe=".$id_groupe."&export=pdf' target='_blank' title=\"Exporter la liste en PDF\">PDF</a>
			</div>";
	}
	echo "
			<input type='hidden' name='valider_pret' value='y' />

			<p>Date prévisionnelle de retour des exemplaires&nbsp;: 
			<input type='text' name='date_previsionnelle_retour' id='date_previsionnelle_retour' size='10' value=\"".(isset($date_previsionnelle_retour) ? $date_previsionnelle_retour : strftime('%d/%m/%Y'))."\" onKeyDown=\"clavier_date(this.id,event);\" AutoComplete=\"off\" />
						".img_calendrier_js("date_previsionnelle_retour", "img_bouton_date_previsionnelle_retour")."
			</p>

			<table class='boireaus boireaus_alt'>
				<thead>
					<tr>
						<th>Élève</th>
						<th>
							Numéro<br />
							<a href='#' onclick=\"attribuer_exemplaires_disponibles();return false;changement();\" title=\"Attribuer automatiquement les exemplaires disponibles.\"><img src='../../images/icons/wizard.png' class='icone16' /></a>
						</th>
						<th>Date prévisionnelle de retour ?</th>
						<th>
							Retour<br />
							<a href=\"javascript:cocher_retour_pret(true)\"><img src='../../images/enabled.png' class='icone16' alt='Tout cocher' /></a> / 
							<a href=\"javascript:cocher_retour_pret(false)\"><img src='../../images/disabled.png' class='icone16' alt='Tout décocher' /></a>
						</th>
						<!--
						<th>Réservations</th>
						-->
					</tr>
				</thead>
				<tbody>";
	$cpt_eleve=0;
	foreach($tab_ele as $id_eleve => $current_eleve) {
		echo "
					<tr>
						<td>";
		if(isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve])) {
			echo "<span style='color:green' title=\"L'exemplaire numéro ".$tab_exemplaires2[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['numero']." est prếté à cet élève.\">".$current_eleve['nom']." ".$current_eleve['prenom']."</span>";
		}
		else {
			echo $current_eleve['nom']." ".$current_eleve['prenom'];
		}
		echo "</td>
						<td>";
		// Si un exemplaire a été prêté à cet élève et qu'on n'est pas le prêteur, ne pas permettre de modifier
		if((isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]))&&
		(isset($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]))&&
		($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur']!=$_SESSION['login'])) {
			echo "<span title=\"Prếté par ".civ_nom_prenom($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['login_preteur'])." avec une date prévisionnelle de retour au ".formate_date($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['date_previsionnelle_retour'])."\">".$tab_exemplaires2[$tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['id_exemplaire']]['numero']."</span>";
		}
		else {
			echo "
							<select name='pret[$id_eleve]' id='pret_".$cpt_eleve."' onchange=\"info_reservation('pret_".$cpt_eleve."');changement();\">
								<option value=''>---</option>";
			for($loop=0;$loop<count($tab_exemplaires);$loop++) {
				if((isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]))&&
				($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]==$tab_exemplaires[$loop]['id'])) {
					$selected=' selected="true"';
					echo "
								<option value='".$tab_exemplaires[$loop]['id']."'".$selected.">".$tab_exemplaires[$loop]['numero'];
					/*
					if(array_key_exists($tab_exemplaires[$loop]['id'], $tab_reservations)) {
						// Est-ce réservé par un autre utilisateur?
						// Sinon, pré-sélectionner cet exemplaire, si c'est pour le même groupe/classe
						echo " (réservé du ".formate_date($tab_reservations[$tab_exemplaires[$loop]['id']]['date_previsionnelle_pret'])." au ".formate_date($tab_reservations[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour']).")";
					}
					*/

					echo "</option>";
				}
				elseif(in_array($tab_exemplaires[$loop]['id'], $tab_exemplaires_disponibles)) {
					$selected='';
					// Normalement, le cas selected est traité dans le if au-dessus
					if((isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]))&&
					($tab_exemplaires_pretes_a_ces_eleves[$id_eleve]==$tab_exemplaires[$loop]['id'])) {
						$selected=' selected="true"';
					}

					echo "
									<option value='".$tab_exemplaires[$loop]['id']."'".$selected.">".$tab_exemplaires[$loop]['numero'];
					/*
					if(array_key_exists($tab_exemplaires[$loop]['id'], $tab_reservations)) {
						// Est-ce réservé par un autre utilisateur?
						// Sinon, pré-sélectionner cet exemplaire, si c'est pour le même groupe/classe
						echo " (réservé du ".formate_date($tab_reservations[$tab_exemplaires[$loop]['id']]['date_previsionnelle_pret'])." au ".formate_date($tab_reservations[$tab_exemplaires[$loop]['id']]['date_previsionnelle_retour']).")";
					}
					*/
					echo "</option>";
				}
			}
		}
		echo "
							</select>
						</td>
						<td>";
		if(isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve])) {
			echo "<span title=\"Date de prévisionnelle de retour enregistrée pour l'exemplaire prêté.\">".formate_date($tab_exemplaires_empruntes[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['date_previsionnelle_retour'])."</span>";
		}
		echo "
					</td>";
		if(isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve])) {
			// A FAIRE : Ne pas permettre de cocher si on n'est pas le prêteur
			echo "
					<td title=\"Cocher cette case lorsque l'exemplaire est rendu.\">
						<input type='checkbox' name='retour_pret[".$id_eleve."]' id='retour_pret_".$cpt_eleve."' value='y' />";
		}
		else {
			echo "
					<td>";
		}
		echo "
						</td>";
		/*
		echo "
						<td id='td_reservation_".$cpt_eleve."'>
						</td>";
		*/
		echo "
					</tr>";
		$cpt_eleve++;
	}
	echo "
				</tbody>
			</table>

			<p><input type='submit' value=\"Valider\" /></p>

			<div id='fixe'><input type='submit' value='Valider' /></div>

			<br />

			$msg_reservation

		</fieldset>
	</form>
	
	<script type='text/javascript'>
		function attribuer_exemplaires_disponibles() {
			//alert(document.getElementById('pret_'+i).length);

			cpt_exemplaire=0;
			for(i=0;i<$cpt_eleve;i++) {
				if(document.getElementById('pret_'+i)) {
					if(document.getElementById('pret_'+i).length>=i+1) {
						document.getElementById('pret_'+i).selectedIndex=i+1;
					}
				}
			}
		}

		function cocher_retour_pret(mode) {
			for(i=0;i<$cpt_eleve;i++) {
				if(document.getElementById('retour_pret_'+i)) {
					document.getElementById('retour_pret_'+i).checked=mode;
				}
			}
		}

		function info_reservation(id) {
			// Tester l'option sélectionnée du champ id
			// Y a-t-il une réservation pour l'id_exemplaire?
			// Afficher le résultat dans 'td_reservation_<CPT_ELEVE>'
		}
	</script>";




	include("../../lib/footer.inc.php");
	die();
}
else {
	// Choisir le groupe ou la classe
	if($_SESSION['statut']=='professeur') {
		// Choix groupe

		// Dans le cas d'un administrateur, pouvoir faire le prêt pour le compte d'un autre utilisateur
		$_login=$_SESSION['login'];
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
			$tab_lien[]=$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_groupe=".$current_group['id'];
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
			$tab_lien[]=$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_classe=".$lig_clas->id;
		}

		echo tab_liste($tab_txt,$tab_lien,$nbcol);


	}

}

include("../../lib/footer.inc.php");
?>
