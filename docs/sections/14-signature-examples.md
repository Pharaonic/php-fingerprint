## Signature Examples

These examples use the methods from [Ordered Signatures](#signatures). The package has no code for any particular provider: each example only describes a provider's format with `fields()`, `separator()`, `algorithm()` and `hmac()`. They go from the simplest to the most involved.

### 1. Your Own Webhooks

When you control both sides, the sender and the receiver build the same ordered message from the same fields:

- ===Sender

  ```php title="src/Webhooks/Sender.php"
  use Pharaonic\Fingerprint\Fingerprint;

  $signature = Fingerprint::signature($event)
      ->fields(['id', 'type', 'amount' => 'data.amount', 'currency' => 'data.currency'])
      ->keyValue()->separator('&')
      ->hmac($sharedSecret)
      ->make();

  $http->post($url, $event, ['X-Signature' => $signature]);
  ```

- ===Receiver

  ```php title="src/Webhooks/Receiver.php"
  use Pharaonic\Fingerprint\Fingerprint;

  $event = json_decode($body, true);

  $valid = Fingerprint::signature($event)
      ->fields(['id', 'type', 'amount' => 'data.amount', 'currency' => 'data.currency'])
      ->keyValue()->separator('&')
      ->hmac($sharedSecret)
      ->verify($headers['X-Signature'] ?? '');
  ```

### 2. Paymob Transaction Callback

When a payment finishes, Paymob sends your server a **transaction processed callback**: a `POST` request with a JSON body and the signature in the URL (`?hmac=...`). Anyone can call your callback URL, so check that signature before you trust the request.

**What Paymob sends.** The body looks like this (shortened):

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

**How Paymob signs it.** Paymob takes 20 fields of `obj`, always in the same (alphabetical) order, writes each value as text (`true`/`false` for booleans), joins them with nothing in between, and signs the result with HMAC-SHA512 and your HMAC secret from the Paymob dashboard.

**Do the same and compare.** List the fields in Paymob's order, and let the package build the text and compare the signatures:

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

**Trust the data, not just the signature.** A valid signature proves Paymob sent the callback. Still check that `success` is `true`, and that `amount_cents` and `currency` match the order in your database before marking it paid.

**When it doesn't match**, print the text that is signed and compare it with what Paymob describes:

```php
Fingerprint::signature($transaction)->fields($fields)->message();
// "1000002024-06-13T11:33:44.592345EGPfalsefalse192036465..."
```

The usual causes are the wrong HMAC secret, a field missing from the list or in the wrong place, or passing the whole body instead of its `obj` part.

:::info Other Paymob callbacks
Paymob also redirects the customer back to your site with the transaction in the query string. That callback lists its fields differently, and PHP turns dots in query names into underscores (`source_data.pan` arrives as `source_data_pan`), so check Paymob's documentation for its field list. The package has no Paymob-specific code: the same steps work for any gateway that signs ordered fields.
:::

### 3. Kashier Payment Hash

Kashier's payment page expects a hash of the order, so here you **make** a signature instead of verifying one. Kashier signs the path `/?payment=` followed by the merchant ID, the order ID, the amount and the currency, joined with dots, with HMAC-SHA256 and your payment API key:

```php title="src/Payments/KashierHash.php"
use Pharaonic\Fingerprint\Fingerprint;

$hash = Fingerprint::signature([
        'merchantId' => 'mid-0-1',
        'orderId' => '99',
        'amount' => '20',
        'currency' => 'EGP',
    ])
    ->fields(['merchantId', 'orderId', 'amount', 'currency'])
    ->separator('.')
    ->at('merchantId')->transform(fn (string $mid) => '/?payment='.$mid)
    ->hmac($apiKey);

$hash->message(); // "/?payment=mid-0-1.99.20.EGP"
$hash->make();    // "606a8a1307d64caf4e2e9bb724738f115a8972c27eccb2a8acd9194c357e4bec" with the key "11111"
```

The prefix is not a field, so `transform()` adds it to the first one. The values above are Kashier's own test vector: check your setup against it before going live. Generate the hash on your server, never in the browser, and check [Kashier's documentation](https://developers.kashier.io/docs/direct-api/hashing) for the optional fields some payment types add.

### Your Provider

The same steps work for any provider that signs ordered fields. From its documentation, note:

1. the fields, in order, and whether it joins values only or `key=value` pairs ([Shaping the Message](#signatures));
2. the separator, if any;
3. how it writes booleans, amounts and dates ([How Values Become Strings](#signatures));
4. the algorithm, and whether it uses HMAC with a secret.

Build the message with `message()` first, compare it with an example from the provider, then add `hmac()` and `verify()`.

**Next:** the Reference pages. Start with [Debugging](#debugging) when a result surprises you, or browse the [Examples](#examples).
