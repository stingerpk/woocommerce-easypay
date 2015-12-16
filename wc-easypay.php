<?php

/*
Plugin Name: Woocommerce Easypay
Plugin URI: https://github.com/stingerpk/woocommerce-easypay
Description: Telenor Easypay Payment Gateway Plugin for Woocommerce
Version: 1.0
Author: Imroz Technologies
Author URI: http://imroz.pk
*/

if ( ! defined( 'ABSPATH' ) ) exit;
    
add_action('plugins_loaded', 'woocommerce_easypay');

function woocommerce_easypay() {

    class WC_Gateway_Easypay extends WC_Payment_Gateway {

        public function __construct() {
            
            $plugin_dir = plugin_dir_url(__FILE__);
            
            $this->id = 'easypay';
            $this->has_fields = true;
            $this->icon = apply_filters('woocommerce_easypay_icon', ''.$plugin_dir.'easy-pay-logo.png');
            $this->method_title = 'Pay via Easypay';
            $this->method_description = 'Easypay payment method by Telenor Easypaisa';
            $this->order_button_text  = __( 'Proceed to EasyPay', 'woocommerce' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('easypay_title');
            $this->description = $this->get_option('description');
            
            $this->storeid = $this->get_option('storeid');
            $this->orderidprefix = $this->get_option('orderidprefix');
            $this->daystoexpire = $this->get_option('daystoexpire');
            $this->sandboxmode = $this->get_option('sandboxmode');

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_easypay', array( $this, 'gateway_handler' ) );
        }

        function init_form_fields() {
            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Easypay Payment Module.', 'woocommerce'),
                    'default' => 'yes'
                ),
                'easypay_title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay via Easypay', 'woocommerce' ),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
                    'default'     => __( 'Pay with Easypay.', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'storeid' => array(
                    'title'       => __( 'Store ID', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This is the Store ID defined in your Easypay Merchant Account.', 'woocommerce' ),
                    'default'     => __( '', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'orderidprefix' => array(
                    'title'       => __( 'Order ID Prefix', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Specify if you want to append an identifier before the order number.', 'woocommerce' ),
                    'default'     => __( '', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'daystoexpire' => array(
                    'title'       => __( 'Token Expiry Days', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Specify for how many days the payment token remains valid.', 'woocommerce' ),
                    'default'     => __( '4', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'sandboxmode' => array(
                    'title' => __('Enable Sandbox?', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Sandbox Mode', 'woocommerce'),
                    'default' => 'no'
                )
            );
        }

        function admin_options() {
            ?>
            <h2><?php _e('Easypay','easypay'); ?></h2>
            <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            </table> <?php
        }
                
        function process_payment($order_id) {
            global $woocommerce;
			
            $order = new WC_Order($order_id);
            
            $totalamount = floatval( preg_replace( '#[^\d.]#', '', $woocommerce->cart->get_cart_total() ) );

            $website_url = home_url( '/' );
            
            return array(
                'result' => 'success',
                'redirect' => ''.$website_url.'wc-api/WC_Gateway_Easypay?orderId='.$order_id.'&amount='.$totalamount
            );
        }
                
        function gateway_handler() {
            global $woocommerce;
            @ob_clean();
            header( 'HTTP/1.1 200 OK' );
            
            if ( isset($_GET["orderId"]) && isset($_GET["amount"]) ) {
                
                $storeId = $this->storeid;
                $daysToExpire = $this->daystoexpire;
                $orderId = $this->orderidprefix;
                
                if ('yes' == $this->sandboxmode){
                    $easypayIndexPage = "http://202.69.8.50:9080/easypay/Index.jsf";
                }
                elseif ('no' == $this->sandboxmode){
                    $easypayIndexPage = "https://easypay.easypaisa.com.pk/easypay/Index.jsf";
                }

                $merchantConfirmPage = home_url( '/' ).'wc-api/WC_Gateway_Easypay';
                
                $orderId .= $_GET['orderId'];
                
                $currentDate = new DateTime();
                $currentDate->modify('+ 10 day');
                $expiryDate = $currentDate->format('Ymd His');
                
                
                ?>

                <form name="easypayform" method="post" action="<?php echo $easypayIndexPage ?>">
                    <input name="storeId" value="<?php echo $storeId ?>" hidden = "true"/>
                    <input name="amount" value="<?php echo $_GET['amount'] ?>" hidden = "true"/>
                    <input name="postBackURL" value="<?php echo $merchantConfirmPage ?>" hidden = "true"/>
                    <input name="orderRefNum" value="<?php echo $orderId ?>" hidden = "true"/>
                    <input name="expiryDate" value="<?php echo $expiryDate ?>" hidden = "true"/>
                </form>

                <script type="text/javascript">
                    document.easypayform.submit();
                </script>

                <?php
                    
                exit();
            }
            
            if ( isset($_GET["auth_token"]) ) {
                
                if ('yes' == $this->sandboxmode){
                    $easypayConfirmPage = "http://202.69.8.50:9080/easypay/Confirm.jsf";
                }
                elseif ('no' == $this->sandboxmode){
                    $easypayConfirmPage = "https://easypay.easypaisa.com.pk/easypay/Confirm.jsf";
                }
                
                $merchantStatusPage = home_url( '/' ).'wc-api/WC_Gateway_Easypay';
                        
                ?>

                <form name="easypayconfirmform" action="<?php echo $easypayConfirmPage ?>" method="POST">
                    <input name="auth_token" value="<?php echo $_GET['auth_token'] ?>" hidden = "true"/>
                    <input name="postBackURL" value="<?php echo $merchantStatusPage ?>" hidden = "true"/>
                </form>

                <script type="text/javascript">
                    document.easypayconfirmform.submit();
                </script>

                <?php
    
                exit();
            }
    
            if ( isset($_GET["status"]) && isset($_GET["orderRefNumber"]) ) {
                
                $status = $_GET['status'];
                $orderRefNumber = $_GET ['orderRefNumber'];
                $order = wc_get_order( $orderRefNumber );
                
                if ($status == '0000') {
                    
                    // Mark order complete
                    $order->payment_complete();
                    
                    // Empty cart and clear session
                    $woocommerce->cart->empty_cart();
                    
                    wp_redirect( $this->get_return_url( $order ) );
                    exit();
                }
            }

            if (isset($_GET["url"])) {
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_URL, $_GET["url"]);
                $output=curl_exec($curl);
                
                
                if($output != null) {
                    
                    $orderRefNumber = substr($_GET['url'], strrpos($_GET['url'], '/') + 1);
                    $order = wc_get_order( $orderRefNumber );

                    $IPNresponse = json_decode($output);
                    
                    update_post_meta($orderRefNumber, 'Payment Token', $IPNresponse->payment_token );
                    update_post_meta($orderRefNumber, 'Token Expiry Date Time', $IPNresponse->token_expiry_datetime );
                    update_post_meta($orderRefNumber, 'Store Name', $IPNresponse->store_name );
                    update_post_meta($orderRefNumber, 'Response Code', $IPNresponse->response_code );
                    update_post_meta($orderRefNumber, 'Order Date Time', $IPNresponse->order_datetime );
                    update_post_meta($orderRefNumber, 'Order ID', $IPNresponse->order_id );
                    update_post_meta($orderRefNumber, 'Paid Date Time', $IPNresponse->paid_datetime );
                    update_post_meta($orderRefNumber, 'Transaction Status', $IPNresponse->transaction_status );
                    update_post_meta($orderRefNumber, 'Store ID', $IPNresponse->store_id );
                    update_post_meta($orderRefNumber, 'Transaction Amount', $IPNresponse->transaction_amount );
                    update_post_meta($orderRefNumber, 'Payment Method', $IPNresponse->payment_method );
                    update_post_meta($orderRefNumber, 'MSISDN', $IPNresponse->msisdn );
                    update_post_meta($orderRefNumber, 'Account Number', $IPNresponse->account_number );
                    update_post_meta($orderRefNumber, 'Description', $IPNresponse->description );
                    update_post_meta($orderRefNumber, 'Transaction ID', $IPNresponse->transaction_id );
                    /*
                    if ($IPNresponse->response_code == '0000') {
                        
                        // Mark order complete
                        $order->payment_complete();
                        
                        wp_redirect( $this->get_return_url( $order ) );
                        exit();
                    }*/
                }
                curl_close($curl);
                exit();
            }
            
            wp_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }
    }

    function add_telenor_easypay($methods) {
        $methods[] = 'WC_Gateway_Easypay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_telenor_easypay');
}