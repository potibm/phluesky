<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Response\VideoJobStatusResponse;

#[CoversClass(VideoJobStatusResponse::class)]
#[UsesClass(UploadBlobResponse::class)]
final class VideoJobStatusResponseTest extends TestCase
{
    public function testCreateValidObject(): void
    {
        $status = new VideoJobStatusResponse(self::generateJobStatus('JOB_STATE_COMPLETED'));

        $this->assertEquals('job-123', $status->getJobId());
        $this->assertEquals('did:plc:1234567890', $status->getDid());
        $this->assertEquals('JOB_STATE_COMPLETED', $status->getState());
        $this->assertEquals(75, $status->getProgress());
        $this->assertNotNull($status->getBlob());
        $this->assertEquals('video processing failed', $status->getError());
        $this->assertEquals('generic_failure', $status->getFailureCode());
        $this->assertEquals('something went wrong', $status->getMessage());
        $this->assertTrue($status->isCompleted());
        $this->assertFalse($status->isFailed());
    }

    public function testMinimalJobStatusUsesDefaults(): void
    {
        $jobStatus = new \stdClass();
        $jobStatus->jobId = 'job-min';
        $jobStatus->did = 'did:plc:1234567890';
        $jobStatus->state = 'JOB_STATE_PROCESSING';

        $status = new VideoJobStatusResponse($jobStatus);

        $this->assertEquals(0, $status->getProgress());
        $this->assertNull($status->getBlob());
        $this->assertNull($status->getError());
        $this->assertNull($status->getFailureCode());
        $this->assertNull($status->getMessage());
        $this->assertFalse($status->isCompleted());
        $this->assertFalse($status->isFailed());
    }

    public function testFailedState(): void
    {
        $status = new VideoJobStatusResponse(self::generateJobStatus('JOB_STATE_FAILED'));

        $this->assertTrue($status->isFailed());
        $this->assertFalse($status->isCompleted());
    }

    public function testFromResponseUnwrapsJobStatus(): void
    {
        $response = new \stdClass();
        $response->jobStatus = self::generateJobStatus('JOB_STATE_COMPLETED');

        $status = VideoJobStatusResponse::fromResponse($response);

        $this->assertEquals('job-123', $status->getJobId());
    }

    public function testFromResponseAcceptsRawJobStatus(): void
    {
        $status = VideoJobStatusResponse::fromResponse(self::generateJobStatus('JOB_STATE_COMPLETED'));

        $this->assertEquals('job-123', $status->getJobId());
    }

    public function testMissingRequiredProperty(): void
    {
        $jobStatus = new \stdClass();
        $jobStatus->jobId = 'job-123';
        $jobStatus->did = 'did:plc:1234567890';

        try {
            new VideoJobStatusResponse($jobStatus);
            $this->fail('Expected InvalidPayloadException');
        } catch (InvalidPayloadException $e) {
            $this->assertStringContainsString('state', $e->getMessage());
        }
    }

    public static function generateJobStatus(string $state = 'JOB_STATE_COMPLETED'): \stdClass
    {
        $jobStatus = new \stdClass();
        $jobStatus->jobId = 'job-123';
        $jobStatus->did = 'did:plc:1234567890';
        $jobStatus->state = $state;
        $jobStatus->progress = 75;
        $jobStatus->blob = UploadBlobResponseTest::generateBlobResponse();
        $jobStatus->error = 'video processing failed';
        $jobStatus->failureCode = 'generic_failure';
        $jobStatus->message = 'something went wrong';

        return $jobStatus;
    }
}
