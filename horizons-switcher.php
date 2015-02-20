<?php
/**
 *  Plugin Name: Horizons Redirector
 *  Plugin URI: https://github.com/Planview/horizons-event-switcher
 *  Description: Guess what homepage to display based on the user's IP, manage cookies for that
 *  Author: Steve Crockett
 *  Version: 0.1-alpha
 *  Author URI: https://github.com/crockett95
 *  Text Domain: horizons-switcher
 *  Domain Path: /lang
 */

if (!defined('WPINC'))
    die;

function horizons_switcher_admin_menu() {
    add_options_page(
        __( 'Event Redirector', 'horizons-switcher' ),
        __( 'Redirector', 'horizons-switcher' ),
        'manage_options',
        'horizons-switcher',
        'horizons_switcher_admin_page'
    );

}
add_action( 'admin_menu', 'horizons_switcher_admin_menu' );

function horizons_switcher_reg_settings() {
    add_settings_section( 'navigation', 'Navigation', '__return_true', 'horizons_switcher_admin_page' );

    register_setting( 'horizons_switcher_admin_page', 'pvhsw_menu', 'horizons_switcher_menu_clean' );
    add_settings_field(
        'pvhsw_menu',
        __('Switcher Menu', 'horizons-2015'),
        'horizons_switcher_menu_build',
        'horizons_switcher_admin_page',
        'navigation',
        array(
            'name' => 'pvhsw_menu',
            'label' => 'Menu used to Switch',
            'label_for' => 'pvhsw_menu'
        )
    );

    add_settings_section( 'pages', 'Pages', '__return_true', 'horizons_switcher_admin_page' );

    register_setting( 'horizons_switcher_admin_page', 'pvhsw_cookie_pages', 'horizons_switcher_page_clean' );
    add_settings_field(
        'pvhsw_cookie_pages',
        'Home Pages',
        'horizons_switcher_page_build',
        'horizons_switcher_admin_page',
        'pages',
        array(
            'name' => 'pvhsw_cookie_pages',
            'label' => 'Pages To Redirect to Based on Cookies',
            'label_for' => 'pvhsw_cookie_pages',
            'multi' => true
        )
    );
    register_setting( 'horizons_switcher_admin_page', 'pvhsw_default', 'horizons_switcher_page_clean' );
    add_settings_field(
        'pvhsw_default',
        'Default Home',
        'horizons_switcher_page_build',
        'horizons_switcher_admin_page',
        'pages',
        array(
            'name' => 'pvhsw_default',
            'label' => 'Default Home',
            'label_for' => 'pvhsw_default',
            'multi' => false
        )
    );

}
add_action( 'admin_init', 'horizons_switcher_reg_settings' );

function horizons_switcher_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficent permissions to access this page' ) );
    }

    include plugin_dir_path(__FILE__) . "views/admin.php";
}

function horizons_switcher_menu_clean( $val ) {
    if ( ! in_array($val, array_keys( get_registered_nav_menus() ) ) ) {
        return '';
    } else {
        return $val;
    }
}

function horizons_switcher_menu_build( $args ) {
    $output = '';
    $menus = get_registered_nav_menus();
    $options = '';
    $current = get_option( $args['name'], '' );

    foreach ($menus as $slug => $name) {
        $options .= sprintf('<option value="%s"%s>%s</option>',
            esc_attr( $slug ),
            $slug === $current ? ' selected' : '',
            esc_html( $name )
        );
    }

    $output = sprintf('<select name="%1$s" id="%1$s">%2$s</select>',
        esc_attr( $args['name'] ),
        $options
    );

    echo $output;
}

function horizons_switcher_pages_array() {
    static $pages;

    if (null === $pages) {
        $pages = array();

        $query = new WP_Query(
            array(
                'post_type' => 'page',
                'post_status' => array(
                    'publish'
                ),
            )
        );

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $pages[get_the_id()] = get_the_title();
            }
        }
        wp_reset_postdata();
    }

    return $pages;
}

function horizons_switcher_page_build( $args ) {
    $output = '';
    $pages = horizons_switcher_pages_array();
    $options = '';
    $current = get_option( $args['name'], '' );

    if ( ! is_array($current) ) {
        $current = array($current);
    }

    foreach ($pages as $slug => $name) {
        $options .= sprintf('<option value="%s"%s>%s</option>',
            esc_attr( $slug ),
            in_array($slug, $current) ? ' selected' : '',
            esc_html( $name )
        );
    }

    $output = sprintf('<select name="%1$s%4$s" id="%1$s"%3$s>%2$s</select>',
        esc_attr( $args['name'] ),
        $options,
        $args['multi'] ? ' multiple' : '',
        $args['multi'] ? '[]' : ''
    );

    echo $output;
}


function horizons_switcher_page_clean($val) {
    if ( is_array($val) ) {
        for ($i=0; $i < count($val); $i++) {
            $val[$i] = intval($val[$i]);
        }
    } else {
        $val = intval($val);
    }
    return $val;
}

function horizons_switcher_init() {
    if ( is_front_page() ) {
        if ( isset( $_COOKIE['pvhorizons'] ) ) {
            horizons_switcher_redirect_cookie($_COOKIE['pvhorizons']);
        } else {
            horizons_switcher_redirect_default();
        }
    } elseif ( is_page() ) {
        $cookie_pages = get_option( 'pvhsw_cookie_pages', array() );
        $page_id = get_queried_object_id();

        if ( isset( $_GET['pvhorizons'] ) && 'switch' === $_GET['pvhorizons'] ){
            horizons_switcher_set_cookie( $page_id );
        } elseif ( (! isset($_COOKIE['pvhorizons'])) && in_array( $page_id, $cookie_pages ) ) {
            horizons_switcher_set_cookie( $page_id );
        }
    }
}
add_action( 'get_header', 'horizons_switcher_init' );

function horizons_switcher_set_cookie( $id ) {
    $month_in_seconds = 30 * 24 * 60 * 60;
    setcookie('pvhorizons', $id, time() + 6 * $month_in_seconds, '/');
}

function horizons_switcher_redirect_cookie( $cookie ) {
    $link = get_permalink( $cookie );

    if ( $link ) {
        wp_redirect( $link , 302 );
        exit;
    } else {
        horizons_switcher_redirect_default();
    }
}

function horizons_switcher_redirect_default() {
    $page_id = get_option( 'pvhsw_default' );

    horizons_switcher_set_cookie( $page_id );
    wp_redirect( get_permalink( $page_id ), 302 );
    exit;
}

function horizons_switcher_filter_menu_start( $args ) {
    if ( isset( $args['theme_location'] ) && get_option( 'pvhsw_menu' ) === $args['theme_location'] ) {
        add_filter( 'nav_menu_link_attributes', 'horizons_switcher_filter_menu_href' );
        add_filter( 'wp_nav_menu', 'horizons_switcher_filter_menu_end' );
    }

    return $args;
}
add_filter( 'wp_nav_menu_args', 'horizons_switcher_filter_menu_start' );

function horizons_switcher_filter_menu_href( $atts ) {
    if ( isset( $atts['href'] ) ) {
        $atts['href'] = add_query_arg( 'pvhorizons', 'switch', $atts['href'] );
    }

    return $atts;
}

function horizons_switcher_filter_menu_end( $val ) {
    remove_filter( 'nav_menu_link_attributes', 'horizons_switcher_filter_menu_href' );
    remove_filter( 'wp_nav_menu', 'horizons_switcher_filter_menu_end' );

    return $val;
}
