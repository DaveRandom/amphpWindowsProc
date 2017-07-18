<?php declare(strict_types=1);

namespace WindowsProc;

use Amp\Loop;

abstract class ProcessStream
{
    private $id;

    protected $socket;
    protected $watcher;

    private function destroySocket(): void
    {
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);
        }

        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __construct($socket, int $id)
    {
        $this->socket = $socket;
        $this->id = $id;
    }

    public function __destruct()
    {
        $this->destroySocket();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isOpen(): bool
    {
        return $this->socket !== null;
    }

    public function close(): void
    {
        if ($this->socket === null) {
            throw new \LogicException('Stream already closed');
        }

        $this->destroySocket();
    }
}
