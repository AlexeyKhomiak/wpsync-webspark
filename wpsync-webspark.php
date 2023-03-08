<?php

/**
 * Plugin Name: wpsync-webspark
 * Description: Этот супер-плагин позволяет cинхронизировать базу товаров с остатками c ресурсом https://wp.webspark.dev/wp-api/products
 * Version: 1.0.0
 * Author: Aleksey Khomiak
 * Author URI: https://github.com/AlexeyKhomiak?tab=repositories* 
 * Developer: Aleksey Khomiak
 * Developer URI: https://github.com/AlexeyKhomiak?tab=repositories
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
require_once ABSPATH . 'wp-admin/includes/media.php';

defined('ABSPATH') || exit;

if (!function_exists('is_woocommerce_activated')) {
    function is_woocommerce_activated()
    {
        if (class_exists('woocommerce')) {
            return true;
        } else {
            return false;
        }
    }
}

if (is_woocommerce_activated()) {
    function wpsync_webspark_sync()
    {
        if (is_user_logged_in()) {

            $products = get_transient('wpsync_webspark_products');

            if (empty($products)) {
                $url = 'https://wp.webspark.dev/wp-api/products';
                $params = array(
                    'timeout' => 20,
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                );
                $response = wp_remote_get($url, $params);
                $responseArr = json_decode($response['body'], false);

                if ($responseArr->error == false && !is_wp_error($response)) {
                    $products = $responseArr->data;
                    set_transient('wpsync_webspark_products', $products, HOUR_IN_SECONDS);
                } else {
                    error_log($responseArr->message);
                    return;
                }
            }

            foreach ($products as $product) {

                $sku = $product->sku;
                $name = $product->name;
                $description = $product->description;
                $price = $product->price;
                $picture = $product->picture;
                $in_stock = $product->in_stock;

                $product_id = wc_get_product_id_by_sku($sku);

                if ($product_id) {
                    $productUpdate = wc_get_product($product_id);
                    $productUpdate->set_name($name);
                    $productUpdate->set_regular_price($price);
                    $productUpdate->set_short_description($description);
                    $productUpdate->set_stock_quantity($in_stock);
                    $productUpdate->save();
                } else {
                    $productNew = new WC_Product_Simple();
                    $productNew->set_name($name);
                    $productNew->set_regular_price($price);
                    $productNew->set_short_description($description);
                    $productNew->set_stock_quantity($in_stock);
                    $productNew->set_sku($sku);
                    $productNew->save();
                }
            }
            //delete_transient('wpsync_webspark_products');
        }
    }

    function wpsync_webspark_set_image_as_featured($product_id, $image_url)
    {
        $image_id = null;
        $image_name = basename($image_url);
        $image_data = wp_remote_get($image_url);
        if (is_array($image_data) && !is_wp_error($image_data)) {
            $image_id = media_handle_sideload(array(
                'name'     => $image_name,
                'tmp_name' => $image_data['file'],
            ), $product_id);
        }
        return $image_id;
    }

    add_action('init', 'wpsync_webspark_sync');
}
