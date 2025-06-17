<?php

use Composer\InstalledVersions;
use Ripple\Driver\Workerman\Driver5;
use Workerman\Events\Fiber;
use Workerman\Events\Swoole;
use Workerman\Events\Swow;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('tcp://0.0.0.0:7000');
$worker->count = 4;
$worker->eventLoop = event_loop();
$worker->onMessage = function (TcpConnection $con, string $data) {
    $sni = parseSNI($data);
    $host = $sni ? parseHost($sni) : null;
    if (!$sni || !$host) {
        Worker::safeEcho("无法解析SNI或Host:" . bin2hex($data));
        $con->close();
        return;
    }
    $asyncTcpConnection = new AsyncTcpConnection('tcp://' . $host);
    $asyncTcpConnection->send($data);
    $con->pipe($asyncTcpConnection);
    $asyncTcpConnection->pipe($con);
    $asyncTcpConnection->connect();
};

Worker::runAll();


function parseSNI(string $tlsHandshake): ?string
{
    $offset = 0;
    if (strlen($tlsHandshake) < 5) return null;
    if (ord($tlsHandshake[$offset++]) !== 0x16) return null;
    $offset += 2;
    $recordLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
    $offset += 2;
    $handshakeType = ord($tlsHandshake[$offset++]);
    if ($handshakeType !== 0x01) return null;
    $handshakeLength = unpack('N', "\x00" . substr($tlsHandshake, $offset, 3))[1];
    $handshakeEnd = $offset + $handshakeLength;
    if ($offset > $handshakeEnd) return null;
    $offset += 3;
    $offset += 34;
    $sessionIdLength = ord($tlsHandshake[$offset++]);
    $offset += $sessionIdLength;
    $cipherSuitesLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
    $offset += 2 + $cipherSuitesLength;
    $compressionMethodsLength = ord($tlsHandshake[$offset++]);
    $offset += $compressionMethodsLength;
    if ($offset >= strlen($tlsHandshake)) return null;
    $extensionsLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
    $offset += 2;
    $extensionsEnd = $offset + $extensionsLength;
    while ($offset < $extensionsEnd) {
        if ($offset + 4 > $extensionsEnd) break;
        $extensionType = unpack('n', substr($tlsHandshake, $offset, 2))[1];
        $offset += 2;
        $extensionLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
        $offset += 2;
        if ($extensionType === 0x0000) {
            $serverNameListLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
            $offset += 2;
            $listEnd = $offset + $serverNameListLength;
            while ($offset < $listEnd) {
                $nameType = ord($tlsHandshake[$offset++]);
                $nameLength = unpack('n', substr($tlsHandshake, $offset, 2))[1];
                $offset += 2;
                if ($nameType === 0x00) {
                    return substr($tlsHandshake, $offset, $nameLength);
                }
                $offset += $nameLength;
            }
        } else {
            $offset += $extensionLength;
        }
    }
    return null;
}

function parseHost(string $host, string $prefix = '_'): ?string
{
    $dns_get_record = dns_get_record($prefix . $host, DNS_TXT);
    $dns_get_record = array_column($dns_get_record, 'txt');
    array_walk($dns_get_record, fn (&$item) => parse_str(str_replace(';', '&', $item), $item));
    array_filter($dns_get_record, fn ($item) => is_array($item));
    if (empty($dns_get_record)) {
        return null;
    }
    [$ip, $port] = array_values(current($dns_get_record));
    return $ip . ':' . $port;
}


function event_loop(): string
{
    if (\extension_loaded('swow')) {
        return Swow::class;
    }
    if (\extension_loaded('swoole')) {
        return Swoole::class;
    }
    if (InstalledVersions::isInstalled('cloudtay/ripple-driver') && \PHP_VERSION_ID >= 80100) {
        return Driver5::class;
    }
    if (InstalledVersions::isInstalled('revolt/event-loop') && \PHP_VERSION_ID >= 80100) {
        return Fiber::class;
    }

    return '';
}