<?php

/**
 * Plugin Name: Sentry for WordPress
 * Plugin URI: https://github.com/astappiev/wp-sentry
 * Description: Minimal Sentry error tracking for WordPress
 * License: MIT
 * Author: Oleh Astappiev
 * Constants: SENTRY_DSN, SENTRY_BROWSER_DSN
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function get_wp_sentry_options(): array
{
    return [
        'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production',
        'error_types' => E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING,
        'tags' => [
            'wordpress' => get_bloginfo('version'),
            'language' => get_bloginfo('language'),
        ],
    ];
}

if (class_exists('\Sentry\SentrySdk') && defined('SENTRY_DSN') && !defined('SENTRY_LOADED')) {
    define('SENTRY_LOADED', true);

    \Sentry\init(array_merge(get_wp_sentry_options(), [
        'dsn' => SENTRY_DSN,
    ]));

    add_action('sentry_capture_message', function ($message, $level = 'info', $hint = []) {
        \Sentry\captureMessage($message, new \Sentry\Severity($level), \Sentry\EventHint::fromArray($hint));
    }, 10, 3);

    add_action('sentry_capture_exception', function ($e, $hint = []) {
        \Sentry\captureException($e, \Sentry\EventHint::fromArray($hint));
    }, 10, 2);

    add_action('set_current_user', function (): void {
        \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && $current_user->exists()) {
                $scope->setUser([
                    'id' => $current_user->ID,
                    'username' => $current_user->user_login,
                ]);
            }
        });
    });
}

if (defined('SENTRY_BROWSER_DSN')) {
    add_action('wp_enqueue_scripts', function () {
        $options = array_merge(get_wp_sentry_options(), [
            'dsn' => SENTRY_BROWSER_DSN,
        ]);

        wp_enqueue_script('sentry-browser-cdn', 'https://browser.sentry-cdn.com/10.34.0/bundle.min.js', [], null, ['strategy' => 'defer', 'in_footer' => false]);
        wp_add_inline_script('sentry-browser-cdn', sprintf("try{var opts=%s;if(typeof Sentry!='undefined'){Sentry.init(opts)}else{document.getElementById('sentry-browser-cdn-js').addEventListener('load',()=>{Sentry.init(opts)})}}catch(e){console.error(e)}", json_encode($options)));
    }, 0);
}
