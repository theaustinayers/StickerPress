<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend shortcode & asset loading for Sticker Creator.
 */

add_action( 'wp_enqueue_scripts', 'sc_maybe_enqueue' );
function sc_maybe_enqueue() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'sticker_creator' ) ) {
        sc_enqueue_assets();
    }
}

function sc_enqueue_assets() {
    wp_enqueue_style( 'sc-front-css', SC_PLUGIN_URL . 'assets/css/frontend.css', array(), SC_VERSION );
    wp_enqueue_script( 'sc-front-js', SC_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), SC_VERSION, true );

    $sizes          = get_option( 'sc_sizes', array() );
    $pricing        = get_option( 'sc_pricing', array() );
    $qty_breaks     = get_option( 'sc_quantity_breaks', array() );
    $qty_overrides  = get_option( 'sc_quantity_breaks_overrides', array() );
    $hidden_stickers = get_option( 'sc_hidden_stickers', array() );
    $min_quantity    = max( 1, (int) get_option( 'sc_min_quantity', 1 ) );
    $lam_enabled     = get_option( 'sc_lamination_enabled', '1' ) === '1';

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
        'pricing'            => $visible_pricing,
        'qty_breaks'         => $qty_breaks,
        'qty_overrides'      => (object) $qty_overrides,
        'max_upload'         => wp_max_upload_size(),
        'wc_active'          => $wc_active,
        'cart_url'           => $wc_active ? wc_get_cart_url() : '',
        'min_quantity'       => $min_quantity,
        'lamination_enabled' => $lam_enabled,
    ));
}

add_shortcode( 'sticker_creator', 'sc_shortcode_render' );
function sc_shortcode_render( $atts ) {
    sc_enqueue_assets();

    $sizes = get_option( 'sc_sizes', array() );

    ob_start();
    ?>
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
                        <option value="vinyl">Vinyl</option>
                        <option value="paper">Paper</option>
                        <option value="clear">Clear</option>
                    </select>
                </div>

                <div class="sc-option-group">
                    <label>Finish</label>
                    <div class="sc-finish-options">
                        <label class="sc-finish-option sc-selected" data-finish="glossy">
                            <input type="radio" name="sc_finish" value="glossy" checked hidden />
                            <div class="sc-finish-demo sc-finish-glossy">
                                <div class="sc-shine-effect"></div>
                            </div>
                            <span>Glossy</span>
                        </label>
                        <label class="sc-finish-option" data-finish="matte">
                            <input type="radio" name="sc_finish" value="matte" hidden />
                            <div class="sc-finish-demo sc-finish-matte"></div>
                            <span>Matte</span>
                        </label>
                    </div>
                </div>

                <div class="sc-option-group sc-lamination-group">
                    <label>Lamination</label>
                    <div class="sc-lamination-options">
                        <label class="sc-lam-option sc-selected" data-lam="yes">
                            <input type="radio" name="sc_laminated" value="yes" checked hidden />
                            <span>Laminated</span>
                        </label>
                        <label class="sc-lam-option" data-lam="no">
                            <input type="radio" name="sc_laminated" value="no" hidden />
                            <span>Non-Laminated</span>
                        </label>
                    </div>
                </div>

                <div class="sc-option-group">
                    <label>White Border</label>
                    <div class="sc-border-options">
                        <label class="sc-lam-option sc-selected" data-border="yes">
                            <input type="radio" name="sc_white_border" value="yes" checked hidden />
                            <span>White Border</span>
                        </label>
                        <label class="sc-lam-option" data-border="no">
                            <input type="radio" name="sc_white_border" value="no" hidden />
                            <span>No Border</span>
                        </label>
                    </div>
                </div>

                <div class="sc-option-group">
                    <label>Quantity</label>
                    <input type="number" id="sc-quantity" value="<?php echo esc_attr( max( 50, $min_quantity ) ); ?>" min="<?php echo esc_attr( $min_quantity ); ?>" max="10000" />
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
                    <p class="sc-preview-placeholder" id="sc-preview-placeholder">Upload artwork to see preview</p>
                </div>
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
    <?php
    return ob_get_clean();
}
