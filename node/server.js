const express = require("express");
const cors = require('cors');
const app = express();
const { resolve } = require("path");
// Copy the .env.example in the root into a .env file in this folder
const env = require("dotenv").config({ path: "./.env" });
const stripe = require("stripe")(process.env.STRIPE_SECRET_KEY);

// Allows cross-origin requests for developing on localhost
app.use(cors());

app.use(
  express.json({
    // We need the raw body to verify webhook signatures.
    // Let's compute it only when hitting the Stripe webhook endpoint.
    verify: function(req, res, buf) {
      if (req.originalUrl.startsWith("/webhook")) {
        req.rawBody = buf.toString();
      }
    }
  })
);
/****
 * Discovering readers
 */

app.post("/connection-token", async (req, res) => {
  // Step 0-2
  const connectionToken = await stripe.terminal.connectionTokens.create();

  res.send({
    secret: connectionToken.secret
  });
});

/****
 * Payment
 */

app.post("/create-payment-intent", async (req, res) => {
  const { amount } = req.body;
  // Create a PaymentIntent with the order amount and currency
  // Step 1-2, 1-3
  const paymentIntent = await stripe.paymentIntents.create({
    amount: amount,
    currency: 'sgd',
    payment_method_types: ['card_present'],
    capture_method: "manual"
  });

  // Send publishable key and PaymentIntent details to client
  // Step 1-4
  res.send({
    publicKey: env.parsed.STRIPE_PUBLISHABLE_KEY,
    clientSecret: paymentIntent.client_secret,
    id: paymentIntent.id
  });
});


app.post("/capture-payment-intent", async (req, res) => {
  const { paymentIntentId } = req.body;

  // Step 4a-2
  let result = await stripe.paymentIntents.capture(paymentIntentId);
  console.log(result);

  // Step 4a-3
  res.send({status: result.status});
});

// Webhook handler for asynchronous events.
// Only relevant for 4b process
app.post("/webhook-capture-payment-intent", async (req, res) => {
  // Check if webhook signing is configured.
  if (env.parsed.STRIPE_WEBHOOK_SECRET) {
    // Retrieve the event by verifying the signature using the raw body and secret.
    let event;
    let signature = req.headers["stripe-signature"];
    try {
      event = stripe.webhooks.constructEvent(
        req.rawBody,
        signature,
        env.parsed.STRIPE_WEBHOOK_SECRET
      );
    } catch (err) {
      console.log(`⚠️  Webhook signature verification failed.`);
      return res.sendStatus(400);
    }
    data = event.data;
    eventType = event.type;
  } else {
    // Webhook signing is recommended, but if the secret is not configured in `config.js`,
    // we can retrieve the event data directly from the request body.
    data = req.body.data;
    eventType = req.body.type;
  }

  if (eventType === "payment_intent.amount_capturable_updated") {
    // Step 4b-2
    console.log(`❗ Charging the card for: ${data.object.amount_capturable}`);
    const intent = await stripe.paymentIntents.capture(data.object.id);
  }

  res.sendStatus(200);
});

app.listen(4242, () => console.log(`Node server listening on port ${4242}!`));
