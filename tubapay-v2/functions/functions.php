<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once 'tubapay-restapi-class.php';

if(!function_exists('tubapay2_plugin_activate')) {
    function tubapay2_plugin_activate() {
        add_option('tubapay2_statuses_upgrade', 'on');

        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        deactivate_plugins( 'tubapay-v2/tubapay2.php' );
    }
}

if(!function_exists('tubapay2_requirements_plugin_notice')) {
    function tubapay2_requirements_plugin_notice() {
        ?><div class="error"><p>Przepraszamy, ale do prawidłowego działania pluginu TubaPay wymagany jest moduł PHP-CURL oraz plugin WooCommerce.</p></div><?php
    }
}

if(!function_exists('tubapay2_requirements_check')) {
    function tubapay2_requirements_check() {
        $requirements_met = false;

        if (function_exists('curl_version') && class_exists("WC_Payment_Gateway")) {
            $requirements_met = true;
        }

        if (is_admin() && current_user_can('activate_plugins') && !$requirements_met) {
            add_action('admin_notices', 'tubapay2_requirements_plugin_notice');

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
}

if(!function_exists('tubapay2_load_plugin')) {
    function tubapay2_load_plugin() {
        if (is_admin() && get_option('tubapay2_statuses_upgrade') == 'on') {
            tubapay2_order_statuses_upgrade();
            delete_option('tubapay2_statuses_upgrade');
        }
    }
}

if(!function_exists('tubapay2_label_update')) {
    function tubapay2_label_update() {
        $tuba_gw = new WC_Gateway_TubaPay2();
        $tuba_gw->update_labels();
    }
}

if(!function_exists('tubapay2_order_statuses_upgrade')) {
    function tubapay2_order_statuses_upgrade() {
        $statuses_changes = array(
            'wc-tubapay-pending' => 'wc-tubapay2-pending',
            'wc-tubapay-sent' => 'wc-tubapay2-pending',
            'wc-tubapay-fail' => 'wc-tubapay2-fail',
            'wc-tubapay-accepted' => 'wc-tubapay2-accepted',
            'wc-tubapay-rejected' => 'wc-tubapay2-rejected',
        );

        // Not used due to bug: https://github.com/woocommerce/woocommerce/issues/27238
//        $args = array(
//            'status' => array_keys($statuses_changes),
//            'return' => 'ids',
//            'limit' => -1,
//        );
//        $orders = wc_get_orders($args);

        $old_statuses = array_keys($statuses_changes);
        $old_statuses = "'" . implode("','", $old_statuses) . "'";

        global $wpdb;
        $orders = $wpdb->get_col( "
            SELECT `ID`
            FROM `{$wpdb->prefix}posts`
            WHERE `post_type` = 'shop_order'
            AND `post_status` IN ( {$old_statuses} )
        " );

        if (count($orders) > 0) {
            foreach ($statuses_changes as $status_old => $status_new) {
//                $args = array(
//                    'status' => array($status_old),
//                    'return' => 'ids',
//                    'limit' => -1,
//                );
//                $orders = wc_get_orders($args);

                $orders = $wpdb->get_col( "
                    SELECT `ID`
                    FROM `{$wpdb->prefix}posts`
                    WHERE `post_type` = 'shop_order'
                    AND `post_status` IN ( {$status_old} )
                " );

                foreach ($orders as $order) {
                    $order = wc_get_order( $order );
                    $slug = 'wc-'.$order->get_status();

                    if ($slug == $status_old) {
                        $order->update_status($status_new);
                        $order->save();
                    }
                }
            }
        }
    }
}

if(!function_exists('tubapay2_wpex_wc_register_post_statuses')) {
    function tubapay2_wpex_wc_register_post_statuses() {
        register_post_status('wc-tubapay2-pending', array(
            'label' => _x('Oczekiwanie na dokonanie płatności TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay oczekujące (%s)', 'Tubapay oczekujące (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-fail', array(
            'label' => _x('Umowa odrzucona przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay odrzucone (%s)', 'Tubapay odrzucone (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-accepted', array(
            'label' => _x('Zaakceptowano przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay zaakceptowane (%s)', 'Tubapay zaakceptowane (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-rejected', array(
            'label' => _x('Odrzucone przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay odrzucone (%s)', 'Tubapay wypowiedziane (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-terminated', array(
            'label' => _x('Wypowiedziane przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay wypowiedziane (%s)', 'Tubapay wypowiedziane (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-error', array(
            'label' => _x('Błąd komunikacji z TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Tubapay błąd (%s)', 'Tubapay błędy (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-p-paid', array(
            'label' => _x('Zrealizowane w całości przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Zrealizowane w całości przez TubaPay (%s)', 'Zrealizowane w całości przez TubaPay (%s)', 'tubapay-v2')
        ));
        register_post_status('wc-tubapay2-p-pend', array(
            'label' => _x('W trakcie realizacji przez TubaPay', 'WooCommerce Order status', 'tubapay-v2'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('W trakcie realizacji przez TubaPay (%s)', 'W trakcie realizacji przez TubaPay (%s)', 'tubapay-v2')
        ));

    }
}

if(!function_exists('tubapay2_user_has_cap')) {
    function tubapay2_user_has_cap($allcaps, $caps, $args) {
        //zezwolenie niezalogowanym na płacenie bez logowania
        if (isset($caps[0], $_GET['key'])) {
            if ($caps[0] == 'pay_for_order') {
                $order_id = isset($args[2]) ? $args[2] : null;
                $order = wc_get_order($order_id);
                if ($order) {
                    $allcaps['pay_for_order'] = true;
                }
            }
        }
        return $allcaps;
    }
}

if(!function_exists('tubapay2_manage_shop_order_posts_custom_column')) {
    function tubapay2_manage_shop_order_posts_custom_column($column, $post_id) {
        global $the_order;

        switch ($column) {
            case 'tubapay' :
                // Get custom order metadata
                $main_order = $the_order->get_meta('tubapay2_main_order');
                if (!empty($main_order)) {
                    $order = wc_get_order( $main_order );
                    $html =  "Podzamówienie dla: <a target='_blank' href='" . $order->get_edit_order_url()  . "'>" . $main_order . "</a>";
                    echo wp_kses(
                        $html,
                        array(
                            'a'     => array(
                                'href'      => array(),
                                'target'    => array(),
                                'class'     => array(),
                            ),
                        )
                    );
                } else {
                    echo '';
                }
                break;
        }
    }
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
 */
if(!function_exists('tubapay2_declare_cart_checkout_blocks_compatibility')) {
    function tubapay2_declare_cart_checkout_blocks_compatibility() {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
}

/**
 * Custom function to register a payment method type
 */
if(!function_exists('tubapay2_register_order_approval_payment_method_type')) {
    function tubapay2_register_order_approval_payment_method_type() {
        // Check if the required class exists
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        // Include the custom Blocks Checkout class
        require_once plugin_dir_path(__FILE__) . '/tubapay_gateway_blocks.php';

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                // Register an instance of My_Custom_Gateway_Blocks
                $payment_method_registry->register(new TubaPay2_Gateway_Blocks);
            }
        );
    }
}

//formularz po checkoucie
if(!function_exists('tubapay2_before_woocommerce_pay')) {
    function tubapay2_before_woocommerce_pay() {
        if (isset($_GET['key'])) {
            $order_id = intval(basename(strtok($_SERVER["REQUEST_URI"], '?')));
            $order = wc_get_order($order_id);

            if ($order->get_payment_method() == 'tubapay2') {
                $tubapay = new WC_Gateway_TubaPay2();
                $tubapay->getTubaPayForm($order_id);
            }
        }
    }
}

/**
 * Wyświetlenie meta
 */
if(!function_exists('tubapay2_checkout_field_display_admin_order_meta')) {
    function tubapay2_checkout_field_display_admin_order_meta($order) {
        $order_id = $order->get_id();
        $method = get_post_meta($order_id, '_payment_method', true);
        if ($method != 'tubapay2')
            return;

        $agreementNumber = get_post_meta($order_id, 'agreementNumber', true);
        $tubapay2Response = get_post_meta($order_id, 'tubapay2Response', true);
        $raty = get_post_meta($order_id, 'raty', true);

        $html = '<h3>Dane przesłane do TubaPay</h3><hr>
            <strong>' . esc_html__('Płatności', 'tubapay-v2') . ':</strong> ' . esc_html($raty) . '<br>
            <h3>Dane otrzymane od TubaPay</h3><hr>
            <strong>' . esc_html__('Odpowiedź TubaPay', 'tubapay-v2') . ':</strong> ' . esc_html($tubapay2Response) . '<br>
            <strong>' . esc_html__('Numer umow', 'tubapay-v2') . ':</strong> ' . esc_html($agreementNumber) . '<br>
            ';

        echo wp_kses(
            $html,
            array(
                'h3' => array(),
                'hr' => array(),
                'strong' => array(),
                'br' => array(),
            )
        );
    }
}

if(!function_exists('tubapay2_wc_order_statuses')) {
    function tubapay2_wc_order_statuses($order_statuses) {
        $order_statuses['wc-tubapay2-pending'] = _x('Oczekiwanie na dokonanie płatności TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-fail'] = _x('Umowa odrzucona przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-accepted'] = _x('Umowa zaakceptowana przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-rejected'] = _x('Umowa odrzucona przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-terminated'] = _x('Umowa wypowiedziana przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-error'] = _x('Błąd komunikacji z TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-p-paid'] = _x('Zrealizowane w całości przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');
        $order_statuses['wc-tubapay2-p-pend'] = _x('W trakcie realizacji przez TubaPay', 'WooCommerce Order status', 'tubapay-v2');

        return $order_statuses;
    }
}

if(!function_exists('tubapay2_woocommerce_order_is_paid_statuses')) {
    function tubapay2_woocommerce_order_is_paid_statuses($statuses) {
        $statuses[] = 'wc-tubapay2-accepted';
        $statuses[] = 'wc-tubapay2-p-paid';
        $statuses[] = 'wc-tubapay2-p-pend';
        return $statuses;
    }
}

// Dodanie statusu do akceptowanych
if(!function_exists('tubapay2_woocommerce_valid_order_statuses_for_payment')) {
    function tubapay2_woocommerce_valid_order_statuses_for_payment($array, $instance) {
        $my_order_status = array(
            'tubapay2-pending',
            'tubapay2-fail',
            'tubapay2-accepted',
            'tubapay2-rejected',
            'tubapay2-terminated',
            'tubapay2-error',
            'tubapay2-p-paid',
            'tubapay2-p-pend'
        );
        return array_merge($array, $my_order_status);
    }
}

if(!function_exists('tubapay2_get_status')) {
    function tubapay2_get_status($status, $wc_prefix = true) {
        $tuba_gw = new WC_Gateway_TubaPay2();
        $status_option = str_replace('wc', 'status', $status);
        $option = $tuba_gw->get_option($status_option);
        if (!empty($option)) {
            $return = $option;
        } else {
            $return = $status;
        }

        if (!$wc_prefix) {
            $return = str_replace('wc-', '', $return);
        }

        return $return;
    }
}

if(!function_exists('tubapay2_woocommerce_add_to_cart_validation')) {
    function tubapay2_woocommerce_add_to_cart_validation($passed, $product_id, $quantity) {
        if (isset($_GET['tubapay']) && $_GET['tubapay'] == 'direct_checkout') {
            if (!WC()->cart->is_empty()) {
                WC()->cart->empty_cart();
            }
        }

        return $passed;
    }
}

if(!function_exists('tubapay2_direct_checkout_payment')) {
    function tubapay2_direct_checkout_payment($available_gateways) {
        // Not in backend (admin)
        if (is_admin())
            return $available_gateways;

        if (isset($_GET['tubapay']) && $_GET['tubapay'] == 'direct_checkout') {
            foreach ($available_gateways as $gateway => $gw) {
                if ($gateway !== 'tubapay2') {
                    unset($available_gateways[$gateway]);
                }
            }

        }
        return $available_gateways;
    }
}

if(!function_exists('tubapay2_woocommerce_before_add_to_cart_form')) {
    function tubapay2_woocommerce_before_add_to_cart_form() {
        $tuba_gw = new WC_Gateway_TubaPay2();
        if ($tuba_gw->get_option('direct_checkout') == 'yes' && is_product()) {
            global $product;

            if (is_a($product, 'WC_Product')) {
                $product_price = $product->get_price();

                $html = $tuba_gw->getQuickOrderInfobox($product_price, $product);

                echo wp_kses(
                    $html,
                    array(
                        'div' => array(
                            'class' => array(),
                            'onclick' => array(),
                            'style' => array(),
                        ),
                        'br' => array(),
                        'bdi' => array(),
                        'span' => array(
                            'class' => array(),
                        ),
                    )
                );
            }
        }
    }
}

if(!function_exists('tubapay2_woocommerce_gateway_description')) {
    function tubapay2_woocommerce_gateway_description($description, $payment_id) {
        global $woocommerce;
        if ('tubapay2' === $payment_id) {
            ob_start(); // Start buffering
            $amount = $woocommerce->cart->total;

            $tuba_gw = new WC_Gateway_TubaPay2();
            $html = $tuba_gw->getSelectInstallmentsInputForAmount($amount);

            echo wp_kses(
                $html,
                array(
                    'div' => array(
                        'class' => array(),
                        'style' => array(),
                    ),
                    'p' => array(
                        'style' => array(),
                    ),
                    'span' => array(
                        'class' => array(),
                    ),
                    'input' => array(
                        'type' => array(),
                        'class' => array(),
                        'value' => array(),
                        'name' => array(),
                        'id' => array(),
                        'style' => array(),
                        'checked' => array(),
                        'required' => array(),
                    ),
                    'label' => array(
                        'style' => array(),
                        'for' => array(),
                        'class' => array(),
                    ),
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'class' => array(),
                    ),
                )
            );

            $description .= ob_get_clean(); // Append buffered content
        }
        return $description;
    }
}

if(!function_exists('tubapay2_woocommerce_checkout_process')) {
// Checkout custom field validation
    function tubapay2_woocommerce_checkout_process() {
        if ($_POST['payment_method'] === 'tubapay2' && empty($_POST['RODO_BP'])) {
            wc_add_notice(__('Wyraź zgodę na przetwarzanie danych przez TubaPay', 'tubapay-v2'), 'error');
        }

        if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'tubapay2'
            && isset($_POST['tubapay_installments']) && empty($_POST['tubapay_installments'])) {
            wc_add_notice(__('Wybierz ilość płatności', 'tubapay-v2'), 'error');
        }
    }
}

// Checkout custom field save to order meta
if(!function_exists('tubapay2_woocommerce_checkout_create_order')) {
    function tubapay2_woocommerce_checkout_create_order($order, $data) {
        if (isset($_POST['tubapay_installments']) && !empty($_POST['tubapay_installments'])) {
            $installments = esc_attr(sanitize_text_field($_POST['tubapay_installments']));
            $order->update_meta_data('tubapay_installments', $installments);
        }
        if (isset($_POST['RODO_BP']) && !empty($_POST['RODO_BP'])) {
            $RODO_BP = intval($_POST['RODO_BP']);
            $order->update_meta_data('tubapay2_RODO_BP', $RODO_BP);
        }
    }
}

// Display custom field on order totals lines everywhere
if(!function_exists('tubapay2_woocommerce_get_order_item_totals')) {
    function tubapay2_woocommerce_get_order_item_totals($total_rows, $order, $tax_display) {
        if ($order->get_payment_method() === 'tubapay2' && $tubapay_option = $order->get_meta('tubapay_installments')) {
            $sorted_total_rows = [];

            foreach ($total_rows as $key_row => $total_row) {
                $sorted_total_rows[$key_row] = $total_row;
                if ($key_row === 'payment_method') {
                    $sorted_total_rows['tubapay_installments'] = [
                        'label' => __("Raty Tubapay", "tubapay-v2"),
                        'value' => esc_html($tubapay_option),
                    ];
                }
            }
            $total_rows = $sorted_total_rows;
        }
        return $total_rows;
    }
}

// Display custom field in Admin orders, below billing address block
if(!function_exists('tubapay2_display_option_near_admin_order_billing_address')) {
    function tubapay2_display_option_near_admin_order_billing_address($order) {
        if ($tubapay_option = $order->get_meta('_tubapay_option')) {
            $html = '<div class="tubapay-option">
                <p><strong>' . esc_html__('Ilość płatności TubaPay', 'tubapay-v2') . ':</strong> ' . esc_html($tubapay_option) . '</p>
                </div>';

            echo wp_kses(
                $html,
                array(
                    'div' => array(
                        'class' => array(),
                    ),
                    'p' => array(),
                    'strong' => array(),
                )
            );
        }
    }
}

if(!function_exists('tubapay2_turn_off')) {
    function tubapay2_turn_off($available_gateways) {
        global $woocommerce;
        if (is_admin()) {
            return $available_gateways;
        }

        if (is_wc_endpoint_url('order-pay')) { // Pay for order page
            $key = esc_attr(sanitize_text_field($_GET['key']));
            $order_id = wc_get_order_id_by_order_key($key);
            $order = wc_get_order($order_id);
            $order_total = $order->total;

        } else { // Cart/Checkout page
            $order_total = WC()->cart->total;
        }

//    $order_total = $order_total*100;
        $order_total = ceil($order_total);

        $tubapay = new WC_Gateway_TubaPay2();
        $check = $tubapay->checkIfAvailableForAmount($order_total);

        if (!$check) {
            unset($available_gateways['tubapay2']); // unset Cash on Delivery
        }

        return $available_gateways;

    }
}

if(!function_exists('tubapay2_get_checkout_payment_url')) {
    function tubapay2_get_checkout_payment_url($order)  {
        return $order->get_checkout_payment_url(true);
    }
}

// Display partials data
if(!function_exists('tubapay2_woocommerce_admin_order_data_after_order_details')) {
    function tubapay2_woocommerce_admin_order_data_after_order_details( $order ) {
        $order_id = $order->get_id();
        $tuba_gw = new WC_Gateway_TubaPay2();
        $html = '';
        if ($tuba_gw->get_option( 'partial_orders' ) == 'yes') {
            $html .= '<hr>
                <div class="form-field form-field-wide tubapay2_partials_data_column">
                    <h3>Podzamówienia miesięczne TubaPay</h3>';
            $main_order = $order->get_meta('tubapay2_main_order');

            if (!empty($main_order)) {
                $html .= "<b>Główne zamówienie TubaPay: </b>
                    <a target='_blank' href='".get_edit_post_link($main_order)."'>Zamówienie nr.".$main_order."</a>
                    <br />";
            }

            $partials = $order->get_meta('tubapay2_partial_orders');

            if (!empty($partials)) {
                $html .= "<b>Częściowe zamówienia TubaPay: </b><br>";
                $partials = json_decode($partials);
                foreach ($partials as $partial) {
                    $html .= "<a target='_blank' href='".get_edit_post_link($partial)."'>Zamówienie nr.".$partial."</a><br>";
                }
            }
            $html .= "</div>";
        }
        echo wp_kses(
            $html,
            array(
                'div'      => array(
                    'class' => array(),
                ),
                'h3'      => array(
                    'style' => array(),
                ),
                'a'     => array(
                    'href'      => array(),
                    'target'    => array(),
                ),
                'hr'      => array( ),
                'br'      => array( ),
            )
        );
    }
}

if(!function_exists('tubapay2_woocommerce_order_status_changed')) {
    function tubapay2_woocommerce_order_status_changed($order_id, $status_from, $status_to) {
        $order = wc_get_order($order_id);

        if ($status_from == 'tubapay2-p-pend' && $status_to !== tubapay2_get_status('tubapay2-p-paid')) {
            $order->update_status('tubapay2-p-pend', '', false);
        }
    }
}

if(!function_exists('tubapay2_add_admin_order_list_custom_column')) {
    function tubapay2_add_admin_order_list_custom_column($columns) {
        $reordered_columns = array();

        // Inserting columns to a specific location
        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;
            if ($key == 'order_status') {
                // Inserting after "Status" column
                $reordered_columns['tubapay'] = __('Title1', 'theme_domain');
            }
        }
        return $reordered_columns;
    }
}

if(!function_exists('tubapay2_add_wc_order_list_custom_column')) {
    function tubapay2_add_wc_order_list_custom_column($columns) {
        $reordered_columns = array();

        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;

            if ($key === 'order_status') {
                $reordered_columns['tubapay'] = __('Tubapay', 'tubapay-v2');
            }
        }
        return $reordered_columns;
    }
}

if(!function_exists('tubapay2_display_wc_order_list_custom_column_content')) {
    function tubapay2_display_wc_order_list_custom_column_content($column, $order) {

        switch ($column) {
            case 'tubapay' :
                $main_order = $order->get_meta('tubapay2_main_order');
                if (!empty($main_order)) {
                    $order = wc_get_order( $main_order );
                    $html =  "Podzamówienie dla: <a target='_blank' href='" . $order->get_edit_order_url()  . "'>" . $main_order . "</a>";
                    echo wp_kses(
                        $html,
                        array(
                            'a'     => array(
                                'href'      => array(),
                                'target'    => array(),
                                'class'     => array(),
                            ),
                        )
                    );
                } else {
                    echo '';
                }
                break;
        }
    }
}

if(!function_exists('tubapay_display_calc_box')) {
    function tubapay_display_calc_box($price, $product) {
        if (is_product()) {
            $tuba_gw = new WC_Gateway_TubaPay2();
            if ($tuba_gw->get_option('direct_checkout') == 'yes' && is_product()) {
                $price = $price . get_tubapay_quick_order_infobox($price, $product);
            }
        }
        return $price;
    }
}

//add_action( 'cfw_checkout_payment_method_tab', function() {
//    echo '<h2>Your Disclaimer and Other HTML</h2>';
//}, 2 );

function custom_gateway_icon( $icon, $id ) {
    if ( $id === 'tubapay2' ) {
//        $icon = '<img src="' . plugins_url('../assets/images/tubapay-icon.svg', __FILE__) . '" > ';
        $tooltip_text = "<strong>Hej, tu <a href='https://tubapay.pl/dla-klienta' target='_blank' style='color:#000000 !important'> TubaPay!</a> Jestem tutaj, aby podzielić Twoją płatność.</strong><br><br>
<ul>
	<li>Zrobisz to 24/7/365, łatwo, szybko i wygodnie</li>
	<li>Włączasz w 2 minuty bez skomplikowanych wniosków i czekania na decyzję</li>
	<li>W pełni bezpiecznie i bez dodatkowych kosztów</li>
	<li>Kolejne płatności realizujesz według wybranej przez Ciebie opcji harmonogramu</li>
</ul>
<script>
(function($) {
    $(document).ready(function() {
        // Delegowanie zdarzeń na tooltip trigger
        $(document).off('click', '.custom-gateway-tooltip-trigger').on('click', '.custom-gateway-tooltip-trigger', function(event) {
            // Zapobiegaj domyślnemu zachowaniu
            event.preventDefault();

            // Zatrzymanie propagacji zdarzenia
            event.stopPropagation();

            // Przełącz widoczność tooltipa
            $('.custom-gateway-tooltip').toggleClass('mm-show');
            $('.custom-gateway-tooltip-trigger').toggleClass('mm-show');
            console.log('toggle');
        });

        let tooltipListenerAdded = false;
        if (!tooltipListenerAdded) {
            tooltipListenerAdded = true; // Ustaw flagę, aby wskazywać, że listener został
            // Dodaj globalny listener z fazą capture
            document.addEventListener('click', function(event) {
                if (!$('.custom-gateway-tooltip.mm-show').length) {
                    return;
                }

                // Jeśli kliknięto poza tooltip, ukryj tooltip
                if (!$(event.target).closest('.custom-gateway-tooltip').length &&
                    !$(event.target).closest('.custom-gateway-tooltip-trigger').length) {
                    $('.custom-gateway-tooltip').removeClass('mm-show');
                    $('.custom-gateway-tooltip-trigger').removeClass('mm-show');
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true); // true aktywuje fazę capture
        }
    });
})(jQuery.noConflict());
</script>
";
        $icon = '<div class="custom-gateway-icon-wrapper">
                <img src="'.plugins_url('../assets/images/tubapay-icon.svg', __FILE__).'" alt="Tubapay" />
                <span class="custom-gateway-tooltip-trigger">i</span>
                <div class="custom-gateway-tooltip">'.$tooltip_text.'</div>
            </div>
<style>
.custom-gateway-icon-wrapper {
    display: inline-flex;
    align-items: center;
    position: relative;
}

label[for="payment_method_tubapay2"] {
	display:inline-flex !important;
	align-items:flex-start !important;
}
.custom-gateway-icon {
    max-width: 50px;
    margin-right: 10px;
}
.custom-gateway-tooltip-trigger {
    display:flex;
	justify-content:center;
	align-items:center;
    font-weight: bold;
    font-size:11px;
    color: #000;
    cursor: pointer;
    margin-left:25px;
    position: relative;
    border:2px solid #000000;
    border-radius: 15px;
    padding: 5px;
    line-height: 10px;
	width:6px;
	height:6px;
	max-width:6px;
	max-height:6px;
}
.custom-gateway-tooltip-trigger.mm-show:after {
	content:"";
    display:block;
    width:15px;
    height:15px;
    rotate:45deg;
    background:#f1e9ed;
    position:absolute;
    bottom:-21px;
	z-index:5;
}
.custom-gateway-tooltip-trigger:hover {
    color: #005f8d; /* Slightly darker color on hover */
}
.custom-gateway-tooltip {
    display: none;
    background:#f1e9ed;
    color:#000000;
    padding:20px;
    border-radius:8px;
    font-size:12px;
    line-height:18px;
    position: absolute;
	z-index:6;
    top:calc(100% + 7px);
    left:50%;
    transform:translateX(-50%);
    white-space:nowrap;
    z-index: 10;
    box-shadow:0 1px 2px rgba(0, 0, 0, 0.1);
}
.custom-gateway-tooltip ul {
	list-style-type:disc !important;
	padding-left:15px !important;
}
.custom-gateway-tooltip ul li {
	font-size:12px !important;
	line-height:17px !important;
}
.custom-gateway-tooltip ul li::marker {
	color:#f73b9b !important;
}
.custom-gateway-tooltip ul li:before,
.custom-gateway-tooltip ul li:after {
	display:none !important;
}

.custom-gateway-tooltip strong {
	font-weight:bold;
}

.custom-gateway-tooltip.mm-show {
    display: block;
}
@media (max-width:767px) {
	label[for="payment_method_tubapay2"] {
		display:inline !important;
		flex-wrap:wrap !important;
		width:calc(100% - 28px);
	}
	.custom-gateway-tooltip {
		left:unset;
		transform:unset;
		right:0;
		white-space:unset !important;
		position:relative;
		top:8px;
        margin-bottom:10px;
		margin-left:-26px;
		width:100%;
	}
	.custom-gateway-icon-wrapper {
		flex-wrap:wrap;
	}
	li.wc_payment_method.payment_method_tubapay2 {
		display:flex !important;
		align-items:baseline !important;
		flex-wrap:wrap !important;
	}
}
</style>';
    }
    return $icon;
}

//public function get_icon_with_tooltip() {
//    $icon_url = plugins_url( '../assets/images/tubapay-icon.svg', __FILE__ );
//    $tooltip_text = __( 'This is your custom gateway tooltip text.', 'tubapay-v2' );
//
//    $html  = '<div class="custom-gateway-icon-wrapper">
//                <img src="'.$icon_url.'" alt="Tubapay" class="custom-gateway-icon" />
//                <span class="custom-gateway-tooltip-trigger">?</span>
//                <div class="custom-gateway-tooltip">'.$tooltip_text.'</div>
//            </div>';
//
//    return $html;
//}
