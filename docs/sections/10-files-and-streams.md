## Files & Streams

Read this to fingerprint a file or a stream's raw bytes, such as an upload or a download. It uses the hashing options from the [previous page](#hashing).

`Fingerprint::file()` and `Fingerprint::stream()` hash raw bytes exactly as they are, without canonicalization. They read in chunks, so a large file never loads into memory. They return a `DigestBuilder` with the same `algorithm()`, `hmac()`, encoding and `verify()` options:

```php
use Pharaonic\Fingerprint\Fingerprint;

Fingerprint::file('/backups/db.sql.gz')->make();          // the same as hash_file('sha256', ...)
Fingerprint::file($path)->algorithm('sha512')->base64()->make();
Fingerprint::file($path)->hmac($secret)->verify($expected);
```

### Streams

A stream is read from its **current position** to its end. It is never rewound or closed, so a second `make()` on the same stream only sees what is left:

```php
$stream = fopen('php://temp', 'r+');
fwrite($stream, $body);
rewind($stream);

$fingerprint = Fingerprint::stream($stream)->make();
```

### Errors

A missing file, a directory, an unreadable file, a closed or non-stream resource, or a stream opened for writing only throws an `UnreadableResourceException`:

```php
Fingerprint::file('/missing')->make();
// UnreadableResourceException: The file "/missing" does not exist or cannot be read.
```

:::warning Trusted paths only
`file()` reads whatever path PHP accepts, stream wrappers included. Never pass a path that comes from user input without validating it first.
:::

**Next:** [Objects](#objects): fingerprint data that holds models, value objects or collections.
