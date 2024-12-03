# *ph*luesky

[![Latest Version](https://img.shields.io/github/release/potibm/phluesky.svg?style=flat-square)](https://github.com/potibm/phluesky/releases)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/potibm/phluesky?style=flat-square)](https://packagist.org/packages/potibm/phluesky)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Coverage Status](https://img.shields.io/codecov/c/github/potibm/phluesky?style=flat-square)](https://app.codecov.io/gh/potibm/phluesky)

An small PHP library for Bluesky social using the AT Protocol.

## Usage

## Install

Installing using composer is suggested

```bash
composer require potibm/phluesky
```

You will need a PSR-7, PSR-17 and PSR-18 client or adapter from [this list](https://docs.php-http.org/en/latest/clients.html). For development [symfony/http-client](https://packagist.org/packages/symfony/http-client) and [nyholm/psr7](https://packagist.org/packages/nyholm/psr7) are used. 

The HTTP service discovery will do the magic. In most cases no additional steps are required. 

### Setup and posting a simple message

```php
$api = new \potibm\Bluesky\BlueskyApi('nick.bsky.social', 'abcd-efgh-ijkl-mnop');
$postService = new \potibm\Bluesky\BlueskyPostService($api);

$post = \potibm\Bluesky\Feed\Post::create('✨ example mentioning @atproto.com to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.');

$response = $api->createRecord($post);
```

### Adding mentions and links from post text

```php
$post = \potibm\Bluesky\Feed\Post::create('✨ example mentioning @atproto.com to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.');
$post = $postService->addFacetsFromMentionsAndLinks($post);
```

### Adding mentions and links and tags from post text

```php
$post = \potibm\Bluesky\Feed\Post::create('✨ example mentioning @atproto.com to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR. and #HashtagFun');
$post = $postService->addFacetsFromMentionsAndLinksAndTags($post);
```

### Adding images

[https://atproto.com/blog/create-post#images-embeds](https://atproto.com/blog/create-post#images-embeds)

```php
$post = \potibm\Bluesky\Feed\Post::create('example post with image attached');
$post = $postService->addImage(
    $post, 
    'image.jpg', 
    'alt text'
);
```

### Adding website card embeds

[https://atproto.com/blog/create-post#website-card-embeds](https://atproto.com/blog/create-post#website-card-embeds)

```php
$post = \potibm\Bluesky\Feed\Post::create('post which embeds an external URL as a card');
$post = $postService->addWebsiteCard(
    $post, 
    'https://example.com', 
    'Example website', 
    'Example website description',
    'optionalimage.jpg'
);
```

### Reply to a post

[https://atproto.com/blog/create-post#replies](https://atproto.com/blog/create-post#replies)

```php
$post = \potibm\Bluesky\Feed\Post::create('example of a reply');
$post = $postService->addReply(
    $post, 
    'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k43tv4rft22g'
);
```

### Quote a post

[https://atproto.com/blog/create-post#quote-posts](https://atproto.com/blog/create-post#quote-posts)

```php
$post = \potibm\Bluesky\Feed\Post::create('example of a quote-post');
$post = $postService->addQuote(
    $post, 
    'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g'
);
```

### Handling errors

While performing requests using the API, exceptions may be thrown. 

The exceptions are of the base type `potibm\Bluesky\Exception\Exception`.
The exception message will contain details from the API.

```php
try {
    $response = $api->createRecord($post);
} catch (\potibm\Bluesky\Exception\HttpRequestException $e) {
    echo 'Error performing request on HTTP level: ' . $e->getMessage();
} catch (\potibm\Bluesky\Exception\AuthenticationErrorException $e) {
    echo 'Unable to authorize: ' . $e->getMessage();
} catch (\potibm\Bluesky\Exception\HttpStatusCodeException $e) {
    echo 'Unable to perform request on API level: ' . $e->getMessage();
} catch (\potibm\Bluesky\Exception\InvalidPayloadException $e) {
    echo 'Received unserializable JSON payload: ' . $e->getMessage();
}
``` 

## License

The MIT License (MIT). Please see [License File](https://github.com/potibm/phluesky/blob/main/LICENSE) for more information.
