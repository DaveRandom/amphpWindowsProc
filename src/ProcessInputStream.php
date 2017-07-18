<?php declare(strict_types=1);

namespace WindowsProc;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

final class ProcessInputStream extends ProcessStream
{
    private $pendingWrites = [];
    private $onWritableClosure;

    private function onWritable(): void
    {
        /** @var Deferred $deferred */
        list($data, $deferred) = $this->pendingWrites[0];

        $len = fwrite($this->socket, $data);

        if ($len < strlen($data)) {
            $this->pendingWrites[0][0] = substr($data, $len);
            return;
        }

        array_shift($this->pendingWrites);

        if (empty($this->pendingWrites)) {
            Loop::cancel($this->watcher);
            $this->watcher = null;
        }

        $deferred->resolve();
    }

    /**
     * @param resource $socket
     * @param int $id
     * @uses onWritable
     */
    public function __construct($socket, int $id)
    {
        parent::__construct($socket, $id);

        $this->onWritableClosure = (new \ReflectionMethod($this, 'onWritable'))->getClosure($this);
    }

    public function write($data): Promise
    {
        if (empty($this->pendingWrites)) {
            $this->watcher = Loop::onWritable($this->socket, $this->onWritableClosure);
        }

        $this->pendingWrites[] = [$data, $deferred = new Deferred];

        return $deferred->promise();
    }
}
