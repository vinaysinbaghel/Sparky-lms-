# Sparky LMS — Controlled Pilot Walkthrough & UI Guide

This guide walks through the end-to-end user journeys for the three core roles in Sparky LMS: **Administrator**, **Instructor**, and **Student**.

---

## 1. System Setup & Access

### Starting the Pilot Instance
```bash
# 1. Copy production environment configuration
cp .env.example .env

# 2. Launch production stack with health checks
docker compose up -d

# 3. Access application securely
https://localhost:443
```

---

## 2. Administrator Journey

### 2.1 Dashboard & System Administration
- **Entry Point:** Navigate to `/main/admin/index.php`.
- **Capabilities:**
  - Create and manage user accounts with role assignments (Admin, Teacher, Learner).
  - Create and configure course catalogues.
  - Assign instructors to specific course offerings.
  - Enroll students individually or via session groups.
  - Review platform-wide learning activity and access logs.
  - Design and publish course certificates via the hardened Certificate Editor (`/main/admin/certificate_editor.php`).

### 2.2 Visual Progression: Administrator Workflow
```text
+--------------------------------------------------------------------------+
|  SPARKY LMS — ADMINISTRATION PORTAL                                      |
+--------------------------------------------------------------------------+
|  [Users]             [Courses]              [Sessions]      [Settings]   |
|  * Add User          * Create Course        * Manage Cohorts             |
|  * Bulk Import       * Course Categories    * Career Tracks              |
|  * User Rights       * Certificate Studio   * System Backup              |
+--------------------------------------------------------------------------+
|  QUICK METRICS:                                                          |
|  Active Students: 250    Active Courses: 14    Storage Used: 4.2 GB      |
+--------------------------------------------------------------------------+
```

---

## 3. Instructor Journey

### 3.1 Course Studio & Curriculum Building
- **Entry Point:** Access instructor dashboard at `/index.php` -> Select Course -> Open **Curriculum Studio**.
- **Capabilities:**
  - Create chapters and structure hierarchical learning modules.
  - Upload lectures, video modules (MP4, WebM), and PDF reading guides.
  - Reorder lessons using drag-and-drop or sequential chapter tools.
  - Create assignments, quizzes, and assessments.
  - Grade student submissions and review lesson completion tracking.

### 3.2 Visual Progression: Curriculum Builder
```text
+--------------------------------------------------------------------------+
|  COURSE STUDIO: Introduction to Data Engineering (CS101)                 |
+--------------------------------------------------------------------------+
|  [+ New Chapter]  [+ Add Lesson]  [Bulk Upload]  [Publish Course]        |
|                                                                          |
|  v CHAPTER 1: Foundation & Architecture                                  |
|    |-- [Video]  1.1 Distributed Systems Overview (14:32) [Edit] [Delete] |
|    |-- [PDF]    1.2 Storage Engines & ACID Guarantees    [Edit] [Delete] |
|    |-- [Quiz]   1.3 Checkpoint Assessment 1              [Edit] [Delete] |
|                                                                          |
|  > CHAPTER 2: Stream Processing with Kafka & Spark                       |
+--------------------------------------------------------------------------+
```

---

## 4. Student Journey

### 4.1 Enrollment & Course Access
- **Entry Point:** Student logs in via `/index.php` or single-click Magic Login (`/main/auth/magic_login.php?token=...`).
- **Dashboard:**
  - View all enrolled courses with visual progress bars.
  - One-click **Resume Incomplete Lesson** returns the student directly to where they left off.

### 4.2 Lecture Streaming & Verification
- **Video Playback:** Native HTML5 player with HTTP 206 byte-range seeking allows instant scrubbing without downloading the entire video.
- **Completion Tracking:** Finishing a lesson automatically records completion in `c_curriculum_lesson_track` and unlocks the next module.
- **Certificate Issuance:** Upon completing all chapters, the student can download their personalized certificate.

### 4.3 Visual Progression: Student Course View
```text
+--------------------------------------------------------------------------+
|  SPARKY LMS — LEARNER DASHBOARD                                          |
+--------------------------------------------------------------------------+
|  COURSE: Advanced Cloud Infrastructure                                   |
|  Progress: [=========================>          ] 72% Completed          |
|                                                                          |
|  [ Resume Lesson: "Section 3.2 Kubernetes Networking" ]                  |
|                                                                          |
|  CURRICULUM OUTLINE:                                                     |
|  [x] 1. Container Fundamentals                        (Completed)        |
|  [x] 2. Pod Lifecycle & Services                      (Completed)        |
|  [>] 3. Ingress Controllers & Service Meshes          (In Progress)      |
|  [ ] 4. StatefulSets & Persistent Volumes             (Locked)           |
|  [ ] 5. Capstone Project & Certificate Assessment     (Locked)           |
+--------------------------------------------------------------------------+
```

---

## 5. Security Checklist for Pilot Administrators

Before inviting external learners to the pilot:
1. Ensure `.env` contains strong, unique secrets (`DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REDIS_PASSWORD`).
2. Replace default self-signed certificates in `docker/ssl/` with valid domain certificates (e.g., Let's Encrypt).
3. Confirm Port 80 HTTP traffic redirects to Port 443 HTTPS.
4. Verify that direct file access to `/database/`, `/app/config/`, and `/.git` is blocked.
5. Run the automated test suite before opening registration:
   ```bash
   php tests/run_test_suite.php
   php tests/test_database_backup_restore.php
   ```
