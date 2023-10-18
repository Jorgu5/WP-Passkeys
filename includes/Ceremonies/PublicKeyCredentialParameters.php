<?php

namespace WpPasskeys\Ceremonies;

use WpPasskeys\AlgorithmManager\AlgorithmManager;

class PublicKeyCredentialParameters
{
    public readonly PublicKeyCredentialParametersFactory $publicKeyCredentialParametersFactory;
    public readonly AlgorithmManager $algorithmManager;

    public function __construct(
        AlgorithmManager $algorithmManager,
        PublicKeyCredentialParametersFactory $publicKeyCredentialParametersFactory
    ) {
        $this->algorithmManager = $algorithmManager;
        $this->publicKeyCredentialParametersFactory = $publicKeyCredentialParametersFactory;
    }

    public function get(): array
    {
        $publicKeyCredentialParameters = [];
        $algorithmManagerKeys          = $this->algorithmManager->getAlgorithmIdentifiers();

        foreach ($algorithmManagerKeys as $algorithmNumber) {
            $publicKeyCredentialParameters[] = $this->publicKeyCredentialParametersFactory->create(
                'public-key',
                $algorithmNumber
            );
        }

        return $publicKeyCredentialParameters;
    }
}
