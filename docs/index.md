---
name: Fingerprint

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: security
  icon: shield-check
  tags: fingerprint hash checksum canonical hmac signature idempotency deduplication change-detection webhook payment
  description: Deterministic fingerprints of PHP data, whatever the key order, plus ordered HMAC signatures for payment gateways and webhooks.

seo:
  title: "{package.fullName} - Deterministic Hashes, HMAC & Ordered Signatures for PHP Data"
  description: "{package.name} is a PHP package for deterministic fingerprints of arrays and objects, regardless of key order, with path rules, HMAC and ordered signatures for payment gateways and webhooks. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: php fingerprint, php array hash, canonical hash php, deterministic hash, php hmac signature, idempotency key php, change detection php, paymob hmac php, webhook signature php, checksum php
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a PHP package for deterministic fingerprints of arrays and objects, regardless of key order, with path rules, HMAC and ordered signatures for payment gateways and webhooks."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
