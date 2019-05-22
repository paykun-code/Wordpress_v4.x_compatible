<?php
/*
Plugin Name: PayKun Gateway (WooCommerce)
Plugin URI: http://paykun.com/
Description: PayKun Payment Gateway with Check Status.
Version: 0.2
Author: Paykun
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action('plugins_loaded', 'woocommerce_paykun_init', 0);

function woocommerce_paykun_init() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Localisation
     */
    load_plugin_textdomain('wc-paykun', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    if(isset($_GET['msg'])){
        add_action('the_content', 'paykunShowMessage');
    }

    function paykunShowMessage($content){
        return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }

    /**
     * Gateway class
     */
    class WC_paykun extends WC_Payment_Gateway {
        protected $msg = array();
        public function __construct() {  // construct form //
            // Go wild in here
            $this -> id = 'paykun';
            $this -> method_title = __('Paykun');
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/paykun-logo.svg';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> merchantIdentifier = $this -> settings['merchantIdentifier'];
            $this -> access_token = html_entity_decode($this -> settings['access_token']);
            $this -> encryption_key = $this -> settings['encryption_key'];

            //            $this -> transaction_status_url = $this -> settings['transaction_status_url'];
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            // $this -> mode = $this -> settings['mode'];
            if(isset($this -> settings) && !empty($this -> settings)) {
                if(isset($this -> settings['callbackurl'])) {
                    $this -> callbackurl = $this -> settings['callbackurl'];
                }
            } else {
                $this -> callbackurl = '';
            }
//            $this -> callbackurl = $this -> settings['callbackurl'];
            $this -> log = $this -> settings['log'];

            $this -> msg['message'] = "";
            $this -> msg['class'] = "";

            add_action('init', array(&$this, 'check_paykun_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_paykun_response' ) );
            add_action('valid-paykun-request', array(&$this, 'successful_request')); // this save
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_paykun', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_paykun',array(&$this, 'thankyou_page'));
        }
        //$merchant_id -> $gateway_url =>  $industry_type => $channel_id = >$website

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable paykun Payment Module.'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default' => __('paykun')),
                'description' => array(
                    'title' => __('Description:'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default' => __('The best payment gateway provider in India for e-payment through credit card, debit card & netbanking.')),

                'merchantIdentifier' => array(
                    'title' => __('Merchant Identifier'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Secret Key" of "Integration -> Card payments integration at paykun."')),

                'access_token' => array(
                    'title' => __('Access Token'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paykun'),
                ),
                'encryption_key' => array(
                    'title' => __('Encryption Key'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paykun'),
                ),
                /*'gateway_url' => array(
                    'title' => __('Gateway URL'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paykun'),
                ),*/
                /*'transaction_status_url' => array(
                    'title' => __('Transaction Status Url'),
                    'type' => 'text',
                    'description' =>__('Given to Merchant by paykun')
                ),*/
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                ),
                'log' => array(
                    'title' => __('Do you want to log'),
                    'type' => 'text',
                    'options' => 'text',
                    'description' => "(yes/no)"
                )
            );


        }

        /**
         * @param $message
         */
        public function addLog($message) {

            //You can find this log on the path (wp-content\uploads\wc-logs)

            if($this->log == "yes"){

                $log = new WC_Logger();
                $log->add( 'paykun-payment', $message );

            }
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('paykun Payment Gateway').'</h3>';
            echo '<p>'.__('India online payment solutions for all your transactions by paykun').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for paykun, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){

            if($this->checkIfRequiredFieldMissing() == false) {

                echo '<p>'.__('Thank you for your order, please click the button below to pay via paykun.').'</p>';
                echo $this -> generate_paykun_form($order);

            } else {

                $this->addLog("PAYKUN ERROR: Some of the required field missing in admin. Please make sure Merchant Id, Access Token and Encryption Key is filled properly.");
                echo '<p><strong>PAYKUN ERROR:</strong> Some of the required field missing in admin. Please make sure Merchant Id, Access Token and 
                        Encryption Key is filled properly.</p>';
            }

        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                $order = new WC_Order($order_id);
            } else {
                $order = new woocommerce_order($order_id);
            }
            return array('result' => 'success', 'redirect' => add_query_arg('order',
                $order_id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url( true )))
            );

        }


        /**
         * Check for valid paykun server callback // response processing //
         **/
        function check_paykun_response(){

            global $woocommerce;

            if(isset($_REQUEST['payment-id']) && isset($_REQUEST['payment-id'])){
                $paymentId = $_REQUEST['payment-id'];
                $response = $this->_getcurlInfo($paymentId);

                if(isset($response['status']) && $response['status'] == "1" || $response['status'] == 1 ) {
                    $payment_status = $response['data']['transaction']['status'];
                    $order_id = $response['data']['transaction']['custom_field_1'];
                    if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                        $order = new WC_Order($order_id);
                    } else {
                        $order = new woocommerce_order($order_id);
                    }

                    $this->addLog("Response Code = " . $payment_status);

                    $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                    $this -> msg['class'] = 'error';
                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been Failed For Reason  : Payment Cancelled by customer";

                    if($payment_status === "Success") { //Transaction is success
                    //if(1) { //Transaction is success

                        $resAmout = $response['data']['transaction']['order']['gross_amount'];
                        if(($order->order_total	== $resAmout)) {

                            $this->addLog("amount matched");

                            if($order -> status !== 'completed'){
                                $this->addLog("SUCCESS. Order Id => $order_id, Payment Id => $paymentId");
                                $this -> msg['message'] = "Thank you for your order . 
                                Your transaction has been successful.  
                                Your  Order Id is => ".$order_id . " And Paykun Transaction Id => ".$paymentId;
                                $this -> msg['class'] = 'success';
                                $order -> add_order_note($this->msg['message']);
                                $order -> update_status('processing');

                                $this->addLog("Paid successfully with the order status 'processing' for order id $order_id");

                                //$woocommerce -> cart -> empty_cart();
                                /*if($order ->status == 'processing'){
                                    //Process code for 'processing status'
                                }*/
                            }
                        }
                        else {
                            // Order mismatch occur //

                            $this -> msg['class'] = 'error';
                            $this -> msg['message'] = "Order Mismatch Occur with Payment Id = $paymentId. Please try again. order status changed to 'failed'";
                            $order -> update_status('failed');
                            $this->addLog($this->msg['message']);
                            $order -> add_order_note('Failed');
                            $order -> add_order_note($this->msg['message']);
                        }
                    }
                    else { //Transaction failed

                        $this->addLog($this->msg['message']);
                        $order -> update_status('failed');
                        $order -> add_order_note('Failed');
                        $order -> add_order_note("With Payment Id => ".$paymentId);
                        $order -> add_order_note($this->msg['message']);
                    }
                }

                add_action('the_content', array(&$this, 'paykunShowMessage'));

                $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id==0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
            }
        }


        private function _getcurlInfo($iTransactionId) {

            try {

                $cUrl        = 'https://api.paykun.com/v1/merchant/transaction/' . $iTransactionId . '/';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $cUrl);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$this->merchantIdentifier", "AccessToken:$this->access_token"));

                $response       = curl_exec($ch);
                $error_number   = curl_errno($ch);
                $error_message  = curl_error($ch);

                $res = json_decode($response, true);
                curl_close($ch);

                return ($error_message) ? null : $res;

            } catch (ValidationException $e) {

                $this->addLog("Server couldn't respond, ".$e->getMessage());
                throw new ValidationException("Server couldn't respond, ".$e->getMessage(), $e->getCode(), null);
                return null;

            }

        }

        /**
         * @param $orderId
         * @return string
         */
        public function getOrderIdForPaykun($orderId) {

            $orderNumber = str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
            return $orderNumber;

        }

        /**
         * Generate paykun button link
         **/
        public function generate_paykun_form($order_id)  {

            require_once "Paykun/Payment.php";
            require_once "Paykun/Errors/ValidationException.php";

            global $woocommerce;

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                $order = new WC_Order($order_id);
            } else {
                $order = new woocommerce_order($order_id);
            }

            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);

            // pretty url check //
            $a = strstr($redirect_url, "?");
            if ($a) {
                $redirect_url .= "&wc-api=WC_paykun";
            } else {
                $redirect_url .= "?wc-api=WC_paykun";
            }
            $this->addLog("redirect url = this $redirect_url");

            if ($this->callbackurl == 'yes') {
                $post_variables = [];
                $post_variables["CALLBACK_URL"] = get_site_url() . '/?page_id=7&wc-api=WC_paykun';
            }


            if($this->checkIfRequiredFieldMissing()) {

                $this->addLog('PAYKUN: Some of the required field missing in admin. Please make sure Merchant Id, Access Token and Encryption Key is filled properly.');

                return '<p><strong>PAYKUN ERROR:</strong> Some of the required field missing in admin. Please make sure Merchant Id, Access Token and 
                        Encryption Key is filled properly.</p>';

            }
            $preparedData = $this->prepareData($order, $order_id, $redirect_url);
            return $this->initPayment($preparedData);

        }

        /**
         * @return bool
         */
        function checkIfRequiredFieldMissing() {

            return ($this->merchantIdentifier == "" || $this->access_token == "" || $this->encryption_key == "");

        }

        public function initPayment ($orderDetail) {

            try {
                $this->addLog(
                    "merchantId => ".$orderDetail['merchantId'].
                    ", accessToken=> ".$orderDetail['accessToken'].
                    ", encKey => ".$orderDetail['encKey'].
                    ", orderId => ".$orderDetail['orderId'].
                    ", purpose=>".$orderDetail['purpose'].
                    ", amount=> ".$orderDetail['amount']
                );
                $obj = new Payment($orderDetail['merchantId'], $orderDetail['accessToken'], $orderDetail['encKey'], true, true);

                // Initializing Order
                $obj->initOrder($orderDetail['orderId'], $orderDetail['purpose'], $orderDetail['amount'],
                    $orderDetail['successUrl'], $orderDetail['failureUrl']);

                // Add Customer
                $obj->addCustomer($orderDetail['customerName'], $orderDetail['customerEmail'], $orderDetail['customerMoNo']);

                // Add Shipping address
                $obj->addShippingAddress($orderDetail['s_country'], $orderDetail['s_state'], $orderDetail['s_city'], $orderDetail['s_pinCode'],
                    $orderDetail['s_addressString']);

                // Add Billing Address
                $obj->addBillingAddress($orderDetail['b_country'], $orderDetail['b_state'], $orderDetail['b_city'], $orderDetail['b_pinCode'],
                    $orderDetail['b_addressString']);

                $obj->setCustomFields(['udf_1' => $orderDetail['orginalOrderId']]);
                //Render template and submit the form
                $data = $obj->submit();

                $this->addLog("AllParams : " . $data['encrypted_request']); //Set here encryption request
                $this->addLog("Access Token : " . $data['access_token']); //Set here encryption request

                $form = $obj->prepareCustomFormTemplate($data, $orderDetail['cancelOrderUrl'], $orderDetail['loaderUrl']);

                return $form;

            } catch (ValidationException $e) {

                $this->addLog($e->getMessage());
                echo $e->getMessage();
                //throw new ValidationException("Something went wrong.".$e->getMessage(), $e->getCode(), null);
                return null;

            }

        }

        /**
         * @param $order
         * @param $orderId
         */
        private function prepareData($order, $order_id, $redirect_url) {
            global $woocommerce;
            $amt = $order->order_total;
            $purpose = "";
            $email = '';
            $mobile_no = '';

            try {
                $email = $order->billing_email;
            } catch (Exception $e) {
                $this->addLog("Server couldn't respond, ".$e->getMessage());
            }

            try {
                $mobile_no = preg_replace('#[^0-9]{0,13}#is', '', $order->billing_phone);
            } catch (Exception $e) {
                $this->addLog("Server couldn't respond, ".$e->getMessage());
            }
            $count = count($order->get_items());
            $currentCount = 1;
            foreach($order->get_items() as $item) {
                $stuff = ", ";
                if($count == $currentCount) {
                    $stuff = "";
                }
                $purpose = $item['name'].$stuff;

            }

            return array(
                'merchantId'    => $this->merchantIdentifier,
                'accessToken'   => $this->access_token,
                'encKey'    =>  $this->encryption_key,

                'orginalOrderId' => $order_id,
                'orderId'   => $this->getOrderIdForPaykun($order_id),
                'purpose'   => $purpose,
                "amount"    => $amt,
                'successUrl' => $redirect_url,
                'failureUrl' => $redirect_url,

                /*customer data*/
                "customerName"  => $order->billing_first_name. ' '.$order->billing_last_name,
                "customerEmail" =>  $email,
                "customerMoNo"  => $mobile_no,
                /*customer data over*/

                /*Shipping detail*/
                "s_country"     =>  WC()->countries->countries[$order->shipping_country],
                "s_state"       =>  WC()->countries->states[$order->shipping_country][$order->shipping_state],
                "s_city"        =>  $order->shipping_city,
                "s_pinCode"     =>  $order->shipping_postcode,
                "s_addressString" => $order->shipping_address_1.' '.$order->shipping_address_2,
                /*Shipping detail over*/

                /*Billing detail*/
                "b_country"     => WC()->countries->countries[$order->billing_country],
                "b_state"       => WC()->countries->states[$order->billing_country][$order->billing_state],
                "b_city"        => $order->billing_city,
                "b_pinCode"     => $order->billing_postcode,
                "b_addressString" => $order->billing_address_1.' '.$order->billing_address_2,
                /*Billing detail over*/
                "cancelOrderUrl"    => $order->get_cancel_order_url(),
                "loaderUrl" => "../images/loading.gif",
            );

        }

        // get all pages

        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

    }


    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_paykun_gateway($methods) {
        $methods[] = 'WC_paykun';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_paykun_gateway' );
}

?>