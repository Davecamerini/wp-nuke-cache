<?php
/**
 * Plugin Name: Nuke Cache
 * Plugin URI: https://www.davecamerini.com/nuke-cache
 * Description: Scans wp-content for cache folders and provides options to empty them.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Davecamerini
 * Author URI: https://www.davecamerini.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nuke cache
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin initialization
function nuke_cache_init() {
    // Load plugin text domain
    load_plugin_textdomain('nuke cache', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'nuke_cache_init');

// Hook to add a menu item in the admin dashboard
add_action('admin_menu', 'cache_folder_scanner_menu');
add_action('admin_enqueue_scripts', 'nuke_cache_admin_scripts');

function nuke_cache_admin_scripts($hook) {
    if ('toplevel_page_cache-folder-scanner' !== $hook) {
        return;
    }

    // Register and enqueue JavaScript
    wp_register_script(
        'nuke-cache-script',
        plugins_url('nuke-cache.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    // Localize the script with nonce
    wp_localize_script(
        'nuke-cache-script',
        'nukeCacheData',
        array(
            'nonce' => wp_create_nonce('nuke_cache_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        )
    );

    wp_enqueue_script('nuke-cache-script');
}

function nuke_cache_admin_styles($hook) {
    if ('toplevel_page_cache-folder-scanner' !== $hook) {
        return;
    }
    ?>
    <style>
        .wrap {
        }
        .wrap h1 {
            margin-bottom: 30px;
            color: #1d2327;
            font-size: 24px;
        }
        .nuke-cache-grid {
            display: grid;
            grid-template-columns: repeat(2, 600px);
            gap: 24px;
            margin-top: 20px;
        }
        .nuke-cache-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e2e4e7;
        }
        .nuke-cache-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .nuke-card-header {
            background: #f8f9fa;
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nuke-card-header .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: #2271b1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nuke-card-header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
        }
        .nuke-card-content {
            padding: 24px;
        }
        .nuke-cache-size {
            font-size: 32px;
            font-weight: 600;
            color: #2271b1;
            line-height: 1.2;
            margin-bottom: 8px;
            text-align: center;
        }
        .nuke-cache-label {
            font-size: 14px;
            color: #646970;
            font-weight: 500;
            text-align: center;
            margin-bottom: 20px;
        }
        .nuke-cache-status {
            padding: 12px;
            border-radius: 6px;
            margin: 16px 0;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }
        .nuke-cache-status.found {
            background: #f0f6fc;
            color: #2271b1;
            border: 1px solid #c5d9ed;
        }
        .nuke-cache-status.empty {
            background: #f0f6fc;
            color: #646970;
            border: 1px solid #e2e4e7;
        }
        .nuke-cache-card .button {
            width: 100%;
            text-align: center;
            margin-top: 16px;
            padding: 8px 16px;
            height: auto;
            line-height: 1.4;
        }
        @media screen and (max-width: 1248px) {
            .nuke-cache-grid {
                grid-template-columns: 600px;
            }
        }
    </style>
    <?php
}

function cache_folder_scanner_menu() {
    add_menu_page(
        'Cache Folder Scanner',
        'Cache Nuker',
        'manage_options',
        'cache-folder-scanner',
        'cache_folder_scanner_page',
        plugins_url('Mon.png', __FILE__),
        30
    );
}

function cache_folder_scanner_page() {
    // Define cache directories
    $cache_dir = WP_CONTENT_DIR . '/cache';
    $et_cache_dir = WP_CONTENT_DIR . '/et-cache';
    $elementor_css_dir = WP_CONTENT_DIR . '/uploads/elementor/css';
    $litespeed_cache_dir = WP_CONTENT_DIR . '/lscache';
    $litespeed_alt_cache_dir = WP_CONTENT_DIR . '/litespeed';

    // Initialize cache sizes
    $cache_size = is_dir($cache_dir) ? folder_size($cache_dir) : 0;
    $et_cache_size = is_dir($et_cache_dir) ? folder_size($et_cache_dir) : 0;
    $elementor_cache_size = is_dir($elementor_css_dir) ? folder_size($elementor_css_dir) : 0;
    
    // Check for LiteSpeed cache in both possible locations
    $litespeed_cache_size = 0;
    $litespeed_cache_dir_found = '';
    if (is_dir($litespeed_cache_dir)) {
        $litespeed_cache_size = folder_size($litespeed_cache_dir);
        $litespeed_cache_dir_found = $litespeed_cache_dir;
    } elseif (is_dir($litespeed_alt_cache_dir)) {
        $litespeed_cache_size = folder_size($litespeed_alt_cache_dir);
        $litespeed_cache_dir_found = $litespeed_alt_cache_dir;
    }

    // Handle form submissions with nonce verification
    if (isset($_POST['empty_cache']) && isset($_POST['nuke_cache_nonce']) && wp_verify_nonce($_POST['nuke_cache_nonce'], 'empty_cache_action')) {
        delete_folder($cache_dir);
        echo '<div class="updated"><p>' . esc_html__('Cache folder emptied.', 'nuke cache') . '</p></div>';
        // Refresh the cache size after deletion
        $cache_size = is_dir($cache_dir) ? folder_size($cache_dir) : 0;
    }

    if (isset($_POST['empty_et_cache']) && isset($_POST['nuke_cache_nonce']) && wp_verify_nonce($_POST['nuke_cache_nonce'], 'empty_et_cache_action')) {
        delete_folder($et_cache_dir);
        echo '<div class="updated"><p>' . esc_html__('Et-cache folder emptied.', 'nuke cache') . '</p></div>';
        // Refresh the et-cache size after deletion
        $et_cache_size = is_dir($et_cache_dir) ? folder_size($et_cache_dir) : 0;
    }

    if (isset($_POST['empty_elementor_cache']) && isset($_POST['nuke_cache_nonce']) && wp_verify_nonce($_POST['nuke_cache_nonce'], 'empty_elementor_cache_action')) {
        if ( did_action( 'elementor/loaded' ) ) {
            \Elementor\Plugin::instance()->files_manager->clear_cache();
            echo '<div class="updated"><p>' . esc_html__('Elementor cache cleared (Regenerate Files & Data).', 'nuke cache') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('Elementor is not active or loaded.', 'nuke cache') . '</p></div>';
        }
        // Refresh the elementor cache size after clearing
        $elementor_cache_size = is_dir($elementor_css_dir) ? folder_size($elementor_css_dir) : 0;
    }

    if (isset($_POST['empty_litespeed_cache']) && isset($_POST['nuke_cache_nonce']) && wp_verify_nonce($_POST['nuke_cache_nonce'], 'empty_litespeed_cache_action')) {
        if (!empty($litespeed_cache_dir_found)) {
            delete_folder($litespeed_cache_dir_found);
            echo '<div class="updated"><p>' . esc_html__('LiteSpeed cache folder emptied.', 'nuke cache') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . esc_html__('No LiteSpeed cache folder found to clear.', 'nuke cache') . '</p></div>';
        }
        // Refresh the litespeed cache size after deletion
        $litespeed_cache_size = 0;
        $litespeed_cache_dir_found = '';
        if (is_dir($litespeed_cache_dir)) {
            $litespeed_cache_size = folder_size($litespeed_cache_dir);
            $litespeed_cache_dir_found = $litespeed_cache_dir;
        } elseif (is_dir($litespeed_alt_cache_dir)) {
            $litespeed_cache_size = folder_size($litespeed_alt_cache_dir);
            $litespeed_cache_dir_found = $litespeed_alt_cache_dir;
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Cache Nuker', 'nuke cache'); ?></h1>
        <div class="nuke-cache-grid" style="display: grid; grid-template-columns: repeat(2, 400px); gap: 24px; margin-top: 20px;">
            <!-- W3TC Cache Card -->
            <div class="nuke-cache-card" style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); overflow: hidden; transition: all 0.3s ease; border: 1px solid #e2e4e7; min-height: 300px;">
                <div class="nuke-card-header" style="background: #f8f9fa; padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-performance" style="font-size: 24px; width: 24px; height: 24px; color: #2271b1; display: flex; align-items: center; justify-content: center;"></span>
                    <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;"><?php echo esc_html__('W3TC Cache', 'nuke cache'); ?></h2>
                </div>
                <div class="nuke-card-content" style="padding: 24px;">
                    <?php if ($cache_size > 0): ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">
                            <?php echo esc_html(size_format($cache_size)); ?>
                        </div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status found" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #2271b1; border: 1px solid #c5d9ed;">
                            <?php echo esc_html__('Cache folder found and ready to be cleared.', 'nuke cache'); ?>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('empty_cache_action', 'nuke_cache_nonce'); ?>
                            <input type="submit" name="empty_cache" class="button button-primary" value="<?php echo esc_attr__('Empty Cache Folder', 'nuke cache'); ?>" style="width: 100%; text-align: center; margin-top: 16px; padding: 8px 16px; height: auto; line-height: 1.4;" />
                        </form>
                    <?php else: ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">0 MB</div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status empty" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #646970; border: 1px solid #e2e4e7;">
                            <?php echo esc_html__('No Cache folder found.', 'nuke cache'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Divi Cache Card -->
            <div class="nuke-cache-card" style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); overflow: hidden; transition: all 0.3s ease; border: 1px solid #e2e4e7; min-height: 300px;">
                <div class="nuke-card-header" style="background: #f8f9fa; padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-layout" style="font-size: 24px; width: 24px; height: 24px; color: #2271b1; display: flex; align-items: center; justify-content: center;"></span>
                    <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;"><?php echo esc_html__('Divi Cache', 'nuke cache'); ?></h2>
                </div>
                <div class="nuke-card-content" style="padding: 24px;">
                    <?php if ($et_cache_size > 0): ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">
                            <?php echo esc_html(size_format($et_cache_size)); ?>
                        </div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status found" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #2271b1; border: 1px solid #c5d9ed;">
                            <?php echo esc_html__('Divi cache folder found and ready to be cleared.', 'nuke cache'); ?>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('empty_et_cache_action', 'nuke_cache_nonce'); ?>
                            <input type="submit" name="empty_et_cache" class="button button-primary" value="<?php echo esc_attr__('Empty Et-cache Folder', 'nuke cache'); ?>" style="width: 100%; text-align: center; margin-top: 16px; padding: 8px 16px; height: auto; line-height: 1.4;" />
                        </form>
                    <?php else: ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">0 MB</div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status empty" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #646970; border: 1px solid #e2e4e7;">
                            <?php echo esc_html__('No Divi cache folder found.', 'nuke cache'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Elementor Cache Card -->
            <div class="nuke-cache-card" style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); overflow: hidden; transition: all 0.3s ease; border: 1px solid #e2e4e7; min-height: 300px;">
                <div class="nuke-card-header" style="background: #f8f9fa; padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-admin-customizer" style="font-size: 24px; width: 24px; height: 24px; color: #2271b1; display: flex; align-items: center; justify-content: center;"></span>
                    <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;"><?php echo esc_html__('Elementor Cache', 'nuke cache'); ?></h2>
                </div>
                <div class="nuke-card-content" style="padding: 24px;">
                    <?php if ($elementor_cache_size > 0): ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">
                            <?php echo esc_html(size_format($elementor_cache_size)); ?>
                        </div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status found" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #2271b1; border: 1px solid #c5d9ed;">
                            <?php echo esc_html__('Elementor cache folder found. Use the button below to clear cache using Elementor API.', 'nuke cache'); ?>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('empty_elementor_cache_action', 'nuke_cache_nonce'); ?>
                            <input type="submit" name="empty_elementor_cache" class="button button-primary" value="<?php echo esc_attr__('Clear Elementor Cache', 'nuke cache'); ?>" style="width: 100%; text-align: center; margin-top: 16px; padding: 8px 16px; height: auto; line-height: 1.4;" />
                        </form>
                    <?php else: ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">0 MB</div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status empty" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #646970; border: 1px solid #e2e4e7;">
                            <?php echo esc_html__('No Elementor cache folder found.', 'nuke cache'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LiteSpeed Cache Card -->
            <div class="nuke-cache-card" style="background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); overflow: hidden; transition: all 0.3s ease; border: 1px solid #e2e4e7; min-height: 300px;">
                <div class="nuke-card-header" style="background: #f8f9fa; padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-admin-site" style="font-size: 24px; width: 24px; height: 24px; color: #2271b1; display: flex; align-items: center; justify-content: center;"></span>
                    <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1d2327;"><?php echo esc_html__('LiteSpeed Cache', 'nuke cache'); ?></h2>
                </div>
                <div class="nuke-card-content" style="padding: 24px;">
                    <?php if ($litespeed_cache_size > 0): ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">
                            <?php echo esc_html(size_format($litespeed_cache_size)); ?>
                        </div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status found" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #2271b1; border: 1px solid #c5d9ed;">
                            <?php echo esc_html__('LiteSpeed cache folder found and ready to be cleared.', 'nuke cache'); ?>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('empty_litespeed_cache_action', 'nuke_cache_nonce'); ?>
                            <input type="submit" name="empty_litespeed_cache" class="button button-primary" value="<?php echo esc_attr__('Empty LiteSpeed Cache', 'nuke cache'); ?>" style="width: 100%; text-align: center; margin-top: 16px; padding: 8px 16px; height: auto; line-height: 1.4;" />
                        </form>
                    <?php else: ?>
                        <div class="nuke-cache-size" style="font-size: 32px; font-weight: 600; color: #2271b1; line-height: 1.2; margin-bottom: 8px; text-align: center;">0 MB</div>
                        <div class="nuke-cache-label" style="font-size: 14px; color: #646970; font-weight: 500; text-align: center; margin-bottom: 20px;">
                            <?php echo esc_html__('Total Cache Size', 'nuke cache'); ?>
                        </div>
                        <div class="nuke-cache-status empty" style="padding: 12px; border-radius: 6px; margin: 16px 0; text-align: center; font-size: 14px; font-weight: 500; background: #f0f6fc; color: #646970; border: 1px solid #e2e4e7;">
                            <?php echo esc_html__('No LiteSpeed cache folder found.', 'nuke cache'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function folder_size($dir) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function delete_folder($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_folder("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}
?>
