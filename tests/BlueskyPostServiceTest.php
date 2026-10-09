<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyApiInterface;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\BlueskyUri;
use potibm\Bluesky\Embed\AspectRatio;
use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Embed\Video;
use potibm\Bluesky\Exception\FileNotFoundException;
use potibm\Bluesky\Exception\VideoUploadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Media\BlobMediaSource;
use potibm\Bluesky\Media\FileMediaSource;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Response\VideoJobStatusResponse;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Richtext\FacetTag;
use potibm\Bluesky\Test\Response\RecordResponseTest;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;
use potibm\Bluesky\Test\Response\VideoJobStatusResponseTest;

#[CoversClass(BlueskyPostService::class)]
#[UsesClass(Post::class)]
#[UsesClass(AbstractFacet::class)]
#[UsesClass(FacetLink::class)]
#[UsesClass(FacetMention::class)]
#[UsesClass(FacetTag::class)]
#[UsesClass(Images::class)]
#[UsesClass(Video::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(External::class)]
#[UsesClass(BlueskyUri::class)]
#[UsesClass(Record::class)]
#[UsesClass(RecordResponse::class)]
#[UsesClass(UploadBlobResponse::class)]
#[UsesClass(VideoJobStatusResponse::class)]
#[UsesClass(VideoUploadException::class)]
#[UsesClass(FileMediaSource::class)]
#[UsesClass(BlobMediaSource::class)]
#[UsesClass(FileNotFoundException::class)]
final class BlueskyPostServiceTest extends TestCase
{
    private const SAMPLE = '✨ example mentioning @atproto.com ' .
        'to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR. and a #HashtagFun.';

    private BlueskyPostService $postService;

    /**
     * @var BlueskyApiInterface&MockObject
     */
    private BlueskyApiInterface $clientMock;

    private Post $post;

    public function testMentionFacet(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromMentions($this->post);

        $this->assertCount(1, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        /** @var FacetMention $firstFacet */
        $firstFacet = $resultPost->getFacets()[0];
        $this->assertEquals('did:plc:ewvi7nxzyoun6zhxrhs64oiz', $firstFacet->getDid());
        $this->assertEquals(23, $firstFacet->getStart());
        $this->assertEquals(35, $firstFacet->getEnd());
    }

    public function testLinkFacet(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromLinks($this->post);

        $this->assertCount(1, $resultPost->getFacets());
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[0]);
        /** @var FacetLink $firstFacet */
        $firstFacet = $resultPost->getFacets()[0];
        $this->assertEquals('https://en.wikipedia.org/wiki/CBOR', $firstFacet->getUri());
        $this->assertEquals(74, $firstFacet->getStart());
        $this->assertEquals(108, $firstFacet->getEnd());
    }

    public function testTagFacet(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromTags($this->post);

        $this->assertCount(1, $resultPost->getFacets());
        $this->assertInstanceOf(FacetTag::class, $resultPost->getFacets()[0]);
        /** @var FacetTag $firstFacet */
        $firstFacet = $resultPost->getFacets()[0];
        $this->assertEquals('HashtagFun', $firstFacet->getTag());
        $this->assertEquals(116, $firstFacet->getStart());
        $this->assertEquals(127, $firstFacet->getEnd());
    }

    public function testLinkAndMentionFacets(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromMentionsAndLinks($this->post);

        $this->assertCount(2, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[1]);
    }

    public function testLinkAndMentionAndTagFacets(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromMentionsAndLinksAndTags($this->post);

        $this->assertCount(3, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[1]);
        $this->assertInstanceOf(FacetTag::class, $resultPost->getFacets()[2]);
    }

    public function testAddImage(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addImage($this->post, new FileMediaSource(__FILE__), 'an alt text', null);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Images::class, $embed);
        $this->assertCount(1, $embed);
    }

    public function testAddImageWithoutAspectRatio(): void
    {
        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('image.png')->at($root);
        $file->setContent(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgGZ4zBUAAAAASUVORK5CYII='
        ));

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addImage($this->post, new FileMediaSource($file->url()), 'an alt text', null);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Images::class, $embed);
        $this->assertCount(1, $embed);
    }

    public function testAddImageWithBlobMediaSource(): void
    {
        $source = new BlobMediaSource('image-binary-data', 'image/png');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addImage($this->post, $source, 'an alt text', null);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Images::class, $embed);
        $this->assertCount(1, $embed);
    }

    #[IgnoreDeprecations]
    public function testAddImageWithStringPathTriggersDeprecation(): void
    {
        $this->expectUserDeprecationMessage('Passing a file path string is deprecated. Use FileMediaSource instead.');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addImage($this->post, __FILE__, 'an alt text', null);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Images::class, $embed);
        $this->assertCount(1, $embed);
    }

    public function testAddImgeWithMissingImage(): void
    {
        $this->expectException(FileNotFoundException::class);
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addImage($this->post, new FileMediaSource(__DIR__ . '/missingfile.png'), 'an alt text');
    }

    public function testAddImgeWithUnreadableFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageMatches('/^Unable to read file/');

        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('image.png', 0000)->at($root);

        $this->postService->addImage($this->post, new FileMediaSource($file->url()), 'an alt text');
    }

    public function testAddVideo(): void
    {
        $this->clientMock->expects($this->once())
            ->method('getServiceAuth')
            ->with('com.atproto.repo.uploadBlob')
            ->willReturn('service-token');
        $this->clientMock->expects($this->once())
            ->method('uploadVideo')
            ->with('video-data', 'video.mp4', 'video/mp4', 'service-token')
            ->willReturn(new VideoJobStatusResponse(
                VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_COMPLETED')
            ));

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/mp4'),
            'an alt text',
            new AspectRatio(16, 9)
        );

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Video::class, $embed);
        $this->assertInstanceOf(UploadBlobResponse::class, $embed->getVideo());
        $this->assertEquals('an alt text', $embed->getAlt());
        $this->assertEquals(new AspectRatio(16, 9), $embed->getAspectRatio());
    }

    public function testAttachVideo(): void
    {
        $blob = $this->createBlob();

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->attachVideo(
            $this->post,
            $blob,
            'an alt text',
            new AspectRatio(4, 3)
        );

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Video::class, $embed);
        $this->assertSame($blob, $embed->getVideo());
        $this->assertEquals('an alt text', $embed->getAlt());
        $this->assertEquals(new AspectRatio(4, 3), $embed->getAspectRatio());
    }

    public function testAttachVideoWithDefaults(): void
    {
        $blob = $this->createBlob();

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->attachVideo($this->post, $blob);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Video::class, $embed);
        $this->assertSame($blob, $embed->getVideo());
        $this->assertEquals('', $embed->getAlt());
        $this->assertNull($embed->getAspectRatio());

        $json = $embed->jsonSerialize();
        $this->assertIsArray($json);
        $this->assertArrayNotHasKey('alt', $json);
        $this->assertArrayNotHasKey('aspectRatio', $json);
    }

    public function testAddVideoWithCustomFilename(): void
    {
        $this->clientMock->method('getServiceAuth')->willReturn('service-token');
        $this->clientMock->expects($this->once())
            ->method('uploadVideo')
            ->with('video-data', 'my-clip.mp4', 'video/mp4', 'service-token')
            ->willReturn(new VideoJobStatusResponse(
                VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_COMPLETED')
            ));

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/mp4'),
            'an alt text',
            null,
            'my-clip.mp4'
        );

        $this->assertInstanceOf(Video::class, $resultPost->getEmbed());
    }

    public function testAddVideoPollsUntilCompleted(): void
    {
        $service = new BlueskyPostService($this->clientMock, 0, 5);

        $this->clientMock->method('getServiceAuth')->willReturn('service-token');
        $this->clientMock->method('uploadVideo')->willReturn(
            new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_PROCESSING'))
        );
        $this->clientMock->expects($this->exactly(2))
            ->method('getVideoJobStatus')
            ->willReturnOnConsecutiveCalls(
                new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_ENCODING')),
                new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_COMPLETED'))
            );

        $resultPost = $service->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/mp4'),
            '',
            null
        );

        $this->assertInstanceOf(Video::class, $resultPost->getEmbed());
    }

    public function testAddVideoThrowsOnFailedJob(): void
    {
        $this->clientMock->method('getServiceAuth')->willReturn('service-token');
        $this->clientMock->method('uploadVideo')->willReturn(
            new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_FAILED'))
        );

        $this->expectException(VideoUploadException::class);
        $this->expectExceptionMessage('Video processing failed (job job-123)');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/mp4'),
            '',
            null
        );
    }

    public function testAddVideoRejectsNonMp4(): void
    {
        $this->expectException(VideoUploadException::class);
        $this->expectExceptionMessage('only supports video/mp4');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/webm'),
            '',
            null
        );
    }

    public function testAddVideoTimesOut(): void
    {
        $service = new BlueskyPostService($this->clientMock, 0, 2);

        $this->clientMock->method('getServiceAuth')->willReturn('service-token');
        $this->clientMock->method('uploadVideo')->willReturn(
            new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_PROCESSING'))
        );
        $this->clientMock->expects($this->exactly(2))
            ->method('getVideoJobStatus')
            ->willReturn(
                new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_PROCESSING'))
            );

        $this->expectException(VideoUploadException::class);
        $this->expectExceptionMessage('did not complete');

        $service->addVideo(
            $this->post,
            new BlobMediaSource('video-data', 'video/mp4'),
            '',
            null
        );
    }

    #[IgnoreDeprecations]
    public function testAddVideoWithStringPathTriggersDeprecation(): void
    {
        $this->expectUserDeprecationMessage('Passing a file path string is deprecated. Use FileMediaSource instead.');

        $videoPath = $this->createTemporaryVideoFile();

        $this->clientMock->method('getServiceAuth')->willReturn('service-token');
        $this->clientMock->method('uploadVideo')->willReturn(
            new VideoJobStatusResponse(VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_COMPLETED'))
        );

        try {
            /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
            $resultPost = $this->postService->addVideo($this->post, $videoPath, 'an alt text');

            $this->assertInstanceOf(Video::class, $resultPost->getEmbed());
        } finally {
            @unlink($videoPath);
        }
    }

    public function testAddExternal(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', new FileMediaSource(__FILE__));

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNotNull($embed->getThumb());
    }

    public function testAddExternalWithBlobMediaSource(): void
    {
        $source = new BlobMediaSource('image-binary-data', 'image/png');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', $source);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNotNull($embed->getThumb());
    }

    #[IgnoreDeprecations]
    public function testAddExternalWithStringPathTriggersDeprecation(): void
    {
        $this->expectUserDeprecationMessage('Passing a file path string is deprecated. Use FileMediaSource instead.');

        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', __FILE__);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNotNull($embed->getThumb());
    }

    public function testAddExternalWithoutThumb(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc');

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNull($embed->getThumb());
    }

    public function testAddExternalWithMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', new FileMediaSource(__DIR__ . '/missingfile.png'));
    }

    public function testAddQuote(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';

        $this->clientMock
            ->method('getRecord')
            ->with(new BlueskyUri($uri))
            ->willReturn(new RecordResponse(RecordResponseTest::generateBlobResponse()));

        $resultPost = $this->postService->addQuote($this->post, $uri);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Record::class, $embed);
        $this->assertEquals($uri, $embed->getUri());
    }

    public function testAddReplyIsRoot(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';

        $this->clientMock
            ->method('getRecord')
            ->with(new BlueskyUri($uri))
            ->willReturn(new RecordResponse(RecordResponseTest::generateBlobResponse()));

        $resultPost = $this->postService->addReply($this->post, $uri);

        $json = $resultPost->jsonSerialize();
        $this->assertArrayHasKey('reply', $json);
        $this->assertArrayHasKey('root', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['root']);
        $this->assertEquals($uri, $json['reply']['root']['uri']);
        $this->assertArrayHasKey('parent', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['parent']);
        $this->assertEquals($uri, $json['reply']['parent']['uri']);
    }

    public function testAddReplyHasRoot(): void
    {
        $recordResponseRootBlob = RecordResponseTest::generateBlobResponse('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vroot');
        $recordResponseRoot = new RecordResponse($recordResponseRootBlob);

        $recordResponseParentBlob = RecordResponseTest::generateBlobResponse(null, $recordResponseRootBlob);
        $recordResponseParent = new RecordResponse($recordResponseParentBlob);

        $this->clientMock
            ->method('getRecord')
            ->willReturn($recordResponseParent, $recordResponseRoot);

        $resultPost = $this->postService->addReply($this->post, $recordResponseParentBlob->uri);

        $json = $resultPost->jsonSerialize();
        $this->assertArrayHasKey('reply', $json);
        $this->assertArrayHasKey('root', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['root']);
        $this->assertEquals($recordResponseRootBlob->uri, $json['reply']['root']['uri']);
        $this->assertArrayHasKey('parent', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['parent']);
        $this->assertEquals($recordResponseParentBlob->uri, $json['reply']['parent']['uri']);
    }

    #[\Override]
    public function setUp(): void
    {
        $this->post = Post::create(self::SAMPLE);

        $this->clientMock = $this->createMock(BlueskyApiInterface::class);
        $this->clientMock->method('getDidForHandle')->with('atproto.com')
            ->willReturn('did:plc:ewvi7nxzyoun6zhxrhs64oiz');

        $this->postService = new BlueskyPostService($this->clientMock);
    }

    private function createBlob(): UploadBlobResponse
    {
        return new UploadBlobResponse(UploadBlobResponseTest::generateBlobResponse());
    }

    private function createTemporaryVideoFile(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phluesky_video_');
        file_put_contents($path, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom");

        return $path;
    }
}
