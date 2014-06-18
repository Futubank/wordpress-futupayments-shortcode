<?php
/**
 * =========================================================================
 * ВНИМАНИЕ! Это общая часть всех плагинов. Оригинал всегда лежит по адресу:
 * https://github.com/Futubank/futuplugins/blob/master/php/futubank_core.php
 * =========================================================================
 * Вывод формы оплаты:
 *
 * $ff = new FutubankForm($merchant_id, $secret_key, $is_test);
 *
 * // URL для отправки формы:
 * $url = $ff->get_url()
 *
 * // значения полей формы
 * $form = compose(
 *     $amount,        // сумма заказа
 *     $currency,      // валюта заказа (поддерживается только "RUB")
 *     $order_id,      // номер заказа
 *     $client_email,  // e-mail клиента (может быть '')
 *     $client_name,   // имя клиента (может быть '')
 *     $client_phone,  // телефон клиента (может быть '')
 *     $success_url,   // URL, куда направить клиента при успешной оплате
 *     $fail_url,      // URL, куда направить клиента при ошибке
 *     $cancel_url     // URL текущей страницы
 * );
 *
 * // далее можно самостоятельно вывести $form в виде hidden-полей,
 * // а можно воспользоваться готовым статическим методом array_to_hidden_fields:
 *
 * echo "<form action='$url' method='post'>" . FutubankForm::array_to_hidden_fields($form) . '<input type="submit"></form>';
 *
 */
class FutubankForm {
    private $merchant_id;
    private $secret_key;
    private $is_test;

    const HOST = 'https://secure.futubank.com';

    public function __construct($merchant_id, $secret_key, $is_test) {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        $this->is_test = $is_test;
    }

    public function get_url() {
        if ($this->is_test) {
            return self::HOST . '/testing-pay/';
        } else {
            return self::HOST . '/pay/';
        }
    }

    public function compose(
        $amount,
        $currency,
        $order_id,
        $client_email,
        $client_name,
        $client_phone,
        $success_url,
        $fail_url,
        $cancel_url,
        $meta=''
    ) {
        $form = array(
            'merchant'       => $this->merchant_id,
            'unix_timestamp' => time(),
            'salt'           => $this->get_salt(32),
            'amount'         => $amount,
            'currency'       => $currency,
            'description'    => "Заказ №$order_id",
            'order_id'       => $order_id,
            'client_email'   => $client_email,
            'client_name'    => $client_name,
            'client_phone'   => $client_phone,
            'success_url'    => $success_url,
            'fail_url'       => $fail_url,
            'cancel_url'     => $cancel_url,
            'meta'           => $meta,
        );
        $form['signature'] = $this->get_signature($form);
        return $form;
    }

    public function is_signature_correct(array $form) {
        if (!array_key_exists('signature', $form)) {
            return false;
        }
        return $this->get_signature($form) == $form['signature'];
    }

    public function is_order_completed(array $form) {
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

    private function get_signature(array $params) {
        $keys = array_keys($params);
        sort($keys);
        $chunks = array();
        foreach ($keys as $k) {
            if ($params[$k] && ($k != 'signature')) {
                $chunks[] = $k . '=' . base64_encode($params[$k]);
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
}
