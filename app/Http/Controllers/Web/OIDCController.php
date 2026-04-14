<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class OIDCController extends Controller
{
    /**
     * Redirect the user to the LabPics ID authorization endpoint.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('oidc')->redirect();
    }

    /**
     * Handle the OAuth callback from LabPics ID.
     *
     * - JIT provisioning: creates the user on first OIDC login from token claims.
     * - Links existing email accounts to their OIDC subject on first SSO use.
     * - Maps the `role` claim to a Solidtime organization role (owner / member).
     * - Auto-joins OIDC_DEFAULT_ORG_ID if configured and the user is not yet a member.
     */
    public function handleCallback(): RedirectResponse
    {
        $socialUser = Socialite::driver('oidc')->user();

        $oidcSub = $socialUser->getId();
        $email = $socialUser->getEmail();
        $name = $socialUser->getName() ?? $email;

        // Extract role from LabPics ID token claims
        $rawClaims = $socialUser->user ?? [];
        $role = $this->resolveRole(
            $rawClaims['role'] ?? $rawClaims['roles'][0] ?? 'member'
        );

        // Locate user by OIDC subject (stable across email changes), fall back to email
        $user = User::where('oidc_sub', $oidcSub)->first()
            ?? User::where('email', $email)->first();

        if ($user === null) {
            // JIT provisioning: new user from OIDC claims
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'oidc_sub' => $oidcSub,
                'password' => null,
                'email_verified_at' => now(),
            ]);
        } elseif ($user->oidc_sub === null) {
            // Bind an existing local account to this OIDC subject on first SSO use
            $user->oidc_sub = $oidcSub;
            $user->save();
        }

        // Auto-join the configured default organization with the mapped role
        $defaultOrgId = config('services.oidc.default_org_id');
        if ($defaultOrgId !== null) {
            $organization = Organization::find($defaultOrgId);
            if (
                $organization !== null
                && ! $user->organizations()->whereKey($organization->getKey())->exists()
            ) {
                $user->organizations()->attach($organization->getKey(), [
                    'id' => (string) Str::uuid(),
                    'role' => $role,
                    'billable_rate' => null,
                ]);
            }
        }

        Auth::guard('web')->login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Map an OIDC role claim to a Solidtime organization role.
     *
     * Solidtime supports two roles: 'owner' and 'member'.
     */
    private function resolveRole(mixed $claim): string
    {
        return match (strtolower((string) $claim)) {
            'owner', 'admin', 'administrator' => 'owner',
            default => 'member',
        };
    }
}
