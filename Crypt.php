<?php

class Crypt
{
    public static $key = 'z123456789Z';

    private static function strRotPass($str, $key, $decrypt = false)
    {
        $length = strlen($key);
        $result = str_repeat(' ', strlen($str));
        for ($i = 0; $i < strlen($str); $i++) {
            if ($decrypt) {
                $ascii = ord($str[$i]) - ord($key[$i % $length]);
            } else {
                $ascii = ord($str[$i]) + ord($key[$i % $length]);
            }
            $result[$i] = chr($ascii);
        }
        return $result;
    }

    public static function base64UrlEncode($input)
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($input)
    {
        return base64_decode(str_pad(strtr($input, '-_', '+/'), strlen($input) % 4, '=', STR_PAD_RIGHT));
    }

    public static function urlEncrypt($url)
    {
        $url = static::strRotPass($url, static::$key);
        return static::base64UrlEncode($url);
    }

    public static function urlDecrypt($url)
    {
        $url = static::base64UrlDecode($url);
        return static::strRotPass($url, static::$key, true);
    }
}
