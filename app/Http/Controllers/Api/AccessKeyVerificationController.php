<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessKey;
use Illuminate\Http\Request;

/**
 * Called by the desktop app on launch to check the code a user typed in.
 * Unauthenticated by design — the desktop app has no other credential yet
 * at this point — so it's rate-limited instead (see routes/api.php) and
 * never reveals anything beyond a yes/no.
 */
class AccessKeyVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $code = strtoupper(trim($data['code']));
        $key = AccessKey::where('code', $code)->first();

        if (! $key || ! $key->isValid()) {
            return response()->json(['valid' => false], 401);
        }

        $key->recordUse();

        return response()->json(['valid' => true]);
    }
}
