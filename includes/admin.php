<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin menu & settings page for Sticker Creator.
 */

add_action( 'admin_menu', 'sc_admin_menu' );
function sc_admin_menu() {
    add_menu_page(
        'Sticker Creator',
        'Sticker Creator',
        'manage_options',
        'sticker-creator',
        'sc_admin_page',
        'dashicons-format-image',
        56
    );
}

add_action( 'admin_enqueue_scripts', 'sc_admin_assets' );
function sc_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_sticker-creator' ) return;

    wp_enqueue_style( 'sc-admin-css', SC_PLUGIN_URL . 'assets/css/admin.css', array(), SC_VERSION );
    wp_enqueue_script( 'sc-admin-js', SC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SC_VERSION, true );
    wp_localize_script( 'sc-admin-js', 'scAdmin', array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'sc_admin_nonce' ),
        'sizes'      => get_option( 'sc_sizes', array() ),
        'materials'  => array( 'vinyl', 'paper', 'clear' ),
        'finishes'   => array( 'glossy', 'matte' ),
        'overrides'  => get_option( 'sc_quantity_breaks_overrides', array() ),
        'hidden_stickers' => get_option( 'sc_hidden_stickers', array() ),
    ));
}

/**
 * Render admin page.
 */
function sc_admin_page() {
    $sizes       = get_option( 'sc_sizes', array() );
    $pricing     = get_option( 'sc_pricing', array() );
    $qty_breaks  = get_option( 'sc_quantity_breaks', array() );
    $qty_overrides = get_option( 'sc_quantity_breaks_overrides', array() );
    $hidden_stickers = get_option( 'sc_hidden_stickers', array() );

    $materials = array( 'vinyl', 'paper', 'clear' );
    $finishes  = array( 'glossy', 'matte' );
    $lam_opts  = array( 'yes', 'no' );

    $wc_active = class_exists( 'WooCommerce' );
    ?>
    <div class="wrap sc-admin-wrap">
        <h1>Sticker Creator Settings</h1>

        <?php if ( ! $wc_active ) : ?>
        <div class="notice notice-warning">
            <p><strong>WooCommerce is not active.</strong> Install and activate WooCommerce for full cart &amp; checkout integration. The plugin will use a basic session cart as a fallback.</p>
        </div>
        <?php else : ?>
        <div class="sc-admin-shortcode-info" style="background:#e3f2fd;border-color:#90caf9;">
            <h3>WooCommerce Active</h3>
            <p>Sticker orders integrate with WooCommerce cart and checkout.</p>
        </div>
        <?php endif; ?>

        <div class="sc-admin-shortcode-info">
            <h3>Shortcode</h3>
            <p>Use <code>[sticker_creator]</code> to embed the sticker builder on any page or post.</p>
        </div>

        <!-- GLOBAL SETTINGS -->
        <div class="sc-admin-section">
            <h2>Global Settings</h2>
            <form id="sc-global-settings-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_global_nonce_field' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="sc-min-quantity">Minimum Order Quantity</label></th>
                        <td>
                            <input type="number" id="sc-min-quantity" min="1" value="<?php echo esc_attr( get_option( 'sc_min_quantity', 1 ) ); ?>" />
                            <p class="description">Customers must order at least this many stickers.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sc-lamination-enabled">Enable Lamination Option</label></th>
                        <td>
                            <label><input type="checkbox" id="sc-lamination-enabled" value="1" <?php checked( get_option( 'sc_lamination_enabled', '1' ), '1' ); ?> /> Show lamination option on the order form</label>
                            <p class="description">When unchecked, the lamination choice is hidden and only non-laminated stickers are available.</p>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Save Global Settings</button></p>
            </form>
        </div>

        <!-- SIZES -->
        <div class="sc-admin-section">
            <h2>Sizes</h2>
            <form id="sc-sizes-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_nonce_field' ); ?>
                <table class="widefat sc-sizes-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Width (inches)</th>
                            <th>Height (inches)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-sizes-tbody">
                    <?php foreach ( $sizes as $i => $size ) : ?>
                        <tr data-index="<?php echo esc_attr( $i ); ?>">
                            <td><input type="text" name="sizes[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $size['label'] ); ?>" /></td>
                            <td><input type="number" step="0.25" min="0.5" name="sizes[<?php echo $i; ?>][width]" value="<?php echo esc_attr( $size['width'] ); ?>" /></td>
                            <td><input type="number" step="0.25" min="0.5" name="sizes[<?php echo $i; ?>][height]" value="<?php echo esc_attr( $size['height'] ); ?>" /></td>
                            <td><button type="button" class="button sc-remove-size">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-size">+ Add Size</button>
                    <button type="submit" class="button button-primary">Save Sizes</button>
                </p>
            </form>
        </div>

        <!-- PRICING PER STICKER (editable rows) -->
        <div class="sc-admin-section">
            <h2>Pricing per Sticker</h2>
            <p class="description">Add, remove, or edit pricing for each sticker configuration. Only configurations listed here will be available to customers.</p>
            <form id="sc-pricing-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_pricing_nonce_field' ); ?>
                <table class="widefat sc-pricing-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Material</th>
                            <th>Finish</th>
                            <th>Laminated</th>
                            <th>Price ($)</th>
                            <th>Visible</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-pricing-tbody">
                    <?php
                    $row = 0;
                    foreach ( $pricing as $key => $price ) :
                        $parts = explode( '_', $key );
                        if ( count( $parts ) !== 4 ) continue;
                        list( $size_idx, $mat, $fin, $lam ) = $parts;
                    ?>
                        <tr data-row="<?php echo $row; ?>">
                            <td>
                                <select name="pricing_rows[<?php echo $row; ?>][size_index]">
                                    <?php foreach ( $sizes as $i => $s ) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected( (int)$size_idx, $i ); ?>><?php echo esc_html( $s['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="pricing_rows[<?php echo $row; ?>][material]">
                                    <?php foreach ( $materials as $m ) : ?>
                                        <option value="<?php echo $m; ?>" <?php selected( $mat, $m ); ?>><?php echo ucfirst( $m ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="pricing_rows[<?php echo $row; ?>][finish]">
                                    <?php foreach ( $finishes as $f ) : ?>
                                        <option value="<?php echo $f; ?>" <?php selected( $fin, $f ); ?>><?php echo ucfirst( $f ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="pricing_rows[<?php echo $row; ?>][laminated]">
                                    <option value="yes" <?php selected( $lam, 'yes' ); ?>>Yes</option>
                                    <option value="no" <?php selected( $lam, 'no' ); ?>>No</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="pricing_rows[<?php echo $row; ?>][price]" value="<?php echo esc_attr( $price ); ?>" /></td>
                            <td><input type="checkbox" class="sc-pricing-visible" <?php checked( ! in_array( $key, $hidden_stickers, true ) ); ?> /></td>
                            <td><button type="button" class="button sc-remove-pricing">Remove</button></td>
                        </tr>
                    <?php $row++; endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-pricing">+ Add Pricing Row</button>
                    <button type="submit" class="button button-primary">Save Pricing</button>
                </p>
            </form>
        </div>

        <!-- DEFAULT QUANTITY BREAKS -->
        <div class="sc-admin-section">
            <h2>Default Quantity Price Breaks</h2>
            <p class="description">These breaks apply to all stickers unless overridden below for a specific configuration.</p>
            <form id="sc-qty-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_qty_nonce_field' ); ?>
                <table class="widefat sc-qty-table">
                    <thead>
                        <tr>
                            <th>Min Qty</th>
                            <th>Max Qty</th>
                            <th>Price Multiplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-qty-tbody">
                    <?php foreach ( $qty_breaks as $j => $brk ) : ?>
                        <tr data-index="<?php echo $j; ?>">
                            <td><input type="number" min="1" name="qty[<?php echo $j; ?>][min]" value="<?php echo esc_attr( $brk['min'] ); ?>" /></td>
                            <td><input type="number" min="1" name="qty[<?php echo $j; ?>][max]" value="<?php echo esc_attr( $brk['max'] ); ?>" /></td>
                            <td><input type="number" step="0.01" min="0" max="2" name="qty[<?php echo $j; ?>][multiplier]" value="<?php echo esc_attr( $brk['multiplier'] ); ?>" /></td>
                            <td><button type="button" class="button sc-remove-qty">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-qty">+ Add Break</button>
                    <button type="submit" class="button button-primary">Save Default Breaks</button>
                </p>
            </form>
        </div>

        <!-- PER-STICKER QUANTITY BREAK OVERRIDES -->
        <div class="sc-admin-section">
            <h2>Per-Sticker Quantity Break Overrides</h2>
            <p class="description">Override the default quantity breaks for specific sticker configurations. If no override is set for a sticker, the defaults above are used.</p>
            <div class="sc-override-selector">
                <label><strong>Select Sticker Configuration:</strong></label>
                <select id="sc-override-sticker-select">
                    <option value="">— Choose a sticker —</option>
                    <?php foreach ( $pricing as $key => $price ) :
                        $parts = explode( '_', $key );
                        if ( count( $parts ) !== 4 ) continue;
                        list( $si, $m, $f, $l ) = $parts;
                        $size_label = isset( $sizes[ (int)$si ] ) ? $sizes[ (int)$si ]['label'] : 'Size ' . $si;
                        $label = $size_label . ' / ' . ucfirst( $m ) . ' / ' . ucfirst( $f ) . ' / Lam: ' . ucfirst( $l );
                        $has_override = isset( $qty_overrides[ $key ] ) ? ' ★' : '';
                    ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label . $has_override ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="sc-override-breaks-wrap" style="display:none; margin-top:20px;">
                <p id="sc-override-status" class="description"></p>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Min Qty</th>
                            <th>Max Qty</th>
                            <th>Price Multiplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-override-breaks-tbody">
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-override-break">+ Add Break</button>
                    <button type="button" class="button button-primary" id="sc-save-override-breaks">Save Overrides for This Sticker</button>
                    <button type="button" class="button" id="sc-remove-overrides">Remove Overrides (Use Defaults)</button>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/* ─── Admin AJAX Handlers ─── */

add_action( 'wp_ajax_sc_save_global_settings', 'sc_save_global_settings' );
function sc_save_global_settings() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $min_qty = max( 1, intval( $_POST['min_quantity'] ?? 1 ) );
    $lam_enabled = isset( $_POST['lamination_enabled'] ) && $_POST['lamination_enabled'] === '1' ? '1' : '0';

    update_option( 'sc_min_quantity', $min_qty );
    update_option( 'sc_lamination_enabled', $lam_enabled );
    wp_send_json_success( 'Global settings saved.' );
}

add_action( 'wp_ajax_sc_save_sizes', 'sc_save_sizes' );
function sc_save_sizes() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['sizes'] ) ? $_POST['sizes'] : array();
    $sizes = array();
    foreach ( $raw as $s ) {
        $sizes[] = array(
            'label'  => sanitize_text_field( $s['label'] ),
            'width'  => floatval( $s['width'] ),
            'height' => floatval( $s['height'] ),
        );
    }
    update_option( 'sc_sizes', $sizes );
    wp_send_json_success( 'Sizes saved.' );
}

add_action( 'wp_ajax_sc_save_pricing', 'sc_save_pricing' );
function sc_save_pricing() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['pricing_rows'] ) ? $_POST['pricing_rows'] : array();
    $pricing = array();
    $hidden  = array();
    foreach ( $raw as $row ) {
        $si  = intval( $row['size_index'] );
        $mat = sanitize_text_field( $row['material'] );
        $fin = sanitize_text_field( $row['finish'] );
        $lam = sanitize_text_field( $row['laminated'] );
        $key = "{$si}_{$mat}_{$fin}_{$lam}";
        $pricing[ $key ] = floatval( $row['price'] );
        if ( empty( $row['visible'] ) || $row['visible'] === '0' ) {
            $hidden[] = $key;
        }
    }
    update_option( 'sc_pricing', $pricing );
    update_option( 'sc_hidden_stickers', $hidden );
    wp_send_json_success( 'Pricing saved.' );
}

add_action( 'wp_ajax_sc_save_qty_breaks', 'sc_save_qty_breaks' );
function sc_save_qty_breaks() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['qty'] ) ? $_POST['qty'] : array();
    $breaks = array();
    foreach ( $raw as $b ) {
        $breaks[] = array(
            'min'        => intval( $b['min'] ),
            'max'        => intval( $b['max'] ),
            'multiplier' => floatval( $b['multiplier'] ),
        );
    }
    update_option( 'sc_quantity_breaks', $breaks );
    wp_send_json_success( 'Quantity breaks saved.' );
}

add_action( 'wp_ajax_sc_save_qty_overrides', 'sc_save_qty_overrides' );
function sc_save_qty_overrides() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $sticker_key = sanitize_text_field( $_POST['sticker_key'] ?? '' );
    if ( empty( $sticker_key ) ) wp_send_json_error( 'No sticker key.' );

    $raw = isset( $_POST['breaks'] ) ? $_POST['breaks'] : array();
    $breaks = array();
    foreach ( $raw as $b ) {
        $breaks[] = array(
            'min'        => intval( $b['min'] ),
            'max'        => intval( $b['max'] ),
            'multiplier' => floatval( $b['multiplier'] ),
        );
    }

    $overrides = get_option( 'sc_quantity_breaks_overrides', array() );
    $overrides[ $sticker_key ] = $breaks;
    update_option( 'sc_quantity_breaks_overrides', $overrides );
    wp_send_json_success( 'Overrides saved for this sticker.' );
}

add_action( 'wp_ajax_sc_remove_qty_overrides', 'sc_remove_qty_overrides' );
function sc_remove_qty_overrides() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $sticker_key = sanitize_text_field( $_POST['sticker_key'] ?? '' );
    if ( empty( $sticker_key ) ) wp_send_json_error( 'No sticker key.' );

    $overrides = get_option( 'sc_quantity_breaks_overrides', array() );
    unset( $overrides[ $sticker_key ] );
    update_option( 'sc_quantity_breaks_overrides', $overrides );
    wp_send_json_success( 'Overrides removed. Default breaks will be used.' );
}
