<?php

use NLD\Momentum\IndexRequestState;
use NLD\Momentum\Tests\Support\TestController;

beforeEach(function () {
    $this->controller = new TestController;
});

describe('resolve()', function () {
    it('returns empty array by default', function () {
        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve())->toBe([]);
    });

    it('uses indexConfig from controller', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'email', 'direction' => 'desc']],
            'params' => 'first,second',
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve())->toBe([
            'searchable' => true,
            'search' => null,
            'sortable' => ['name', 'email'],
            'sort' => [
                ['field' => 'email', 'direction' => 'desc'],
            ],
            'defaultSort' => [
                ['field' => 'email', 'direction' => 'desc'],
            ],
            'params' => [
                'first' => null,
                'second' => null,
            ],
        ]);
    });

    it('uses request params to build indexConfig', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'email', 'direction' => 'desc']],
            'params' => 'first,second',
        ]);

        request()->merge([
            'search' => 'test',
            'sort' => 'name!asc',
            'first' => 'one',
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve())->toBe([
            'searchable' => true,
            'search' => 'test',
            'sortable' => ['name', 'email'],
            'sort' => [
                ['field' => 'name', 'direction' => 'asc'],
            ],
            'defaultSort' => [
                ['field' => 'email', 'direction' => 'desc'],
            ],
            'params' => [
                'first' => 'one',
                'second' => null,
            ],
        ]);
    });

    it('caches parsed result', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        $meta->resolve();

        $this->controller->setProperty('indexConfig', [
            'sortable' => 'ignored',
        ]);

        expect($meta->resolve())->toBe([
            'sortable' => ['name', 'email'],
            'sort' => [],
            'defaultSort' => [],
        ]);
    });

    it('parses typed params with int casting', function () {
        $this->controller->setProperty('indexConfig', [
            'params' => 'count:int',
        ]);

        request()->merge(['count' => '5']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['params']['count'])->toBe(5);
    });

    it('keeps missing typed int params as null', function () {
        $this->controller->setProperty('indexConfig', [
            'params' => 'count:int',
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['params']['count'])->toBeNull();
    });

    it('parses typed params with bool casting', function () {
        $this->controller->setProperty('indexConfig', [
            'params' => 'active:bool',
        ]);

        request()->merge(['active' => '1']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['params']['active'])->toBeTrue();
    });

    it('handles array sortable values', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => ['name', 'email'],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sortable'])->toBe(['name', 'email']);
    });

    it('ignores sort fields not in sortable list', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'unknown!desc,name!asc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
        ]);
    });

    it('handles comma-separated sort values from request', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'name!asc,email!desc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('trims whitespace from field names', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => ' name ! asc , email ! asc ']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
            ['field' => 'email', 'direction' => 'asc'],
        ]);
    });

    it('rejects empty field names from parsing', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'name!asc,,email!asc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
            ['field' => 'email', 'direction' => 'asc'],
        ]);
    });

    it('validates defaultSort against sortable allowlist', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'invalid_field', 'direction' => 'desc']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['defaultSort'])->toBe([]);
    });

    it('accepts valid defaultSort from allowlist', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'name', 'direction' => 'asc']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['defaultSort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
        ]);
    });

    it('accepts defaultSort with desc direction', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'email', 'direction' => 'desc']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['defaultSort'])->toBe([
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('rejects defaultSort with invalid direction', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'name', 'direction' => 'invalid']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['defaultSort'])->toBe([]);
    });

    it('ignores sort fields with invalid direction', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'name!invalid,email!desc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('defaults sort direction to asc when not provided', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'name,email!desc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'asc'],
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('trims whitespace around sort modifiers', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
        ]);

        request()->merge(['sort' => 'name! desc,email ! desc']);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->resolve()['sort'])->toBe([
            ['field' => 'name', 'direction' => 'desc'],
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('uses validated defaultSort as fallback when sort is not provided', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'email', 'direction' => 'desc']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['sort'])->toBe([
            ['field' => 'email', 'direction' => 'desc'],
        ]);
    });

    it('ignores invalid defaultSort fallback and uses empty sort', function () {
        $this->controller->setProperty('indexConfig', [
            'sortable' => 'name,email',
            'defaultSort' => [['field' => 'invalid_field', 'direction' => 'desc']],
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());
        $parsed = $meta->resolve();

        expect($parsed['sort'])->toBe([]);
    });
});

describe('getPersistedParamNames()', function () {
    it('returns empty array by default', function () {
        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->getPersistedParamNames())->toBe([]);
    });

    it('uses names from indexConfig', function () {
        $this->controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'params' => 'first,second',
        ]);

        $meta = IndexRequestState::fromController($this->controller, request());

        expect($meta->getPersistedParamNames())->toBe(['search', 'sort', 'first', 'second']);
    });
});
