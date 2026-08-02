<?php

declare(strict_types=0);

namespace AmpacheApi\Tests\Fixture;

use RuntimeException;

/**
 * Runs tests/fixtures/ampache-server.php under the php built-in server.
 *
 * The transport tests have to go over real http: ignore_errors and the status
 * line are http stream wrapper behaviour, so anything that stubs the wrapper
 * out would test the stub instead of the thing Phase 1 changed.
 */
final class StubServer
{
    private int $port = 0;

    /** @var resource|null */
    private $process = null;

    public function host(): string
    {
        return '127.0.0.1:' . $this->port;
    }

    public function start(): void
    {
        $this->port  = $this->freePort();
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-S', $this->host(), dirname(__DIR__) . '/fixtures/ampache-server.php'],
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the stub server');
        }

        $this->process = $process;
        $this->waitUntilReady();
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Asks the kernel for a port nobody is using, then hands it straight back.
     */
    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException('Unable to reserve a port: ' . $errstr);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitUntilReady(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        throw new RuntimeException('The stub server never came up on port ' . $this->port);
    }
}
