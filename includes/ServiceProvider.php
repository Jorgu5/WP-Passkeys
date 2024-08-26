<?php

namespace WpPasskeys;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialSource;
use WpPasskeys\Admin\PasskeysInfoRender;
use WpPasskeys\Admin\UserPasskeysCardRender;
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
use WpPasskeys\Ceremonies\EmailConfirmation;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

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
        PublicKeyCredentialSource::class,
        AttestationObjectLoader::class,
        AttestationStatementSupportManager::class,
        CredentialEntityInterface::class,
        Utilities::class,
        UserSettings::class,
        UserPasskeysCardRender::class,
        PasskeysInfoRender::class,
        EmailConfirmation::class,
        CeremonyStepManagerFactory::class,
        WebauthnSerializerFactory::class,
        AuthenticatorAttestationResponseValidator::class,
    ];

    public function provides(string $id): bool
    {
        return in_array($id, $this->provides, true);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(AttestationStatementSupportManager::class, function () {
            $manager = new AttestationStatementSupportManager();
            $manager->add(new NoneAttestationStatementSupport());

            return $manager;
        });

        $container->add(CeremonyStepManagerFactory::class, function () use ($container) {
            $csmFactory = new CeremonyStepManagerFactory();

            $csmFactory->setAttestationStatementSupportManager(
                $container->get(AttestationStatementSupportManager::class)
            );

            $utilities             = $container->get(Utilities::class);
            $securedRelyingPartyId = $utilities->isLocalhost() ? ["localhost"] : [];
            $csmFactory->setSecuredRelyingPartyId($securedRelyingPartyId);

            return $csmFactory;
        });

        // Add ceremony steps as services
        $container->add('creationCeremony', function () use ($container) {
            return $container->get(CeremonyStepManagerFactory::class)->creationCeremony();
        });

        $container->add('requestCeremony', function () use ($container) {
            return $container->get(CeremonyStepManagerFactory::class)->requestCeremony();
        });

        $container->add(AuthenticatorAttestationResponseValidator::class, function () use ($container) {
            return new AuthenticatorAttestationResponseValidator(
                $container->get('creationCeremony')
            );
        });

        $container->add(AuthenticatorAssertionResponseValidator::class, function () use ($container) {
            return new AuthenticatorAssertionResponseValidator(
                $container->get('requestCeremony')
            );
        });

        $container->add(WebauthnSerializerFactory::class)->addArgument(AttestationStatementSupportManager::class);
        $container->add(EmailConfirmation::class);
        $container->add(PasskeysInfoRender::class);
        $container->add(UserPasskeysCardRender::class)->addArgument(CredentialHelperInterface::class);
        $container->add(UsernameHandler::class)->addArgument(SessionHandlerInterface::class);
        $container->add(AttestationObjectLoader::class)->addArgument(AttestationStatementSupportManager::class);
        $container->add(ExtensionOutputCheckerHandler::class, ExtensionOutputCheckerHandler::create());
        $container->add(CredentialHelperInterface::class, CredentialHelper::class)
            ->addArguments([
                SessionHandlerInterface::class,
                Utilities::class,
                WebauthnSerializerFactory::class,
            ]);
        $container->add(CredentialEntityInterface::class, CredentialEntity::class)->addArgument(Utilities::class);
        $container->add(PublicKeyCredentialParameters::class)->addArguments([
            AlgorithmManagerInterface::class,
            PublicKeyCredentialParametersFactory::class,
        ]);
        $container->add(SessionHandlerInterface::class, SessionHandler::class);
        $container->add(AlgorithmManagerInterface::class, AlgorithmManager::class);
        $container->add(Utilities::class);
        $container->add(CredentialEntityInterface::class, CredentialEntity::class);
        $container->add(CredentialEndpointsInterface::class, CredentialEndpoints::class)->addArguments([
            SessionHandlerInterface::class,
            Utilities::class,
        ]);
        $container->add(PublicKeyCredentialParametersFactory::class, PublicKeyCredentialParametersFactory::class);

        $container->add(AuthEndpointsInterface::class, AuthEndpoints::class)->addArguments([
            AuthenticatorAssertionResponseValidator::class,
            CredentialHelperInterface::class,
            AlgorithmManagerInterface::class,
            Utilities::class,
            SessionHandlerInterface::class,
            WebauthnSerializerFactory::class,
        ]);

        $container->add(RegisterEndpointsInterface::class, RegisterEndpoints::class)->addArguments([
            AuthenticatorAttestationResponseValidator::class,
            CredentialHelperInterface::class,
            CredentialEntityInterface::class,
            Utilities::class,
            UsernameHandler::class,
            PublicKeyCredentialParameters::class,
            UserPasskeysCardRender::class,
            EmailConfirmation::class,
            WebauthnSerializerFactory::class,
        ]);

        $container->add(RestApiHandler::class)->addArguments([
            AuthEndpointsInterface::class,
            RegisterEndpointsInterface::class,
            CredentialEndpointsInterface::class,
            SessionHandlerInterface::class,
        ]);

        $container->add(UserSettings::class)->addArguments([
            CredentialHelperInterface::class,
            UserPasskeysCardRender::class,
            PasskeysInfoRender::class,
        ]);
    }
}
