<?php

/**
 *
 * DigiWallet.nl
 * DigiWallet plugin for Opencart 2.x, 3.x
 * Changelog: 20171120: apply for both 2.x 3.x
 *
 *  (C) Copyright TargetMedia B.V 2014
 *
 * @file        DigiWallet Admin Controller
 *
 */

define('OC_VERSION', substr(VERSION, 0, 1));

class DigiWalletAdmin extends \Opencart\System\Engine\Controller
{
    const DEFAULT_RTLO = 156187;
    const DEFAULT_API_TOKEN = 'bf72755a648832f48f0995454';

    // Default payment ordering
    public $payment_default_order = array(
        DigiWalletCore::METHOD_IDEAL => 1,
        DigiWalletCore::METHOD_MRCASH => 2,
        DigiWalletCore::METHOD_AFTERPAY => 3,
        DigiWalletCore::METHOD_BANKWIRE => 4,
        DigiWalletCore::METHOD_EPS => 5,
        DigiWalletCore::METHOD_GIRO => 6,
        DigiWalletCore::METHOD_PAYSAFE => 7,
        DigiWalletCore::METHOD_PAYPAL => 8,
        DigiWalletCore::METHOD_SOFORT => 9,
        DigiWalletCore::METHOD_CREDIT_CARD => 10
    );

    public function getMethodName()
    {
        return $this->methodName;
    }

    public function index()
    {
        $setting_model_key = ($this->prefix_code) . ($this->type);
        //Check Opencart version.
        $redirectLink = (OC_VERSION == 2) ? 'extension' : 'marketplace';
        $token = (OC_VERSION == 2) ? 'token' : 'user_token';
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';


        $this->load->language('extension/digiwallet/payment/' . $setting_model_key);

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $json = [];
            if($this->validate()) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->db->escape($setting_name . $setting_model_key) . "'");

                $this->model_setting_setting->editSetting($setting_name . $setting_model_key, $this->request->post);
                $json['success'] = $this->language->get('text_success');
            } else {
                $json['error'] = $this->language->get('error_permission');
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $data['type'] = $setting_model_key;   //20171120

            $data['heading_title'] = $this->language->get('heading_title');

            $data['text_enabled'] = $this->language->get('text_enabled');
            $data['text_disabled'] = $this->language->get('text_disabled');
            $data['text_edit'] = $this->language->get('text_edit');
            $data['text_all_zones'] = $this->language->get('text_all_zones');
            $data['text_yes'] = $this->language->get('text_yes');
            $data['text_no'] = $this->language->get('text_no');

            $data['entry_rtlo'] = $this->language->get('entry_rtlo');
            $data['entry_api_token'] = $this->language->get('entry_api_token');
            $data['entry_test'] = $this->language->get('entry_test');
            $data['entry_transaction'] = $this->language->get('entry_transaction');
            $data['entry_total'] = $this->language->get('entry_total');
            $data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
            $data['entry_status'] = $this->language->get('entry_status');
            $data['entry_sort_order'] = $this->language->get('entry_sort_order');

            $data['entry_canceled_status'] = $this->language->get('entry_canceled_status');
            $data['entry_pending_status'] = $this->language->get('entry_pending_status');

            $data['help_test'] = $this->language->get('help_test');
            $data['help_debug'] = $this->language->get('help_debug');
            $data['help_total'] = $this->language->get('help_total');

            $data['button_save'] = $this->language->get('button_save');
            $data['button_cancel'] = $this->language->get('button_cancel');

            $data['tab_general'] = $this->language->get('tab_general');
            $data['tab_status'] = $this->language->get('tab_status');

            if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
            } else {
                $data['error_warning'] = '';
            }

            if (isset($this->error['rtlo'])) {
                $data['error_rtlo'] = $this->error['rtlo'];
            } else {
                $data['error_rtlo'] = '';
            }

            if (isset($this->error['api_token'])) {
                $data['error_api_token'] = $this->error['api_token'];
            } else {
                $data['error_api_token'] = '';
            }

            $data['breadcrumbs'] = array();

            $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'),'href' => $this->url->link('common/dashboard', $token . '=' . $this->session->data[$token], 'SSL'));

            $data['breadcrumbs'][] = array('text' => $this->language->get('text_payment'),'href' => $this->url->link($redirectLink . '/extension', $token . '=' . $this->session->data[$token] . '&type=payment', 'SSL'));

            $data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'),'href' => $this->url->link('extension/digiwallet/payment/' . $setting_model_key, $token . '=' . $this->session->data[$token], 'SSL'));

            $data['action'] = $this->url->link('extension/digiwallet/payment/' . $setting_model_key, $token . '=' . $this->session->data[$token], 'SSL');

            $data['cancel'] = $this->url->link($redirectLink . '/extension', $token . '=' . $this->session->data[$token] . '&type=payment', 'SSL');

            if (isset($this->request->post[$setting_name . $setting_model_key . '_rtlo'])) {
                $data['payment_rtlo'] = $this->request->post[$setting_name . $setting_model_key . '_rtlo'];
            } else {
                $data['payment_rtlo'] = $this->config->get($setting_name . $setting_model_key . '_rtlo');
            }

            if (isset($this->request->post[$setting_name . $setting_model_key . '_api_token'])) {
                $data['payment_api_token'] = $this->request->post[$setting_name . $setting_model_key . '_api_token'];
            } else {
                $data['payment_api_token'] = $this->config->get($setting_name . $setting_model_key . '_api_token');
            }

            if (! isset($data['payment_rtlo'])) {
                $data['payment_rtlo'] = self::DEFAULT_RTLO; // Default DigiWallet
            }

            if (! isset($data['payment_api_token'])) {
                $data['payment_api_token'] = self::DEFAULT_API_TOKEN; // Default API DigiWallet
            }

            if (isset($this->request->post[$setting_name . $setting_model_key . '_total'])) {
                $data['payment_total'] = $this->request->post[$setting_name . $setting_model_key. '_total'];
            } else {
                $data['payment_total'] = $this->config->get($setting_name . $setting_model_key . '_total');
            }

            if (! isset($data['payment_total'])) {
                $data['payment_total'] = 2;
            }

            if (isset($this->request->post[$setting_name . $setting_model_key . '_pending_status_id'])) {
                $data['payment_pending_status_id'] = $this->request->post[$setting_name . $setting_model_key . '_pending_status_id'];
            } else {
                $data['payment_pending_status_id'] = $this->config->get($setting_name . $setting_model_key . '_pending_status_id');
            }

            // Bug fix for 2.0.0.0 ... everything defaults to canceled, not user friendly

            if (is_null($data['payment_pending_status_id'])) {
                $data['payment_pending_status_id'] = 1;
            }

            $this->load->model('localisation/order_status');

            $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

            if (isset($this->request->post[$setting_name . $setting_model_key . '_geo_zone_id'])) {
                $data['payment_geo_zone_id'] = $this->request->post[$setting_name . $setting_model_key . '_geo_zone_id'];
            } else {
                $data['payment_geo_zone_id'] = $this->config->get($setting_name . $setting_model_key . '_geo_zone_id');
            }

            $this->load->model('localisation/geo_zone');

            $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

            if (isset($this->request->post[$setting_name . $setting_model_key . '_status'])) {
                $data['payment_status'] = $this->request->post[$setting_name . $setting_model_key . '_status'];
            } else {
                $data['payment_status'] = $this->config->get($setting_name . $setting_model_key . '_status');
            }

            if (isset($this->request->post[$setting_name . $setting_model_key . '_sort_order'])) {
                $data['payment_sort_order'] = $this->request->post[$setting_name . $setting_model_key . '_sort_order'];
            } else {
                $data['payment_sort_order'] = $this->config->get($setting_name . $setting_model_key . '_sort_order');
            }

            if (! isset($data['payment_sort_order'])) {
                $data['payment_sort_order'] = $this->payment_default_order[$this->type];
            }

            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            //render admin general template, use for both 2.x, 3.x
            // 2.x use tpl, 3.x use twig
            $this->response->setOutput($this->load->view('extension/digiwallet/payment/digiwallet', $data));
        }
    }

    private function validate()
    {
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        $setting_model_key = ($this->prefix_code) . ($this->type);
        if (! $this->user->hasPermission('modify', 'extension/digiwallet/payment/' . $setting_model_key)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post[$setting_name . $setting_model_key . '_rtlo'])  && empty($this->request->post[$setting_name . $setting_model_key . '_rtlo'])) {
            $this->error['rtlo'] = $this->language->get('error_rtlo');
        }
        if (! $this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function install()
    {
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';
        $setting_model_key = ($this->prefix_code) . ($this->type);

        $this->db->query(\DigiWalletCore::getCreateTableQuery($this));

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($setting_name . $setting_model_key, array($this->type . '_status' => 1));
    }
}