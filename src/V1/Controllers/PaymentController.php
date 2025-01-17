<?php

namespace Immera\Payment\V1\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Immera\Payment\Events\PaymentInstanceCreated;
use Immera\Payment\Models\PaymentInstance;
use Immera\Payment\V1\Payment;
use Log;
use Immera\Payment\Controllers\PaymentController as PaymentControllerDefault;

class PaymentController extends PaymentControllerDefault
{

    public function initPayment(Request $request)
    {
        if(config('payment.active_version') == "v1" ){
            $pay_instance = new PaymentInstance();
            $pay_instance->payment_method = $request->payment_method;
            $pay_instance->return_url = $request->return_url;
            $pay_instance->amount = $request->amount;
            $pay_instance->currency = $request->currency;
            $pay_instance->additional_info = $request->additional_info;
            $pay_instance->save();

            $payment = new Payment();

            $options = $request->except(['currency', 'amount', 'payment_method']);

            $data = $payment->pay(
                $request->payment_method,
                $request->currency,
                $request->amount,
                $options
            );

            $response = $data['payment-intents'];
            $customerOptions = $data['customer-options'];
    
            $pay_instance->refresh();
            $pay_instance->setIntentIdFromObj($response, ['id']);
            $pay_instance->setClientSecretFromObj($response);
            $pay_instance->setStatusFromObj($response);
            $pay_instance->request_options = $request->all();
            $pay_instance->response_object = json_encode($response);
            $pay_instance->save();
    
            Log::info("About to raise and event 'PaymentInstanceCreated'.");
            event(new PaymentInstanceCreated($pay_instance));
            Log::info("'PaymentInstanceCreated' event has been raised.");
    
            return [
                'callback' => route('payment.callback'),
                'response' => $response,
                'customerOptions'=>$customerOptions
            ];
    
        }else{
            // Use old method as it is  
            return parent::initPayment($request);           
        }
    }    
}
