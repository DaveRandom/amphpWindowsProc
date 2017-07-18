<?php declare(strict_types=1);

namespace WindowsProc;

final class Process
{
    private $handle;
    private $pipes;

    private $stdIn;
    private $stdOut;
    private $stdErr;

    public function __construct($handle, array $pipes, array $sockets)
    {
        $this->handle = $handle;
        $this->pipes = $pipes;
        $this->sockets = $sockets;

        fclose($pipes[0]);
        unset($pipes[0]);

        $this->stdIn = new ProcessInputStream($sockets[0], 0);
        $this->stdOut = new ProcessOutputStream($sockets[1], 1);
        $this->stdErr = new ProcessOutputStream($sockets[2], 2);
    }

    public function close(): int
    {
        $pipes = $this->pipes;
        $handle = $this->handle;

        unset($this->pipes, $this->handle);

        \stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        \stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        if ($this->stdIn->isOpen()) {
            $this->stdIn->close();
        }

        if ($this->stdOut->isOpen()) {
            $this->stdOut->close();
        }

        if ($this->stdErr->isOpen()) {
            $this->stdErr->close();
        }

        return proc_close($handle);
    }

    public function getStdIn(): ProcessInputStream
    {
        return $this->stdIn;
    }

    public function getStdOut(): ProcessOutputStream
    {
        return $this->stdOut;
    }

    public function getStdErr(): ProcessOutputStream
    {
        return $this->stdErr;
    }
}
