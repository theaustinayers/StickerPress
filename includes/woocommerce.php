<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce integration for StickerPress.
 */

/**
 * Check if WooCommerce is active.
 */
function sc_wc_is_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Create a hidden WooCommerce product for custom stickers.
 */
function sc_wc_create_product() {
    if ( ! sc_wc_is_active() ) return;

    $product_id = get_option( 'sc_wc_product_id' );
    if ( $product_id && get_post_status( $product_id ) !== false ) return;

    $product = new WC_Product_Simple();
    $product->set_name( 'Custom Sticker' );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'hidden' );
    $product->set_price( 0 );
    $product->set_regular_price( 0 );
    $product->set_virtual( true );
    $product->set_sold_individually( false );
    $product->save();

    update_option( 'sc_wc_product_id', $product->get_id() );
}

// Auto-create the WC product if it doesn't exist yet (handles first load after activation)
add_action( 'init', 'sc_wc_create_product' );

/**
 * Ensure each sticker config gets its own cart line item.
 */
add_filter( 'woocommerce_add_cart_item_data', 'sc_wc_add_cart_item_data', 10, 2 );
function sc_wc_add_cart_item_data( $cart_item_data, $product_id ) {
    $sc_product_id = (int) get_option( 'sc_wc_product_id' );
    if ( (int) $product_id !== $sc_product_id ) return $cart_item_data;

    if ( isset( $cart_item_data['sc_sticker'] ) ) {
        $cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
    }
    return $cart_item_data;
}

/**
 * Restore sc_sticker data from the session so custom price/thumbnail/name work on cart page.
 */
add_filter( 'woocommerce_get_cart_item_from_session', 'sc_wc_get_cart_item_from_session', 10, 2 );
function sc_wc_get_cart_item_from_session( $cart_item, $values ) {
    if ( isset( $values['sc_sticker'] ) ) {
        $cart_item['sc_sticker'] = $values['sc_sticker'];
    }
    return $cart_item;
}

/**
 * Set the custom price for sticker items before cart totals.
 */
add_action( 'woocommerce_before_calculate_totals', 'sc_wc_set_cart_prices', 20, 1 );
function sc_wc_set_cart_prices( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;

    foreach ( $cart->get_cart() as $cart_item ) {
        if ( isset( $cart_item['sc_sticker'] ) ) {
            $price = floatval( $cart_item['sc_sticker']['price_each'] );
            $cart_item['data']->set_price( $price );

            // Set artwork as the product image so WC block cart displays it
            if ( ! empty( $cart_item['sc_sticker']['attachment_id'] ) ) {
                $cart_item['data']->set_image_id( (int) $cart_item['sc_sticker']['attachment_id'] );
            }
        }
    }
}

/**
 * Display custom sticker data in the cart table.
 */
add_filter( 'woocommerce_get_item_data', 'sc_wc_display_cart_item_data', 10, 2 );
function sc_wc_display_cart_item_data( $item_data, $cart_item ) {
    if ( ! isset( $cart_item['sc_sticker'] ) ) return $item_data;

    $sticker = $cart_item['sc_sticker'];
    $sizes   = get_option( 'sc_sizes', array() );
    $si      = isset( $sticker['size_index'] ) ? (int) $sticker['size_index'] : 0;
    $size_label = isset( $sizes[ $si ] ) ? $sizes[ $si ]['label'] : 'Unknown';

    $item_data[] = array( 'key' => 'Size',       'value' => $size_label );
    $item_data[] = array( 'key' => 'Material',   'value' => ucfirst( $sticker['material'] ) );
    $item_data[] = array( 'key' => 'Finish',     'value' => ucfirst( $sticker['finish'] ) );
    $item_data[] = array( 'key' => 'Lamination', 'value' => $sticker['laminated'] === 'yes' ? 'Laminated' : 'Non-Laminated' );
    $item_data[] = array( 'key' => 'Cut Type',   'value' => ucwords( str_replace( '-', ' ', $sticker['cut'] ) ) );
    $border_level = isset( $sticker['border_level'] ) ? (int) $sticker['border_level'] : ( ( isset( $sticker['white_border'] ) && $sticker['white_border'] === 'no' ) ? 0 : 2 );
    $border_label = $border_level > 0 ? 'Level ' . $border_level : 'None';
    $item_data[] = array( 'key' => 'White Border', 'value' => $border_label );

    if ( ! empty( $sticker['artwork_url'] ) ) {
        $item_data[] = array(
            'key'     => 'Artwork',
            'value'   => '<a href="' . esc_url( $sticker['artwork_url'] ) . '" target="_blank">View Artwork</a>',
            'display' => '<a href="' . esc_url( $sticker['artwork_url'] ) . '" target="_blank">View Artwork</a>',
        );
    }

    if ( ! empty( $sticker['proof_url'] ) ) {
        $item_data[] = array(
            'key'     => 'Proof',
            'value'   => '<a href="' . esc_url( $sticker['proof_url'] ) . '" target="_blank">View Proof</a>',
            'display' => '<a href="' . esc_url( $sticker['proof_url'] ) . '" target="_blank">View Proof</a>',
        );
    }

    $item_data[] = array( 'key' => 'Price Each', 'value' => '$' . number_format( (float) $sticker['price_each'], 2 ) );

    return $item_data;
}

/**
 * Save custom sticker data to the order lineâ€‘item meta.
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'sc_wc_save_order_item_meta', 10, 4 );
function sc_wc_save_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( ! isset( $values['sc_sticker'] ) ) return;

    $sticker = $values['sc_sticker'];
    $sizes   = get_option( 'sc_sizes', array() );
    $si      = isset( $sticker['size_index'] ) ? (int) $sticker['size_index'] : 0;
    $size_label = isset( $sizes[ $si ] ) ? $sizes[ $si ]['label'] : 'Unknown';

    $item->add_meta_data( '_sc_artwork',     $sticker['artwork'], true );
    $item->add_meta_data( '_sc_artwork_url', $sticker['artwork_url'], true );
    $item->add_meta_data( 'Artwork File',   $sticker['artwork_url'], true );
    $item->add_meta_data( 'Size',            $size_label, true );
    $item->add_meta_data( 'Material',        ucfirst( $sticker['material'] ), true );
    $item->add_meta_data( 'Finish',          ucfirst( $sticker['finish'] ), true );
    $item->add_meta_data( 'Lamination',      $sticker['laminated'] === 'yes' ? 'Laminated' : 'Non-Laminated', true );
    $item->add_meta_data( 'Cut Type',        ucwords( str_replace( '-', ' ', $sticker['cut'] ) ), true );
    $border_level = isset( $sticker['border_level'] ) ? (int) $sticker['border_level'] : ( ( isset( $sticker['white_border'] ) && $sticker['white_border'] === 'no' ) ? 0 : 2 );
    $border_label = $border_level > 0 ? 'Level ' . $border_level : 'None';
    $item->add_meta_data( 'White Border',    $border_label, true );
    $item->add_meta_data( '_sc_white_border', isset( $sticker['white_border'] ) ? $sticker['white_border'] : 'yes', true );
    $item->add_meta_data( '_sc_border_level', $border_level, true );
    $item->add_meta_data( '_sc_price_each',  $sticker['price_each'], true );
    if ( ! empty( $sticker['attachment_id'] ) ) {
        $item->add_meta_data( '_sc_attachment_id', (int) $sticker['attachment_id'], true );
    }
    if ( ! empty( $sticker['proof_file'] ) ) {
        $upload_dir = wp_upload_dir();
        $proof_url = $upload_dir['baseurl'] . '/sticker-creator/' . $sticker['proof_file'];
        $item->add_meta_data( '_sc_proof_file', $sticker['proof_file'], true );
        $item->add_meta_data( '_sc_proof_url', $proof_url, true );
        $item->add_meta_data( 'Proof File', $proof_url, true );
    }
}

/**
 * Show artwork thumbnail in the admin order items list.
 */
add_filter( 'woocommerce_admin_order_item_thumbnail', 'sc_wc_admin_order_item_thumbnail', 10, 3 );
function sc_wc_admin_order_item_thumbnail( $image, $item_id, $item ) {
    $artwork_url = $item->get_meta( '_sc_artwork_url' );
    if ( $artwork_url ) {
        return '<img src="' . esc_url( $artwork_url ) . '" alt="Custom Sticker" style="max-width:80px;max-height:80px;object-fit:contain;" />';
    }
    return $image;
}

/**
 * Hide internal meta keys from the admin order display.
 */
add_filter( 'woocommerce_hidden_order_itemmeta', 'sc_wc_hide_order_itemmeta' );
function sc_wc_hide_order_itemmeta( $hidden ) {
    $hidden[] = '_sc_artwork';
    $hidden[] = '_sc_artwork_url';
    $hidden[] = '_sc_price_each';
    $hidden[] = '_sc_attachment_id';
    $hidden[] = '_sc_white_border';
    $hidden[] = '_sc_border_level';
    $hidden[] = '_sc_proof_file';
    $hidden[] = '_sc_proof_url';
    return $hidden;
}

/**
 * Add a "Regenerate Proof" action button to the admin order page.
 */
add_action( 'woocommerce_order_actions', 'sc_wc_add_regenerate_proof_action' );
function sc_wc_add_regenerate_proof_action( $actions ) {
    $actions['sc_regenerate_proof'] = 'Regenerate Sticker Proof(s)';
    return $actions;
}

add_action( 'woocommerce_order_action_sc_regenerate_proof', 'sc_wc_handle_regenerate_proof' );
function sc_wc_handle_regenerate_proof( $order ) {
    $order_id = $order->get_id();

    // Clear the flag so proofs regenerate
    $order->delete_meta_data( '_sc_proofs_generated' );
    $order->save();

    // Clear existing proof URLs from items
    foreach ( $order->get_items() as $item_id => $item ) {
        $item->delete_meta_data( '_sc_proof_url' );
        $item->delete_meta_data( 'Proof File' );
        $item->save();
    }

    // Generate fresh proofs
    sc_wc_generate_proofs( $order_id );
}

/**
 * Render the "Artwork File" meta value as a clickable link in admin.
 */
add_filter( 'woocommerce_order_item_display_meta_value', 'sc_wc_artwork_meta_link', 10, 3 );
function sc_wc_artwork_meta_link( $display_value, $meta, $item ) {
    if ( ( $meta->key === 'Artwork File' || $meta->key === 'Proof File' ) && filter_var( $display_value, FILTER_VALIDATE_URL ) ) {
        $label = $meta->key === 'Proof File' ? 'View / Download Proof' : 'View / Download';
        return '<a href="' . esc_url( $display_value ) . '" target="_blank">' . $label . '</a>';
    }
    return $display_value;
}

/**
 * Show artwork thumbnail in the cart (classic shortcode-based cart).
 */
add_filter( 'woocommerce_cart_item_thumbnail', 'sc_wc_cart_thumbnail', 10, 3 );
function sc_wc_cart_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['sc_sticker'] ) ) {
        // Prefer proof image for cart thumbnail
        $url = ! empty( $cart_item['sc_sticker']['proof_url'] ) ? $cart_item['sc_sticker']['proof_url'] : ( ! empty( $cart_item['sc_sticker']['artwork_url'] ) ? $cart_item['sc_sticker']['artwork_url'] : '' );
        if ( $url ) {
            return '<img src="' . esc_url( $url ) . '" alt="Custom Sticker" style="max-width:80px;max-height:80px;" />';
        }
    }
    return $thumbnail;
}

/**
 * Set the product image per cart item for block-based cart.
 * Uses the attachment created at add-to-cart time.
 */
add_filter( 'woocommerce_cart_item_product', 'sc_wc_set_product_image_for_blocks', 10, 2 );
function sc_wc_set_product_image_for_blocks( $product, $cart_item ) {
    if ( ! isset( $cart_item['sc_sticker'] ) ) {
        return $product;
    }
    if ( ! empty( $cart_item['sc_sticker']['attachment_id'] ) ) {
        $product->set_image_id( (int) $cart_item['sc_sticker']['attachment_id'] );
    }
    return $product;
}

/**
 * Custom product name in the cart.
 */
add_filter( 'woocommerce_cart_item_name', 'sc_wc_cart_item_name', 10, 3 );
function sc_wc_cart_item_name( $name, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['sc_sticker'] ) ) {
        $sticker = $cart_item['sc_sticker'];
        $sizes   = get_option( 'sc_sizes', array() );
        $si      = isset( $sticker['size_index'] ) ? (int) $sticker['size_index'] : 0;
        $size_label = isset( $sizes[ $si ] ) ? $sizes[ $si ]['label'] : '';
        return 'Custom Sticker â€“ ' . esc_html( $size_label ) . ' ' . esc_html( ucfirst( $sticker['material'] ) );
    }
    return $name;
}

/**
 * On order completion/processing, generate a final composite PNG for each
 * sticker line item and email it to the admin.
 */

/**
 * Schedule proof generation after checkout completes (non-blocking).
 * Heavy image processing must NOT run during the checkout API request.
 */
add_action( 'woocommerce_checkout_order_processed', 'sc_wc_schedule_proof_generation', 10, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'sc_wc_schedule_proof_generation', 10, 1 );
function sc_wc_schedule_proof_generation( $order_id ) {
    if ( $order_id instanceof WC_Order ) {
        $order_id = $order_id->get_id();
    }
    if ( ! wp_next_scheduled( 'sc_generate_proofs_event', array( $order_id ) ) ) {
        wp_schedule_single_event( time(), 'sc_generate_proofs_event', array( $order_id ) );
    }
}

/**
 * Also generate proofs when order reaches processing/completed status.
 * This is the reliable fallback â€” runs in a separate PHP request from checkout.
 */
add_action( 'sc_generate_proofs_event', 'sc_wc_generate_proofs', 10, 1 );
add_action( 'woocommerce_order_status_processing', 'sc_wc_generate_proofs', 5, 1 );
add_action( 'woocommerce_order_status_completed',  'sc_wc_generate_proofs', 5, 1 );
function sc_wc_generate_proofs( $order_id ) {
    if ( $order_id instanceof WC_Order ) {
        $order_id = $order_id->get_id();
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    if ( $order->get_meta( '_sc_proofs_generated' ) ) return;

    error_log( 'StickerPress: Starting proof generation for order #' . $order_id );

    $upload_dir  = wp_upload_dir();
    $sticker_dir = $upload_dir['basedir'] . '/sticker-creator';

    if ( ! file_exists( $sticker_dir ) ) {
        wp_mkdir_p( $sticker_dir );
    }

    $has_imagick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
    error_log( 'StickerPress: Imagick available: ' . ( $has_imagick ? 'yes' : 'no' ) );

    foreach ( $order->get_items() as $item_id => $item ) {
        $artwork_file = $item->get_meta( '_sc_artwork' );
        if ( empty( $artwork_file ) ) continue;

        if ( $item->get_meta( '_sc_proof_url' ) ) continue;

        $artwork_path = $sticker_dir . '/' . sanitize_file_name( $artwork_file );
        if ( ! file_exists( $artwork_path ) ) {
            error_log( 'StickerPress: Artwork file not found: ' . $artwork_path );
            continue;
        }

        $ext = strtolower( pathinfo( $artwork_path, PATHINFO_EXTENSION ) );
        if ( in_array( $ext, array( 'pdf', 'ai', 'psd' ), true ) ) continue;

        $cut = strtolower( str_replace( ' ', '-', trim( $item->get_meta( 'Cut Type' ) ) ) );
        error_log( 'StickerPress: Generating proof for item #' . $item_id . ', cut: ' . $cut . ', file: ' . $artwork_file );

        try {
            $white_border = $item->get_meta( '_sc_white_border' );
            if ( $white_border === '' ) $white_border = 'yes';
            $proof_png = sc_generate_proof_png( $artwork_path, $item, $white_border );
            if ( $proof_png && file_exists( $proof_png ) ) {
                $proof_url = $upload_dir['baseurl'] . '/sticker-creator/' . basename( $proof_png );
                $item->add_meta_data( 'Proof File', $proof_url, true );
                $item->add_meta_data( '_sc_proof_url', $proof_url, true );
                $item->save();
                error_log( 'StickerPress: Proof generated: ' . $proof_url );
            } else {
                error_log( 'StickerPress: Proof generation returned false for item #' . $item_id );
            }
        } catch ( Exception $e ) {
            error_log( 'StickerPress: Proof generation EXCEPTION for order #' . $order_id . ': ' . $e->getMessage() );
        } catch ( \Throwable $t ) {
            error_log( 'StickerPress: Proof generation FATAL for order #' . $order_id . ': ' . $t->getMessage() );
        }
    }

    $order->update_meta_data( '_sc_proofs_generated', 1 );
    $order->save();
    error_log( 'StickerPress: Proof generation complete for order #' . $order_id );
}

add_action( 'woocommerce_order_status_processing', 'sc_wc_email_artwork_to_admin', 10, 1 );
add_action( 'woocommerce_order_status_completed',  'sc_wc_email_artwork_to_admin', 10, 1 );
function sc_wc_email_artwork_to_admin( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // Prevent sending twice
    if ( $order->get_meta( '_sc_artwork_emailed' ) ) return;

    // Ensure proofs are generated (in case cron hasn't run yet)
    sc_wc_generate_proofs( $order_id );

    $upload_dir  = wp_upload_dir();
    $sticker_dir = $upload_dir['basedir'] . '/sticker-creator';
    $attachments = array();
    $details     = array();

    foreach ( $order->get_items() as $item_id => $item ) {
        $artwork_file = $item->get_meta( '_sc_artwork' );
        if ( empty( $artwork_file ) ) continue;

        $artwork_path = $sticker_dir . '/' . sanitize_file_name( $artwork_file );
        if ( ! file_exists( $artwork_path ) ) continue;

        $ext = strtolower( pathinfo( $artwork_path, PATHINFO_EXTENSION ) );

        if ( in_array( $ext, array( 'pdf', 'ai', 'psd' ), true ) ) {
            $attachments[] = $artwork_path;
        } else {
            // Use existing proof if available, otherwise attach artwork
            $proof_url = $item->get_meta( '_sc_proof_url' );
            if ( $proof_url ) {
                $proof_file = $sticker_dir . '/' . basename( $proof_url );
                if ( file_exists( $proof_file ) ) {
                    $attachments[] = $proof_file;
                } else {
                    $attachments[] = $artwork_path;
                }
            } else {
                $attachments[] = $artwork_path;
            }
        }

        $size     = $item->get_meta( 'Size' );
        $material = $item->get_meta( 'Material' );
        $finish   = $item->get_meta( 'Finish' );
        $lam      = $item->get_meta( 'Lamination' );
        $cut      = $item->get_meta( 'Cut Type' );
        $qty      = $item->get_quantity();
        $each     = $item->get_meta( '_sc_price_each' );

        $details[] = sprintf(
            "- %s | %s | %s | %s | %s | Qty: %d | $%s/ea",
            $size, $material, $finish, $lam === 'Laminated' ? 'Laminated' : 'Non-Laminated', $cut, $qty, number_format( (float) $each, 2 )
        );
    }

    if ( empty( $attachments ) ) return;

    $admin_email = get_option( 'admin_email' );
    $subject     = sprintf( 'Sticker Order #%d â€“ Artwork Files', $order_id );
    $message     = sprintf(
        "A new sticker order has been placed.\n\nOrder #%d\nCustomer: %s %s (%s)\n\nSticker Details:\n%s\n\nThe artwork file(s) are attached. PDF, AI, and PSD files are included as-is for production.\n\nView order: %s",
        $order_id,
        $order->get_billing_first_name(),
        $order->get_billing_last_name(),
        $order->get_billing_email(),
        implode( "\n", $details ),
        admin_url( 'post.php?post=' . $order_id . '&action=edit' )
    );

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    wp_mail( $admin_email, $subject, $message, $headers, $attachments );

    $order->update_meta_data( '_sc_artwork_emailed', 1 );
    $order->save();
}

/**
 * Generate a proof PNG using ImageMagick (with GD fallback).
 *
 * For ALL cut types (square, round, rounded-rect, die-cut):
 * 1. White border/backing around artwork (controlled by $white_border)
 * 2. Dashed cut line tracing the shape
 * 3. Transparent outside the cut line
 *
 * Die-cut uses ImageMagick morphology to dilate the alpha mask for a true
 * contour-following outline. Other shapes use geometric clipping.
 */
function sc_generate_proof_png( $artwork_path, $item, $white_border = 'yes' ) {
    // Proof always gets white border, never dashed cut line
    $white_border = 'yes';

    $upload_dir  = wp_upload_dir();
    $sticker_dir = $upload_dir['basedir'] . '/sticker-creator';
    $proof_name  = 'proof-' . wp_generate_password( 10, false ) . '.png';
    $proof_name  = wp_unique_filename( $sticker_dir, sanitize_file_name( $proof_name ) );
    $proof_path  = $sticker_dir . '/' . $proof_name;

    $cut = strtolower( str_replace( ' ', '-', trim( $item->get_meta( 'Cut Type' ) ) ) );
    if ( empty( $cut ) ) $cut = 'square';

    $has_imagick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );

    error_log( 'StickerPress: sc_generate_proof_png called, cut=' . $cut . ', imagick=' . ( $has_imagick ? 'yes' : 'no' ) );

    if ( $has_imagick ) {
        $result = sc_generate_proof_imagick( $artwork_path, $proof_path, $cut, $white_border );
        if ( $result ) {
            error_log( 'StickerPress: Imagick proof success: ' . $proof_path );
            return $proof_path;
        }
        error_log( 'StickerPress: Imagick proof FAILED, falling back to GD' );
    }

    // GD fallback
    $result = sc_generate_proof_gd( $artwork_path, $proof_path, $cut, $white_border );
    if ( $result ) {
        error_log( 'StickerPress: GD proof success: ' . $proof_path );
        return $proof_path;
    }

    error_log( 'StickerPress: Both Imagick and GD proof generation FAILED' );
    return false;
}

/**
 * ImageMagick proof generation â€” true contour tracing via morphology.
 */
function sc_generate_proof_imagick( $artwork_path, $proof_path, $cut, $white_border ) {
    try {
        $src = new Imagick( $artwork_path );
        $src->setImageFormat( 'png' );
        $src->setImageAlphaChannel( Imagick::ALPHACHANNEL_SET );

        $w = $src->getImageWidth();
        $h = $src->getImageHeight();

        // Border padding in pixels (~4% of smallest dimension, min 20px)
        $pad = max( 20, (int) round( min( $w, $h ) * 0.04 ) );
        $total_pad = $pad;

        $fw = $w + $total_pad * 2;
        $fh = $h + $total_pad * 2;

        if ( $cut === 'die-cut' ) {
            return sc_proof_imagick_diecut( $src, $proof_path, $w, $h, $fw, $fh, $total_pad, $pad, $white_border );
        }

        // --- Geometric shapes: square, round, rounded-rect ---

        // Create final canvas (transparent)
        $canvas = new Imagick();
        $canvas->newImage( $fw, $fh, new ImagickPixel( 'transparent' ) );
        $canvas->setImageFormat( 'png' );

        // Build shape mask
        $mask = new Imagick();
        $mask->newImage( $fw, $fh, new ImagickPixel( 'black' ) );
        $mask->setImageFormat( 'png' );
        $draw = new ImagickDraw();
        $draw->setFillColor( new ImagickPixel( 'white' ) );

        if ( $cut === 'round' ) {
            $draw->ellipse( $fw / 2, $fh / 2, $fw / 2, $fh / 2, 0, 360 );
        } elseif ( strpos( $cut, 'rounded' ) !== false ) {
            $r = min( $fw, $fh ) * 0.12;
            $draw->roundRectangle( 0, 0, $fw - 1, $fh - 1, $r, $r );
        } else {
            // Square
            $draw->rectangle( 0, 0, $fw - 1, $fh - 1 );
        }
        $mask->drawImage( $draw );

        // White backing layer
        $white_layer = new Imagick();
        $white_layer->newImage( $fw, $fh, new ImagickPixel( 'white' ) );
        $white_layer->setImageFormat( 'png' );
        // Apply shape mask to white layer
        $mask_clone = clone $mask;
        $white_layer->compositeImage( $mask_clone, Imagick::COMPOSITE_DSTIN, 0, 0, Imagick::CHANNEL_ALPHA );
        $canvas->compositeImage( $white_layer, Imagick::COMPOSITE_OVER, 0, 0 );
        $white_layer->destroy();
        $mask_clone->destroy();

        // Composite artwork centered
        $canvas->compositeImage( $src, Imagick::COMPOSITE_OVER, $total_pad, $total_pad );

        // Apply shape mask to the full composite (clip artwork to shape)
        $canvas->compositeImage( $mask, Imagick::COMPOSITE_DSTIN, 0, 0, Imagick::CHANNEL_ALPHA );

        $canvas->writeImage( $proof_path );
        $canvas->destroy();
        $mask->destroy();
        $src->destroy();
        return true;

    } catch ( Exception $e ) {
        error_log( 'StickerPress proof (Imagick) Exception: ' . $e->getMessage() );
        return false;
    } catch ( \Throwable $t ) {
        error_log( 'StickerPress proof (Imagick) Fatal: ' . $t->getMessage() );
        return false;
    }
}

/**
 * Die-cut proof with ImageMagick â€” blur+threshold dilation (works on all IM versions).
 */
function sc_proof_imagick_diecut( $src, $proof_path, $w, $h, $fw, $fh, $total_pad, $pad, $white_border ) {
    try {
        error_log( 'StickerPress: Die-cut Imagick proof start, w=' . $w . ', h=' . $h );

        // Extract alpha channel as a grayscale mask
        $alpha_mask = clone $src;
        $alpha_mask->separateImageChannel( Imagick::CHANNEL_ALPHA );

        // Threshold to clean binary mask
        $alpha_mask->thresholdImage( 0.5 * Imagick::getQuantum() );

        // Check mask orientation: corner pixel should be background (black)
        $corner = $alpha_mask->getImagePixelColor( 0, 0 );
        $corner_rgb = $corner->getColor();
        error_log( 'StickerPress: Corner pixel R=' . $corner_rgb['r'] );
        if ( $corner_rgb['r'] > 128 ) {
            $alpha_mask->negateImage( false );
            error_log( 'StickerPress: Negated alpha mask (was inverted)' );
        }
        // Now: white = shape, black = background

        // Dilate using blur + threshold (universal, no morphology needed)
        $border_radius = max( 8, (int) round( min( $w, $h ) * 0.03 ) );
        $dilated = clone $alpha_mask;
        // Gaussian blur spreads white pixels outward
        $sigma = $border_radius * 0.65;
        $dilated->gaussianBlurImage( $border_radius, $sigma );
        // Low threshold reclaims them as solid white at the expanded size
        $dilated->thresholdImage( 0.05 * Imagick::getQuantum() );

        error_log( 'StickerPress: Blur+threshold dilation done, border_radius=' . $border_radius );

        // Create final canvas
        $canvas = new Imagick();
        $canvas->newImage( $fw, $fh, new ImagickPixel( 'transparent' ) );
        $canvas->setImageFormat( 'png' );

        // White backing: create white image shaped by dilated mask
        $white_layer = new Imagick();
        $white_layer->newImage( $w, $h, new ImagickPixel( 'white' ) );
        $white_layer->setImageFormat( 'png' );
        $white_layer->setImageAlphaChannel( Imagick::ALPHACHANNEL_OPAQUE );

        // Use the dilated mask as alpha: white in mask â†’ opaque white, black â†’ transparent
        $white_layer->compositeImage( $dilated, Imagick::COMPOSITE_COPYOPACITY, 0, 0 );

        $canvas->compositeImage( $white_layer, Imagick::COMPOSITE_OVER, $total_pad, $total_pad );
        $white_layer->destroy();

        // Composite artwork on top
        $canvas->compositeImage( $src, Imagick::COMPOSITE_OVER, $total_pad, $total_pad );

        // Clean up
        $dilated->destroy();
        $alpha_mask->destroy();
        $src->destroy();

        $canvas->writeImage( $proof_path );
        $canvas->destroy();
        error_log( 'StickerPress: Die-cut Imagick proof SUCCESS' );
        return true;

    } catch ( Exception $e ) {
        error_log( 'StickerPress die-cut proof (Imagick) Exception: ' . $e->getMessage() );
        return false;
    } catch ( \Throwable $t ) {
        error_log( 'StickerPress die-cut proof (Imagick) Fatal: ' . $t->getMessage() );
        return false;
    }
}

/**
 * GD-based proof generation fallback (for hosts without ImageMagick PHP extension).
 * Handles square, round, rounded-rect with geometric shapes.
 * Die-cut uses pixel-level alpha dilation for a true contour-following white border.
 */
function sc_generate_proof_gd( $artwork_path, $proof_path, $cut, $white_border ) {
    $ext = strtolower( pathinfo( $artwork_path, PATHINFO_EXTENSION ) );

    $src = null;
    switch ( $ext ) {
        case 'png':  $src = @imagecreatefrompng( $artwork_path ); break;
        case 'jpg':
        case 'jpeg': $src = @imagecreatefromjpeg( $artwork_path ); break;
        case 'webp':
            if ( function_exists( 'imagecreatefromwebp' ) ) {
                $src = @imagecreatefromwebp( $artwork_path );
            }
            break;
    }

    if ( ! $src ) return false;

    $w = imagesx( $src );
    $h = imagesy( $src );

    $pad = max( 20, (int) round( min( $w, $h ) * 0.04 ) );
    $fw = $w + $pad * 2;
    $fh = $h + $pad * 2;

    $final = imagecreatetruecolor( $fw, $fh );
    imagesavealpha( $final, true );
    imagealphablending( $final, false );
    $trans = imagecolorallocatealpha( $final, 0, 0, 0, 127 );
    imagefill( $final, 0, 0, $trans );

    imagealphablending( $final, true );

    $white = imagecolorallocate( $final, 255, 255, 255 );

    if ( $cut === 'die-cut' ) {
        // Die-cut: iterative 1px dilation for white border contour
        $border_r = max( 6, (int) round( min( $w, $h ) * 0.03 ) );

        // Build binary alpha mask (1 = opaque, 0 = transparent)
        imagealphablending( $src, false );
        $curr = new SplFixedArray( $w * $h );
        for ( $y = 0; $y < $h; $y++ ) {
            for ( $x = 0; $x < $w; $x++ ) {
                $rgba = imagecolorat( $src, $x, $y );
                $a = ( $rgba >> 24 ) & 0x7F;
                $curr[ $y * $w + $x ] = ( $a < 64 ) ? 1 : 0;
            }
        }

        // Iterative 1px dilation: each pass expands by 1 pixel (4-connected)
        // O(w * h * border_r) â€” fast even for large images
        for ( $pass = 0; $pass < $border_r; $pass++ ) {
            $next = clone $curr;
            for ( $y = 0; $y < $h; $y++ ) {
                for ( $x = 0; $x < $w; $x++ ) {
                    if ( $curr[ $y * $w + $x ] ) continue;
                    if ( ( $x > 0     && $curr[ $y * $w + ($x - 1) ] ) ||
                         ( $x < $w-1  && $curr[ $y * $w + ($x + 1) ] ) ||
                         ( $y > 0     && $curr[ ($y - 1) * $w + $x ] ) ||
                         ( $y < $h-1  && $curr[ ($y + 1) * $w + $x ] ) ) {
                        $next[ $y * $w + $x ] = 1;
                    }
                }
            }
            $curr = $next;
        }

        // Draw white pixels on the final image where dilated mask is set
        for ( $y = 0; $y < $h; $y++ ) {
            for ( $x = 0; $x < $w; $x++ ) {
                if ( $curr[ $y * $w + $x ] ) {
                    imagesetpixel( $final, $x + $pad, $y + $pad, $white );
                }
            }
        }
    } elseif ( $cut === 'round' ) {
        imagefilledellipse( $final, $fw / 2, $fh / 2, $fw, $fh, $white );
    } elseif ( strpos( $cut, 'rounded' ) !== false ) {
        imagefilledrectangle( $final, 0, 0, $fw - 1, $fh - 1, $white );
    } else {
        imagefilledrectangle( $final, 0, 0, $fw - 1, $fh - 1, $white );
    }

    // Composite artwork
    imagecopy( $final, $src, $pad, $pad, 0, 0, $w, $h );
    imagedestroy( $src );

    imagealphablending( $final, false );
    imagesavealpha( $final, true );
    imagepng( $final, $proof_path, 6 );
    imagedestroy( $final );

    return true;
}

/**
 * Draw a dashed rectangle on a GD image.
 */
function sc_draw_dashed_rect( $img, $x1, $y1, $x2, $y2, $color ) {
    $dash = 10;
    $gap  = 6;
    // Top
    sc_draw_dashed_line( $img, $x1, $y1, $x2, $y1, $color, $dash, $gap );
    // Right
    sc_draw_dashed_line( $img, $x2, $y1, $x2, $y2, $color, $dash, $gap );
    // Bottom
    sc_draw_dashed_line( $img, $x2, $y2, $x1, $y2, $color, $dash, $gap );
    // Left
    sc_draw_dashed_line( $img, $x1, $y2, $x1, $y1, $color, $dash, $gap );
}

/**
 * Draw a dashed line between two points.
 */
function sc_draw_dashed_line( $img, $x1, $y1, $x2, $y2, $color, $dash, $gap ) {
    $dx = $x2 - $x1;
    $dy = $y2 - $y1;
    $len = sqrt( $dx * $dx + $dy * $dy );
    if ( $len < 1 ) return;
    $ux = $dx / $len;
    $uy = $dy / $len;
    $pos = 0;
    $draw = true;
    while ( $pos < $len ) {
        $seg = $draw ? $dash : $gap;
        $end = min( $pos + $seg, $len );
        if ( $draw ) {
            imageline(
                $img,
                (int) round( $x1 + $ux * $pos ),
                (int) round( $y1 + $uy * $pos ),
                (int) round( $x1 + $ux * $end ),
                (int) round( $y1 + $uy * $end ),
                $color
            );
        }
        $pos = $end;
        $draw = ! $draw;
    }
}

/**
 * Draw a dashed ellipse.
 */
function sc_draw_dashed_ellipse( $img, $cx, $cy, $w, $h, $color ) {
    $steps = 120;
    $dash  = 8;
    $gap   = 5;
    $acc   = 0;
    $draw  = true;
    $px    = $cx + $w / 2;
    $py    = $cy;
    for ( $i = 1; $i <= $steps; $i++ ) {
        $angle = 2 * M_PI * $i / $steps;
        $nx = $cx + cos( $angle ) * $w / 2;
        $ny = $cy + sin( $angle ) * $h / 2;
        $seg_len = sqrt( pow( $nx - $px, 2 ) + pow( $ny - $py, 2 ) );
        $acc += $seg_len;
        $limit = $draw ? $dash : $gap;
        if ( $acc >= $limit ) {
            $acc = 0;
            $draw = ! $draw;
        }
        if ( $draw ) {
            imageline( $img, (int) round( $px ), (int) round( $py ), (int) round( $nx ), (int) round( $ny ), $color );
        }
        $px = $nx;
        $py = $ny;
    }
}

/**
 * Draw a dashed rounded rectangle.
 */
function sc_draw_dashed_roundrect( $img, $x1, $y1, $x2, $y2, $r, $color ) {
    $r = min( $r, ( $x2 - $x1 ) / 2, ( $y2 - $y1 ) / 2 );
    $dash = 10;
    $gap  = 6;
    // Top
    sc_draw_dashed_line( $img, $x1 + $r, $y1, $x2 - $r, $y1, $color, $dash, $gap );
    // Right
    sc_draw_dashed_line( $img, $x2, $y1 + $r, $x2, $y2 - $r, $color, $dash, $gap );
    // Bottom
    sc_draw_dashed_line( $img, $x2 - $r, $y2, $x1 + $r, $y2, $color, $dash, $gap );
    // Left
    sc_draw_dashed_line( $img, $x1, $y2 - $r, $x1, $y1 + $r, $color, $dash, $gap );
    // Corner arcs (dashed)
    sc_draw_dashed_arc( $img, $x1 + $r, $y1 + $r, $r, 180, 270, $color );
    sc_draw_dashed_arc( $img, $x2 - $r, $y1 + $r, $r, 270, 360, $color );
    sc_draw_dashed_arc( $img, $x2 - $r, $y2 - $r, $r, 0, 90, $color );
    sc_draw_dashed_arc( $img, $x1 + $r, $y2 - $r, $r, 90, 180, $color );
}

/**
 * Draw a dashed arc.
 */
function sc_draw_dashed_arc( $img, $cx, $cy, $r, $start_deg, $end_deg, $color ) {
    $steps = 30;
    $dash  = 6;
    $gap   = 4;
    $acc   = 0;
    $draw  = true;
    $s_rad = deg2rad( $start_deg );
    $px = $cx + cos( $s_rad ) * $r;
    $py = $cy + sin( $s_rad ) * $r;
    for ( $i = 1; $i <= $steps; $i++ ) {
        $angle = deg2rad( $start_deg + ( $end_deg - $start_deg ) * $i / $steps );
        $nx = $cx + cos( $angle ) * $r;
        $ny = $cy + sin( $angle ) * $r;
        $seg_len = sqrt( pow( $nx - $px, 2 ) + pow( $ny - $py, 2 ) );
        $acc += $seg_len;
        if ( $acc >= ( $draw ? $dash : $gap ) ) {
            $acc = 0;
            $draw = ! $draw;
        }
        if ( $draw ) {
            imageline( $img, (int) round( $px ), (int) round( $py ), (int) round( $nx ), (int) round( $ny ), $color );
        }
        $px = $nx;
        $py = $ny;
    }
}
