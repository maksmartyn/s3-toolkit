<?php

declare(strict_types=1);

namespace App\Services\AccessKey\Support;

use RuntimeException;

/**
 * Подпись RSASSA-PSS (SHA-256, соль 32 байта) — для JWT с алгоритмом PS256.
 * В расширении openssl нет PSS для openssl_sign, поэтому кодирование EMSA-PSS (RFC 8017, 9.1.1) сделано здесь,
 * а само RSA-преобразование выполняет openssl без дополнения.
 */
final class RsaPss
{
    private const int HASH_LENGTH = 32;

    public static function sign(string $data, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);

        if ($key === false) {
            throw new RuntimeException('Invalid private key.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('The private key must be an RSA key.');
        }

        $modBits = (int) $details['bits'];
        $emBits = $modBits - 1;
        $emLength = intdiv($emBits + 7, 8);

        $mHash = hash('sha256', $data, true);
        $salt = random_bytes(self::HASH_LENGTH);
        $h = hash('sha256', str_repeat("\0", 8).$mHash.$salt, true);

        $db = str_repeat("\0", $emLength - self::HASH_LENGTH * 2 - 2)."\x01".$salt;
        $maskedDb = $db ^ self::mgf1($h, $emLength - self::HASH_LENGTH - 1);

        // Обнуляем лишние старшие биты, чтобы число было меньше модуля
        $maskedDb[0] = chr(ord($maskedDb[0]) & (0xFF >> (8 * $emLength - $emBits)));

        $encoded = $maskedDb.$h."\xbc";

        // При длине модуля, кратной 8, длина EM на байт меньше длины модуля
        $encoded = str_pad($encoded, intdiv($modBits + 7, 8), "\0", STR_PAD_LEFT);

        if (! openssl_private_encrypt($encoded, $signature, $key, OPENSSL_NO_PADDING)) {
            throw new RuntimeException('RSA signing failed.');
        }

        return $signature;
    }

    private static function mgf1(string $seed, int $length): string
    {
        $mask = '';

        for ($counter = 0; strlen($mask) < $length; $counter++) {
            $mask .= hash('sha256', $seed.pack('N', $counter), true);
        }

        return substr($mask, 0, $length);
    }
}
