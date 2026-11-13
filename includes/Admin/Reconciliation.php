<?php

namespace JFB_Razorpay\Admin;

use Jet_Form_Builder\Exceptions\Gateway_Exception;
use Jet_Form_Builder\Exceptions\Query_Builder_Exception;
use JFB_Modules\Gateways\Db_Models\Payment_Model;
use JFB_Modules\Gateways\Query_Views\Payment_With_Record_View;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Gateway_Context;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Logic\Pay_Now_Logic;
use JFB_Razorpay\Compatibility\Jet_Form_Builder\Tabs\Razorpay_Tab;
use Razorpay\Api\Api;

if ( ! defined( 'WPINC' ) ) {
	die();
}

class Reconciliation {
	const MENU_SLUG     = 'jfb-razorpay-reconciliation';
	const ACTION        = 'jfb_razorpay_reconcile';
	const NONCE_ACTION  = 'jfb_razorpay_reconcile_payments';
	const RESULT_PREFIX = 'jfb_razorpay_reconcile_result_';
	const DEFAULT_LIMIT = 20;
	const DEFAULT_AGE   = 30;

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_reconcile' ) );
	}

	public function add_menu_page(): void {
		add_management_page(
			__( 'Razorpay Reconciliation', 'jet-form-builder-razorpay-gateway' ),
			__( 'Razorpay Reconciliation', 'jet-form-builder-razorpay-gateway' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'jet-form-builder-razorpay-gateway' ) );
		}

		$result = get_transient( $this->result_key() );
		delete_transient( $this->result_key() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'JetFormBuilder Razorpay Reconciliation', 'jet-form-builder-razorpay-gateway' ); ?></h1>
			<p>
				<?php esc_html_e( 'Use this tool to check old JetFormBuilder Razorpay payments that are still marked CREATED. It asks Razorpay whether the related order was actually paid, then completes the matching JetFormBuilder payment and runs the gateway success actions once.', 'jet-form-builder-razorpay-gateway' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'This is useful for event registrations, donations, application fees, and similar form payments where the customer paid but the browser did not return to the website.', 'jet-form-builder-razorpay-gateway' ); ?>
			</p>

			<?php if ( is_array( $result ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Reconciliation completed.', 'jet-form-builder-razorpay-gateway' ); ?></strong>
						<?php
						printf(
							/* translators: 1: checked count, 2: completed count, 3: skipped count, 4: error count */
							esc_html__( 'Checked: %1$d. Completed: %2$d. Skipped: %3$d. Errors: %4$d.', 'jet-form-builder-razorpay-gateway' ),
							(int) ( $result['checked'] ?? 0 ),
							(int) ( $result['completed'] ?? 0 ),
							(int) ( $result['skipped'] ?? 0 ),
							(int) ( $result['errors'] ?? 0 )
						);
						?>
					</p>
				</div>

				<?php if ( ! empty( $result['items'] ) && is_array( $result['items'] ) ) : ?>
					<table class="widefat striped" style="max-width: 1100px; margin: 16px 0;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'JFB Payment ID', 'jet-form-builder-razorpay-gateway' ); ?></th>
								<th><?php esc_html_e( 'Razorpay Order', 'jet-form-builder-razorpay-gateway' ); ?></th>
								<th><?php esc_html_e( 'Action', 'jet-form-builder-razorpay-gateway' ); ?></th>
								<th><?php esc_html_e( 'Message', 'jet-form-builder-razorpay-gateway' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['items'] as $item ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( $item['payment_id'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $item['order_id'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $item['action'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $item['message'] ?? '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 24px;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="jfb_razorpay_age_minutes"><?php esc_html_e( 'Only check records older than', 'jet-form-builder-razorpay-gateway' ); ?></label></th>
						<td>
							<input type="number" id="jfb_razorpay_age_minutes" name="age_minutes" min="5" max="1440" value="<?php echo esc_attr( self::DEFAULT_AGE ); ?>" class="small-text" />
							<?php esc_html_e( 'minutes', 'jet-form-builder-razorpay-gateway' ); ?>
							<p class="description"><?php esc_html_e( 'Default is 30 minutes, so active checkout sessions are not touched.', 'jet-form-builder-razorpay-gateway' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jfb_razorpay_limit"><?php esc_html_e( 'Maximum records per run', 'jet-form-builder-razorpay-gateway' ); ?></label></th>
						<td>
							<input type="number" id="jfb_razorpay_limit" name="limit" min="1" max="100" value="<?php echo esc_attr( self::DEFAULT_LIMIT ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Keep this modest to avoid long admin requests and unnecessary Razorpay API calls.', 'jet-form-builder-razorpay-gateway' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Check Pending Razorpay Payments', 'jet-form-builder-razorpay-gateway' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_reconcile(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run reconciliation.', 'jet-form-builder-razorpay-gateway' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$limit = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : self::DEFAULT_LIMIT;
		$age   = isset( $_POST['age_minutes'] ) ? absint( wp_unslash( $_POST['age_minutes'] ) ) : self::DEFAULT_AGE;

		$limit = min( max( $limit, 1 ), 100 );
		$age   = min( max( $age, 5 ), 1440 );

		$result = $this->run( $limit, $age );

		set_transient( $this->result_key(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'tools.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	public function run( int $limit = self::DEFAULT_LIMIT, int $age_minutes = self::DEFAULT_AGE ): array {
		$result = array(
			'checked'   => 0,
			'completed' => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'items'     => array(),
		);

		$credentials = Razorpay_Tab::get_credentials();

		if ( empty( $credentials['key_id'] ) || empty( $credentials['secret'] ) ) {
			$result['errors']++;
			$result['items'][] = array(
				'payment_id' => '',
				'order_id'   => '',
				'action'     => 'configuration_error',
				'message'    => __( 'Razorpay Key ID or Key Secret is missing.', 'jet-form-builder-razorpay-gateway' ),
			);

			return $result;
		}

		$api  = new Api( $credentials['key_id'], $credentials['secret'] );
		$rows = $this->find_pending_rows( $limit, $age_minutes );

		foreach ( $rows as $row ) {
			$result['checked']++;

			$item = $this->process_row( $row, $api );
			$result['items'][] = $item;

			if ( 'completed' === ( $item['action'] ?? '' ) ) {
				$result['completed']++;
			} elseif ( 'error' === ( $item['action'] ?? '' ) ) {
				$result['errors']++;
			} else {
				$result['skipped']++;
			}
		}

		return $result;
	}

	private function find_pending_rows( int $limit, int $age_minutes ): array {
		global $wpdb;

		$table  = Payment_Model::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $age_minutes * MINUTE_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway_id = %s AND status = %s AND created_at <= %s AND initial_transaction_id LIKE %s ORDER BY id ASC LIMIT %d",
				'razorpay',
				'CREATED',
				$cutoff,
				'order_%',
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private function process_row( array $row, Api $api ): array {
		$order_id   = sanitize_text_field( (string) ( $row['initial_transaction_id'] ?? $row['transaction_id'] ?? '' ) );
		$payment_id = (int) ( $row['id'] ?? 0 );

		$base_item = array(
			'payment_id' => $payment_id,
			'order_id'   => $order_id,
			'action'     => 'skipped',
			'message'    => '',
		);

		if ( '' === $order_id ) {
			$base_item['message'] = __( 'Missing Razorpay order ID.', 'jet-form-builder-razorpay-gateway' );
			return $base_item;
		}

		try {
			$row = $this->load_payment_with_record( $payment_id, $row );
			Gateway_Context::prepare_controller_for_form( (int) ( $row['form_id'] ?? 0 ) );

			$paid_payment = $this->find_paid_payment_for_order( $api, $order_id, $row );

			if ( empty( $paid_payment ) ) {
				$base_item['message'] = __( 'No captured or authorized Razorpay payment found for this order.', 'jet-form-builder-razorpay-gateway' );
				return $base_item;
			}

			$fetched = $api->payment->fetch( $paid_payment['id'] );
			$details = method_exists( $fetched, 'toArray' ) ? $fetched->toArray() : array();

			Gateway_Context::assert_payment_matches_row( $details, $row, $order_id );

			$logic   = ( new Pay_Now_Logic() )->load_scenario_row( $row );
			$payload = Gateway_Context::enrich_payload_with_payment_details(
				array(
					'razorpay_order_id'          => $order_id,
					'razorpay_payment_id'        => sanitize_text_field( (string) ( $details['id'] ?? $paid_payment['id'] ) ),
					'razorpay_payment_status'    => sanitize_text_field( (string) ( $details['status'] ?? $paid_payment['status'] ) ),
					'razorpay_completion_source' => 'admin_reconciliation',
				),
				$details
			);
			$result = $logic->complete_verified_payment( $payload );

			if ( 'completed' === $result ) {
				$logic->init_request();
				$logic->init_actions();
				$logic->process_status( 'success' );
				jet_fb_action_handler()->set_current_action( false );
				do_action( 'jet-form-builder/gateways/before-send', 'success', false, $logic );

				$base_item['action']  = 'completed';
				$base_item['message'] = sprintf(
					/* translators: %s: Razorpay payment ID */
					__( 'Completed from Razorpay payment %s.', 'jet-form-builder-razorpay-gateway' ),
					sanitize_text_field( (string) ( $details['id'] ?? $paid_payment['id'] ) )
				);

				return $base_item;
			}

			$base_item['action']  = $result;
			$base_item['message'] = __( 'Payment did not need reconciliation.', 'jet-form-builder-razorpay-gateway' );

			return $base_item;
		} catch ( \Throwable $exception ) {
			if ( Gateway_Context::is_amount_mismatch_exception( $exception ) ) {
				Gateway_Context::persist_amount_mismatch( $row );
				$base_item['action']  = 'amount_mismatch';
				$base_item['message'] = $exception->getMessage();

				return $base_item;
			}

			error_log( '[JFB Razorpay] Reconciliation failed for order ' . $order_id . ': ' . $exception->getMessage() );

			$base_item['action']  = 'error';
			$base_item['message'] = $exception->getMessage();

			return $base_item;
		}
	}

	private function load_payment_with_record( int $payment_id, array $fallback ): array {
		if ( ! $payment_id ) {
			return $fallback;
		}

		try {
			$row = Payment_With_Record_View::findOne(
				array(
					'id' => $payment_id,
				)
			)->query()->query_one();

			return is_array( $row ) && ! empty( $row ) ? $row : $fallback;
		} catch ( Query_Builder_Exception $exception ) {
			return $fallback;
		}
	}

	/**
	 * @throws Gateway_Exception
	 */
	private function find_paid_payment_for_order( Api $api, string $order_id, array $row ): array {
		$collection = $api->order->fetch( $order_id )->payments();
		$data       = method_exists( $collection, 'toArray' ) ? $collection->toArray() : array();
		$items      = is_array( $data['items'] ?? null ) ? $data['items'] : array();
		$candidate          = array();
		$mismatch_exception = null;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$status = sanitize_text_field( (string) ( $item['status'] ?? '' ) );

			if ( ! in_array( $status, array( 'captured', 'authorized' ), true ) ) {
				continue;
			}

			try {
				Gateway_Context::assert_payment_matches_row( $item, $row, $order_id );
			} catch ( Gateway_Exception $exception ) {
				if ( Gateway_Context::is_amount_mismatch_exception( $exception ) ) {
					$mismatch_exception = $exception;
				}

				continue;
			}

			if ( 'captured' === $status ) {
				return $item;
			}

			$candidate = $item;
		}

		if ( empty( $candidate ) && $mismatch_exception ) {
			throw $mismatch_exception;
		}

		return $candidate;
	}

	private function result_key(): string {
		return self::RESULT_PREFIX . get_current_user_id();
	}
}
