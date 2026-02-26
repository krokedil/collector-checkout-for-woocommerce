<?php
/**
 * Class for the request to update metadata.
 *
 * @package Collector_Checkout/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Update_Metadata class.
 */
class Walley_Checkout_Request_Update_Metadata extends Walley_Checkout_Request_Put {
	/**
	 * The metadata to update.
	 *
	 * @var array
	 */
	protected $metadata;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update metadata';
		$this->metadata  = $arguments['metadata'] ?? '';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . "/checkouts/{$this->private_id}/metadata";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$body['metadata'] = $this->metadata;
		return apply_filters( 'walley_update_metadata_args', $body, $this->order_id );
	}
}
