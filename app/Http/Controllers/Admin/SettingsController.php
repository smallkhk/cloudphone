<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Chat\ChatAssistant;
use App\Services\Chat\SiteKnowledge;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SettingsController extends Controller
{
    /** Setting keys that are encrypted and never rendered back to the browser. */
    protected const SECRET_KEYS = [
        'vmos_access_key', 'vmos_secret_key', 'vmos_webhook_token',
        'trongrid_api_key', 'bscscan_api_key', 'mail_password', 'anthropic_api_key',
        'assistant_openai_api_key',
    ];

    public function edit(string $tab = 'site')
    {
        abort_unless(in_array($tab, ['site', 'payments', 'vmos', 'mail', 'assistant'], true), 404);

        return view('admin.settings.edit', [
            'tab' => $tab,
            'settings' => Setting::all_settings(),
            'secretKeys' => self::SECRET_KEYS,
        ]);
    }

    public function updateSite(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_url' => ['nullable', 'url', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_telegram' => ['nullable', 'string', 'max:255'],
            'support_whatsapp' => ['nullable', 'string', 'max:255'],
            'default_markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $this->save($data);

        return back()->with('status', 'Site settings saved.');
    }

    public function updatePayments(Request $request)
    {
        $data = $request->validate([
            'crypto_usdt_trc20_address' => ['nullable', 'string', 'max:64'],
            'crypto_usdt_bep20_address' => ['nullable', 'string', 'max:64'],
            'crypto_payment_window_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'crypto_amount_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'trongrid_api_key' => ['nullable', 'string', 'max:255'],
            'bscscan_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->save($data);

        return back()->with('status', 'Payment settings saved.');
    }

    public function updateVmos(Request $request)
    {
        $data = $request->validate([
            'vmos_base_url' => ['nullable', 'url', 'max:255'],
            'vmos_access_key' => ['nullable', 'string', 'max:255'],
            'vmos_secret_key' => ['nullable', 'string', 'max:255'],
            'vmos_webhook_token' => ['nullable', 'string', 'max:255'],
            'vmos_price_unit' => ['nullable', 'in:cents,units'],
        ]);

        // Keep whatever is already stored when the field isn't submitted, so a
        // partial update doesn't silently reset the price unit.
        $data['vmos_price_unit'] = $data['vmos_price_unit'] ?? Setting::get('vmos_price_unit', 'cents');

        $this->save($data);

        return back()->with('status', 'VMOS credentials saved.');
    }

    public function updateMail(Request $request)
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:smtp,log,sendmail,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->save($data);

        return back()->with('status', 'Mail settings saved.');
    }

    public function updateAssistant(Request $request)
    {
        $data = $request->validate([
            'assistant_provider' => ['required', 'in:claude,openai'],
            'anthropic_api_key' => ['nullable', 'string', 'max:255'],
            'assistant_model' => ['nullable', 'string', 'max:100'],
            'assistant_openai_preset' => ['nullable', 'string', 'max:32'],
            'assistant_openai_base_url' => ['nullable', 'url', 'max:255'],
            'assistant_openai_api_key' => ['nullable', 'string', 'max:255'],
            'assistant_openai_model' => ['nullable', 'string', 'max:128'],
            'assistant_max_tokens' => ['nullable', 'integer', 'min:256', 'max:8192'],
            'assistant_greeting' => ['nullable', 'string', 'max:500'],
            'assistant_rate_limit_per_hour' => ['nullable', 'integer', 'min:1', 'max:500'],
            'assistant_knowledge' => ['nullable', 'string', 'max:20000'],
        ]);

        // Checkboxes don't post when unticked, so the toggle is set explicitly.
        $data['assistant_enabled'] = $request->boolean('assistant_enabled') ? '1' : '0';

        $this->save($data);

        if ($request->boolean('assistant_enabled') && ! filled(config('assistant.api_key'))) {
            return back()->with('error', 'Saved, but live chat stays hidden until you add an Anthropic API key.');
        }

        return back()->with('status', 'Assistant settings saved.');
    }

    /** Sends one real message through whichever provider is selected. */
    public function testAssistant(SiteKnowledge $knowledge, ChatAssistant $assistant)
    {
        $provider = $assistant->provider();

        $missing = config('assistant.provider') === 'openai'
            ? ! filled(config('assistant.openai_api_key')) || ! filled(config('assistant.openai_base_url'))
            : ! filled(config('assistant.api_key'));

        if ($missing) {
            return back()->with('error', 'Fill in the endpoint and API key for your chosen provider first, and save.');
        }

        try {
            $result = $provider->complete(
                system: [['type' => 'text', 'text' => $knowledge->sitePrompt(), 'cache' => false]],
                messages: [['role' => 'user', 'content' => 'In one short sentence, what is the cheapest plan on this site?']],
            );

            return back()->with('status', $provider->label().' is working. It replied: “'.trim($result['text']).'”');
        } catch (Throwable $e) {
            return back()->with('error', 'Assistant test failed: '.$e->getMessage());
        }
    }

    /** Verifies the stored VMOS credentials by making a real signed API call. */
    public function testVmos(VmosCloudPhoneService $vmos)
    {
        if (! filled(config('vmos.access_key')) || ! filled(config('vmos.secret_key'))) {
            return back()->with('error', 'Add your VMOS Access Key and Secret Key first.');
        }

        try {
            $response = $vmos->listGoods(androidVersion: 13);
            $count = count($response['data']['configs'] ?? []);

            return back()->with('status', "VMOS connection OK — {$count} device configuration(s) visible to this account.");
        } catch (Throwable $e) {
            return back()->with('error', 'VMOS connection failed: '.$e->getMessage());
        }
    }

    public function testMail(Request $request)
    {
        $request->validate(['test_email' => ['required', 'email']]);

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you received it, your mail settings work.',
                fn ($message) => $message->to($request->input('test_email'))->subject('Test email from '.config('app.name'))
            );

            return back()->with('status', 'Test email sent to '.$request->input('test_email').'. (With MAIL_MAILER=log it goes to storage/logs instead.)');
        } catch (Throwable $e) {
            return back()->with('error', 'Sending failed: '.$e->getMessage());
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function save(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::set($key, $value, in_array($key, self::SECRET_KEYS, true));
        }
    }
}
