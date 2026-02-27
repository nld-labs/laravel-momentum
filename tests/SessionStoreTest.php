<?php

use NLD\Momentum\IndexRequestState;
use NLD\Momentum\IndexStateSessionStore;
use NLD\Momentum\Tests\Support\TestController;

beforeEach(function () {
    $this->store = new IndexStateSessionStore(TestController::class);
});

describe('put() and get()', function () {
    it('saves and retrieves param value', function () {
        $this->store->put('test1', 11);

        expect($this->store->get('test1'))->toBe(11);
    });

    it('saves and retrieves multiple param values', function () {
        $this->store->put('test1', 11);
        $this->store->put('test2', 'saved');

        expect($this->store->get('test1'))->toBe(11);
        expect($this->store->get('test2'))->toBe('saved');
    });

    it('returns default value if param is not saved', function () {
        expect($this->store->get('unknown', 'testing'))->toBe('testing');
    });

    it('returns null if param is not saved and no default value', function () {
        expect($this->store->get('unknown'))->toBeNull();
    });
});

describe('all()', function () {
    it('returns all saved params', function () {
        $this->store->put('test1', 11);
        $this->store->put('test2', 'saved');

        expect($this->store->all())->toBe([
            'test1' => 11,
            'test2' => 'saved',
        ]);
    });

    it('returns empty array when nothing saved', function () {
        expect($this->store->all())->toBe([]);
    });
});

describe('forget()', function () {
    it('forgets saved param value', function () {
        $this->store->put('test1', 11);
        $this->store->forget('test1');

        expect($this->store->get('test1'))->toBeNull();
    });
});

describe('persistFromRequest()', function () {
    it('saves param values from request', function () {
        $controller = new TestController;
        $controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'params' => 'first,second',
        ]);

        request()->merge([
            'search' => 'test',
            'sort' => 'name!asc',
            'first' => 'one',
            'second' => 'two',
            'ignored' => 'value',
        ]);

        $meta = IndexRequestState::fromController($controller, request());
        $this->store->persistFromRequest($meta);

        expect($this->store->all())->toBe([
            'search' => 'test',
            'sort' => 'name!asc',
            'first' => 'one',
            'second' => 'two',
        ]);
    });

    it('forgets already saved param values if not in request', function () {
        $controller = new TestController;
        $controller->setProperty('indexConfig', [
            'searchable' => true,
            'sortable' => 'name,email',
            'params' => 'first,second',
        ]);

        $this->store->put('search', 'test');
        $this->store->put('sort', 'name!asc');
        $this->store->put('first', 'one');
        $this->store->put('second', 'two');

        expect($this->store->all())->toBe([
            'search' => 'test',
            'sort' => 'name!asc',
            'first' => 'one',
            'second' => 'two',
        ]);

        $meta = IndexRequestState::fromController($controller, request());
        $this->store->persistFromRequest($meta);

        expect($this->store->all())->toBe([]);
    });
});
