<?php
/**
 * Plugin Name: WC Loop Variations Display Pro
 * Description: Display product variations under products in loops with price and stock status, grouped by attribute. Includes admin settings.
 * Version: 1.1.0
 * Author: Your Name
 * Text Domain: wc-loop-variations-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * -----------------------
 * ADMIN SETTINGS PAGE
 * -----------------------
 */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        __( 'Loop Variations Settings', 'wc-loop-variations-pro' ),
        __( 'Loop Variations', 'wc-loop-variations-pro' ),
        'manage_options',
        'wc-loop-variations-settings',
        'wc_loop_variations_settings_page'
    );
});

add_action( 'admin_init', function() {
    register_setting( 'wc_loop_variations_settings', 'wc_loop_variations_options', array(
        'sanitize_callback' => 'wc_loop_variations_sanitize_options',
        'default' => array(
            'show_stock' => 1,
            'show_price' => 1,
            'attributes' => array(),
        ),
    ));

    add_settings_section(
        'wc_loop_variations_section',
        __( 'Loop Variations Display Options', 'wc-loop-variations-pro' ),
        '__return_false',
        'wc-loop-variations-settings'
    );

    add_settings_field(
        'wc_loop_variations_attributes',
        __( 'Attributes to display', 'wc-loop-variations-pro' ),
        'wc_loop_variations_attributes_field',
        'wc-loop-variations-settings',
        'wc_loop_variations_section'
    );

    add_settings_field(
        'wc_loop_variations_show_stock',
        __( 'Show stock status', 'wc-loop-variations-pro' ),
        'wc_loop_variations_show_stock_field',
        'wc-loop-variations-settings',
        'wc_loop_variations_section'
    );

    add_settings_field(
        'wc_loop_variations_show_price',
        __( 'Show variation price', 'wc-loop-variations-pro' ),
        'wc_loop_variations_show_price_field',
        'wc-loop-variations-settings',
        'wc_loop_variations_section'
    );
});

function wc_loop_variations_sanitize_options( $input ) {
    $output = array();
    $output['show_stock'] = isset( $input['show_stock'] ) ? 1 : 0;
    $output['show_price'] = isset( $input['show_price'] ) ? 1 : 0;
    $output['attributes'] = isset( $input['attributes'] ) && is_array( $input['attributes'] ) ? array_map( 'sanitize_text_field', $input['attributes'] ) : array();
    return $output;
}

function wc_loop_variations_attributes_field() {
    $options = get_option( 'wc_loop_variations_options', array() );
    $selected = isset( $options['attributes'] ) ? $options['attributes'] : array();
    $product_attributes = wc_get_attribute_taxonomies();

    if ( ! empty( $product_attributes ) ) {
        foreach ( $product_attributes as $attr ) {
            $checked = in_array( $attr->attribute_name, $selected ) ? 'checked' : '';
            echo '<label style="margin-right:10px;"><input type="checkbox" name="wc_loop_variations_options[attributes][]" value="' . esc_attr( $attr->attribute_name ) . '" ' . $checked . '> ' . esc_html( $attr->attribute_label ) . '</label>';
        }
    } else {
        echo __( 'No global attributes found.', 'wc-loop-variations-pro' );
    }
}

function wc_loop_variations_show_stock_field() {
    $options = get_option( 'wc_loop_variations_options', array() );
    $checked = isset( $options['show_stock'] ) && $options['show_stock'] ? 'checked' : '';
    echo '<input type="checkbox" name="wc_loop_variations_options[show_stock]" value="1" ' . $checked . '>';
}

function wc_loop_variations_show_price_field() {
    $options = get_option( 'wc_loop_variations_options', array() );
    $checked = isset( $options['show_price'] ) && $options['show_price'] ? 'checked' : '';
    echo '<input type="checkbox" name="wc_loop_variations_options[show_price]" value="1" ' . $checked . '>';
}

function wc_loop_variations_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Loop Variations Settings', 'wc-loop-variations-pro' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wc_loop_variations_settings' );
            do_settings_sections( 'wc-loop-variations-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * -----------------------
 * FRONTEND LOOP DISPLAY
 * -----------------------
 */
add_action( 'woocommerce_after_shop_loop_item_title', 'wc_loop_display_variations', 15 );

function wc_loop_display_variations() {

    global $product;

    if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) return;

    $options = get_option( 'wc_loop_variations_options', array() );
    $show_stock = isset( $options['show_stock'] ) ? $options['show_stock'] : 1;
    $show_price = isset( $options['show_price'] ) ? $options['show_price'] : 1;
    $attributes_to_show = isset( $options['attributes'] ) ? $options['attributes'] : array();

    $variation_ids = $product->get_children();
    if ( empty( $variation_ids ) ) return;

    $attributes_grouped = array();

    foreach ( $variation_ids as $variation_id ) {

        $variation = wc_get_product( $variation_id );
        if ( ! $variation || ! $variation->exists() ) continue;

        $variation_attributes = $variation->get_attributes();
        if ( empty( $variation_attributes ) ) continue;

        $variation_info = array(
            'price' => $variation->get_price_html(),
            'stock' => $variation->is_in_stock() ? esc_html__( 'In stock', 'wc-loop-variations-pro' ) : esc_html__( 'Out of stock', 'wc-loop-variations-pro' ),
        );

        foreach ( $variation_attributes as $taxonomy => $slug ) {

            $taxonomy_clean = str_replace( 'attribute_', '', $taxonomy );

            if ( ! empty( $attributes_to_show ) && ! in_array( $taxonomy_clean, $attributes_to_show ) ) {
                continue; // skip attribute not selected in settings
            }

            if ( taxonomy_exists( $taxonomy_clean ) ) {
                $term = get_term_by( 'slug', $slug, $taxonomy_clean );
                $label = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
            } else {
                $label = $slug;
            }

            if ( ! isset( $attributes_grouped[ $taxonomy_clean ] ) ) {
                $attributes_grouped[ $taxonomy_clean ] = array();
            }

            if ( ! isset( $attributes_grouped[ $taxonomy_clean ][ $label ] ) ) {
                $attributes_grouped[ $taxonomy_clean ][ $label ] = $variation_info;
            }
        }
    }

    if ( empty( $attributes_grouped ) ) return;

    echo '<div class="wc-loop-variations">';
    foreach ( $attributes_grouped as $attr_name => $values ) {
        echo '<div class="wc-loop-attribute">';
        echo '<strong>' . esc_html( wc_attribute_label( $attr_name ) ) . ':</strong> ';
        $items = array();
        foreach ( $values as $value_name => $info ) {
            $text = esc_html( $value_name );
            if ( $show_price && ! empty( $info['price'] ) ) $text .= ' (' . wp_kses_post( $info['price'] ) . ')';
            if ( $show_stock ) $text .= ' [' . esc_html( $info['stock'] ) . ']';
            $items[] = $text;
        }
        echo implode( ', ', $items );
        echo '</div>';
    }
    echo '</div>';
}

/**
 * -----------------------
 * FRONTEND STYLING
 * -----------------------
 */
add_action( 'wp_head', function() { ?>
    <style>
        .wc-loop-variations {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        .wc-loop-attribute {
            margin-bottom: 4px;
        }
        @media (max-width:768px) {
            .wc-loop-variations { font-size: 12px; }
        }
    </style>
<?php });
