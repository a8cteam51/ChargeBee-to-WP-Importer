This is a basic importer for importing Stripe based subscriptions from ChargeBee to WooCommerce Subscriptions. 

## To Use
Make a copy of the config.sample.php and save as config.php

You will need to fill in the following details:
* `api_key` - The ChargeBee API key
* `account_id` - The name of your chargebee account
* `obfusticate` (setting this to true will obfusticate email and stripe ids, for debugging and development)
* `product_map` - This is an array of all the chargebee plans and what they relate to on the WP site.

## How it works

We have 2 commands
* `wp cb2wp compile-chargebee` - This will get all the data from chargebee and save it in an intermediate csv file
* `wp cb2wp compile-wc` - This will then go through all the chargebee data and create the WooCommerce subscriptions.

## Modifying.

Most of the work is done under the two `Actions` these are what the commands call. so any changes for taxes etc, will more than likely be done in that process.

This is setup to only work with UK Vat (inc or exc), but you should be able to work out how to change it for your own needs.

##  Debugging
This plugin has the Symfony Debugger built in, so you can use the `debug()` function to output data to the console and use dd(). These are scattered around the code, in areas where we dont have plans that match skus. So when its running you should get meaningful output.

## Notes
The ChargeBee SDK is bundled in here, it was added in April 2025, so please check the version and update if needed.

This is setup for people of print, but just copy this mu-plugin and whatever site your working on, and keep track changes separately 