---
view: components.packages.package-hero
badges:
  - label: PHP Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
eyebrow: "{package.name}"
title: Same data,
highlight: same fingerprint
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

`hash(json_encode($data))` changes when keys move and blurs `1` with `1.0`. Fingerprint gives PHP data a stable SHA-256 or HMAC fingerprint, whatever the key order, machine or locale, with rules for what to ignore or normalize. It also builds the exact ordered signatures that payment gateways and webhooks expect.
