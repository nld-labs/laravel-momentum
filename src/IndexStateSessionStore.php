<?php

declare(strict_types=1);

namespace NLD\Momentum;

use Illuminate\Support\Str;

/**
 * Stores index state in session using a controller-specific prefix.
 */
class IndexStateSessionStore
{
    protected string $prefix;

    /**
     * Create a session store scoped to the given controller class.
     */
    public function __construct(string $controllerClass)
    {
        $sessionKeyPrefix = config('momentum.sessionKeyPrefix', '--saved--');
        $this->prefix = "{$controllerClass}{$sessionKeyPrefix}";
    }

    /**
     * Get a persisted value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return session($this->prefix.$key, $default);
    }

    /**
     * Get all persisted values for the current controller.
     */
    public function all(): array
    {
        $params = [];

        foreach (session()->all() as $key => $value) {
            if (Str::startsWith($key, $this->prefix)) {
                $params[substr($key, strlen($this->prefix))] = $value;
            }
        }

        return $params;
    }

    /**
     * Persist a single value by key.
     */
    public function put(string $key, mixed $value): void
    {
        session()->put($this->prefix.$key, $value);
    }

    /**
     * Remove a persisted value by key.
     */
    public function forget(string $key): void
    {
        session()->forget($this->prefix.$key);
    }

    /**
     * Persist configured request parameters and forget missing ones.
     */
    public function persistFromRequest(IndexRequestState $indexState): void
    {
        foreach ($indexState->getPersistedParamNames() as $key) {
            if (request()->has($key)) {
                $this->put($key, request($key));
            } else {
                $this->forget($key);
            }
        }
    }
}
