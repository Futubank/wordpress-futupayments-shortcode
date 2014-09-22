<?php
/**
 * =========================================================================
 * ВНИМАНИЕ! Это общая часть всех плагинов. Оригинал всегда лежит по адресу:
 * https://github.com/Futubank/futuplugins/blob/master/php/futubank_core.php
 * =========================================================================
 *
 * 1. Вывод формы оплаты
 * ---------------------
 *
 * $ff = new FutubankForm($merchant_id, $secret_key, $is_test);
 *
 * // URL для отправки формы:
 * $url = $ff->get_url();
 *
 * // значения полей формы
 * $form = $ff->compose(
 *     $amount,        // сумма заказа
 *     $currency,      // валюта заказа (поддерживается только "RUB")
 *     $order_id,      // номер заказа
 *     $client_email,  // e-mail клиента (может быть '')
 *     $client_name,   // имя клиента (может быть '')
 *     $client_phone,  // телефон клиента (может быть '')
 *     $success_url,   // URL, куда направить клиента при успешной оплате
 *     $fail_url,      // URL, куда направить клиента при ошибке
 *     $cancel_url,    // URL текущей страницы
 *     $meta,          // дополнительная информация в свободной форме (необязательно)
 *     $description,    // описание (необязательно)
 *     // для периодических платежей:
 *     $recurring_frequency,   // частота периодических платежей (необязательно, 'day', 'week', 'month', 'quartal', 'half-year', 'year')
 *     $recurring_finish_date, // конечная дата периодических платежей (необязательно, дата в формате 'YYYY-MM-DD')
 *     $recurrind_tx_id,       // для рекуррентного платежа - id первой транзакции (необязательно)
 *     $recurring_token        // для рекуррентного платежа - токен рекуррентного платежа (необязательно)
 * );
 *
 * // далее можно самостоятельно вывести $form в виде hidden-полей,
 * // а можно воспользоваться готовым статическим методом array_to_hidden_fields:
 *
 * echo "<form action='$url' method='post'>" . FutubankForm::array_to_hidden_fields($form) . '<input type="submit"></form>';
 *
 *
 * 2. Приём сообщений о выполненных транзакциях (http://yoursite.com/callback.php)
 * -------------------------------------------------------------------------------
 * // создаём класс обработчика транзакций, который знает всё про статусы заказов в вашей системе
 * class MyCallbackHandler extends AbstractFutubankCallbackHandler {
 *     // предположим, вся логика вашего плагина содержится в классе MyPlugin
 *     private $plugin;
 *     function __construct(MyPlugin $plugin)              { $this->plugin = $plugin; }
 *     // определяем ключевые методы. Код методов приведён исключительно для примера
 *     protected function get_futubank_form()              { return $this->plugin->get_futubank_form(); }
 *     protected function load_order($order_id)            { return $this->plugin->load_order($order_id); }
 *     protected function get_order_currency($order)       { return $order->getCurrency(); }
 *     protected function get_order_amount($order)         { return $order->getAmount(); }
 *     protected function is_order_completed($order)       { return $order->getStatus() == 'completed'; }
 *     protected function mark_order_as_completed($order, array $data) {
 *         $order->setStatus('completed');
 *         $order->save()
 *     }
 *     protected function mark_order_as_error($order, array $data) {
 *         $order->setStatus('error');
 *         $order->save()
 *     }
 * }
 *
 * // схема ориентироваочная и зависит от архитектуры вашей CMS или фреймворка
 * $myplugin = new MyPlugin();
 * $h = new MyCallbackHandler($myplugin);
 * // обрабатываем сообщение от банка, пришедшее в _POST, и если всё хорошо, отмечаем заказ как оплаченный
 * $h->show($_POST);
 *
 *
 * 3. Отправка рекуррентного платежа
 * ---------------------------------
 *
 * $ff = new FutubankForm($merchant_id, $secret_key, $is_test);
 *
 * $result = $ff->rebill(
 *       $amount,           // сумма заказа
 *       $currency,         // валюта заказа (поддерживается только "RUB")
 *       $order_id,         // номер заказа
 *       $recurrind_tx_id,  // id транзакции, получен при первом платеже
 *       $recurring_token,  // токен рекуррентной транзакции, получен при первом платеже
 *       $description = ''  // описание заказа (необязательно)
 * );
 *
 */
class FutubankForm {
    private $merchant_id;
    private $secret_key;
    private $is_test;
    private $plugininfo;
    private $cmsinfo;
    private $futugate_host;

    function __construct(
        $merchant_id,
        $secret_key,
        $is_test,
        $plugininfo = '',
        $cmsinfo = ''
    ) {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        $this->is_test = (bool) $is_test;
        $this->plugininfo = $plugininfo ?: 'Futuplugins/PHP v.' . phpversion();
        $this->cmsinfo = $cmsinfo;
        $this->futugate_host = 'https://secure.futubank.com';
        //$this->futugate_host = 'http://127.0.0.1:8000';
    }

    function get_url() {
        return $this->futugate_host . '/pay/';
    }

    function get_rebill_url() {
        return $this->futugate_host . '/api/v1/rebill/';
    }

    function compose(
        $amount,
        $currency,
        $order_id,
        $client_email,
        $client_name,
        $client_phone,
        $success_url,
        $fail_url,
        $cancel_url,
        $meta = '',
        $description = '',
        $recurring_frequency = '',
        $recurring_finish_date = ''
    ) {
        if (!$description) {
            $description = "Заказ №$order_id";
        }
        $form = array(
            'testing'               => (int) $this->is_test,
            'merchant'              => $this->merchant_id,
            'unix_timestamp'        => time(),
            'salt'                  => $this->get_salt(32),
            'amount'                => $amount,
            'currency'              => $currency,
            'description'           => $description,
            'order_id'              => $order_id,
            'client_email'          => $client_email,
            'client_name'           => $client_name,
            'client_phone'          => $client_phone,
            'success_url'           => $success_url,
            'fail_url'              => $fail_url,
            'cancel_url'            => $cancel_url,
            'meta'                  => $meta,
            'sysinfo'               => $this->get_sysinfo(),
            'recurring_frequency'   => $recurring_frequency,
            'recurring_finish_date' => $recurring_finish_date
        );
        $form['signature'] = $this->get_signature($form);
        return $form;
    }

    private function get_sysinfo() {
        return ('{' .
            '"json_enabled": ' . var_export(function_exists('json_encode'), 1) . ', ' .
            '"language": "PHP ' . phpversion() . '", ' .
            '"plugin": "' . $this->plugininfo . '", ' .
            '"cms": "' . $this->cmsinfo . '"' .
        '}');
    }

    function is_signature_correct(array $form) {
        if (!array_key_exists('signature', $form)) {
            return false;
        }
        return $this->get_signature($form) == $form['signature'];
    }

    function is_order_completed(array $form) {
        $is_testing_transaction = ($form['testing'] === '1');
        return ($form['state'] == 'COMPLETE') && ($is_testing_transaction == $this->is_test);
    }

    public static function array_to_hidden_fields(array $form) {
        $result = '';
        foreach ($form as $k => $v) {
            $result .= '<input name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '" type="hidden">';
        }
        return $result;
    }

    function get_signature(array $params, $key = 'signature') {
        $keys = array_keys($params);
        sort($keys);
        $chunks = array();
        foreach ($keys as $k) {
            $v = (string) $params[$k];
            if (($v !== '') && ($k != 'signature')) {
                $chunks[] = $k . '=' . base64_encode($v);
            }
        }
        return $this->double_sha1(implode('&', $chunks));
    }

    private function double_sha1($data) {
        for ($i = 0; $i < 2; $i++) {
            $data = sha1($this->secret_key . $data);
        }
        return $data;
    }

    private function get_salt($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $result;
    }

    function rebill(
        $amount,
        $currency,
        $order_id,
        $recurrind_tx_id,
        $recurring_token,
        $description = ''
    ){
        if (!$description) {
            $description = "Заказ №$order_id";
        }
        $form = array(
            'testing'               => (int) $this->is_test,
            'merchant'              => $this->merchant_id,
            'unix_timestamp'        => time(),
            'salt'                  => $this->get_salt(32),
            'amount'                => $amount,
            'currency'              => $currency,
            'description'           => $description,
            'order_id'              => $order_id,
            'initial_transaction'   => $recurrind_tx_id,
            'recurring_token'       => $recurring_token,
        );
        $form['signature'] = $this->get_signature($form);
        $paramstr = http_build_query($form);
        $ch = curl_init($this->get_rebill_url());
        curl_setopt($ch, CURLOPT_USERAGENT, $this->plugininfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramstr);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }
}


abstract class AbstractFutubankCallbackHandler {
    /**
    * @return FutubankForm
    */
    abstract protected function get_futubank_form();
    abstract protected function load_order($order_id);
    abstract protected function get_order_currency($order);
    abstract protected function get_order_amount($order);
    /**
    * @return bool
    */
    abstract protected function is_order_completed($order);
    /**
    * @return bool
    */
    abstract protected function mark_order_as_completed($order, array $data);
    /**
    * @return bool
    */
    abstract protected function mark_order_as_error($order, array $data);

    function show(array $data) {
        $error = null;
        $debug_messages = array();
        $ff = $this->get_futubank_form();

        if (!$ff->is_signature_correct($data)) {
            $error = 'Incorrect "signature"';
        } else if (!($order_id = (int) $data['order_id'])) {
            $error = 'Empty "order_id"';
        } else if (!($order = $this->load_order($order_id))) {
            $error = 'Unknown order_id';
        } else if ($this->get_order_currency($order) != $data['currency']) {
            $error = 'Currency mismatch: "' . $this->get_order_currency($order) . '" != "' . $data['currency'] . '"';
        } else if ($this->get_order_amount($order) != $data['amount']) {
            $error = 'Amount mismatch: "' . $this->get_order_amount($order) . '" != "' . $data['amount'] . '"';
        } else if ($ff->is_order_completed($data)) {
            $debug_messages[] = "info: order completed";
            if ($this->is_order_completed($order)) {
                $debug_messages[] = "order already marked as completed";
            } else if ($this->mark_order_as_completed($order, $data)) {
                $debug_messages[] = "mark order as completed";
            } else {
                $error = "Can't mark order as completed";
            }
        } else {
            $debug_messages[] = "info: order not completed";
            if (!$this->is_order_completed($order)) {
                if ($this->mark_order_as_error($order, $data)) {
                    $debug_messages[] = "mark order as error";
                } else {
                    $error = "Can't mark order as error";
                }
            }
        }

        if ($error) {
            echo "ERROR: $error\n";
        } else {
            echo "OK$order_id\n";
        }
        foreach ($debug_messages as $msg) {
            echo "...$msg\n";
        }
    }
}
