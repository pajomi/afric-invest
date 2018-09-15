<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// COUNTRY_CLIENT -> Datenbank Name, Pfad zu den Bilddateien, Elmes Dateiname, Pfad zu datenblatt/stapeldruck
// COUNTRY#CLIENT -> Token-Name für de_object.db, Elmes-Programmierung
define("COUNTRY",	"de");
define("CLIENT",	"lenz");
// datasheet
define("PDF_HEADER_TEXT", "Lenz Sicherheit\nwww.lenz-sicherheitsdienst.de");


class De_lenz extends Members_Controller {

	function __construct()
	{
		parent::__construct();

		$this->db = $this->load->database(COUNTRY.'_'.CLIENT,true);

		// folgende Zeilen über config/autoload.php abgewickelt
		// $autoload['libraries'] = array('database','ion_auth','session','email');
		$this->load->library('grocery_CRUD');
		$this->load->library('image_CRUD');
		$this->load->library('pdf');

		// folgende Zeilen über config/autoload.php abgewickelt
		// $autoload['helper'] = array('url','form');
		$this->load->helper('file');
		$this->load->helper('download');
	}

	function index() {
		$aktuell = strtotime(date("Y-m-d",time()));
		$aktuell_jahr = date("Y",time());

		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$count_all = $this->db->get()->num_rows();
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');		
		$count_active = $this->db->get()->num_rows();		
		$count_inactive = $count_all-$count_active;

		$this->db->select('at_wartung');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$query = $this->db->get();

		$count_at_wartung_ok = 0;
		$count_at_wartung_uf = 0;
		$count_at_wartung_ne = 0;

		foreach($query->result() as $value) {
			$wartung = strtotime('+1 year',strtotime($value->at_wartung));
			$differenz = floor(($wartung - $aktuell)/86400);

			switch(true) {
				case ($differenz >= 0):		$count_at_wartung_ok++;
								break;
				case ($differenz < -7300):	$count_at_wartung_ne++;
								break;
				case ($differenz < 0):		$count_at_wartung_uf++;
								break;
			}
		}

		$this->db->where('at_status',"001");
		$count_at_status = $this->db->count_all_results(COUNTRY.'_'.CLIENT.'_objekte');
		
		$this->db->select('tt_eichung_std');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$query = $this->db->get();

		$count_tt_eichung_ok = 0;
		$count_tt_eichung_uf = 0;
		$count_tt_eichung_ne = 0;

		foreach($query->result() as $value) {
			/*
			$differenz = floor(($aktuell - strtotime($value->tt_eichung))/86400);
			if ($differenz > 730) {
				$count_tt_eichung++;
			}
			*/
			
			$differenz = $value->tt_eichung_std - $aktuell_jahr;
			/*
			if ($differenz < -1000 ) {
				$count_tt_eichung_ne++;
			}
			if ($differenz < 0) {
				$count_tt_eichung_uf++;
			}
			if ($differenz >= 0) {
				$count_tt_eichung_ok++;
			}
			*/

			switch(TRUE) {
				case($differenz < -1000):
					$count_tt_eichung_ne++;
					break;
				case($differenz < 0):
					$count_tt_eichung_uf++;
					break;
				case($differenz >= 0):
					$count_tt_eichung_ok++;
					break;
			}

		}
		$this->db->where('tt_status',"0");
		$count_tt_status = $this->db->count_all_results(COUNTRY.'_'.CLIENT.'_objekte');
		
		$this->db->where('vt_ausbau','1');
		$count_vt_nichtvorhanden = $this->db->count_all_results(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('vt_ausbau','2');
		$count_vt_analog         = $this->db->count_all_results(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('vt_ausbau','3');
		$count_vt_digital        = $this->db->count_all_results(COUNTRY.'_'.CLIENT.'_objekte');
		
		$data = array(
				'count_all'					=> $count_all,
				'count_active'				=> $count_active,
				'count_inactive'			=> $count_inactive,
				'country_client'			=> COUNTRY.'_'.CLIENT,
			    'count_at_wartung_ok'		=> $count_at_wartung_ok,
			    'count_at_wartung_uf'		=> $count_at_wartung_uf,
			    'count_at_wartung_ne'		=> $count_at_wartung_ne,
			    'count_at_status'			=> $count_at_status,
			    'count_tt_eichung_ok'		=> $count_tt_eichung_ok,
			    'count_tt_eichung_uf'		=> $count_tt_eichung_uf,
			    'count_tt_eichung_ne'		=> $count_tt_eichung_ne,
			    'count_tt_status'			=> $count_tt_status,
			    'count_vt_nichtvorhanden'	=> $count_vt_nichtvorhanden,
			    'count_vt_analog'			=> $count_vt_analog,
			    'count_vt_digital'			=> $count_vt_digital
			    );
		
		//$group = $this->ion_auth->get_users_groups()->row();
		//$status['group'] = $group;
		
		$this->load->view('de/index',$data);
	}


	function object_management() {
		try{
			$crud = new grocery_CRUD();
			
			if ( $this->ion_auth->is_admin() == FALSE ) {
				$crud->unset_add();
				$crud->unset_delete();
			}
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}
			//$crud->unset_clone();

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Objekt');

			$state = $crud->getState();
			switch( $state ) {
				case 'add':
					$crud->callback_add_field('sd_acc', function() {
						$this->db->select_max('sd_acc','max_sd_acc');
						$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
						$max_sd_acc = $this->db->get()->row()->max_sd_acc + 1;
						return '<input type="text" maxlength="7" style="width:500px;" value="'. $max_sd_acc . '" name="sd_acc">';
					});
					$crud->callback_add_field('sd_active', function () {
						return '<input type="radio" id="field-sd_active-true" value="1" name="sd_active"> aktiv' . '<br>' . '<input type="radio" id="field-sd_active-false" value="0" name="sd_active"> inaktiv'; 
					});
				case 'edit':
					$crud->set_primary_key('va_kurzform',COUNTRY.'_'.CLIENT.'_vertragsart');
					$crud->set_relation('sd_contract',COUNTRY.'_'.CLIENT.'_vertragsart','{va_kurzform} - {va_beschreibung}');
					$crud->set_primary_key('fl_name',COUNTRY.'_'.CLIENT.'_beflaggung');
					$crud->set_relation('sd_banner',COUNTRY.'_'.CLIENT.'_beflaggung','{fl_name}',array('fl_active'=>'1'));
					$crud->set_primary_key('bl_iso','de_states');
					$crud->set_relation('sd_state','de_states','{bl_iso} - {bl_name}');
					$crud->set_primary_key('ma_kurzform',COUNTRY.'_'.CLIENT.'_mitarbeiter');
					$crud->set_relation('sd_innendienst', COUNTRY.'_'.CLIENT.'_mitarbeiter','{ma_kurzform} - {ma_name}',array('ma_kategorie'=>'ID'));
					$crud->set_relation('sd_aussendienst',COUNTRY.'_'.CLIENT.'_mitarbeiter','{ma_kurzform} - {ma_name}',array('ma_kategorie'=>'AD'));

					// Altes Beispiel, trotzdem behalten
					/*
					$this->db->select('ma_kurzform,ma_name');
					$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
					$this->db->where('ma_kategorie','ID');
					$this->db->where('ma_active','1');
					$ma_array = $this->db->get()->result_array();
					foreach($ma_array as $key => $value) {
						$id_array[$value['ma_kurzform']] = $value['ma_kurzform'] . " - " . $value['ma_name'];
					}
					$crud->field_type('sd_innendienst','dropdown',$id_array);

					$this->db->select('ma_kurzform,ma_name');
					$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
					$this->db->where('ma_kategorie','AD');
					$this->db->where('ma_active','1');
					$ma_array = $this->db->get()->result_array();
					foreach($ma_array as $key => $value) {
						$ad_array[$value['ma_kurzform']] = $value['ma_kurzform'] . " - " . $value['ma_name'];
					}
					$crud->field_type('sd_aussendienst','dropdown',$ad_array);
					*/

					$crud->callback_field('sd_open_mo', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_mo" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_di', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_di" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_mi', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_mi" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_do', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_do" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_fr', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_fr" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_sa', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_sa" style="width:262px">' . " Uhr";
					});
					$crud->callback_field('sd_open_so', function($value = '', $primary_key = null) {
						return '<input type="text" maxlength="50" value="'.$value.'" name="sd_open_so" style="width:262px">' . " Uhr";
					});
					break;
				case 'export':
					$crud->columns('sd_acc','sd_contract','sd_banner','sd_object','sd_state','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_innendienst','sd_aussendienst');
					break;
				case 'print':
					$crud->columns('sd_acc','sd_contract','sd_banner','sd_object','sd_state','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_innendienst','sd_aussendienst');
					break;
				//case 'list':
				//case 'ajax_list':
				default:
					$crud->columns('sd_acc','sd_contract','sd_banner','sd_object','sd_state','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_innendienst','sd_aussendienst','sd_photo_1');
					$crud->callback_column('sd_acc',			array($this,'_callback_column_sd_acc'));
					$crud->callback_column('sd_contract',		array($this,'_callback_column_sd_contract'));
					$crud->callback_column('sd_banner',			array($this,'_callback_column_sd_banner'));
					$crud->callback_column('sd_object',			array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_state',			array($this,'_callback_column_sd_state'));
					$crud->callback_column('sd_zip',			array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_cty',			array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_district',		array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_str',			array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_hno',			array($this,'_callback_column_active_txt'));
					$crud->callback_column('sd_innendienst',	array($this,'_callback_column_sd_innendienst'));
					$crud->callback_column('sd_aussendienst',	array($this,'_callback_column_sd_aussendienst'));
					$crud->callback_column('sd_photo_1',		array($this,'_callback_column_active_img'));
					$crud->callback_column('sd_photo_2',		array($this,'_callback_column_active_img'));
					$crud->callback_column('sd_contractor',		array($this,'_callback_column_active_txt'));
					break;
			}

			$crud->order_by('sd_cty','asc');
			$crud->unique_fields('sd_acc');
			$crud->required_fields('sd_acc','sd_banner','sd_state','sd_cty','sd_str');
			$crud->fields('sd_active','sd_acc','sd_contract','sd_banner','sd_object','sd_state','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_gps_lat','sd_gps_lon','sd_innendienst','sd_aussendienst','sd_photo_1','sd_photo_2','sd_open_mo','sd_open_di','sd_open_mi','sd_open_do','sd_open_fr','sd_open_sa','sd_open_so','sd_contractor','sd_bearbeitet_von','sd_bearbeitet_am');
			$crud->set_read_fields('sd_active','sd_acc','sd_contract','sd_banner','sd_object','sd_state','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_gps_lat','sd_gps_lon','sd_innendienst','sd_aussendienst','sd_photo_1','sd_photo_2','sd_open_mo','sd_open_sa','sd_open_so','sd_contractor','sd_bearbeitet_von','sd_bearbeitet_am');

			$crud->display_as('sd_active','Status');
			$crud->display_as('sd_acc','Kst.');
			$crud->display_as('sd_contract','Vertragsart');
			$crud->display_as('sd_banner','Flagge');
			$crud->display_as('sd_object','Objekt');
			$crud->display_as('sd_state','Bundesland');
			$crud->display_as('sd_zip','PLZ');
			$crud->display_as('sd_cty','Ort');
			$crud->display_as('sd_district','Ortsteil');
			$crud->display_as('sd_str','Strasse');
			$crud->display_as('sd_hno','Nr.');
			$crud->display_as('sd_gps_lat','GPS Lat.');
			$crud->display_as('sd_gps_lon','GPS Lon.');
			$crud->display_as('sd_aussendienst','Aussendienst');
			$crud->display_as('sd_innendienst','Innendienst');
			$crud->display_as('sd_photo_1','Aussenfoto');
			$crud->display_as('sd_photo_2','Shopfoto');
			$crud->display_as('sd_open_mo','Montag');
			$crud->display_as('sd_open_di','Dienstag');
			$crud->display_as('sd_open_mi','Mittwoch');
			$crud->display_as('sd_open_do','Donnerstag');
			$crud->display_as('sd_open_fr','Freitag');
			$crud->display_as('sd_open_sa','Samstag');
			$crud->display_as('sd_open_so','Sonntag');
			$crud->display_as('sd_contractor','Kontraktor');

			$crud->set_field_upload('sd_photo_1','assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos');
			$crud->set_field_upload('sd_photo_2','assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos');

			$crud->field_type('sd_hno','string');
			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));

			$crud->callback_before_insert(array($this,'object_callback_before_insert'));
			$crud->callback_after_insert(array($this, 'object_callback_after_insert'));
			$crud->callback_before_update(array($this,'object_callback_before_update'));
			$crud->callback_after_update(array($this, 'object_callback_after_update'));
			$crud->callback_before_delete(array($this, 'object_callback_before_delete'));

			//void add_action(string $label, string $image_url, string $link_url, string $css_class, mixed $url_callback)
			if ( $this->ion_auth->in_group("gast") == FALSE ) {
				$crud->add_action('Ansprechpartner','/assets/action_ansprechpartner.png', COUNTRY.'_'.CLIENT . '/partner_management/edit');
			}
			// Funktioniert nicht, ruft sofort auf
			//$crud->add_action('Datenblatt','/assets/action_pdf.png','','',array($this,'datenblatt'));
			$crud->add_action('Datenblatt','/assets/action_pdf.png','','',array($this,'object_action_datenblatt'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Objekte';

			$this->load->view('de/objekt',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_column_active_txt($value,$row) {
		if( $row->sd_active == '1' ) {
			return $value;
		} else {
			return "<s>$value</s>";
		}
	}

	function _callback_column_active_img($value,$row) {
		$link = '';
		if( !empty($value) ) {
			if( $row->sd_active == '1' ) {
				$link = '<a class="image-thumbnail" href="/assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/'.$value.'"><img src="' . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/' . $value . '" title="' . 'aktiv'   . '" height="50px"></a>';
			} else {
				$link = '<img src="' . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/' . $value . '" title="' . 'inaktiv' . '" height="50px" style="opacity:0.4;">';
			}
		}
		return $link;
	}

	function _callback_column_sd_acc($value,$row) {
		$value = ( $row->sd_active == "1") ? $value : "<s>$value</s>";

		switch(true) {
			case ( $row->sd_open_mo == "00:00 - 24:00" ):
				return $value . ' <strong style="color:red">(24h)</strong>';
				break;
			default:
				return $value;
				break;
		}
	}

	function _callback_column_sd_contract($value,$row) {
		$link = '';
		if( !empty($value) ) {
			$this->db->select('va_kurzform,va_beschreibung');
			$this->db->from(COUNTRY.'_'.CLIENT.'_vertragsart');
			$this->db->where('va_kurzform',$row->sd_contract);
			$result = $this->db->get()->row();
			if ($result != NULL && $row->sd_active == '1') {
				$link = "<span style='color:blue' title='" . $result->va_beschreibung . "'><b>" . $result->va_kurzform . "</b></span>";
			}
			if ($result != NULL && $row->sd_active == '0') {
				$link = "<span style='text-decoration:line-through' title='" . $result->va_beschreibung . "'>" . $result->va_kurzform . "</span>";
			}
		}
		return $link;
	}

	function _callback_column_sd_banner($value,$row) {
		$link = '';
		if( !empty($value) ) {
			// fl_name wird gebraucht für Excel-Export
			$this->db->select('fl_name,fl_symbol,fl_beschreibung');
			$this->db->from(COUNTRY.'_'.CLIENT.'_beflaggung');
			$this->db->where('fl_name',$value);
			$result = $this->db->get()->row();
			if ($result != NULL && $row->sd_active == '1') {
				$result->fl_symbol = (empty($result->fl_symbol))  ? 'unbekannt.png' : $result->fl_symbol;
				$link = '<img src="/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/' . $result->fl_symbol . '" title="' . $result->fl_beschreibung . '" height="25"><span style="display:none">' . $result->fl_name . '</span>';
			}
			if ($result != NULL && $row->sd_active == '0') {
				$result->fl_symbol = (empty($result->fl_symbol))  ? 'unbekannt.png' : $result->fl_symbol;
				$link = '<img src="/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/' . $result->fl_symbol . '" title="' . $result->fl_beschreibung . '" height="25" style="opacity:0.4;"><span style="display:none">' . $result->fl_name . '</span>';				
			}
		}
		return $link;
	}

	function _callback_column_sd_state($value,$row) {
		$link = '';
		if( !empty($value) ) {
			$this->db->select('bl_name,bl_iso');
			$this->db->from('sup_bundeslaender');
			$this->db->where('bl_iso',$row->sd_state);
			$result = $this->db->get()->row();
			if ($result != NULL && $row->sd_active == '1') {
				$link = "<span style='color:blue' title='" . $result->bl_name . "'><b>" . $result->bl_iso . "</b></span>";
			}
			if ($result != NULL && $row->sd_active == '0') {
				$link = "<span style='text-decoration:line-through' title='" . $result->bl_name . "'>" . $result->bl_iso . "</span>";
			}
		}
		return $link;
	}

	function _callback_column_sd_innendienst($value,$row) {
		$link = '';
		if( !empty($value) ) {
			$this->db->select('ma_name,ma_kurzform,ma_email');
			$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
			$this->db->where('ma_kurzform',$row->sd_innendienst);
			$result = $this->db->get()->row();
			if ($result != NULL) {
				$subject = "KST: " . $row->sd_acc . ", " . $row->sd_object . ", " . $row->sd_zip . " " . $row->sd_cty . ", " . $row->sd_str . " " . $row->sd_hno;
				$link = "<a href='mailto:" . $result->ma_email  . "?subject=" . $subject . "' title='e-Mail an " . $result->ma_name . "'>" . $result->ma_kurzform . "</a>" . "<span style='display:none'>" . $result->ma_name . "</span>";
			}
		}
		return $link;
	}

	function _callback_column_sd_aussendienst($value,$row) {
		$link = '';
		if( !empty($value) ) {
			$this->db->select('ma_name,ma_kurzform,ma_email');
			$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
			$this->db->where('ma_kurzform',$row->sd_aussendienst);
			$result = $this->db->get()->row();
			if($result != NULL) {
				$subject = "KST: " . $row->sd_acc . ", " . $row->sd_object . ", " . $row->sd_zip . " " . $row->sd_cty . ", " . $row->sd_str . " " . $row->sd_hno;
				$link = "<a href='mailto:" . $result->ma_email  . "?subject=" . $subject . "' title='e-Mail an " . $result->ma_name . "'>" . $result->ma_kurzform . "</a>";
			}
		}
		return $link;
	}

	function object_action_partner($primary_key, $row) {
		return "partner_management/edit/" . $row->id;
	}

	function object_action_datenblatt($primary_key, $row) {
		return site_url(COUNTRY.'_'.CLIENT . '/object_datenblatt/' . $row->id);
	}

	function object_datenblatt($value) {
		$leertext = '<font color="#FF0000">X _____________</font>';
		$leerzahl = '<font color="#FF0000">X ____</font>';
		$leerbild = '<img src="assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/unbekannt.png" height="50">';

		$this->db->select('sd_acc,sd_contract,sd_banner,sd_object,sd_state,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,sd_innendienst,sd_aussendienst,sd_open_mo,sd_open_di,sd_open_mi,sd_open_do,sd_open_fr,sd_open_sa,sd_open_so,sd_contractor,sd_bearbeitet_von,sd_bearbeitet_am,pd_firma,pd_name_1,pd_name_2,pd_telefon,pd_telefax,pd_mobil_1,pd_email_1');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		$this->db->select('fl_symbol');
		$this->db->from(COUNTRY.'_'.CLIENT.'_beflaggung');
		$this->db->where('fl_name',$data->sd_banner);
		$fl_symbol = $this->db->get()->row()->fl_symbol;
		$data->fl_symbol = ( empty($fl_symbol) ) ? $leerbild : '<img src="assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/' . $fl_symbol . '" height="50">';

		$this->db->select('bl_name');
		$this->db->from('sup_bundeslaender');
		$this->db->where('bl_iso',$data->sd_state);
		$data->sd_state = $this->db->get()->row()->bl_name;

		$this->db->select('ma_kurzform,ma_name,ma_telefon');
		$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
		$this->db->where('ma_kurzform',$data->sd_innendienst);
		$query = $this->db->get()->row();
		$data->id_name    = (empty($query->ma_name))    ? $leertext : $query->ma_name;
		$data->id_telefon = (empty($query->ma_telefon)) ? $leertext : '<a href="tel:' . str_replace(array(" ","/","-"),"",$query->ma_telefon) . '">' . $query->ma_telefon . '</a>';

		$this->db->select('ma_kurzform,ma_name,ma_mobil');
		$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
		$this->db->where('ma_kurzform',$data->sd_aussendienst);
		$query = $this->db->get()->row();
		$data->ad_name    = (empty($query->ma_name))    ? $leertext : $query->ma_name;
		$data->ad_mobil   = (empty($query->ma_mobil))   ? $leertext : '<a href="tel:' . str_replace(array(" ","/","-"),"",$query->ma_mobil) . '">' . $query->ma_mobil . '</a>';

		$data->sd_acc			= ( empty($data->sd_acc) )			? $leertext		: $data->sd_acc;
		$data->sd_contract		= ( empty($data->sd_contract) )		? $leertext		: $data->sd_contract;
		$data->sd_object		= ( empty($data->sd_object) )		? $leertext		: $data->sd_object;
		$data->sd_state			= ( empty($data->sd_state) )		? $leertext		: $data->sd_state;
		$data->sd_zip			= ( empty($data->sd_zip) )			? $leertext		: $data->sd_zip;
		$data->sd_cty			= ( empty($data->sd_district) )		? $data->sd_cty	: $data->sd_cty . ', ' . $data->sd_district;
		$data->sd_str			= ( empty($data->sd_str) )			? $leertext		: $data->sd_str;
		$data->sd_hno			= ( empty($data->sd_hno) )			? $leerzahl		: $data->sd_hno;
		$data->sd_gps_lat		= ( is_null($data->sd_gps_lat) )	? $leertext		: sprintf("%09.6f",$data->sd_gps_lat);
		$data->sd_gps_lon		= ( is_null($data->sd_gps_lon) )	? $leertext		: sprintf("%09.6f",$data->sd_gps_lon);
		$data->sd_open_mo		= ( empty($data->sd_open_mo) )		? $leertext		: $data->sd_open_mo . " Uhr";
		$data->sd_open_di		= ( empty($data->sd_open_di) )		? $leertext		: $data->sd_open_di . " Uhr";
		$data->sd_open_mi		= ( empty($data->sd_open_mi) )		? $leertext		: $data->sd_open_mi . " Uhr";
		$data->sd_open_do		= ( empty($data->sd_open_do) )		? $leertext		: $data->sd_open_do . " Uhr";
		$data->sd_open_fr		= ( empty($data->sd_open_fr) )		? $leertext		: $data->sd_open_fr . " Uhr";
		$data->sd_open_sa		= ( empty($data->sd_open_sa) )		? $leertext		: $data->sd_open_sa . " Uhr";
		$data->sd_open_so		= ( empty($data->sd_open_so) )		? ""			: $data->sd_open_so . " Uhr";
		$data->sd_contractor	= ( empty($data->sd_contractor) )	? $leertext		: $data->sd_contractor;

		$data->pd_firma			= ( empty($data->pd_firma) )		? $leertext		: $data->pd_firma;
		$data->pd_name_1		= ( empty($data->pd_name_1) )		? $leertext		: $data->pd_name_1;
		$data->pd_name_2		= ( empty($data->pd_name_2) )		? $leertext		: $data->pd_name_2;
		$data->pd_telefon		= ( empty($data->pd_telefon) )		? $leertext		: '<a href="tel:' . str_replace(array(" ","/","-"),"",$data->pd_telefon) . '">' . $data->pd_telefon . '</a>';
		$data->pd_telefax		= ( empty($data->pd_telefax) )		? $leertext		: $data->pd_telefax;
		$data->pd_mobil_1		= ( empty($data->pd_mobil_1) )		? $leertext		: '<a href="tel:'    . $data->pd_mobil_1 . '">' . $data->pd_mobil_1 . '</a>';
		$data->pd_email_1		= ( empty($data->pd_email_1) )		? $leertext		: '<a href="mailto:' . $data->pd_email_1 . '">' . $data->pd_email_1 . '</a>';

		$data->pdf_header_text = constant("PDF_HEADER_TEXT");

		$this->load->view('de/datenblatt',$data);
	}
	
	function object_callback_before_insert($post_array) {
		// default Wert in DB
		//$post_array['sd_active']	= '1';

		$post_array['sd_object']		= ucfirst(trim($post_array['sd_object']));
		$post_array['sd_zip']			= "D-" . preg_replace('/\D/','',trim($post_array['sd_zip']));
		$post_array['sd_cty']			= ucfirst(trim($post_array['sd_cty']));
		$post_array['sd_district']		= ucfirst(trim($post_array['sd_district']));
		$post_array['sd_str']			= ucfirst(str_replace(array("straße","strasse"),"str.",trim($post_array['sd_str'])));
		$post_array['sd_hno']			= str_replace(' ','',$post_array['sd_hno']);
		$post_array['sd_open_mo']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_mo']));
		$post_array['sd_open_di']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_di']));
		$post_array['sd_open_mi']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_mi']));
		$post_array['sd_open_do']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_do']));
		$post_array['sd_open_fr']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_fr']));
		$post_array['sd_open_sa']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_sa']));
		$post_array['sd_open_so']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_so']));
		$post_array['sd_contractor']	= strtoupper(trim($post_array['sd_contractor']));

		if ( empty($post_array['sd_object']) ) {
			$this->db->select('fl_beschreibung');
			$this->db->where('fl_name',$post_array['sd_banner']);
			$post_array['sd_object'] = $this->db->get(COUNTRY.'_'.CLIENT.'_beflaggung')->row()->fl_beschreibung;
		}
		if( !empty($post_array['sd_photo_1']) ) {
			$old_name = $post_array['sd_photo_1'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_aussen.jpg";

			$post_array['sd_photo_1'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/objekt-fotos/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/';
			rename( $path . $old_name, $path . $new_name);
		}
		if( !empty($post_array['sd_photo_2']) ) {
			$old_name = $post_array['sd_photo_2'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_shop.jpg";

			$post_array['sd_photo_2'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/objekt-fotos/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/';
			rename( $path . $old_name, $path . $new_name);
		}

		return $post_array;
	}

	function object_callback_after_insert($post_array) {
		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			// country_client_account,       obj,    zip,    cty,    str,    hno,    gps_lat,    gps_lon
			// COUNTRY#CLIENT#sd_acc,  sd_object, sd_zip, sd_cty, sd_str, sd_hno, sd_gps_lat, sd_gps_lon

			$sql = "SELECT COUNT(*) FROM object WHERE country_client_account='" . COUNTRY.'#'.CLIENT . "#" . $post_array['sd_acc'] . "';";
			$object_select = $db_object->query($sql);
			
			if ( $object_select->fetchColumn() == 0 ) {
				$sql ="INSERT INTO object (country_client_account,obj,zip,cty,str,hno,gps_lat,gps_lon) VALUES('" . COUNTRY.'#'.CLIENT . "#" . $post_array['sd_acc'] . "','" . $post_array['sd_object'] . "','".$post_array['sd_zip']."','".$post_array['sd_cty']."','".$post_array['sd_str']."','".$post_array['sd_hno']."','".$post_array['sd_gps_lat']."','".$post_array['sd_gps_lon']."');";
				$object_insert = $db_object->query($sql);
			}
		}
		catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}
	}

	function object_callback_before_update($post_array,$primary_key) {
		$this->db->select('sd_acc');
		$this->db->where('id',$primary_key);
		$old_sd_acc = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row()->sd_acc;

		$post_array['sd_object']		= ucfirst(trim($post_array['sd_object']));
		$post_array['sd_zip']			= "D-" . preg_replace('/\D/','',trim($post_array['sd_zip']));
		$post_array['sd_cty']			= ucfirst(trim($post_array['sd_cty']));
		$post_array['sd_district']		= ucfirst(trim($post_array['sd_district']));
		$post_array['sd_str']			= ucfirst(str_replace(array("straße","strasse"),"str.",trim($post_array['sd_str'])));
		$post_array['sd_hno']			= str_replace(' ','',$post_array['sd_hno']);
		$post_array['sd_open_mo']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_mo']));
		$post_array['sd_open_di']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_di']));
		$post_array['sd_open_mi']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_mi']));
		$post_array['sd_open_do']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_do']));
		$post_array['sd_open_fr']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_fr']));
		$post_array['sd_open_sa']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_sa']));
		$post_array['sd_open_so']		= str_replace(array("-","–"), " - ", str_replace(' ', '', $post_array['sd_open_so']));
		$post_array['sd_contractor']	= strtoupper(trim($post_array['sd_contractor']));

		if ( empty($post_array['sd_object']) ) {
			$this->db->select('fl_beschreibung');
			$this->db->where('fl_name',$post_array['sd_banner']);
			$post_array['sd_object'] = $this->db->get(COUNTRY.'_'.CLIENT.'_beflaggung')->row()->fl_beschreibung;
		}
		if( !empty($post_array['sd_photo_1']) ) {
			$old_name = $post_array['sd_photo_1'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_aussen.jpg";

			$post_array['sd_photo_1'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/objekt-fotos/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/';
			rename( $path . $old_name, $path . $new_name);
		}
		if( !empty($post_array['sd_photo_2']) ) {
			$old_name = $post_array['sd_photo_2'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_shop.jpg";

			$post_array['sd_photo_2'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/objekt-fotos/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/objekt-fotos/';
			rename( $path . $old_name, $path . $new_name);
		}

		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			// country_client_account, obj,       zip,    cty,    str,    hno,    gps_lat,    gps_lon
			// COUNTRY#CLIENT#sd_acc,  sd_object, sd_zip, sd_cty, sd_str, sd_hno, sd_gps_lat, sd_gps_lon

			if ( $post_array['sd_acc'] == $old_sd_acc ) {
				$sql = "UPDATE object SET country_client_account='" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "',obj='" . $post_array['sd_object'] . "',zip='" . $post_array['sd_zip'] . "',cty='" . $post_array['sd_cty'] . "',str='" . $post_array['sd_str'] . "',hno='" . $post_array['sd_hno'] . "',gps_lat='" . $post_array['sd_gps_lat'] . "',gps_lon='" . $post_array['sd_gps_lon'] . "' WHERE country_client_account='" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "';";
			} else {
				$sql = "UPDATE object SET country_client_account='" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "',obj='" . $post_array['sd_object'] . "',zip='" . $post_array['sd_zip'] . "',cty='" . $post_array['sd_cty'] . "',str='" . $post_array['sd_str'] . "',hno='" . $post_array['sd_hno'] . "',gps_lat='" . $post_array['sd_gps_lat'] . "',gps_lon='" . $post_array['sd_gps_lon'] . "' WHERE country_client_account='" . COUNTRY."#".CLIENT."#".$old_sd_acc . "';";
			}
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/debug.txt';
			file_put_contents($path,$sql);
			
			$object_update = $db_object->query($sql);
		}
		catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}			
		
		return $post_array;
	}

	function object_callback_after_update($post_array,$primary_key) {
		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			// country_client_account, obj,       zip,    cty,    str,    hno,    gps_lat,    gps_lon
			// COUNTRY#CLIENT#sd_acc,  sd_object, sd_zip, sd_cty, sd_str, sd_hno, sd_gps_lat, sd_gps_lon

			$sql = "SELECT COUNT(*) FROM object WHERE country_client_account='" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "';";
			$object_select = $db_object->query($sql);

			//$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/debug.txt';
			//file_put_contents($path,$sql,FILE_APPEND);
			
			if ( $object_select->fetchColumn() == 0 ) {
				$sql ="INSERT INTO object (country_client_account,obj,zip,cty,str,hno,gps_lat,gps_lon) VALUES('" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "','" . $post_array['sd_object'] . "','".$post_array['sd_zip']."','".$post_array['sd_cty']."','".$post_array['sd_str']."','".$post_array['sd_hno']."','".$post_array['sd_gps_lat']."','".$post_array['sd_gps_lon']."');";
				$object_insert = $db_object->query($sql);
			}
		}
		catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}
	}

	function object_callback_before_delete($primary_key) {
		$this->db->select('sd_acc');
		$this->db->where('id',$primary_key);
		$sd_acc = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row()->sd_acc;

		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			// country_client_account,       obj,    zip,    cty,    str,    hno,    gps_lat,    gps_lon
			// COUNTRY#CLIENT#sd_acc,  sd_object, sd_zip, sd_cty, sd_str, sd_hno, sd_gps_lat, sd_gps_lon

			$sql = "DELETE FROM object WHERE country_client_account='" . COUNTRY."#".CLIENT."#".$sd_acc . "';";
			$object_delete = $db_object->query($sql);
		}
		catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}

		$base = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT;

		// Objekt-Fotos löschen
		$mask = $base . '/objekt-fotos/KST-' . $sd_acc;
		$dbg_file = __DIR__ . '/../../assets/uploads/debug.txt';
		file_put_contents($dbg_file, $mask . "\n", FILE_APPEND);

		foreach( glob($mask . '*') as $file ) {		
			file_put_contents($dbg_file, $file . "\n", FILE_APPEND);
			unlink($file);
		}

		// Bericht löschen
		$mask = $base . '/alarmtechnik/KST-' . $sd_acc;
		$dbg_file = __DIR__ . '/../../assets/uploads/debug.txt';
		file_put_contents($dbg_file, $mask . "\n", FILE_APPEND);

		foreach( glob($mask . '*') as $file ) {		
			file_put_contents($dbg_file, $file . "\n", FILE_APPEND);
			unlink($file);
		}
	}



	function partner_management() {
		try{
			$crud = new grocery_CRUD();

			$crud->where('sd_active', '1');

			$crud->unset_add();
			$crud->unset_delete();
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Ansprechpartner');

			$state = $crud->getState();
			switch( $state ) {
				case 'export':	$crud->columns('sd_acc','pd_firma','pd_name_1','pd_name_2','pd_telefon','pd_telefax','pd_mobil_1','pd_mobil_2','pd_email_1','pd_email_2','pd_aufschaltung');
								break;
				case 'print':   $crud->columns('sd_acc','pd_firma','pd_name_1','pd_name_2','pd_telefon','pd_telefax','pd_mobil_1','pd_mobil_2','pd_email_1','pd_email_2','pd_aufschaltung');
								break;
				default:		$crud->columns('sd_acc','pd_firma','pd_name_1','pd_telefon','pd_telefax','pd_mobil_1','pd_email_1','pd_aufschaltung','at_dsgvo');
								break;
			}

			$crud->order_by('pd_name_1','asc');
			$crud->fields('sd_acc','sd_bearbeitet_von','sd_bearbeitet_am','pd_firma','pd_name_1','pd_name_2','pd_telefon','pd_telefax','pd_mobil_1','pd_mobil_2','pd_email_1','pd_email_2','pd_aufschaltung');
			$crud->set_read_fields('sd_bearbeitet_von','sd_bearbeitet_am','pd_firma','pd_name_1','pd_name_2','pd_telefon','pd_telefax','pd_mobil_1','pd_mobil_2','pd_email_1','pd_email_2','pd_aufschaltung');

			$crud->display_as('sd_acc',				'Kst.');
			$crud->display_as('pd_firma',			'Firma');
			$crud->display_as('pd_anrede',			'Anrede');
			$crud->display_as('pd_name_1',			'1.Ansprechpartner');
			$crud->display_as('pd_name_2',			'2.Ansprechpartner');
			$crud->display_as('pd_telefon',			'Telefon');
			$crud->display_as('pd_telefax',			'Fax');
			$crud->display_as('pd_mobil_1',			'1.Mobil-Nr.');
			$crud->display_as('pd_mobil_2',			'2.Mobil-Nr.');
			$crud->display_as('pd_email_1',			'1.Email');
			$crud->display_as('pd_email_2',			'2.Email');
			$crud->display_as('pd_aufschaltung',	'Privat-Aufschaltung');
			$crud->display_as('at_dsgvo',			'DSGVO');

			//$crud->callback_column('pd_name_1',  array($this,'_callback_column_pd_name_1'));
			$crud->callback_column('pd_email_1', array($this,'_callback_column_pd_email'));
			$crud->callback_column('pd_email_2', array($this,'_callback_column_pd_email'));

			$crud->set_field_upload('at_dsgvo','assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik');

			$crud->callback_field('pd_name_1', function($value,$primary_key) {
				return '<input id="field-pd_name_1" class="form-control" name="pd_name_1" maxlength="60" type="text" value="' . $value . '">' . ' z.B "Vorname Nachname"  oder  "Nachname, Vorname"';
			});

			$crud->field_type('sd_acc','hidden');
			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));

			$crud->callback_before_update(array($this,'partner_callback_before_update'));
			$crud->callback_after_update(array($this, 'partner_callback_after_update'));

			//if ( $this->ion_auth->in_group("gast") == FALSE ) {
			//	$crud->add_action('Tankstelle','/assets/action-paechter.png','herm/tankstellen_management/edit');
			//}
			$crud->add_action('Datenblatt','/assets/action_pdf.png','','',array($this,'object_action_datenblatt'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Ansprechpartner';

			if ( $state == 'edit' || $state == 'read' ) {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
				$this->db->where('id',$primary_key);
				$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			}

			$this->load->view('de/partner',$output);
		
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_column_pd_name_1($value,$row) {
		$names = explode(" ", $value);
		switch(count($names)) {
			case '3':
				return $names[2] . ", " . $names[0] . " " . $names[1];
				break;			
			case '2':
				return $names[1] . ", " . $names[0];
				break;
			case '1':
				return $names[0]; 
				break;
		}
	}

	function _callback_column_pd_email($value,$row) {
		return "<a href='mailto:" . $value . "' title='e-Mail senden'>$value" . "</a>";
	}

	function partner_callback_before_update($post_array, $primary_key) {
		$post_array['pd_firma']   = trim($post_array['pd_firma']);
		$post_array['pd_name_1']  = trim($post_array['pd_name_1']);
		$post_array['pd_name_2']  = trim($post_array['pd_name_2']);
		$post_array['pd_mobil_1'] = trim($post_array['pd_mobil_1']);
		$post_array['pd_mobil_2'] = trim($post_array['pd_mobil_2']);
		$post_array['pd_email_1'] = trim($post_array['pd_email_1']);
		$post_array['pd_email_2'] = trim($post_array['pd_email_2']);

		if( !empty($post_array['pd_mobil_1']) ) {
			$post_array['pd_mobil_1'] = str_replace(array(" ","/","-"),"",$post_array['pd_mobil_1']);
			$post_array['pd_mobil_1'] = preg_replace("/^0+/","+49",$post_array['pd_mobil_1']);
		}
		if( !empty($post_array['pd_mobil_2']) ) {
			$post_array['pd_mobil_2'] = str_replace(array(" ","/","-"),"",$post_array['pd_mobil_2']);
			$post_array['pd_mobil_2'] = preg_replace("/^0+/","+49",$post_array['pd_mobil_2']);
		}
		return $post_array;
	}

	function partner_callback_after_update($post_array, $primary_key) {
		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			$sql = "UPDATE object SET tsp='" . $post_array['pd_mobil_1'] . "',tsp_ff='" . $post_array['pd_aufschaltung'] . "' WHERE country_client_account='" . COUNTRY."#".CLIENT."#".$post_array['sd_acc'] . "';";
			$object_update = $db_object->query($sql);
		}
			catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}
	}

	function partner_serienbrief() {
		$this->db->select('COUNT(*) AS summe_alle');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$summe_alle = $this->db->get()->row()->summe_alle;

		$this->db->select('COUNT(pd_name_1) AS summe_pd_name');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_active' => '1',
			       'pd_name_1 !=' => ''
			       );
		$this->db->where($where,TRUE);
		$summe_pd_name = $this->db->get()->row()->summe_pd_name;

		$this->db->select('COUNT(pd_email_1) AS summe_pd_email');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_active' => '1',
			       'pd_email_1 !=' => ''
			       );
		$this->db->where($where,TRUE);
		$summe_pd_email = $this->db->get()->row()->summe_pd_email;

		$this->db->select('COUNT(pd_mobil_1) AS summe_pd_mobil');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_active' => '1',
			       'pd_mobil_1 !=' => ''
			       );
		$this->db->where($where,TRUE);
		$summe_pd_mobil = $this->db->get()->row()->summe_pd_mobil;

		$data['summen'] = array(
					'summe_alle'     => $summe_alle,
					'summe_pd_name'  => $summe_pd_name,
					'summe_pd_email' => $summe_pd_email,
					'summe_pd_mobil' => $summe_pd_mobil
				);

		$this->db->select('pd_name_1,sd_str,sd_hno,sd_zip,sd_cty');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_active' => '1',
			       'pd_name_1 !=' => ''
			       );
		$this->db->where($where,TRUE);
		//$this->db->where('sd_active','1');
		//$this->db->where('pd_name_1 !=','');

		$partnerliste = $this->db->get()->result();
		$data['partnerliste'] = $partnerliste;

		/*
		$pa_summe = new stdClass();
		$pa_summe->symbol = "Summe:";
		$pa_summe->anzahl = 0;
		*/
		/*
		foreach($data['partner'] as $value) {
			if( empty($value->pd_name_1) ) {
				$value->pd_name_1 = "nicht erfasst";
			} else {
				$value->sd_contract = "<span title='" . $value->va_beschreibung . "'>" . $value->sd_contract . "</span>";
			}
			$pa_summe->anzahl += $value->va_anzahl;
		}
		$data['pa_summe'] = $va_summe;
		*/

		$data['country_client'] = COUNTRY.'_'.CLIENT;

		$this->load->view('de/serienbrief',$data);
	}



	function vertragsart_management() {
		try{
			$crud = new grocery_CRUD();
			
			if ( $this->ion_auth->is_admin() == FALSE ) {
				$crud->unset_add();
				$crud->unset_delete();
			}
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}
			
			$crud->set_table(COUNTRY.'_'.CLIENT.'_vertragsart');
			$crud->set_subject('Vertragsart');
			
			$crud->columns('va_id','va_kurzform','va_beschreibung');
			$crud->unique_fields('va_kurzform');
			$crud->fields('va_kurzform','va_beschreibung');
			
			$crud->display_as('va_id','id');
			$crud->display_as('va_kurzform','Kurzform');
			$crud->display_as('va_beschreibung','Beschreibung');

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Vertragsart';

			$this->load->view('de/objekt',$output);
			
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}


	function banner_management() {
		try{
			$crud = new grocery_CRUD();
			
			if ( $this->ion_auth->is_admin() == FALSE ) {
				//$crud->unset_add();
				$crud->unset_delete();
			}
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}
			
			$crud->set_table(COUNTRY.'_'.CLIENT.'_beflaggung');
			$crud->set_subject('Beflaggung');
			
			$crud->columns('fl_id','fl_name','fl_symbol','fl_beschreibung');
			$crud->unique_fields('fl_name');
			$crud->fields('fl_active','fl_name','fl_symbol','fl_beschreibung');
			
			$crud->set_field_upload('fl_symbol','assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner');
			
			$crud->display_as('fl_id','id');
			$crud->display_as('fl_active','Status');
			$crud->display_as('fl_name','Name');
			$crud->display_as('fl_symbol','Symbol');
			$crud->display_as('fl_beschreibung','Beschreibung');

			$crud->callback_column('fl_name',        array($this,'_callback_fl_active'));
			$crud->callback_column('fl_symbol',      array($this,'_callback_fl_img_active'));
			$crud->callback_column('fl_beschreibung',array($this,'_callback_fl_active'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject = 'Beflaggung';

			$this->load->view('de/objekt',$output);
			
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_fl_active($value,$row) {
		if( $row->fl_active == '1' ) {
			return $value;
		} else {
			return "<s>$value</s>";
		}
	}

	function _callback_fl_img_active($value,$row) {
		
		$value = ( empty($value) ) ? "unbekannt.png" : $value;
		
		if($row->fl_active == '1') {
			return '<img src="' . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/' . $value . '" title="' . $row->fl_beschreibung . '" height="25px">';
		} else {
			return '<img src="' . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/' . $value . '" title="' . 'inaktiv' . '" height="25px">';
		}
	}

	function mitarbeiter_management() {
		try{
			$crud = new grocery_CRUD();

			if ( $this->ion_auth->is_admin() == FALSE ) {
				$crud->unset_add();
				$crud->unset_delete();
			}
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_mitarbeiter');
			$crud->set_subject('Mitarbeiter');

			$crud->columns('ma_id','ma_anrede','ma_name','ma_kurzform','ma_funktion','ma_telefon','ma_mobil','ma_email');
			$crud->unique_fields('ma_kurzform');
			$crud->fields('ma_active','ma_kategorie','ma_anrede','ma_name','ma_kurzform','ma_funktion','ma_telefon','ma_mobil','ma_email');
			$crud->required_fields('ma_name','ma_kurzform');

			$crud->display_as('ma_id','id');
			$crud->display_as('ma_active','Status');
			$crud->display_as('ma_kategorie','Kategorie');
			$crud->display_as('ma_anrede','Anrede');
			$crud->display_as('ma_name','Name');
			$crud->display_as('ma_kurzform','Kurzform');
			$crud->display_as('ma_funktion','Funktion');
			$crud->display_as('ma_telefon','Telefon');
			$crud->display_as('ma_mobil','Mobil');
			$crud->display_as('ma_email','e-Mail');

			//$crud->callback_column('ma_kategorie',  array($this,'_callback_ma_active'));
			$crud->callback_column('ma_anrede',     array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_name',       array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_kurzform',   array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_funktion',   array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_telefon',    array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_mobil',      array($this,'_callback_column_ma_active'));
			$crud->callback_column('ma_email',      array($this,'_callback_column_ma_email'));

			$crud->field_type('ma_kategorie','dropdown',array('ID'=>'Innendienst','AD'=>'Aussendienst','TA'=>'TechAD'));
			$crud->field_type('ma_anrede','enum',array('Herr','Frau'));

			// Variante ohne DB-feld
			$crud->callback_before_update(array($this,'mitarbeiter_callback_before_update'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Mitarbeiter';

			$this->load->view('de/objekt',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_column_ma_active($value,$row) {
		if ( $row->ma_active == '1' ) {
			return $value;
		} else {
			return "<s>$value</s>";
		}
	}

	function _callback_column_ma_email($value,$row) {
		if( $row->ma_active == '1' ) {
			return "<a href='mailto:" . $value . "' title='e-Mail senden'>$value" . "</a>";
		} else {
			return "<span title='inaktiv'><s>$value</s></span>";
		}
	}

	// Variante ohne zusätzlichem DB-feld
	function mitarbeiter_callback_before_update($post_array,$primary_key) {
		$post_array['ma_name']     = trim($post_array['ma_name']);
		$post_array['ma_funktion'] = trim($post_array['ma_funktion']);
		$post_array['ma_telefon']  = trim($post_array['ma_telefon']);
		$post_array['ma_mobil']    = trim($post_array['ma_mobil']);
		$post_array['ma_email']    = trim($post_array['ma_email']);
		
		//$old_value = $post_array['id_kurzform']; // funktioniert nicht
		$this->db->select('ma_kategorie,ma_kurzform');
		$this->db->where('ma_id',$primary_key);
		$result = $this->db->get(COUNTRY.'_'.CLIENT.'_mitarbeiter')->row();
		$kategorie = $result->ma_kategorie;
		$old_value = $result->ma_kurzform;
		$new_value = $post_array['ma_kurzform'];
		
		switch($kategorie) {
			case 'ID':	$data = array('sd_innendienst' => $new_value);
					$this->db->where('sd_innendienst',$old_value);
					$this->db->update(COUNTRY.'_'.CLIENT.'_objekte',$data);
					break;
			case 'AD':	$data = array('sd_aussendienst' => $new_value);
					$this->db->where('sd_aussendienst',$old_value);
					$this->db->update(COUNTRY.'_'.CLIENT.'_objekte',$data);
					break;
		}

		if( !empty($post_array['ma_mobil']) ) {
			$post_array['ma_mobil'] = str_replace(array(" ","/","-"),"",$post_array['ma_mobil']);
			$post_array['ma_mobil'] = preg_replace("/^0+/","+49",$post_array['ma_mobil']);
		}
		return $post_array;
	}



	function dienstleister_management() {
		try{
			$crud = new grocery_CRUD();

			if ( $this->ion_auth->is_admin() == FALSE ) {
				//$crud->unset_add();
				$crud->unset_delete();
			}
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_dienstleister');
			$crud->set_subject('Dienstleister');

			$crud->columns('dl_id','dl_bereich','dl_firma','dl_name','dl_telefon','dl_fax','dl_email');
			$crud->unique_fields('dl_kurzform');
			$crud->fields('dl_bereich','dl_kurzform','dl_firma','dl_strasse','dl_ort','dl_name','dl_telefon','dl_fax','dl_email','dl_active');
			$crud->required_fields('dl_bereich','dl_kurzform','dl_firma','dl_strasse','dl_ort','dl_email');

			$crud->display_as('dl_id','id');
			$crud->display_as('dl_bereich','Bereich');
			$crud->display_as('dl_kurzform','Kurzform');
			$crud->display_as('dl_firma','Firma');
			$crud->display_as('dl_strasse','Strasse');
			$crud->display_as('dl_ort','Ort');
			$crud->display_as('dl_name','Name');
			$crud->display_as('dl_telefon','Telefon');
			$crud->display_as('dl_fax','Fax');
			$crud->display_as('dl_mobil','Mobil');
			$crud->display_as('dl_email','Email');
			$crud->display_as('dl_active','Status');

			$crud->callback_column('dl_bereich',array($this,'_callback_dl_active'));
			$crud->callback_column('dl_firma',  array($this,'_callback_dl_active'));
			$crud->callback_column('dl_name',   array($this,'_callback_dl_active'));
			$crud->callback_column('dl_telefon',array($this,'_callback_dl_active'));
			$crud->callback_column('dl_fax',    array($this,'_callback_dl_active'));
			$crud->callback_column('dl_email',  array($this,'_callback_dl_email'));

			$crud->field_type('dl_bereich','dropdown',array(
						'AT' => 'Alarm-Technik',
						'KT' => 'Kassen-Technik',
						'LT' => 'Licht-Technik',
						'ST' => 'Schliess-Technik',
						'TT' => 'Tank-Technik',
						'VT' => 'Video-Technik'
						));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Dienstleister';

			$this->load->view('de/objekt',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_dl_active($value,$row) {
		if ( $row->dl_active == '1' ) {
			return $value;
		} else {
			return "<s>$value</s>";
		}
	}

	function _callback_dl_email($value,$row) {
		if($row->dl_active == '1') {
			return "<a href='mailto:" . $value . "' title='e-Mail senden'>$value" . "</a>";
		} else {
			return "<span title='inaktiv'><s>$value</s></span>";
		}
	}


	function object_statistik() {
		$select = 'sd_contract,'.COUNTRY.'_'.CLIENT.'_vertragsart.va_beschreibung,COUNT(sd_contract) AS va_anzahl';
		$this->db->select($select);
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->join( COUNTRY.'_'.CLIENT.'_vertragsart' , COUNTRY.'_'.CLIENT.'_vertragsart.va_kurzform='.COUNTRY.'_'.CLIENT.'_objekte.sd_contract','left');

		$this->db->group_by('sd_contract');
		$this->db->order_by('va_anzahl','desc');
		$query = $this->db->get();
		$data['sd_contract'] = $query->result();

		$va_summe = new stdClass();
		$va_summe->symbol = "Summe:";
		$va_summe->anzahl = 0;

		foreach($data['sd_contract'] as $value) {
			if( empty($value->sd_contract) ) {
				$value->sd_contract = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				$value->sd_contract = "<span title='" . $value->va_beschreibung . "'>" . $value->sd_contract . "</span>";
			}
			$va_summe->anzahl += $value->va_anzahl;
		}
		$data['sd_contract_summe'] = $va_summe;


		$select = 'sd_banner,'.COUNTRY.'_'.CLIENT.'_beflaggung.fl_symbol,'.COUNTRY.'_'.CLIENT.'_beflaggung.fl_beschreibung,COUNT(sd_banner) AS fl_anzahl';
		$this->db->select($select);
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->join(COUNTRY.'_'.CLIENT.'_beflaggung',COUNTRY.'_'.CLIENT.'_beflaggung.fl_name='.COUNTRY.'_'.CLIENT.'_objekte.sd_banner','left');
		$this->db->group_by('sd_banner');
		$this->db->order_by('fl_anzahl','desc');
		$query = $this->db->get();
		$data['beflaggung'] = $query->result();

		/*
		$summe = new stdClass();
		$summe->sd_banner="SUMME";
		$summe->fl_symbol="Summe:";
		$summe->fl_anzahl=0;
		*/
		$fl_summe = new stdClass();
		$fl_summe->symbol = "Summe:";
		$fl_summe->anzahl = 0;
		
		foreach($data['beflaggung'] as $value) {

			$imgpath = '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/';

			$value->fl_beschreibung = ( empty($value->fl_beschreibung) ) ? "unbekannt" : $value->fl_beschreibung;
			$value->fl_symbol       = ( empty($value->fl_symbol) )       ? "<img src='" . $imgpath . "unbekannt.png' title='" . $value->fl_beschreibung . "' width='50'>" : "<img src='" . $imgpath . "$value->fl_symbol' title='" . $value->fl_beschreibung . "' width='50'>";

			/*
			if( empty($value->fl_symbol) ) {
				$value->fl_symbol = "<img src='/assets/uploads/HERM/sd_bannern/unbekannt.png' title='" . $value->fl_beschreibung . "' width='50'>";
			} else {
				$value->fl_symbol = "<img src='/assets/uploads/HERM/sd_bannern/" . $value->fl_symbol . "' title='" . $value->fl_beschreibung . "' width='50'>";
			}
			*/
		
			$fl_summe->anzahl += $value->fl_anzahl;
		}
		$data['beflaggung_summe'] = $fl_summe;


		$this->db->select('sd_state,COUNT(sd_state) AS bl_anzahl,sup_bundeslaender.bl_name');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->join('sup_bundeslaender','sup_bundeslaender.bl_iso='.COUNTRY.'_'.CLIENT.'_objekte.sd_state','left');
		$this->db->group_by('sd_state');
		$this->db->order_by('bl_anzahl','desc');
		$query = $this->db->get();
		$data['bundesland'] = $query->result();

		$bl_summe = new stdClass();
		$bl_summe->symbol = "Summe:";
		$bl_summe->anzahl = 0;

		foreach($data['bundesland'] as $value) {
			if( empty($value->sd_state) ) {
				$value->bl_name = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				$value->bl_name = "<span title='" . $value->bl_name . "'>" . $value->sd_state . "</span>";
			}
			$bl_summe->anzahl += $value->bl_anzahl;
		}
		$data['bundesland_summe'] = $bl_summe;


		$this->db->select('SUBSTRING(sd_zip,3,1) AS sd_zip_bereich,COUNT(*) AS sd_zip_anzahl',FALSE);
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('sd_zip_bereich');
		$this->db->order_by('sd_zip_anzahl','desc');
		$query = $this->db->get();
		$data['postleitzahl'] = $query->result();

		$pl_summe = new stdClass();
		$pl_summe->symbol = "Summe:";
		$pl_summe->anzahl = 0;

		foreach($data['postleitzahl'] as $value) {
			if( !is_numeric($value->sd_zip_bereich) ) {
				$value->sd_zip_bereich = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				$value->sd_zip_bereich = "<span title='D-" . $value->sd_zip_bereich . "'>D-" . $value->sd_zip_bereich . "XXXX</span>";
			}
			
			$pl_summe->anzahl += $value->sd_zip_anzahl;
		}
		$data['postleitzahl_summe'] = $pl_summe;


		$select = 'sd_innendienst,COUNT(sd_innendienst) AS id_anzahl,'.COUNTRY.'_'.CLIENT.'_mitarbeiter.ma_name,'.COUNTRY.'_'.CLIENT.'_mitarbeiter.ma_active';
		$this->db->select($select);
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->join(COUNTRY.'_'.CLIENT.'_mitarbeiter',COUNTRY.'_'.CLIENT.'_mitarbeiter.ma_kurzform='.COUNTRY.'_'.CLIENT.'_objekte.sd_innendienst','left');
		$this->db->group_by('sd_innendienst');
		$this->db->order_by('id_anzahl','desc');
		$query = $this->db->get();
		$data['sd_innendienst'] = $query->result();

		$id_summe = new stdClass();
		$id_summe->symbol = "Summe:";
		$id_summe->anzahl = 0;

		foreach($data['sd_innendienst'] as $value) {
			if( empty($value->sd_innendienst) ) {
				$value->ma_name = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				//switch($value->id_active) {
				//}
				$value->ma_name = "<span title='" . $value->ma_name . "'>" . $value->sd_innendienst . "</span>";
			}
			$id_summe->anzahl += $value->id_anzahl;
		}
		$data['sd_innendienst_summe'] = $id_summe;


		$select = 'sd_aussendienst,COUNT(sd_aussendienst) AS ad_anzahl,'.COUNTRY.'_'.CLIENT.'_mitarbeiter.ma_name';
		$this->db->select($select);
		$this->db->where('sd_active','1');
		$this->db->join(COUNTRY.'_'.CLIENT.'_mitarbeiter',COUNTRY.'_'.CLIENT.'_mitarbeiter.ma_kurzform='.COUNTRY.'_'.CLIENT.'_objekte.sd_aussendienst','left');
		$this->db->group_by('sd_aussendienst');
		$this->db->order_by('ad_anzahl','desc');
		$query = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte');
		$data['sd_aussendienst'] = $query->result();
		
		$ad_summe = new stdClass();
		$ad_summe->symbol = "Summe:";
		$ad_summe->anzahl = 0;

		foreach($data['sd_aussendienst'] as $value) {
			if( empty($value->sd_aussendienst) ) {
				$value->ma_name = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				$value->ma_name = "<span title='" . $value->ma_name . "'>" . $value->sd_aussendienst . "</span>";
			}
			$ad_summe->anzahl += $value->ad_anzahl;
		}
		$data['sd_aussendienst_summe'] = $ad_summe;


		$this->db->select('sd_contractor,COUNT(sd_contractor) AS sc_anzahl');
		$this->db->where('sd_active','1');
		$this->db->group_by('sd_contractor');
		$this->db->order_by('sc_anzahl','desc');
		$query = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte');
		$data['sd_contractor'] = $query->result();

		$sc_summe = new stdClass();
		$sc_summe->symbol = "Summe:";
		$sc_summe->anzahl = 0;

		foreach($data['sd_contractor'] as $value) {
			if( empty($value->sd_contractor) ) {
				$value->sd_contractor = "<span title='nicht erfasst'>n.e.</span>";
			} else {
				$value->sd_contractor = "<span title='" . $value->sd_contractor . "'>" . $value->sd_contractor . "</span>";
			}
			$sc_summe->anzahl += $value->sc_anzahl;
		}
		$data['sd_contractor_summe'] = $sc_summe;

		$data['country_client'] = COUNTRY.'_'.CLIENT;
		$this->load->view('de/statistik_objekt',$data);
	}


	function alarmtechnik_management() {
		try{
			$crud = new grocery_CRUD();

			$crud->where('sd_active', '1');

			$crud->unset_add();
			$crud->unset_delete();
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Alarmtechnik');

			$state = $crud->getState();
			switch( $state ) {
				case 'export':	$crud->columns('sd_acc','sd_object','sd_zip','sd_cty','sd_district','sd_str','sd_hno','at_wartung','at_zentrale','at_zentrale_einbau','at_gsm_vertrag','wd_name','wd_aufschalt_id');
								break;
				case 'print':   $crud->columns('sd_acc','sd_object','sd_zip','sd_cty','sd_district','sd_str','sd_hno','at_wartung','wd_name','wd_aufschalt_id');
								break;
				default:		$crud->columns('sd_acc','sd_object','sd_zip','sd_cty','sd_district','sd_str','sd_hno','at_bericht','at_wartung','at_offene_punkte','wd_name','wd_aufschalt_id','wd_polizei_tel');
								break;
			}

			$crud->order_by('at_wartung','desc');
			// sd_contractor, pd_mobil_1, at_status als hidden --> de_object.db
			$crud->fields('sd_acc','sd_contractor','sd_bearbeitet_von','sd_bearbeitet_am','pd_mobil_1','at_bericht','at_protokoll','at_skizze','at_dsgvo','at_interne_punkte','at_offene_punkte','at_wartung','at_zentrale','at_zentrale_einbau','at_zutritt','at_ip_typ','at_ip_mac','at_ip_adr','at_ip_gw','at_ip_mask','at_gsm_typ','at_gsm_tel','at_gsm_knr','at_gsm_vertrag','at_nebel','wd_name','wd_aufschalt_id','wd_telefon','wd_email','wd_polizei_tel');
			//$crud->required_fields('at_ip_typ','at_gsm_typ');
			$crud->set_read_fields('sd_acc','sd_contractor','sd_bearbeitet_von','sd_bearbeitet_am','pd_mobil_1','at_bericht','at_protokoll','at_skizze','at_dsgvo','at_interne_punkte','at_offene_punkte','at_wartung','at_zentrale','at_zentrale_einbau','at_zutritt','at_ip_typ','at_ip_mac','at_ip_adr','at_ip_gw','at_ip_mask','at_gsm_typ','at_gsm_tel','at_gsm_knr','at_gsm_vertrag','at_nebel','wd_name','wd_aufschalt_id','wd_telefon','wd_email','wd_polizei_tel');

			$crud->display_as('sd_acc','Kst.');
			$crud->display_as('sd_object','Objekt');
			$crud->display_as('sd_zip','PLZ');
			$crud->display_as('sd_cty','Ort');
			$crud->display_as('sd_district','Ortsteil');
			$crud->display_as('sd_str','Strasse');
			$crud->display_as('sd_hno','Nr.');
			$crud->display_as('sd_contractor','Kontraktor');
			$crud->display_as('at_bericht','A.Bericht');
			$crud->display_as('at_protokoll','W.Protokoll');
			$crud->display_as('at_skizze','Lageplan');
			$crud->display_as('at_dsgvo','DSGVO');
			$crud->display_as('at_interne_punkte','Interne Punkte');
			$crud->display_as('at_offene_punkte','Offene Punkte');
			$crud->display_as('at_wartung','Wartung');
			//$crud->display_as('at_status','Status');
			$crud->display_as('at_zentrale','Zentrale');
			$crud->display_as('at_zentrale_einbau','Einbaujahr');
			$crud->display_as('at_zutritt','Scharfschaltung');
			$crud->display_as('at_ip_typ', 'IP-Wählgerät');
			$crud->display_as('at_ip_mac', 'IP-Wählgerät-MAC');
			$crud->display_as('at_ip_adr', 'IP-Wählgerät-IP');
			$crud->display_as('at_ip_gw',  'IP-Wählgerät-GW');
			$crud->display_as('at_ip_mask','IP-Wählgerät-Mask');
			$crud->display_as('at_gsm_typ','GSM-Wählgerät');
			$crud->display_as('at_gsm_tel','GSM-Telefon-Nr.');
			$crud->display_as('at_gsm_knr','GSM-Karten-Nr.');
			$crud->display_as('at_gsm_vertrag','GSM-Vertragsart');
			$crud->display_as('at_nebel','Nebelgerät');
			$crud->display_as('wd_name',        'Wachdienst');
			$crud->display_as('wd_aufschalt_id','WD-ID');
			$crud->display_as('wd_telefon',     'WD-Tel');
			$crud->display_as('wd_email',       'WD-Email');
			$crud->display_as('wd_polizei_tel', 'Polizei');

			$crud->callback_column('sd_acc',			array($this,'_callback_column_sd_acc'));
			$crud->callback_column('at_wartung',		array($this,'_callback_column_at_wartung'));
			$crud->callback_column('at_offene_punkte',	array($this,'_callback_column_at_offene_punkte'));

			$crud->set_field_upload('at_bericht',  'assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik');
			$crud->set_field_upload('at_protokoll','assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik');
			$crud->set_field_upload('at_skizze' ,  'assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik');
			$crud->set_field_upload('at_dsgvo',	   'assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik');

			$crud->callback_field('at_interne_punkte', function($value,$primary_key) {
				return '<textarea style="height: 100px;"            id="field-at_interne_punkte" class="mini-texteditor" name="at_interne_punkte">'.$value.'</textarea>' . " (nicht im Stapeldruck)";
			});
			$crud->callback_field('at_offene_punkte', function($value,$primary_key) {
				return '<textarea style="border: 3px solid violet;" id="field-at_offene_punkte"  class="mini-texteditor" name="at_offene_punkte">'.$value.'</textarea>';
			});
			$crud->callback_field('at_zentrale', function($value,$primary_key) {
				return '<input id="field-at_zentrale" type="text" maxlength="30" style="width:300px;" value="'.$value.'" name="at_zentrale">' . " z.B. Telenot Complex 400H";
			});

			$crud->field_type('sd_acc','hidden');
			$crud->field_type('sd_contractor','hidden');
			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));
			$crud->field_type('pd_mobil_1','hidden');
			$crud->field_type('at_ip_typ','dropdown',array(
						'nicht vorhanden'   => 'Nicht vorhanden',
						'telenot 3116'      => 'Telenot 3116 (ISDN)',
						'telenot 3216'      => 'Telenot 3216 (ISDN)',
						'telenot 3516-1'    => 'Telenot 3516-1 (ISDN/IP)',
						'telenot 3516-2'    => 'Telenot 3516-2 (IP)',
						'telenot 3516-2gsm' => 'Telenot 3516-2 (IP&GSM)',
						'telenot 1516'      => 'Telenot 1516 (IP)',
						'telenot 7516'      => 'Telenot 7516'
						));
			$crud->field_type('at_gsm_typ','dropdown',array(
						'nicht vorhanden'   => 'Nicht vorhanden',
						'Elmes'             => 'Elmes',
						'Visortech'         => 'Visortech',
						'Mops-UE'           => 'Mops-UE',
						'Televisor'         => 'Televisor'
						));
			$crud->field_type('at_gsm_vertrag','dropdown',array(
						// DB               => Anzeige
						'Prepaid'           => 'Prepaid',
						'Vertrag'           => 'Vertrag'
						));

			$crud->callback_before_update(array($this,'alarmtechnik_callback_before_update'));
			$crud->callback_after_update(array($this, 'alarmtechnik_callback_after_update'));
			$crud->callback_after_upload(array($this, 'alarmtechnik_callback_after_upload'));

			// Alternative 1, add_action
			/*
			if (false) {
				$alarm_foto = '/assets/action-fotos_blau.png';
			} else {
				$alarm_foto = '/assets/action-fotos.png';
			}
			$crud->add_action('Fotos',$alarm_foto,'q1/alarmtechnik_fotos');
			*/
			// Alternative 2, erstmal feststellen, dass Fotos vorhanden sind
			//$crud->callback_column('fotos',array($this,'_callback_fotos'));

			$crud->add_action('Aktensicht', '/assets/action_aktensicht.png', COUNTRY.'_'.CLIENT . '/alarmtechnik_action_akten');
			$crud->add_action('Fotos',      '/assets/action_fotos.png',      COUNTRY.'_'.CLIENT . '/alarmtechnik_action_fotos');
			$crud->add_action('Stapeldruck','/assets/action_pdf.png','','',array($this,'alarmtechnik_action_stapeldruck'));
			// Thema Fancy-Box
			//$crud->add_action('GSM',        '/assets/action-gsm.png','herm/gsm');
			//$crud->add_action('GSM',        '/assets/action-gsm.png','','',array($this,'_action_gsm'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Alarmtechnik';

			if ( $state == 'edit' || $state == 'read' ) {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('sd_acc,sd_contract,sd_object,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_contractor');
				$this->db->where('id',$primary_key);
				$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			}

			$this->load->view('de/technik',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_column_at_offene_punkte($value,$row) {
		if( !empty($value) ) {
			$ampel = "ampel-lila.svg";
			$color = "black";
			return "<div style='padding:18px;text-align:left;color:" . $color . ";background-image:url(/assets/ampel/" . $ampel . "); background-size:contain; background-repeat:no-repeat' align='center' title='" . $value . "'>OP</div>";
		} else {
			return "";
		}
	}

	function _callback_column_at_wartung($value,$row) {
		$aktuell = strtotime(date("Y-m-d",time()));
		// wegen Schaltjahr geändert!!
		$wartung = strtotime('+1 year',strtotime($value));
		$differenz = ceil(($wartung - $aktuell)/86400);

		switch(TRUE) {
			case ($differenz > 14):
				$title="Wartung in " . abs($differenz) . " Tagen fällig";
				$ampel="ring-gruen.svg";
				$color="black";
				break;
			case ($differenz > 0):
				$title="Wartung in " . abs($differenz) . " Tagen fällig";
				$ampel="ring-gelb.svg";
				$color="black";
				break;
			case ($differenz == 0):
				$title="Wartung heute fällig";
				$ampel="ring-gelb.svg";
				$color="black";
				break;
			case ($differenz == -1):
				$title="Wartung seit Gestern überfällig";
				$ampel="ring-rot.svg";
				$color="black";
				break;
			// 7300 Tage c.a. 20 Jahre
			case ($differenz < -7300):
				$title="Wartung nicht erfasst";
				$ampel="ring-weiss.svg";
				$color="black";
				$differenz="----";
				break;
			case ($differenz < 0):
				$title="Wartung seit " . abs($differenz) . " Tagen überfällig";
				$ampel="ring-rot.svg";
				$color="black";
				break;
		}
		return "<div style='padding:18px;text-align:left;color:" . $color . ";background-image:url(/assets/ampel/" . $ampel . "); background-size:contain; background-repeat:no-repeat' align='center' title='" . $title . "'>" . $differenz . "</div>";
	}


	function _callback_column_at_status($value,$row) {
		//$at_status = $value;             // liefert "STOERUNG", ... zurück
		//$at_status = $row->at_status;    // liefert "001", ...      zurück

		switch($row->at_status) {
			case "000":
				$title="OK seit $row->at_status_seit";
				$ampel="ampel-gruen.svg";
				$color="#00FF00";
				break;
			case "001":
				$title="STOERUNG seit $row->at_status_seit";
				$ampel="ampel-gelb.svg";
				$color="#FFFF00";
				break;
			case "010":
				$title="TECHNIK-KLAR seit $row->at_status_seit";
				$ampel="ampel-gruen.svg";
				$color="#00FF00";
				break;
			case "011":
				$title="TECHNIK seit $row->at_status_seit";
				$ampel="ampel-gelb.svg";
				$color="#FFFF00";
				break;
			case "100":
				$title="EINBRUCH-KLAR seit $row->at_status_seit";
				$ampel="ampel-gruen.svg";
				$color="#00FF00";
				break;
			case "101":
				$title="EINBRUCH seit $row->at_status_seit";
				$ampel="ampel-rot.svg";
				$color="#FF0000";
				break;
			/*
			case "110":	$title="NOTRUF-KLAR seit $row->at_status_seit";
					$ampel="ampel-gruen.svg";
					$color="#00FF00";
					break;
			*/
			case "111":
				$title="NOTRUF seit $row->at_status_seit";
				$aktuell = strtotime(date("Y-m-d",time()));
				if ( substr($row->at_status_seit,0,10) == $aktuell ) {
				    $ampel = "ampel-rot.svg";
				    $color = "#FF0000";
				} else {
				    $ampel = "ampel-gruen.svg";
				    $color = "#00FF00";
				}
				//$ampel="ampel-rot.svg";
				//$color="#FF0000";
				break;
			case "998":
				$title="GEWERKE beendet seit $row->at_status_seit";
				$ampel="ampel-gruen.svg";
				$color="#00FF00";
				break;
			case "999":
				$title="GEWERKE vor Ort seit $row->at_status_seit";
				$ampel="ampel-blau.svg";
				$color="#0000FF";
				break;
			default:
				$title="nicht erfasst";
				$ampel="ampel-weiss.svg";
				$color="black";
				$row->at_status = "---";
				break;
		}
		return "<div style='padding:18px;text-align:left;color:" . $color . ";background-image:url(/assets/" . $ampel . "); background-size:contain; background-repeat:no-repeat' align='center' title='" . $title . "'>" . $row->at_status . "</div>";
	}

	function alarmtechnik_action_stapeldruck($primary_key, $row) {
		return site_url(COUNTRY.'_'.CLIENT . '/alarmtechnik_stapeldruck/' . $row->id);
	}

	function alarmtechnik_stapeldruck($value) {
		$this->db->select('*');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		$this->db->select('bl_name');
		$this->db->from('sup_bundeslaender');
		$this->db->where('bl_iso',$data->sd_state);
		$data->sd_state = $this->db->get()->row()->bl_name;

		/*
		$xml = file_get_contents(__DIR__ . "/../../xml/KST-10536_objekt.xml");
		$data->xmlobject = new SimpleXMLElement($xml);
		*/

		$leertext = '<font color="#FF0000">X _____________</font>';
		$leerzahl = '<font color="#FF0000">X __</font>';
		$leerbild = '<img src="assets/uploads/' . COUNTRY.'_'.CLIENT . '/banner/unbekannt.png" height="50">';

		$data->sd_object     = ( empty($data->sd_object) )      ? $leertext     : $data->sd_object;
		$data->sd_country    = ( empty($data->sd_country) )     ? $leertext     : $data->sd_country;
		$data->sd_state      = ( empty($data->sd_state) )       ? $leertext     : $data->sd_state;
		$data->sd_zip        = ( empty($data->sd_zip) )         ? $leerzahl     : $data->sd_zip;
		$data->sd_cty        = ( empty($data->sd_district) )    ? $data->sd_cty : $data->sd_cty . ', ' . $data->sd_district;
		$data->sd_str        = ( empty($data->sd_str) )         ? $leertext     : $data->sd_str;
		$data->sd_hno        = ( empty($data->sd_hno) )         ? $leerzahl     : $data->sd_hno;
		$data->sd_open_mo    = ( empty($data->sd_open_mo) )     ? $leertext     : $data->sd_open_mo . " Uhr";
		$data->sd_open_di    = ( empty($data->sd_open_di) )     ? $leertext     : $data->sd_open_di . " Uhr";
		$data->sd_open_mi    = ( empty($data->sd_open_mi) )     ? $leertext     : $data->sd_open_mi . " Uhr";
		$data->sd_open_do    = ( empty($data->sd_open_do) )     ? $leertext     : $data->sd_open_do . " Uhr";
		$data->sd_open_fr    = ( empty($data->sd_open_fr) )     ? $leertext     : $data->sd_open_fr . " Uhr";
		$data->sd_open_sa    = ( empty($data->sd_open_sa) )     ? $leertext     : $data->sd_open_sa . " Uhr";
		$data->sd_open_so    = ( empty($data->sd_open_so) )     ? ""			: $data->sd_open_so . " Uhr";
		$data->sd_contractor = ( empty($data->sd_contractor) )  ? $leertext     : $data->sd_contractor;

		$data->pd_firma      = ( empty($data->pd_firma) )       ? $leertext     : $data->pd_firma;
		$data->pd_name_1     = ( empty($data->pd_name_1) )      ? $leertext     : $data->pd_name_1;
		$data->pd_name_2     = ( empty($data->pd_name_2) )      ? $leertext     : $data->pd_name_2;
		$data->pd_telefon    = ( empty($data->pd_telefon) )     ? $leertext     : '<a href="tel:' . str_replace(array(" ","/","-"),"",$data->pd_telefon) . '">' . $data->pd_telefon . '</a>';
		$data->pd_telefax    = ( empty($data->pd_telefax) )     ? $leertext     : $data->pd_telefax;
		$data->pd_mobil_1    = ( empty($data->pd_mobil_1) )     ? $leertext     : '<a href="tel:'    . $data->pd_mobil_1 . '">' . $data->pd_mobil_1 . '</a>';
		$data->pd_mobil_2    = ( empty($data->pd_mobil_2) )     ? $leertext     : $data->pd_mobil_2;
		$data->pd_email_1    = ( empty($data->pd_email_1) )     ? $leertext     : '<a href="mailto:' . $data->pd_email_1 . '">' . $data->pd_email_1 . '</a>';
		$data->pd_email_2    = ( empty($data->pd_email_2) )     ? $leertext     : '<a href="mailto:' . $data->pd_email_2 . '">' . $data->pd_email_2 . '</a>';

		$data->anzahl_rm                = $leerzahl;
		$data->anzahl_ir                = $leerzahl;
		$data->anzahl_nt                = $leerzahl;
		$data->anzahl_mk                = $leerzahl;
		$data->anzahl_rk                = $leerzahl;

		$data->at_zentrale_einbau       = ( empty($data->at_zentrale_einbau) )     ? $leertext           : $data->at_zentrale_einbau;
		$at_zentrale = explode(" ",trim($data->at_zentrale));
		$at_zentrale_marke = array_shift($at_zentrale);
		switch (true) {
			case ($at_zentrale_marke == "Papp"):
				$at_zentrale_marke = "[X] Papp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Telenot&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Andere:";
				break;
			case ($at_zentrale_marke == "Telenot"):
				$at_zentrale_marke = "[&nbsp;&nbsp;] Papp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[X] Telenot&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Andere:";
				break;
			case ( !empty($at_zentrale_marke) ):
				$at_zentrale_marke = "[&nbsp;&nbsp;] Papp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Telenot&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[X] Andere: " . $at_zentrale_marke;
				break;
			default:
				$at_zentrale_marke = "[&nbsp;&nbsp;] Papp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Telenot&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Andere:";
				break;
		}
		$data->at_zentrale_marke        = $at_zentrale_marke;
		$at_zentrale_typ = implode(" ", $at_zentrale);
		$data->at_zentrale_typ          = ( empty($at_zentrale) ) ? $leertext : $at_zentrale_typ;
		switch(true) {
			case ($data->at_zutritt == "Keyflex"):
				$data->at_zutritt = "[X] Keyflex&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Andere:";
				break;
			case ($data->at_zutritt == "Telenot"):
				$data->at_zutritt = "[&nbsp;&nbsp;] Keyflex&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[X] Andere: " . $data->at_zutritt;
				break;
			default:
				$data->at_zutritt = "[&nbsp;&nbsp;] Keyflex&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Andere:";
				break;
		}

		switch ($data->at_gsm_vertrag) {
			case "Prepaid":
				$data->at_gsm_vertrag = "&nbsp;[X] Prepaid&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Vertrag";
				break;
			case "Vertrag":
				$data->at_gsm_vertrag = "&nbsp;[&nbsp;&nbsp;] Prepaid&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[X] Vertrag";
				break;
			default:
				$data->at_gsm_vertrag = "&nbsp;[&nbsp;&nbsp;] Prepaid&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Vertrag";
				break;
		}

		switch ($data->at_gsm_typ) {
			//case empty($data['at_gsm_typ']):
			case "":
				$data->at_gsm_typ = "&nbsp;[&nbsp;&nbsp;] Nein&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Ja:";
				break;
			case "nicht vorhanden":
				$data->at_gsm_typ = "&nbsp;[X] Nein&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[&nbsp;&nbsp;] Ja:";
				break;
			default:
				$data->at_gsm_typ = "&nbsp;[&nbsp;&nbsp;] Nein&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[X] Ja:&nbsp;&nbsp;" . $data->at_gsm_typ;
				$data->at_gsm_tel = ( empty($data->at_gsm_tel) )                 ? $leertext                 : '<a href="tel:' . $data->at_gsm_tel . '">' . $data->at_gsm_tel . '</a>';
				$data->at_gsm_knr = ( empty($data->at_gsm_knr) )                 ? $leertext                 : $data->at_gsm_knr;
				break;
		}

		$data->wd_name                = ( empty($data->wd_name) )                ? $leertext                 : $data->wd_name;
		$data->wd_aufschalt_id        = ( empty($data->wd_aufschalt_id) )        ? $leertext                 : $data->wd_aufschalt_id;
		$data->wd_telefon             = ( empty($data->wd_telefon) )             ? $leertext                 : '<a href="tel:' . str_replace(array(" ","/","-"),"",$data->wd_telefon) . '">' . $data->wd_telefon . '</a>';
		$data->wd_email               = ( empty($data->wd_email) )               ? $leertext                 : '<a href="mailto:' . $data->wd_email . '">' . $data->wd_email . '</a>';
		$data->wd_code_wort_1         = ( empty($data->wd_code_wort_1) )         ? $leertext                 : $data->wd_code_wort_1;
		$data->wd_code_bed_1          = ( empty($data->wd_code_bed_1) )          ? $leertext                 : $data->wd_code_bed_1;
		$data->wd_code_wort_2         = ( empty($data->wd_code_wort_2) )         ? $leertext                 : $data->wd_code_wort_2;
		$data->wd_code_bed_2          = ( empty($data->wd_code_bed_2) )          ? $leertext                 : $data->wd_code_bed_2;
		$data->wd_polizei_tel         = ( empty($data->wd_polizei_tel) )         ? $leertext                 : '<a href="tel:' . str_replace(array(" ","/","-"),"",$data->wd_polizei_tel) . '">' . $data->wd_polizei_tel . '</a>';

		$data->wd_stoerung_1_aktion   = "Service anrufen";
		$data->wd_stoerung_1_telefon  = ( empty($data->wd_stoerung_1_telefon) )  ? $leertext                 : $data->wd_stoerung_1_telefon;
		$data->wd_stoerung_2_aktion   = "Contractor anrufen";
		$data->wd_stoerung_2_telefon  = ( empty($data->wd_stoerung_2_telefon) )  ? $leertext                 : '<a href="tel:' . $data->wd_stoerung_2_telefon . '">' . $data->wd_stoerung_2_telefon . '</a>';
		$data->wd_einbruch_1_aktion   = "Ansprechpartner anrufen";
		$data->wd_einbruch_1_telefon  = ( empty($data->pd_mobil_1) )             ? $leertext                 : '<a href="tel:' . $data->pd_mobil_1 . '">' . $data->pd_mobil_1 . '</a>';
		$data->wd_einbruch_2_aktion   = "Polizei anrufen";
		$data->wd_einbruch_2_telefon  = ( empty($data->wd_einbruch_2_telefon) )  ? "110"                     : $data->wd_einbruch_2_telefon;
		$data->wd_notruf_1_aktion     = "Objekt anrufen";
		$data->wd_notruf_1_telefon    = ( empty($data->pd_telefon) )             ? $leertext                 : '<a href="tel:' . $data->pd_telefon . '">' . $data->pd_telefon . '</a>';
		$data->wd_notruf_2_aktion     = "Polizei anrufen";
		$data->wd_notruf_2_telefon    = ( empty($data->wd_notruf_2_telefon) )    ? "110"                     : $data->wd_notruf_2_telefon;

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,sd_contractor,at_wartung');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_acc <>', $data->sd_acc);
		$this->db->where('sd_active', '1');
		$data->query = $this->db->get();

		$data->country_client = COUNTRY.'_'.CLIENT;
		$data->token          = COUNTRY.'#'.CLIENT;

		$this->load->view('de/stapeldruck_alarm',$data);
	}



	function alarmtechnik_action_fotos($row) {
		$image_crud = new image_CRUD();

		if ( $this->ion_auth->in_group("gast") ) {
			$image_crud->unset_delete();
			$image_crud->unset_upload();
		}

		$image_crud->set_language('german');
		$image_crud->set_primary_key_field('id');
		$image_crud->set_url_field('url');
		//$image_crud->set_title_field('title');
		$image_crud->set_table(COUNTRY.'_'.CLIENT.'_alarm_fotos')
		->set_relation_field('category_id')
		//->set_ordering_field('priority')
		->set_image_path('assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/fotos');

		$output = $image_crud->render();

		$output->country_client = COUNTRY.'_'.CLIENT;
		$output->subject      = 'Alarmtechnik';
		$output->call_method  = 'alarmtechnik_management';

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
		$this->db->where('id',$row);
		$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();

		$this->load->view('de/fotos',$output);
	}

	function alarmtechnik_action_akten($row) {
		$image_crud = new image_CRUD();

		//$image_crud->unset_delete();
		$image_crud->unset_upload();

		$image_crud->set_language('german');
		$image_crud->set_primary_key_field('id');
		$image_crud->set_url_field('url');
		//$image_crud->set_title_field('title');
		$image_crud->set_table(COUNTRY.'_'.CLIENT.'_alarm_akten');
		$image_crud->set_relation_field('category_id');
		//$image_crud->set_ordering_field('priority');
		$image_crud->set_image_path('assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/akten');

		$output = $image_crud->render();

		$output->country_client	= COUNTRY.'_'.CLIENT;
		$output->subject		= 'Alarmtechnik';
		$output->call_method	= 'alarmtechnik_management';

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
		$this->db->where('id',$row);
		$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();

		$this->load->view('de/akten',$output);
	}

	function alarmtechnik_callback_before_update($post_array,$primary_key) {
		// Problem mit $post_array['sd_acc'],$post_array['sd_contractor']  wenn field type = 'readonly' oder 'invisible'
		//$this->db->select('sd_acc,sd_contractor');
		//$this->db->where('id',$primary_key);
		//$row = $this->db->get('std_objekte')->row();
		//$sd_acc        = $row->sd_acc;

		if( !empty($post_array['at_bericht']) ) {
			$old_name = $post_array['at_bericht'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_bericht.jpg";

			$post_array['at_bericht'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			rename( $path . $old_name, $path . $new_name);
		}
		if( !empty($post_array['at_protokoll']) ) {
			$old_name = $post_array['at_protokoll'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_protokoll.jpg";

			$post_array['at_protokoll'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			rename( $path . $old_name, $path . $new_name);
		}
		if( !empty($post_array['at_skizze']) ) {
			$old_name = $post_array['at_skizze'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_skizze.jpg";

			$post_array['at_skizze'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			rename( $path . $old_name, $path . $new_name);
		} else {
			$new_name = "KST-" . $post_array['sd_acc'] . "_skizze.jpg";
			$post_array['at_skizze'] = $new_name;
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';			
			copy( $path . "skizze-bak.jpg", $path . $new_name);
		}
		if( !empty($post_array['at_dsgvo']) ) {
			$old_name = $post_array['at_dsgvo'];
			$new_name = "KST-" . $post_array['sd_acc'] . "_dsgvo.jpg";

			$post_array['at_dsgvo'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			rename( $path . $old_name, $path . $new_name);
		}

		if( !empty($post_array['at_interne_punkte']) ) {
			//$post_array['at_interne_punkte'] = preg_replace("/\n+|[\r\n]+/", "\n", $post_array['at_interne_punkte']);
			$post_array['at_interne_punkte'] = trim($post_array['at_interne_punkte']);
		}
		if( !empty($post_array['at_offene_punkte']) ) {
			//$post_array['at_offene_punkte'] = preg_replace("/\n+|\r+|[\r\n]+/", "\n", $post_array['at_offene_punkte']);
			$post_array['at_offene_punkte'] = trim($post_array['at_offene_punkte']);
		}
		if( !empty($post_array['at_zentrale']) ) {
			$post_array['at_zentrale'] = ucfirst(strtolower(trim($post_array['at_zentrale'])));
		}
		if( !empty($post_array['at_zutritt']) ) {
			$post_array['at_zutritt'] = ucfirst(strtolower(trim($post_array['at_zutritt'])));
		}
		if( !empty($post_array['at_ip_mac']) ) {
			$post_array['at_ip_mac'] = strtoupper(trim($post_array['at_ip_mac']));
		}
		if( !empty($post_array['at_gsm_tel']) ) {
			$post_array['at_gsm_tel'] = preg_replace("/^0+/","+49",$post_array['at_gsm_tel']);
		}
		if( !empty($post_array['wd_name']) ) {
			$post_array['wd_name'] = strtoupper(trim($post_array['wd_name']));
		}
		if( !empty($post_array['wd_aufschalt_id']) ) {
			$post_array['wd_aufschalt_id'] = trim($post_array['wd_aufschalt_id']);

			$first_chars = trim(substr($post_array['wd_aufschalt_id'], 0, -3));
			$last_chars  = substr($post_array['wd_aufschalt_id'], -3);

			$post_array['wd_aufschalt_id'] = $first_chars.' '. $last_chars;
		}
		return $post_array;
	}

	function alarmtechnik_callback_after_update($post_array,$primary_key) {
		//pd_mobil_1 temporär, bis sie überall erfasst sind
		try {
			$db_object = new PDO('sqlite:assets/uploads/de_object.db', NULL, NULL, array(PDO::ATTR_PERSISTENT=>true));
			$db_object->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

			$sql = "UPDATE object SET tsp='" . $post_array['pd_mobil_1'] . "',maintenance='" . $post_array['at_wartung'] . "',gsm_tel='" . $post_array['at_gsm_tel'] . "',sentry='" . $post_array['wd_name'] . "',ff_id='" . $post_array['wd_aufschalt_id'] . "' WHERE country_client_account='" . COUNTRY.'#'.CLIENT.'#'.$post_array['sd_acc'] . "';";
			$object_update = $db_object->query($sql);
		}
		catch ( PDOException $Exception) {
			echo $Exception->getMessage() . "\n";
		}

		if( !empty($post_array['at_bericht']) ) {
			$date   = date("Y-m-d",time());
			//$old_path = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$old_path = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			$new_path = $old_path . "akten/";

			$old_file = $old_path . $post_array['at_bericht'];
			$mdate    = date("Y-m-d",filemtime($old_file));

			$filename  = pathinfo($old_file, PATHINFO_FILENAME);   // Dateiname ohne Extension
			$extension = pathinfo($old_file, PATHINFO_EXTENSION);  // Extension

			$new_base = $filename. "_" . $mdate . "." . $extension;
			$new_file = $new_path . $new_base;

			if ( !file_exists($new_file) ) {
				$this->load->library('image_moo');

				file_put_contents($old_path . "_debug_at_bericht.txt" , "Date: " . $date . "\n" . "Old_File: " . $old_file . "\n" . "New_File: " . $new_file . "\n" . "New_Base: " . $new_base);

				copy($old_file , $new_file);
				$this->image_moo->load($new_file)->resize(561,800)->save($new_file,true);

				$data = array(
					'url'         => $new_base,
					'title'       => $mdate,
					'category_id' => $primary_key
				);
				$this->db->insert(COUNTRY.'_'.CLIENT.'_alarm_akten',$data);
			}
		}
		if( !empty($post_array['at_protokoll']) ) {
			$date   = date("Y-m-d",time());
			//$old_path = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$old_path = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			$new_path = $old_path . "akten/";

			$old_file = $old_path . $post_array['at_protokoll'];
			$mdate    = date("Y-m-d",filemtime($old_file));

			$filename  = pathinfo($old_file, PATHINFO_FILENAME);   // Dateiname ohne Extension
			$extension = pathinfo($old_file, PATHINFO_EXTENSION);  // Extension

			$new_base = $filename. "_" . $mdate . "." . $extension;
			$new_file = $new_path . $new_base;

			if ( !file_exists($new_file) ) {
				$this->load->library('image_moo');

				file_put_contents($old_path . "_debug_at_protokoll.txt" , "Date: " . $date . "\n" . "Old_File: " . $old_file . "\n" . "New_File: " . $new_file . "\n" . "New_Base: " . $new_base);

				copy($old_file , $new_file);
				$this->image_moo->load($new_file)->resize(561,800)->save($new_file,true);

				$data = array(
					'url'         => $new_base,
					'title'       => $mdate,
					'category_id' => $primary_key
				);
				$this->db->insert(COUNTRY.'_'.CLIENT.'_alarm_akten',$data);
			}
		}
		if( !empty($post_array['at_skizze']) ) {
			$path = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			$file = $path . $post_array['at_skizze'];
			
			list($width, $height) = getimagesize($file);
			if ($height > $width) {
				// Portrait --> Landscape
				$this->load->library('image_moo');
				$this->image_moo->load($file)->rotate(270)->save($file,true);
			}
		}
		if( !empty($post_array['at_dsgvo']) ) {
			$date   = date("Y-m-d",time());
			//$old_path = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . "/assets/uploads/HERM/alarmtechnik/";
			$old_path = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';
			$new_path = $old_path . "akten/";

			$old_file = $old_path . $post_array['at_dsgvo'];
			$mdate    = date("Y-m-d",filemtime($old_file));

			$filename  = pathinfo($old_file, PATHINFO_FILENAME);   // Dateiname ohne Extension
			$extension = pathinfo($old_file, PATHINFO_EXTENSION);  // Extension

			$new_base = $filename. "_" . $mdate . "." . $extension;
			$new_file = $new_path . $new_base;

			if ( !file_exists($new_file) ) {
				$this->load->library('image_moo');

				file_put_contents($old_path . "_debug_at_dsgvo.txt" , "Date: " . $date . "\n" . "Old_File: " . $old_file . "\n" . "New_File: " . $new_file . "\n" . "New_Base: " . $new_base);

				copy($old_file , $new_file);
				$this->image_moo->load($new_file)->resize(561,800)->save($new_file,true);

				$data = array(
					'url'         => $new_base,
					'title'       => $mdate,
					'category_id' => $primary_key
				);
				$this->db->insert(COUNTRY.'_'.CLIENT.'_alarm_akten',$data);
			}
		}
	}

	function alarmtechnik_callback_after_upload($uploader_response, $field_info, $files_to_upload) {
		if ( $field_info->field_name == "at_skizze" ) {
			$path = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/alarmtechnik/';

			file_put_contents($path . "_debug_at_skizze.txt" , '$field_info->field_name:     '.$field_info->field_name     . "\n");
			file_put_contents($path . "_debug_at_skizze.txt" , '$field_info->upload_path:    '.$field_info->upload_path    . "\n",FILE_APPEND);
			file_put_contents($path . "_debug_at_skizze.txt" , '$uploader_response[0]->name: '.$uploader_response[0]->name . "\n",FILE_APPEND);

			//$file = $field_info->upload_path.'/'.$uploader_response[0]->name;
			$file = $path . $uploader_response[0]->name;
			file_put_contents($path . "_debug_at_skizze.txt" , '$file:                       '.$file                       . "\n",FILE_APPEND);

			list($width, $height) = getimagesize($file);
			if ($width > $height) {
				// Landscape
				file_put_contents($path . "_debug_at_skizze.txt" , 'Orientation:                 Landscape' . "\n", FILE_APPEND);
			} else {
				// Portrait or Square
				file_put_contents($path . "_debug_at_skizze.txt" , 'Orientation:                 Portrait' . "\n", FILE_APPEND);

				$this->load->library('image_moo');
				$this->image_moo->load($file)->rotate(270)->save($file,true);
			}
		}
		return true;
	}

	function action_beauftragung($value) {
		$this->db->select('sd_acc,sd_contract,sd_object,sd_state,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,sd_innendienst,sd_aussendienst,sd_bearbeitet_von,sd_bearbeitet_am,pd_telefon,pd_mobil_1,at_offene_punkte');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		/*
		$this->db->select('bl_iso,bl_name');
		$this->db->from('sup_bundeslaender');
		$this->db->where('bl_iso',$data->sd_state);
		$data->bl_name = $this->db->get()->row()->bl_name;
		*/

		$this->load->view('de/action_view_beauftragung',$data);
	}

	function action_beauftragung_email() {
		$auftragszeit    = date('Ymd-Hi');
		//$auftragsdatum = $this->input->post('auftragsdatum');
		$auftragsdatum   = date('d.m.Y');
		//$bearbeitung   = $this->input->post('bearbeitung');
		$bearbeitung     = $this->the_user->first_name . ' ' . $this->the_user->last_name;
		//$durchwahl     = $this->input->post('durchwahl');
		$durchwahl       = $this->the_user->phone;
		//$email         = $this->input->post('email');
		$email           = $this->the_user->email;

		$kostenstelle    = $this->input->post('kostenstelle');
		$leistungsort    = nl2br($this->input->post('leistungsort'));
		$auftragsnummer  = $this->input->post('auftragsnummer');

		$dl_kurzform     = $this->input->post('dienstleister');
		$this->db->select('dl_kurzform,dl_firma,dl_strasse,dl_ort,dl_email');
		$this->db->from(COUNTRY.'_'.CLIENT.'_dienstleister');
		$this->db->where('dl_kurzform',$dl_kurzform);
		$dienstleister  = $this->db->get()->row();
		$anschrift      = $dienstleister->dl_firma   . "<br>" .
		                  $dienstleister->dl_strasse . "<br>" .
		                  $dienstleister->dl_ort;
		$aktivitaet     = $this->input->post('aktivitaet');
		$termin         = $this->input->post('termin');
		$todo           = nl2br($this->input->post('todo'));

		$message  = "<table>\n";
		$message .= "<tr><td><b>Beauftragung</b></td>        <td></td></tr>\n";
		$message .= "<tr><td>Auftragsdatum:</td>             <td>$auftragsdatum</td></tr>\n";
		$message .= "<tr><td>Bearbeitung:</td>               <td>$bearbeitung</td></tr>\n";
		$message .= '<tr><td>Durchwahl:</td>                 <td><a href="tel:'    . $durchwahl . '">' . $durchwahl . '</a></td></tr>' . "\n";
		//$message .= "<tr><td>Email:</td>                     <td>". mailto($email,$email) . "</td></tr>\n";
		$message .= '<tr><td>Email:</td>                     <td><a href="mailto:' . $email     . '">' . $email     . '</a></td></tr>' . "\n";
		$message .= "<tr><td valign='top'>Leistungsort:</td> <td>$leistungsort</td></tr>\n";
		$message .= "<tr><td>Kostenstelle:</td>              <td>$kostenstelle</td></tr>\n";
		$message .= "<tr><td>Auftragsnummer:</td>            <td>$auftragsnummer</td></tr>\n";
		$message .= "<tr><td valign='top'>Dienstleister:</td><td>$anschrift</td></tr>\n";
		$message .= "<tr><td>Aktivität:</td>                 <td>$aktivitaet</td></tr>\n";
		$message .= "<tr><td>Termin:</td>                    <td>$termin</td></tr>\n";
		$message .= "</table>\n";
		$message .= "<br>\n";
		$message .= "<hr>\n";
		$message .= "<b>Bitte um Erledigung folgender Punkte:</b><br><br>\n";
		$message .= $todo . "<br>\n";

		echo $message;


		// create new PDF document
		$pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		// set document information
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor($bearbeitung);
		$pdf->SetTitle('Beauftragung');
		$pdf->SetSubject($aktivitaet);
		$pdf->SetKeywords('Objekt, Beauftragung, Übersicht');

		// set default header data
		//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
		//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, "Beauftragung", "Herm GmbH & Co. KG\nwww.herm.net");
		// set header and footer fonts
		//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setPrintHeader(false);

		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		// set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/ger.php')) {
			require_once(dirname(__FILE__).'/lang/ger.php');
			$pdf->setLanguageArray($l);
		}
		// ---------------------------------------------------------
		// add a page
		$pdf->AddPage();

		$style = array(
		    'position' => '',
		    'align' => 'C',
		    'stretch' => false,
		    'fitwidth' => true,
		    'cellfitalign' => '',
		    'border' => false,
		    'hpadding' => 'auto',
		    'vpadding' => 'auto',
		    'fgcolor' => array(0,0,0),
		    'bgcolor' => false, //array(255,255,255),
		    'text' => true,
		    'font' => 'helvetica',
		    'fontsize' => 8,
		    'stretchtext' => 4
		);
		//    write1DBarcode(        $code,  $type, $x,  $y, $w, $h, $xres, $style, $align)
		$pdf->write1DBarcode($auftragszeit, 'C128', '', '5', '', 18,   0.4, $style,    'N');

		$image_file = '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/banner/logo.png';
		//    Image(      $file, $x,$y,  $w,  $h,$type,$link,$align,$resize,$dpi,$palign,$ismask,$imgmask,$border,$fitbox,$hidden,$fitonpage,$alt,$altimgs)
		$pdf->Image($image_file,160, 5,'30','15','PNG',   '',   '',       2, 300,     '',  false,   false,      0,  false,  false,     false);

		//     Line($x,$y, $x,$y)
		$pdf->Line(15,25,195,25);

		$pdf->Ln(2);

		$pdf->SetFont('helvetica', '', 12);

		//    writeHMTL(    html,   ln,  fill, reseth,  cell, align)
		$pdf->writeHTML($message, true, false,  false, false, '');
		// -----------------------------------------------------------------------------

		//Close and output PDF document
		$dateiname = __DIR__ . '/../../assets/uploads/files/' . "commissioning_" . $auftragszeit . ".pdf";
		$pdf->Output($dateiname,'F');

		$subject = "Beauftragung für " . COUNTRY.'#'.CLIENT . "#" . $kostenstelle;

		//Nachdem pdf generiert worden ist, zusätzlicher Annahme- und Ablehnungsknopf für Email-Body hinzufügen
		$message .= "<hr>";
		$message .= "<a href='mailto:" . $email . "?subject=" . $subject . " bestätigt" . "'>[ Bestätigung ]</a>";
		$message .= "&nbsp;&nbsp;&nbsp;";
		$message .= "<a href='mailto:" . $email . "?subject=" . $subject . " abgelehnt" . "'>[ Ablehnung ]</a>";

		//$this->email->initialize();

		$this->email->from('info@tank-mops.de',$bearbeitung);
		$this->email->to($dienstleister->dl_email);

		//$cc_verteiler = array('sicherheitstechnik-lenz@t-online.de','ptrckmchl@gmail.com');
		$cc_verteiler  = array('ptrckmchl@gmail.com');
		//$this->email->cc($cc_verteiler);

		$bcc_verteiler = array('p.michel@dusira.com');
		$this->email->bcc($bcc_verteiler);

		$this->email->subject($subject);
		$this->email->message($message);
		$this->email->attach($dateiname);
		$this->email->send();
		
		echo "<script>\n";
		echo "alert('Beauftragung ausgelöst');";
		echo "window.close();";
		echo "</script>\n";
	}

	function action_gsm($value) {
		$this->db->select('id,sd_acc,sd_object,sd_zip,sd_cty,sd_district,sd_str,sd_hno');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		$data->token   = COUNTRY.'#'.CLIENT;
		$data->pattern = COUNTRY.'_'.CLIENT;
		
		$this->load->view('de/action_view_gsm',$data);
	}

	function action_gsm_status() {
		$meldung = COUNTRY.'#'.CLIENT . "#" . $this->input->post('sd_acc') . "#" . $this->input->post('status');

		//$debug_file = '/tmp/_debug_gsm_status.txt';
		//file_put_contents($debug_file, $meldung);

		$fp_uz = fsockopen("192.168.2.203", 9468, $errno, $errstr, 10);
		fwrite($fp_uz, $meldung);
		fclose($fp_uz);

		echo "<script>\n";
		echo "alert('Status ausgelöst');";
		echo "window.close();";
		echo "</script>\n";
	}

	function action_gsm_download() {
		$programmierung  = $this->input->post('elmes_code');
		$dateiname       = $this->input->post('filename');

		force_download($dateiname, $programmierung);

		echo "<script>\n";
		echo "alert('Download ausgelöst');";
		echo "window.close();";
		echo "</script>\n";
	}



	function action_tsp($value) {
		$this->db->select('id,sd_acc,pd_firma,pd_name_1,pd_name_2,pd_telefon,pd_telefax,pd_mobil_1,pd_mobil_2,pd_email_1,pd_email_2,pd_aufschaltung');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		$this->load->view('de/action_view_tsp',$data);
	}

	function action_tsp_save($value) {
		$pd_firma   = trim($this->input->post('pd_firma'));
		$pd_name_1  = trim($this->input->post('pd_name_1'));
		$pd_name_2  = trim($this->input->post('pd_name_2'));
		$pd_telefon = trim($this->input->post('pd_telefon'));
		$pd_telefax = trim($this->input->post('pd_telefax'));
		$pd_mobil_1 = str_replace(array(" ","/","-"),"",$this->input->post('pd_mobil_1'));
		$pd_mobil_1 = preg_replace("/^0+/","+49",$pd_mobil_1);
		$pd_mobil_2 = str_replace(array(" ","/","-"),"",$this->input->post('pd_mobil_2'));
		$pd_mobil_2 = preg_replace("/^0+/","+49",$pd_mobil_2);
		$pd_email_1 = trim($this->input->post('pd_email_1'));
		$pd_email_2 = trim($this->input->post('pd_email_2'));

		$data = array(
			'pd_firma'        => $pd_firma,
			'pd_name_1'       => $pd_name_1,
			'pd_name_2'       => $pd_name_2,
			'pd_telefon'      => $pd_telefon,
			'pd_telefax'      => $pd_telefax,
			'pd_mobil_1'      => $pd_mobil_1,
			'pd_mobil_2'      => $pd_mobil_2,
			'pd_email_1'      => $pd_email_1,
			'pd_email_2'      => $pd_email_2,
			'pd_aufschaltung' => $this->input->post('pd_aufschaltung')
		);

		$this->db->where('id',$value);
		$this->db->update(COUNTRY.'_'.CLIENT.'_objekte',$data);

		echo "<script>\n";
		echo "window.close();";
		echo "</script>\n";
	}

	function alarmtechnik_statistik() {
		$this->db->select('at_zentrale,COUNT(at_zentrale) AS zentrale_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_zentrale');
		$this->db->order_by('zentrale_anzahl','desc');
		$query = $this->db->get();
		$data['at_zentrale'] = $query->result();

		$azt_summe = new stdClass();
		$azt_summe->symbol = "Prüfsumme:";
		$azt_summe->anzahl = 0;

		foreach($data['at_zentrale'] as $value) {
			if( empty($value->at_zentrale) ) {
				$value->at_zentrale = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_zentrale == "nicht vorhanden") {
				$value->at_zentrale = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$azt_summe->anzahl += $value->zentrale_anzahl;
		}
		$data['at_zentrale_summe'] = $azt_summe;



		$this->db->select('at_zentrale_einbau,COUNT(at_zentrale_einbau) AS einbau_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_zentrale_einbau');
		$this->db->order_by('at_zentrale_einbau','asc');
		$query = $this->db->get();
		$data['at_zentrale_einbau'] = $query->result();

		$aze_summe = new stdClass();
		$aze_summe->symbol = "Prüfsumme:";
		$aze_summe->anzahl = 0;

		foreach($data['at_zentrale_einbau'] as $value) {
			if( empty($value->at_zentrale_einbau) ) {
				$value->at_zentrale_einbau = "<span title='nicht erfassst'>n.e.</span>";
			}
			if ( $value->at_zentrale_einbau == "nicht vorhanden") {
				$value->at_zentrale_einbau = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$aze_summe->anzahl += $value->einbau_anzahl;
		}
		$data['at_einbau_summe'] = $aze_summe;


		$this->db->select('at_zutritt,COUNT(at_zutritt) AS az_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_zutritt');
		$this->db->order_by('az_anzahl','desc');
		$query = $this->db->get();
		$data['at_zutritt'] = $query->result();

		$atz_summe = new stdClass();
		$atz_summe->symbol = "Prüfsumme:";
		$atz_summe->anzahl = 0;

		foreach($data['at_zutritt'] as $value) {
			if( empty($value->at_zutritt) ) {
				$value->at_zutritt = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_zutritt == "nicht vorhanden") {
				$value->at_zutritt = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$atz_summe->anzahl += $value->az_anzahl;
		}
		$data['at_zutritt_summe'] = $azt_summe;


		$this->db->select('at_ip_typ,COUNT(at_ip_typ) AS ip_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_ip_typ');
		$this->db->order_by('ip_anzahl','desc');
		$query = $this->db->get();
		$data['at_ip_typ'] = $query->result();

		$ait_summe = new stdClass();
		$ait_summe->symbol = "Prüfsumme:";
		$ait_summe->anzahl = 0;

		foreach($data['at_ip_typ'] as $value) {
			if( empty($value->at_ip_typ) ) {
				$value->at_ip_typ = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_ip_typ == "nicht vorhanden") {
				$value->at_ip_typ = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$ait_summe->anzahl += $value->ip_anzahl;
		}
		$data['at_ip_typ_summe'] = $ait_summe;


		$this->db->select('at_ip_mac');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		//$this->db->group_by('at_ip_typ');
		//$this->db->order_by('mac_anzahl','desc');
		$query = $this->db->get();
		$data['at_ip_mac'] = $query->result();

		$data['at_ip_nomac_summe'] = 0;
		$data['at_ip_mac_summe']   = 0;

		$aim_summe = new stdClass();
		$aim_summe->symbol = "Prüfsumme:";
		$aim_summe->anzahl = 0;

		foreach($data['at_ip_mac'] as $value) {
			if( empty($value->at_ip_mac) ) {
				$value->at_ip_mac = "<span title='nicht vorhanden'>n.v.</span>";
				$data['at_ip_nomac_summe']++;
			}
			else {
				$value->at_ip_mac = "<span title='vorhanden'>v.</span>";
				$data['at_ip_mac_summe']++;
			}
			//$aim_summe->anzahl += $value->mac_anzahl;
		}
		$aim_summe->anzahl = $data['at_ip_nomac_summe'] + $data['at_ip_mac_summe'];
		$data['aim_summe'] = $aim_summe;


		$this->db->select('at_gsm_typ,COUNT(at_gsm_typ) AS gsm_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_gsm_typ');
		$this->db->order_by('gsm_anzahl','desc');
		$query = $this->db->get();
		$data['at_gsm_typ'] = $query->result();

		$agt_summe = new stdClass();
		$agt_summe->symbol = "Prüfsumme:";
		$agt_summe->anzahl = 0;

		foreach($data['at_gsm_typ'] as $value) {
			if( empty($value->at_gsm_typ) ) {
				$value->at_gsm_typ = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_gsm_typ == "nicht vorhanden") {
				$value->at_gsm_typ = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$agt_summe->anzahl += $value->gsm_anzahl;
		}
		$data['at_gsm_typ_summe'] = $agt_summe;


		$this->db->select('at_gsm_vertrag,COUNT(at_gsm_vertrag) AS gsm_vertrag_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_gsm_vertrag');
		$this->db->order_by('gsm_vertrag_anzahl','desc');
		$query = $this->db->get();
		$data['at_gsm_vertrag'] = $query->result();

		$agv_summe = new stdClass();
		$agv_summe->symbol = "Prüfsumme:";
		$agv_summe->anzahl = 0;

		foreach($data['at_gsm_vertrag'] as $value) {
			if( empty($value->at_gsm_vertrag) ) {
				$value->at_gsm_vertrag = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_gsm_vertrag == "nicht vorhanden") {
				$value->at_gsm_vertrag = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$agv_summe->anzahl += $value->gsm_vertrag_anzahl;
		}
		$data['at_gsm_vertrag_summe'] = $agv_summe;


		$this->db->select('at_nebel,COUNT(at_nebel) AS nebel_anzahl');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('sd_active','1');
		$this->db->group_by('at_nebel');
		$this->db->order_by('nebel_anzahl','desc');
		$query = $this->db->get();
		$data['at_nebel'] = $query->result();

		$nebel_summe = new stdClass();
		$nebel_summe->symbol = "Prüfsumme:";
		$nebel_summe->anzahl = 0;

		foreach($data['at_nebel'] as $value) {
			if( empty($value->at_nebel) ) {
				$value->at_nebel = "<span title='nicht erfasst'>n.e.</span>";
			}
			if ( $value->at_nebel == "nicht vorhanden") {
				$value->at_nebel = "<span title='nicht vorhanden'>n.v.</span>";
			}
			//switch($value->at_gsm_typ) {
			//}
			$nebel_summe->anzahl += $value->nebel_anzahl;
		}
		$data['at_nebel_summe'] = $nebel_summe;

		$data['country_client'] = COUNTRY.'_'.CLIENT;
		$this->load->view('de/statistik_technik',$data);
	
	}

	function alarmtechnik_karte() {
		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,pd_mobil_1,at_wartung,at_status,at_status_seit,at_offene_punkte');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_gps_lat IS NOT NULL' => null,
			       'sd_gps_lon IS NOT NULL' => null,
			       'sd_active' => '1'
			       );
		$this->db->where($where,TRUE);
		$this->db->order_by('at_offene_punkte', 'ASC');
		$data['objektliste'] = $this->db->get()->result();
		
		$data['country_client'] = COUNTRY.'_'.CLIENT;
		$data['token']          = COUNTRY.'#'.CLIENT;
		$this->load->view('de/karte_alarm',$data);
	}



	function schliesstechnik_management() {
		try{
			$crud = new grocery_CRUD();

			$crud->where('sd_active', '1');

			$crud->unset_add();
			$crud->unset_delete();
			//$crud->unset_back_to_list();
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Schließtechnik');

			$state = $crud->getState();
			switch( $state ) {
				case 'export':	$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','st_wartung');
								break;
				case 'print':   $crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','st_wartung');
								break;
				default:		$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','st_schliessplan','st_wartung');
								break;
			}

			$crud->order_by('st_schliessplan','desc');
			$crud->fields('sd_bearbeitet_von','sd_bearbeitet_am','st_schliessplan','st_offene_punkte','st_wartung');
			$crud->set_read_fields('st_schliessplan','st_offene_punkte','st_wartung');

			$crud->display_as('sd_acc','Kst.');
			$crud->display_as('sd_zip','PLZ');
			$crud->display_as('sd_cty','Ort');
			$crud->display_as('sd_district','Ortsteil');
			$crud->display_as('sd_str','Strasse');
			$crud->display_as('sd_hno','Nr.');
			$crud->display_as('sd_contractor','Contractor');
			$crud->display_as('st_schliessplan','Schliessplan');
			$crud->display_as('st_offene_punkte','Offene Punkte');
			$crud->display_as('st_wartung','Seccor');

			$crud->callback_column('st_wartung',array($this,'_callback_column_at_wartung'));

			$crud->set_field_upload('st_schliessplan','assets/uploads/'.COUNTRY.'_'.CLIENT.'/schliesstechnik');

			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));
			
			$crud->callback_before_update(array($this,'schliesstechnik_callback_before_update'));

			$crud->add_action('Fotos',			'/assets/action_fotos.png', COUNTRY.'_'.CLIENT . '/schliesstechnik_action_fotos');
			$crud->add_action('Stapeldruck',	'/assets/action_pdf.png','','',array($this,'schliesstechnik_action_stapeldruck'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Schließtechnik';
			
			if ( $state == 'edit' || $state == 'read' ) {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('sd_acc,sd_contract,sd_object,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_contractor');
				$this->db->where('id',$primary_key);
				$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			}

			$this->load->view('de/technik',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function schliesstechnik_action_stapeldruck($primary_key, $row) {
		return site_url(COUNTRY.'_'.CLIENT . '/schliesstechnik_stapeldruck/' . $row->id);
	}

	function schliesstechnik_stapeldruck($value) {
		$this->db->select('*');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$this->db->where('id',$value);
		$data = $this->db->get()->row();

		$this->db->select('bl_name');
		$this->db->from('sup_bundeslaender');
		$this->db->where('bl_iso',$data->sd_state);
		$data->sd_state = $this->db->get()->row()->bl_name;

		$leertext = '<font color="#FF0000">X _____________</font>';
		$leerzahl = '<font color="#FF0000">X __</font>';
		$leerbild = '<img src="assets/uploads/' . COUNTRY.'_'.CLIENT . '/banner/unbekannt.png" height="50">';

		$data->sd_object     = ( empty($data->sd_object) )      ? $leertext     : $data->sd_object;
		$data->sd_country    = ( empty($data->sd_country) )     ? $leertext     : $data->sd_country;
		$data->sd_state      = ( empty($data->sd_state) )       ? $leertext     : $data->sd_state;
		$data->sd_zip        = ( empty($data->sd_zip) )         ? $leerzahl     : $data->sd_zip;
		$data->sd_cty        = ( empty($data->sd_district) )    ? $data->sd_cty : $data->sd_cty . ', ' . $data->sd_district;
		$data->sd_str        = ( empty($data->sd_str) )         ? $leertext     : $data->sd_str;
		$data->sd_hno        = ( empty($data->sd_hno) )         ? $leerzahl     : $data->sd_hno;
		$data->sd_open_mo    = ( empty($data->sd_open_mo) )     ? $leertext     : $data->sd_open_mo . " Uhr";
		$data->sd_open_di    = ( empty($data->sd_open_di) )     ? $leertext     : $data->sd_open_di . " Uhr";
		$data->sd_open_mi    = ( empty($data->sd_open_mi) )     ? $leertext     : $data->sd_open_mi . " Uhr";
		$data->sd_open_do    = ( empty($data->sd_open_do) )     ? $leertext     : $data->sd_open_do . " Uhr";
		$data->sd_open_fr    = ( empty($data->sd_open_fr) )     ? $leertext     : $data->sd_open_fr . " Uhr";
		$data->sd_open_sa    = ( empty($data->sd_open_sa) )     ? $leertext     : $data->sd_open_sa . " Uhr";
		$data->sd_open_so    = ( empty($data->sd_open_so) )     ? ""			: $data->sd_open_so . " Uhr";
		$data->sd_contractor = ( empty($data->sd_contractor) )  ? $leertext     : $data->sd_contractor;

		$data->pd_firma      = ( empty($data->pd_firma) )       ? $leertext     : $data->pd_firma;
		$data->pd_name_1     = ( empty($data->pd_name_1) )      ? $leertext     : $data->pd_name_1;
		$data->pd_name_2     = ( empty($data->pd_name_2) )      ? $leertext     : $data->pd_name_2;
		$data->pd_telefon    = ( empty($data->pd_telefon) )     ? $leertext     : '<a href="tel:' . str_replace(array(" ","/","-"),"",$data->pd_telefon) . '">' . $data->pd_telefon . '</a>';
		$data->pd_telefax    = ( empty($data->pd_telefax) )     ? $leertext     : $data->pd_telefax;
		$data->pd_mobil_1    = ( empty($data->pd_mobil_1) )     ? $leertext     : '<a href="tel:'    . $data->pd_mobil_1 . '">' . $data->pd_mobil_1 . '</a>';
		$data->pd_mobil_2    = ( empty($data->pd_mobil_2) )     ? $leertext     : $data->pd_mobil_2;
		$data->pd_email_1    = ( empty($data->pd_email_1) )     ? $leertext     : '<a href="mailto:' . $data->pd_email_1 . '">' . $data->pd_email_1 . '</a>';
		$data->pd_email_2    = ( empty($data->pd_email_2) )     ? $leertext     : '<a href="mailto:' . $data->pd_email_2 . '">' . $data->pd_email_2 . '</a>';

		$data->country_client = COUNTRY.'_'.CLIENT;
		$data->token          = COUNTRY.'#'.CLIENT;

		$this->load->view('de/stapeldruck_schliess',$data);
	}

	function schliesstechnik_action_fotos($row) {
		$image_crud = new image_CRUD();

		$image_crud->set_language('german');
	
		$image_crud->set_primary_key_field('id');
		$image_crud->set_url_field('url');
		$image_crud->set_title_field('title');
		$image_crud->set_table(COUNTRY.'_'.CLIENT.'_schliess_fotos')
			->set_relation_field('category_id')
			->set_ordering_field('priority')
			->set_image_path('assets/uploads/'.COUNTRY.'_'.CLIENT.'/schliesstechnik/fotos');
		
		$output = $image_crud->render();

		$output->country_client	= COUNTRY.'_'.CLIENT;
		$output->subject		= 'Schließtechnik';
		$output->call_method	= 'manage_schliesstechnik';

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
		$this->db->where('id',$row);
		$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();

		$this->load->view('de/fotos',$output);
	}

	function schliesstechnik_callback_before_update($post_array,$primary_key) {
		if( !empty($post_array['st_schliessplan']) ) {
			$this->db->select('id,sd_acc');
			$this->db->where('id',$primary_key);
			$result = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			$id     = $result->id;
			$sd_acc = $result->sd_acc;

			$old_name = $post_array['st_schliessplan'];
			$new_name = "KST-" . $sd_acc . "_schliessplan.jpg";

			$post_array['st_schliessplan'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/schliesstechnik/';
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/schliesstechnik/';
			rename( $path . $old_name, $path . $new_name);
		}
		return $post_array;
	}



	function tanktechnik_management() {
		try{
			$crud = new grocery_CRUD();

			$crud->where('sd_active', '1');

			$crud->unset_add();
			$crud->unset_delete();
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Tanktechnik');

			$state = $crud->getState();
			switch( $state ) {
				case 'export':	$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','tt_eichung_std','tt_eichung_lpg');
								break;
				case 'print':   $crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','tt_eichung_std','tt_eichung_lpg');
								break;
				default:		$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','sd_contractor','tt_admin','tt_eichung_std','tt_eichung_lpg','tt_status');
								break;
			}
			
			$crud->order_by('tt_eichung_std','desc');
			$crud->fields('sd_contractor','sd_bearbeitet_von','sd_bearbeitet_am','tt_admin','tt_status','tt_offene_punkte','tt_eichung_std','tt_eichung_lpg');
			$crud->required_fields('tt_status');
			$crud->set_read_fields('sd_contractor','tt_admin','tt_status','tt_offene_punkte','tt_eichung_std','tt_eichung_lpg');

			$crud->display_as('sd_acc','Kst.');
			$crud->display_as('sd_zip','PLZ');
			$crud->display_as('sd_cty','Ort');
			$crud->display_as('sd_district','Ortsteil');
			$crud->display_as('sd_str','Strasse');
			$crud->display_as('sd_hno','Nr.');
			$crud->display_as('sd_contractor','Contractor');
			$crud->display_as('tt_admin','Admin');
			$crud->display_as('tt_offene_punkte','Offene Punkte');
			$crud->display_as('tt_eichung_std','Geeicht bis');
			$crud->display_as('tt_eichung_lpg','LPG geeicht bis');
			$crud->display_as('tt_status','Status');

			$crud->callback_column($this->_unique_field_name('tech_ad'),array($this,'_callback_column_tt_admin'));
			$crud->callback_column('tt_eichung_std',	array($this,'_callback_column_tt_eichung'));
			$crud->callback_column('tt_eichung_lpg',	array($this,'_callback_column_tt_eichung'));
			$crud->callback_column('tt_status',			array($this,'_callback_column_tt_status'));

			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));
			$crud->field_type('tt_eichung_std','enum',array('2011','2012','2013','2014','2015','2016','2017','2018','2019','2020','2021','2022'));
			$crud->field_type('tt_eichung_lpg','enum',array('2011','2012','2013','2014','2015','2016','2017','2018','2019','2020','2021','2022'));
			$crud->field_type('tt_status','true_false',array('1'=>'AN','0'=>'AUS'));

			$crud->set_primary_key('ma_kurzform',COUNTRY.'_'.CLIENT.'_mitarbeiter');
			$crud->set_relation('tt_admin',COUNTRY.'_'.CLIENT.'_mitarbeiter','{ma_name}');

		
			//$crud->add_action('Not-Aus','/assets/action-notaus.png','','',array($this,'_action_notaus'));
			$crud->add_action('Fotos',			'/assets/action_fotos.png', COUNTRY.'_'.CLIENT.'/tanktechnik_action_fotos');
			$crud->add_action('Stapeldruck',	'/assets/action_pdf.png','','',array($this,'tanktechnik_action_stapeldruck'));

			$output = $crud->render();

			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Tanktechnik';

			if ( $state == 'edit' || $state == 'read' ) {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('sd_acc,sd_contract,sd_object,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_contractor');
				$this->db->where('id',$primary_key);
				$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			}

			$this->load->view('de/technik_tank',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

/*
	function _callback_tt_eichung($value,$row) {
		if ( $value == "0000-00-00" ) {
			$symbol     = "geeicht_bis_0000.png";
			$title      = "Nicht erfasst";
		} else {
			$tt_eichung = date("Y",strtotime($value)) + 2;
			$symbol     = "geeicht_bis_" . $tt_eichung . ".png";
			$title      = "Geeicht bis: " . $tt_eichung;
		}
		return "<img src='" .site_url('../assets/' . $symbol) . "' title='" . $title ."' height='50px'>";
	}
*/

	function _callback_column_tt_admin($value,$row) {
		$link = '';
		if( !empty($value) ) {
			$this->db->select('ma_name,ma_kurzform,ma_email,ma_active');
			$this->db->from(COUNTRY.'_'.CLIENT.'_mitarbeiter');
			$this->db->where('ma_kurzform',$row->tt_admin);
			$result = $this->db->get()->row();

			if($result != NULL) {
				switch($result->ma_active) {
					case '1':	$subject = $row->sd_object . ": " . $row->sd_acc . ", " . $row->sd_cty . ", " . $row->sd_str . " " . $row->sd_hno;
							$link = "<a href='mailto:" . $result->ma_email  . "?subject=" . $subject . "' title='e-Mail an " . $result->ma_name . "'>" . $result->ma_kurzform . "</a>";
							break;
					case '0':	$link = "<span title='$result->ma_name ist inaktiv'>$result->ma_kurzform</span>";
							break;
					default:        $link = '';
							break;
				}
			}
		}
		return $link;
	}

	function _callback_column_tt_eichung($value,$row) {
		if ( $value == "0000" || $value == "" ) {
			return "<div style='padding:18px;text-align:left;background-image:url(/assets/ampel/geeicht_bis_0000.svg);           background-repeat:no-repeat' title='nicht erfasst'>" . $value . "</div>";
		} else {
			return "<div style='padding:18px;text-align:left;background-image:url(/assets/ampel/geeicht_bis_" . $value . ".svg); background-repeat:no-repeat' title='Geeicht bis " . $value . "'>" . $value . "</div>";
		}
	}

	function _callback_column_tt_eichung_std($value,$row) {
		if ( $value == "0000" ) {
			$symbol = "geeicht_bis_0000.png";
			$title  = "Nicht erfasst";
			$image  = "<img src='" . site_url('../assets/ampel/' . $symbol) . "' title='" . $title ."' height='50px'>";
		} else {
			//$tt_eichung = date("Y",strtotime($value));
			$symbol = "geeicht_bis_" . $value . ".png";
			$title  = "Geeicht bis: " . $value;
			$image  = "<img src='" . site_url('../assets/ampel/' . $symbol) . "' title='" . $title ."' height='50px'>";
			if ( $value < date("Y",time()) ) {
				$image  = "<div style='background-color:#F00000;width:50px;padding:0'>" . $image . "</div>";
			}
		}
		return $image;
	}

	function _callback_column_tt_status($value,$row) {
		//$value == $row->tt_status;
		switch($value) {
			case "0":
				$title="Tanktechnik: AUS";
				$ampel="ampel-rot.svg";
				break;
			case "1":
			default:
				$title="Tanktechnik: AN";
				$ampel="ampel-gruen.svg";
				break;
		}
		return "<img title='" . $title . "' src='" . site_url('../assets/ampel/' . $ampel) . "'>";
	}

	function tanktechnik_action_stapeldruck($primary_key, $row) {
		//Geändert wegen mini-mops-1
		return "/pdf/eichdruck-herm.php?id=" . $row->id;
		//return "/pdf/alarmplan-herm.php?kst=" . $row->sd_acc;
	}

	/*
	function tanktechnik_action_notaus($primary_key, $row) {
		
		return "/pdf/toggle-herm.php?id=" . $row->id;
	}

	function tanktechnik_notaus($id) {
		
		$data = array();
		$data['id'] = $id;
		
		$this->load->view('herm/notaus',$data);
	}
	*/

	function tanktechnik_toggle($id) {
		
		$data = array(
		    'tt_status'=>'0'
		);
		
		$this->db->where('id',$id);
		$this->db->update(COUNTRY.'_'.CLIENT.'_objekte',$data);

		redirect(COUNTRY.'_'.CLIENT . '/tanktechnik_management');
	}

	function tanktechnik_action_fotos($row) {
		$image_crud = new image_CRUD();

		$image_crud->set_language('german');
	
		$image_crud->set_primary_key_field('id');
		$image_crud->set_url_field('url');
		$image_crud->set_title_field('title');
		$image_crud->set_table(COUNTRY.'_'.CLIENT.'_tank_fotos')
			->set_relation_field('category_id')
			->set_ordering_field('priority')
			->set_image_path('assets/uploads/'.COUNTRY.'_'.CLIENT.'/tanktechnik/fotos');
		
		$output = $image_crud->render();

		$output->country_client	= COUNTRY.'_'.CLIENT;
		$output->subject		= 'Tanktechnik';
		$output->call_method	= 'tanktechnik_management';

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
		$this->db->where('id',$row);
		$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();

		$this->load->view('de/fotos',$output);
	}

	function tanktechnik_karte() {
		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,sd_contractor,pd_mobil_1,tt_eichung_std,tt_status');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_gps_lat IS NOT NULL' => null,
			       'sd_gps_lon IS NOT NULL' => null,
			       'sd_active' => '1'
			       );
		$this->db->where($where,TRUE);
		$data['objektliste'] = $this->db->get()->result();

		$data['country_client'] = COUNTRY.'_'.CLIENT;
		$data['token']          = COUNTRY.'#'.CLIENT;
		$this->load->view('de/karte_tank',$data);
	}



	function videotechnik_management() {
		try{
			$crud = new grocery_CRUD();

			$crud->where('sd_active', '1');

			$crud->unset_add();
			$crud->unset_delete();
			if ( $this->ion_auth->in_group("gast") ) {
				$crud->unset_edit();
				$crud->unset_export();
			} else {
				$crud->unset_read();
			}

			$crud->set_table(COUNTRY.'_'.CLIENT.'_objekte');
			$crud->set_subject('Videotechnik');

			$state = $crud->getState();
			switch( $state ) {
				case 'export':	$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','vt_ausbau','vt_domain','vt_teamviewer');
								break;
				case 'print':   $crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','vt_ausbau','vt_domain','vt_teamviewer');
								break;
				default:		$crud->columns('sd_acc','sd_zip','sd_cty','sd_district','sd_str','sd_hno','vt_protokoll','vt_ausbau','vt_domain','vt_teamviewer','vt_status');
								break;
			}

			$crud->order_by('sd_cty','asc');
			$crud->fields('sd_bearbeitet_von','sd_bearbeitet_am','vt_ausbau','vt_domain','vt_teamviewer','vt_offene_punkte','vt_protokoll','vt_status');
			$crud->set_read_fields('vt_ausbau','vt_domain','vt_teamviewer','vt_offene_punkte','vt_protokoll','vt_status');

			$crud->display_as('sd_acc','Kst.');
			$crud->display_as('sd_zip','PLZ');
			$crud->display_as('sd_cty','Ort');
			$crud->display_as('sd_district','Ortsteil');			
			$crud->display_as('sd_str','Strasse');			
			$crud->display_as('sd_hno','Nr.');
			$crud->display_as('vt_domain','Domain');
			$crud->display_as('vt_teamviewer','Teamviewer ID');
			$crud->display_as('vt_ausbau','Ausbau');
			$crud->display_as('vt_offene_punkte','Offene Punkte');
			$crud->display_as('vt_protokoll','Protokoll');
			$crud->display_as('vt_status','Status');

			$crud->callback_column('vt_domain' ,array($this,'_callback_column_vt_domain'));
			$crud->callback_column('vt_status' ,array($this,'_callback_column_vt_status'));

			$crud->set_field_upload('vt_protokoll','assets/uploads/'.COUNTRY.'_'.CLIENT.'/videotechnik');

			$crud->field_type('sd_bearbeitet_von', 'hidden', $this->the_user->username);
			$crud->field_type('sd_bearbeitet_am',  'hidden', date("Y-m-d",time()));
			$crud->field_type('vt_ausbau','dropdown',array('0'=>'unbekannt','1'=>'nicht vorhanden','2'=>'analog','3'=>'digital'));
			$crud->field_type('vt_status','dropdown',array(
						// DB               => Anzeige
						'300'               => 'VIDEONAS-OK',
						'301'               => 'VIDEONAS',
						'310'               => 'VIDALARM-OK',
						'311'               => 'VIDALARM',
						'998'               => 'GEWERKE-KLAR',
						'999'               => 'GEWERKE'
						));

			$crud->callback_before_update(array($this,'videotechnik_callback_before_update'));

			$crud->add_action('Fotos','/assets/action_fotos.png', COUNTRY.'_'.CLIENT . '/videotechnik_action_fotos');

			$output = $crud->render();
			
			$output->country_client = COUNTRY.'_'.CLIENT;
			$output->subject        = 'Videotechnik';

			if ( $state == 'edit' || $state == 'read' ) {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('sd_acc,sd_contract,sd_object,sd_zip,sd_cty,sd_district,sd_str,sd_hno,sd_contractor');
				$this->db->where('id',$primary_key);
				$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			}
			
			$this->load->view('de/technik',$output);

		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_column_vt_domain($value,$row) {
		if (!empty($value)) return anchor(prep_url($value),$value);
	}

	function _callback_column_vt_status($value,$row) {
	
		$vt_ausbau = $row->vt_ausbau;
		$vt_domain = $row->vt_domain;
	
		if ( empty($vt_domain) ) {
			$title="Videotechnik: UNBEKANNT";
			$ampel="ampel-weiss.svg";
			return "<img title='" . $title . "' src='" . site_url('../assets/ampel/' . $ampel) . "'>";
		}
	
		switch($value) {
			case "0":
				$title="Videotechnik: AUS";
				$ampel="ampel-rot.svg";
				break;
			case "1":
				$title="Videotechnik: AN";
				$ampel="ampel-gruen.svg";
				break;
			default:
				$title="Videotechnik: nicht erfasst";
				$ampel="ampel-weiss.svg";
				break;
		}
		//return "<a href='" . site_url('../assets/uploads/ampel/' . $value . '.jpg') . "'>$value</a>";
		return "<img title='" . $title . "' src='" . site_url('../assets/ampel/' . $ampel) . "'>";
	}

	function videotechnik_action_fotos($row) {
		$image_crud = new image_CRUD();

		$image_crud->set_language('german');
	
		$image_crud->set_primary_key_field('id');
		$image_crud->set_url_field('url');
		$image_crud->set_title_field('title');
		$image_crud->set_table(COUNTRY.'_'.CLIENT.'_video_fotos')
			->set_relation_field('category_id')
			->set_ordering_field('priority')
			->set_image_path('assets/uploads/'.COUNTRY.'_'.CLIENT.'/videotechnik/fotos');

		$output = $image_crud->render();

		$output->country_client	= COUNTRY.'_'.CLIENT;
		$output->subject		= 'Videotechnik';
		$output->call_method	= 'videotechnik_management';

		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno');
		$this->db->where('id',$row);
		$output->extra = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();

		$this->load->view('de/fotos',$output);
	}

	function videotechnik_callback_before_update($post_array,$primary_key) {
		if( !empty($post_array['vt_protokoll']) ) {
			$this->db->select('id,sd_acc');
			$this->db->where('id',$primary_key);
			$result = $this->db->get(COUNTRY.'_'.CLIENT.'_objekte')->row();
			$id     = $result->id;
			$sd_acc = $result->sd_acc;

			$old_name = $post_array['vt_protokoll'];
			$new_name = "KST-" . $sd_acc . "_protokoll.jpg";

			$post_array['vt_protokoll'] = $new_name;

			// relativer Pfad
			//$path   = $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/assets/uploads/'.COUNTRY.'_'.CLIENT.'/videotechnik/';
			$path   = __DIR__ . '/../../assets/uploads/'.COUNTRY.'_'.CLIENT.'/videotechnik/';
			rename( $path . $old_name, $path . $new_name);
		}
		return $post_array;
	}


	function automatentechnik_karte() {
		$this->db->select('sd_acc,sd_object,sd_zip,sd_cty,sd_str,sd_hno,sd_gps_lat,sd_gps_lon,pd_mobil_1,at_wartung,at_offene_punkte');
		$this->db->from(COUNTRY.'_'.CLIENT.'_objekte');
		$where = array(
			       'sd_gps_lat IS NOT NULL' => null,
			       'sd_gps_lon IS NOT NULL' => null,
			       'sd_active' => '1'
			       );
		$this->db->where($where,TRUE);
		$this->db->order_by('at_offene_punkte', 'ASC');
		$data['objektliste'] = $this->db->get()->result();
		
		$data['country_client'] = COUNTRY.'_'.CLIENT;
		$data['token']          = COUNTRY.'#'.CLIENT;
		$this->load->view('de/karte_automat',$data);
	}



	/* set_relation breaks processing of field with same name returned use of unique_field_name */
	function _unique_field_name($field_name) {
		//This s is because is better for a string to begin with a letter and not with a number
		return 's'.substr(md5($field_name),0,8);
	}

}
?>
