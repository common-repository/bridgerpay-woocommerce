<?php
namespace Bridgerpay\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Subscriptions compatibility.
 */
trait WC_Bridgerpay_Subscriptions {

  // use WC_Bridgerpay_Logger;
  /**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @since 5.6.0
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && version_compare( \WC_Subscriptions::$version, '2.2.0', '>=' );
	}


  public function maybe_init_subscriptions(){

    if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$this->supports = array_merge(
			$this->supports,
			[
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			]
		);

    if(isset($_GET['test_subscription'])){
      $order_id = 61;
      $order = wc_get_order($order_id);
    //   $this->process_subscription_payment(10, $order);

		$subscriptions = wcs_get_subscriptions_for_order( $order );
		// db($subscriptions);
		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {

				$new_dates = array(

					'next_payment' => date('Y-m-d H:i:s', strtotime('+2 minutes', wp_date('U')))
				);
	
				$subscription->update_dates($new_dates, 'site');
				// db($subscription);
				// $subscription->payment_complete();
			}
		}
    }

    //subscription hooks
    add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );

  }

  /**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription_payment( $amount_to_charge, $renewal_order, false, false );
	}


  /**
	 * Process_subscription_payment function.
	 *
	 * @since 3.0
	 * @since 4.0.4 Add third parameter flag to retry.
	 * @since 4.1.0 Add fourth parameter to log previous errors.
	 * @since 5.6.0 Process renewal payments for SEPA and UPE.
	 *
	 * @param float  $amount
	 * @param mixed  $renewal_order
	 * @param bool   $retry Should we retry the process?
	 * @param object $previous_error
	 */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {
		
		// file_put_contents(dirname(__FILE__) . '/subscription_log_'.time().'.log', serialize($renewal_order));
		// file_put_contents(dirname(__FILE__) . '/subscription_log_amount_'.time().'.log', $amount);
		$order_id = $renewal_order->get_id();
		$parent_order_id = '';
		/* translators: error message */
		// $order->update_status( 'failed' );
		// $order->update_meta_data( '_stripe_charge_captured', $captured );
		// $order->set_transaction_id( $response->id );
		// $order->payment_complete( $response->id );
		/* translators: transaction id */
		// $message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id );
		// $order->add_order_note( $message );
		//$order_items = $order->get_items();
		// Only one subscription allowed in the cart when PayPal Standard is active
		// $product = $order->get_product_from_item( $order_items[0] );
		$subscriptions = wcs_get_subscriptions_for_order( $order_id , ['order_type' => 'any']);
		// db($renewal_order);
		// db($subscriptions);exit();
		if ( ! empty( $subscriptions ) ) {
			// file_put_contents(dirname(__FILE__) . '/subscription_log_subscriptions_'.$order_id.'.log', serialize($subscriptions));
			foreach ( $subscriptions as $subscription ) {
				$parent_order_id = $subscription->get_parent_id();
				// $subscription->payment_complete();
			}
		}
		//$subscription = wcs_get_subscription( $subscription_id );
		$parent_order = wc_get_order($parent_order_id);
		if(!$parent_order){
			$renewal_order->update_status( 'failed' );
			$error_message = 'Bridgerpay Subscription payment failed with error. '.' no parent order found.';
			$renewal_order->add_order_note($error_message);
			return $error_message;
		}

		$renewal_order->add_order_note("Trying bridgerpay subscription payment for order ".$order_id." and parent order id = ".$parent_order_id);
		// do_action( 'wc_gateway_stripe_process_response', $response, $order );

		$response = $this->make_subscription_payment( $parent_order, $amount);
		// file_put_contents(dirname(__FILE__) . '/subscription_log_response_'.$order_id.'.log', serialize($response));
		// $renewal_order->add_order_note($response);
		if($response && isset($response->status) && $response->status == 'approved'){
			$renewal_order->update_meta_data( '_bridgerpay_subscription_charge_captured_'.$response->id,  serialize($response));
			$renewal_order->payment_complete();
			$renewal_order->set_transaction_id( $response->id );
			if ( is_callable( [ $renewal_order, 'save' ] ) ) {
				$renewal_order->save();
			}
			$renewal_order->add_order_note('Bridgerpay Subscription payment was successfully paid . '.$renewal_order->get_currency()." ".$amount);

			// \WC_Subscriptions_Manager::process_subscription_payments_on_order( $renewal_order );

			return true;
		}
		else if($response && isset($response->status) && $response->status != 'approved'){
			// \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order);
			$renewal_order->update_status( 'failed' );
			$error_message = 'Bridgerpay Subscription payment failed with error. '.$response->status;
			$renewal_order->add_order_note($error_message);
			return $error_message;
		}else if($response && is_string($response)){
			// \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order);
			$renewal_order->update_status( 'failed' );
			$error_message = 'Bridgerpay Subscription payment failed with error. '.$response;
			$renewal_order->add_order_note($error_message);
			return $error_message;
		}
		else{
			// \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order);
			$renewal_order->update_status( 'failed' );
			$error_message = 'Bridgerpay Subscription payment failed with error. '.'Unknown Error';
			$renewal_order->add_order_note($error_message);
			return $error_message;
		}

		return;
		// Unlike regular off-session subscription payments, early renewals are treated as on-session payments, involving the customer.
		// This makes the SCA authorization popup show up for the "Renew early" modal (Subscriptions settings > Accept Early Renewal Payments via a Modal).

		// if ( isset( $_REQUEST['process_early_renewal'] ) && 'bridgerpay_gateway' === $this->id  ) {
		// 	// Hijack all other redirects in order to do the redirection in JavaScript.
		// 	add_action( 'wp_redirect', [ $this, 'redirect_after_early_renewal' ], 100 );

		// 	return;
		// }
			
			
			
		
	}


  public function make_subscription_payment($order, $amount){
	$output = __('Unknown Error', 'bridgerpay');
    if($order){
        $order_data = $order->get_meta('bridger_pay_order_completed_data');
		
        if(!empty($order_data)){
            $order_data = @json_decode($order_data, true);
			// db($order_data['data']['charge']['attributes']);exit(); 
			// $renewal_order->add_order_note($response);
			// $order_data['data']['charge']['attributes']['credit_card_token'] = 'test';
			// db($order_data);exit();
            if(is_array($order_data) && count($order_data) >= 1 && isset($order_data['data']) && isset($order_data['data']['charge']) && isset($order_data['data']['charge']['attributes']) && isset($order_data['data']['charge']['attributes']['credit_card_token']) && $order_data['data']['charge']['attributes']['credit_card_token']){
                
                $credit_card_token = $order_data['data']['charge']['attributes']['credit_card_token'];
                
                $options = array(
                    'api_key' => $this->get_option('api_key'),
                    'cashier_key' => $this->get_option('cashier_key'),
                    'api_url' => $this->get_option('api_url'),
                    'embed_url' => $this->get_option('embed_url'),
                    'version' => $this->get_option('version'),
                );
        
                $user_name = $this->getUserName();
                $password = $this->getPassword();
                // db($options);db($password);db($user_name);exit();
                $payment = new \Bridgerpay\Payment($user_name, $password, $options);
				
				$payment_response = $payment->make_subscription_payment($order, $order_data, $amount);
				return $payment_response;
				
            }else{
				return 'This order does not support subscription payments.';
			}
            
        }else{
			return 'This order does not have bridgerpay payment details.';
		}
        
    }
	return $output;
	// throw new \Exception(__('Unknown Error', 'bridgerpay'));
  }
}