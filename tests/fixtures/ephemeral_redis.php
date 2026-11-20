<?php
/**
 * Disposable Pure-PHP Redis Protocol (RESP) Server for Local Integration Testing
 * Listens on 127.0.0.1:<port> and responds to standard Redis commands over real TCP sockets.
 */

$port = isset($argv[1]) ? (int)$argv[1] : 6389;
$server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Failed to start RESP server on port $port: $errstr ($errno)\n");
    exit(1);
}

$store = [];
$expirations = [];

while ($client = @stream_socket_accept($server, 30)) {
    while (!feof($client)) {
        $line = fgets($client);
        if ($line === false) break;
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = [];
        if (strpos($line, '*') === 0) {
            $numArgs = (int)substr($line, 1);
            for ($i = 0; $i < $numArgs; $i++) {
                $lenLine = fgets($client);
                $argLine = fgets($client);
                $parts[] = trim($argLine);
            }
        } else {
            $parts = explode(' ', $line);
        }
        
        if (empty($parts)) continue;
        $cmd = strtoupper($parts[0]);
        
        if ($cmd === 'PING') {
            fwrite($client, "+PONG\r\n");
        } elseif ($cmd === 'AUTH') {
            fwrite($client, "+OK\r\n");
        } elseif ($cmd === 'SELECT') {
            fwrite($client, "+OK\r\n");
        } elseif ($cmd === 'SET') {
            $key = $parts[1] ?? '';
            $val = $parts[2] ?? '';
            $store[$key] = $val;
            fwrite($client, "+OK\r\n");
        } elseif ($cmd === 'GET') {
            $key = $parts[1] ?? '';
            if (isset($store[$key])) {
                $v = (string)$store[$key];
                fwrite($client, "$" . strlen($v) . "\r\n" . $v . "\r\n");
            } else {
                fwrite($client, "$-1\r\n");
            }
        } elseif ($cmd === 'EXPIRE') {
            $key = $parts[1] ?? '';
            $ttl = (int)($parts[2] ?? 0);
            $expirations[$key] = time() + $ttl;
            fwrite($client, ":1\r\n");
        } elseif ($cmd === 'TTL') {
            $key = $parts[1] ?? '';
            if (isset($expirations[$key])) {
                $remaining = max(0, $expirations[$key] - time());
                fwrite($client, ":{$remaining}\r\n");
            } else {
                fwrite($client, ":-1\r\n");
            }
        } elseif ($cmd === 'DEL') {
            $key = $parts[1] ?? '';
            unset($store[$key], $expirations[$key]);
            fwrite($client, ":1\r\n");
        } elseif ($cmd === 'QUIT') {
            fwrite($client, "+OK\r\n");
            break;
        } else {
            fwrite($client, "+OK\r\n");
        }
    }
    fclose($client);
}
fclose($server);
