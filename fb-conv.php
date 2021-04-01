<?php
/* CONFIG */
date_default_timezone_set('UTC');
// Composer
require '/PATH/TO/vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\ClientException;

/* ORDER CREATED WEBHOOK */
$method = $_SERVER['REQUEST_METHOD'];
http_response_code(200); // Respond to webhook with OK 200, required by BigCommerce
$webhook = json_decode(file_get_contents('php://input'), true); // Decode JSON webhook response to an array
if (!$webhook) $webhook = array(); // Set webhook variables to array
$orderID = $webhook['data']['id']; // Get the Order ID
$orderStoreHash = str_replace("stores/", "", $webhook['producer']);  // Get the store hash

/* BIGCOMMERCE API GET ORDER */
function letsGO($config, $apiConfig, $apiName, $orderID)
{
    // set the API header & client
    $headers = [
      'x-auth-client' => CLIENT ID, 
      'x-auth-token' => AUTH TOKEN, 
      'content-type' => 'application/json', 
      'accept' => 'application/json'];
    $client = new Client(['base_uri' => 'https://api.bigcommerce.com/stores', 'headers' => isset($headers) ? $headers : []]);
    // retrieve response from API call
    $orderResponse = bigCommerceOrderAPIGet($client, 'https://api.bigcommerce.com/stores/' . $orderStoreHash . '/v2/orders/' . $orderID); // Query the Order
    if ($orderResponse !== "error") {
        $order = json_decode($orderResponse->getBody(), TRUE);  // set JSON Order response to array
        $productUrl = $order['products']['url']; // Get the Order Product URL
        $productResponse = bigCommerceOrderAPIGet($client, $productUrl); // Query the Products
        $product = json_decode($productResponse->getBody(), TRUE); // set JSON Product response to array
      /* FACEBOOK CONVERSION API */
        // RULES TO INCLUDE OR EXCLUDE ORDERS
        if (
                $order['order_source'] !== 'manual' &&
                ($order['payment_status'] == 'captured' ||
                    $order['payment_status'] == 'authorized' ||
                    $order['order_source'] == 'facebookshop')
            ) {
                $conversionContents = [];
    foreach ($orderProducts as $orderProduct) {
        $conversionContents[] = array(
            "id" => $orderProduct['sku'],
            "item_price" => number_format($orderProduct['base_price'], 2),
            "quantity" => $orderProduct['quantity']
        );
    }
    $conversionOrder = array(
        "data" => [array(
            "event_name" => "Purchase",
            "event_time" => strtotime($order['date_created']),
            "action_source" => "webhook",
            "user_data" => array(
                "client_ip_address" => $order['ip_address'],
                "client_user_agent" => $apiConfig['facebook_user_agent'],
                "em"=>hash('sha256',str_replace(" ","",strtolower($order['billing_address']['email']))),
                "ph"=>hash('sha256',preg_replace('/\D/', '', $order['billing_address']['phone']))
            ),
            "custom_data" => array(
                "currency" => "usd",
                "order_id" => $order['id'],
                "value" => number_format($order['total_inc_tax'], 2),
                "num_items" => $order['items_total'],
                "content_type" => 'product',
                "contents" => $conversionContents,
                "order_source" => $order['order_source'],
                "payment_method" => $order['payment_method'],
                "coupon" => number_format($order['coupon_discount'], 2),
                "discount" => number_format($order['discount_amount'], 2),
                "tax" => number_format($order['total_tax'], 2),
                "shipping" => number_format($order['base_shipping_cost'], 2)
            )
        )],
        // USE FOR TEST EVENTS ON FACEBOOK PIXEL
        "test_event_code" => "TEST18241"
    );
    $facebookHeaders = ['access_token' => $apiConfig['facebook_auth_token'], 'content-type' => 'application/json', 'accept' => 'application/json'];
    $facebookClient = new Client(['base_uri' => $apiConfig['facebook_base_uri'], 'headers' => isset($facebookHeaders) ? $facebookHeaders : []]); // create the client
    $facebookAPIBase = $apiConfig['facebook_api_base'] . $apiConfig['facebook_auth_token'];
    facebookConversionAPIPost($facebookClient, $facebookAPIBase, $conversionOrder);
            }
        }
    }
}

// GUZZLE GET BIGCOMMERCE ORDER DETAILS
function bigCommerceOrderAPIGet($client, $apiBase)
{
    try {
        $response = $client->request('GET', $apiBase);
    } catch (ClientException $e) {
        //$response = $e->getResponse(); // error checking
        $response = 'error';
    }
  return $response
}

// GUZZLE POST FACEBOOK CONVERSION API
function facebookConversionAPIPost(&$client, $apiBase, $conversionOrder)
{
    try {
        $response = $client->request('POST', $apiBase, ['json' => $conversionOrder]);
        return $response;
    } catch (ClientException $e) {
        $response = $e->getResponse();
    }
  return $response
}
