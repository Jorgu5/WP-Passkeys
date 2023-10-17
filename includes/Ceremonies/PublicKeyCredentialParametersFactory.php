<?php

namespace WpPasskeys\Ceremonies;

use Webauthn\PublicKeyCredentialParameters;

class PublicKeyCredentialParametersFactory
{
    public function create(string $type, int $algorithmNumber): PublicKeyCredentialParameters
    {
        return new PublicKeyCredentialParameters($type, $algorithmNumber);
    }
}