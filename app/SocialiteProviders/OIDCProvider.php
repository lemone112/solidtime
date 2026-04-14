<?php

declare(strict_types=1);

namespace App\SocialiteProviders;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * OIDC Socialite Provider for LabPics ID.
 *
 * Auto-discovers endpoints from {OIDC_HOST}/identity/.well-known/openid-configuration.
 * Implements PKCE S256 as required by OAuth 2.1 / LabPics ID.
 * Extracts extended claims (role, etc.) from id_token payload and merges with userinfo.
 */
class OIDCProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    private ?array $discoveryDoc = null;

    private ?array $idTokenClaims = null;

    private function discovery(): array
    {
        if ($this->discoveryDoc === null) {
            $host = rtrim(config('services.oidc.host', 'https://auth.lab.pics'), '/');
            $url = $host . '/identity/.well-known/openid-configuration';
            $response = $this->getHttpClient()->get($url);
            $this->discoveryDoc = json_decode((string) $response->getBody(), associative: true);
        }

        return $this->discoveryDoc;
    }

    /**
     * Builds the authorization URL and attaches a PKCE S256 code challenge.
     * The code verifier is persisted in the session for token exchange.
     */
    protected function getAuthUrl($state): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        session(['oidc_pkce_verifier' => $verifier]);

        $challenge = rtrim(
            strtr(base64_encode(hash('sha256', $verifier, binary: true)), '+/', '-_'),
            '='
        );

        $base = $this->buildAuthUrlFromBase($this->discovery()['authorization_endpoint'], $state);

        return $base . '&code_challenge=' . $challenge . '&code_challenge_method=S256';
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery()['token_endpoint'];
    }

    protected function getTokenFields(string $code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'code_verifier' => session()->pull('oidc_pkce_verifier', ''),
        ]);
    }

    protected function getAccessTokenResponse(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        $body = json_decode((string) $response->getBody(), associative: true);

        // Decode id_token payload to surface extended claims (role, etc.).
        // Signature verification is handled by the provider over HTTPS.
        if (isset($body['id_token'])) {
            $parts = explode('.', $body['id_token']);
            if (count($parts) === 3) {
                $payload = json_decode(
                    base64_decode(strtr($parts[1], '-_', '+/')),
                    associative: true
                );
                if (is_array($payload)) {
                    $this->idTokenClaims = $payload;
                }
            }
        }

        return $body;
    }

    protected function getUserByToken($token): array
    {
        $userinfoEndpoint = $this->discovery()['userinfo_endpoint'];
        $response = $this->getHttpClient()->get($userinfoEndpoint, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        $userinfo = json_decode((string) $response->getBody(), associative: true);

        // Merge: id_token supplies role and custom claims; userinfo is authoritative for identity.
        return array_merge($this->idTokenClaims ?? [], $userinfo);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'sub'),
            'nickname' => Arr::get($user, 'preferred_username'),
            'name' => Arr::get($user, 'name') ?? Arr::get($user, 'preferred_username') ?? Arr::get($user, 'email'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'picture'),
        ]);
    }
}
