# *ph*luesky

[![Latest Version](https://img.shields.io/github/release/potibm/phluesky.svg?style=flat-square)](https://github.com/potibm/phluesky/releases)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/potibm/phluesky?style=flat-square)](https://packagist.org/packages/potibm/phluesky)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Coverage Status](https://img.shields.io/codecov/c/github/potibm/phluesky?style=flat-square)](https://app.codecov.io/gh/potibm/phluesky)

An small PHP library for Bluesky social using the AT Protocol.

## Usage

```
$api = new BlueskyApi('nick.bsky.social', 'abcd-efgh-ijkl-mnop');

$post = \potibm\Bluesky\Feed\Post::create('✨ example mentioning @atproto.com to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.');

$postService = new \potibm\Bluesky\BlueskyPostService($api);
$post = $postService->addFacetsFromMentionsAndLinks($post);
$post = $postService->addImage($post, 'image.jpg', 'alt text');

$response = $api->createRecord($post);
```

## License

The MIT License (MIT). Please see [License File](https://github.com/potibm/phluesky/blob/main/LICENSE) for more information.
