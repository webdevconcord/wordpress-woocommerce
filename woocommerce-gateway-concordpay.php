<?php
/**
 *Plugin Name: WooCommerce - ConcordPay
 *Description: ConcordPay Payment Gateway for WooCommerce.
 *Version: 1.0
 *Author URI: https://pay.concord.ua
 *License: GNU General Public License v3.0
 *License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


add_action('plugins_loaded', 'woocommerce_concordpay_init', 0);

function woocommerce_concordpay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (isset($_GET['msg']) && !empty($_GET['msg'])) {
        add_action('the_content', 'showConcordPayMessage');
    }
    function showConcordPayMessage($content)
    {
        return '<div class="' . htmlentities($_GET['type']) . '">' . htmlentities(urldecode($_GET['msg'])) . '</div>' . $content;
    }

    /**
     * Gateway class
     */
    class WC_concordpay extends WC_Payment_Gateway
    {
        protected $url = "https://pay.concord.ua/api/";

        const SIGNATURE_SEPARATOR = ';';

        const ORDER_NEW = 'New';
        const ORDER_DECLINED = 'Declined';
        const ORDER_REFUND_IN_PROCESSING = 'RefundInProcessing';
        const ORDER_REFUNDED = 'Refunded';
        const ORDER_EXPIRED = 'Expired';
        const ORDER_PENDING = 'Pending';
        const ORDER_APPROVED = 'Approved';
        const ORDER_WAITING_AUTH_COMPLETE = 'WaitingAuthComplete';
        const ORDER_IN_PROCESSING = 'InProcessing';
        const ORDER_SEPARATOR = '#';
        const ORDER_SUFFIX = '_woopay_';

        protected $keysForResponseSignature = array(
            'merchantAccount',
            'orderReference',
            'amount',
            'currency',
        );

        /** @var array */
        protected $keysForRequestSignature = array(
            'merchant_id',
            'order_id',
            'amount',
            'currency_iso',
            'description'
        );


        public function __construct()
        {
            $this->id = 'concordpay';
            $this->method_title = 'ConcordPay';
            $this->method_description = "Payment gateway";
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = "ConcordPay";
            $this->lang = $this->getLanguage();
            $this->description = $this->setDescription($this->lang);

            $this->redirect_page_id = $this->settings['approveUrl'];

            $this->declineUrl = $this->settings['declineUrl'];
            $this->cancelUrl = $this->settings['cancelUrl'];
            $this->callbackUrl = $this->settings['callbackUrl'];

            $this->merchant_id = $this->settings['merchant_id'];
            $this->secretKey = $this->settings['secret_key'];

            $this->msg['message'] = "";
            $this->msg['class'] = "";


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                /* 2.0.0 */
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_concordpay_response'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                /* 1.6.6 */
                add_action('init', array(&$this, 'check_concordpay_response'));
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_concordpay', array(&$this, 'receipt_page'));
        }

        function init_form_fields()
        {
            // fields for settings
            $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'kdc'),
                'type' => 'checkbox',
                'label' => __('Enable ConcordPay Payment Module.', 'kdc'),
                'default' => 'no',
                'description' => 'Show in the Payment List as a payment option'),
                'merchant_id' => array('title' => __('Merchant Account', 'kdc'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by ConcordPay'),
                    'desc_tip' => true
                ),
                'secret_key' => array('title' => __('Secret key', 'kdc'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by ConcordBank', 'kdc'),
                    'desc_tip' => true,
                ),
//                'status_after_payment' => array('title' => __('Status after received payment', 'kdc'),
//                    'options' => $this->get_order_status_labels(),
//                    'type' => 'select',
//                ),
                'concordpayUrl' => array('title' => __('System url'),
                    'type' => 'text',
                    'description' => __('Default url - https://pay.concord.ua/api/', 'kdc'),
                    'default' => 'https://pay.concord.ua/api/',
                    'desc_tip' => true),
                'approveUrl' => array('title' => __('Successful payment redirect URL'),
                    'options' => $this->get_all_pages('Select Page'),
                    'type' => 'select',
                    'description' => __('Successful payment redirect URL', 'kdc'),
                    'desc_tip' => true),
                'approveUrl_t' => array('title' => __('или укажите'),
                    'type' => 'text',
                    'description' => __('Successful payment redirect URL', 'kdc'),
                    'desc_tip' => true),
                'cancelUrl' => array('title' => __('Redirect URL in case of failure to make payment'),
                    'type' => 'text',
                    'description' => __('Redirect URL in case of failure to make payment', 'kdc'),
                    'desc_tip' => true),
                'declineUrl' => array('title' => __('Redirect URL failed to pay'),
                    'type' => 'text',
                    'description' => __('Redirect URL failed to pay', 'kdc'),
                    'desc_tip' => true),
                'callbackUrl' => array('title' => __('URL of the result information'),
                    'options' => $this->get_all_pages('Select Page'),
                    'type' => 'select',
                    'description' => __('The URL to which will receive information about the result of the payment', 'kdc'),
                    'desc_tip' => true)
            );
        }

        /**
         * Admin Panel Options
         */
        public function admin_options()
        {
            echo '<h3>' . __('Concord.ua', 'kdc') . '</h3>';
            echo '<p>' . __('Payment gateway') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Receipt Page
         **/
        function receipt_page($order)
        {
            global $woocommerce;

            echo '<p>' . __('Спасибо за ваш заказ, сейчас вы будете перенаправлены на страницу оплаты ConcordPay.', 'kdc') . '</p>';
            echo $this->generate_concordpay_form($order);

            $woocommerce->cart->empty_cart();
        }

        protected function isPaymentValid($response)
        {
            global $woocommerce;

            list($orderId,) = explode(self::ORDER_SUFFIX, $response['orderReference']);
            $order = new WC_Order($orderId);
            if ($order === FALSE) {
                return 'An error has occurred during payment. Please contact us to ensure your order has submitted.';
            }

            if ($this->merchant_id != $response['merchantAccount']) {
                return 'An error has occurred during payment. Merchant data is incorrect.';
            }

            $responseSignature = $response['merchantSignature'];


            if ($this->getResponseSignature($response) != $responseSignature) {
                die('An error has occurred during payment. Signature is not valid.');
            }


            if ($response['transactionStatus'] == self::ORDER_APPROVED) {

                $order->update_status('completed');
                $order->payment_complete();
                $order->add_order_note("ConcordPay : orderReference:" . $response['transactionStatus'] . " \n\n recToken: " . $response['recToken']);
                return true;
            }

            $woocommerce->cart->empty_cart();
            return false;
        }

        function check_concordpay_response()
        {
            $data = json_decode(file_get_contents("php://input"), true);
            $paymentInfo = $this->isPaymentValid($data);
            if ($paymentInfo === true) {
                echo $this->getAnswerToGateWay($data);

                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                $this->msg['class'] = 'woocommerce-message';
            }
            exit;
        }

        /**
         * @param $data
         * @return mixed|string|void
         */
        public function getAnswerToGateWay($data)
        {
            $time = time();
            $responseToGateway = array(
                'orderReference' => $data['orderReference'],
                'status' => 'accept',
                'time' => $time
            );
            $sign = array();
            foreach ($responseToGateway as $dataKey => $dataValue) {
                $sign [] = $dataValue;
            }
            $sign = implode(';', $sign);
            $sign = hash_hmac('md5', $sign, $this->secretKey);
            $responseToGateway['signature'] = $sign;

            return json_encode($responseToGateway);
        }

        /**
         * @param $option
         * @param $keys
         * @return string
         */
        public function getSignature($option, $keys)
        {
            $hash = array();
            foreach ($keys as $dataKey) {
                if (!isset($option[$dataKey])) {
                    continue;
                }
                if (is_array($option[$dataKey])) {
                    foreach ($option[$dataKey] as $v) {
                        $hash[] = $v;
                    }
                } else {
                    $hash [] = $option[$dataKey];
                }
            }
            $hash = implode(';', $hash);

            return hash_hmac('md5', $hash, $this->secretKey);
        }


        /**
         * @param $options
         * @return string
         */
        public function getRequestSignature($options)
        {
            return $this->getSignature($options, $this->keysForRequestSignature);
        }

        /**
         * @param $options
         * @return string
         */
        public function getResponseSignature($options)
        {
            return $this->getSignature($options, $this->keysForResponseSignature);
        }

        /**
         * Generate ConcordPay button link
         */
        function generate_concordpay_form($order_id)
        {
            $order = new WC_Order($order_id);

            $orderDate = isset($order->post->post_date) ? $order->post->post_date : $order->order_date;

            $currency = str_replace(
                array('ГРН', 'UAH'),
                array('UAH', 'UAH'),
                get_woocommerce_currency()
            );

            $concordpay_args = array(
                'operation' => 'Purchase',
                'merchant_id' => $this->merchant_id,
                'order_id' => $order_id . self::ORDER_SUFFIX . time(),
                'amount' => $order->get_total(),
                'currency_iso' => $currency,
                'description' => 'Оплата картой VISA или Mastercard на сайте  ' . $_SERVER["HTTP_HOST"],
                'approve_url' => $this->getCallbackUrl(),
                'callback_url' => $this->getCallbackUrl(true),
                'decline_url' => $this->settings['declineUrl'],
                'cancel_url' => $this->settings['cancelUrl'],

                'language' => $this->getLanguage()
            );

            $concordpay_args['signature'] = $this->getRequestSignature($concordpay_args);

            $items = $order->get_items();
            foreach ($items as $item) {
                $concordpay_args['productName'][] = esc_html($item['name']);
                $concordpay_args['productCount'][] = $item['qty'];
                $concordpay_args['productPrice'][] = $item['line_total'];
            }
            $phone = $order->billing_phone;
            $phone = str_replace(array('+', ' ', '(', ')'), array('', '', '', ''), $phone);
            if (strlen($phone) == 10) {
                $phone = '38' . $phone;
            } elseif (strlen($phone) == 11) {
                $phone = '3' . $phone;
            }

            $client = array(
                "clientFirstName" => $order->billing_first_name,
                "clientLastName" => $order->billing_last_name,
                "clientAddress" => $order->billing_address_1 . ' ' . $order->billing_address_2,
                "clientCity" => $order->billing_city,
                "clientPhone" => $phone,
                "clientEmail" => $order->billing_email,
                "clientCountry" => strlen($order->billing_country) != 3 ? 'UKR' : $order->billing_country,
                "clientZipCode" => $order->billing_postcode
            );

            $concordpay_args = array_merge($concordpay_args, $client);

            return $this->generateForm($concordpay_args);
        }

        /**
         * Generate form with fields
         *
         * @param $data
         * @return string
         */
        protected function generateForm($data)
        {
            $form = '<form method="post" id="form_concordpay" action="' . $this->url . '" accept-charset="utf-8">';
            foreach ($data as $k => $v) $form .= $this->printInput($k, $v);
            $button = "<img style='position:absolute; top:50%; left:47%; margin-top:-125px; margin-left:-60px;' src='$img' >
                        <script>
                            function submitConcordPayForm()
                            {
                                document.getElementById('form_concordpay').submit();
                            }
                            setTimeout( submitConcordPayForm, 1500);
                        </script>";

            return $form .
                "<input type='submit' style='display:none;' /></form>"
                . $button;
        }

        /**
         * Print inputs in form
         *
         * @param $name
         * @param $val
         * @return string
         */
        protected function printInput($name, $val)
        {
            $str = "";
            if (!is_array($val)) return '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($val) . '">' . "\n<br />";
            foreach ($val as $v) $str .= $this->printInput($name . '[]', $v);
            return $str;
        }

        /**
         *  There are no payment fields for techpro, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
                /* 2.1.0 */
                $checkout_payment_url = $order->get_checkout_payment_url(true);
            } else {
                /* 2.0.0 */
                $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
            }

            return array('result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $checkout_payment_url)));
        }

        /**
         * @param bool $callback
         * @return bool|string
         */
        private function getCallbackUrl($callback = false)
        {

            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            if (!$callback) {
                if (
                    isset($this->settings['returnUrl_m']) &&
                    trim($this->settings['returnUrl_m']) !== ''
                ) {
                    return trim($this->settings['returnUrl_m']);
                }
                return $redirect_url;
            }

            return add_query_arg('wc-api', get_class($this), $redirect_url);
        }


        // get all pages
        function get_all_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) {
                $page_list[] = $title;
            }
            foreach ($wp_pages as $page) {
                $prefix = '';

                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }

                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        /**
         * Get statuses labels
         *
         * @return array
         */
        public function get_order_status_labels()
        {
            $order_statuses = array();

            foreach (wc_get_order_statuses() as $key => $label) {
                $new_key = str_replace('wc-', '', $key);
                $order_statuses[$new_key] = $label;
            }

            return $order_statuses;
        }

        private function setDescription($lang) {
            switch($lang) {
                case 'en':
                    return 'Pay via Visa, Mastercard, GooglePay (ConcordPay)';
                case 'ru':
                    return 'Оплата картами VISA, Mastercard, GooglePay (ConcordPay)';
                case 'uk':
                    return 'Оплата картами VISA, Mastercard, GooglePay (ConcordPay)';
                default:
                    return 'Оплата картами VISA, Mastercard, GooglePay (ConcordPay)';
            }
        }

        private function getLanguage()
        {
            return substr(get_bloginfo('language'), 0, 2);
        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_concordpay_gateway($methods)
    {
        $methods[] = 'WC_concordpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_concordpay_gateway');
}
