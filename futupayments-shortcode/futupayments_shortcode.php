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


class FutupaymentsShortcodeCallback extends AbstractFutubankCallbackHandler {
    private $plugin;
    function __construct(FutupaymentsShortcode $plugin) {
        $this->plugin = $plugin;
    }
    protected function get_futubank_form() { 
        return $this->plugin->get_futubank_form(); 
    }
    protected function load_order($order_id) { 
        return $this->plugin->load_order();
    }
    protected function get_order_currency($order) {
        return $order['currency'];
    }
    protected function get_order_amount($order) {
        return $order['amount'];
    }
    protected function is_order_completed($order) {
        return $order['status'] == FutupaymentsShortcode::STATUS_PAID;
    }
    protected function mark_order_as_completed($order, array $data) {
        $order['status'] = FutupaymentsShortcode::STATUS_PAID;
        $order['meta'] = $data['meta'];
        return $this->plugin->save_order($order);
    }
    protected function mark_order_as_error($order, array $data) {
        $order['status'] = FutupaymentsShortcode::STATUS_ERROR;
        $order['meta'] = $data['meta'];
        return $this->plugin->save_order($order);   
    }
}


class FutupaymentsShortcode {
    const DB_VERSION     = '2';

    const SETTINGS_GROUP = 'futupayments-shortcode-optionz';
    const SETTINGS_SLUG  = 'futupayments-shortcode';
    
    const SUCCESS_URL    = '/?futupayment-success';
    const FAIL_URL       = '/?futupayment-fail';
    const SUBMIT_URL     = '/?futupayment-submit';
    const CALLBACK_URL   = '/?futupayment-callback';
    
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_PAID    = 'paid';
    const STATUS_ERROR   = 'error';

    private $order_table;
    private $order_table_format;
    private $templates_dir;
    private $invoice_hidden_fields;
    private $change_status = true;

    function __construct() {
        global $wpdb;
        
        $db_prefix = $wpdb->prefix . 'futupayments_';    
        $this->order_table = $db_prefix . 'order';
        
        $this->order_table_format = array(
            '%s',  # creation_datetime
            '%s',  # amount
            '%s',  # currency 
            '%s',  # description 
            '%s',  # client_email 
            '%s',  # client_name 
            '%s',  # client_phone 
            '%s',  # status
            '%s',  # meta
        );
        
        $this->invoice_hidden_fields =array(
            'amount',
            'currency',
            'description',
            'cancel_url',
        );

        $this->templates_dir = dirname(__FILE__) . '/templates/';

        add_action('init',  array($this, 'init'));
        add_shortcode('futupayment', array($this, 'futupayment'));
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('plugins_loaded', array($this, 'plugins_loaded'));
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
            self::SUCCESS_URL  => array($this, 'success_page'),
            self::FAIL_URL     => array($this, 'fail_page'),
            self::SUBMIT_URL   => array($this, 'submit_page'),
            self::CALLBACK_URL => array($this, 'callback_page'),
        ) as $url => $func) {
            if ($_SERVER['REQUEST_URI'] == $url) {
                call_user_func($func);
                exit();
            }
        }
    }

    function futupayment($atts) {
        $ff = $this->get_futubank_form();

        if (!$ff) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments');
        }

        $atts = shortcode_atts(array(
            'amount'      => 0,
            'currency'    => 'RUB',
            'description' => '',
        ), $atts);

        if (!$atts['amount']) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('amount required', 'futupayments');
        }

        if (!$atts['currency']) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('currency required', 'futupayments');
        }

        if (!$atts['description']) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('description required', 'futupayments');
        }

        $atts['cancel_url'] = get_home_url() . $_SERVER['REQUEST_URI'];
        $h = array();
        foreach ($this->invoice_hidden_fields as $k) {
            $h[$k] = $atts[$k];
        }
        $atts['signature'] = $ff->get_signature($h);

        $options = $this->get_options();

        return (
            '<form action="' . self::SUBMIT_URL . '" method="post">' .
                FutubankForm::array_to_hidden_fields($atts) .
                '<p class="submit">' .
                    '<input type="submit" name="submit" class="button button-primary"' .
                    ' value="' . esc_attr($options['pay_button_text']) . '">' .
                '</p>' .
            '</form>'
        );
    }

    function init() {
        load_plugin_textdomain('futupayments', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    function admin_menu() { 
        add_options_page(
            __('Payments via Futubank.com', 'futupayments'),
            'Futupayments Shortcode',
            'manage_options',
            self::SETTINGS_SLUG, 
            array($this, 'settings_page')
        );
        add_menu_page(
            __('Orders and payments', 'futupayments'),
            __('Orders and payments', 'futupayments'),
            'manage_options',
            'list',
            array($this, 'list_page'),
            '',
            6
        );
    }

    function list_page() { 
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . $this->order_table . ' ORDER BY id DESC', ARRAY_A);
        include $this->templates_dir . 'list.php';
    }

    function settings_page() {
        $group = self::SETTINGS_GROUP;
        $slug = self::SETTINGS_SLUG;
        $callback_url = get_home_url() . self::CALLBACK_URL;
        include $this->templates_dir . 'settings.php';
    }

    function admin_init() {
        register_setting(self::SETTINGS_GROUP, self::SETTINGS_GROUP);
        add_settings_section(
            self::SETTINGS_GROUP, 
            __('Payments via Futubank.com', 'futupayments'), 
            array($this, 'settings_intro_text'),
            self::SETTINGS_SLUG
        );
        add_settings_field(
            'merchant_id', 
            __('Merchant ID', 'futupayments'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'merchant_id')
        );
        add_settings_field(
            'secret_key', 
            __('Secret key', 'futupayments'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'secret_key')
        );
        add_settings_field(
            'test_mode', 
            __('Test mode', 'futupayments'), 
            array($this, 'boolean_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'test_mode')
        );
        add_settings_field(
            'success_url', 
            __('Success URL', 'futupayments'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'success_url')
        );
        add_settings_field(
            'fail_url', 
            __('Fail URL', 'futupayments'), 
            array($this, 'char_field'), 
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'fail_url')
        );
        add_settings_field(
            'pay_button_text', 
            __('Pay button text', 'futupayments'), 
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
        if (
            $options['merchant_id'] && 
            $options['secret_key']
        ) {
            return new FutubankForm(
                $options['merchant_id'], 
                $options['secret_key'], 
                $options['test_mode']
            );
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

    private function create_order(array $data) {
        global $wpdb;
        $order = array(
            'creation_datetime' => current_time('mysql'),
            'amount'            => $data['amount'],
            'currency'          => $data['currency'],
            'description'       => $data['description'],
            'client_email'      => self::get($data, 'client_email', ''),
            'client_name'       => self::get($data, 'client_name', ''),
            'client_phone'      => self::get($data, 'client_phone', ''),
            'status'            => self::STATUS_UNKNOWN,
            'meta'              => '',
        );

        $wpdb->insert($this->order_table, $order) or die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('can\'t create order', 'futupayments'));
        $order['id'] = $wpdb->insert_id;
        return $order;
    }
    
    public function load_order($order_id) {
        global $wpdb;
        return $wpdb->get_row('SELECT * FROM ' . $this->order_table . ' WHERE id = ' . $order_id, ARRAY_A);
    }
    
    private function save_order(array $order) {
        global $wpdb;
        $order_id = array_pop($order, 'id');
        return (bool) $wpdb->update( 
            'table',
            $order,
            array('id' => $order_id),
            $this->order_table_format,
            array('%d')
        );
    }

    private function submit_page() {
        $ff = $this->get_futubank_form() or 
            die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments'));
        
        $h = array();
        foreach ($this->invoice_hidden_fields() as $k) {
            $h[$k] = self::get($_POST, $k);
        }

        if ($ff->get_signature($h) != self::get($_POST, 'signature')) {
            die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('incorrect data', 'futupayments'));
        }

        $options = $this->get_options();
        $order = $this->create_order($_POST);
        $meta = '';

        $data = $ff->compose(
            $order['amount'],
            $order['currency'],
            $order['id'],
            $order['client_email'],
            $order['client_name'],
            $order['client_phone'],
            $options['success_url'],
            $options['fail_url'],
            $_POST['cancel_url'],
            $meta,
            $order['description']
        );

        include $this->templates_dir . 'submit.php';
    }

    private function success_page() {
        $ff = $this->get_futubank_form() or 
            die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments'));
        include $this->templates_dir . 'success.php';
    }

    private function fail_page() {
        $ff = $this->get_futubank_form() or 
        die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments'));
        include $this->templates_dir . 'fail.php';
    }

    private function callback_page() {
        $cb = new FutupaymentsShortcodeCallback($this->get_futubank_form(), $this);
        $cb->show($_POST);

        // $error = null;
        // if (!$ff->is_signature_correct($_POST)) {
        //     $error = 'Incorrect "signature"';
        // } else if (!($order_id = (int) self::get($_POST, 'order_id', 0))) {
        //     $error = 'Empty "order_id"';
        // } else if (!($order = $this->load_order($order_id))) {
        //     $error = 'Unknown order_id';
        // } else if ($order['currency'] != self::get($_POST, 'currency')) {
        //     $error = "Currency mismatch: '$order[currency]'' != '$_POST[currency]'";
        // } else if ($order['amount'] != self::get($_POST, 'amount')) {
        //     $error = "Amount mismatch: '$order[amount]'' != '$_POST[amount]'";
        // }

        // if ($error) {
        //     echo "ERROR: $error\n";
        // } else {
        //     echo "OK$order_id\n";
        //     if ($ff->is_order_completed($_POST)) {
        //         echo "order completed\n";
        //         if ($this->change_status) {
        //             if ($order['status'] == self::STATUS_PAID) {
        //                 echo "already paid\n";
        //             } else {
        //                 $order['status'] = self::STATUS_PAID;
        //                 $order['meta'] = self::get($_POST, 'meta', '');
        //                 if ($this->save_order($order)) {
        //                     echo "paid now\n";
        //                 } else {
        //                     echo "ERROR: can't change payment status\n";
        //                 }
        //             }
        //         }
        //     } else {
        //         echo "order not completed\n";
        //         if ($order['status'] != self::STATUS_PAID) {
        //             $order['status'] = self::STATUS_ERROR;
        //             $order['meta'] = self::get($_POST, 'meta', '');
        //             $this->save_order($order);
        //         }
        //     }
        // }
    }

    private function create_plugin_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        // dbDelta("
        //     CREATE TABLE `" . $this->db_prefix . "payment` (
        //         `id` integer AUTO_INCREMENT NOT NULL,
        //         `creation_datetime` datetime NOT NULL,
        //         `transaction_id` bigint NOT NULL,
        //         `testing` bool NOT NULL,
        //         `amount` numeric(10, 2) NOT NULL,
        //         `currency` varchar(3) NOT NULL,
        //         `order_id` varchar(128) NOT NULL,
        //         `state` varchar(10) NOT NULL,
        //         `message` longtext NOT NULL,
        //         `meta` longtext NOT NULL,
        //         UNIQUE (`state`, `transaction_id`)
        //     );
        // ");
        dbDelta("
            CREATE TABLE `" . $this->order_table . "` (
                `id` integer AUTO_INCREMENT NOT NULL,
                `creation_datetime` datetime NOT NULL,
                `amount` numeric(10, 2) NOT NULL,
                `currency` varchar(3) NOT NULL,
                `description` longtext NOT NULL,
                `client_email` varchar(120) NOT NULL,
                `client_name` varchar(120) NOT NULL,
                `client_phone` varchar(30) NOT NULL,
                `status` varchar(30) NOT NULL default '" . self::STATUS_UNKNOWN . "',
                `meta` longtext NOT NULL
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

new FutupaymentsShortcode;
