<?php
/*
  Plugin Name: Borgun Payment gateway ( Heimir )
  Plugin URI: http://github.com/depill/woocommerce-heimir-borgun
  Description: Allow you to use Borgun, Heimir API for credit card payments.
  Version: 0.0.1
  Author: David Fannar Gunnarsson
  Author URI: http://www.icelandr.com
  TwoCheckout plugin used as a base template
 */
require_once('Heimir/HeimirAPI.php');
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_heimir', 0);

function woocommerce_heimir(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing

    class WC_Gateway_Heimir extends WC_Payment_Gateway{
        public function __construct(){

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'heimir';
            $this->supports[] = 'default_credit_card_form';
            $this->has_fields = true;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->seller_id = $this->get_option('seller_id');
            $this->username = $this->get_option('username');
            $this->password = $this->get_option('password');
            $this->ProcessorId = $this->get_option('ProcessorId');
            $this->MerchantId = $this->get_option('MerchantId');
            $this->TerminalId = $this->get_option('TerminalId');
            $this->RRN_Prefix = $this->get_option('RRN_Prefix');
            $this->MerchantContractNumber = $this->get_option('MerchantContractNumber');
            $this->test = $this->get_option('test');
            $this->currency = [
                "ISK" => 352,
                "USD" => 840,
                "EUR" => 978
             ];

            // Logs
            if ($this->debug == 'yes'){
                $this->log = $woocommerce->logger();
            }

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );


            if (!$this->is_valid_for_use()){
                $this->enabled = false;
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_heimir_supported_currencies', array( 'EUR', 'ISK', 'USD' ) ) ) ) return false;            
            return true;
        }

        /**
         * Admin Panel Options
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( 'Borgun/Heimir', 'woocommerce' ); ?></h3>
            <p><?php _e( 'Borgun - Credit Card', 'woocommerce' ); ?></p>


                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table><!--/.form-table-->

            <?php
        }


        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Borgun', 'woocommerce' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Credit Card', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay with Credit Card', 'woocommerce' )
                ),
                'username' => array(
                    'title' => __( 'Username', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter username for Borgun/Heimir API - default is for test env.', 'woocommerce' ),
                    'default' => 'authdev',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'password' => array(
                    'title' => __( 'Password', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter password for Borgun/Heimir API - default is for test env', 'woocommerce' ),
                    'default' => 'ad.900',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'ProcessorId' => array(
                    'title' => __( 'Processor ID', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter processor_id for Borgun/Heimir API - default is for test env.', 'woocommerce' ),
                    'default' => '21',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'MerchantId' => array(
                    'title' => __( 'Merchant Id', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter Merchant id for Borgun/Heimir API - default is for test env.', 'woocommerce' ),
                    'default' => '21',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'TerminalId' => array(
                    'title' => __( 'Terminal Id', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'Please enter Terminal Id from Borgun - default is for test env.', 'woocommerce' ),
                    'default' => '21',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'RRN_Prefix' => array(
                    'title' => __( 'RRN Prefix', 'woocommerce' ),
                    'type'          => 'text',
                    'description' => __( 'RRN is made of 12 numbers, this is the prefix you can choose ( order id fills up rest ) - default is for test env.', 'woocommerce' ),
                    'default' => 'TESTING',
                    'desc_tip'      => true,
                    'placeholder'   => ''
                ),
                'MerchantContractNumber' => array(
                    'title' => __( 'Merchant Contract Number', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter Merchant Contract Number for Borgun/Heimir API - default is for test env.', 'woocommerce' ),
                    'default' => '9256684',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'test' => array(
                    'title' => __( 'Test/Production', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Use Heimir in test mode', 'woocommerce' ),
                    'default' => 'yes'
                )
            );

        }


        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if ( 'yes' == $this->debug )
                $this->log->add( 'heimir', 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );


            if ($this->test == 'yes')
                $test = "test";
            else
                $test = "";

            $dt = new DateTime();

            $heimir = new HeimirAPI(plugin_dir_url(__FILE__), $this->username, $this->password, $this->ProcessorId, $this->MerchantId, $this->TerminalId, $this->RRN_Prefix, $test);
            $charge = $heimir->getAuthorization(1, $order->get_total(), $this->currency[get_woocommerce_currency()], date( 'ymdHis', strtotime( $order->order_date )), str_replace(" ", '', $_POST['heimir-card-number']), str_replace(' / ', '', $_POST['heimir-card-expiry']), $_POST['heimir-card-cvc'], $order_id);
            $actioncode = intval($charge->ActionCode);

            if($charge->ActionCode == "000") {
                $order->payment_complete();
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            }
            elseif($actioncode > 99 && $actioncode < 200) {
                $woocommerce->add_error('Payment error: Payment denied', 'woothemes');
                $order->add_order_note('Denied Borgun / ActionCode ' . $charge->ActionCode);

            }            
            elseif($actioncode > 200) {
                $woocommerce->add_error('Payment error: Invalid credit card', 'woothemes');
                $order->add_order_note('Take card / ActionCode ' . $charge->ActionCode);
            }     
        }

    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_heimir_gateway($methods){
        $methods[] = 'WC_Gateway_Heimir';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_heimir_gateway');

}