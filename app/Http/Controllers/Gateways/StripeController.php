<?php

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Currency;
use App\Models\CustomSettings;
use App\Models\GatewayProducts;
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
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent;
use Stripe\Plan;

/**
 * Controls ALL Payment actions of Stripe
 */
class StripeController extends Controller
{
    /**
     * Reads GatewayProducts table and returns price id of the given plan
     */
    public static function getStripePriceId($planId){

        //check if plan exists
        $plan = PaymentPlans::where('id', $planId)->first();
        if($plan != null){
            $product = GatewayProducts::where(["plan_id" => $planId, "gateway_code" => "stripe"])->first();
            if($product != null){
                return $product->price_id;
            }else{
                return null;
            }
        }
        return null;

        

    }

    /**
     * Displays Payment Page of Stripe gateway.
     */
    public static function subscribe($planId, $plan){

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            config(['cashier.key' => $gateway->sandbox_client_id]);
            config(['cashier.secret' => $gateway->sandbox_client_secret]); 
            config(['cashier.currency' => $currency]); 
        }else{
            config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
            config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
            config(['cashier.currency' => $currency]); //currency()->code
        }

        $user = Auth::user();
        $intent = null;
        try {
            $intent = auth()->user()->createSetupIntent();
            $exception = null;
            if(self::getStripePriceId($planId) == null){
                $exception = "Stripe product ID is not set!";
            }
        } catch (\Exception $th) {
            // $exception = $th;
            $exception = Str::before($th->getMessage(),':');
        }


        
        return view('panel.user.payment.subscription.payWithStripe', compact('plan', 'intent', 'gateway', 'exception'));
    }


    /**
     * Handles payment action of Stripe.
     * 
     * Subscribe payment page posts here.
     */
    public function subscribePay(Request $request){

        $plan = PaymentPlans::find($request->plan);
        $user = Auth::user();
        $settings = Setting::first();

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            config(['cashier.key' => $gateway->sandbox_client_id]);
            config(['cashier.secret' => $gateway->sandbox_client_secret]); 
            config(['cashier.currency' => $currency]); 
        }else{
            config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
            config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
            config(['cashier.currency' => $currency]); //currency()->code
        }

        if (!$user->hasDefaultPaymentMethod()) {
            $user->updateDefaultPaymentMethodFromStripe();
        }

        $planId = $plan->id;

        $productId = self::getStripePriceId($planId);

        self::cancelAllSubscriptions();

        $subscription = $request->user()->newSubscription($planId, $productId)
            ->create($request->token);

        $subscription->plan_id = $planId;
        $subscription->paid_with = 'stripe';
        $subscription->save();

        $payment = new UserOrder();
        $payment->order_id = Str::random(12);
        $payment->plan_id = $planId;
        $payment->user_id = $user->id;
        $payment->payment_type = 'Credit, Debit Card';
        $payment->price = $plan->price;
        $payment->affiliate_earnings = ($plan->price*$settings->affiliate_commission_percentage)/100;
        $payment->status = 'Success';
        $payment->country = Auth::user()->country == 'Unknown';
        $payment->save();

        $user->remaining_words += $plan->total_words;
        $user->remaining_images += $plan->total_images;
        $user->save();

        createActivity($user->id, 'Purchased', $plan->name.' Plan', null);

        return redirect()->route('dashboard.index')->with(['message' => 'Thank you for your purchase. Enjoy your remaining words and images.', 'type' => 'success']);

    }

    public function cancelAllSubscriptions(){
        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            $key = $gateway->sandbox_client_secret;
        }else{
            $key = $gateway->live_client_secret;
        }

        $stripe = new \Stripe\StripeClient($key);

        $product = null;

        $user = Auth::user();

        $allSubscriptions = $stripe->subscriptions->all();
        if($allSubscriptions != null){
            foreach($allSubscriptions as $subs){
                $user->subscription($subs->name)->cancelNow();
            }
        }
    }

    /**
     * Cancels current subscription plan
     */
    public static function subscribeCancel(){

        $user = Auth::user();
        $settings = Setting::first();

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            config(['cashier.key' => $gateway->sandbox_client_id]);
            config(['cashier.secret' => $gateway->sandbox_client_secret]); 
            config(['cashier.currency' => $currency]); 
        }else{
            config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
            config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
            config(['cashier.currency' => $currency]); //currency()->code
        }

        $activesub = $user->subscriptions()->where('stripe_status', 'active')->first();

        if($activesub != null){
            $plan = PaymentPlans::where('id', $activesub->plan_id)->first();

            $recent_words = $user->remaining_words - $plan->total_words;
            $recent_images = $user->remaining_images - $plan->total_images;

            $user->subscription($activesub->name)->cancelNow();

            $user->remaining_words = $recent_words < 0 ? 0 : $recent_words;
            $user->remaining_images = $recent_images < 0 ? 0 : $recent_images;
            $user->save();

            createActivity($user->id, 'Cancelled', 'Subscription plan', null);


            return back()->with(['message' => 'Your subscription is cancelled succesfully.', 'type' => 'success']);
        }
        
        return back()->with(['message' => 'Could not find active subscription. Nothing changed!', 'type' => 'error']);
    }


    /**
     * Displays Payment Page of Stripe gateway for prepaid plans.
     */
    public static function prepaid($planId, $plan, $incomingException = null){

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            config(['cashier.key' => $gateway->sandbox_client_id]);
            config(['cashier.secret' => $gateway->sandbox_client_secret]); 
            config(['cashier.currency' => $currency]); 
        }else{
            config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
            config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
            config(['cashier.currency' => $currency]); //currency()->code
        }

        $user = Auth::user();
        $activesubs = $user->subscriptions()->where('stripe_status', 'active')->get();
        $intent = null;
        try {
            $intent = auth()->user()->createSetupIntent();
            $exception = $incomingException;
            if(self::getStripePriceId($planId) == null){
                $exception = "Stripe product ID is not set!";
            }
        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(),':');
        }
        
        return view('panel.user.payment.prepaid.payWithStripe', compact('plan', 'intent', 'gateway', 'exception', 'activesubs'));
    }


    /**
     * Handles payment action of Stripe.
     * 
     * Prepaid payment page posts here.
     */
    public function prepaidPay(Request $request){

        $plan = PaymentPlans::find($request->plan);
        $user = Auth::user();
        $settings = Setting::first();

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            config(['cashier.key' => $gateway->sandbox_client_id]);
            config(['cashier.secret' => $gateway->sandbox_client_secret]); 
            config(['cashier.currency' => $currency]); 
        }else{
            config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
            config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
            config(['cashier.currency' => $currency]); //currency()->code
        }

        $paymentMethod = $request->payment_method;

        try {
            $user->createOrGetStripeCustomer();
            $user->updateDefaultPaymentMethod($paymentMethod);
            $user->charge($plan->price * 100, $paymentMethod);
        } catch (\Exception $exception) {
            return self::prepaid($plan->id, $plan, $incomingException = $exception->getMessage());
            // return back()->with('error', $exception->getMessage());
        }

        $payment = new UserOrder();
        $payment->order_id = Str::random(12);
        $payment->plan_id = $plan->id;
        $payment->type = 'prepaid';
        $payment->user_id = $user->id;
        $payment->payment_type = 'Credit, Debit Card';
        $payment->price = $plan->price;
        $payment->affiliate_earnings = ($plan->price*$settings->affiliate_commission_percentage)/100;
        $payment->status = 'Success';
        $payment->country = Auth::user()->country == 'Unknown';
        $payment->save();

        $user->remaining_words += $plan->total_words;
        $user->remaining_images += $plan->total_images;
        $user->save();

        createActivity($user->id, 'Purchased', $plan->name.' Token Pack', null);

        return redirect()->route('dashboard.index')->with(['message' => 'Thank you for your purchase. Enjoy your remaining words and images.', 'type' => 'success']);
    }


    /**
     * Saves Membership plan product in stripe gateway.
     * @param planId ID of plan in PaymentPlans model.
     * @param productName Name of the product, plain text
     * @param price Price of product (Must be in cents level)
     * @param frequency Time interval of subscription, month / annual
     * @param type Type of product subscription/one-time
     */
    public static function saveProduct($planId, $productName, $price, $frequency, $type){

    try{

        // error_log("Stripe controller saving product\n".$planId."\n".$productName."\n".$price."\n".$frequency."\n".$type);

        $gateway = Gateways::where("code", "stripe")->first();
        if($gateway == null) { abort(404); } 

        $currency = Currency::where('id', $gateway->currency)->first()->code;

        if(env('APP_STATUS') == 'Development'){
            $key = $gateway->sandbox_client_secret;
        }else{
            $key = $gateway->live_client_secret;
        }

        $stripe = new \Stripe\StripeClient($key);

        $product = null;

        //check if product exists
        $productData = GatewayProducts::where(["plan_id" => $planId, "gateway_code" => "stripe"])->first();
        if($productData != null){
            if($productData->product_id != null && $productName != null){
                //Product has been created before, so only update name
                $updatedProduct = $stripe->products->update(
                    $productData->product_id,
                    ['name' => $productName]
                );
                $productData->plan_name = $productName;
                $productData->save();
            }else{
                //Product has not been created before but record exists. Create new product and update record.
                $newProduct =$stripe->products->create(['name' => $productName,]);
                $productData->product_id = $newProduct->id;
                $productData->plan_name = $productName;
                $productData->save();
            }
            $product = $productData;
        }else{

            $newProduct = $stripe->products->create(['name' => $productName,]);

            $product = new GatewayProducts();
            $product->plan_id = $planId;
            $product->plan_name = $productName;
            $product->gateway_code = "stripe";
            $product->gateway_title = "Stripe";
            $product->product_id = $newProduct->id;
            $product->save();
        }


        //check if price exists
        if($product->price_id != null){
            //Price exists
            // Since stripe api does not allow to update recurring values, we deactivate all prices added to this product before and add a new price object.

            // Deactivate all prices
            foreach ($stripe->prices->all(['product' => $product->product_id]) as $oldPrice) {
                $stripe->prices->update($oldPrice->id, ['active' => false]);
            }

            // One-Time price
            if($type == "o"){
                $updatedPrice = $stripe->prices->create([
                    'unit_amount' => $price,
                    'currency' => $currency,
                    'product' => $product->product_id,
                ]);
                $product->price_id = $updatedPrice->id;
                $product->save();
            }else{
                // Subscription
                $updatedPrice = $stripe->prices->create([
                    'unit_amount' => $price,
                    'currency' => $currency,
                    'recurring' => ['interval' => $frequency == "m" ? 'month' : 'year'],
                    'product' => $product->product_id,
                ]);
                $product->price_id = $updatedPrice->id;
                $product->save();
            }
        }else{
            // One-Time price
            if($type == "o"){
                $updatedPrice = $stripe->prices->create([
                    'unit_amount' => $price,
                    'currency' => $currency,
                    'product' => $product->product_id,
                ]);
                $product->price_id = $updatedPrice->id;
                $product->save();
            }else{
                // Subscription
                $updatedPrice = $stripe->prices->create([
                    'unit_amount' => $price,
                    'currency' => $currency,
                    'recurring' => ['interval' => $frequency == "m" ? 'month' : 'year'],
                    'product' => $product->product_id,
                ]);
                $product->price_id = $updatedPrice->id;
                $product->save();
            }
        }

    }catch(\Exception $ex){
        error_log("StripeController::saveProduct()\n".$ex->getMessage());
        return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
    }

    }


    /**
     * Used to generate new product id and price id of all saved membership plans in stripe gateway.
     */
    public static function saveAllProducts(){
        try{

            $gateway = Gateways::where("code", "stripe")->first();
            if($gateway == null) { 
                return back()->with(['message' => __('Please enable Stripe'), 'type' => 'error']);
                abort(404); } 

            // Get all membership plans

            $plans = PaymentPlans::where('active', 1)->get();

            foreach ($plans as $plan) {
                // Replaced definitions here. Because if monthly or prepaid words change just updating here will be enough.
                $freq = $plan->frequency == "monthly" ? "m" : "y"; // m => month | y => year
                $typ = $plan->type == "prepaid" ? "o" : "s"; // o => one-time | s => subscription

                self::saveProduct($plan->id, $plan->name, $plan->price, $freq, $typ);
            }

        }catch(\Exception $ex){
            error_log("StripeController::saveAllProducts()\n".$ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }

    }















    // Table structure of gatewayproducts
    // $table->integer('plan_id')->default(0);
    // $table->string('plan_name')->nullable();
    // $table->string('gateway_code')->nullable();
    // $table->string('gateway_title')->nullable();
    // $table->string('product_id')->nullable();
    // $table->string('price_id')->nullable();


}