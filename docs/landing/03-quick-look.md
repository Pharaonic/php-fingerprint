---
view: components.packages.quick-look
title: A quick look
subtitle: A default fingerprint in one call, rules when your data needs them.
file: src/Sync/OrderFingerprint.php
language: php
code: |
  use Pharaonic\Fingerprint\Fingerprint;

  Fingerprint::make(['id' => 7, 'tags' => ['php']])
      === Fingerprint::make(['tags' => ['php'], 'id' => 7]); // true

  $fingerprint = Fingerprint::for($order)
      ->ignore(['updated_at', 'items.*.cached_label'])
      ->at('customer.email')->trim()->lower()
      ->at('items')->sortBy('sku')
      ->hmac($secret)
      ->make();
---
