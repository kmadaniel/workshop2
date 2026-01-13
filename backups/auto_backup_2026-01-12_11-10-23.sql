-- Automated Backup - 2026-01-12 11:10:23

DROP TABLE IF EXISTS `assignment_cancellations`;
CREATE TABLE `assignment_cancellations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `volunteer_id` int NOT NULL,
  `reason` text,
  `cancelled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `volunteer_id` (`volunteer_id`),
  KEY `cancelled_at` (`cancelled_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `assignment_cancellations` VALUES("1","97","0","unavailable","2026-01-05 13:11:22");
INSERT INTO `assignment_cancellations` VALUES("2","100","23","unavailable","2026-01-05 13:15:46");
INSERT INTO `assignment_cancellations` VALUES("3","100","23","unavailable","2026-01-05 13:44:57");
INSERT INTO `assignment_cancellations` VALUES("4","100","23","unavailable","2026-01-05 13:46:35");
INSERT INTO `assignment_cancellations` VALUES("5","100","23","unavailable","2026-01-05 13:48:50");
INSERT INTO `assignment_cancellations` VALUES("6","100","23","schedule_conflict","2026-01-05 13:58:46");
INSERT INTO `assignment_cancellations` VALUES("7","99","23","unavailable","2026-01-06 14:44:59");
INSERT INTO `assignment_cancellations` VALUES("8","102","23","schedule_conflict","2026-01-06 14:45:27");
INSERT INTO `assignment_cancellations` VALUES("23","103","23","schedule_conflict","2026-01-07 02:41:50");
INSERT INTO `assignment_cancellations` VALUES("24","112","24","unavailable","2026-01-08 01:47:38");
INSERT INTO `assignment_cancellations` VALUES("25","114","24","unavailable","2026-01-08 10:51:15");
INSERT INTO `assignment_cancellations` VALUES("26","116","23","unavailable","2026-01-09 19:44:29");
INSERT INTO `assignment_cancellations` VALUES("27","111","24","unavailable","2026-01-09 20:40:10");
DROP TABLE IF EXISTS `attendance_confirmations`;
CREATE TABLE `attendance_confirmations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `volunteer_name` varchar(100) NOT NULL,
  `volunteer_phone` varchar(20) NOT NULL,
  `status` enum('confirmed','tentative','cancelled') DEFAULT 'confirmed',
  `notes` text,
  `confirmed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_confirmation` (`distribution_id`,`volunteer_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `attendance_confirmations` VALUES("1","97","Volunteer 0","","cancelled","unavailable","2026-01-05 13:11:22","2026-01-05 13:11:22");
INSERT INTO `attendance_confirmations` VALUES("2","100","Volunteer 23","","cancelled","unavailable","2026-01-05 13:15:46","2026-01-05 13:15:46");
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `audit_id` int NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `record_id` varchar(100) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `action_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `old_value` text,
  `new_value` text,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`audit_id`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_timestamp` (`action_timestamp`),
  KEY `idx_user` (`user_name`)
) ENGINE=InnoDB AUTO_INCREMENT=517 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `audit_log` VALUES("1","victim_approvals","UPDATE","182","root@localhost","2026-01-11 11:56:48",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 11:56:48","Victim approval status changed");
INSERT INTO `audit_log` VALUES("2","victim_approvals","UPDATE","186","root@localhost","2026-01-11 11:56:58",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 11:56:58","Victim approval status changed");
INSERT INTO `audit_log` VALUES("3","distribution","INSERT","122","root@localhost","2026-01-11 12:02:31",NULL,NULL,"New Distribution: ID=122 | Victim ID: 1 | Location: Test Location | Date: 2026-01-11 | Status: Pending | Qty Sent: 100 | Coordinator: Test Coordinator","New distribution record created");
INSERT INTO `audit_log` VALUES("4","distribution","UPDATE","122","root@localhost","2026-01-11 12:02:51",NULL,"Old Status: Pending | Location: Test Location | Qty Sent: 100 | Qty Received: 0","New Status: Dispatched | Location: Test Location | Qty Sent: 100 | Qty Received: 95","Distribution details updated");
INSERT INTO `audit_log` VALUES("5","distribution","DELETE","122","root@localhost","2026-01-11 12:03:53",NULL,"Deleted Distribution: ID=122 | Location: Test Location | Date: 2026-01-11",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("6","distribution_items","DELETE","54","root@localhost","2026-01-11 20:17:08",NULL,"Deleted Item: ID=54 | Distribution ID: 114 | Victim ID: 121",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("7","distribution","DELETE","114","root@localhost","2026-01-11 20:17:08",NULL,"Deleted Distribution: ID=114 | Location: N/A | Date: 2026-01-23",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("8","distribution_items","DELETE","1","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=1 | Distribution ID: 59 | Victim ID: 109",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("9","distribution_items","DELETE","2","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=2 | Distribution ID: 60 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("10","distribution_items","DELETE","3","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=3 | Distribution ID: 61 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("11","distribution_items","DELETE","4","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=4 | Distribution ID: 62 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("12","distribution_items","DELETE","5","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=5 | Distribution ID: 63 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("13","distribution_items","DELETE","6","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=6 | Distribution ID: 64 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("14","distribution_items","DELETE","7","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=7 | Distribution ID: 65 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("15","distribution_items","DELETE","10","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=10 | Distribution ID: 67 | Victim ID: 74",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("16","distribution_items","DELETE","11","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=11 | Distribution ID: 67 | Victim ID: 76",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("17","distribution_items","DELETE","12","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=12 | Distribution ID: 68 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("18","distribution_items","DELETE","13","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=13 | Distribution ID: 68 | Victim ID: 43",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("19","distribution_items","DELETE","16","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=16 | Distribution ID: 71 | Victim ID: 112",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("20","distribution_items","DELETE","17","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=17 | Distribution ID: 72 | Victim ID: 112",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("21","distribution_items","DELETE","24","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=24 | Distribution ID: 78 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("22","distribution_items","DELETE","25","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=25 | Distribution ID: 79 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("23","distribution_items","DELETE","26","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=26 | Distribution ID: 80 | Victim ID: 39",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("24","distribution_items","DELETE","29","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=29 | Distribution ID: 83 | Victim ID: 109",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("25","distribution_items","DELETE","30","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=30 | Distribution ID: 84 | Victim ID: 74",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("26","distribution_items","DELETE","31","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=31 | Distribution ID: 85 | Victim ID: 112",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("27","distribution_items","DELETE","32","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=32 | Distribution ID: 86 | Victim ID: 63",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("28","distribution_items","DELETE","33","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=33 | Distribution ID: 87 | Victim ID: 63",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("29","distribution_items","DELETE","34","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=34 | Distribution ID: 88 | Victim ID: 63",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("30","distribution_items","DELETE","35","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=35 | Distribution ID: 89 | Victim ID: 63",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("31","distribution_items","DELETE","36","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=36 | Distribution ID: 90 | Victim ID: 50",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("32","distribution_items","DELETE","37","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=37 | Distribution ID: 91 | Victim ID: 50",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("33","distribution_items","DELETE","38","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=38 | Distribution ID: 92 | Victim ID: 74",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("34","distribution_items","DELETE","39","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=39 | Distribution ID: 93 | Victim ID: 50",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("35","distribution_items","DELETE","40","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=40 | Distribution ID: 94 | Victim ID: 50",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("36","distribution_items","DELETE","41","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=41 | Distribution ID: 95 | Victim ID: 50",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("37","distribution_items","DELETE","42","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=42 | Distribution ID: 96 | Victim ID: 66",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("38","distribution_items","DELETE","44","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=44 | Distribution ID: 98 | Victim ID: 112",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("39","distribution_items","DELETE","45","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=45 | Distribution ID: 99 | Victim ID: 112",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("40","distribution_items","DELETE","47","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=47 | Distribution ID: 101 | Victim ID: 107",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("41","distribution_items","DELETE","48","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=48 | Distribution ID: 102 | Victim ID: 108",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("42","distribution_items","DELETE","49","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=49 | Distribution ID: 103 | Victim ID: 72",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("43","distribution_items","DELETE","50","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=50 | Distribution ID: 104 | Victim ID: 81",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("44","distribution_items","DELETE","51","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=51 | Distribution ID: 111 | Victim ID: 97",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("45","distribution_items","DELETE","52","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=52 | Distribution ID: 112 | Victim ID: 105",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("46","distribution_items","DELETE","53","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=53 | Distribution ID: 113 | Victim ID: 40",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("47","distribution_items","DELETE","55","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=55 | Distribution ID: 115 | Victim ID: 132",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("48","distribution_items","DELETE","56","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=56 | Distribution ID: 116 | Victim ID: 2",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("49","distribution_items","DELETE","57","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=57 | Distribution ID: 117 | Victim ID: 59",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("50","distribution_items","DELETE","61","root@localhost","2026-01-11 20:27:24",NULL,"Deleted Item: ID=61 | Distribution ID: 121 | Victim ID: 103",NULL,"Distribution item removed");
INSERT INTO `audit_log` VALUES("51","victim_approvals","DELETE","1","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=1 | Victim ID: 74 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("52","victim_approvals","DELETE","2","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=2 | Victim ID: 76 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("53","victim_approvals","DELETE","3","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=3 | Victim ID: 96 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("54","victim_approvals","DELETE","4","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=4 | Victim ID: 97 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("55","victim_approvals","DELETE","5","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=5 | Victim ID: 102 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("56","victim_approvals","DELETE","6","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=6 | Victim ID: 71 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("57","victim_approvals","DELETE","7","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=7 | Victim ID: 72 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("58","victim_approvals","DELETE","8","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=8 | Victim ID: 73 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("59","victim_approvals","DELETE","9","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=9 | Victim ID: 75 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("60","victim_approvals","DELETE","10","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=10 | Victim ID: 77 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("61","victim_approvals","DELETE","11","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=11 | Victim ID: 78 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("62","victim_approvals","DELETE","12","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=12 | Victim ID: 79 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("63","victim_approvals","DELETE","13","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=13 | Victim ID: 80 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("64","victim_approvals","DELETE","14","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=14 | Victim ID: 95 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("65","victim_approvals","DELETE","15","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=15 | Victim ID: 98 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("66","victim_approvals","DELETE","16","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=16 | Victim ID: 99 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("67","victim_approvals","DELETE","17","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=17 | Victim ID: 100 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("68","victim_approvals","DELETE","18","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=18 | Victim ID: 101 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("69","victim_approvals","DELETE","19","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=19 | Victim ID: 103 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("70","victim_approvals","DELETE","20","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=20 | Victim ID: 104 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("71","victim_approvals","DELETE","21","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=21 | Victim ID: 105 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("72","victim_approvals","DELETE","22","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=22 | Victim ID: 107 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("73","victim_approvals","DELETE","23","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=23 | Victim ID: 108 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("74","victim_approvals","DELETE","24","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=24 | Victim ID: 81 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("75","victim_approvals","DELETE","25","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=25 | Victim ID: 82 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("76","victim_approvals","DELETE","26","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=26 | Victim ID: 84 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("77","victim_approvals","DELETE","27","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=27 | Victim ID: 85 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("78","victim_approvals","DELETE","28","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=28 | Victim ID: 86 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("79","victim_approvals","DELETE","29","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=29 | Victim ID: 88 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("80","victim_approvals","DELETE","30","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=30 | Victim ID: 89 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("81","victim_approvals","DELETE","31","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=31 | Victim ID: 90 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("82","victim_approvals","DELETE","32","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=32 | Victim ID: 91 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("83","victim_approvals","DELETE","33","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=33 | Victim ID: 93 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("84","victim_approvals","DELETE","34","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=34 | Victim ID: 94 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("85","victim_approvals","DELETE","35","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=35 | Victim ID: 83 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("86","victim_approvals","DELETE","36","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=36 | Victim ID: 87 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("87","victim_approvals","DELETE","37","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=37 | Victim ID: 92 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("88","victim_approvals","DELETE","38","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=38 | Victim ID: 39 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("89","victim_approvals","DELETE","39","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=39 | Victim ID: 43 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("90","victim_approvals","DELETE","40","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=40 | Victim ID: 49 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("91","victim_approvals","DELETE","41","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=41 | Victim ID: 51 | Status: Rejected",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("92","victim_approvals","DELETE","42","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=42 | Victim ID: 54 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("93","victim_approvals","DELETE","43","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=43 | Victim ID: 59 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("94","victim_approvals","DELETE","44","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=44 | Victim ID: 63 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("95","victim_approvals","DELETE","45","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=45 | Victim ID: 69 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("96","victim_approvals","DELETE","46","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=46 | Victim ID: 70 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("97","victim_approvals","DELETE","47","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=47 | Victim ID: 36 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("98","victim_approvals","DELETE","48","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=48 | Victim ID: 37 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("99","victim_approvals","DELETE","49","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=49 | Victim ID: 38 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("100","victim_approvals","DELETE","50","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=50 | Victim ID: 40 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("101","victim_approvals","DELETE","51","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=51 | Victim ID: 41 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("102","victim_approvals","DELETE","52","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=52 | Victim ID: 42 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("103","victim_approvals","DELETE","53","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=53 | Victim ID: 44 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("104","victim_approvals","DELETE","54","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=54 | Victim ID: 45 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("105","victim_approvals","DELETE","55","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=55 | Victim ID: 46 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("106","victim_approvals","DELETE","56","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=56 | Victim ID: 47 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("107","victim_approvals","DELETE","57","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=57 | Victim ID: 48 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("108","victim_approvals","DELETE","58","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=58 | Victim ID: 50 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("109","victim_approvals","DELETE","59","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=59 | Victim ID: 52 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("110","victim_approvals","DELETE","60","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=60 | Victim ID: 53 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("111","victim_approvals","DELETE","61","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=61 | Victim ID: 55 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("112","victim_approvals","DELETE","62","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=62 | Victim ID: 56 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("113","victim_approvals","DELETE","63","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=63 | Victim ID: 57 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("114","victim_approvals","DELETE","64","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=64 | Victim ID: 58 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("115","victim_approvals","DELETE","65","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=65 | Victim ID: 60 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("116","victim_approvals","DELETE","66","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=66 | Victim ID: 61 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("117","victim_approvals","DELETE","67","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=67 | Victim ID: 62 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("118","victim_approvals","DELETE","68","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=68 | Victim ID: 64 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("119","victim_approvals","DELETE","69","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=69 | Victim ID: 65 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("120","victim_approvals","DELETE","70","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=70 | Victim ID: 66 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("121","victim_approvals","DELETE","71","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=71 | Victim ID: 67 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("122","victim_approvals","DELETE","72","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=72 | Victim ID: 68 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("123","victim_approvals","DELETE","73","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=73 | Victim ID: 110 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("124","victim_approvals","DELETE","74","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=74 | Victim ID: 112 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("125","victim_approvals","DELETE","75","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=75 | Victim ID: 109 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("126","victim_approvals","DELETE","76","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=76 | Victim ID: 5 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("127","victim_approvals","DELETE","77","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=77 | Victim ID: 11 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("128","victim_approvals","DELETE","78","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=78 | Victim ID: 15 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("129","victim_approvals","DELETE","79","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=79 | Victim ID: 19 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("130","victim_approvals","DELETE","80","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=80 | Victim ID: 21 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("131","victim_approvals","DELETE","81","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=81 | Victim ID: 25 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("132","victim_approvals","DELETE","82","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=82 | Victim ID: 29 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("133","victim_approvals","DELETE","83","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=83 | Victim ID: 35 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("134","victim_approvals","DELETE","84","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=84 | Victim ID: 2 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("135","victim_approvals","DELETE","85","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=85 | Victim ID: 3 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("136","victim_approvals","DELETE","86","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=86 | Victim ID: 4 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("137","victim_approvals","DELETE","87","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=87 | Victim ID: 6 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("138","victim_approvals","DELETE","88","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=88 | Victim ID: 7 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("139","victim_approvals","DELETE","89","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=89 | Victim ID: 8 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("140","victim_approvals","DELETE","90","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=90 | Victim ID: 9 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("141","victim_approvals","DELETE","91","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=91 | Victim ID: 10 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("142","victim_approvals","DELETE","92","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=92 | Victim ID: 12 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("143","victim_approvals","DELETE","93","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=93 | Victim ID: 13 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("144","victim_approvals","DELETE","94","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=94 | Victim ID: 14 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("145","victim_approvals","DELETE","95","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=95 | Victim ID: 16 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("146","victim_approvals","DELETE","96","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=96 | Victim ID: 17 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("147","victim_approvals","DELETE","97","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=97 | Victim ID: 18 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("148","victim_approvals","DELETE","98","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=98 | Victim ID: 20 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("149","victim_approvals","DELETE","99","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=99 | Victim ID: 22 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("150","victim_approvals","DELETE","100","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=100 | Victim ID: 23 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("151","victim_approvals","DELETE","101","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=101 | Victim ID: 24 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("152","victim_approvals","DELETE","102","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=102 | Victim ID: 26 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("153","victim_approvals","DELETE","103","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=103 | Victim ID: 27 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("154","victim_approvals","DELETE","104","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=104 | Victim ID: 28 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("155","victim_approvals","DELETE","105","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=105 | Victim ID: 30 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("156","victim_approvals","DELETE","106","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=106 | Victim ID: 31 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("157","victim_approvals","DELETE","107","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=107 | Victim ID: 32 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("158","victim_approvals","DELETE","108","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=108 | Victim ID: 33 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("159","victim_approvals","DELETE","109","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=109 | Victim ID: 34 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("160","victim_approvals","DELETE","110","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=110 | Victim ID: 1 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("161","victim_approvals","DELETE","111","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=111 | Victim ID: 116 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("162","victim_approvals","DELETE","112","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=112 | Victim ID: 121 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("163","victim_approvals","DELETE","113","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=113 | Victim ID: 118 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("164","victim_approvals","DELETE","114","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=114 | Victim ID: 119 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("165","victim_approvals","DELETE","115","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=115 | Victim ID: 123 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("166","victim_approvals","DELETE","116","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=116 | Victim ID: 130 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("167","victim_approvals","DELETE","117","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=117 | Victim ID: 131 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("168","victim_approvals","DELETE","118","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=118 | Victim ID: 132 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("169","victim_approvals","DELETE","119","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=119 | Victim ID: 1 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("170","victim_approvals","DELETE","120","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=120 | Victim ID: 71 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("171","victim_approvals","DELETE","121","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=121 | Victim ID: 72 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("172","victim_approvals","DELETE","122","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=122 | Victim ID: 73 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("173","victim_approvals","DELETE","123","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=123 | Victim ID: 74 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("174","victim_approvals","DELETE","124","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=124 | Victim ID: 75 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("175","victim_approvals","DELETE","125","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=125 | Victim ID: 76 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("176","victim_approvals","DELETE","126","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=126 | Victim ID: 77 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("177","victim_approvals","DELETE","127","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=127 | Victim ID: 78 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("178","victim_approvals","DELETE","128","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=128 | Victim ID: 79 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("179","victim_approvals","DELETE","129","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=129 | Victim ID: 80 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("180","victim_approvals","DELETE","130","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=130 | Victim ID: 81 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("181","victim_approvals","DELETE","131","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=131 | Victim ID: 82 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("182","victim_approvals","DELETE","132","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=132 | Victim ID: 83 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("183","victim_approvals","DELETE","133","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=133 | Victim ID: 84 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("184","victim_approvals","DELETE","134","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=134 | Victim ID: 85 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("185","victim_approvals","DELETE","135","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=135 | Victim ID: 86 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("186","victim_approvals","DELETE","136","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=136 | Victim ID: 87 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("187","victim_approvals","DELETE","137","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=137 | Victim ID: 88 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("188","victim_approvals","DELETE","138","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=138 | Victim ID: 89 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("189","victim_approvals","DELETE","139","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=139 | Victim ID: 90 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("190","victim_approvals","DELETE","140","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=140 | Victim ID: 91 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("191","victim_approvals","DELETE","141","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=141 | Victim ID: 92 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("192","victim_approvals","DELETE","142","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=142 | Victim ID: 93 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("193","victim_approvals","DELETE","143","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=143 | Victim ID: 94 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("194","victim_approvals","DELETE","144","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=144 | Victim ID: 95 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("195","victim_approvals","DELETE","145","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=145 | Victim ID: 96 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("196","victim_approvals","DELETE","146","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=146 | Victim ID: 97 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("197","victim_approvals","DELETE","147","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=147 | Victim ID: 98 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("198","victim_approvals","DELETE","148","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=148 | Victim ID: 99 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("199","victim_approvals","DELETE","149","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=149 | Victim ID: 100 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("200","victim_approvals","DELETE","150","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=150 | Victim ID: 101 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("201","victim_approvals","DELETE","151","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=151 | Victim ID: 102 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("202","victim_approvals","DELETE","152","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=152 | Victim ID: 103 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("203","victim_approvals","DELETE","153","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=153 | Victim ID: 104 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("204","victim_approvals","DELETE","154","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=154 | Victim ID: 105 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("205","victim_approvals","DELETE","155","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=155 | Victim ID: 106 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("206","victim_approvals","DELETE","156","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=156 | Victim ID: 107 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("207","victim_approvals","DELETE","157","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=157 | Victim ID: 108 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("208","victim_approvals","DELETE","158","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=158 | Victim ID: 109 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("209","victim_approvals","DELETE","159","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=159 | Victim ID: 110 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("210","victim_approvals","DELETE","160","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=160 | Victim ID: 111 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("211","victim_approvals","DELETE","161","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=161 | Victim ID: 112 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("212","victim_approvals","DELETE","162","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=162 | Victim ID: 113 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("213","victim_approvals","DELETE","163","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=163 | Victim ID: 114 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("214","victim_approvals","DELETE","164","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=164 | Victim ID: 115 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("215","victim_approvals","DELETE","165","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=165 | Victim ID: 116 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("216","victim_approvals","DELETE","166","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=166 | Victim ID: 117 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("217","victim_approvals","DELETE","167","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=167 | Victim ID: 118 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("218","victim_approvals","DELETE","168","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=168 | Victim ID: 119 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("219","victim_approvals","DELETE","169","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=169 | Victim ID: 120 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("220","victim_approvals","DELETE","170","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=170 | Victim ID: 121 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("221","victim_approvals","DELETE","171","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=171 | Victim ID: 122 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("222","victim_approvals","DELETE","172","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=172 | Victim ID: 2 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("223","victim_approvals","DELETE","173","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=173 | Victim ID: 3 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("224","victim_approvals","DELETE","174","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=174 | Victim ID: 4 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("225","victim_approvals","DELETE","175","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=175 | Victim ID: 5 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("226","victim_approvals","DELETE","176","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=176 | Victim ID: 6 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("227","victim_approvals","DELETE","177","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=177 | Victim ID: 7 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("228","victim_approvals","DELETE","178","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=178 | Victim ID: 8 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("229","victim_approvals","DELETE","179","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=179 | Victim ID: 9 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("230","victim_approvals","DELETE","180","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=180 | Victim ID: 10 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("231","victim_approvals","DELETE","181","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=181 | Victim ID: 11 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("232","victim_approvals","DELETE","182","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=182 | Victim ID: 12 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("233","victim_approvals","DELETE","183","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=183 | Victim ID: 13 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("234","victim_approvals","DELETE","184","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=184 | Victim ID: 14 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("235","victim_approvals","DELETE","185","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=185 | Victim ID: 15 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("236","victim_approvals","DELETE","186","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=186 | Victim ID: 16 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("237","victim_approvals","DELETE","187","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=187 | Victim ID: 17 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("238","victim_approvals","DELETE","188","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=188 | Victim ID: 18 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("239","victim_approvals","DELETE","189","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=189 | Victim ID: 19 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("240","victim_approvals","DELETE","190","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=190 | Victim ID: 20 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("241","victim_approvals","DELETE","191","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=191 | Victim ID: 21 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("242","victim_approvals","DELETE","192","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=192 | Victim ID: 22 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("243","victim_approvals","DELETE","193","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=193 | Victim ID: 23 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("244","victim_approvals","DELETE","194","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=194 | Victim ID: 24 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("245","victim_approvals","DELETE","195","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=195 | Victim ID: 25 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("246","victim_approvals","DELETE","196","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=196 | Victim ID: 26 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("247","victim_approvals","DELETE","197","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=197 | Victim ID: 27 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("248","victim_approvals","DELETE","198","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=198 | Victim ID: 28 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("249","victim_approvals","DELETE","199","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=199 | Victim ID: 29 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("250","victim_approvals","DELETE","200","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=200 | Victim ID: 30 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("251","victim_approvals","DELETE","201","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=201 | Victim ID: 31 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("252","victim_approvals","DELETE","202","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=202 | Victim ID: 32 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("253","victim_approvals","DELETE","203","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=203 | Victim ID: 36 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("254","victim_approvals","DELETE","204","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=204 | Victim ID: 37 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("255","victim_approvals","DELETE","205","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=205 | Victim ID: 38 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("256","victim_approvals","DELETE","206","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=206 | Victim ID: 39 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("257","victim_approvals","DELETE","207","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=207 | Victim ID: 40 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("258","victim_approvals","DELETE","208","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=208 | Victim ID: 41 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("259","victim_approvals","DELETE","209","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=209 | Victim ID: 42 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("260","victim_approvals","DELETE","210","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=210 | Victim ID: 43 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("261","victim_approvals","DELETE","211","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=211 | Victim ID: 44 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("262","victim_approvals","DELETE","212","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=212 | Victim ID: 45 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("263","victim_approvals","DELETE","213","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=213 | Victim ID: 46 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("264","victim_approvals","DELETE","214","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=214 | Victim ID: 47 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("265","victim_approvals","DELETE","215","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=215 | Victim ID: 48 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("266","victim_approvals","DELETE","216","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=216 | Victim ID: 49 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("267","victim_approvals","DELETE","217","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=217 | Victim ID: 50 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("268","victim_approvals","DELETE","218","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=218 | Victim ID: 51 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("269","victim_approvals","DELETE","219","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=219 | Victim ID: 52 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("270","victim_approvals","DELETE","220","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=220 | Victim ID: 53 | Status: Rejected",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("271","victim_approvals","DELETE","221","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=221 | Victim ID: 54 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("272","victim_approvals","DELETE","222","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=222 | Victim ID: 55 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("273","victim_approvals","DELETE","223","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=223 | Victim ID: 56 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("274","victim_approvals","DELETE","224","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=224 | Victim ID: 57 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("275","victim_approvals","DELETE","225","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=225 | Victim ID: 58 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("276","victim_approvals","DELETE","226","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=226 | Victim ID: 59 | Status: Approved",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("277","victim_approvals","DELETE","227","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=227 | Victim ID: 60 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("278","victim_approvals","DELETE","228","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=228 | Victim ID: 61 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("279","victim_approvals","DELETE","229","root@localhost","2026-01-11 20:28:04",NULL,"Deleted Approval: ID=229 | Victim ID: 62 | Status: Pending",NULL,"Victim approval record deleted");
INSERT INTO `audit_log` VALUES("280","distribution","DELETE","27","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=27 | Location: N/A | Date: 2025-12-12",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("281","distribution","DELETE","28","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=28 | Location: N/A | Date: 2025-12-12",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("282","distribution","DELETE","29","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=29 | Location: N/A | Date: 2025-01-20",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("283","distribution","DELETE","30","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=30 | Location: N/A | Date: 2025-01-20",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("284","distribution","DELETE","31","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=31 | Location: N/A | Date: 2025-12-12",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("285","distribution","DELETE","32","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=32 | Location: N/A | Date: 2025-12-12",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("286","distribution","DELETE","33","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=33 | Location: N/A | Date: 2025-12-13",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("287","distribution","DELETE","37","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=37 | Location: N/A | Date: 2025-12-18",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("288","distribution","DELETE","42","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=42 | Location: N/A | Date: 2025-12-18",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("289","distribution","DELETE","47","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=47 | Location: N/A | Date: 2025-12-26",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("290","distribution","DELETE","50","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=50 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("291","distribution","DELETE","52","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=52 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("292","distribution","DELETE","53","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=53 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("293","distribution","DELETE","54","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=54 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("294","distribution","DELETE","55","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=55 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("295","distribution","DELETE","56","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=56 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("296","distribution","DELETE","57","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=57 | Location: N/A | Date: 2025-12-27",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("297","distribution","DELETE","58","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=58 | Location: Gelanggang Futsal Bandaraya | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("298","distribution","DELETE","59","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=59 | Location: Gelanggang Futsal Bandaraya | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("299","distribution","DELETE","60","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=60 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("300","distribution","DELETE","61","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=61 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("301","distribution","DELETE","62","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=62 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("302","distribution","DELETE","63","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=63 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("303","distribution","DELETE","64","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=64 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("304","distribution","DELETE","65","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=65 | Location: Dewan Komuniti Taman Seri Bayu | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("305","distribution","DELETE","67","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=67 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("306","distribution","DELETE","68","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=68 | Location: Balai Raya Kampung Baru | Date: 2026-01-01",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("307","distribution","DELETE","71","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=71 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-02",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("308","distribution","DELETE","72","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=72 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-02",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("309","distribution","DELETE","78","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=78 | Location: Balai Raya Kampung Baru | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("310","distribution","DELETE","79","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=79 | Location: Balai Raya Kampung Baru | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("311","distribution","DELETE","80","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=80 | Location: Balai Raya Kampung Baru | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("312","distribution","DELETE","83","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=83 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-07",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("313","distribution","DELETE","84","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=84 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("314","distribution","DELETE","85","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=85 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("315","distribution","DELETE","86","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=86 | Location: Gelanggang Futsal Bandaraya | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("316","distribution","DELETE","87","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=87 | Location: Gelanggang Futsal Bandaraya | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("317","distribution","DELETE","88","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=88 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("318","distribution","DELETE","89","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=89 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("319","distribution","DELETE","90","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=90 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("320","distribution","DELETE","91","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=91 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("321","distribution","DELETE","92","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=92 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("322","distribution","DELETE","93","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=93 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("323","distribution","DELETE","94","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=94 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("324","distribution","DELETE","95","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=95 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("325","distribution","DELETE","96","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=96 | Location: Dewan Komuniti Taman Seri Bayu | Date: 2026-01-06",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("326","distribution","DELETE","98","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=98 | Location: Sekolah Kebangsaan Alor Gajah | Date: 2026-01-06",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("327","distribution","DELETE","99","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=99 | Location: Dewan Komuniti Taman Seri Bayu | Date: 2026-01-06",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("328","distribution","DELETE","101","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=101 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-05",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("329","distribution","DELETE","102","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=102 | Location: Dewan Serbaguna Masjid Tanah | Date: 2026-01-07",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("330","distribution","DELETE","103","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=103 | Location: Gelanggang Futsal Bandaraya | Date: 2026-01-08",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("331","distribution","DELETE","104","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=104 | Location: N/A | Date: 2026-01-08",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("332","distribution","DELETE","111","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=111 | Location: N/A | Date: 2026-01-09",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("333","distribution","DELETE","112","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=112 | Location: N/A | Date: 2026-01-09",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("334","distribution","DELETE","113","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=113 | Location: N/A | Date: 2026-01-30",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("335","distribution","DELETE","115","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=115 | Location: N/A | Date: 2026-01-24",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("336","distribution","DELETE","116","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=116 | Location: N/A | Date: 2026-02-04",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("337","distribution","DELETE","117","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=117 | Location: N/A | Date: 2026-01-11",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("338","distribution","DELETE","121","root@localhost","2026-01-11 20:33:28",NULL,"Deleted Distribution: ID=121 | Location: N/A | Date: 2026-01-11",NULL,"Distribution record deleted");
INSERT INTO `audit_log` VALUES("339","victim_approvals","INSERT","230","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=230 | Victim ID: 63 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("340","victim_approvals","INSERT","231","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=231 | Victim ID: 64 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("341","victim_approvals","INSERT","232","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=232 | Victim ID: 65 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("342","victim_approvals","INSERT","233","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=233 | Victim ID: 66 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("343","victim_approvals","INSERT","234","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=234 | Victim ID: 67 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("344","victim_approvals","INSERT","235","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=235 | Victim ID: 68 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("345","victim_approvals","INSERT","236","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=236 | Victim ID: 69 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("346","victim_approvals","INSERT","237","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=237 | Victim ID: 70 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("347","victim_approvals","INSERT","238","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=238 | Victim ID: 71 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("348","victim_approvals","INSERT","239","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=239 | Victim ID: 72 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("349","victim_approvals","INSERT","240","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=240 | Victim ID: 73 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("350","victim_approvals","INSERT","241","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=241 | Victim ID: 74 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("351","victim_approvals","INSERT","242","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=242 | Victim ID: 75 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("352","victim_approvals","INSERT","243","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=243 | Victim ID: 76 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("353","victim_approvals","INSERT","244","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=244 | Victim ID: 77 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("354","victim_approvals","INSERT","245","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=245 | Victim ID: 78 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("355","victim_approvals","INSERT","246","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=246 | Victim ID: 79 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("356","victim_approvals","INSERT","247","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=247 | Victim ID: 80 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("357","victim_approvals","INSERT","248","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=248 | Victim ID: 81 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("358","victim_approvals","INSERT","249","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=249 | Victim ID: 82 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("359","victim_approvals","INSERT","250","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=250 | Victim ID: 83 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("360","victim_approvals","INSERT","251","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=251 | Victim ID: 84 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("361","victim_approvals","INSERT","252","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=252 | Victim ID: 85 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("362","victim_approvals","INSERT","253","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=253 | Victim ID: 86 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("363","victim_approvals","INSERT","254","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=254 | Victim ID: 87 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("364","victim_approvals","INSERT","255","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=255 | Victim ID: 88 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("365","victim_approvals","INSERT","256","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=256 | Victim ID: 89 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("366","victim_approvals","INSERT","257","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=257 | Victim ID: 90 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("367","victim_approvals","INSERT","258","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=258 | Victim ID: 91 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("368","victim_approvals","INSERT","259","root@localhost","2026-01-11 20:34:21",NULL,NULL,"New Approval: ID=259 | Victim ID: 92 | Disaster ID: 3 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("369","victim_approvals","UPDATE","234","root@localhost","2026-01-11 20:34:30",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 20:34:30","Victim approval status changed");
INSERT INTO `audit_log` VALUES("370","distribution","INSERT","123","root@localhost","2026-01-11 21:37:36",NULL,NULL,"New Distribution: ID=123 | Victim ID: 67 | Location: N/A | Date: 2026-01-13 | Status: Planning | Qty Sent: 0 | Coordinator: En. Daniel","New distribution record created");
INSERT INTO `audit_log` VALUES("371","distribution_items","INSERT","62","root@localhost","2026-01-11 21:37:36",NULL,NULL,"New Item: ID=62 | Distribution ID: 123 | Victim ID: 67 | Status: Scheduled | Volunteer ID: Unassigned","New distribution item added");
INSERT INTO `audit_log` VALUES("372","distribution","UPDATE","123","root@localhost","2026-01-11 21:38:15",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("373","victim_approvals","INSERT","260","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=260 | Victim ID: 93 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("374","victim_approvals","INSERT","261","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=261 | Victim ID: 94 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("375","victim_approvals","INSERT","262","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=262 | Victim ID: 95 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("376","victim_approvals","INSERT","263","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=263 | Victim ID: 96 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("377","victim_approvals","INSERT","264","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=264 | Victim ID: 97 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("378","victim_approvals","INSERT","265","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=265 | Victim ID: 98 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("379","victim_approvals","INSERT","266","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=266 | Victim ID: 99 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("380","victim_approvals","INSERT","267","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=267 | Victim ID: 100 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("381","victim_approvals","INSERT","268","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=268 | Victim ID: 101 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("382","victim_approvals","INSERT","269","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=269 | Victim ID: 102 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("383","victim_approvals","INSERT","270","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=270 | Victim ID: 103 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("384","victim_approvals","INSERT","271","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=271 | Victim ID: 104 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("385","victim_approvals","INSERT","272","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=272 | Victim ID: 105 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("386","victim_approvals","INSERT","273","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=273 | Victim ID: 106 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("387","victim_approvals","INSERT","274","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=274 | Victim ID: 107 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("388","victim_approvals","INSERT","275","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=275 | Victim ID: 108 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("389","victim_approvals","INSERT","276","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=276 | Victim ID: 109 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("390","victim_approvals","INSERT","277","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=277 | Victim ID: 110 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("391","victim_approvals","INSERT","278","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=278 | Victim ID: 111 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("392","victim_approvals","INSERT","279","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=279 | Victim ID: 112 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("393","victim_approvals","INSERT","280","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=280 | Victim ID: 113 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("394","victim_approvals","INSERT","281","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=281 | Victim ID: 114 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("395","victim_approvals","INSERT","282","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=282 | Victim ID: 115 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("396","victim_approvals","INSERT","283","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=283 | Victim ID: 116 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("397","victim_approvals","INSERT","284","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=284 | Victim ID: 117 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("398","victim_approvals","INSERT","285","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=285 | Victim ID: 118 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("399","victim_approvals","INSERT","286","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=286 | Victim ID: 119 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("400","victim_approvals","INSERT","287","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=287 | Victim ID: 120 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("401","victim_approvals","INSERT","288","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=288 | Victim ID: 121 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("402","victim_approvals","INSERT","289","root@localhost","2026-01-11 21:38:53",NULL,NULL,"New Approval: ID=289 | Victim ID: 122 | Disaster ID: 4 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("403","victim_approvals","INSERT","290","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=290 | Victim ID: 33 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("404","victim_approvals","INSERT","291","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=291 | Victim ID: 34 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("405","victim_approvals","INSERT","292","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=292 | Victim ID: 35 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("406","victim_approvals","INSERT","293","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=293 | Victim ID: 36 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("407","victim_approvals","INSERT","294","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=294 | Victim ID: 37 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("408","victim_approvals","INSERT","295","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=295 | Victim ID: 38 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("409","victim_approvals","INSERT","296","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=296 | Victim ID: 39 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("410","victim_approvals","INSERT","297","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=297 | Victim ID: 40 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("411","victim_approvals","INSERT","298","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=298 | Victim ID: 41 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("412","victim_approvals","INSERT","299","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=299 | Victim ID: 42 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("413","victim_approvals","INSERT","300","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=300 | Victim ID: 43 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("414","victim_approvals","INSERT","301","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=301 | Victim ID: 44 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("415","victim_approvals","INSERT","302","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=302 | Victim ID: 45 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("416","victim_approvals","INSERT","303","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=303 | Victim ID: 46 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("417","victim_approvals","INSERT","304","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=304 | Victim ID: 47 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("418","victim_approvals","INSERT","305","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=305 | Victim ID: 48 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("419","victim_approvals","INSERT","306","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=306 | Victim ID: 49 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("420","victim_approvals","INSERT","307","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=307 | Victim ID: 50 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("421","victim_approvals","INSERT","308","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=308 | Victim ID: 51 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("422","victim_approvals","INSERT","309","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=309 | Victim ID: 52 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("423","victim_approvals","INSERT","310","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=310 | Victim ID: 53 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("424","victim_approvals","INSERT","311","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=311 | Victim ID: 54 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("425","victim_approvals","INSERT","312","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=312 | Victim ID: 55 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("426","victim_approvals","INSERT","313","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=313 | Victim ID: 56 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("427","victim_approvals","INSERT","314","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=314 | Victim ID: 57 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("428","victim_approvals","INSERT","315","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=315 | Victim ID: 58 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("429","victim_approvals","INSERT","316","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=316 | Victim ID: 59 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("430","victim_approvals","INSERT","317","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=317 | Victim ID: 60 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("431","victim_approvals","INSERT","318","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=318 | Victim ID: 61 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("432","victim_approvals","INSERT","319","root@localhost","2026-01-11 21:39:17",NULL,NULL,"New Approval: ID=319 | Victim ID: 62 | Disaster ID: 2 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("433","victim_approvals","UPDATE","298","root@localhost","2026-01-11 21:39:32",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 21:39:32","Victim approval status changed");
INSERT INTO `audit_log` VALUES("434","victim_approvals","UPDATE","299","root@localhost","2026-01-11 21:39:42",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 21:39:42","Victim approval status changed");
INSERT INTO `audit_log` VALUES("435","victim_approvals","UPDATE","300","root@localhost","2026-01-11 21:39:52",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 21:39:52","Victim approval status changed");
INSERT INTO `audit_log` VALUES("442","distribution","INSERT","127","root@localhost","2026-01-11 21:49:01",NULL,NULL,"New Distribution: ID=127 | Victim ID: 41 | Location: N/A | Date: 2026-01-13 | Status: Planning | Qty Sent: 0 | Coordinator: En. Daniel","New distribution record created");
INSERT INTO `audit_log` VALUES("443","distribution_items","INSERT","66","root@localhost","2026-01-11 21:49:01",NULL,NULL,"New Item: ID=66 | Distribution ID: 127 | Victim ID: 41 | Status: Scheduled | Volunteer ID: Unassigned","New distribution item added");
INSERT INTO `audit_log` VALUES("444","distribution","INSERT","128","root@localhost","2026-01-11 21:50:44",NULL,NULL,"New Distribution: ID=128 | Victim ID: 42 | Location: N/A | Date: 2026-01-16 | Status: Planning | Qty Sent: 0 | Coordinator: En. Kamal bin Ismail","New distribution record created");
INSERT INTO `audit_log` VALUES("445","distribution_items","INSERT","67","root@localhost","2026-01-11 21:50:44",NULL,NULL,"New Item: ID=67 | Distribution ID: 128 | Victim ID: 42 | Status: Scheduled | Volunteer ID: Unassigned","New distribution item added");
INSERT INTO `audit_log` VALUES("446","distribution","UPDATE","123","root@localhost","2026-01-11 21:55:43",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("447","victim_approvals","UPDATE","268","root@localhost","2026-01-11 22:01:08",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 22:01:08","Victim approval status changed");
INSERT INTO `audit_log` VALUES("448","victim_approvals","UPDATE","269","root@localhost","2026-01-11 22:01:18",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-11 22:01:18","Victim approval status changed");
INSERT INTO `audit_log` VALUES("449","distribution","INSERT","129","root@localhost","2026-01-11 22:05:27",NULL,NULL,"New Distribution: ID=129 | Victim ID: 102 | Location: N/A | Date: 2026-01-13 | Status: Planning | Qty Sent: 0 | Coordinator: En. Daniel","New distribution record created");
INSERT INTO `audit_log` VALUES("450","distribution_items","INSERT","68","root@localhost","2026-01-11 22:05:27",NULL,NULL,"New Item: ID=68 | Distribution ID: 129 | Victim ID: 102 | Status: Scheduled | Volunteer ID: Unassigned","New distribution item added");
INSERT INTO `audit_log` VALUES("451","distribution","UPDATE","128","root@localhost","2026-01-12 00:40:40",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("452","distribution","UPDATE","128","root@localhost","2026-01-12 00:41:34",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("453","distribution","UPDATE","128","root@localhost","2026-01-12 00:41:58",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("454","distribution","UPDATE","128","root@localhost","2026-01-12 00:42:38",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("455","distribution","UPDATE","128","root@localhost","2026-01-12 00:43:05",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Volunteer Needed | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("456","distribution","UPDATE","128","root@localhost","2026-01-12 00:43:42",NULL,"Old Status: Volunteer Needed | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("457","distribution","UPDATE","128","root@localhost","2026-01-12 00:44:17",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("458","distribution","UPDATE","128","root@localhost","2026-01-12 00:44:54",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("460","distribution","UPDATE","128","root@localhost","2026-01-12 01:21:50",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("461","distribution","UPDATE","123","root@localhost","2026-01-12 01:23:13",NULL,"Old Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("462","distribution","UPDATE","123","root@localhost","2026-01-12 01:24:29",NULL,"Old Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("463","distribution","UPDATE","128","root@localhost","2026-01-12 01:24:48",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("464","distribution","UPDATE","128","root@localhost","2026-01-12 01:25:02",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("465","distribution","UPDATE","127","root@localhost","2026-01-12 01:26:33",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("466","distribution","UPDATE","123","root@localhost","2026-01-12 01:27:30",NULL,"Old Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("467","distribution","UPDATE","127","root@localhost","2026-01-12 01:27:55",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("468","distribution","UPDATE","127","root@localhost","2026-01-12 01:28:23",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("469","distribution","INSERT","130","root@localhost","2026-01-12 15:19:27",NULL,NULL,"New Distribution: ID=130 | Victim ID: 101 | Location: N/A | Date: 2026-01-12 | Status: Planning | Qty Sent: 0 | Coordinator: En. Daniel","New distribution record created");
INSERT INTO `audit_log` VALUES("470","distribution_items","INSERT","69","root@localhost","2026-01-12 15:19:27",NULL,NULL,"New Item: ID=69 | Distribution ID: 130 | Victim ID: 101 | Status: Scheduled | Volunteer ID: Unassigned","New distribution item added");
INSERT INTO `audit_log` VALUES("471","distribution","UPDATE","130","root@localhost","2026-01-12 15:22:09",NULL,"Old Status: Planning | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("472","distribution","UPDATE","130","root@localhost","2026-01-12 15:22:29",NULL,"Old Status: Assigned | Location: N/A | Qty Sent: 0 | Qty Received: 0","New Status: In Progress | Location: N/A | Qty Sent: 0 | Qty Received: 0","Distribution details updated");
INSERT INTO `audit_log` VALUES("473","victim_approvals","INSERT","320","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=320 | Victim ID: 1 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("474","victim_approvals","INSERT","321","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=321 | Victim ID: 2 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("475","victim_approvals","INSERT","322","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=322 | Victim ID: 3 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("476","victim_approvals","INSERT","323","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=323 | Victim ID: 4 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("477","victim_approvals","INSERT","324","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=324 | Victim ID: 5 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("478","victim_approvals","INSERT","325","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=325 | Victim ID: 6 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("479","victim_approvals","INSERT","326","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=326 | Victim ID: 7 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("480","victim_approvals","INSERT","327","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=327 | Victim ID: 8 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("481","victim_approvals","INSERT","328","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=328 | Victim ID: 9 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("482","victim_approvals","INSERT","329","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=329 | Victim ID: 10 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("483","victim_approvals","INSERT","330","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=330 | Victim ID: 11 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("484","victim_approvals","INSERT","331","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=331 | Victim ID: 12 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("485","victim_approvals","INSERT","332","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=332 | Victim ID: 13 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("486","victim_approvals","INSERT","333","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=333 | Victim ID: 14 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("487","victim_approvals","INSERT","334","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=334 | Victim ID: 15 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("488","victim_approvals","INSERT","335","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=335 | Victim ID: 16 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("489","victim_approvals","INSERT","336","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=336 | Victim ID: 17 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("490","victim_approvals","INSERT","337","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=337 | Victim ID: 18 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("491","victim_approvals","INSERT","338","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=338 | Victim ID: 19 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("492","victim_approvals","INSERT","339","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=339 | Victim ID: 20 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("493","victim_approvals","INSERT","340","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=340 | Victim ID: 21 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("494","victim_approvals","INSERT","341","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=341 | Victim ID: 22 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("495","victim_approvals","INSERT","342","root@localhost","2026-01-12 16:55:24",NULL,NULL,"New Approval: ID=342 | Victim ID: 23 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("496","victim_approvals","INSERT","343","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=343 | Victim ID: 24 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("497","victim_approvals","INSERT","344","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=344 | Victim ID: 25 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("498","victim_approvals","INSERT","345","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=345 | Victim ID: 26 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("499","victim_approvals","INSERT","346","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=346 | Victim ID: 27 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("500","victim_approvals","INSERT","347","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=347 | Victim ID: 28 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("501","victim_approvals","INSERT","348","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=348 | Victim ID: 29 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("502","victim_approvals","INSERT","349","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=349 | Victim ID: 30 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("503","victim_approvals","INSERT","350","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=350 | Victim ID: 31 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("504","victim_approvals","INSERT","351","root@localhost","2026-01-12 16:55:25",NULL,NULL,"New Approval: ID=351 | Victim ID: 32 | Disaster ID: 1 | Status: Pending","New victim approval request created");
INSERT INTO `audit_log` VALUES("505","victim_approvals","UPDATE","320","root@localhost","2026-01-12 16:55:30",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:55:30","Victim approval status changed");
INSERT INTO `audit_log` VALUES("506","victim_approvals","UPDATE","321","root@localhost","2026-01-12 16:55:40",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:55:40","Victim approval status changed");
INSERT INTO `audit_log` VALUES("507","victim_approvals","UPDATE","322","root@localhost","2026-01-12 16:55:50",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:55:50","Victim approval status changed");
INSERT INTO `audit_log` VALUES("508","victim_approvals","UPDATE","323","root@localhost","2026-01-12 16:56:00",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:56:00","Victim approval status changed");
INSERT INTO `audit_log` VALUES("509","victim_approvals","UPDATE","324","root@localhost","2026-01-12 16:56:10",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:56:10","Victim approval status changed");
INSERT INTO `audit_log` VALUES("510","victim_approvals","UPDATE","325","root@localhost","2026-01-12 16:56:20",NULL,"Old Status: Pending","New Status: Approved | Approved At: 2026-01-12 16:56:20","Victim approval status changed");
INSERT INTO `audit_log` VALUES("511","victim_approvals","UPDATE","320","root@localhost","2026-01-12 16:56:55",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:56:55","Victim approval status changed");
INSERT INTO `audit_log` VALUES("512","victim_approvals","UPDATE","321","root@localhost","2026-01-12 16:57:05",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:57:05","Victim approval status changed");
INSERT INTO `audit_log` VALUES("513","victim_approvals","UPDATE","322","root@localhost","2026-01-12 16:57:15",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:57:15","Victim approval status changed");
INSERT INTO `audit_log` VALUES("514","victim_approvals","UPDATE","323","root@localhost","2026-01-12 16:57:25",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:57:25","Victim approval status changed");
INSERT INTO `audit_log` VALUES("515","victim_approvals","UPDATE","324","root@localhost","2026-01-12 16:57:35",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:57:35","Victim approval status changed");
INSERT INTO `audit_log` VALUES("516","victim_approvals","UPDATE","325","root@localhost","2026-01-12 16:57:45",NULL,"Old Status: Approved","New Status: Approved | Approved At: 2026-01-12 16:57:45","Victim approval status changed");
DROP TABLE IF EXISTS `coordinator_alerts`;
CREATE TABLE `coordinator_alerts` (
  `alert_id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `message` text NOT NULL,
  `alert_type` enum('volunteer_cancelled','status_change','new_volunteer','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`alert_id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `coordinators`;
CREATE TABLE `coordinators` (
  `coordinator_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `ic_number` varchar(20) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`coordinator_id`),
  UNIQUE KEY `ic_number` (`ic_number`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `coordinators` VALUES("1","Encik Ahmad bin Ali","750123-01-5678","06-282-1234","ahmad.ali@jkm.gov.my","Relief Operations","Senior Coordinator","Active","2026-01-04 23:46:24","2026-01-04 23:46:24");
INSERT INTO `coordinators` VALUES("2","Puan Siti Aminah binti Hassan","820456-02-3456","06-282-5678","siti.aminah@jkm.gov.my","Logistics","Logistics Manager","Active","2026-01-04 23:46:24","2026-01-04 23:46:24");
INSERT INTO `coordinators` VALUES("3","En. Rashid bin Ibrahim","780789-03-1234","06-282-9012","rashid.ibrahim@jkm.gov.my","Field Operations","Field Supervisor","Active","2026-01-04 23:46:24","2026-01-04 23:46:24");
INSERT INTO `coordinators` VALUES("4","Puan Nora binti Abdullah","850321-02-7890","06-282-3456","nora.abdullah@jkm.gov.my","Distribution","Distribution Head","Active","2026-01-04 23:46:24","2026-01-04 23:46:24");
INSERT INTO `coordinators` VALUES("5","En. Kamal bin Ismail","770912-01-4567","06-282-7890","kamal.ismail@jkm.gov.my","Emergency Response","Emergency Coordinator","Active","2026-01-04 23:46:24","2026-01-04 23:46:24");
INSERT INTO `coordinators` VALUES("6","En. Daniel","041118080431","017-585 6449","aj@gmail.com","Database Department","Pegawai Takbir","Active","2026-01-06 02:37:21","2026-01-06 02:37:21");
DROP TABLE IF EXISTS `distribution`;
CREATE TABLE `distribution` (
  `distribution_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `victim_id` int unsigned DEFAULT NULL,
  `disaster_id` int DEFAULT NULL,
  `resource_id` bigint DEFAULT NULL,
  `date` date NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `quantity_sent` int NOT NULL,
  `comments` text,
  `location` varchar(255) DEFAULT NULL,
  `coordinator_name` varchar(100) DEFAULT NULL,
  `coordinator_contact` varchar(20) DEFAULT NULL,
  `estimated_duration` int DEFAULT NULL,
  `volunteers_needed` int DEFAULT NULL,
  `quantity_received` int DEFAULT NULL,
  PRIMARY KEY (`distribution_id`),
  UNIQUE KEY `distribution_id` (`distribution_id`)
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution` VALUES("123","67","3",NULL,"2026-01-12","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-13 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Total Family Members: 5 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Maria bin Gopal (ID: 67)
  Family: 5 members
  Shelter: Masjid Al-Amin Emergency Shelter
  Contact: 013-7842598
  Address: 93 Jalan Melaka, Alor Gajah, Melaka, Alor Gajah
  Special Request: Prescription Medications
Glucose Meter
Face Masks
  👴 Has elderly
  ♿ Has disabled

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:


SPECIAL REQUEST ALLOCATIONS:
----------------------------

Plan Created: 2026-01-11 13:37:36",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("127","41","2",NULL,"2026-01-12","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-13 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Total Family Members: 10 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Daud binti Zainal (ID: 41)
  Family: 4 members
  Shelter: Dewan Seri Negeri Melaka
  Contact: 012-4364206
  Address: 17 Jalan Tun Ali, Melaka Tengah, Melaka, Melaka Tengah
  Special Request: Baby Diapers
Blood Pressure Monitors
  👶 Has baby
  ♿ Has disabled

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:

- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Cooking Oil 5kg: 5 kg (5 kg per family × 1 families)
- Mineral Water 1.5L: 5 l (5 l per family × 1 families)
- Canned Sardines: 1 tins (1 tins per family × 1 families)
- Instant Noodles: 1 packs (1 packs per family × 1 families)
- Toilet Paper: 1 rolls (1 rolls per family × 1 families)
- Bath Soap: 1 bars (1 bars per family × 1 families)
- Sugar 1kg: 1 kg (1 kg per family × 1 families)
- Wheat Flour 1kg: 1 kg (1 kg per family × 1 families)
- Toothpaste: 1 tubes (1 tubes per family × 1 families)
- Laundry Detergent: 1 bags (1 bags per family × 1 families)
- Dry Crackers/Biscuits: 1 tins (1 tins per family × 1 families)
- Condensed Milk: 1 tins (1 tins per family × 1 families)
- Sanitary Pads: 1 packs (1 packs per family × 1 families)
- Blankets: 1 pieces (1 pieces per family × 1 families)

SPECIAL REQUEST ALLOCATIONS:
----------------------------
- [Special] Baby Diapers: 1 pieces (Requested by: Daud binti Zainal)
- [Special] Blood Pressure Monitors: 1 units (Requested by: Daud binti Zainal)
- [Special] First Aid Kits: 1 units
- [Special] Antiseptic Solution: 1 units
- [Special] Bandages & Gauze: 1 units

Plan Created: 2026-01-11 13:49:01",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("128","42","2",NULL,"2026-01-12","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-16 at 14:00
Coordinator: En. Kamal bin Ismail (06-282-7890)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Total Family Members: 6 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Bala binti Ibrahim (ID: 42)
  Family: 3 members
  Shelter: Melaka Tengah Emergency Shelter 1
  Contact: 013-3341741
  Address: 15 Jalan Melaka, Melaka Tengah, Melaka, Melaka Tengah
  Special Request: First Aid Kits
Baby Diapers
  👶 Has baby
  ♿ Has disabled

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:

- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Cooking Oil 5kg: 5 kg (5 kg per family × 1 families)
- Mineral Water 1.5L: 5 l (5 l per family × 1 families)
- Canned Sardines: 1 tins (1 tins per family × 1 families)
- Instant Noodles: 1 packs (1 packs per family × 1 families)
- Toilet Paper: 1 rolls (1 rolls per family × 1 families)
- Bath Soap: 1 bars (1 bars per family × 1 families)
- Sugar 1kg: 1 kg (1 kg per family × 1 families)
- Wheat Flour 1kg: 1 kg (1 kg per family × 1 families)
- Toothpaste: 1 tubes (1 tubes per family × 1 families)
- Laundry Detergent: 1 bags (1 bags per family × 1 families)
- Dry Crackers/Biscuits: 1 tins (1 tins per family × 1 families)
- Condensed Milk: 1 tins (1 tins per family × 1 families)
- Sanitary Pads: 1 packs (1 packs per family × 1 families)
- Blankets: 1 pieces (1 pieces per family × 1 families)

SPECIAL REQUEST ALLOCATIONS:
----------------------------
- [Special] Baby Diapers: 1 pieces (Requested by: Bala binti Ibrahim)
- [Special] First Aid Kits: 1 units (Requested by: Bala binti Ibrahim)
- [Special] Antiseptic Solution: 1 units
- [Special] Bandages & Gauze: 1 units

Plan Created: 2026-01-11 13:50:44",NULL,"En. Kamal bin Ismail","06-282-7890","2","2",NULL);
INSERT INTO `distribution` VALUES("129","102","4",NULL,"2026-01-13","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-13 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Total Family Members: 7 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Jamal bin Ahmad (ID: 102)
  Family: 3 members
  Shelter: Jasin Community Shelter
  Contact: 010-7241373
  Address: 20 Jalan Tun Perak, Jasin, Melaka, Jasin

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:

- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Cooking Oil 5kg: 5 kg (5 kg per family × 1 families)
- Mineral Water 1.5L: 5 l (5 l per family × 1 families)
- Canned Sardines: 1 tins (1 tins per family × 1 families)
- Instant Noodles: 1 packs (1 packs per family × 1 families)
- Toilet Paper: 1 rolls (1 rolls per family × 1 families)
- Bath Soap: 1 bars (1 bars per family × 1 families)
- Sugar 1kg: 1 kg (1 kg per family × 1 families)
- Wheat Flour 1kg: 1 kg (1 kg per family × 1 families)
- Toothpaste: 1 tubes (1 tubes per family × 1 families)
- Laundry Detergent: 1 bags (1 bags per family × 1 families)
- Dry Crackers/Biscuits: 1 tins (1 tins per family × 1 families)
- Condensed Milk: 1 tins (1 tins per family × 1 families)
- Sanitary Pads: 1 packs (1 packs per family × 1 families)
- Blankets: 1 pieces (1 pieces per family × 1 families)

Plan Created: 2026-01-11 14:05:27",NULL,"En. Daniel","017-585 6449","2","2",NULL);
INSERT INTO `distribution` VALUES("130","101","4",NULL,"2026-01-12","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-12 at 15:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Total Family Members: 4 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Johan bin Ahmad (ID: 101)
  Family: 4 members
  Shelter: Balai Raya Merlimau
  Contact: 010-6397665
  Address: 4 Jalan Merdeka, Jasin, Melaka, Jasin
  Special Request: Baby Clothes
Blood Pressure Monitors
Emergency Blankets
  👶 Has baby

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:

- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Rice 10kg: 10 kg (10 kg per family × 1 families)
- Cooking Oil 5kg: 5 kg (5 kg per family × 1 families)
- Mineral Water 1.5L: 5 l (5 l per family × 1 families)
- Canned Sardines: 1 tins (1 tins per family × 1 families)
- Instant Noodles: 1 packs (1 packs per family × 1 families)
- Toilet Paper: 1 rolls (1 rolls per family × 1 families)
- Bath Soap: 1 bars (1 bars per family × 1 families)
- Sugar 1kg: 1 kg (1 kg per family × 1 families)
- Wheat Flour 1kg: 1 kg (1 kg per family × 1 families)
- Toothpaste: 1 tubes (1 tubes per family × 1 families)
- Laundry Detergent: 1 bags (1 bags per family × 1 families)
- Dry Crackers/Biscuits: 1 tins (1 tins per family × 1 families)
- Condensed Milk: 1 tins (1 tins per family × 1 families)
- Sanitary Pads: 1 packs (1 packs per family × 1 families)
- Blankets: 1 pieces (1 pieces per family × 1 families)

SPECIAL REQUEST ALLOCATIONS:
----------------------------
- [Special] Baby Clothes: 1 pieces (Requested by: Johan bin Ahmad)
- [Special] Emergency Blankets: 1 pieces (Requested by: Johan bin Ahmad)
- [Special] Blood Pressure Monitors: 1 units (Requested by: Johan bin Ahmad)

Plan Created: 2026-01-12 07:19:27",NULL,"En. Daniel","017-585 6449","2","2",NULL);
DROP TABLE IF EXISTS `distribution_items`;
CREATE TABLE `distribution_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `victim_id` int NOT NULL,
  `need_id` int DEFAULT '1',
  `status` enum('Scheduled','Dispatched','Delivered','Cancelled') DEFAULT 'Scheduled',
  `assigned_volunteer_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `distribution_id` (`distribution_id`),
  CONSTRAINT `distribution_items_ibfk_1` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_items_ibfk_2` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_items_ibfk_3` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_items_ibfk_4` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dist_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_items` VALUES("62","123","67","1","Scheduled",NULL,"2026-01-11 21:37:36");
INSERT INTO `distribution_items` VALUES("66","127","41","1","Scheduled",NULL,"2026-01-11 21:49:01");
INSERT INTO `distribution_items` VALUES("67","128","42","1","Scheduled",NULL,"2026-01-11 21:50:44");
INSERT INTO `distribution_items` VALUES("68","129","102","1","Scheduled",NULL,"2026-01-11 22:05:27");
INSERT INTO `distribution_items` VALUES("69","130","101","1","Scheduled",NULL,"2026-01-12 15:19:27");
DROP TABLE IF EXISTS `distribution_log`;
CREATE TABLE `distribution_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `volunteer_id` int NOT NULL,
  `shelter_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `victim_id` int NOT NULL,
  `resource_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `need_id` int NOT NULL,
  `quantity_distributed` int DEFAULT '1',
  `distributed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('completed','skipped','partial','in_transit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'completed',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `signature_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_distribution` (`distribution_id`),
  KEY `idx_volunteer` (`volunteer_id`),
  KEY `idx_victim` (`victim_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_dist_log_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `distribution_log` VALUES("45","123","24","Masjid Al-Amin Emergency Shelter","77",NULL,"77","1","2026-01-11 22:46:29","in_transit",NULL,NULL,"2026-01-11 22:46:29");
DROP TABLE IF EXISTS `distribution_resources`;
CREATE TABLE `distribution_resources` (
  `allocation_id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `resource_name` varchar(255) DEFAULT NULL,
  `resource_id` int DEFAULT NULL,
  `quantity_allocated` int NOT NULL,
  `quantity_distributed` int DEFAULT '0',
  `is_special_request` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `resource_id` (`resource_id`),
  CONSTRAINT `distribution_resources_ibfk_1` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_resources_ibfk_2` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_resources_ibfk_3` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_resources` VALUES("175","129","Rice 10kg",NULL,"10","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("176","129","Cooking Oil 5kg",NULL,"5","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("177","129","Mineral Water 1.5L",NULL,"5","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("178","129","Canned Sardines",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("179","129","Instant Noodles",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("180","129","Toilet Paper",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("181","129","Bath Soap",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("182","129","Sugar 1kg",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("183","129","Wheat Flour 1kg",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("184","129","Toothpaste",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("185","129","Laundry Detergent",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("186","129","Dry Crackers/Biscuits",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("187","129","Condensed Milk",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("188","129","Sanitary Pads",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("189","129","Blankets",NULL,"1","0","0","2026-01-11 22:05:27");
INSERT INTO `distribution_resources` VALUES("190","130","Rice 10kg",NULL,"10","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("191","130","Cooking Oil 5kg",NULL,"5","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("192","130","Mineral Water 1.5L",NULL,"5","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("193","130","Canned Sardines",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("194","130","Instant Noodles",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("195","130","Toilet Paper",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("196","130","Bath Soap",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("197","130","Sugar 1kg",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("198","130","Wheat Flour 1kg",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("199","130","Toothpaste",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("200","130","Laundry Detergent",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("201","130","Dry Crackers/Biscuits",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("202","130","Condensed Milk",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("203","130","Sanitary Pads",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("204","130","Blankets",NULL,"1","0","0","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("205","130","Baby Clothes",NULL,"1","0","1","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("206","130","Emergency Blankets",NULL,"1","0","1","2026-01-12 15:19:27");
INSERT INTO `distribution_resources` VALUES("207","130","Blood Pressure Monitors",NULL,"1","0","1","2026-01-12 15:19:27");
DROP TABLE IF EXISTS `distribution_tracking`;
CREATE TABLE `distribution_tracking` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `volunteer_id` int NOT NULL,
  `shelter_name` varchar(255) DEFAULT NULL,
  `victim_id` int DEFAULT NULL,
  `current_location` varchar(255) NOT NULL,
  `tracking_notes` text,
  `estimated_arrival` varchar(100) DEFAULT NULL,
  `status` enum('pending','departed','in_transit','arrived','delayed','completed','cancelled','scheduled','in_progress') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tracking_distribution` (`distribution_id`),
  KEY `idx_tracking_volunteer` (`volunteer_id`),
  KEY `idx_tracking_victim` (`victim_id`),
  KEY `idx_tracking_created` (`created_at`),
  CONSTRAINT `fk_tracking_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=624 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_tracking` VALUES("621","123","24","Masjid Al-Amin Emergency Shelter",NULL,"Distribution Center","Items loaded and ready for delivery to shelter",NULL,"departed","2026-01-11 22:46:29");
INSERT INTO `distribution_tracking` VALUES("622","123","24","Masjid Al-Amin Emergency Shelter",NULL,"Distribution Center","Items loaded and ready for delivery to shelter",NULL,"departed","2026-01-11 22:48:05");
INSERT INTO `distribution_tracking` VALUES("623","123","24","Masjid Al-Amin Emergency Shelter",NULL,"Lat: 2.247157, Lng: 102.257652","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-11 22:50:20");
DROP TABLE IF EXISTS `distribution_volunteer`;
CREATE TABLE `distribution_volunteer` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `volunteer_id` bigint unsigned NOT NULL,
  `distribution_id` bigint unsigned NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` enum('Assigned','Active','Completed','Cancelled') DEFAULT 'Assigned',
  `assigned_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`volunteer_id`,`distribution_id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `idx_distribution_volunteer_timestamp` (`assigned_timestamp`),
  CONSTRAINT `distribution_volunteer_ibfk_2` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_volunteer` VALUES("123","24","123","Medical","Active","2026-01-11 21:38:15",NULL,"2026-01-11 21:55:43");
INSERT INTO `distribution_volunteer` VALUES("127","24","128","Medical","Active","2026-01-12 01:24:48",NULL,"2026-01-12 01:25:02");
INSERT INTO `distribution_volunteer` VALUES("128","23","127","General Volunteer","Active","2026-01-12 01:27:54",NULL,"2026-01-12 01:28:23");
INSERT INTO `distribution_volunteer` VALUES("129","1026","130","Logistics / Supplies, Food Services","Active","2026-01-12 15:22:09",NULL,"2026-01-12 15:22:29");
DROP TABLE IF EXISTS `live_tracking`;
CREATE TABLE `live_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `volunteer_id` int NOT NULL,
  `distribution_id` int NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  `altitude` float DEFAULT NULL,
  `heading` float DEFAULT NULL,
  `speed` float DEFAULT NULL,
  `battery_level` int DEFAULT NULL,
  `is_moving` tinyint(1) DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `volunteer_id` (`volunteer_id`,`distribution_id`),
  KEY `recorded_at` (`recorded_at`)
) ENGINE=InnoDB AUTO_INCREMENT=600 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `live_tracking` VALUES("599","24","123","2.24715745","102.25765245","10",NULL,NULL,NULL,"80","0","2026-01-11 22:50:20");
DROP TABLE IF EXISTS `processed_disasters`;
CREATE TABLE `processed_disasters` (
  `processed_id` int NOT NULL AUTO_INCREMENT,
  `disaster_id` int NOT NULL,
  `processed_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_by` varchar(100) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`processed_id`),
  UNIQUE KEY `unique_disaster` (`disaster_id`),
  KEY `idx_disaster` (`disaster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `shelter_coordinates`;
CREATE TABLE `shelter_coordinates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shelter_name` varchar(255) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shelter` (`shelter_name`),
  KEY `shelter_name` (`shelter_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `shelter_coordinates` VALUES("3","Melaka Tengah Emergency Shelter 1","Alor Gajah","2.20000000","102.25000000","2026-01-11 21:56:11");
INSERT INTO `shelter_coordinates` VALUES("4","Masjid Al-Amin Emergency Shelter","Alor Gajah","2.24760000","102.25700000","2026-01-11 22:41:38");
INSERT INTO `shelter_coordinates` VALUES("5","Dewan Seri Negeri Melaka","Melaka Tengah","2.22630000","102.30250000","2026-01-12 01:28:32");
DROP TABLE IF EXISTS `status_changes`;
CREATE TABLE `status_changes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `reason` text,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `changed_at` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `status_changes` VALUES("23","123","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-11 21:55:43");
INSERT INTO `status_changes` VALUES("24","128","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-12 00:43:05");
INSERT INTO `status_changes` VALUES("25","128","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-12 01:25:02");
INSERT INTO `status_changes` VALUES("26","127","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-12 01:28:23");
INSERT INTO `status_changes` VALUES("27","130","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-12 15:22:29");
DROP TABLE IF EXISTS `status_updates`;
CREATE TABLE `status_updates` (
  `update_id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `volunteer_id` int NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `location_note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`update_id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `volunteer_id` (`volunteer_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `victim_approvals`;
CREATE TABLE `victim_approvals` (
  `approval_id` int NOT NULL AUTO_INCREMENT,
  `victim_id` int NOT NULL,
  `disaster_id` int NOT NULL,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_id`),
  UNIQUE KEY `unique_victim_disaster` (`victim_id`,`disaster_id`),
  KEY `idx_disaster` (`disaster_id`),
  KEY `idx_status` (`approval_status`)
) ENGINE=InnoDB AUTO_INCREMENT=352 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `victim_approvals` VALUES("230","63","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("231","64","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("232","65","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("233","66","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("234","67","3","Approved","2026-01-11 20:34:30","2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("235","68","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("236","69","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("237","70","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("238","71","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("239","72","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("240","73","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("241","74","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("242","75","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("243","76","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("244","77","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("245","78","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("246","79","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("247","80","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("248","81","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("249","82","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("250","83","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("251","84","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("252","85","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("253","86","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("254","87","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("255","88","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("256","89","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("257","90","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("258","91","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("259","92","3","Pending",NULL,"2026-01-11 20:34:21");
INSERT INTO `victim_approvals` VALUES("260","93","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("261","94","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("262","95","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("263","96","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("264","97","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("265","98","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("266","99","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("267","100","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("268","101","4","Approved","2026-01-11 22:01:08","2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("269","102","4","Approved","2026-01-11 22:01:18","2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("270","103","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("271","104","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("272","105","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("273","106","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("274","107","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("275","108","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("276","109","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("277","110","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("278","111","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("279","112","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("280","113","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("281","114","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("282","115","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("283","116","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("284","117","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("285","118","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("286","119","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("287","120","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("288","121","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("289","122","4","Pending",NULL,"2026-01-11 21:38:53");
INSERT INTO `victim_approvals` VALUES("290","33","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("291","34","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("292","35","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("293","36","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("294","37","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("295","38","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("296","39","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("297","40","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("298","41","2","Approved","2026-01-11 21:39:32","2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("299","42","2","Approved","2026-01-11 21:39:42","2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("300","43","2","Approved","2026-01-11 21:39:52","2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("301","44","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("302","45","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("303","46","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("304","47","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("305","48","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("306","49","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("307","50","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("308","51","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("309","52","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("310","53","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("311","54","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("312","55","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("313","56","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("314","57","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("315","58","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("316","59","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("317","60","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("318","61","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("319","62","2","Pending",NULL,"2026-01-11 21:39:17");
INSERT INTO `victim_approvals` VALUES("320","1","1","Approved","2026-01-12 16:56:55","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("321","2","1","Approved","2026-01-12 16:57:05","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("322","3","1","Approved","2026-01-12 16:57:15","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("323","4","1","Approved","2026-01-12 16:57:25","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("324","5","1","Approved","2026-01-12 16:57:35","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("325","6","1","Approved","2026-01-12 16:57:45","2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("326","7","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("327","8","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("328","9","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("329","10","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("330","11","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("331","12","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("332","13","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("333","14","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("334","15","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("335","16","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("336","17","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("337","18","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("338","19","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("339","20","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("340","21","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("341","22","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("342","23","1","Pending",NULL,"2026-01-12 16:55:24");
INSERT INTO `victim_approvals` VALUES("343","24","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("344","25","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("345","26","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("346","27","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("347","28","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("348","29","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("349","30","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("350","31","1","Pending",NULL,"2026-01-12 16:55:25");
INSERT INTO `victim_approvals` VALUES("351","32","1","Pending",NULL,"2026-01-12 16:55:25");
DROP TABLE IF EXISTS `victim_coordinates`;
CREATE TABLE `victim_coordinates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `victim_id` int NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_victim` (`victim_id`),
  KEY `victim_id` (`victim_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `volunteer_alerts`;
CREATE TABLE `volunteer_alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `volunteer_id` int NOT NULL,
  `distribution_id` int NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `volunteer_id` (`volunteer_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `volunteer_alerts` VALUES("3","24","123","You have confirmed your assignment for Distribution #123. You can now start the distribution when ready.","1","2026-01-11 21:55:43");
INSERT INTO `volunteer_alerts` VALUES("4","24","128","You have confirmed your assignment for Distribution #128. You can now start the distribution when ready.","1","2026-01-12 01:25:02");
INSERT INTO `volunteer_alerts` VALUES("5","23","127","You have confirmed your assignment for Distribution #127. You can now start the distribution when ready.","1","2026-01-12 01:28:23");
INSERT INTO `volunteer_alerts` VALUES("6","1026","130","You have confirmed your assignment for Distribution #130. You can now start the distribution when ready.","1","2026-01-12 15:22:29");
DROP TABLE IF EXISTS `volunteer_distribution_assignments`;
CREATE TABLE `volunteer_distribution_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` int NOT NULL,
  `volunteer_id` int NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Volunteer',
  `status` varchar(50) NOT NULL DEFAULT 'Assigned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`distribution_id`,`volunteer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `volunteer_distribution_assignments` VALUES("3","123","24","Medical","Assigned","2026-01-11 21:38:15","2026-01-11 21:38:16");
INSERT INTO `volunteer_distribution_assignments` VALUES("6","128","24","Medical","Assigned","2026-01-12 01:24:48","2026-01-12 01:24:48");
INSERT INTO `volunteer_distribution_assignments` VALUES("7","127","23","General Volunteer","Assigned","2026-01-12 01:27:55","2026-01-12 01:27:55");
INSERT INTO `volunteer_distribution_assignments` VALUES("8","130","1026","Logistics / Supplies, Food Services","Assigned","2026-01-12 15:22:09","2026-01-12 15:22:09");
DROP TABLE IF EXISTS `vw_victim_public`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_victim_public` AS select `victim_approvals`.`victim_id` AS `victim_id`,`victim_approvals`.`approval_status` AS `approval_status`,`victim_approvals`.`approved_at` AS `approved_at` from `victim_approvals`;

INSERT INTO `vw_victim_public` VALUES("63","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("64","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("65","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("66","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("67","Approved","2026-01-11 20:34:30");
INSERT INTO `vw_victim_public` VALUES("68","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("69","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("70","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("71","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("72","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("73","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("74","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("75","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("76","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("77","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("78","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("79","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("80","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("81","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("82","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("83","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("84","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("85","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("86","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("87","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("88","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("89","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("90","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("91","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("92","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("93","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("94","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("95","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("96","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("97","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("98","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("99","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("100","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("101","Approved","2026-01-11 22:01:08");
INSERT INTO `vw_victim_public` VALUES("102","Approved","2026-01-11 22:01:18");
INSERT INTO `vw_victim_public` VALUES("103","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("104","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("105","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("106","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("107","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("108","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("109","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("110","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("111","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("112","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("113","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("114","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("115","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("116","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("117","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("118","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("119","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("120","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("121","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("122","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("33","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("34","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("35","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("36","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("37","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("38","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("39","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("40","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("41","Approved","2026-01-11 21:39:32");
INSERT INTO `vw_victim_public` VALUES("42","Approved","2026-01-11 21:39:42");
INSERT INTO `vw_victim_public` VALUES("43","Approved","2026-01-11 21:39:52");
INSERT INTO `vw_victim_public` VALUES("44","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("45","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("46","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("47","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("48","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("49","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("50","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("51","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("52","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("53","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("54","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("55","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("56","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("57","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("58","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("59","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("60","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("61","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("62","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("1","Approved","2026-01-12 16:56:55");
INSERT INTO `vw_victim_public` VALUES("2","Approved","2026-01-12 16:57:05");
INSERT INTO `vw_victim_public` VALUES("3","Approved","2026-01-12 16:57:15");
INSERT INTO `vw_victim_public` VALUES("4","Approved","2026-01-12 16:57:25");
INSERT INTO `vw_victim_public` VALUES("5","Approved","2026-01-12 16:57:35");
INSERT INTO `vw_victim_public` VALUES("6","Approved","2026-01-12 16:57:45");
INSERT INTO `vw_victim_public` VALUES("7","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("8","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("9","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("10","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("11","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("12","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("13","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("14","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("15","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("16","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("17","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("18","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("19","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("20","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("21","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("22","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("23","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("24","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("25","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("26","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("27","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("28","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("29","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("30","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("31","Pending",NULL);
INSERT INTO `vw_victim_public` VALUES("32","Pending",NULL);
