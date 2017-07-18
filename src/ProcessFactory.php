<?php declare(strict_types=1);

namespace WindowsProc;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

final class ProcessFactory
{
    private const DEFAULT_SERVER_PORT = 38351;

    private const EXE_PATH = 'D:\Visual Studio\Projects\amphpWinUtils\ChildProcessWrapper\bin\Debug\amphpChildProcessWrapper.exe';
    private const PIPE_SPEC = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    private static $serverPort = self::DEFAULT_SERVER_PORT;
    private static $serverSocket;
    private static $serverWatcher;
    private static $pidCounter = 0;

    /**
     * @var \WindowsProc\PendingProcess[]
     */
    private static $pendingProcesses = [];

    private static function onNewClientReadable($watcherId, $client)
    {
        Loop::cancel($watcherId);

        $data = fgets($client);
        $parts = array_map('intval', explode(';', trim($data)));

        if (count($parts) !== 2 || !isset(self::$pendingProcesses[$parts[1]])) {
            fwrite($client, "\x01\n");
            return;
        }

        fwrite($client, "\x00\n");

        [$streamId, $processId] = $parts;
        $proc = self::$pendingProcesses[$processId];

        $proc->sockets[$streamId] = $client;

        if (count($proc->sockets) < 3) {
            return;
        }

        unset(self::$pendingProcesses[$processId]);

        $proc->deferred->resolve(new Process($proc->handle, $proc->pipes, $proc->sockets));

        if (empty(self::$pendingProcesses)) {
            self::stopServer();
        }
    }

    private static function makeCommand(int $pid, string $command, ?string $workingDirectory): string
    {
        $result = sprintf('"%s" --port=%d --process-id=%d', self::EXE_PATH, self::$serverPort, $pid);

        if ($workingDirectory !== null) {
            $result .= ' "--cwd=' . \rtrim($workingDirectory, '\\') . '"';
        }

        $result .= ' ' . $command;

        return $result;
    }

    private static function createServerSocket(int $port)
    {
        $uri = 'tcp://127.0.0.1:' . $port;

        $socket = stream_socket_server($uri, $errNo, $errStr, STREAM_SERVER_LISTEN | STREAM_SERVER_BIND);

        if (!$socket) {
            throw new \RuntimeException($errStr, $errNo);
        }

        return $socket;
    }

    /**
     * @uses onNewClientReadable
     */
    private static function startServer(): void
    {
        static $onNewClientReadableClosure;

        if (self::$serverSocket === null) {
            self::$serverSocket = self::createServerSocket(self::$serverPort);
        }

        $onNewClientReadableClosure = $onNewClientReadableClosure
            ?? (new \ReflectionMethod(self::class, 'onNewClientReadable'))->getClosure();

        self::$serverWatcher = Loop::onReadable(self::$serverSocket, function () use ($onNewClientReadableClosure) {
            if (!$client = stream_socket_accept(self::$serverSocket)) {
                throw new \RuntimeException('Client accept failed');
            }

            \stream_set_blocking($client, false);

            $id = (int)$client;
            $watcher = Loop::onReadable($client, $onNewClientReadableClosure);
        });
    }

    private static function stopServer(): void
    {
        if (self::$serverWatcher === null) {
            throw new \LogicException('Server not currently active');
        }

        Loop::cancel(self::$serverWatcher);
        self::$serverWatcher = null;
    }

    public static function getServerPort(): int
    {
        return self::$serverPort;
    }

    public static function setServerPort(int $port): void
    {
        if (self::$serverSocket !== null) {
            throw new \LogicException('Port cannot be changed after socket has been created');
        }

        self::$serverPort = $port;
    }

    public static function start(string $command, string $workingDirectory = null, array $envVars = null): Promise
    {
        if (empty(self::$pendingProcesses)) {
            self::startServer();
        }

        $pid = self::$pidCounter++;

        $cmd = self::makeCommand($pid, $command, $workingDirectory);
        $handle = proc_open($cmd, self::PIPE_SPEC, $pipes, null, $envVars);

        $proc = new PendingProcess;
        $proc->deferred = new Deferred;
        $proc->handle = $handle;
        $proc->pipes = $pipes;

        self::$pendingProcesses[$pid] = $proc;

        return $proc->deferred->promise();
    }
}
