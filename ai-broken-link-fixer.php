<?php
/**
 * Plugin Name:       AI Broken Link Fixer
 * Plugin URI:        https://github.com/RenanM97/ai-broken-link-fixer
 * Description:       Find and fix broken links automatically. Pathfinder AI analyzes your content and suggests the best replacement URLs from your own site — one click to fix.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Renan Marques
 * Author URI:        https://github.com/RenanM97
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-broken-link-fixer
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABLF_VERSION', '1.0.0' );
define( 'ABLF_DB_VERSION', '1.1.0' );
define( 'ABLF_PLUGIN_FILE', __FILE__ );
define( 'ABLF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABLF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABLF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Freemius checkout URLs.
define( 'ABLF_URL_PRO',          'https://checkout.freemius.com/plugin/28106/plan/46424/licenses/1/' );
define( 'ABLF_URL_AGENCY',       'https://checkout.freemius.com/plugin/28106/plan/46425/licenses/unlimited/' );
define( 'ABLF_URL_CREDITS_500',  'https://checkout.freemius.com/plugin/28106/plan/46426/licenses/1/' );
define( 'ABLF_URL_CREDITS_1000', 'https://checkout.freemius.com/plugin/28106/plan/46428/licenses/1/' );
define( 'ABLF_URL_CREDITS_5000', 'https://checkout.freemius.com/plugin/28106/plan/46429/licenses/1/' );

/**
 * Initialize Freemius SDK.
 */
if ( ! function_exists( 'ablf_fs' ) ) {
	function ablf_fs() {
		global $ablf_fs;
		if ( ! isset( $ablf_fs ) ) {
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
			$ablf_fs = fs_dynamic_init( array(
				'id'                  => '28106',
				'slug'                => 'ai-broken-link-fixer',
				'type'                => 'plugin',
				'public_key'          => 'pk_c371de3397266ca182052cd6c7432',
				'is_premium'          => true,
				'has_premium_version' => true,
				'has_addons'          => true,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				'menu'                => array(
					'support' => false,
				),
			) );
		}
		return $ablf_fs;
	}
	ablf_fs();
	do_action( 'ablf_fs_loaded' );
}

/**
 * PSR-4-ish autoloader for ABLF classes under includes/ and admin/.
 * Maps ABLF_Foo_Bar → class-ablf-foo-bar.php.
 */
spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'ABLF_' ) !== 0 ) {
		return;
	}
	$filename = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
	$paths    = array(
		ABLF_PLUGIN_DIR . 'includes/' . $filename,
		ABLF_PLUGIN_DIR . 'admin/' . $filename,
	);
	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

register_activation_hook( __FILE__, array( 'ABLF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ABLF_Deactivator', 'deactivate' ) );

/**
 * Boot plugin components on plugins_loaded.
 */
function ablf_bootstrap() {
	load_plugin_textdomain( 'ai-broken-link-fixer', false, dirname( ABLF_PLUGIN_BASENAME ) . '/languages' );

	// Run DB migrations for existing installs when DB version is outdated.
	if ( get_option( 'ablf_db_version', '0' ) !== ABLF_DB_VERSION ) {
		if ( class_exists( 'ABLF_Activator' ) ) {
			ABLF_Activator::maybe_upgrade_db();
		}
	}

	if ( class_exists( 'ABLF_Scheduler' ) ) {
		ABLF_Scheduler::register_schedules();
		ABLF_Scheduler::register_hooks();
	}

	if ( is_admin() && class_exists( 'ABLF_Admin' ) ) {
		ABLF_Admin::init();
	}

	if ( class_exists( 'ABLF_Scanner' ) ) {
		ABLF_Scanner::register_hooks();
	}

	if ( class_exists( 'ABLF_Pathfinder' ) ) {
		ABLF_Pathfinder::register_hooks();
	}

	if ( class_exists( 'ABLF_Fixer' ) ) {
		ABLF_Fixer::register_hooks();
	}

	if ( class_exists( 'ABLF_Redirect' ) ) {
		ABLF_Redirect::register_hooks();
	}
}
add_action( 'plugins_loaded', 'ablf_bootstrap' );

/**
 * Small helpers.
 */
function ablf_get_option( $key, $default = '' ) {
	return get_option( $key, $default );
}

function ablf_encrypt( $value ) {
	if ( ! $value ) {
		return '';
	}
	if ( ! defined( 'AUTH_KEY' ) || ! defined( 'AUTH_SALT' ) ) {
		return base64_encode( $value );
	}
	$iv = substr( AUTH_SALT, 0, 16 );
	$encrypted = openssl_encrypt( $value, 'AES-256-CBC', AUTH_KEY, 0, $iv );
	return $encrypted ? $encrypted : '';
}

function ablf_decrypt( $value ) {
	if ( ! $value ) {
		return '';
	}
	if ( ! defined( 'AUTH_KEY' ) || ! defined( 'AUTH_SALT' ) ) {
		return base64_decode( $value );
	}
	$iv = substr( AUTH_SALT, 0, 16 );
	$decrypted = openssl_decrypt( $value, 'AES-256-CBC', AUTH_KEY, 0, $iv );
	return $decrypted ? $decrypted : '';
}
