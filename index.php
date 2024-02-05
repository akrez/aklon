<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Src\Aklon;
use Src\Cookie;

if (false) {
    require_once('../vendor/autoload.php');
    $baseSchema = 'https';
    $baseHost = 'aklon.akrezing.ir';
} else {
    require_once('./vendor/autoload.php');
    $baseSchema = 'http';
    $baseHost = 'localhost/filter/aklon';
}

if (isset($_GET['url'])) {
    $url = Aklon::encryptUrl($_GET['url'], $baseHost, $baseSchema);
    header('Location: ' . $url);
}

if (
    isset($_GET['q'])
    and $q = $_GET['q']
) {

    $realUrl = Aklon::decryptUrl($q, $baseHost);

    $request = ServerRequest::fromGlobals()->withUri(new Uri($realUrl));

    Cookie::onBeforeRequest($request);

    $client = new Client([
        'curl' => [
            CURLOPT_CONNECTTIMEOUT     => 10,
            CURLOPT_TIMEOUT            => 0,
            // don't bother with ssl
            CURLOPT_SSL_VERIFYPEER    => false,
            CURLOPT_SSL_VERIFYHOST    => false,
            // we will take care of redirects
            CURLOPT_FOLLOWLOCATION    => false,
            CURLOPT_AUTOREFERER        => false
        ]
    ]);

    try {
        $response = $client->send($request);
    } catch (GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
    }

    Cookie::onHeadersReceived($request, $response);

    $response = $response
        ->withoutHeader('Transfer-Encoding')
        ->withoutHeader('Connection');
    foreach ($response->getHeaders() as $headerIndex => $headers) {
        header($headerIndex . ':' . implode(',', $headers));
    }

    $body = $response->getBody();

    $cleanContentType = Aklon::getCleanContentType(implode(',', $response->getHeader('content-type')));

    if ('text/html' == $cleanContentType) {
        echo Aklon::convertHtml($body->getContents(), $baseHost, $baseSchema, $realUrl);
    } elseif ('text/css' == $cleanContentType) {
        echo Aklon::convertCss($body->getContents(), $baseHost, $baseSchema, $realUrl);
    } else {
        while (!$body->eof()) {
            echo $body->read(512);
            flush();
        }
    }
} else {
    require('./form.php');
}
