# Sparky LMS — Authenticated Multi-Learner Load Test Report

**Execution Date:** 2026-11-20 10:15:29
**Test Runner:** `tests/test_concurrency.php` (SparkyLoadTester 2.0)
**Workload Profile:** 4 Actions per learner (Course Navigation, Lesson Curriculum Query, HTTP 206 Video Streaming, Database-Backed Progress Update)

## Benchmark Results

| Active Learner Cohort | Operations | Success Rate | Error Rate | Throughput | Median (p50) | p90 Latency | p95 Latency | p99 Latency | Max Latency |
|---|---|---|---|---|---|---|---|---|---|
| **10 Learners** | 40 ops | 100.0% | 0.0% | 10.9 req/s | 425.56 ms | 567.59 ms | 599.48 ms | 621.11 ms | 628.67 ms |
| **25 Learners** | 100 ops | 100.0% | 0.0% | 13.4 req/s | 731.87 ms | 815.45 ms | 826.67 ms | 839.03 ms | 853.05 ms |
| **50 Learners** | 200 ops | 100.0% | 0.0% | 12.7 req/s | 1179.81 ms | 1282.15 ms | 1295.81 ms | 1325.55 ms | 1352.25 ms |
| **100 Learners** | 400 ops | 100.0% | 0.0% | 12.6 req/s | 1566.35 ms | 1828.23 ms | 1872.74 ms | 1947.05 ms | 2007.07 ms |

## Operational Verdict
- **Concurrent Video Seeking:** Verified HTTP 206 Partial Content byte ranges served without buffer starvation.
- **Database & Session Integrity:** Parallel progress writes executed with zero deadlocks.
- **Reliability:** 100% success rate across all cohorts under concurrent learner load.
