-- BT-Support Database Schema
-- Version 1.0

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','supervisor','agent','client') NOT NULL DEFAULT 'client',
  `language` enum('es','en') NOT NULL DEFAULT 'es',
  `avatar` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(64) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Departments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#0d6efd',
  `email` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Department Users (many-to-many)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `department_users` (
  `department_id` int NOT NULL,
  `user_id` int NOT NULL,
  `is_supervisor` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`department_id`,`user_id`),
  KEY `fk_du_user` (`user_id`),
  CONSTRAINT `fk_du_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_du_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Priorities
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `priorities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_es` varchar(50) NOT NULL,
  `name_en` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6c757d',
  `level` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- SLA Policies
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sla_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `priority_id` int NOT NULL,
  `first_response_hours` decimal(5,1) NOT NULL DEFAULT 24.0,
  `resolution_hours` decimal(6,1) NOT NULL DEFAULT 72.0,
  `business_hours_only` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_sla_priority` (`priority_id`),
  CONSTRAINT `fk_sla_priority` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_cat_parent` (`parent_id`),
  KEY `fk_cat_dept` (`department_id`),
  CONSTRAINT `fk_cat_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tags
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6c757d',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
  `priority_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `assigned_to` int DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `sla_due_at` datetime DEFAULT NULL,
  `sla_breached` tinyint(1) NOT NULL DEFAULT 0,
  `merge_into` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `fk_t_priority` (`priority_id`),
  KEY `fk_t_category` (`category_id`),
  KEY `fk_t_dept` (`department_id`),
  KEY `fk_t_created_by` (`created_by`),
  KEY `fk_t_assigned` (`assigned_to`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_t_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_t_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_t_priority` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ticket Replies
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tr_ticket` (`ticket_id`),
  KEY `fk_tr_user` (`user_id`),
  CONSTRAINT `fk_tr_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ticket Attachments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `reply_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int NOT NULL DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ta_ticket` (`ticket_id`),
  KEY `fk_ta_reply` (`reply_id`),
  CONSTRAINT `fk_ta_reply` FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ta_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ticket Tags
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_tags` (
  `ticket_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`ticket_id`,`tag_id`),
  KEY `fk_tt_tag` (`tag_id`),
  CONSTRAINT `fk_tt_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Related Tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `related_tickets` (
  `ticket_id` int NOT NULL,
  `related_id` int NOT NULL,
  PRIMARY KEY (`ticket_id`,`related_id`),
  KEY `fk_rt_related` (`related_id`),
  CONSTRAINT `fk_rt_related` FOREIGN KEY (`related_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rt_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Canned Responses
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `canned_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `department_id` int DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cr_dept` (`department_id`),
  CONSTRAINT `fk_cr_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ratings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_rating` (`ticket_id`),
  KEY `fk_r_user` (`user_id`),
  CONSTRAINT `fk_r_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_r_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Knowledge Base Categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kb_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name_es` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'bi-folder',
  `sort_order` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_kbc_parent` (`parent_id`),
  UNIQUE KEY `uk_kbc_name_es` (`name_es`),
  CONSTRAINT `fk_kbc_parent` FOREIGN KEY (`parent_id`) REFERENCES `kb_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Knowledge Base Articles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kb_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `title_es` varchar(255) NOT NULL,
  `title_en` varchar(255) NOT NULL,
  `content_es` longtext NOT NULL,
  `content_en` longtext NOT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `views` int NOT NULL DEFAULT 0,
  `helpful_yes` int NOT NULL DEFAULT 0,
  `helpful_no` int NOT NULL DEFAULT 0,
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_kba_cat` (`category_id`),
  KEY `fk_kba_author` (`created_by`),
  CONSTRAINT `fk_kba_author` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kba_cat` FOREIGN KEY (`category_id`) REFERENCES `kb_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Email Templates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `subject_es` varchar(255) NOT NULL,
  `subject_en` varchar(255) NOT NULL,
  `body_es` longtext NOT NULL,
  `body_en` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_n_user` (`user_id`),
  CONSTRAINT `fk_n_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Activity Log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Password Resets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Login Attempts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_ip` (`email`,`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Default Data
-- --------------------------------------------------------

-- Default priorities (IGNORE prevents duplicates on re-run)
INSERT IGNORE INTO `priorities` (`name_es`, `name_en`, `color`, `level`) VALUES
('Baja',    'Low',      '#6c757d', 1),
('Normal',  'Normal',   '#0d6efd', 2),
('Alta',    'High',     '#fd7e14', 3),
('Urgente', 'Urgent',   '#dc3545', 4),
('Crítica', 'Critical', '#6f0000', 5);

-- Default SLA policies
INSERT IGNORE INTO `sla_policies` (`name`, `priority_id`, `first_response_hours`, `resolution_hours`) VALUES
('SLA Baja',    1, 48.0, 168.0),
('SLA Normal',  2, 24.0, 72.0),
('SLA Alta',    3, 8.0,  24.0),
('SLA Urgente', 4, 2.0,  8.0),
('SLA Crítica', 5, 0.5,  2.0);

-- Default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name',       'BT-Support'),
('company_slogan',     'Sistema de Soporte de Tickets'),
('company_color',      '#0d6efd'),
('company_logo',       ''),
('company_address',    ''),
('company_phone',      ''),
('company_website',    ''),
('default_language',   'es'),
('timezone',           'America/Costa_Rica'),
('allow_registration', '1'),
('require_email_verify','0'),
('auto_close_days',    '7'),
('tickets_per_page',   '25');

-- Default department
INSERT IGNORE INTO `departments` (`name`, `description`, `color`) VALUES
('Soporte General', 'Departamento de soporte general', '#0d6efd');

-- Default KB category
INSERT IGNORE INTO `kb_categories` (`name_es`, `name_en`, `icon`) VALUES
('General', 'General', 'bi-question-circle');
