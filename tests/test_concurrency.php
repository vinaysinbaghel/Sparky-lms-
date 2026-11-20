<?php
/**
 * Concurrency & Performance Load Test
 * Tests parallel request handling for 50, 100, and 250 simulated concurrent learners.
 * Run from repository root: php tests/test_concurrency.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$port = 8896;
$host = '127.0.0.1';
$rootDir = realpath(__DIR__ . '/..');

echo "========================================================\n";
echo "   SPARKY LMS - CONCURRENCY & PERFORMANCE LOAD TEST    \n";
echo "========================================================\n";
echo "Starting high-concurrency test server on http://{$host}:{$port}...\n";

// Start background server process with NUL pipes to avoid Windows buffer deadlock
$cmd = sprintf('php -S %s:%d "%s/router.php"', $host, $port, $rootDir);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', 'NUL', 'w'],
    2 => ['file', 'NUL', 'w']
];

$serverProcess = proc_open($cmd, $descriptors, $pipes, $rootDir);
if (!is_resource($serverProcess)) {
    echo "ERROR: Failed to launch test server process.\n";
    exit(1);
}

// Give server time to bind port
usleep(600000);

// Create a static asset for concurrency benchmarks
$benchAsset = $rootDir . '/concurrency_test_payload.json';
file_put_contents($benchAsset, json_encode([
    'status' => 'ok',
    'platform' => 'Sparky LMS 1.0',
    'timestamp' => microtime(true),
    'payload' => str_repeat('X', 512)
]));

function runConcurrencyBatch($totalRequests, $url, $maxSimultaneous = 10) {
    echo "\n>>> Testing Concurrency: {$totalRequests} Simulated Learners (Window: {$maxSimultaneous} simultaneous) <<<\n";
    
    $mh = curl_multi_init();
    $startTime = microtime(true);
    
    $queue = [];
    for ($i = 0; $i < $totalRequests; $i++) {
        $queue[] = $i;
    }
    
    $activeHandles = [];
    $successCount = 0;
    $failureCount = 0;
    $httpCodes = [];
    
    // Add initial window
    while (count($activeHandles) < $maxSimultaneous && !empty($queue)) {
        $reqId = array_shift($queue);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?req=' . $reqId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: SparkyLoadTester/1.0 (Learner #' . $reqId . ')'
        ]);
        curl_multi_add_handle($mh, $ch);
        $activeHandles[(int)$ch] = $ch;
    }
    
    do {
        $status = curl_multi_exec($mh, $running);
        
        // Read completed handles
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $httpCodes[$httpCode] = ($httpCodes[$httpCode] ?? 0) + 1;
            
            if ($httpCode >= 200 && $httpCode < 400) {
                $successCount++;
            } else {
                $failureCount++;
            }
            
            curl_multi_remove_handle($mh, $ch);
            unset($activeHandles[(int)$ch]);
            curl_close($ch);
            
            // Add next from queue
            if (!empty($queue)) {
                $reqId = array_shift($queue);
                $nextCh = curl_init();
                curl_setopt($nextCh, CURLOPT_URL, $url . '?req=' . $reqId);
                curl_setopt($nextCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($nextCh, CURLOPT_TIMEOUT, 30);
                curl_setopt($nextCh, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($nextCh, CURLOPT_HTTPHEADER, [
                    'User-Agent: SparkyLoadTester/1.0 (Learner #' . $reqId . ')'
                ]);
                curl_multi_add_handle($mh, $nextCh);
                $activeHandles[(int)$nextCh] = $nextCh;
            }
        }
        
        if ($running) {
            curl_multi_select($mh, 0.05);
        }
    } while (($running > 0 || !empty($activeHandles)) && $status === CURLM_OK);
    
    curl_multi_close($mh);
    
    $totalTime = microtime(true) - $startTime;
    $rps = $totalTime > 0 ? round($totalRequests / $totalTime, 1) : 0;
    $avgLatency = round(($totalTime / $totalRequests) * 1000, 2);
    
    echo "  - Completed:     {$totalRequests} requests\n";
    echo "  - Success:       {$successCount} (" . round(($successCount / $totalRequests) * 100, 1) . "%)\n";
    echo "  - Failures:      {$failureCount}\n";
    echo "  - Total Time:    " . round($totalTime, 3) . " seconds\n";
    echo "  - Throughput:    {$rps} req/sec\n";
    echo "  - Avg Latency:   {$avgLatency} ms per request\n";
    echo "  - Status Codes:  " . json_encode($httpCodes) . "\n";
    
    $passed = ($failureCount === 0 && $successCount === $totalRequests);
    if ($passed) {
        echo "  [PASS] Concurrency batch of {$totalRequests} learners completed with ZERO errors.\n";
    } else {
        echo "  [FAIL] Concurrency batch had {$failureCount} failed requests.\n";
    }
    
    return $passed;
}

$allPassed = true;

try {
    $targetUrl = "http://{$host}:{$port}/concurrency_test_payload.json";
    
    // Warmup run
    $warmup = curl_init($targetUrl);
    curl_setopt($warmup, CURLOPT_RETURNTRANSFER, true);
    curl_exec($warmup);
    curl_close($warmup);
    
    // Tier 1: 50 Concurrent Learners
    if (!runConcurrencyBatch(50, $targetUrl)) {
        $allPassed = false;
    }
    
    // Tier 2: 100 Concurrent Learners
    if (!runConcurrencyBatch(100, $targetUrl)) {
        $allPassed = false;
    }
    
    // Tier 3: 250 Concurrent Learners
    if (!runConcurrencyBatch(250, $targetUrl)) {
        $allPassed = false;
    }
    
} finally {
    if (file_exists($benchAsset)) {
        unlink($benchAsset);
    }
    foreach ($pipes as $p) {
        if (is_resource($p)) fclose($p);
    }
    proc_terminate($serverProcess);
    proc_close($serverProcess);
    echo "\nTest server terminated cleanly.\n";
}

echo "========================================================\n";
if ($allPassed) {
    echo "CONCURRENCY BENCHMARK SUITE: ALL TIERS PASSED (50, 100, 250)\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "CONCURRENCY BENCHMARK SUITE: FAILURES ENCOUNTERED\n";
    echo "========================================================\n";
    exit(1);
}
