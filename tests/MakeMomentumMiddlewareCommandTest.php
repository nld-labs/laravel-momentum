<?php

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use NLD\Momentum\Commands\MakeMomentumMiddlewareCommand;

it('uses expected middleware stub and default namespace', function () {
    $command = new class(app('files')) extends MakeMomentumMiddlewareCommand
    {
        public function exposedGetStub(): string
        {
            return $this->getStub();
        }

        public function exposedGetDefaultNamespace(string $rootNamespace): string
        {
            return $this->getDefaultNamespace($rootNamespace);
        }
    };

    $stub = $command->exposedGetStub();

    expect($stub)->toEndWith('/stubs/middleware.stub');
    expect(file_exists($stub))->toBeTrue();
    expect($command->exposedGetDefaultNamespace('App'))->toBe('App\\Http\\Middleware');
});

it('defines expected arguments and options', function () {
    $command = new class(app('files')) extends MakeMomentumMiddlewareCommand
    {
        public function exposedGetArguments(): array
        {
            return $this->getArguments();
        }

        public function exposedGetOptions(): array
        {
            return $this->getOptions();
        }
    };

    $arguments = $command->exposedGetArguments();
    $options = $command->exposedGetOptions();

    expect($arguments)->toHaveCount(1);
    expect($arguments[0])->toBe([
        'name',
        InputArgument::OPTIONAL,
        'Name of the Middleware that should be created',
        'HandleInertiaRequests',
    ]);

    expect($options)->toHaveCount(1);
    expect($options[0])->toBe([
        'force',
        null,
        InputOption::VALUE_NONE,
        'Create the class even if the Middleware already exists',
    ]);
});
