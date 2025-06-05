<?php
/*
Plugin Name: FuturCreative - Bulgarian Eurozone Dual Currency
Description: Displays WooCommerce prices in both BGN and EUR using fixed rate 1 EUR = 1.95583 BGN.
Version: 1.0.0
Author: FuturCreative
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class FC_Bulgarian_Eurozone_Dual_Currency {
    const OPTION_KEY = 'fc_dc_settings';
    const RATE = 1.95583;

    private static $instance = null;
    private $settings = array();

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = $this->get_settings();

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 10, 3 );
        add_filter( 'woocommerce_widget_cart_item_price', array( $this, 'filter_cart_item_price' ), 10, 3 );
        add_filter( 'woocommerce_cart_subtotal', array( $this, 'filter_cart_subtotal' ), 10, 3 );
        add_filter( 'woocommerce_cart_total', array( $this, 'filter_cart_total' ), 10 );
        add_filter( 'woocommerce_email_order_item_subtotal', array( $this, 'filter_email_item_subtotal' ), 10, 3 );
        add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'filter_order_total' ), 10, 3 );
        add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'filter_shipping_label' ), 10, 2 );
        add_filter( 'woocommerce_cart_totals_tax_html', array( $this, 'filter_tax_label' ), 10, 2 );
        add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'filter_coupon_label' ), 10, 3 );
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_rest_product' ), 10, 3 );
    }

    private function get_settings() {
        $defaults = array(
            'enabled'   => 'yes',
            'eur_label' => '€',
            'position'  => 'right',
            'locations' => array(
                'single_product',
                'variable_product',
                'cart_item',
                'cart_subtotal',
                'cart_total',
                'order_confirmation_email',
                'my_account_orders',
                'rest_api',
                'shipping_labels',
                'tax_labels',
                'mini_cart',
                'thank_you',
                'coupons'
            )
        );

        $options = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $options, $defaults );
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Dual Currency', 'fc-dc' ),
            __( 'Dual Currency', 'fc-dc' ),
            'manage_woocommerce',
            'fc-dc',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'fc_dc_settings_group', self::OPTION_KEY );
    }

    public function render_settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Dual Currency Settings', 'fc-dc' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'fc_dc_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Dual Currency', 'fc-dc' ); ?></th>
                        <td><input name="<?php echo self::OPTION_KEY; ?>[enabled]" type="checkbox" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'EUR Label', 'fc-dc' ); ?></th>
                        <td><input name="<?php echo self::OPTION_KEY; ?>[eur_label]" type="text" value="<?php echo esc_attr( $settings['eur_label'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'EUR Position', 'fc-dc' ); ?></th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[position]">
                                <option value="left" <?php selected( $settings['position'], 'left' ); ?>><?php esc_html_e( 'Left of BGN Price', 'fc-dc' ); ?></option>
                                <option value="right" <?php selected( $settings['position'], 'right' ); ?>><?php esc_html_e( 'Right of BGN Price', 'fc-dc' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Display Locations', 'fc-dc' ); ?></th>
                        <td>
                            <?php
                            $locations = array(
                                'single_product'          => __( 'Single product pages', 'fc-dc' ),
                                'variable_product'        => __( 'Variable product pages', 'fc-dc' ),
                                'cart_item'               => __( 'Cart item prices', 'fc-dc' ),
                                'cart_subtotal'           => __( 'Cart subtotals', 'fc-dc' ),
                                'cart_total'              => __( 'Cart totals', 'fc-dc' ),
                                'order_confirmation_email'=> __( 'Order confirmation & emails', 'fc-dc' ),
                                'my_account_orders'       => __( 'My Account > Orders table', 'fc-dc' ),
                                'rest_api'                => __( 'REST API responses', 'fc-dc' ),
                                'shipping_labels'         => __( 'Shipping method labels', 'fc-dc' ),
                                'tax_labels'              => __( 'Tax amount labels', 'fc-dc' ),
                                'mini_cart'               => __( 'Mini cart', 'fc-dc' ),
                                'thank_you'               => __( 'Thank you page', 'fc-dc' ),
                                'coupons'                 => __( 'Coupons', 'fc-dc' ),
                            );
                            foreach ( $locations as $key => $label ) {
                                ?>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[locations][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['locations'], true ) ); ?> />
                                    <?php echo esc_html( $label ); ?>
                                </label><br/>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function format_dual_price( $bgn_price_html, $bgn_amount ) {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return $bgn_price_html;
        }

        $eur       = round( $bgn_amount / self::RATE, 2 );
        $eur_label = $this->settings['eur_label'] ? $this->settings['eur_label'] : '€';
        $eur_text  = $eur_label . wc_format_localized_price( $eur );

        if ( $this->settings['position'] === 'left' ) {
            return $eur_text . ' / ' . $bgn_price_html;
        }

        return $bgn_price_html . ' / ' . $eur_text;
    }

    public function filter_price_html( $price_html, $product ) {
        if ( ! in_array( 'single_product', $this->settings['locations'], true ) ) {
            return $price_html;
        }
        $amount = $product->get_price();
        if ( '' === $amount ) {
            return $price_html;
        }
        return $this->format_dual_price( $price_html, $amount );
    }

    public function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
        if ( ! in_array( 'cart_item', $this->settings['locations'], true ) ) {
            return $price_html;
        }
        $amount = $cart_item['data']->get_price();
        return $this->format_dual_price( $price_html, $amount );
    }

    public function filter_cart_subtotal( $cart_subtotal, $compound, $cart ) {
        if ( ! in_array( 'cart_subtotal', $this->settings['locations'], true ) ) {
            return $cart_subtotal;
        }
        $amount = $cart->get_subtotal();
        return $this->format_dual_price( $cart_subtotal, $amount );
    }

    public function filter_cart_total( $price_html ) {
        if ( ! in_array( 'cart_total', $this->settings['locations'], true ) ) {
            return $price_html;
        }
        $cart = WC()->cart;
        if ( ! $cart ) {
            return $price_html;
        }
        $amount = $cart->get_total( 'edit' );
        return $this->format_dual_price( $price_html, $amount );
    }

    public function filter_email_item_subtotal( $price_html, $item, $bool = false ) {
        if ( ! in_array( 'order_confirmation_email', $this->settings['locations'], true ) ) {
            return $price_html;
        }
        $amount = $item->get_total();
        return $this->format_dual_price( $price_html, $amount );
    }

    public function filter_order_total( $formatted_total, $order, $tax_display ) {
        if ( ! in_array( 'order_confirmation_email', $this->settings['locations'], true ) ) {
            return $formatted_total;
        }
        $amount = $order->get_total();
        return $this->format_dual_price( $formatted_total, $amount );
    }

    public function filter_shipping_label( $label, $method ) {
        if ( ! in_array( 'shipping_labels', $this->settings['locations'], true ) ) {
            return $label;
        }
        if ( isset( $method->cost ) ) {
            return $this->format_dual_price( $label, $method->cost );
        }
        return $label;
    }

    public function filter_tax_label( $tax_html, $tax ) {
        if ( ! in_array( 'tax_labels', $this->settings['locations'], true ) ) {
            return $tax_html;
        }
        $amount = isset( $tax->amount ) ? $tax->amount : 0;
        return $this->format_dual_price( $tax_html, $amount );
    }

    public function filter_coupon_label( $coupon_html, $coupon, $discount_amount_html ) {
        if ( ! in_array( 'coupons', $this->settings['locations'], true ) ) {
            return $coupon_html;
        }
        $amount = $coupon->get_amount();
        return $this->format_dual_price( $coupon_html, $amount );
    }

    public function filter_rest_product( $response, $object, $request ) {
        if ( ! in_array( 'rest_api', $this->settings['locations'], true ) ) {
            return $response;
        }
        $bgn_price = $object->get_price();
        $response->data['price_eur'] = round( $bgn_price / self::RATE, 2 );
        return $response;
    }
}

FC_Bulgarian_Eurozone_Dual_Currency::instance();
