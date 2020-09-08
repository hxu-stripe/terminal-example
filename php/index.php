<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Stripe\Stripe;

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

require './config.php';

if (PHP_SAPI == 'cli-server') {
  $_SERVER['SCRIPT_NAME'] = '/index.php';
}

error_reporting(E_ERROR | E_WARNING);
$app = new \Slim\App;

// Instantiate the logger as a dependency
$container = $app->getContainer();
$container['logger'] = function ($c) {
  $settings = $c->get('settings')['logger'];
  $logger = new Monolog\Logger($settings['name']);
  $logger->pushProcessor(new Monolog\Processor\UidProcessor());
  $logger->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/logs/app.log', \Monolog\Logger::DEBUG));
  return $logger;
};

$app->add(function ($request, $response, $next) {
    Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    return $next($request, $response)
    // Allow CORS requests for localhost.
    // Do not set this to the wildcard in production, since it is insecure
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

/***
* Discovering readers
***/
$app->post('/connection-token', function (Request $request, Response $response, array $args) {
    // Step 0-2
    $connectionToken = \Stripe\Terminal\ConnectionToken::create();
    $logger = $this->get('logger');
    $logger->info($connectionToken);
    return $response->withJson(array('secret' => $connectionToken->secret));
});


/***
* Payment
***/

$app->post('/create-payment-intent', function (Request $request, Response $response, array $args) {
    $pub_key = getenv('STRIPE_PUBLISHABLE_KEY');
    $body = json_decode($request->getBody());

    // Create a PaymentIntent with the order amount and currency
    // Step 1-2, 1-3
    $payment_intent = \Stripe\PaymentIntent::create([
      "amount" => $body->amount,
      "currency" => "sgd",
      'payment_method_types' => ['card_present'],
      "capture_method" => "manual"
    ]);
    
    // Send publishable key and PaymentIntent details to client
    // Step 1-4
    return $response->withJson(array('publicKey' => $pub_key, 'clientSecret' => $payment_intent->client_secret, 'id' => $payment_intent->id));
});

$app->post('/capture-payment-intent', function (Request $request, Response $response, array $args) {
    $body = json_decode($request->getBody());

    // Step 4a-2
    $intent = \Stripe\PaymentIntent::retrieve($body->paymentIntentId);
    $intent->capture();

    // Step 4a-3
    return $response->withJson(array('status' => $intent->status));
});


// Only relevant for 4b process
$app->post('/webhook-capture-payment-intent', function(Request $request, Response $response) {
    $logger = $this->get('logger');
    $event = $request->getParsedBody();
    // Parse the message body (and check the signature if possible)
    $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
    if ($webhookSecret) {
      try {
        $event = \Stripe\Webhook::constructEvent(
          $request->getBody(),
          $request->getHeaderLine('stripe-signature'),
          $webhookSecret
        );
      } catch (\Exception $e) {
        return $response->withJson([ 'error' => $e->getMessage() ])->withStatus(403);
      }
    } else {
      $event = $request->getParsedBody();
    }
    $type = $event['type'];
    $object = $event['data']['object'];
    
    if ($type == 'payment_intent.amount_capturable_updated') {
      // You can capture an amount less than or equal to the amount_capturable
      // By default capture() will capture the full amount_capturable
      // To cancel a payment before capturing use .cancel() (https://stripe.com/docs/api/payment_intents/cancel)
      $intent = \Stripe\PaymentIntent::retrieve($object->id);
      // Step 4b-2
      $logger->info('❗ Charging the card for: ' . $intent->amount_capturable);
      $intent->capture();
    }

    return $response->withJson([ 'status' => 'success' ])->withStatus(200);
});

$app->run();
