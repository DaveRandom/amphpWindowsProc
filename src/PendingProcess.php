<?php declare(strict_types=1);

namespace WindowsProc;

use Amp\Deferred;

final class PendingProcess
{
    /**
     * @var Deferred
     */
    public $deferred;

    /**
     * @var resource
     */
    public $handle;

    /**
     * @var resource[]
     */
    public $pipes;

    /**
     * @var resource[]
     */
    public $sockets = [];
}
