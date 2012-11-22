<?php
/*
Plugin Name: bbPress Beta Tester
Description: Lets you easily upgrade to the latest trunk version of bbPress.
Version: 0.1
Author: Daniel Dvorkin
Author URI: http://twitter.com/MZAWeb

Based on "Plugin beta tester" by mitcho (Michael Yoshitaka Erlewine)
*/


class bbPress_beta_tester {

	function __construct() {
		add_filter( 'http_response', array( $this, 'filter_http_response' ), 10, 3 );
		add_action( 'plugins_loaded', array( $this, 'add_upgrade_warning' ) );
	}

	function reset_update_plugins_transient() {
		delete_site_transient( 'update_plugins' );
	}

	function filter_http_response( $response, $r, $url ) {

		if ( $url !== 'http://api.wordpress.org/plugins/update-check/1.0/' || !function_exists( 'bbpress' ) )
			return $response;

		$wpapi = maybe_unserialize( $response['body'] );

		if ( !$wpapi )
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

	function add_upgrade_warning() {
		if ( function_exists( 'bbpress' ) )
			add_action( "in_plugin_update_message-" . bbpress()->basename, array( $this, 'beta_message' ), 10, 2 );
	}

	function beta_message( $plugin_data, $r ) {
		echo sprintf( ' <span style="color:red;">%s</span>', __( 'Warning: trunk version may break your site.', 'bbpress-beta-tester' ) );
	}

}

$bbp_beta_tester = new bbPress_beta_tester();

register_activation_hook( __FILE__, array( $bbp_beta_tester, 'reset_update_plugins_transient' ) );
register_deactivation_hook( __FILE__, array( $bbp_beta_tester, 'reset_update_plugins_transient' ) );
