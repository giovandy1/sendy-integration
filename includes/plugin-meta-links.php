<?php
// includes/plugin-meta-links.php

if (!defined('ABSPATH')) exit;

function sendy_plugin_meta_links($links, $file) {
    // Cocokkan dengan path file utama plugin
    if ($file === plugin_basename(dirname(__DIR__) . '/sendy-integration.php')) {
        $links[] = '<a href="https://github.com/giovandy1/sendy-integration/tree/master" target="_blank">View Details</a>';
        $links[] = '<a href="https://github.com/giovandy1/sendy-integration/blob/master/README.md" target="_blank">Changelog</a>';
    }
    return $links;
}

add_filter('plugin_row_meta', 'sendy_plugin_meta_links', 10, 2);
