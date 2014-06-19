<?php
/*
  Plugin Name: Futupayments Shortcode
  Plugin URI: https://github.com/Futubank/wordpress-futupayments-shortcode
  Description: Allows you to use Futubank.com payment gateway with the shortcode button.
  Version: 1.0
*/

//include(dirname(__FILE__) . '/inc/widget.php');
include(dirname(__FILE__) . '/inc/futubank_core.php');

class _FutupaymentsShortcode {
    const SETTINGS_GROUP = 'futupayments-shortcode-settings1';
    const SETTINGS_SLUG = 'futupayments-shortcode';

    function __construct() {
        add_action('init',  array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
        }
    }

    function init() {
        load_plugin_textdomain('futupayments_shortcode', false, dirname(plugin_basename(__FILE__)) . '/languages' );
    }

    function admin_menu() { 
        add_options_page(
            __('Payments via Futubank.com', 'futupayments_shortcode'),
            'Futupayments Shortcode',
            'manage_options',
            self::SETTINGS_SLUG, 
            array($this, 'settings_page')
        );
    }

    function settings_page() { ?>
        <form action="options.php" method="post">
            <?php settings_fields(self::SETTINGS_GROUP); ?>
            <?php do_settings_sections(self::SETTINGS_SLUG); ?>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo __('Save Changes'); ?>">
            </p>
        </form>
    <?php }

    function admin_init() {        
        register_setting(self::SETTINGS_GROUP, self::SETTINGS_GROUP);
        add_settings_section(
            self::SETTINGS_GROUP, 
            __('Payments via Futubank.com', 'futupayments_shortcode'), 
            array($this, 'settings_intro_text'),
            self::SETTINGS_SLUG
        );
        add_settings_field(
            'merchant_id', 
            __('Merchant ID', 'futupayments_shortcode'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'merchant_id')
        );
        add_settings_field(
            'secret_key', 
            __('Secret key', 'futupayments_shortcode'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'secret_key')
        );
        add_settings_field(
            'test_mode', 
            __('Test mode1', 'futupayments_shortcode'), 
            array($this, 'boolean_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'test_mode')
        );
        add_settings_field(
            'success_url', 
            __('Success URL', 'futupayments_shortcode'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'success_url')
        );
        add_settings_field(
            'fail_url', 
            __('Fail URL', 'futupayments_shortcode'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'fail_url')
        );
    }

    function settings_intro_text() {
        # FIXME
        echo ''; 
    }

    private function get_options() {
        return get_option(self::SETTINGS_GROUP, array(
            'merchant_id' => '',
            'secret_key'  => '',
            'success_url' => 'https://secure.futubank.com/success',
            'fail_url'    => 'https://secure.futubank.com/fail',
            'test_mode'   => 'on',
        ));
    }

    function char_field($args) {
        $options =  $this->get_options();
        echo '<input name="' . self::SETTINGS_GROUP . '[' . $args['id'] . ']"' . 
             ' type="text" size="40" value="' . $options[$args['id']] . '">';
    }

    function boolean_field($args) {
        $options =  $this->get_options();
        echo '<input name="' . self::SETTINGS_GROUP . '[' . $args['id'] . ']"' . 
             ' type="checkbox" value="on" ' . checked($options[$args['id']], 'on', false) . '>';
    }
}

new _FutupaymentsShortcode;

/*

//  	if(is_admin()) {
//  		if(in_array(basename($_SERVER['PHP_SELF']), array('post.php', 'page.php', 'page-new.php', 'post-new.php'))){
// 		   	add_action('admin_footer',  'add_futupayments_shortcode_popup');
// 		}
//  	}

//  	$options = get_option('futupayments_shortcode-settings-group');
// 	if (strpos($_SERVER["REQUEST_URI"], '/futupayments_shortcode/res.php') !== false) {
//  		if (strtoupper(md5($_POST['OutSum'].":".$_POST['InvId'].":".$options['futupayments_shortcode_key2'].":shpemail=".$_POST['shpemail'].":shpfirstname=".$_POST['shpfirstname'].":shpsku=".$_POST['shpsku']))==$_POST['SignatureValue'])
	 
// 	 if($options['futupayments_shortcode_send']==1){
// 	$mail=str_replace(array('[+email+]','[+sku+]','[+name+]','[+description+]','[+price+]'),array($_REQUEST['shpemail'],$_REQUEST['shpsku'],$_REQUEST['shpfirstname'],$_REQUEST['shpdescription'],$_POST['OutSum']),$options['futupayments_shortcode_email']);
// 	wp_mail($_REQUEST['shpemail'], 'Subscription create', '<p>'.$mail.'</p>');
// 	}
// 	  die('OK'.$_POST['InvId']);
// 	}
// 	elseif(strpos($_SERVER["REQUEST_URI"], '/futupayments_shortcode/success.php')!==false )
// 	{
// 	wp_redirect($options['futupayments_shortcode_success_url']);
// 	exit();
// 	}
// 	elseif(strpos($_SERVER["REQUEST_URI"], '/futupayments_shortcode/fail.php')!==false )
// 	{
// 	wp_redirect($options['futupayments_shortcode_fail_url']);
// 	exit();
// 	}
// 	elseif(strpos($_SERVER["REQUEST_URI"], '/futupayments_shortcode/send.php')!==false )
// 	{

// 	 $hash='';
// 	 foreach($_POST as $key=>$value)
// 	 {
// 	  if(strpos($key,'a_')!==false)
// 	    $hash.=$value.":";
// 	 }
// 	 if(md5($hash.$options['futupayments_shortcode_sitepass'])!=$_REQUEST['hash'])
// 	   return;
// 	 $options = get_option('futupayments_shortcode-settings-group');
// 	 $action_adr = 'https://merchant.roboxchange.com/Index.aspx';
// 	 if($options['futupayments_shortcode_test']==1)
// 		$action_adr = 'http://test.robokassa.ru/Index.aspx';
// 	 if(!isset($_POST['a_price']) && isset($_POST['price']))
// 		$_POST['a_price']=$_POST['price'];
// 	 $summ=number_format($_POST['a_price'], 2, '.', '');
// 	 $orderid=time();
// 	 $rksk_path=plugin_dir_path(__FILE__);
// 	 include($rksk_path.'inc/ReflectionTypeHint.php');
// 	 include($rksk_path.'inc/UTF8.php');
// 	//echo utf8_encode($_POST['a_desc']);die("test2");
// 	 $signature=md5($options['futupayments_shortcode_merchant'].":".$summ.":".$orderid.":".$options['futupayments_shortcode_key1'].":shpdescription=".UTF8::convert_from($_POST['a_description']).":shpemail=".UTF8::convert_from($_POST['shpemail']).":shpfirstname=".UTF8::convert_from($_POST['shpfirstname']).":shpsku=".UTF8::convert_from($_POST['a_sku']));
// 	 $args =array(
// 			// Merchant
// 			'MrchLogin' => $options['futupayments_shortcode_merchant'],
					
// 			// Session
// 			'Culture' => 'ru',
// 			'Desc'=>$_POST['a_description'],		
// 			// Order
// 			'OutSum' => $summ,
// 			'InvId' => $orderid,
// 			'Encoding'=>'utf-8',
// 			'SignatureValue' => $signature,
// 			"shpdescription"=>UTF8::convert_from($_POST['a_description']),
// 			"shpfirstname" => UTF8::convert_from($_POST["shpfirstname"]),
// 			"shpemail" => UTF8::convert_from($_POST["shpemail"]),
// 			"shpsku"=>UTF8::convert_from($_POST['a_sku']),
// 			);
// 	 wp_register_style( 'RobokassaStylesheet', plugins_url('styles.css', __FILE__) );
// 	 wp_enqueue_style( 'RobokassaStylesheet' );
// 	 $url=$action_adr."?";
// 	 $urlpar=array();
// 	 $form='<div id="futupayments_shortcodebox" style="display:none;"><form id="rkform" action="'.$action_adr.'" method="post">';
// 	 foreach($args as $key=>$value)
// 	 {
// 	  $form.='<input type="hidden" name="'.$key.'" value="'.$value.'">';
// 	 }
// 	 $form.="<input type='submit'>";
// 	 $form.='</form></div>';
// 	 $form.='<script>document.getElementById("rkform").submit();</script>';
// 	 echo $form;
// }
// 
// }


add_shortcode('futupayments_shortcode', 'futupayments_shortcode_robokassa_sc' );

add_action('media_buttons', 'rksk_add_icon', 11);

function rksk_add_icon() {
	$icon_url = network_site_url('/wp-content/plugins/robokassa-shortcode/img/icon.png');
	//<a href="#TB_inline?width=480&inlineId=select_gravity_form" class="thickbox" id="add_gform" title="' . __("Add Gravity Form", 'gravityforms') . '">
 	echo '<a href="#TB_inline?width=480&inlineId=insert_robokassa_shortcode" class="thickbox" id="insert_futupayments_shortcode" title="' . __("Insert futupayments_shortcode shortcode", 'futupayments_shortcode') . '">';
 	echo "<img src='{$icon_url}' /></a>";
}


function futupayments_shortcode_robokassa_sc($attr) {
	 // $fid=rand();
	 // $options = get_option('futupayments_shortcode-settings-group');
	 // wp_register_style( 'RobokassaStylesheet', plugins_url('styles.css', __FILE__) );
	 // wp_enqueue_style( 'RobokassaStylesheet' );
	 // $form='<div id="futupayments_shortcodebox'.$fid.'" style=" display: none;"><form id="rkform" action="/futupayments_shortcode/send.php" method="post" >';
	 // $form.=__('Name','futupayments_shortcode').':<input name="shpfirstname"><br />';
	 // $form.=__('email','futupayments_shortcode').':<input name="shpemail"><br />';
	 // if(!isset($attr['price']) || $attr['price']=='') {
	 // 	$form.=__('Price','futupayments_shortcode').':<input name="price"><br />';
	 // }
	 // $hash='';
	 // foreach($attr as $key=>$value) {
	 //  	if($value!='') {
	 //  		$form.="<input type='hidden' name='a_{$key}' value='{$value}'>";
	 //  	}
	 // }

	 // $hash=md5(implode(':',$attr).":".$options['futupayments_shortcode_sitepass']);
	 // $form.="<input type='hidden' name='hash' value='{$hash}'>";
	 // $form.="<input class='rksksend' type='submit' value='Pay'>";
	 // $form.="<input type='button' class='rcskcancel' onclick='document.getElementById(\"futupayments_shortcodebox{$fid}\").style.display=\"none\";document.getElementById(\"rkbutton{$fid}\").style.display=\"inline-block\"' value='Cancel'>";
	 // $form.='</form></div>';
	 // $form.="<a id='rkbutton{$fid}' href='#' onclick='if(document.getElementById(\"futupayments_shortcodebox{$fid}\").style.display==\"block\"){document.getElementById(\"futupayments_shortcodebox{$fid}\").style.display=\"none\"}else{document.getElementById(\"futupayments_shortcodebox{$fid}\").style.display=\"block\";document.getElementById(\"rkbutton{$fid}\").style.display=\"none\"};return false;' class='classname'>Robokassa</a>";
	 // return $form;
}


function add_futupayments_shortcode_popup() { ?>
    <script>
    	function InsertForm(){
		var sku = jQuery("#futupayments_shortcode_sku").val();
		var sku_val = (sku == '')?'': " sku=\"" + sku +"\"";
		var description = jQuery("#futupayments_shortcode_description").val();
		var description_val = (description == '')?'': " description=\"" + description +"\"";
		var price = jQuery("#futupayments_shortcode_price").val();
		var price_val = (price == '')?'': " price=\"" + price +"\"";
		
		//[rk_button price="100" sku="test" description="Test payment"]
                window.send_to_editor("[rk_button " + sku_val + description_val + price_val + "]");
    }
    </script>

    <div id="insert_robokassa_shortcode" style="display:none;">
		<div class="wrap">
        	<input id="futupayments_shortcode_sku"  /> <label for="futupayments_shortcode_sku"><?php _e("SKU", "futupayments_shortcode"); ?></label><br />
            <input id="futupayments_shortcode_description"  /> <label for="futupayments_shortcode_description"><?php _e("Description", "futupayments_shortcode"); ?></label><br />                
			<input id="futupayments_shortcode_price"  /> <label for="futupayments_shortcode_price"><?php _e("Price", "futupayments_shortcode"); ?></label><br />
        	<input type="button" class="button-primary" value="Add shortcode" onclick="InsertForm();"/>&nbsp;&nbsp;&nbsp;
                    <a class="button" style="color:#bbb;" href="#" onclick="tb_remove(); return false;"><?php _e("Cancel", "gravityforms"); ?></a>  
		</div>
    </div>
<?php }
*/
