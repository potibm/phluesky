<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use JsonSerializable;

class JobStatusResponse
{
    use ResponseTrait;

    const JOB_STATE_COMPLETED = 'JOB_STATE_COMPLETED';
    const JOB_STATE_FAILED = 'JOB_STATE_FAILED';


    private string $jobId;
    private string $did;
    private string $state;
    private ?int $progress;
   private ?string $error = null;
    private ?string $message = null;
    private ?string $blob = null;

    public function __construct(\stdClass $response)
    {
        $this->jobId = (string) $this->getSessionProperty($response, 'jobId');
        $this->did = (string) $this->getSessionProperty($response, 'did');
        $this->state = (string) $this->getSessionProperty($response, 'state');
        if (property_exists($response, 'progress')) {
            $this->progress = (int)$this->getSessionProperty($response, 'progress');
        }
        if (property_exists($response, 'error')) {
            $this->error = $this->getSessionProperty($response, 'error');
        }
        if (property_exists($response, 'message')) {
            $this->message = $this->getSessionProperty($response, 'message');
        }
        if (property_exists($response, 'blob')) {
            $this->blob = $this->getSessionProperty($response, 'blob');
        }
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

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getBlob(): ?string
    {
        return $this->blob;
    }
}