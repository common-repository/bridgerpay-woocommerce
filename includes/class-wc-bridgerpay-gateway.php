<?php
use Bridgerpay\Order;
use Bridgerpay\Payment;

class WC_Bridgerpay_Gateway extends WC_Payment_Gateway
{
    use \Bridgerpay\Traits\WC_Bridgerpay_Subscriptions;
    
    private $user_name;
    private $password;

    public function __construct()
    {
        loadBridgerPayLibrary();

        $this->id = 'bridgerpay_gateway';
        $this->icon = apply_filters('woocommerce_bridgerpay_icon', '');
        $this->has_fields = true;
        $this->method_title = _x('BridgerPay Payment', 'woocommerce');
        $this->method_description = __('Accept credit card payments and cryptocurrency on your website via BridgerPay universal payment gateway.', 'woocommerce');
        


        $this->init_form_fields();
        $this->init_settings();
        
        $this->supports           = array(
			'products',
			// 'subscriptions',
			// 'default_credit_card_form',
			// 'subscription_cancellation', 
			// 'subscription_suspension', 
			// 'subscription_reactivation',
			// 'subscription_amount_changes',
			// 'subscription_date_changes',
			// 'subscription_payment_method_change',
			// 'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			// 'multiple_subscriptions',
			'refunds'
        );

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description       = $this->get_option( 'description' );
        // $this->instructions       = $this->get_option( 'instructions' );
        $this->user_name = $this->get_option('user_name');
        $this->password = $this->get_option('password');
        $this->activation_key = $this->get_option("activation_key");
        $this->cashier_key = $this->get_option("cashier_key");
        $this->cashier_id = $this->get_option("cashier_id");
        $this->environment = $this->get_option("environment");
        $this->version = $this->get_option("version","v2");
        switch ($this->environment){
            case "Sandbox-v1":
                $this->activation_url = "https://signup-sandbox.bridgerpay.dev/api/v1/license/activate/";
                $this->version = 'v1';
                break;
            case "Sandbox-v2":
                $this->activation_url = "https://signup-sandbox.bridgerpay.dev/api/v2/license/activate/";
                $this->version = 'v2';
                break;
            case "Staging":
                $this->activation_url = "https://signup.bridgerpay.dev/api/v2/license/activate/";
                $this->version = 'v2';
                break;
            case "Production-v1":
                $this->activation_url = "https://signup.bridgerpay.com/api/v1/license/activate/";
                $this->version = 'v1';
                break;
            default:
                $this->activation_url = "https://signup.bridgerpay.com/api/v2/license/activate/";
                $this->version = 'v2';
                break;
        }

        if($this->get_option("enable_subscriptions") == 'yes')
            $this->maybe_init_subscriptions();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_bridgerpay_gateway', array($this, 'callback_success'));
        add_action('woocommerce_after_checkout_form', array($this, 'bridgerpay_gateway_fingerprint_checkout'));
        add_action('woocommerce_pay_order_after_submit', array($this, 'bridgerpay_gateway_fingerprint_checkout'));

        add_action( 'admin_enqueue_scripts', array($this, 'form_fields_js'));

        //cancel and refund hooks
        // add_action( 'woocommerce_order_status_cancelled', [ $this, 'cancel_payment' ] );
		// add_action( 'woocommerce_order_status_refunded', [ $this, 'cancel_payment' ] );

        add_action('wp_footer', [$this, 'wp_footer']);

        /* call success order api  */
        add_action('woocommerce_thankyou', [$this, 'woocommerce_thankyou'], 11, 1);
        add_action( 'woocommerce_receipt_bridgerpay_gateway', array( $this, 'receipt_page' ), 10 );
        $this->redirect_to_thank_you_page();
        $this->bridger_pay_update_order_status();
    }

    public function bridger_pay_update_order_status(){
        if(!(isset($_GET['orderId']) && $_GET['orderId'] && isset($_GET['sessionId']) && $_GET['sessionId'] && isset($_GET['status']) && $_GET['status'] != 'approved' && !isset($_GET['key'])))
            return false;
        $order_id = (int) $_GET['orderId'];
        $order = wc_get_order( $order_id );
        if(empty($order))
            return false;

        if ($order->get_status() == "completed" || $order->get_status() == "on-hold" || $order->get_status() == "processing")
            return false;

        $user_name = $this->getUserName();
        $password = $this->getPassword();
        
        $options = array(
            'api_key' => $this->get_option('api_key'),
            'cashier_key' => $this->get_option('cashier_key'),
            'api_url' => $this->get_option('api_url'),
            'embed_url' => $this->get_option('embed_url'),
            'version' => $this->get_option('version'),
        );
        if(!($user_name && $password))
            return false;
        
        $payment = new Payment($user_name, $password, $options);
        $verified_order = $payment->getTransactionByOrderID($order_id);
        if($order_id && $verified_order && is_object($verified_order) && isset($verified_order->result) && isset($verified_order->result->deposit) && isset($verified_order->result->deposit->merchant_order_id) && $verified_order->result->deposit->merchant_order_id == $order_id){
            if(isset($verified_order->result->deposit->status) && $verified_order->result->deposit->status == 'declined'){
                $decline_message = __('Card was declined.', '');
                if(isset($verified_order->result->deposit->decline_reason) && $verified_order->result->deposit->decline_reason){
                    $decline_message = $verified_order->result->deposit->decline_reason;
                }
                // $order->add_order_note($decline_message);
                $order->update_status( 'wc-failed', $decline_message);
            }
        }
    }

    function receipt_page( $order_id ){
      
        $order = new \WC_Order( $order_id );
        $this->bridgerpay_gateway_fingerprint_checkout();
        ?>
          <div id="step-payment" class="checkout-step">
              <div class="checkout-step-heading clearfix">
                  <div class="sprite-stepsIndicator indicatorStep3 step-indicator pull-left"></div>
                  <h3 class="step-title"><?php _e('', 'woocommerce_icredit'); ?></h3>
              </div>
              <div class="checkout-frame" >
                <div class="payment_method_bridgerpay_gateway"></div>
                  
              </div><!-- .checkout-frame -->
          </div>
          <?php
          
    }

    public function wp_footer(){
        ?>
        <style>
            .wc_payment_method .payment_box.payment_method_bridgerpay_gateway{
                background-color:transparent;
            }
        </style>
        <?php


        if ( is_wc_endpoint_url( 'order-received' ) ) {
            if(isset($_GET['orderId']) && $_GET['orderId']){
                $order_id = $_GET['orderId'];
                $this->woocommerce_thankyou($order_id);
            }
        }
    }

    public function process_admin_options(){
        $old_activation_key = $this->activation_key;
        $new_activation_key = $this->get_post_data()['woocommerce_bridgerpay_gateway_activation_key'];
        // If the activation key or env has changed, try to activate the plugin.
        if ($old_activation_key != $new_activation_key) {
            $results_from_bridger = $this->get_bridgerpay_settings($this->activation_url.$new_activation_key);
            // if got activation results from BridgerPay
            if (!empty($results_from_bridger)){
                parent::update_option('user_name', $results_from_bridger->user_name);
                parent::update_option('password', $results_from_bridger->password);
                parent::update_option('api_key', $results_from_bridger->api_key);
                parent::update_option('cashier_key', $results_from_bridger->cashier_key);
                parent::update_option('api_url',$results_from_bridger->api_url);
                parent::update_option('embed_url',$results_from_bridger->embed_url);
                parent::update_option('version',$this->version);

                $WP_settings = new WC_Admin_Settings();
                $WP_settings->add_message('BridgerPay plugin is activated successfully!');
            }
        }

        $saved = parent::process_admin_options();
        return $saved;
    }

    public function cancel_payment($order_id){
        $order = wc_get_order( $order_id );

        if ( empty($order) ) {
            return;
        }

        if ( 'bridgerpay_gateway' === $order->get_payment_method() ) {

            $order_total = (float) $order->get_total();
            $total_refunded = (float) $order->get_total_refunded();
            $amount = $order_total - $total_refunded;
			$this->process_refund($order_id, $amount, array('full' => __('Full Refund.', 'woocommerce')));
        }
    }
    protected function get_bridgerpay_settings($url) {

        //$checkout_url = wc_get_checkout_url();
        $request_body = array(
            "webhook_url" => trailingslashit(get_site_url()) . '?wc-api=bridgerpay_gateway',
            "success_redirect_url"=> trailingslashit(trailingslashit(get_site_url()) . 'checkout/order-received'),
            "cancel_redirect_url"=> wc_get_cart_url(),
            "failure"=> wc_get_cart_url(),
            "domain"=> $_SERVER['SERVER_NAME'],
            "plugin_type"=> "woocommerce"
        );
        $args =
            array('body'=>
                wp_json_encode($request_body),
                'headers' => array(
                    'Content-Type' => 'application/json;charset=' . get_bloginfo( 'charset' )),
            );

        $request = wp_safe_remote_post($url,$args);

        if ($request === false) {
            throw new \Exception("Connection error");
        }
        if (wp_remote_retrieve_response_code($request) != 200) {
            $WP_settings = new WC_Admin_Settings();
            $WP_settings->add_error('Activation failed, Please contact BridgerPay Team');
            return null;
        }

        $json = json_decode($request['body']);
        return $json->result;
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $output = false;
        $output_message = __( 'There was some problem with refund please try again.', 'woocommerce');

        $order = wc_get_order( $order_id );

        if ( empty($order)) {
            $output_message = __( 'No order found.', 'woocommerce');
            throw new \Exception( $output_message );
        }
        
        if(empty($amount) || $amount == '0.00' || $amount == '0'){
            $output_message = __( 'Amount is missing.', 'woocommerce');
            throw new \Exception( $output_message );
        }
        
     
        $amount = (float) $amount;
        $order_total = (float) $order->get_total();
        $order_currency = $order->get_currency();

        $total_refunded = (float) $order->get_total_refunded();

        if(is_array($reason) && isset($reason['full'])){
            $total_refunded = $total_refunded + $amount;
        }

        if ( (/*$amount +*/ $total_refunded) > $order_total){
            $output_message = __( 'Refund Amount should be less than or equal to order total amount.', 'woocommerce');
            throw new \Exception( $output_message );
        }

        $order_data = $order->get_meta('bridger_pay_order_completed_data');
        
        if(!empty($order_data)){
            $callback = $this->is_order_support_refund($order_id);
            if(!empty($callback)){
                $api_url = trailingslashit($this->get_option('api_url'));
                $bearer_token = false;

                $auth_url = $this->version."/auth/login";
                $auth_endpoint = $api_url.$auth_url;

                $user_name = $this->get_option('user_name');
                $password = $this->get_option('password');
                $data = array (
                    "user_name" => $user_name,
                    "password" => $password,
                );
                $body = apply_filters('convertkit-call-args', $data);

                $response = bridger_pay_remote_post($auth_endpoint, $body);

                if(!is_wp_error($response) && isset($response['response']) && isset($response['response']['code']) && $response['response']['code'] == 200 && isset($response['body'])){
                    $body = @json_decode($response['body'], true);
                    
                    if(is_array($body) && isset($body['result']) && isset($body['result']['access_token']) && isset($body['result']['access_token']['token'])){
                        $bearer_token = $body['result']['access_token']['token'];
                        $body['result']['access_token']['creation_time'] = wp_date('U');
                        update_option('bridgerpay_gateway_access_token', $body['result']['access_token']);
                    }
                }

                if($bearer_token){
 
                    $bridger_pay_refund_id = $callback['data']['charge']['refund_id'];

                    if(is_array($reason) && isset($reason['full'])){
                        $endpoint = $api_url.$this->version.'/'.$this->get_option('api_key').'/transactions/'.$bridger_pay_refund_id.'/refund';
                        $reason = $reason['full'];
                    }
                    else
                        $endpoint = $api_url.$this->version.'/'.$this->get_option('api_key').'/transactions/'.$bridger_pay_refund_id.'/refund/'.$amount;
                    $headers = array(
                        'Authorization' => 'Bearer '.$bearer_token,
                    );
                    $body = array();

                    $response = bridger_pay_remote_post($endpoint, $body, $headers);
                    // db($response);
                    if(!is_wp_error($response) && isset($response['response']) && isset($response['response']['code']) && $response['response']['code'] == 200 && isset($response['body'])){
                        $body = @json_decode($response['body'], true);
                        if(is_array($body) && isset($body['result']) && isset($body['result']['refund_transaction_id']) && isset($body['result']['status']) && $body['result']['status'] == 'approved'){
                            $output = true;
                        }                        
                    } 

                }

            }else{
                $output_message = __( 'This order doesn\'t support refund.', 'woocommerce');
            }
        }else{
            $output_message = __( 'No bridgerpay data found for this order.', 'woocommerce');
        }


        if($output == true){
            $order->add_order_note( $reason );
            return true;
        }
        else
            throw new \Exception( $output_message );
    }


    public function form_fields_js( $hook_suffix ){
        wp_enqueue_script( 'newscript', BRIDGERPAY_PATH . 'assets/js/bridgerpay.js', array('jquery'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'bridgerpay' ),
                'type' => 'checkbox',
                'label' => __( 'Enable BridgerPay Payment', 'bridgerpay' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'bridgerpay' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'bridgerpay' ),
                'default' => __( 'Online payments', 'bridgerpay' ),
                //'desc_tip'      => true,
            ),
            'gateway_icon' => array(
                'title'             => __( 'Title Icon', 'bridgerpay' ),
                'type'              => 'gateway_icon',
                // 'custom_attributes' => array(
                //     'onclick' => "location.href='http://www.woothemes.com'",
                // ),
                // 'description'       => __( 'Customize your settings by going to the integration site directly.', 'woocommerce-integration-demo' ),
                // 'desc_tip'          => true,
            ),
            'description'     => array(
				'title'       => __( 'Description', 'bridgerpay' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'bridgerpay' ),
				// 'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce' ),
				// 'desc_tip'    => true,
			),
            'activation_key' => array(
                'title' => __( 'Activation key', 'bridgerpay' ),
                'type' => 'text',
                //'description' => __( '', 'bridgerpay' ),
                'default' => '',
                'description' => __( 'Activation key from BridgerPay, the key can be activated only once', 'bridgerpay' ),
            ),
            'environment' => array(
                'title' => __( 'Environment', 'bridgerpay' ),
                'type' => 'select',
                'options' => array(
                    'Production' => 'Production',
                    'Sandbox-v1' => 'Sandbox-v1',
                    'Sandbox-v2' => 'Sandbox-v2',
                    'Production-v1' => 'Production-v1',
                    'Staging' => 'Staging'
                ),
                'description' => __( '', 'bridgerpay' ),
                'default' => 'Production',
            ),
            'theme_options' => array(
                'title' => __( ' Theme options', 'bridgerpay' ),
                'type' => 'select',
                'options' => array(
                    'dark' => 'dark',
                    'light' => 'light',
                    'transparent' => 'transparent',
                    'bright' => 'bright'
                ),
                'description' => __( '', 'bridgerpay' ),
                'default' => 'dark'
            ),
            'pay_mode' => array(
                'title' => __( 'Checkout UI Wording', 'bridgerpay' ),
                'type' => 'select',
                'options' => array(
                    'pay' => 'Pay',
                    'deposit' => 'Deposit',
                ),
                'description' => __( '', 'bridgerpay' ),
                'default' => 'pay'
            ),
            'deposit_button_text' => array(
                'title' => __( 'Custom Text for Checkout Button', 'bridgerpay' ),
                'type' => 'text',
                'default' => 'Pay',
                'class' => 'input-bridger',
            ),
            'hide_header' => array(
                'title' => __( 'Hide Header', 'bridgerpay' ),
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            'tick_save_credit_card_checkbox' => array(
                'title' => __( 'Tick save credit card checkbox', 'bridgerpay' ),
                'label'   => __( 'This check box indicates that the checkbox for saving the debit or credit card is preselected by default. (Enable for subscription based transactions)
', 'bridgerpay' ),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'hide_save_credit_card_checkbox' => array(
                'title' => __( 'Hide save credit card checkbox', 'bridgerpay' ),
                'label'   => __( 'This check box hides the checkbox for saving the debit or credit card as a token within the Cashier widget. (Enable for subscription based transactions)
', 'bridgerpay' ),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'enable_subscriptions' => array(
                'title' => __( 'Enable subscriptions', 'bridgerpay' ),
                'type' => 'checkbox',
                'default' => 'no',
                'label'   => __( 'Enable subscriptions based payments', 'bridgerpay' ),
                // 'description' => __( 'Currently only our Stripe PSP support this feature.', 'bridgerpay' ),
            ),
        );
    }

    public function payment_fields() {
    }

    public function bridgerpay_gateway_fingerprint_checkout() {

        if ( is_checkout() ) {
            
            $checkout_url = 'checkout';
            if(is_wc_endpoint_url("order-pay")){
                $checkout_url = 'order-pay';
            }

            $cashier_has_token = 'no';
            $script_path = BRIDGERPAY_DIR.'assets/js/add_cashier.js';
            wp_enqueue_script( 'add_cashier', BRIDGERPAY_PATH . 'assets/js/add_cashier.js', array('jquery'), get_file_time($script_path) );
            $cashier_description = json_encode(array($this->get_option( 'description' )));

            if($checkout_url == 'order-pay' && get_query_var('order-pay')){
                $this->process_payment(get_query_var('order-pay'));
            }

        $settings = get_option('woocommerce_bridgerpay_gateway_settings');
        if (isset($settings['cashier_token']) && !empty($settings['cashier_token'])) {
            $cashier_has_token = 'yes';
            $version = $settings['version'];
            $embed_url = $settings['embed_url'];
            $cashier_key = $settings['cashier_key'];
            $cashier_token = $settings['cashier_token'];
            $theme_options = $settings['theme_options'];
            $deposit_button_text = $this->get_option( 'deposit_button_text' );
            if(!empty($this->get_option( 'pay_mode' )) && $this->get_option( 'pay_mode' ) && $this->get_option( 'pay_mode' ) == 'pay')
                $pay_mode = 'true';
            else
                $pay_mode = 'false';



            wp_add_inline_script( 'add_cashier', 'var cashier_url = "' . $embed_url . '",
                                                              data_cashier_key = "' . $cashier_key . '",
                                                              data_cashier_token = "' . $cashier_token . '",
                                                              data_hide_header = "true",
                                                              version  ="'. $version.'",
                                                              bp_checkout_url  ="'. $checkout_url.'",
                                                              data_deposit_button_text = "' . $deposit_button_text . '",
                                                              data_lang = "' . $this->get_site_lang() . '",
                                                              data_theme = "' . $theme_options . '";',
                                                              
                                 'before' );
            //data_pay_mode = "' . $pay_mode . '",
            add_action('wp_footer', function(){
                if(!wp_doing_ajax())
                    $this->update_option('cashier_token', '');
            });
        }

        wp_add_inline_script( 'add_cashier', 'var cashier_has_token = "' . $cashier_has_token . '", bridgerpay_cashier_description = '.$cashier_description.';',
                                 'before' );
    }
    }

    public function get_icon() {
        // $image_url =  BRIDGERPAY_PATH . 'assets/images/visa-master_card.png';
        // $icon_html = '<img src="' . $image_url . '" alt="BridgerPay mark" width="51" height="32" />';

        $icon_html = '';
        $image_url = $this->get_option('gateway_icon');
        if($image_url)
            $icon_html = '<img src="' . $image_url . '" alt="BridgerPay mark" width="51" height="32" />';

        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    public function process_payment( $order_id ) {

        $user_name = $this->getUserName();
        $password = $this->getPassword();

        if($this->get_option('hide_header') == 'yes')
            $hide_header = true;
        else
            $hide_header = false;

        if($this->get_option('tick_save_credit_card_checkbox') == 'yes')
            $tick_save_credit_card_checkbox = true;
        else
            $tick_save_credit_card_checkbox = false;

        if($this->get_option('hide_save_credit_card_checkbox') == 'yes')
            $hide_save_credit_card_checkbox = true;
        else
            $hide_save_credit_card_checkbox = false;

        if(!empty($this->get_option( 'pay_mode' )) && $this->get_option( 'pay_mode' ) && $this->get_option( 'pay_mode' ) == 'pay')
            $pay_mode = true;
        else
            $pay_mode = false;

        $options = array(
            'api_key' => $this->get_option('api_key'),
            'cashier_key' => $this->get_option('cashier_key'),
            'api_url' => $this->get_option('api_url'),
            'embed_url' => $this->get_option('embed_url'),
            'version' => $this->get_option('version'),
            'hide_header' => $hide_header,
            'pay_mode' => $pay_mode,
            'tick_save_credit_card_checkbox_by_default' => $tick_save_credit_card_checkbox,
            'hide_save_credit_card_checkbox' => $hide_save_credit_card_checkbox,
        );

        $order = new WC_Order( $order_id );

        $address = $order->get_address();

        $bridgerPayOrder = new Order();
        $bridgerPayOrder->setId($order->get_id());
        $bridgerPayOrder->setCurrency($order->get_currency());
        $bridgerPayOrder->setCountry($address['country']);
        $bridgerPayOrder->setState($address['state']);
        $bridgerPayOrder->setPhone($address['phone']);
        $bridgerPayOrder->setZipCpde($address['postcode']);
        $bridgerPayOrder->setCity($address['city']);
        $bridgerPayOrder->setAddress($address['address_1']);
        $bridgerPayOrder->setAmountLock(true);
        $bridgerPayOrder->setCurrencyLock(true);
        $bridgerPayOrder->setEmail($address['email']);
        $bridgerPayOrder->setLastName($address['last_name']);
        $bridgerPayOrder->setFirstName($address['first_name']);
        $bridgerPayOrder->setAmount($order->get_total());

        $payment = new Payment($user_name, $password, $options);
        $payment->setOrder($bridgerPayOrder);

        $this->update_option('cashier_token', '');

        try {
            $cashier_token = $payment->createCashierSession();
            if ( !empty($cashier_token) ) {
                $this->update_option('cashier_token', $cashier_token);
                if ($order->get_status() != 'pending') {
                    $order->update_status('pending');
                }
            } else {
                throw new \Exception("Bad request");
            }
        } catch (\Exception $e) {
            wc_add_notice(  'Request error ('. $e->getMessage() . ')', 'error' );
            return false;
        }
        $result = array(
            'result' => 'success',
            'reload'=> true
        );
        if (!empty($_GET['pay_for_order'])) {
            $result['redirect'] = $_SERVER['HTTP_REFERER'];
        }
        $result = apply_filters('bridgerpay_before_process_payment', $result, $order);
        return $result;
    }


    public function get_order_success_url ($order = null) {
        if ( !$order ) 
            return false;

		return add_query_arg( 'key', $order->get_order_key(), wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() ) );
    }

    public function redirect_to_thank_you_page(){
        if ( is_wc_endpoint_url( 'order-received' ) ) {
            if(isset($_GET['orderId']) && $_GET['orderId'] && isset($_GET['sessionId']) && $_GET['sessionId'] && isset($_GET['status']) && $_GET['status'] == 'approved' && !isset($_GET['key'])){
                $order_id = $_GET['orderId'];
                $order = wc_get_order( $order_id );
                if(!empty($order)){
                    $order_success_url = $this->get_order_success_url($order);
                    $order_success_url = add_query_arg(array('orderId' => $_GET['orderId'], 'sessionId' => $_GET['sessionId'], 'status' => $_GET['status']), $order_success_url);
                    wp_redirect($order_success_url);exit();
                }
            }
        }
    }

    public function callback_success() {

        $callback_json = @file_get_contents('php://input');
        $callback = &json_decode($callback_json, true);

        $response = new \Bridgerpay\Response($callback);
        // if(is_array($callback) && isset($callback['data']) && isset($callback['data']['order_id']) && $callback['data']['order_id']){
		// 	update_post_meta($callback['data']['order_id'], '_bridgerpay_testing_callback', $callback_json);
		// }

        if($response->isComplete()) {

            if(is_array($callback) && isset($callback['webhook']) && $callback['webhook']['type'] == 'approved'){
                if(isset($callback['data']) && isset($callback['data']['order_id'])){
                    $order_id = $callback['data']['order_id'];
                    $order = wc_get_order( $order_id );
                    
                    if(!empty($order)){

                        global $woocommerce;
                        $woocommerce->cart->empty_cart();
                        if(
                            isset($callback['data']['charge']) && 
                            isset($callback['data']['charge']['id']) && 
                            !empty($callback['data']['charge']['id']) && 
                            isset($callback['data']['charge']['operation_type']) && 
                            $callback['data']['charge']['operation_type'] == 'deposit'
                        ){
                            $order->payment_complete($callback['data']['charge']['id']);
                            $order->update_meta_data( 'bridger_pay_order_completed_data', $callback_json );
                            $order->save();
                        }
                        
                    }
                    
                }
            }
            
        }
    }

    protected function getUserName() {
        return $this->user_name;
    }

    protected function getPassword() {
        return $this->password;
    }

    public function can_refund_order( $order ) {
        $order_data = $this->is_order_support_refund($order->get_id());
        if(empty($order_data))
            return false;
        else
            return true;
    }

    public function is_order_support_refund($order_id){
        $output = [];
        $order = wc_get_order( $order_id );

        if ( !empty($order)) {

            $order_data = $order->get_meta('bridger_pay_order_completed_data');

            if(!empty($order_data)){
                
                $callback = @json_decode($order_data, true);
                if(is_array($callback) && isset($callback['data']) && isset($callback['data']['charge']) && isset($callback['data']['charge']['is_refundable']) && $callback['data']['charge']['is_refundable'] == true && isset($callback['data']['charge']['refund_id']) && !empty($callback['data']['charge']['refund_id']) && isset($callback['data']['charge']['id']) && !empty($callback['data']['charge']['id'])){
                    $output = $callback;
                }

                if(!wp_doing_ajax()){
                    $total_refunded = (float) $order->get_total_refunded();
                    $order_total = (float) $order->get_total();
                    if ( $total_refunded >= $order_total){
                        $output = [];
                    }
                }
            }
        }

        return $output;
    }

    public function woocommerce_thankyou($order_id){

        
        if(!$order_id){
            return;
        }

        $order = wc_get_order( $order_id );
                    
        if(!empty($order)){
            $user_name = $this->getUserName();
            $password = $this->getPassword();
            
            $options = array(
                'api_key' => $this->get_option('api_key'),
                'cashier_key' => $this->get_option('cashier_key'),
                'api_url' => $this->get_option('api_url'),
                'embed_url' => $this->get_option('embed_url'),
                'version' => $this->get_option('version'),
            );
            if($user_name && $password){
                $payment = new Payment($user_name, $password, $options);
            
                $verified_order = $payment->getTransactionByOrderID($order_id);
                
                if($order_id && $verified_order && isset($verified_order->result) && isset($verified_order->result->deposit) && isset($verified_order->result->deposit->merchant_order_id) && $verified_order->result->deposit->merchant_order_id == $order_id){
                    $order->payment_complete();
                    // $order->update_status( 'wc-completed' );
                    $order->save();
                }
            }
        }
        
    }

    public function get_site_lang() {
        $lang = get_locale();
    
        if(!empty($lang)) {
          $str = explode('_', $lang);
          return (isset($str[0]) && !empty($str[0]))? trim($str[0]) : trim($lang);
        }
      }


    /**
     * Generate gateway_icon HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_gateway_icon_html( $key, $data ) {
        $field    = 'woocommerce_'.$this->id . '_' . $key;
        $defaults = array(
            // 'class'             => 'button-secondary',
            'css'               => '',
            'custom_attributes' => array(),
            'desc_tip'          => false,
            'description'       => '',
            'title'             => '',
        );

        $data = wp_parse_args( $data, $defaults );
        $value = $this->get_option('gateway_icon');
        
        wp_enqueue_media();
        $script_path = BRIDGERPAY_DIR.'assets/admin/js/settings.js';
        wp_enqueue_script( 'settings', BRIDGERPAY_PATH . 'assets/admin/js/settings.js', array('jquery'), get_file_time($script_path) );

        $script_path = BRIDGERPAY_DIR.'assets/admin/css/settings.css';
        wp_enqueue_style( 'settings', BRIDGERPAY_PATH . 'assets/admin/css/settings.css', array(), get_file_time($script_path) );
        
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data ); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input type="text" value="<?php echo $value; ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?> />
                    <input type="button" id="" class="otw_file_upload_button button" value="Select Image">
                    <div class="otw_single_image_preview bb_image_preview">
                    <?php if($value){
                        echo '<span><img src="'.$value.'"><a href="#" class="otw_dismiss_icon">&nbsp;</a></span>';
                    } ?>
                    <div class="clearboth"></div></div>
                    <?php echo $this->get_description_html( $data ); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

}