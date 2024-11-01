<?php
/*
Plugin Name: Simple JSON-LD Header Adder
Description: A simple plugin that allows you to add json+ld data to the header of your home page only!
Version: 20181006
Author: Bruce Hearder
Author URI: https://brucehearder.com
*/


bh_simplejsonld::get_instance();

class bh_simplejsonld  {
  private $siteurl='';
  private $plugintitle='';
  private static $instance;


  /* If an instance hasn't been created and set to $instance
   create an instance and set it to $instance.
  */
  public static function get_instance() {
    if ( null == self::$instance ) {
        self::$instance = new self;
      }
      return self::$instance;
  }

  public function __construct() {
    $this->plugintitle='Simple JSON-LD';
    $this->handle = str_replace('.php','',basename(__FILE__));

    /* Define hooks */
    add_action('admin_menu', array($this, 'admin_menu'));
    register_uninstall_hook(__FILE__,array($this, 'uninstall_plugin'));
    add_action ("wp_head", array($this, "wp_head"), 99,0);
    add_action('admin_head', array($this, 'admin_head'));
  }

  /* Define some admin styles for the error messages and input fields.
  All styled are constrained to be used only within the
  JSONLD_ADDER div
  */
  function admin_head() {

    echo '<style>
    #JSONLD_ADDER .errors {
      padding: 15px;
      border: 1px solid #000;
      font-weight:bold
    }
    #JSONLD_ADDER .errors p:first-child {
      color: #000;
    }
    #JSONLD_ADDER .submit_btn {
      padding: 15px;
      border: 1px solid #000;
      font-weight:bold;
      min-width: 150px;
    }
    </style>';
  }

  /* Insert the option value into the pages head
     Only insert if option non-blank, and is the home page only
  */
  function wp_head() {
    $opt = get_option($this->optionname.'_jsonld')?
           get_option($this->optionname.'_jsonld'):
           '';

    if ($opt != '' && (is_front_page() || is_home())) {
      echo '<script type="application/ld+json">'.$opt.'</script>';
    }
  }

  /* When plugin is uninstalled, remove option value to reduce DB clutter */
  function uninstall_plugin() {
    delete_option($this->handle.'_jsonld');
  }

  /* Provide a "menu" item on the Wordpress sidebar */
  function admin_menu() {
    global $current_user, $menu;
    add_options_page($this->plugintitle, $this->plugintitle,
    'manage_options', $this->handle.'_admin' , array($this, 'show_admin') );
  }

  /* Function takes a string and determines if it is valid JSON or not */
  function isJson($string) {
   json_decode($string);
   return (json_last_error() == JSON_ERROR_NONE);
  }


  function show_admin() {
    /* Define form submittion URL */
    $formurl = admin_url().'admin.php?page='.$this->handle.'_admin';
    $err = isset($_GET['err'])?intval($_GET['err']):-1;

    /*
      Lets check if we are recieving a POST, if so, do the following:

      1. Check if a nonce is defined in the POST
      2. Verify the nonce is valid
      3. Check the current user is authorized to do this
      4. Strip all tags and slashes from the POST['code'] value.
      5. Check that the POST['code'] value is valid JSON

      If all of the above pass, then update option value to POST['code']

      If one or more of the above fail, then delete the option value.
    */

    /* Possible error messages */
    $errmsg = array('The supplied JSON appears to be invalid<br />
    Validate your JSON using a tool like
    <a target="new" href="https://jsonlint.com">https://jsonlint.com</a>
    and then paste into the box below again.',
    'No data provided.');

     if (current_user_can( 'administrator' )) {
      if (isset($_POST) && isset($_POST['nonce']) ) {
        $err='&err=1';
        $nonce = isset($_POST['nonce'])?trim(sanitize_text_field($_POST['nonce'])):'';
        if (wp_verify_nonce( $nonce, $this->handle.'_admin' ) ) {
          $opt = stripslashes(strip_tags($_POST['code']));
          /*
            If code is not present, or is empty, return error code 1
          */
          if (!array_key_exists('code', $_POST) || trim($_POST['code'] =='')) {
            $err='&err=1';
          }
          else if ($this->isJson(stripslashes($opt))) {
            /* Code snippet is valid JSON */
            update_option($this->optionname.'_jsonld', $opt);
            $err='';
          } else {
            /* Code snippet is not valid, so return error code 0 */
            $err='&err=0';
            delete_option($this->optionname.'_jsonld');
          }
          /* Redirect back to the input form */
          echo '<script>window.location="'.$formurl.$err.'";</script>';
          die;
        }
      }
      /* Create a nonce for the form */
      $nonce = wp_create_nonce($this->handle.'_admin');

      /* Check if there is an already existing option_value */
      $opt = get_option($this->optionname.'_jsonld')?stripslashes(get_option($this->optionname.'_jsonld')):'';

      echo '<div class="wrap" id="JSONLD_ADDER">';

      if ($err>=0 && $err <= count($errmsg)) {
         echo '<div class="errors"><p>'.$errmsg[$err].'</p></div><br>';
      }

      echo '<form name="'.$this->handle.'" method="post" action="'.esc_url($formurl).'">
      <input type="hidden" name="nonce" value="'.$nonce.'">
      <h2>Paste your JSON+LD code in the box below and then press submit.<h2>
      <p>Search Google for JSON+LD generator to create your code snippet</p>
      <textarea name="code" class="widefat" rows="15">'.$opt.'</textarea>
      <input class="submit_btn" type="submit" name="submit_btn" value="Submit" />
      </form>';
    } else {
      echo '<div class="wrap" id="JSONLD_ADDER">
          <div class="errors">
            <p>You are not authorized to use this feature</p>
          </div>
        </div>';
    }
  }
}
