<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$option_key = 'yrwp_options';
$post_type = 'yrwp_widget';
$transient_prefixes = [
    '_transient_yrwp_cache_',
    '_transient_timeout_yrwp_cache_',
];

delete_option($option_key);
delete_site_option($option_key);

$widget_ids = get_posts([
    'post_type' => $post_type,
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
    'suppress_filters' => false,
]);

foreach ($widget_ids as $widget_id) {
    wp_delete_post((int) $widget_id, true);
}

global $wpdb;

foreach ($transient_prefixes as $prefix) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%'
        )
    );
}

if (is_multisite()) {
    foreach ($transient_prefixes as $prefix) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
    }
}
