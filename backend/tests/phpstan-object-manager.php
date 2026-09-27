<?php

declare(strict_types=1);

// Hands PHPStan's Doctrine extension the real EntityManager, so DQL and findBy() calls are checked against
// the mapping (phpstan.dist.neon). Never used at runtime.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
