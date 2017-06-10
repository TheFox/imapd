<?php

namespace TheFox\Network;

use RuntimeException;

class StreamSocket extends AbstractSocket
{
    /**
     * @var string
     */
    private $ip = '';

    /**
     * @var int
     */
    private $port = 0;

    /**
     * @param string $ip
     * @param int $port
     * @return bool
     */
    public function bind(string $ip, int $port): bool
    {
        $this->ip = $ip;
        $this->port = $port;

        return true;
    }

    /**
     * @return bool
     */
    public function listen(): bool
    {
        $handle = @stream_socket_server('tcp://' . $this->ip . ':' . $this->port, $errno, $errstr);
        if ($handle !== false) {
            $this->setHandle($handle);
            return true;
        } else {
            throw new RuntimeException($errstr, $errno);
        }
    }

    /**
     * @param string $ip
     * @param int $port
     * @return bool
     */
    public function connect(string $ip, int $port): bool
    {
        $handle = @stream_socket_client('tcp://' . $ip . ':' . $port, $errno, $errstr, 2);
        if ($handle !== false) {
            $this->setHandle($handle);
            return true;
        } else {
            throw new RuntimeException($errstr, $errno);
        }
    }

    /**
     * @return null|StreamSocket
     */
    public function accept()
    {
        $handle = @stream_socket_accept($this->getHandle(), 2);
        if ($handle !== false) {
            $socket = new StreamSocket();
            $socket->setHandle($handle);

            return $socket;
        }
    }

    /**
     * @param array $readHandles
     * @param array $writeHandles
     * @param array $exceptHandles
     * @return int
     */
    public function select(array &$readHandles, array &$writeHandles, array &$exceptHandles): int
    {
        return @stream_select($readHandles, $writeHandles, $exceptHandles, 0);
    }

    /**
     * @param string $ip
     * @param int $port
     */
    public function getPeerName(string &$ip, int &$port)
    {
        $ip = 'N/A';
        $port = -1;
        $name = stream_socket_get_name($this->getHandle(), true);
        $pos = strpos($name, ':');
        if ($pos === false) {
            $ip = $name;
        } else {
            $ip = substr($name, 0, $pos);
            $port = substr($name, $pos + 1);
        }
    }

    /**
     * @return int
     */
    public function lastError(): int
    {
        return 0;
    }

    /**
     * @return string
     */
    public function strError(): string
    {
        return '';
    }

    public function clearError()
    {
    }

    /**
     * @return string
     */
    public function read(): string
    {
        return stream_socket_recvfrom($this->getHandle(), 2048);
    }

    /**
     * @param string $data
     * @return int
     */
    public function write(string $data): int
    {
        $rv = @stream_socket_sendto($this->getHandle(), $data);
        return $rv;
    }

    public function shutdown()
    {
        stream_socket_shutdown($this->getHandle(), STREAM_SHUT_RDWR);
    }

    public function close()
    {
    }
}
