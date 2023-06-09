<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Currency;
use App\Models\CustomSettings;
use App\Models\Gateways;
use App\Models\PaymentPlans;
use App\Models\Setting;
use App\Models\HowitWorks;
use App\Models\User;
use App\Models\UserAffiliate;
use App\Models\UserOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Laravel\Cashier\Subscription;

use App\Http\Controllers\Gateways\StripeController;



/**
 * Controls ALL Payment actions
 */
class PaymentController extends Controller
{

    /**
     * Checks subscription table if given plan is active on user (already subscribed)
     */
    function isActiveSubscription($planId){
        // $plan->stripe_product_id != null
        $user = Auth::user();
        $activesub = $user->subscriptions()->where('stripe_status', 'active')->first();
        if($activesub != null){
            $activesubid = $activesub->id;
        }else{
            $activesubid = 0; //id can't be zero, so this will be easy to check instead of null
        }
        return $activesubid == $planId;
    }


    public function startSubscriptionProcess($planId, $gatewayCode){
        $plan = PaymentPlans::where('id', $planId)->first();
        if($plan != null){
            if(self::isActiveSubscription($planId) == true){
                return back()->with(['message' => 'You already have subscription.Please cancel it before creating a new subscription.', 'type' => 'error']);
            }
            if($gatewayCode == 'stripe'){
                return StripeController::subscribe($planId, $plan);
            }
        }
        abort(404);
    }

    public function cancelActiveSubscription(){
        $user = Auth::user();
        $activesub = $user->subscriptions()->where('stripe_status', 'active')->first();
        if($activesub == null){
            abort(404, 'Could not find any subscription. Please check your gateways panel.');
            return back()->with(['message' => 'Could not find any subscription. Please check your gateways panel.', 'type' => 'error']);
        }
        $gatewayCode = $activesub->paid_with;
        if($gatewayCode == 'stripe'){
            return StripeController::subscribeCancel();
        }
        return back()->with(['message' => 'Could not cancel subscription. Please try again. If this error occures again, please update and migrate.', 'type' => 'error']);
    }


    public function startPrepaidPaymentProcess($planId, $gatewayCode){
        $plan = PaymentPlans::where('id', $planId)->first();
        if($plan != null){
            if($gatewayCode == 'stripe'){
                return StripeController::prepaid($planId, $plan);
            }
        }
        abort(404);
    }

    /**
     * Saves Membership plan product in all gateways.
     * @param planId ID of plan in PaymentPlans model.
     * @param productName Name of the product, plain text
     * @param price Price of product
     * @param frequency Time interval of subscription, month / annual
     * @param type Type of product subscription/one-time
     */
    public static function saveGatewayProducts($planId, $productName, $price, $frequency, $type){

        // error_log('Executing PaymentController->saveGatewayProducts() with :\n'.$planId."\n".$productName."\n".$price."\n".$frequency."\n".$type);
        
        // Replaced definitions here. Because if monthly or prepaid words change just updating here will be enough.
        $freq = $frequency == "monthly" ? "m" : "y"; // m => month | y => year
        $typ = $type == "prepaid" ? "o" : "s"; // o => one-time | s => subscription

        $gateways = Gateways::all();
        if($gateways != null){
            foreach($gateways as $gateway){
                if((int)$gateway->is_active == 1){
                    if($gateway->code == 'stripe'){
                        // error_log("Active gateway stripe");
                        $tmp = StripeController::saveProduct($planId, $productName, $price, $freq, $typ);
                    }
                }
            }
        }else{
            error_log("Could not find any active gateways!\nPaymentController->saveGatewayProducts()");
            return back()->with(['message' => 'Please enable at least one gateway.', 'type' => 'error']);
        }
    }



}