<?php

/**
 * The dashboard-specific functionality of the plugin.
 *
 * @link       https://github.com/AZdv/wp-plugin-packer
 * @since      1.0.0
 *
 * @package    Wp_Plugin_Packer
 * @subpackage Wp_Plugin_Packer/admin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Wp_Plugin_Packer_Admin {

	private $wp_plugin_packer;
	private $version;
	private $options;

	public function __construct( $wp_plugin_packer, $version ) {

		$this->wp_plugin_packer = $wp_plugin_packer;
		$this->version = $version;
		$this->default_pack = 'Default Pack'; //Name of the Default Pack.
		$this->default_pack_slug = sanitize_title( $this->default_pack );

		add_action( 'admin_menu', array( $this, 'plugin_packer_menu' ) );
		add_action( 'admin_init', array( $this, 'plugin_packer_init' ) );
		add_action( 'upload_mimes', array( $this, 'add_json_mime' ) );
		add_action( 'wp_ajax_wp_plugin_packer_import_file', array( $this, 'import_file' ) );
		add_action( 'wp_ajax_sanitize_title', array( $this, 'handle_sanitize_title' ) );
		add_action( 'upgrader_process_complete', array( $this, 'add_plugin_to_pack' ), 99 );
		add_action( 'admin_notices', array( $this, 'missing_plugins_notices' ) );
	}

	/**
	 * Register the stylesheets for the Dashboard.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->wp_plugin_packer, plugin_dir_url( __FILE__ ) . 'css/wp-plugin-packer-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the dashboard.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_media();
		wp_register_script( $this->wp_plugin_packer, plugin_dir_url( __FILE__ ) . 'js/wp-plugin-packer-admin.js', array( 'jquery', 'jquery-ui-sortable', 'media' ), $this->version, false );
		
		wp_localize_script( $this->wp_plugin_packer, 'translationStrings', array(
			'wp_plugin_packer' => $this->wp_plugin_packer,
			'nonce' => wp_create_nonce( $this->wp_plugin_packer ),
			'download_export_file' => __( 'Download Export File' ),
			'import_modal_title' => __( 'Import Plugin Packs (json format only)' ),
			'import_confirm' => __( "Import will overwrite your current Plugin Packs! \nAre you sure?" ),
			'deactivate_url' => admin_url( 'plugins.php?deactivate=true' ),
			'nonce_plugins' => wp_create_nonce( 'bulk-plugins' ),
		) );
		
		wp_enqueue_script( $this->wp_plugin_packer );
	}

	/**
	 * Admin Init
	 */
	public function plugin_packer_init() {
		if ( isset( $_GET['action'] ) ) {
			switch ( $_GET['action'] ) {
				case 'export_file':
					$this->handle_generate_export_file(); //Outputting json & exiting
					break;
				case 'delete':
					if ( isset( $_GET['plugin'] ) )
						$this->handle_delete_plugin( $_GET['plugin'] ); //Deleting and redirecting
					break;
			}
		}

		if ( isset( $_POST['plugin_packs'] ) && wp_verify_nonce( $_POST['_wpnonce'], $this->wp_plugin_packer . '-options' ) ) {
			$this->set_plugin_packs( $_POST['plugin_packs'] );
			wp_redirect( admin_url( 'options-general.php?page=' . $this->wp_plugin_packer ) );
		}
		register_setting( $this->wp_plugin_packer, $this->wp_plugin_packer . '_plugin_packs' ); // is this needed?

		add_settings_section( 
			$this->wp_plugin_packer . '_plugin_packs_section', // ID
			'', // Title
			array( $this, 'plugin_packs_callback' ), // callback
			$this->wp_plugin_packer // page's menu slug (from add_options_page)
		);

	}

	public function handle_admin_display() {
			ob_start();
			require_once plugin_dir_path( __FILE__ ) . 'partials/wp-plugin-packer-admin-display.php';
			echo ob_get_clean();
	}
	
	public function plugin_packer_menu() {
		add_options_page( 'WP Plugin Packer', 'WP Plugin Packer', 'manage_options', $this->wp_plugin_packer, array( $this, 'handle_admin_display' ) );

	}

	public function plugin_packs_callback() {
		$plugin_packs = $this->get_plugin_packs();
		$i = 0;
		$str = '';
		$str .= '<div class="drag-and-drop">' . __( 'Drag & Drop to sort' ) . '</div>';
		foreach( $plugin_packs as $pack ) {
			$str .= '<div class="single-pack">';
			$str .= sprintf( '<div class="single-pack-title"><input type="checkbox" class="select-pack" /><h3 class="editable hint--right" data-hint="Click to edit">%s</h3><input type="text" class="pack-title" value="%s" /><input type="hidden" class="pack-slug" value="%s" /></div>', $pack['name'], esc_attr( $pack['name'] ), sanitize_title( $pack['name'] ) );
			if ( $i )
				$str .= '<div class="button remove-pack">' . __( 'Remove Pack' ) . '</div>';
			$str .= sprintf( '<table class="%s widefat plugins"><tbody>', $this->wp_plugin_packer );
			foreach( $pack['plugins'] as $plugin ) {
				$missing = isset( $plugin['missing'] ) && $plugin['missing'] ? true : false;
				$str .= sprintf( '<tr class="%s %s">', is_plugin_active( $plugin['file'] ) ? 'active' : 'inactive', $missing ? 'missing' : '' );
				$str .= sprintf( '<th class="plugin-name"><label><input type="checkbox" name="%s" %s /></label></th>' , $this->wp_plugin_packer . '_plugin_packs', $missing ? 'disabled="disabled"' : '' );
				$str .= sprintf( '<td class="plugin-title"><strong class="plugin-title-value">%s</strong> %s<div class="version">Version: <span class="version-value">%s</span> , %s</div><input type="hidden" class="plugin_file_name" value="%s" />' , $plugin['name'], $missing ? __( ' <em>Missing! Please Install Plugin</em>' ) : '', $plugin['version'], ( is_plugin_active( $plugin['file'] ) ? 'Activate' : 'Inactive' ), $plugin['file'] );
				if ( $missing )
					$str .= '<a href="' . admin_url( 'options-general.php?page=' . $this->wp_plugin_packer . '&action=delete&plugin=' . sanitize_title( $plugin['name'] ) ) . '">' . __( 'Delete' ) . '</a>';
				$str .= '</td></tr>';
			}
			$str .= sprintf( '<tr class="placeholder"><td colspan="3">%s</td></tr></tbody></table></div>', __( 'Drop Plugins Here') );
			$i++;
		}
		echo $str;
	}

	public function set_plugin_packs( $packs ) {
		if ( ! is_string( $packs ) )
			$packs = addslashes( json_encode( $packs ) );

		update_option( $this->wp_plugin_packer . '_plugin_packs', $packs );
	}
	public function get_plugin_packs() {
		$plugin_packs = get_option( $this->wp_plugin_packer . '_plugin_packs' );
		$missing_plugins = array();
		if ( false === $plugin_packs ) {
			$plugin_packs = $this->init_plugin_packs();
		} else {
			if ( is_string( $plugin_packs ) ) {
				$plugin_packs = json_decode( stripcslashes( $plugin_packs ), true );
			}
			$existing_plugins = get_plugins();
			//Running through packs to see if any plugin was deleted since last update
			foreach ( $plugin_packs as &$pack ) {
				foreach ( $pack['plugins'] as $key => &$plugin ) {
					if ( ! isset( $existing_plugins[ $plugin['file'] ]) ) {
						$plugin['missing'] = true;
						$missing_plugins[ $plugin['file'] ] = $plugin;
					} else {
						if ( isset( $missing_plugins[ $plugin['file'] ] ) )
							unset( $missing_plugins[ $plugin['file'] ] );

						unset( $plugin['missing'] );
						
					}
				}
			}
			
		}
		$this->set_missing_plugins( $missing_plugins );

		return $plugin_packs;
	}

	public function init_plugin_packs() {
		$plugins_array = get_plugins();
		$plugins = new stdClass();
		$plugins_update_urls = get_site_option( '_site_transient_update_plugins' );
		$plugins_update_urls = (array)$plugins_update_urls->response + (array)$plugins_update_urls->checked + (array)$plugins_update_urls->no_update;
		//preparing default plugins structure
		foreach( $plugins_array as $key => $plugin ) {
			$plugins->$key->name = $plugin[ 'Name' ];
			$plugins->$key->version = $plugin[ 'Version' ];
			$plugins->$key->file = $key;
			$plugins->$key->wp_api_slug = isset( $plugins_update_urls[ $key ] ) ? $plugins_update_urls[ $key ]->slug : null;
		}

		//packing all plugins in Default Pack (initial state)

		$plugin_packs->{ $this->default_pack_slug }->name = $this->default_pack;
		$plugin_packs->{ $this->default_pack_slug }->plugins = $plugins;

		$this->set_plugin_packs( $plugin_packs );
		
		return $plugin_packs;
	}

	public function set_missing_plugins( $missing_plugins ) {
		if ( ! is_string( $missing_plugins ) )
			$missing_plugins = json_encode( $missing_plugins );

		update_option( $this->wp_plugin_packer . '_missing_plugins', $missing_plugins );
	}
	public function get_missing_plugins( $array = false ) {
		$missing_plugins = get_option( $this->wp_plugin_packer . '_missing_plugins' );
		if ( $array ) {
			$missing_plugins = json_decode( stripcslashes( $missing_plugins ) );
		}
		return $missing_plugins;
	}

	// Import/Export
	public function handle_generate_export_file() {
		if ( ! wp_verify_nonce( $_GET['nonce'], $this->wp_plugin_packer ) ) {
			wp_send_json_error( array( 'error' => __( 'Bad Request' ) ) );
		}
		if ( isset( $_GET['plugin_files'] ) && count( $_GET['plugin_files'] ) ) {
			$plugin_packs = json_decode( stripcslashes( get_option( $this->wp_plugin_packer . '_plugin_packs' ) ) );
			foreach ( $plugin_packs as $key => &$pack ) {
				//Going through plugins, removing the ones that are not included in export
				foreach ( $pack->plugins as $e_key => $plugin ) {
					$plugin_in_export = false;
					foreach ( $_GET['plugin_files'] as $plugin_file ) {
						if ( $plugin_file == $plugin->file ) {
							$plugin_in_export = true;
							break;
						}
					}
					if ( ! $plugin_in_export ) {
						unset( $pack->plugins->$e_key );
						$check_me = (array) $pack->plugins;
						if ( empty( $check_me ) ) {
							unset( $plugin_packs->$key );
						}
					}
				}
			}
			$plugins_json = Wp_Plugin_Packer_Helper::prettyPrint( stripcslashes( json_encode( $plugin_packs ) ) );
		} else {
			$plugins_json = Wp_Plugin_Packer_Helper::prettyPrint( stripcslashes( get_option( $this->wp_plugin_packer . '_plugin_packs' ) ) );
		}
		
		$length = strlen( $plugins_json );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename=export.json' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . $length );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		echo $plugins_json;
		exit;
	}

	public function add_json_mime( $mime_types = array() ) {
		//Adding JSON to allowed mime types, to enable json upload
		$mime_types[ 'json' ] = 'application/json';

		return $mime_types;
	}

	public function import_file() {
		if ( ! wp_verify_nonce( $_POST['nonce'], $this->wp_plugin_packer ) || ! is_numeric( $_POST['attachment_id'] ) ) {
			wp_send_json_error( array( 'error' => __( 'Bad Request' ) ) );
		}
		$file = get_attached_file( $_POST['attachment_id'] );
		if ( file_exists( $file ) ) {
			$file = file_get_contents( $file );
			$imported_plugins_array = json_decode( $file );
			$existing_plugins_array = get_plugins();
			$existing_plugin_packs = $this->get_plugin_packs();
			$none_existing_plugins = array();

			//TODO: Add an option to overwrite OR merge the imported plugin packs
			foreach ( $imported_plugins_array as $key => $pack ) {
				foreach ( $pack->plugins as $plugin ) {
					$plugin_exists = false;
					foreach ( $existing_plugin_packs as &$e_pack ) {
						foreach ( $e_pack['plugins'] as $e_key => $e_plugin ) {
							if ( is_object( $e_plugin ) )
								$e_plugin = (array) $e_plugin;

							if ( $e_plugin['file'] == $plugin->file ) {
								$plugin_exists = true;
								//If the plugin exists but in different pack, let's remove from existing pack
								if ( $e_pack['name'] != $pack->name ) {
									unset( $e_pack['plugins'][ $e_key ] );
								}
							} else if ( sanitize_title( $plugin->name ) == $this->wp_plugin_packer ) {
								$plugin_exists = true;
							}
						}
					}
					if ( ! $plugin_exists ) {
						$none_existing_plugins[] = array(
							'name' => $plugin->name,
							'wp_api_slug' => $plugin->wp_api_slug,
							'required' => true,
						);
					}
					$existing_plugin_packs[ $key ][ 'plugins' ][ $plugin->file ] = $plugin;
					if ( ! isset( $existing_plugin_packs[ $key ]['name'] ) ) {
						$existing_plugin_packs[ $key ]['name'] = $pack->name;
					}

				}
			}

			if ( ! empty( $none_existing_plugins ) ) {
				//the missing plugins are for the admin_notice
				$this->set_missing_plugins( $none_existing_plugins );
			}

			$this->set_plugin_packs( $existing_plugin_packs );

			wp_send_json_success( array( 'message' => __( 'Import Successful' ) ) );
		}
	}

	public function add_plugin_to_pack( $args ) {
		$plugin_packs = $this->get_plugin_packs();
		wp_cache_delete( 'plugins', 'plugins' );
		$existing_plugins = get_plugins();
		$file = null;
		foreach ( $existing_plugins as $key => $plugin ) {
			if ( isset( $plugin['Name'] ) && $plugin['Name'] == $args->skin->api->name ) {
				$file = $key;
				break;
			}
		}
		$add_plugin = true; //If plugin exists, don't add the plugin - just remove its "missing" flag
		foreach ( $plugin_packs as &$pack ) {
			foreach ( $pack['plugins'] as $key => $plugin ) {
				if ( $plugin['name'] == $args->skin->api->name ) {
					$add_plugin = false;
					unset( $pack['plugins'][ $key ]['missing'] );
				}
			}
		}
		//Adding plugin to First Pack
		if ( $add_plugin ) {
			$plugin_packs[ $this->default_pack_slug ]['plugins'][] = array(
				'name' => $args->skin->api->name,
				'version' => $args->skin->api->version,
				'file' => $file,
				'wp_api_slug' => $args->skin->api->slug,
			);
		}

		$this->set_plugin_packs( $plugin_packs );
	}

	/**
	 * missing plugins notices
	 */
	public function missing_plugins_notices() {
		$screen = get_current_screen();
		if ( $screen->id == 'settings_page_' . $this->wp_plugin_packer ) {
			$missing_plugins = $this->get_missing_plugins( true );
			$notice = '';
			if ( ! empty ( $missing_plugins ) )  {
				$notice .= '<div class="updated"><p>' . __( 'The following plugins are in packs, but are not installed: ' );
				$i = 0;
				foreach ( $missing_plugins as $plugin ) {
					$notice .= ( $i == 0 ? '' : ', ' ) . '<a target="_blank" href="';
					if ( $plugin->wp_api_slug ) {
						$notice .= admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin->wp_api_slug );
					} else {
						$notice .= admin_url( 'plugin-install.php?tab=search&type=term&s=' . urlencode( $plugin->name ) );
					}
					$notice .= '">' . $plugin->name . '</a>';
					$i++;
				}
				$notice .= '</p></div>';
			}
			if ( isset( $_GET['deleted'] ) ) {
				$notice .= '<div class="updated"><p>' . __( 'Successfully deleted the plugin: ' ) . $_GET['deleted'];
				$notice .= '</p></div>';
			}
			echo $notice;
		}
	}

	public function handle_delete_plugin( $plugin_name ) {
		$plugin_packs = $this->get_plugin_packs();
		foreach ( $plugin_packs as &$pack ) {
			foreach ( $pack['plugins'] as $key => $plugin ) {
				if ( sanitize_title( $plugin['name'] ) == $plugin_name ) {
					unset( $pack['plugins'][ $key ] );
				}
			}
		}
		$this->set_plugin_packs( $plugin_packs );
		wp_redirect( admin_url( 'options-general.php?page=' . $this->wp_plugin_packer . '&deleted=' . $plugin_name ) );
	}

	public function handle_sanitize_title() {
		if ( isset( $_POST['sanitize_title'] ) ) {
			wp_send_json_success( array( 'message' => sanitize_title( $_POST['sanitize_title'] ) ) );
		}
	}

}  