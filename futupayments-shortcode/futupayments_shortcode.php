<?php
/*
  Plugin Name: Futupayments Shortcode
  Plugin URI: https://github.com/Futubank/wordpress-futupayments-shortcode
  Description: Allows you to use Futubank.com payment gateway with the shortcode button.
  Version: 1.4
*/

//include(dirname(__FILE__) . '/inc/widget.php');
if (!class_exists('FutubankForm')) {
    include(dirname(__FILE__) . '/inc/futubank_core.php');
}


class FutupaymentsShortcodeCallback extends AbstractFutubankCallbackHandler {
    private $plugin;
    function __construct(FutupaymentsShortcode $plugin) { $this->plugin = $plugin; }
    protected function get_futubank_form()              { return $this->plugin->get_futubank_form(); }
    protected function load_order($order_id)            { return $this->plugin->load_order($order_id); }
    protected function get_order_currency($order)       { return $order['currency']; }
    protected function get_order_amount($order)         { return $order['amount']; }
    protected function is_order_completed($order)       { return $order['status'] == FutupaymentsShortcode::STATUS_PAID; }
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
    const VERSION        = '1.4';
    const NAME           = 'wordpress-futupayments-shortcode';

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
    private $invoice_protected_fields;

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
            '%d',  # testing
            '%s',  # meta
        );

        $this->invoice_protected_fields = array(
            'amount',
            'currency',
            'description',
            'fields',
            'cancel_url',
        );

        $this->templates_dir = dirname(__FILE__) . '/templates/';

        add_action('init',  array($this, 'init'));
        add_shortcode('futupayment', array($this, 'futupayment_button'));
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('plugins_loaded', array($this, 'plugins_loaded'));
            add_action('admin_init', array($this, 'admin_init'));
        }
        add_action('parse_request', array($this, 'parse_request'));
    }

    function log($msg) {
        $dest = $_SERVER['HOME'] . '/' . self::NAME . '.log';
        if (!@error_log("$msg\n", 3, $dest)) {
            error_log('[' . self::NAME . '] ' . $msg);
        }
    }

    function plugins_loaded() {
        $this->log('plugins_loaded()');
        $version = get_site_option('futupayment_version');
        if ($version != self::VERSION) {
            $this->log('Prev version: ' . $version);
            $this->create_plugin_tables();
            $this->log('Update to ' . self::VERSION);
            update_site_option('futupayment_version', self::VERSION);
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

    private function get_additional_fields(array $atts) {
        $additional_fields = array(
            'client_amount' => array(
                'label'  => __('Amount', 'futupayments'),
                'hidden' => true,
                'type'   => 'number',
            ),
            'client_description' => array(
                'label'  => __('Description', 'futupayments'),
                'hidden' => true,
                'type'   => 'text',
            ),
            'client_name' => array(
                'label'  => __('Name', 'futupayments'),
                'hidden' => true,
                'type'   => 'text',
            ),
            'client_email' => array(
                'label'  => __('Email', 'futupayments'),
                'hidden' => true,
                'type'   => 'email',
            ),
            'client_phone' => array(
                'label'  => __('Phone', 'futupayments'),
                'hidden' => true,
                'type'   => 'text',
            ),
        );

        foreach (preg_split("~\s*,\s*~", $atts['fields']) as $key) {
            if (array_key_exists($key, $additional_fields)) {
                $additional_fields[$key]['hidden'] = false;
            }
        }

        if (!$atts['amount']) {
            $additional_fields['client_amount']['hidden'] = false;
        }

        if (!$atts['description']) {
            $additional_fields['client_description']['hidden'] = false;
        }

        return $additional_fields;
    }

    function futupayment_button(array $atts) {
        $this->log('futupayment_button()');

        $ff = $this->get_futubank_form();

        if (!$ff) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments');
        }

        $options = $this->get_options();

        $atts = shortcode_atts(array(
            'amount'      => '',
            'currency'    => 'RUB',
            'description' => '',
            'fields'      => '',
            'button_text' => $options['pay_button_text'],
        ), $atts);

        $additional_fields = $this->get_additional_fields($atts);

        if (!$atts['currency']) {
            return __('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('currency required', 'futupayments');
        }

        $atts['cancel_url'] = get_home_url() . $_SERVER['REQUEST_URI'];

        $signed_fields = array();
        foreach ($this->invoice_protected_fields as $k) {
            $signed_fields[$k] = $atts[$k];
        }
        $atts['signature'] = $ff->get_signature($signed_fields);

        $url = self::SUBMIT_URL;
        ob_start();
        include $this->templates_dir . 'futupayment_button.php';
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    function init() {
        $this->log('init()');
        load_plugin_textdomain('futupayments', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    function admin_menu() {
        #$this->log('admin_menu()');
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
        #$this->log('list_page()');
        global $wpdb;
        $step = 50;
        $limit = (int) self::get($_GET, 'limit', $step);
        $rows = $wpdb->get_results(
            'SELECT * FROM ' . $this->order_table .
            ' ORDER BY id DESC' .
            ' LIMIT ' . $limit,
            ARRAY_A
        );
        $statuses = array(
            self::STATUS_UNKNOWN => __('inprocess', 'futupayments'),
            self::STATUS_PAID    => __('paid', 'futupayments'),
            self::STATUS_ERROR   => __('error', 'futupayments'),
        );
        include $this->templates_dir . 'list.php';
    }

    function settings_page() {
        #$this->log('settings_page()');
        $group = self::SETTINGS_GROUP;
        $slug = self::SETTINGS_SLUG;
        $callback_url = get_home_url() . self::CALLBACK_URL;
        include $this->templates_dir . 'settings.php';
    }

    function admin_init() {
        $this->log('admin_init()');
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
            'secret_key',
            __('Host', 'futupayments'),
            array($this, 'char_field'),
            self::SETTINGS_SLUG,
            self::SETTINGS_GROUP,
            array('id' => 'futugate_host')
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

    function get_futubank_form() {
        $options = $this->get_options();
        if (
            $options['merchant_id'] &&
            $options['secret_key']
        ) {
            return new FutubankForm(
                $options['merchant_id'],
                $options['secret_key'],
                $options['test_mode'],
                self::NAME . ' ' . self::VERSION,
                "WordPress " . get_bloginfo('version'),
                $options['futugate_host'] ?: 'https://secure.futubank.com'
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
        $this->log('create_order(): ' . var_export($data, 1));
        $additional_fields = $this->get_additional_fields($data);
        $options = $this->get_options();
        $order = array(
            'creation_datetime' => current_time('mysql'),
            'amount'            => $data['amount'] ? $data['amount'] : $data['client_amount'],
            'currency'          => $data['currency'],
            'description'       => $data['description'] ? $data['description'] : $data['client_description'],
            'client_email'      => $data['client_email'],
            'client_name'       => $data['client_name'],
            'client_phone'      => $data['client_phone'],
            'status'            => self::STATUS_UNKNOWN,
            'testing'           => $options['test_mode'] ? 1 : 0,
            'meta'              => '',
        );
        global $wpdb;
        $wpdb->insert($this->order_table, $order) or die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('can\'t create order', 'futupayments'));
        $order['id'] = $wpdb->insert_id;
        $this->log('order_id = ' . $order['id']);
        return $order;
    }

    function load_order($order_id) {
        global $wpdb;
        return $wpdb->get_row('SELECT * FROM ' . $this->order_table . ' WHERE id = ' . $order_id, ARRAY_A);
    }

    function save_order(array $order) {
        $this->log('save_order(): ' . var_export($order, 1));
        global $wpdb;
        $order_id = $order['id'];
        unset($order['id']);
        $result = (bool) $wpdb->update(
            $this->order_table,
            $order,
            array('id' => $order_id),
            $this->order_table_format,
            array('%d')
        );
        $this->log('save_order result: ' . var_export($result, 1));
        return $result;
    }

    private function submit_page() {
        $ff = $this->get_futubank_form() or
            die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments'));

        $h = array();
        foreach ($this->invoice_protected_fields as $k) {
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
        $this->get_futubank_form() or
            die(__('FUTUPAYMENT ERROR', 'futupayments') . ': ' . __('plugin is not configured', 'futupayments'));
        $cb = new FutupaymentsShortcodeCallback($this);
        $cb->show($_POST);
    }

    private function create_plugin_tables() {
        $this->log('create_plugin_tables()');
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "
            CREATE TABLE " . $this->order_table . " (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                creation_datetime datetime NOT NULL,
                amount numeric(10, 2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                description longtext NOT NULL,
                client_email VARCHAR(120) NOT NULL,
                client_name VARCHAR(120) NOT NULL,
                client_phone VARCHAR(30) NOT NULL,
                status VARCHAR(30) NOT NULL default '" . self::STATUS_UNKNOWN . "',
                testing int NOT NULL default '1',
                meta longtext NOT NULL,
                UNIQUE KEY id (id)
            );
        ";
        $this->log('dbDelta(): ' . $sql);
        $result = dbDelta($sql);
        $this->log('dbDelta result: ' . var_export($for_update, 1));
        return $result;
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
