---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything you need to compare and sign PHP data
subtitle: One call for the default, a fluent builder when you need rules.
items:
  - icon: switch
    title: Key Order Never Matters
    text: "Maps are ordered canonically at every level, while lists keep their order unless you call `unordered()`."
  - icon: badge-check
    title: Types Preserved
    text: "`1`, `'1'`, `1.0` and `true` differ, and `null` is not a missing key, unless you normalize them."
  - icon: code-brackets
    title: Path Rules
    text: "`ignore('items.*.cached_at')`, `only()`, `at('email')->trim()->lower()`, `precision(2)`, `sortBy('id')`."
  - icon: lock
    title: Hashing and HMAC
    text: "SHA-256 by default, any `hash()` algorithm, `hmac($secret)`, namespaces, and hex, binary or base64 output."
  - icon: shield-check
    title: Ordered Signatures
    text: "`Fingerprint::signature($payload)->fields([...])` rebuilds a gateway's exact message and verifies it in constant time."
  - icon: search
    title: Explainable
    text: "`result()->canonical()` shows the exact bytes behind a fingerprint, so a difference is never a mystery."
---
