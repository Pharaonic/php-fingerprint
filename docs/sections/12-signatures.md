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

### Example: Verifying a Paymob Callback

When a payment finishes, Paymob sends your server a **transaction processed callback**: a `POST` request with a JSON body and the signature in the URL (`?hmac=...`). Anyone can call your callback URL, so check that signature before you trust the request.

**Step 1: what Paymob sends.** The body looks like this (shortened):

```json title="POST /paymob/callback?hmac=3f9a...e1"
{
    "type": "TRANSACTION",
    "obj": {
        "id": 192036465,
        "amount_cents": 100000,
        "currency": "EGP",
        "success": true,
        "pending": false,
        "created_at": "2024-06-13T11:33:44.592345",
        "order": { "id": 217503754 },
        "source_data": { "type": "card", "pan": "2346", "sub_type": "MasterCard" }
    }
}
```

**Step 2: how Paymob signs it.** Paymob takes 20 fields of `obj`, always in the same (alphabetical) order, writes each value as text (`true`/`false` for booleans), joins them with nothing in between, and signs the result with HMAC-SHA512 and your HMAC secret from the Paymob dashboard.

**Step 3: do the same and compare.** List the fields in Paymob's order, and let the package build the text and compare the signatures:

- ===Plain PHP

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

  if (! $valid) {
      http_response_code(403);
      exit;
  }

  // The callback really comes from Paymob: update the order here.
  ```

- ===Laravel

  ```php title="app/Http/Controllers/PaymobCallbackController.php"
  namespace App\Http\Controllers;

  use Illuminate\Http\Request;
  use Pharaonic\Fingerprint\Fingerprint;

  class PaymobCallbackController extends Controller
  {
      private const FIELDS = [
          'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
          'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
          'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
          'source_data.type', 'success',
      ];

      public function __invoke(Request $request)
      {
          $transaction = json_decode($request->getContent())->obj;

          $valid = Fingerprint::signature($transaction)
              ->fields(self::FIELDS)
              ->algorithm('sha512')
              ->hmac(config('services.paymob.hmac'))
              ->verify((string) $request->query('hmac'));

          abort_unless($valid, 403);

          // The callback really comes from Paymob: update the order here.

          return response()->noContent();
      }
  }
  ```

**Step 4: trust the data, not just the signature.** A valid signature proves Paymob sent the callback. Still check that `success` is `true`, and that `amount_cents` and `currency` match the order in your database before marking it paid.

### When the Signature Doesn't Match

Print the text that is signed and compare it with what Paymob describes:

```php
Fingerprint::signature($transaction)->fields($fields)->message();
// "1000002024-06-13T11:33:44.592345EGPfalsefalse192036465..."
```

The usual causes are the wrong HMAC secret, a field missing from the list or in the wrong place, or passing the whole body instead of its `obj` part.

:::info Other Paymob callbacks
Paymob also redirects the customer back to your site with the transaction in the query string. That callback lists its fields differently, and PHP turns dots in query names into underscores (`source_data.pan` arrives as `source_data_pan`), so check Paymob's documentation for its field list. The package has no Paymob-specific code: the same steps work for any gateway that signs ordered fields.
:::
