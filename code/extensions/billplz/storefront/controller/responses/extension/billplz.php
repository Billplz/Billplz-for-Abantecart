<?php
/* ------------------------------------------------------------------------------
 *
 * Billplz
 *
 * Author: Billplz Sdn. Bhd.
 * Version: 3.1.2
 *
------------------------------------------------------------------------------ */

if (!defined('DIR_CORE')) {
    header('Location: static_pages/');
}

/**
 * @property ModelExtensionBillplz $model_extension_billplz
 * @property ModelCheckoutOrder $model_checkout_order
 */
class ControllerResponsesExtensionBillplz extends AController
{
    public $data = array();

    public function main()
    {
        $this->loadLanguage('billplz/billplz');

        if ($this->request->get['rt'] == 'checkout/guest_step_3') {
            $back_url = $this->html->getSecureURL('checkout/guest_step_2', '&mode=edit', true);
        } else {
            $back_url = $this->html->getSecureURL('checkout/payment', '&mode=edit', true);
        }

        $this->data['text_skip_bill_page'] = $this->language->get('skip_bill_page');

        $this->data['button_back'] = $this->html->buildElement(
            array(
                'type' => 'button',
                'name' => 'back',
                'text' => $this->language->get('button_back'),
                'href' => $back_url,
            ));

        $skip_bill_page = $this->config->get('billplz_skip_bill_page') == 'true';

        if ($skip_bill_page) {

            /* This class required for getting list of banks */
            require 'billplz_bankname.php';

            $form = new AForm();
            $form->setForm(array('form_name' => 'billplz'));
            $this->data['form_open'] = $form->getFieldHtml(
                array(
                    'type' => 'form',
                    'name' => 'billplz',
                    'attr' => 'class = "form-horizontal"',
                    'csrf' => true,
                    'action' => $this->html->getSecureURL('r/extension/billplz/confirm'),
                )
            );
            $this->data['billplz_skip_bill_page'] = $form->getFieldHtml(
                array(
                    'type' => 'selectbox',
                    'name' => 'bank_code',
                    'value' => '',
                    'options' => BillplzBankname::get(),
                    'style' => 'input-medium',
                )
            );
            $this->data['button_confirm'] = $this->html->buildElement(
                array(
                    'type' => 'submit',
                    'name' => $this->language->get('button_confirm'),
                    'style' => 'button',
                )
            );
            $this->view->batchAssign($this->data);
            $this->processTemplate('responses/billplz_skip_bill_page.tpl');
        } else {

            $this->data['button_confirm'] = $this->html->buildElement(
                array(
                    'type' => 'submit',
                    'name' => $this->language->get('button_confirm'),
                    'style' => 'button',
                    'href' => $this->html->getSecureURL('r/extension/billplz/confirm',
                        '&csrfinstance=' . $this->csrftoken->setInstance()
                        . '&csrftoken=' . $this->csrftoken->setToken()),
                )
            );
            $this->view->batchAssign($this->data);
            $this->processTemplate('responses/billplz.tpl');
        }
    }

    public function confirm()
    {
        if (!$this->csrftoken->isTokenValid()) {
            exit('Forbidden: invalid csrf-token');
        }

        $skip_bill_page = $this->config->get('billplz_skip_bill_page') == 'true';
        $skip_bill_page = $this->request->is_POST() && $skip_bill_page && $_POST['bank_code'];

        if ($skip_bill_page) {
            /* This class required for getting list of banks */
            require 'billplz_bankname.php';

            $bank_name = BillplzBankName::get();
            $bank_code = isset($bank_name[$_POST['bank_code']]) ? $_POST['bank_code'] : '';
        }

        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];

        $order_info = $this->model_checkout_order->getOrder($order_id);

        $description = '';
        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $description .= $product['name'] . ' ' . $product['quantity'] . ' ';
        }

        $total_amount = $this->currency->format($order_info['total'], $order_info['currency'], $order_info['value'], false);

        $billplz_charges = $this->config->get('billplz_charges');

        if ($order_info['payment_lastname']) {
            $name = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        } else if ($order_info['shipping_lastname']) {
            $name = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
        } else {
            $name = $order_info['firstname'] . ' ' . $order_info['lastname'];
        }

        if ($order_info['currency'] === 'MYR') {
            $final_amount = $total_amount + $billplz_charges;
        } else {
            $final_amount = $this->model_checkout_order->currency->convert($total_amount + $billplz_charges, $order_info['currency'], 'MYR');
        }

        $parameter = array(
            'collection_id' => $this->config->get('billplz_collection_id'),
            'email' => $order_info['email'],
            'mobile' => trim($order_info['telephone']),
            'name' => empty($name) ? $order_info['email'] : $name,
            'amount' => $final_amount * 100,
            'callback_url' => $this->html->getSecureURL('extension/billplz/callback_url', '&order_id=' . $order_id),
            'description' => mb_substr("Order " . $order_id . " - " . $description, 0, 199),
        );

        $optional = array(
            'redirect_url' => $this->html->getSecureURL('extension/billplz/redirect_url', '&order_id=' . $order_id),
            'reference_1_label' => $skip_bill_page ? 'Bank Code' : '',
            'reference_1' => isset($bank_code) ? $bank_code : '',
            'reference_2_label' => 'Order ID',
            'reference_2' => $order_id,
        );

        $is_sandbox = $this->config->get('billplz_env') == 'sandbox' ? true : false;

        /* This class required for creating a bill */
        require 'billplz_api.php';
        require 'billplz_connect.php';

        try {
            $connect = new BillplzConnect($this->config->get('billplz_api_key'));
            $connect->setStaging($is_sandbox);

            $billplz = new BillplzAPI($connect);
            list($rheader, $rbody) = $billplz->toArray($billplz->createBill($parameter, $optional));
        } catch (Exception $e) {
            exit($e->getMessage());
        }

        if ($rheader !== 200) {
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Failed');
            ADebug::error("Billplz_Create_A_Bill", $rheader, json_encode($rbody));
            $this->model_checkout_order->confirm($order_id, $order_status_id, json_encode($rbody));
        } else {
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Pending');
            $order_message = 'Bill ID: ' . $rbody['id'] . '. Bill URL: ' . $rbody['url'];
            $this->model_checkout_order->updatePaymentMethodData($order_id,
                serialize($rbody));
            $this->model_checkout_order->confirm($order_id, $order_status_id, $order_message);
            redirect($rbody['url'] . ($skip_bill_page ? '?auto_submit=true' : ''));
        }

    }

    public function redirect_url()
    {
        if (!$this->request->is_GET()) {
            exit;
        }

        /* This class required for checking a bill */
        require 'billplz_api.php';
        require 'billplz_connect.php';

        try {
            $data = BillplzConnect::getXSignature($this->config->get('billplz_x_signature'));
        } catch (Exception $e) {
            status_header(403);
            exit($e->getMessage());
        }

        $connect = new BillplzConnect($this->config->get('billplz_api_key'));
        $is_sandbox = $this->config->get('billplz_env') == 'sandbox' ? true : false;
        $connect->setStaging($is_sandbox);

        $billplz = new BillplzAPI($connect);
        list($rheader, $rbody) = $billplz->toArray($billplz->getBill($data['id']));

        if ($rbody['reference_2'] != $_GET['order_id']) {
            exit('Invalid Order ID');
        }

        $order_id = (int) $rbody['reference_2'];

        $this->load->model('checkout/order');
        $this->load->model('extension/billplz');

        $order_info = $this->model_checkout_order->getOrder($order_id);
        $old_rbody = unserialize($order_info['payment_method_data']);
        if ($old_rbody['id'] != $data['id']) {
            exit('Invalid Bill ID');
        }

        if (!$this->customer->isLogged()) {
            $this->session->data['guest']['firstname'] = $order_info['payment_firstname'];
            $this->session->data['guest']['lastname'] = $order_info['payment_lastname'];
            $this->session->data['guest']['email'] = $order_info['email'];
            $this->session->data['guest']['address_1'] = $order_info['payment_address_1'];
            $this->session->data['guest']['address_2'] = has_value($order_info['payment_address_2']) ? $order_info['payment_address_2'] : '';
            $this->session->data['guest']['postcode'] = $order_info['payment_postcode'];
            $this->session->data['guest']['city'] = $order_info['payment_city'];
            $this->session->data['guest']['country'] = $order_info['payment_country'];
            $this->session->data['guest']['zone'] = $order_info['payment_zone'];
        }

        $billplz_order_status_id = $this->config->get('billplz_order_status_id');
        $notify_customer = $billplz_order_status_id != $order_info['order_status_id'];
        $order_message = '(Redirect) Bill ID: ' . $rbody['id'] . '. Bill URL: ' . $rbody['url'] . '. State: ' . $rbody['state'];

        if ($rbody['paid']) {
            $this->model_checkout_order->update($order_id, $this->config->get('billplz_order_status_id'), $order_message, $notify_customer);
            if ($this->customer->isLogged()) {
                $this->redirect($this->html->getSecureURL('checkout/confirm'));
            } else {
                $this->redirect($this->html->getSecureURL('checkout/success'));
            }
        } else {
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Pending');
            $this->model_checkout_order->update($order_id, $order_status_id, $order_message);
            $this->redirect($this->html->getSecureURL('checkout/cart'));
        }
    }

    public function callback_url()
    {
        if (!$this->request->is_POST()) {
            $this->redirect($this->html->getURL('index/home'));
        }

        /* This class required for checking a bill */
        require 'billplz_api.php';
        require 'billplz_connect.php';

        try {
            $data = BillplzConnect::getXSignature($this->config->get('billplz_x_signature'));
        } catch (Exception $e) {
            status_header(403);
            exit($e->getMessage());
        }

        $connect = new BillplzConnect($this->config->get('billplz_api_key'));
        $is_sandbox = $this->config->get('billplz_env') == 'sandbox' ? true : false;
        $connect->setStaging($is_sandbox);

        $billplz = new BillplzAPI($connect);
        list($rheader, $rbody) = $billplz->toArray($billplz->getBill($data['id']));

        if ($rbody['reference_2'] != $_GET['order_id']) {
            status_header(403);
            throw new \Exception('Invalid Order ID');
        }

        $order_id = (int) $rbody['reference_2'];

        $this->load->model('checkout/order');
        $this->load->model('extension/billplz');

        $order_info = $this->model_checkout_order->getOrder($order_id);

        $old_rbody = unserialize($order_info['payment_method_data']);
        if ($old_rbody['id'] != $data['id']) {
            exit('Invalid Bill ID');
        }

        $billplz_order_status_id = $this->config->get('billplz_order_status_id');
        $notify_customer = $billplz_order_status_id != $order_info['order_status_id'];
        $order_message = '(Callback) Bill ID: ' . $rbody['id'] . '. Bill URL: ' . $rbody['url'] . '. State: ' . $rbody['state'];

        if ($rbody['paid']) {
            $this->model_checkout_order->update($order_id, $billplz_order_status_id, $order_message, $notify_customer);
        } else {
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Failed');
            $this->model_checkout_order->confirm($order_id, $order_status_id, $order_message);
        }
    }

}
