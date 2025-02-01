<?php

namespace App\Gateways\PayPalIPN;

use LaraPay\Framework\Interfaces\GatewayFoundation;
use Illuminate\Support\Facades\Http;
use LaraPay\Framework\Payment;
use Illuminate\Http\Request;
use Exception;

class Gateway extends GatewayFoundation
{
    /**
     * Define the gateway identifier. This identifier should be unique. For example,
     * if the gateway name is "PayPal Express", the gateway identifier should be "paypal-express".
     *
     * @var string
     */
    protected string $identifier = 'paypal-ipn';

    /**
     * Define the gateway version.
     *
     * @var string
     */
    protected string $version = '1.0.0';

    public function config(): array
    {
        return [
            'mode' => [
                'label' => 'Mode (Sandbox/Production)',
                'description' => 'The mode in which the gateway should run',
                'type' => 'select',
                'options' => [
                    'sandbox' => 'Sandbox',
                    'production' => 'Production',
                ],
                'default' => 'production',
                'rules' => ['required', 'in:sandbox,production'],
            ],
            'email' => [
                'label' => 'PayPal Email',
                'description' => 'The email address on which you receive payments',
                'type' => 'text',
                'rules' => ['required', 'email'],
            ],
        ];
    }

    public function pay($payment)
    {
        $url = $this->getPayPalUrl($payment->gateway->config('mode', 'production'));

        $checkoutUrl = url()->query($url, [
            'cmd' => '_xclick',
            'business' => $payment->gateway->config('email'),
            'item_name' => $payment->description,
            'item_number' => $payment->id,
            'amount' => $payment->total(),
            'currency_code' => $payment->currency,
            'cancel_return' => $payment->cancelUrl(),
            'notify_url' => $payment->webhookUrl(),
            'return' => $payment->successUrl(),
            'rm' => 2,
            'charset' => 'uft-8',
            'no_note' => 1,
        ]);

        return redirect($checkoutUrl);
    }

    public function webhook(Request $request)
    {
        $payment = Payment::where('token', $request->input('payment_token'))->first();

        if (!$payment) {
            throw new Exception("Payment not found");
        }

        $gateway = $payment->gateway;

        // The IPN request is a POST request, so we'll get the data from the request input
        $ipnPayload = $request->post();

        // Before processing the IPN message, you should validate it to make sure it's actually from PayPal
        $this->validateIpn($ipnPayload, $gateway);

        // Perform some validation checks
        $this->validationChecks($ipnPayload, $payment);

        $payment->completed($ipnPayload['txn_id'], $ipnPayload);
    }

    private function validateIpn($ipnPayload, $gateway)
    {
        $paypalUrl = $this->getPayPalUrl($gateway->config('mode', 'production'));

        // Build a form-data array, prepending cmd=_notify-validate
        $postData = array_merge(['cmd' => '_notify-validate'], $ipnPayload);

        // Make the request as application/x-www-form-urlencoded
        $response = Http::asForm()
            ->post($paypalUrl, $postData);

        // Compare to see if it is 'VERIFIED'
        if($response->body() !== 'VERIFIED') {
            throw new \Exception('IPN is not valid');
        }

        return true;
    }

    private function validationChecks($ipnPayload, $payment)
    {
        // check if the payment status is completed
        if ($ipnPayload['payment_status'] !== 'Completed') {
            // The payment status is not completed
            throw new Exception("The payment status is not completed");
        }

        // check if the payment is not already paid
        if ($payment->isPaid()) {
            // The payment is already paid
            throw new Exception("The payment {$payment->id} is already paid");
        }

        // compare the payment amount sent with the amount from the database
        if ($ipnPayload['mc_gross'] != $payment->total()) {
            // The payment amount doesn't match the amount from the database
            throw new Exception("The payment {$payment->id} amount doesn't match the amount from the database. Given amount: {$ipnPayload['mc_gross']}");
        }

        // check if the receiver email is the same as the one in the database
        if ($ipnPayload['receiver_email'] != $payment->gateway->config('email')) {
            // The receiver email doesn't match the email from the database
            throw new Exception("The receiver email doesn't match the email from the gateway config");
        }

        // check if the item_number is the same as the one in the database
        if ($ipnPayload['item_number'] != $payment->id) {
            // The item_number doesn't match the item_number from the database
            throw new Exception("The item_number doesn't match the item_number from the database, Given item_number: {$ipnPayload['item_number']}");
        }

        // check if the currency is the same as the one in the database
        if ($ipnPayload['mc_currency'] != $payment->currency) {
            // The currency doesn't match the currency from the database
            throw new Exception("The currency doesn't match the currency from the database, Given currency: {$ipnPayload['mc_currency']}");
        }

        // check if the transaction is already processed
        if (Payment::where('transaction_id', $ipnPayload['txn_id'])->exists()) {
            // The transaction is already processed
            throw new Exception("The transaction {$ipnPayload['txn_id']} is already processed");
        }
    }

    private function getPayPalUrl(string $environment = 'production')
    {
        if ($environment == 'production') {
            return 'https://ipnpb.paypal.com/cgi-bin/webscr';
        }

        return 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
    }
}
