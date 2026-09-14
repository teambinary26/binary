<?php

use App\Support\OcrExtractor;

$philId = <<<'TEXT'
REPUBLIKA NG PILIPINAS
Republic of the Philippines:
PAMBANSANG PAGKAKAKILANLAN
Philippine National ID
APELYIDO/LAST NAME
BLANQUERA
MGA PANGALAN/GIVEN NAMES
JOHN LLOYD
GITNANG APELYIDO/ MIDDLE NAME
PACULAN
PETSA NG KAPANGANAKAN/DATE OF BIRTH
April 01, 2000
DIGITAL ID NUMBER
DQQ329
TIRAHAN/ADDRESS
ONE 4, SANTA ELENA, BULA, CAMARINES SUR
TEXT;

test('philippine national id text yields name and birth date', function () use ($philId) {
    $extractor = new OcrExtractor();

    $name = $extractor->value($philId, 'full_name', 'JOHN LLOYD Paculan BLANQUERA');
    $birthDate = $extractor->value($philId, 'date_of_birth', '2000-04-12');

    expect($name)->toBe('JOHN LLOYD PACULAN BLANQUERA')
        ->and($birthDate)->toBe('April 01, 2000');

    [$nameStatus] = $extractor->compare('JOHN LLOYD Paculan BLANQUERA', $name, 'full_name');
    [$dateStatus] = $extractor->compare('2000-04-12', $birthDate, 'date_of_birth');
    [$matchingDate] = $extractor->compare('2000-04-01', $birthDate, 'date_of_birth');

    expect($nameStatus)->toBe('matched')
        ->and($dateStatus)->toBe('mismatch')
        ->and($matchingDate)->toBe('matched');
});

test('the same calendar day matches across date formats', function () {
    $extractor = new OcrExtractor();

    $formats = [
        '2000-04-12',
        'April 12, 2000',
        'April 12 2000',
        '12 April 2000',
        '12 Apr 2000',
        '04/12/2000',
        '12/04/2000',
        '04-12-2000',
        '12-04-2000',
    ];

    foreach ($formats as $format) {
        [$status] = $extractor->compare('2000-04-12', $format, 'date_of_birth');
        expect($status)->toBe('matched');
    }

    [$mismatch] = $extractor->compare('2000-04-12', 'April 01, 2000', 'date_of_birth');
    expect($mismatch)->toBe('mismatch');
});

test('colon labeled enrollment text still extracts fields', function () {
    $extractor = new OcrExtractor();
    $text = "Certificate of Enrollment\nStudent Name: Ana Cruz\nSchool: ABC University\nCurrently Enrolled";

    expect($extractor->value($text, 'full_name', 'Ana Cruz'))->toBe('Ana Cruz')
        ->and($extractor->value($text, 'school_name', 'ABC University'))->toBe('ABC University');
});
