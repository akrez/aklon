<?php

namespace Src;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Cookie
{
    const COOKIE_PREFIX = 'pc';

    public static function onBeforeRequest(ServerRequestInterface $request)
    {
        // cookie sent by the browser to the server
        $requestCookie =  implode("; ", $request->getHeader("cookie"));

        // remove old cookie header and rewrite it
        $request = $request->withoutHeader("cookie");

        $sendCookies = array();

        // A Proxy Cookie would have the following name: COOKIE_PREFIX_domain-it-belongs-to__cookie-name
        if ($requestCookie and preg_match_all('@' . static::COOKIE_PREFIX . '_(.+?)__(.+?)=([^;]+)@', $requestCookie, $matches, PREG_SET_ORDER)) {

            foreach ($matches as $match) {
                $name = $match[2];
                $value = $match[3];
                $domain = str_replace("_", ".", $match[1]);

                // what is the domain or our current URL?
                $host = parse_url($request->getUri(), PHP_URL_HOST);

                // does this cookie belong to this domain?
                // sometimes domain begins with a DOT indicating all subdomains - deprecated but still in use on some servers...
                if (strpos($host, $domain) !== false) {
                    $sendCookies[] = $name . '=' . $value;
                }
            }
        }

        // do we have any cookies to send?
        if ($sendCookies) {
            $request->withAddedHeader('cookie', implode("; ", $sendCookies));
        }
    }

    // cookies received from a target server via set-cookie should be rewritten
    public static function onHeadersReceived(ServerRequestInterface $request, ResponseInterface $response)
    {
        // does the response send any cookies?
        $responseHeaders = $response->getHeader('set-cookie');

        if ($responseHeaders) {

            // remove set-cookie header and reconstruct it differently
            $response = $response->withoutHeader('set-cookie');

            // loop through each set-cookie line
            foreach ($responseHeaders as $responseHeader) {

                // parse cookie data as array from header line
                $cookie = static::parse_cookie($responseHeader, $request->getUri());

                // construct a "proxy cookie" whose name includes the domain to which this cookie belongs to
                // replace dots with underscores as cookie name can only contain alphanumeric and underscore
                $cookie_name = sprintf("%s_%s__%s", self::COOKIE_PREFIX, str_replace('.', '_', $cookie['domain']), $cookie['name']);

                // append a simple name=value cookie to the header - no expiration date means that the cookie will be a session cookie
                $response->withAddedHeader('set-cookie', $cookie_name . '=' . $cookie['value'], false);
            }
        }
    }

    // adapted from browserkit
    private static function parse_cookie($line, $url)
    {

        $host = parse_url($url, PHP_URL_HOST);

        $data = array(
            'name' => '',
            'value' => '',
            'domain' => $host,
            'path' => '/',
            'expires' => 0,
            'secure' => false,
            'httpOnly' => true
        );

        $line = preg_replace('/^Set-Cookie2?: /i', '', trim($line));

        // there should be at least one name=value pair
        $pairs = array_filter(array_map('trim', explode(';', $line)));

        foreach ($pairs as $index => $comp) {

            $parts = explode('=', $comp, 2);
            $key = trim($parts[0]);

            if (count($parts) == 1) {

                // secure; HttpOnly; == 1
                $data[$key] = true;
            } else {

                $value = trim($parts[1]);

                if ($index == 0) {
                    $data['name'] = $key;
                    $data['value'] = $value;
                } else {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }
}
