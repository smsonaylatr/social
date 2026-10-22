-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: socialflow_ai
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `socialflow_ai`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `socialflow_ai` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `socialflow_ai`;

--
-- Table structure for table `ai_content_generations`
--

DROP TABLE IF EXISTS `ai_content_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_content_generations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `video_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `platform_code` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `hook_sentence` text DEFAULT NULL,
  `caption` text NOT NULL,
  `hashtags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `seo_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `call_to_action` text DEFAULT NULL,
  `suggested_product_url` text DEFAULT NULL,
  `ai_model` varchar(255) DEFAULT NULL,
  `prompt_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `completion_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `cost_usd` decimal(8,6) NOT NULL DEFAULT 0.000000,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_content_generations_video_id_platform_code_index` (`video_id`,`platform_code`),
  KEY `fk_acg_brand` (`brand_id`),
  CONSTRAINT `fk_acg_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_acg_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_content_generations`
--

LOCK TABLES `ai_content_generations` WRITE;
/*!40000 ALTER TABLE `ai_content_generations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_content_generations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_prompt_templates`
--

DROP TABLE IF EXISTS `ai_prompt_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_prompt_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `platform_code` varchar(255) NOT NULL DEFAULT 'all',
  `content_type` varchar(255) NOT NULL DEFAULT 'reels',
  `system_prompt` text NOT NULL,
  `user_prompt_template` text NOT NULL,
  `ai_model` varchar(255) NOT NULL DEFAULT 'gpt-4o-mini',
  `temperature` decimal(3,2) NOT NULL DEFAULT 0.70,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_prompt_templates_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `ai_prompt_templates_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_prompt_templates`
--

LOCK TABLES `ai_prompt_templates` WRITE;
/*!40000 ALTER TABLE `ai_prompt_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_prompt_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_usage_logs`
--

DROP TABLE IF EXISTS `api_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_usage_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(255) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL DEFAULT 'POST',
  `status_code` int(11) DEFAULT NULL,
  `response_time_ms` decimal(8,2) NOT NULL DEFAULT 0.00,
  `prompt_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `completion_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `estimated_cost_usd` decimal(8,6) NOT NULL DEFAULT 0.000000,
  `is_error` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `api_usage_logs_provider_created_at_index` (`provider`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_usage_logs`
--

LOCK TABLES `api_usage_logs` WRITE;
/*!40000 ALTER TABLE `api_usage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `auditable_type` varchar(255) DEFAULT NULL,
  `auditable_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `slogan` varchar(255) DEFAULT NULL,
  `brand_tone` varchar(255) NOT NULL DEFAULT 'energetic',
  `target_audience` varchar(255) DEFAULT NULL,
  `brand_language` varchar(255) NOT NULL DEFAULT 'tr',
  `seo_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `forbidden_words` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `default_cta` text DEFAULT NULL,
  `landing_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `campaign_notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brands_workspace_id_slug_unique` (`workspace_id`,`slug`),
  CONSTRAINT `fk_brand_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,1,'Patenli Ayakkabılar','patenli-ayakkabilar','https://patenliayakkabilar.com','Walk. Roll. Fly.','energetic','Çocuklar, gençler, ebeveynler, hediye arayanlar ve tekerlekli ayakkabı tutkunları','tr','[\"patenli ayakkabı\",\"tekerlekli ayakkabı\",\"çocuk patenli ayakkabı\",\"ışıklı patenli ayakkabı\",\"patenli spor ayakkabı\",\"roller shoes\"]','[\"ucuz\",\"çakma\",\"taklit\",\"garantisiz\"]','Hemen incelemek ve sipariş vermek için web sitemizi ziyaret edin! 🛼👟',NULL,NULL,1,'2026-10-22 01:15:43','2026-10-22 01:15:43');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('socialflow-ai-cache-livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3','i:1;',1792631928),('socialflow-ai-cache-livewire-rate-limiter:a17961fa74e9275d529f489537f179c05d50c2f3:timer','i:1792631928;',1792631928);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `playlist_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `target_platforms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_platforms`)),
  `target_social_account_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_social_account_ids`)),
  `target_url` text DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_workspace_id_slug_unique` (`workspace_id`,`slug`),
  KEY `campaigns_brand_id_foreign` (`brand_id`),
  KEY `campaigns_playlist_id_foreign` (`playlist_id`),
  CONSTRAINT `campaigns_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_playlist_id_foreign` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hashtag_groups`
--

DROP TABLE IF EXISTS `hashtag_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hashtag_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'brand',
  `color` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hashtag_groups_workspace_id_foreign` (`workspace_id`),
  KEY `hashtag_groups_brand_id_foreign` (`brand_id`),
  CONSTRAINT `hashtag_groups_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hashtag_groups_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hashtag_groups`
--

LOCK TABLES `hashtag_groups` WRITE;
/*!40000 ALTER TABLE `hashtag_groups` DISABLE KEYS */;
INSERT INTO `hashtag_groups` VALUES (1,1,1,'Marka Etiketleri','brand','#3b82f6','2026-10-22 00:52:00','2026-10-22 00:52:00'),(2,1,1,'Çocuk & Hediye Etiketleri','kids','#10b981','2026-10-22 00:52:00','2026-10-22 00:52:00');
/*!40000 ALTER TABLE `hashtag_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hashtags`
--

DROP TABLE IF EXISTS `hashtags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hashtags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `tag` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `platform_filter` varchar(255) DEFAULT NULL,
  `priority` int(10) unsigned NOT NULL DEFAULT 5,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hashtags_workspace_id_tag_unique` (`workspace_id`,`tag`),
  CONSTRAINT `fk_ht_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hashtags`
--

LOCK TABLES `hashtags` WRITE;
/*!40000 ALTER TABLE `hashtags` DISABLE KEYS */;
INSERT INTO `hashtags` VALUES (1,1,NULL,'patenliayakkabi','brand',NULL,10,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(2,1,NULL,'patenliayakkabilar','brand',NULL,10,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(3,1,NULL,'walkrollfly','brand',NULL,9,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(4,1,NULL,'tekerlekliayakkabi','product',NULL,9,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(5,1,NULL,'rollershoes','product',NULL,9,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(6,1,NULL,'cocukmodasi','kids',NULL,8,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(7,1,NULL,'cocukhediyesi','kids',NULL,8,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(8,1,NULL,'isikliayakkabi','product',NULL,8,0,NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43');
/*!40000 ALTER TABLE `hashtags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `keyword_library`
--

DROP TABLE IF EXISTS `keyword_library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `keyword_library` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `keyword` varchar(255) NOT NULL,
  `language` varchar(5) NOT NULL DEFAULT 'tr',
  `category` varchar(255) NOT NULL DEFAULT 'product',
  `search_intent` varchar(255) NOT NULL DEFAULT 'commercial',
  `priority` int(10) unsigned NOT NULL DEFAULT 5,
  `target_url` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword_library_workspace_id_keyword_unique` (`workspace_id`,`keyword`),
  KEY `fk_kl_brand` (`brand_id`),
  CONSTRAINT `fk_kl_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kl_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `keyword_library`
--

LOCK TABLES `keyword_library` WRITE;
/*!40000 ALTER TABLE `keyword_library` DISABLE KEYS */;
INSERT INTO `keyword_library` VALUES (1,1,1,'patenli ayakkabı','tr','product','transactional',10,'https://patenliayakkabilar.com',NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(2,1,1,'tekerlekli ayakkabı','tr','product','transactional',9,'https://patenliayakkabilar.com',NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(3,1,1,'çocuk patenli ayakkabı','tr','kids','commercial',9,'https://patenliayakkabilar.com/cocuk-patenli-ayakkabi',NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(4,1,1,'ışıklı patenli ayakkabı','tr','product','commercial',8,'https://patenliayakkabilar.com',NULL,0,1,'2026-10-22 01:15:43','2026-10-22 01:15:43');
/*!40000 ALTER TABLE `keyword_library` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_22_000001_create_workspaces_and_brands_tables',1),(5,'2026_10_22_000002_create_social_platforms_and_accounts_tables',1),(6,'2026_10_22_000003_create_videos_and_categories_tables',1),(7,'2026_10_22_000004_create_playlists_and_cursors_tables',1),(8,'2026_10_22_000005_create_ai_and_seo_tables',1),(9,'2026_10_22_000006_create_campaigns_and_schedules_tables',1),(10,'2026_10_22_000007_create_system_logs_and_settings_tables',1),(11,'2026_10_22_041816_create_notifications_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playlist_cursors`
--

DROP TABLE IF EXISTS `playlist_cursors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playlist_cursors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `playlist_id` bigint(20) unsigned NOT NULL,
  `current_index` int(10) unsigned NOT NULL DEFAULT 0,
  `current_video_id` bigint(20) unsigned DEFAULT NULL,
  `cycle_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_dispatched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playlist_id` (`playlist_id`),
  KEY `fk_pc_video` (`current_video_id`),
  CONSTRAINT `fk_pc_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_video` FOREIGN KEY (`current_video_id`) REFERENCES `videos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playlist_cursors`
--

LOCK TABLES `playlist_cursors` WRITE;
/*!40000 ALTER TABLE `playlist_cursors` DISABLE KEYS */;
/*!40000 ALTER TABLE `playlist_cursors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playlist_items`
--

DROP TABLE IF EXISTS `playlist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `playlist_id` bigint(20) unsigned NOT NULL,
  `video_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `priority_score` int(10) unsigned NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playlist_items_playlist_id_video_id_unique` (`playlist_id`,`video_id`),
  KEY `fk_pi_video` (`video_id`),
  CONSTRAINT `fk_pi_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playlist_items`
--

LOCK TABLES `playlist_items` WRITE;
/*!40000 ALTER TABLE `playlist_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `playlist_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `playlists`
--

DROP TABLE IF EXISTS `playlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `playlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `loop_mode` varchar(255) NOT NULL DEFAULT 'sequential',
  `is_circular` tinyint(1) NOT NULL DEFAULT 1,
  `cooldown_days` int(10) unsigned NOT NULL DEFAULT 14,
  `pause_when_exhausted` tinyint(1) NOT NULL DEFAULT 0,
  `prioritize_new_videos` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `platform_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `playlists_workspace_id_slug_unique` (`workspace_id`,`slug`),
  KEY `fk_pl_brand` (`brand_id`),
  CONSTRAINT `fk_pl_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pl_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `playlists`
--

LOCK TABLES `playlists` WRITE;
/*!40000 ALTER TABLE `playlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `playlists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `publication_attempts`
--

DROP TABLE IF EXISTS `publication_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `publication_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scheduled_post_target_id` bigint(20) unsigned NOT NULL,
  `attempt_number` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL,
  `http_status_code` int(11) DEFAULT NULL,
  `request_payload_summary` text DEFAULT NULL,
  `response_summary` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pa_target` (`scheduled_post_target_id`),
  CONSTRAINT `fk_pa_target` FOREIGN KEY (`scheduled_post_target_id`) REFERENCES `scheduled_post_targets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `publication_attempts`
--

LOCK TABLES `publication_attempts` WRITE;
/*!40000 ALTER TABLE `publication_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `publication_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheduled_post_targets`
--

DROP TABLE IF EXISTS `scheduled_post_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduled_post_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scheduled_post_id` bigint(20) unsigned NOT NULL,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `platform_code` varchar(255) NOT NULL,
  `correlation_id` varchar(64) NOT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `title` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `hashtags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `product_url` varchar(255) DEFAULT NULL,
  `external_post_id` varchar(255) DEFAULT NULL,
  `external_post_url` text DEFAULT NULL,
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correlation_id` (`correlation_id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `fk_spt_account` (`social_account_id`),
  KEY `fk_spt_post` (`scheduled_post_id`),
  CONSTRAINT `fk_spt_account` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_spt_post` FOREIGN KEY (`scheduled_post_id`) REFERENCES `scheduled_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduled_post_targets`
--

LOCK TABLES `scheduled_post_targets` WRITE;
/*!40000 ALTER TABLE `scheduled_post_targets` DISABLE KEYS */;
/*!40000 ALTER TABLE `scheduled_post_targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheduled_posts`
--

DROP TABLE IF EXISTS `scheduled_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduled_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `video_id` bigint(20) unsigned NOT NULL,
  `playlist_id` bigint(20) unsigned DEFAULT NULL,
  `schedule_id` bigint(20) unsigned DEFAULT NULL,
  `campaign_id` bigint(20) unsigned DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scheduled_posts_scheduled_at_index` (`scheduled_at`),
  KEY `fk_sp_video` (`video_id`),
  KEY `fk_sp_workspace` (`workspace_id`),
  CONSTRAINT `fk_sp_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduled_posts`
--

LOCK TABLES `scheduled_posts` WRITE;
/*!40000 ALTER TABLE `scheduled_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `scheduled_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `playlist_id` bigint(20) unsigned DEFAULT NULL,
  `campaign_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `time_of_day` time NOT NULL DEFAULT '12:00:00',
  `days_of_week` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `cron_expression` varchar(255) DEFAULT NULL,
  `target_social_account_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `max_posts_per_day` int(10) unsigned NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sch_brand` (`brand_id`),
  KEY `fk_sch_playlist` (`playlist_id`),
  KEY `fk_sch_workspace` (`workspace_id`),
  CONSTRAINT `fk_sch_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sch_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sch_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('59aDM943zByRhfSP7DPklVuvnwpbdQvvLqH9hn54',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; tr-TR) WindowsPowerShell/5.1.19041.6456','YTozOntzOjY6Il90b2tlbiI7czo0MDoiOVBTNFM4SGplc29ObkZrRTFMV3JORlF3QzdDTGlUZnk4bEhvVnc5ZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoyNToiZmlsYW1lbnQuYWRtaW4uYXV0aC5sb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1792631576),('kyYd3Sq7CoKovZhrN7pYYONRAXNZPNkguKqt8JrP',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiREtrTTJ4ZDlyYkFsTXUxVDZUU3htUE1LOFVKbjdNbWdnd1FwZHJlZCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbiI7czo1OiJyb3V0ZSI7czozMDoiZmlsYW1lbnQuYWRtaW4ucGFnZXMuZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJwYXNzd29yZF9oYXNoX3dlYiI7czo2NDoiMzhiMTIwODRkNGRjZmJiNTI0NDRmZDAzYTI3YTY3NDU2NTAxNDY1YTE2OTliZTI0ZjQyZjAwNDUyMGJjZmJjOCI7fQ==',1792631900);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'string',
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_workspace_id_foreign` (`workspace_id`),
  CONSTRAINT `settings_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_account_credentials`
--

DROP TABLE IF EXISTS `social_account_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_account_credentials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_type` varchar(255) NOT NULL DEFAULT 'Bearer',
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `scopes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sac_social_account_id_fk` (`social_account_id`),
  CONSTRAINT `fk_sac_account` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_account_credentials`
--

LOCK TABLES `social_account_credentials` WRITE;
/*!40000 ALTER TABLE `social_account_credentials` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_account_credentials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_account_permissions`
--

DROP TABLE IF EXISTS `social_account_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_account_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `social_account_id` bigint(20) unsigned NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'granted',
  `verified_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_permission_unique` (`social_account_id`,`permission_name`),
  CONSTRAINT `fk_sap_account` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_account_permissions`
--

LOCK TABLES `social_account_permissions` WRITE;
/*!40000 ALTER TABLE `social_account_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_account_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_accounts`
--

DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `platform_id` bigint(20) unsigned NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `account_type` varchar(255) NOT NULL DEFAULT 'page',
  `name` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `profile_url` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_accounts_platform_id_account_id_unique` (`platform_id`,`account_id`),
  KEY `fk_sa_brand` (`brand_id`),
  KEY `fk_sa_workspace` (`workspace_id`),
  CONSTRAINT `fk_sa_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_platform` FOREIGN KEY (`platform_id`) REFERENCES `social_platforms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_accounts`
--

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `social_platforms`
--

DROP TABLE IF EXISTS `social_platforms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_platforms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `supported_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `daily_post_limit` int(11) NOT NULL DEFAULT 20,
  `app_credentials_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `social_platforms`
--

LOCK TABLES `social_platforms` WRITE;
/*!40000 ALTER TABLE `social_platforms` DISABLE KEYS */;
INSERT INTO `social_platforms` VALUES (1,'facebook','Facebook','heroicon-o-globe-alt','Meta Graph API üzerinden Facebook Sayfaları ve Video/Reels paylaşımı',1,'[\"feed_post\",\"video\",\"reels\",\"page_insights\"]',25,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(2,'instagram','Instagram','heroicon-o-camera','Meta Graph API üzerinden Instagram Professional/Business Reels ve Video paylaşımı',1,'[\"reels\",\"video_post\",\"insights\"]',25,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(3,'youtube','YouTube','heroicon-o-play-circle','Google YouTube Data API v3 ile Video ve Shorts yükleme',1,'[\"shorts\",\"video\",\"tags\",\"playlists\"]',10,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(4,'x','X (Twitter)','heroicon-o-chat-bubble-bottom-center-text','Resmi X API v2 ile video gönderi paylaşımı',1,'[\"video_tweet\",\"short_caption\"]',50,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(5,'pinterest','Pinterest','heroicon-o-bookmark','Pinterest API v5 ile Video Pin ve pano yönetimi',1,'[\"video_pin\",\"board_select\",\"link\"]',30,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(6,'tiktok','TikTok','heroicon-o-sparkles','TikTok Content Posting API ile Creator/Business video paylaşımı',1,'[\"video_direct_post\",\"caption\",\"privacy\"]',10,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(7,'linkedin','LinkedIn','heroicon-o-briefcase','LinkedIn Posts API & Videos API ile Şirket Sayfası ve Profil video paylaşımı',1,'[\"video_post\",\"organization_page\",\"member_post\"]',20,NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43');
/*!40000 ALTER TABLE `social_platforms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_notifications`
--

DROP TABLE IF EXISTS `system_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_notifications_workspace_id_foreign` (`workspace_id`),
  KEY `system_notifications_user_id_foreign` (`user_id`),
  CONSTRAINT `system_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `system_notifications_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_notifications`
--

LOCK TABLES `system_notifications` WRITE;
/*!40000 ALTER TABLE `system_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'super_admin',
  `avatar_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'SocialFlow Admin','admin@socialflow.ai',NULL,'$2y$12$6pDpj8HXKe8By2vo4oO77.7xS.HGlnXLBl.gHblW9FjnsKpRqtuei','super_admin',NULL,1,'wSiuI8oaJarH4lHeSEOaU0w2DuLATracGBGXYCWnUvXazg1fSkRvMtNY8BsS','2026-10-22 01:15:43','2026-10-22 01:16:34');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_categories`
--

DROP TABLE IF EXISTS `video_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_categories_workspace_id_slug_unique` (`workspace_id`,`slug`),
  KEY `fk_vc_brand` (`brand_id`),
  CONSTRAINT `fk_vc_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vc_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_categories`
--

LOCK TABLES `video_categories` WRITE;
/*!40000 ALTER TABLE `video_categories` DISABLE KEYS */;
INSERT INTO `video_categories` VALUES (1,1,1,'Ürün Tanıtımı','urun-tanitimi','#3b82f6',NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(2,1,1,'Müşteri Deneyimi & Yorumlar','musteri-deneyimi','#10b981',NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(3,1,1,'Kullanım Rehberi & İpuçları','kullanim-rehberi','#f59e0b',NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(4,1,1,'Trend & Eğlenceli Reels','trend-reels','#ec4899',NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43'),(5,1,1,'Kampanya & İndirimler','kampanya-indirimler','#8b5cf6',NULL,'2026-10-22 01:15:43','2026-10-22 01:15:43');
/*!40000 ALTER TABLE `video_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_tags`
--

DROP TABLE IF EXISTS `video_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `video_id` bigint(20) unsigned NOT NULL,
  `tag` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_tags_video_id_tag_unique` (`video_id`,`tag`),
  KEY `video_tags_tag_index` (`tag`),
  CONSTRAINT `video_tags_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_tags`
--

LOCK TABLES `video_tags` WRITE;
/*!40000 ALTER TABLE `video_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_variants`
--

DROP TABLE IF EXISTS `video_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `video_id` bigint(20) unsigned NOT NULL,
  `variant_type` varchar(255) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL DEFAULT 'public',
  `file_size` bigint(20) unsigned NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `aspect_ratio` varchar(16) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_variants_video_id_foreign` (`video_id`),
  CONSTRAINT `video_variants_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_variants`
--

LOCK TABLES `video_variants` WRITE;
/*!40000 ALTER TABLE `video_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `videos`
--

DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_path` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL DEFAULT 'public',
  `file_hash` varchar(64) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `duration_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `width` int(10) unsigned NOT NULL DEFAULT 0,
  `height` int(10) unsigned NOT NULL DEFAULT 0,
  `aspect_ratio` varchar(16) NOT NULL DEFAULT '9:16',
  `fps` decimal(6,2) NOT NULL DEFAULT 30.00,
  `mime_type` varchar(255) NOT NULL DEFAULT 'video/mp4',
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ready',
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `is_reusable` tinyint(1) NOT NULL DEFAULT 1,
  `restricted_platforms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `product_url` text DEFAULT NULL,
  `custom_caption` text DEFAULT NULL,
  `custom_hashtags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `technical_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `videos_file_hash_index` (`file_hash`),
  KEY `fk_v_brand` (`brand_id`),
  KEY `fk_v_category` (`category_id`),
  KEY `fk_v_workspace` (`workspace_id`),
  CONSTRAINT `fk_v_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_v_category` FOREIGN KEY (`category_id`) REFERENCES `video_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_v_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `videos`
--

LOCK TABLES `videos` WRITE;
/*!40000 ALTER TABLE `videos` DISABLE KEYS */;
/*!40000 ALTER TABLE `videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspace_users`
--

DROP TABLE IF EXISTS `workspace_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workspace_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `workspace_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'editor',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workspace_users_unique` (`workspace_id`,`user_id`),
  KEY `fk_wu_user` (`user_id`),
  CONSTRAINT `fk_wu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wu_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspace_users`
--

LOCK TABLES `workspace_users` WRITE;
/*!40000 ALTER TABLE `workspace_users` DISABLE KEYS */;
INSERT INTO `workspace_users` VALUES (1,1,1,'super_admin','2026-10-22 01:15:43','2026-10-22 01:16:34');
/*!40000 ALTER TABLE `workspace_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workspaces`
--

DROP TABLE IF EXISTS `workspaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workspaces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workspaces`
--

LOCK TABLES `workspaces` WRITE;
/*!40000 ALTER TABLE `workspaces` DISABLE KEYS */;
INSERT INTO `workspaces` VALUES (1,'Ana Çalışma Alanı','default-workspace','Sosyal medya hesapları ve video kütüphanesi ana çalışma alanı',1,'2026-10-22 01:15:42','2026-10-22 01:15:42');
/*!40000 ALTER TABLE `workspaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'socialflow_ai'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-22  4:18:22
