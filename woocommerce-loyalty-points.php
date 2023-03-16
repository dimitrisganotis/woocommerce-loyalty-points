<?php

/**
 * Plugin Name: My Loyalty Points
 * Plugin URI: https://dimitrisganotis.gr
 * Description: A plugin that rewards customers with points for their purchases
 * Version: 1.0.0
 * Author: Dimitris Ganotis
 * Author URI: https://dimitrisganotis.gr
 */

defined( 'ABSPATH' ) or die( 'No direct access allowed' );

class My_Loyalty_Points
{
    public function __construct()
    {
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'add_reward_points_to_customer_account' ] );
        add_action( 'woocommerce_cart_totals_before_order_total', [ $this, 'display_reward_points' ] );
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'display_reward_points_redemption_input' ] );
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'deduct_reward_points_from_customer_account' ] );
        add_action( 'woocommerce_account_dashboard', [ $this, 'display_user_points' ] );
        add_action( 'admin_menu', [ $this, 'add_points_menu' ] );
    }

    // Add reward points to customer account after purchase
    public function add_reward_points_to_customer_account( $order_id )
    {
        $order = wc_get_order( $order_id );
        $customer_id = $order->get_customer_id();
        $reward_points = $order->get_total() / 10; // award 1 point for every $10 spent
        update_user_meta( $customer_id, 'reward_points', $reward_points );
    }

    // Display reward points on cart and checkout pages
    public function display_reward_points() {
        $customer_id = get_current_user_id();
        $reward_points = get_user_meta( $customer_id, 'reward_points', true );
        if ( $reward_points ) {
            echo '<tr class="reward-points"><th>' . __( 'Reward Points', 'woocommerce' ) . '</th><td>' . $reward_points . '</td></tr>';
        }
    }

    // Allow customers to enter the amount of reward points to redeem
    public function display_reward_points_redemption_input() {
        $customer_id = get_current_user_id();
        $reward_points = get_user_meta( $customer_id, 'reward_points', true );
        if ( $reward_points ) { ?>
            <div class="reward-points-redemption">
                <label for="reward_points_redeemed"><?= __( 'Redeem Reward Points:', 'woocommerce' ) ?></label>
                <input type="number" name="reward_points_redeemed" id="reward_points_redeemed" value="0" min="0" max="<?= $reward_points ?>" step="1" />
            </div>
        <?php }
    }

    // Deduct reward points from customer account when redeemed
    public function deduct_reward_points_from_customer_account( $cart )
    {
        $customer_id = get_current_user_id();
        $reward_points = get_user_meta( $customer_id, 'reward_points', true );
        $reward_points_redeemed = ! empty( $_POST['reward_points_redeemed'] ) ? intval( $_POST['reward_points_redeemed'] ) : 0;

        if ( $reward_points_redeemed > 0 && $reward_points_redeemed <= $reward_points && $cart->get_cart_discount_total() == 0 ) {
            $discount_amount = $reward_points_redeemed / 10; // redeem 1 point for every $10 discount
            $cart->add_fee( __( 'Reward Points Discount', 'woocommerce' ), -$discount_amount );
            update_user_meta( $customer_id, 'reward_points', $reward_points - $reward_points_redeemed );
        }
    }

    // Display user points in My Account section
    public function display_user_points()
    {
        $user_id = get_current_user_id();
        $points = get_user_meta( $user_id, 'reward_points', true );
        if ( $points ) {
            echo '<p>Your current points: ' . $points . '</p>';
        } else {
            echo '<p>You have no points yet.</p>';
        }
    }

    // Add new sub-menu for managing customer points
    public function add_points_menu()
    {
        add_submenu_page(
            'woocommerce',
            'Manage Customer Points',
            'Manage Points',
            'manage_options',
            'customer-points',
            [ $this, 'display_customer_points' ]
        );
    }

    // Display table of customer points
    public function display_customer_points()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'usermeta';

        // Check if form was submitted to update points
        if ( isset( $_POST['update_points'] ) && ! empty( $_POST['user_id'] )  && ! empty( $_POST['points'] ) ) {
            $user_id = $_POST['user_id'];
            $points = $_POST['points'];
            update_user_meta( $user_id, 'reward_points', $points );
        }

        // Get all users with loyalty points
        $users = $wpdb->get_results( "
            SELECT user_id, meta_value
            FROM $table_name
            WHERE meta_key = 'reward_points'
        " );

        // Display table of users and points with form to update points
        echo '<div class="wrap">';
        echo '<h1>Manage Customer Points</h1>';
        echo '<table class="widefat">';
        echo '<thead><tr><th>User ID</th><th>Username</th><th>Email</th><th>Points</th><th>Update Points</th></tr></thead>';
        echo '<tbody>';
        foreach ( $users as $user ) {
            $user_id = $user->user_id;
            $username = get_userdata( $user_id )->user_login;
            $email = get_userdata( $user_id )->user_email;
            $points = $user->meta_value;
            echo '<tr><td>' . $user_id . '</td><td>' . $username . '</td><td>' . $email . '</td><td>' . $points . '</td>';
            echo '<td><form method="post">';
            echo '<input type="hidden" name="user_id" value="' . $user_id . '">';
            echo '<input type="number" name="points" value="0">';
            echo '<input type="submit" name="update_points" class="button" value="Update Points">';
            echo '</form></td></tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

new My_Loyalty_Points();

/*
function is_product_in_offers_category( $product_id ) {
    $offers_category_id = get_term_by( 'name', 'Offers', 'product_cat' )->term_id;
    return has_term( $offers_category_id, 'product_cat', $product_id );
}

function calculate_points( $cart ) {
    $points = 0;
    foreach ( $cart->get_cart_contents() as $cart_item_key => $cart_item ) {
        $product_id = $cart_item['product_id'];
        $product_price = $cart_item['data']->get_price();
        $product_quantity = $cart_item['quantity'];
        if ( is_product_in_offers_category( $product_id ) ) {
            $points += floor( $product_price * $product_quantity / 10 ) * 2;
        } else {
            $points += floor( $product_price * $product_quantity / 10 );
        }
    }
    return $points;
}
*/
