<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend shortcode & asset loading for StickerPress.
 */

add_action( 'wp_enqueue_scripts', 'sc_maybe_enqueue' );
function sc_maybe_enqueue() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'sticker_creator' ) || has_shortcode( $post->post_content, 'sticker_press' ) ) ) {
        sc_enqueue_assets();
    }
}

function sc_enqueue_assets() {
    wp_enqueue_style( 'sc-front-css', SC_PLUGIN_URL . 'assets/css/frontend.css', array(), SC_VERSION );
    wp_enqueue_script( 'sc-front-js', SC_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), SC_VERSION, true );

    $sizes          = get_option( 'sc_sizes', array() );
    $materials      = get_option( 'sc_materials', array() );
    $finishes       = get_option( 'sc_finishes', array() );
    $pricing        = get_option( 'sc_pricing', array() );
    $qty_breaks     = get_option( 'sc_quantity_breaks', array() );
    $qty_overrides  = get_option( 'sc_quantity_breaks_overrides', array() );
    $hidden_stickers = get_option( 'sc_hidden_stickers', array() );
    $min_quantity    = max( 1, (int) get_option( 'sc_min_quantity', 1 ) );
    $default_qty     = max( 1, (int) get_option( 'sc_default_quantity', 50 ) );
    $safe_area_pct   = max( 10, min( 200, (int) get_option( 'sc_safe_area_percent', 100 ) ) );

    // Filter out hidden stickers from pricing sent to frontend
    $visible_pricing = array();
    foreach ( $pricing as $key => $price ) {
        if ( ! in_array( $key, $hidden_stickers, true ) ) {
            $visible_pricing[ $key ] = $price;
        }
    }

    $wc_active = class_exists( 'WooCommerce' );

    wp_localize_script( 'sc-front-js', 'scData', array(
        'ajax_url'           => admin_url( 'admin-ajax.php' ),
        'nonce'              => wp_create_nonce( 'sc_front_nonce' ),
        'sizes'              => $sizes,
        'materials'          => $materials,
        'finishes'           => $finishes,
        'pricing'            => $visible_pricing,
        'qty_breaks'         => $qty_breaks,
        'qty_overrides'      => (object) $qty_overrides,
        'cut_fees'           => (object) get_option( 'sc_cut_fees', array() ),
        'max_upload'         => wp_max_upload_size(),
        'wc_active'          => $wc_active,
        'cart_url'           => $wc_active ? wc_get_cart_url() : '',
        'min_quantity'       => $min_quantity,
        'default_quantity'   => $default_qty,
        'safe_area_percent'  => $safe_area_pct,
    ));
}

add_shortcode( 'sticker_creator', 'sc_shortcode_render' );
add_shortcode( 'sticker_press', 'sc_shortcode_render' );
function sc_shortcode_render( $atts ) {
    sc_enqueue_assets();

    $sizes     = get_option( 'sc_sizes', array() );
    $materials = get_option( 'sc_materials', array() );
    $finishes  = get_option( 'sc_finishes', array() );
    $min_quantity    = max( 1, (int) get_option( 'sc_min_quantity', 1 ) );
    $default_qty     = max( $min_quantity, (int) get_option( 'sc_default_quantity', 50 ) );
    $disclaimer_text = get_option( 'sc_disclaimer_text', 'This preview is for reference only and may not reflect the exact final product. Colors, proportions, and finishes may vary slightly in print.' );
    $safe_area_pct   = max( 10, min( 200, (int) get_option( 'sc_safe_area_percent', 100 ) ) );

    // Accent color - compute CSS custom property overrides
    $accent_mode = get_option( 'sc_accent_mode', 'solid' );
    $accent = sanitize_hex_color( get_option( 'sc_accent_color', '#8b3045' ) );
    if ( ! $accent ) $accent = '#8b3045';
    $accent2 = sanitize_hex_color( get_option( 'sc_accent_color2', '#6e2537' ) );
    if ( ! $accent2 ) $accent2 = '#6e2537';
    $accent_angle = (int) get_option( 'sc_accent_angle', 135 );

    $hover_mode = get_option( 'sc_hover_mode', 'solid' );
    $hover_color = sanitize_hex_color( get_option( 'sc_hover_color', '#6e2537' ) );
    if ( ! $hover_color ) $hover_color = '#6e2537';
    $hover_color2 = sanitize_hex_color( get_option( 'sc_hover_color2', '#4a1525' ) );
    if ( ! $hover_color2 ) $hover_color2 = '#4a1525';
    $hover_angle = (int) get_option( 'sc_hover_angle', 135 );

    $r = hexdec( substr( $accent, 1, 2 ) );
    $g = hexdec( substr( $accent, 3, 2 ) );
    $b = hexdec( substr( $accent, 5, 2 ) );

    // Build background values (solid or gradient)
    if ( $accent_mode === 'gradient' ) {
        $accent_bg = sprintf( 'linear-gradient(%ddeg, %s, %s)', $accent_angle, $accent, $accent2 );
    } else {
        $accent_bg = $accent;
    }
    if ( $hover_mode === 'gradient' ) {
        $hover_bg = sprintf( 'linear-gradient(%ddeg, %s, %s)', $hover_angle, $hover_color, $hover_color2 );
    } else {
        $hover_bg = $hover_color;
    }

    // Light tint derived from accent color 1
    $light = sprintf( '#%02x%02x%02x', min( 255, 245 + (int)( ( $r - 200 ) * 0.05 ) ), min( 255, 234 + (int)( ( $g - 200 ) * 0.05 ) ), min( 255, 238 + (int)( ( $b - 200 ) * 0.05 ) ) );

    ob_start();
    ?>
    <style>
    .sc-wrap {
        --sc-accent: <?php echo esc_attr( $accent ); ?>;
        --sc-accent-bg: <?php echo $accent_bg; ?>;
        --sc-accent-hover: <?php echo esc_attr( $hover_color ); ?>;
        --sc-accent-hover-bg: <?php echo $hover_bg; ?>;
        --sc-accent-light: <?php echo esc_attr( $light ); ?>;
        --sc-accent-shadow-15: rgba(<?php echo "$r, $g, $b"; ?>, 0.15);
        --sc-accent-shadow-20: rgba(<?php echo "$r, $g, $b"; ?>, 0.2);
    }
    </style>
    <div id="sc-sticker-creator" class="sc-wrap">

        <!-- Step 1: Upload -->
        <div class="sc-step sc-step-upload sc-active" data-step="1">
            <h2 class="sc-step-title">1. Upload Your Artwork</h2>
            <div class="sc-upload-zone" id="sc-upload-zone">
                <div class="sc-upload-inner">
                    <svg class="sc-upload-icon" viewBox="0 0 24 24" width="48" height="48"><path d="M19.35 10.04A7.49 7.49 0 0012 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 000 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z" fill="currentColor"/></svg>
                    <p>Drag &amp; drop your artwork here</p>
                    <p class="sc-upload-or">or</p>
                    <label class="sc-btn sc-btn-outline">
                        Browse Files
                        <input type="file" id="sc-file-input" accept="image/png,image/jpeg,image/svg+xml,image/webp,application/pdf,.ai,.psd" hidden />
                    </label>
                    <p class="sc-upload-hint">PNG, JPG, SVG, WebP, PDF, AI, or PSD &bull; Max <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></p>
                    <p class="sc-upload-disclaimer">For best results, upload a <strong>transparent PNG</strong>, PDF, AI, or PSD file.</p>
                </div>
            </div>
            <div class="sc-upload-preview-wrap" id="sc-upload-preview-wrap" style="display:none;">
                <img id="sc-uploaded-img" src="" alt="Uploaded artwork" />
                <div class="sc-upload-actions">
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-outline" id="sc-remove-bg">Remove Background</button>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-danger" id="sc-remove-upload">Remove</button>
                </div>
                <p class="sc-bg-status" id="sc-bg-status" style="display:none;"></p>
            </div>
        </div>

        <!-- Step 2: Cut Type -->
        <div class="sc-step sc-step-cut" data-step="2">
            <h2 class="sc-step-title">2. Choose Cut Type</h2>
            <div class="sc-cut-options">
                <label class="sc-cut-option sc-selected" data-cut="square">
                    <input type="radio" name="sc_cut" value="square" checked hidden />
                    <div class="sc-cut-demo sc-cut-square"></div>
                    <span>Square</span>
                </label>
                <label class="sc-cut-option" data-cut="round">
                    <input type="radio" name="sc_cut" value="round" hidden />
                    <div class="sc-cut-demo sc-cut-round"></div>
                    <span>Round / Circle</span>
                </label>
                <label class="sc-cut-option" data-cut="rounded-rect">
                    <input type="radio" name="sc_cut" value="rounded-rect" hidden />
                    <div class="sc-cut-demo sc-cut-rounded-rect"></div>
                    <span>Rounded Rectangle</span>
                </label>
                <label class="sc-cut-option" data-cut="die-cut">
                    <input type="radio" name="sc_cut" value="die-cut" hidden />
                    <div class="sc-cut-demo sc-cut-die-cut"></div>
                    <span>Die Cut</span>
                </label>
            </div>
        </div>

        <!-- Step 3: Options -->
        <div class="sc-step sc-step-options" data-step="3">
            <h2 class="sc-step-title">3. Select Options</h2>
            <div class="sc-options-grid">
                <div class="sc-option-group">
                    <label>Size</label>
                    <select id="sc-size">
                        <?php foreach ( $sizes as $i => $s ) : ?>
                            <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $s['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-option-group">
                    <label>Material</label>
                    <select id="sc-material">
                        <?php foreach ( $materials as $m ) : ?>
                            <option value="<?php echo esc_attr( $m['slug'] ); ?>"><?php echo esc_html( $m['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-option-group">
                    <label>Finish</label>
                    <div class="sc-finish-options">
                        <?php foreach ( $finishes as $fi => $f ) : ?>
                        <label class="sc-finish-option<?php echo $fi === 0 ? ' sc-selected' : ''; ?>" data-finish="<?php echo esc_attr( $f['slug'] ); ?>">
                            <input type="radio" name="sc_finish" value="<?php echo esc_attr( $f['slug'] ); ?>" <?php echo $fi === 0 ? 'checked' : ''; ?> hidden />
                            <div class="sc-finish-demo sc-finish-<?php echo esc_attr( $f['slug'] ); ?>">
                                <?php if ( $f['slug'] === 'glossy' ) : ?><div class="sc-shine-effect"></div><?php endif; ?>
                            </div>
                            <span><?php echo esc_html( $f['label'] ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sc-option-group sc-lamination-group">
                    <label>Lamination</label>
                    <div class="sc-lam-static" style="display:none;padding:10px 16px;background:#f5f5f5;border:2px solid #e0e0e0;border-radius:12px;font-weight:600;color:#666;font-size:0.9rem;">Non-Laminated</div>
                    <div class="sc-lamination-options">
                        <label class="sc-lam-option" data-lam="yes">
                            <input type="radio" name="sc_laminated" value="yes" hidden />
                            Laminated
                        </label>
                        <label class="sc-lam-option sc-selected" data-lam="no">
                            <input type="radio" name="sc_laminated" value="no" checked hidden />
                            <span>Non-Laminated</span>
                        </label>
                    </div>
                </div>

                <div class="sc-option-group">
                    <label>Quantity</label>
                    <input type="number" id="sc-quantity" value="<?php echo esc_attr( $default_qty ); ?>" min="<?php echo esc_attr( $min_quantity ); ?>" max="10000" />
                    <p class="sc-min-qty-msg" id="sc-min-qty-msg" style="display:none;"></p>
                </div>
            </div>
        </div>

        <!-- Live Preview & Pricing -->
        <div class="sc-preview-pricing">
            <div class="sc-live-preview">
                <h2 class="sc-step-title">Live Preview</h2>
                <div class="sc-preview-container" id="sc-preview-container">
                    <div class="sc-preview-artwork" id="sc-preview-artwork">
                        <div class="sc-cut-outline" id="sc-cut-outline"></div>
                        <div class="sc-sticker-backing"></div>
                        <img id="sc-preview-img" src="" alt="Preview" />
                        <div class="sc-preview-shine" id="sc-preview-shine"></div>
                        <div class="sc-preview-laminate" id="sc-preview-laminate"></div>
                    </div>
                    <!-- Die-cut outline canvas (drawn dynamically) -->
                    <canvas id="sc-diecut-canvas" style="display:none;"></canvas>
                    <div class="sc-safe-area" id="sc-safe-area">
                        <span class="sc-safe-area-label">SAFE AREA</span>
                    </div>
                    <p class="sc-preview-placeholder" id="sc-preview-placeholder">Upload artwork to see preview</p>
                </div>
                <div class="sc-editor-toolbar" id="sc-editor-toolbar" style="display:none;">
                    <div class="sc-editor-control">
                        <label><svg viewBox="0 0 24 24" width="14" height="14"><path d="M17.66 7.93L12 2.27 6.34 7.93a8 8 0 1011.32 0zM12 19.59c-1.6 0-3.11-.62-4.24-1.76A5.95 5.95 0 016 13.59c0-1.6.63-3.11 1.76-4.24L12 5.1l4.24 4.25A5.95 5.95 0 0118 13.59c0 1.6-.63 3.11-1.76 4.24A5.95 5.95 0 0112 19.59z" fill="currentColor"/></svg> BG Tolerance</label>
                        <input type="range" id="sc-bg-tolerance" min="5" max="180" value="120" />
                        <span id="sc-bg-tolerance-val">120</span>
                    </div>
                    <div class="sc-editor-control">
                        <label><svg viewBox="0 0 24 24" width="14" height="14"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1114 9.5 4.49 4.49 0 019.5 14zM12 10h-2v2H9v-2H7V9h2V7h1v2h2v1z" fill="currentColor"/></svg> Scale</label>
                        <input type="range" id="sc-edit-scale" min="10" max="200" value="100" />
                        <span id="sc-edit-scale-val">100%</span>
                    </div>
                    <div class="sc-editor-control">
                        <label><svg viewBox="0 0 24 24" width="14" height="14"><path d="M7.34 6.41L.86 12.9l6.49 6.48 6.49-6.48-6.5-6.49zM3.69 12.9l3.66-3.66L11 12.9l-3.66 3.66-3.65-3.66zm15.67-6.26A8.95 8.95 0 0013 4V1l-4 4 4 4V6c3.87 0 7.19 2.77 7.88 6.57a8.01 8.01 0 01-2.52 7.07l1.41 1.41A9.97 9.97 0 0023 13c0-2.74-1.11-5.22-2.64-6.36z" fill="currentColor"/></svg> Rotate</label>
                        <input type="range" id="sc-edit-rotate" min="-180" max="180" value="0" />
                        <span id="sc-edit-rotate-val">0°</span>
                    </div>
                    <div class="sc-editor-control sc-editor-control-border">
                        <label><svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-4.86 8.86l-3 3.87L9 13.14 6 17h12l-3.86-5.14z" fill="currentColor"/></svg> White Border</label>
                        <input type="range" id="sc-border-level" min="0" max="5" value="2" step="1" />
                        <span id="sc-border-level-val">Level 2</span>
                    </div>
                    <button type="button" class="sc-btn sc-btn-sm sc-btn-outline" id="sc-edit-reset">Reset</button>
                </div>
                <p class="sc-preview-disclaimer"><?php echo esc_html( $disclaimer_text ); ?></p>
                <p class="sc-safe-area-hint"><span class="sc-safe-area-swatch"></span> Keep important artwork within the <strong>safe area</strong> for best print results.</p>
            </div>

            <div class="sc-pricing-panel">
                <h2 class="sc-step-title">Pricing</h2>
                <div class="sc-price-breakdown">
                    <div class="sc-price-row">
                        <span>Size:</span>
                        <span id="sc-price-size">-</span>
                    </div>
                    <div class="sc-price-row">
                        <span>Material:</span>
                        <span id="sc-price-material">-</span>
                    </div>
                    <div class="sc-price-row">
                        <span>Finish:</span>
                        <span id="sc-price-finish">-</span>
                    </div>
                    <div class="sc-price-row">
                        <span>Lamination:</span>
                        <span id="sc-price-lam">-</span>
                    </div>
                    <div class="sc-price-row">
                        <span>Cut Type:</span>
                        <span id="sc-price-cut">-</span>
                    </div>
                    <hr />
                    <div class="sc-price-row">
                        <span>Price per sticker:</span>
                        <span id="sc-price-each">$0.00</span>
                    </div>
                    <div class="sc-price-row">
                        <span>Quantity:</span>
                        <span id="sc-price-qty">50</span>
                    </div>
                    <div class="sc-price-row sc-price-discount" id="sc-discount-row" style="display:none;">
                        <span>Volume discount:</span>
                        <span id="sc-price-discount">0%</span>
                    </div>
                    <hr />
                    <div class="sc-price-row sc-price-total-row">
                        <span>Total:</span>
                        <span id="sc-price-total" class="sc-price-total">$0.00</span>
                    </div>
                </div>
                <button type="button" class="sc-btn sc-btn-primary sc-btn-lg" id="sc-add-to-cart" disabled>
                    Add to Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Proof Approval Modal -->
    <div class="sc-proof-overlay" id="sc-proof-overlay" style="display:none;">
        <div class="sc-proof-modal">
            <h2 class="sc-proof-title">Accept Your Proof</h2>
            <p class="sc-proof-subtitle">Please review your sticker before adding to cart.</p>
            <div class="sc-proof-preview">
                <div class="sc-proof-image-wrap" id="sc-proof-image-wrap">
                    <img id="sc-proof-img" src="" alt="Sticker proof" />
                    <!-- Width measurement (top) -->
                    <div class="sc-proof-ruler sc-proof-ruler-w" id="sc-proof-ruler-w">
                        <span class="sc-ruler-tick sc-ruler-tick-l"></span>
                        <span class="sc-ruler-line"></span>
                        <span class="sc-ruler-label" id="sc-proof-dim-w"></span>
                        <span class="sc-ruler-line"></span>
                        <span class="sc-ruler-tick sc-ruler-tick-r"></span>
                    </div>
                    <!-- Height measurement (right) -->
                    <div class="sc-proof-ruler sc-proof-ruler-h" id="sc-proof-ruler-h">
                        <span class="sc-ruler-tick sc-ruler-tick-t"></span>
                        <span class="sc-ruler-line"></span>
                        <span class="sc-ruler-label" id="sc-proof-dim-h"></span>
                        <span class="sc-ruler-line"></span>
                        <span class="sc-ruler-tick sc-ruler-tick-b"></span>
                    </div>
                </div>
            </div>
            <div class="sc-proof-resolution-warning" id="sc-proof-res-warning" style="display:none;">
                <span class="sc-warning-icon">&#9888;</span>
                <span><strong>Low Resolution Warning:</strong> Your image may appear blurry or pixelated when printed at this size. For best results, please upload a higher resolution source file.</span>
            </div>
            <div class="sc-proof-details" id="sc-proof-details"></div>
            <div class="sc-proof-actions">
                <button type="button" class="sc-btn sc-btn-primary sc-btn-lg" id="sc-proof-accept">Accept Proof</button>
                <button type="button" class="sc-btn sc-btn-outline sc-btn-lg" id="sc-proof-reject">Back to Editor</button>
            </div>
            <p class="sc-proof-disclaimer">By clicking <strong>Accept Proof</strong>, you confirm that you have reviewed the artwork, dimensions, and options shown above. Final printed stickers may vary slightly in color due to monitor calibration and printing processes. We are not responsible for errors in artwork, spelling, or design that are approved at this stage. Please double-check everything before proceeding.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
