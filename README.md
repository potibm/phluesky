# *ph*luesky

An small PHP library for Bluesky social using the AT Protocol.

## Usage

```
$api = new BlueskyApi('nick.bsky.social', 'abcd-efgh-ijkl-mnop');
$postService = new \potibm\Bluesky\BlueskyPostService($api);

$post = \potibm\Bluesky\Feed\Post::create('✨ example mentioning @atproto.com to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.');
$post = $postService->addFacetsFromMentionsAndLinks($post);

$response = $api->createRecord($post);
```

## License

The MIT License (MIT). Please see [License File](https://github.com/potibm/phluesky/blob/main/LICENSE) for more information.
