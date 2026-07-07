<?php

declare(strict_types=1);

use Arqel\Marketplace\Services\VersionMatcher;

it('reports affected when the installed version satisfies the constraint', function () {
    expect(VersionMatcher::isAffected('1.0.0', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.3.0', '>=1.0.1,<1.5'))->toBeTrue();
});

it('reports NOT affected when the installed version is outside the constraint', function () {
    expect(VersionMatcher::isAffected('2.5.0', '<2.0'))->toBeFalse();
    expect(VersionMatcher::isAffected('1.6.0', '>=1.0.1,<1.5'))->toBeFalse();
});

it('fails safe to affected when the installed version is unknown', function () {
    expect(VersionMatcher::isAffected(null, '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('   ', '<2.0'))->toBeTrue();
});

it('fails safe to affected when the constraint is empty (all versions)', function () {
    expect(VersionMatcher::isAffected('1.0.0', ''))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', '   '))->toBeTrue();
});

it('fails safe to affected when the version or constraint is unparseable', function () {
    expect(VersionMatcher::isAffected('not-a-version', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', 'garbage!!'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', '>=1.0.0@badstability'))->toBeTrue();
});
