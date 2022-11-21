<?php
/**
 * Plugin Name:     ConcordPay for WooCommerce
 * Plugin URI:      https://concordpay.concord.ua
 * Description:     ConcordPay Payment Gateway for WooCommerce.
 * Version:         1.4.2
 * Author:          ConcordPay
 * Author URI:      https://mustpay.tech
 * Domain Path:     /lang
 * Text Domain:     concordpay-for-woocommerce
 * License:         GPLv3 or later
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least:    6.0.0
 * WC tested up to:         7.1.0
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
add_action( 'plugins_loaded', 'woocommerce_concordpay_init', 0 );
// Variables for translate plugin header.
$plugin_name        = esc_html__( 'ConcordPay for WooCommerce', 'concordpay-for-woocommerce' );
$plugin_description = esc_html__( 'ConcordPay Payment Gateway for WooCommerce.', 'concordpay-for-woocommerce' );
define( 'CONCORDPAY_IMGDIR', WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/assets/img/' );

// Add ConcordPay menu item to main menu.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-concordpay-menu.php';
new ConcordpayMenu();

/**
 * Init ConcordPay
 */
function woocommerce_concordpay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	if ( isset( $_GET['msg'] ) && ! empty( $_GET['msg'] ) ) {
		add_action( 'the_content', 'show_concordpay_message' );
	}

	/**
	 * Show ConcordPay message.
	 *
	 * @param string $content ConcordPay message.
	 *
	 * @return string
	 */
	function show_concordpay_message( $content ) {
		return '<div class="' . htmlentities( $_GET['type'] ) . '">' . htmlentities( urldecode( $_GET['msg'] ) ) . '</div>' . $content;
	}

	load_plugin_textdomain( 'concordpay-for-woocommerce', false, basename( __DIR__ ) . '/lang' );

	/**
	 * ConcordPay Payment Gateway class
	 *
	 * @property string $lang
	 * @property string $approve_url
	 * @property string $decline_url
	 * @property string $cancel_url
	 * @property string $callback_url
	 * @property string $title
	 * @property string $description
	 * @property array  $add_params
	 * @property string $merchant_id
	 * @property string $secret_key
	 * @property string $concordpay_widget
	 * @property string $default_order_status
	 * @property string $declined_order_status
	 * @property string $refunded_order_status
	 */
	class WC_Concordpay extends WC_Payment_Gateway {

		/**
		 * ConcordPay message
		 *
		 * @var array
		 */
		public $msg = array();

		/**
		 * Default order status after payment
		 *
		 * @var false|string
		 */
		public $default_order_status;

		/**
		 * Order status after declined payment
		 *
		 * @var false|string
		 */
		public $declined_order_status;

		/**
		 * Order status after refunded payment
		 *
		 * @var false|string
		 */
		public $refunded_order_status;

		/**
		 * ConcordPay method title in List of allowed payment methods
		 *
		 * @var string|void
		 */
		public $title;

		/**
		 * ConcordPay method description in List of allowed payment methods
		 *
		 * @var string
		 */
		public $description;

		/**
		 * Payment completed order status.
		 *
		 * @var mixed
		 */
		protected $approve_url;

		/**
		 * Payment declined order status.
		 *
		 * @var mixed
		 */
		protected $decline_url;

		/**
		 * Payment canceled order status
		 *
		 * @var mixed
		 */
		protected $cancel_url;

		/**
		 * URL of the result information
		 *
		 * @var mixed
		 */
		protected $callback_url;

		/**
		 * ConcordPay image on checkout/thankyou page
		 *
		 * @var string
		 */
		protected $img;

		/**
		 * ConcordPay API URL
		 *
		 * @var string
		 */
		protected $url = 'https://pay.concord.ua/api/';

		const CONCORDPAY_SITE_URL = 'https://concordpay.concord.ua';

		const SIGNATURE_SEPARATOR         = ';';
		const ORDER_NEW                   = 'New';
		const ORDER_DECLINED              = 'Declined';
		const ORDER_REFUND_IN_PROCESSING  = 'RefundInProcessing';
		const ORDER_REFUNDED              = 'Refunded';
		const ORDER_EXPIRED               = 'Expired';
		const ORDER_PENDING               = 'Pending';
		const ORDER_APPROVED              = 'Approved';
		const ORDER_WAITING_AUTH_COMPLETE = 'WaitingAuthComplete';
		const ORDER_IN_PROCESSING         = 'InProcessing';
		const ORDER_SEPARATOR             = '#';
		const ORDER_SUFFIX                = '_woopay_';
		const PHONE_LENGTH_MIN            = 10;
		const PHONE_LENGTH_MAX            = 11;
		const ALLOWED_CURRENCIES          = array( 'UAH' );
		const RESPONSE_TYPE_REVERSE       = 'reverse';

		/**
		 * Array keys for generate response signature.
		 *
		 * @var string[]
		 */
		protected $keys_for_response_signature = array(
			'merchantAccount',
			'orderReference',
			'amount',
			'currency',
		);

		/**
		 * Array keys for generate request signature.
		 *
		 * @var string[]
		 */
		protected $keys_for_request_signature = array(
			'merchant_id',
			'order_id',
			'amount',
			'currency_iso',
			'description',
		);

		/**
		 * WC_concordpay constructor.
		 */
		public function __construct() {
			if ( self::check_environment() ) {
				return;
			}

			$this->id                 = 'concordpay';
			$this->method_title       = 'ConcordPay';
			$this->method_description = __( 'A payment service that allows you to accept payments with Visa/MasterCard payment cards and GooglePay/ApplePay mobile wallets', 'concordpay-for-woocommerce' );
			$this->has_fields         = false;

			$this->title       = $this->get_gateway_title();
			$this->description = ''; // Description moved to title.

			$this->init_form_fields();
			$this->init_settings();

			if ( 'yes' === $this->settings['showlogo'] ) {
				$this->icon = CONCORDPAY_IMGDIR . 'concordpay-2.svg';
			}

			$this->img  = CONCORDPAY_IMGDIR . 'concordpay-2.svg';
			$this->lang = $this->settings['language'];

			$this->approve_url  = $this->settings['approve_url'];
			$this->decline_url  = $this->settings['decline_url'];
			$this->cancel_url   = $this->settings['cancel_url'];
			$this->callback_url = $this->settings['callback_url'];

			$this->merchant_id = $this->settings['merchant_id'];
			$this->secret_key  = $this->settings['secret_key'];

			$this->default_order_status  = $this->get_option( 'default_order_status' ) ?
				$this->get_option( 'default_order_status' ) :
				false;
			$this->declined_order_status = $this->get_option( 'declined_order_status' ) ?
				$this->get_option( 'declined_order_status' ) :
				false;
			$this->refunded_order_status = $this->get_option( 'refunded_order_status' ) ?
				$this->get_option( 'refunded_order_status' ) :
				false;

			$this->concordpay_widget = $this->settings['concordpay_widget'];

			$this->msg['message'] = '';
			$this->msg['class']   = '';

			if ( version_compare( self::get_wc_version(), '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_concordpay_response' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'init', array( &$this, 'check_concordpay_response' ) );
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			if ( is_checkout_pay_page() && $this->get_current_order()->get_payment_method() === $this->id ) {
				add_action( 'wp_head', array( &$this, 'is_allowed_currency' ) );
				add_action( 'wp_enqueue_scripts', array( &$this, 'link_concordpay_scripts' ) );
				if ( $this->is_widget_enabled() ) {
					add_action( 'wp_footer', array( &$this, 'generate_widget' ) );
				}
			}

			add_action( 'woocommerce_receipt_concordpay', array( &$this, 'receipt_page' ) );

			// Add ConcordPay settings link on Plugins page.
			$plugin_file = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_$plugin_file", array( &$this, 'concordpay_plugin_settings_link' ) );

			// Support plugin link in plugin list.
			add_filter( "plugin_action_links_{$plugin_file}", array( &$this, 'concordpay_plugin_support_link' ) );
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		public static function check_environment() {
			if ( PHP_VERSION_ID < 50400 ) {
				/* translators: 1: Required PHP version, 2: Running PHP version. */
				$message = sprintf( __( 'The minimum PHP version required for ConcordPay is %1$s. You are running %2$s.', 'concordpay-for-woocommerce' ), '5.4.0', PHP_VERSION );

				return esc_html( $message );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return esc_html__( 'WooCommerce needs to be activated.', 'concordpay-for-woocommerce' );
			}

			if ( version_compare( self::get_wc_version(), '3.0.0', '<' ) ) {
				/* translators: 1: Required WooCommerce version, 2: Running WooCommerce version. */
				$message = sprintf( __( 'The minimum WooCommerce version required for ConcordPay is %1$s. You are running %2$s.', 'concordpay-for-woocommerce' ), '3.0.0', self::get_wc_version() );

				return esc_html( $message );
			}

			return false;
		}

		/**
		 * Admin dashboard settings for ConcordPay Gateway
		 */
		public function init_form_fields() {
			// Fields for settings.
			$this->form_fields = array(
				'enabled'               => array(
					'title'       => __( 'Enable/Disable', 'concordpay-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable ConcordPay Payment Module', 'concordpay-for-woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Show in the Payment List as a payment option', 'concordpay-for-woocommerce' ),
				),
				'concordpay_widget'     => array(
					'title'       => __( 'ConcordPay Widget', 'concordpay-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable ConcordPay Widget', 'concordpay-for-woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Use a ConcordPay Widget instead separate checkout page', 'concordpay-for-woocommerce' ),
				),
				'showlogo'              => array(
					'title'       => __( 'Show Logo', 'concordpay-for-woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Show the ConcordPay logo in the Payment Method section for the user', 'concordpay-for-woocommerce' ),
					'default'     => 'yes',
					'description' => __( 'Tick to show ConcordPay logo', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'title'                 => array(
					'title'       => __( 'Title in the Payment List', 'concordpay-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Title in the list of available payment methods', 'concordpay-for-woocommerce' ),
					'default'     => $this->get_gateway_title(),
					'desc_tip'    => true,
				),
				'merchant_id'           => array(
					'title'       => __( 'Merchant Account', 'concordpay-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Given to Merchant by ConcordPay', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'secret_key'            => array(
					'title'       => __( 'Secret key', 'concordpay-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Given to Merchant by ConcordPay', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'default_order_status'  => array(
					'title'       => __( 'Payment completed order status', 'concordpay-for-woocommerce' ),
					'type'        => 'select',
					'options'     => $this->get_payment_order_statuses(),
					'default'     => 'none',
					'description' => __( 'The default order status after successful payment.', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'declined_order_status' => array(
					'title'       => __( 'Payment declined order status', 'concordpay-for-woocommerce' ),
					'type'        => 'select',
					'options'     => $this->get_payment_order_statuses(),
					'default'     => 'none',
					'description' => __( 'Order status when payment was declined.', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'refunded_order_status' => array(
					'title'       => __( 'Payment refunded order status', 'concordpay-for-woocommerce' ),
					'type'        => 'select',
					'options'     => $this->get_payment_order_statuses(),
					'default'     => 'none',
					'description' => __( 'Order status when payment was refunded.', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'concordpay_url'        => array(
					'title'       => __( 'System url', 'concordpay-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Default url - https://pay.concord.ua/api/', 'concordpay-for-woocommerce' ),
					'default'     => 'https://pay.concord.ua/api/',
					'desc_tip'    => true,
				),
				'approve_url'           => array(
					'title'       => __( 'Successful payment redirect URL', 'concordpay-for-woocommerce' ),
					'options'     => $this->get_all_pages( __( 'Standard Page', 'concordpay-for-woocommerce' ) ),
					'default'     => '0',
					'type'        => 'select',
					'description' => __( 'Successful payment redirect URL', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'cancel_url'            => array(
					'title'       => __( 'Redirect URL in case of failure to make payment', 'concordpay-for-woocommerce' ),
					'options'     => $this->get_all_pages( __( 'Standard Page', 'concordpay-for-woocommerce' ) ),
					'default'     => '0',
					'type'        => 'select',
					'description' => __( 'Redirect URL in case of failure to make payment', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'decline_url'           => array(
					'title'       => __( 'Redirect URL failed to pay', 'concordpay-for-woocommerce' ),
					'options'     => $this->get_all_pages( __( 'Standard Page', 'concordpay-for-woocommerce' ) ),
					'default'     => '0',
					'type'        => 'select',
					'description' => __( 'Redirect URL failed to pay', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'callback_url'          => array(
					'title'       => __( 'URL of the result information', 'concordpay-for-woocommerce' ),
					'options'     => $this->get_all_pages( __( 'Standard Page', 'concordpay-for-woocommerce' ) ),
					'default'     => '0',
					'type'        => 'select',
					'description' => __( 'The URL to which will receive information about the result of the payment', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
				'language'        => array(
					'title'       => __( 'Payment page language', 'concordpay-for-woocommerce' ),
					'options'     => self::get_languages(),
					'default'     => 'ua',
					'type'        => 'select',
					'description' => __( 'ConcordPay payment page language', 'concordpay-for-woocommerce' ),
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Admin Panel Options
		 */
		public function admin_options() {
			echo '<h3>' . esc_html__( 'Payment gateway', 'concordpay-for-woocommerce' );
			wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
			echo '</h3>';
			echo '<a href="' . esc_html( self::CONCORDPAY_SITE_URL ) . '"><img src="' . esc_attr( CONCORDPAY_IMGDIR . 'concordpay.svg' ) . '" alt="ConcordPay"></a>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
		 * Add ConcordPay settings link on Plugins page.
		 *
		 * @param array $links Links under the name of the plugin.
		 *
		 * @return array
		 */
		public function concordpay_plugin_settings_link( $links ) {
			$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=concordpay' ) . '">' . __( 'Settings', 'concordpay-for-woocommerce' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;
		}

		/**
		 * Adds Concordpay support link in plugin list.
		 *
		 * @param array $links Plugin links in menu list.
		 *
		 * @return array
		 */
		public function concordpay_plugin_support_link( $links ): array {
			unset( $links['edit'] );

			$links[] = '<a target="_blank" href="https://t.me/ConcordPaySupport">' . __( 'Support', 'concordpay-for-woocommerce' ) . '</a>';

			return $links;
		}

		/**
		 * Receipt Page
		 *
		 * @param integer $order_id Order ID.
		 */
		public function receipt_page( int $order_id ) {
			global $woocommerce;
			$order = $this->get_order( $order_id );

			if ( ! $this->is_widget_enabled() ) {
				echo '<p>' . esc_html__( 'Thank you for your order, you will now be redirected to the ConcordPay payment page', 'concordpay-for-woocommerce' ) . ' ' . "<img style='display:inline-block; max-width:70px' src=" . esc_attr( $this->img ) . " alt='ConcordPay'>" . '</p>';
				echo $this->generate_concordpay_form( $order );
			}

			$woocommerce->cart->empty_cart();
		}

		/**
		 * Checking is payment valid.
		 *
		 * @param array $response Response data.
		 * @return bool|string
		 */
		public function is_payment_valid( $response ) {
			global $woocommerce;

			list( $order_id, ) = explode( self::ORDER_SUFFIX, $response['orderReference'] );
			$order             = new WC_Order( $order_id );
			if ( false === $order ) {
				return __( 'An error has occurred during payment. Please contact us to ensure your order has submitted.', 'concordpay-for-woocommerce' );
			}

			if ( $this->merchant_id !== $response['merchantAccount'] ) {
				return __( 'An error has occurred during payment. Merchant data is incorrect.', 'concordpay-for-woocommerce' );
			}

			$response_signature = $response['merchantSignature'];

			if ( $this->get_response_signature( $response ) !== $response_signature ) {
				die( esc_html__( 'An error has occurred during payment. Signature is not valid.', 'concordpay-for-woocommerce' ) );
			}

			if ( self::ORDER_DECLINED === $response['transactionStatus'] ) {
				if ( $this->declined_order_status && 'default' !== $this->declined_order_status ) {
					$order->update_status( $this->declined_order_status );
				} else {
					$order->update_status( 'failed' );
				}
			}

			if ( self::ORDER_APPROVED === $response['transactionStatus'] ) {
				if ( isset( $response['type'] ) && self::RESPONSE_TYPE_REVERSE === $response['type'] ) {
					// Refunded payment callback.
					if ( $this->refunded_order_status && 'default' !== $this->refunded_order_status ) {
						$order->update_status( $this->refunded_order_status );
					} else {
						$order->update_status( 'refunded' );
					}
					$order->payment_complete();
					$order->add_order_note( 'ConcordPay refund status: ' . $response['transactionStatus'] . ', Refund payment id: ' . $response['orderReference'] );
				} else {
					// Purchase callback.
					if ( $this->default_order_status && 'default' !== $this->default_order_status ) {
						$order->update_status( $this->default_order_status );
					} else {
						$order->update_status( 'completed' );
					}
					$order->payment_complete();
					$order->add_order_note( 'ConcordPay : orderReference:' . $response['transactionStatus'] . " \n\n recToken: " . $response['recToken'] );
				}

				return true;
			}

			$woocommerce->cart->empty_cart();
			return false;
		}

		/**
		 * Check ConcordPay response
		 */
		public function check_concordpay_response() {
			$data         = json_decode( file_get_contents( 'php://input' ), true );
			$payment_info = $this->is_payment_valid( $data );
			if ( true === $payment_info ) {
				echo $this->get_answer_to_gateway( $data );

				$this->msg['message'] = __( 'Thank you for shopping with us. Your account has been charged and your transaction is successful.', 'concordpay-for-woocommerce' );
				$this->msg['class']   = 'woocommerce-message';
			}
			exit;
		}

		/**
		 * Get answer to Gateway.
		 *
		 * @param array $data Response data.
		 * @return mixed|string|void
		 */
		public function get_answer_to_gateway( $data ) {
			$time = time();

			$response_to_gateway = array(
				'orderReference' => $data['orderReference'],
				'status'         => 'accept',
				'time'           => $time,
			);

			$sign = array();
			foreach ( $response_to_gateway as $data_key => $data_value ) {
				$sign [] = $data_value;
			}
			$sign = implode( ';', $sign );
			$sign = hash_hmac( 'md5', $sign, $this->secret_key );

			$response_to_gateway['signature'] = $sign;

			return wp_json_encode( $response_to_gateway );
		}

		/**
		 * Generate signature for operation.
		 *
		 * @param array $option Request or response data.
		 * @param array $keys Keys for signature.
		 * @return string
		 */
		public function get_signature( $option, $keys ) {
			$hash = array();
			foreach ( $keys as $data_key ) {
				if ( ! isset( $option[ $data_key ] ) ) {
					continue;
				}
				if ( is_array( $option[ $data_key ] ) ) {
					foreach ( $option[ $data_key ] as $v ) {
						$hash[] = $v;
					}
				} else {
					$hash [] = $option[ $data_key ];
				}
			}
			$hash = implode( ';', $hash );

			return hash_hmac( 'md5', $hash, $this->secret_key );
		}

		/**
		 * Generate request signature.
		 *
		 * @param array $options Request data.
		 * @return string
		 */
		public function get_request_signature( $options ) {
			return $this->get_signature( $options, $this->keys_for_request_signature );
		}

		/**
		 * Generate response signature.
		 *
		 * @param array $options Response data.
		 * @return string
		 */
		public function get_response_signature( $options ) {
			return $this->get_signature( $options, $this->keys_for_response_signature );
		}

		/**
		 * Generate ConcordPay payment form.
		 *
		 * @param WC_Order $order Order object.
		 * @return string
		 */
		protected function generate_concordpay_form( $order ) {
			$concordpay_args = $this->get_concordpay_args( $order );

			return $this->generate_form( $concordpay_args );
		}

		/**
		 * Generate ConcordPay payment form with hidden fields.
		 *
		 * @param array $data Order data, prepared for payment.
		 * @return string
		 */
		protected function generate_form( $data ) {
			$form = PHP_EOL . "<form method='post' id='form_concordpay' action=$this->url accept-charset=utf-8>" . PHP_EOL;
			foreach ( $data as $k => $v ) {
				$form .= $this->print_input( $k, $v );
			}
			$form .= "<input type='submit' style='display:none;'/>" . PHP_EOL;
			$form .= '</form>' . PHP_EOL;
			$form .= "<script type='text/javascript'>window.addEventListener('DOMContentLoaded', function () { document.querySelector('#form_concordpay').submit(); }) </script>";

			return $form;
		}

		/**
		 * Prints inputs in form.
		 *
		 * @param string       $name Attribute name.
		 * @param array|string $val Attribute value.
		 * @return string
		 */
		protected function print_input( $name, $val ) {
			$str = '';
			if ( ! is_array( $val ) ) {
				return "<input type='hidden' name='" . $name . "' value='" . htmlspecialchars( $val ) . "'><br />" . PHP_EOL;
			}
			foreach ( $val as $v ) {
				$str .= $this->print_input( $name . '[]', $v );
			}
			return $str;
		}

		/**
		 *  Description of the payment method in the list of available payment methods
		 **/
		public function payment_fields() {
			if ( $this->description ) {
				echo esc_html( wpautop( wptexturize( $this->description ) ) );
			}
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param integer $order_id Order ID.
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );

			if ( version_compare( self::get_wc_version(), '2.1.0', '>=' ) ) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink( get_option( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result'   => 'success',
				'redirect' => add_query_arg( 'order', $order->get_id(), add_query_arg( 'key', $order->get_order_key(), $checkout_payment_url ) ),
			);
		}

		/**
		 * Generate Callback URL
		 *
		 * @return bool|string
		 */
		private function get_callback_url() {
			$redirect_url = ( '' === $this->callback_url || 0 === $this->callback_url ) ?
				get_site_url() . '/' :
				get_permalink( $this->callback_url );

			return add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
		}

		/**
		 * Gets the URL where the customer is redirected after payment.
		 *
		 * @param WC_Order $order Order object.
		 * @param string   $url Redirect URL from admin settings.
		 * @example {YOUR_SITE}/checkout/order-received/59/?key=wc_order_qwertyuiop
		 *
		 * @return mixed
		 */
		public function get_redirect_url( $order, $url ) {
			return ( '' === $url || '0' === $url )
				? $order->get_checkout_order_received_url()
				: get_permalink( $url );
		}

		/**
		 * Get all pages.
		 *
		 * @param bool $title By page title.
		 * @param bool $indent By page indent.
		 *
		 * @return array
		 */
		protected function get_all_pages( $title = false, $indent = true ) {
			$wp_pages  = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) {
				$page_list[] = $title;
			}
			foreach ( $wp_pages as $page ) {
				$prefix = '';

				if ( $indent ) {
					$has_parent = $page->post_parent;
					while ( $has_parent ) {
						$prefix    .= ' - ';
						$next_page  = get_post( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}

				$page_list[ $page->ID ] = $prefix . $page->post_title;
			}

			return $page_list;
		}

		/**
		 * Get statuses labels.
		 *
		 * @return array
		 */
		public function get_order_status_labels() {
			$order_statuses = array();

			foreach ( wc_get_order_statuses() as $key => $label ) {
				$new_key = str_replace( 'wc-', '', $key );

				$order_statuses[ $new_key ] = $label;
			}

			return $order_statuses;
		}

		/**
		 * Get ConcordPay title in allowed payment methods list
		 *
		 * @return string|void
		 */
		private function get_gateway_title() {
			$title = trim( htmlspecialchars( $this->get_option( 'title' ) ) );

			return $title ? $title : esc_html__( 'ConcordPay - Payment Visa, Mastercard, GooglePay, ApplePay', 'concordpay-for-woocommerce' );
		}

		/**
		 * Get ConcordPay description in allowed payment methods list
		 *
		 * @return string|void
		 */
		private function get_gateway_description() {
			$description = esc_html__( 'Payment Visa, Mastercard, GooglePay, ApplePay', 'concordpay-for-woocommerce' );

			return $description ?? esc_html( 'Оплата Visa, Mastercard, GooglePay, ApplePay' );
		}

		/**
		 * Get current locale.
		 *
		 * @return false|string
		 */
		private function get_language() {
			return substr( get_bloginfo( 'language' ), 0, 2 );
		}

		/**
		 * Enabled Widget flag
		 *
		 * @return bool
		 */
		private function is_widget_enabled() {
			return mb_strtolower( $this->concordpay_widget ) === 'yes';
		}

		/**
		 * Get order by ID.
		 *
		 * @param integer $order_id Order ID.
		 *
		 * @return string|WC_Order
		 */
		protected function get_order( int $order_id ) {
			$order = new WC_Order( $order_id );
			if ( false === (bool) $order ) {
				return __( 'An error has occurred during payment. Please contact us to ensure your order has submitted.', 'concordpay-for-woocommerce' );
			}

			return $order;
		}

		/**
		 * Generate ConcordPay Payment Widget.
		 */
		public function generate_widget() {
			$order = $this->get_current_order();

			$concordpay_args = $this->get_concordpay_args( $order );
			$widget          = '
            <script id="widget-wcp-script" type="text/javascript"
                        src="' . plugin_dir_url( __FILE__ ) . 'assets/js/pay-widget.js">
            </script>
            <script type="text/javascript">
                const assets_directory_uri = "' . plugin_dir_url( __FILE__ ) . '";
            </script>
            <script type="text/javascript">
            const concordpay = new Concordpay();
            function pay() {
                concordpay.run({
                    "operation": "' . $concordpay_args['operation'] . '", 
                    "merchant_id": "' . $concordpay_args['merchant_id'] . '",
                    "amount": "' . $concordpay_args['amount'] . '",
                    "signature": "' . $concordpay_args['signature'] . '",
                    "order_id": "' . $concordpay_args['order_id'] . '",
                    "currency_iso": "' . $concordpay_args['currency_iso'] . '",
                    "description": "' . $concordpay_args['description'] . '",
                    "add_params": [],
                    "approve_url": "' . $concordpay_args['approve_url'] . '",
                    "decline_url" : "' . $concordpay_args['decline_url'] . '",
                    "cancel_url": "' . $concordpay_args['cancel_url'] . '",
                    "callback_url": "' . $concordpay_args['callback_url'] . '",
                    "client_last_name": "' . $concordpay_args['client_last_name'] . '",
                    "client_first_name": "' . $concordpay_args['client_first_name'] . '",
                    "email": "' . $concordpay_args['email'] . '",
                    "phone": "' . $concordpay_args['phone'] . '"
                    }
                );
            }
            
            </script>';

			echo $widget;
		}

		/**
		 * Get ConcordPay order arguments.
		 *
		 * @param WC_Order $order Order object.
		 *
		 * @return array
		 */
		protected function get_concordpay_args( $order ) {
			$currency = str_replace(
				array( 'ГРН', 'UAH' ),
				array( 'UAH', 'UAH' ),
				get_woocommerce_currency()
			);

			$phone = $order->get_billing_phone();
			$phone = str_replace( array( '+', ' ', '(', ')' ), array( '', '', '', '' ), $phone );
			if ( strlen( $phone ) === self::PHONE_LENGTH_MIN ) {
				$phone = '38' . $phone;
			} elseif ( strlen( $phone ) === self::PHONE_LENGTH_MAX ) {
				$phone = '3' . $phone;
			}

			// Statistics.
			$client_last_name  = $order->get_billing_last_name() ?? '';
			$client_first_name = $order->get_billing_first_name() ?? '';
			$phone             = $phone ?? '';
			$email             = $order->get_billing_email() ?? '';

			$description = __( 'Payment by card on the site', 'concordpay-for-woocommerce' ) .
						   ' ' . get_site_url() . ', ' . $order->get_billing_first_name() . ' ' .
						   $order->get_billing_last_name() . ', ' . $phone;

			$concordpay_args = array(
				'operation'         => 'Purchase',
				'merchant_id'       => $this->merchant_id,
				'order_id'          => $order->get_id() . self::ORDER_SUFFIX . time(),
				'amount'            => $order->get_total(),
				'currency_iso'      => $currency,
				'description'       => $description,
				'approve_url'       => $this->get_redirect_url( $order, $this->approve_url ),
				'decline_url'       => $this->get_redirect_url( $order, $this->decline_url ),
				'cancel_url'        => $this->get_redirect_url( $order, $this->cancel_url ),
				'callback_url'      => $this->get_callback_url(),
				'language'          => $this->lang,
				// Statistics.
				'client_last_name'  => $client_last_name,
				'client_first_name' => $client_first_name,
				'email'             => $email,
				'phone'             => $phone,
			);

			$concordpay_args['signature'] = $this->get_request_signature( $concordpay_args );

			return $concordpay_args;
		}

		/**
		 * Links ConcordPay scripts to checkout page.
		 */
		public function link_concordpay_scripts() {
			wp_enqueue_script( 'wcp-concordpay', plugin_dir_url( __FILE__ ) . 'assets/js/concordpay.js', null, null, true );
		}

		/**
		 * Check for allowed currency.
		 */
		public function is_allowed_currency() {
			$order = $this->get_current_order();
			if ( in_array( $order->get_currency(), self::ALLOWED_CURRENCIES, true ) ) {
				echo '<script> const isAllowedCurrency = true; </script>';
			} else {
				echo '<script> const isAllowedCurrency = false; </script>';
			}
		}

		/**
		 * Get current order.
		 *
		 * @return WC_Order
		 */
		protected function get_current_order() {
			global $wp;
			$order_id = $wp->query_vars['order-pay'];

			return new WC_Order( $order_id );
		}

		/**
		 * Getting all available woocommerce order statuses.
		 *
		 * @return array
		 */
		private function get_payment_order_statuses() {
			$order_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();

			$statuses = array(
				'default' => __( 'Default status', 'concordpay-for-woocommerce' ),
			);
			if ( $order_statuses ) {
				foreach ( $order_statuses as $k => $v ) {
					$statuses[ str_replace( 'wc-', '', $k ) ] = $v;
				}
			}

			return $statuses;
		}

		/**
		 * List of allowed payment page languages.
		 *
		 * @return array
		 */
		protected static function get_languages() {
			return array(
				'ua' => __( 'UA', 'concordpay-for-woocommerce' ),
				'ru' => __( 'RU', 'concordpay-for-woocommerce' ),
				'en' => __( 'EN', 'concordpay-for-woocommerce' ),
			);
		}

		/**
		 * Get installed WooCommerce version.
		 *
		 * @return mixed
		 */
		protected static function get_wc_version() {
			if ( ! defined( 'WC_VERSION' ) ) {
				exit;
			}

			return WC_VERSION;
		}
	}

	/**
	 * Add the Gateway to WooCommerce.
	 *
	 * @param array $methods Payment methods.
	 * @return mixed
	 */
	function woocommerce_add_concordpay_gateway( $methods ) {
		$methods[] = 'WC_Concordpay';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_concordpay_gateway' );
}
