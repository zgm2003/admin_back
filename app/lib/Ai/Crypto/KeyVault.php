<?php

namespace app\lib\Ai\Crypto;

use RuntimeException;

/**
 * API Key 加密/解密工具
 * 使用 AES-256-GCM 算法，IV 随机生成，tag 一并保存，整体 base64 存储
 */
class KeyVault
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /**
     * 获取加密密钥
     */
    private static function getKey(): string
    {
        $key = config('ai.vault_key', '');
        if (empty($key)) {
            throw new RuntimeException('AI_VAULT_KEY 未配置，请在 .env 中设置');
        }
        // 确保密钥为 32 字节
        return hash('sha256', $key, true);
    }

    /**
     * 加密明文
     * @param string $plain 明文
     * @return string base64 编码的密文（格式：iv + tag + ciphertext）
     */
    public static function encrypt(string $plain): string
    {
        if (empty($plain)) {
            return '';
        }

        $key = self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('加密失败');
        }

        // 格式：iv(12) + tag(16) + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * 解密密文
     * @param string $cipher base64 编码的密文
     * @return string 明文
     */
    public static function decrypt(string $cipher): string
    {
        if (empty($cipher)) {
            return '';
        }

        $key = self::getKey();
        $data = base64_decode($cipher, true);

        if ($data === false || strlen($data) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('密文格式错误');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plain === false) {
            throw new RuntimeException('解密失败');
        }

        return $plain;
    }

    /**
     * 生成 API Key 提示（显示后4位）
     * @param string $plain 明文 API Key
     * @return string 如 "***1234"
     */
    public static function hint(string $plain): string
    {
        if (empty($plain)) {
            return '';
        }

        $len = strlen($plain);
        if ($len <= 4) {
            return '***' . $plain;
        }

        return '***' . substr($plain, -4);
    }
}
