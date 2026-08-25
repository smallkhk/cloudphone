<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Access codes for the (not-yet-built) desktop app. The website itself has no
 * lock — this only gates the desktop app, which checks a code against
 * POST /api/access-keys/verify before it loads the site.
 */
class AccessKeyController extends Controller
{
    public function index()
    {
        return view('admin.access-keys.index', [
            'keys' => AccessKey::with('creator')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $key = AccessKey::create([
            'code' => AccessKey::generateCode(),
            'label' => $data['label'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', "New access code: {$key->code} — copy it now, it won't be shown highlighted again.");
    }

    public function toggle(AccessKey $accessKey)
    {
        $accessKey->update(['is_active' => ! $accessKey->is_active]);

        return back()->with('status', $accessKey->is_active ? 'Access code re-enabled.' : 'Access code revoked.');
    }

    public function destroy(AccessKey $accessKey)
    {
        $accessKey->delete();

        return back()->with('status', 'Access code deleted.');
    }
}
