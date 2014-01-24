<?php
/*
Plugin Name: PMPro WooCommerce
Plugin URI: http://www.paidmembershipspro.com/pmpro-woocommerce/
Description: Integrate WooCommerce with Paid Memberships Pro.
Version: .2
Author: Stranger Studios
Author URI: http://www.strangerstudios.com

General Idea:

	1. Connect WooCommerce products to PMPro Membership Levels.
	2. If a user purchases a certain product, give them the corresponding membership level.
	3. If WooCommerce subscriptions are installed, and a subscription is cancelled, cancel the corresponding PMPro membership level.
	
	NOTE: You can still only have one level per user with PMPro.
*/

/*
	Globals/Settings	
*/

// Get all Product Membership Levels
global $pmprowoo_product_levels;
$pmprowoo_product_levels = get_option('_pmprowoo_product_levels');
if (empty($pmprowoo_product_levels)) {
    $pmprowoo_product_levels = [];
}

//Define discounts per level. Discounts applied to all WooCommerce purchases. Array is of form PMPro $level_id => .1 (discount as decimal)
//Example below. Copy this to your active theme's functions.php or a custom plugin, edit, and remove the comment //
global $pmprowoo_member_discounts;
$pmprowoo_member_discounts = get_option('_pmprowoo_member_discounts');
if (empty($pmprowoo_member_discounts)) {
    $pmprowoo_member_discounts = [];
}

//apply discounts to subscriptions as well?
//Example below. Copy this to your active theme's functions.php or a custom plugin, edit, and remove the comment //
//global $pmprowoo_discounts_on_subscriptions;
//$pmprowoo_discounts_on_subscriptions = false;

// Get all PMPro Membership Levels
global $membership_levels;
/*
	Add users to membership levels after order is completed.
*/
function pmprowoo_add_membership_from_order($order_id)
{
    global $pmprowoo_product_levels;

    echo $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    //membership product ids
    $product_ids = array_keys($pmprowoo_product_levels);

    //get order
    $order = new WC_Order($order_id);

    //does the order have a user id and some products?
    if(!empty($order->user_id) && sizeof($order->get_items()) > 0)
    {
        foreach($order->get_items() as $item)
        {
            if($item['product_id'] > 0) 	//not sure when a product has id 0, but the Woo code checks this
            {
                //is there a membership level for this product?
                if(in_array($item['product_id'], $product_ids))
                {

                    //add the user to the level
                    pmpro_changeMembershipLevel($pmprowoo_product_levels[$item['product_id']], $order->user_id);

                    //only going to process the first membership product, so break the loop
                    break;
                }
            }
        }
    }
}
add_action("woocommerce_order_status_completed", "pmprowoo_add_membership_from_order");

/*
	Cancel memberships when orders go into pending, processing, refunded, failed, or on hold.
*/
function pmprowoo_cancel_membership_from_order($order_id)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    //membership product ids
    $product_ids = array_keys($pmprowoo_product_levels);

    //get order
    $order = new WC_Order($order_id);

    //does the order have a user id and some products?
    if(!empty($order->user_id) && sizeof($order->get_items()) > 0)
    {
        foreach($order->get_items() as $item)
        {
            if($item['product_id'] > 0) 	//not sure when a product has id 0, but the Woo code checks this
            {
                //is there a membership level for this product?
                if(in_array($item['product_id'], $product_ids))
                {
                    //add the user to the level
                    pmpro_changeMembershipLevel(0, $order->user_id);

                    //only going to process the first membership product, so break the loop
                    break;
                }
            }
        }
    }
}
add_action("woocommerce_order_status_pending", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_processing", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_refunded", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_failed", "pmprowoo_cancel_membership_from_order");
add_action("woocommerce_order_status_on_hold", "pmprowoo_cancel_membership_from_order");

/*
	Activate memberships when WooCommerce subscriptions change status.
*/
function pmprowoo_activated_subscription($user_id, $subscription_key)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    $subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
    if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) )
    {
        $product_id = $subscription['product_id'];
        $order_id = $subscription['order_id'];

        //membership product ids
        $product_ids = array_keys($pmprowoo_product_levels);

        //get order
        $order = new WC_Order($order_id);

        //does the order have a user id and some products?
        if(!empty($order->user_id) && !empty($product_id))
        {
            //is there a membership level for this product?
            if(in_array($product_id, $product_ids))
            {
                //add the user to the level
                pmpro_changeMembershipLevel($pmprowoo_product_levels[$product_id], $order->user_id);
            }
        }
    }
}
add_action("activated_subscription", "pmprowoo_activated_subscription", 10, 2);
add_action("reactivated_subscription", "pmprowoo_activated_subscription", 10, 2);

/*
	Cancel memberships when WooCommerce subscriptions change status.
*/
function pmprowoo_cancelled_subscription($user_id, $subscription_key)
{
    global $pmprowoo_product_levels;

    //don't bother if array is empty
    if(empty($pmprowoo_product_levels))
        return;

    /*
        does this order contain a membership product?
    */
    $subscription = WC_Subscriptions_Manager::get_users_subscription( $user_id, $subscription_key );
    if ( isset( $subscription['product_id'] ) && isset( $subscription['order_id'] ) )
    {
        $product_id = $subscription['product_id'];
        $order_id = $subscription['order_id'];

        //membership product ids
        $product_ids = array_keys($pmprowoo_product_levels);

        //get order
        $order = new WC_Order($order_id);

        //does the order have a user id and some products?
        if(!empty($order->user_id) && !empty($product_id))
        {
            //is there a membership level for this product?
            if(in_array($product_id, $product_ids))
            {
                //add the user to the level
                pmpro_changeMembershipLevel(0, $order->user_id);
            }
        }
    }
}
add_action("cancelled_subscription", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_trashed", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_expired", "pmprowoo_cancelled_subscription", 10, 2);
add_action("subscription_put_on", "pmprowoo_cancelled_subscription", 10, 2);

/*
 * Update Product Prices with Membership Price or Discount
 */
function pmprowoo_get_membership_price($price, $product)
{
    global $current_user, $pmprowoo_member_discounts, $pmprowoo_product_levels, $woocommerce, $cart_membership_level;

    $newprice = false;
    $product_ids = array_keys($pmprowoo_product_levels); // membership product levels
    $items = $woocommerce->cart->cart_contents; // items in the cart

    // Search for any membership level products. IF found, use first one as the cart membership level.
    foreach($items as $item)
    {
        if (in_array($item['product_id'], $product_ids)) {
            $cart_membership_level = $pmprowoo_product_levels[$item['product_id']];
            break;
        }
    }

    // use cart membership level price if set, otherwise use current member level
    if (isset($cart_membership_level)) {
        $level_price = '_level_' . $cart_membership_level . '_price';
    }
    elseif (pmpro_hasMembershipLevel) {
        $level_price = '_level_' . $current_user->membership_level->id . '_price';
    }

    $newprice = get_post_meta($product->id, $level_price, true);

    // if we didn't get a price, look for a general member discount
    if($newprice === false || $newprice === "")
    {
        $newprice = $price;

        //are there discounts? does the current user have a membership?
        if(!empty($pmprowoo_member_discounts) && pmpro_hasMembershipLevel())
        {
            //ignore subscriptions if we are set that way
            if(!$pmprowoo_discounts_on_subscriptions && ($product->product_type == "subscription" || $product->product_type == "variable-subscription" || $product->product_type == "subscription_variation"))
                return $price;

            //check all memberships
            foreach($pmprowoo_member_discounts as $level_id => $discount)
            {
                if(pmpro_hasMembershipLevel($level_id))
                {
                    //apply discount
                    $newprice = $price - ($price * $discount);

                    break;        //can only have 1 level at a time
                }
            }
        }
    }

	return $newprice;
}

// only change price if this is on the front end
if (!is_admin()) {
    add_filter("woocommerce_get_price", "pmprowoo_get_membership_price", 10, 2);
}

/*
 * Add PMPro Tab to Edit Products page
 */

function pmprowoo_tab_options_tab() {
    ?>
    <li class="pmprowoo_tab"><a href="#pmprowoo_tab_data"><?php _e('Membership', 'pmprowoo'); ?></a></li>
<?php
}
add_action('woocommerce_product_write_panel_tabs', 'pmprowoo_tab_options_tab');

/*
 * Add Fields to PMPro Tab
 */
function pmprowoo_tab_options() {

   global $membership_levels, $pmprowoo_product_levels;

   $membership_level_options[0] = 'None';
   foreach ($membership_levels as $option) {
       $key = $option->id;
       $membership_level_options[$key] = $option->name;
   }
   ?>

    <div id="pmprowoo_tab_data" class="panel woocommerce_options_panel">

        <div class="options_group">
            <p class="form-field">                <?php
                    // Membership Product
                    woocommerce_wp_select(
                        array(
                        'id'      => '_membership_product_level',
                        'label'   => __( 'Membership Product', 'pmprowoo' ),
                        'options' => $membership_level_options
                        )
                    );
                ?>
            </p>
        </div>
        <div class="options-group">
            <p class="form-field">
                <?php
                    // For each membership level, create respective price field
                    foreach ($membership_levels as $level) {
                        woocommerce_wp_text_input(
                            array(
                                'id'                 => '_level_' . $level->id . '_price',
                                'label'              => __(  $level->name . " Price", 'pmprowoo' ),
                                'placeholder'        => '',
                                'type'               => 'number',
                                'desc_tip'           => 'true',
                                'custom_attributes'  => array(
                                    'step'  => 'any',
                                    'min'   => '0'
                                )
                            )
                        );
                    }
                ?>
            </p>
        </div>
    </div>
<?php
}
add_action('woocommerce_product_write_panels', 'pmprowoo_tab_options');

/*
 * Process PMPro Meta
 */
function pmprowoo_process_product_meta() {

    global $membership_levels, $post_id, $pmprowoo_product_levels;

    // Save membership product level
    $level = $_POST['_membership_product_level'];

    // update array of product levels
    $pmprowoo_product_levels[$post_id] = $level;

    if( isset( $level ) ) {
        update_post_meta( $post_id, '_membership_product_level', esc_attr( $level ));
        update_option('_pmprowoo_product_levels', $pmprowoo_product_levels);

        // Save each membership level price
        foreach ($membership_levels as $level) {
            $price = $_POST['_level_' . $level->id . "_price"];
            if( isset( $price ) ) {
                update_post_meta( $post_id, '_level_' . $level->id . '_price', esc_attr( $price ));
            }
        }
    }
}
add_action( 'woocommerce_process_product_meta', 'pmprowoo_process_product_meta' );

/*
 * Add Membership Discount Field to Edit Membership Page
 */

function pmprowoo_add_membership_discount() {

    global $pmprowoo_member_discounts;
    $level_id = intval($_REQUEST['edit']);
    if($level_id > 0)
        $membership_discount = $pmprowoo_member_discounts[$level_id] * 100; //convert back to %
    else
        $membership_discount = '';
    ?>
    <h3 class="topborder">Set Membership Discount</h3>
    <p>Set a membership discount for this level which will be applied when a user with this membership level is logged in.</p>
    <table>
        <tbody class="form-table">
        <tr>
            <th scope="row" valign="top"><label for="membership_discount">Membership Discount (%):</label></th>
            <td>
                <input type="number" min="0" max="100" name="membership_discount" value="<?php echo esc_attr($membership_discount);?>" />
            </td>
        </tr>
        </tbody>
    </table>

<?php
}

add_action("pmpro_membership_level_after_other_settings", "pmprowoo_add_membership_discount");

/*
 * Update Membership Level Discount
 */
function pmprowoo_save_membership_level($level_id) {
    global $pmprowoo_member_discounts;

    //convert % to decimal
    $member_discount = $_POST['membership_discount']/100;
    $pmprowoo_member_discounts[$level_id] = $member_discount;
    update_option('_pmprowoo_member_discounts', $pmprowoo_member_discounts);
}
add_action("pmpro_save_membership_level", "pmprowoo_save_membership_level");


