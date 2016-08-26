<?php

/* ------------------------------------------------------------------------------
  Billplz - Malaysia Online Payment Gateway
  http://www.github.com/wzul
 * 
 * Fair & Simple Payment Gateway
 * 
 * Author: Wanzul Hosting Enterprise
 * Version: 1.0

  ------------------------------------------------------------------------------ */

if (!defined('DIR_CORE')) {
    header('Location: static_pages/');
}

/**
 * @property ModelExtensionBillplz $model_extension_billplz
 * @property ModelCheckoutOrder $model_checkout_order
 */
class ControllerResponsesExtensionBillplz extends AController {

    public function main() {
        $this->loadLanguage('billplz/billplz');
        $template_data['button_confirm'] = $this->language->get('button_confirm');
        $template_data['button_back'] = $this->language->get('button_back');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $merchant_id = $this->config->get('billplz_account');
        $merchant_verify_key = $this->config->get('billplz_secret');
        $template_data['autosubmit'] = $this->config->get('billplz_auto_submit');
        $billplz_charges = $this->config->get('billplz_charges');

        $total_amount = $this->currency->format($order_info['total'], $order_info['currency'], $order_info['value'], FALSE);

        $template_data['order_number'] = $this->session->data['order_id'];
        //$template_data['card_holder_name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        $template_data['deliver'] = $this->config->get('billplz_deliver');
        $deliver = $template_data['deliver'] == 'true' ? true : false;
        if ($order_info['shipping_lastname']) {
            $name = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
        } else {
            $name = $order_info['firstname'] . ' ' . $order_info['lastname'];
        }

        $template_data['products'] = '';

        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $template_data['products'] = $product['name'] . ' ' . $product['quantity'] . ' ';
        }

        $template_data['callback_url'] = $this->html->getSecureURL('extension/billplz/capture');
        $template_data['return_url'] = $this->html->getSecureURL('extension/billplz/returnback');

        //number intelligence
        $custTel = $order_info['telephone'];
        $custTel2 = substr($order_info['telephone'], 0, 1);
        if ($custTel2 == '+') {
            $custTel3 = substr($order_info['telephone'], 1, 1);
            if ($custTel3 != '6')
                $custTel = "+6" . $order_info['telephone'];
        } else if ($custTel2 == '6') {
            
        } else {
            if ($custTel != '')
                $custTel = "+6" . $order_info['telephone'];
        }
        //number intelligence

        $billplz_data = array(
            'amount' => ($total_amount + $billplz_charges) * 100,
            'name' => $name,
            'email' => $order_info['email'],
            'mobile' => $custTel,
            'collection_id' => $merchant_verify_key,
            'deliver' => $deliver,
            'description' => substr("Order " . $template_data['order_number'] . " - " . $template_data['products'], 0, 199),
            'reference_1_label' => 'ID',
            'reference_1' => $this->session->data['order_id'],
            'redirect_url' => $template_data['return_url'],
            'callback_url' => $template_data['callback_url'],
        );

        $process = curl_init($this->config->get('billplz_sandbox') . 'bills/');
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_USERPWD, $merchant_id . ":");
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
        $return = curl_exec($process);
        curl_close($process);
        $arr = json_decode($return, true);
        $template_data['action'] = isset($arr['url']) ? $arr['url'] : null;

        if (isset($arr['error'])) {
            unset($billplz_data['mobile']);
            $process = curl_init($this->config->get('billplz_sandbox') . 'bills/');
            curl_setopt($process, CURLOPT_HEADER, 0);
            curl_setopt($process, CURLOPT_USERPWD, $merchant_id . ":");
            curl_setopt($process, CURLOPT_TIMEOUT, 30);
            curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
            $return = curl_exec($process);
            curl_close($process);
            $arr = json_decode($return, true);
            $template_data['action'] = isset($arr['url']) ? $arr['url'] : "http://facebook.com/billplzplugin";
        }

        $this->view->batchAssign($template_data);
        $this->processTemplate('responses/billplz.tpl');
    }

    public function capture() {
        if ($this->request->is_GET()) {
            $this->redirect($this->html->getURL('index/home'));
        }

        $post = $this->request->post;

        $verification2 = $post['id'];
        $merchant_id = $this->config->get('billplz_account');

        $process = curl_init($this->config->get('billplz_sandbox') . "bills/" . $verification2);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_USERPWD, $merchant_id . ":");
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        $return = curl_exec($process);
        curl_close($process);
        $arra = json_decode($return, true);
        $orderid = $arra['reference_1'];
        if ($arra['collection_id'] != $post['collection_id']) {
            exit;
        }

        $this->load->model('checkout/order');
        $this->load->model('extension/billplz');



        if ($arra['paid']) { // Success
            $this->model_checkout_order->confirm((int) $orderid, $this->config->get('billplz_order_status_id'));
        } else { // Failed
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Failed');
            $this->model_checkout_order->update((int) $orderid, $order_status_id);
        }
    }

    public function returnback() {

        $get = $this->request->get;

        $verification2 = $get['billplz']['id'];
        $merchant_id = $this->config->get('billplz_account');

        $process = curl_init($this->config->get('billplz_sandbox') . "bills/" . $verification2);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_USERPWD, $merchant_id . ":");
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        $return = curl_exec($process);
        curl_close($process);
        $arra = json_decode($return, true);

        $orderid = $arra['reference_1'];
        $this->load->model('checkout/order');
        $this->load->model('extension/billplz');
        if (!$this->customer->isLogged()) {
            // get order info
            $order_info = $this->model_checkout_order->getOrder($orderid);

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

        if ($arra['paid']) { // Success
            if ($this->customer->isLogged()) {
                $this->model_checkout_order->confirm((int) $orderid, $this->config->get('billplz_order_status_id'));
                $this->redirect($this->html->getSecureURL('checkout/confirm'));
            } else {
                $this->redirect($this->html->getSecureURL('checkout/success'));
            }
        } else { // Failed
            $order_status_id = $this->model_extension_billplz->getOrderStatusIdByName('Failed');
            $this->model_checkout_order->update((int) $orderid, $order_status_id);
            $this->redirect($this->html->getSecureURL('checkout/cart'));
        }
    }

}
