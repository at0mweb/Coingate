<?php

namespace App\Extensions\Gateways\Coingate;

use App\Classes\Extensions\Gateway;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

require_once __DIR__ . '/coingate/init.php';

class Coingate extends Gateway
{
    /**
    * Get the extension metadata
    * 
    * @return array
    */
    public function getMetadata()
    {
        return [
            'display_name' => 'Coingate',
            'version' => '1.0.0',
            'author' => 'at0mweb',
            'website' => 'https://github.com/at0mweb',
        ];
    }

    /**
     * Get all the configuration for the extension
     * 
     * @return array
     */
    public function getConfig()
    {
        return [
            [
                'name' => 'auth_token',
                'friendlyName' => 'Auth Token',
                'type' => 'password',
                'description' => 'Auth Token from CoinGate API Apps at https://coingate.com',
                'required' => true,
            ],
            [
                'name' => 'use_sandbox_env',
                'friendlyName' => 'Sandbox Mode',
                'type' => 'dropdown',
                'options' => [
                    [
                        'name'=>'Yes',
                        'value'=>'true'
                    ],
                    [
                        'name'=>'No',
                        'value'=>'false'
                    ]
                ],
                'description' => 'Enable to use Sandbox for testing purpose. Please note, that for Sandbox you must generate separate API credentials at https://sandbox.coingate.com',
                'required' => true,
            ],
            [
                'name' => 'payout_currency',
                'friendlyName' => 'Payout Currency',
                'type' => 'dropdown',
                'options' => [
                    [
                        'name'=>'BTC',
                        'value'=>'BTC'
                    ],
                    [
                        'name'=>'USDT',
                        'value'=>'USDT'
                    ],
                    [
                        'name'=>'ETH',
                        'value'=>'ETH'
                    ],
                    [
                        'name'=>'LTC',
                        'value'=>'LTC'
                    ],
                    [
                        'name'=>'EUR',
                        'value'=>'EUR'
                    ],
                    [
                        'name'=>'USD',
                        'value'=>'USD'
                    ],
                    [
                        'name'=>'DO NOT CONVERT',
                        'value'=>'DO_NOT_CONVERT'
                    ]
                ],
                'description' => 'Currency you want to receive when making withdrawal at CoinGate. Please take a note what if you choose EUR or USD you will be asked to verify your business before making a withdrawal at CoinGate',
                'required' => true,
            ]
           
            
        ];
    }
    

    /**
     * Get the URL to redirect to
     * 
     * @param int $total
     * @param array $products
     * @param int $invoiceId
     * @return string
     */
    public function pay($total, $products, $invoiceId)
    {
        $apiAuthToken = ExtensionHelper::getConfig('Coingate', 'auth_token');
        $useSandboxEnv = ExtensionHelper::getConfig('Coingate', 'use_sandbox_env');
        $receiveCurrency = ExtensionHelper::getConfig('Coingate', 'payout_currency');

        $currencyCode = ExtensionHelper::getCurrency();
        $amount = $total;

        $client = new \CoinGate\Client($apiAuthToken, $useSandboxEnv);

        $description = 'Products: ';
        foreach ($products as $product) {
            $description .= $product->name . ' x' . $product->quantity . ', ';
        }

      

        $order = $client->order->create([
            'order_id' => $invoiceId,
            'price_amount' => number_format($amount, 8, '.', ''),
            'price_currency' => $currencyCode,
            'receive_currency'  => $receiveCurrency,
            'cancel_url'        => route('clients.invoice.show', $invoiceId),
            'callback_url'      => url('/extensions/coingate/webhook'),
            'success_url'       => route('clients.invoice.show', $invoiceId),
            'title'             => $invoiceId,
            'description'       => $description
        ]);

    
    
        return $order->payment_url;
    }

    public function webhook(Request $request)
    {
        $apiAuthToken = ExtensionHelper::getConfig('Coingate', 'auth_token');
        $useSandboxEnv = ExtensionHelper::getConfig('Coingate', 'use_sandbox_env');
        $client = new \CoinGate\Client($apiAuthToken, $useSandboxEnv);

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $transactionId = $data['id'];
        
        $order = $client->order->get($transactionId);
        $orderId = $order->order_id;

        if ($order->status == 'paid') {
            ExtensionHelper::paymentDone($orderId);
        }

        return response()->json(['message' => 'Webhook received, payment success!'], 200);
    }
}
