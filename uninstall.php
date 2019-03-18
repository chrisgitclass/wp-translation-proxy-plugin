<?php

defined('ABSPATH') or die('Not allow to directly access this file');

if (!defined('WP_UNINSTALL_PLUGIN')) {
  wp_die('Not allowed');
}

require_once(__DIR__ . '/translation-proxy.php');

TranslationProxy::uninstall();

