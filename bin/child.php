<?php declare(strict_types=1);

$data = stream_get_contents(STDIN);

fwrite(STDOUT, "stdout data");
sleep(1);
fwrite(STDERR, "stderr data");
sleep(1);
fwrite(STDOUT, "stdout data");
sleep(1);
fwrite(STDERR, "stderr data");
sleep(1);
fwrite(STDOUT, "Here's what I got from stdin: $data");

exit(26);
