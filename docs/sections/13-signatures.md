## Ordered Signatures

Payment gateways, banks and webhook providers often sign a callback by joining selected fields in a fixed order, then hashing or HMAC-signing the result. `Fingerprint::signature()` reproduces that message exactly.

This page uses `at()` from [Paths](#paths), the rules from [Normalization](#normalization), and `algorithm()` and `hmac()` from [Hashing & HMAC](#hashing). Nothing here is specific to one provider: you describe the provider's format, and the package builds it. Signatures are a separate pipeline from fingerprints, though: **nothing is sorted**, and the order of `fields()` is the order of the message.

### Build, Sign, Verify

**Build the message.** List the fields in the provider's order. `message()` shows the exact string that will be signed:

```php
use Pharaonic\Fingerprint\Fingerprint;

Fingerprint::signature(['amount_cents' => 1050, 'currency' => 'EGP', 'success' => true])
    ->fields(['amount_cents', 'currency', 'success'])
    ->message();
// "1050EGPtrue"
```

**Sign it.** Add the provider's algorithm and your secret, then call `make()`:

```php
$signature = Fingerprint::signature($payload)
    ->fields(['amount_cents', 'currency', 'success'])
    ->algorithm('sha512')
    ->hmac($secret);

$signature->make(); // the signature
```

**Verify what you received.** `verify()` compares in constant time:

```php
$signature->verify($received); // true or false
```

### Selecting Fields

`fields()` takes dot paths, without wildcards, in exactly the protocol's order. Calling it again replaces the list. The payload may be an array, an `ArrayAccess`, a `JsonSerializable`, or an object with public properties, such as the `stdClass` that `json_decode()` returns. Private and protected properties are never read.

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

A string key in `fields()`, like `'amount' => 'order.amount'` above, renames the field in `keyValue()` output.

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

The difference shows in the message. Here the field `b` is missing:

```php
$signature = Fingerprint::signature(['a' => '1', 'c' => '3'])
    ->fields(['a', 'b', 'c'])
    ->separator('|');

$signature->onMissing('empty')->message(); // "1||3"
$signature->onMissing('skip')->message();  // "1|3"
$signature->onMissing('null')->message();  // "1|null|3"
```

A closure decides per field:

```php
->onNull(fn (string $field) => $field === 'note' ? '' : null)
```

A path through a `null` or a scalar counts as missing. Exception messages name the field path, never its value.

### When the Signature Doesn't Match

Print the message and compare it with the provider's documentation:

```php
Fingerprint::signature($payload)->fields($fields)->message();
```

Check the field order, the separators, how booleans and amounts are written (`true` or `1`, `1050` or `10.50`), and whether missing fields should be empty or skipped. Then check the secret and the algorithm.

**Next:** [Signature Examples](#signature-examples): complete examples for a webhook, Paymob and Kashier.
