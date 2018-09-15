<?php

class Admin_Controller extends CI_Controller {

	//Class-wide variable to store user object in.
	protected $the_user;
	protected $the_group;
	//protected $the_browser;

	public function __construct() {

		parent::__construct();

		$this->load->helper('url'); // sonst Fehler 500: 'call to undefined function redirect()'

		$data = new stdClass(); // sonst Warnung: 'Creating default object form empty value'

		/*
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$data->the_browser = "old_browser";
		} else {
			$data->the_browser = "new_browser";
		}
		*/

		//Check if user is in admin group
		if ( $this->ion_auth->is_admin() ) {

			//Put User in Class-wide variable
			$this->the_user  = $this->ion_auth->user()->row();
			//$this->the_group = $this->ion_auth->get_users_groups()->row()->name;
			$this->the_group = $this->ion_auth->get_users_groups()->result();

			//Store user in $data
			$data->the_user  = $this->the_user;
			$data->the_group = $this->the_group;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

class Members_Controller extends CI_Controller {

	protected $the_user;
	protected $the_group;
	//protected $the_browser;

	public function __construct() {

		parent::__construct();

		$this->load->helper('url'); // sonst Fehler 500: 'call to undefined function redirect()'

		$data = new stdClass(); // sonst Warnung: 'Creating default object form empty value'

		/*
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$data->the_browser = "old_browser";
		} else {
			$data->the_browser = "new_browser";
		}
		*/

		// $group_name = $this->ion_auth->get_users_groups()->row()->name;
		// if( in_array('tamoil',$group_name) )
		$group = array('de_herm','de_lenz','de_makita','de_q1','de_tamoil','de_willer');

		if( $this->ion_auth->in_group($group) ) {

			//Put User in Class-wide variable
			$this->the_user  = $this->ion_auth->user()->row();
			//$this->the_group = $this->ion_auth->get_users_groups()->row()->name;
			$this->the_group = $this->ion_auth->get_users_groups()->result();

			//Store user in $data
			$data->the_user  = $this->the_user;
			$data->the_group = $this->the_group;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

class Q1_Controller extends CI_Controller {

	protected $the_user;
	protected $the_group;
	protected $the_browser;

	public function __construct() {

		parent::__construct();

		$data = new stdClass(); // sonst Warnung: 'Creating default object form empty value'

		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$data->the_browser = "old_browser";
		} else {
			$data->the_browser = "new_browser";
		}


		if($this->ion_auth->in_group('q1')) {

			//Put User in Class-wide variable
			$this->the_user = $this->ion_auth
						->user()
						->row();
			$this->the_group = $this->ion_auth->get_users_groups()->row()->name;

			//Store user in $data
			$data->the_user  = $this->the_user;
			$data->the_group = $this->the_group;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

class Willer_Controller extends CI_Controller {

	protected $the_user;
	protected $the_browser;

	public function __construct() {

		parent::__construct();

		$this->load->helper('url'); // sonst Fehler 500: 'call to undefined function redirect()'

		$data = new stdClass(); // sonst Warnung: 'Creating default object form empty value'

		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$data->the_browser = "old_browser";
		} else {
			$data->the_browser = "new_browser";
		}

		// $group_name = $this->ion_auth->get_users_groups()->row()->name;
		// if( in_array('tamoil',$group_name) )
		if($this->ion_auth->in_group('tamoil')) {

			//Put User in Class-wide variable
			$this->the_user = $this->ion_auth->user()->row();

			//Store user in $data
			$data->the_user = $this->the_user;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

class Lenz_Controller extends CI_Controller {

	protected $the_user;
	protected $the_browser;

	public function __construct() {

		parent::__construct();

		$data = new stdClass(); // sonst Warnung: 'Creating default object form empty value'

		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$data->the_browser = "old_browser";
		} else {
			$data->the_browser = "new_browser";
		}


		if($this->ion_auth->in_group('lenz')) {

			//Put User in Class-wide variable
			$this->the_user = $this->ion_auth
						->user()
						->row();

			//Store user in $data
			$data->the_user = $this->the_user;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

class Common_Auth_Controller extends CI_Controller {

	protected $the_user;
	protected $the_browser;

	public function __construct() {

		parent::__construct();

		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8.') !== false) {
			$this->the_browser = "old_browser";
		}

		if($this->ion_auth->logged_in()) {
	
			//Put User in Class-wide variable
			$this->the_user = $this->ion_auth
						->user()
						->row();

			//Store user in $data
			$data->the_user = $this->the_user;

			//Load $the_user in all views
			$this->load->vars($data);
		}
		else {
			redirect('/');
		}
	}
}

?>
