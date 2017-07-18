<?php declare(strict_types=1);

use Amp\Loop;
use Amp\Promise;
use WindowsProc\Process;

require __DIR__ . '/../vendor/autoload.php';

function process_output_stream(WindowsProc\ProcessOutputStream $stream)
{
    while (null !== $data = yield $stream->read()) {
        echo "Got data from stream #{$stream->getId()}: {$data}\n";
    }
}

Loop::run(function () {
    /** @var Process $proc */
    echo "Starting process...\n";
    $proc = yield WindowsProc\ProcessFactory::start('php child.php');
    echo "Process started\n";

    yield $proc->getStdIn()->write("This is some data written to stdin");
    $proc->getStdIn()->close();

    yield Promise\all([
        \Amp\call('process_output_stream', $proc->getStdOut()),
        \Amp\call('process_output_stream', $proc->getStdErr()),
    ]);

    var_dump($proc->close());
});
