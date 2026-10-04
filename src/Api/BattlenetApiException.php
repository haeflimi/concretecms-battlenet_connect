<?php

namespace BattlenetConnect\Api;

use RuntimeException;

class BattlenetApiException extends RuntimeException
{
    /** @var array|null decoded JSON body of the error response, if there was one */
    protected $responseData;

    public function setResponseData(?array $responseData): self
    {
        $this->responseData = $responseData;

        return $this;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * The credentials are invalid or the request is not allowed: retrying with another character won't help.
     */
    public function isFatal(): bool
    {
        return in_array($this->getCode(), [401, 403, 429], true);
    }
}
