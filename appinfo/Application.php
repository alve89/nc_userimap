<?php
declare(strict_types=1);

namespace OCA\UserIMAP\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\UserIMAP\Service\UserIMAPService;
use OCA\UserIMAP\IMAP;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'userimap';

    public function __construct(array $params = []) {
        parent::__construct(self::APP_ID, $params);
    }

    public function register(IRegistrationContext $context): void {
        // Services mit korrekter Dependency Injection registrieren
        $context->registerService(UserIMAPService::class, function ($container) {
            return new UserIMAPService($container->get(IConfig::class));
        });

        $context->registerService(IMAP::class, function ($container) {
            return new IMAP(
                $container->get(UserIMAPService::class),
                $container->get(LoggerInterface::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        // User Backend registrieren - der moderne Weg
        $container = $context->getAppContainer();
        $serverContainer = $context->getServerContainer();
        
        $userManager = $serverContainer->getUserManager();
        $backend = $container->get(IMAP::class);
        
        $userManager->registerBackend($backend);
        
        // Success-Log für Monitoring
        $logger = $container->get(LoggerInterface::class);
        // $logger->info('UserIMAP backend registered successfully via modern Bootstrap');
    }
}
