<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Access codes are retired — the site and apps are open to anyone. Older
 * desktop app builds still ask for a code on launch and call this; it always
 * answers yes so those installs keep working without an update.
 */
class AccessKeyVerificationController extends Controller
{
    public function verify(): JsonResponse
    {
        return response()->json(['valid' => true]);
    }
}
