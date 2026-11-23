<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the iPhone app call the website's own action controllers (restart a
 * device, buy a plan, top up, bind a number...) so business rules live in
 * exactly one place. Those controllers answer a browser with a redirect plus
 * a flash message; this turns that into JSON:
 *
 *   { "ok": true|false, "message": "...", "data": {other flashed values}, "location": "..." }
 *
 * A flashed `error` becomes HTTP 422. Validation failures and abort()s already
 * render as JSON because the request is forced to expect JSON.
 */
class MobileActionResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $session = app('session.store');
        $session->flush();
        $request->setLaravelSession($session);

        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $error = $session->get('error');
        $errors = $session->get('errors');

        $data = collect($session->get('_flash.new', []))
            ->reject(fn ($key) => in_array($key, ['status', 'error', 'errors'], true))
            ->mapWithKeys(fn ($key) => [$key => $session->get($key)])
            ->all();

        $payload = [
            'ok' => ! $error && ! $errors,
            'message' => $error ?? $session->get('status'),
            'data' => (object) $data,
            'location' => $response->getTargetUrl(),
        ];

        if ($errors && method_exists($errors, 'getBag')) {
            $messages = $errors->getBag('default')->getMessages();
            $payload['errors'] = $messages;
            $payload['message'] ??= collect($messages)->flatten()->first();
        }

        $session->flush();

        return response()->json($payload, $payload['ok'] ? 200 : 422);
    }
}
