<?php
/**
 *
 *  DigiWallet.nl
 * DigiWallet plugin for Opencart 2.0+
 *
 *  (C) Copyright TargetMedia B.V 2014
 *
 *  @file     DigiWallet Catalog Model
 *  @author TargetMedia B.V / https://digiwallet.nl
 *
 */

define('OC_VERSION', substr(VERSION, 0, 1));
class BaseDigiWalletModel extends \Opencart\System\Engine\Model
{
    /**
     * Get the payment method info
     * @param $address
     * @param $total
     * @param $payment_type
     * @param $img_code
     * @return array|bool
     */
    public function getMethodModel($address, $total, $payment_type, $img_code)
    {
        $setting_model_key = ($this->prefix_code) . ($payment_type);

        $this->load->language('extension/digiwallet/payment/' . $setting_model_key);
        $setting_name = (OC_VERSION == 2) ? '' : 'payment_';

        $checkTable = $this->db->query('show tables like "' . DB_PREFIX . 'digiwallet_'.$payment_type.'"');
        if (! $checkTable->num_rows) {
            return false;
        }
        $cart_total = $total;
        if($total == -1) {
			$cart_total = $this->cart->getTotal();
			if(empty($cart_total)) {
				$cart_total = $this->cart->getSubTotal();
			}
		}

        $status = true;
        if (!empty($cart_total) && $this->config->get($setting_name . $setting_model_key . '_total') > $cart_total) {
            $status = false;
        } elseif (! $this->config->get($setting_name . $setting_model_key . '_geo_zone_id')) {
            $status = true;
        } elseif(isset($address['country_id'])){
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get($setting_name . $setting_model_key . '_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
            $status = $query->num_rows;
        }
        if (!in_array(strtoupper($this->config->get('config_currency')), $this->currencies)) {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $option_data[$setting_model_key] = [
                'code' => "{$setting_model_key}.{$setting_model_key}",
                'name' => $this->language->get('text_title'),
                'terms' => '<img src="' . $this->config->get('config_ssl') . 'catalog/view/theme/default/image/digiwallet/'.$img_code.'.png" style="height:30px; display:inline; margin-left: 5px;">'
            ];
            $pm_title_label = "DigiWallet Payments";
            if($total == -1) {
                // Show to select
                $pm_css = "<script>$('.digi-payment-title').hide();$('.digi-payment-title:first').show();</script>";
                $pm_title_label = "<span class='digi-payment-title'>{$pm_title_label}</span>{$pm_css}";
            }
            $method_data = array(
                'code' => $setting_model_key,
                'title' => $this->language->get('text_title'),
                'name' => $pm_title_label,
                'sort_order' => $this->config->get($setting_name . $setting_model_key . '_sort_order'),
                'option'     => $option_data
            );
        }
        return $method_data;
    }
}
