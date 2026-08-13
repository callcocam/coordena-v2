<?php

use App\Support\Phone;

test('normalizes ddd plus number with formatting to e164 digits', function () {
    expect(Phone::normalize('(51) 99999-0000'))->toBe('5551999990000');
});

test('keeps numbers that already carry the country code', function () {
    expect(Phone::normalize('+55 51 99999-0000'))->toBe('5551999990000');
});

test('strips leading trunk zeros before normalizing', function () {
    expect(Phone::normalize('051 3222-1111'))->toBe('555132221111');
});

test('normalizes landlines with ten digits', function () {
    expect(Phone::normalize('(51) 3222-1111'))->toBe('555132221111');
});

test('returns null for null empty or non numeric input', function () {
    expect(Phone::normalize(null))->toBeNull();
    expect(Phone::normalize(''))->toBeNull();
    expect(Phone::normalize('sem telefone'))->toBeNull();
});

test('returns null for numbers too short to be complete', function () {
    expect(Phone::normalize('9999-0000'))->toBeNull();
});
