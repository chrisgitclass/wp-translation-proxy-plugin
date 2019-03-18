<?php
/**
 * Plugin Name: Translation Proxy
 * Description: Purges proxy cache when the Wordpress site is updated.
 * Version: 1.0.0
 * Author: Yoshiaki Iinuma
 * License: GPL2
 */

defined('ABSPATH') or die('Not allow to directly access this file');

require_once(__DIR__ . '/translation-proxy.php');

add_action('admin_init', 'TranslationProxy::initialize');
add_action('admin_menu', 'TranslationProxy::setup_admin');
register_activation_hook(__FILE__, 'TranslationProxy::activate');
register_deactivation_hook(__FILE__, 'TranslationProxy::deactivate');

add_action('wp', 'TranslationProxy::set_inject_hooks');
add_action('wp_enqueue_scripts', 'TranslationProxy::load_scripts');
