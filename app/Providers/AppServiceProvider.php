<?php

namespace App\Providers;

use App\Models\FrontendSectionsStatusses;
use App\Models\FrontendSetting;
use App\Models\OpenAIGenerator;
use App\Models\Setting;
use App\Models\UserOpenai;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        try {
            DB::connection()->getPdo();
            $db_set = 1;
        } catch (\Exception $e) {
            $db_set = 2;
        }

        if ($db_set == 1) {
            app()->useLangPath(base_path('lang'));
            if (Schema::hasTable('migrations')){
                View::share('setting', Setting::first());
                if (Schema::hasTable('frontend_footer_settings')) {
                    if (FrontendSetting::first() == null) {
                        $fSettings = new FrontendSetting();
                        $fSettings->save();
                    }
                    View::share('fSetting', FrontendSetting::first());
                }
                if (Schema::hasTable('frontend_sections_statuses_titles')) {
                    if (FrontendSectionsStatusses::first() == null) {
                        $fSectSettings = new FrontendSectionsStatusses();
                        $fSectSettings->save();
                    }
                    View::share('fSectSettings', FrontendSectionsStatusses::first());
                }
                $aiWriters = OpenAIGenerator::orderBy('title', 'asc')->where('active', 1)->get();
                View::share('aiWriters', $aiWriters);
                view()->composer('*', function ($view) {
                    if (Auth::check()) {
                        $total_documents_finder = UserOpenai::where('user_id', Auth::id())->get();
                        $total_words = UserOpenai::where('user_id', Auth::id())->sum('credits');
                        $total_documents = count($total_documents_finder);
                        $total_text_documents = count($total_documents_finder->where('credits', '!=', 1));
                        $total_image_documents = count($total_documents_finder->where('credits', '==', 1));
                        View::share('total_words', $total_words);
                        View::share('total_documents', $total_documents);
                        View::share('total_text_documents', $total_text_documents);
                        View::share('total_image_documents', $total_image_documents);
                    }
                });

                //Global Mail Settings
                $settings = Setting::first();

                if ($settings !== null && isset($settings->stripe_status_for_now) && $settings->stripe_status_for_now == 'active') {
                    View::share('good_for_now', true);
                }else{
                    View::share('good_for_now', false);
                }

                Config::set(['mail.mailers' => ['smtp' =>
                    [
                        'transport' => 'smtp',
                        'host' => $settings->smtp_host ?? env('MAIL_HOST'),
                        'port' => (int)$settings->smtp_port ?? (int)env('MAIL_PORT'),
                        'encryption' => $settings->smtp_encryption ?? env('MAIL_ENCRYPTION'),
                        'username' => $settings->smtp_username ?? env('MAIL_USERNAME'),
                        'password' => $settings->smtp_password ?? env('MAIL_PASSWORD')],
                    'timeout' => null,
                    'local_domain' => env('MAIL_EHLO_DOMAIN'),
                    'auth_mode' => null,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]]);

                Config::set(
                    ['mail.from' => ['address' => $settings->smtp_email ?? env('MAIL_FROM_ADDRESS'), 'name' => $settings->smtp_sender_name ?? env('MAIL_FROM_NAME')]]
                );


                $wordlist = DB::table('jobs')->where('id', '>', 0)->get();
                if (count($wordlist)>0){
                    Artisan::call("queue:work --once");
                }

            }
        }

        Health::checks([
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new(),
            Memory_Limit::new()
        ]);

    }


}

/**
 * Creating custom checks
 */

class Memory_Limit extends \Spatie\Health\Checks\Check
{
    public function run(): \Spatie\Health\Checks\Result
    {
        $memory_limit = getServerMemoryLimit();

        $result = \Spatie\Health\Checks\Result::make();

        if ($memory_limit < 64) {
            return $result->failed("{$memory_limit}M");
        }

        return $result->ok("{$memory_limit}M");

    }

}
