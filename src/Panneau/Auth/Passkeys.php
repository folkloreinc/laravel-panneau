<?php

namespace Panneau\Auth;

use Illuminate\Contracts\Container\Container;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The WebAuthn ceremonies of passkeys (panneau-js decision 0051), on the actions of
 * `laravel/passkeys`: options in the JSON form of the WebAuthn spec, kept serialized between the
 * two requests of a ceremony, and credentials checked against them.
 *
 * @internal
 */
class Passkeys
{
    public function __construct(private readonly Container $container) {}

    /**
     * Options of a login with any passkey: what the browser gets, and what the session keeps.
     *
     * @return array{browser: array<string, mixed>, serialized: string}
     */
    public function loginOptions(): array
    {
        $options = $this->container->make(GenerateVerificationOptions::class)();

        return ['browser' => WebAuthn::toBrowserArray($options), 'serialized' => WebAuthn::toJson($options)];
    }

    /**
     * @return array{browser: array<string, mixed>, serialized: string}
     */
    public function registrationOptions(PasskeyUser $user): array
    {
        $options = $this->container->make(GenerateRegistrationOptions::class)($user);

        return ['browser' => WebAuthn::toBrowserArray($options), 'serialized' => WebAuthn::toJson($options)];
    }

    /**
     * The passkey of a login credential, checked against the options of the session.
     *
     * @param  array<string, mixed>  $credential
     */
    public function verify(array $credential, string $options): Passkey
    {
        return $this->container->make(VerifyPasskey::class)(
            $this->credential($credential),
            WebAuthn::fromJson($options, PublicKeyCredentialRequestOptions::class),
        );
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    public function store(PasskeyUser $user, string $name, array $credential, string $options): Passkey
    {
        return $this->container->make(StorePasskey::class)(
            $user,
            $name,
            $this->credential($credential),
            WebAuthn::fromJson($options, PublicKeyCredentialCreationOptions::class),
        );
    }

    /**
     * @param  array<string, mixed>  $credential
     */
    private function credential(array $credential): PublicKeyCredential
    {
        try {
            return WebAuthn::fromJson((string) json_encode($credential), PublicKeyCredential::class);
        } catch (Throwable) {
            throw InvalidPasskeyException::make('This passkey could not be read. Try again.');
        }
    }
}
