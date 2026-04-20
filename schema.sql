-- =
--  CampusMart Database Schema (PHP Version)
--  Uganda Martyrs University | Student Marketplace


CREATE DATABASE IF NOT EXISTS campusmart_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campusmart_db;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  student_id    VARCHAR(20)  UNIQUE,
  course        VARCHAR(100),
  year_of_study TINYINT      DEFAULT 1,
  phone         VARCHAR(20),
  avatar        VARCHAR(255),
  bio           TEXT,
  role          ENUM('student','admin') DEFAULT 'student',
  is_verified   TINYINT(1)   DEFAULT 0,
  is_banned     TINYINT(1)   DEFAULT 0,
  trust_score   DECIMAL(3,2) DEFAULT 5.00,
  total_reviews INT          DEFAULT 0,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL UNIQUE,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  icon        VARCHAR(10),
  description TEXT,
  sort_order  INT          DEFAULT 0,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, slug, icon, description, sort_order) VALUES
  ('Electronics',      'electronics', '💻', 'Phones, laptops, accessories', 10),
  ('Textbooks',        'textbooks',   '📚', 'Course books and study materials', 20),
  ('Clothing',         'clothing',    '👕', 'Clothes, shoes, bags', 30),
  ('Appliances',       'appliances',  '🔌', 'Kitchen and home appliances', 40),
  ('Furniture',        'furniture',   '🪑', 'Chairs, tables, beds', 50),
  ('Sports & Fitness', 'sports',      '⚽', 'Sports equipment and gear', 60),
  ('Stationery',       'stationery',  '✏️', 'Pens, notebooks, art supplies', 70),
  ('Other',            'other',       '📦', 'Miscellaneous items', 80);

-- ------------------------------------------------------------
-- LISTINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listings (
  id              INT          AUTO_INCREMENT PRIMARY KEY,
  seller_id       INT          NOT NULL,
  category_id     INT          NOT NULL,
  title           VARCHAR(150) NOT NULL,
  description     TEXT         NOT NULL,
  price           DECIMAL(12,2) NOT NULL,
  condition_type  ENUM('new','like_new','good','fair','poor') DEFAULT 'good',
  status          ENUM('pending','active','rejected','sold','reserved','removed') DEFAULT 'pending',
  location        VARCHAR(100) DEFAULT 'On Campus',
  view_count      INT          DEFAULT 0,
  is_featured     TINYINT(1)   DEFAULT 0,
  created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id)   REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_seller   (seller_id),
  INDEX idx_category (category_id),
  INDEX idx_status   (status),
  FULLTEXT INDEX ft_search (title, description)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LISTING IMAGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listing_images (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT          NOT NULL,
  image_path  VARCHAR(255) NOT NULL,
  is_primary  TINYINT(1)   DEFAULT 0,
  sort_order  INT          DEFAULT 0,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MESSAGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT          NOT NULL,
  sender_id   INT          NOT NULL,
  receiver_id INT          NOT NULL,
  content     TEXT         NOT NULL,
  is_read     TINYINT(1)   DEFAULT 0,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id)    ON DELETE CASCADE,
  INDEX idx_receiver (receiver_id),
  INDEX idx_listing  (listing_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REVIEWS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id           INT          AUTO_INCREMENT PRIMARY KEY,
  reviewer_id  INT          NOT NULL,
  reviewed_id  INT          NOT NULL,
  listing_id   INT,
  rating       TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment      TEXT,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (reviewer_id, reviewed_id, listing_id),
  FOREIGN KEY (reviewer_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (reviewed_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (listing_id)  REFERENCES listings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REPORTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
  id               INT          AUTO_INCREMENT PRIMARY KEY,
  reporter_id      INT          NOT NULL,
  listing_id       INT,
  reported_user_id INT,
  reason           ENUM('spam','fraud','inappropriate','prohibited','other') NOT NULL,
  description      TEXT,
  status           ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  admin_note       TEXT,
  created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  resolved_at      DATETIME,
  FOREIGN KEY (reporter_id)      REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (listing_id)       REFERENCES listings(id) ON DELETE SET NULL,
  FOREIGN KEY (reported_user_id) REFERENCES users(id)    ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SAVED LISTINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS saved_listings (
  user_id    INT      NOT NULL,
  listing_id INT      NOT NULL,
  saved_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, listing_id),
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ADMIN AUDIT LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT          NOT NULL,
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40),
  entity_id   INT,
  meta        TEXT,
  ip_address  VARCHAR(45),
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_created (admin_id, created_at),
  INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;
USE campusmart_db;

CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT          NOT NULL,
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40),
  entity_id   INT,
  meta        TEXT,
  ip_address  VARCHAR(45),
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_created (admin_id, created_at),
  INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;