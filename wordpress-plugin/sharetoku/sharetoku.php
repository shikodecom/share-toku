<?php

/**
 * Plugin Name: ShareToku
 * Description: ShareToku の紹介特典を安全に表示します。
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: sharetoku
 */
if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__.'/includes/class-sharetoku-plugin.php';
ShareToku_Plugin::boot(__FILE__);
