<?php

namespace OmnipayWP\EDD;

if ( ! class_exists( __NAMESPACE__ . '\License_System' ) ) :

	class License_System {

		public function init() {
			add_action( 'admin_init', array( $this, 'settings_data_update' ) );
			add_action( 'admin_init', array( $this, 'plugin_updater' ), 0 );
			add_action( 'admin_init', array( $this, 'activate_license' ), 0 );

			add_action( 'admin_notices', array( $this, 'license_admin_notice' ) );
		}


		/**
		 * Where all the Getter magic happens.
		 *
		 * @param string $key
		 * @return mixed|void
		 */
		public function __get( $key ) {
			// $this->option_name recursively call magic __get to get the set option_name config value.
			if ( $key == 'license_key' ) {
				return edd_get_option( "{$this->option_name}_license_key", '' );
			}

			if ( $key == 'license_status' ) {
				return get_option( "{$this->option_name}_license_status", '' );
			}

			return $this->$key;
		}


		/**
		 * Where all the Setter magic happens.
		 *
		 * @param string $key
		 * @param string $value
		 */
		public function __set( $key, $value ) {
			$this->$key = $value;
		}


		/**
		 * EDD Plugin update method
		 */
		public function plugin_updater() {

			// retrieve our license key from the DB
			$license_key = $this->license_key;

			if ( class_exists( 'EDD_SL_Plugin_Updater' ) && is_admin() ) {

				// setup the updater
				$edd_updater = new \EDD_SL_Plugin_Updater(
					$this->store_url,
					$this->plugin_path,
					array(
						'version'   => $this->version_number,            // current version number
						'license'   => $license_key,        // license key (used get_option above to retrieve from DB)
						'item_name' => $this->item_name,    // name of this plugin
						'author'    => $this->plugin_developer  // author of this plugin
					)
				);
			}

		}


		/** Activate license */
		public function activate_license() {

			// retrieve the license from the database
			$license = $this->license_key;

			// only run update if license status isn't valid
			if ( empty( $license ) || 'valid' == $this->license_status ) {
				return;
			}

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->item_name ), // the name of our product in EDD
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, $this->store_url ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				) );

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "valid" or "invalid"
			update_option( "{$this->option_name}_license_status", @$license_data->license );
		}


		/**
		 * Deactivate license
		 */
		public function deactivate_license() {

			// retrieve the license from the database
			$license = $this->license_key;

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->item_name ),
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, $this->store_url ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				)
			);

			// make sure the response came back okay
			if ( is_wp_error( $response ) ) {
				return;
			}

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed"
			if ( $license_data->license == 'deactivated' ) {
				delete_option( "{$this->option_name}_license_status" );
			}
		}


		/**
		 * Deactivate license and license status when license key is changed.
		 *
		 * @return mixed
		 */
		public function settings_data_update() {
			// only submit if form is bing submitted / POSTed by checking for license key field.

			// set to null if undefined or null.
			$POSTed_license_key_value = @$_POST['edd_settings']["{$this->option_name}_license_key"] ?: null;

			if ( ! isset( $POSTed_license_key_value ) ) {
				return;
			}

			$new = trim( sanitize_text_field( $POSTed_license_key_value ) );
			$old = edd_get_option( "{$this->option_name}_license_key" );

			if ( $new != $old ) {
				$this->deactivate_license();
				delete_option( "{$this->option_name}_license_status" );
			}
		}

		/**
		 * Admin notice to activate license when license status isn't valid.
		 */
		public function license_admin_notice() {
			// retrieve the license from the database
			$license_key = $this->license_key;
			// if license key isn't saved or license status is not valid, display notice
			if ( empty( $license_key ) || 'valid' != $this->license_status ) : ?>
				<div id="message" class="error notice"><p>
						<?php printf(
							__(
								'Enter and save your license key to receive %s plugin updates. <strong><a href="%s">Do it now</a></strong>.'
							),
							"<strong>{$this->item_name}</strong>",
							$this->settings_page_url
						); ?>
					</p></div>
			<?php endif;
		}
	}

endif;
