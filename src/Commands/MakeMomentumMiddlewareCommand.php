<?php

declare(strict_types=1);

namespace NLD\Momentum\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Momentum-ready Inertia middleware class.
 */
#[AsCommand(name: 'momentum:middleware')]
class MakeMomentumMiddlewareCommand extends GeneratorCommand
{
    protected $name = 'momentum:middleware';

    protected $description = 'Create new Momentum middleware';

    protected $type = 'Middleware';

    /**
     * Resolve the middleware stub file path.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/middleware.stub';
    }

    /**
     * Place generated middleware under the HTTP middleware namespace.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Http\Middleware';
    }

    /**
     * Define generator command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::OPTIONAL, 'Name of the Middleware that should be created', 'HandleInertiaRequests'],
        ];
    }

    /**
     * Define generator command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the Middleware already exists'],
        ];
    }
}
