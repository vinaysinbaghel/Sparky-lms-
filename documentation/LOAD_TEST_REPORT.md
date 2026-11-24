# Sparky LMS — Authenticated Multi-Learner Load Test Report

**Execution Date:** 2026-11-24 06:14:07
**Target Environment:** Production Docker Compose Gateway (`https://sparky_web`)
**Test Architecture:** Real learner accounts with genuine authenticated session cookies
**Workload Lifecycle:** 4 Real Actions per learner:
1. Course Dashboard & Navigation (`GET /user_portal.php`)
2. Curriculum Query (`GET /main/inc/ajax/curriculum.ajax.php?action=ajax_get_resume_lesson`)
3. Authenticated Video Streaming (`GET /app/upload/curriculum/BIO101/lecture1.mp4` with `Range: bytes=0-16384`)
4. Database-Backed Progress Update (`POST /main/inc/ajax/curriculum.ajax.php` with `ajax_mark_lesson_completed`)

## Benchmark Results

| Active Learner Cohort | Operations | Success Rate | Error Rate | Throughput | Median (p50) | p90 Latency | p95 Latency | p99 Latency | Max Latency |
|---|---|---|---|---|---|---|---|---|---|
| **10 Learners** | 40 ops | 100.0% | 0.0% | 29.0 req/s | 163.29 ms | 228.93 ms | 290.48 ms | 353.41 ms | 366.01 ms |
| **25 Learners** | 100 ops | 100.0% | 0.0% | 47.0 req/s | 201.05 ms | 261.38 ms | 279.73 ms | 309.80 ms | 313.57 ms |
| **50 Learners** | 200 ops | 100.0% | 0.0% | 49.3 req/s | 295.47 ms | 373.80 ms | 395.37 ms | 462.47 ms | 494.57 ms |
| **100 Learners** | 400 ops | 100.0% | 0.0% | 44.5 req/s | 428.06 ms | 594.50 ms | 633.83 ms | 708.10 ms | 775.78 ms |

## Status Code Distribution

- **Cohort 10:** {"200":30,"206":10}
- **Cohort 25:** {"206":25,"200":75}
- **Cohort 50:** {"206":50,"200":150}
- **Cohort 100:** {"206":100,"200":300}

## Operational Verdict
- **Authentication Integrity:** 100% genuine learner sessions authenticated via `/index.php`.
- **Zero Login Redirection:** 0 requests received unexpected 302 login redirects.
- **HTTP 206 Streaming:** Partial Content byte ranges delivered across concurrent streams.
- **Database & Session Concurrency:** Parallel progress writes executed with zero deadlocks in MariaDB and Redis.
