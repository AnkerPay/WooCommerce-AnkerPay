<?php
/**
 * Plugin Name: AnkerPay
 * Plugin URI: https://ankerpay.com/
 * Description: AnkerPay is a bitcoin payment gateway for WooCommerce
 * Author: AnkerPay
 * Author URI: https://ankerpay.com/
 * Version: 1.0.1
 * License: GPLv2 or later
 * Text Domain: ankerpay
 * Domain Path: /languages/
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'anker_add_gateway_class' );
function anker_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Anker_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'anker_init_gateway_class' );
function anker_init_gateway_class() {

	class WC_Anker_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

            $this->id                  = 'anker_pay';
            $this->icon                = plugins_url( 'images/bitcoin.png', __FILE__ );
            $this->has_fields          = false;
            $this->invoice_url         = 'https://ankerpay.com/api/v1/pos/invoice/new';
            $this->qr_url              = 'https://ankerpay.com/moya/api/qr';
            $this->method_title        = 'AnkerPay';
            $this->method_label        = 'AnkerPay';
            $this->method_description  = 'Expand your payment options by accepting cryptocurrency payments';
            
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            
            $this->api_key            = $this->get_option('api_key');

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 
 		}
 		
    /**
     * Checkout receipt page
     *
     * @return void
     */
    public function receipt_page( $order ) {

      $order = wc_get_order( $order );
      $btcaddress = $order->get_meta( 'BTC Address' );
      $amount = $order->get_meta( 'BTC Amount' );

      echo '<p>'.__( 'Thank you for your order, please scan the QR code below to pay with AnkerPay.', 'ankerpay' ).'</p>';
      echo '<div><img src="https://ankerpay.com/moya/api/qr?img=bitcoin:'. $btcaddress . '&amount=' . $amount . '" style="display: block; border: none; margin: auto; width: 320px;"></div>';
      echo '<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">';
      echo __( 'Cancel order &amp; restore cart', 'ankerpay' ) . '</a>';

    }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'ankerpay' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable AnkerPay', 'ankerpay' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'ankerpay' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'ankerpay' ),
                    'default' => __( 'AnkerPay', 'ankerpay' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'ankerpay' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'ankerpay' ),
                    'default' => __( 'Pay with Bitcoin', 'ankerpay' )
                ),
                'api_key' => array(
                    'title' => __( 'API Key ID', 'ankerpay' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your AnkerPay API Key ID.', 'ankerpay' ) . ' ' . sprintf( __( 'You can to get this information in: %sAnkerPay Account%s.', 'ankerpay' ), '<a href="https://ankerpay.com/" target="_blank">', '</a>' ),
                    'default' => ''
                )
            );
		
	
	 	}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {

		
				 
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {

		
	
	 	}


		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        $args = $this->get_form_args( $order );

        $details = $this->create_invoice( $args );

        if ( $details && $details->success ) {

            // Displays AnkerPay iframe.
            $html = '<img src="https://ankerpay.com/moya/api/qr?img=bitcoin:'. $details->success->deposit . '&amount=' . $details->success->amount . '" style="display: block; border: none; margin: 0 auto 25px; width: 500px;">';

            $html .= '<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'ankerpaywoo' ) . '</a>';


            // Register order details.
            update_post_meta( $order->get_id(), 'AnkerPay ID', esc_attr( $details->success->invoiceId ) );
            update_post_meta( $order->get_id(), 'BTC Address', esc_attr( $details->success->deposit ) );
            update_post_meta( $order->get_id(), 'BTC Amount', esc_attr( $details->success->amount ) );
            
                return array(
                    'result' => 'success',
                    'redirect' => apply_filters('process_payment_redirect', $order->get_checkout_payment_url(true), $order),
                );

            //return $html;

        } else {


            return $this->btc_order_error( $order );
        }
					
	 	}
	 	
    /**
      * Order error button.
      *
      * @param  object $order Order data.
      *
      * @return string        Error message and cancel button.
      */
    protected function btc_order_error( $order ) {

        // Display message if there is problem.
        $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'ankerpaywoo' ) . '</p>';

        $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'ankerpaywoo' ) . '</a>';

        return $html;
    }

    /**
      * Create order invoice.
      *
      * @param  array $args Order argumments.
      *
      * @return mixed       Object with order details or false.
      */
    public function create_invoice( $args ) {

        // Built wp_remote_post params.
        $params = array(
            'body'       => $args ,
            'method'     => 'POST',
            'sslverify'  => false,
            'timeout'    => 30,
            'headers'    => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode( $this->api_key )
            )
        );

        $response = wp_remote_post( $this->invoice_url, $params );

        // Check to see if the request was valid.
        if ( !is_wp_error( $response ) && $response['response']['code'] == 200 ) {
            return json_decode( $response['body'] );
        }

        return false;
    }
    /**
      * Generate the args to form.
      *
      * @param  array $order Order data.
      * @return array
      */
    public function get_form_args( $order ) {

        $args = array(
            'amount'            => $order->get_total(),
            'currency'          => get_woocommerce_currency(),
            'storeId'           => get_home_url(),
            'orderID'           => $order->get_id(),
            'postype'           => esc_url( $this->get_return_url( $order ) ),
            'terminalId'        => $this->api_key
        );

        if ( is_ssl() ) {
            $args['notificationURL'] = str_replace( 'http:', 'https:', get_permalink( woocommerce_get_page_id( 'pay' ) ) );
        }
        return $args;
    }

 	}
}
