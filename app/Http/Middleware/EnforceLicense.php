<?php

namespace App\Http\Middleware;

use App\Services\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the admin panel (and therefore POS and KDS) when the licence is
 * missing, tampered with, bound to another machine, or expired past its grace
 * window. The customer-facing eMenu is deliberately left alone — locking that
 * would take a shop's tables offline for its guests rather than pressure the
 * operator.
 */
class EnforceLicense
{
    public function __construct(private readonly LicenseManager $licenses) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->licenses->allowsAccess()) {
            return $next($request);
        }

        // Keep the health endpoint answering so container orchestration
        // doesn't read an unlicensed install as a crashed one.
        if ($request->is('up')) {
            return $next($request);
        }

        return response()->view('license.blocked', [
            'status' => $this->licenses->status(),
            'message' => $this->licenses->message(),
            'license' => $this->licenses->license(),
            'fingerprint' => $this->licenses->fingerprint(),
        ], Response::HTTP_FORBIDDEN);
    }
}
