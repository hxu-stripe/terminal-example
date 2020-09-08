# terminal demo app

## Demo layout

This app consists of:

1. A frontend written with our Terminal Javascript SDK (`./frontend-js`)
1. A backend implemented in two languages: Node (`./node`) and PHP (`./php`).  You only need one of these

Note that this demo does not handle the initial reader setup required to bind the reader to your Stripe account

## Running this demo

1. Choose the backend you want to use
1. Copy `.env.example` into the folder for the relevant backend and rename it to `.env`.  
1. Put in your Stripe account's publishable and secret keys into `.env`
1. Setup the environment for the backend (assuming you already have node or php and composer installed)
   1. If node, run `npm install` from the node folder
   1. If php, run `composer install` from the php folder
1. Run the backend server
   1. If node, run `npm start` from the node folder
   1. If php, run `php -S localhost:4242 index.php` from the php folder
1. Open `./frontend-js/index.html` in your browser
1. Click the buttons to walk through an end to end example
