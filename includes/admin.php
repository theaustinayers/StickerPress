<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin menu & settings page for StickerPress.
 */

add_action( 'admin_menu', 'sc_admin_menu' );
function sc_admin_menu() {
    add_menu_page(
        'StickerPress',
        'StickerPress',
        'manage_options',
        'sticker-creator',
        'sc_admin_page',
        SC_PLUGIN_URL . 'sp.png',
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
        'materials'  => get_option( 'sc_materials', array() ),
        'finishes'   => get_option( 'sc_finishes', array() ),
        'overrides'  => get_option( 'sc_quantity_breaks_overrides', array() ),
        'hidden_stickers' => get_option( 'sc_hidden_stickers', array() ),
    ));
}

/**
 * Render admin page.
 */
function sc_admin_page() {
    $sizes       = get_option( 'sc_sizes', array() );

    // One-time cleanup: strip accumulated backslashes from size labels
    $needs_clean = false;
    foreach ( $sizes as &$sz ) {
        $clean = stripslashes( $sz['label'] );
        if ( $clean !== $sz['label'] ) { $sz['label'] = $clean; $needs_clean = true; }
    }
    unset( $sz );
    if ( $needs_clean ) update_option( 'sc_sizes', $sizes );

    $pricing     = get_option( 'sc_pricing', array() );
    $qty_breaks  = get_option( 'sc_quantity_breaks', array() );
    $qty_overrides = get_option( 'sc_quantity_breaks_overrides', array() );
    $hidden_stickers = get_option( 'sc_hidden_stickers', array() );
    $cut_fees = get_option( 'sc_cut_fees', array() );

    $materials = get_option( 'sc_materials', array() );
    $finishes  = get_option( 'sc_finishes', array() );
    $lam_opts  = array( 'yes', 'no' );

    $wc_active = class_exists( 'WooCommerce' );
    ?>
    <div class="wrap sc-admin-wrap">
        <h1>StickerPress Settings</h1>

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
            <p>Use <code>[sticker_creator]</code> or <code>[sticker_press]</code> to embed the sticker builder on any page or post.</p>
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
                        <th><label for="sc-default-quantity">Default Quantity</label></th>
                        <td>
                            <input type="number" id="sc-default-quantity" min="1" value="<?php echo esc_attr( get_option( 'sc_default_quantity', 50 ) ); ?>" />
                            <p class="description">Pre-filled quantity when a customer first loads the page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Accent Color</th>
                        <td>
                            <?php
                            $acc_mode   = get_option( 'sc_accent_mode', 'solid' );
                            $acc_color1 = get_option( 'sc_accent_color', '#8b3045' );
                            $acc_color2 = get_option( 'sc_accent_color2', '#6e2537' );
                            $acc_angle  = get_option( 'sc_accent_angle', 135 );
                            ?>
                            <label><input type="radio" name="sc_accent_mode" value="solid" <?php checked( $acc_mode, 'solid' ); ?> /> Solid</label>&nbsp;
                            <label><input type="radio" name="sc_accent_mode" value="gradient" <?php checked( $acc_mode, 'gradient' ); ?> /> Gradient</label>
                            <div style="margin-top:8px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:12px;display:block;">Color 1</label>
                                    <input type="color" id="sc-accent-color" value="<?php echo esc_attr( $acc_color1 ); ?>" style="width:60px;height:36px;padding:2px;cursor:pointer;" />
                                    <code id="sc-accent-color-hex"><?php echo esc_html( $acc_color1 ); ?></code>
                                </div>
                                <div class="sc-gradient-fields" style="<?php echo $acc_mode === 'solid' ? 'display:none;' : ''; ?>">
                                    <label style="font-size:12px;display:block;">Color 2</label>
                                    <input type="color" id="sc-accent-color2" value="<?php echo esc_attr( $acc_color2 ); ?>" style="width:60px;height:36px;padding:2px;cursor:pointer;" />
                                    <code id="sc-accent-color2-hex"><?php echo esc_html( $acc_color2 ); ?></code>
                                </div>
                                <div class="sc-gradient-fields" style="<?php echo $acc_mode === 'solid' ? 'display:none;' : ''; ?>">
                                    <label style="font-size:12px;display:block;">Angle (°)</label>
                                    <input type="number" id="sc-accent-angle" min="0" max="360" value="<?php echo esc_attr( $acc_angle ); ?>" style="width:70px;" />
                                </div>
                                <div id="sc-accent-preview" style="width:80px;height:36px;border-radius:6px;border:1px solid #ccc;"></div>
                            </div>
                            <p class="description">Button and highlight color. Gradient uses both colors at the specified angle.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Hover Color</th>
                        <td>
                            <?php
                            $hov_mode   = get_option( 'sc_hover_mode', 'solid' );
                            $hov_color1 = get_option( 'sc_hover_color', '#6e2537' );
                            $hov_color2 = get_option( 'sc_hover_color2', '#4a1525' );
                            $hov_angle  = get_option( 'sc_hover_angle', 135 );
                            ?>
                            <label><input type="radio" name="sc_hover_mode" value="solid" <?php checked( $hov_mode, 'solid' ); ?> /> Solid</label>&nbsp;
                            <label><input type="radio" name="sc_hover_mode" value="gradient" <?php checked( $hov_mode, 'gradient' ); ?> /> Gradient</label>
                            <div style="margin-top:8px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:12px;display:block;">Color 1</label>
                                    <input type="color" id="sc-hover-color" value="<?php echo esc_attr( $hov_color1 ); ?>" style="width:60px;height:36px;padding:2px;cursor:pointer;" />
                                    <code id="sc-hover-color-hex"><?php echo esc_html( $hov_color1 ); ?></code>
                                </div>
                                <div class="sc-hover-gradient-fields" style="<?php echo $hov_mode === 'solid' ? 'display:none;' : ''; ?>">
                                    <label style="font-size:12px;display:block;">Color 2</label>
                                    <input type="color" id="sc-hover-color2" value="<?php echo esc_attr( $hov_color2 ); ?>" style="width:60px;height:36px;padding:2px;cursor:pointer;" />
                                    <code id="sc-hover-color2-hex"><?php echo esc_html( $hov_color2 ); ?></code>
                                </div>
                                <div class="sc-hover-gradient-fields" style="<?php echo $hov_mode === 'solid' ? 'display:none;' : ''; ?>">
                                    <label style="font-size:12px;display:block;">Angle (°)</label>
                                    <input type="number" id="sc-hover-angle" min="0" max="360" value="<?php echo esc_attr( $hov_angle ); ?>" style="width:70px;" />
                                </div>
                                <div id="sc-hover-preview" style="width:80px;height:36px;border-radius:6px;border:1px solid #ccc;"></div>
                            </div>
                            <p class="description">Color shown when buttons are hovered.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sc-safe-area-percent">Safe Area Default (%)</label></th>
                        <td>
                            <input type="number" id="sc-safe-area-percent" min="10" max="200" value="<?php echo esc_attr( get_option( 'sc_safe_area_percent', 100 ) ); ?>" />
                            <p class="description">Default safe area size as a percentage of the sticker dimensions (10% – 200%).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sc-disclaimer-text">Preview Disclaimer Text</label></th>
                        <td>
                            <textarea id="sc-disclaimer-text" rows="3" style="width:100%;max-width:500px;"><?php echo esc_textarea( get_option( 'sc_disclaimer_text', 'This preview is for reference only and may not reflect the exact final product. Colors, proportions, and finishes may vary slightly in print.' ) ); ?></textarea>
                            <p class="description">Shown below the live preview area.</p>
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
                            <th>Min Qty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-sizes-tbody">
                    <?php foreach ( $sizes as $i => $size ) : ?>
                        <tr data-index="<?php echo esc_attr( $i ); ?>">
                            <td><input type="text" name="sizes[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $size['label'] ); ?>" /></td>
                            <td><input type="number" step="0.25" min="0.5" name="sizes[<?php echo $i; ?>][width]" value="<?php echo esc_attr( $size['width'] ); ?>" /></td>
                            <td><input type="number" step="0.25" min="0.5" name="sizes[<?php echo $i; ?>][height]" value="<?php echo esc_attr( $size['height'] ); ?>" /></td>
                            <td><input type="number" min="0" name="sizes[<?php echo $i; ?>][min_qty]" value="<?php echo esc_attr( $size['min_qty'] ?? '' ); ?>" placeholder="Global" style="width:70px;" /></td>
                            <td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-size">Remove</button></td>
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

        <!-- MATERIALS -->
        <div class="sc-admin-section">
            <h2>Materials</h2>
            <form id="sc-materials-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_materials_nonce_field' ); ?>
                <table class="widefat sc-materials-table">
                    <thead>
                        <tr>
                            <th>Slug (internal key)</th>
                            <th>Display Label</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-materials-tbody">
                    <?php foreach ( $materials as $i => $m ) : ?>
                        <tr data-index="<?php echo esc_attr( $i ); ?>">
                            <td><input type="text" name="materials[<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $m['slug'] ); ?>" /></td>
                            <td><input type="text" name="materials[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $m['label'] ); ?>" /></td>
                            <td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-material">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-material">+ Add Material</button>
                    <button type="submit" class="button button-primary">Save Materials</button>
                </p>
            </form>
        </div>

        <!-- FINISHES -->
        <div class="sc-admin-section">
            <h2>Finishes</h2>
            <form id="sc-finishes-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_finishes_nonce_field' ); ?>
                <table class="widefat sc-finishes-table">
                    <thead>
                        <tr>
                            <th>Slug (internal key)</th>
                            <th>Display Label</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sc-finishes-tbody">
                    <?php foreach ( $finishes as $i => $f ) : ?>
                        <tr data-index="<?php echo esc_attr( $i ); ?>">
                            <td><input type="text" name="finishes[<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $f['slug'] ); ?>" /></td>
                            <td><input type="text" name="finishes[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>" /></td>
                            <td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-remove-finish">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="sc-add-finish">+ Add Finish</button>
                    <button type="submit" class="button button-primary">Save Finishes</button>
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
                                        <option value="<?php echo esc_attr( $m['slug'] ); ?>" <?php selected( $mat, $m['slug'] ); ?>><?php echo esc_html( $m['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="pricing_rows[<?php echo $row; ?>][finish]">
                                    <?php foreach ( $finishes as $f ) : ?>
                                        <option value="<?php echo esc_attr( $f['slug'] ); ?>" <?php selected( $fin, $f['slug'] ); ?>><?php echo esc_html( $f['label'] ); ?></option>
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
                            <td><button type="button" class="button sc-move-up" title="Move up">▲</button> <button type="button" class="button sc-move-down" title="Move down">▼</button> <button type="button" class="button sc-duplicate-pricing" title="Duplicate">⧉</button> <button type="button" class="button sc-remove-pricing">Remove</button></td>
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

        <!-- CUT TYPE FEES -->
        <div class="sc-admin-section">
            <h2>Cut Type Fees</h2>
            <p class="description">Optional per-cut-type surcharge applied to the base sticker price. Set amount to 0 to disable.</p>
            <form id="sc-cut-fees-form">
                <?php wp_nonce_field( 'sc_admin_nonce', 'sc_cut_fees_nonce_field' ); ?>
                <table class="widefat sc-cut-fees-table">
                    <thead>
                        <tr>
                            <th>Cut Type</th>
                            <th>Fee Amount</th>
                            <th>Fee Type</th>
                        </tr>
                    </thead>
                    <tbody id="sc-cut-fees-tbody">
                        <?php
                        $cut_types = array(
                            'square'       => 'Square',
                            'round'        => 'Round / Circle',
                            'rounded-rect' => 'Rounded Rectangle',
                            'die-cut'      => 'Die Cut',
                        );
                        foreach ( $cut_types as $slug => $label ) :
                            $fee = isset( $cut_fees[ $slug ] ) ? $cut_fees[ $slug ] : array( 'amount' => 0, 'type' => 'flat' );
                        ?>
                        <tr data-cut="<?php echo esc_attr( $slug ); ?>">
                            <td><strong><?php echo esc_html( $label ); ?></strong></td>
                            <td><input type="number" step="0.01" min="0" class="sc-cut-fee-amount" value="<?php echo esc_attr( $fee['amount'] ); ?>" style="width:100px;" /></td>
                            <td>
                                <select class="sc-cut-fee-type">
                                    <option value="flat" <?php selected( $fee['type'] ?? 'flat', 'flat' ); ?>>$ Flat Rate</option>
                                    <option value="percent" <?php selected( $fee['type'] ?? 'flat', 'percent' ); ?>>% Percentage</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary">Save Cut Type Fees</button></p>
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

        <!-- PROFIT CALCULATOR -->
        <div class="sc-admin-section">
            <h2>Profit Calculator</h2>
            <p class="description">Estimate how many stickers fit on a roll, your cost per sticker, and total profit.</p>
            <table class="form-table" style="max-width:700px;">
                <tr>
                    <th>Roll Width (inches)</th>
                    <td><input type="number" id="sc-calc-roll-w" step="0.1" min="1" value="13" style="width:100px;" /></td>
                </tr>
                <tr>
                    <th>Roll Length (inches)</th>
                    <td>
                        <input type="number" id="sc-calc-roll-l" step="0.1" min="1" value="1200" style="width:100px;" />
                        <span class="description" style="margin-left:8px;">(100 ft = 1200 in)</span>
                    </td>
                </tr>
                <tr>
                    <th>Roll Cost ($)</th>
                    <td><input type="number" id="sc-calc-roll-cost" step="0.01" min="0" value="50.00" style="width:100px;" /></td>
                </tr>
                <tr>
                    <th>Sticker Size</th>
                    <td>
                        <select id="sc-calc-size">
                            <?php foreach ( $sizes as $i => $s ) : ?>
                                <option value="<?php echo esc_attr( $i ); ?>" data-w="<?php echo esc_attr( $s['width'] ); ?>" data-h="<?php echo esc_attr( $s['height'] ); ?>">
                                    <?php echo esc_html( $s['label'] ); ?> (<?php echo esc_html( $s['width'] ); ?>" &times; <?php echo esc_html( $s['height'] ); ?>")
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Spacing / Gap (inches)</th>
                    <td><input type="number" id="sc-calc-gap" step="0.0625" min="0" value="0.125" style="width:100px;" /></td>
                </tr>
                <tr>
                    <th>Profit Margin</th>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <label><input type="radio" name="sc_calc_margin_type" value="percent" checked /> %</label>
                            <label><input type="radio" name="sc_calc_margin_type" value="dollar" /> $ per sticker</label>
                            <input type="number" id="sc-calc-margin" step="0.01" min="0" value="50" style="width:100px;" />
                        </div>
                    </td>
                </tr>
            </table>

            <div id="sc-calc-results" style="margin-top:20px;padding:20px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;max-width:700px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Stickers per Row</span>
                        <div id="sc-calc-per-row" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Total Rows on Roll</span>
                        <div id="sc-calc-rows" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Total Stickers</span>
                        <div id="sc-calc-total" style="font-size:1.3rem;font-weight:700;color:#1565c0;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Cost per Sticker</span>
                        <div id="sc-calc-cost-each" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Sell Price per Sticker</span>
                        <div id="sc-calc-price-each" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Profit per Sticker</span>
                        <div id="sc-calc-profit-each" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div style="border-top:2px solid #ddd;padding-top:12px;">
                        <span style="color:#666;font-size:0.85rem;">Total Roll Cost</span>
                        <div id="sc-calc-total-cost" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div style="border-top:2px solid #ddd;padding-top:12px;">
                        <span style="color:#666;font-size:0.85rem;">Total Revenue</span>
                        <div id="sc-calc-revenue" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Total Profit</span>
                        <div id="sc-calc-total-profit" style="font-size:1.5rem;font-weight:800;">—</div>
                    </div>
                    <div>
                        <span style="color:#666;font-size:0.85rem;">Profit Margin %</span>
                        <div id="sc-calc-margin-pct" style="font-size:1.3rem;font-weight:700;">—</div>
                    </div>
                    <div style="grid-column:1/-1;border-top:2px solid #ddd;padding-top:12px;">
                        <span style="color:#666;font-size:0.85rem;">Recommended Unit Price</span>
                        <div id="sc-calc-recommended" style="font-size:1.3rem;font-weight:700;color:#1565c0;">—</div>
                    </div>
                    <div style="grid-column:1/-1;border-top:2px solid #ddd;padding-top:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                        <div style="background:#e8f5e9;border-radius:8px;padding:14px;text-align:center;">
                            <span style="color:#2e7d32;font-weight:700;font-size:0.9rem;">Low Profit — 20%</span>
                            <div id="sc-calc-low-price" style="font-size:1.2rem;font-weight:700;margin-top:6px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">Price per Unit</span>
                            <div id="sc-calc-low-profit" style="font-size:1rem;font-weight:600;margin-top:4px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">(Maximum Profit)</span>
                        </div>
                        <div style="background:#fff3e0;border-radius:8px;padding:14px;text-align:center;">
                            <span style="color:#e65100;font-weight:700;font-size:0.9rem;">Medium Profit — 45%</span>
                            <div id="sc-calc-med-price" style="font-size:1.2rem;font-weight:700;margin-top:6px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">Price per Unit</span>
                            <div id="sc-calc-med-profit" style="font-size:1rem;font-weight:600;margin-top:4px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">(Maximum Profit)</span>
                        </div>
                        <div style="background:#e3f2fd;border-radius:8px;padding:14px;text-align:center;">
                            <span style="color:#1565c0;font-weight:700;font-size:0.9rem;">High Profit — 75%</span>
                            <div id="sc-calc-high-price" style="font-size:1.2rem;font-weight:700;margin-top:6px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">Price per Unit</span>
                            <div id="sc-calc-high-profit" style="font-size:1rem;font-weight:600;margin-top:4px;">—</div>
                            <span style="color:#666;font-size:0.75rem;">(Maximum Profit)</span>
                        </div>
                    </div>
                </div>
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
    $default_qty = max( 1, intval( $_POST['default_quantity'] ?? 50 ) );

    // Accent color settings
    $accent_mode = in_array( $_POST['accent_mode'] ?? 'solid', array( 'solid', 'gradient' ), true ) ? $_POST['accent_mode'] : 'solid';
    $accent = sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#8b3045' ) );
    if ( ! $accent ) $accent = '#8b3045';
    $accent2 = sanitize_hex_color( wp_unslash( $_POST['accent_color2'] ?? '#6e2537' ) );
    if ( ! $accent2 ) $accent2 = '#6e2537';
    $accent_angle = max( 0, min( 360, intval( $_POST['accent_angle'] ?? 135 ) ) );

    // Hover color settings
    $hover_mode = in_array( $_POST['hover_mode'] ?? 'solid', array( 'solid', 'gradient' ), true ) ? $_POST['hover_mode'] : 'solid';
    $hover_color = sanitize_hex_color( wp_unslash( $_POST['hover_color'] ?? '#6e2537' ) );
    if ( ! $hover_color ) $hover_color = '#6e2537';
    $hover_color2 = sanitize_hex_color( wp_unslash( $_POST['hover_color2'] ?? '#4a1525' ) );
    if ( ! $hover_color2 ) $hover_color2 = '#4a1525';
    $hover_angle = max( 0, min( 360, intval( $_POST['hover_angle'] ?? 135 ) ) );

    $safe_pct = max( 10, min( 200, intval( $_POST['safe_area_percent'] ?? 100 ) ) );
    $disclaimer = sanitize_textarea_field( wp_unslash( $_POST['disclaimer_text'] ?? '' ) );

    update_option( 'sc_min_quantity', $min_qty );
    update_option( 'sc_default_quantity', $default_qty );
    update_option( 'sc_accent_mode', $accent_mode );
    update_option( 'sc_accent_color', $accent );
    update_option( 'sc_accent_color2', $accent2 );
    update_option( 'sc_accent_angle', $accent_angle );
    update_option( 'sc_hover_mode', $hover_mode );
    update_option( 'sc_hover_color', $hover_color );
    update_option( 'sc_hover_color2', $hover_color2 );
    update_option( 'sc_hover_angle', $hover_angle );
    update_option( 'sc_safe_area_percent', $safe_pct );
    update_option( 'sc_disclaimer_text', $disclaimer );
    wp_send_json_success( 'Global settings saved.' );
}

add_action( 'wp_ajax_sc_save_sizes', 'sc_save_sizes' );
function sc_save_sizes() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['sizes'] ) ? wp_unslash( $_POST['sizes'] ) : array();
    $sizes = array();
    foreach ( $raw as $s ) {
        $sizes[] = array(
            'label'   => sanitize_text_field( $s['label'] ),
            'width'   => floatval( $s['width'] ),
            'height'  => floatval( $s['height'] ),
            'min_qty' => max( 0, intval( $s['min_qty'] ?? 0 ) ),
        );
    }
    update_option( 'sc_sizes', $sizes );
    wp_send_json_success( 'Sizes saved.' );
}

add_action( 'wp_ajax_sc_save_materials', 'sc_save_materials' );
function sc_save_materials() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['materials'] ) ? wp_unslash( $_POST['materials'] ) : array();
    $materials = array();
    foreach ( $raw as $m ) {
        $slug = sanitize_title( $m['slug'] );
        $label = sanitize_text_field( $m['label'] );
        if ( $slug && $label ) {
            $materials[] = array( 'slug' => $slug, 'label' => $label );
        }
    }
    update_option( 'sc_materials', $materials );
    wp_send_json_success( 'Materials saved.' );
}

add_action( 'wp_ajax_sc_save_finishes', 'sc_save_finishes' );
function sc_save_finishes() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['finishes'] ) ? wp_unslash( $_POST['finishes'] ) : array();
    $finishes = array();
    foreach ( $raw as $f ) {
        $slug = sanitize_title( $f['slug'] );
        $label = sanitize_text_field( $f['label'] );
        if ( $slug && $label ) {
            $finishes[] = array( 'slug' => $slug, 'label' => $label );
        }
    }
    update_option( 'sc_finishes', $finishes );
    wp_send_json_success( 'Finishes saved.' );
}

add_action( 'wp_ajax_sc_save_pricing', 'sc_save_pricing' );
function sc_save_pricing() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['pricing_rows'] ) ? wp_unslash( $_POST['pricing_rows'] ) : array();
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

add_action( 'wp_ajax_sc_save_cut_fees', 'sc_save_cut_fees' );
function sc_save_cut_fees() {
    check_ajax_referer( 'sc_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $raw = isset( $_POST['cut_fees'] ) ? wp_unslash( $_POST['cut_fees'] ) : array();
    $valid_cuts = array( 'square', 'round', 'rounded-rect', 'die-cut' );
    $fees = array();
    foreach ( $raw as $cut => $data ) {
        $cut = sanitize_text_field( $cut );
        if ( ! in_array( $cut, $valid_cuts, true ) ) continue;
        $fees[ $cut ] = array(
            'amount' => max( 0, floatval( $data['amount'] ?? 0 ) ),
            'type'   => in_array( $data['type'] ?? 'flat', array( 'flat', 'percent' ), true ) ? $data['type'] : 'flat',
        );
    }
    update_option( 'sc_cut_fees', $fees );
    wp_send_json_success( 'Cut type fees saved.' );
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
