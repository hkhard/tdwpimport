<?php
/**
 * CSS-only Configurable Dashboard Framework
 *
 * A self-contained, CSS-only dashboard framework for WordPress.
 * This is a library file - integrate it into your plugin.
 *
 * @version 1.0.0
 * @author Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-dashboard-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dashboard-filters.php';

/**
 * Base CSS Dashboard class
 *
 * Extend this class to create custom dashboards
 */
abstract class CSS_Dashboard_Base
{

    protected function get_menu_slug()
    {
        return 'css-dashboard';
    }

    protected function get_page_title()
    {
        return 'Dashboard';
    }

    protected function get_menu_title()
    {
        return 'Dashboard';
    }

    protected function get_menu_icon()
    {
        return 'dashicons-dashboard';
    }

    protected function get_menu_position()
    {
        return 2;
    }

    /**
     * Constructor - registers menu and assets
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Add dashboard menu page
     */
    public function add_menu_page()
    {
        add_menu_page(
            $this->get_page_title(),
            $this->get_menu_title(),
            'manage_options',
            $this->get_menu_slug(),
            array($this, 'render_dashboard'),
            $this->get_menu_icon(),
            $this->get_menu_position()
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_assets($hook)
    {
        $page_hook = 'toplevel_page_' . $this->get_menu_slug();
        if ($page_hook !== $hook && $hook !== 'poker-tournament-import_page_' . $this->get_menu_slug()) {
            return;
        }

        $css_dir = POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css-dashboard/';
        wp_enqueue_style('css-dashboard-core', $css_dir . 'dashboard-core.css', array(), '1.0.0');
        wp_enqueue_style('css-dashboard-components', $css_dir . 'components.css', array(), '1.0.0');
        wp_enqueue_style('css-dashboard-filters', $css_dir . 'filters.css', array('css-dashboard-components'), '1.0.0');
    }

    /**
     * Render dashboard
     */
    public function render_dashboard()
    {
        if (isset($_GET['css_dashboard_iframe'])) {
            $this->render_iframe_content();
            wp_die();
        }
        $config = $this->get_config();
        $theme_colors = $this->get_theme_colors();
        $renderer = new CSS_Dashboard_Renderer($config, $theme_colors);
        echo $renderer->render();
    }

    /**
     * Render iframe content for auto-refresh
     */
    public function render_iframe_content()
    {
        $config = $this->get_config();
        $theme_colors = $this->get_theme_colors();
        $renderer = new CSS_Dashboard_Renderer($config, $theme_colors);
        echo $renderer->render();
    }

    /**
     * Get dashboard configuration
     * Override this in your subclass
     */
    protected function get_config()
    {
        return apply_filters('css_dashboard_config', array(
            'title' => $this->get_page_title(),
            'sections' => array()
        ));
    }

    /**
     * Get theme colors
     */
    protected function get_theme_colors()
    {
        $colors = array(
            'primary' => '',
            'accent' => '',
            'surface' => '',
            'text' => '',
        );

        // 1. Try Gutenberg Global Styles (Block Themes)
        if (function_exists('wp_get_global_styles')) {
            $global_colors = wp_get_global_styles(array('color', 'palette'));
            if (!empty($global_colors)) {
                $colors['primary'] = !empty($global_colors['primary']) ? $global_colors['primary'] : '';
                $colors['accent'] = !empty($global_colors['accent']) ? $global_colors['accent'] : '';
            }
        }

        // 2. Try Customizer Colors (Classic Themes)
        if (empty($colors['primary'])) {
            $colors['primary'] = get_theme_mod('background_color') ? '#' . get_theme_mod('background_color') : '';
        }

        return apply_filters('css_dashboard_theme_colors', array_filter($colors));
    }
}
