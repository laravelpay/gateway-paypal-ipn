# PayPal IPN integration for Laravel Pay
This package uses PayPal IPN to create payments and let users checkout on PayPal. The benefit is that you only need to configure your PayPal email.

Before you can install this package, make sure you have the composer package `laravelpay/framework` installed. Learn more here https://github.com/laravelpay/gateway-stripe

## Installation
Run this command inside your Laravel application

```
php artisan gateway:install laravelpay/gateway-paypal-ipn
```

## Setup
```
php artisan gateway:setup paypal-ipn
```
