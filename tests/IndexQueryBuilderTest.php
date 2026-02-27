<?php

use Illuminate\Database\Eloquent\Builder;
use NLD\Momentum\IndexQueryBuilder;
use NLD\Momentum\Tests\Support\TestModel;

describe('build()', function () {
    it('returns query builder', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::query(), []);

        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('resolves string class name to query', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, []);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('applies sorting from meta', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'sort' => [
                ['field' => 'name', 'direction' => 'asc'],
                ['field' => 'id', 'direction' => 'desc'],
            ],
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" order by "name" asc, "id" desc');
    });

    it('uses model custom sorting method if available', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'sort' => [
                ['field' => 'some_method', 'direction' => 'desc'],
            ],
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" order by "model_method" desc');
    });

    it('applies search scope', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'searchable' => true,
            'search' => 'test',
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models" where "name" = ?')
            ->getBindings()->toBe(['test']);
    });

    it('does not apply search if not searchable', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'searchable' => false,
            'search' => 'test',
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('does not apply search if search value is null', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'searchable' => true,
            'search' => null,
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('does not apply search if search value is empty string', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'searchable' => true,
            'search' => '   ',
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });

    it('uses custom search scope name', function () {
        $builder = new IndexQueryBuilder('customScope');
        $query = $builder->build(TestModel::class, [
            'searchable' => true,
            'search' => 'something',
        ]);

        expect($query)
            ->toSql()->toBe('select * from "test_models" where "search" = ?')
            ->getBindings()->toBe(['something']);
    });

    it('does not apply sorting if sort array is empty', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, [
            'sort' => [],
        ]);

        expect($query)
            ->toSql()
            ->toBe('select * from "test_models"');
    });
});

describe('resolveQuery() validation', function () {
    it('throws InvalidArgumentException for non-existent class', function () {
        $builder = new IndexQueryBuilder;

        expect(fn () => $builder->build('NonExistentClass', []))
            ->toThrow(InvalidArgumentException::class, 'Invalid model class');
    });

    it('throws InvalidArgumentException for non-Model class', function () {
        $builder = new IndexQueryBuilder;

        expect(fn () => $builder->build(stdClass::class, []))
            ->toThrow(InvalidArgumentException::class, 'Invalid model class');
    });

    it('accepts valid Model class names', function () {
        $builder = new IndexQueryBuilder;
        $query = $builder->build(TestModel::class, []);

        expect($query)
            ->toBeInstanceOf(Builder::class);
    });
});
