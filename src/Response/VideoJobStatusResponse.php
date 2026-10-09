<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

final class VideoJobStatusResponse
{
    use ResponseTrait;

    private const STATE_COMPLETED = 'JOB_STATE_COMPLETED';

    private const STATE_FAILED = 'JOB_STATE_FAILED';

    private string $jobId;

    private string $did;

    private string $state;

    private int $progress;

    private ?UploadBlobResponseInterface $blob;

    private ?string $error;

    private ?string $failureCode;

    private ?string $message;

    public function __construct(\stdClass $jobStatus)
    {
        $this->jobId = (string) $this->getSessionProperty($jobStatus, 'jobId');
        $this->did = (string) $this->getSessionProperty($jobStatus, 'did');
        $this->state = (string) $this->getSessionProperty($jobStatus, 'state');
        $this->progress = property_exists($jobStatus, 'progress') ? (int) $jobStatus->progress : 0;
        $this->blob = property_exists($jobStatus, 'blob') && $jobStatus->blob instanceof \stdClass
            ? new UploadBlobResponse($jobStatus->blob)
            : null;
        $this->error = property_exists($jobStatus, 'error') ? (string) $jobStatus->error : null;
        $this->failureCode = property_exists($jobStatus, 'failureCode') ? (string) $jobStatus->failureCode : null;
        $this->message = property_exists($jobStatus, 'message') ? (string) $jobStatus->message : null;
    }

    /**
     * Build a job status from either a raw job status object or a response that
     * wraps it in a "jobStatus" property (as returned by the video service).
     */
    public static function fromResponse(\stdClass $response): self
    {
        if (property_exists($response, 'jobStatus') && $response->jobStatus instanceof \stdClass) {
            return new self($response->jobStatus);
        }

        return new self($response);
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getBlob(): ?UploadBlobResponseInterface
    {
        return $this->blob;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getFailureCode(): ?string
    {
        return $this->failureCode;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }
}
