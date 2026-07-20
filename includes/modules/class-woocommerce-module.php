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

		// ── Additional read tools (v1.1.0) ────────────────────────────────
		$this->register_tool(
			'getOrder',
			array(
				'description'  => 'Get a single order by ID with line items and totals',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'order_id' ),
					'properties' => array(
						'order_id' => array( 'type' => 'integer' ),
					),
				),
			)
		);

		$this->register_tool(
			'listOrders',
			array(
				'description'  => 'List orders across the shop with filters (admin scope)',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'description' => 'Order status (any/pending/processing/completed/…)' ),
						'after'    => array( 'type' => 'string', 'description' => 'ISO date — orders created after' ),
						'before'   => array( 'type' => 'string', 'description' => 'ISO date — orders created before' ),
						'limit'    => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
			)
		);

		$this->register_tool(
			'getCustomer',
			array(
				'description'  => 'Get customer profile + order stats',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'customer_id' ),
					'properties' => array(
						'customer_id' => array( 'type' => 'integer' ),
					),
				),
			)
		);

		$this->register_tool(
			'listCustomers',
			array(
				'description'  => 'List customers with basic profile info',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array( 'type' => 'string', 'description' => 'Search by email/name' ),
						'limit'  => array( 'type' => 'integer', 'default' => 20 ),
					),
				),
			)
		);

		$this->register_tool(
			'listProductCategories',
			array(
				'description'  => 'List all product categories with counts',
				'input_schema' => array( 'type' => 'object', 'properties' => (object) array() ),
			)
		);

		$this->register_tool(
			'getSalesReport',
			array(
				'description'  => 'Sales totals + counts for a date range',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'from' => array( 'type' => 'string', 'description' => 'ISO date (default: 30 days ago)' ),
						'to'   => array( 'type' => 'string', 'description' => 'ISO date (default: today)' ),
					),
				),
			)
		);

		$this->register_tool(
			'getTopSellers',
			array(
				'description'  => 'Top-selling products by units sold in a period',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'from'  => array( 'type' => 'string', 'description' => 'ISO date (default: 30 days ago)' ),
						'to'    => array( 'type' => 'string', 'description' => 'ISO date (default: today)' ),
						'limit' => array( 'type' => 'integer', 'default' => 10 ),
					),
				),
			)
		);

		// ── Write tools (v1.1.0) ──────────────────────────────────────────
		$this->register_tool(
			'createProduct',
			array(
				'description'  => 'Create a new WooCommerce product',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'          => array( 'type' => 'string' ),
						'regular_price' => array( 'type' => 'string' ),
						'sale_price'    => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'short_description' => array( 'type' => 'string' ),
						'sku'           => array( 'type' => 'string' ),
						'stock_quantity' => array( 'type' => 'integer' ),
						'categories'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
						'status'        => array( 'type' => 'string', 'description' => 'publish/draft/pending', 'default' => 'publish' ),
					),
				),
			)
		);

		$this->register_tool(
			'updateProduct',
			array(
				'description'  => 'Update an existing product\'s fields',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'product_id' ),
					'properties' => array(
						'product_id'    => array( 'type' => 'integer' ),
						'name'          => array( 'type' => 'string' ),
						'regular_price' => array( 'type' => 'string' ),
						'sale_price'    => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string' ),
					),
				),
			)
		);

		$this->register_tool(
			'updateInventory',
			array(
				'description'  => 'Update stock quantity + stock-status for a product',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'product_id', 'stock_quantity' ),
					'properties' => array(
						'product_id'     => array( 'type' => 'integer' ),
						'stock_quantity' => array( 'type' => 'integer' ),
						'manage_stock'   => array( 'type' => 'boolean', 'default' => true ),
					),
				),
			)
		);

		$this->register_tool(
			'createOrder',
			array(
				'description'  => 'Create a new order with line items',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'line_items' ),
					'properties' => array(
						'customer_id' => array( 'type' => 'integer' ),
						'status'      => array( 'type' => 'string', 'default' => 'pending' ),
						'line_items'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'product_id', 'quantity' ),
								'properties' => array(
									'product_id' => array( 'type' => 'integer' ),
									'quantity'   => array( 'type' => 'integer' ),
								),
							),
						),
						'billing'     => array( 'type' => 'object', 'description' => 'Billing address fields' ),
					),
				),
			)
		);

		$this->register_tool(
			'updateOrderStatus',
			array(
				'description'  => 'Change an order\'s status (e.g. pending → processing → completed)',
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'order_id', 'status' ),
					'properties' => array(
						'order_id' => array( 'type' => 'integer' ),
						'status'   => array( 'type' => 'string', 'description' => 'pending/processing/on-hold/completed/cancelled/refunded/failed' ),
						'note'     => array( 'type' => 'string', 'description' => 'Optional internal note attached to the status change' ),
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

	/* ────────────────────────────────────────────────────────────────────
	 * Additional read executors (v1.1.0)
	 * ────────────────────────────────────────────────────────────────────*/

	/**
	 * Return a full order with line items + totals + billing.
	 */
	public function execute_getOrder( $params ) {
		$id    = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'       => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			);
		}
		return array(
			'id'             => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'status'         => $order->get_status(),
			'currency'       => $order->get_currency(),
			'total'          => $order->get_total(),
			'subtotal'       => $order->get_subtotal(),
			'shipping_total' => $order->get_shipping_total(),
			'tax_total'      => $order->get_total_tax(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'customer_id'    => $order->get_customer_id(),
			'billing'        => $order->get_address( 'billing' ),
			'shipping'       => $order->get_address( 'shipping' ),
			'line_items'     => $items,
		);
	}

	/**
	 * Admin-scope order list across all customers with filters.
	 */
	public function execute_listOrders( $params ) {
		$args = array(
			'limit' => isset( $params['limit'] ) ? min( 100, \absint( $params['limit'] ) ) : 20,
		);
		if ( ! empty( $params['status'] ) ) {
			$args['status'] = \sanitize_text_field( $params['status'] );
		}
		if ( ! empty( $params['after'] ) ) {
			$args['date_created'] = '>=' . \sanitize_text_field( $params['after'] );
		}
		if ( ! empty( $params['before'] ) ) {
			$args['date_created'] = ( isset( $args['date_created'] ) ? $args['date_created'] . ',' : '' )
				. '<=' . \sanitize_text_field( $params['before'] );
		}
		$orders = \wc_get_orders( $args );
		return array(
			'orders' => array_map( array( $this, 'format_order' ), $orders ),
			'count'  => count( $orders ),
		);
	}

	/**
	 * Customer profile with lifetime spend + order count.
	 */
	public function execute_getCustomer( $params ) {
		$id       = isset( $params['customer_id'] ) ? \absint( $params['customer_id'] ) : 0;
		$customer = $id ? new \WC_Customer( $id ) : null;
		if ( ! $customer || 0 === $customer->get_id() ) {
			return new \WP_Error( 'customer_not_found', 'Customer not found', array( 'status' => 404 ) );
		}
		return array(
			'id'                    => $customer->get_id(),
			'email'                 => $customer->get_email(),
			'first_name'            => $customer->get_first_name(),
			'last_name'             => $customer->get_last_name(),
			'username'              => $customer->get_username(),
			'date_created'          => $customer->get_date_created() ? $customer->get_date_created()->date( 'Y-m-d' ) : null,
			'orders_count'          => (int) $customer->get_order_count(),
			'total_spent'           => $customer->get_total_spent(),
			'is_paying_customer'    => $customer->get_is_paying_customer(),
			'billing'               => $customer->get_billing(),
		);
	}

	/**
	 * List customers with basic profile info.
	 */
	public function execute_listCustomers( $params ) {
		$args = array(
			'role'   => 'customer',
			'number' => isset( $params['limit'] ) ? min( 100, \absint( $params['limit'] ) ) : 20,
		);
		if ( ! empty( $params['search'] ) ) {
			$args['search']         = '*' . \sanitize_text_field( $params['search'] ) . '*';
			$args['search_columns'] = array( 'user_email', 'user_login', 'display_name' );
		}
		$users = \get_users( $args );
		$out   = array();
		foreach ( $users as $u ) {
			$out[] = array(
				'id'         => (int) $u->ID,
				'email'      => $u->user_email,
				'username'   => $u->user_login,
				'display'    => $u->display_name,
				'registered' => $u->user_registered,
			);
		}
		return array( 'customers' => $out, 'count' => count( $out ) );
	}

	/**
	 * List product categories with post counts.
	 */
	public function execute_listProductCategories( $params ) {
		$terms = \get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		if ( \is_wp_error( $terms ) ) {
			return $terms;
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'    => (int) $t->term_id,
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => (int) $t->count,
			);
		}
		return array( 'categories' => $out, 'count' => count( $out ) );
	}

	/**
	 * Sales totals + counts for a date range.
	 */
	public function execute_getSalesReport( $params ) {
		$to   = ! empty( $params['to'] )   ? \sanitize_text_field( $params['to'] )   : \gmdate( 'Y-m-d' );
		$from = ! empty( $params['from'] ) ? \sanitize_text_field( $params['from'] ) : \gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$orders = \wc_get_orders( array(
			'limit'        => -1,
			'status'       => array( 'completed', 'processing' ),
			'date_created' => $from . '...' . $to,
		) );
		$total = 0.0;
		foreach ( $orders as $o ) {
			$total += (float) $o->get_total();
		}
		return array(
			'from'         => $from,
			'to'           => $to,
			'orders_count' => count( $orders ),
			'gross_total'  => round( $total, 2 ),
			'average'      => $orders ? round( $total / count( $orders ), 2 ) : 0,
			'currency'     => \get_woocommerce_currency(),
		);
	}

	/**
	 * Top-selling products by units in a period.
	 */
	public function execute_getTopSellers( $params ) {
		$to    = ! empty( $params['to'] )    ? \sanitize_text_field( $params['to'] )    : \gmdate( 'Y-m-d' );
		$from  = ! empty( $params['from'] )  ? \sanitize_text_field( $params['from'] )  : \gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$limit = isset( $params['limit'] )   ? min( 100, \absint( $params['limit'] ) )  : 10;

		$orders = \wc_get_orders( array(
			'limit'        => -1,
			'status'       => array( 'completed', 'processing' ),
			'date_created' => $from . '...' . $to,
		) );
		$counts = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$pid = (int) $item->get_product_id();
				if ( ! isset( $counts[ $pid ] ) ) {
					$counts[ $pid ] = 0;
				}
				$counts[ $pid ] += (int) $item->get_quantity();
			}
		}
		arsort( $counts );
		$counts = array_slice( $counts, 0, $limit, true );
		$out    = array();
		foreach ( $counts as $pid => $qty ) {
			$product = \wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$out[] = array(
				'product_id' => $pid,
				'name'       => $product->get_name(),
				'sku'        => $product->get_sku(),
				'units_sold' => $qty,
				'price'      => $product->get_price(),
			);
		}
		return array( 'from' => $from, 'to' => $to, 'top' => $out );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * Write executors (v1.1.0)
	 * ────────────────────────────────────────────────────────────────────*/

	/**
	 * Create a new product.
	 */
	public function execute_createProduct( $params ) {
		if ( empty( $params['name'] ) ) {
			return new \WP_Error( 'missing_parameter', 'Required parameter name is missing', array( 'status' => 400 ) );
		}
		$product = new \WC_Product_Simple();
		$product->set_name( \sanitize_text_field( $params['name'] ) );
		if ( isset( $params['regular_price'] ) ) {
			$product->set_regular_price( (string) $params['regular_price'] );
		}
		if ( isset( $params['sale_price'] ) ) {
			$product->set_sale_price( (string) $params['sale_price'] );
		}
		if ( isset( $params['description'] ) ) {
			$product->set_description( \wp_kses_post( $params['description'] ) );
		}
		if ( isset( $params['short_description'] ) ) {
			$product->set_short_description( \wp_kses_post( $params['short_description'] ) );
		}
		if ( ! empty( $params['sku'] ) ) {
			$product->set_sku( \sanitize_text_field( $params['sku'] ) );
		}
		if ( isset( $params['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $params['stock_quantity'] );
		}
		if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
			$product->set_category_ids( array_map( 'absint', $params['categories'] ) );
		}
		if ( ! empty( $params['status'] ) ) {
			$product->set_status( \sanitize_text_field( $params['status'] ) );
		}
		$id = $product->save();
		return array( 'id' => (int) $id, 'name' => $product->get_name(), 'permalink' => \get_permalink( $id ) );
	}

	/**
	 * Update an existing product.
	 */
	public function execute_updateProduct( $params ) {
		$id      = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$product = $id ? \wc_get_product( $id ) : null;
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		}
		foreach ( array( 'name' => 'set_name', 'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price', 'status' => 'set_status' ) as $key => $setter ) {
			if ( isset( $params[ $key ] ) ) {
				$product->{$setter}( \sanitize_text_field( $params[ $key ] ) );
			}
		}
		if ( isset( $params['description'] ) ) {
			$product->set_description( \wp_kses_post( $params['description'] ) );
		}
		$product->save();
		return array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'status'        => $product->get_status(),
			'updated'       => true,
		);
	}

	/**
	 * Update stock quantity for a product.
	 */
	public function execute_updateInventory( $params ) {
		$id      = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$product = $id ? \wc_get_product( $id ) : null;
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		}
		if ( ! isset( $params['stock_quantity'] ) ) {
			return new \WP_Error( 'missing_parameter', 'Required parameter stock_quantity is missing', array( 'status' => 400 ) );
		}
		$manage = isset( $params['manage_stock'] ) ? (bool) $params['manage_stock'] : true;
		$product->set_manage_stock( $manage );
		$product->set_stock_quantity( (int) $params['stock_quantity'] );
		$product->save();
		return array(
			'id'             => $product->get_id(),
			'stock_quantity' => (int) $product->get_stock_quantity(),
			'in_stock'       => $product->is_in_stock(),
			'manage_stock'   => $product->get_manage_stock(),
		);
	}

	/**
	 * Create a new order with line items.
	 */
	public function execute_createOrder( $params ) {
		if ( empty( $params['line_items'] ) || ! is_array( $params['line_items'] ) ) {
			return new \WP_Error( 'missing_parameter', 'Required parameter line_items is missing', array( 'status' => 400 ) );
		}
		$order = \wc_create_order( array(
			'status'      => ! empty( $params['status'] ) ? \sanitize_text_field( $params['status'] ) : 'pending',
			'customer_id' => isset( $params['customer_id'] ) ? \absint( $params['customer_id'] ) : 0,
		) );
		if ( \is_wp_error( $order ) ) {
			return $order;
		}
		foreach ( $params['line_items'] as $li ) {
			if ( empty( $li['product_id'] ) || empty( $li['quantity'] ) ) {
				continue;
			}
			$product = \wc_get_product( \absint( $li['product_id'] ) );
			if ( ! $product ) {
				continue;
			}
			$order->add_product( $product, (int) $li['quantity'] );
		}
		if ( ! empty( $params['billing'] ) && is_array( $params['billing'] ) ) {
			$order->set_address( $params['billing'], 'billing' );
		}
		$order->calculate_totals();
		$order->save();
		return array(
			'id'           => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'status'       => $order->get_status(),
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
		);
	}

	/**
	 * Change an order's status.
	 */
	public function execute_updateOrderStatus( $params ) {
		$id    = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		if ( empty( $params['status'] ) ) {
			return new \WP_Error( 'missing_parameter', 'Required parameter status is missing', array( 'status' => 400 ) );
		}
		$note = ! empty( $params['note'] ) ? \sanitize_text_field( $params['note'] ) : '';
		$order->update_status( \sanitize_text_field( $params['status'] ), $note );
		return array(
			'id'         => $order->get_id(),
			'status'     => $order->get_status(),
			'updated'    => true,
		);
	}
}
