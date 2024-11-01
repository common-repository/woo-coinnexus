<?php
/**
 * Plugin Name: Bitcoin CoinNexus accept fiat and get bitcoin payment plugin
 * Plugin URI: https://www.lamium.fi/coinnexus
 * Description: Accept EUR, USD or CHF payments from customers and receive Bitcoins.
 * Author: CoinNexus Oy
 * Author URI: https://www.lamium.io/
 * Version: 1.1.0
 * Text Domain: woocommerce-gateway-fiat-to-bitcoin-coinnexus-api
 * Domain Path: /languages/
 *
 * Copyright: (c) 2018 CoinNexus Oy (support@lamium.io) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   woocommerce-gateway-fiat-to-bitcoin-coinnexus-api
 * @author    CoinNexus Oy
 * @category  Admin
 * @copyright Copyright (c) 2018, CoinNexus Oy and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This offline gateway forks the WooCommerce core "Cheque" payment gateway to create a fiat to bitcoin conversion payment plugin.
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

register_activation_hook(__FILE__,'lamiumActivation');
add_action( 'wp', 'lamiumActivation' );

function lamiumActivation() {

    if (! wp_next_scheduled ( 'lamium_hourly_event' )) {
		wp_schedule_event(time(), 'hourly', 'lamium_hourly_event');
    }
}
//updates order status of pending orders by connecting to the coinnexus api
add_action('lamium_hourly_event','lamium_do_this_hourly');
register_deactivation_hook(__FILE__, 'LamiumDeactivation');
function lamium_do_this_hourly()
{
	$lamiumPaymentObj = new WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api;
	$lamiumPaymentObj->do_this_hourly();
}
function LamiumDeactivation() {
	wp_clear_scheduled_hook('lamium_hourly_event');
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + fiat to bitcoin coinnexus api gateway
 */
function wc_fiat_to_bitcoin_coinnexus_api_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_fiat_to_bitcoin_coinnexus_api_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */ 
function wc_fiat_to_bitcoin_coinnexus_api_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fiat_to_bitcoin_coinnexus_api_gateway' ) . '">' . __( 'Configure', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_fiat_to_bitcoin_coinnexus_api_gateway_plugin_links' );


/**
 * Fiat To Bitcoin Coinnexus Api 
 *
 * CoinNexus Bitcoin payment gateway that allows you to accept fiat and converts directly to bitcoins.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		CoinNexus Oy
 */
add_action( 'plugins_loaded', 'wc_fiat_to_bitcoin_coinnexus_api_gateway_init', 11 );

function wc_fiat_to_bitcoin_coinnexus_api_gateway_init() {

	class WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			global $wp_session;
	  
			$this->id                 = 'fiat_to_bitcoin_coinnexus_api_gateway';
			$this->icon               = apply_filters('woocommerce_fiat_to_bitcoin_coinnexus_api_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Fiat to bitcoin coinnexus api', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' );
			$this->method_description = __( 'Allows fiat to bitcoin payments. Very handy if you use your cheque gateway for another payment method, and can help with testing. Orders are marked as "payment-pending" when received.', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->username  = $this->get_option( 'username' );
			$this->password  = $this->get_option( 'password' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
		 
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
		  // Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_fiat_to_bitcoin_coinnexus_api_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Fiat to bitcoin coinnexus api Payment', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default'     => __( 'Bank Payment', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'desc_tip'    => true,
				),
				'username' => array(
					'title'       => __( 'Lamium username', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'        => 'text',
					'description' => __( 'Lamium api username', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default'     => __( '', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'desc_tip'    => true,
				),
				'password' => array(
					'title'       => __( 'Lamium api password', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'        => 'text',
					'description' => __( 'Lamium api password', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default'     => __( '', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				print_r(WC()->session->get('lamiumData'));
			}
		}
		
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			$orderData = $order->get_data();
			if ( $this->instructions && ! $sent_to_admin && $this->id === $orderData['payment_method'] && $order->has_status( 'payment-pending' ) ) {
				echo wpautop( wptexturize( $this->instructions )) . PHP_EOL;
			}
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) 
		{
			try {
				$order = wc_get_order( $order_id );
		 		$orderData = $order->get_data();
				$data = array(
					'username' =>$this->username,
	                'password'  => $this->password,
	               );
				$data = json_encode($data);
				$url = 'http://api.lamium.fi/api/users/token';
	            $tokenRemoteCall = null;
	            $i = 0;
	            do {
	            	$tokenRemoteCall = $this->_getCoinnexusToken();
	            	$tokenRemoteCall =json_decode($tokenRemoteCall['body']);
	            	$i = $i +1;
	            }while (($i<3) && (@$tokenRemoteCall->success!=true));
	            if(empty($tokenRemoteCall->success)){$this->_fail($tokenRemoteCall,$orderData);}
	            $lamiumApiData['amount']= $orderData['total'];
	            $lamiumApiData['currency']= $orderData['currency'];
	            $lamiumApiData['purchase_bitcoin_agreement']= 1;
	            $lamiumApiData['customer_name']= $orderData['billing']['first_name'].'--'.$orderData['billing']['last_name'];
	            $lamiumApiData['customer_phone']= $orderData['billing']['phone'];
	            $lamiumApiData['customer_address']= $orderData['billing']['address_1'].'--'.$orderData['billing']['address_2'].'--'.$orderData['billing']['city'].'--'.
					$orderData['billing']['state'].'--'.$orderData['billing']['postcode'].'--'.$orderData['billing']['country'];	
	            $lamiumApiData['item']='url - '.get_home_url().'- Woocommerce Order id -'.$orderData['id'];
	            $lamiumApiData['vat_rate']=$orderData['total_tax'];
	            $lamiumApiData = json_encode($lamiumApiData);
	            $url = 'http://api.lamium.fi/api/payments/add';
	            $apiDataRemoteCall = null;
	            $i = 0;
	            do {
	            	$apiDataRemoteCall = $this->_wpRemoteCall($url,$lamiumApiData,$tokenRemoteCall->data->token);
	            	$apiDataRemoteCall =json_decode($apiDataRemoteCall['body']);
	            	$i = $i +1;
	            }while (($i<3) && (@$apiDataRemoteCall->success!=true));
	            if(empty($apiDataRemoteCall->success)){$this->_fail($apiDataRemoteCall,$orderData);}
				// Mark as payment-pending (we're awaiting the payment)
			    $order->update_status( 'payment-pending', __( 'Awaiting fiat payment', 'wc-gateway-fiat-to-bitcoin-coinnexus-api' ) );
				update_post_meta( $order_id , '_lamium_merchant_id',$apiDataRemoteCall->data[0]->merchant_id);
	 			update_post_meta( $order_id , '_lamium_customer_reference',$apiDataRemoteCall->data[0]->customer_reference);
				update_post_meta( $order_id , '_lamium_transaction_id',$apiDataRemoteCall->data[0]->transaction_id);
				 // Reduce stock levels
				$order->reduce_order_stock();
			 	// Remove cart
			    WC()->cart->empty_cart();
			    //send new order and payment details email to customer
			   // load the mailer class
				 $mailer = WC()->mailer();
				//format the email
				$recipient = $orderData['billing']['email'];
				$subject = get_bloginfo()." payment details for order #".$order_id;
				$content = '<div>Dear '.$orderData['billing']['first_name'].' '.$orderData['billing']['last_name'].',<br/>
					Thank you for your order at '.get_bloginfo().'.</div>
					<div>In order to complete the order please send the following bank transaction using the exact reference code so that we can track your payment:</div>
					<table class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<tr class="woocommerce-order-overview__order order"><td>Please send <strong>'.$orderData['total'].' '.$orderData['currency'].
					'</strong> to the following account:<td></tr>
					<tr><td>IBAN : <strong>'.$apiDataRemoteCall->data[0]->iban.'</strong><td><tr>
					<tr><td>BIC :  <strong>'.$apiDataRemoteCall->data[0]->bic.'</strong><td></tr>
					<tr><td>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></td></tr></table>';
				$content .= $this->_get_custom_email_html( $order, $subject, $mailer );
				$headers = "Content-Type: text/html\r\n";
				//send the email through wordpress
				$mailer->send( $recipient, $subject, $content, $headers );
			    $paymentDetailsBlock ='<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<li class="woocommerce-order-overview__order order"><p>Please send <strong>'.$orderData['total'].' '.$orderData['currency'].
					'</strong> to the following account:</p>
					<p>IBAN : <strong>'.$apiDataRemoteCall->data[0]->iban.'</strong></p>
					<p>BIC :  <strong>'.$apiDataRemoteCall->data[0]->bic.'</strong></p>
					<p>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></p>';
			     // Return thankyou redirect
			    WC()->session->set( 'lamiumData', '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<li class="woocommerce-order-overview__order order"><p>Please send <strong>'.$orderData['total'].' '.$orderData['currency'].
					'</strong> to the following account:</p>
					<p>IBAN : <strong>'.$apiDataRemoteCall->data[0]->iban.'</strong></p>
					<p>BIC :  <strong>'.$apiDataRemoteCall->data[0]->bic.'</strong></p>
					<p>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></p>');
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url($order)
				);
			}
			catch(Exception $e) {
    			$this->_tryCatchError($e->getMessage());
			}
	}
	public function do_this_hourly() {

	try {
		$customer_orders = get_posts( array(
			        'numberposts' => 10,
			        'order' => 'ASC',
			        'meta_key'    => '_customer_user',
			        'post_type'   => array( 'shop_order' ),
			        'post_status' => array( 'wc-pending' )
	    		));
		 
		if(empty($customer_orders)){return true;}
		$transaction_ids = array();	
		$orderIdTransactionIdMap = array();			
		foreach ( $customer_orders as $customer_order ) 
		{
		    $metaData = get_post_meta($customer_order->ID);
		    if(!isset($metaData["_lamium_customer_reference"])){continue;}
		    $transaction_id = $metaData["_lamium_transaction_id"][0];
		    $transaction_ids[] = $transaction_id;
		    $merchantId = $metaData["_lamium_merchant_id"][0];
		    $orderIdTransactionIdMap[$transaction_id] = $customer_order->ID;     
		}
		if(empty($transaction_ids)){return true;}
	    $tokenRemoteCall = $this->_getCoinnexusToken();
	    $tokenRemoteCall =json_decode($tokenRemoteCall['body']);
	    if(empty($tokenRemoteCall->success))
	    {
	        $this->_fail($tokenRemoteCall,$orderData,true);
	        return true;
	    }	
	    $lamiumApiData['merchant_id'] = $merchantId;
	    $lamiumApiData['transaction_ids'] = $transaction_ids;
	    $lamiumApiData = json_encode($lamiumApiData);
	    $url = 'http://api.lamium.fi/api/payments/allorderpaymentstatus';
	    $apiDataRemoteCall = $this->_wpRemoteCall($url,$lamiumApiData,$tokenRemoteCall->data->token);
        $apiDataRemoteCall =json_decode($apiDataRemoteCall['body']);
        if(empty($apiDataRemoteCall->success)){return true;}
        foreach($apiDataRemoteCall->data[0]->records as $apiData)
        {	  
            if($apiData->status =='fiat paid'|| $apiData->status=='btc sent')
            { 
            	$orderId = $orderIdTransactionIdMap[$apiData->transaction_id];
            	$order = wc_get_order($orderId);
            	$orderUpdate = $order->update_status('processing', __( 'Fiat paid by customer', 'wc-gateway-fiat-to-bitcoin-coinnexus-api'));
            }
        }
    }
	    catch(Exception $e) {
	    	$this->_tryCatchError($e->getMessage());
		}
	}

	protected function _get_custom_email_html( $order, $heading = false, $mailer ) {
		$template = 'emails/customer-invoice.php';
		return wc_get_template_html( $template, array(
			'order'         => $order,
			'email_heading' => $heading,
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $mailer
		) );
	}

	protected function _wpRemoteCall($url,$bodyData,$token=null)
	{	
		return wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'headers' => array("Content-type" =>'application/json','Accept'=>'application/json','Authorization'=>'Bearer '.$token),
			'body' => $bodyData
		    )
		);
	}

	protected function _getCoinnexusToken()
	{
		$data = array(
				'username' =>$this->username,
                'password'  => $this->password,
               );
			$data = json_encode($data);
			$url = 'http://api.lamium.fi/api/users/token';
            return $this->_wpRemoteCall($url,$data);
	}

    protected function _fail($cornCallObj,$orderData,$automatedCall=false)
    {
    	$to = 'support@lamium.io,debanjan@lamium.io';
		$subject = 'WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api payment failed';
		$message = get_home_url().'---'.$cornCallObj->message.'--------'.$cornCallObj->url.'-------'.$orderData['currency'].'--'.$orderData['total'].
		'--'.$orderData['billing']['first_name'].$orderData['billing']['last_name'].'--'.$orderData['billing']['phone'].'--'.
		$orderData['billing']['address_1'].'--'.$orderData['billing']['address_2'].'--'.$orderData['billing']['city'].'--'.
		$orderData['billing']['state'].'--'.$orderData['billing']['postcode'].'--'.$orderData['billing']['country'].'--'.$orderData['total_tax'];
		wp_mail( $to, $subject, $message);
		if(!$automatedCall)
		{
			throw new Exception( __( 'order processing failed, please try again later', 'woo' ) );
		}	
    }

    protected function _tryCatchError($error)
    {
    	$to = 'support@lamium.io,debanjan@lamium.io';
		$subject = 'WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api plugin run failed';
		$message = get_home_url().'-----'.$error;
    }
	
  } // end \WC_Gateway_Fiat_To_Bitcoin_Coinnexus_Api class
}