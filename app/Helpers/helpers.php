<?php

use App\Models\Activity;
use App\Models\Gateways;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Post;
use App\Models\UserUpvote;
use App\Models\User;
use App\Models\UserCategory;


function activeRoute($route_name){
    if (Route::currentRouteName() == $route_name){
        return 'active';
    }
}

function activeRouteBulk($route_names){
    $current_route = Route::currentRouteName();
    if (in_array($current_route, $route_names)){
        return 'active';
    }
}

function activeRouteBulkShow($route_names){
    $current_route = Route::currentRouteName();
    if (in_array($current_route, $route_names)){
        return 'show';
    }
}


function createActivity($user_id, $activity_type, $activity_title, $url){
    $activityEntry = new Activity();
    $activityEntry->user_id = $user_id;
    $activityEntry->activity_type = $activity_type;
    $activityEntry->activity_title = $activity_title;
    $activityEntry->url = $url;
    $activityEntry->save();

}

function percentageChange($old, $new, int $precision = 1){
    if ($old == 0) {
        $old++;
        $new++;
    }
    $change = round((($new - $old) / $old) * 100, $precision);

    if ($change < 0 ){
        return '<span class="inline-flex items-center leading-none !ms-2 text-[var(--tblr-red)] text-[10px] bg-[rgba(var(--tblr-red-rgb),0.15)] px-[5px] py-[3px] rounded-[3px]">
            <svg class="mr-1 -scale-100" width="7" height="4" viewBox="0 0 7 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 3.2768C0 3.32591 0.0245541 3.38116 0.061384 3.41799L0.368304 3.72491C0.405134 3.76174 0.46038 3.78629 0.509487 3.78629C0.558594 3.78629 0.61384 3.76174 0.65067 3.72491L3.06306 1.31252L5.47545 3.72491C5.51228 3.76174 5.56752 3.78629 5.61663 3.78629C5.67188 3.78629 5.72098 3.76174 5.75781 3.72491L6.06473 3.41799C6.10156 3.38116 6.12612 3.32591 6.12612 3.2768C6.12612 3.2277 6.10156 3.17245 6.06473 3.13562L3.20424 0.275129C3.16741 0.238299 3.11217 0.213745 3.06306 0.213745C3.01395 0.213745 2.95871 0.238299 2.92188 0.275129L0.061384 3.13562C0.0245541 3.17245 0 3.2277 0 3.2768Z"/>
            </svg>
            '.$change.'%
        </span>';
    }else{
        return '<span class="inline-flex items-center leading-none !ms-2 text-[var(--tblr-green)] text-[10px] bg-[rgba(var(--tblr-green-rgb),0.15)] px-[5px] py-[3px] rounded-[3px]">
                    <svg class="mr-1" width="7" height="4" viewBox="0 0 7 4" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0 3.2768C0 3.32591 0.0245541 3.38116 0.061384 3.41799L0.368304 3.72491C0.405134 3.76174 0.46038 3.78629 0.509487 3.78629C0.558594 3.78629 0.61384 3.76174 0.65067 3.72491L3.06306 1.31252L5.47545 3.72491C5.51228 3.76174 5.56752 3.78629 5.61663 3.78629C5.67188 3.78629 5.72098 3.76174 5.75781 3.72491L6.06473 3.41799C6.10156 3.38116 6.12612 3.32591 6.12612 3.2768C6.12612 3.2277 6.10156 3.17245 6.06473 3.13562L3.20424 0.275129C3.16741 0.238299 3.11217 0.213745 3.06306 0.213745C3.01395 0.213745 2.95871 0.238299 2.92188 0.275129L0.061384 3.13562C0.0245541 3.17245 0 3.2277 0 3.2768Z"/>
                    </svg>
                    '.$change.'%
                </span>';
    }


}

function percentageChangeSign($old, $new, int $precision = 2){

    if (percentageChange($old, $new) > 0){
        return 'plus';
    }else{
        return 'minus';
    }

}


function currency(){
    $setting = \App\Models\Setting::first();
    return \App\Models\Currency::where('id', $setting->default_currency)->first();
}

function getSubscription(){
    $user = Auth::user();
    $subs = $user->subscriptions()->where('stripe_status', 'active')->first();
    if($subs != null){
        $gatewayCode = $subs->paid_with;
        $gateway = Gateways::where("code", $gatewayCode)->first();
        if($gateway == null) { return null; } //TODO: show error to set at least one gateway active

        $success = 0;
        if(env('APP_STATUS') == 'Development'){
            if($gatewayCode == 'stripe'){
                config(['cashier.key' => $gateway->sandbox_client_id]);
                config(['cashier.secret' => $gateway->sandbox_client_secret]); 
                config(['cashier.currency' => $gateway->currency]); 
                $success = 1;
            }
        }else{
            if($gatewayCode == 'stripe'){
                config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
                config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
                config(['cashier.currency' => $gateway->currency]); //currency()->code
                $success = 1;
            }
        }
        if($success == 1){
            return $subs->asStripeSubscription();
        }
    }
    return null;
}

function getSubscriptionActive(){
    // $user = Auth::user();
    // $settings = Setting::first();
    // config(['cashier.key' => $settings->stripe_key]);
    // config(['cashier.secret' => $settings->stripe_secret]);
    // config(['cashier.currency' => currency()->code]);
    // $activesubs = $user->subscriptions()->where('stripe_status', 'active')->first()->asStripeSubscription();
    // return $activesubs;

    return getSubscription();

}

function getSubscriptionStatus(){
    $user = Auth::user();
    $activeSub = $user->subscriptions()->where('stripe_status', 'active')->first();
    if ($activeSub == null){
        return false;
    }else{
        // $settings = Setting::first();
        // config(['cashier.key' => $settings->stripe_key]);
        // config(['cashier.secret' => $settings->stripe_secret]);
        // config(['cashier.currency' => currency()->code]);
        // $activesubs = $user->subscriptions()->where('stripe_status', 'active')->first()->asStripeSubscription();
        $activesubs = getSubscription();
        if ($activesubs->status == 'active'){
            return true;
        }else{
            $activeSub->stripe_status = 'canceled';
            $activeSub->ends_at = \Carbon\Carbon::now();
            $activeSub->save();
            return false;
        }
    }
}

function getSubscriptionName(){
    $user = Auth::user();
    return \App\Models\PaymentPlans::where('id', $user->subscriptions()->where('stripe_status', 'active')->first()->name)->first()->name;

}
function getSubscriptionRenewDate()
{
    // $settings = Setting::first();
    // config(['cashier.key' => $settings->stripe_key]);
    // config(['cashier.secret' => $settings->stripe_secret]);
    // config(['cashier.currency' => currency()->code]);
    // $user = Auth::user();
    // $activesub = $user->subscriptions()->where('stripe_status', 'active')->first()->asStripeSubscription();

    $activesub = getSubscription();
    return \Carbon\Carbon::createFromTimeStamp($activesub->current_period_end)->format('F jS, Y');
}

function getSubscriptionDaysLeft()
{
    // $settings = Setting::first();
    // config(['cashier.key' => $settings->stripe_key]);
    // config(['cashier.secret' => $settings->stripe_secret]);
    // config(['cashier.currency' => currency()->code]);
    // $user = Auth::user();
    // $activesub = $user->subscriptions()->where('stripe_status', 'active')->first()->asStripeSubscription();

    $activesub = getSubscription();
    return \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::createFromTimeStamp($activesub->current_period_end));
}

//Templates favorited
function isFavorited($template_id){
    $isFav = \App\Models\UserFavorite::where('user_id', Auth::id())->where('openai_id', $template_id)->exists();
    return $isFav;
}

//Country Flags
function country2flag(string $countryCode): string
{
    return (string) preg_replace_callback(
        '/./',
        static fn (array $letter) => mb_chr(ord($letter[0]) % 32 + 0x1F1E5),
        $countryCode
    );
}

//Memory Limit
function getServerMemoryLimit() {
    return (int) ini_get('memory_limit');
}
