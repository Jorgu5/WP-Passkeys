<?php

namespace WpPasskeys;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use WpPasskeys\Admin\UserSettings;
use WpPasskeys\Ceremonies\AuthEndpoints;
use WpPasskeys\Ceremonies\RegisterEndpoints;
use WpPasskeys\Credentials\CredentialEntity;
use WpPasskeys\Credentials\CredentialEndpoints;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Credentials\UsernameHandler;
use WpPasskeys\RestApi\RestApiHandler;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\Ceremonies\PublicKeyCredentialParameters;
use WpPasskeys\Ceremonies\PublicKeyCredentialParametersFactory;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialEndpointsInterface;
use WpPasskeys\Ceremonies\AuthEndpointsInterface;
use WpPasskeys\Ceremonies\RegisterEndpointsInterface;
use WpPasskeys\AlgorithmManager\AlgorithmManagerInterface;

class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        RestApiHandler::class,
        AuthEndpointsInterface::class,
        RegisterEndpointsInterface::class,
        CredentialEndpointsInterface::class,
        PublicKeyCredentialParameters::class,
        PublicKeyCredentialParametersFactory::class,
        CredentialHelperInterface::class,
        AlgorithmManagerInterface::class,
        SessionHandlerInterface::class,
        ExtensionOutputCheckerHandler::class,
        AuthenticatorAssertionResponseValidator::class,
        PublicKeyCredentialLoader::class,
        AttestationObjectLoader::class,
        AttestationStatementSupportManager::class,
        CredentialEntityInterface::class,
        Utilities::class,
        UserSettings::class,
    ];

    public function provides(string $id): bool
    {
        return in_array($id, $this->provides, true);
    }

    public function register(): void
    {
        $container = $this->getContainer();
        $container->add(AttestationStatementSupportManager::class, function () {
            $manager = AttestationStatementSupportManager::create();
            $manager->add(NoneAttestationStatementSupport::create());

            return $manager;
        });

        $container->add(UsernameHandler::class)
                  ->addArgument(SessionHandlerInterface::class);

        $container->add(AttestationObjectLoader::class)
                  ->addArgument(AttestationStatementSupportManager::class);
        $container->add(PublicKeyCredentialLoader::class)
                  ->addArgument(AttestationObjectLoader::class);
        $container->add(AuthenticatorAssertionResponseValidator::class)
                  ->addArguments([
                      null,
                      null,
                      ExtensionOutputCheckerHandler::class,
                      null,
                  ]);
        $container->add(ExtensionOutputCheckerHandler::class, ExtensionOutputCheckerHandler::create());
        $container->add(CredentialHelperInterface::class, CredentialHelper::class)
                  ->addArguments([
                      SessionHandlerInterface::class,
                  ]);
        $container->add(PublicKeyCredentialParameters::class)
                  ->addArguments([
                      AlgorithmManagerInterface::class,
                      PublicKeyCredentialParametersFactory::class,
                  ]);
        $container->add(SessionHandlerInterface::class, SessionHandler::class);
        $container->add(AlgorithmManagerInterface::class, AlgorithmManager::class);
        $container->add(Utilities::class);
        $container->add(CredentialEntityInterface::class, CredentialEntity::class);
        $container->add(CredentialEndpointsInterface::class, CredentialEndpoints::class)
                  ->addArgument(SessionHandlerInterface::class);
        $container->add(PublicKeyCredentialParametersFactory::class, PublicKeyCredentialParametersFactory::class);


        $container->add(AuthEndpointsInterface::class, AuthEndpoints::class)
                  ->addArguments([
                      PublicKeyCredentialLoader::class,
                      AuthenticatorAssertionResponseValidator::class,
                      CredentialHelperInterface::class,
                      AlgorithmManagerInterface::class,
                      Utilities::class,
                      SessionHandlerInterface::class,
                  ]);

        $container->add(RegisterEndpointsInterface::class, RegisterEndpoints::class)
                  ->addArguments([
                      CredentialHelperInterface::class,
                      CredentialEntityInterface::class,
                      Utilities::class,
                      UsernameHandler::class,
                      PublicKeyCredentialParameters::class,
                      PublicKeyCredentialLoader::class,
                      AttestationStatementSupportManager::class,
                  ]);

        $container->add(RestApiHandler::class)
                  ->addArguments([
                      AuthEndpointsInterface::class,
                      RegisterEndpointsInterface::class,
                      CredentialEndpointsInterface::class,
                  ]);

        $container->add(UserSettings::class)
                  ->addArgument(CredentialHelperInterface::class);
    }
}
