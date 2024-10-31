<?php

/**
 * Plugin Name: Oribi Analytics for WooCommerce
 * Description: Understand your visitors' behavior, streamline your sales flow, and maximize your store's potential.
 * Author: Oribi
 * Author URI: https://oribi.io
 * Version: 1.8.8
 * Text Domain: oribi
 * WC requires at least: 2.2
 * WC tested up to: 5.3.0
 */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

    function oribi_add_stylesheet()
    {
        if(get_current_screen()->base === 'settings_page_oribi') {
            wp_enqueue_style('oribi-styles', plugins_url('css/style.css', __FILE__));
            wp_enqueue_style('roboto', 'https://fonts.googleapis.com/css?family=Roboto:400,500');
        }
    }
    add_action( 'admin_print_styles', 'oribi_add_stylesheet' );


    require plugin_dir_path( __FILE__ ) . '/inc/oribi-admin-settings.php';

    $plugin_name = plugin_basename( __FILE__ );

    function oribi_plugin_settings_link( $links ) {
        $url = esc_url( add_query_arg(
            'page',
            'oribi',
            get_admin_url() . 'admin.php'
        ));

        $settings_link = "<a href='$url'>" . __( 'Settings', 'oribi' ) . '</a>';

        array_push( $links, $settings_link );
        return $links;
    }
    add_filter( "plugin_action_links_{$plugin_name}", 'oribi_plugin_settings_link' );

    function oribi_get_snippet() {
        $snippet = '';

        if ( ! empty( get_option( 'oribi_snippet' ) ) ) {
            $snippet = get_option( 'oribi_snippet' );
        }

        return $snippet;
    }

    function oribi_insert_snippet() {
        echo oribi_get_snippet();
    }
    add_action( 'wp_head', 'oribi_insert_snippet' );

    class Oribi_Event_Tracker {
        public static $default_tracking_capabilities = array(
            'email' => false,
            'completed' => true
        );

        public static function init() {
            $tracking_capabilities = self::get_tracking_capabilities();

            add_filter('woocommerce_payment_successful_result',  array( self::class, 'oribi_track_woocommerce_purchase' ), 1, 2 );
            add_filter('woocommerce_payment_complete',  array( self::class, 'oribi_track_woocommerce_payment_complete' ), 1, 2 );
            add_action('wp_footer', array( self::class, 'oribiDisplayVersionOfPlugin' )); // Add function for checking the version of plugin
            add_action('wp_footer', array( self::class, 'trackIntegratePurchase' ));

            if ( isset( $tracking_capabilities['email'] ) && (bool)$tracking_capabilities['email'] ) {
                add_action( 'wp_footer', array( self::class, 'oribi_track_users_email' ), 999999999 );
            }
        }

        public static function get_tracking_capabilities() {
            $tracking_capabilities = get_option( 'oribi_tracking_capabilities' );
            $default_unchecked = array(
                'email' => false,
                'completed' => false
            );
            $tracking_capabilities =
                is_array( $tracking_capabilities )
                    ? array_replace( $default_unchecked, $tracking_capabilities )
                    : $default_unchecked;
            return array_replace( self::$default_tracking_capabilities, $tracking_capabilities );
        }

        public static function oribi_track_users_email() {
            $user_email = null;
            if ( is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                $user_email = $current_user->user_email;
            }
            if ( isset( $user_email ) ) {
                ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        ORIBI.api('setUserEmail', '<?php echo $user_email ?>');
                    });
                </script>
                <?php
            }
        }

        /**
         * This static function is only for checking of plugin version.
         * Displaying The Plugin Version on every page of Wordpress.
         */
        public static function oribiDisplayVersionOfPlugin() {
            try {
                $data = get_file_data( __FILE__, ['ver'=>'Version'] );
                $settings = self::oribiDisplayUserSetting();
            } catch (Exception $e) {

            }

            if(isset($data) && isset($data['ver'])) {
                ?><script>console.debug('ORIBI Plugin Version: ' + '<?= $data['ver'] . $settings ?>');</script><?php
            }
        }

        /**
         * This static function is only for displaying user's settings of plugin in a special format
         */
        public static function oribiDisplayUserSetting() {
            $tracking_capabilities = self::get_tracking_capabilities();

            return '.' . ($tracking_capabilities['completed'] ? '1' : '0') . '.' . ($tracking_capabilities['email'] ? '1' : '0');
        }

        public static function oribi_track_woocommerce_payment_complete($order_id){
            Oribi_Event_Tracker::oribi_track_woocommerce_purchase('success', $order_id);
        }

        public static function oribi_track_woocommerce_purchase($result, $order_id) {
            $order    = wc_get_order( $order_id );

            if ( !isset($order) ) return $result;

            // Check if user set the track only completed orders
            $tracking_capabilities = self::get_tracking_capabilities();

            if(isset( $tracking_capabilities['completed'] ) && (bool)$tracking_capabilities['completed']) {
                $allow_order_status = array('processing', 'completed'); // We use 'processing' status here, because orders with 'cash on delivery' has this status on checkout closing
            } else {
                $allow_order_status = array('processing', 'completed', 'pending', 'on-hold');
            }

            if (!in_array($order->get_status(), $allow_order_status)){
                return $result;
            }

            $items    = $order->get_items();
            $products = array();

            $user_email = $order->get_billing_email();
            if ( !isset($user_email) && is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                $user_email = $current_user->user_email;
            }

            foreach( $items as $item ) {
                $terms               = get_the_terms ( $item->get_product_id(), 'product_cat' );
                $product             = $item->get_product();
                $quantity            = (int)$item->get_quantity();
                $price               = $quantity > 0 ? (float)round($item->get_subtotal() / $quantity, 2) : 0;
                $productRegularPrice = (float)$product->get_regular_price();
                $productActivePrice  = (float)$product->get_price();

                if ($productRegularPrice >= $productActivePrice) {
                    $discountPrice = $productRegularPrice - $productActivePrice;
                } else {
                    $discountPrice = $productRegularPrice - $price;
                }
                $discountPrice = $discountPrice > 0 ? $discountPrice : 0;

                $product = array(
                    'id'            => $item->get_product_id(),
                    'name'          => $item->get_name(),
                    'price'         => $price,
                    'taxPrice'      => $quantity > 0 ? (float)round($item->get_subtotal_tax() / $quantity, 2) : 0,
                    'discountPrice' => $discountPrice,
                    'quantity'      => $quantity,
                    'categories'    => array(),
                );

                foreach( $terms as $term ){
                    $product['categories'][] = $term->name;
                }

                $products[] = $product;
            }

            $data = array(
                'orderId'       => $order_id,
                'currency'      => $order->get_currency(),
                'totalPrice'    => (float)$order->get_total(),
                'taxPrice'      => (float)$order->get_total_tax(),
                'shippingPrice' => (float)$order->calculate_shipping(),
                'discountPrice' => (float)$order->get_total_discount(),
                'storeCurrency' => get_woocommerce_currency(),
                'products'      => $products,
                'source'        => 'WooCommerce-plugin',
            );

            $customer_id = $order->get_customer_id();
            if ( $customer_id > 0 ) {
                $customer = new WC_Customer( $customer_id );
                $data['isFirstPurchase'] = $customer->get_order_count() === 1;
            }
            $data = json_encode($data);

            try {
                if(function_exists( 'WC' )) {
                    if(!isset(WC()->session)) {
                        WC()->initialize_session();
                    }

                    WC()->session->set( 'trackIntegratePurchase_data' , $data );

                    $tracking_capabilities = self::get_tracking_capabilities();
                    if( isset( $user_email ) && $tracking_capabilities['email'] ) {
                        WC()->session->set( 'trackIntegratePurchase_email' , $user_email );
                    }
                }
            } catch (Error $e) {
                unset($e);
            }

            return $result;
        }

        public static function trackIntegratePurchase() {
            if ( function_exists( 'WC' ) && !empty(WC()->session) ) {
                $data = WC()->session->get( 'trackIntegratePurchase_data' );
                $user_email =  WC()->session->get( 'trackIntegratePurchase_email');

                if(!empty($data)){
                    $tracking_capabilities = self::get_tracking_capabilities();
                    WC()->session->set( 'trackIntegratePurchase_email' , '' );
                    WC()->session->set( 'trackIntegratePurchase_data' , '' );
                    ?>
                    <script>
                        ORIBI.api('trackIntegratePurchase', <?php echo $data ?>);
                        <?php if( isset($user_email) && $tracking_capabilities['email'] ) : ?>
                        ORIBI.api('setUserEmail', '<?php echo $user_email ?>');
                        <?php endif; ?>
                    </script>
                    <?php
                }
            }
        }
    }

  Oribi_Event_Tracker::init();
