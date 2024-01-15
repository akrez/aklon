<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;

require('./vendor/autoload.php');
require('./Aklon.php');

$baseHost = 'localhost/filter/aklon';

$fakeUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$realUrl = Aklon::decryptUrl($fakeUrl, $baseHost);

$request = ServerRequest::fromGlobals()->withUri(new Uri($realUrl));

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

$response = $response
    ->withoutHeader('Transfer-Encoding')
    ->withoutHeader('Connection');
foreach ($response->getHeaders() as $headerKey => $headers) {
    header($headerKey . ':' . implode(',', $headers));
}

$body = $response->getBody();

$cleanContentType = Aklon::getCleanContentType(implode(',', $response->getHeader('content-type')));

if ('text/html' == $cleanContentType) {
    echo Aklon::convertHtml($body->getContents(), $baseHost, $fakeUrl, $realUrl);
} elseif ('text/css' == $cleanContentType) {
    echo Aklon::convertCss($body->getContents(), $baseHost, $fakeUrl, $realUrl);
} else {
    while (!$body->eof()) {
        echo $body->read(512);
        flush();
    }
}
