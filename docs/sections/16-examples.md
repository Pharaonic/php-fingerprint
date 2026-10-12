## Examples

### 1. Skip Unchanged Records in a Sync

Store each record's fingerprint and only push records whose fingerprint changed. Timestamps that change on every read are ignored, and tags are compared regardless of order:

```php title="src/Sync/ProductSync.php"
use Pharaonic\Fingerprint\Fingerprint;

$fingerprints = Fingerprint::for(null)
    ->namespace('product-sync')
    ->ignore(['updated_at', 'variants.*.stock_checked_at'])
    ->at('tags')->unordered()
    ->at('variants')->sortBy('sku');

foreach ($products as $product) {
    $fingerprint = $fingerprints->for($product)->make();

    if ($fingerprint !== $store->lastFingerprint($product['id'])) {
        $api->push($product);
        $store->saveFingerprint($product['id'], $fingerprint);
    }
}
```

### 2. Idempotency Keys

Turn a request body into an idempotency key, so a retried request with the same content, in any key order, is recognised. HMAC keeps the keys unguessable:

```php title="src/Http/IdempotencyKey.php"
use Pharaonic\Fingerprint\Fingerprint;

$key = Fingerprint::for(json_decode($body))
    ->namespace('payment-request')
    ->ignore('client_timestamp')
    ->at('customer.email')->trim()->lower()
    ->at('amount')->precision(2)
    ->hmac($appSecret)
    ->base64Url()
    ->make();
```

`json_decode()` without `true` returns `stdClass` objects, which are maps just like associative arrays.

### 3. De-duplicating Imported Contacts

Contacts that differ only in whitespace, case or the order of their phone numbers get the same fingerprint:

```php title="src/Import/ContactDeduplicator.php"
use Pharaonic\Fingerprint\Fingerprint;

$contacts = Fingerprint::for(null)
    ->only(['name', 'email', 'phones'])
    ->trim()->normalizeWhitespace()
    ->at('email')->lower()
    ->at('phones.*')->transform(fn (string $phone) => preg_replace('/\D/', '', $phone))
    ->at('phones')->unordered()->unique();

$unique = [];

foreach ($rows as $row) {
    $unique[$contacts->for($row)->make()] ??= $row;
}
```

The `phones.*` rule cleans each number before the `phones` list is ordered and de-duplicated, because a list's rules run after its children.

### 4. Signing and Verifying Webhooks

The sender and the receiver build the same ordered message from the same fields:

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

### 5. Checking Downloaded Files

Compare a download with the checksum the publisher lists, without loading the file into memory:

```php title="src/Releases/ChecksumVerifier.php"
use Pharaonic\Fingerprint\Fingerprint;

if (! Fingerprint::file($archive)->algorithm('sha512')->verify($publishedChecksum)) {
    throw new RuntimeException('The download is corrupted.');
}
```
