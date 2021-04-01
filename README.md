# bigcommerce-facebook-conversion-api
Want to send **[Facebook Conversion API](https://developers.facebook.com/docs/marketing-api/conversions-api/set-up-conversions-api-as-a-platform)** Purchase events from your BigCommerce Store?

Utilizing the Order Creation Webhook, send the newly created Order ID to the PHP file. Query the Order with Products, set the Facebook purchase event and POST a Purchase event.

## Composer Requirements
`composer require guzzlehttp/guzzle`

## Set your Variables
### BigCommerce
Create an API with Order (Read) scope
- x-auth-client = Client ID
- x-auth-token = Auth Token

### Facebook Business Manager
Generate a Access Token via **[Event Manager](https://developers.facebook.com/docs/marketing-api/conversions-api/get-started#via-events-manager)**
- facebook-auth-token = Facebook Auth Token
- pixel-id = Business Pixel ID

## Update PHP File
Set your variable from above.
Alter your **[Facebook Payload](https://developers.facebook.com/docs/marketing-api/conversions-api/payload-helper/)** if desired ($conversionOrder).

*Recommend setting the 'test_event_code' to check it's working, remove for production.

## Save your PHP to a Website
This is your destination to run the server program.
### Testing
Before creating the webhook, you can test by POST this JSON Body to your destination url.

`{
    "data": { "id":bc-order-id },
    "producer": "stores/bc-store-hash"
}
`

## Create your BigCommerce Webhook
Send a POST to https://api.bigcommerce.com/stores/{store-hash}/v2/hooks with the following JSON Body:

`{
  "scope": "store/order/created",
  "destination": "https://domain.com/fb-conv.php",
  "is_active": true
}`
