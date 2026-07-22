<?php
/**
 * Plugin Name: Discount Architect
 * Plugin URI:  https://yourdomain.com/discount-architect
 * Description: Master your store's pricing with precision. Apply dynamic percentage or flat-rate discounts globally, by category, or per product with ease.
 * Version:     1.0.0
 * Author:      Aniruddh Vishwakarma
 * Author URI:  https://aniruddh-port.netlify.app/
 * Text Domain: discount-architect
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 8.0.0
 *
 * @package Discount_Architect
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main Discount Architect Class
 */
final class Discount_Architect
{

    /**
     * Plugin Version
     */
    const VERSION = '1.0.0';

    /**
     * Instance of this class.
     *
     * @var Discount_Architect
     */
    private static $instance = null;

    /**
     * Get the instance of this class.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define constants
     */
    private function define_constants()
    {
        define('DISCOUNT_ARCHITECT_PATH', plugin_dir_path(__FILE__));
        define('DISCOUNT_ARCHITECT_URL', plugin_dir_url(__FILE__));
        define('DISCOUNT_ARCHITECT_VERSION', self::VERSION);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_rule_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX Search
        add_action('wp_ajax_da_search_targets', array($this, 'ajax_search_targets'));

        // Frontend hooks for applying discounts
        add_filter('woocommerce_product_get_price', array($this, 'apply_custom_discount'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'apply_custom_discount'), 10, 2);
        add_filter('woocommerce_get_price_html', array($this, 'format_price_display'), 10, 2);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Cart price display (MRP + Discounted)
        add_filter('woocommerce_cart_item_subtotal', array($this, 'format_cart_price_display'), 9999, 3);
        add_filter('woocommerce_cart_item_name', array($this, 'add_discount_label_to_cart_name'), 9999, 3);
        add_filter('woocommerce_get_item_data', array($this, 'add_discount_to_item_data'), 9999, 2);
        add_filter('woocommerce_order_item_get_formatted_subtotal', array($this, 'format_order_item_display'), 9999, 3);

        // Display discount labels on images (using multiple filters for maximum compatibility)
        // add_filter( 'woocommerce_product_get_image', array( $this, 'add_badge_to_image' ), 10, 2 );
        // add_filter( 'post_thumbnail_html', array( $this, 'add_badge_to_post_thumbnail' ), 10, 5 );
        // add_action( 'woocommerce_before_single_product_summary', array( $this, 'display_discount_badge' ), 10 );

        // Declare compatibility with WooCommerce features (HPOS)
        add_action('before_woocommerce_init', array($this, 'declare_wc_compatibility'));
    }

    /**
     * Declare WooCommerce compatibility
     */
    public function declare_wc_compatibility()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Format Price Display (Strike-through original price + Discount Label)
     */
    public function format_price_display($price_html, $product)
    {
        $original_price = $product->get_regular_price();
        $discounted_price = $this->apply_custom_discount($original_price, $product);

        if ((float) $original_price !== (float) $discounted_price) {
            $price_html = wc_format_sale_price(
                wc_get_price_to_display($product, array('price' => $original_price)),
                wc_get_price_to_display($product, array('price' => $discounted_price))
            ) . $product->get_price_suffix();

            $discount_label = $this->get_discount_label($product);
            if ($discount_label) {
                $price_html .= ' <span class="da-price-discount-label">' . esc_html($discount_label) . '</span>';
            }
        }

        return $price_html;
    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('discount-architect-frontend', DISCOUNT_ARCHITECT_URL . 'assets/frontend.css', array(), time());
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_discount-architect' !== $hook) {
            return;
        }

        // Enqueue SweetAlert2 for premium notifications
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);

        // Enqueue Select2 from CDN to ensure it's always available and styled
        wp_enqueue_style('select2-cdn', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2-cdn', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        wp_enqueue_style('discount-architect-admin', DISCOUNT_ARCHITECT_URL . 'assets/admin.css', array('select2-cdn'), DISCOUNT_ARCHITECT_VERSION);
        wp_enqueue_script('discount-architect-admin', DISCOUNT_ARCHITECT_URL . 'assets/admin.js', array('jquery', 'select2-cdn', 'sweetalert2'), DISCOUNT_ARCHITECT_VERSION, true);

        wp_localize_script('discount-architect-admin', 'da_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('da_search_nonce'),
        ));
    }

    /**
     * AJAX Search Targets
     */
    public function ajax_search_targets()
    {
        check_ajax_referer('da_search_nonce', 'nonce');

        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'product';
        $results = array();

        if ('product' === $type) {
            global $wpdb;
            $search = '%' . $wpdb->esc_like($term) . '%';

            // Direct SQL query to search in Title, SKU, and ID for maximum reliability
            $query = $wpdb->prepare(
                "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status = 'publish'
				AND (p.post_title LIKE %s OR pm.meta_value LIKE %s OR p.ID = %d)
				LIMIT 20",
                $search,
                $search,
                is_numeric($term) ? (int) $term : 0
            );

            $ids = $wpdb->get_col($query);

            foreach ($ids as $id) {
                $product = wc_get_product($id);
                if (!$product) {
                    continue;
                }

                $sku = $product->get_sku();
                $display_text = '#' . $id . ' ' . $product->get_name();
                if ($sku) {
                    $display_text .= ' (SKU: ' . $sku . ')';
                }

                $results[] = array(
                    'id' => $id,
                    'text' => $display_text,
                );
            }
        } else {
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'name__like' => $term,
                'hide_empty' => false,
            ));

            foreach ($categories as $cat) {
                $results[] = array(
                    'id' => $cat->term_id,
                    'text' => $cat->name . ' (ID: ' . $cat->term_id . ')',
                );
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Handle Rule Actions (Save/Delete)
     */
    public function handle_rule_actions()
    {
        if (!isset($_POST['da_new_rule_nonce']) || !wp_verify_nonce($_POST['da_new_rule_nonce'], 'da_save_new_rule')) {
            // Handle delete
            if (isset($_GET['page']) && $_GET['page'] === 'discount-architect' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['index'])) {
                $index = (int) $_GET['index'];
                $rules = $this->get_rules();
                if (isset($rules[$index])) {
                    unset($rules[$index]);
                    update_option('da_discount_rules', array_values($rules));
                    wp_redirect(admin_url('admin.php?page=discount-architect&message=deleted'));
                    exit;
                }
            }
            return;
        }

        if (isset($_POST['da_new_rule'])) {
            $raw_rule = $_POST['da_new_rule'];
            $new_rule = array(
                'scope' => sanitize_text_field($raw_rule['scope']),
                'type' => sanitize_text_field($raw_rule['type']),
                'value' => sanitize_text_field($raw_rule['value']),
                'weight' => isset($raw_rule['weight']) ? (int) $raw_rule['weight'] : 0,
            );

            // Handle multiple targets (IDs)
            if (isset($raw_rule['targets']) && is_array($raw_rule['targets'])) {
                $targets = array_map('intval', $raw_rule['targets']);
                $new_rule['target'] = implode(',', $targets);
            }

            // Handle price threshold (Min)
            if (isset($raw_rule['target_price']) && !empty($raw_rule['target_price'])) {
                $new_rule['threshold'] = sanitize_text_field($raw_rule['target_price']);

                // If target was empty (for pure price rules), use threshold as target
                if (!isset($new_rule['target']) || empty($new_rule['target'])) {
                    $new_rule['target'] = $new_rule['threshold'];
                }
            }

            // Handle price threshold (Max)
            if (isset($raw_rule['target_price_max']) && !empty($raw_rule['target_price_max'])) {
                $new_rule['threshold_max'] = sanitize_text_field($raw_rule['target_price_max']);
            }

            // Handle global scope target
            if ($new_rule['scope'] === 'global') {
                $new_rule['target'] = 'all';
            }

            if (!empty($new_rule['target']) && !empty($new_rule['value'])) {
                $rules = $this->get_rules();
                $rules[] = $new_rule;
                update_option('da_discount_rules', $rules);

                wp_redirect(admin_url('admin.php?page=discount-architect&message=saved'));
                exit;
            }
        }
    }

    /**
     * Register Admin Menu
     */
    public function register_admin_menu()
    {
        $page_hook = add_menu_page(
            __('Discount Architect', 'discount-architect'),
            __('Discount Architect', 'discount-architect'),
            'manage_options',
            'discount-architect',
            array($this, 'render_admin_page'),
            'dashicons-tag',
            58
        );

        // Remove all distracting notices when on our page
        add_action("load-$page_hook", array($this, 'remove_unrelated_notices'));
    }

    /**
     * Remove standard WordPress notices for a cleaner UI
     */
    public function remove_unrelated_notices()
    {
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');

        // Also add CSS to hide any leftover custom banners
        add_action('admin_head', function () {
            echo '<style>
				.toplevel_page_discount-architect .notice, 
				.toplevel_page_discount-architect .updated, 
				.toplevel_page_discount-architect .error, 
				.toplevel_page_discount-architect .is-dismissible,
				.toplevel_page_discount-architect .update-nag,
				.toplevel_page_discount-architect div.wp-header-end + div.notice,
				.toplevel_page_discount-architect #message { 
					display: none !important; 
				}
			</style>';
        });
    }

    /**
     * Register Settings
     */
    public function register_settings()
    {
        register_setting('discount_architect_settings', 'da_discount_rules');
        register_setting('discount_architect_settings', 'da_badge_position');
        register_setting('discount_architect_settings', 'da_show_on_shop');
        register_setting('discount_architect_settings', 'da_show_on_archive');
        register_setting('discount_architect_settings', 'da_show_on_single');
        register_setting('discount_architect_settings', 'da_show_on_related');
        register_setting('discount_architect_settings', 'da_show_on_search');
        register_setting('discount_architect_settings', 'da_show_on_cart');
    }

    /**
     * Render Admin Page
     */
    public function render_admin_page()
    {
        $rules = $this->get_rules();
        $message = isset($_GET['message']) ? $_GET['message'] : '';
        ?>
        <div class="wrap">
            <h1><?php _e('Discount Architect', 'discount-architect'); ?></h1>
            <p class="description">
                <?php _e('Craft premium, high-performance discount rules for your WooCommerce store.', 'discount-architect'); ?>
            </p>

            <h2 class="nav-tab-wrapper">
                <a href="#operation" class="nav-tab nav-tab-active" id="tab-operation">
                    <span class="dashicons dashicons-admin-tools"></span> <?php _e('Operation', 'discount-architect'); ?>
                </a>
                <a href="#documentation" class="nav-tab" id="tab-documentation">
                    <span class="dashicons dashicons-editor-help"></span> <?php _e('Documentation', 'discount-architect'); ?>
                </a>
            </h2>

            <div id="section-operation" class="da-tab-content">
                <!-- Rules Table -->
                <div class="da-rules-container">
                    <h2><?php _e('Active Discount Rules', 'discount-architect'); ?></h2>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Scope', 'discount-architect'); ?></th>
                                <th><?php _e('Target', 'discount-architect'); ?></th>
                                <th><?php _e('Type', 'discount-architect'); ?></th>
                                <th><?php _e('Value', 'discount-architect'); ?></th>
                                <th><?php _e('Weight', 'discount-architect'); ?></th>
                                <th><?php _e('Action', 'discount-architect'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rules)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">
                                        <span class="dashicons dashicons-info"
                                            style="font-size: 30px; width: 30px; height: 30px;"></span>
                                        <br><br>
                                        <?php _e('No rules defined yet. Start by adding one below.', 'discount-architect'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rules as $index => $rule): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $icon = 'dashicons-tag';
                                            if ($rule['scope'] === 'product')
                                                $icon = 'dashicons-cart';
                                            if ($rule['scope'] === 'category')
                                                $icon = 'dashicons-category';
                                            if (strpos($rule['scope'], 'price') !== false)
                                                $icon = 'dashicons-money-alt';
                                            ?>
                                            <span class="dashicons <?php echo $icon; ?>"></span>
                                            <?php
                                            $scope_label = ucfirst($rule['scope']);
                                            if ($rule['scope'] === 'cat_price_gt')
                                                $scope_label = __('Category + Price >', 'discount-architect');
                                            if ($rule['scope'] === 'cat_price_lt')
                                                $scope_label = __('Category + Price <', 'discount-architect');
                                            if ($rule['scope'] === 'price_between')
                                                $scope_label = __('Price Between', 'discount-architect');
                                            if ($rule['scope'] === 'cat_price_between')
                                                $scope_label = __('Category + Price Between', 'discount-architect');
                                            echo esc_html($scope_label);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (strpos($rule['scope'], 'price_between') !== false && !empty($rule['threshold']) && !empty($rule['threshold_max'])) {
                                                echo __('Range: ', 'discount-architect') . get_woocommerce_currency_symbol() . esc_html($rule['threshold']) . ' - ' . get_woocommerce_currency_symbol() . esc_html($rule['threshold_max']);
                                            }

                                            $targets = explode(',', $rule['target']);
                                            $display_names = array();

                                            foreach ($targets as $target_id) {
                                                if ($target_id === 'all' || is_numeric($target_id) === false)
                                                    continue;
                                                if ($rule['scope'] === 'product') {
                                                    $product = wc_get_product($target_id);
                                                    if ($product) {
                                                        $sku = $product->get_sku();
                                                        $display_names[] = $product->get_name() . ($sku ? ' (SKU: ' . $sku . ')' : '');
                                                    } else {
                                                        $display_names[] = '#' . $target_id;
                                                    }
                                                } elseif ($rule['scope'] === 'category' || strpos($rule['scope'], 'cat_price') !== false) {
                                                    $term = get_term((int) $target_id, 'product_cat');
                                                    $display_names[] = (!is_wp_error($term) && $term) ? $term->name : '#' . $target_id;
                                                } else {
                                                    $display_names[] = $target_id;
                                                }
                                            }

                                            echo esc_html(implode(', ', $display_names));

                                            if (!empty($rule['threshold']) && strpos($rule['scope'], 'cat_price') !== false && strpos($rule['scope'], 'between') === false) {
                                                echo ' | ' . __('Threshold: ', 'discount-architect') . esc_html($rule['threshold']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($rule['type'] === 'percentage' ? '%' : 'Flat'); ?></td>
                                        <td><?php echo esc_html($rule['value']); ?></td>
                                        <td><strong
                                                style="color: #6366f1;">#<?php echo esc_html(isset($rule['weight']) ? $rule['weight'] : '0'); ?></strong>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=discount-architect&action=delete&index=' . $index)); ?>"
                                                class="da-delete-rule button-link-delete">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e('Delete', 'discount-architect'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <hr style="margin: 40px 0; border: 0; border-top: 1px solid #f1f5f9;">

                <!-- Add New Rule -->
                <form method="post" action="">
                    <?php wp_nonce_field('da_save_new_rule', 'da_new_rule_nonce'); ?>

                    <h2><?php _e('Add New Discount Rule', 'discount-architect'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="da_scope"><?php _e('Discount Scope', 'discount-architect'); ?></label></th>
                            <td>
                                <select id="da_scope" name="da_new_rule[scope]">
                                    <optgroup label="<?php _e('Global Rules', 'discount-architect'); ?>">
                                        <option value="global"><?php _e('All Products', 'discount-architect'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('Specific Targets', 'discount-architect'); ?>">
                                        <option value="product"><?php _e('Product Wise', 'discount-architect'); ?></option>
                                        <option value="category"><?php _e('Category Wise', 'discount-architect'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('Price Conditions', 'discount-architect'); ?>">
                                        <option value="price_gt"><?php _e('Price Greater Than (>)', 'discount-architect'); ?>
                                        </option>
                                        <option value="price_lt"><?php _e('Price Less Than (<)', 'discount-architect'); ?>
                                        </option>
                                        <option value="price_between">
                                            <?php _e('In Between 2 Prices (Min/Max)', 'discount-architect'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php _e('Compound Rules', 'discount-architect'); ?>">
                                        <option value="cat_price_gt">
                                            <?php _e('Category + Price Greater Than', 'discount-architect'); ?></option>
                                        <option value="cat_price_lt">
                                            <?php _e('Category + Price Less Than', 'discount-architect'); ?></option>
                                        <option value="cat_price_between">
                                            <?php _e('Category + In Between 2 Prices', 'discount-architect'); ?></option>
                                    </optgroup>
                                </select>
                                <p class="description">
                                    <?php _e('Choose where this discount should apply.', 'discount-architect'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr id="da_targets_row">
                            <th><label for="da_targets"><?php _e('Select Targets', 'discount-architect'); ?></label></th>
                            <td>
                                <select id="da_targets" name="da_new_rule[targets][]" class="da-select2" multiple="multiple"
                                    style="width: 100%;"></select>
                                <p class="description"><?php _e('Search by Name or SKU.', 'discount-architect'); ?></p>
                            </td>
                        </tr>
                        <tr id="da_price_threshold_row" style="display:none;">
                            <th><label for="da_price_threshold"
                                    id="da_price_label_min"><?php _e('Price Threshold', 'discount-architect'); ?></label></th>
                            <td>
                                <input type="number" step="0.01" id="da_price_threshold" name="da_new_rule[target_price]"
                                    class="regular-text">
                                <p class="description" id="da_price_desc_min">
                                    <?php _e('Enter the price limit.', 'discount-architect'); ?></p>
                            </td>
                        </tr>
                        <tr id="da_price_threshold_max_row" style="display:none;">
                            <th><label
                                    for="da_price_threshold_max"><?php _e('Max Price Threshold', 'discount-architect'); ?></label>
                            </th>
                            <td>
                                <input type="number" step="0.01" id="da_price_threshold_max"
                                    name="da_new_rule[target_price_max]" class="regular-text">
                                <p class="description"><?php _e('Enter the upper price limit.', 'discount-architect'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="da_weight"><?php _e('Rule Weight', 'discount-architect'); ?></label></th>
                            <td>
                                <input type="number" id="da_weight" name="da_new_rule[weight]" class="regular-text" value="0">
                                <p class="description">
                                    <?php _e('Priority level. Higher numbers take precedence.', 'discount-architect'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="da_type"><?php _e('Discount Type', 'discount-architect'); ?></label></th>
                            <td>
                                <select id="da_type" name="da_new_rule[type]">
                                    <option value="percentage"><?php _e('Percentage (%)', 'discount-architect'); ?></option>
                                    <option value="flat"><?php _e('Flat Amount', 'discount-architect'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="da_value"><?php _e('Discount Value', 'discount-architect'); ?></label></th>
                            <td>
                                <input type="number" step="0.01" id="da_value" name="da_new_rule[value]" class="regular-text"
                                    required>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Add Rule to Architect', 'discount-architect')); ?>
                </form>

                <hr style="margin: 40px 0; border: 0; border-top: 1px solid #f1f5f9;">

                <!-- Global Settings -->
                <form method="post" action="options.php">
                    <?php settings_fields('discount_architect_settings'); ?>
                    <h2><?php _e('Global Label Settings', 'discount-architect'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label
                                    for="da_badge_position"><?php _e('Discount Label Position', 'discount-architect'); ?></label>
                            </th>
                            <td>
                                <?php $pos = get_option('da_badge_position', 'top_left'); ?>
                                <select id="da_badge_position" name="da_badge_position">
                                    <option value="top_left" <?php selected($pos, 'top_left'); ?>>
                                        <?php _e('Top Left', 'discount-architect'); ?>
                                    </option>
                                    <option value="top_right" <?php selected($pos, 'top_right'); ?>>
                                        <?php _e('Top Right', 'discount-architect'); ?>
                                    </option>
                                    <option value="bottom_left" <?php selected($pos, 'bottom_left'); ?>>
                                        <?php _e('Bottom Left', 'discount-architect'); ?>
                                    </option>
                                    <option value="bottom_right" <?php selected($pos, 'bottom_right'); ?>>
                                        <?php _e('Bottom Right', 'discount-architect'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Show Label On', 'discount-architect'); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="da_show_on_shop" value="1" <?php checked(get_option('da_show_on_shop', 1), 1); ?>>
                                        <?php _e('Shop Page', 'discount-architect'); ?></label><br>
                                    <label><input type="checkbox" name="da_show_on_archive" value="1" <?php checked(get_option('da_show_on_archive', 1), 1); ?>>
                                        <?php _e('Category/Archive Pages', 'discount-architect'); ?></label><br>
                                    <label><input type="checkbox" name="da_show_on_single" value="1" <?php checked(get_option('da_show_on_single', 1), 1); ?>>
                                        <?php _e('Single Product Page (Main)', 'discount-architect'); ?></label><br>
                                    <label><input type="checkbox" name="da_show_on_related" value="1" <?php checked(get_option('da_show_on_related', 1), 1); ?>>
                                        <?php _e('Related Products & Up-sells', 'discount-architect'); ?></label><br>
                                    <label><input type="checkbox" name="da_show_on_search" value="1" <?php checked(get_option('da_show_on_search', 1), 1); ?>>
                                        <?php _e('Search Results', 'discount-architect'); ?></label><br>
                                    <label><input type="checkbox" name="da_show_on_cart" value="1" <?php checked(get_option('da_show_on_cart', 1), 1); ?>>
                                        <?php _e('Cart Page', 'discount-architect'); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Global Settings', 'discount-architect')); ?>
                </form>
            </div>

            <div id="section-documentation" class="da-tab-content" style="display:none;">
                <!-- Documentation Content -->
                <div class="da-doc-section">
                    <h3><?php _e('🚀 Getting Started', 'discount-architect'); ?></h3>
                    <p><?php _e('Discount Architect allows you to apply flexible, high-performance discount rules across your store. Rules are processed in real-time without slowing down your site.', 'discount-architect'); ?>
                    </p>

                    <div class="da-doc-step">
                        <span class="da-step-num">1</span>
                        <div>
                            <strong><?php _e('Choose a Scope:', 'discount-architect'); ?></strong>
                            <?php _e('Decide if the discount applies to everything (Global), specific products, or entire categories.', 'discount-architect'); ?>
                        </div>
                    </div>
                    <div class="da-doc-step">
                        <span class="da-step-num">2</span>
                        <div>
                            <strong><?php _e('Set the Discount:', 'discount-architect'); ?></strong>
                            <?php _e('Choose between a Percentage (e.g., 10%) or a Flat Amount (e.g., $5 off).', 'discount-architect'); ?>
                        </div>
                    </div>
                    <div class="da-doc-step">
                        <span class="da-step-num">3</span>
                        <div>
                            <strong><?php _e('Assign Weight:', 'discount-architect'); ?></strong>
                            <?php _e('If a product qualifies for multiple rules, the one with the highest weight will be applied.', 'discount-architect'); ?>
                        </div>
                    </div>
                </div>

                <div class="da-doc-section">
                    <h3><?php _e('⚖️ Rule Priority (Weight System)', 'discount-architect'); ?></h3>
                    <p><?php _e('The Weight system prevents discount conflicts. For example:', 'discount-architect'); ?></p>
                    <ul style="list-style: disc; margin-left: 20px; color: #475569;">
                        <li><strong>Rule A (Weight 10):</strong> 20% off on "Earrings" category.</li>
                        <li><strong>Rule B (Weight 50):</strong> 30% off on "Gold Earrings" specific product.</li>
                    </ul>
                    <p><?php _e('In this case, "Gold Earrings" will receive <strong>30% off</strong> because Rule B has a higher weight, even though it also belongs to the "Earrings" category.', 'discount-architect'); ?>
                    </p>
                </div>

                <div class="da-doc-section">
                    <h3><?php _e('🔍 Search Tips', 'discount-architect'); ?></h3>
                    <p><?php _e('The "Select Targets" field is optimized for speed. You can search by:', 'discount-architect'); ?>
                    </p>
                    <code
                        style="display: block; background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; margin: 10px 0;">
                                        - Product Name (e.g., "Silver Ring")<br>
                                        - SKU (e.g., "EAR806ON")<br>
                                        - Category Name (e.g., "Bracelets")
                                    </code>
                </div>

                <div class="da-doc-section">
                    <h3><?php _e('💡 Best Practices', 'discount-architect'); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px; color: #475569;">
                        <li><?php _e('Use <strong>Global</strong> rules for store-wide sales (e.g., Black Friday).', 'discount-architect'); ?>
                        </li>
                        <li><?php _e('Use <strong>Price Threshold</strong> rules to encourage higher spending (e.g., "10% off for products over $100").', 'discount-architect'); ?>
                        </li>
                        <li><?php _e('Keep your weights consistent (e.g., 10 for basic, 50 for specific, 100 for high-priority).', 'discount-architect'); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <?php
    }

    /**
     * Get Discount Label for a product
     */
    public function get_discount_label($product)
    {
        $discount = $this->get_discount_info($product);
        if (!$discount) {
            return false;
        }

        if ($discount['type'] === 'percentage') {
            return $discount['value'] . '% OFF';
        } else {
            return get_woocommerce_currency_symbol() . $discount['value'] . ' OFF';
        }
    }

    /**
     * Get applied discount info for a product
     *
     * @param WC_Product $product
     * @return array|false Array with 'value' and 'type' if a discount is applied, false otherwise.
     */
    public function get_discount_info($product)
    {
        $original_price = (float) $product->get_regular_price();
        if (!$original_price) {
            return false;
        }

        $rules = $this->get_rules();
        if (empty($rules)) {
            return false;
        }

        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();
        $categories = $product->get_category_ids();

        // If it's a variation and categories are empty, inherit from parent
        if ($parent_id && empty($categories)) {
            $parent_product = wc_get_product($parent_id);
            if ($parent_product) {
                $categories = $parent_product->get_category_ids();
            }
        }

        foreach ($rules as $rule) {
            $applied = false;
            $scope = $rule['scope'];

            if ($scope === 'global') {
                $applied = true;
            } elseif ($scope === 'product') {
                $targets = explode(',', $rule['target']);
                // Check both variation ID and parent ID for product-wise rules
                if (in_array((string) $product_id, $targets) || ($parent_id && in_array((string) $parent_id, $targets))) {
                    $applied = true;
                }
            } elseif ($scope === 'category') {
                $targets = explode(',', $rule['target']);
                $intersect = array_intersect($categories, $targets);
                if (!empty($intersect)) {
                    $applied = true;
                }
            } elseif ($scope === 'price_gt' && $original_price > (float) $rule['target']) {
                $applied = true;
            } elseif ($scope === 'price_lt' && $original_price < (float) $rule['target']) {
                $applied = true;
            } elseif ($scope === 'price_between' && $original_price >= (float) $rule['threshold'] && $original_price <= (float) $rule['threshold_max']) {
                $applied = true;
            } elseif (strpos($scope, 'cat_price') !== false) {
                $targets = explode(',', $rule['target']);
                $intersect = array_intersect($categories, $targets);

                if (!empty($intersect)) {
                    if ($scope === 'cat_price_gt' && $original_price > (float) $rule['threshold']) {
                        $applied = true;
                    } elseif ($scope === 'cat_price_lt' && $original_price < (float) $rule['threshold']) {
                        $applied = true;
                    } elseif ($scope === 'cat_price_between' && $original_price >= (float) $rule['threshold'] && $original_price <= (float) $rule['threshold_max']) {
                        $applied = true;
                    }
                }
            }

            if ($applied) {
                return array('value' => (float) $rule['value'], 'type' => $rule['type']);
            }
        }

        return false;
    }

    /**
     * Add Discount to Item Data (Guaranteed visibility in cart/checkout)
     */
    public function add_discount_to_item_data($item_data, $cart_item)
    {
        $product = $cart_item['data'];
        $discount_label = $this->get_discount_label($product);

        if ($discount_label) {
            $item_data[] = array(
                'key' => __('Discount', 'discount-architect'),
                'value' => $discount_label,
                'display' => '<span class="da-price-discount-label">' . $discount_label . '</span>',
            );
        }

        return $item_data;
    }

    /**
     * Add Discount Label to Cart Item Name (Fallback for visibility)
     */
    public function add_discount_label_to_cart_name($name, $cart_item, $cart_item_key)
    {
        $product = $cart_item['data'];
        $discount_label = $this->get_discount_label($product);

        if ($discount_label) {
            // Append label to name with a line break or space
            $name .= '<br><span class="da-price-discount-label cart-name-label">' . esc_html($discount_label) . '</span>';
        }

        return $name;
    }

    /**
     * Format Cart Price Display (Show original price crossed out + Discount Label)
     */
    public function format_cart_price_display($price_html, $cart_item, $cart_item_key)
    {
        $product = $cart_item['data'];

        $discount = $this->get_discount_info($product);
        if (!$discount) {
            return $price_html;
        }

        $original_price = (float) $product->get_regular_price();
        $discounted_price = (float) $product->get_price();

        // For subtotal, we need to multiply by quantity
        if (current_filter() === 'woocommerce_cart_item_subtotal') {
            $quantity = $cart_item['quantity'];
            $original_price *= $quantity;
            $discounted_price *= $quantity;
        }

        // Re-format price HTML to ensure strike-through is present
        $price_html = wc_format_sale_price(
            wc_get_price_to_display($product, array('price' => $original_price)),
            wc_get_price_to_display($product, array('price' => $discounted_price))
        );

        $discount_label = $this->get_discount_label($product);
        if ($discount_label) {
            $price_html .= ' <span class="da-price-discount-label">' . esc_html($discount_label) . '</span>';
        }

        return $price_html;
    }

    /**
     * Format Order Item Display
     */
    public function format_order_item_display($subtotal, $item, $order)
    {
        $product = $item->get_product();
        if (!$product) {
            return $subtotal;
        }

        $discount_label = $this->get_discount_label($product);
        if ($discount_label) {
            // Check if a discount was actually applied to this item
            // We can compare regular price with item total/qty
            $regular_price = (float) $product->get_regular_price() * $item->get_quantity();
            $current_total = (float) $item->get_total();

            if ($regular_price > $current_total) {
                $subtotal .= ' <span class="da-price-discount-label">' . esc_html($discount_label) . '</span>';
            }
        }

        return $subtotal;
    }

    /**
     * Add Discount Badge to Standard Post Thumbnail HTML
     */
    public function add_badge_to_post_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return $html;
        }

        if (get_post_type($post_id) !== 'product') {
            return $html;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return $html;
        }

        return $this->add_badge_to_image($html, $product);
    }

    /**
     * Add Discount Badge to Product Image HTML
     * This works for shop loop, related products, etc.
     */
    public function add_badge_to_image($html, $product)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return $html;
        }

        // Visibility Checks
        $show_on_shop = get_option('da_show_on_shop', 1);
        $show_on_archive = get_option('da_show_on_archive', 1);
        $show_on_single = get_option('da_show_on_single', 1);
        $show_on_related = get_option('da_show_on_related', 1);
        $show_on_search = get_option('da_show_on_search', 1);
        $show_on_cart = get_option('da_show_on_cart', 1);

        if (is_shop() && !$show_on_shop)
            return $html;
        if ((is_product_category() || is_product_tag()) && !$show_on_archive)
            return $html;
        if (is_search() && !$show_on_search)
            return $html;
        if (is_cart() && !$show_on_cart)
            return $html;

        if (is_product()) {
            // In single product page, we need to distinguish between main image and related
            global $post;
            $is_main_image = ($post && $post->ID === $product->get_id());

            if ($is_main_image && !$show_on_single)
                return $html;
            if (!$is_main_image && !$show_on_related)
                return $html;
        }

        $discount = $this->get_discount_info($product);
        if (!$discount) {
            return $html;
        }

        // Prevent duplicate badges on the same image string
        if (strpos($html, 'da-discount-badge') !== false) {
            return $html;
        }

        $label = '';
        if ($discount['type'] === 'percentage') {
            $label = $discount['value'] . '% OFF';
        } else {
            $label = get_woocommerce_currency_symbol() . $discount['value'] . ' OFF';
        }

        $position = get_option('da_badge_position', 'top_left');
        $badge = '<div class="da-discount-badge da-badge-' . esc_attr($position) . '"><span>' . esc_html($label) . '</span></div>';

        // Wrap in a relative container to ensure absolute positioning works everywhere
        return '<div class="da-image-container" style="position:relative !important; display:block !important; width:100%;">' . $badge . $html . '</div>';
    }

    /**
     * Display Discount Badge on Product Image (Action version for single product)
     */
    public function display_discount_badge()
    {
        global $product;

        if (!$product) {
            return;
        }

        // Visibility Check for Single Product Page (since this is called via action)
        if (is_product() && !get_option('da_show_on_single', 1)) {
            return;
        }

        // Prevent duplicate badges if the filter also fired
        static $displayed_badges = array();
        $product_id = $product->get_id();

        if (in_array($product_id, $displayed_badges)) {
            return;
        }

        $discount = $this->get_discount_info($product);
        if (!$discount) {
            return;
        }

        $displayed_badges[] = $product_id;

        $label = '';
        if ($discount['type'] === 'percentage') {
            $label = $discount['value'] . '% OFF';
        } else {
            $label = get_woocommerce_currency_symbol() . $discount['value'] . ' OFF';
        }

        $position = get_option('da_badge_position', 'top_left');
        echo '<div class="da-discount-badge da-badge-' . esc_attr($position) . '"><span>' . esc_html($label) . '</span></div>';
    }

    /**
     * Apply Custom Discount Logic
     * 
     * @param float $price
     * @param WC_Product $product
     * @return float
     */
    public function apply_custom_discount($price, $product)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }

        $original_price = (float) $product->get_regular_price();
        if (!$original_price) {
            return $price;
        }

        $discount = $this->get_discount_info($product);
        if (!$discount) {
            return $price;
        }

        $applied_discount = $discount['value'];
        $discount_type = $discount['type'];

        // Calculate final price based on ORIGINAL price
        $final_price = $original_price;
        if ($discount_type === 'percentage') {
            $final_price = $original_price - ($original_price * ($applied_discount / 100));
        } elseif ($discount_type === 'flat') {
            $final_price = $original_price - $applied_discount;
        }

        return $final_price > 0 ? $final_price : 0;
    }

    /**
     * Centralized helper to get rules as an array
     */
    private function get_rules()
    {
        $rules = get_option('da_discount_rules', array());
        if (!is_array($rules)) {
            return array();
        }

        // Sort by weight descending (Higher weight first)
        usort($rules, function ($a, $b) {
            $wa = isset($a['weight']) ? (int) $a['weight'] : 0;
            $wb = isset($b['weight']) ? (int) $b['weight'] : 0;
            if ($wa === $wb)
                return 0;
            return ($wa > $wb) ? -1 : 1;
        });

        return $rules;
    }
}

/**
 * Initialize the plugin
 */
function run_discount_architect()
{
    return Discount_Architect::get_instance();
}
run_discount_architect();
