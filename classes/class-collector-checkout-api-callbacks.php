<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Collector_Api_Callbacks class.
 *
 * Class that handles Collector API callbacks.
 */
class Collector_Api_Callbacks {
	
	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Collector_Api_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'collector_check_for_order', array( $this, 'collector_check_for_order_callback' ), 10, 2 );

	}

	public function collector_check_for_order_callback( $private_id, $public_token ) {
		error_log('collector_check_for_order_callback hit');
	    $query = new WC_Order_Query( array(
	        'limit' => -1,
	        'orderby' => 'date',
	        'order' => 'DESC',
	        'return' => 'ids',
	        'payment_method' => 'collector_checkout',
	        'date_created' => '>' . ( time() - 3600 )
	    ) );
	    $orders = $query->get_orders();
	    
	    foreach( $orders as $order_id ) {
	        $order_private_id = get_post_meta( $order_id, '_collector_private_id', true );
	        if( $order_private_id === $private_id ) {
	            //return false;
	            break;
	        }
	    }
	    // If we get here, no order is found. Create an order.
	    if( $order_id ) {
		    $order = wc_get_order( $order_id );
	        
	        if( $order ) {
		        $order->add_order_note( 'API-callback hit.' );
	        } else {
		        $order = wc_create_order();
		        $order->add_order_note( 'Created via API-callback.' );
	        }
	    }
	    
	}

    public function create_order() {
        $order = wc_create_order();

        return $order;
    }

    
}
Collector_Api_Callbacks::get_instance();