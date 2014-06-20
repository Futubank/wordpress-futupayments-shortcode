<?php
/*
  Plugin Name: Futupayments Shortcode
  Plugin URI: https://github.com/Futubank/wordpress-futupayments-shortcode
  Description: Allows you to use Futubank.com payment gateway with the shortcode button.
  Version: 1.0
*/

//include(dirname(__FILE__) . '/inc/widget.php');
if (!class_exists('FutubankForm')) {
    include(dirname(__FILE__) . '/inc/futubank_core.php');
}

class _FutupaymentsShortcode {
    const DB_VERSION = '1.0';

    const SETTINGS_GROUP = 'futupayments-shortcode-optionz';
    const SETTINGS_SLUG = 'futupayments-shortcode';
    
    const SUCCESS_URL = '/?futupayment-success';
    const FAIL_URL    = '/?futupayment-fail';
    const SUBMIT_URL  = '/?futupayment-submit';

    const TABLE_PREFIX = 'futupayments';

    private $templates_dir;

    function __construct() {
        $this->templates_dir = dirname(__FILE__) . '/templates/';
        add_action('init',  array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('futupayment', array($this, 'futupayment'));
        if (is_admin()) {
            add_action('plugins_loaded',  array($this, 'plugins_loaded'));
            add_action('admin_init', array($this, 'admin_init'));
        }
        add_action('parse_request', array($this, 'parse_request'));
    }

    function plugins_loaded() {
        if (get_site_option('futupayment_db_version') != self::DB_VERSION) {
            $this->create_plugin_tables();
            update_site_option('futupayment_db_version', self::DB_VERSION);
        }
    }

    function parse_request() {
        foreach (array(
            self::SUCCESS_URL => array($this, 'success_page'),
            self::FAIL_URL    => array($this, 'fail_page'),
            self::SUBMIT_URL  => array($this, 'submit_page'),
        ) as $url => $func) {
            if ($_SERVER['REQUEST_URI'] == $url) {
                call_user_func($func);
                exit();
            }
        }
    }

    function myplugin_update_db_check() {
        global $jal_db_version;
        if (get_site_option( 'jal_db_version' ) != $jal_db_version) {
            jal_install();
        }
    }
    
    private function get_invoice_hidden_fields() {
        return array(
            'amount',
            'currency',
            'description',
            'page_url',
        );
    }

    function futupayment($atts) {
        $ff = $this->get_futubank_form();

        if (!$ff) {
            return __('FUTUPAYMENT ERROR: plugin is not configured', 'futupayments_shortcode');
        }

        $atts = shortcode_atts(array(
            'amount'      => 0,
            'currency'    => 'RUB',
            'description' => '',
        ), $atts);

        if (!$atts['amount']) {
            return __('FUTUPAYMENT ERROR: amount required', 'futupayments_shortcode');
        }

        if (!$atts['currency']) {
            return __('FUTUPAYMENT ERROR: currency required', 'futupayments_shortcode');
        }

        if (!$atts['description']) {
            return __('FUTUPAYMENT ERROR: description required', 'futupayments_shortcode');
        }

        $atts['page_url'] = get_home_url() . $_SERVER['REQUEST_URI'];
        
        $h = array();
        foreach ($this->get_invoice_hidden_fields() as $k) {
            $h[$k] = $atts[$k];
        }
        $atts['signature'] = $ff->get_signature($h);

        $options = $this->get_options();

        return (
            '<form action="' . self::SUBMIT_URL . '" method="post">' .
                FutubankForm::array_to_hidden_fields($atts) .
                '<p class="submit">' .
                    '<input type="submit" name="submit" class="button button-primary" value="' . esc_attr($options['pay_button_text']) . '">' .
                '</p>' .
            '</form>'
        );
    }

    function init() {
        load_plugin_textdomain('futupayments_shortcode', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            __('Test mode', 'futupayments_shortcode'), 
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
        add_settings_field(
            'pay_button_text', 
            __('Pay button text', 'futupayments_shortcode'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'pay_button_text')
        );
    }

    function settings_intro_text() {
        # FIXME
        echo ''; 
    }

    function char_field($args) {
        $options =  $this->get_options();
        echo '<input name="' . self::SETTINGS_GROUP . '[' . $args['id'] . ']"' . 
             ' type="text" size="40" value="' . esc_attr($options[$args['id']]) . '">';
    }

    function boolean_field($args) {
        $options =  $this->get_options();
        echo '<input name="' . self::SETTINGS_GROUP . '[' . $args['id'] . ']"' . 
             ' type="checkbox" value="on" ' . checked($options[$args['id']], 'on', false) . '>';
    }

    private function get_futubank_form() {
        $options = $this->get_options();
        if ($options['merchant_id'] && $options['secret_key']) {
            return new FutubankForm($options['merchant_id'], $options['secret_key'], $options['test_mode']);
        } else {
            return false;
        }
    }
    
    private function get_options() {
        $result = get_option(self::SETTINGS_GROUP);
        if (!$result) {
            $result = array();
        }
        foreach (array(
            'merchant_id'     => '',
            'secret_key'      => '',
            'success_url'     => 'https://secure.futubank.com/success',
            'fail_url'        => 'https://secure.futubank.com/fail',
            'test_mode'       => 'on',
            'pay_button_text' => __('Pay from card'),
        ) as $k => $v) {
            $result[$k] = self::get($result, $k, $v);
        }
        return $result;
    }

    private function get_new_order_id() {
        return time();
    }

    private function submit_page() {
        $ff = $this->get_futubank_form() or die(__('FUTUPAYMENT ERROR: plugin is not configured', 'futupayments_shortcode'));
        $h = array();
        foreach ($this->get_invoice_hidden_fields() as $k) {
            $h[$k] = self::get($_POST, $k);
        }

        if ($ff->get_signature($h) != self::get($_POST, 'signature')) {
            die(__('FUTUPAYMENT ERROR: incorrect data', 'futupayments_shortcode'));
        }

        $options = $this->get_options();
        $data = $ff->compose(
            $_POST['amount'],
            $_POST['currency'],
            $this->get_new_order_id(),
            self::get($_POST, 'client_email', ''),
            self::get($_POST, 'client_name', ''),
            self::get($_POST, 'client_phone', ''),
            $options['success_url'],
            $options['fail_url'],
            $_POST['page_url'],
            '',
            $_POST['description']
        );
        include $this->templates_dir . 'submit.php';
    }

    private function success_page() {
        $ff = $this->get_futubank_form() or die(__('FUTUPAYMENT ERROR: plugin is not configured', 'futupayments_shortcode'));
        echo 'success!';
    }

    private function fail_page() {
        $ff = $this->get_futubank_form() or die(__('FUTUPAYMENT ERROR: plugin is not configured', 'futupayments_shortcode'));
        echo 'fail!';
    }

    private function create_plugin_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $prefix = $wpdb->prefix . self::TABLE_PREFIX;
        dbDelta("
            CREATE TABLE `${prefix}_payment` (
                `id` integer AUTO_INCREMENT NOT NULL,
                `creation_datetime` datetime NOT NULL,
                `transaction_id` bigint NOT NULL,
                `testing` bool NOT NULL,
                `amount` numeric(10, 2) NOT NULL,
                `currency` varchar(3) NOT NULL,
                `order_id` varchar(128) NOT NULL,
                `state` varchar(10) NOT NULL,
                `message` longtext NOT NULL,
                `meta` longtext NOT NULL,
                UNIQUE (`state`, `transaction_id`)
            );
        ");
        dbDelta("
            CREATE TABLE `${prefix}_order` (
                `id` integer AUTO_INCREMENT NOT NULL,
                `creation_datetime` datetime NOT NULL,
                `amount` numeric(10, 2) NOT NULL,
                `currency` varchar(3) NOT NULL,
                `description` longtext NOT NULL,
                `client_email` varchar(120) NOT NULL,
                `client_name` varchar(120) NOT NULL,
                `client_phone` varchar(30) NOT NULL
            );
        ");
    }

    ## helpers ##

    private static function get(array $hash, $key, $default=null) {
        if (array_key_exists($key, $hash)) {
            return $hash[$key];
        } else {
            return $default;
        }
    }
}

new _FutupaymentsShortcode;
