<?php

namespace Base\Ledger\Security;

use Base\Ledger\Exception\BankException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seals what a bank connection keeps (the provider's tokens, its user) with
 * sodium's secretbox (XSalsa20-Poly1305), under a key derived from
 * `ledger.secret` (the kernel secret by default): "v1:" + base64(nonce +
 * ciphertext). A sealed state that was tampered with, or sealed under
 * another secret, does not open.
 */
class StateCipher
{
    private const PREFIX = 'v1:';

    private readonly string $key;

    public function __construct(#[Autowire('%ledger.secret%')] string $secret)
    {
        if ('' === $secret) {
            throw new \InvalidArgumentException('The ledger needs a secret (ledger.secret, the kernel secret by default) to seal bank connections.');
        }
        $this->key = hash('sha256', 'omnibase/ledger bank connection|'.$secret, true);
    }

    public function seal(array $data): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $json = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox($json, $nonce, $this->key));
    }

    /** @throws BankException when it was not sealed by this secret, or was changed since */
    public function open(string $sealed): array
    {
        if (!str_starts_with($sealed, self::PREFIX) || false === ($raw = base64_decode(substr($sealed, \strlen(self::PREFIX)), true))
            || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new BankException('The bank connection\'s state is not a sealed state.');
        }
        $json = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if (false === $json) {
            throw new BankException('The bank connection\'s state does not open: sealed under another secret, or altered. Connect the bank again.');
        }

        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
