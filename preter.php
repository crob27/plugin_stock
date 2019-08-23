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
		$nom_groupe2=get_info_grp($id_groupe, array('description', 'classes'), '');
		if($nom_groupe=='') {
			$msg="L 'identifiant d'enseignement $id_groupe est invalide.<br />";
			unset($id_groupe);
		}
	}
}

$mysql_date_courante=strftime("%Y-%m-%d %H:%M:%S");
$annee_scolaire=getSettingValue('gepiYear');

if((isset($_GET['export']))&&($_GET['export']=='pdf')&&(isset($id_ouvrage))&&
((isset($id_classe))||(isset($id_groupe)))) {
	// A FAIRE : Générer un export PDF des élèves et du numéro attribué



	if (!defined('FPDF_VERSION')) {
		require_once('../../fpdf/fpdf.php');
	}

	define('LargeurPage','210');
	define('HauteurPage','297');

	$hauteur_page=297;
	$largeur_page=210;

	// Pb avec php 7.2:
	$test = phpversion();
	$version = mb_substr($test, 0, 1);
	if ($version<7) {
		session_cache_limiter('private');
	}

	$MargeHaut=10;
	$MargeDroite=10;
	$MargeGauche=10;
	$MargeBas=10;

	function pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $taille_police, $graisse_police) {
		global $pdf;
		global $hauteur_min_font;
		global $fonte;

		if((!isset($hauteur_min_font))||(!preg_match('/^[0-1]{1,}$/', $hauteur_min_font))||($hauteur_min_font<1)) {
			$hauteur_min_font=3;
		}

		if((!isset($hauteur_min_font))||($fonte=='')) {
			$fonte='DejaVu';
		}

		$taille_texte_ok=false;
		$pdf->SetFont($fonte,$graisse_police,$taille_police);
		$taille_texte_courant = $pdf->GetStringWidth($texte);
		while(!$taille_texte_ok) {
			if($taille_texte_courant>$largeur_dispo) {
				$taille_police-=0.3;
				if($taille_police<$hauteur_min_font) {
					$taille_police=$hauteur_min_font;
					$taille_texte_ok=true;
					break;
				}
				$pdf->SetFont($fonte,$graisse_police,$taille_police);
				$taille_texte_courant = $pdf->GetStringWidth($texte);
			}
			else {
				$taille_texte_ok=true;
			}
		}
		$taille_texte_ok=false;

		return $taille_police;
	}

	class export_PDF extends FPDF
	{
		function Footer()
		{
			global $nom_groupe;
			global $nom_groupe2;
			global $nom_classe;
			global $info_ouvrage;
			global $info_dates;
			global $mysql_date_courante;
			global $hauteur_max_font;
			global $hauteur_min_font;

			global $fonte;

			//global $num_page;
			//global $decompte_page;
			//echo "Footer: $professeur_courant<br />\n";

			$this->SetXY(5,287);
			$this->SetFont($fonte,'',7.5);

			//$texte=getSettingValue("gepiSchoolName")."  ";
			$texte=$info_ouvrage." ($info_dates) - ".$nom_classe.$nom_groupe2;

			$lg_text=$this->GetStringWidth($texte);
			$this->SetXY(10,287);
			$this->Cell(0,5,$texte,0,0,'L');

			//$this->SetY(287);
			$this->Cell(0,5,'Page '.$this->PageNo(),"0",1,'C');
			//$this->Cell(0,5,'Page '.($this->PageNo()-$decompte_page),"0",1,'C');
			//$this->Cell(0,5,'Page '.$this->PageNo().'-'.$decompte_page.'='.($this->PageNo()-$decompte_page),"0",1,'C');
			//$this->Cell(0,5,'Page '.$num_page,"0",1,'C');

			// Je ne parviens pas à faire reprendre la numérotation à 1 lors d'un changement de salle
		}

		function EnteteListe()
		{
			global $nom_groupe;
			global $nom_groupe2;
			global $nom_classe;
			global $info_ouvrage;
			global $info_dates;
			global $mysql_date_courante;

			global $fonte, $MargeDroite, $largeur_page, $MargeGauche, $sc_interligne, $salle, $i;
			global $hauteur_max_font;
			global $hauteur_min_font;
			//global $num_page;
			//global $decompte_page;

			$this->SetFont($fonte,'B',14);
			$this->Setxy(10,10);
			$this->Cell($largeur_page-$MargeDroite-$MargeGauche,20,getSettingValue('gepiSchoolName').' - Année scolaire '.getSettingValue('gepiYear'),'LRBT',1,'C');

			$x1=$this->GetX();
			$y1=$this->GetY();

			$this->SetFont($fonte,'B',12);
			$texte='Ouvrage : ';
			$largeur_tmp=$this->GetStringWidth($texte);
			$this->Cell($largeur_tmp,$this->FontSize*$sc_interligne,$texte,'',0,'L');
			$this->SetFont($fonte,'',12);
			$texte=$info_ouvrage;
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, 80, $hauteur_max_font, '');
			$this->Cell($this->GetStringWidth($texte),$this->FontSize*$sc_interligne,$texte,'',1,'L');

			//$y2a=$this->GetY();

			$this->SetFont($fonte,'B',12);
			$texte='Dates : ';
			$this->Cell($largeur_tmp,$this->FontSize*$sc_interligne,$texte,'',0,'L');
			$this->SetFont($fonte,'',12);
			$texte=$info_dates;
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, 80, $hauteur_max_font, '');
			$this->Cell($this->GetStringWidth($texte),$this->FontSize*$sc_interligne,$texte,'',1,'L');

			$x2=$this->GetX();
			$y2=$this->GetY();

			$this->SetFont($fonte,'B',12);
			$texte=$nom_classe.$nom_groupe2;
			$larg_tmp=$sc_interligne*($this->GetStringWidth($texte));
			//$this->SetXY($largeur_page-$larg_tmp-$MargeDroite,$y1+($y2-$y1)/4);
			//echo "\$largeur_page-\$larg_tmp-\$MargeDroite=$largeur_page-$larg_tmp-$MargeDroite=".($largeur_page-$larg_tmp-$MargeDroite)."<br />";
			//die();
			$this->SetXY($largeur_page-$larg_tmp-$MargeDroite,$y1+($y2-$y1)/4);
			$this->Cell($larg_tmp,$this->FontSize*$sc_interligne,$texte,'LRBT',1,'C');
		}
	}

	// Définition de la page
	$pdf=new export_PDF("P","mm","A4");
	//$pdf=new FPDF("P","mm","A4");
	$pdf->SetTopMargin($MargeHaut);
	$pdf->SetRightMargin($MargeDroite);
	$pdf->SetLeftMargin($MargeGauche);
	//$pdf->SetAutoPageBreak(true, $MargeBas);

	// Couleur des traits
	$pdf->SetDrawColor(0,0,0);
	$pdf->SetLineWidth(0.2);

	$fonte='DejaVu';
	$sc_interligne=1.3;

	// Hauteur des lignes du tableau
	$h_cell=8;
	$hauteur_max_font=10;
	$hauteur_min_font=4;
	$bordure='LRBT';
	$v_align='C';
	$align='L';

	// Variables à définir
	if(!isset($nom_groupe)) {$nom_groupe='';}
	if(!isset($nom_classe)) {$nom_classe='';}
	$info_ouvrage=plugin_stock_afficher_ouvrage($id_ouvrage);
	// A FAIRE : Récupérer date_previsionnelle_retour
	$sql="select MAX(date_previsionnelle_retour) AS date_previsionnelle_retour from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."' AND 
			login_preteur='".$_SESSION['login']."'";
	if(isset($id_groupe)) {
		$sql.=" AND id_groupe='$id_groupe'";
	}
	if(isset($id_classe)) {
		$sql.=" AND id_classe='$id_classe'";
	}
	$sql.=";";
	//echo "$sql<br />";
	//die();
	$res2=mysqli_query($mysqli, $sql);
	if(mysqli_num_rows($res2)>0) {
		$lig2=mysqli_fetch_object($res2);
		$info_dates=formate_date($mysql_date_courante)."->".formate_date($lig2->date_previsionnelle_retour);
	}
	else {
		$info_dates=formate_date($mysql_date_courante)."->"."...";
	}

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
					$tmp_clas=get_clas_ele_telle_date($lig_ele['login'], $mysql_date_courante);
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

	$tab_exemplaires=array();
	$tab_exemplaires2=array();
	$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."' ORDER BY numero;";
	$res2=mysqli_query($mysqli, $sql);
	$nb_exemplaires=mysqli_num_rows($res2);
	if($nb_exemplaires>0) {
		while($lig2=mysqli_fetch_assoc($res2)) {
			$tab_exemplaires[]=$lig2;
			$tab_exemplaires2[$lig2['id']]=$lig2;
		}
	}

	$tab_exemplaires_empruntes=array();
	$tab_exemplaires_empruntes_ele=array();
	$sql="select * from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."';";
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_assoc($res2)) {
		$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
		$tab_exemplaires_empruntes_ele[$lig2['id_eleve']]=$lig2;
	}

	$num_page=0;

	$compteur=0;

	$num_page++;
	$pdf->AddPage("P");
	$pdf->EnteteListe();

	$cpt_col=0;

	$x0=10;
	$x1=$x0;
	$y1=30;
	$y2=41;
	$y=$y2;

	foreach($tab_ele as $id_eleve => $current_eleve) {
		// Si $y atteint le bas de page, ajouter une page et remettre $compteur à 0

		if($y+3*$h_cell+$MargeBas>HauteurPage) {
		//if($y+2*$h_cell+$MargeBas>$hauteur_page) {
			$num_page++;
			$pdf->AddPage("P");
			$pdf->EnteteListe();

			$compteur=0;
		}

		if($compteur==0) {
			// Ligne d'entête du tableau
			$y=$y2;
			$pdf->SetXY($x1,$y);

			$texte='Élève';
			$largeur_dispo=70;
			$taille_texte_ok=false;
			//$taille_police=$hauteur_max_font;
			$graisse_police='B';
			/*
			$pdf->SetFont($fonte,$graisse_police,$taille_police);
			$taille_texte_courant = $pdf->GetStringWidth($texte);
			while(!$taille_texte_ok) {
				if($taille_texte_courant>$largeur_dispo) {
					$taille_police-=0.3;
					$pdf->SetFont($fonte,$graisse_police,$taille_police);
					$taille_texte_courant = $pdf->GetStringWidth($texte);
				}
				else {
					$taille_texte_ok=true;
				}
			}
			$taille_texte_ok=false;
			*/
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $hauteur_max_font, $graisse_police);
			$pdf->Cell($largeur_dispo, $h_cell, $texte, 'LRBT', 0, 'C');

			$texte='Numéro';
			$largeur_dispo=20;
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $hauteur_max_font, $graisse_police);
			$pdf->Cell($largeur_dispo, $h_cell, $texte, 'LRBT', 0, 'C');

			$texte='État';
			$largeur_dispo=50;
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $hauteur_max_font, $graisse_police);
			$pdf->Cell($largeur_dispo, $h_cell, $texte, 'LRBT', 0, 'C');

			$texte='Dates';
			$largeur_dispo=50;
			$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $hauteur_max_font, $graisse_police);

			$pdf->Cell($largeur_dispo, $h_cell, $texte, 'LRBT', 0, 'C');

		}

		$graisse_police='';
		$y+=$h_cell;
		$pdf->SetXY($x1,$y);

		$texte=$current_eleve['nom'].' '.$current_eleve['prenom'];
		//$texte.=" ".$y;
		$largeur_dispo=70;
		$taille_police=pdf_ajuste_taille_police_pour_cellule($texte, $largeur_dispo, $hauteur_max_font, $graisse_police);
		$pdf->Cell($largeur_dispo, $h_cell, $texte, 'LRBT', 0, 'L');

		$numero='';
		$etat='';
		$dates='';

		if(isset($tab_exemplaires_empruntes_ele[$current_eleve['id_eleve']])) {
			$id_exemplaire=$tab_exemplaires_empruntes_ele[$current_eleve['id_eleve']]['id_exemplaire'];
			if(isset($tab_exemplaires2[$id_exemplaire])) {
				$numero=$tab_exemplaires2[$id_exemplaire]['numero'];
			}
			$dates="Prêté le ".formate_date($tab_exemplaires_empruntes_ele[$current_eleve['id_eleve']]['date_pret']);
			$etat="État initial : ".$tab_exemplaires_empruntes_ele[$current_eleve['id_eleve']]['etat_initial'];
		}

		$largeur_dispo=20;
		$taille_police=pdf_ajuste_taille_police_pour_cellule($numero, $largeur_dispo, $hauteur_max_font, $graisse_police);
		$pdf->Cell($largeur_dispo, $h_cell, $numero, 'LRBT', 0, 'C');

		$largeur_dispo=50;
		$taille_police=pdf_ajuste_taille_police_pour_cellule($etat, $largeur_dispo, $hauteur_max_font, $graisse_police);
		$pdf->Cell($largeur_dispo, $h_cell, $etat, 'LRBT', 0, 'C');

		$largeur_dispo=50;
		$taille_police=pdf_ajuste_taille_police_pour_cellule($dates, $largeur_dispo, $hauteur_max_font, $graisse_police);

		$pdf->Cell($largeur_dispo, $h_cell, $dates, 'LRBT', 0, 'C');

		$compteur++;
	}

	//$pdf->Footer();

	$pref_output_mode_pdf=get_output_mode_pdf();

	$date=date("Ymd_Hi");
	$nom_fich='plugin_stock_pret_'.$id_ouvrage.'_'.$date.'.pdf';
	send_file_download_headers('application/pdf',$nom_fich);
	$pdf->Output($nom_fich,$pref_output_mode_pdf);
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
	elseif($date_previsionnelle_retour<strftime("%d/%m/%Y")) {
		$msg="Date prévisionnelle de retour ".$date_previsionnelle_retour." antérieure à la date courante (".strftime("%d/%m/%Y").") non valide.<br />";
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
						$tmp_clas=get_clas_ele_telle_date($lig_ele['login'], $mysql_date_courante);
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

		$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."' ORDER BY numero;";
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
					date_pret<='".$mysql_date_courante."' AND 
					date_retour>='".$mysql_date_courante."';";
			$res2=mysqli_query($mysqli, $sql);
			while($lig2=mysqli_fetch_assoc($res2)) {
				$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
			}

			/*
			// Il n'y a pas de champ id_exemplaire dans plugin_stock_reservations, seulement un nb_exemplaires
			$tab_reservations=array();
			$sql="select * from plugin_stock_reservations where id_ouvrage='".$id_ouvrage."' AND 
					date_previsionnelle_retour>='".$mysql_date_courante."';";
			$res2=mysqli_query($mysqli, $sql);

			if(mysqli_num_rows($res2)>0) {
				while($lig2=mysqli_fetch_assoc($res2)) {
					$tab_reservations[$lig2['id_exemplaire']]=$lig2;
				}
			}

			echo "<div style='float:left; width:20em;margin:0.5em;' class='fieldset_opacite50'>
			tab_reservations<pre>";
			print_r($tab_reservations);
			echo "
			</pre>
			</div>";

			*/

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
			*/


			$mysql_date_previsionnelle_retour=get_mysql_date_from_slash_date($date_previsionnelle_retour);

			// Vérifier le nombre d'exemplaires dispo
			$sql="SELECT DISTINCT psex.id AS id_exemplaire FROM plugin_stock_exemplaires psex 
					WHERE psex.id_ouvrage='".$id_ouvrage."' AND 
						psex.statut!='perdu' AND 
						psex.date_de_retrait>='".$mysql_date_courante."' AND 
						psex.id NOT IN (SELECT id_exemplaire FROM plugin_stock_emprunts 
											WHERE id_ouvrage='".$id_ouvrage."' AND 
												(
													(date_pret<='$mysql_date_courante' AND 
													date_previsionnelle_retour>='$mysql_date_courante')
													 OR 
													(date_pret<='".$mysql_date_previsionnelle_retour."' AND 
													date_previsionnelle_retour>='".$mysql_date_previsionnelle_retour."')
													 OR 
													(date_pret>='".$mysql_date_courante."' AND 
													date_previsionnelle_retour<='".$mysql_date_previsionnelle_retour."')
												)
									);";
			plugin_stock_echo_debug("$sql<br /><br />");
			$res=mysqli_query($mysqli, $sql);
			$nb_exemplaires_non_pretes=mysqli_num_rows($res);

			// NE PAS PRENDRE EN COMPTE LES RESERVATIONS POUR LE id_groupe OU id_classe COURANT PAR LE MEME login_preteur
			$sql="SELECT nb_exemplaires FROM plugin_stock_reservations 
								WHERE id_ouvrage='".$id_ouvrage."' AND 
									(
										(date_previsionnelle_pret<='$mysql_date_courante' AND 
										date_previsionnelle_retour>='$mysql_date_courante')
										 OR 
										(date_previsionnelle_pret<='$mysql_date_previsionnelle_retour' AND 
										date_previsionnelle_retour>='$mysql_date_previsionnelle_retour')
										 OR 
										(date_previsionnelle_pret>='".$mysql_date_courante."' AND 
										date_previsionnelle_retour<='".$mysql_date_previsionnelle_retour."')
									);";
			plugin_stock_echo_debug("$sql<br /><br />");
			$res=mysqli_query($mysqli, $sql);
			$nb_exemplaires_reserves=0;
			if(mysqli_num_rows($res)>0) {
				while($lig=mysqli_fetch_object($res)) {
					$nb_exemplaires_reserves+=$lig->nb_exemplaires;
				}
			}

			$sql="SELECT nb_exemplaires FROM plugin_stock_reservations 
								WHERE id_ouvrage='".$id_ouvrage."' AND 
									(
										(date_previsionnelle_pret<='$mysql_date_courante' AND 
										date_previsionnelle_retour>='$mysql_date_courante')
										 OR 
										(date_previsionnelle_pret<='$mysql_date_previsionnelle_retour' AND 
										date_previsionnelle_retour>='$mysql_date_previsionnelle_retour')
										 OR 
										(date_previsionnelle_pret>='".$mysql_date_courante."' AND 
										date_previsionnelle_retour<='".$mysql_date_previsionnelle_retour."')
									) AND 
									login_preteur='".$_SESSION['login']."'";
			if(isset($id_groupe)) {
				$sql.=" AND id_groupe='$id_groupe'";
			}
			if(isset($id_classe)) {
				$sql.=" AND id_classe='$id_classe'";
			}
			$sql.=";";
			plugin_stock_echo_debug("$sql<br /><br />");
			$res=mysqli_query($mysqli, $sql);
			$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe=0;
			if(mysqli_num_rows($res)>0) {
				while($lig=mysqli_fetch_object($res)) {
					$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe+=$lig->nb_exemplaires;
				}
			}

			$nb_exemplaires_dispo=$nb_exemplaires_non_pretes-$nb_exemplaires_reserves+$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe;

			plugin_stock_echo_debug("\$nb_exemplaires_dispo=\$nb_exemplaires_non_pretes-\$nb_exemplaires_reserves+\$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe=$nb_exemplaires_non_pretes-$nb_exemplaires_reserves+$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe=".$nb_exemplaires_dispo."<br /><br />");

			// En cas de changement de date_previsionnelle_retour, contrôler qu'il n'y a pas collision sur le nombre de prêts avec une réservation pré-existante
			// On peut imaginer prêter d'aujourd'hui pour demain, puis étendre la date prévisionnelle de retour.
			// Dans ce cas on fait des UPDATE et non des INSERT.
			// Or pour le moment, on ne teste que le $nb_insert comparé à $nb_exemplaires_dispo

			$nb_insert=0;
			$nb_update=0;
			$nb_suppr=0;

			if($nb_exemplaires_dispo<0) {
				$msg="Aucun exemplaire n'est disponible avec la date de retour choisie (".$date_previsionnelle_retour.").<br />";

				$sql="SELECT DISTINCT date_previsionnelle_retour FROM plugin_stock_emprunts 
									WHERE id_ouvrage='".$id_ouvrage."' AND 
										login_preteur='".$_SESSION['login']."'";
				if(isset($id_groupe)) {
					$sql.=" AND id_groupe='$id_groupe'";
				}
				if(isset($id_classe)) {
					$sql.=" AND id_classe='$id_classe'";
				}
				$sql.=";";
				plugin_stock_echo_debug("$sql<br /><br />");
				$res=mysqli_query($mysqli, $sql);
				$nb_exemplaires_reserves_par_moi_pour_ce_groupe_ou_classe=0;
				if(mysqli_num_rows($res)>0) {
					while($lig=mysqli_fetch_object($res)) {
						// En principe, on ne fait qu'un tour dans la boucle.
						$date_previsionnelle_retour=formate_date($lig->date_previsionnelle_retour);
					}
				}
				else {
					$date_previsionnelle_retour=formate_date($mysql_date_courante);
				}
			}
			else {

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
					/*
					elseif(array_key_exists($tab_exemplaires[$loop]['id'], $tab_reservations)) {
						// Voir si c'est réservé par $_SESSION['login']
						//$_login=$_SESSION['login'];

						// A MODIFIER / CORRIGER
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

				$temoin_nb_exemplaires_dispo_depasse=false;

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
									plugin_stock_echo_debug("$sql<br />");
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
										plugin_stock_echo_debug("$sql<br />");
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
																		date_pret='".$mysql_date_courante." ', 
																		date_previsionnelle_retour='".get_mysql_date_from_slash_date($date_previsionnelle_retour)."' 
														WHERE id_ouvrage='".$id_ouvrage."' AND 
															id_exemplaire='".$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]."';";
											plugin_stock_echo_debug("$sql<br />");
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
										if($nb_insert<$nb_exemplaires_dispo) {
											$sql="INSERT INTO plugin_stock_emprunts 
												SET id_exemplaire='".$pret[$id_eleve]."', 
													date_pret='".$mysql_date_courante." ', 
													date_previsionnelle_retour='".get_mysql_date_from_slash_date($date_previsionnelle_retour)."', 
													date_retour='9999-12-30 00:00:00', 
													id_ouvrage='".$id_ouvrage."', 
													id_eleve='".$id_eleve."', 
													".(isset($id_groupe) ? "id_groupe='".$id_groupe."', " : "")."
													".(isset($id_classe) ? "id_classe='".$id_classe."', " : "")."
													classe='".$tab_ele[$id_eleve]['classe']."', 
													annee_scolaire='".$annee_scolaire."', 
													mef_code='".$tab_ele[$id_eleve]['mef_code']."', 
													login_preteur='".$_SESSION['login']."', 
													etat_initial='".$tab_exemplaires2[$pret[$id_eleve]]['etat']."';";
											plugin_stock_echo_debug("$sql<br />");
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
										else {
											if($temoin_nb_exemplaires_dispo_depasse) {
												$msg.="Prêt impossible pour ".plugin_stock_get_eleve($id_eleve)."&nbsp;: ".$nb_insert." exemplaire(s) a(ont) été prêté(s) et il ne restait que $nb_exemplaires_dispo exemplaire(s) non prêtés ou réservés.<br />";
											}
											else {
												$msg.="Prêt impossible pour ".plugin_stock_get_eleve($id_eleve).".<br />";
											}
											$temoin_nb_exemplaires_dispo_depasse=true;
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
			}

			$nb_retours=0;
			if(isset($_POST['retour_pret'])) {
				$date_retour=strftime("%Y-%m-%d %H:%M:%S", time()-10);

				foreach($_POST['retour_pret'] as $id_eleve => $value) {
					// Vérifier que le prêt est bien à ce prêteur

					$sql="SELECT * FROM plugin_stock_emprunts WHERE id_ouvrage='".$id_ouvrage."' AND 
									id_eleve='".$id_eleve."' AND 
									login_preteur='".$_SESSION['login']."';";
					plugin_stock_echo_debug("$sql<br />");
					$res=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res)==0) {
						$msg.="Retour d'exemplaire impossible&nbsp;: Vous n'avez prêté aucun exemplaire à ".plugin_stock_get_eleve($id_eleve)."<br />";
					}
					else {
						//$lig=mysqli_fetch_object($res);

						$sql="UPDATE  plugin_stock_emprunts SET date_retour='".$date_retour."', 
											date_previsionnelle_retour='".$date_retour."' ";
						if(isset($_POST['etat_retour'][$id_eleve])) {
							// Gérer ailleurs les dégradations
							//if($lig->etat_initial!=) {
							//}
							$sql.=", etat_retour='".mysqli_real_escape_string($mysqli, $_POST['etat_retour'][$id_eleve])."' ";
						}
						$sql.="
										WHERE id_ouvrage='".$id_ouvrage."' AND 
											id_eleve='".$id_eleve."' AND 
											login_preteur='".$_SESSION['login']."';";
						plugin_stock_echo_debug("$sql<br />");
						$update=mysqli_query($mysqli, $sql);
						if(!$update) {
							$msg.="Erreur lors de $sql<br />";
						}
						else {
							$nb_retours++;

							$sql="SELECT * FROM plugin_stock_emprunts WHERE id_ouvrage='".$id_ouvrage."' AND 
											id_eleve='".$id_eleve."' AND 
											login_preteur='".$_SESSION['login']."' AND 
											etat_retour!=etat_initial;";
							plugin_stock_echo_debug("$sql<br />");
							$res=mysqli_query($mysqli, $sql);
							if(mysqli_num_rows($res)>0) {
								$lig=mysqli_fetch_object($res);

								$sql="UPDATE plugin_stock_exemplaires SET etat='".mysqli_real_escape_string($mysqli, $lig->etat_retour)."'
											WHERE id_ouvrage='".$id_ouvrage."' AND 
												id='".$lig->id_exemplaire."';";
								plugin_stock_echo_debug("$sql<br />");
								$update=mysqli_query($mysqli, $sql);
								if(!$update) {
									$msg.="ERREUR lors de la mise à jour de l'état de l'exemplaire n°".$tab_exemplaires2[$lig->id_exemplaire]['numero']."<br />";
								}
							}
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
					plugin_stock_echo_debug("$sql<br />");
					$res_resa=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res_resa)>0) {
						$sql="DELETE FROM plugin_stock_reservations WHERE id_classe='".$id_classe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
						plugin_stock_echo_debug("$sql<br />");
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
					plugin_stock_echo_debug("$sql<br />");
					$res_resa=mysqli_query($mysqli, $sql);
					if(mysqli_num_rows($res_resa)>0) {
						$sql="DELETE FROM plugin_stock_reservations WHERE id_groupe='".$id_groupe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
						plugin_stock_echo_debug("$sql<br />");
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
	 | <a href=\"reserver.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Réserver des ouvrages/exemplaires</a>
	 | <a href=\"historique.php\" onclick=\"return confirm_abandon (this, change, '$themessage')\">Historique</a>";

?></p>

<h2>Plugin stock&nbsp;: Prêt</h2>
<p></p>

<?php
if(!isset($id_ouvrage)) {
	// Choix de l'ouvrage

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
			$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$lig->id."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."';";
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

echo "<h3>".plugin_stock_afficher_ouvrage($id_ouvrage)."</h3>";

//==================================

// AFFICHER LE NOMBRE D'EXEMPLAIRES DISPO, LES RESA A VENIR

$sql="select * from plugin_stock_exemplaires where id_ouvrage='".$id_ouvrage."' AND statut!='perdu' AND date_de_retrait>='".$mysql_date_courante."' ORDER BY numero;";
$res2=mysqli_query($mysqli, $sql);
$nb_exemplaires=mysqli_num_rows($res2);
echo "<p>L'ouvrage compte ".$nb_exemplaires." exemplaire(s)";
	if($plugin_stock_is_administrateur) {
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
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."';";
$res2=mysqli_query($mysqli, $sql);
$nb_emprunts=mysqli_num_rows($res2);
if($nb_emprunts>0) {
	echo "<p style='margin-left:3em; text-indent:-3em;'><strong>Emprunts&nbsp;:</strong> ".$nb_emprunts." exemplaire(s) de l'ouvrage sont actuellement emprunté(s)&nbsp;:<br />";

	/*
	$sql="select *, COUNT(id_exemplaire) AS nb_exemplaires from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."' 
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

	//echo "<br />";

	echo "Classes concernées par ces prêts&nbsp;: ";
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
	echo "<br />";

	$sql="select * from plugin_stock_emprunts where id_ouvrage='".$id_ouvrage."' AND 
			date_pret<='".$mysql_date_courante."' AND 
			date_retour>='".$mysql_date_courante."';";
	//plugin_stock_echo_debug("$sql<br />");
	$res2=mysqli_query($mysqli, $sql);
	while($lig2=mysqli_fetch_assoc($res2)) {
		$tab_exemplaires_empruntes[$lig2['id_exemplaire']]=$lig2;
	}

	echo "Hors réservation éventuelle, il reste ".($nb_exemplaires-$nb_emprunts)." exemplaire(s) disponible(s) pour l'emprunt.</p>";
}

//==================================
// Afficher les réservations à venir avec le nombre d'exemplaires.

echo "
			<div style='float:right;width:16px'>
				<a href='reserver.php?id_ouvrage=".$id_ouvrage."' title=\"Réserver des exemplaires.\" onclick=\"return confirm_abandon (this, change, '$themessage')\"><img src='../../images/icons/calendrier.gif' class='icone16' /></a>
			</div>";

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
		if(($lig2['login_preteur']==$_SESSION['login'])||($plugin_stock_is_administrateur)) {
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

// Le choix de la classe ou du groupe se fait plus bas dans le else
if((isset($id_classe))||(isset($id_groupe))) {
	// La classe/groupe est choisie
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
		plugin_stock_echo_debug("$sql<br />");
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

		$msg_reservation='';
		$sql="SELECT * FROM plugin_stock_reservations WHERE id_groupe='".$id_groupe."' AND login_preteur='".$_SESSION['login']."' AND id_ouvrage='".$id_ouvrage."';";
		plugin_stock_echo_debug("$sql<br />");
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

			<div style='float:right; margin:0.5em; width:32px;'>
				<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_classe=".$id_classe."&export=pdf' target='_blank' title=\"Exporter la liste en PDF\"><img src='../../images/icons/pdf32.png' class='icone32' /></a>
			</div>";
	}
	elseif(isset($id_groupe)) {
		echo "
			<input type='hidden' name='id_groupe' value='$id_groupe' />

			<div style='float:right; margin:0.5em; width:32px;'>
				<a href='".$_SERVER['PHP_SELF']."?id_ouvrage=".$id_ouvrage."&id_groupe=".$id_groupe."&export=pdf' target='_blank' title=\"Exporter la liste en PDF\"><img src='../../images/icons/pdf32.png' class='icone32' /></a>
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
						<th>Date prévisionnelle<br />de retour</th>
						<th title=\"État de l'exemplaire lors du prêt\">État<br />initial</th>
						<th>
							Restitution/
							Retour<br />
							<a href=\"javascript:cocher_retour_pret(true)\"><img src='../../images/enabled.png' class='icone16' alt='Tout cocher' /></a> / 
							<a href=\"javascript:cocher_retour_pret(false)\"><img src='../../images/disabled.png' class='icone16' alt='Tout décocher' /></a>
						</th>
						<th title=\"État de l'exemplaire lors de la restitution\">État<br />au retour</th>
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
			// Etat au retour de l'exemplaire
			echo "
					<td id='td_etat_initial_".$cpt_eleve."'>".$tab_exemplaires2[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['etat'];
			if($plugin_stock_is_administrateur) {
				echo "<a href='saisir_ouvrage.php?id_ouvrage=".$id_ouvrage."&mode=saisir_exemplaire' onclick=\"return confirm_abandon (this, change, '$themessage')\" title=\"Corriger l'état de cet exemplaire de l'ouvrage.\"><img src='../../images/edit16.png' /></a>";
				// A FAIRE : REMPLACER PAR UN ajax
			}
			echo "</td>";
		}
		else {
			echo "
					<td>
					</td>";
		}

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

		if(isset($tab_exemplaires_pretes_a_ces_eleves[$id_eleve])) {
			// Etat au retour de l'exemplaire
			echo "
					<td>
						<input type='text' name='etat_retour[".$id_eleve."]' value=\"".$tab_exemplaires2[$tab_exemplaires_pretes_a_ces_eleves[$id_eleve]]['etat']."\" onchange='changement()' />
					</td>";
		}
		else {
			echo "
					<td>
					</td>";
		}

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

		// A FAIRE : mettre à jour 'td_etat_initial_<cpt_eleve>' lors du changement d'exemplaire








	</script>

	<!--
	<p style='text-indent:-4em; margin-left:4em;'><em>NOTES&nbsp;:</em></p>
	-->
	<p><em>NOTES&nbsp;:</em></p>
	<ul>
		<li><p>Lorsque les exemplaires sont restitués par les élèves, il convient de cocher la case dans la colonne <strong>Retour</strong> pour que l'exemplaire de l'ouvrage soit à nouveau disponible pour l'emprunt, et conserver un historique des prêts.<br />
		Il ne faut pas vider le champ SELECT dans la colonne <strong>Numéro</strong>&nbsp;; cela supprimerait la trace du prêt.</p></li>
		<li><p>L'historique des prêts permettra notamment de savoir quels ouvrages ont été étudiés par quels classes sur quelles années scolaires.</p></li>
	<!--
		<li><p></p></li>
		<li><p></p></li>
	<!--
	</ul>";




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
