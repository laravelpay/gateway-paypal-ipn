<?php

namespace App\Gateways\PayPalIPN;

use Illuminate\Http\Request;
use LaraPay\Framework\Foundation\Interfaces\GatewayFoundation;

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

    /**
     * Define the currencies supported by this gateway
     *
     * @var array
     */
    protected array $currencies = [

    ];

    public function config(): array
    {
        return [
            'email' => [
                'label' => 'PayPal Email',
                'description' => 'The email address on which you receive payments',
                'type' => 'text',
                'rules' => ['required', 'email'],
            ],
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
        ];
    }

    public function pay($payment)
    {
        $gateway = $payment->gateway;

        if ($gateway->config('mode', 'production') == 'production') {
            $url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
        } else {
            $url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
        }

        echo '<body onload="document.redirectform.submit()" style="display: none">
            <form action="'. $url .'" method="post" name="redirectform">
                <input type="hidden" name="cmd" value="_xclick">
                <input type="hidden" name="business" value="'. $gateway->config('email') .'">
                <input type="hidden" name="item_name" value="'.$payment->description.'">
                <input type="hidden" name="item_number" value="'.$payment->id.'">
                <input type="hidden" name="amount" value="'.$payment->total().'">
                <input type="hidden" name="currency_code" value="'.$payment->currency.'">
                <input name="cancel_return" value="'. $payment->cancelUrl() .'">
                <input name="notify_url" value="'. $payment->webhookUrl() .'">
                <input name="return" value="'. $payment->successUrl() .'">
                <input name="rm" value="2">
                <input name="charset" value="utf-8">
                <input name="no_note" value="1">
              </form>
        </body>';
    }

    public function callback(Request $request)
    {

    }
}
