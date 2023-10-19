<?php

namespace WpPasskeys\Credentials;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WpPasskeys\Utilities;

class CredentialEntity implements CredentialEntityInterface
{
    public function createRpEntity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            get_bloginfo('name'),
            Utilities::getHostname(),
            null
        );
    }

    public function createUserEntity(string $userLogin): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $userLogin,
            $this->generateBinaryId(),
            $userLogin,
            null
        );
    }

    /**
     * Generate a binary ID using wp_generate_uuid4() and convert it to binary.
     *
     * @return string The binary ID.
     */
    public function generateBinaryId(): string
    {
        $encodedValue = '';
        $uuid = wp_generate_uuid4();
        $binaryUuId = hex2bin(str_replace('-', '', $uuid));
        if($binaryUuId !== false ) {
            $encodedValue = base64_encode($binaryUuId);
        }

        return $encodedValue;
    }

}