<?php
/**
 * Plugin Name: Me Live Edit
 * Author: Fachri Riyanto
 * Author URI: https://fachririyanto.com
 * Description: Simple live edit feature for page, post, custom post type, menu, customizer, etc.
 * Version: 1.0.0
 */
class MeLiveEdit {
    /**
     * Version.
     * 
     * @var string
     */
    var $version = '1.0.0';

    /**
     * Setup plugin.
     * 
     * @uses add_action()
     * @since 1.0.0
     */
    function init() {
        add_action( 'wp_footer', array( $this, 'render_frontend_script' ) );
        add_action( 'admin_head', array( $this, 'render_admin_stylesheet' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
    }

    /**
     * Render frontend script.
     * 
     * @uses is_user_logged_in()
     * @return void
     * @since 1.0.0
     */
    function render_frontend_script() {
        if ( ! is_user_logged_in() ) return;
        ?><script type="text/javascript">
            (function($) {
                $(window).on('load', function() {
                    $('body').on('click', '.me-live-edit-button', function(e) {
                        e.preventDefault();

                        var button = $(this);
                        var elementID = button.attr('data-reload-id');

                        // add loading class
                        $(elementID).addClass('me-live-edit-is-loading');

                        $.fancybox.open({
                            type: 'iframe',
                            src: button.attr('data-src'),
                            opts: {
                                afterClose: function() {
                                    // reload selected post
                                    $.ajax({
                                        url: window.location.href + '?live_edit_reload_element',
                                        type: 'GET',
                                        success: function(results) {
                                            var html = $(results).find(elementID).html();
                                            if (html === undefined) {
                                                // if html not found, delete current html
                                                var parent = button.attr('data-parent-elm');
                                                if (parent === undefined) {
                                                    $(elementID).remove();
                                                } else {
                                                    $(elementID).closest(parent).remove();
                                                }
                                            } else {
                                                // update html
                                                $(elementID).html(html);
                                            }
                                            // remove loading class
                                            $(elementID).removeClass('me-live-edit-is-loading');
                                        }
                                    });
                                }
                            }
                        });
                    });
                });
            })(jQuery);
        </script><?php
    }

    /**
     * Render admin stylesheet.
     * 
     * @return void
     * @since 1.0.0
     */
    function render_admin_stylesheet() {
        // @docs https://stackoverflow.com/questions/6662542/check-if-site-is-inside-iframe
        if ( isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe' ) :
            ?>
            <style>
                html.wp-toolbar {
                    padding-top: 0;
                }
                html, body {
                    height: initial;
                }
                body {
                    min-height: initial;
                }
                #wpadminbar,
                #adminmenuwrap,
                #adminmenuback {
                    display: none;
                }
                .folded #wpcontent,
                .folded #wpfooter,
                #wpcontent, #wpfooter {
                    margin-left: 0;
                }
                #wpwrap {
                    min-height: initial;
                }
                .auto-fold .interface-interface-skeleton {
                    top: 0;
                    left: 0;
                }
            </style>
            <?php
        endif;
    }

    /**
     * Register scripts.
     * 
     * @uses is_user_logged_in()
     * @uses wp_enqueue_style()
     * @uses wp_enqueue_script()
     * @uses plugins_url()
     * @since 1.0.0
     */
    function register_scripts() {
        if ( isset( $_GET['live_edit_reload_element'] ) ) {
            return;
        }
        if ( is_user_logged_in() ) {
            // stylesheet
            wp_enqueue_style( 'me-live-edit-fancybox', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css', array(), '3.5.7' );
            wp_enqueue_style( 'me-live-edit-style', plugins_url( '/css/style.css', __FILE__ ), array( 'me-live-edit-fancybox' ), $this->version );

            // scripts
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'fancybox', 'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js', array( 'jquery' ), '3.5.7', true );
        }
    }
}

/**
 * Get customizer link.
 * 
 * @uses home_url()
 * @uses admin_url()
 * @return string $customizer_link
 * @since 1.0.0
 */
function me_live_edit_customizer_link() {
    // get current url
    global $wp;
    $current_url = home_url( $wp->request );

    // setup customizer link
    $customizer_link = admin_url( '/customize.php?url=' . urlencode( $current_url ) );
    return $customizer_link;
}

/**
 * Get menu link.
 * 
 * @uses get_nav_menu_locations()
 * @uses admin_url()
 * @param string $location
 * @since 1.0.0
 */
function me_live_edit_menu_link( $location ) {
    $theme_locations = get_nav_menu_locations();
    if ( isset( $theme_locations[ $location ] ) ) {
        $menu_id   = $theme_locations[ $location ];
        $admin_url = admin_url( '/nav-menus.php?action=edit&menu=' . $menu_id );
    } else {
        $admin_url = admin_url();
    }
    return $admin_url;
}

/**
 * Get live edit link.
 * 
 * @uses get_edit_post_link()
 * @uses admin_url()
 * @param string $action 'add' | 'edit'
 * @param string $object_type 'post' | 'page' | 'custom-post-type-name'
 * @param int $post_id
 * @return string $add_or_edit_link
 * @since 1.0.0
 */
function me_live_edit_link( $action, $object_type, $post_id ) {
    if ( $action == 'edit' ) {
        $admin_url = get_edit_post_link( $post_id );
    } else {
        $admin_url = admin_url( '/post-new.php?post_type=' . $object_type );
    }
    return $admin_url . '&live_edit_embeded=true';
}

/**
 * Render for custom admin link.
 * 
 * @uses is_user_logged_in()
 * @uses is_preview()
 * @uses is_customize_preview()
 * @uses wp_parse_args()
 * @uses admin_url()
 * @return void
 * @since 1.0.0
 */
function me_live_edit_custom_link( $args = array() ) {
    if ( ! is_user_logged_in() || is_preview() || is_customize_preview() ) {
        return;
    }

    // define default args
    $args = wp_parse_args( $args, array(
        /**
         * Admin page link.
         * 
         * @var string
         */
        'admin_link' => '',

        /**
         * Icon.
         * 
         * @uses Dashicons https://developer.wordpress.org/resource/dashicons/
         * @var string
         */
        'icon' => 'dashicons-admin-generic',

        /**
         * Text label.
         * 
         * @var string
         */
        'text_label' => 'New Post',

        /**
         * Reloaded element id.
         * 
         * @var string
         */
        'element_id' => '#ui-target-reloaded'
    ) );

    // define custom admin link
    $admin_url = admin_url( $args['admin_link'] );

    // render element
    ?><div class="ui-me-live-edit">
        <a href="javascript;;" data-src="<?php echo $admin_url; ?>" data-reload-id="<?php echo $args['element_id']; ?>" class="mle-wrapper me-live-edit-button">
            <span class="mle-icon">
                <span class="dashicons <?php echo $args['icon']; ?>"></span>
            </span>
            <span class="mle-label">
                <?php echo $args['text_label']; ?>
            </span>
        </a>
    </div><?php
}

/**
 * Render for customizer.
 * 
 * @uses is_user_logged_in()
 * @uses is_preview()
 * @uses is_customize_preview()
 * @uses wp_parse_args()
 * @return void
 * @since 1.0.0
 */
function me_live_edit_customizer( $args = array() ) {
    if ( ! is_user_logged_in() || is_preview() || is_customize_preview() ) {
        return;
    }

    // define default args
    $args = wp_parse_args( $args, array(
        /**
         * Section name.
         * 
         * @var string
         */
        'section' => '',

        /**
         * Icon.
         * 
         * @uses Dashicons https://developer.wordpress.org/resource/dashicons/
         * @var string
         */
        'icon' => 'dashicons-admin-generic',

        /**
         * Text label.
         * 
         * @var string
         */
        'text_label' => 'New Post',
    ) );

    // define customizer link
    $admin_url = me_live_edit_customizer_link();
    if ( ! empty( $args['section'] ) ) {
        $admin_url .= '&autofocus[section]=' . $args['section'];
    }

    // render element
    ?><div class="ui-me-live-edit">
        <a href="<?php echo $admin_url; ?>" class="mle-wrapper">
            <span class="mle-icon">
                <span class="dashicons <?php echo $args['icon']; ?>"></span>
            </span>
            <span class="mle-label">
                <?php echo $args['text_label']; ?>
            </span>
        </a>
    </div><?php
}

/**
 * Render for menus.
 * 
 * @uses is_user_logged_in()
 * @uses is_preview()
 * @uses is_customize_preview()
 * @uses wp_parse_args()
 * @return void
 * @since 1.0.0
 */
function me_live_edit_menus( $args = array() ) {
    if ( ! is_user_logged_in() || is_preview() || is_customize_preview() ) {
        return;
    }

    // define default args
    $args = wp_parse_args( $args, array(
        /**
         * Theme location name.
         * 
         * @var string
         */
        'theme_location' => '',

        /**
         * Icon.
         * 
         * @uses Dashicons https://developer.wordpress.org/resource/dashicons/
         * @var string
         */
        'icon' => 'dashicons-admin-generic',

        /**
         * Text label.
         * 
         * @var string
         */
        'text_label' => 'New Post',

        /**
         * Reloaded element id.
         * 
         * @var string
         */
        'element_id' => '#ui-target-reloaded'
    ) );

    // define admin URL
    $admin_url = me_live_edit_menu_link( $args['theme_location'] );

    // render element
    ?><div class="ui-me-live-edit">
        <a href="javascript;;" data-src="<?php echo $admin_url; ?>" data-reload-id="<?php echo $args['element_id']; ?>" class="mle-wrapper me-live-edit-button">
            <span class="mle-icon">
                <span class="dashicons <?php echo $args['icon']; ?>"></span>
            </span>
            <span class="mle-label">
                <?php echo $args['text_label']; ?>
            </span>
        </a>
    </div><?php
}

/**
 * Render live edit.
 * 
 * @uses is_user_logged_in()
 * @uses is_preview()
 * @uses is_customize_preview()
 * @uses wp_parse_args()
 * @param $args array
 * @since 1.0.0
 */
function me_live_edit( $args = array() ) {
    if ( ! is_user_logged_in() || is_preview() || is_customize_preview() ) {
        return;
    }

    // define default args
    $args = wp_parse_args( $args, array(
        /**
         * Post type name.
         * 
         * @var string 'post' | 'page' | 'custom-post-type-name'
         */
        'object_type' => 'post',

        /**
         * Type of action.
         * 
         * @var string 'add' | 'edit'
         */
        'action' => 'add',

        /**
         * Post ID, if type of action is 'add' set to 0.
         * 
         * @var int
         */
        'post_id' => 0,

        /**
         * Icon.
         * 
         * @uses Dashicons https://developer.wordpress.org/resource/dashicons/
         * @var string
         */
        'icon' => 'dashicons-admin-generic',

        /**
         * Text label.
         * 
         * @var string
         */
        'text_label' => 'New Post',

        /**
         * Reloaded element id.
         * 
         * @var string
         */
        'element_id' => '#ui-target-reloaded'
    ));

    // define admin URL
    $admin_url = me_live_edit_link( $args['action'], $args['object_type'], $args['post_id'] );

    // render element
    ?><div class="ui-me-live-edit">
        <a href="javascript;;" data-src="<?php echo $admin_url; ?>" data-reload-id="<?php echo $args['element_id']; ?>" class="mle-wrapper me-live-edit-button">
            <span class="mle-icon">
                <span class="dashicons <?php echo $args['icon']; ?>"></span>
            </span>
            <span class="mle-label">
                <?php echo $args['text_label']; ?>
            </span>
        </a>
    </div><?php
}

/**
 * RUN PLUGIN.
 */
$plugin = new MeLiveEdit();
$plugin->init();
