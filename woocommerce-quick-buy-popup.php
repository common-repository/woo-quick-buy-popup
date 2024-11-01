<?php
/*
* Plugin Name: Woocommerce Quick Buy Popup
* Version: 1.0.0
* Description: Woocommerce Quick Buy Popup is a plugin that allows customers to quickly buy a product anywhere in the product as a Popup
* Author: XS Themes
* Author URI: https://xsthemes.com
* Plugin URI: https://xsthemes.com/plugins/
* Text Domain: woocommerce-quickbuy-popup
* Domain Path: /languages
* WC requires at least: 2.5.5
* WC tested up to: 3.5.1
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if (!class_exists('Woocommerce_Quick_Buy_Popup')) {
        class Woocommerce_Quick_Buy_Popup
        {
            protected static $instance;
            public $_version = '1.0.0';
            public $_optionName = 'quickbuy_options';
            public $_optionGroup = 'quickbuy-options-group';
            public $_defaultOptions = array(
                'enable' => '1',
                'enable_ship' => '',
                'hidden_email' => '0',
                'require_email' => '0',
                'require_district' => '0',
                'require_village' => '0',
                'require_address' => '0',
                'enable_location' => '',
                'popup_infor_enable' => '1',
                'in_loop_prod' => '0',
                'button_text1' => 'Quick Buy',
                'button_text2' => '',
                'popup_title' => 'Ordered %s',
                'popup_gotothankyou' => '0',
                'popup_mess' => 'Please enter the correct phone number so that we will call the order confirmation before delivery. Thank you!',
                'popup_sucess' => '<div class="popup-message success" style="color:#333;"><p class="clearfix" style="font-size:22px;color: #00c700;text-align:center">Order Success!</p><p class="clearfix" style="color: #00c700;padding: 10px 0;">Code orders <span style="color: #333;font-weight: bold">#%%order_id%%</span></p><p class="clearfix">Woocommerce Shop will contact you in the next 12 hours. Thank you for giving us the opportunity to serve.<br><strong>Hotline:</strong> 001 205 123456</p><div></div><div></div></div>',
                'popup_error' => 'Order failed. Please order again. Thank you!',
                'out_of_stock_mess' => 'Out of stock!',
                'license_key'       =>  ''
            );

            public static function init()
            {
                is_null(self::$instance) AND self::$instance = new self;
                return self::$instance;
            }

            public function __construct()
            {
                $this->define_constants();
                global $quickbuy_settings;
                $quickbuy_settings = $this->get_dvlsoptions();
                add_filter( 'plugin_action_links_' . QUICKBUY_BASENAME, array( $this, 'add_action_links' ), 10, 2 );
                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
                add_action( 'admin_init', array( $this, 'dvls_register_mysettings') );
                add_action( 'plugins_loaded', array($this,'dvls_load_textdomain') );
                add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

                if($quickbuy_settings['enable']){
                    add_action('wp_enqueue_scripts', array($this, 'load_plugins_scripts'));

                    add_shortcode('woocommerce_quickbuy', array($this, 'woocommerce_button_quick_buy'));
                    add_action('woocommerce_single_product_summary', array($this, 'add_button_quick_buy'), 35);
                    add_action('woocommerce_after_single_product', array($this, 'quick_buy_popup_content_single') );

                    add_action( 'wp_ajax_woocommerce_quickbuy', array($this,'woocommerce_quickbuy_func') );
                    add_action( 'wp_ajax_nopriv_woocommerce_quickbuy', array($this,'woocommerce_quickbuy_func') );

                    add_action( 'wp_ajax_woocommerce_form_quickbuy', array($this,'woocommerce_form_quickbuy_func') );
                    add_action( 'wp_ajax_nopriv_woocommerce_form_quickbuy', array($this,'woocommerce_form_quickbuy_func') );

                    add_action( 'wp_ajax_quickbuy_load_administrativeboundaries', array($this, 'load_administrativeboundaries_func') );
                    add_action( 'wp_ajax_nopriv_quickbuy_load_administrativeboundaries', array($this, 'load_administrativeboundaries_func') );

                    add_action('woocommerce_prod_variable','woocommerce_template_single_add_to_cart');
                    if($quickbuy_settings['in_loop_prod']) {
                        add_action('woocommerce_after_shop_loop_item', array($this, 'add_quick_buy_to_loop_func'), 15);
                    }
                }
            }

            public function define_constants()
            {
                if (!defined('QUICKBUY_VERSION_NUM'))
                    define('QUICKBUY_VERSION_NUM', $this->_version);
                if (!defined('QUICKBUY_URL'))
                    define('QUICKBUY_URL', plugin_dir_url(__FILE__));
                if (!defined('QUICKBUY_BASENAME'))
                    define('QUICKBUY_BASENAME', plugin_basename(__FILE__));
                if (!defined('QUICKBUY_PLUGIN_DIR'))
                    define('QUICKBUY_PLUGIN_DIR', plugin_dir_path(__FILE__));
            }

            function dvls_load_textdomain() {
                load_textdomain('woocommerce-quickbuy-popup', dirname(__FILE__) . '/languages/woocommerce-quickbuy-popup-' . get_locale() . '.mo');
            }

            function woocommerce_button_quick_buy($atts)
            {
                $atts = shortcode_atts( array(
                    'id' => '',
                    'view'  =>  true,
                    'button_text1'  =>  '',
                    'button_text2'  =>  '',
                    'small_link'    =>  false
                ), $atts, 'woocommerce_quickbuy' );

                $id = $atts['id'];
                $view = (bool) $atts['view'];
                $button_text1 = $atts['button_text1'];
                $button_text2 = $atts['button_text2'];
                $small_link = (bool) $atts['small_link'];
                global $quickbuy_settings;
                if(!$id) {
                    global $product;
                    $this_product = $product;
                }else {
                    $this_product = wc_get_product($id);
                }
                if($this_product) {
                    ob_start();
                    if (!is_admin() && $this_product->is_in_stock()):
                        if(!$small_link):
                        ?>
                        <a href="javascript:void(0);" class="woocommerce_buy_now woocommerce_buy_now_style" data-id="<?php echo $this_product->get_id(); ?>">
                            <strong><?php echo ($button_text1) ? $button_text1 : $quickbuy_settings['button_text1']; ?></strong>
                            <span><?php echo ($button_text2) ? $button_text2 : $quickbuy_settings['button_text2']; ?></span>
                        </a>
                        <?php
                        else:
                        ?>
                        <a href="javascript:void(0);" class="woocommerce_buy_now" data-id="<?php echo $this_product->get_id();?>"><?php echo ($button_text1) ? $button_text1 : $quickbuy_settings['button_text1']; ?></a>
                        <?php
                        endif;
                        if ($view) {
                            $this->quick_buy_popup_content($atts, false);
                        }
                    endif;
                    return ob_get_clean();
                }
            }

            function woocommerce_form_quickbuy_func(){
                $prodID = isset($_POST['prodid']) ? intval($_POST['prodid']) : '';
                if($prodID){
                    $args = array(
                        'id' => $prodID,
                    );
                    $this->form_popup_content($args);
                }
                die();
            }

            function form_popup_content($args = array())
            {
                global $quickbuy_settings, $product;
                if($args){
                    $prodID = isset($args['id']) ? intval($args['id']) : '';
                    if($prodID){
                        $thisProduct = wc_get_product($prodID);
                        if($thisProduct){
                            $require_email = $quickbuy_settings['require_email'];
                            ?>
                            <div class="woocommerce-popup-content-left <?php echo ($quickbuy_settings['popup_infor_enable']) ? '' : 'popup_quickbuy_hidden_mobile';?>">
                                <div class="woocommerce-popup-prod">
                                    <?php
                                    $thumbArgs = wp_get_attachment_image_src(get_post_thumbnail_id($thisProduct->get_id()), 'shop_thumbnail');
                                    if($thumbArgs):?>
                                        <div class="woocommerce-popup-img"><img src="<?php echo $thumbArgs[0]?>" alt=""/></div>
                                    <?php endif;?>
                                    <div class="woocommerce-popup-info">
                                        <span class="woocommerce_title"><?php echo $thisProduct->get_title();?></span>
                                        <?php if($thisProduct->get_type() == 'simple'):?><span class="woocommerce_price"><?php echo $thisProduct->get_price_html(); ?></span><?php endif;?>
                                    </div>
                                </div>
                                <div class="woocommerce_prod_variable" data-simpleprice="<?php echo $thisProduct->get_price();?>">
                                    <?php
                                    $product = $thisProduct;
                                    do_action('woocommerce_prod_variable');
                                    ?>
                                    <script>jQuery(document).ready(function(){setInterval(function(){jQuery(".woocommerce_prod_variable table").remove(".variations")},500)});</script>
                                </div>
                                <?php echo $quickbuy_settings['popup_mess'];?>
                            </div>

                            <div class="woocommerce-popup-content-right">
                                <form class="woocommerce_cusstom_info" id="woocommerce_cusstom_info" method="post">
                                    <div class="popup-customer-info">
                                        <div class="popup-customer-info-title"><?php _e('Buy information','woocommerce-quickbuy-popup')?></div>
                                        <?php do_action('before_field_woocommerce_quickbuy');?>
                                        <div class="popup-customer-info-group popup-customer-info-radio">
                                            <label>
                                                <input type="radio" name="customer-gender" value="1" checked/>
                                                <span><?php _e('Mr','woocommerce-quickbuy-popup');?></span>
                                            </label>
                                            <label>
                                                <input type="radio" name="customer-gender" value="2"/>
                                                <span><?php _e('Mrs','woocommerce-quickbuy-popup');?></span>
                                            </label>
                                             <label>
                                                <input type="radio" name="customer-gender" value="2"/>
                                                <span><?php _e('Other','woocommerce-quickbuy-popup');?></span>
                                            </label>
                                        </div>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-2">
                                                <input type="text" class="customer-name" name="customer-name" placeholder="<?php _e('Full name','woocommerce-quickbuy-popup');?>">
                                            </div>
                                            <div class="popup-customer-info-item-2">
                                                <input type="text" class="customer-phone" name="customer-phone" placeholder="<?php _e('Phone number','woocommerce-quickbuy-popup');?>">
                                            </div>
                                        </div>
                                        <?php if(!$quickbuy_settings['hidden_email']):?>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-1">
                                                <?php if($require_email):?>
                                                    <input type="text" class="customer-email" name="customer-email" data-required="true" required placeholder="<?php _e('Email','woocommerce-quickbuy-popup');?>">
                                                <?php else:?>
                                                    <input type="text" class="customer-email" name="customer-email" data-required="false" placeholder="<?php _e('Email (Not required)','woocommerce-quickbuy-popup');?>">
                                                <?php endif;?>
                                            </div>
                                        </div>
                                        <?php endif;?>
                                        <?php
                                        $countries = new WC_Countries;
                                        $vn_states = $countries->get_states('VN');
                                        if($vn_states && is_array($vn_states) && $quickbuy_settings['enable_location']):
                                            ?>
                                            <?php if(!$this->check_plugin_active()):?>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-1">
                                                    <select class="customer-location" name="customer-location" id="woocommerce_city">
                                                        <?php foreach($vn_states as $k=>$v):?>
                                                            <option value="<?php echo $k;?>"><?php echo $v;?></option>
                                                        <?php endforeach;?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-1">
                                                    <textarea class="customer-address" name="customer-address" placeholder="<?php _e('Address','woocommerce-quickbuy-popup');?> <?php echo ($quickbuy_settings['require_address'] == 0)?__('Not required','woocommerce-quickbuy-popup'):'';?>"></textarea>
                                                </div>
                                            </div>
                                        <?php else:?>
                                            <?php
                                            $default_state = wc_get_base_location();
                                            ?>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-3-13">
                                                    <select class="customer-location" name="customer-location" id="woocommerce_city">
                                                        <option value=""><?php _e('City','woocommerce-quickbuy-popup')?></option>
                                                        <?php foreach($vn_states as $k=>$v):?>
                                                            <option value="<?php echo $k;?>" <?php selected($k,$default_state['state'],true);?>><?php echo $v;?></option>
                                                        <?php endforeach;?>
                                                    </select>
                                                </div>
                                                <div class="popup-customer-info-item-3-23">
                                                    <select class="customer-quan" name="customer-quan" id="woocommerce_district">
                                                        <option value=""><?php _e('District','woocommerce-quickbuy-popup')?></option>
                                                    </select>
                                                    <input name="require_district" id="require_district" type="hidden" value="<?php echo $quickbuy_settings['require_district'];?>"/>
                                                </div>
                                                <div class="popup-customer-info-item-3-33">
                                                    <select class="customer-xa" name="customer-xa" id="woocommerce_ward">
                                                        <option value=""><?php _e('Ward','woocommerce-quickbuy-popup');?></option>
                                                    </select>
                                                    <input name="require_village" id="require_village" type="hidden" value="<?php echo $quickbuy_settings['require_village'];?>"/>
                                                </div>
                                            </div>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-1">
                                                    <input type="text" class="customer-address" name="customer-address" placeholder="<?php _e('Street','woocommerce-quickbuy-popup');?> <?php echo ($quickbuy_settings['require_address'] == 0)?__('Not required','woocommerce-quickbuy-popup'):'';?>"/>
                                                </div>
                                            </div>
                                        <?php endif;?>
                                        <?php else:?>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-1">
                                                    <textarea class="customer-address" name="customer-address" placeholder="<?php _e('Address','woocommerce-quickbuy-popup');?> <?php echo ($quickbuy_settings['require_address'] == 0)?__('Not required','woocommerce-quickbuy-popup'):'';?>"></textarea>
                                                </div>
                                            </div>
                                        <?php endif;?>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-1">
                                                <textarea class="order-note" name="order-note" placeholder="<?php _e('Note (Not required)','woocommerce-quickbuy-popup');?>"></textarea>
                                            </div>
                                        </div>
                                        <?php if($quickbuy_settings['enable_ship'] && $quickbuy_settings['enable_location'] && $vn_states && is_array($vn_states)):?>
                                            <div class="popup-customer-info-group">
                                                <div class="popup-customer-info-item-1 popup_quickbuy_shipping">
                                                    <div class="popup_quickbuy_shipping_title"><?php _e('Shipping:','woocommerce-quickbuy-popup');?></div>
                                                    <div class="popup_quickbuy_shipping_calc"></div>
                                                </div>
                                            </div>
                                        <?php endif;?>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-1 popup_quickbuy_shipping">
                                                <div class="popup_quickbuy_shipping_title"><?php _e('Total:','woocommerce-quickbuy-popup');?></div>
                                                <div class="popup_quickbuy_total_calc"></div>
                                            </div>
                                        </div>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-1">
                                                <button type="button" class="woocommerce-order-btn"><?php _e('Quick Buy','woocommerce-quickbuy-popup');?></button>
                                            </div>
                                        </div>
                                        <div class="popup-customer-info-group">
                                            <div class="popup-customer-info-item-1">
                                                <div class="woocommerce_quickbuy_mess"></div>
                                            </div>
                                        </div>
                                        <?php do_action('after_field_woocommerce_quickbuy');?>
                                    </div>
                                    <input type="hidden" name="prod_id" id="prod_id" value="<?php echo $thisProduct->get_id();?>">
                                    <input type="hidden" name="prod_nonce" id="prod_nonce" value="">
                                    <input type="hidden" name="enable_ship" id="enable_ship" value="<?php echo $quickbuy_settings['enable_ship'];?>">
                                    <input name="require_address" id="require_address" type="hidden" value="<?php echo $quickbuy_settings['require_address'];?>"/>
                                </form>
                            </div>
                            <?php
                        }
                    }
                }

            }

            function quick_buy_popup_content_single(){
                global $product;
                $args = array(
                    'id' => $product->get_id()
                );
                $this->quick_buy_popup_content($args, true);
            }

            function quick_buy_popup_content($args = array(), $view = true)
            {
                global $quickbuy_settings;
                if($args){
                    $prodID = isset($args['id']) ? intval($args['id']) : '';
                    if($prodID){
                        $thisProduct = wc_get_product($prodID);
                        if($thisProduct){
                            ?>
                            <div class="woocommerce-popup-quickbuy" data-popup="popup-quickbuy" id="popup_content_<?php echo $thisProduct->get_id();?>">
                                <div class="woocommerce-popup-inner">
                                    <div class="woocommerce-popup-title">
                                        <span><?php printf($quickbuy_settings['popup_title'], $thisProduct->get_title());?></span>
                                        <button type="button" class="woocommerce-popup-close"></button>
                                    </div>
                                    <div class="woocommerce-popup-content woocommerce-popup-content_<?php echo $thisProduct->get_id();?> <?php echo (!$view) ? 'ghn_not_loaded' : '';?>">
                                        <?php if($view):?>
                                            <?php $this->form_popup_content($args);?>
                                        <?php endif;?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
            }

            function check_plugin_active($base_plugin = 'woocommerce-woo-address-selectbox/woocommerce-woo-address-selectbox.php'){
                if (
                    in_array( $base_plugin, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
                    in_array( 'woo-vietnam-checkout/woocommerce-woo-address-selectbox.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
                    in_array( 'woocommerce-woo-ghtk/woocommerce-woo-ghtk.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
                    in_array( 'woocommerce-vietnam-shipping/woocommerce-vietnam-shipping.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )
                ) {
                    return true;
                }
                return false;
            }

            function add_button_quick_buy(){
                global $product;
                echo do_shortcode('[woocommerce_quickbuy view="0" id="'.$product->get_id().'"]');
            }

            function add_quick_buy_to_loop_func(){
                global $product;
                echo do_shortcode('[woocommerce_quickbuy small_link="1" id="'.$product->get_id().'"]');
            }

            function woocommerce_get_rates($package = array(), $product_info = array()){
                $available_methods = $old_cart_key = array();
                $package = wp_parse_args($package, array(
                    'country'   =>  'VN',
                    'state'     =>  '01',
                    'city'      =>  '',
                    'postcode'  =>  '',
                ));
                $old_cart = WC()->cart->get_cart_contents();
                if($old_cart && !empty($old_cart)){
                    foreach($old_cart as $k=>$cartItem){
                        $old_cart_key[] = $k;
                        WC()->cart->remove_cart_item($k);
                    }
                }
                if($product_info && is_array($product_info) && !empty($product_info)){
                    $product_id = isset($product_info['product_id']) ? intval($product_info['product_id']) : '';
                    if(!$product_id) {
                        $product_id = isset($product_info['add-to-cart']) ? intval($product_info['add-to-cart']) : '';
                    }
                    $quantity = isset($product_info['quantity']) ? intval($product_info['quantity']) : 0;
                    $variation_id = isset($product_info['variation_id']) ? intval($product_info['variation_id']) : '';
                    if ($product_id) {
                        if ($variation_id && $variation_id != "" && $variation_id > 0) {
                            $variation = array();
                            foreach($product_info as $k=>$v){
                                if( strpos($k, 'attribute_') !== false ){
                                    $variation[$k] = $product_info[$k];
                                }
                            }
                            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
                        } else {
                            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
                        }
                        WC()->customer->set_billing_location($package['country'],$package['state'],$package['postcode'],$package['city']);
                        WC()->customer->set_shipping_location($package['country'],$package['state'],$package['postcode'],$package['city']);
                        $packages = WC()->cart->get_shipping_packages();
                        $packages = WC()->shipping->calculate_shipping($packages);
                        $available_methods = WC()->shipping->get_packages();
                        $available_methods = isset($available_methods[0]['rates']) ? $available_methods[0]['rates'] : array();

                        WC()->cart->remove_cart_item($cart_item_key);
                    }
                    if($old_cart && !empty($old_cart)){
                        foreach($old_cart as $k=>$cartItem){
                            $product_id = isset($cartItem['product_id']) ? intval($cartItem['product_id']) : '';
                            $variation_id = isset($cartItem['variation_id']) ? intval($cartItem['variation_id']) : '';
                            $variation = isset($cartItem['variation']) ? wc_clean($cartItem['variation']) : array();
                            $quantity = isset($cartItem['quantity']) ? intval($cartItem['quantity']) : '';
                            if($product_id) {
                                if ($variation_id > 0) {
                                    WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
                                } else {
                                    WC()->cart->add_to_cart($product_id, $quantity);
                                }
                            }
                        }
                    }
                }
                wc_clear_notices();
                return $available_methods;
            }

            function check_product_incart($product_info = array())
            {
                $product_id = isset($product_info['product_id']) ? intval($product_info['product_id']) : '';
                $variation_id = isset($product_info['variation_id']) ? intval($product_info['variation_id']) : '';
                foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
                    $_product_id = isset($values['product_id']) ? $values['product_id'] : '';
                    $_variation_id = isset($values['variation_id']) ? $values['variation_id'] : '';
                    if (($product_id == $_product_id && $_variation_id == '') || ($product_id == $_product_id && $_variation_id && $variation_id == $_variation_id)) {
                        return $cart_item_key;
                    }
                }
                return false;
            }

            function get_productkey_incart($product_id)
            {
                foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
                    $_product = $values['data'];
                    if ($product_id == $_product->id) {
                        return true;
                    }
                }
                return false;
            }

            function woocommerce_calculate_shipping($package = array(), $product_info = array()){
                $available_methods = $this->woocommerce_get_rates($package, $product_info);
                //print_r($available_methods);
                ob_start();
                ?>
                <?php if ( 1 < count( $available_methods ) ) : ?>
                    <ul id="shipping_method">
                        <?php $stt = 1; foreach ( $available_methods as $method ) :?>
                            <li>
                                <?php
                                printf( '<input type="radio" name="shipping_method[%1$d]" data-cost="%6$d" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />
                                <label for="shipping_method_%1$d_%2$s">%5$s</label>',
                                    0,
                                    sanitize_title( $method->id ),
                                    esc_attr( $method->id ),
                                    ($stt == 1) ? 'checked' : '',
                                    wc_cart_totals_shipping_method_label( $method ),
                                    sanitize_text_field($method->cost));

                                do_action( 'woocommerce_after_shipping_rate', $method, 0 );
                                ?>
                            </li>
                            <?php $stt++; endforeach; ?>
                    </ul>
                <?php elseif ( 1 === count( $available_methods ) ) :  ?>
                    <?php
                    $method = current( $available_methods );
                    printf( '%3$s <input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" data-cost="%4$d" id="shipping_method_%1$d" value="%2$s" class="shipping_method" checked/>', 0, esc_attr( $method->id ), wc_cart_totals_shipping_method_label( $method ),sanitize_text_field($method->cost) );
                    do_action( 'woocommerce_after_shipping_rate', $method, 0 );
                    ?>
                <?php endif;?>
                <?php
                return ob_get_clean();
            }

            function load_administrativeboundaries_func() {
                global $quickbuy_settings;
                if(
                    !class_exists('Woo_Address_Selectbox_Class') &&
                    !class_exists('Quick_Buy_Popup_Checkout_Class') &&
                    !class_exists('Quick_Buy_GHTK_Class')
                ) wp_send_json_error();
                if(class_exists('Woo_Address_Selectbox_Class')){
                    $address = new Woo_Address_Selectbox_Class;
                }elseif(class_exists('Quick_Buy_Popup_Checkout_Class')){
                    $address = new Quick_Buy_Popup_Checkout_Class;
                }else{
                    $address = new Quick_Buy_GHTK_Class;
                }
                $matp = isset($_POST['matp']) ? sanitize_text_field($_POST['matp']) : '';
                $maqh = isset($_POST['maqh']) ? intval($_POST['maqh']) : '';
                $getvalue = isset($_POST['getvalue']) ? intval($_POST['getvalue']) : 1;
                $result['shipping'] = '';
                if($quickbuy_settings['enable_ship']) {
                    $package['country'] = 'USA';
                    $package['state'] = sanitize_text_field($matp);
                    $package['city'] = sprintf("%03d", $maqh);
                    parse_str(wc_clean($_POST['product_info']), $product_info);
                    if(!isset($product_info['add-to-cart']) && isset($_POST['prod_id'])){
                        $prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : '';
                        $product_info['add-to-cart'] = $prod_id;
                    }
                    $result['shipping'] = $this->woocommerce_calculate_shipping($package, $product_info);
                }
                if($getvalue == 1 && $matp){
                    $result['list_district'] = $address->get_list_district($matp);
                    wp_send_json_success($result);
                }elseif($getvalue == 2 && $maqh){
                    $result['list_district'] = $address->get_list_village($maqh);
                    wp_send_json_success($result);
                }
                wp_send_json_error();
                die();
            }

            function woocommerce_quickbuy_func(){
                global $quickbuy_settings;
                $prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : '';
                $prod_check = wc_get_product($prod_id);

                if(!$prod_check || is_wp_error($prod_check)) wp_send_json_error();

                parse_str($_POST['customer_info'], $customer_info);
                parse_str($_POST['product_info'], $product_info);

                $qty = isset($product_info['quantity']) ? (float) $product_info['quantity'] : 1;
                $variation_id = isset($product_info['variation_id']) ? (float) $product_info['variation_id'] : '';
                $product_id = isset($product_info['product_id']) ? (int) $product_info['product_id'] : '';
                if(!$product_id) {
                    if(!isset($product_info['add-to-cart']) && $prod_id){
                        $product_info['add-to-cart'] = $prod_id;
                    }
                    $product_id = isset($product_info['add-to-cart']) ? (int)$product_info['add-to-cart'] : '';
                }

                $customer_gender = (isset($customer_info['customer-gender']) && $customer_info['customer-gender'] == 1)  ? 'Mr' : 'Mrs';
                $customer_name = isset($customer_info['customer-name']) ? sanitize_text_field($customer_info['customer-name']): '';
                $customer_email = isset($customer_info['customer-email']) ? sanitize_email($customer_info['customer-email']): '';
                $customer_phone = isset($customer_info['customer-phone']) ? sanitize_text_field($customer_info['customer-phone']): '';
                $customer_address = isset($customer_info['customer-address']) ? sanitize_textarea_field($customer_info['customer-address']): '';
                $customer_location = isset($customer_info['customer-location']) ? sanitize_text_field($customer_info['customer-location']): '';
                $customer_note = isset($customer_info['order-note']) ? sanitize_textarea_field($customer_info['order-note']): '';
                $customer_quan = isset($customer_info['customer-quan']) ? sanitize_text_field($customer_info['customer-quan']): '';
                $customer_xa = isset($customer_info['customer-xa']) ? sanitize_text_field($customer_info['customer-xa']): '';
                $shipping_method = isset($customer_info['shipping_method']) ? $customer_info['shipping_method']: '';

                $address = array(
                    'first_name' => $customer_gender,
                    'last_name'  => $customer_name,
                    'email'      => $customer_email,
                    'phone'      => $customer_phone,
                    'address_1'  => $customer_address,
                    'state'      => $customer_location,
                    'city'       => $customer_quan,
                    'address_2'  => $customer_xa,
                    'country'    => 'VN'
                );

                // Now we create the order
                $order = wc_create_order();

                if(!is_wp_error($order)) {
                    $args = $variation = array();
                    if($variation_id && is_array($product_info)){
                        foreach($product_info as $k=>$v){
                            if( strpos($k, 'attribute_') !== false ){
                                $variation[$k] = $product_info[$k];
                            }
                        }
                        if($variation){
                            $args = array(
                                'variation_id'  => $variation_id,
                                'variation' =>  $variation,
                                'product_id'    =>  $product_id
                            );
                        }
                        $prod_check = wc_get_product($variation_id);
                    }
                    $order->add_product($prod_check, $qty, $args);
                    $order->set_address($address, 'billing');
                    $order->set_address($address, 'shipping');
                    if($customer_note) {
                        $order->set_customer_note($customer_note);
                    }

                    if($quickbuy_settings['enable_ship'] && $shipping_method) {
                        $item = new WC_Order_Item_Shipping();

                        $package['country'] = 'VN';
                        $package['state'] = sanitize_text_field($customer_location);
                        if($customer_quan) {
                            $package['city'] = sprintf("%03d", $customer_quan);
                        }

                        $available_methods = $this->woocommerce_get_rates($package, $product_info);

                        $shipping_rate = isset($available_methods[$shipping_method[0]]) ? $available_methods[$shipping_method[0]] : array();
                        if($shipping_rate) {
                            $item->set_props(array(
                                'method_title' => $shipping_rate->label,
                                'method_id' => $shipping_rate->id,
                                'total' => wc_format_decimal($shipping_rate->cost),
                                'taxes' => $shipping_rate->taxes,
                                'order_id' => $order->get_id(),
                            ));
                            foreach ($shipping_rate->get_meta_data() as $key => $value) {
                                $item->add_meta_data($key, $value, true);
                            }
                            $item->save();
                            $order->add_item($item);
                        }
                    }

                    $order->calculate_totals();

                    $order->update_status("processing", 'Quick Row', TRUE);

                    if($this->check_woo_version()) {
                        wc_reduce_stock_levels($order->get_id());
                    }

                    do_action( 'woocommerce_checkout_order_processed', $order->get_id(), array(), $order );

                    $result['content'] = str_replace('%%order_id%%', $order->get_order_number(), $quickbuy_settings['popup_sucess']);
                    $result['gotothankyou'] = ($quickbuy_settings['popup_gotothankyou'] == 1 )? true : false;
                    $result['thankyou_link'] = $order->get_checkout_order_received_url();;

                    wp_send_json_success($result);
                }
                wp_send_json_error();
                die();
            }

            public function check_woo_version($version = '3.0.0'){
                if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, $version, '>=' ) ) {
                    return true;
                }
                return false;
            }

            public function add_action_links($links, $file)
            {
                if (strpos($file, 'woocommerce-quick-buy-popup.php') !== false) {
                    $settings_link = '<a href="' . admin_url('options-general.php?page=quickkbuy-setting') . '" title="' . __('Settings', 'woocommerce-quickbuy-popup') . '">' . __('Settings', 'woocommerce-quickbuy-popup') . '</a>';
                    array_unshift($links, $settings_link);
                }
                return $links;
            }

            function load_plugins_scripts()
            {
                global $quickbuy_settings;
                wp_enqueue_style('woocommerce-quickbuy-popup-style', plugins_url('css/woocommerce-quick-buy-popup.css', __FILE__), array(), $this->_version, 'all');
                wp_enqueue_script('jquery.validate', plugins_url('js/jquery.validate.min.js', __FILE__), array('jquery'), $this->_version, true);
                wp_enqueue_script('bpopup', plugins_url('js/jquery.bpopup.min.js', __FILE__), array('jquery'), $this->_version, true);
                wp_enqueue_script('woocommerce-quickbuy-popup-script', plugins_url('js/woocommerce-quick-buy-popup.js', __FILE__), array('jquery','bpopup', 'wc-add-to-cart-variation'), $this->_version, true);
                $array = array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'siteurl' => home_url(),
                    'popup_error'   =>  $quickbuy_settings['popup_error'],
                    'out_of_stock_mess'   =>  $quickbuy_settings['out_of_stock_mess'],
                    'price_decimal'   =>  wc_get_price_decimal_separator(),
                    'num_decimals'   =>  wc_get_price_decimals(),
                    'currency_format'   =>  get_woocommerce_currency_symbol(),
                    'qty_text'  =>  __('Quantity', 'woocommerce-quickbuy-popup'),
                    'name_text'  =>  __('Full name is required', 'woocommerce-quickbuy-popup'),
                    'phone_text'  =>  __('Phone number is required', 'woocommerce-quickbuy-popup'),
                    'email_text'  =>  __('Email is required', 'woocommerce-quickbuy-popup'),
                    'quan_text'  =>  __('District is required', 'woocommerce-quickbuy-popup'),
                    'xa_text'  =>  __('Ward is required', 'woocommerce-quickbuy-popup'),
                    'address_text'  =>  __('Stress is required', 'woocommerce-quickbuy-popup')
                );
                wp_localize_script('woocommerce-quickbuy-popup-script', 'woocommerce_quickbuy_array', $array);
            }

            public function admin_enqueue_scripts()
            {
                $current_screen = get_current_screen();
                if (isset($current_screen->base) && $current_screen->base == 'settings_page_quickkbuy-setting') {
                    wp_enqueue_style('woocommerce-quickbuy-popup-admin-styles', plugins_url('/css/admin-style.css', __FILE__), array(), $this->_version, 'all');
                    wp_enqueue_script('woocommerce-quickbuy-popup-admin-js', plugins_url('/js/admin-jquery.js', __FILE__), array('jquery'), $this->_version, true);
                }
            }

            function get_dvlsoptions()
            {
                return wp_parse_args(get_option($this->_optionName), $this->_defaultOptions);
            }

            function admin_menu()
            {
                add_options_page(
                    __('Quick Buy Setting', 'woocommerce-quickbuy-popup'),
                    __('Quick Buy Setting', 'woocommerce-quickbuy-popup'),
                    'manage_options',
                    'quickkbuy-setting',
                    array(
                        $this,
                        'woocommerce_settings_page'
                    )
                );
            }

            function dvls_register_mysettings()
            {
                register_setting($this->_optionGroup, $this->_optionName);
            }

            function admin_notices(){
                global $quickbuy_settings;
                $class = 'notice notice-error';
                $license_key = $quickbuy_settings['license_key'];
                if(!$license_key) {
                    printf('<div class="%1$s"><p><strong>Quick Buy Plugin:</strong> Please fill out <strong>License Key</strong> to automatically update when a new version is available. <a href="%2$s">Add here</a></p></div>', esc_attr($class), esc_url(admin_url('options-general.php?page=quickkbuy-setting')));
                }
            }

            function woocommerce_modify_plugin_update_message( $plugin_data, $response ) {
                global $quickbuy_settings;
                $license_key = sanitize_text_field($quickbuy_settings['license_key']);
                if( $license_key && isset($plugin_data['package']) && $plugin_data['package']) return;
                $PluginURI = isset($plugin_data['PluginURI']) ? $plugin_data['PluginURI'] : '';
                echo '<br />' . sprintf( __('<strong>Purchase the license to be automatically updated. <a href="%s" target="_blank">More information buy copyright</a></strong> or contact directly through <a href="%s" target="_blank">facebook</a>', 'woocommerce-quickbuy-popup'), $PluginURI, 'https://xsthemes.com/');
            }

            function woocommerce_settings_page()
            {
                global $quickbuy_settings;
                ?>
                <div class="wrap woocommerce_quickbuy">
                    <h1><?php _e('Quick Buy Setting', 'woocommerce-quickbuy-popup');?></h1>
                    <form method="post" action="options.php" novalidate="novalidate">
                        <?php settings_fields($this->_optionGroup); ?>
                        <h2><?php _e('General settings','woocommerce-quickbuy-popup');?></h2>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="enable"><?php _e('Enable','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label>
                                        <input type="radio" id="enable" value="1" <?php checked(1,$quickbuy_settings['enable']);?> name="<?php echo $this->_optionName . '[enable]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?>
                                    </label>
                                    <label>
                                        <input type="radio" id="enable" value="0" <?php checked(0,$quickbuy_settings['enable']);?> name="<?php echo $this->_optionName . '[enable]'?>"> <?php _e('Deactive','woocommerce-quickbuy-popup');?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="enable_location"><?php _e('Select location','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="enable_location" value="1" <?php checked(1,$quickbuy_settings['enable_location']);?> name="<?php echo $this->_optionName . '[enable_location]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?><br>
                                        <small><?php _e('Requires have state','woocommerce-quickbuy-popup');?></small>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="enable_ship"><?php _e('Shipping','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="enable_ship" value="1" <?php checked(1,$quickbuy_settings['enable_ship']);?> name="<?php echo $this->_optionName . '[enable_ship]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="hidden_email"><?php _e('Hidden Email','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="hidden_email" value="1" <?php checked(1,$quickbuy_settings['hidden_email']);?> name="<?php echo $this->_optionName . '[hidden_email]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="require_email"><?php _e('Require Email','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="require_email" value="1" <?php checked(1,$quickbuy_settings['require_email']);?> name="<?php echo $this->_optionName . '[require_email]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            <?php if($this->check_plugin_active()):?>
                                <tr>
                                    <th scope="row"><label for="require_district"><?php _e('Require District','woocommerce-quickbuy-popup');?></label></th>
                                    <td>
                                        <label><input type="checkbox" id="require_district" value="1" <?php checked(1,$quickbuy_settings['require_district']);?> name="<?php echo $this->_optionName . '[require_district]'?>"> <?php _e('Require','woocommerce-quickbuy-popup');?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="require_village"><?php _e('Require Village','woocommerce-quickbuy-popup');?></label></th>
                                    <td>
                                        <label><input type="checkbox" id="require_village" value="1" <?php checked(1,$quickbuy_settings['require_village']);?> name="<?php echo $this->_optionName . '[require_village]'?>"> <?php _e('Require','woocommerce-quickbuy-popup');?></label>
                                    </td>
                                </tr>
                            <?php endif;?>
                            <tr>
                                <th scope="row"><label for="require_address"><?php _e('Require Address box','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="require_address" value="1" <?php checked(1,$quickbuy_settings['require_address']);?> name="<?php echo $this->_optionName . '[require_address]'?>"> <?php _e('Require','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <h2><?php _e('Button quick buy','woocommerce-quickbuy-popup');?></h2>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="button_text1"><?php _e('Button text','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <input type="text" id="button_text1" value="<?php echo esc_attr($quickbuy_settings['button_text1']);?>" name="<?php echo $this->_optionName . '[button_text1]'?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_text2"><?php _e('Button sub text','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <input type="text" id="button_text1" value="<?php echo esc_attr($quickbuy_settings['button_text2']);?>" name="<?php echo $this->_optionName . '[button_text2]'?>">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <h2><?php _e('Popup setting','woocommerce-quickbuy-popup');?></h2>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="popup_infor_enable"><?php _e('View product info on mobile','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="popup_infor_enable" value="1" <?php checked(1,$quickbuy_settings['popup_infor_enable']);?> name="<?php echo $this->_optionName . '[popup_infor_enable]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="popup_title"><?php _e('Popup title','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <input type="text" id="popup_title" value="<?php echo esc_attr($quickbuy_settings['popup_title']);?>" name="<?php echo $this->_optionName . '[popup_title]'?>"/>
                                    <br><small><?php _e('%s to view product title','woocommerce-quickbuy-popup');?></small>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="popup_mess"><?php _e('Popup mess','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <?php
                                    $settings = array(
                                        'textarea_name' => $this->_optionName.'[popup_mess]',
                                        'textarea_rows' => 5,
                                    );
                                    wp_editor( $quickbuy_settings['popup_mess'], 'popup_mess', $settings );?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="popup_gotothankyou"><?php _e('Go to thank you page','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <label><input type="checkbox" id="popup_gotothankyou" value="1" <?php checked(1,$quickbuy_settings['popup_gotothankyou']);?> name="<?php echo $this->_optionName . '[popup_gotothankyou]'?>"> <?php _e('Active','woocommerce-quickbuy-popup');?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="popup_sucess"><?php _e('Checkout successfully mess','woocommerce-quickbuy-popup');?></label><br>
                                    <small><?php _e('Type %%order_id%% to view Order ID','woocommerce-quickbuy-popup')?></small></th>
                                <td>
                                    <?php
                                    $settings = array(
                                        'textarea_name' => $this->_optionName.'[popup_sucess]',
                                        'textarea_rows' => 15,
                                    );
                                    wp_editor( $quickbuy_settings['popup_sucess'], 'popup_sucess', $settings );?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="popup_error"><?php _e('Checkout error message','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <input type="text" id="popup_error" value="<?php echo esc_attr($quickbuy_settings['popup_error']);?>" name="<?php echo $this->_optionName . '[popup_error]'?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="out_of_stock_mess"><?php _e('Out of stock message','woocommerce-quickbuy-popup');?></label></th>
                                <td>
                                    <input type="text" id="out_of_stock_mess" value="<?php echo esc_attr($quickbuy_settings['out_of_stock_mess']);?>" name="<?php echo $this->_optionName . '[out_of_stock_mess]'?>">
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <?php do_settings_fields('quickbuy-options-group', 'default'); ?>
                        <?php do_settings_sections('quickbuy-options-group', 'default'); ?>
                        <?php submit_button(); ?>
                    </form>
                </div>
                <?php
            }

        }

        new Woocommerce_Quick_Buy_Popup();
    }
}