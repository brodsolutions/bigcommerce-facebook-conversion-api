<?php
/* CONFIG */
require '/PATH/TO/vendor/autoload.php';
use GuzzleHttp\Client;

/* ORDER CREATED WEBHOOK */
$method = $_SERVER['REQUEST_METHOD'];
http_response_code(200); // Respond to webhook with OK 200, required by BigCommerce
$webhook = json_decode(file_get_contents('php://input'), true); // Decode JSON webhook response to an array
if (!$webhook) $webhook = array(); // Set webhook variables to array
$orderId = $webhook['data']['id']; // Get the Order ID
$orderStoreHash = str_replace("stores/", "", $webhook['producer']);  // Get the store hash 

/* SET VARIABLES */
// BigCommerce API
$bigcommerceBaseUri = 'https://api.bigcommerce.com/stores/';
$bigcommerceClientId = '<CLIENT ID>';
$bigcommerceAuthToken = '<AUTH TOKEN>';
// Facebook Business Manager
$facebookBaseUri = 'https://graph.facebook.com/v10.0/';
$facebookPixelId = '<PIXEL ID>';
$facebookAuthToken = '<ACCESS TOKEN>';

/* BIGCOMMERCE API GET ORDER */
// set the API Client for BigCommerce
$bigcommerceClient = new Client(
    [
        'base_uri' => 'https://api.bigcommerce.com/stores',
        'headers' => [
            'x-auth-client' => $bigcommerceClientId,
            'x-auth-token' => $bigcommerceAuthToken,
            'content-type' => 'application/json',
            'accept' => 'application/json'
        ]
    ]
);
$orderResponse = bigCommerceOrderAPIGet($bigcommerceClient, $bigcommerceBaseUri . $orderStoreHash . '/v2/orders/' . $orderId); // Query the Order
$order = json_decode($orderResponse->getBody(), TRUE);  // set JSON Order response to array
$productUrl = $order['products']['url']; // Get the Order Product URL
$productResponse = bigCommerceOrderAPIGet($bigcommerceClient, $productUrl); // Query the Products
$products = json_decode($productResponse->getBody(), TRUE); // set JSON Product response to array

/* FACEBOOK CONVERSION API */
// RULES TO INCLUDE OR EXCLUDE ORDERS (source is not manual/facebookshop and payment is captured or authorized)
if (($order['order_source'] !== 'manual' || $order['order_source'] == 'facebookshop') && ($order['payment_status'] == 'captured' || $order['payment_status'] == 'authorized')) {
    $conversionContents = [];
    // Create Facebook "Contents" for each Order Product
    foreach ($products as $product) {
        $conversionContents[] = array(
            "id" => $product['sku'],
            "item_price" => number_format($product['base_price'], 2),
            "quantity" => $product['quantity']
        );
    }
    // Facebook Payload
    $conversionOrder = array(
        "data" => [array(
            "event_name" => "Purchase",
            "event_time" => strtotime($order['date_created']), // set to order time in UTC
            "action_source" => "webhook",  // set to anything you want
            "user_data" => array(
                "client_ip_address" => $order['ip_address'],
                "client_user_agent" => 'alumilite-ua-webhook',
                "em" => hash('sha256', str_replace(" ", "", strtolower($order['billing_address']['email']))),  // optional, must hash be lowercase
                "ph" => hash('sha256', preg_replace('/\D/', '', $order['billing_address']['phone']))  // optional, must hash
            ),
            "custom_data" => array(
                "currency" => "usd",
                "order_id" => $order['id'],
                "value" => number_format($order['total_inc_tax'], 2),
                "num_items" => $order['items_total'],
                "content_type" => 'product',
                "contents" => $conversionContents, // optional Products
                "order_source" => $order['order_source'], // optional
                "payment_method" => $order['payment_method'], // optional
                "coupon" => number_format($order['coupon_discount'], 2), // optional
                "discount" => number_format($order['discount_amount'], 2), // optional
                "tax" => number_format($order['total_tax'], 2), // optional
                "shipping" => number_format($order['base_shipping_cost'], 2) // optional
            )
        )],
        "test_event_code" => "<TESTxxxxx>" // for Test Events only
    );
    // Create the Facebook Client
    $facebookClient = new Client(
        [
            'base_uri' => $facebookBaseUri,
            'headers' => ['access_token' => $facebookAuthToken, 'content-type' => 'application/json', 'accept' => 'application/json']
        ]
    );
    // Send the Payload to Facebook
    facebookConversionAPIPost($facebookClient, $facebookBaseUri . $facebookPixelId . '/events?access_token=' . $facebookAuthToken, $conversionOrder);
}

/* API GUZZLE CALLS, code exists upon error */
// GET BIGCOMMERCE ORDER DETAILS
function bigCommerceOrderAPIGet($client, $apiBase)
{
    try {
        $response = $client->request('GET', $apiBase);
    } catch (Client $e) {
        exit();
    }
    return $response;
}

// POST FACEBOOK CONVERSION API
function facebookConversionAPIPost($client, $apiBase, $conversionOrder)
{
    try {
        $response = $client->request('POST', $apiBase, ['json' => $conversionOrder]);
        return $response;
    } catch (Client $e) {
        exit();
    }
    return $response;
}
