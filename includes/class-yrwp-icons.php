<?php

namespace YRWP;

if (!defined('ABSPATH')) exit;

class Icons
{
    const WEATHER_SUBDIR = 'yrwp-icons';
    const DETAIL_SUBDIR = 'yrwp-detail-icons';
    const WEATHER_BUILTIN_DIR = 'assets/icons/weather/svg';
    const DETAIL_BUILTIN_DIR = 'assets/icons/details';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_yrwp_icons_upload', [__CLASS__, 'handle_upload']);
        add_action('admin_post_yrwp_icons_delete', [__CLASS__, 'handle_delete']);
    }

    public static function menu()
    {
        add_submenu_page(
            'yrwp_main',
            'Icons',
            'Icons',
            'manage_options',
            'yrwp_icons',
            [__CLASS__, 'render_page']
        );
    }

    public static function get_detail_icon_url(string $filename): string
    {
        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            return '';
        }

        $custom_file = self::custom_dir_abs('details') . $filename;
        if (file_exists($custom_file)) {
            return self::custom_dir_url('details') . rawurlencode($filename);
        }

        $builtin_file = self::builtin_dir_abs('details') . $filename;
        if (file_exists($builtin_file)) {
            return YRWP_URL . self::DETAIL_BUILTIN_DIR . '/' . rawurlencode($filename);
        }

        return '';
    }

    private static function custom_dir_abs(string $type): string
    {
        $u = wp_upload_dir();
        $subdir = $type === 'details' ? self::DETAIL_SUBDIR : self::WEATHER_SUBDIR;
        return trailingslashit($u['basedir']) . $subdir . '/';
    }

    private static function custom_dir_url(string $type): string
    {
        $u = wp_upload_dir();
        $subdir = $type === 'details' ? self::DETAIL_SUBDIR : self::WEATHER_SUBDIR;
        return trailingslashit($u['baseurl']) . $subdir . '/';
    }

    private static function builtin_dir_abs(string $type): string
    {
        $dir = $type === 'details' ? self::DETAIL_BUILTIN_DIR : self::WEATHER_BUILTIN_DIR;
        return trailingslashit(YRWP_PATH . $dir);
    }

    private static function ensure_dir(string $type): bool
    {
        $dir = self::custom_dir_abs($type);
        if (!file_exists($dir)) {
            return wp_mkdir_p($dir);
        }

        return is_dir($dir) && is_writable($dir);
    }

    private static function list_builtin_svgs(string $type): array
    {
        $dir = self::builtin_dir_abs($type);
        if (!is_dir($dir)) return [];

        $files = glob($dir . '*.svg');
        if (!$files) return [];

        usort($files, static fn($a, $b) => strcmp(basename($a), basename($b)));
        return $files;
    }

    private static function sanitize_svg(string $svg): string
    {
        $svg = preg_replace('#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $svg);
        $svg = preg_replace('#<\s*foreignObject[^>]*>.*?<\s*/\s*foreignObject\s*>#is', '', $svg);
        $svg = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/iu', '', $svg);
        $svg = preg_replace('/\s(xlink:href|href)\s*=\s*(["\'])\s*javascript:.*?\2/iu', '', $svg);

        if (stripos($svg, '<svg') === false) return '';
        return $svg;
    }

    private static function safe_name(string $name): string
    {
        $name = sanitize_file_name($name);
        if (strtolower(substr($name, -4)) !== '.svg') {
            $name .= '.svg';
        }
        return $name;
    }

    private static function current_tab(): string
    {
        $tab = sanitize_key($_GET['tab'] ?? 'weather');
        return in_array($tab, ['weather', 'details'], true) ? $tab : 'weather';
    }

    private static function labels(string $type): array
    {
        if ($type === 'details') {
            return [
                'subtitle' => 'Detail icons (Built-in + Custom override)',
            ];
        }

        return [
            'subtitle' => 'Weather icons (Built-in + Custom override)',
        ];
    }

    private static function detail_icon_tokens(): array
    {
        return [
            'temperature.svg' => '{{ icon-temperature }}',
            'wind_speed.svg' => '{{ icon-wind-speed }}',
            'wind_direction.svg' => '{{ icon-wind-direction }}',
            'humidity.svg' => '{{ icon-humidity }}',
            'pressure.svg' => '{{ icon-pressure }}',
            'precipitation_amount.svg' => '{{ icon-cloud-area-fraction }}',
            'precipitation.svg' => '{{ icon-precipitation }}',
        ];
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) return;

        $tab = self::current_tab();
        $type = $tab === 'details' ? 'details' : 'weather';
        $labels = self::labels($type);

        echo '<div class="wrap"><h1>Icons</h1>';

        if (!empty($_GET['msg'])) {
            echo '<div class="notice notice-success"><p>' . esc_html($_GET['msg']) . '</p></div>';
        }
        if (!empty($_GET['err'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($_GET['err']) . '</p></div>';
        }

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px;">';
        self::render_tab('weather', 'Weather icons', $tab);
        self::render_tab('details', 'Detail icons', $tab);
        echo '</nav>';

        echo '<h2>' . esc_html($labels['subtitle']) . '</h2>';

        $builtin = self::list_builtin_svgs($type);
        if (!$builtin) {
            echo '<p>No built-in icons found.</p></div>';
            return;
        }

        self::ensure_dir($type);

        $custom_dir = self::custom_dir_abs($type);
        $custom_url = self::custom_dir_url($type);
        $detail_tokens = self::detail_icon_tokens();

        echo '<table class="widefat striped" style="max-width:1280px;">';
        echo '<thead><tr>';
        echo '<th style="width:180px;">Built-in</th>';
        echo '<th style="width:220px;">Name</th>';
        echo '<th style="width:280px;">Placeholder</th>';
        echo '<th style="width:180px;">Custom</th>';
        echo '<th style="width:320px;">Action</th>';
        echo '</tr></thead><tbody>';

        foreach ($builtin as $abs) {
            $name = basename($abs);
            $builtin_svg = file_get_contents($abs);
            $custom_file = $custom_dir . $name;
            $custom_exists = file_exists($custom_file);

            echo '<tr>';

            echo '<td style="vertical-align:middle;">
                <div style="width:52px;height:52px;border:1px solid #ddd;background:#fff;padding:6px;border-radius:6px;display:flex;align-items:center;justify-content:center;">'
                . $builtin_svg .
                '</div>
            </td>';

            echo '<td style="vertical-align:middle;"><code>' . esc_html($name) . '</code></td>';

            // A weather icon goes into manual templates through one token; the
            // forecast picks which icon it shows.
            $token_text = $type === 'details' ? ($detail_tokens[$name] ?? '—') : '{{ weather_icon }}';
            echo '<td style="vertical-align:middle;"><code>' . esc_html($token_text) . '</code></td>';

            echo '<td style="vertical-align:middle;">';
            if ($custom_exists) {
                echo '<img src="' . esc_url($custom_url . rawurlencode($name)) . '" style="width:52px;height:52px;border:1px solid #ddd;background:#fff;padding:6px;border-radius:6px;">';
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            echo '</td>';

            echo '<td style="vertical-align:middle;">';

            echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:12px;">';
            wp_nonce_field('yrwp_icons_upload', 'yrwp_icons_nonce');
            echo '<input type="hidden" name="action" value="yrwp_icons_upload">';
            echo '<input type="hidden" name="icon_type" value="' . esc_attr($type) . '">';
            echo '<input type="hidden" name="force_name" value="' . esc_attr($name) . '">';
            echo '<input type="file" name="svg" class="yrwp-file-input" accept=".svg,image/svg+xml" required style="margin-bottom:6px;"><br>';
            $button_label = $custom_exists ? 'Replace' : 'Add';
            echo '<button type="submit" class="button button-primary yrwp-add-btn" style="display:none;">' . esc_html($button_label) . '</button>';
            echo '</form>';

            if ($custom_exists) {
                $del_url = wp_nonce_url(
                    admin_url('admin-post.php?action=yrwp_icons_delete&icon_type=' . rawurlencode($type) . '&file=' . rawurlencode($name)),
                    'yrwp_icons_delete',
                    'yrwp_icons_nonce'
                );

                echo '<a class="button button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Delete custom icon?\');">Delete</a>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".yrwp-file-input").forEach(function(input) {
                    input.addEventListener("change", function() {
                        const form = input.closest("form");
                        const btn = form.querySelector(".yrwp-add-btn");
                        btn.style.display = input.files.length > 0 ? "inline-block" : "none";
                    });
                });
            });
        </script>';
        echo '</div>';
    }

    public static function handle_upload()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        if (!wp_verify_nonce($_POST['yrwp_icons_nonce'] ?? '', 'yrwp_icons_upload')) wp_die('Bad nonce');

        $type = self::normalize_type($_POST['icon_type'] ?? 'weather');

        if (empty($_FILES['svg']['tmp_name'])) self::redirect_err('No file uploaded', $type);
        if (!self::ensure_dir($type)) self::redirect_err('Upload folder not writable', $type);

        $forced = sanitize_file_name($_POST['force_name'] ?? '');
        $safe_name = self::safe_name($forced ?: ($_FILES['svg']['name'] ?? ''));

        $raw = file_get_contents($_FILES['svg']['tmp_name']);
        if ($raw === false) self::redirect_err('Cannot read upload', $type);

        $clean = self::sanitize_svg($raw);
        if ($clean === '') self::redirect_err('Invalid SVG', $type);

        $dest = self::custom_dir_abs($type) . $safe_name;
        if (!file_put_contents($dest, $clean)) {
            self::redirect_err('Failed to save file', $type);
        }

        self::redirect_ok('Uploaded: ' . $safe_name, $type);
    }

    public static function handle_delete()
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        if (!wp_verify_nonce($_GET['yrwp_icons_nonce'] ?? '', 'yrwp_icons_delete')) wp_die('Bad nonce');

        $type = self::normalize_type($_GET['icon_type'] ?? 'weather');
        $file = sanitize_file_name($_GET['file'] ?? '');
        $path = self::custom_dir_abs($type) . $file;

        if (!$file || !file_exists($path)) self::redirect_err('File not found', $type);

        unlink($path);
        self::redirect_ok('Deleted: ' . $file, $type);
    }

    private static function normalize_type(string $type): string
    {
        return $type === 'details' ? 'details' : 'weather';
    }

    private static function render_tab(string $key, string $label, string $active): void
    {
        $url = admin_url('admin.php?page=yrwp_icons&tab=' . $key);
        $cls = 'nav-tab' . ($key === $active ? ' nav-tab-active' : '');
        echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    private static function redirect_ok(string $msg, string $type): void
    {
        wp_safe_redirect(admin_url('admin.php?page=yrwp_icons&tab=' . rawurlencode($type) . '&msg=' . rawurlencode($msg)));
        exit;
    }

    private static function redirect_err(string $err, string $type): void
    {
        wp_safe_redirect(admin_url('admin.php?page=yrwp_icons&tab=' . rawurlencode($type) . '&err=' . rawurlencode($err)));
        exit;
    }
}
