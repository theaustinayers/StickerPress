<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend AJAX handlers for StickerPress.
 */

add_action( 'wp_ajax_sc_upload_artwork', 'sc_upload_artwork' );
add_action( 'wp_ajax_nopriv_sc_upload_artwork', 'sc_upload_artwork' );
function sc_upload_artwork() {
    check_ajax_referer( 'sc_front_nonce', 'nonce' );

    if ( empty( $_FILES['artwork'] ) ) {
        wp_send_json_error( 'No file uploaded.' );
    }

    $file = $_FILES['artwork'];

    // Validate file type
    $allowed = array(
        'image/png', 'image/jpeg', 'image/svg+xml', 'image/webp',
        'application/pdf', 'application/postscript', 'application/illustrator',
        'application/x-photoshop', 'image/vnd.adobe.photoshop',
        'application/octet-stream', // AI/PSD often detected as this
    );
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );

    // Also allow by extension for AI/PSD (MIME detection is unreliable)
    $ext_lower = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    $allowed_by_ext = in_array( $ext_lower, array( 'ai', 'psd' ), true );

    if ( ! in_array( $mime, $allowed, true ) && ! $allowed_by_ext ) {
        wp_send_json_error( 'Invalid file type. Allowed: PNG, JPG, SVG, WebP, PDF, AI, PSD.' );
    }

    // Validate file size
    if ( $file['size'] > wp_max_upload_size() ) {
        wp_send_json_error( 'File too large.' );
    }

    // Sanitize filename
    $ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
    $allowed_exts = array( 'png', 'jpg', 'jpeg', 'svg', 'webp', 'pdf', 'ai', 'psd' );
    if ( ! in_array( strtolower( $ext ), $allowed_exts, true ) ) {
        wp_send_json_error( 'Invalid file extension.' );
    }

    $filename = wp_unique_filename(
        wp_upload_dir()['basedir'] . '/sticker-creator',
        sanitize_file_name( 'artwork-' . wp_generate_password( 8, false ) . '.' . $ext )
    );

    $upload_dir = wp_upload_dir();
    $dest = $upload_dir['basedir'] . '/sticker-creator/' . $filename;

    if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
        wp_send_json_error( 'Upload failed.' );
    }

    $url = $upload_dir['baseurl'] . '/sticker-creator/' . $filename;

    // Check if the uploaded image has transparency (PNG only)
    $has_transparency = false;
    $is_raster = in_array( strtolower( $ext ), array( 'png', 'jpg', 'jpeg', 'webp' ), true );
    $is_vector = in_array( strtolower( $ext ), array( 'pdf', 'ai', 'psd', 'svg' ), true );

    if ( strtolower( $ext ) === 'png' ) {
        $has_transparency = sc_png_has_transparency( $dest );
    }

    wp_send_json_success( array(
        'url'              => esc_url( $url ),
        'filename'         => $filename,
        'has_transparency' => $has_transparency,
        'is_raster'        => $is_raster,
        'is_vector'        => $is_vector,
        'ext'              => strtolower( $ext ),
    ));
}

/**
 * Upload a client-rendered proof PNG (base64 data URL).
 */
add_action( 'wp_ajax_sc_upload_proof', 'sc_upload_proof' );
add_action( 'wp_ajax_nopriv_sc_upload_proof', 'sc_upload_proof' );
function sc_upload_proof() {
    check_ajax_referer( 'sc_front_nonce', 'nonce' );

    $data_url = isset( $_POST['proof_data'] ) ? $_POST['proof_data'] : '';
    if ( empty( $data_url ) || strpos( $data_url, 'data:image/png;base64,' ) !== 0 ) {
        wp_send_json_error( 'Invalid proof data.' );
    }

    $base64 = substr( $data_url, strlen( 'data:image/png;base64,' ) );
    $decoded = base64_decode( $base64, true );
    if ( ! $decoded || strlen( $decoded ) < 100 ) {
        wp_send_json_error( 'Invalid image data.' );
    }

    // Limit size to 10MB
    if ( strlen( $decoded ) > 10 * 1024 * 1024 ) {
        wp_send_json_error( 'Proof image too large.' );
    }

    $upload_dir = wp_upload_dir();
    $sticker_dir = $upload_dir['basedir'] . '/sticker-creator';
    if ( ! file_exists( $sticker_dir ) ) {
        wp_mkdir_p( $sticker_dir );
    }

    $filename = wp_unique_filename( $sticker_dir, sanitize_file_name( 'proof-' . wp_generate_password( 8, false ) . '.png' ) );
    $path = $sticker_dir . '/' . $filename;

    if ( file_put_contents( $path, $decoded ) === false ) {
        wp_send_json_error( 'Failed to save proof.' );
    }

    $url = $upload_dir['baseurl'] . '/sticker-creator/' . $filename;

    wp_send_json_success( array(
        'filename' => $filename,
        'url'      => $url,
    ));
}

/**
 * Check if a PNG image has any transparent pixels.
 */
function sc_png_has_transparency( $path ) {
    $img = @imagecreatefrompng( $path );
    if ( ! $img ) return false;

    $w = imagesx( $img );
    $h = imagesy( $img );

    // Sample a grid of pixels to check for transparency (fast check)
    $step = max( 1, (int) floor( min( $w, $h ) / 40 ) );
    for ( $y = 0; $y < $h; $y += $step ) {
        for ( $x = 0; $x < $w; $x += $step ) {
            $rgba = imagecolorat( $img, $x, $y );
            $alpha = ( $rgba >> 24 ) & 0x7F;
            if ( $alpha > 10 ) { // any significant transparency
                imagedestroy( $img );
                return true;
            }
        }
    }

    imagedestroy( $img );
    return false;
}

add_action( 'wp_ajax_sc_remove_artwork', 'sc_remove_artwork' );
add_action( 'wp_ajax_nopriv_sc_remove_artwork', 'sc_remove_artwork' );
function sc_remove_artwork() {
    check_ajax_referer( 'sc_front_nonce', 'nonce' );

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
    if ( empty( $filename ) ) {
        wp_send_json_error( 'No filename.' );
    }

    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/sticker-creator/' . $filename;

    // Ensure path stays within the sticker-creator directory
    $real_path = realpath( $path );
    $allowed_dir = realpath( $upload_dir['basedir'] . '/sticker-creator' );
    if ( $real_path === false || strpos( $real_path, $allowed_dir ) !== 0 ) {
        wp_send_json_error( 'Invalid path.' );
    }

    if ( file_exists( $real_path ) ) {
        wp_delete_file( $real_path );
    }

    wp_send_json_success( 'Removed.' );
}

add_action( 'wp_ajax_sc_add_to_cart', 'sc_add_to_cart' );
add_action( 'wp_ajax_nopriv_sc_add_to_cart', 'sc_add_to_cart' );
function sc_add_to_cart() {
    check_ajax_referer( 'sc_front_nonce', 'nonce' );

    $artwork   = sanitize_file_name( $_POST['artwork'] ?? '' );
    $cut       = sanitize_text_field( $_POST['cut'] ?? 'square' );
    $size_idx  = intval( $_POST['size_index'] ?? 0 );
    $material  = sanitize_text_field( $_POST['material'] ?? 'vinyl' );
    $finish    = sanitize_text_field( $_POST['finish'] ?? 'glossy' );
    $laminated = sanitize_text_field( $_POST['laminated'] ?? 'no' );
    $white_border = sanitize_text_field( $_POST['white_border'] ?? 'yes' );
    $border_level = max( 0, min( 5, intval( $_POST['border_level'] ?? 0 ) ) );
    $quantity  = max( 1, intval( $_POST['quantity'] ?? 1 ) );
    $price_each = floatval( $_POST['price_each'] ?? 0 );
    $proof_file = sanitize_file_name( $_POST['proof_file'] ?? '' );
    $total     = floatval( $_POST['total_price'] ?? 0 );

    // Enforce minimum quantity (per-size overrides global)
    $sizes = get_option( 'sc_sizes', array() );
    $size_min = isset( $sizes[ $size_idx ]['min_qty'] ) ? (int) $sizes[ $size_idx ]['min_qty'] : 0;
    $global_min = max( 1, (int) get_option( 'sc_min_quantity', 1 ) );
    $min_qty = $size_min > 0 ? $size_min : $global_min;
    if ( $quantity < $min_qty ) {
        wp_send_json_error( 'Minimum order quantity is ' . $min_qty . ' stickers.' );
    }

    // Enforce hidden sticker check
    $hidden_stickers = get_option( 'sc_hidden_stickers', array() );
    $sticker_key = $size_idx . '_' . $material . '_' . $finish . '_' . $laminated;
    if ( in_array( $sticker_key, $hidden_stickers, true ) ) {
        wp_send_json_error( 'This sticker configuration is currently unavailable.' );
    }

    $upload_dir = wp_upload_dir();
    $artwork_url = $upload_dir['baseurl'] . '/sticker-creator/' . $artwork;

    // WooCommerce integration
    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
        $product_id = (int) get_option( 'sc_wc_product_id' );
        if ( ! $product_id || ! get_post( $product_id ) ) {
            // Try creating the product on the fly
            require_once SC_PLUGIN_DIR . 'includes/woocommerce.php';
            sc_wc_create_product();
            $product_id = (int) get_option( 'sc_wc_product_id' );
        }

        if ( ! $product_id ) {
            wp_send_json_error( 'WooCommerce product not found. Please deactivate and reactivate the plugin.' );
        }

        // Create a WP attachment from the artwork so WC block cart can display it
        $artwork_path = $upload_dir['basedir'] . '/sticker-creator/' . $artwork;
        $sc_attachment_id = 0;
        if ( file_exists( $artwork_path ) ) {
            $filetype = wp_check_filetype( $artwork_path );
            $attachment_data = array(
                'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
                'post_title'    => sanitize_file_name( pathinfo( $artwork, PATHINFO_FILENAME ) ),
                'post_content'  => '',
                'post_status'   => 'inherit',
            );
            $sc_attachment_id = wp_insert_attachment( $attachment_data, $artwork_path );
            if ( $sc_attachment_id && ! is_wp_error( $sc_attachment_id ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $meta = wp_generate_attachment_metadata( $sc_attachment_id, $artwork_path );
                wp_update_attachment_metadata( $sc_attachment_id, $meta );
            }
        }

        $cart_item_data = array(
            'sc_sticker' => array(
                'artwork'       => $artwork,
                'artwork_url'   => esc_url( $artwork_url ),
                'cut'           => $cut,
                'size_index'    => $size_idx,
                'material'      => $material,
                'finish'        => $finish,
                'laminated'     => $laminated,
                'white_border'  => $white_border,
                'border_level'  => $border_level,
                'price_each'    => $price_each,
                'attachment_id' => $sc_attachment_id,
                'proof_file'    => $proof_file,
                'proof_url'     => $proof_file ? esc_url( $upload_dir['baseurl'] . '/sticker-creator/' . $proof_file ) : '',
            ),
        );

        $added = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );

        if ( $added ) {
            wp_send_json_success( array(
                'message'  => 'Added to cart!',
                'cart_url' => wc_get_cart_url(),
            ));
        } else {
            wp_send_json_error( 'Could not add to cart.' );
        }
    } else {
        // Fallback: transient-based session cart
        if ( ! session_id() ) {
            session_start();
        }

        $order = array(
            'artwork'    => $artwork,
            'cut'        => $cut,
            'size'       => $size_idx,
            'material'   => $material,
            'finish'     => $finish,
            'laminated'  => $laminated,
            'quantity'   => $quantity,
            'price_each' => $price_each,
            'price'      => $total,
        );

        $session_key = 'sc_cart_' . session_id();
        $cart = get_transient( $session_key );
        if ( ! is_array( $cart ) ) $cart = array();
        $cart[] = $order;
        set_transient( $session_key, $cart, DAY_IN_SECONDS );

        wp_send_json_success( array(
            'message' => 'Added to cart!',
            'cart'    => $cart,
        ));
    }
}

/**
 * Remove background from uploaded artwork using GD.
 * Uses flood-fill from corners to remove the dominant background color.
 */
add_action( 'wp_ajax_sc_remove_background', 'sc_remove_background' );
add_action( 'wp_ajax_nopriv_sc_remove_background', 'sc_remove_background' );
function sc_remove_background() {
    check_ajax_referer( 'sc_front_nonce', 'nonce' );

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
    if ( empty( $filename ) ) {
        wp_send_json_error( 'No filename provided.' );
    }

    $upload_dir = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/sticker-creator/' . $filename;

    // Path traversal protection
    $real_path = realpath( $path );
    $allowed_dir = realpath( $upload_dir['basedir'] . '/sticker-creator' );
    if ( $real_path === false || strpos( $real_path, $allowed_dir ) !== 0 ) {
        wp_send_json_error( 'Invalid file path.' );
    }

    if ( ! file_exists( $real_path ) ) {
        wp_send_json_error( 'File not found.' );
    }

    $ext = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );

    // Load image with GD
    $src = null;
    switch ( $ext ) {
        case 'png':
            $src = @imagecreatefrompng( $real_path );
            break;
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg( $real_path );
            break;
        case 'webp':
            if ( function_exists( 'imagecreatefromwebp' ) ) {
                $src = @imagecreatefromwebp( $real_path );
            }
            break;
    }

    if ( ! $src ) {
        wp_send_json_error( 'Could not process this image format. Only PNG, JPG, and WebP are supported for background removal.' );
    }

    $w = imagesx( $src );
    $h = imagesy( $src );

    // Create output with alpha channel
    $out = imagecreatetruecolor( $w, $h );
    imagesavealpha( $out, true );
    imagealphablending( $out, false );
    $transparent = imagecolorallocatealpha( $out, 0, 0, 0, 127 );
    imagefill( $out, 0, 0, $transparent );

    // Copy source onto output
    imagealphablending( $out, true );
    imagecopy( $out, $src, 0, 0, 0, 0, $w, $h );
    imagealphablending( $out, false );

    // Sample background color from four corners + mid-edges (8 sample points)
    $sample_points = array(
        array( 0, 0 ),
        array( $w - 1, 0 ),
        array( 0, $h - 1 ),
        array( $w - 1, $h - 1 ),
        array( (int)($w / 2), 0 ),
        array( (int)($w / 2), $h - 1 ),
        array( 0, (int)($h / 2) ),
        array( $w - 1, (int)($h / 2) ),
    );

    $bg_colors = array();
    foreach ( $sample_points as $c ) {
        $rgb = imagecolorat( $src, $c[0], $c[1] );
        $r = ( $rgb >> 16 ) & 0xFF;
        $g = ( $rgb >> 8 ) & 0xFF;
        $b = $rgb & 0xFF;
        $key = round( $r / 16 ) . '-' . round( $g / 16 ) . '-' . round( $b / 16 );
        if ( ! isset( $bg_colors[ $key ] ) ) {
            $bg_colors[ $key ] = array( 'r' => $r, 'g' => $g, 'b' => $b, 'count' => 0 );
        }
        $bg_colors[ $key ]['count']++;
    }
    // Use the most common edge color
    usort( $bg_colors, function( $a, $b ) { return $b['count'] - $a['count']; } );
    $bg = $bg_colors[0];

    // Tolerance from user slider — respect exactly, no auto-boost
    $tolerance = isset( $_POST['tolerance'] ) ? intval( $_POST['tolerance'] ) : 120;
    $tolerance = max( 5, min( 180, $tolerance ) );

    // Flood-fill background removal from ALL edge pixels using BFS
    $visited = array();
    $queue = new SplQueue();

    // Seed from EVERY edge pixel (not every 2nd)
    for ( $x = 0; $x < $w; $x++ ) {
        $queue->enqueue( array( $x, 0 ) );
        $queue->enqueue( array( $x, $h - 1 ) );
    }
    for ( $y = 1; $y < $h - 1; $y++ ) {
        $queue->enqueue( array( 0, $y ) );
        $queue->enqueue( array( $w - 1, $y ) );
    }

    while ( ! $queue->isEmpty() ) {
        list( $px, $py ) = $queue->dequeue();
        if ( $px < 0 || $py < 0 || $px >= $w || $py >= $h ) continue;
        $vk = $py * $w + $px;
        if ( isset( $visited[ $vk ] ) ) continue;
        $visited[ $vk ] = true;

        $rgb = imagecolorat( $out, $px, $py );
        $a = ( $rgb >> 24 ) & 0x7F;
        if ( $a === 127 ) continue; // already transparent

        $r = ( $rgb >> 16 ) & 0xFF;
        $g = ( $rgb >> 8 ) & 0xFF;
        $b = $rgb & 0xFF;

        $dist = sqrt( pow( $r - $bg['r'], 2 ) + pow( $g - $bg['g'], 2 ) + pow( $b - $bg['b'], 2 ) );

        if ( $dist <= $tolerance ) {
            imagesetpixel( $out, $px, $py, $transparent );
            // Enqueue 8-connected neighbors for thorough removal
            $queue->enqueue( array( $px + 1, $py ) );
            $queue->enqueue( array( $px - 1, $py ) );
            $queue->enqueue( array( $px, $py + 1 ) );
            $queue->enqueue( array( $px, $py - 1 ) );
            $queue->enqueue( array( $px + 1, $py + 1 ) );
            $queue->enqueue( array( $px - 1, $py - 1 ) );
            $queue->enqueue( array( $px + 1, $py - 1 ) );
            $queue->enqueue( array( $px - 1, $py + 1 ) );
        }
    }

    // Edge cleanup: remove thin fringe pixels adjacent to removed areas
    for ( $pass = 0; $pass < 2; $pass++ ) {
        $to_remove = array();
        for ( $py = 0; $py < $h; $py++ ) {
            for ( $px = 0; $px < $w; $px++ ) {
                $rgb = imagecolorat( $out, $px, $py );
                $a = ( $rgb >> 24 ) & 0x7F;
                if ( $a === 127 ) continue;

                // Check 8-connected neighbors for transparency
                $near_transparent = false;
                for ( $dy = -1; $dy <= 1 && ! $near_transparent; $dy++ ) {
                    for ( $dx = -1; $dx <= 1 && ! $near_transparent; $dx++ ) {
                        if ( $dx === 0 && $dy === 0 ) continue;
                        $nx = $px + $dx;
                        $ny = $py + $dy;
                        if ( $nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h ) {
                            $near_transparent = true;
                        } else {
                            $na = ( imagecolorat( $out, $nx, $ny ) >> 24 ) & 0x7F;
                            if ( $na === 127 ) $near_transparent = true;
                        }
                    }
                }
                if ( ! $near_transparent ) continue;

                $r = ( $rgb >> 16 ) & 0xFF;
                $g = ( $rgb >> 8 ) & 0xFF;
                $b = $rgb & 0xFF;
                $dist = sqrt( pow( $r - $bg['r'], 2 ) + pow( $g - $bg['g'], 2 ) + pow( $b - $bg['b'], 2 ) );

                // Remove fringe pixel if close to background color
                if ( $dist <= $tolerance * 1.3 ) {
                    $to_remove[] = array( $px, $py );
                }
            }
        }
        foreach ( $to_remove as $pt ) {
            imagesetpixel( $out, $pt[0], $pt[1], $transparent );
        }
        if ( empty( $to_remove ) ) break; // no more fringe to remove
    }

    imagedestroy( $src );

    // Save as PNG
    $new_filename = pathinfo( $filename, PATHINFO_FILENAME ) . '-nobg.png';
    $new_filename = wp_unique_filename( $upload_dir['basedir'] . '/sticker-creator', sanitize_file_name( $new_filename ) );
    $new_path = $upload_dir['basedir'] . '/sticker-creator/' . $new_filename;

    imagepng( $out, $new_path, 9 );
    imagedestroy( $out );

    $new_url = $upload_dir['baseurl'] . '/sticker-creator/' . $new_filename;

    wp_send_json_success( array(
        'url'      => esc_url( $new_url ),
        'filename' => $new_filename,
    ));
}
