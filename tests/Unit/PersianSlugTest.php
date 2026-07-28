<?php

use App\Support\PersianSlug;

test('Persian letters remain in generated slugs', function () {
    expect(PersianSlug::make('ایرپاد و هندزفری'))
        ->toBe('ایرپاد-و-هندزفری');
});

test('Arabic variants are normalized to Persian letters', function () {
    expect(PersianSlug::make('كالاى ديجيتال'))
        ->toBe('کالای-دیجیتال');
});
