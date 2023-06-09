<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SettingsController extends Controller
{
    public function general(){
        return view('panel.admin.settings.general');
    }

    public function generalSave(Request $request){
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo') {
            $settings = Setting::first();
            $settings->site_name = $request->site_name;
            $settings->site_url = $request->site_url;
            $settings->site_email = $request->site_email;
            $settings->default_country = $request->default_country;
            $settings->default_currency = $request->default_currency;
            $settings->register_active = $request->register_active;
            $settings->google_analytics_code = $request->google_analytics_code;
            $settings->meta_title = $request->meta_title;
            $settings->feature_ai_writer = $request->feature_ai_writer;
            $settings->feature_ai_image = $request->feature_ai_image;
            $settings->feature_ai_chat = $request->feature_ai_chat;
            $settings->feature_ai_code = $request->feature_ai_code;
            $settings->feature_ai_speech_to_text = $request->feature_ai_speech_to_text;
            $settings->feature_affilates = $request->feature_affilates;
            $settings->save();

            $logo_types = [
                'logo' => '',
                'logo_dark' => 'dark',
                'logo_sticky' => 'sticky',
                'logo_dashboard' => 'dashboard',
                'logo_dashboard_dark' => 'dashboard-dark',
                'logo_collapsed' => 'collapsed',
                'logo_collapsed_dark' => 'collapsed-dark',
                // retina
                'logo_2x' => '2x',
                'logo_dark_2x' => 'dark-2x',
                'logo_sticky_2x' => 'sticky-2x',
                'logo_dashboard_2x' => 'dashboard-2x',
                'logo_dashboard_dark_2x' => 'dashboard-dark-2x',
                'logo_collapsed_2x' => 'collapsed-2x',
                'logo_collapsed_dark_2x' => 'collapsed-dark-2x',
            ];

            foreach( $logo_types as $logo => $logo_prefix ) {

                if ($request->hasFile($logo)) {
                    $path = 'upload/images/logo/';
                    $image = $request->file($logo);
                    $image_name = Str::random(4) . '-'. $logo_prefix .'-' . Str::slug($settings->site_name) . '-logo.' . $image->getClientOriginalExtension();

                    //Resim uzantı kontrolü
                    $imageTypes = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
                    if (!in_array(Str::lower($image->getClientOriginalExtension()), $imageTypes)) {
                        $data = array(
                            'errors' => ['The file extension must be jpg, jpeg, png, webp or svg.'],
                        );
                        return response()->json($data, 419);
                    }

                    $image->move($path, $image_name);

                    $settings->{$logo.'_path'} = $path . $image_name;
                    $settings->{$logo} = $image_name;
                    $settings->save();
                }

            }

            if ($request->hasFile('favicon')) {
                $path = 'upload/images/favicon/';
                $image = $request->file('favicon');
                $image_name = Str::random(4) . '-' . Str::slug($settings->site_name) . '-favicon.' . $image->getClientOriginalExtension();

                //Resim uzantı kontrolü
                $imageTypes = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
                if (!in_array(Str::lower($image->getClientOriginalExtension()), $imageTypes)) {
                    $data = array(
                        'errors' => ['The file extension must be jpg, jpeg, png, webp or svg.'],
                    );
                    return response()->json($data, 419);
                }

                $image->move($path, $image_name);

                $settings->favicon_path = $path . $image_name;
                $settings->favicon = $image_name;
                $settings->save();
            }
        }

    }

    public function openai(){
        return view('panel.admin.settings.openai');
    }

    public function openaiSave(Request $request){
        $settings = Setting::first();
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo'){
        $settings->openai_api_secret = $request->openai_api_secret;
        $settings->openai_default_model = $request->openai_default_model;
        $settings->openai_default_language = $request->openai_default_language;
        $settings->openai_default_tone_of_voice = $request->openai_default_tone_of_voice;
        $settings->openai_default_creativity = $request->openai_default_creativity;
        $settings->openai_max_input_length = $request->openai_max_input_length;
        $settings->openai_max_output_length = $request->openai_max_output_length;
        $settings->save();
        }
        return response()->json([], 200);
    }

    public function invoice(){
        return view('panel.admin.settings.invoice');
    }

    public function invoiceSave(Request $request){
        $settings = Setting::first();
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo') {
            $settings->invoice_currency = $request->invoice_currency;
            $settings->invoice_name = $request->invoice_name;
            $settings->invoice_website = $request->invoice_website;
            $settings->invoice_address = $request->invoice_address;
            $settings->invoice_city = $request->invoice_city;
            $settings->invoice_state = $request->invoice_state;
            $settings->invoice_country = $request->invoice_country;
            $settings->invoice_phone = $request->invoice_phone;
            $settings->invoice_postal = $request->invoice_postal;
            $settings->invoice_vat = $request->invoice_vat;
            $settings->save();
        }
        return response()->json([], 200);
    }

    public function payment(){
        return view('panel.admin.settings.stripe');
    }

    public function paymentSave(Request $request){
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo') {
            $settings = Setting::first();
            $settings->default_currency = $request->default_currency;
            $settings->stripe_active = 1;
            $settings->stripe_key = $request->stripe_key;
            $settings->stripe_secret = $request->stripe_secret;
            $settings->stripe_base_url = $request->stripe_base_url;
            $settings->save();
        }
        return response()->json([], 200);
    }

    public function affiliate(){
        return view('panel.admin.settings.affiliate');
    }

    public function affiliateSave(Request $request){
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo') {
            $settings = Setting::first();
            $settings->affiliate_minimum_withdrawal = $request->affiliate_minimum_withdrawal;
            $settings->affiliate_commission_percentage = $request->affiliate_commission_percentage;
            $settings->save();
        }
        return response()->json([], 200);
    }

    public function smtp(){
        return view('panel.admin.settings.smtp');
    }

    public function smtpSave(Request $request){
        // TODO SETTINGS
        if (env('APP_STATUS') != 'Demo') {
            $settings = Setting::first();
            $settings->smtp_host = $request->smtp_host;
            $settings->smtp_port = $request->smtp_port;
            $settings->smtp_username = $request->smtp_username;
            $settings->smtp_password = $request->smtp_password;
            $settings->smtp_email = $request->smtp_email;
            $settings->smtp_sender_name = $request->smtp_sender_name;
            $settings->smtp_encryption = $request->smtp_encryption;
            $settings->save();
        }
        return response()->json([], 200);
    }

    public function smtpTest(Request $request) {
        $toEmail =  $request->test_email;
        $toName = 'Test Email';

        try
        {
            Mail::raw('Test email content', function ($message) use ($toEmail, $toName) {
                $message->to($toEmail, $toName)
                    ->subject('Test Email');
            });
            return 'Test email sent!';

        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }
}
