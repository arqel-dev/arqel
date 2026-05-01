<?php

declare(strict_types=1);

namespace Arqel\Cli;

use Arqel\Cli\Commands\NewCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public const string NAME = 'arqel';

    public const string VERSION = '0.1.0-dev';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->addCommand(new NewCommand());
    }
}
