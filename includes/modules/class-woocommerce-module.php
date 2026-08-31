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
		// Agents (goldnat, Claude Desktop, etc.) sometimes serialise
		// object/array params to JSON strings before dispatch. Auto-decode
		// so tool handlers get the right PHP types. Idempotent — non-JSON
		// strings pass through unchanged.
		$params = $this->coerce_json_params( $params );

		// Dev / CI bypass — same pattern as Elementor + bridge-pro License_Client.
		// Prod is unaffected: without the env var, Freemius still gates.
		$env = getenv( 'AICONNECT_EDITION' );
		if ( is_string( $env ) && strtolower( $env ) === 'pro' ) {
			return parent::execute_tool( $tool_name, $params );
		}

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

		// ── v1.2.0 — Coupons ──────────────────────────────────────────────
		$this->register_tool( 'createCoupon', array(
			'description'  => 'Create a new WooCommerce coupon (percent / fixed_cart / fixed_product).',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'code', 'discount_type', 'amount' ),
				'properties' => array(
					'code'                        => array( 'type' => 'string', 'description' => 'Coupon code shoppers enter at checkout' ),
					'discount_type'               => array( 'type' => 'string', 'description' => 'percent / fixed_cart / fixed_product' ),
					'amount'                      => array( 'type' => 'string', 'description' => 'Discount amount (percent value like "20" or currency like "5.00")' ),
					'description'                 => array( 'type' => 'string' ),
					'date_expires'                => array( 'type' => 'string', 'description' => 'ISO date; expires end of day' ),
					'individual_use'              => array( 'type' => 'boolean', 'description' => 'Cannot be combined with other coupons' ),
					'usage_limit'                 => array( 'type' => 'integer', 'description' => 'Total uses allowed across all customers' ),
					'usage_limit_per_user'        => array( 'type' => 'integer' ),
					'free_shipping'               => array( 'type' => 'boolean' ),
					'minimum_amount'              => array( 'type' => 'string' ),
					'product_ids'                 => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'excluded_product_ids'        => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'product_categories'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				),
			),
		) );
		$this->register_tool( 'listCoupons', array(
			'description'  => 'List existing coupons with usage stats. Filter by status/search.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array( 'type' => 'string', 'description' => 'publish/draft/expired (default: publish)' ),
					'search' => array( 'type' => 'string', 'description' => 'Match against coupon code' ),
					'limit'  => array( 'type' => 'integer', 'default' => 20 ),
				),
			),
		) );
		$this->register_tool( 'updateCoupon', array(
			'description'  => 'Modify amount, expiry, usage limit, or status of an existing coupon.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'coupon_id' ),
				'properties' => array(
					'coupon_id'    => array( 'type' => 'integer' ),
					'amount'       => array( 'type' => 'string' ),
					'date_expires' => array( 'type' => 'string' ),
					'usage_limit'  => array( 'type' => 'integer' ),
					'description'  => array( 'type' => 'string' ),
				),
			),
		) );
		$this->register_tool( 'deleteCoupon', array(
			'description'  => 'Trash a coupon by ID (recoverable) or force-delete it.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'coupon_id' ),
				'properties' => array(
					'coupon_id' => array( 'type' => 'integer' ),
					'force'     => array( 'type' => 'boolean', 'description' => 'True for permanent delete' ),
				),
			),
		) );

		// ── v1.2.0 — Refunds ──────────────────────────────────────────────
		$this->register_tool( 'createRefund', array(
			'description'  => 'Issue a full or partial refund on an order. Supports line-item breakdown and restocking.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'amount' ),
				'properties' => array(
					'order_id'      => array( 'type' => 'integer' ),
					'amount'        => array( 'type' => 'string', 'description' => 'Refund amount in order currency' ),
					'reason'        => array( 'type' => 'string' ),
					'restock_items' => array( 'type' => 'boolean', 'description' => 'Return refunded line items to stock' ),
					'refund_payment' => array( 'type' => 'boolean', 'description' => 'Also trigger payment-gateway refund (default false)' ),
				),
			),
		) );
		$this->register_tool( 'listRefunds', array(
			'description'  => 'List all refunds issued against an order with amount, reason, date.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array( 'order_id' => array( 'type' => 'integer' ) ),
			),
		) );

		// ── v1.2.0 — Order Notes ──────────────────────────────────────────
		$this->register_tool( 'addOrderNote', array(
			'description'  => 'Add a note to an order — internal (admin-only) or customer-visible (triggers email).',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'note' ),
				'properties' => array(
					'order_id'         => array( 'type' => 'integer' ),
					'note'             => array( 'type' => 'string' ),
					'is_customer_note' => array( 'type' => 'boolean', 'description' => 'If true, sends note to customer via email (default: false)' ),
				),
			),
		) );
		$this->register_tool( 'listOrderNotes', array(
			'description'  => 'Retrieve all notes on an order with author, timestamp, and type.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id' ),
				'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
					'type'     => array( 'type' => 'string', 'description' => 'internal / customer / any (default any)' ),
				),
			),
		) );

		// ── v1.2.0 — Product Variations ───────────────────────────────────
		$this->register_tool( 'listProductVariations', array(
			'description'  => 'List all variations of a variable product with attributes, price, SKU, stock.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'product_id' ),
				'properties' => array( 'product_id' => array( 'type' => 'integer' ) ),
			),
		) );

		// ── v1.2.0 — Reports ──────────────────────────────────────────────
		$this->register_tool( 'getLowStockReport', array(
			'description'  => 'Products at or below the low-stock threshold, plus a list of out-of-stock SKUs.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array( 'limit' => array( 'type' => 'integer', 'default' => 50 ) ),
			),
		) );

		// ── v1.2.0 — Customer Management ──────────────────────────────────
		$this->register_tool( 'createCustomer', array(
			'description'  => 'Create a new WooCommerce customer with email + address (auto-generates username if omitted).',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'email' ),
				'properties' => array(
					'email'      => array( 'type' => 'string' ),
					'username'   => array( 'type' => 'string' ),
					'password'   => array( 'type' => 'string' ),
					'first_name' => array( 'type' => 'string' ),
					'last_name'  => array( 'type' => 'string' ),
					'billing'    => array( 'type' => 'object', 'description' => 'Billing address fields' ),
					'shipping'   => array( 'type' => 'object' ),
				),
			),
		) );
		$this->register_tool( 'updateCustomer', array(
			'description'  => 'Update customer email/name/address/phone.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'customer_id' ),
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
					'email'       => array( 'type' => 'string' ),
					'first_name'  => array( 'type' => 'string' ),
					'last_name'   => array( 'type' => 'string' ),
					'billing'     => array( 'type' => 'object' ),
					'shipping'    => array( 'type' => 'object' ),
				),
			),
		) );

		// ── v1.2.0 — Order Actions ────────────────────────────────────────
		$this->register_tool( 'sendOrderEmail', array(
			'description'  => 'Trigger a WooCommerce order email (customer_invoice / customer_processing_order / customer_completed_order / new_order).',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'email_type' ),
				'properties' => array(
					'order_id'   => array( 'type' => 'integer' ),
					'email_type' => array( 'type' => 'string', 'description' => 'customer_invoice / customer_processing_order / customer_completed_order / customer_refunded_order / new_order' ),
				),
			),
		) );
		$this->register_tool( 'setOrderTracking', array(
			'description'  => 'Attach shipment tracking metadata to an order (carrier, tracking number, tracking URL).',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'order_id', 'tracking_number' ),
				'properties' => array(
					'order_id'        => array( 'type' => 'integer' ),
					'tracking_number' => array( 'type' => 'string' ),
					'carrier'         => array( 'type' => 'string' ),
					'tracking_url'    => array( 'type' => 'string' ),
					'notify_customer' => array( 'type' => 'boolean', 'description' => 'Add a customer-visible note with the tracking info' ),
				),
			),
		) );

		// ── v1.2.0 — Catalog ──────────────────────────────────────────────
		$this->register_tool( 'createProductCategory', array(
			'description'  => 'Create a WooCommerce product category (product_cat term) with optional parent + description.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'name' ),
				'properties' => array(
					'name'        => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'parent_id'   => array( 'type' => 'integer' ),
					'description' => array( 'type' => 'string' ),
				),
			),
		) );

		// ── v1.2.0 — Cart ─────────────────────────────────────────────────
		$this->register_tool( 'applyCouponToCart', array(
			'description'  => 'Apply a coupon code to the current cart, returning updated totals.',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'code' ),
				'properties' => array( 'code' => array( 'type' => 'string' ) ),
			),
		) );

		// ── v1.3.0 — Product CRUD extras ─────────────────────────────────
		$this->register_tool( 'deleteProduct', array(
			'description'  => 'Delete a product (trash or force-delete).',
			'input_schema' => array( 'type' => 'object', 'required' => array('product_id'), 'properties' => array( 'product_id' => array('type'=>'integer'), 'force' => array('type'=>'boolean') ) ),
		) );
		$this->register_tool( 'duplicateProduct', array(
			'description'  => 'Duplicate a product as a new draft (copies core fields, meta, taxonomies).',
			'input_schema' => array( 'type' => 'object', 'required' => array('product_id'), 'properties' => array( 'product_id' => array('type'=>'integer'), 'new_name' => array('type'=>'string') ) ),
		) );
		$this->register_tool( 'bulkUpdateProducts', array(
			'description'  => 'Apply the same price/stock/status/category patch to many products in one call.',
			'input_schema' => array( 'type' => 'object', 'required' => array('product_ids','patch'), 'properties' => array( 'product_ids' => array('type'=>'array','items'=>array('type'=>'integer')), 'patch' => array('type'=>'object','description'=>'Fields: regular_price, sale_price, status, stock_quantity, category_ids') ) ),
		) );

		// ── v1.3.0 — Order CRUD extras ────────────────────────────────────
		$this->register_tool( 'updateOrder', array(
			'description'  => 'Update order fields beyond status: customer_id, billing/shipping addresses, customer_note.',
			'input_schema' => array( 'type' => 'object', 'required' => array('order_id'), 'properties' => array( 'order_id'=>array('type'=>'integer'), 'customer_id'=>array('type'=>'integer'), 'billing'=>array('type'=>'object'), 'shipping'=>array('type'=>'object'), 'customer_note'=>array('type'=>'string') ) ),
		) );
		$this->register_tool( 'deleteOrder', array(
			'description'  => 'Delete an order (trash or force-delete). Force-delete is IRREVERSIBLE and destroys refunds.',
			'input_schema' => array( 'type' => 'object', 'required' => array('order_id'), 'properties' => array( 'order_id'=>array('type'=>'integer'), 'force'=>array('type'=>'boolean') ) ),
		) );

		// ── v1.3.0 — Inventory ────────────────────────────────────────────
		$this->register_tool( 'bulkUpdateStock', array(
			'description'  => 'Set stock quantities for many products in one call: [{product_id, stock_quantity}].',
			'input_schema' => array( 'type' => 'object', 'required' => array('updates'), 'properties' => array( 'updates' => array('type'=>'array','items'=>array('type'=>'object','required'=>array('product_id','stock_quantity'),'properties'=>array('product_id'=>array('type'=>'integer'),'stock_quantity'=>array('type'=>'integer')))) ) ),
		) );
		$this->register_tool( 'getStockStatus', array(
			'description'  => 'Get stock status of one product (or SKU): in_stock/out_of_stock/on_backorder, quantity, manage_stock.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'product_id'=>array('type'=>'integer'), 'sku'=>array('type'=>'string') ) ),
		) );

		// ── v1.3.0 — Customer ─────────────────────────────────────────────
		$this->register_tool( 'deleteCustomer', array(
			'description'  => 'Delete a customer account. Optionally reassign their content/orders to another user.',
			'input_schema' => array( 'type' => 'object', 'required' => array('customer_id'), 'properties' => array( 'customer_id'=>array('type'=>'integer'), 'reassign_to'=>array('type'=>'integer','description'=>'User ID to reassign posts/orders to (default: null → delete content)') ) ),
		) );

		// ── v1.3.0 — Product Variations CRUD ─────────────────────────────
		$this->register_tool( 'getProductVariation', array(
			'description'  => 'Get one variation with attributes, price, SKU, stock.',
			'input_schema' => array( 'type' => 'object', 'required' => array('variation_id'), 'properties' => array( 'variation_id' => array('type'=>'integer') ) ),
		) );
		$this->register_tool( 'createProductVariation', array(
			'description'  => 'Add a variation to a variable product with attributes + price + stock.',
			'input_schema' => array( 'type' => 'object', 'required' => array('product_id','attributes'), 'properties' => array( 'product_id'=>array('type'=>'integer'), 'attributes'=>array('type'=>'object','description'=>'{taxonomy: value} e.g. {"pa_color":"blue","pa_size":"large"}'), 'regular_price'=>array('type'=>'string'), 'sale_price'=>array('type'=>'string'), 'sku'=>array('type'=>'string'), 'stock_quantity'=>array('type'=>'integer'), 'manage_stock'=>array('type'=>'boolean') ) ),
		) );
		$this->register_tool( 'updateProductVariation', array(
			'description'  => 'Update variation price/stock/SKU/status.',
			'input_schema' => array( 'type' => 'object', 'required' => array('variation_id'), 'properties' => array( 'variation_id'=>array('type'=>'integer'), 'regular_price'=>array('type'=>'string'), 'sale_price'=>array('type'=>'string'), 'sku'=>array('type'=>'string'), 'stock_quantity'=>array('type'=>'integer'), 'status'=>array('type'=>'string') ) ),
		) );
		$this->register_tool( 'deleteProductVariation', array(
			'description'  => 'Delete a variation (force-delete recommended).',
			'input_schema' => array( 'type' => 'object', 'required' => array('variation_id'), 'properties' => array( 'variation_id'=>array('type'=>'integer'), 'force'=>array('type'=>'boolean') ) ),
		) );

		// ── v1.3.0 — Categories & Tags ────────────────────────────────────
		$this->register_tool( 'updateProductCategory', array(
			'description'  => 'Update product category name/slug/description/parent.',
			'input_schema' => array( 'type' => 'object', 'required' => array('term_id'), 'properties' => array( 'term_id'=>array('type'=>'integer'), 'name'=>array('type'=>'string'), 'slug'=>array('type'=>'string'), 'description'=>array('type'=>'string'), 'parent_id'=>array('type'=>'integer') ) ),
		) );
		$this->register_tool( 'deleteProductCategory', array(
			'description'  => 'Delete a product category (products keep their other categories).',
			'input_schema' => array( 'type' => 'object', 'required' => array('term_id'), 'properties' => array( 'term_id'=>array('type'=>'integer') ) ),
		) );
		$this->register_tool( 'createProductTag', array(
			'description'  => 'Create a new product tag (product_tag term).',
			'input_schema' => array( 'type' => 'object', 'required' => array('name'), 'properties' => array( 'name'=>array('type'=>'string'), 'slug'=>array('type'=>'string'), 'description'=>array('type'=>'string') ) ),
		) );
		$this->register_tool( 'listProductTags', array(
			'description'  => 'List product tags with post counts.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'search'=>array('type'=>'string'), 'limit'=>array('type'=>'integer','default'=>50) ) ),
		) );

		// ── v1.3.0 — Shipping ─────────────────────────────────────────────
		$this->register_tool( 'listShippingZones', array(
			'description'  => 'List all shipping zones with regions + assigned methods.',
			'input_schema' => array( 'type' => 'object', 'properties' => (object) array() ),
		) );
		$this->register_tool( 'getShippingMethods', array(
			'description'  => 'List all registered shipping method types (flat_rate, free_shipping, local_pickup, etc.).',
			'input_schema' => array( 'type' => 'object', 'properties' => (object) array() ),
		) );
		$this->register_tool( 'updateShippingZone', array(
			'description'  => 'Rename a shipping zone or reorder its position.',
			'input_schema' => array( 'type' => 'object', 'required' => array('zone_id'), 'properties' => array( 'zone_id'=>array('type'=>'integer'), 'name'=>array('type'=>'string'), 'order'=>array('type'=>'integer') ) ),
		) );

		// ── v1.3.0 — Taxes ────────────────────────────────────────────────
		$this->register_tool( 'listTaxRates', array(
			'description'  => 'List all tax rates with country/state/rate/name.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'class'=>array('type'=>'string','description'=>'Tax class (standard/reduced-rate/zero-rate)') ) ),
		) );
		$this->register_tool( 'updateTaxRate', array(
			'description'  => 'Update a tax rate: percentage, name, priority, compound, shipping.',
			'input_schema' => array( 'type' => 'object', 'required' => array('rate_id'), 'properties' => array( 'rate_id'=>array('type'=>'integer'), 'rate'=>array('type'=>'string'), 'name'=>array('type'=>'string'), 'priority'=>array('type'=>'integer'), 'compound'=>array('type'=>'boolean'), 'shipping'=>array('type'=>'boolean') ) ),
		) );

		// ── v1.3.0 — Reports (expanded) ──────────────────────────────────
		$this->register_tool( 'getRevenueReport', array(
			'description'  => 'Revenue breakdown over a date range with day-by-day totals and comparison to previous period.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'from'=>array('type'=>'string'), 'to'=>array('type'=>'string'), 'compare'=>array('type'=>'boolean') ) ),
		) );
		$this->register_tool( 'getCustomerReport', array(
			'description'  => 'Top customers by spend + new/returning breakdown for a date range.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'from'=>array('type'=>'string'), 'to'=>array('type'=>'string'), 'limit'=>array('type'=>'integer','default'=>20) ) ),
		) );

		// ── v1.3.0 — Reviews ──────────────────────────────────────────────
		$this->register_tool( 'listProductReviews', array(
			'description'  => 'List product reviews with rating + status + author.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'product_id'=>array('type'=>'integer'), 'status'=>array('type'=>'string','description'=>'approve/hold/spam/trash (default: any)'), 'min_rating'=>array('type'=>'integer'), 'limit'=>array('type'=>'integer','default'=>20) ) ),
		) );
		$this->register_tool( 'updateReview', array(
			'description'  => 'Approve / hold / spam / trash a review.',
			'input_schema' => array( 'type' => 'object', 'required' => array('comment_id','status'), 'properties' => array( 'comment_id'=>array('type'=>'integer'), 'status'=>array('type'=>'string','description'=>'approve/hold/spam/trash') ) ),
		) );
		$this->register_tool( 'replyToReview', array(
			'description'  => 'Post a reply to a review as the site admin.',
			'input_schema' => array( 'type' => 'object', 'required' => array('comment_id','content'), 'properties' => array( 'comment_id'=>array('type'=>'integer'), 'content'=>array('type'=>'string') ) ),
		) );

		// ── v1.3.0 — Webhooks ─────────────────────────────────────────────
		$this->register_tool( 'listWebhooks', array(
			'description'  => 'List active WooCommerce webhooks with topic + delivery URL.',
			'input_schema' => array( 'type' => 'object', 'properties' => array( 'status'=>array('type'=>'string') ) ),
		) );
		$this->register_tool( 'createWebhook', array(
			'description'  => 'Create a webhook: topic (order.created, product.updated…), delivery URL, secret.',
			'input_schema' => array( 'type' => 'object', 'required' => array('name','topic','delivery_url'), 'properties' => array( 'name'=>array('type'=>'string'), 'topic'=>array('type'=>'string'), 'delivery_url'=>array('type'=>'string'), 'secret'=>array('type'=>'string') ) ),
		) );
		$this->register_tool( 'deleteWebhook', array(
			'description'  => 'Delete a webhook by ID.',
			'input_schema' => array( 'type' => 'object', 'required' => array('webhook_id'), 'properties' => array( 'webhook_id'=>array('type'=>'integer') ) ),
		) );

		// ── v1.3.0 — Payment Gateways ────────────────────────────────────
		$this->register_tool( 'listPaymentGateways', array(
			'description'  => 'List available payment gateways with enabled state + settings summary.',
			'input_schema' => array( 'type' => 'object', 'properties' => (object) array() ),
		) );
		$this->register_tool( 'updatePaymentGateway', array(
			'description'  => 'Enable / disable a payment gateway or update its title/description.',
			'input_schema' => array( 'type' => 'object', 'required' => array('gateway_id'), 'properties' => array( 'gateway_id'=>array('type'=>'string'), 'enabled'=>array('type'=>'boolean'), 'title'=>array('type'=>'string'), 'description'=>array('type'=>'string') ) ),
		) );

		// ── v1.3.0 — Attributes ──────────────────────────────────────────
		$this->register_tool( 'listProductAttributes', array(
			'description'  => 'List global product attributes (Color, Size…) with their term counts.',
			'input_schema' => array( 'type' => 'object', 'properties' => (object) array() ),
		) );
		$this->register_tool( 'createProductAttribute', array(
			'description'  => 'Create a global attribute (product_attribute) with name + slug + type.',
			'input_schema' => array( 'type' => 'object', 'required' => array('name'), 'properties' => array( 'name'=>array('type'=>'string'), 'slug'=>array('type'=>'string'), 'type'=>array('type'=>'string','default'=>'select'), 'order_by'=>array('type'=>'string','default'=>'menu_order'), 'has_archives'=>array('type'=>'boolean') ) ),
		) );
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
		// Defensive: some code paths (unbounded wc_get_orders() without
		// `type` filter, or a caller passing a refund_id) can hand us a
		// WC_Order_Refund which lacks get_order_number(). Fall back to the
		// numeric ID string so the caller still gets a usable payload
		// instead of a fatal.
		$order_number = method_exists( $order, 'get_order_number' )
			? $order->get_order_number()
			: (string) $order->get_id();
		$date         = $order->get_date_created();
		return array(
			'id'             => $order->get_id(),
			'order_number'   => $order_number,
			'status'         => $order->get_status(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'date_created'   => $date ? $date->date( 'Y-m-d H:i:s' ) : null,
			'payment_method' => method_exists( $order, 'get_payment_method_title' ) ? $order->get_payment_method_title() : null,
			'items_count'    => method_exists( $order, 'get_item_count' ) ? $order->get_item_count() : 0,
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
			// Explicit type: without it, wc_get_orders() may return refund objects
			// alongside real orders, and format_order() blows up on
			// WC_Order_Refund which lacks get_order_number(). Restrict to
			// shop_order so both "listOrders" and "getOrders" behave the same.
			'type'  => 'shop_order',
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

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Coupons
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_createCoupon( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		foreach ( array( 'code', 'discount_type', 'amount' ) as $required ) {
			if ( empty( $params[ $required ] ) ) {
				return new \WP_Error( 'missing_parameter', "{$required} is required", array( 'status' => 400 ) );
			}
		}
		$allowed_types = array( 'percent', 'fixed_cart', 'fixed_product' );
		$type          = \sanitize_text_field( $params['discount_type'] );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', 'discount_type must be one of: ' . implode( ', ', $allowed_types ), array( 'status' => 400 ) );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( \sanitize_text_field( $params['code'] ) );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( (string) $params['amount'] );
		if ( ! empty( $params['description'] ) )              $coupon->set_description( \wp_kses_post( $params['description'] ) );
		if ( ! empty( $params['date_expires'] ) )             $coupon->set_date_expires( \sanitize_text_field( $params['date_expires'] ) );
		if ( isset( $params['individual_use'] ) )             $coupon->set_individual_use( (bool) $params['individual_use'] );
		if ( isset( $params['usage_limit'] ) )                $coupon->set_usage_limit( (int) $params['usage_limit'] );
		if ( isset( $params['usage_limit_per_user'] ) )       $coupon->set_usage_limit_per_user( (int) $params['usage_limit_per_user'] );
		if ( isset( $params['free_shipping'] ) )              $coupon->set_free_shipping( (bool) $params['free_shipping'] );
		if ( isset( $params['minimum_amount'] ) )             $coupon->set_minimum_amount( (string) $params['minimum_amount'] );
		if ( ! empty( $params['product_ids'] ) )              $coupon->set_product_ids( array_map( 'absint', (array) $params['product_ids'] ) );
		if ( ! empty( $params['excluded_product_ids'] ) )     $coupon->set_excluded_product_ids( array_map( 'absint', (array) $params['excluded_product_ids'] ) );
		if ( ! empty( $params['product_categories'] ) )       $coupon->set_product_categories( array_map( 'absint', (array) $params['product_categories'] ) );

		$id = $coupon->save();
		return array(
			'id'            => (int) $id,
			'code'          => $coupon->get_code(),
			'discount_type' => $coupon->get_discount_type(),
			'amount'        => $coupon->get_amount(),
			'date_expires'  => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
		);
	}

	public function execute_listCoupons( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$limit  = isset( $params['limit'] )  ? min( 100, \absint( $params['limit'] ) )  : 20;
		$status = ! empty( $params['status'] ) ? \sanitize_text_field( $params['status'] ) : 'publish';

		$args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => $status === 'any' ? array( 'publish', 'draft', 'expired' ) : $status,
			'posts_per_page' => $limit,
		);
		if ( ! empty( $params['search'] ) ) $args['s'] = \sanitize_text_field( $params['search'] );

		$posts = \get_posts( $args );
		$out   = array();
		foreach ( $posts as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			$out[]  = array(
				'id'             => (int) $coupon->get_id(),
				'code'           => $coupon->get_code(),
				'discount_type'  => $coupon->get_discount_type(),
				'amount'         => $coupon->get_amount(),
				'usage_count'    => (int) $coupon->get_usage_count(),
				'usage_limit'    => (int) $coupon->get_usage_limit(),
				'date_expires'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
				'status'         => $post->post_status,
			);
		}
		return array( 'coupons' => $out, 'count' => count( $out ) );
	}

	public function execute_updateCoupon( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$id     = isset( $params['coupon_id'] ) ? \absint( $params['coupon_id'] ) : 0;
		$coupon = $id ? new \WC_Coupon( $id ) : null;
		if ( ! $coupon || ! $coupon->get_id() ) {
			return new \WP_Error( 'coupon_not_found', 'Coupon not found', array( 'status' => 404 ) );
		}
		if ( isset( $params['amount'] ) )       $coupon->set_amount( (string) $params['amount'] );
		if ( isset( $params['date_expires'] ) ) $coupon->set_date_expires( \sanitize_text_field( $params['date_expires'] ) );
		if ( isset( $params['usage_limit'] ) )  $coupon->set_usage_limit( (int) $params['usage_limit'] );
		if ( isset( $params['description'] ) )  $coupon->set_description( \wp_kses_post( $params['description'] ) );
		$coupon->save();
		return array(
			'id'           => (int) $coupon->get_id(),
			'code'         => $coupon->get_code(),
			'amount'       => $coupon->get_amount(),
			'date_expires' => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
			'updated'      => true,
		);
	}

	public function execute_deleteCoupon( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$id     = isset( $params['coupon_id'] ) ? \absint( $params['coupon_id'] ) : 0;
		$coupon = $id ? new \WC_Coupon( $id ) : null;
		if ( ! $coupon || ! $coupon->get_id() ) {
			return new \WP_Error( 'coupon_not_found', 'Coupon not found', array( 'status' => 404 ) );
		}
		$force = ! empty( $params['force'] );
		$result = $coupon->delete( $force );
		return array(
			'id'      => $id,
			'deleted' => (bool) $result,
			'force'   => $force,
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Refunds
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_createRefund( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$order_id = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		if ( ! $order_id || ! \wc_get_order( $order_id ) ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		if ( ! isset( $params['amount'] ) ) {
			return new \WP_Error( 'missing_parameter', 'amount is required', array( 'status' => 400 ) );
		}

		$args = array(
			'order_id'       => $order_id,
			'amount'         => (string) $params['amount'],
			'reason'         => ! empty( $params['reason'] ) ? \sanitize_text_field( $params['reason'] ) : '',
			'refund_payment' => ! empty( $params['refund_payment'] ),
			'restock_items'  => ! empty( $params['restock_items'] ),
		);
		$refund = \wc_create_refund( $args );
		if ( \is_wp_error( $refund ) ) return $refund;
		if ( ! $refund ) {
			return new \WP_Error( 'refund_failed', 'wc_create_refund returned null', array( 'status' => 500 ) );
		}
		return array(
			'refund_id'    => (int) $refund->get_id(),
			'order_id'     => $order_id,
			'amount'       => $refund->get_amount(),
			'reason'       => $refund->get_reason(),
			'date_created' => $refund->get_date_created() ? $refund->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
		);
	}

	public function execute_listRefunds( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$order_id = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order    = $order_id ? \wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		$refunds = $order->get_refunds();
		$out     = array();
		foreach ( $refunds as $r ) {
			$out[] = array(
				'refund_id'    => (int) $r->get_id(),
				'amount'       => $r->get_amount(),
				'reason'       => $r->get_reason(),
				'date_created' => $r->get_date_created() ? $r->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
				'refunded_by'  => (int) $r->get_refunded_by(),
			);
		}
		$total_refunded = 0.0;
		foreach ( $refunds as $r ) $total_refunded += (float) $r->get_amount();
		return array(
			'order_id'       => $order_id,
			'refunds'        => $out,
			'count'          => count( $out ),
			'total_refunded' => round( $total_refunded, 2 ),
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Order Notes
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_addOrderNote( $params ) {
		if ( ! \current_user_can( 'edit_shop_orders' ) ) {
			return new \WP_Error( 'forbidden', 'edit_shop_orders required', array( 'status' => 403 ) );
		}
		$id    = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		if ( empty( $params['note'] ) ) {
			return new \WP_Error( 'missing_parameter', 'note is required', array( 'status' => 400 ) );
		}
		$is_customer_note = ! empty( $params['is_customer_note'] );
		$note_id = $order->add_order_note( \wp_kses_post( $params['note'] ), $is_customer_note ? 1 : 0, true );
		return array(
			'note_id'          => (int) $note_id,
			'order_id'         => $id,
			'is_customer_note' => $is_customer_note,
		);
	}

	public function execute_listOrderNotes( $params ) {
		if ( ! \current_user_can( 'edit_shop_orders' ) ) {
			return new \WP_Error( 'forbidden', 'edit_shop_orders required', array( 'status' => 403 ) );
		}
		$id = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		if ( ! $id || ! \wc_get_order( $id ) ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		$type_filter = ! empty( $params['type'] ) ? \sanitize_text_field( $params['type'] ) : 'any';
		$args        = array( 'order_id' => $id );
		if ( $type_filter === 'internal' )  $args['type'] = 'internal';
		if ( $type_filter === 'customer' )  $args['type'] = 'customer';

		$notes = function_exists( 'wc_get_order_notes' ) ? \wc_get_order_notes( $args ) : array();
		$out   = array();
		foreach ( $notes as $n ) {
			$out[] = array(
				'id'               => (int) $n->id,
				'author'           => $n->added_by,
				'is_customer_note' => (bool) $n->customer_note,
				'date_created'     => $n->date_created ? $n->date_created->date( 'Y-m-d H:i:s' ) : null,
				'content'          => $n->content,
			);
		}
		return array( 'order_id' => $id, 'notes' => $out, 'count' => count( $out ) );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Product Variations
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listProductVariations( $params ) {
		$id      = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$product = $id ? \wc_get_product( $id ) : null;
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		}
		if ( $product->get_type() !== 'variable' ) {
			return new \WP_Error( 'not_variable', 'Product is not a variable product', array( 'status' => 400 ) );
		}
		$variation_ids = $product->get_children();
		$out           = array();
		foreach ( $variation_ids as $vid ) {
			$v = \wc_get_product( $vid );
			if ( ! $v ) continue;
			$out[] = array(
				'id'             => (int) $v->get_id(),
				'sku'            => $v->get_sku(),
				'price'          => $v->get_price(),
				'regular_price'  => $v->get_regular_price(),
				'sale_price'     => $v->get_sale_price(),
				'stock_quantity' => $v->get_stock_quantity(),
				'in_stock'       => $v->is_in_stock(),
				'attributes'     => $v->get_variation_attributes(),
			);
		}
		return array( 'product_id' => $id, 'variations' => $out, 'count' => count( $out ) );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Reports
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_getLowStockReport( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		}
		$limit     = isset( $params['limit'] ) ? min( 200, \absint( $params['limit'] ) ) : 50;
		$threshold = (int) \get_option( 'woocommerce_notify_low_stock_amount', 2 );

		$low = \wc_get_products( array(
			'status'       => 'publish',
			'stock_status' => 'instock',
			'limit'        => $limit,
			'meta_query'   => array(
				array( 'key' => '_stock', 'value' => $threshold, 'compare' => '<=', 'type' => 'NUMERIC' ),
				array( 'key' => '_manage_stock', 'value' => 'yes' ),
			),
		) );
		$oos = \wc_get_products( array( 'status' => 'publish', 'stock_status' => 'outofstock', 'limit' => $limit ) );
		$bo  = \wc_get_products( array( 'status' => 'publish', 'stock_status' => 'onbackorder', 'limit' => $limit ) );

		$fmt = function ( $p ) {
			return array(
				'id'             => $p->get_id(),
				'name'           => $p->get_name(),
				'sku'            => $p->get_sku(),
				'stock_quantity' => $p->get_stock_quantity(),
				'stock_status'   => $p->get_stock_status(),
			);
		};
		return array(
			'low_stock_threshold' => $threshold,
			'low_stock'           => array_map( $fmt, $low ),
			'out_of_stock'        => array_map( $fmt, $oos ),
			'on_backorder'        => array_map( $fmt, $bo ),
			'counts'              => array(
				'low_stock'    => count( $low ),
				'out_of_stock' => count( $oos ),
				'on_backorder' => count( $bo ),
			),
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Customer Management
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_createCustomer( $params ) {
		if ( ! \current_user_can( 'create_users' ) ) {
			return new \WP_Error( 'forbidden', 'create_users required', array( 'status' => 403 ) );
		}
		if ( empty( $params['email'] ) || ! is_email( $params['email'] ) ) {
			return new \WP_Error( 'invalid_email', 'valid email is required', array( 'status' => 400 ) );
		}
		$email    = \sanitize_email( $params['email'] );
		$username = ! empty( $params['username'] ) ? \sanitize_user( $params['username'] ) : '';
		$password = ! empty( $params['password'] ) ? (string) $params['password'] : \wp_generate_password( 16 );

		$new_id = \wc_create_new_customer( $email, $username, $password );
		if ( \is_wp_error( $new_id ) ) return $new_id;

		$customer = new \WC_Customer( $new_id );
		if ( ! empty( $params['first_name'] ) ) $customer->set_first_name( \sanitize_text_field( $params['first_name'] ) );
		if ( ! empty( $params['last_name'] ) )  $customer->set_last_name(  \sanitize_text_field( $params['last_name'] ) );
		if ( ! empty( $params['billing'] ) && is_array( $params['billing'] ) )   $customer->set_billing( $params['billing'] );
		if ( ! empty( $params['shipping'] ) && is_array( $params['shipping'] ) ) $customer->set_shipping( $params['shipping'] );
		$customer->save();

		return array(
			'id'         => (int) $new_id,
			'email'      => $customer->get_email(),
			'username'   => $customer->get_username(),
			'first_name' => $customer->get_first_name(),
			'last_name'  => $customer->get_last_name(),
		);
	}

	public function execute_updateCustomer( $params ) {
		if ( ! \current_user_can( 'edit_users' ) ) {
			return new \WP_Error( 'forbidden', 'edit_users required', array( 'status' => 403 ) );
		}
		$id       = isset( $params['customer_id'] ) ? \absint( $params['customer_id'] ) : 0;
		$customer = $id ? new \WC_Customer( $id ) : null;
		if ( ! $customer || 0 === $customer->get_id() ) {
			return new \WP_Error( 'customer_not_found', 'Customer not found', array( 'status' => 404 ) );
		}
		if ( isset( $params['email'] ) && is_email( $params['email'] ) ) {
			$customer->set_email( \sanitize_email( $params['email'] ) );
		}
		if ( isset( $params['first_name'] ) ) $customer->set_first_name( \sanitize_text_field( $params['first_name'] ) );
		if ( isset( $params['last_name'] ) )  $customer->set_last_name(  \sanitize_text_field( $params['last_name'] ) );
		if ( ! empty( $params['billing'] ) && is_array( $params['billing'] ) )   $customer->set_billing( $params['billing'] );
		if ( ! empty( $params['shipping'] ) && is_array( $params['shipping'] ) ) $customer->set_shipping( $params['shipping'] );
		$customer->save();

		return array(
			'id'         => (int) $customer->get_id(),
			'email'      => $customer->get_email(),
			'first_name' => $customer->get_first_name(),
			'last_name'  => $customer->get_last_name(),
			'updated'    => true,
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Order Actions
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_sendOrderEmail( $params ) {
		if ( ! \current_user_can( 'edit_shop_orders' ) ) {
			return new \WP_Error( 'forbidden', 'edit_shop_orders required', array( 'status' => 403 ) );
		}
		$id    = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		$type = \sanitize_key( $params['email_type'] ?? '' );
		if ( $type === '' ) {
			return new \WP_Error( 'missing_parameter', 'email_type is required', array( 'status' => 400 ) );
		}

		$mailer = \WC()->mailer();
		$emails = $mailer->get_emails();
		$key    = 'WC_Email_' . implode( '_', array_map( 'ucfirst', explode( '_', $type ) ) );
		if ( ! isset( $emails[ $key ] ) ) {
			return new \WP_Error( 'unknown_email', "Email class {$key} not registered. Try: customer_invoice / customer_processing_order / customer_completed_order / customer_refunded_order / new_order.", array( 'status' => 400 ) );
		}
		$emails[ $key ]->trigger( $id );
		return array(
			'order_id'   => $id,
			'email_type' => $type,
			'sent'       => true,
		);
	}

	public function execute_setOrderTracking( $params ) {
		if ( ! \current_user_can( 'edit_shop_orders' ) ) {
			return new \WP_Error( 'forbidden', 'edit_shop_orders required', array( 'status' => 403 ) );
		}
		$id    = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		if ( empty( $params['tracking_number'] ) ) {
			return new \WP_Error( 'missing_parameter', 'tracking_number is required', array( 'status' => 400 ) );
		}
		$tracking = \sanitize_text_field( $params['tracking_number'] );
		$carrier  = ! empty( $params['carrier'] )      ? \sanitize_text_field( $params['carrier'] )      : '';
		$url      = ! empty( $params['tracking_url'] ) ? \esc_url_raw( $params['tracking_url'] )         : '';

		// Compatible with the popular "Shipment Tracking" plugin's data shape;
		// falls back to plain postmeta when that plugin isn't active.
		$item = array(
			'tracking_provider'        => $carrier,
			'custom_tracking_provider' => $carrier,
			'tracking_number'          => $tracking,
			'custom_tracking_link'     => $url,
			'date_shipped'             => time(),
		);
		$existing   = $order->get_meta( '_wc_shipment_tracking_items', true );
		$items      = is_array( $existing ) ? $existing : array();
		$items[]    = $item;
		$order->update_meta_data( '_wc_shipment_tracking_items', $items );
		$order->update_meta_data( '_tracking_number', $tracking );
		if ( $carrier ) $order->update_meta_data( '_tracking_carrier', $carrier );
		if ( $url )     $order->update_meta_data( '_tracking_url', $url );
		$order->save();

		if ( ! empty( $params['notify_customer'] ) ) {
			$note = sprintf( 'Tracking updated: %s%s%s',
				$carrier ? $carrier . ' — ' : '',
				$tracking,
				$url ? ' (' . $url . ')' : ''
			);
			$order->add_order_note( $note, 1, true );
		}
		return array(
			'order_id'        => $id,
			'tracking_number' => $tracking,
			'carrier'         => $carrier,
			'tracking_url'    => $url,
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Catalog
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_createProductCategory( $params ) {
		if ( ! \current_user_can( 'manage_product_terms' ) && ! \current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error( 'forbidden', 'manage_product_terms required', array( 'status' => 403 ) );
		}
		$name = \sanitize_text_field( $params['name'] ?? '' );
		if ( $name === '' ) {
			return new \WP_Error( 'missing_parameter', 'name is required', array( 'status' => 400 ) );
		}
		$args = array();
		if ( ! empty( $params['slug'] ) )        $args['slug']        = \sanitize_title( $params['slug'] );
		if ( ! empty( $params['description'] ) ) $args['description'] = \wp_kses_post( $params['description'] );
		if ( ! empty( $params['parent_id'] ) )   $args['parent']      = \absint( $params['parent_id'] );

		$result = \wp_insert_term( $name, 'product_cat', $args );
		if ( \is_wp_error( $result ) ) return $result;
		return array(
			'term_id'     => (int) $result['term_id'],
			'name'        => $name,
			'slug'        => \get_term( $result['term_id'] )->slug,
			'parent_id'   => (int) ( $args['parent'] ?? 0 ),
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.2.0 — Cart
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_applyCouponToCart( $params ) {
		if ( ! function_exists( 'WC' ) ) {
			return new \WP_Error( 'wc_not_available', 'WooCommerce not available', array( 'status' => 500 ) );
		}
		$code = \sanitize_text_field( $params['code'] ?? '' );
		if ( $code === '' ) {
			return new \WP_Error( 'missing_parameter', 'code is required', array( 'status' => 400 ) );
		}
		\wc_load_cart();
		$applied = \WC()->cart->apply_coupon( $code );
		$errors  = array();
		foreach ( (array) \wc_get_notices( 'error' ) as $n ) {
			$errors[] = is_array( $n ) ? ( $n['notice'] ?? '' ) : (string) $n;
		}
		\wc_clear_notices();

		if ( ! $applied ) {
			return new \WP_Error( 'apply_failed', 'Coupon could not be applied', array( 'status' => 400, 'errors' => $errors ) );
		}
		return array(
			'code'         => $code,
			'applied'      => true,
			'cart_total'   => \WC()->cart->get_cart_total(),
			'discount'     => \WC()->cart->get_discount_total(),
			'coupons'      => array_map( 'strtoupper', \WC()->cart->get_applied_coupons() ),
		);
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Product CRUD extras
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_deleteProduct( $params ) {
		if ( ! \current_user_can( 'delete_products' ) ) return new \WP_Error( 'forbidden', 'delete_products required', array( 'status' => 403 ) );
		$id = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$product = $id ? \wc_get_product( $id ) : null;
		if ( ! $product ) return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		$force = ! empty( $params['force'] );
		$result = $product->delete( $force );
		return array( 'id' => $id, 'deleted' => (bool) $result, 'force' => $force );
	}

	public function execute_duplicateProduct( $params ) {
		if ( ! \current_user_can( 'edit_products' ) ) return new \WP_Error( 'forbidden', 'edit_products required', array( 'status' => 403 ) );
		$id = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$product = $id ? \wc_get_product( $id ) : null;
		if ( ! $product ) return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		$duplicator = new \WC_Admin_Duplicate_Product();
		$duplicate  = $duplicator->product_duplicate( $product );
		if ( ! empty( $params['new_name'] ) ) {
			$duplicate->set_name( \sanitize_text_field( $params['new_name'] ) );
			$duplicate->save();
		}
		return array( 'source_id' => $id, 'new_id' => (int) $duplicate->get_id(), 'new_name' => $duplicate->get_name(), 'status' => $duplicate->get_status() );
	}

	public function execute_bulkUpdateProducts( $params ) {
		if ( ! \current_user_can( 'edit_products' ) ) return new \WP_Error( 'forbidden', 'edit_products required', array( 'status' => 403 ) );
		$ids   = isset( $params['product_ids'] ) && is_array( $params['product_ids'] ) ? array_map( 'absint', $params['product_ids'] ) : array();
		$patch = isset( $params['patch'] ) && is_array( $params['patch'] ) ? $params['patch'] : array();
		if ( ! $ids || ! $patch ) return new \WP_Error( 'invalid_data', 'product_ids and patch required', array( 'status' => 400 ) );
		$results = array();
		foreach ( $ids as $pid ) {
			$p = \wc_get_product( $pid );
			if ( ! $p ) { $results[] = array( 'id' => $pid, 'success' => false, 'error' => 'not_found' ); continue; }
			foreach ( array( 'regular_price'=>'set_regular_price','sale_price'=>'set_sale_price','status'=>'set_status' ) as $k=>$s ) {
				if ( isset( $patch[$k] ) ) $p->{$s}( \sanitize_text_field( (string) $patch[$k] ) );
			}
			if ( isset( $patch['stock_quantity'] ) ) { $p->set_manage_stock( true ); $p->set_stock_quantity( (int) $patch['stock_quantity'] ); }
			if ( ! empty( $patch['category_ids'] ) && is_array( $patch['category_ids'] ) ) $p->set_category_ids( array_map( 'absint', $patch['category_ids'] ) );
			$p->save();
			$results[] = array( 'id' => $pid, 'success' => true );
		}
		$ok = count( array_filter( $results, function ( $r ) { return $r['success']; } ) );
		return array( 'total' => count( $results ), 'succeeded' => $ok, 'results' => $results );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Order CRUD extras
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_updateOrder( $params ) {
		if ( ! \current_user_can( 'edit_shop_orders' ) ) return new \WP_Error( 'forbidden', 'edit_shop_orders required', array( 'status' => 403 ) );
		$id = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		if ( isset( $params['customer_id'] ) )       $order->set_customer_id( \absint( $params['customer_id'] ) );
		if ( isset( $params['customer_note'] ) )     $order->set_customer_note( \wp_kses_post( $params['customer_note'] ) );
		if ( ! empty( $params['billing'] ) && is_array( $params['billing'] ) )   $order->set_address( $params['billing'], 'billing' );
		if ( ! empty( $params['shipping'] ) && is_array( $params['shipping'] ) ) $order->set_address( $params['shipping'], 'shipping' );
		$order->save();
		return array( 'id' => $id, 'updated' => true, 'customer_id' => $order->get_customer_id() );
	}

	public function execute_deleteOrder( $params ) {
		if ( ! \current_user_can( 'delete_shop_orders' ) ) return new \WP_Error( 'forbidden', 'delete_shop_orders required', array( 'status' => 403 ) );
		$id = isset( $params['order_id'] ) ? \absint( $params['order_id'] ) : 0;
		$order = $id ? \wc_get_order( $id ) : null;
		if ( ! $order ) return new \WP_Error( 'order_not_found', 'Order not found', array( 'status' => 404 ) );
		$force  = ! empty( $params['force'] );
		$result = $order->delete( $force );
		return array( 'id' => $id, 'deleted' => (bool) $result, 'force' => $force );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Inventory
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_bulkUpdateStock( $params ) {
		if ( ! \current_user_can( 'edit_products' ) ) return new \WP_Error( 'forbidden', 'edit_products required', array( 'status' => 403 ) );
		$updates = isset( $params['updates'] ) && is_array( $params['updates'] ) ? $params['updates'] : array();
		if ( ! $updates ) return new \WP_Error( 'invalid_data', 'updates array required', array( 'status' => 400 ) );
		$results = array();
		foreach ( $updates as $u ) {
			$pid = isset( $u['product_id'] ) ? \absint( $u['product_id'] ) : 0;
			$qty = isset( $u['stock_quantity'] ) ? (int) $u['stock_quantity'] : null;
			$p = $pid ? \wc_get_product( $pid ) : null;
			if ( ! $p || $qty === null ) { $results[] = array( 'product_id'=>$pid, 'success'=>false, 'error'=>$p ? 'missing_qty' : 'not_found' ); continue; }
			$p->set_manage_stock( true );
			$p->set_stock_quantity( $qty );
			$p->save();
			$results[] = array( 'product_id' => $pid, 'stock_quantity' => $qty, 'success' => true );
		}
		return array( 'total' => count( $results ), 'succeeded' => count( array_filter( $results, function ( $r ) { return $r['success']; } ) ), 'results' => $results );
	}

	public function execute_getStockStatus( $params ) {
		$id = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		if ( ! $id && ! empty( $params['sku'] ) ) $id = \wc_get_product_id_by_sku( \sanitize_text_field( $params['sku'] ) );
		$p = $id ? \wc_get_product( $id ) : null;
		if ( ! $p ) return new \WP_Error( 'product_not_found', 'Product not found', array( 'status' => 404 ) );
		return array( 'id' => $p->get_id(), 'sku' => $p->get_sku(), 'name' => $p->get_name(), 'stock_status' => $p->get_stock_status(), 'stock_quantity' => $p->get_stock_quantity(), 'manage_stock' => $p->get_manage_stock(), 'in_stock' => $p->is_in_stock(), 'backorders' => $p->get_backorders() );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Customer
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_deleteCustomer( $params ) {
		if ( ! \current_user_can( 'delete_users' ) ) return new \WP_Error( 'forbidden', 'delete_users required', array( 'status' => 403 ) );
		$id = isset( $params['customer_id'] ) ? \absint( $params['customer_id'] ) : 0;
		if ( ! $id || ! \get_user_by( 'id', $id ) ) return new \WP_Error( 'customer_not_found', 'Customer not found', array( 'status' => 404 ) );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$reassign = isset( $params['reassign_to'] ) ? \absint( $params['reassign_to'] ) : null;
		$result   = \wp_delete_user( $id, $reassign );
		return array( 'id' => $id, 'deleted' => (bool) $result, 'reassigned_to' => $reassign );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Product Variations CRUD
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_getProductVariation( $params ) {
		$vid = isset( $params['variation_id'] ) ? \absint( $params['variation_id'] ) : 0;
		$v = $vid ? \wc_get_product( $vid ) : null;
		if ( ! $v || $v->get_type() !== 'variation' ) return new \WP_Error( 'variation_not_found', 'Variation not found', array( 'status' => 404 ) );
		return array( 'id'=>$v->get_id(), 'parent_id'=>$v->get_parent_id(), 'sku'=>$v->get_sku(), 'price'=>$v->get_price(), 'regular_price'=>$v->get_regular_price(), 'sale_price'=>$v->get_sale_price(), 'stock_quantity'=>$v->get_stock_quantity(), 'in_stock'=>$v->is_in_stock(), 'attributes'=>$v->get_variation_attributes(), 'status'=>$v->get_status() );
	}

	public function execute_createProductVariation( $params ) {
		if ( ! \current_user_can( 'edit_products' ) ) return new \WP_Error( 'forbidden', 'edit_products required', array( 'status' => 403 ) );
		$pid = isset( $params['product_id'] ) ? \absint( $params['product_id'] ) : 0;
		$parent = $pid ? \wc_get_product( $pid ) : null;
		if ( ! $parent || $parent->get_type() !== 'variable' ) return new \WP_Error( 'not_variable', 'Parent product must be variable', array( 'status' => 400 ) );
		if ( empty( $params['attributes'] ) || ! is_array( $params['attributes'] ) ) return new \WP_Error( 'missing_parameter', 'attributes object is required', array( 'status' => 400 ) );
		$v = new \WC_Product_Variation();
		$v->set_parent_id( $pid );
		$v->set_attributes( $params['attributes'] );
		if ( isset( $params['regular_price'] ) )  $v->set_regular_price( (string) $params['regular_price'] );
		if ( isset( $params['sale_price'] ) )     $v->set_sale_price( (string) $params['sale_price'] );
		if ( isset( $params['sku'] ) )            $v->set_sku( \sanitize_text_field( $params['sku'] ) );
		if ( isset( $params['stock_quantity'] ) ) { $v->set_manage_stock( true ); $v->set_stock_quantity( (int) $params['stock_quantity'] ); }
		if ( isset( $params['manage_stock'] ) )   $v->set_manage_stock( (bool) $params['manage_stock'] );
		$vid = $v->save();
		return array( 'variation_id' => (int) $vid, 'parent_id' => $pid, 'attributes' => $v->get_variation_attributes(), 'price' => $v->get_price() );
	}

	public function execute_updateProductVariation( $params ) {
		if ( ! \current_user_can( 'edit_products' ) ) return new \WP_Error( 'forbidden', 'edit_products required', array( 'status' => 403 ) );
		$vid = isset( $params['variation_id'] ) ? \absint( $params['variation_id'] ) : 0;
		$v = $vid ? \wc_get_product( $vid ) : null;
		if ( ! $v || $v->get_type() !== 'variation' ) return new \WP_Error( 'variation_not_found', 'Variation not found', array( 'status' => 404 ) );
		foreach ( array( 'regular_price'=>'set_regular_price','sale_price'=>'set_sale_price','sku'=>'set_sku','status'=>'set_status' ) as $k=>$s ) {
			if ( isset( $params[$k] ) ) $v->{$s}( \sanitize_text_field( (string) $params[$k] ) );
		}
		if ( isset( $params['stock_quantity'] ) ) { $v->set_manage_stock( true ); $v->set_stock_quantity( (int) $params['stock_quantity'] ); }
		$v->save();
		return array( 'variation_id' => $vid, 'updated' => true, 'price' => $v->get_price(), 'stock_quantity' => $v->get_stock_quantity() );
	}

	public function execute_deleteProductVariation( $params ) {
		if ( ! \current_user_can( 'delete_products' ) ) return new \WP_Error( 'forbidden', 'delete_products required', array( 'status' => 403 ) );
		$vid = isset( $params['variation_id'] ) ? \absint( $params['variation_id'] ) : 0;
		$v = $vid ? \wc_get_product( $vid ) : null;
		if ( ! $v || $v->get_type() !== 'variation' ) return new \WP_Error( 'variation_not_found', 'Variation not found', array( 'status' => 404 ) );
		$force = ! empty( $params['force'] );
		$result = $v->delete( $force );
		return array( 'variation_id' => $vid, 'deleted' => (bool) $result, 'force' => $force );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Categories & Tags
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_updateProductCategory( $params ) {
		if ( ! \current_user_can( 'manage_product_terms' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_product_terms required', array( 'status' => 403 ) );
		$id = isset( $params['term_id'] ) ? \absint( $params['term_id'] ) : 0;
		if ( ! $id || ! \get_term( $id, 'product_cat' ) ) return new \WP_Error( 'category_not_found', 'Category not found', array( 'status' => 404 ) );
		$args = array();
		if ( isset( $params['name'] ) )        $args['name']        = \sanitize_text_field( $params['name'] );
		if ( isset( $params['slug'] ) )        $args['slug']        = \sanitize_title( $params['slug'] );
		if ( isset( $params['description'] ) ) $args['description'] = \wp_kses_post( $params['description'] );
		if ( isset( $params['parent_id'] ) )   $args['parent']      = \absint( $params['parent_id'] );
		if ( ! $args ) return new \WP_Error( 'nothing_to_update', 'Provide at least one of: name, slug, description, parent_id', array( 'status' => 400 ) );
		$result = \wp_update_term( $id, 'product_cat', $args );
		if ( \is_wp_error( $result ) ) return $result;
		return array( 'term_id' => $id, 'updated' => true );
	}

	public function execute_deleteProductCategory( $params ) {
		if ( ! \current_user_can( 'manage_product_terms' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_product_terms required', array( 'status' => 403 ) );
		$id = isset( $params['term_id'] ) ? \absint( $params['term_id'] ) : 0;
		if ( ! $id || ! \get_term( $id, 'product_cat' ) ) return new \WP_Error( 'category_not_found', 'Category not found', array( 'status' => 404 ) );
		$result = \wp_delete_term( $id, 'product_cat' );
		if ( \is_wp_error( $result ) ) return $result;
		return array( 'term_id' => $id, 'deleted' => (bool) $result );
	}

	public function execute_createProductTag( $params ) {
		if ( ! \current_user_can( 'manage_product_terms' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_product_terms required', array( 'status' => 403 ) );
		if ( empty( $params['name'] ) ) return new \WP_Error( 'missing_parameter', 'name is required', array( 'status' => 400 ) );
		$args = array();
		if ( isset( $params['slug'] ) )        $args['slug']        = \sanitize_title( $params['slug'] );
		if ( isset( $params['description'] ) ) $args['description'] = \wp_kses_post( $params['description'] );
		$result = \wp_insert_term( \sanitize_text_field( $params['name'] ), 'product_tag', $args );
		if ( \is_wp_error( $result ) ) return $result;
		return array( 'term_id' => (int) $result['term_id'], 'name' => $params['name'] );
	}

	public function execute_listProductTags( $params ) {
		$args = array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'number' => isset( $params['limit'] ) ? min( 200, \absint( $params['limit'] ) ) : 50 );
		if ( ! empty( $params['search'] ) ) $args['search'] = \sanitize_text_field( $params['search'] );
		$terms = \get_terms( $args );
		if ( \is_wp_error( $terms ) ) return $terms;
		$out = array_map( function ( $t ) { return array( 'id'=>(int)$t->term_id, 'name'=>$t->name, 'slug'=>$t->slug, 'count'=>(int)$t->count ); }, $terms );
		return array( 'tags' => $out, 'count' => count( $out ) );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Shipping
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listShippingZones( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$zones = \WC_Shipping_Zones::get_zones();
		$out   = array();
		foreach ( $zones as $z ) {
			$out[] = array( 'id'=>(int)$z['zone_id'], 'name'=>$z['zone_name'], 'order'=>(int)$z['zone_order'], 'locations'=>$z['zone_locations'] ?? array(), 'methods'=>array_map( function ( $m ) { return array( 'id'=>(int)$m->instance_id, 'method_id'=>$m->id, 'title'=>$m->get_title(), 'enabled'=>$m->is_enabled() ); }, $z['shipping_methods'] ?? array() ) );
		}
		// Include the "Rest of the world" zone (id=0)
		$rest = new \WC_Shipping_Zone( 0 );
		$out[] = array( 'id'=>0, 'name'=>$rest->get_zone_name(), 'order'=>0, 'locations'=>array(), 'methods'=>array_map( function ( $m ) { return array( 'id'=>(int)$m->instance_id, 'method_id'=>$m->id, 'title'=>$m->get_title(), 'enabled'=>$m->is_enabled() ); }, $rest->get_shipping_methods() ) );
		return array( 'zones' => $out, 'count' => count( $out ) );
	}

	public function execute_getShippingMethods( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$methods = \WC()->shipping()->get_shipping_methods();
		$out     = array();
		foreach ( $methods as $id => $m ) $out[] = array( 'id'=>$id, 'title'=>$m->method_title ?? $m->title, 'description'=>$m->method_description ?? '' );
		return array( 'methods' => $out, 'count' => count( $out ) );
	}

	public function execute_updateShippingZone( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$id   = isset( $params['zone_id'] ) ? \absint( $params['zone_id'] ) : 0;
		$zone = new \WC_Shipping_Zone( $id );
		if ( $id > 0 && ! $zone->get_id() ) return new \WP_Error( 'zone_not_found', 'Shipping zone not found', array( 'status' => 404 ) );
		if ( isset( $params['name'] ) )  $zone->set_zone_name( \sanitize_text_field( $params['name'] ) );
		if ( isset( $params['order'] ) ) $zone->set_zone_order( (int) $params['order'] );
		$zone->save();
		return array( 'zone_id' => $zone->get_id(), 'name' => $zone->get_zone_name(), 'order' => $zone->get_zone_order(), 'updated' => true );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Taxes
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listTaxRates( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		global $wpdb;
		$class = ! empty( $params['class'] ) ? \sanitize_text_field( $params['class'] ) : null;
		$sql   = "SELECT tax_rate_id id, tax_rate rate, tax_rate_name name, tax_rate_country country, tax_rate_state state, tax_rate_priority priority, tax_rate_compound compound, tax_rate_shipping shipping, tax_rate_class class FROM {$wpdb->prefix}woocommerce_tax_rates";
		if ( $class ) $sql .= $wpdb->prepare( ' WHERE tax_rate_class = %s', $class === 'standard' ? '' : $class );
		$rows  = $wpdb->get_results( $sql, ARRAY_A );
		return array( 'rates' => $rows, 'count' => count( $rows ) );
	}

	public function execute_updateTaxRate( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$id = isset( $params['rate_id'] ) ? \absint( $params['rate_id'] ) : 0;
		if ( ! $id ) return new \WP_Error( 'missing_parameter', 'rate_id required', array( 'status' => 400 ) );
		$data = array();
		if ( isset( $params['rate'] ) )     $data['tax_rate']          = (string) $params['rate'];
		if ( isset( $params['name'] ) )     $data['tax_rate_name']     = \sanitize_text_field( $params['name'] );
		if ( isset( $params['priority'] ) ) $data['tax_rate_priority'] = (int) $params['priority'];
		if ( isset( $params['compound'] ) ) $data['tax_rate_compound'] = ! empty( $params['compound'] ) ? 1 : 0;
		if ( isset( $params['shipping'] ) ) $data['tax_rate_shipping'] = ! empty( $params['shipping'] ) ? 1 : 0;
		if ( ! $data ) return new \WP_Error( 'nothing_to_update', 'Provide at least one field to update', array( 'status' => 400 ) );
		\WC_Tax::_update_tax_rate( $id, $data );
		return array( 'rate_id' => $id, 'updated' => true );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Reports (expanded)
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_getRevenueReport( $params ) {
		if ( ! \current_user_can( 'view_woocommerce_reports' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'view_woocommerce_reports required', array( 'status' => 403 ) );
		$to      = ! empty( $params['to'] )   ? \sanitize_text_field( $params['to'] )   : \gmdate( 'Y-m-d' );
		$from    = ! empty( $params['from'] ) ? \sanitize_text_field( $params['from'] ) : \gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$compare = ! empty( $params['compare'] );

		$orders = \wc_get_orders( array( 'limit' => -1, 'status' => array( 'completed', 'processing' ), 'date_created' => $from . '...' . $to ) );
		$daily  = array();
		$total  = 0.0;
		foreach ( $orders as $o ) {
			$d = $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d' ) : $from;
			$daily[ $d ] = ( $daily[ $d ] ?? 0 ) + (float) $o->get_total();
			$total += (float) $o->get_total();
		}
		ksort( $daily );

		$out = array( 'from' => $from, 'to' => $to, 'revenue_total' => round( $total, 2 ), 'orders_count' => count( $orders ), 'daily' => $daily, 'currency' => \get_woocommerce_currency() );
		if ( $compare ) {
			$days_diff  = (int) ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS );
			$prev_to    = \gmdate( 'Y-m-d', strtotime( $from ) - DAY_IN_SECONDS );
			$prev_from  = \gmdate( 'Y-m-d', strtotime( $prev_to ) - $days_diff * DAY_IN_SECONDS );
			$prev       = \wc_get_orders( array( 'limit' => -1, 'status' => array( 'completed', 'processing' ), 'date_created' => $prev_from . '...' . $prev_to ) );
			$prev_total = 0.0;
			foreach ( $prev as $o ) $prev_total += (float) $o->get_total();
			$out['previous_period']       = array( 'from' => $prev_from, 'to' => $prev_to, 'revenue_total' => round( $prev_total, 2 ), 'orders_count' => count( $prev ) );
			$out['change_percent']        = $prev_total > 0 ? round( ( ( $total - $prev_total ) / $prev_total ) * 100, 2 ) : null;
		}
		return $out;
	}

	public function execute_getCustomerReport( $params ) {
		if ( ! \current_user_can( 'view_woocommerce_reports' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'view_woocommerce_reports required', array( 'status' => 403 ) );
		$to    = ! empty( $params['to'] )   ? \sanitize_text_field( $params['to'] )   : \gmdate( 'Y-m-d' );
		$from  = ! empty( $params['from'] ) ? \sanitize_text_field( $params['from'] ) : \gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$limit = isset( $params['limit'] )  ? min( 100, \absint( $params['limit'] ) ) : 20;
		$orders = \wc_get_orders( array( 'limit' => -1, 'status' => array( 'completed', 'processing' ), 'date_created' => $from . '...' . $to ) );
		$byCust = array();
		$new_customer_ids = array();
		foreach ( $orders as $o ) {
			$cid = (int) $o->get_customer_id();
			if ( $cid === 0 ) continue;
			if ( ! isset( $byCust[ $cid ] ) ) $byCust[ $cid ] = array( 'orders' => 0, 'spent' => 0.0 );
			$byCust[ $cid ]['orders']++;
			$byCust[ $cid ]['spent'] += (float) $o->get_total();
			// "new" if customer's earliest order falls within window
			$earliest = \wc_get_orders( array( 'customer_id' => $cid, 'limit' => 1, 'orderby' => 'date', 'order' => 'ASC' ) );
			if ( $earliest && $earliest[0]->get_date_created() && $earliest[0]->get_date_created()->getTimestamp() >= strtotime( $from ) ) {
				$new_customer_ids[ $cid ] = true;
			}
		}
		uasort( $byCust, function ( $a, $b ) { return $b['spent'] <=> $a['spent']; } );
		$top = array_slice( $byCust, 0, $limit, true );
		$out = array();
		foreach ( $top as $cid => $data ) {
			$u = \get_userdata( $cid );
			$out[] = array( 'customer_id' => $cid, 'email' => $u ? $u->user_email : null, 'name' => $u ? $u->display_name : null, 'orders' => $data['orders'], 'total_spent' => round( $data['spent'], 2 ) );
		}
		return array( 'from' => $from, 'to' => $to, 'top_customers' => $out, 'new_customers' => count( $new_customer_ids ), 'returning_customers' => count( $byCust ) - count( $new_customer_ids ) );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Reviews
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listProductReviews( $params ) {
		$args = array( 'type' => 'review', 'number' => isset( $params['limit'] ) ? min( 100, \absint( $params['limit'] ) ) : 20 );
		if ( ! empty( $params['product_id'] ) ) $args['post_id'] = \absint( $params['product_id'] );
		if ( ! empty( $params['status'] ) )     $args['status']  = \sanitize_text_field( $params['status'] );
		$comments = \get_comments( $args );
		$min_rating = isset( $params['min_rating'] ) ? (int) $params['min_rating'] : 0;
		$out = array();
		foreach ( $comments as $c ) {
			$rating = (int) \get_comment_meta( $c->comment_ID, 'rating', true );
			if ( $min_rating && $rating < $min_rating ) continue;
			$out[] = array( 'id' => (int) $c->comment_ID, 'product_id' => (int) $c->comment_post_ID, 'author' => $c->comment_author, 'email' => $c->comment_author_email, 'rating' => $rating, 'content' => $c->comment_content, 'status' => \wp_get_comment_status( $c->comment_ID ), 'date' => $c->comment_date_gmt );
		}
		return array( 'reviews' => $out, 'count' => count( $out ) );
	}

	public function execute_updateReview( $params ) {
		if ( ! \current_user_can( 'moderate_comments' ) ) return new \WP_Error( 'forbidden', 'moderate_comments required', array( 'status' => 403 ) );
		$id     = isset( $params['comment_id'] ) ? \absint( $params['comment_id'] ) : 0;
		$status = \sanitize_text_field( $params['status'] ?? '' );
		if ( ! $id || ! \get_comment( $id ) ) return new \WP_Error( 'review_not_found', 'Review not found', array( 'status' => 404 ) );
		if ( ! in_array( $status, array( 'approve', 'hold', 'spam', 'trash' ), true ) ) return new \WP_Error( 'invalid_status', 'status must be approve/hold/spam/trash', array( 'status' => 400 ) );
		\wp_set_comment_status( $id, $status );
		return array( 'comment_id' => $id, 'status' => $status, 'updated' => true );
	}

	public function execute_replyToReview( $params ) {
		if ( ! \current_user_can( 'moderate_comments' ) ) return new \WP_Error( 'forbidden', 'moderate_comments required', array( 'status' => 403 ) );
		$parent = isset( $params['comment_id'] ) ? \absint( $params['comment_id'] ) : 0;
		$parent_comment = $parent ? \get_comment( $parent ) : null;
		if ( ! $parent_comment ) return new \WP_Error( 'review_not_found', 'Parent review not found', array( 'status' => 404 ) );
		if ( empty( $params['content'] ) ) return new \WP_Error( 'missing_parameter', 'content required', array( 'status' => 400 ) );
		$user_id = \get_current_user_id();
		$user    = \get_userdata( $user_id );
		$new_id  = \wp_insert_comment( array(
			'comment_post_ID'      => $parent_comment->comment_post_ID,
			'comment_author'       => $user ? $user->display_name : 'Admin',
			'comment_author_email' => $user ? $user->user_email : '',
			'comment_content'      => \wp_kses_post( $params['content'] ),
			'comment_parent'       => $parent,
			'user_id'              => $user_id,
			'comment_approved'     => 1,
		) );
		if ( ! $new_id ) return new \WP_Error( 'insert_failed', 'Failed to insert reply', array( 'status' => 500 ) );
		return array( 'reply_id' => (int) $new_id, 'parent_id' => $parent, 'product_id' => (int) $parent_comment->comment_post_ID );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Webhooks
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listWebhooks( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$data_store = \WC_Data_Store::load( 'webhook' );
		$args       = array( 'limit' => 100 );
		if ( ! empty( $params['status'] ) ) $args['status'] = \sanitize_text_field( $params['status'] );
		$ids        = $data_store->search_webhooks( $args );
		$out        = array();
		foreach ( $ids as $id ) {
			$w = \wc_get_webhook( $id );
			if ( ! $w ) continue;
			$out[] = array( 'id' => (int) $w->get_id(), 'name' => $w->get_name(), 'status' => $w->get_status(), 'topic' => $w->get_topic(), 'delivery_url' => $w->get_delivery_url() );
		}
		return array( 'webhooks' => $out, 'count' => count( $out ) );
	}

	public function execute_createWebhook( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		foreach ( array( 'name', 'topic', 'delivery_url' ) as $k ) {
			if ( empty( $params[ $k ] ) ) return new \WP_Error( 'missing_parameter', "$k required", array( 'status' => 400 ) );
		}
		$w = new \WC_Webhook();
		$w->set_name( \sanitize_text_field( $params['name'] ) );
		$w->set_topic( \sanitize_text_field( $params['topic'] ) );
		$w->set_delivery_url( \esc_url_raw( $params['delivery_url'] ) );
		$w->set_status( 'active' );
		$w->set_secret( ! empty( $params['secret'] ) ? (string) $params['secret'] : \wp_generate_password( 32, false ) );
		$w->set_user_id( \get_current_user_id() );
		$id = $w->save();
		return array( 'id' => (int) $id, 'name' => $w->get_name(), 'topic' => $w->get_topic(), 'delivery_url' => $w->get_delivery_url(), 'status' => $w->get_status() );
	}

	public function execute_deleteWebhook( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$id = isset( $params['webhook_id'] ) ? \absint( $params['webhook_id'] ) : 0;
		$w  = $id ? \wc_get_webhook( $id ) : null;
		if ( ! $w ) return new \WP_Error( 'webhook_not_found', 'Webhook not found', array( 'status' => 404 ) );
		$w->delete( true );
		return array( 'id' => $id, 'deleted' => true );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Payment Gateways
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listPaymentGateways( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$gateways = \WC()->payment_gateways()->payment_gateways();
		$out      = array();
		foreach ( $gateways as $id => $g ) {
			$out[] = array( 'id' => $id, 'title' => $g->title, 'description' => $g->description, 'method_title' => $g->method_title ?? '', 'enabled' => 'yes' === ( $g->enabled ?? 'no' ), 'order' => (int) ( $g->settings['order'] ?? 0 ) );
		}
		return array( 'gateways' => $out, 'count' => count( $out ) );
	}

	public function execute_updatePaymentGateway( $params ) {
		if ( ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_woocommerce required', array( 'status' => 403 ) );
		$gid = \sanitize_key( $params['gateway_id'] ?? '' );
		if ( $gid === '' ) return new \WP_Error( 'missing_parameter', 'gateway_id required', array( 'status' => 400 ) );
		$gateways = \WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gid ] ) ) return new \WP_Error( 'gateway_not_found', 'Payment gateway not found', array( 'status' => 404 ) );
		$g       = $gateways[ $gid ];
		$options = \get_option( "woocommerce_{$gid}_settings", array() );
		if ( ! is_array( $options ) ) $options = array();
		if ( isset( $params['enabled'] ) )     $options['enabled']     = ! empty( $params['enabled'] ) ? 'yes' : 'no';
		if ( isset( $params['title'] ) )       $options['title']       = \sanitize_text_field( $params['title'] );
		if ( isset( $params['description'] ) ) $options['description'] = \wp_kses_post( $params['description'] );
		\update_option( "woocommerce_{$gid}_settings", $options );
		return array( 'id' => $gid, 'updated' => true, 'enabled' => 'yes' === $options['enabled'], 'title' => $options['title'] ?? $g->title );
	}

	/* ────────────────────────────────────────────────────────────────────
	 * v1.3.0 — Attributes
	 * ────────────────────────────────────────────────────────────────────*/

	public function execute_listProductAttributes( $params ) {
		$taxes = \wc_get_attribute_taxonomies();
		$out   = array();
		foreach ( $taxes as $t ) {
			$slug = 'pa_' . $t->attribute_name;
			$out[] = array( 'id' => (int) $t->attribute_id, 'name' => $t->attribute_label, 'slug' => $t->attribute_name, 'taxonomy' => $slug, 'type' => $t->attribute_type, 'terms_count' => (int) \wp_count_terms( array( 'taxonomy' => $slug, 'hide_empty' => false ) ) );
		}
		return array( 'attributes' => $out, 'count' => count( $out ) );
	}

	public function execute_createProductAttribute( $params ) {
		if ( ! \current_user_can( 'manage_product_terms' ) && ! \current_user_can( 'manage_woocommerce' ) ) return new \WP_Error( 'forbidden', 'manage_product_terms required', array( 'status' => 403 ) );
		if ( empty( $params['name'] ) ) return new \WP_Error( 'missing_parameter', 'name required', array( 'status' => 400 ) );
		$data = array(
			'attribute_label'   => \sanitize_text_field( $params['name'] ),
			'attribute_name'    => ! empty( $params['slug'] ) ? \wc_sanitize_taxonomy_name( $params['slug'] ) : \wc_sanitize_taxonomy_name( $params['name'] ),
			'attribute_type'    => ! empty( $params['type'] ) ? \sanitize_key( $params['type'] ) : 'select',
			'attribute_orderby' => ! empty( $params['order_by'] ) ? \sanitize_key( $params['order_by'] ) : 'menu_order',
			'attribute_public'  => ! empty( $params['has_archives'] ) ? 1 : 0,
		);
		$id = \wc_create_attribute( $data );
		if ( \is_wp_error( $id ) ) return $id;
		return array( 'id' => (int) $id, 'name' => $data['attribute_label'], 'slug' => $data['attribute_name'], 'taxonomy' => 'pa_' . $data['attribute_name'] );
	}

	/**
	 * Recursively coerce JSON-string params to real arrays/objects.
	 * Agents (goldnat, Claude, etc.) sometimes send objects/arrays as
	 * JSON strings; tool handlers expect PHP types. Idempotent — non-JSON
	 * strings pass through unchanged.
	 */
	private function coerce_json_params( $params ) {
		if ( ! is_array( $params ) ) return $params;
		foreach ( $params as $k => $v ) {
			if ( is_string( $v ) && $v !== '' ) {
				$trimmed = ltrim( $v );
				if ( isset( $trimmed[0] ) && ( $trimmed[0] === '[' || $trimmed[0] === '{' ) ) {
					$decoded = json_decode( $v, true );
					if ( is_array( $decoded ) ) {
						$params[ $k ] = $decoded;
					}
				}
			}
		}
		return $params;
	}
}
