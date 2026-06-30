<?php
/**
 * WooCommerce integration module for GoldT WebMCP Bridge Pro.
 *
 * @package GoldtWebMCP
 */

namespace GoldtWebMCP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Module - provides e-commerce tools via MCP.
 *
 * @package GoldtWebMCP
 */
class WooCommerce_Module extends Module_Base {

	/**
	 * Module identifier name.
	 *
	 * @var string
	 */
	protected $module_name = 'woocommerce';

	/**
	 * Constructor - initializes WooCommerce module if WooCommerce is active.
	 *
	 * @param object $manifest Manifest instance.
	 */
	public function __construct( $manifest ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		parent::__construct( $manifest );
	}

	/**
	 * Execute a tool, enforcing premium license requirement.
	 *
	 * @param string $tool_name Tool name to execute.
	 * @param array  $params    Tool parameters.
	 * @return mixed|\WP_Error
	 */
	public function execute_tool( $tool_name, $params = array() ) {
		$freemius = \GoldtWebMCP\Core\goldtwmcp_fs();

		if ( ! $freemius->is_premium() ) {
			return new \WP_Error(
				'premium_feature_required',
				'WooCommerce tools require Pro or Business license',
				array(
					'status'       => 403,
					'upgrade_url'  => $freemius->get_upgrade_url(),
					'current_plan' => $freemius->get_plan(),
				)
			);
		}

		return parent::execute_tool( $tool_name, $params );
	}

	/**
	 * Register all WooCommerce tools with the manifest.
	 */
	protected function register_tools() {
		$this->register_tool(
			'searchProducts',
			array(
				'description'  => 'Search WooCommerce products with filters',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'    => array(
							'type'        => 'string',
							'description' => 'Search query',
						),
						'category'  => array(
							'type'        => 'string',
							'description' => 'Product category slug',
						),
						'tag'       => array(
							'type'        => 'string',
							'description' => 'Product tag slug',
						),
						'min_price' => array(
							'type'        => 'number',
							'description' => 'Minimum price',
						),
						'max_price' => array(
							'type'        => 'number',
							'description' => 'Maximum price',
						),
						'on_sale'   => array(
							'type'        => 'boolean',
							'description' => 'Filter by products on sale',
						),
						'in_stock'  => array(
							'type'        => 'boolean',
							'description' => 'Filter by products in stock',
						),
						'limit'     => array(
							'type'        => 'integer',
							'description' => 'Maximum number of products',
							'default'     => 10,
						),
					),
				),
			)
		);

		$this->register_tool(
			'getProduct',
			array(
				'description'  => 'Get a single WooCommerce product by ID or SKU',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'identifier' ),
					'properties' => array(
						'identifier' => array(
							'type'        => array( 'integer', 'string' ),
							'description' => 'Product ID or SKU',
						),
					),
				),
			)
		);

		$this->register_tool(
			'addToCart',
			array(
				'description'  => 'Add a product to the shopping cart',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'product_id' ),
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'Product ID to add',
						),
						'quantity'     => array(
							'type'        => 'integer',
							'description' => 'Quantity to add',
							'default'     => 1,
						),
						'variation_id' => array(
							'type'        => 'integer',
							'description' => 'Variation ID if applicable',
						),
					),
				),
			)
		);

		$this->register_tool(
			'getCart',
			array(
				'description'  => 'Get current shopping cart contents',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(),
				),
			)
		);

		$this->register_tool(
			'getOrders',
			array(
				'description'  => 'Get customer orders',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'customer_id' => array(
							'type'        => 'integer',
							'description' => 'Customer ID (defaults to current user)',
						),
						'status'      => array(
							'type'        => 'string',
							'description' => 'Order status filter',
						),
						'limit'       => array(
							'type'        => 'integer',
							'description' => 'Maximum number of orders',
							'default'     => 10,
						),
					),
				),
			)
		);
	}

	/**
	 * Execute product search with optional filters.
	 *
	 * @param array $params Search parameters.
	 * @return array|\WP_Error
	 */
	public function execute_searchProducts( $params ) {
		// Validate and sanitize limit parameter.
		$limit = isset( $params['limit'] ) ? \absint( $params['limit'] ) : 10;
		if ( $limit < 1 ) {
			$limit = 10;
		}
		if ( $limit > 100 ) {
			$limit = 100; // Cap at 100 to prevent resource exhaustion.
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
		);

		if ( isset( $params['search'] ) && ! empty( $params['search'] ) ) {
			$args['s'] = \sanitize_text_field( $params['search'] );
		}

		if ( isset( $params['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => \sanitize_text_field( $params['category'] ),
			);
		}

		if ( isset( $params['tag'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'slug',
				'terms'    => \sanitize_text_field( $params['tag'] ),
			);
		}

		if ( isset( $params['on_sale'] ) && $params['on_sale'] ) {
			$args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
		}

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return $this->success_response( array(), 'No products found' );
		}

		$products = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$product = \wc_get_product( \get_the_ID() );

			if ( ! $product ) {
				continue;
			}

			if ( isset( $params['in_stock'] ) && $params['in_stock'] && ! $product->is_in_stock() ) {
				continue;
			}

			if ( isset( $params['min_price'] ) && $product->get_price() < $params['min_price'] ) {
				continue;
			}

			if ( isset( $params['max_price'] ) && $product->get_price() > $params['max_price'] ) {
				continue;
			}

			$products[] = $this->format_product( $product );
		}
		\wp_reset_postdata();

		return $this->success_response( $products, sprintf( 'Found %d products', count( $products ) ) );
	}

	/**
	 * Execute single product retrieval by ID or SKU.
	 *
	 * @param array $params Parameters including 'identifier'.
	 * @return array|\WP_Error
	 */
	public function execute_getProduct( $params ) {
		// Validate required parameter.
		if ( ! isset( $params['identifier'] ) ) {
			return $this->error_response( 'Missing required parameter: identifier', 'missing_parameter' );
		}

		$identifier = $params['identifier'];

		// Reject empty identifiers.
		if ( '' === $identifier || null === $identifier ) {
			return $this->error_response( 'Parameter "identifier" cannot be empty', 'invalid_parameter' );
		}

		if ( is_numeric( $identifier ) ) {
			$product = \wc_get_product( \absint( $identifier ) );
		} else {
			$product_id = \wc_get_product_id_by_sku( \sanitize_text_field( $identifier ) );
			$product    = $product_id ? \wc_get_product( $product_id ) : null;
		}

		if ( ! $product ) {
			return $this->error_response( 'Product not found', 'product_not_found' );
		}

		return $this->success_response( $this->format_product( $product ) );
	}

	/**
	 * Execute add-to-cart operation.
	 *
	 * @param array $params Parameters including 'product_id', 'quantity', 'variation_id'.
	 * @return array|\WP_Error
	 */
	public function execute_addToCart( $params ) {
		if ( ! function_exists( 'WC' ) ) {
			return $this->error_response( 'WooCommerce not available', 'wc_not_available' );
		}

		wc_load_cart();

		// Validate required parameter.
		if ( empty( $params['product_id'] ) ) {
			return $this->error_response( 'Product ID is required', 'missing_product_id' );
		}

		$product_id = \absint( $params['product_id'] );
		if ( 0 === $product_id ) {
			return $this->error_response( 'Invalid product ID', 'invalid_product_id' );
		}

		$quantity = isset( $params['quantity'] ) ? \absint( $params['quantity'] ) : 1;
		if ( 0 === $quantity ) {
			$quantity = 1; // Default to 1 if invalid.
		}
		// Cap quantity to prevent abuse.
		if ( $quantity > 100 ) {
			$quantity = 100;
		}
		$variation_id = isset( $params['variation_id'] ) ? \absint( $params['variation_id'] ) : 0;

		$product = \wc_get_product( $product_id );
		if ( ! $product ) {
			return $this->error_response( 'Product not found', 'product_not_found' );
		}

		if ( ! $product->is_in_stock() ) {
			return $this->error_response( 'Product is out of stock', 'out_of_stock' );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

		if ( ! $cart_item_key ) {
			return $this->error_response( 'Failed to add product to cart', 'add_to_cart_failed' );
		}

		return $this->success_response(
			array(
				'cart_item_key' => $cart_item_key,
				'cart_count'    => WC()->cart->get_cart_contents_count(),
				'cart_total'    => WC()->cart->get_cart_total(),
			),
			'Product added to cart successfully'
		);
	}

	/**
	 * Execute cart retrieval.
	 *
	 * @param array $params Parameters (unused).
	 * @return array|\WP_Error
	 */
	public function execute_getCart( $params ) {
		if ( ! function_exists( 'WC' ) ) {
			return $this->error_response( 'WooCommerce not available', 'wc_not_available' );
		}

		wc_load_cart();

		$cart_items = array();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			$cart_items[] = array(
				'cart_item_key' => $cart_item_key,
				'product_id'    => $cart_item['product_id'],
				'variation_id'  => $cart_item['variation_id'],
				'quantity'      => $cart_item['quantity'],
				'product_name'  => $product->get_name(),
				'product_price' => $product->get_price(),
				'line_total'    => $cart_item['line_total'],
				'line_subtotal' => $cart_item['line_subtotal'],
			);
		}

		return $this->success_response(
			array(
				'items'          => $cart_items,
				'item_count'     => WC()->cart->get_cart_contents_count(),
				'subtotal'       => WC()->cart->get_cart_subtotal(),
				'total'          => WC()->cart->get_cart_total(),
				'needs_shipping' => WC()->cart->needs_shipping(),
			)
		);
	}

	/**
	 * Execute order retrieval for a customer.
	 *
	 * @param array $params Parameters including optional 'customer_id', 'status', 'limit'.
	 * @return array|\WP_Error
	 */
	public function execute_getOrders( $params ) {
		$customer_id = isset( $params['customer_id'] )
			? \absint( $params['customer_id'] )
			: \get_current_user_id();

		if ( 0 === $customer_id ) {
			return $this->error_response( 'No customer ID provided', 'no_customer' );
		}

		// Cap limit to prevent resource exhaustion.
		$limit = isset( $params['limit'] ) ? \absint( $params['limit'] ) : 10;
		if ( 0 === $limit || $limit > 100 ) {
			$limit = ( 0 === $limit ) ? 10 : 100;
		}

		$args = array(
			'customer_id' => $customer_id,
			'limit'       => $limit,
		);

		if ( isset( $params['status'] ) ) {
			$args['status'] = \sanitize_text_field( $params['status'] );
		}

		$orders = \wc_get_orders( $args );

		if ( empty( $orders ) ) {
			return $this->success_response( array(), 'No orders found' );
		}

		$formatted_orders = array();
		foreach ( $orders as $order ) {
			$formatted_orders[] = $this->format_order( $order );
		}

		return $this->success_response( $formatted_orders, sprintf( 'Found %d orders', count( $formatted_orders ) ) );
	}

	/**
	 * Format a WooCommerce product into a standardized array.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return array
	 */
	private function format_product( $product ) {
		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'on_sale'           => $product->is_on_sale(),
			'in_stock'          => $product->is_in_stock(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'permalink'         => $product->get_permalink(),
			'image'             => \wp_get_attachment_image_url( $product->get_image_id(), 'large' ),
			'categories'        => \wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
			'tags'              => \wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) ),
		);
	}

	/**
	 * Format a WooCommerce order into a standardized array.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return array
	 */
	private function format_order( $order ) {
		return array(
			'id'             => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'status'         => $order->get_status(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'date_created'   => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'payment_method' => $order->get_payment_method_title(),
			'items_count'    => $order->get_item_count(),
		);
	}
}
