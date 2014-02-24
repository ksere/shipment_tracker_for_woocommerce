<?php
/*
Plugin Name: Shipment Tracker for WooCommerce 
Plugin URI: http://www.weice.in/wordex
Description: Customer can able to track their shipment.
Version: 1.0.4
Author: WEICE SOFTSYSTEMS
Author URI: http://www.weice.in
*/


/* Check if woocommerce plugin is active */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	class Shipment_Tracker {

		var $providers;

		function __construct() {

			$this->providers = array(
				
				'Australia Post' => 'http://auspost.com.au/track/track.html?id=',
				
				'Austria Post' => 'http://www.post.at/en/track_trace.php?pnum1=',
				
				'BLUEDART' => 'http://www.bluedart.com/servlet/RoutingServlet?handler=tnt&action=awbquery&awb=awb&numbers=',
				
				'Canada Post' => 'http://www.canadapost.ca/cpotools/apps/track/personal/findByTrackNumber?trackingNumber=',
				
				'City Link' => 'http://www.city-link.co.uk/dynamic/track.php?parcel_ref_num=',
				
				'DHL' => 'http://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=',
				
				'DPD' => 'http://track.dpdnl.nl/?parcelnumber=',
				
				'DTDC' => 'http://www.dtdc.in/dtdcTrack/Tracking/consignInfo.asp?strCnno=',
				
				'Fedex' => 'http://www.fedex.com/Tracking?action=track&tracknumbers=',
				
				'GATI' => 'http://www.gati.com/single_dkt_track_int.jsp?dktNo=',
				
				'OnTrac' => 'http://www.ontrac.com/trackingdetail.asp?tracking=',
				
				'POSTINDIA' => 'http://services.ptcmysore.gov.in/Speednettracking/Track.aspx?articlenumber=',
				
				'Posten AB' => 'http://server.logistik.posten.se/servlet/PacTrack?xslURL=/xsl/pactrack/standard.xsl&/css/kolli.css&lang2=SE&kolliid=',
				
				'ParcelForce' => 'http://www.parcelforce.com/portal/pw/track?trackNumber=',
				
				'Royal Mail' => 'http://track2.royalmail.com/portal/rm/track?trackNumber=',
				
				'SAPO' => 'http://sms.postoffice.co.za/TrackingParcels/Parcel.aspx?id=',
				
				'UPS' => 'http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=',
					
				'USPS' => 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=',
				
			);

			add_action( 'admin_print_styles', array( &$this, 'admin_styles' ) );
			add_action( 'add_meta_boxes', array( &$this, 'add_meta_box' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( &$this, 'save_meta_box' ), 0, 2 );
			
			// View Order Page
			add_action( 'woocommerce_view_order', array( &$this, 'display_tracking_info' ) );
			add_action( 'woocommerce_email_before_order_table', array( &$this, 'email_display' ) );
		}

		function admin_styles() {
			wp_enqueue_style( 'shipment_tracker_styles', plugins_url( basename( dirname( __FILE__ ) ) ) . '/style.css' );
		}

		/* Add the shipment meta box in WooCommerce order page */
		function add_meta_box() {
			add_meta_box( 'shipment-tracker-for-woocommerce', __('Shipment Tracker', 'Shipment_Tracker'), array( &$this, 'meta_box' ), 'shop_order', 'side', 'high');
		}
		
		/* Show the meta box for shipment info on the order page */
		function meta_box() {
			global $woocommerce, $post;

			// Providers
			echo '<p class="form-field tracking_provider_field"><label for="tracking_provider">' . __('Provider:', 'wc_shipment_tracking') . '</label><br/><select id="tracking_provider" name="tracking_provider" class="chosen_select" style="width:100%;">';

			$selected_provider = get_post_meta( $post->ID, '_tracking_provider', true );

			foreach ( $this->providers as $provider => $url ) {
				echo '<option value="' . sanitize_title( $provider ) . '" ' . selected( sanitize_title( $provider ), $selected_provider, true ) . '>' . $provider . '</option>';
			}

			echo '</select> ';

			woocommerce_wp_text_input( array(
				'id' 			=> 'tracking_number',
				'label' 		=> __('Tracking number:', 'wc_shipment_tracking'),
				'placeholder' 	=> '',
				'description' 	=> '',
				'value'			=> get_post_meta( $post->ID, '_tracking_number', true )
			) );
			
			woocommerce_wp_text_input( array(
				'id' 			=> 'date_shipped',
				'label' 		=> __('Date shipped:', 'wc_shipment_tracking'),
				'placeholder' 	=> 'YYYY-MM-DD',
				'description' 	=> '',
				'class'			=> 'date-picker-field',
				'value'			=> ( $date = get_post_meta( $post->ID, '_date_shipped', true ) ) ? date( 'Y-m-d', $date ) : ''
			) );

			// Live preview
			echo '<p class="preview_tracking_link">' . __('Preview:', 'wc_shipment_tracking') . ' <a href="" target="_blank">' . __('Click here to track your shipment', 'wc_shipment_tracking') . '</a></p>';

			$provider_array = array();

			foreach ( $this->providers as $provider ) {
				$provider_array[sanitize_title( $provider )] = urlencode( $format );
			}

			$woocommerce->add_inline_js("
				jQuery('p.custom_tracking_link_field, p.custom_tracking_provider_field').hide();

				jQuery('input#custom_tracking_link, input#tracking_number, #tracking_provider').change(function(){

					var tracking = jQuery('input#tracking_number').val();
					var provider = jQuery('#tracking_provider').val();
					var providers = jQuery.parseJSON( '" . json_encode( $provider_array ) . "' );

					var postcode = jQuery('#_shipping_postcode').val();

					if ( ! postcode )
						postcode = jQuery('#_billing_postcode').val();

					postcode = encodeURIComponent( postcode );

					var link = '';

					if ( providers[ provider ] ) {
						link = providers[provider];
						link = link.replace( '%251%24s', tracking );
						link = link.replace( '%252%24s', postcode );
						link = decodeURIComponent( link );

						jQuery('p.custom_tracking_link_field, p.custom_tracking_provider_field').hide();
					} else {
						jQuery('p.custom_tracking_link_field, p.custom_tracking_provider_field').show();

						link = jQuery('input#custom_tracking_link').val();
					}

					if ( link ) {
						jQuery('p.preview_tracking_link a').attr('href', link);
						jQuery('p.preview_tracking_link').show();
					} else {
						jQuery('p.preview_tracking_link').hide();
					}

				}).change();
			");
		}
		

		/* 
		 * Order Downloads Save
		 *
		 * Function for processing and storing all order downloads.
		 */
		function save_meta_box( $post_id, $post ) {
			if ( isset( $_POST['tracking_number'] ) ) {

				// Download data
				$tracking_provider        = woocommerce_clean( $_POST['tracking_provider'] );
				$tracking_number          = woocommerce_clean( $_POST['tracking_number'] );
				$date_shipped             = woocommerce_clean( strtotime( $_POST['date_shipped'] ) );
				
				// Update order data
				update_post_meta( $post_id, '_tracking_provider', $tracking_provider );
				update_post_meta( $post_id, '_tracking_number', $tracking_number );
				update_post_meta( $post_id, '_date_shipped', $date_shipped );
			}
		}

		/* Display Shipment info in the frontend (order view/tracking page). */
		function display_tracking_info( $order_id ) {

			$tracking_provider = get_post_meta( $order_id, '_tracking_provider', true );
			$tracking_number   = get_post_meta( $order_id, '_tracking_number', true );
			$date_shipped      = get_post_meta( $order_id, '_date_shipped', true );
			$postcode          = get_post_meta( $order_id, '_shipping_postcode', true );

			if ( ! $postcode )
				$postcode		= get_post_meta( $order_id, '_billing_postcode', true );

			if ( ! $tracking_number )
				return;

			if ( $date_shipped )
				$date_shipped = ' ' . sprintf( __( 'on %s', 'Shipment_Tracker' ), date_i18n( __( 'l jS F Y', 'Shipment_Tracker'), $date_shipped ) );

			$tracking_link = '';
			
			foreach ( $this->providers as $provider => $url){
				if ( sanitize_title( $provider ) == $tracking_provider ) {
					$shipment_provider = $provider;
					$tracking_url = $url;
					break;
				}
			}
			
			echo sprintf('<p>Your order was shipped %s by %s and the Tracking number is %s</p>', $date_shipped, $shipment_provider, $tracking_number);
			echo sprintf('<p><a target="_blank" href="%s%s">Click here</a> to track your shipment</p>', $tracking_url, $tracking_number);
			
		}

		/* Display shipment info in customer emails. */
		function email_display( $order ) {
			$this->display_tracking_info( $order->id );
		}
		
	}
	
	/* Register this class globally */
	$GLOBALS['Shipment_Tracker'] = new Shipment_Tracker();
	
	
} else {

	add_action('admin_notices', 'shipment_tracker_for_woocommerce_error_notice');
	function shipment_tracker_for_woocommerce_error_notice(){
		global $current_screen;
		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.__('<strong>Shipment Tracker for WooCommerce</strong> requires <a href="http://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="'.admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce').'" target="_blank">WooCommerce</a> first.').'</p></div>';
		}
	}
	
}
