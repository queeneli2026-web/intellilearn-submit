-- IntelliLearn Quiz Engine v1 -- Schema
-- Phase 1: Admin Quiz Management
-- Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('lecturer','student') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TOPICS TABLE (flat, no parent_id per D-06)
-- ============================================================
CREATE TABLE topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- QUIZZES TABLE
-- ============================================================
CREATE TABLE quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    time_limit_min SMALLINT UNSIGNED NULL COMMENT 'NULL = no limit',
    pass_percentage TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100',
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    INDEX idx_topic_id (topic_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- QUESTIONS TABLE (polymorphic base per D-02)
-- ============================================================
CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq_single','mcq_multi','true_false','fill_blank','short_answer') NOT NULL,
    points DECIMAL(5,1) NOT NULL DEFAULT 1.0,
    explanation TEXT NULL COMMENT 'Shown after answering for corrective feedback',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    INDEX idx_topic_id (topic_id),
    INDEX idx_question_type (question_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MCQ SINGLE OPTIONS (child of questions for mcq_single)
-- ============================================================
CREATE TABLE mcq_single_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    feedback_text TEXT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id),
    INDEX idx_question_correct (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MCQ MULTI OPTIONS (child of questions for mcq_multi)
-- ============================================================
CREATE TABLE mcq_multi_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    feedback_text TEXT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRUE/FALSE OPTIONS (child of questions for true_false)
-- ============================================================
CREATE TABLE true_false_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    is_true TINYINT(1) NOT NULL COMMENT '1=True, 0=False',
    is_correct TINYINT(1) NOT NULL,
    feedback_text TEXT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id),
    UNIQUE KEY uq_tf_option (question_id, is_true)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FILL IN BLANK ANSWERS (child of questions for fill_blank)
-- ============================================================
CREATE TABLE fill_blank_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    correct_answer VARCHAR(500) NOT NULL COMMENT 'Exact answer (case-insensitive comparison)',
    alternative_answers JSON NULL COMMENT '["alt1","alt2"] — additional accepted answers',
    feedback_text TEXT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SHORT ANSWER KEYWORDS (child of questions for short_answer)
-- ============================================================
CREATE TABLE short_answer_keywords (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(200) NOT NULL,
    synonyms JSON NULL COMMENT '["syn1","syn2"]',
    is_required TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Must be present for correct answer',
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- QUIZ QUESTION PIVOT (question bank assignment + ordering)
-- ============================================================
CREATE TABLE quiz_question (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    points_override DECIMAL(5,1) NULL COMMENT 'Override question default points for this quiz',
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_quiz_question (quiz_id, question_id),
    INDEX idx_quiz_sort (quiz_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ATTEMPTS (student quiz attempts)
-- ============================================================
CREATE TABLE attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED NOT NULL,
    status ENUM('in_progress','completed','abandoned','timed_out') NOT NULL DEFAULT 'in_progress',
    score DECIMAL(5,1) NULL,
    max_score DECIMAL(5,1) NULL,
    percentage DECIMAL(5,1) NULL,
    passed TINYINT(1) NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    time_taken_sec INT UNSIGNED NULL,
    attempt_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_user_quiz (user_id, quiz_id),
    INDEX idx_status (status),
    UNIQUE KEY uk_user_quiz_attempt (user_id, quiz_id, attempt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RESPONSES (per-question answers within an attempt)
-- ============================================================
CREATE TABLE responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_option_id INT UNSIGNED NULL COMMENT 'FK to mcq_*_options or tf_options for choice questions',
    answer_text TEXT NULL COMMENT 'For fill_blank and short_answer typed responses',
    is_correct TINYINT(1) NULL COMMENT 'Evaluated after submission',
    points_earned DECIMAL(5,1) NOT NULL DEFAULT 0,
    needs_review TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Short answer requires manual grading',
    time_taken_sec SMALLINT UNSIGNED NULL,
    answered_at TIMESTAMP NULL,
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_attempt_id (attempt_id),
    UNIQUE KEY uk_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- MIGRATION: Add needs_review column (Phase 2, Plan 02-03)
-- Run if upgrading from earlier schema:
--   ALTER TABLE responses ADD COLUMN needs_review TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Short answer requires manual grading';
-- ────────────────────────────────────────────────────────────

-- ============================================================
-- SEED DATA
-- ============================================================

-- Seed lecturer account
-- Password: "lecturer123" (bcrypt hash generated via password_hash with PASSWORD_BCRYPT)
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('lecturer', '$2y$12$MyAEFxlewUY7R5PgtKJYQ.Tj15aowe3PrXHzhI715lwvzCxUb2llG', 'Dr. Smith', 'smith@university.edu', 'lecturer');

-- Seed topic for development and testing
INSERT INTO topics (name, description) VALUES ('Sample Topic', 'A sample topic for development and testing');

-- ============================================================
-- Phase 2: Quiz Taking Extensions
-- ============================================================

-- Resume/timer columns for quiz attempts (D-06, D-10)
ALTER TABLE attempts
    ADD COLUMN current_question_index INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tracks position for resume (D-06)' AFTER attempt_number,
    ADD COLUMN question_order JSON NULL COMMENT 'Shuffled question ID order for this attempt' AFTER current_question_index;

-- Max attempts per quiz (D-12) — needed by AttemptController::startAction for limit checking
ALTER TABLE quizzes
    ADD COLUMN max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Max attempts allowed per student' AFTER pass_percentage;

-- Seed student account for development/testing
-- Password: "lecturer123" (same bcrypt hash as lecturer test account)
INSERT INTO users (username, password_hash, full_name, email, role)
VALUES ('student', '$2y$12$MyAEFxlewUY7R5PgtKJYQ.Tj15aowe3PrXHzhI715lwvzCxUb2llG', 'Jane Student', 'student@university.edu', 'student')
ON DUPLICATE KEY UPDATE username=username;

-- ============================================================
-- Phase 3: Performance Analytics (topic_mastery table for RSLT-05)
-- ============================================================
CREATE TABLE topic_mastery (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NOT NULL,
    mastery_level ENUM('novice','apprentice','proficient','expert') NOT NULL DEFAULT 'novice',
    total_questions INT UNSIGNED NOT NULL DEFAULT 0,
    correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
    accuracy DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    last_practiced TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_topic (user_id, topic_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Phase 4: Spaced Repetition (SM-2 per user per topic)
-- ============================================================
CREATE TABLE spaced_repetition (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NOT NULL,
    ef DECIMAL(4,2) NOT NULL DEFAULT 2.5,
    interval_days INT UNSIGNED NOT NULL DEFAULT 0,
    repetitions INT UNSIGNED NOT NULL DEFAULT 0,
    next_review_at TIMESTAMP NULL,
    last_reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_topic (user_id, topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 4: Gamification columns for users (XP + streaks)
ALTER TABLE users
    ADD COLUMN xp INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN streak_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN last_activity_date DATE NULL;

-- Phase 4: Badge definitions
CREATE TABLE badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    badge_key VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'trophy-fill'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 4: User-earned badges
CREATE TABLE user_badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_badge (user_id, badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed badge definitions
INSERT INTO badges (badge_key, name, description, icon) VALUES
('first_quiz', 'First Steps', 'Complete your first quiz', 'trophy-fill'),
('perfect_score', 'Perfect Score', 'Get 100% on a quiz', 'star-fill'),
('streak_3', 'On a Roll', 'Maintain a 3-day study streak', 'fire'),
('streak_7', 'Unstoppable', 'Maintain a 7-day streak', 'fire'),
('ten_quizzes', 'Knowledge Seeker', 'Complete 10 quizzes', 'book-fill'),
('all_expert', 'Grand Master', 'Reach Expert in all topics', 'gem');

-- ============================================================
-- End of schema
-- ============================================================
