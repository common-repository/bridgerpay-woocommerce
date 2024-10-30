<?php
namespace Bridgerpay;

class Payment {
    private $user_name;
    private $password;
    private $api_key;
    private $cashier_key;
    private $api_url;
    private $cashier_url;
    protected $cancelUrl;
    protected $notificationUrl;
    protected $order;
    protected $returnUrl;
    private $payment_options;
    private $pay_mode;

    const URL_AUTH = "/auth/login";
    const URL_CASHIER = "/cashier/session/create/";

    public function __construct($user_name, $password, $options) {
        $this->user_name = $user_name;
        $this->password = $password;
        $this->api_key = $options['api_key'];
        $this->cashier_key = $options['cashier_key'];
        $this->api_url = $options['api_url'];
        if(isset($options['cashier_url']))
            $this->cashier_url = $options['cashier_url'];
        $this->version = $options['version'];
        $this->payment_options = $options;
        if(isset($options['pay_mode']))
            $this->pay_mode = $options['pay_mode'];
        else
            $this->pay_mode = false;
        
    }

    protected function _apiRequest($url, $postfields, $header = '') {

        $headers = array(
            'Content-type'  => 'application/json',
        );

        if (!empty($header)) {
            $headers['Authorization'] = 'Bearer ' . $header;
        }

        $body = apply_filters('convertkit-call-args', $postfields);

        $args = array(
            'method'      => 'POST',
            'body'        => json_encode($body),
            'headers'     => $headers,
        );

        $request = wp_remote_post($url, $args);
        if (is_wp_error($request) || $request === false) {
            throw new \Exception("Connection error");
        }

        return json_decode($request['body']);
    }

    protected function _apiGetRequest($url, $header = '') {
        
              
        
        $headers = array(
            'Content-type'  => 'application/json',
        );

        

        if (!empty($header)) {
            $headers['Authorization'] = 'Bearer ' . $header;
        }

        $args = array(
            // 'method'      => 'POST',
            // 'body'        => json_encode($body),
            'headers'     => $headers,
        );

        
        $request = wp_remote_get($url, $args);
        if (is_wp_error($request) || $request === false) {
            throw new \Exception("Connection error");
        }

        return json_decode($request['body']);

    }

    public function authorisation() {
        $url = $this->getAPIUrl() .'/' . $this->getVersion() . self::URL_AUTH;

        $user_name = $this->getUserName();
        $pasword = $this->getPassword();
        $data = array (
            "user_name" => $user_name,
            "password" => $pasword,
        );

        $response = $this->_apiRequest($url, $data);
        //print_r($response);
        //die();
        if ($response->response->code == 200) {
            return $response->result->access_token->token;
        } else { // if ($response->response->code == 403) {
            throw new \Exception($response->error->message);
        }
    }

    public function createCashierSession() {
        $url = $this->getAPIUrl() .'/'.$this->getVersion() . self::URL_CASHIER . $this->getAPIKey();
        $auth_token = $this->authorisation();

        if (!empty($this->order)) {
            
            $order = $this->getOrder();
            $data = array (
                "order_id"       => $order->getId(),
                "country"        => $order->getCountry(),
                "state"        	 => $order->getState(),
                "currency"       => $order->getCurrency(),
                "phone"          => $order->getPhone(),
                "zip_code"       => $order->getZipCode(),
                "city"           => $order->getCity(),
                "address"        => $order->getAddress(),
                "currency_lock"  => $order->getCurrencyLock(),
                "amount_lock"    => $order->getAmountLock(),
                "email"          => $order->getEmail(),
                "last_name"      => $order->getLastName(),
                "first_name"     => $order->getFirstName(),
                "amount"         => $order->getAmount(),
                "cashier_key"    => $this->getCashierKey(),
                'hide_header' => $this->payment_options['hide_header'],
                "pay_mode" => $this->pay_mode,
                "personal_id" => $order->getEmail(),
                'tick_save_credit_card_checkbox_by_default' => $this->payment_options['tick_save_credit_card_checkbox_by_default'],
                'hide_save_credit_card_checkbox' => $this->payment_options['hide_save_credit_card_checkbox'],
            );
            if($this->get_site_lang())
                $data['language'] = $this->get_site_lang();

        } else {
            throw new \Exception("Order not exists");
        }

        $response = $this->_apiRequest($url, $data, $auth_token);

        if ($response->response->code == 200) {
            return $response->result->cashier_token;
        } else if ($response->response->code == "400") {
            
            if($response->response->message == 'validation_error' && isset($response->result) && is_array($response->result) && count($response->result) >= 1){
                
                // $output_error_message = '';
                foreach($response->result as $key=>$error_messagge){
                    if(isset($error_messagge->message) && $error_messagge->message)
                        wc_add_notice(  $error_messagge->message, 'error' );
                    // $output_error_message .= $key.' - '.$error_messagge->message.'<br />';
                }
                // if(!$output_error_message){
                //     $output_error_message = $response->response->message;
                // }
                // throw new \Exception($response->response->message);
            }
            throw new \Exception($response->response->message);
        }
        else if ( $response->response->code == "403") {
            throw new \Exception(__("No payment service provider found to support this request.", "bridgerpay"));
        }
    }

    public function getTransactionByOrderID($order_id) {
        // \Configuration::updateValue('last_updated_config_test', 'get transactio by id');
        if (!empty($order_id) ) {
            
            $url = $this->getAPIUrl() .'/'.$this->getVersion() . '/merchant/' . $this->getAPIKey().'/deposits/'.$order_id;
            
            $auth_token = $this->authorisation();
            // \Configuration::updateValue('last_updated_config_test', $auth_token);
            if(empty($this->error_message) && $auth_token){
                // \Configuration::updateValue('last_updated_config_test', 'inside auth condition');
                $response = $this->_apiGetRequest($url, $auth_token);
                
                // \Configuration::updateValue('last_updated_config_test', serialize($response));
                if ($response && isset($response->result)) {
                    return $response;
                } else if ($response && $response->response->code == "400") {
                    $this->error_message = $response->response->message;
                    return '';
                }
                elseif(!empty($this->error_message)){
                    return '';
                }
                else{
                    $this->error_message = 'Unknown Error';
                    return '';
                }
            }
    
            
        }
        
    }

    public function make_subscription_payment($order, $order_data = array(), $amount = 0) {
        $output = __('Unknown Error', 'bridgerpay');
        $credit_card_token = $order_data['data']['charge']['attributes']['credit_card_token'];
        $psp_name = $order_data['data']['psp_name'];
        // $url = 'https://api.bridgerpay.dev/mpi/v2/'.$this->getAPIKey().'/deposit/credit-card/NCR';
        $url = $this->getAPIUrl() .'/mpi/'.$this->getVersion().'/'.$this->getAPIKey().'/deposit/credit-card/';
        $url .= $psp_name;
        
        // $url = $this->getAPIUrl() .'/'.$this->getVersion() . self::URL_CASHIER . $this->getAPIKey();
        $auth_token = $this->authorisation();
        if (!empty($order) && !empty($credit_card_token)) {
            
            $address = $order->get_address();

            $data = array (
                "order_id"       => $order->get_id(),
                "currency"       => $order->get_currency(),
                "amount"         => $amount,
                "credit_card_token" => $credit_card_token,
                "email"          => $address['email'],
                "phone"          => $address['phone'],
                "cashier_key"    => $this->getCashierKey(),
                "country"        => $address['country'],
                "is_recurring" => true,
            );
        } else {
            return __("Order not exists", 'bridgerpay');
        }

        $response = $this->_apiRequest($url, $data, $auth_token, true);
        
        if ($response && $response->response->code == 200) {
            return $response->result;
        } else if ($response->response->message) 
            $output = $response->response->message;
        

        return $output;
    }

    protected function getUserName() {
        return $this->user_name;
    }

    protected function getPassword() {
        return $this->password;
    }

    protected function getAPIKey() {
        return $this->api_key;
    }

    protected function getCashierKey() {
        return $this->cashier_key;
    }

    protected function getAPIUrl() {
        return $this->api_url;
    }
    protected function getVersion() {
        return $this->version;
    }
    protected function getCashierUrl() {
        return $this->cashier_url;
    }

    public function setCancelUrl($url) {
        $this->cancelUrl = $url;
        return $this;
    }

    public function setNotificationUrl($url) {
        $this->notificationUrl = $url;
        return $this;
    }

    public function setOrder(Order $order) {
        $this->order = $order;
        return $this;
    }

    public function setReturnUrl($url) {
        return $this->returnUrl;
    }

    public function getCancelUrl() {
        return $this->cancelUrl;
    }

    public function getNotificationUrl() {
        return $this->notificationUrl;
    }

    public function getOrder() {
        return $this->order;
    }

    public function getReturnUrl() {
        return $this->returnUrl;
    }

    public function get_site_lang() {
        $lang = get_locale();
    
        if(!empty($lang)) {
          $str = explode('_', $lang);
          return (isset($str[0]) && !empty($str[0]))? trim($str[0]) : trim($lang);
        }
        return '';
      }
}
