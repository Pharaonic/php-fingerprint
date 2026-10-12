## Ordered Signatures

Payment gateways, banks and webhook providers often sign a callback by joining selected fields in a fixed order, then hashing or HMAC-signing the result. `Fingerprint::signature()` reproduces that message exactly. It is a separate pipeline from fingerprints: **nothing is sorted**, and the order of `fields()` is the order of the message.

```php
use Pharaonic\Fingerprint\Fingerprint;

$signature = Fingerprint::signature($payload)
    ->fields(['amount_cents', 'currency', 'order.id', 'success'])
    ->algorithm('sha512')
    ->hmac($secret);

$signature->message();          // the exact string that is signed
$signature->make();             // the signature
$signature->verify($received);  // constant-time check
```

### Selecting Fields

`fields()` takes dot paths, without wildcards, in exactly the protocol's order. Calling it again replaces the list. The payload may be an array, an `ArrayAccess`, a `JsonSerializable`, or an object with public properties, such as the `stdClass` that `json_decode()` returns. Private and protected properties are never read.

A string key renames the field in `keyValue()` output:

```php
->fields(['amount' => 'order.amount_cents', 'currency'])
```

### Shaping the Message

| Method | Effect | Default |
| --- | --- | --- |
| `valuesOnly()` | `value1 . separator . value2 …` | yes |
| `keyValue()` | `key1 . keyValueSeparator . value1 . separator . key2 …` | |
| `separator($string)` | Between fields | `''` |
| `keyValueSeparator($string)` | Between a key and its value | `'='` |

```php
Fingerprint::signature(['order' => ['amount' => 1050], 'currency' => 'EGP', 'paid' => true])
    ->fields(['amount' => 'order.amount', 'currency', 'paid'])
    ->keyValue()->separator('&')
    ->message();
// "amount=1050&currency=EGP&paid=true"
```

### How Values Become Strings

| Value | String |
| --- | --- |
| string | as it is |
| int | decimal: `1050` |
| bool | `true` or `false` |
| float | needs `precision()` on its field: `10.50` |
| array, object, date | needs a `transform()` that returns one of the types above |

```php
Fingerprint::signature(['total' => 10.5])
    ->fields(['total'])
    ->at('total')->precision(2)
    ->message();
// "10.50"
```

A float without `precision()` throws an `UnsupportedValueException`, because PHP 8.0 turns floats into text differently depending on the `precision` ini setting. Use `at()` to apply `trim()`, `normalizeWhitespace()`, `lower()`, `upper()`, `unicode()`, `precision()` and `transform()` to one field.

### Missing and Null Fields

By default, a missing field throws a `MissingFieldException` and a null field throws an `UnsupportedValueException`, so a protocol change never goes unnoticed. Choose otherwise with `onMissing()` and `onNull()`, independently:

| Strategy | The field becomes |
| --- | --- |
| `'throw'` | an exception (the default) |
| `'empty'` | an empty string |
| `'null'` | the string `null` |
| `'skip'` | nothing: the field is left out, with its separator |
| a closure | the string it returns for the field path, or nothing when it returns `null` |

```php
->onMissing('empty')
->onNull(fn (string $field) => $field === 'note' ? '' : null)
```

A path through a `null` or a scalar counts as missing. Exception messages name the field path, never its value.

### Paymob-Style Callback

This example verifies a transaction callback with Paymob's documented field order: 20 fields in alphabetical order, joined without a separator, signed with HMAC-SHA512:

```php title="public/paymob-callback.php"
use Pharaonic\Fingerprint\Fingerprint;

$transaction = json_decode(file_get_contents('php://input'))->obj;

$valid = Fingerprint::signature($transaction)
    ->fields([
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success',
    ])
    ->algorithm('sha512')
    ->hmac(getenv('PAYMOB_HMAC_SECRET'))
    ->verify($_GET['hmac'] ?? '');
```

Booleans become `true`/`false`, as Paymob expects. The package has no gateway-specific code, so always check your provider's current documentation for the field list.
