<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CloudInstance;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The live screen inside the iPhone app. The streaming engine only exists as
 * a browser SDK, so the app shows this one page in an embedded web view while
 * everything else in the app is native.
 *
 * The app can't share the website's login cookie, so access is by a signed,
 * expiring link minted for the device's owner (ownership is checked when the
 * link is issued; the signature stops anyone changing the device in it).
 */
class LiveScreenController extends Controller
{
    /** How long the app has to open the page after asking for it. */
    protected const PAGE_LINK_MINUTES = 10;

    /** How long an open page can keep (re)connecting without being reopened. */
    protected const TOKEN_LINK_HOURS = 8;

    /** API: issue the signed page link for one of the signed-in customer's devices. */
    public function link(CloudInstance $instance): JsonResponse
    {
        abort_unless($instance->user_id === Auth::id(), 403);

        if (! $instance->pad_code) {
            return response()->json(['message' => 'This device is still being set up.'], 409);
        }

        return response()->json([
            // native=1 is part of the signature: the page then leaves its own
            // controls out and lets the app's native toolbar drive it.
            'url' => URL::temporarySignedRoute('mobile.live', now()->addMinutes(self::PAGE_LINK_MINUTES), ['instance' => $instance->id, 'native' => 1]),
        ]);
    }

    /** The page itself, opened inside the app's web view. */
    public function page(CloudInstance $instance)
    {
        return view('mobile.live', [
            'instance' => $instance,
            'tokenUrl' => URL::temporarySignedRoute('mobile.live.token', now()->addHours(self::TOKEN_LINK_HOURS), ['instance' => $instance->id]),
        ]);
    }

    /** Streaming session for the page — same as the website's screen-token, minus the cookie login. */
    public function token(CloudInstance $instance, VmosCloudPhoneService $vmos): JsonResponse
    {
        if (! $instance->pad_code) {
            return response()->json(['error' => 'This device is still being set up.'], 409);
        }

        try {
            $token = $vmos->stsToken($instance->pad_code)['data']['token'] ?? null;
        } catch (Throwable $e) {
            Log::warning('mobile.screen_token_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not start the live screen session. Please try again.'], 502);
        }

        if (! $token) {
            return response()->json(['error' => 'Did not get a session token back. Please try again.'], 502);
        }

        return response()->json([
            'token' => $token,
            'padCode' => $instance->pad_code,
            'baseUrl' => (string) config('vmos.sdk_base_url'),
            // Same viewer id as on the website, so reconnecting from the app
            // resumes rather than opening a second seat on the device.
            'userId' => 'user-'.$instance->user_id,
        ]);
    }
}
