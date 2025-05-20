<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class HttpComponentsManager
{
    public readonly ClientInterface $httpClient;

    public readonly UriFactoryInterface $uriFactory;

    public readonly RequestFactoryInterface $requestFactory;

    public readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?UriFactoryInterface $uriFactory = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }
}
