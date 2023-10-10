<?php

namespace WpPasskeys\Credentials;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

interface CredentialEntityInterface
{
    /**
     * Creates a PublicKeyCredentialRpEntity object.
     *
     * @return PublicKeyCredentialRpEntity The created PublicKeyCredentialRpEntity object.
     */
    public function createRpEntity(): PublicKeyCredentialRpEntity;

    /**
     * Creates a WordPress user entity of type PublicKeyCredentialUserEntity.
     *
     * @param string $userLogin
     *
     * @return PublicKeyCredentialUserEntity The created or retrieved WebAuthn user entity.
     */
    public function createUserEntity(string $userLogin): PublicKeyCredentialUserEntity;
}
