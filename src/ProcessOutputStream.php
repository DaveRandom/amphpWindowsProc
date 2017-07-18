<?php declare(strict_types=1);

namespace WindowsProc;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

final class ProcessOutputStream extends ProcessStream
{
    private const READ_CHUNK_SIZE = 1024;

    /**
     * @var Deferred
     */
    private $deferred;
    private $buffer = '';
    private $requestLength;
    private $closed = false;

    private function getBufferData(?int $length): string
    {
        assert($this->buffer !== '', new \LogicException('Getting data from an empty buffer???'));

        if ($length === null || strlen($this->buffer) <= $length) {
            $data = $this->buffer;
            $this->buffer = '';
        } else {
            $data = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        }

        return $data;
    }

    private function onReadable(): void
    {
        $this->buffer .= $data = fread($this->socket, self::READ_CHUNK_SIZE);
        $this->closed = $data === '';

        if ($this->deferred === null) {
            return;
        }

        $deferred = $this->deferred;
        $this->deferred = null;

        $result = $this->buffer !== ''
            ? $this->getBufferData($this->requestLength)
            : null;

        $deferred->resolve($result);
    }

    /**
     * @param resource $socket
     * @param int $id
     * @uses onReadable
     */
    public function __construct($socket, int $id)
    {
        parent::__construct($socket, $id);

        $this->watcher = Loop::onReadable($socket, (new \ReflectionMethod($this, 'onReadable'))->getClosure($this));
    }

    public function read(int $length = null): Promise
    {
        if ($this->deferred !== null) {
            throw new \LogicException('Multiple concurrent reads are not permitted');
        }

        if ($this->buffer !== '') {
            return new Success($this->getBufferData($length));
        }

        if ($this->closed) {
            return new Success();
        }

        $this->deferred = new Deferred();
        $this->requestLength = $length;

        return $this->deferred->promise();
    }
}
