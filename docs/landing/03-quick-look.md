---
view: components.packages.quick-look
title: A quick look
subtitle: Three methods cover most uses; rules are there when you need them.
file: src/Sync/ProductSync.php
language: php
code: |
  use Pharaonic\Fingerprint\Fingerprint;

  // The same data gives the same fingerprint, whatever the key order.
  $fingerprint = Fingerprint::make($product);

  // Later: has anything changed?
  Fingerprint::verify($product, $fingerprint); // true

  // Leave out fields that change on every save.
  Fingerprint::for($product)->ignore('updated_at')->make();
---
