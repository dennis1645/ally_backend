# 🎓 ALLY Backend API — Premium Scholarship Mentorship Platform

> **Capstone Project KADA (KOREA-ASEAN DIGITAL ACADEMY) BATCH 4 2026**  
> **Developed with ❤️ by Team Connectrix**

---

## 📌 About ALLY

**ALLY** is a state-of-the-art AI-powered mentorship and scholarship acceleration platform designed to empower ASEAN students to secure global higher-education scholarships (e.g., LPDP, Chevening, MEXT, KGSP, AAS, FulBright).

The **ALLY Backend** is built on top of Laravel 11/13, utilizing RESTful APIs, Llama 3.2 AI microservices, Google Gemini API, Midtrans Payment Gateway, encrypted Document Vault, Sanctum authentication with 24-hour token expiration, and automated gamification systems.

---

## 🔥 Key Technical Highlights & Architecture

- **AI Microservice Integration**: Connected to Llama 3.2 microservices (`LLAMA_API_URL`) for **Initial Diagnostic**, **Deep Diagnostic & Scholarship Matching**, **AI Mentor Recommendation**, and **OCR Essay & Document Assessment**.
- **Bidirectional Task Translator Layer**: Native bidirectional translator mapping string AI `taskId`s (`application-cv`, `application-transcript`, `application-recommendation`, `essay-motivation`, `essay-university`, `essay-star-story`, `essay-impact`) and numeric database milestone IDs.
- **AI OCR Essay & Document Assessment**: Single locked endpoint (`POST /api/journey/task/upload` or `/api/essay/assess`) evaluating essays across 6 categories (`storytelling`, `motivation`, `leadership`, `impact`, `scholarship_alignment`, `clarity`) and verifying application documents (`cv`, `transcript`, `recommendation`). Deducts 1 Token per review, auto-completes milestones if `score >= 70` or `completed: true`, and returns HTTP 422 AI validation warnings with 0 token deduction.
- **AI Offline Protection & 503 Error Handling**: In case of Ngrok tunnel offline (`ERR_NGROK_3200`) or service unavailability, local dummy fallbacks are disabled to protect data integrity, returning clean HTTP 503 error messages to the user without deducting tokens or saving fake records.
- **Smart Reminders & Upcoming Deadlines API**: `GET /api/reminders/upcoming` endpoint querying upcoming milestone deadlines, scholarship closing dates, and mentor action plans within customizable window (`?days=7`). Returns urgency status (`H-7`, `H-1`, `Hari H`, `Overdue`).
- **Automated Email Notification System**:
  - **Email Verification & Password Reset**: Segoe UI gradient design system.
  - **Task Review Lifecycle**: Automated emails for task approval (`SubmissionApprovedMail`), revision request with mentor notes (`SubmissionRevisionRequestedMail`), and mentee re-submission (`TaskResubmittedMentorMail`).
  - **AI Mentor Matcher Credentials**: Automatically emails login credentials (`MentorCredentialMail`) with temporary password (`P@ssw0rd123`) to newly generated AI mentors.
  - **Daily Smart Nudge Scheduler**: Scheduled command (`php artisan nudge:send-reminders`) running daily at 07:00 to remind users of approaching deadlines (H-3, H-1, Hari H) and trigger H+1 Overdue Freeze Streak.
- **Auto Storage Clean-up on Task Re-submission**: When mentees re-submit a revised task with a new file, old physical files in storage and old `DocumentVault` records are automatically purged to prevent disk clutter.
- **Google Gemini API Chatbot**: Intelligent AI Mentor Chatbot (`POST /api/ai-mentor/chat`) injected with full mentee context (profile, GPA, readiness score, document vault status, and timeline progress).
- **Encrypted Document Vault**: AES-256 encrypted document vault for confidential documents (LoA, IELTS certificates, recommendation letters) with signed URL preview capabilities.
- **Multi-Mentee Dashboard**: Automatically combines both directly assigned mentees (`assigned_mentor_id`) and consultation booking mentees into mentor dashboard (`GET /api/mentor/mentees` & `GET /api/mentor/submissions`).
- **Session Management & Security**: 24-hour token expiration (`config/sanctum.php`), HTTP-Only Cookie (`SameSite: Strict`, XSS & CSRF protection), rate limiting (max 3 login attempts), and strict password rules.

---

## 🛠️ Technology Stack

- **Framework**: Laravel 11 / 13 (PHP 8.2+)
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Sanctum (24-Hour Token Expiration, Bearer Token & HTTP-Only Cookie)
- **Payment Gateway**: Midtrans Snap API & Public Webhook Handler
- **AI Integrations**: Llama 3.2 Microservice & Google Gemini API
- **Mail Service**: Laravel Mailer (SMTP / Mailtrap) with HTML Blade Templates
- **Scheduler**: Laravel Console Scheduler (Daily Smart Nudge Cron)

---

## 🚀 Installation & Local Setup Guide

### 1. Requirements
Ensure your environment meets the following requirements:
- PHP >= 8.2 with extensions: `OpenSSL`, `PDO`, `Mbstring`, `Tokenizer`, `XML`, `Ctype`, `JSON`, `cURL`
- Composer 2.x
- MySQL Server 8.0+

### 2. Clone Repository & Install Dependencies
```bash
# Clone repository
git clone https://github.com/connectrix/ally_backend.git
cd ally_backend

# Install PHP dependencies
composer install
```

### 3. Environment Configuration
Copy `.env.example` to `.env` and adjust your environment settings:
```bash
cp .env.example .env
```

Configure your `.env` variables:
```ini
APP_NAME="ALLY Backend"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ally_backend
DB_USERNAME=root
DB_PASSWORD=

# Sanctum Statefulness
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

# AI Microservices & Gemini
LLAMA_API_URL=https://bodacious-armed-tightwad.ngrok-free.dev/api/
GEMINI_API_KEY=your_google_gemini_api_key

# Payment Gateway
MIDTRANS_SERVER_KEY=your_midtrans_server_key
MIDTRANS_CLIENT_KEY=your_midtrans_client_key
MIDTRANS_IS_PRODUCTION=false

# Mailer Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@ally.id"
MAIL_FROM_NAME="${APP_NAME}"
```

### 4. Application Key & Storage Link
```bash
# Generate Application Key
php artisan key:generate

# Create symbolic link for public storage
php artisan storage:link
```

### 5. Database Migration & Seeding
```bash
# Run database migrations and seed default data (Scholarships, Users, Badges, Shop Items, Smart Nudge Simulation)
php artisan migrate:fresh --seed
```

### 6. Useful Artisan Commands
```bash
# Run Smart Nudge Reminders manually
php artisan nudge:send-reminders

# Run local development server
php artisan serve
```
The API will be available at `http://127.0.0.1:8000`.

---

## 📡 Comprehensive API Endpoint Documentation

### 🔑 1. Authentication (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/register` | Register new user or claim guest onboarding token | Public |
| `POST` | `/api/login` | Login user/mentor/admin and return Bearer Token + 24-hr Cookie | Public |
| `POST` | `/api/logout` | Revoke current access token and clear auth cookie | Bearer Token |
| `POST` | `/api/forgot-password` | Request password reset token via email | Public |
| `POST` | `/api/reset-password` | Reset password using token | Public |
| `POST` | `/api/change-password` | Change password from authenticated profile | Bearer Token |

---

### 👤 2. Profile Management (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/profile` | Retrieve profile data, readiness score, badges, and assigned mentor | Bearer Token |
| `POST` | `/api/update-profile` | Update profile data, avatar, bio, headline, and bank info | Bearer Token |
| `POST` | `/api/profile/academic-target` | Update GPA, undergraduate major, target major & scholarship target | Bearer Token |

---

### 📊 3. Initial & Deep Diagnostic AI Assessments (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/diagnostic/questions` | Get paginated Initial Assessment 1 questions | Public / Bearer |
| `POST` | `/api/diagnostic/submit` | Submit Assessment 1 answers for AI readiness calculation | Public / Bearer |
| `GET` | `/api/diagnostic/my-result` | Retrieve user/guest Assessment 1 AI result | Public / Bearer |
| `GET` | `/api/deep-diagnostic/questions` | Get Deep Assessment 2 questions | Bearer Token |
| `POST` | `/api/deep-diagnostic/submit` | Submit Assessment 2 for AI scholarship matching | Bearer Token |
| `GET` | `/api/deep-diagnostic/my-result` | Retrieve Assessment 2 AI analysis and scholarship recommendations | Bearer Token |
| `POST` | `/api/deep-diagnostic/choose-recommendation` | Accept or reject AI scholarship recommendation | Bearer Token |

---

### 📝 4. AI OCR Essay & Document Assessment (`/api/essay` & `/api/journey`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/journey/task/upload` | Main upload endpoint for essay or document milestone task evaluation | Bearer Token |
| `POST` | `/api/essay/assess` | Submit PDF/DOCX/TXT file or essay text for AI OCR assessment (1 Token cost) | Bearer Token |
| `GET` | `/api/essay/history` | List user's essay assessment history, token balance & remaining daily quota | Bearer Token |
| `GET` | `/api/essay/{id}` | Get detailed essay assessment result (score, categories, strengths, weaknesses) | Bearer Token |

---

### ⏰ 5. Smart Reminders & Upcoming Deadlines (`/api/reminders`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/reminders/upcoming` | Query upcoming milestone deadlines, scholarship closing dates, and mentor tasks (`?days=7`) | Bearer Token |
| `GET` | `/api/milestones/upcoming-deadlines` | Alias endpoint for upcoming deadline reminders dashboard widget | Bearer Token |

---

### 🤝 6. AI Mentor Matching & Booking Flow (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/mentor/match` | AI Match Mentors, auto-sync local DB, auto-assign top mentor, and send credential email | Bearer Token |
| `POST` | `/api/mentor/book` | Book consultation session using mentor availability slot | Bearer Token |
| `GET` | `/api/my-bookings` | List user's consultation booking history | Bearer Token |
| `GET` | `/api/my-bookings/reschedule-popups` | Get active reschedule notifications for mentee dashboard modal | Bearer Token |
| `PATCH` | `/api/my-bookings/{id}/acknowledge-reschedule` | Acknowledge/dismiss reschedule pop-up modal | Bearer Token |
| `POST` | `/api/my-bookings/{id}/review` | Submit rating & review for completed mentor session | Bearer Token |

---

### 🗺️ 7. AI Timeline Milestones & Mentor Action Plans (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/milestones/generate` | Generate personalized AI timeline for selected target scholarship | Bearer Token |
| `GET` | `/api/milestones` | Get mentee's active milestone roadmap | Bearer Token |
| `PATCH` | `/api/milestones/{id}/complete` | Complete milestone task, award XP points & update readiness score | Bearer Token |
| `POST` | `/api/milestones/{id}/submit` | Submit/re-submit milestone task answer with text & document (saved to Vault, auto-purges old file) | Bearer Token |
| `GET` | `/api/milestones/{id}/submission` | View milestone submission status and mentor feedback notes | Bearer Token |
| `GET` | `/api/action-plans/parent/{parentMilestoneId}` | Get branching mentor action plans under parent milestone | Bearer Token |
| `PATCH` | `/api/action-plans/{id}/complete` | Mark mentor action plan completed, update parent milestone & XP | Bearer Token |

---

### 💼 8. Mentor Portal (`/api/mentor`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/mentor/mentees` | List all assigned mentees (combines `assigned_mentor_id` and booking mentees) | Mentor Scope |
| `GET` | `/api/mentor/dossier/{bookingId}` | Get mentee pre-session dossier (GPA, Readiness, Vault status, Milestones) | Mentor Scope |
| `GET` | `/api/mentor/availabilities` | View mentor's availability calendar slots | Mentor Scope |
| `POST` | `/api/mentor/availabilities` | Store single or bulk batch availability calendar slots | Mentor Scope |
| `POST` | `/api/mentor/bookings/{id}/action-plans` | Assign single/bulk branching action plans to mentee post-session | Mentor Scope |
| `PATCH` | `/api/mentor/bookings/{id}/confirm` | Confirm consultation booking with meeting URL | Mentor Scope |
| `PATCH` | `/api/mentor/bookings/{id}/reject` | Reject booking request with reason | Mentor Scope |
| `PATCH` | `/api/mentor/bookings/{id}/reschedule` | Instant reschedule consultation with mentee dashboard pop-up & email | Mentor Scope |
| `POST` | `/api/mentor/bookings/{id}/complete` | Complete session & upload session proof photo (Max 5MB) | Mentor Scope |
| `GET` | `/api/mentor/submissions` | View mentee submission queue for audit & review | Mentor Scope |
| `POST` | `/api/mentor/submissions/{id}/review` | Review mentee task submission (Approve / Request Revision + Email Mentee) | Mentor Scope |
| `GET` | `/api/mentor/dashboard/stats` | View mentor earnings, total sessions & mentee count stats | Mentor Scope |
| `GET` | `/api/mentor/invoices` | View mentor income payouts and invoice history | Mentor Scope |
| `GET` | `/api/mentor/documents` | Get shared mentor documents | Mentor Scope |
| `POST` | `/api/mentor/documents` | Upload shared mentor document | Mentor Scope |
| `DELETE` | `/api/mentor/documents/{id}` | Delete shared mentor document | Mentor Scope |

---

### 🤖 9. Chatbot Mentor AI — Google Gemini (`/api/ai-mentor`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/ai-mentor/chat` | Send message to AI Mentor Chatbot (injects full mentee context) | Bearer Token |

---

### 🔒 10. Encrypted Document Vault (`/api/vault`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/vault` | List all uploaded documents in encrypted vault | Bearer Token |
| `POST` | `/api/vault` | Upload document to vault (LoA, IELTS, Essay, etc.) | Bearer Token |
| `GET` | `/api/vault/{id}` | View/decrypt document detail | Bearer Token |
| `DELETE` | `/api/vault/{id}` | Delete document from vault | Bearer Token |
| `GET` | `/api/document/download/{documentVault}` | Preview encrypted document via temporary Signed URL | Signed Route |

---

### 🎯 11. Daily Drills & Micro-Learning (`/api/daily-drills`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/daily-drills/generate` | Generate daily micro-learning drill question | Bearer Token |
| `POST` | `/api/daily-drills/submit` | Submit answer for daily drill | Bearer Token |
| `GET` | `/api/daily-drills/history` | View daily drill attempt history | Bearer Token |
| `GET` | `/api/daily-drills/{id}` | View daily drill detail | Bearer Token |

---

### 🎫 12. Support Ticketing System (`/api/support`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/support/my-tickets` | List user's support tickets | Bearer Token |
| `GET` | `/api/support/my-tickets/{id}` | View ticket conversation detail | Bearer Token |
| `POST` | `/api/support/submit` | Submit new support ticket | Bearer Token |

---

### 🛠️ 13. Admin Management Portal (`/api/admin`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/admin/dashboard/stats` | Get overall system statistics (users, revenue, sessions) | Admin Scope |
| `GET` | `/api/admin/get-users` | List all registered users and mentors | Admin Scope |
| `GET` | `/api/admin/get-user-detail/{id}` | View specific user detail | Admin Scope |
| `POST` | `/api/admin/create-user` | Create new user or mentor account | Admin Scope |
| `PUT` | `/api/admin/update-user/{id}` | Update user information and role | Admin Scope |
| `PUT` | `/api/admin/update-user-password/{id}` | Force update user password | Admin Scope |
| `PUT` | `/api/admin/toggle-user-status/{id}` | Suspend or activate user account | Admin Scope |
| `DELETE` | `/api/admin/delete-user/{id}` | Soft delete user account | Admin Scope |
| `POST` | `/api/admin/restore-user/{id}` | Restore soft-deleted user account | Admin Scope |
| `GET` | `/api/admin/finances/mentors` | View all mentor financial earnings and session rates | Admin Scope |
| `PATCH` | `/api/admin/finances/mentors/{id}/rate` | Update mentor session rate | Admin Scope |
| `POST` | `/api/admin/finances/mentors/{id}/payout` | Process payout transaction for mentor | Admin Scope |
| `GET` | `/api/admin/finances/consultations` | Inspect mentor consultation proof uploads | Admin Scope |
| `PATCH` | `/api/admin/finances/consultations/{id}/verify-proof` | Approve or reject mentor session proof photo | Admin Scope |
| `GET` | `/api/admin/support/tickets` | View all user support tickets | Admin Scope |
| `POST` | `/api/admin/support/tickets/{id}/reply` | Reply to user support ticket | Admin Scope |
| `PATCH` | `/api/admin/support/tickets/{id}/resolve` | Resolve user support ticket | Admin Scope |

---

### 💳 14. Public Payment Webhook (`/api`)
| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `POST` | `/api/midtrans/webhook` | Midtrans Payment Gateway notification callback | Public |
| `GET` | `/api/payment/return` | Return URL redirect proxy to frontend | Public |

---

## 📬 Postman Collection

The official Postman Collection containing all request payloads, authorization parameters, and sample environments is included in the project repository:
- **Location**: [`Ally_backend_new/Ally_backend_new.json`](Ally_backend_new/Ally_backend_new.json) and root [`Ally_backend_new.json`](Ally_backend_new.json)
- Import either JSON file into Postman and set the `base_url` variable to `http://127.0.0.1:8000`.

---

## 👥 Team Connectrix — Capstone KADA Batch 4 2026

- **Project**: ALLY Mentorship & Scholarship Platform
- **Program**: Korea-ASEAN Digital Academy (KADA) Batch 4 2026
- **Organization**: Team Connectrix

---

## 📄 License
This project is proprietary software developed for the Capstone Project of Korea-ASEAN Digital Academy (KADA) Batch 4 2026. All rights reserved.
