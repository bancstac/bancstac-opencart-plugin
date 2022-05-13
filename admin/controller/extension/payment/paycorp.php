<?php
class ControllerExtensionPaymentPaycorp extends Controller {
	private $error = array();

	protected $options = array(
		'payment_paycorp_pg_domain',
		'payment_paycorp_client_id',
		'payment_paycorp_hmac_secret',
		'payment_paycorp_auth_token',
		'payment_paycorp_transaction_type',
		'payment_paycorp_total',
		'payment_paycorp_order_status_id',
		'payment_paycorp_geo_zone_id',
		'payment_paycorp_debug',
		'payment_paycorp_status',
	);

	public function index() {
		$this->load->language('extension/payment/paycorp');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/order_status');
		$this->load->model('localisation/geo_zone');

		if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_paycorp', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		// Errors
		$errors = array(
			'error_pg_domain',
			'error_client_id',
			'error_hmac_secret',
			'error_auth_token'
		);
		foreach ($errors as $error) {
			$data[$error] = isset($this->error[$error]) ? $this->error[$error] : '';
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/paycorp', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/paycorp', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		// Load options
		foreach ($this->options as $option) {
			if (isset($this->request->post[$option])) {
				$data[$option] = $this->request->post[$option];
			} else {
				$data[$option] = $this->config->get($option);
			}
		}

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/paycorp', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/paycorp')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_paycorp_pg_domain']) {
			$this->error['error_pg_domain'] = $this->language->get('error_pg_domain');
		}

		if (!$this->request->post['payment_paycorp_client_id']) {
			$this->error['error_client_id'] = $this->language->get('error_client_id');
		}

		if (!$this->request->post['payment_paycorp_hmac_secret']) {
			$this->error['error_hmac_secret'] = $this->language->get('error_hmac_secret');
		}

		if (!$this->request->post['payment_paycorp_auth_token']) {
			$this->error['error_auth_token'] = $this->language->get('error_auth_token');
		}

		return !$this->error;
	}

	public function install() {
		//$this->load->model('extension/payment/paycorp');
		//$this->model_extension_payment_paycorp->install();
	}

	public function uninstall() {
		//$this->load->model('extension/payment/paycorp');
		//$this->model_extension_payment_paycorp->uninstall();
	}

	/* public function order() {

		if ($this->config->get('paycorp_status')) {

			$this->load->model('extension/payment/paycorp');

			$paycorp_order = $this->model_extension_payment_paycorp->getOrder($this->request->get['order_id']);

			if (!empty($paycorp_order)) {
				$this->load->language('extension/payment/paycorp');

				$paycorp_order['total_released'] = $this->model_extension_payment_paycorp->getTotalReleased($paycorp_order['paycorp_order_id']);

				$paycorp_order['total_formatted'] = $this->currency->format($paycorp_order['total'], $paycorp_order['currency_code'], false);
				$paycorp_order['total_released_formatted'] = $this->currency->format($paycorp_order['total_released'], $paycorp_order['currency_code'], false);

				$data['paycorp_order'] = $paycorp_order;

				$data['order_id'] = $this->request->get['order_id'];
				
				$data['user_token'] = $this->request->get['user_token'];

				return $this->load->view('extension/payment/paycorp_order', $data);
			}
		}
	}

	public function refund() {
		$this->load->language('extension/payment/paycorp');
		$json = array();

		if (isset($this->request->post['order_id']) && !empty($this->request->post['order_id'])) {
			$this->load->model('extension/payment/paycorp');

			$paycorp_order = $this->model_extension_payment_paycorp->getOrder($this->request->post['order_id']);

			$refund_response = $this->model_extension_payment_paycorp->refund($this->request->post['order_id'], $this->request->post['amount']);

			$this->model_extension_payment_paycorp->logger('Refund result: ' . print_r($refund_response, 1));

			if ($refund_response['status'] == 'success') {
				$this->model_extension_payment_paycorp->addTransaction($paycorp_order['paycorp_order_id'], 'refund', $this->request->post['amount'] * -1);

				$total_refunded = $this->model_extension_payment_paycorp->getTotalRefunded($paycorp_order['paycorp_order_id']);
				$total_released = $this->model_extension_payment_paycorp->getTotalReleased($paycorp_order['paycorp_order_id']);

				$this->model_extension_payment_paycorp->updateRefundStatus($paycorp_order['paycorp_order_id'], 1);

				$json['msg'] = $this->language->get('text_refund_ok_order');
				$json['data'] = array();
				$json['data']['created'] = date("Y-m-d H:i:s");
				$json['data']['amount'] = $this->currency->format(($this->request->post['amount'] * -1), $paycorp_order['currency_code'], false);
				$json['data']['total_released'] = $this->currency->format($total_released, $paycorp_order['currency_code'], false);
				$json['data']['total_refund'] = $this->currency->format($total_refunded, $paycorp_order['currency_code'], false);
				$json['data']['refund_status'] = 1;
				$json['error'] = false;
			} else {
				$json['error'] = true;
				$json['msg'] = isset($refund_response['message']) && !empty($refund_response['message']) ? (string)$refund_response['message'] : 'Unable to refund';
			}
		} else {
			$json['error'] = true;
			$json['msg'] = 'Missing data';
		}

		$this->response->setOutput(json_encode($json));
	} */


}
