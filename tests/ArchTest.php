<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

arch('globals')
    ->expect(['dd', 'dump'])
    ->not->toBeUsed();

// arch('Cline\Forrst\Clients')
//     ->expect('Cline\Forrst\Clients')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Contracts')
//     ->expect('Cline\Forrst\Contracts')
//     ->toUseStrictTypes()
//     ->toBeInterfaces();

// arch('Cline\Forrst\Data')
//     ->expect('Cline\Forrst\Data')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->ignoring([
//         Cline\Forrst\Data\AbstractContentDescriptorData::class,
//         Cline\Forrst\Data\AbstractData::class,
//     ])
//     ->toHaveSuffix('Data')
//     ->toExtend(Spatie\LaravelData\Data::class);

// arch('Cline\Forrst\Exceptions')
//     ->expect('Cline\Forrst\Exceptions')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->ignoring([
//         Cline\Forrst\Exceptions\AbstractRequestException::class,
//         Cline\Forrst\Exceptions\Concerns\RendersThrowable::class,
//     ]);

// arch('Cline\Forrst\Facades')
//     ->expect('Cline\Forrst\Facades')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Http')
//     ->expect('Cline\Forrst\Http')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Jobs')
//     ->expect('Cline\Forrst\Jobs')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->toBeReadonly();

// arch('Cline\Forrst\Functions')
//     ->expect('Cline\Forrst\Functions')
//     ->toUseStrictTypes();

// arch('Cline\Forrst\Mixins')
//     ->expect('Cline\Forrst\Mixins')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->toBeReadonly();

// arch('Cline\Forrst\Normalizers')
//     ->expect('Cline\Forrst\Normalizers')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->toBeReadonly()
//     ->toHaveSuffix('Normalizer');

// arch('Cline\Forrst\QueryBuilders')
//     ->expect('Cline\Forrst\QueryBuilders')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->ignoring('Cline\Forrst\QueryBuilders\Concerns');

// arch('Cline\Forrst\Repositories')
//     ->expect('Cline\Forrst\Repositories')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Requests')
//     ->expect('Cline\Forrst\Requests')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Rules')
//     ->expect('Cline\Forrst\Rules')
//     ->toUseStrictTypes()
//     ->toBeFinal();

// arch('Cline\Forrst\Servers')
//     ->expect('Cline\Forrst\Servers')
//     ->toUseStrictTypes()
//     ->toBeAbstract()
//     ->ignoring(ConfigurationServer::class);

// arch('Cline\Forrst\Transformers')
//     ->expect('Cline\Forrst\Transformers')
//     ->toUseStrictTypes()
//     ->toBeFinal()
//     ->toHaveSuffix('Transformer');
