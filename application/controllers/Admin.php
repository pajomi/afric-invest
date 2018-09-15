<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
 
class Admin extends Admin_Controller {

	function __construct() {
		parent::__construct();
	
		$this->load->database();
		//$this->db = $this->load->database('main',true);
	
		// folgende Zeilen über config/autoload.php abgewickelt
		//$this->load->helper('url');
		//$this->load->helper('form');
	
		/*
		$this->load->library('ion_auth'); // Test von autoload.php
		if(!$this->ion_auth->logged_in()) {
			redirect('auth/login', 'refresh');
		}
		*/
	
		// $this->load->library('session');
		$this->load->library('grocery_CRUD');
		$this->load->library('image_CRUD');
	}
 
	public function index() {
		$this->load->view('admin/index');
	}

	function user_management() {
		try{
			$crud = new grocery_CRUD();

			/*
			$user = $this->ion_auth->user();
			if ( $user->email != 'ptrckmchl@gmail.com' ) {
				$crud->unset_add();
				$crud->unset_edit();
				$crud->unset_delete();
			}
			*/

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
			//$crud->unset_back_to_list();

			$crud->set_table('users');
			$crud->set_subject('Benutzer-Konto');

			$crud->columns('username','email','first_name','last_name','phone','last_login','ip_address');
			$crud->order_by('username','desc');
			$crud->fields('active','username','password','salt','gruppe','email','first_name','last_name','company','phone');
			$crud->required_fields('username','email');

			//$crud->field_type('password','password');
			$crud->field_type('password','readonly');
			$crud->field_type('salt','invisible');
			$crud->field_type('gruppe','multiselect');

			$crud->set_relation_n_n('gruppe','users_groups','groups','user_id','group_id','name');

			$crud->display_as('password','Passwort');
			$crud->display_as('active','Aktiv');
			$crud->display_as('first_name','Vorname');
			$crud->display_as('last_name','Nachname');
			$crud->display_as('phone','Telefon');

			//$crud->callback_column('id',         array($this,'_callback_user_active'));
			$crud->callback_column('username',   array($this,'_callback_user_active'));
			$crud->callback_column('gruppe',     array($this,'_callback_user_active'));
			$crud->callback_column('first_name', array($this,'_callback_user_active'));
			$crud->callback_column('last_name',  array($this,'_callback_user_active'));
			$crud->callback_column('phone',      array($this,'_callback_user_phone'));
			$crud->callback_column('email',      array($this,'_callback_user_email'));

			$crud->callback_column('last_login',array($this,'_callback_last_login'));
			//$crud->callback_column('ip_address',array($this,'_callback_ip_address'));

			//$crud->callback_before_insert(array($this,'encrypt_password_callback'));
			//$crud->callback_before_update(array($this,'encrypt_password_callback'));
			//$crud->callback_edit_field('password',array($this,'decrypt_password_callback'));

			$output = $crud->render();

			$extra = array();
			$extra['subject']  = 'Benutzer';
			$extra['url']      = 'admin/user_management';
			$extra['state']    = $crud->getState();

			/*
			if ( $extra['state'] == 'edit') {
				$primary_key = $crud->getStateInfo()->primary_key;
				$this->db->select('id,ip_address');
				$this->db->where('id',$primary_key);
				$query = $this->db->get('users')->row();
				$extra['query'] = $query;
			}
			*/

			$output->extra = $extra;

			$this->load->view('admin/admin',$output);
			
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_last_login($value,$row) {
		return date("d.m.Y",$value);
	}

	function _callback_ip_address($value,$row) {
		$ipReal='';

		if ( !empty($value) ) {
			//$ipPacked = '75f1382f';
			$ipPacked = bin2hex($value);
			
			$ipSegments = array();
			for ($i = 0; $i < 8; $i += 2) {
				$ipSegments[] = hexdec($ipPacked[$i] . $ipPacked[$i+1]);
			}
			$ipReal = implode('.', $ipSegments);
			//var_dump($ipReal); //string(13) "117.241.56.47"
		}
		return $ipReal;
	}


	function encrypt_password_callback($post_array, $primary_key = null)
	{
		//$this->load->library('encrypt');

		//$key = 'super-secret-key';
		//$post_array['password'] = $this->encrypt->encode($post_array['password'], $key);

		//$post_array['password'] = sha1($post_array['password']);
		//$post_array['password'] = hash_password($post_array['password'], FALSE, TRUE);
		$post_array['password'] = $this->ion_auth->hash_code($post_array['password']);
		
		return $post_array;
	}

	function decrypt_password_callback($value)
	{
		$this->load->library('encrypt');

		$key = 'super-secret-key';
		$decrypted_password = $this->encrypt->decode($value, $key);
		//return "<input type='password' name='password' value='$decrypted_password' />";
		return "<input type='password' name='password' value='$value' />";
	}

	function _callback_user_active($value,$row) {
		if ( $row->active == '1' ) {
			return $value;
		} else {
			return "<s>$value</s>";
		}
	}

	function _callback_user_email($value,$row) {
		if( $row->active == '1' ) {
			return "<a href='mailto:" . $value . "' title='e-Mail senden'>$value" . "</a>";
		} else {
			return "<span title='inaktiv'><s>$value</s></span>";
		}
	}

	function _callback_user_phone($value,$row) {
		if( $row->active == '1' ) {
			return "<a href='tel:" . str_replace(array(" ","/","-"),"",$value) .   "' title='Anrufen'>$value" . "</a>";
		} else {
			return "<span title='inaktiv'><s>$value</s></span>";
		}
	}


	function group_management() {
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
			//$crud->unset_back_to_list();

			$crud->set_table('groups');
			$crud->set_subject('Gruppe');

			$crud->columns('name','description','benutzer');
			$crud->order_by('name','asc');
			$crud->fields('name','description','benutzer');

			$crud->field_type('benutzer','multiselect');

			$crud->set_relation_n_n('benutzer','users_groups','users','group_id','user_id','username');

			$crud->display_as('description','Beschreibung');

			$output = $crud->render();

			$extra = array();
			$extra['subject']  = 'Gruppen';
			$extra['url']      = 'admin/group_management';
			$extra['state']    = $crud->getState();

			if ( $extra['state'] == 'edit') {
				$primary_key = $crud->getStateInfo()->primary_key;
				//$this->db->select('pd_vorname,pd_nachname,pd_telefon,pd_telefax');
				//$this->db->where('id',$primary_key);
				//$query = $this->db->get('std_tankstellen')->row();
				//$extra['query'] = $query;
			}

			$output->extra = $extra;

			$this->load->view('admin/admin',$output);
			
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}


	function gsm_management() {
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

			$crud->unset_back_to_list();

			$crud->set_table('gsm_aufschaltung');
			$crud->set_subject('GSM-Karte');

			$crud->columns('id','mandant','ort','objekt','tel_nr','karten_nr','typ');
			$crud->fields('mandant','ort','objekt','tel_nr','karten_nr','typ');

			$crud->order_by('objekt','asc');

			$crud->field_type('mandant','dropdown',array('LENZ'=>'LENZ','Q1'=>'Q1','TAMOIL'=>'TAMOIL','WILLER'=>'WILLER'));
			$crud->field_type('typ',    'dropdown',array('VERTRAG'=>'VERTRAG','PREPAID'=>'PREPAID','PÄCHTER'=>'PÄCHTER'));

			//$crud->set_relation_n_n('benutzer','users_groups','users','group_id','user_id','username');

			$crud->display_as('objekt','Wo');
			$crud->display_as('tel_nr','Telefon-Nr.');
			$crud->display_as('karten_nr','Karten-Nr.');
			$crud->display_as('typ','Vertragsart');

			$crud->callback_before_insert(array($this,'_callback_before_update_gsm'));
			$crud->callback_before_update(array($this,'_callback_before_update_gsm'));

			$output = $crud->render();

			$extra = array();
			$extra['subject']  = 'GSM';
			$extra['url']      = 'admin/gsm_management';
			$extra['state']    = $crud->getState();

			if ( $extra['state'] == 'edit') {
				$primary_key = $crud->getStateInfo()->primary_key;
				//$this->db->select('pd_vorname,pd_nachname,pd_telefon,pd_telefax');
				//$this->db->where('id',$primary_key);
				//$query = $this->db->get('std_tankstellen')->row();
				//$extra['query'] = $query;
			}

			$output->extra = $extra;

			$this->load->view('admin/admin',$output);
			
		}catch(Exception $e){
			show_error($e->getMessage().' --- '.$e->getTraceAsString());
		}
	}

	function _callback_before_update_gsm($post_array,$primary_key) {
		if( !empty($post_array['tel_nr']) ) {
			$post_array['tel_nr'] = preg_replace("/^0+/","+49",$post_array['tel_nr']);
		}
		return $post_array;
	}
}

/* End of file admin.php */
/* Location: ./application/controllers/admin.php */
