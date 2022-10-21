<?php


class Response extends AbstractMessage
{
    /**
     * @var string
     */
    private $jsonBody;

    /**
     * @var int
     */
    private $statusCode;

    public function __construct(string $jsonBody, int $statusCode, array $headers = [])
    {
        $this->jsonBody = $jsonBody;
        $this->headers = $headers;
        $this->statusCode = $statusCode;
    }

    public function getJsonBody(): string
    {
        return $this->jsonBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
