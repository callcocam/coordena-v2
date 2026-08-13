<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side defense for WhatsApp API features. Two preconditions guard every
 * API-dependent route — hiding the button in the UI is convenience, this is the
 * guarantee:
 *   1. the team is in automatic mode (whatsapp_api_enabled=true); and
 *   2. a manager has accepted the current terms of use.
 *
 * Pass the `mode-only` parameter (`whatsapp.api:mode-only`) to skip the terms
 * check — used by the terms-acceptance route itself, which would otherwise
 * deadlock (you could never accept the terms because you have not accepted them).
 */
class EnsureWhatsappApiEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $team = $request->user()?->currentTeam;

        if ($team !== null) {
            $error = match (true) {
                ! $team->usesWhatsappApi() => 'app.whatsapp.errors.api_disabled',
                $mode !== 'mode-only' && ! $team->hasAcceptedWhatsappTerms() => 'app.whatsapp.errors.terms_required',
                default => null,
            };

            if ($error !== null) {
                // JSON / XHR callers (fetch from the connection card, groups,
                // etc.) get a plain 403; Inertia visits get a toast + back() so
                // the SPA navigation is not broken by an error response.
                if ($request->expectsJson()) {
                    abort(403, __($error));
                }

                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __($error),
                ]);

                return back();
            }
        }

        return $next($request);
    }
}
