-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 02:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `philhealth_queue`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `reference_code` varchar(24) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `walkin_name` varchar(150) DEFAULT NULL,
  `walkin_mobile` varchar(30) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `counter_id` int(11) DEFAULT NULL,
  `client_status` varchar(30) NOT NULL DEFAULT 'Regular',
  `appointment_date` date NOT NULL,
  `appointment_time` time DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `source` enum('online','walkin') NOT NULL DEFAULT 'online',
  `status` enum('booked','checked_in','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `reference_code`, `user_id`, `walkin_name`, `walkin_mobile`, `service_id`, `category_id`, `counter_id`, `client_status`, `appointment_date`, `appointment_time`, `arrival_time`, `source`, `status`, `created_at`) VALUES
(212, 'REF-20260303-A281', NULL, 'a', '', 13, 2, 8, 'Regular', '2026-03-03', NULL, '2026-03-03 12:12:55', 'walkin', 'completed', '2026-03-03 12:12:55'),
(213, 'REF-20260303-6CD8', NULL, 's', '', 14, 2, 8, 'Regular', '2026-03-03', NULL, '2026-03-03 12:13:02', 'walkin', 'completed', '2026-03-03 12:13:02'),
(214, 'REF-20260303-907B', NULL, 'd', '', 15, 2, 9, 'Regular', '2026-03-03', NULL, '2026-03-03 12:13:07', 'walkin', 'completed', '2026-03-03 12:13:07'),
(215, 'REF-20260303-7B24', NULL, 'a', '', 12, 1, 7, 'Regular', '2026-03-03', NULL, '2026-03-03 12:13:11', 'walkin', 'completed', '2026-03-03 12:13:11'),
(216, 'REF-20260303-D60D', NULL, 'd', '', 10, 1, 6, 'Regular', '2026-03-03', NULL, '2026-03-03 12:13:17', 'walkin', 'completed', '2026-03-03 12:13:17'),
(217, 'REF-20260303-3F01', NULL, 'f', '', 11, 1, 6, 'Regular', '2026-03-03', NULL, '2026-03-03 12:13:24', 'walkin', 'completed', '2026-03-03 12:13:24'),
(218, 'REF-20260303-A638', NULL, 'a', '', 13, 2, 9, 'Senior Citizen', '2026-03-03', NULL, '2026-03-03 12:13:32', 'walkin', 'checked_in', '2026-03-03 12:13:32'),
(219, 'REF-20260303-3A0A', NULL, 'ed', '', 15, 2, 9, 'PWD', '2026-03-03', NULL, '2026-03-03 12:13:55', 'walkin', 'checked_in', '2026-03-03 12:13:55'),
(220, 'REF-20260303-C5C0', NULL, 'sa', '', 10, 1, 7, 'Pregnant', '2026-03-03', NULL, '2026-03-03 12:14:02', 'walkin', 'checked_in', '2026-03-03 12:14:02'),
(221, 'REF-20260303-3F6E', NULL, 'a', '', 13, 2, 8, 'PWD', '2026-03-03', NULL, '2026-03-03 12:15:59', 'walkin', 'checked_in', '2026-03-03 12:15:59'),
(222, 'REF-20260303-1DA3', NULL, 'a', '', 14, 2, 2, 'Senior Citizen', '2026-03-03', NULL, '2026-03-03 13:10:00', 'walkin', 'completed', '2026-03-03 13:10:00'),
(223, 'REF-20260303-5A6C', NULL, 'd', '', 10, 1, 2, 'PWD', '2026-03-03', NULL, '2026-03-03 13:10:08', 'walkin', 'completed', '2026-03-03 13:10:08'),
(224, 'REF-20260303-DE2B', NULL, 'f', '', 12, 1, 7, 'Regular', '2026-03-03', NULL, '2026-03-03 13:30:12', 'walkin', 'completed', '2026-03-03 13:30:12'),
(225, 'REF-20260303-309F', NULL, 'asd', '', 10, 1, 4, 'Regular', '2026-03-03', NULL, '2026-03-03 13:30:19', 'walkin', 'completed', '2026-03-03 13:30:19'),
(226, 'REF-20260303-AF8F', NULL, 'gaw', '', 11, 1, 6, 'Regular', '2026-03-03', NULL, '2026-03-03 13:30:25', 'walkin', 'completed', '2026-03-03 13:30:25'),
(227, 'REF-20260303-E405', NULL, 'Ramel Gentugaya', '', 14, 2, 2, 'PWD', '2026-03-03', NULL, '2026-03-03 13:53:25', 'walkin', 'completed', '2026-03-03 13:53:25'),
(228, 'REF-20260303-5553', NULL, 'as', '', 13, 2, 8, 'Regular', '2026-03-03', NULL, '2026-03-03 13:55:20', 'walkin', 'completed', '2026-03-03 13:55:20'),
(229, 'REF-20260303-1BC6', NULL, 'fa', '', 13, 2, 2, 'Pregnant', '2026-03-03', NULL, '2026-03-03 14:02:41', 'walkin', 'checked_in', '2026-03-03 14:02:41');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(100) DEFAULT NULL,
  `actor_role` varchar(20) DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `queue_code` varchar(10) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `counter_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `created_at`, `actor_user_id`, `actor_name`, `actor_role`, `action`, `queue_id`, `appointment_id`, `queue_code`, `category_id`, `counter_id`, `details`) VALUES
(280, '2026-03-03 12:15:19', 2, 'Staff User', 'staff', 'call_next', 78, 216, 'M2001', 1, 6, 'Called to Counter #6 | Client: d | ClientStatus: Regular'),
(281, '2026-03-03 13:12:24', 2, 'Staff User', 'staff', 'call_next', 84, 222, 'H2002', 2, 2, 'Called to Counter #2 | Client: a | ClientStatus: Senior Citizen'),
(282, '2026-03-03 13:13:48', 2, 'Staff User', 'staff', 'call_next', 74, 212, 'H2001', 2, 8, 'Called to Counter #8 | Client: a | ClientStatus: Regular'),
(283, '2026-03-03 13:17:22', 2, 'Staff User', 'staff', 'call_next', 77, 215, 'M2001', 1, 7, 'Called to Counter #7 | Client: a | ClientStatus: Regular'),
(284, '2026-03-03 13:31:22', 3, 'John Doe', 'staff', 'mark_done', 84, 222, 'H2002', 2, 2, 'Service completed | Client: a | ClientStatus: Senior Citizen'),
(285, '2026-03-03 13:31:23', 3, 'John Doe', 'staff', 'call_next', 85, 223, 'M2003', 1, 2, 'Called to Counter #2 | Client: d | ClientStatus: PWD'),
(286, '2026-03-03 13:31:30', 3, 'John Doe', 'staff', 'call_next', 87, 225, 'M2004', 1, 4, 'Called to Counter #4 | Client: asd | ClientStatus: Regular'),
(287, '2026-03-03 13:31:38', 3, 'John Doe', 'staff', 'mark_done', 78, 216, 'M2001', 1, 6, 'Service completed | Client: d | ClientStatus: Regular'),
(288, '2026-03-03 13:31:40', 3, 'John Doe', 'staff', 'call_next', 79, 217, 'M2001', 1, 6, 'Called to Counter #6 | Client: f | ClientStatus: Regular'),
(289, '2026-03-03 13:31:45', 3, 'John Doe', 'staff', 'mark_done', 74, 212, 'H2001', 2, 8, 'Service completed | Client: a | ClientStatus: Regular'),
(290, '2026-03-03 13:31:48', 3, 'John Doe', 'staff', 'call_next', 76, 214, 'H2001', 2, 9, 'Called to Counter #9 | Client: d | ClientStatus: Regular'),
(291, '2026-03-03 13:31:54', 3, 'John Doe', 'staff', 'call_next', 75, 213, 'H2001', 2, 8, 'Called to Counter #8 | Client: s | ClientStatus: Regular'),
(292, '2026-03-03 13:32:00', 3, 'John Doe', 'staff', 'mark_done', 77, 215, 'M2001', 1, 7, 'Service completed | Client: a | ClientStatus: Regular'),
(293, '2026-03-03 13:32:03', 3, 'John Doe', 'staff', 'call_next', 86, 224, 'M2002', 1, 7, 'Called to Counter #7 | Client: f | ClientStatus: Regular'),
(294, '2026-03-03 13:32:11', 3, 'John Doe', 'staff', 'mark_done', 79, 217, 'M2001', 1, 6, 'Service completed | Client: f | ClientStatus: Regular'),
(295, '2026-03-03 13:32:15', 3, 'John Doe', 'staff', 'call_next', 88, 226, 'M2002', 1, 6, 'Called to Counter #6 | Client: gaw | ClientStatus: Regular'),
(296, '2026-03-03 13:32:18', 3, 'John Doe', 'staff', 'mark_done', 88, 226, 'M2002', 1, 6, 'Service completed | Client: gaw | ClientStatus: Regular'),
(297, '2026-03-03 13:32:53', 3, 'John Doe', 'staff', 'mark_done', 87, 225, 'M2004', 1, 4, 'Service completed | Client: asd | ClientStatus: Regular'),
(298, '2026-03-03 13:32:58', 3, 'John Doe', 'staff', 'mark_done', 85, 223, 'M2003', 1, 2, 'Service completed | Client: d | ClientStatus: PWD'),
(299, '2026-03-03 13:33:03', 3, 'John Doe', 'staff', 'mark_done', 86, 224, 'M2002', 1, 7, 'Service completed | Client: f | ClientStatus: Regular'),
(300, '2026-03-03 13:33:15', 3, 'John Doe', 'staff', 'mark_done', 75, 213, 'H2001', 2, 8, 'Service completed | Client: s | ClientStatus: Regular'),
(301, '2026-03-03 13:33:24', 3, 'John Doe', 'staff', 'mark_done', 76, 214, 'H2001', 2, 9, 'Service completed | Client: d | ClientStatus: Regular'),
(302, '2026-03-03 13:53:32', 2, 'Staff User', 'staff', 'call_next', 89, 227, 'H2003', 2, 2, 'Called to Counter #2 | Client: Ramel Gentugaya | ClientStatus: PWD'),
(303, '2026-03-03 13:53:35', 2, 'Staff User', 'staff', 'mark_done', 89, 227, 'H2003', 2, 2, 'Service completed | Client: Ramel Gentugaya | ClientStatus: PWD'),
(304, '2026-03-03 14:02:46', 2, 'Staff User', 'staff', 'call_next', 90, 228, 'H2004', 2, 8, 'Called to Counter #8 | Client: as | ClientStatus: Regular'),
(305, '2026-03-03 14:18:59', 2, 'Staff User', 'staff', 'mark_done', 90, 228, 'H2004', 2, 8, 'Service completed | Client: as | ClientStatus: Regular');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_events`
--

CREATE TABLE `chatbot_events` (
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chatbot_events`
--

INSERT INTO `chatbot_events` (`event_id`, `user_id`, `event_type`, `payload_json`, `created_at`) VALUES
(1, 4, 'user_message', '{\"text\":\"asd\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 08:51:40'),
(2, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 08:51:40'),
(3, 4, 'quick_reply_click', '{\"label\":\"Book appointment\",\"value\":\"Book appointment\",\"node_id\":\"book\"}', '2026-02-21 08:51:52'),
(4, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-02-21 08:51:52'),
(5, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 08:51:52'),
(6, 4, 'quick_reply_click', '{\"label\":\"Back to menu\",\"value\":\"Menu\",\"node_id\":\"root\"}', '2026-02-21 08:51:56'),
(7, 4, 'user_message', '{\"text\":\"Menu\",\"source\":\"button\",\"node_id\":\"root\"}', '2026-02-21 08:51:56'),
(8, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 08:51:56'),
(9, 4, 'user_message', '{\"text\":\"hi\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 08:56:55'),
(10, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 08:56:56'),
(11, 4, 'quick_reply_click', '{\"label\":\"Service requirements\",\"value\":\"Service requirements\",\"node_id\":\"req\"}', '2026-02-21 08:57:00'),
(12, 4, 'user_message', '{\"text\":\"Service requirements\",\"source\":\"button\",\"node_id\":\"req\"}', '2026-02-21 08:57:00'),
(13, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 08:57:00'),
(14, 4, 'user_message', '{\"text\":\"hi\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 09:03:57'),
(15, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 09:03:57'),
(16, 4, 'user_message', '{\"text\":\"fdfd\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 09:04:26'),
(17, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 09:04:26'),
(18, 4, 'quick_reply_click', '{\"label\":\"Book appointment\",\"value\":\"Book appointment\",\"node_id\":\"book\"}', '2026-02-21 09:04:43'),
(19, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-02-21 09:04:44'),
(20, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 09:04:44'),
(21, 4, 'user_message', '{\"text\":\"what service are you booking?\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 09:05:19'),
(22, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 09:05:19'),
(23, 4, 'user_message', '{\"text\":\"hi\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 09:06:10'),
(24, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 09:06:10'),
(25, 4, 'quick_reply_click', '{\"label\":\"Book appointment\",\"value\":\"Book appointment\",\"node_id\":\"book\"}', '2026-02-21 10:11:03'),
(26, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-02-21 10:11:03'),
(27, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:11:03'),
(28, 4, 'quick_reply_click', '{\"label\":\"What can I book?\",\"value\":\"What services can I book?\",\"node_id\":\"book_services\"}', '2026-02-21 10:11:29'),
(29, 4, 'user_message', '{\"text\":\"What services can I book?\",\"source\":\"button\",\"node_id\":\"book_services\"}', '2026-02-21 10:11:29'),
(30, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:11:29'),
(31, 4, 'quick_reply_click', '{\"label\":\"What can I book?\",\"value\":\"What services can I book?\",\"node_id\":\"book_services\"}', '2026-02-21 10:11:32'),
(32, 4, 'user_message', '{\"text\":\"What services can I book?\",\"source\":\"button\",\"node_id\":\"book_services\"}', '2026-02-21 10:11:32'),
(33, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:11:32'),
(34, 4, 'quick_reply_click', '{\"label\":\"Book appointment\",\"value\":\"Book appointment\",\"node_id\":\"book\"}', '2026-02-21 10:14:16'),
(35, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-02-21 10:14:16'),
(36, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:14:16'),
(37, 4, 'user_message', '{\"text\":\"fsd\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 10:18:17'),
(38, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:18:17'),
(39, 4, 'user_message', '{\"text\":\"what are the services you provide?\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 10:18:48'),
(40, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:18:48'),
(41, 4, 'quick_reply_click', '{\"label\":\"Book appointment\",\"value\":\"Book appointment\",\"node_id\":\"book\"}', '2026-02-21 10:20:03'),
(42, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-02-21 10:20:03'),
(43, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:20:03'),
(44, 4, 'quick_reply_click', '{\"label\":\"What can I book?\",\"value\":\"What services can I book?\",\"node_id\":\"book_services\"}', '2026-02-21 10:20:10'),
(45, 4, 'user_message', '{\"text\":\"What services can I book?\",\"source\":\"button\",\"node_id\":\"book_services\"}', '2026-02-21 10:20:10'),
(46, 4, 'bot_response', '{\"node_id\":\"book\",\"has_quick_replies\":true}', '2026-02-21 10:20:10'),
(47, 4, 'quick_reply_click', '{\"label\":\"Back to menu\",\"value\":\"Menu\",\"node_id\":\"root\"}', '2026-02-21 10:20:16'),
(48, 4, 'user_message', '{\"text\":\"Menu\",\"source\":\"button\",\"node_id\":\"root\"}', '2026-02-21 10:20:16'),
(49, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:20:16'),
(50, 4, 'quick_reply_click', '{\"label\":\"Service requirements\",\"value\":\"Service requirements\",\"node_id\":\"req\"}', '2026-02-21 10:20:18'),
(51, 4, 'user_message', '{\"text\":\"Service requirements\",\"source\":\"button\",\"node_id\":\"req\"}', '2026-02-21 10:20:18'),
(52, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 10:20:18'),
(53, 4, 'quick_reply_click', '{\"label\":\"My queue status\",\"value\":\"Queue status\",\"node_id\":\"queue\"}', '2026-02-21 10:23:15'),
(54, 4, 'user_message', '{\"text\":\"Queue status\",\"source\":\"button\",\"node_id\":\"queue\"}', '2026-02-21 10:23:15'),
(55, 4, 'bot_response', '{\"node_id\":\"queue\",\"has_quick_replies\":true}', '2026-02-21 10:23:15'),
(56, 4, 'quick_reply_click', '{\"label\":\"Meaning of Serving\\/Done\",\"value\":\"What does Serving mean?\",\"node_id\":\"queue_meaning\"}', '2026-02-21 10:23:22'),
(57, 4, 'user_message', '{\"text\":\"What does Serving mean?\",\"source\":\"button\",\"node_id\":\"queue_meaning\"}', '2026-02-21 10:23:23'),
(58, 4, 'bot_response', '{\"node_id\":\"queue\",\"has_quick_replies\":true}', '2026-02-21 10:23:23'),
(59, 4, 'quick_reply_click', '{\"label\":\"Meaning of Serving\\/Done\",\"value\":\"What does Serving mean?\",\"node_id\":\"queue_meaning\"}', '2026-02-21 10:23:32'),
(60, 4, 'user_message', '{\"text\":\"What does Serving mean?\",\"source\":\"button\",\"node_id\":\"queue_meaning\"}', '2026-02-21 10:23:32'),
(61, 4, 'bot_response', '{\"node_id\":\"queue\",\"has_quick_replies\":true}', '2026-02-21 10:23:32'),
(62, 4, 'quick_reply_click', '{\"label\":\"My queue number today\",\"value\":\"What is my queue number today?\",\"node_id\":\"queue_num\"}', '2026-02-21 10:23:35'),
(63, 4, 'user_message', '{\"text\":\"What is my queue number today?\",\"source\":\"button\",\"node_id\":\"queue_num\"}', '2026-02-21 10:23:35'),
(64, 4, 'bot_response', '{\"node_id\":\"queue\",\"has_quick_replies\":true}', '2026-02-21 10:23:35'),
(65, 4, 'quick_reply_click', '{\"label\":\"Back to menu\",\"value\":\"Menu\",\"node_id\":\"root\"}', '2026-02-21 10:23:37'),
(66, 4, 'user_message', '{\"text\":\"Menu\",\"source\":\"button\",\"node_id\":\"root\"}', '2026-02-21 10:23:37'),
(67, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:23:37'),
(68, 4, 'quick_reply_click', '{\"label\":\"Talk to agent\",\"value\":\"Talk to agent\",\"node_id\":\"agent\"}', '2026-02-21 10:23:39'),
(69, 4, 'user_message', '{\"text\":\"Talk to agent\",\"source\":\"button\",\"node_id\":\"agent\"}', '2026-02-21 10:23:39'),
(70, 4, 'bot_response', '{\"node_id\":\"agent\",\"has_quick_replies\":true}', '2026-02-21 10:23:40'),
(71, 4, 'quick_reply_click', '{\"label\":\"Back to menu\",\"value\":\"Menu\",\"node_id\":\"root\"}', '2026-02-21 10:23:51'),
(72, 4, 'user_message', '{\"text\":\"Menu\",\"source\":\"button\",\"node_id\":\"root\"}', '2026-02-21 10:23:51'),
(73, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:23:51'),
(74, 4, 'quick_reply_click', '{\"label\":\"Service requirements\",\"value\":\"Service requirements\",\"node_id\":\"req\"}', '2026-02-21 10:23:54'),
(75, 4, 'user_message', '{\"text\":\"Service requirements\",\"source\":\"button\",\"node_id\":\"req\"}', '2026-02-21 10:23:54'),
(76, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 10:23:54'),
(77, 4, 'quick_reply_click', '{\"label\":\"Service requirements\",\"value\":\"Service requirements\",\"node_id\":\"req\"}', '2026-02-21 10:26:10'),
(78, 4, 'user_message', '{\"text\":\"Service requirements\",\"source\":\"button\",\"node_id\":\"req\"}', '2026-02-21 10:26:10'),
(79, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 10:26:11'),
(80, 4, 'quick_reply_click', '{\"label\":\"What documents do I need?\",\"value\":\"What documents are required?\",\"node_id\":\"req_docs\"}', '2026-02-21 10:26:20'),
(81, 4, 'user_message', '{\"text\":\"What documents are required?\",\"source\":\"button\",\"node_id\":\"req_docs\"}', '2026-02-21 10:26:20'),
(82, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 10:26:20'),
(83, 4, 'quick_reply_click', '{\"label\":\"Requirements per service\",\"value\":\"Show requirements per service\",\"node_id\":\"req_per_service\"}', '2026-02-21 10:26:24'),
(84, 4, 'user_message', '{\"text\":\"Show requirements per service\",\"source\":\"button\",\"node_id\":\"req_per_service\"}', '2026-02-21 10:26:24'),
(85, 4, 'bot_response', '{\"node_id\":\"req\",\"has_quick_replies\":true}', '2026-02-21 10:26:24'),
(86, 4, 'user_message', '{\"text\":\"membership registration\",\"source\":\"typed\",\"node_id\":null}', '2026-02-21 10:26:44'),
(87, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-21 10:26:44'),
(88, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-21 10:54:42'),
(89, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-21 10:54:42'),
(90, 4, 'quick_reply_click', '{\"label\":\"Benepisyong Pangkalusugan\",\"value\":\"Benepisyong Pangkalusugan\",\"node_id\":\"benefits\"}', '2026-02-21 10:56:06'),
(91, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-21 10:56:06'),
(92, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-21 10:56:06'),
(93, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-21 10:56:53'),
(94, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-21 10:56:53'),
(95, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-21 11:32:38'),
(96, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-21 11:32:38'),
(97, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-21 11:39:33'),
(98, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-21 11:39:33'),
(99, 7, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-21 14:36:13'),
(100, 7, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-21 14:36:13'),
(101, 7, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-21 14:51:09'),
(102, 7, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-21 14:51:09'),
(103, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-24 12:27:24'),
(104, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 12:27:24'),
(105, 4, 'user_message', '{\"text\":\"Ano ang Philhealth Yakap?\",\"source\":\"typed\",\"node_id\":null}', '2026-02-24 12:27:29'),
(106, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-24 12:27:29'),
(107, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-24 12:28:09'),
(108, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 12:28:09'),
(109, 4, 'user_message', '{\"text\":\"Ano ang Philhealth Yakap?\",\"source\":\"typed\",\"node_id\":null}', '2026-02-24 12:28:34'),
(110, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-24 12:28:34'),
(111, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-24 20:30:02'),
(112, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-24 20:30:13'),
(113, 4, 'user_message', '{\"text\":\"Pano lumilipad ang mga ibon?\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-24 20:31:26'),
(114, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-24 20:31:31'),
(115, 4, 'user_message', '{\"text\":\"daswe\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-24 20:31:56'),
(116, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-24 20:32:00'),
(117, 4, 'user_message', '{\"text\":\"ghdgfhrwfsf\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-24 20:32:06'),
(118, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-24 20:32:09'),
(119, 4, 'user_message', '{\"text\":\"rfesdfqwrefg\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-24 20:32:14'),
(120, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-24 20:32:17'),
(121, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-24 20:42:22'),
(122, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:22'),
(123, 4, 'user_message', '{\"text\":\"updating records\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:36'),
(124, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:37'),
(125, 4, 'user_message', '{\"text\":\"dasdsafas\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:44'),
(126, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:44'),
(127, 4, 'user_message', '{\"text\":\"asdas\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:45'),
(128, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:45'),
(129, 4, 'user_message', '{\"text\":\"f\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:45'),
(130, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:45'),
(131, 4, 'user_message', '{\"text\":\"f\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:45'),
(132, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:45'),
(133, 4, 'user_message', '{\"text\":\"f\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:46'),
(134, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:46'),
(135, 4, 'user_message', '{\"text\":\"as\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:46'),
(136, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:46'),
(137, 4, 'user_message', '{\"text\":\"sa\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-02-24 20:42:46'),
(138, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:46'),
(139, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-24 20:42:50'),
(140, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 20:42:50'),
(141, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-24 20:42:52'),
(142, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-24 20:42:52'),
(143, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-24 20:42:54'),
(144, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 20:42:54'),
(145, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-24 20:42:55'),
(146, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 20:42:55'),
(147, 4, 'user_message', '{\"text\":\"asfsafas\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-02-24 20:42:57'),
(148, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-24 20:42:57'),
(149, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-26 23:19:57'),
(150, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-26 23:20:05'),
(151, 4, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-02-26 23:20:21'),
(152, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-26 23:20:29'),
(153, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-28 12:40:01'),
(154, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-28 12:40:29'),
(155, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-28 12:56:46'),
(156, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-28 12:56:53'),
(157, 4, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-28 12:57:32'),
(158, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 12:57:38'),
(159, 4, 'user_message', '{\"text\":\"3+3?\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-02-28 12:57:50'),
(160, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 12:57:50'),
(161, 4, 'user_message', '{\"text\":\"what are the benefits of philhealth?\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-02-28 12:59:00'),
(162, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 12:59:06'),
(163, 4, 'user_message', '{\"text\":\"sdasfsaf\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-02-28 12:59:15'),
(164, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 12:59:15'),
(165, 4, 'user_message', '{\"text\":\"Queue status\",\"source\":\"button\",\"node_id\":\"queue\"}', '2026-02-28 12:59:33'),
(166, 4, 'bot_response', '{\"node_id\":\"queue\",\"has_quick_replies\":true}', '2026-02-28 12:59:40'),
(167, 4, 'user_message', '{\"text\":\"What is my queue number today?\",\"source\":\"button\",\"node_id\":\"queue_num\"}', '2026-02-28 13:00:27'),
(168, 4, 'bot_response', '{\"node_id\":\"queue_num\",\"has_quick_replies\":false}', '2026-02-28 13:00:32'),
(169, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-28 13:30:24'),
(170, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-28 13:30:31'),
(171, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-28 13:31:07'),
(172, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-28 13:31:12'),
(173, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-28 14:17:48'),
(174, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-28 14:17:54'),
(175, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-28 14:18:04'),
(176, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-28 14:18:10'),
(177, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-02-28 14:19:16'),
(178, 4, 'bot_response', '{\"node_id\":\"membership\",\"has_quick_replies\":true}', '2026-02-28 14:19:19'),
(179, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-02-28 14:19:47'),
(180, 4, 'bot_response', '{\"node_id\":\"benefits\",\"has_quick_replies\":true}', '2026-02-28 14:19:52'),
(181, 4, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-02-28 14:20:02'),
(182, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 14:20:07'),
(183, 4, 'user_message', '{\"text\":\"asdsadas\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-02-28 14:20:40'),
(184, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 14:20:40'),
(185, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-02-28 17:50:32'),
(186, 4, 'bot_response', '{\"node_id\":\"appointment_process\",\"has_quick_replies\":true}', '2026-02-28 17:50:55'),
(187, 4, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"appointment_process\"}', '2026-02-28 17:51:21'),
(188, 4, 'bot_response', '{\"node_id\":\"root\",\"has_quick_replies\":true}', '2026-02-28 17:51:25'),
(189, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 15:32:15'),
(190, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 15:32:22'),
(191, 4, 'user_message', '{\"text\":\"how the birds fly? and 1+12\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 15:32:54'),
(192, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-01 15:33:00'),
(193, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 21:33:15'),
(194, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 21:33:26'),
(195, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 21:42:12'),
(196, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 21:42:32'),
(197, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-03-01 21:42:57'),
(198, 4, 'bot_response', '{\"node_id\":\"appointment_process\"}', '2026-03-01 21:43:06'),
(199, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 21:53:51'),
(200, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 21:53:59'),
(201, 4, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 21:54:11'),
(202, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-01 21:54:18'),
(203, 4, 'user_message', '{\"text\":\"dasdsa\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-03-01 21:54:22'),
(204, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-01 21:54:23'),
(205, 4, 'user_message', '{\"text\":\"how how\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-03-01 21:55:12'),
(206, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-01 21:55:12'),
(207, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-03-01 21:55:27'),
(208, 4, 'bot_response', '{\"node_id\":\"appointment_process\"}', '2026-03-01 21:55:54'),
(209, 1, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 22:39:00'),
(210, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:39:06'),
(211, 1, 'user_message', '{\"text\":\"dasfasf\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 22:39:12'),
(212, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:39:19'),
(213, 1, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 22:39:31'),
(214, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:39:39'),
(215, 1, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-01 22:39:45'),
(216, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:39:51'),
(217, 1, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 22:40:05'),
(218, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:40:35'),
(219, 1, 'user_message', '{\"text\":\"twgerfrrwe\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-01 22:40:56'),
(220, 1, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-01 22:41:01'),
(221, 1, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-01 22:41:10'),
(222, 1, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-01 22:41:16'),
(223, 1, 'user_message', '{\"text\":\"how the birds fly?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-01 22:41:26'),
(224, 1, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-01 22:41:30'),
(225, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 13:57:45'),
(226, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 13:57:52'),
(227, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-02 19:40:01'),
(228, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 19:40:09'),
(229, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 19:40:22'),
(230, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 19:40:28'),
(231, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-03-02 19:40:57'),
(232, 4, 'bot_response', '{\"node_id\":\"appointment_process\"}', '2026-03-02 19:41:04'),
(233, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-02 22:07:59'),
(234, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:08:04'),
(235, 4, 'user_message', '{\"text\":\"ano ang Yakap?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:09:05'),
(236, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:09:11'),
(237, 4, 'user_message', '{\"text\":\"paano mag avail ng yakap?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:09:39'),
(238, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:09:46'),
(239, 4, 'user_message', '{\"text\":\"ano ang mga requirments na kailangan nito?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:10:55'),
(240, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:11:04'),
(241, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 22:11:58'),
(242, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:12:04'),
(243, 4, 'user_message', '{\"text\":\"Menu\",\"source\":\"button\",\"node_id\":\"root\"}', '2026-03-02 22:12:22'),
(244, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-02 22:12:34'),
(245, 4, 'user_message', '{\"text\":\"Book appointment\",\"source\":\"button\",\"node_id\":\"book\"}', '2026-03-02 22:13:19'),
(246, 4, 'bot_response', '{\"node_id\":\"book\"}', '2026-03-02 22:13:24'),
(247, 4, 'user_message', '{\"text\":\"How to book online appointment?\",\"source\":\"button\",\"node_id\":\"book_how\"}', '2026-03-02 22:13:47'),
(248, 4, 'bot_response', '{\"node_id\":\"book_how\"}', '2026-03-02 22:13:51'),
(249, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 22:17:19'),
(250, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:17:23'),
(251, 4, 'user_message', '{\"text\":\"ano ang ibon?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 22:17:48'),
(252, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:17:54'),
(253, 4, 'user_message', '{\"text\":\"pero katung gipang train nimo ya na data FAQ naa diri?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 22:19:36'),
(254, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:20:01'),
(255, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 22:31:53'),
(256, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:31:56'),
(257, 4, 'user_message', '{\"text\":\"fdasfsa\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 22:32:03'),
(258, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:32:06'),
(259, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-02 22:32:16'),
(260, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:32:21'),
(261, 4, 'user_message', '{\"text\":\"How birds fly?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:32:39'),
(262, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-02 22:32:42'),
(263, 4, 'user_message', '{\"text\":\"Paano lumilipad ang mga ibon?\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-03-02 22:34:08'),
(264, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-02 22:34:08'),
(265, 4, 'user_message', '{\"text\":\"paano mag avail sa Membership?\",\"source\":\"typed\",\"node_id\":\"root\"}', '2026-03-02 22:34:35'),
(266, 4, 'bot_response', '{\"node_id\":\"root\"}', '2026-03-02 22:34:38'),
(267, 4, 'user_message', '{\"text\":\"Proseso ng Pagkuha ng Appointment\",\"source\":\"button\",\"node_id\":\"appointment_process\"}', '2026-03-02 22:44:33'),
(268, 4, 'bot_response', '{\"node_id\":\"appointment_process\"}', '2026-03-02 22:44:35'),
(269, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-02 22:44:40'),
(270, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:44:44'),
(271, 4, 'user_message', '{\"text\":\"Step-by-step procedure\",\"source\":\"button\",\"node_id\":\"stepbystep\"}', '2026-03-02 22:45:14'),
(272, 4, 'bot_response', '{\"node_id\":\"stepbystep\"}', '2026-03-02 22:45:19'),
(273, 4, 'user_message', '{\"text\":\"Benepisyong Pangkalusugan\",\"source\":\"button\",\"node_id\":\"benefits\"}', '2026-03-02 22:46:23'),
(274, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:46:30'),
(275, 4, 'user_message', '{\"text\":\"Ano ang Philhealth Yakap?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:46:48'),
(276, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:46:55'),
(277, 4, 'user_message', '{\"text\":\"Ano ang Philhealth Yakap?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:47:35'),
(278, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:47:39'),
(279, 4, 'user_message', '{\"text\":\"Maaari ba akong direktang magpunta (o mag walk-in) sa YAKAP clinic para magpalista?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:48:37'),
(280, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:48:42'),
(281, 4, 'user_message', '{\"text\":\"Bakit mahalaga ang PhilHealth Identification Number (PIN)?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:49:44'),
(282, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:49:52'),
(283, 4, 'user_message', '{\"text\":\"Paano ako makakapili ng Primary Care Clinic (PCC)?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:50:41'),
(284, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:50:47'),
(285, 4, 'user_message', '{\"text\":\"Ano ang mga dokumento na kailangan ko sa assisted selection ng Primary Care Clinic?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:51:26'),
(286, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:51:29'),
(287, 4, 'user_message', '{\"text\":\"Ano ang gagawin kung puno na ang napili kong clinic?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:51:53'),
(288, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:51:56'),
(289, 4, 'user_message', '{\"text\":\"Sino ang maaaring mag-avail ng OECB Package?\",\"source\":\"typed\",\"node_id\":\"benefits\"}', '2026-03-02 22:52:56'),
(290, 4, 'bot_response', '{\"node_id\":\"benefits\"}', '2026-03-02 22:53:00'),
(291, 4, 'user_message', '{\"text\":\"Impormasyon sa Membership\",\"source\":\"button\",\"node_id\":\"membership\"}', '2026-03-02 22:54:15'),
(292, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:54:34'),
(293, 4, 'user_message', '{\"text\":\"1.Ano ang layunin ng PhilHealth Circular No. 2024-0020?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 22:54:42'),
(294, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 22:54:46'),
(295, 4, 'user_message', '{\"text\":\"ano ang aking gagawin?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 23:02:28'),
(296, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 23:02:35'),
(297, 4, 'user_message', '{\"text\":\"pwede ba akong lumipad\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 23:03:04'),
(298, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 23:03:08'),
(299, 4, 'user_message', '{\"text\":\"ano ang ibon?\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 23:04:13'),
(300, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 23:04:18'),
(301, 4, 'user_message', '{\"text\":\"dsdsds\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 23:04:38'),
(302, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 23:04:46'),
(303, 4, 'user_message', '{\"text\":\"agtykiughgk\",\"source\":\"typed\",\"node_id\":\"membership\"}', '2026-03-02 23:05:14'),
(304, 4, 'bot_response', '{\"node_id\":\"membership\"}', '2026-03-02 23:05:17');

-- --------------------------------------------------------

--
-- Table structure for table `counter_services`
--

CREATE TABLE `counter_services` (
  `id` int(11) NOT NULL,
  `counter_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counter_services`
--

INSERT INTO `counter_services` (`id`, `counter_id`, `service_name`, `is_active`) VALUES
(1, 4, 'Membership Registration', 1),
(2, 4, 'Membership Renewal', 1),
(3, 4, 'Amendment of Member Data Record', 1),
(4, 6, 'Membership Registration', 1),
(5, 6, 'Membership Renewal', 1),
(6, 6, 'Amendment of Member Data Record', 1),
(7, 7, 'Membership Registration', 1),
(8, 7, 'Membership Renewal', 1),
(9, 7, 'Amendment of Member Data Record', 1),
(10, 8, 'Admission Verification', 1),
(11, 8, 'Benefit Coverage Assessment', 1),
(12, 8, 'Hospital Billing & Claims', 1),
(13, 9, 'Admission Verification', 1),
(14, 9, 'Benefit Coverage Assessment', 1),
(15, 9, 'Hospital Billing & Claims', 1),
(16, 2, 'Membership Registration', 1),
(17, 2, 'Membership Renewal', 1),
(18, 2, 'Amendment of Member Data Record', 1),
(19, 2, 'Admission Verification', 1),
(20, 2, 'Benefit Coverage Assessment', 1),
(21, 2, 'Hospital Billing & Claims', 1);

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `queue_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `queue_date` date NOT NULL,
  `category_id` int(11) NOT NULL,
  `counter_id` int(11) NOT NULL,
  `prefix` varchar(5) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `queue_code` varchar(20) NOT NULL,
  `status` enum('waiting','serving','done','cancelled') DEFAULT 'waiting',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue`
--

INSERT INTO `queue` (`queue_id`, `appointment_id`, `service_id`, `queue_date`, `category_id`, `counter_id`, `prefix`, `queue_number`, `queue_code`, `status`, `created_at`) VALUES
(74, 212, 13, '2026-03-03', 2, 8, 'H', 2001, 'H2001', 'done', '2026-03-03 12:12:55'),
(75, 213, 14, '2026-03-03', 2, 8, 'H', 2001, 'H2001', 'done', '2026-03-03 12:13:02'),
(76, 214, 15, '2026-03-03', 2, 9, 'H', 2001, 'H2001', 'done', '2026-03-03 12:13:07'),
(77, 215, 12, '2026-03-03', 1, 7, 'M', 2001, 'M2001', 'done', '2026-03-03 12:13:11'),
(78, 216, 10, '2026-03-03', 1, 6, 'M', 2001, 'M2001', 'done', '2026-03-03 12:13:17'),
(79, 217, 11, '2026-03-03', 1, 6, 'M', 2001, 'M2001', 'done', '2026-03-03 12:13:24'),
(80, 218, 13, '2026-03-03', 2, 9, 'H', 2002, 'H2002', 'done', '2026-03-03 12:13:32'),
(81, 219, 15, '2026-03-03', 2, 9, 'H', 2002, 'H2002', 'done', '2026-03-03 12:13:55'),
(82, 220, 10, '2026-03-03', 1, 7, 'M', 2002, 'M2002', 'done', '2026-03-03 12:14:02'),
(83, 221, 13, '2026-03-03', 2, 8, 'H', 2003, 'H2003', 'done', '2026-03-03 12:15:59'),
(84, 222, 14, '2026-03-03', 2, 2, 'H', 2002, 'H2002', 'done', '2026-03-03 13:10:00'),
(85, 223, 10, '2026-03-03', 1, 2, 'M', 2003, 'M2003', 'done', '2026-03-03 13:10:08'),
(86, 224, 12, '2026-03-03', 1, 7, 'M', 2002, 'M2002', 'done', '2026-03-03 13:30:12'),
(87, 225, 10, '2026-03-03', 1, 4, 'M', 2004, 'M2004', 'done', '2026-03-03 13:30:19'),
(88, 226, 11, '2026-03-03', 1, 6, 'M', 2002, 'M2002', 'done', '2026-03-03 13:30:25'),
(89, 227, 14, '2026-03-03', 2, 2, 'H', 2003, 'H2003', 'done', '2026-03-03 13:53:25'),
(90, 228, 13, '2026-03-03', 2, 8, 'H', 2004, 'H2004', 'done', '2026-03-03 13:55:20'),
(91, 229, 13, '2026-03-03', 2, 2, 'H', 2005, 'H2005', 'waiting', '2026-03-03 14:02:41');

-- --------------------------------------------------------

--
-- Table structure for table `queues`
--

CREATE TABLE `queues` (
  `queue_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `queue_date` date NOT NULL,
  `category_id` int(11) NOT NULL,
  `counter_id` int(11) NOT NULL,
  `queue_number` int(11) NOT NULL,
  `queue_code` varchar(20) NOT NULL,
  `status` enum('waiting','serving','done','cancelled') NOT NULL DEFAULT 'waiting',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_categories`
--

CREATE TABLE `queue_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(80) NOT NULL,
  `prefix` varchar(3) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_categories`
--

INSERT INTO `queue_categories` (`category_id`, `category_name`, `prefix`, `is_active`) VALUES
(1, 'Membership', 'M', 1),
(2, 'Hospitalization', 'H', 1);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `counter_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `category_id`, `counter_id`, `is_active`, `created_at`) VALUES
(10, 'Membership Registration', 1, 1, 1, '2026-02-03 11:41:46'),
(11, 'Membership Renewal', 1, 4, 1, '2026-02-03 11:41:46'),
(12, 'Amendment of Member Data Record', 1, 3, 1, '2026-02-03 11:41:46'),
(13, 'Admission Verification', 2, 4, 1, '2026-02-03 11:41:46'),
(14, 'Benefit Coverage Assessment', 2, 4, 1, '2026-02-03 11:41:46'),
(15, 'Hospital Billing & Claims', 2, 5, 1, '2026-02-03 11:41:46');

-- --------------------------------------------------------

--
-- Table structure for table `service_counters`
--

CREATE TABLE `service_counters` (
  `counter_id` int(11) NOT NULL,
  `counter_name` varchar(80) NOT NULL,
  `service_type` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_counters`
--

INSERT INTO `service_counters` (`counter_id`, `counter_name`, `service_type`, `is_active`) VALUES
(1, 'Counter 1', 'Membership', 0),
(2, 'Counter 2', 'Membership', 1),
(3, 'Counter 3', 'Membership', 0),
(4, 'Counter 4', 'Membership', 1),
(5, 'Counter 5', 'Hospitalization', 0),
(6, 'Counter 6', 'Membership', 1),
(7, 'Counter 7', 'Membership', 1),
(8, 'Counter 8', 'Hospitalization', 1),
(9, 'Counter 9', 'Hospitalization', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','staff','admin') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `mobile_number`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Jay Batiola', 'Jay@gmail.com', '', '$2y$10$n1oMe25J81850Ru/uikekum8nugPSorfbHge7eLk6j/2Eg6eWVb8q', 'user', '2026-02-03 12:05:43'),
(2, 'Staff User', 'staff@office.gov', NULL, '$2y$10$DVWB8hl6JKvlS5IoXqX9Z.y/3vAkku7ZOhLeKSNBj1hYk146/eUjO', 'staff', '2026-02-03 13:20:01'),
(3, 'John Doe', 'Doe@office.gov', NULL, '$2y$10$bO7efooqHTTxhCvqpmKtB.DhMP9mg3PqwMt3gHAx0fqSalDstOtqm', 'staff', '2026-02-03 13:38:22'),
(4, 'Darel Sevilla', 'darel@gmail.com', '', '$2y$10$hMubbRtXIsPBQOlecidyHOvxAJk8zweiTkmzIclen8176vMU0Mt6W', 'user', '2026-02-03 15:03:03'),
(6, 'bill Doe', 'bill@gmail.com', '', '$2y$10$2Gj2yozE9pZVxTKXkWGG9u7JZF.ffiEJZJ.Brthh6219kbYZJz/lm', 'user', '2026-02-04 08:44:37'),
(7, 'Mae Abadingo', 'Kathy@gmail.com', '', '$2y$10$f2pibtbTEDPGT8ZdvFgscObkMo7Vu7Mu.yIQ7sQIRc.Qg4EyPZkNm', 'user', '2026-02-04 11:09:46'),
(18, 'dela cuz', 'del@gmail.com', '09904288382', '$2y$10$WQS/mBSYZ1jfXi4aRgVdVuzpNeFyjpoTe2Z134UZ0ai0bDx8xFD0y', 'user', '2026-02-18 20:13:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `uq_appointments_reference` (`reference_code`),
  ADD KEY `idx_appt_user_date` (`user_id`,`appointment_date`),
  ADD KEY `idx_appt_status_date` (`status`,`appointment_date`),
  ADD KEY `idx_appt_category_date` (`category_id`,`appointment_date`),
  ADD KEY `fk_appt_service` (`service_id`),
  ADD KEY `fk_appt_counter` (`counter_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_actor` (`actor_user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_queue` (`queue_id`),
  ADD KEY `idx_appt` (`appointment_id`);

--
-- Indexes for table `chatbot_events`
--
ALTER TABLE `chatbot_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `counter_services`
--
ALTER TABLE `counter_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_counter_service` (`counter_id`,`service_name`),
  ADD KEY `idx_counter` (`counter_id`);

--
-- Indexes for table `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`queue_id`),
  ADD UNIQUE KEY `uq_queue_day_service_num` (`queue_date`,`service_id`,`queue_number`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `queue_date` (`queue_date`),
  ADD KEY `counter_id` (`counter_id`);

--
-- Indexes for table `queues`
--
ALTER TABLE `queues`
  ADD PRIMARY KEY (`queue_id`),
  ADD UNIQUE KEY `uq_appointment` (`appointment_id`),
  ADD UNIQUE KEY `uq_queuecode` (`queue_date`,`queue_code`),
  ADD KEY `idx_day_counter_status` (`queue_date`,`counter_id`,`status`),
  ADD KEY `idx_day_counter_cat` (`queue_date`,`counter_id`,`category_id`);

--
-- Indexes for table `queue_categories`
--
ALTER TABLE `queue_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_category_name` (`category_name`),
  ADD UNIQUE KEY `uq_category_prefix` (`prefix`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `idx_services_category` (`category_id`),
  ADD KEY `idx_services_counter` (`counter_id`);

--
-- Indexes for table `service_counters`
--
ALTER TABLE `service_counters`
  ADD PRIMARY KEY (`counter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_mobile` (`mobile_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=306;

--
-- AUTO_INCREMENT for table `chatbot_events`
--
ALTER TABLE `chatbot_events`
  MODIFY `event_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT for table `counter_services`
--
ALTER TABLE `counter_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `queue`
--
ALTER TABLE `queue`
  MODIFY `queue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `queues`
--
ALTER TABLE `queues`
  MODIFY `queue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue_categories`
--
ALTER TABLE `queue_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `service_counters`
--
ALTER TABLE `service_counters`
  MODIFY `counter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_category` FOREIGN KEY (`category_id`) REFERENCES `queue_categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_counter` FOREIGN KEY (`counter_id`) REFERENCES `service_counters` (`counter_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `counter_services`
--
ALTER TABLE `counter_services`
  ADD CONSTRAINT `fk_counter_services_counter` FOREIGN KEY (`counter_id`) REFERENCES `service_counters` (`counter_id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `queue_categories` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_services_counter` FOREIGN KEY (`counter_id`) REFERENCES `service_counters` (`counter_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
