<?php
/*
Plugin Name: bbPress Beta Tester
Description: Lets you easily upgrade to the latest trunk version of bbPress.
Version: 0.1
Author: Daniel Dvorkin
Author URI: http://twitter.com/MZAWeb
*/


class bbPress_beta_tester {

	function __construct() {
		add_filter( 'http_response', array( $this, 'filter_http_response' ), 10, 3 );

		// Doing this in plugins_loaded instead of directly here to make sure bbpress is loaded
		add_action( 'plugins_loaded', array( $this, 'add_upgrade_warning' ) );
	}

	/**
	 *  Clear the update_plugins transient on plugin activation/deactivation
	 *  to add / remove our hijack.
	 */
	function reset_update_plugins_transient() {
		delete_site_transient( 'update_plugins' );
	}

	/**
	 *
	 * Hijack the version check against WP.org and make our own check
	 * against the latest version in trunk. If there's a newer trunk
	 * version than the one the user has installed, force the update
	 * notification.
	 *
	 * @param $response
	 * @param $r
	 * @param $url
	 *
	 * @return array
	 */
	function filter_http_response( $response, $r, $url ) {

		if ( $url !== 'http://api.wordpress.org/plugins/update-check/1.0/' )
			return $response;

		if ( !function_exists( 'bbpress' ) || $this->get_latest_trunk_version() === bbpress()->version )
			return $response;

		$wpapi = maybe_unserialize( $response['body'] );

		if ( empty( $wpapi ) )
			$wpapi = array();

		$basename = bbpress()->basename;
		$slug     = bbpress()->domain;

		$wpapi[$basename]                 = new stdClass;
		$wpapi[$basename]->slug           = $slug;
		$wpapi[$basename]->new_version    = 'trunk';
		$wpapi[$basename]->url            = "http://wordpress.org/extend/plugins/$slug/";
		$wpapi[$basename]->package        = "http://downloads.wordpress.org/plugin/$slug.zip";
		$wpapi[$basename]->upgrade_notice = " <strong>" . __( 'This release is a beta.', 'plugin-beta-tester' ) . "</strong>";

		$response['body'] = serialize( $wpapi );

		return $response;
	}

	/**
	 * Add a warning message besides the upgrade notification
	 * to warn the user about the risk of running trunk.
	 */
	function add_upgrade_warning() {
		/* Need to check again for function_exists( 'bbpress' )
		 * because after the plugin upgrade process this gets
		 * excecuted _before_ the re-activation.
		 */
		if ( function_exists( 'bbpress' ) )
			add_action( "in_plugin_update_message-" . bbpress()->basename, array( $this, 'beta_message' ), 10, 2 );
	}

	/**
	 * Add the actual warning message
	 * @param $plugin_data
	 * @param $r
	 */
	function beta_message( $plugin_data, $r ) {
		echo sprintf( ' <span style="color:red;">%s</span>', __( 'Warning: trunk version may break your site.', 'bbpress-beta-tester' ) );
	}

	/**
	 * Get the latest bbpress main file from trunk and parse it to get
	 * the latest tagged bleeding version code.
	 *
	 * @return null|string
	 */
	function get_latest_trunk_version() {

		$svn_content = wp_remote_get( 'http://plugins.svn.wordpress.org/bbpress/trunk/bbpress.php' );

		if ( is_wp_error( $svn_content ) )
			return null;

		$bbpress_code = wp_remote_retrieve_body( $svn_content );
		$bbpress_code = str_replace( "\r", "\n", $bbpress_code );

		// Taken from get_file_data in wp-includes/functions.php
		preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', $bbpress_code, $match );

		if ( empty( $match[1] ) )
			return null;

		return _cleanup_header_comment( $match[1] );
	}


}

$bbp_beta_tester = new bbPress_beta_tester();

register_activation_hook( __FILE__, array( $bbp_beta_tester, 'reset_update_plugins_transient' ) );
register_deactivation_hook( __FILE__, array( $bbp_beta_tester, 'reset_update_plugins_transient' ) );
