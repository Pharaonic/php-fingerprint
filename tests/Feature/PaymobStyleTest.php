<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

// A transaction callback shaped like Paymob's, with invented values and an invented test key.
$payload = json_decode((string) json_encode([
    'obj' => [
        'id' => 192036465,
        'pending' => false,
        'amount_cents' => 100000,
        'success' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'is_refunded' => false,
        'is_3d_secure' => true,
        'integration_id' => 4097558,
        'has_parent_transaction' => false,
        'order' => ['id' => 217503754],
        'created_at' => '2024-06-13T11:33:44.592345',
        'currency' => 'EGP',
        'error_occured' => false,
        'owner' => 302852,
        'source_data' => ['type' => 'card', 'pan' => '2346', 'sub_type' => 'MasterCard'],
    ],
]));

$fields = [
    'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
    'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
    'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
    'source_data.type', 'success',
];

$message = '1000002024-06-13T11:33:44.592345EGPfalsefalse1920364654097558truefalsefalsefalsetruefalse'
    . '217503754302852false2346MasterCardcardtrue';

it('builds the protocol message exactly', function () use ($payload, $fields, $message) {
    expect(Fingerprint::signature($payload->obj)->fields($fields)->message())->toBe($message);
});

it('signs it with HMAC-SHA512 and verifies the callback', function () use ($payload, $fields, $message) {
    $signature = Fingerprint::signature($payload->obj)->fields($fields)->algorithm('sha512')->hmac('test-hmac-secret');
    $received = hash_hmac('sha512', $message, 'test-hmac-secret');

    expect($signature->make())->toBe($received)
        ->and($signature->verify($received))->toBeTrue();
});

it('rejects a tampered callback', function () use ($payload, $fields, $message) {
    $tampered = clone $payload->obj;
    $tampered->amount_cents = 100001;
    $received = hash_hmac('sha512', $message, 'test-hmac-secret');

    expect(Fingerprint::signature($tampered)->fields($fields)->algorithm('sha512')->hmac('test-hmac-secret')->verify($received))->toBeFalse();
});
