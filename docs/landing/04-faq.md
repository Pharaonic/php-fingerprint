---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Why not hash(json_encode($data))?

`json_encode()` keeps key order, so the same data with keys in another order gives another hash. It also writes `1.0` as `1` and depends on ini settings for floats. {package.name} orders maps canonically, keeps types apart, and encodes floats from their exact bits.

## What is the difference between a fingerprint and a signature?

A fingerprint answers "is this the same data?": key order never matters and you choose what to ignore. A signature answers "does this payload carry the signature a protocol defines?": fields are joined in exactly the declared order, as payment gateways and webhooks require. The two never share a format.

## Will fingerprints match across servers and PHP versions?

Yes. The canonical form never depends on the locale, ini settings or the PHP version, and hand-written golden vectors check it on every supported PHP line. Only rules that rely on Unicode tables or timezone databases can differ between builds, and the documentation lists them.

## Does it depend on Laravel or another framework?

No. {package.name} is plain PHP with no runtime dependency besides `ext-mbstring`.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, rules, signatures and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), or ask in the [Pharaonic Discord](https://discord.gg/XQG9RhvEvf).
