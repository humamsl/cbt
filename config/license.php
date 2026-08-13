<?php

return [

    'app'   => 'cbt',

    'owner' => 'AnonymouSL',

    'public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAlIOMArx5HQECPLxShZ3w
V/opq/4aSAzd6LEaRrpiRW3N9wD0CZt8B5zfucXQHRir7gHcvKCzZjChe6pwaVWW
5SDeOuL3hTPzabvBrBpPUEqDsfipjMEuH97rCZziWRUw2f4gytwNZ36x+jR3USTU
t4vsQgCzgV65ERN8aNwofyZ92ZV34mqtN8Z9EnaPQlxpxnLsV+ikoI3VsArWJogD
AFAYS4NaJDwagz77cb5sXkMRSwnIszAaABkbf8iYa0j8PNEM6X5dKugdd9Xduze+
UgHdwZB11zEiy9kZRNa4KDxFcWoUQfunK8t8tuwB+eHoMofIUmjiaOoionbXWFg0
lcpgQ4YP81bNFggNosbcz9/Zz0poUHxJchFJAOFCjVbcevabgc+UpniYQ9L2tiyQ
dFXpYH/iIdScAh2S95FpR9dvP6Enh/X/Dx102hvlRODcYUrP/Qql4jiBV7oJbl2Z
Ur4vqgjVwGdXqryrHGKwNsLcNceexoMQNLIzYs/AIBeqfM40633w9hEho2mYOkaA
n4ba0+JWFiIzUhlaTmVvchQDTL0NdyW0o9hOSLIMjdDxX4BJBLFtOLbisD5iZu7g
bDq+0uCaFMDvSK4pHjpyBOWLre5XJMUu8dUhw91xW2oef81HkI09RHnYaBNIM6sl
bRtTyupzrP9BpBF5rCmnqCcCAwEAAQ==
-----END PUBLIC KEY-----
PEM,

    'private_key_path' => env('LICENSE_PRIVATE_KEY', base_path('../license-keys/private.pem')),

    'enforce' => (bool) env('LICENSE_ENFORCE', true),

];
