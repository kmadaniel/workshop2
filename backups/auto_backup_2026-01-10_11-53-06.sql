-- Automated Backup - 2026-01-10 11:53:06

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution` VALUES("27",NULL,"3",NULL,"2025-12-12","Active","0","DISTRIBUTION PLAN
=================
Time: 2025-12-12 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: kamal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 3, 5
Additional Comments: hhh
Plan Created: 2025-12-10 07:47:09

Status Update (2025-12-10 15:48:04): 

Status Update (2025-12-10 19:56:24): ",NULL,NULL,NULL,NULL,NULL,"0");
INSERT INTO `distribution` VALUES("28",NULL,"2",NULL,"2025-12-12","Planned","0","DISTRIBUTION PLAN
=================
Time: 2025-12-12 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: kamal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 2
Additional Comments: iiii
Plan Created: 2025-12-10 12:03:20",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("29","41","13","25","2025-01-20","Delivered","30","Delivered by BSM Team. Family of 6 received basic supplies.",NULL,NULL,NULL,NULL,NULL,"30");
INSERT INTO `distribution` VALUES("30","41","13","29","2025-01-20","Delivered","5","Additional baby supplies included",NULL,NULL,NULL,NULL,NULL,"5");
INSERT INTO `distribution` VALUES("31",NULL,"4",NULL,"2025-12-12","Active","0","DISTRIBUTION PLAN
=================
Time: 2025-12-12 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 18, 22, 25
Additional Comments: Fund
Plan Created: 2025-12-10 17:08:59

Status Update (2025-12-11 01:10:18): ",NULL,NULL,NULL,NULL,NULL,"0");
INSERT INTO `distribution` VALUES("32",NULL,"4",NULL,"2025-12-12","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-12 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 24, 19
Additional Comments: no need
Plan Created: 2025-12-10 23:58:06

Status Update (2025-12-11 09:50:23): k",NULL,NULL,NULL,NULL,NULL,"0");
INSERT INTO `distribution` VALUES("33",NULL,"6",NULL,"2025-12-13","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-13 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 39
Additional Comments: hk
Plan Created: 2025-12-11 01:38:29

Status Update (2025-12-11 09:44:03): complete hantar",NULL,NULL,NULL,NULL,NULL,"0");
INSERT INTO `distribution` VALUES("37",NULL,"7",NULL,"2025-12-18","Planned","0","DISTRIBUTION PLAN
=================
Time: 2025-12-18 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ahmad  (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 51
Additional Comments: dd
Plan Created: 2025-12-16 03:40:35",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("42",NULL,"7",NULL,"2025-12-18","Planning","0","DISTRIBUTION PLAN
=================
Time: 2025-12-18 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ahmad  (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 53
Plan Created: 2025-12-16 08:50:56",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("47",NULL,"8",NULL,"2025-12-26","Cancelled","0","DISTRIBUTION PLAN
=================
Time: 2025-12-26 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 7
Additional Comments: gg
Plan Created: 2025-12-24 15:40:08

Status Update (2025-12-25 00:31:30): 

Status Update (2025-12-25 11:15:44): ",NULL,NULL,NULL,NULL,NULL,"0");
INSERT INTO `distribution` VALUES("50",NULL,"3",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Needs: 5
Additional Comments: jj
Plan Created: 2025-12-25 04:22:28",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("52",NULL,"8",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ahmad  (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 10
Additional Comments: gg
Plan Created: 2025-12-25 04:51:09",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("53",NULL,"5",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 33
Additional Comments: gg
Plan Created: 2025-12-25 05:12:56",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("54",NULL,"8",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 11
Additional Comments: jj
Plan Created: 2025-12-25 05:44:51",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("55",NULL,"8",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ahmad  (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 16
Additional Comments: kk
Plan Created: 2025-12-25 05:46:14",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("56",NULL,"5",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 29
Additional Comments: sd
Plan Created: 2025-12-25 06:50:19",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("57",NULL,"8",NULL,"2025-12-27","Completed","0","DISTRIBUTION PLAN
=================
Time: 2025-12-27 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: Ahmad  (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Needs: 13
Additional Comments: kk
Plan Created: 2025-12-25 07:38:25",NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `distribution` VALUES("58",NULL,"2",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 3 kg
- Beras Super 5%: 2 kg
Additional Comments: gg
Plan Created: 2025-12-30 13:40:14","Gelanggang Futsal Bandaraya","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("59",NULL,"2",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 3 kg
- Beras Super 5%: 2 kg
Additional Comments: gg
Plan Created: 2025-12-30 13:54:05","Gelanggang Futsal Bandaraya","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("60",NULL,"3",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 2 kg
- Beras Super 5%: 2 kg
Additional Comments: dd
Plan Created: 2025-12-30 14:43:05","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("61",NULL,"3",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 2 kg
- Beras Super 5%: 2 kg
Additional Comments: dd
Plan Created: 2025-12-30 14:50:26","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("62",NULL,"3",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 2 kg
- Beras Super 5%: 2 kg
Additional Comments: dd
Plan Created: 2025-12-30 14:50:38","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("63",NULL,"3",NULL,"2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 2 kg
- Beras Super 5%: 2 kg
Additional Comments: dd
Plan Created: 2025-12-30 14:50:48","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("64","39","3","7","2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Beras Siam: 2 kg
- Beras Super 5%: 2 kg
Additional Comments: dd
Plan Created: 2025-12-30 15:13:20","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("65","39","3","14","2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Biskut Marie: 2 pek
- Blanket: 2 pieces
- Blanket Wool: 2 helai
Additional Comments: kk
Plan Created: 2025-12-30 15:14:21","Dewan Komuniti Taman Seri Bayu","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("67","74","5","1","2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: kamal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 2 families
Resource Allocations:
- Rice: 3 kg
- Cooking Oil: 3 liter
- Sugar: 3 kg
Additional Comments: gg
Plan Created: 2025-12-30 15:40:19","Dewan Serbaguna Masjid Tanah","kamal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("68","39","3","1","2026-01-01","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-01 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 2 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Additional Comments: hh
Plan Created: 2025-12-30 15:59:53","Balai Raya Kampung Baru","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("71","112","7","1","2026-01-02","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-02 at 16:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Daniel (01117845504)
Estimated Duration: 3 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 3 kg
- Cooking Oil: 3 liter
- Sugar: 2 kg
- Bottled Water: 2 bottles
Additional Comments: Hantar 
Plan Created: 2026-01-02 13:48:57","Sekolah Kebangsaan Alor Gajah","Daniel","01117845504","3","4",NULL);
INSERT INTO `distribution` VALUES("72","112","7","1","2026-01-02","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-02 at 16:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Daniel (01117845504)
Estimated Duration: 3 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 3 kg
- Cooking Oil: 3 liter
- Sugar: 2 kg
- Bottled Water: 2 bottles
Additional Comments: Hantar 
Plan Created: 2026-01-02 13:50:04","Sekolah Kebangsaan Alor Gajah","Daniel","01117845504","3","4",NULL);
INSERT INTO `distribution` VALUES("78","39","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Plan Created: 2026-01-03 07:46:44","Balai Raya Kampung Baru","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("79","39","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Plan Created: 2026-01-03 07:47:12","Balai Raya Kampung Baru","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("80","39","3","1","2026-01-05","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Balai Raya Kampung Baru
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Plan Created: 2026-01-03 08:02:52","Balai Raya Kampung Baru","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("83","109","2","1","2026-01-07","Cancelled","0","DISTRIBUTION PLAN
=================
Time: 2026-01-07 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 3
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Additional Comments: dd
Plan Created: 2026-01-03 12:35:59

Status Update (2026-01-d 03:59:30): ","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","3",NULL);
INSERT INTO `distribution` VALUES("84","74","5","2","2026-01-05","Completed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Cooking Oil: 2 liter
- Sugar: 2 kg
Additional Comments: gg
Plan Created: 2026-01-03 12:40:42","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("85","112","7","1","2026-01-05","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 1 liter
- Sugar: 2 kg
- Bottled Water: 2 bottles
Additional Comments: hh
Plan Created: 2026-01-03 17:56:44","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("86","63","3","1","2026-01-05","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 3
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Additional Comments: kk
Plan Created: 2026-01-03 18:14:58","Gelanggang Futsal Bandaraya","Ikmal","01117845504","2","3",NULL);
INSERT INTO `distribution` VALUES("87","63","3","1","2026-01-05","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Gelanggang Futsal Bandaraya
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 3
Selected Victims: 1 families
Resource Allocations:
- Rice: 2 kg
- Cooking Oil: 2 liter
Additional Comments: kk
Plan Created: 2026-01-03 18:15:18","Gelanggang Futsal Bandaraya","Ikmal","01117845504","2","3",NULL);
INSERT INTO `distribution` VALUES("88","63","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 0.09 units
- Painkillers (medical): 0.02 units
- Syringes (medical): 0.02 units
- Disinfectant (medical): 0.02 units
Additional Comments: jj
Plan Created: 2026-01-03 19:45:30","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("89","63","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 0.09 units
- Painkillers (medical): 0.02 units
- Syringes (medical): 0.02 units
- Disinfectant (medical): 0.02 units
Additional Comments: jj
Plan Created: 2026-01-03 19:45:52","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("90","50","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 1 units
- Bandages (medical): 1 units
Additional Comments: kk
Plan Created: 2026-01-03 20:07:18","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("91","50","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 1 units
- Bandages (medical): 1 units
Additional Comments: kk
Plan Created: 2026-01-03 20:07:56","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("92","74","5","7","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 3 hours
Volunteers Needed: 2
Selected Victims: 1 families
Resource Allocations:
- Bandages (medical): 1 units
Additional Comments: kk
Plan Created: 2026-01-03 20:10:00","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","3","2",NULL);
INSERT INTO `distribution` VALUES("93","50","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 1 units
Additional Comments: kk
Plan Created: 2026-01-03 20:23:15","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("94","50","3","1","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 1 units
Additional Comments: kk
Plan Created: 2026-01-03 20:23:33","Dewan Serbaguna Masjid Tanah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("95","50","3","7","2026-01-05","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Ikmal (01117845504)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Bandages (medical): 78 units
Additional Comments: ff
Plan Created: 2026-01-03 20:33:55","Sekolah Kebangsaan Alor Gajah","Ikmal","01117845504","2","4",NULL);
INSERT INTO `distribution` VALUES("96","66","3","1","2026-01-06","In Transit","0","DISTRIBUTION PLAN
=================
Time: 2026-01-06 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: Ikmal (01117845504)
Estimated Duration: 7 hours
Volunteers Needed: 3
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 79 units
Additional Comments: gg
Plan Created: 2026-01-04 12:43:28","Dewan Komuniti Taman Seri Bayu","Ikmal","01117845504","7","3",NULL);
INSERT INTO `distribution` VALUES("98","112","7","13","2026-01-06","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-06 at 14:00
Location: Sekolah Kebangsaan Alor Gajah
Coordinator: Puan Nora binti Abdullah (06-282-3456)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Resource Allocations:
- Gloves (medical): 3 units
- Thermometers (medical): 2 units
Additional Comments: No comment
Plan Created: 2026-01-04 16:17:00","Sekolah Kebangsaan Alor Gajah","Puan Nora binti Abdullah","06-282-3456","2","2",NULL);
INSERT INTO `distribution` VALUES("99","112","7","1","2026-01-06","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-06 at 14:00
Location: Dewan Komuniti Taman Seri Bayu
Coordinator: En. Kamal bin Ismail (06-282-7890)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets (clothing): 2 units
- Painkillers (medical): 2 units
Additional Comments: hh
Plan Created: 2026-01-04 17:25:35","Dewan Komuniti Taman Seri Bayu","En. Kamal bin Ismail","06-282-7890","2","4",NULL);
INSERT INTO `distribution` VALUES("101","107","5","5","2026-01-05","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-05 at 16:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Encik Ahmad bin Ali (06-282-1234)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Resource Allocations:
- Disinfectant: 3 pieces
- Bandages: 2 liter
- Antibiotic Cream: 1 units
Additional Comments: no need
Plan Created: 2026-01-05 12:54:45","Dewan Serbaguna Masjid Tanah","Encik Ahmad bin Ali","06-282-1234","2","2",NULL);
INSERT INTO `distribution` VALUES("102","108","5","4","2026-01-07","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-07 at 14:00
Location: Dewan Serbaguna Masjid Tanah
Coordinator: Encik Ahmad bin Ali (06-282-1234)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Syringes: 1 bottles
Plan Created: 2026-01-05 18:14:57","Dewan Serbaguna Masjid Tanah","Encik Ahmad bin Ali","06-282-1234","2","4",NULL);
INSERT INTO `distribution` VALUES("103","72","5","1","2026-01-08","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-08 at 17:00
Location: Gelanggang Futsal Bandaraya
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets: 3 pieces
Additional Comments: okey
Plan Created: 2026-01-06 17:20:11","Gelanggang Futsal Bandaraya","En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("104","81","5","1","2026-01-08","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-08 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets: 1 pieces
Plan Created: 2026-01-06 17:24:57",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("111","97","5","1","2026-01-09","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-09 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets: 2 pieces
- Thermometers: 1 units
Plan Created: 2026-01-07 07:44:37",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("112","105","5","207","2026-01-09","Volunteer Needed","0","DISTRIBUTION PLAN
=================
Time: 2026-01-09 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets: 1 pieces
- Drinking Water: 1 liters
Plan Created: 2026-01-07 13:58:23",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("113","40","3","207","2026-01-30","In Progress","0","DISTRIBUTION PLAN
=================
Time: 2026-01-30 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Blankets: 2 pieces
Plan Created: 2026-01-07 14:55:01",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("114","121","7","610","2026-01-23","Assigned","0","DISTRIBUTION PLAN
=================
Time: 2026-01-23 at 17:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 3
Selected Victims: 1 families
Resource Allocations:
- Drinking Water: 1 liters
Plan Created: 2026-01-08 02:36:34",NULL,"En. Daniel","017-585 6449","2","3",NULL);
INSERT INTO `distribution` VALUES("115","132","12","0","2026-01-24","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-24 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- Emergency Kits: 1 units
Plan Created: 2026-01-08 12:58:03",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("116","2","1","0","2026-02-04","Assigned","0","DISTRIBUTION PLAN
=================
Time: 2026-02-04 at 18:20
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 2
Selected Victims: 1 families
Resource Allocations:
- [Special Need] Emergency Blankets: 1 pieces
- [Normal Need] Baby Clothes: 1 pieces
- [Special Need] Adult Diapers: 1 units
- [Special Need] Baby Diapers: 1 units
- [Special Need] Baby Formula: 1 units
- [Special Need] Baby Wipes: 1 units
- [Special Need] Blood Pressure Monitor: 1 units
- [Special Need] Blood Pressure Monitors: 1 units
- [Special Need] Pain Relievers: 1 units
- [Special Need] Prescription Medications: 1 units
- [Special Need] Walking Stick: 1 units
- [Normal Need] Baby Bottles: 1 units
- [Normal Need] Basic Needs: 1 units
- [Normal Need] Glucose Meter: 1 units
- [Normal Need] Shower Chair: 1 units
- [Special Need] Antiseptic Solution: 1 bottles
- [Special Need] Bandages & Gauze: 1 rolls
- [Special Need] Face Masks: 1 units/packs
- [Special Need] First Aid Kits: 1 units/packs
- [Special Need] Medical Gloves: 1 units/packs
Additional Comments: okey
Plan Created: 2026-01-09 11:39:52",NULL,"En. Daniel","017-585 6449","2","2",NULL);
INSERT INTO `distribution` VALUES("117","59","3","0","2026-01-11","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-11 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Resource Allocations:
- [Special Need] Baby Bottles
Bandages & Gauze: 3 units
Plan Created: 2026-01-09 14:04:28",NULL,"En. Daniel","017-585 6449","2","4",NULL);
INSERT INTO `distribution` VALUES("121","103","4",NULL,"2026-01-11","Planning","0","DISTRIBUTION PLAN
=================
Time: 2026-01-11 at 14:00
Coordinator: En. Daniel (017-585 6449)
Estimated Duration: 2 hours
Volunteers Needed: 4
Selected Victims: 1 families
Total Family Members: 1 individuals

SELECTED FAMILIES DETAILS:
-------------------------
- Muthu bin Wong (ID: 103)
  Family: 1 members
  Shelter: Jasin Community Shelter
  Contact: 012-1930646
  Address: 79 Jalan Bunga Raya, Jasin, Melaka, Jasin
  Special Request: Bandages & Gauze
Blood Pressure Monitors
  👶 Has baby

AUTOMATIC BASIC NEEDS ALLOCATION:
---------------------------------
Basic needs are automatically allocated to all selected families:

- Rice: 5 kg (5 kg per family × 1 families)
- Drinking Water: 10 liters (10 liters per family × 1 families)
- Canned Food: 6 cans (6 cans per family × 1 families)
- Blanket: 2 pieces (2 pieces per family × 1 families)
- First Aid Kit: 1 kits (1 kits per family × 1 families)
- Hygiene Kit: 1 kits (1 kits per family × 1 families)
- Milk Powder: 2 packets (2 packets per family × 1 families)
- Instant Noodles: 10 packets (10 packets per family × 1 families)

SPECIAL REQUEST ALLOCATIONS:
----------------------------
- [Special] Bandages & Gauze: 3 units (Requested by: Muthu bin Wong)
- [Special] Blood Pressure Monitors: 3 units (Requested by: Muthu bin Wong)

Plan Created: 2026-01-09 15:54:29",NULL,"En. Daniel","017-585 6449","2","4",NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_items` VALUES("1","59","109","1","Scheduled",NULL,"2025-12-30 21:54:05");
INSERT INTO `distribution_items` VALUES("2","60","39","1","Scheduled",NULL,"2025-12-30 22:43:05");
INSERT INTO `distribution_items` VALUES("3","61","39","1","Scheduled",NULL,"2025-12-30 22:50:27");
INSERT INTO `distribution_items` VALUES("4","62","39","1","Scheduled",NULL,"2025-12-30 22:50:39");
INSERT INTO `distribution_items` VALUES("5","63","39","1","Scheduled",NULL,"2025-12-30 22:50:48");
INSERT INTO `distribution_items` VALUES("6","64","39","1","Scheduled",NULL,"2025-12-30 23:13:21");
INSERT INTO `distribution_items` VALUES("7","65","39","1","Scheduled",NULL,"2025-12-30 23:14:21");
INSERT INTO `distribution_items` VALUES("10","67","74","1","Scheduled",NULL,"2025-12-30 23:40:19");
INSERT INTO `distribution_items` VALUES("11","67","76","1","Scheduled",NULL,"2025-12-30 23:40:19");
INSERT INTO `distribution_items` VALUES("12","68","39","1","Scheduled",NULL,"2025-12-30 23:59:53");
INSERT INTO `distribution_items` VALUES("13","68","43","1","Scheduled",NULL,"2025-12-30 23:59:53");
INSERT INTO `distribution_items` VALUES("16","71","112","1","Scheduled",NULL,"2026-01-02 21:48:57");
INSERT INTO `distribution_items` VALUES("17","72","112","1","Scheduled",NULL,"2026-01-02 21:50:04");
INSERT INTO `distribution_items` VALUES("24","78","39","1","Scheduled",NULL,"2026-01-03 15:46:44");
INSERT INTO `distribution_items` VALUES("25","79","39","1","Scheduled",NULL,"2026-01-03 15:47:13");
INSERT INTO `distribution_items` VALUES("26","80","39","1","Delivered",NULL,"2026-01-03 16:02:52");
INSERT INTO `distribution_items` VALUES("29","83","109","1","Dispatched",NULL,"2026-01-03 20:35:59");
INSERT INTO `distribution_items` VALUES("30","84","74","1","Delivered",NULL,"2026-01-03 20:40:42");
INSERT INTO `distribution_items` VALUES("31","85","112","1","Scheduled",NULL,"2026-01-04 01:56:44");
INSERT INTO `distribution_items` VALUES("32","86","63","1","Scheduled",NULL,"2026-01-04 02:14:58");
INSERT INTO `distribution_items` VALUES("33","87","63","1","Dispatched",NULL,"2026-01-04 02:15:18");
INSERT INTO `distribution_items` VALUES("34","88","63","1","Scheduled",NULL,"2026-01-04 03:45:31");
INSERT INTO `distribution_items` VALUES("35","89","63","1","Scheduled",NULL,"2026-01-04 03:45:52");
INSERT INTO `distribution_items` VALUES("36","90","50","1","Scheduled",NULL,"2026-01-04 04:07:18");
INSERT INTO `distribution_items` VALUES("37","91","50","1","Scheduled",NULL,"2026-01-04 04:07:56");
INSERT INTO `distribution_items` VALUES("38","92","74","1","Scheduled",NULL,"2026-01-04 04:10:00");
INSERT INTO `distribution_items` VALUES("39","93","50","1","Scheduled",NULL,"2026-01-04 04:23:15");
INSERT INTO `distribution_items` VALUES("40","94","50","1","Scheduled",NULL,"2026-01-04 04:23:33");
INSERT INTO `distribution_items` VALUES("41","95","50","1","Dispatched",NULL,"2026-01-04 04:33:56");
INSERT INTO `distribution_items` VALUES("42","96","66","1","Dispatched",NULL,"2026-01-04 20:43:28");
INSERT INTO `distribution_items` VALUES("44","98","112","1","Scheduled",NULL,"2026-01-05 00:17:00");
INSERT INTO `distribution_items` VALUES("45","99","112","1","Scheduled",NULL,"2026-01-05 01:25:35");
INSERT INTO `distribution_items` VALUES("47","101","107","1","Scheduled",NULL,"2026-01-05 20:54:45");
INSERT INTO `distribution_items` VALUES("48","102","108","1","Scheduled",NULL,"2026-01-06 02:14:57");
INSERT INTO `distribution_items` VALUES("49","103","72","1","Scheduled",NULL,"2026-01-07 01:20:12");
INSERT INTO `distribution_items` VALUES("50","104","81","1","Dispatched",NULL,"2026-01-07 01:24:57");
INSERT INTO `distribution_items` VALUES("51","111","97","1","Scheduled",NULL,"2026-01-07 15:44:37");
INSERT INTO `distribution_items` VALUES("52","112","105","1","Scheduled",NULL,"2026-01-07 21:58:23");
INSERT INTO `distribution_items` VALUES("53","113","40","1","Dispatched",NULL,"2026-01-07 22:55:01");
INSERT INTO `distribution_items` VALUES("54","114","121","1","Scheduled",NULL,"2026-01-08 10:36:34");
INSERT INTO `distribution_items` VALUES("55","115","132","1","Scheduled",NULL,"2026-01-08 20:58:03");
INSERT INTO `distribution_items` VALUES("56","116","2","1","Scheduled",NULL,"2026-01-09 19:39:52");
INSERT INTO `distribution_items` VALUES("57","117","59","1","Scheduled",NULL,"2026-01-09 22:04:28");
INSERT INTO `distribution_items` VALUES("61","121","103","1","Scheduled",NULL,"2026-01-09 23:54:29");
DROP TABLE IF EXISTS `distribution_log`;
CREATE TABLE `distribution_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `volunteer_id` int NOT NULL,
  `shelter_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `victim_id` int NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `distribution_log` VALUES("1","80","23",NULL,"39","17439","1","2026-01-03 18:17:41","in_transit",NULL,NULL,"2026-01-03 18:17:41");
INSERT INTO `distribution_log` VALUES("5","83","23",NULL,"109","17424","1","2026-01-03 20:36:56","in_transit",NULL,NULL,"2026-01-03 20:36:56");
INSERT INTO `distribution_log` VALUES("6","84","23",NULL,"74","17425","1","2026-01-03 21:13:01","in_transit","hh","uploads/signatures/signature_84_74_1767446052.png","2026-01-03 21:13:01");
INSERT INTO `distribution_log` VALUES("7","87","23",NULL,"63","17445","1","2026-01-04 02:16:32","in_transit",NULL,NULL,"2026-01-04 02:16:32");
INSERT INTO `distribution_log` VALUES("8","95","23",NULL,"50","17502","1","2026-01-04 04:39:15","in_transit",NULL,NULL,"2026-01-04 04:39:15");
INSERT INTO `distribution_log` VALUES("9","96","23",NULL,"66","17515","1","2026-01-04 21:57:39","in_transit",NULL,NULL,"2026-01-04 21:57:39");
INSERT INTO `distribution_log` VALUES("11","102","23",NULL,"108","1","1","2026-01-06 04:00:45","in_transit",NULL,NULL,"2026-01-06 04:00:45");
INSERT INTO `distribution_log` VALUES("12","99","23",NULL,"112","1","1","2026-01-06 14:42:02","in_transit",NULL,NULL,"2026-01-06 14:42:02");
INSERT INTO `distribution_log` VALUES("15","104","23",NULL,"81","1","1","2026-01-07 02:45:36","in_transit",NULL,NULL,"2026-01-07 02:45:36");
INSERT INTO `distribution_log` VALUES("16","104","23",NULL,"81","17519","1","2026-01-07 03:08:50","in_transit",NULL,NULL,"2026-01-07 03:08:50");
INSERT INTO `distribution_log` VALUES("18","113","24",NULL,"40","1","1","2026-01-08 01:31:30","in_transit",NULL,NULL,"2026-01-08 01:31:30");
INSERT INTO `distribution_log` VALUES("20","113","24",NULL,"40","17494","1","2026-01-08 01:59:38","in_transit",NULL,NULL,"2026-01-08 01:59:38");
INSERT INTO `distribution_log` VALUES("26","114","24",NULL,"121","1","1","2026-01-08 10:56:47","in_transit",NULL,NULL,"2026-01-08 10:56:47");
INSERT INTO `distribution_log` VALUES("27","113","24","Stadium Hang Jebat","33","33","1","2026-01-09 22:36:18","in_transit",NULL,NULL,"2026-01-09 22:36:18");
INSERT INTO `distribution_log` VALUES("28","113","24","Stadium Hang Jebat","43","43","1","2026-01-09 22:36:18","in_transit",NULL,NULL,"2026-01-09 22:36:18");
INSERT INTO `distribution_log` VALUES("29","113","24","Stadium Hang Jebat","51","51","1","2026-01-09 22:36:18","in_transit",NULL,NULL,"2026-01-09 22:36:18");
INSERT INTO `distribution_log` VALUES("30","113","24","Stadium Hang Jebat","52","52","1","2026-01-09 22:36:18","in_transit",NULL,NULL,"2026-01-09 22:36:18");
INSERT INTO `distribution_log` VALUES("31","95","23","Melaka Tengah Emergency Shelter 1","1","1","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("32","95","23","Melaka Tengah Emergency Shelter 1","42","42","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("33","95","23","Melaka Tengah Emergency Shelter 1","44","44","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("34","95","23","Melaka Tengah Emergency Shelter 1","47","47","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("35","95","23","Melaka Tengah Emergency Shelter 1","55","55","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("36","95","23","Melaka Tengah Emergency Shelter 1","57","57","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("37","95","23","Melaka Tengah Emergency Shelter 1","59","59","1","2026-01-09 22:38:53","in_transit",NULL,NULL,"2026-01-09 22:38:53");
INSERT INTO `distribution_log` VALUES("38","113","24","Melaka Tengah Emergency Shelter 1","1","1","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("39","113","24","Melaka Tengah Emergency Shelter 1","42","42","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("40","113","24","Melaka Tengah Emergency Shelter 1","44","44","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("41","113","24","Melaka Tengah Emergency Shelter 1","47","47","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("42","113","24","Melaka Tengah Emergency Shelter 1","55","55","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("43","113","24","Melaka Tengah Emergency Shelter 1","57","57","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
INSERT INTO `distribution_log` VALUES("44","113","24","Melaka Tengah Emergency Shelter 1","59","59","1","2026-01-10 15:11:26","in_transit",NULL,NULL,"2026-01-10 15:11:26");
DROP TABLE IF EXISTS `distribution_resources`;
CREATE TABLE `distribution_resources` (
  `allocation_id` int NOT NULL AUTO_INCREMENT,
  `distribution_id` bigint unsigned NOT NULL,
  `resource_id` int NOT NULL,
  `quantity_allocated` int NOT NULL,
  `quantity_distributed` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  KEY `distribution_id` (`distribution_id`),
  KEY `resource_id` (`resource_id`),
  CONSTRAINT `distribution_resources_ibfk_1` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_resources_ibfk_2` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE,
  CONSTRAINT `distribution_resources_ibfk_3` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`distribution_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_resources` VALUES("1","64","7","2","0","2025-12-30 23:13:21");
INSERT INTO `distribution_resources` VALUES("2","64","6","2","0","2025-12-30 23:13:21");
INSERT INTO `distribution_resources` VALUES("3","65","14","2","0","2025-12-30 23:14:21");
INSERT INTO `distribution_resources` VALUES("4","65","4","2","0","2025-12-30 23:14:21");
INSERT INTO `distribution_resources` VALUES("5","65","18","2","0","2025-12-30 23:14:21");
INSERT INTO `distribution_resources` VALUES("10","67","1","3","0","2025-12-30 23:40:19");
INSERT INTO `distribution_resources` VALUES("11","67","2","3","0","2025-12-30 23:40:19");
INSERT INTO `distribution_resources` VALUES("12","67","3","3","0","2025-12-30 23:40:19");
INSERT INTO `distribution_resources` VALUES("13","68","1","2","0","2025-12-30 23:59:53");
INSERT INTO `distribution_resources` VALUES("14","68","2","2","0","2025-12-30 23:59:53");
INSERT INTO `distribution_resources` VALUES("20","71","1","3","0","2026-01-02 21:48:57");
INSERT INTO `distribution_resources` VALUES("21","71","2","3","0","2026-01-02 21:48:57");
INSERT INTO `distribution_resources` VALUES("22","71","3","2","0","2026-01-02 21:48:57");
INSERT INTO `distribution_resources` VALUES("23","71","4","2","0","2026-01-02 21:48:57");
INSERT INTO `distribution_resources` VALUES("24","72","1","3","0","2026-01-02 21:50:04");
INSERT INTO `distribution_resources` VALUES("25","72","2","3","0","2026-01-02 21:50:04");
INSERT INTO `distribution_resources` VALUES("26","72","3","2","0","2026-01-02 21:50:04");
INSERT INTO `distribution_resources` VALUES("27","72","4","2","0","2026-01-02 21:50:04");
INSERT INTO `distribution_resources` VALUES("42","78","1","2","0","2026-01-03 15:46:44");
INSERT INTO `distribution_resources` VALUES("43","78","2","2","0","2026-01-03 15:46:44");
INSERT INTO `distribution_resources` VALUES("44","79","1","2","0","2026-01-03 15:47:13");
INSERT INTO `distribution_resources` VALUES("45","79","2","2","0","2026-01-03 15:47:13");
INSERT INTO `distribution_resources` VALUES("46","80","1","2","0","2026-01-03 16:02:52");
INSERT INTO `distribution_resources` VALUES("47","80","2","2","0","2026-01-03 16:02:52");
INSERT INTO `distribution_resources` VALUES("55","83","1","2","0","2026-01-03 20:35:59");
INSERT INTO `distribution_resources` VALUES("56","83","2","2","0","2026-01-03 20:35:59");
INSERT INTO `distribution_resources` VALUES("57","84","2","2","0","2026-01-03 20:40:42");
INSERT INTO `distribution_resources` VALUES("58","84","3","2","0","2026-01-03 20:40:42");
INSERT INTO `distribution_resources` VALUES("59","85","1","2","0","2026-01-04 01:56:44");
INSERT INTO `distribution_resources` VALUES("60","85","2","1","0","2026-01-04 01:56:44");
INSERT INTO `distribution_resources` VALUES("61","85","3","2","0","2026-01-04 01:56:44");
INSERT INTO `distribution_resources` VALUES("62","85","4","2","0","2026-01-04 01:56:44");
INSERT INTO `distribution_resources` VALUES("63","86","1","2","0","2026-01-04 02:14:58");
INSERT INTO `distribution_resources` VALUES("64","86","2","2","0","2026-01-04 02:14:58");
INSERT INTO `distribution_resources` VALUES("65","87","1","2","0","2026-01-04 02:15:18");
INSERT INTO `distribution_resources` VALUES("66","87","2","2","0","2026-01-04 02:15:18");
INSERT INTO `distribution_resources` VALUES("67","88","1","0","0","2026-01-04 03:45:31");
INSERT INTO `distribution_resources` VALUES("68","88","3","0","0","2026-01-04 03:45:31");
INSERT INTO `distribution_resources` VALUES("69","88","4","0","0","2026-01-04 03:45:31");
INSERT INTO `distribution_resources` VALUES("70","88","5","0","0","2026-01-04 03:45:31");
INSERT INTO `distribution_resources` VALUES("71","89","1","0","0","2026-01-04 03:45:52");
INSERT INTO `distribution_resources` VALUES("72","89","3","0","0","2026-01-04 03:45:52");
INSERT INTO `distribution_resources` VALUES("73","89","4","0","0","2026-01-04 03:45:52");
INSERT INTO `distribution_resources` VALUES("74","89","5","0","0","2026-01-04 03:45:52");
INSERT INTO `distribution_resources` VALUES("75","90","1","1","0","2026-01-04 04:07:18");
INSERT INTO `distribution_resources` VALUES("76","90","7","1","0","2026-01-04 04:07:18");
INSERT INTO `distribution_resources` VALUES("77","91","1","1","0","2026-01-04 04:07:56");
INSERT INTO `distribution_resources` VALUES("78","91","7","1","0","2026-01-04 04:07:56");
INSERT INTO `distribution_resources` VALUES("79","92","7","1","0","2026-01-04 04:10:00");
INSERT INTO `distribution_resources` VALUES("80","93","1","1","0","2026-01-04 04:23:15");
INSERT INTO `distribution_resources` VALUES("81","94","1","1","0","2026-01-04 04:23:33");
INSERT INTO `distribution_resources` VALUES("82","95","7","78","0","2026-01-04 04:33:56");
INSERT INTO `distribution_resources` VALUES("83","96","1","79","0","2026-01-04 20:43:28");
INSERT INTO `distribution_resources` VALUES("86","98","13","3","0","2026-01-05 00:17:00");
INSERT INTO `distribution_resources` VALUES("87","98","14","2","0","2026-01-05 00:17:00");
INSERT INTO `distribution_resources` VALUES("88","99","1","2","0","2026-01-05 01:25:35");
INSERT INTO `distribution_resources` VALUES("89","99","3","2","0","2026-01-05 01:25:35");
INSERT INTO `distribution_resources` VALUES("92","101","5","3","0","2026-01-05 20:54:45");
INSERT INTO `distribution_resources` VALUES("93","101","2","2","0","2026-01-05 20:54:45");
INSERT INTO `distribution_resources` VALUES("94","101","11","1","0","2026-01-05 20:54:45");
INSERT INTO `distribution_resources` VALUES("95","102","4","1","0","2026-01-06 02:14:57");
INSERT INTO `distribution_resources` VALUES("96","103","1","3","0","2026-01-07 01:20:12");
INSERT INTO `distribution_resources` VALUES("97","104","1","1","0","2026-01-07 01:24:57");
INSERT INTO `distribution_resources` VALUES("98","111","1","2","0","2026-01-07 15:44:37");
INSERT INTO `distribution_resources` VALUES("99","111","14","1","0","2026-01-07 15:44:37");
INSERT INTO `distribution_resources` VALUES("100","112","207","1","0","2026-01-07 21:58:23");
INSERT INTO `distribution_resources` VALUES("101","112","610","1","0","2026-01-07 21:58:23");
INSERT INTO `distribution_resources` VALUES("102","113","207","2","0","2026-01-07 22:55:01");
INSERT INTO `distribution_resources` VALUES("103","114","610","1","0","2026-01-08 10:36:34");
INSERT INTO `distribution_resources` VALUES("104","115","0","1","0","2026-01-08 20:58:03");
INSERT INTO `distribution_resources` VALUES("105","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("106","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("107","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("108","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("109","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("110","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("111","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("112","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("113","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("114","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("115","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("116","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("117","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("118","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("119","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("120","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("121","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("122","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("123","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("124","116","0","1","0","2026-01-09 19:39:52");
INSERT INTO `distribution_resources` VALUES("125","117","0","3","0","2026-01-09 22:04:28");
INSERT INTO `distribution_resources` VALUES("126","121","0","5","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("127","121","0","10","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("128","121","0","6","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("129","121","0","2","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("130","121","0","1","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("131","121","0","1","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("132","121","0","2","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("133","121","0","10","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("134","121","1","3","0","2026-01-09 23:54:29");
INSERT INTO `distribution_resources` VALUES("135","121","1","3","0","2026-01-09 23:54:29");
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
) ENGINE=InnoDB AUTO_INCREMENT=621 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_tracking` VALUES("1","84","23",NULL,"74","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-03 21:13:01");
INSERT INTO `distribution_tracking` VALUES("2","84","23",NULL,"74","On the way - Main Highway","heavy","40","departed","2026-01-03 21:13:32");
INSERT INTO `distribution_tracking` VALUES("3","83","23",NULL,"109","Stopped for traffic","","40","delayed","2026-01-03 21:17:41");
INSERT INTO `distribution_tracking` VALUES("4","87","23",NULL,"63","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-04 02:16:32");
INSERT INTO `distribution_tracking` VALUES("5","95","23",NULL,"50","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-04 04:39:15");
INSERT INTO `distribution_tracking` VALUES("6","96","23",NULL,"66","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-04 21:57:39");
INSERT INTO `distribution_tracking` VALUES("7","95","23",NULL,"50","Stopped for traffic","heavy","40","in_transit","2026-01-06 20:49:32");
INSERT INTO `distribution_tracking` VALUES("8","95","23",NULL,"0","Lat: 2.248337, Lng: 102.310231","Live GPS tracking - Accuracy: 17m",NULL,"in_transit","2026-01-06 21:17:23");
INSERT INTO `distribution_tracking` VALUES("9","95","23",NULL,"0","Lat: 2.248048, Lng: 102.310273","Live GPS tracking - Accuracy: 19.4m",NULL,"in_transit","2026-01-06 21:17:25");
INSERT INTO `distribution_tracking` VALUES("10","95","23",NULL,"0","Lat: 2.247922, Lng: 102.310592","Live GPS tracking - Accuracy: 20.8m",NULL,"in_transit","2026-01-06 21:17:28");
INSERT INTO `distribution_tracking` VALUES("11","95","23",NULL,"0","Lat: 2.247672, Lng: 102.310719","Live GPS tracking - Accuracy: 27.2m",NULL,"in_transit","2026-01-06 21:17:30");
INSERT INTO `distribution_tracking` VALUES("12","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:17:32");
INSERT INTO `distribution_tracking` VALUES("13","95","23",NULL,"0","Lat: 2.247562, Lng: 102.310818","Live GPS tracking - Accuracy: 29m",NULL,"in_transit","2026-01-06 21:17:33");
INSERT INTO `distribution_tracking` VALUES("14","95","23",NULL,"0","Lat: 2.247867, Lng: 102.311283","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 21:17:35");
INSERT INTO `distribution_tracking` VALUES("15","95","23",NULL,"0","Lat: 2.247771, Lng: 102.311176","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:17:38");
INSERT INTO `distribution_tracking` VALUES("16","95","23",NULL,"0","Lat: 2.247804, Lng: 102.311053","Live GPS tracking - Accuracy: 27.5m",NULL,"in_transit","2026-01-06 21:17:40");
INSERT INTO `distribution_tracking` VALUES("17","95","23",NULL,"0","Lat: 2.247496, Lng: 102.311301","Live GPS tracking - Accuracy: 14.2m",NULL,"in_transit","2026-01-06 21:17:43");
INSERT INTO `distribution_tracking` VALUES("18","95","23",NULL,"0","Lat: 2.247257, Lng: 102.311218","Live GPS tracking - Accuracy: 18m",NULL,"in_transit","2026-01-06 21:17:45");
INSERT INTO `distribution_tracking` VALUES("19","95","23",NULL,"0","Lat: 2.247169, Lng: 102.310832","Live GPS tracking - Accuracy: 10.8m",NULL,"in_transit","2026-01-06 21:17:48");
INSERT INTO `distribution_tracking` VALUES("20","95","23",NULL,"0","Lat: 2.24709, Lng: 102.311166","Live GPS tracking - Accuracy: 22.6m",NULL,"in_transit","2026-01-06 21:17:50");
INSERT INTO `distribution_tracking` VALUES("21","95","23",NULL,"0","Lat: 2.247495, Lng: 102.311107","Live GPS tracking - Accuracy: 14.8m",NULL,"in_transit","2026-01-06 21:17:53");
INSERT INTO `distribution_tracking` VALUES("22","95","23",NULL,"0","Lat: 2.247795, Lng: 102.311153","Live GPS tracking - Accuracy: 13m",NULL,"in_transit","2026-01-06 21:17:55");
INSERT INTO `distribution_tracking` VALUES("23","95","23",NULL,"0","Lat: 2.248001, Lng: 102.310782","Live GPS tracking - Accuracy: 29m",NULL,"in_transit","2026-01-06 21:17:58");
INSERT INTO `distribution_tracking` VALUES("24","95","23",NULL,"0","Lat: 2.247526, Lng: 102.310392","Live GPS tracking - Accuracy: 19.4m",NULL,"in_transit","2026-01-06 21:18:00");
INSERT INTO `distribution_tracking` VALUES("25","95","23",NULL,"0","Lat: 2.247672, Lng: 102.31084","Live GPS tracking - Accuracy: 10.1m",NULL,"in_transit","2026-01-06 21:18:03");
INSERT INTO `distribution_tracking` VALUES("26","95","23",NULL,"0","Lat: 2.247179, Lng: 102.311323","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 21:18:05");
INSERT INTO `distribution_tracking` VALUES("27","95","23",NULL,"0","Lat: 2.247532, Lng: 102.311735","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:18:08");
INSERT INTO `distribution_tracking` VALUES("28","95","23",NULL,"0","Lat: 2.247547, Lng: 102.311285","Live GPS tracking - Accuracy: 29.6m",NULL,"in_transit","2026-01-06 21:18:10");
INSERT INTO `distribution_tracking` VALUES("29","95","23",NULL,"0","Lat: 2.247607, Lng: 102.31131","Live GPS tracking - Accuracy: 25m",NULL,"in_transit","2026-01-06 21:18:13");
INSERT INTO `distribution_tracking` VALUES("30","95","23",NULL,"0","Lat: 2.24808, Lng: 102.311109","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:18:15");
INSERT INTO `distribution_tracking` VALUES("31","95","23",NULL,"0","Lat: 2.248571, Lng: 102.311281","Live GPS tracking - Accuracy: 20.1m",NULL,"in_transit","2026-01-06 21:18:18");
INSERT INTO `distribution_tracking` VALUES("32","95","23",NULL,"0","Lat: 2.248079, Lng: 102.311078","Live GPS tracking - Accuracy: 14.9m",NULL,"in_transit","2026-01-06 21:18:20");
INSERT INTO `distribution_tracking` VALUES("33","95","23",NULL,"0","Lat: 2.247623, Lng: 102.310983","Live GPS tracking - Accuracy: 11.3m",NULL,"in_transit","2026-01-06 21:18:23");
INSERT INTO `distribution_tracking` VALUES("34","95","23",NULL,"0","Lat: 2.247245, Lng: 102.311035","Live GPS tracking - Accuracy: 11.9m",NULL,"in_transit","2026-01-06 21:18:25");
INSERT INTO `distribution_tracking` VALUES("35","95","23",NULL,"0","Lat: 2.246786, Lng: 102.311345","Live GPS tracking - Accuracy: 16.2m",NULL,"in_transit","2026-01-06 21:18:28");
INSERT INTO `distribution_tracking` VALUES("36","95","23",NULL,"0","Lat: 2.247024, Lng: 102.310953","Live GPS tracking - Accuracy: 16.3m",NULL,"in_transit","2026-01-06 21:18:30");
INSERT INTO `distribution_tracking` VALUES("37","95","23",NULL,"0","Lat: 2.247513, Lng: 102.31091","Live GPS tracking - Accuracy: 26.4m",NULL,"in_transit","2026-01-06 21:18:33");
INSERT INTO `distribution_tracking` VALUES("38","95","23",NULL,"0","Lat: 2.247416, Lng: 102.310703","Live GPS tracking - Accuracy: 25.6m",NULL,"in_transit","2026-01-06 21:18:35");
INSERT INTO `distribution_tracking` VALUES("39","95","23",NULL,"0","Lat: 2.247764, Lng: 102.31088","Live GPS tracking - Accuracy: 10.1m",NULL,"in_transit","2026-01-06 21:18:38");
INSERT INTO `distribution_tracking` VALUES("40","95","23",NULL,"0","Lat: 2.247999, Lng: 102.3108","Live GPS tracking - Accuracy: 14.9m",NULL,"in_transit","2026-01-06 21:18:40");
INSERT INTO `distribution_tracking` VALUES("41","95","23",NULL,"0","Lat: 2.248451, Lng: 102.310833","Live GPS tracking - Accuracy: 19.1m",NULL,"in_transit","2026-01-06 21:18:43");
INSERT INTO `distribution_tracking` VALUES("42","95","23",NULL,"0","Lat: 2.248009, Lng: 102.310712","Live GPS tracking - Accuracy: 13.4m",NULL,"in_transit","2026-01-06 21:18:45");
INSERT INTO `distribution_tracking` VALUES("43","95","23",NULL,"0","Lat: 2.248449, Lng: 102.310385","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 21:18:48");
INSERT INTO `distribution_tracking` VALUES("44","95","23",NULL,"0","Lat: 2.248669, Lng: 102.310603","Live GPS tracking - Accuracy: 16.5m",NULL,"in_transit","2026-01-06 21:18:50");
INSERT INTO `distribution_tracking` VALUES("45","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:18:51");
INSERT INTO `distribution_tracking` VALUES("46","95","23",NULL,"0","Lat: 2.248678, Lng: 102.310164","Live GPS tracking - Accuracy: 29.8m",NULL,"in_transit","2026-01-06 21:18:53");
INSERT INTO `distribution_tracking` VALUES("47","95","23",NULL,"0","Lat: 2.248464, Lng: 102.309699","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:18:55");
INSERT INTO `distribution_tracking` VALUES("48","95","23",NULL,"0","Lat: 2.248338, Lng: 102.310087","Live GPS tracking - Accuracy: 12.6m",NULL,"in_transit","2026-01-06 21:18:58");
INSERT INTO `distribution_tracking` VALUES("49","95","23",NULL,"0","Lat: 2.248641, Lng: 102.31029","Live GPS tracking - Accuracy: 12.5m",NULL,"in_transit","2026-01-06 21:19:00");
INSERT INTO `distribution_tracking` VALUES("50","95","23",NULL,"0","Lat: 2.248729, Lng: 102.309901","Live GPS tracking - Accuracy: 11.3m",NULL,"in_transit","2026-01-06 21:19:03");
INSERT INTO `distribution_tracking` VALUES("51","95","23",NULL,"0","Lat: 2.248455, Lng: 102.310076","Live GPS tracking - Accuracy: 11.5m",NULL,"in_transit","2026-01-06 21:19:08");
INSERT INTO `distribution_tracking` VALUES("52","95","23",NULL,"0","Lat: 2.248389, Lng: 102.310273","Live GPS tracking - Accuracy: 27.1m",NULL,"in_transit","2026-01-06 21:19:13");
INSERT INTO `distribution_tracking` VALUES("53","95","23",NULL,"50","On the way - Main Highway","hh","40","in_transit","2026-01-06 21:19:18");
INSERT INTO `distribution_tracking` VALUES("54","95","23",NULL,"0","Lat: 2.248698, Lng: 102.310349","Live GPS tracking - Accuracy: 27.8m",NULL,"in_transit","2026-01-06 21:19:20");
INSERT INTO `distribution_tracking` VALUES("55","95","23",NULL,"0","Lat: 2.248481, Lng: 102.3104","Live GPS tracking - Accuracy: 24.3m",NULL,"in_transit","2026-01-06 21:19:22");
INSERT INTO `distribution_tracking` VALUES("56","95","23",NULL,"0","Lat: 2.248784, Lng: 102.310494","Live GPS tracking - Accuracy: 17.1m",NULL,"in_transit","2026-01-06 21:19:27");
INSERT INTO `distribution_tracking` VALUES("57","95","23",NULL,"0","Lat: 2.248665, Lng: 102.310288","Live GPS tracking - Accuracy: 27.3m",NULL,"in_transit","2026-01-06 21:19:32");
INSERT INTO `distribution_tracking` VALUES("58","95","23",NULL,"0","Lat: 2.249008, Lng: 102.310714","Live GPS tracking - Accuracy: 18.3m",NULL,"in_transit","2026-01-06 21:19:37");
INSERT INTO `distribution_tracking` VALUES("59","95","23",NULL,"0","Lat: 2.248947, Lng: 102.31044","Live GPS tracking - Accuracy: 26.7m",NULL,"in_transit","2026-01-06 21:19:42");
INSERT INTO `distribution_tracking` VALUES("60","95","23",NULL,"0","Lat: 2.248876, Lng: 102.310574","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 21:19:47");
INSERT INTO `distribution_tracking` VALUES("61","95","23",NULL,"0","Lat: 2.248547, Lng: 102.310614","Live GPS tracking - Accuracy: 17m",NULL,"in_transit","2026-01-06 21:19:52");
INSERT INTO `distribution_tracking` VALUES("62","95","23",NULL,"0","Lat: 2.248444, Lng: 102.310207","Live GPS tracking - Accuracy: 24.6m",NULL,"in_transit","2026-01-06 21:19:57");
INSERT INTO `distribution_tracking` VALUES("63","95","23",NULL,"0","Lat: 2.248191, Lng: 102.310271","Live GPS tracking - Accuracy: 14m",NULL,"in_transit","2026-01-06 21:20:02");
INSERT INTO `distribution_tracking` VALUES("64","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:20:05");
INSERT INTO `distribution_tracking` VALUES("65","95","23",NULL,"0","Lat: 2.247702, Lng: 102.310567","Live GPS tracking - Accuracy: 23.9m",NULL,"in_transit","2026-01-06 21:20:07");
INSERT INTO `distribution_tracking` VALUES("66","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:20:08");
INSERT INTO `distribution_tracking` VALUES("67","95","23",NULL,"0","Lat: 2.24806, Lng: 102.310232","Live GPS tracking - Accuracy: 18.5m",NULL,"in_transit","2026-01-06 21:20:12");
INSERT INTO `distribution_tracking` VALUES("68","95","23",NULL,"0","Lat: 2.248166, Lng: 102.310109","Live GPS tracking - Accuracy: 29.1m",NULL,"in_transit","2026-01-06 21:20:17");
INSERT INTO `distribution_tracking` VALUES("69","95","23",NULL,"0","Lat: 2.247751, Lng: 102.31036","Live GPS tracking - Accuracy: 10.6m",NULL,"in_transit","2026-01-06 21:20:22");
INSERT INTO `distribution_tracking` VALUES("70","95","23",NULL,"0","Lat: 2.247956, Lng: 102.310641","Live GPS tracking - Accuracy: 29.4m",NULL,"in_transit","2026-01-06 21:20:27");
INSERT INTO `distribution_tracking` VALUES("71","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:20:31");
INSERT INTO `distribution_tracking` VALUES("72","95","23",NULL,"0","Lat: 2.247547, Lng: 102.310692","Live GPS tracking - Accuracy: 10.3m",NULL,"in_transit","2026-01-06 21:20:32");
INSERT INTO `distribution_tracking` VALUES("73","95","23",NULL,"0","Lat: 2.2532, Lng: 102.3156","Live GPS tracking - Accuracy: 5m",NULL,"in_transit","2026-01-06 21:20:32");
INSERT INTO `distribution_tracking` VALUES("74","95","23",NULL,"0","Lat: 2.247176, Lng: 102.311136","Live GPS tracking - Accuracy: 18.1m",NULL,"in_transit","2026-01-06 21:20:37");
INSERT INTO `distribution_tracking` VALUES("75","95","23",NULL,"0","Lat: 2.24739, Lng: 102.311548","Live GPS tracking - Accuracy: 20.9m",NULL,"in_transit","2026-01-06 21:20:42");
INSERT INTO `distribution_tracking` VALUES("76","95","23",NULL,"0","Lat: 2.247276, Lng: 102.311126","Live GPS tracking - Accuracy: 22.3m",NULL,"in_transit","2026-01-06 21:20:47");
INSERT INTO `distribution_tracking` VALUES("77","95","23",NULL,"0","Lat: 2.24704, Lng: 102.311558","Live GPS tracking - Accuracy: 25.1m",NULL,"in_transit","2026-01-06 21:20:52");
INSERT INTO `distribution_tracking` VALUES("78","95","23",NULL,"0","Lat: 2.24744, Lng: 102.311538","Live GPS tracking - Accuracy: 28m",NULL,"in_transit","2026-01-06 21:20:57");
INSERT INTO `distribution_tracking` VALUES("79","95","23",NULL,"0","Lat: 2.247493, Lng: 102.311128","Live GPS tracking - Accuracy: 19m",NULL,"in_transit","2026-01-06 21:21:02");
INSERT INTO `distribution_tracking` VALUES("80","95","23",NULL,"0","Lat: 2.247521, Lng: 102.311435","Live GPS tracking - Accuracy: 16.5m",NULL,"in_transit","2026-01-06 21:21:07");
INSERT INTO `distribution_tracking` VALUES("81","95","23",NULL,"0","Lat: 2.247474, Lng: 102.311257","Live GPS tracking - Accuracy: 14.7m",NULL,"in_transit","2026-01-06 21:21:12");
INSERT INTO `distribution_tracking` VALUES("82","95","23",NULL,"0","Lat: 2.247509, Lng: 102.311388","Live GPS tracking - Accuracy: 11.3m",NULL,"in_transit","2026-01-06 21:21:17");
INSERT INTO `distribution_tracking` VALUES("83","95","23",NULL,"0","Lat: 2.247368, Lng: 102.311727","Live GPS tracking - Accuracy: 24.5m",NULL,"in_transit","2026-01-06 21:21:22");
INSERT INTO `distribution_tracking` VALUES("84","95","23",NULL,"0","Lat: 2.247414, Lng: 102.31196","Live GPS tracking - Accuracy: 25.5m",NULL,"in_transit","2026-01-06 21:21:27");
INSERT INTO `distribution_tracking` VALUES("85","95","23",NULL,"0","Lat: 2.247281, Lng: 102.31204","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 21:21:32");
INSERT INTO `distribution_tracking` VALUES("86","95","23",NULL,"0","Lat: 2.247677, Lng: 102.311959","Live GPS tracking - Accuracy: 24.7m",NULL,"in_transit","2026-01-06 21:21:37");
INSERT INTO `distribution_tracking` VALUES("87","95","23",NULL,"0","Lat: 2.247325, Lng: 102.312354","Live GPS tracking - Accuracy: 16.4m",NULL,"in_transit","2026-01-06 21:21:42");
INSERT INTO `distribution_tracking` VALUES("88","95","23",NULL,"0","Lat: 2.247019, Lng: 102.312228","Live GPS tracking - Accuracy: 17.5m",NULL,"in_transit","2026-01-06 21:21:47");
INSERT INTO `distribution_tracking` VALUES("89","95","23",NULL,"0","Lat: 2.247138, Lng: 102.312268","Live GPS tracking - Accuracy: 12.8m",NULL,"in_transit","2026-01-06 21:21:52");
INSERT INTO `distribution_tracking` VALUES("90","95","23",NULL,"0","Lat: 2.246883, Lng: 102.312142","Live GPS tracking - Accuracy: 13.2m",NULL,"in_transit","2026-01-06 21:21:57");
INSERT INTO `distribution_tracking` VALUES("91","95","23",NULL,"0","Lat: 2.246806, Lng: 102.312642","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 21:22:02");
INSERT INTO `distribution_tracking` VALUES("92","95","23",NULL,"0","Lat: 2.246828, Lng: 102.312734","Live GPS tracking - Accuracy: 18.9m",NULL,"in_transit","2026-01-06 21:22:07");
INSERT INTO `distribution_tracking` VALUES("93","95","23",NULL,"0","Lat: 2.246927, Lng: 102.312462","Live GPS tracking - Accuracy: 10.6m",NULL,"in_transit","2026-01-06 21:22:12");
INSERT INTO `distribution_tracking` VALUES("94","95","23",NULL,"0","Lat: 2.247377, Lng: 102.312923","Live GPS tracking - Accuracy: 25.5m",NULL,"in_transit","2026-01-06 21:22:17");
INSERT INTO `distribution_tracking` VALUES("95","95","23",NULL,"0","Lat: 2.247405, Lng: 102.312497","Live GPS tracking - Accuracy: 11.7m",NULL,"in_transit","2026-01-06 21:22:22");
INSERT INTO `distribution_tracking` VALUES("96","95","23",NULL,"0","Lat: 2.24773, Lng: 102.312257","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:22:27");
INSERT INTO `distribution_tracking` VALUES("97","95","23",NULL,"0","Lat: 2.247803, Lng: 102.312456","Live GPS tracking - Accuracy: 23.1m",NULL,"in_transit","2026-01-06 21:22:32");
INSERT INTO `distribution_tracking` VALUES("98","95","23",NULL,"0","Lat: 2.247672, Lng: 102.311973","Live GPS tracking - Accuracy: 21.3m",NULL,"in_transit","2026-01-06 21:22:37");
INSERT INTO `distribution_tracking` VALUES("99","95","23",NULL,"0","Lat: 2.247374, Lng: 102.311844","Live GPS tracking - Accuracy: 25.1m",NULL,"in_transit","2026-01-06 21:22:42");
INSERT INTO `distribution_tracking` VALUES("100","95","23",NULL,"0","Lat: 2.247253, Lng: 102.311545","Live GPS tracking - Accuracy: 10.2m",NULL,"in_transit","2026-01-06 21:22:47");
INSERT INTO `distribution_tracking` VALUES("101","95","23",NULL,"0","Lat: 2.246799, Lng: 102.311692","Live GPS tracking - Accuracy: 21.2m",NULL,"in_transit","2026-01-06 21:22:52");
INSERT INTO `distribution_tracking` VALUES("102","95","23",NULL,"0","Lat: 2.246316, Lng: 102.311426","Live GPS tracking - Accuracy: 25.4m",NULL,"in_transit","2026-01-06 21:22:57");
INSERT INTO `distribution_tracking` VALUES("103","95","23",NULL,"0","Lat: 2.246502, Lng: 102.311119","Live GPS tracking - Accuracy: 10.6m",NULL,"in_transit","2026-01-06 21:23:06");
INSERT INTO `distribution_tracking` VALUES("104","95","23",NULL,"0","Lat: 2.246738, Lng: 102.311196","Live GPS tracking - Accuracy: 20.2m",NULL,"in_transit","2026-01-06 21:23:07");
INSERT INTO `distribution_tracking` VALUES("105","95","23",NULL,"0","Lat: 2.246512, Lng: 102.311312","Live GPS tracking - Accuracy: 15.2m",NULL,"in_transit","2026-01-06 21:23:12");
INSERT INTO `distribution_tracking` VALUES("106","95","23",NULL,"0","Lat: 2.246874, Lng: 102.311157","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 21:23:17");
INSERT INTO `distribution_tracking` VALUES("107","95","23",NULL,"0","Lat: 2.247312, Lng: 102.311321","Live GPS tracking - Accuracy: 13.9m",NULL,"in_transit","2026-01-06 21:23:22");
INSERT INTO `distribution_tracking` VALUES("108","95","23",NULL,"0","Lat: 2.247523, Lng: 102.311523","Live GPS tracking - Accuracy: 15.7m",NULL,"in_transit","2026-01-06 21:23:27");
INSERT INTO `distribution_tracking` VALUES("109","95","23",NULL,"0","Lat: 2.248015, Lng: 102.311316","Live GPS tracking - Accuracy: 12.1m",NULL,"in_transit","2026-01-06 21:23:32");
INSERT INTO `distribution_tracking` VALUES("110","95","23",NULL,"0","Lat: 2.247813, Lng: 102.311501","Live GPS tracking - Accuracy: 28.7m",NULL,"in_transit","2026-01-06 21:23:37");
INSERT INTO `distribution_tracking` VALUES("111","95","23",NULL,"0","Lat: 2.247901, Lng: 102.311979","Live GPS tracking - Accuracy: 15.9m",NULL,"in_transit","2026-01-06 21:23:42");
INSERT INTO `distribution_tracking` VALUES("112","95","23",NULL,"0","Lat: 2.247854, Lng: 102.311871","Live GPS tracking - Accuracy: 14m",NULL,"in_transit","2026-01-06 21:23:47");
INSERT INTO `distribution_tracking` VALUES("113","95","23",NULL,"0","Lat: 2.248322, Lng: 102.312335","Live GPS tracking - Accuracy: 17.7m",NULL,"in_transit","2026-01-06 21:23:52");
INSERT INTO `distribution_tracking` VALUES("114","95","23",NULL,"0","Lat: 2.247881, Lng: 102.312749","Live GPS tracking - Accuracy: 15m",NULL,"in_transit","2026-01-06 21:23:57");
INSERT INTO `distribution_tracking` VALUES("115","95","23",NULL,"0","Lat: 2.247773, Lng: 102.31238","Live GPS tracking - Accuracy: 26.8m",NULL,"in_transit","2026-01-06 21:24:02");
INSERT INTO `distribution_tracking` VALUES("116","95","23",NULL,"0","Lat: 2.247909, Lng: 102.312774","Live GPS tracking - Accuracy: 14.7m",NULL,"in_transit","2026-01-06 21:24:07");
INSERT INTO `distribution_tracking` VALUES("117","95","23",NULL,"0","Lat: 2.24774, Lng: 102.312622","Live GPS tracking - Accuracy: 26.5m",NULL,"in_transit","2026-01-06 21:24:12");
INSERT INTO `distribution_tracking` VALUES("118","95","23",NULL,"0","Lat: 2.247756, Lng: 102.312373","Live GPS tracking - Accuracy: 18.1m",NULL,"in_transit","2026-01-06 21:24:17");
INSERT INTO `distribution_tracking` VALUES("119","95","23",NULL,"0","Lat: 2.24763, Lng: 102.312279","Live GPS tracking - Accuracy: 22.9m",NULL,"in_transit","2026-01-06 21:24:22");
INSERT INTO `distribution_tracking` VALUES("120","95","23",NULL,"0","Lat: 2.247701, Lng: 102.312618","Live GPS tracking - Accuracy: 10.5m",NULL,"in_transit","2026-01-06 21:24:27");
INSERT INTO `distribution_tracking` VALUES("121","95","23",NULL,"0","Lat: 2.24738, Lng: 102.312474","Live GPS tracking - Accuracy: 27.5m",NULL,"in_transit","2026-01-06 21:24:32");
INSERT INTO `distribution_tracking` VALUES("122","95","23",NULL,"0","Lat: 2.247848, Lng: 102.311998","Live GPS tracking - Accuracy: 20.8m",NULL,"in_transit","2026-01-06 21:24:37");
INSERT INTO `distribution_tracking` VALUES("123","95","23",NULL,"0","Lat: 2.247607, Lng: 102.312402","Live GPS tracking - Accuracy: 21m",NULL,"in_transit","2026-01-06 21:24:42");
INSERT INTO `distribution_tracking` VALUES("124","95","23",NULL,"0","Lat: 2.247492, Lng: 102.312457","Live GPS tracking - Accuracy: 21.9m",NULL,"in_transit","2026-01-06 21:24:47");
INSERT INTO `distribution_tracking` VALUES("125","95","23",NULL,"0","Lat: 2.247834, Lng: 102.312927","Live GPS tracking - Accuracy: 11.9m",NULL,"in_transit","2026-01-06 21:24:52");
INSERT INTO `distribution_tracking` VALUES("126","95","23",NULL,"0","Lat: 2.248206, Lng: 102.313048","Live GPS tracking - Accuracy: 25.6m",NULL,"in_transit","2026-01-06 21:24:57");
INSERT INTO `distribution_tracking` VALUES("127","95","23",NULL,"0","Lat: 2.248275, Lng: 102.31349","Live GPS tracking - Accuracy: 29.6m",NULL,"in_transit","2026-01-06 21:25:06");
INSERT INTO `distribution_tracking` VALUES("128","95","23",NULL,"0","Lat: 2.248327, Lng: 102.313613","Live GPS tracking - Accuracy: 19.8m",NULL,"in_transit","2026-01-06 21:25:13");
INSERT INTO `distribution_tracking` VALUES("129","95","23",NULL,"0","Lat: 2.248093, Lng: 102.314095","Live GPS tracking - Accuracy: 22.7m",NULL,"in_transit","2026-01-06 21:25:17");
INSERT INTO `distribution_tracking` VALUES("130","95","23",NULL,"0","Lat: 2.247664, Lng: 102.314334","Live GPS tracking - Accuracy: 27.7m",NULL,"in_transit","2026-01-06 21:25:22");
INSERT INTO `distribution_tracking` VALUES("131","95","23",NULL,"0","Lat: 2.248636, Lng: 102.310269","Live GPS tracking - Accuracy: 21m",NULL,"in_transit","2026-01-06 21:25:25");
INSERT INTO `distribution_tracking` VALUES("132","95","23",NULL,"0","Lat: 2.248803, Lng: 102.30986","Live GPS tracking - Accuracy: 11.9m",NULL,"in_transit","2026-01-06 21:25:27");
INSERT INTO `distribution_tracking` VALUES("133","95","23",NULL,"0","Lat: 2.248495, Lng: 102.311049","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:25:29");
INSERT INTO `distribution_tracking` VALUES("134","95","23",NULL,"0","Lat: 2.248156, Lng: 102.311386","Live GPS tracking - Accuracy: 15.3m",NULL,"in_transit","2026-01-06 21:25:30");
INSERT INTO `distribution_tracking` VALUES("135","95","23",NULL,"0","Lat: 2.248014, Lng: 102.311347","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:25:32");
INSERT INTO `distribution_tracking` VALUES("136","95","23",NULL,"0","Lat: 2.248454, Lng: 102.31143","Live GPS tracking - Accuracy: 15.6m",NULL,"in_transit","2026-01-06 21:25:35");
INSERT INTO `distribution_tracking` VALUES("137","95","23",NULL,"0","Lat: 2.248168, Lng: 102.311836","Live GPS tracking - Accuracy: 17.3m",NULL,"in_transit","2026-01-06 21:25:36");
INSERT INTO `distribution_tracking` VALUES("138","95","23",NULL,"0","Lat: 2.248482, Lng: 102.311955","Live GPS tracking - Accuracy: 10.8m",NULL,"in_transit","2026-01-06 21:25:37");
INSERT INTO `distribution_tracking` VALUES("139","95","23",NULL,"0","Lat: 2.248633, Lng: 102.311492","Live GPS tracking - Accuracy: 14.2m",NULL,"in_transit","2026-01-06 21:25:40");
INSERT INTO `distribution_tracking` VALUES("140","95","23",NULL,"0","Lat: 2.24913, Lng: 102.311182","Live GPS tracking - Accuracy: 23.9m",NULL,"in_transit","2026-01-06 21:25:40");
INSERT INTO `distribution_tracking` VALUES("141","95","23",NULL,"0","Lat: 2.248769, Lng: 102.310758","Live GPS tracking - Accuracy: 21.5m",NULL,"in_transit","2026-01-06 21:25:42");
INSERT INTO `distribution_tracking` VALUES("142","95","23",NULL,"0","Lat: 2.248363, Lng: 102.311109","Live GPS tracking - Accuracy: 19.1m",NULL,"in_transit","2026-01-06 21:25:44");
INSERT INTO `distribution_tracking` VALUES("143","95","23",NULL,"0","Lat: 2.248716, Lng: 102.311086","Live GPS tracking - Accuracy: 24.1m",NULL,"in_transit","2026-01-06 21:25:45");
INSERT INTO `distribution_tracking` VALUES("144","95","23",NULL,"0","Lat: 2.248786, Lng: 102.311438","Live GPS tracking - Accuracy: 28.7m",NULL,"in_transit","2026-01-06 21:25:47");
INSERT INTO `distribution_tracking` VALUES("145","95","23",NULL,"0","Lat: 2.24886, Lng: 102.311823","Live GPS tracking - Accuracy: 15.5m",NULL,"in_transit","2026-01-06 21:25:49");
INSERT INTO `distribution_tracking` VALUES("146","95","23",NULL,"0","Lat: 2.248462, Lng: 102.312225","Live GPS tracking - Accuracy: 24.8m",NULL,"in_transit","2026-01-06 21:25:50");
INSERT INTO `distribution_tracking` VALUES("147","95","23",NULL,"0","Lat: 2.248413, Lng: 102.312324","Live GPS tracking - Accuracy: 23.6m",NULL,"in_transit","2026-01-06 21:25:52");
INSERT INTO `distribution_tracking` VALUES("148","95","23",NULL,"0","Lat: 2.248331, Lng: 102.312466","Live GPS tracking - Accuracy: 28.6m",NULL,"in_transit","2026-01-06 21:25:54");
INSERT INTO `distribution_tracking` VALUES("149","95","23",NULL,"0","Lat: 2.248192, Lng: 102.312896","Live GPS tracking - Accuracy: 13.4m",NULL,"in_transit","2026-01-06 21:25:55");
INSERT INTO `distribution_tracking` VALUES("150","95","23",NULL,"0","Lat: 2.247697, Lng: 102.312444","Live GPS tracking - Accuracy: 22m",NULL,"in_transit","2026-01-06 21:25:57");
INSERT INTO `distribution_tracking` VALUES("151","95","23",NULL,"0","Lat: 2.248026, Lng: 102.312389","Live GPS tracking - Accuracy: 24.8m",NULL,"in_transit","2026-01-06 21:25:59");
INSERT INTO `distribution_tracking` VALUES("152","95","23",NULL,"0","Lat: 2.248143, Lng: 102.312569","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 21:26:00");
INSERT INTO `distribution_tracking` VALUES("153","95","23",NULL,"0","Lat: 2.248334, Lng: 102.31223","Live GPS tracking - Accuracy: 13.2m",NULL,"in_transit","2026-01-06 21:26:02");
INSERT INTO `distribution_tracking` VALUES("154","95","23",NULL,"0","Lat: 2.248744, Lng: 102.31245","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:26:04");
INSERT INTO `distribution_tracking` VALUES("155","95","23",NULL,"0","Lat: 2.248357, Lng: 102.312264","Live GPS tracking - Accuracy: 23.3m",NULL,"in_transit","2026-01-06 21:26:05");
INSERT INTO `distribution_tracking` VALUES("156","95","23",NULL,"0","Lat: 2.247978, Lng: 102.312159","Live GPS tracking - Accuracy: 10.4m",NULL,"in_transit","2026-01-06 21:26:07");
INSERT INTO `distribution_tracking` VALUES("157","95","23",NULL,"0","Lat: 2.248052, Lng: 102.31262","Live GPS tracking - Accuracy: 25.9m",NULL,"in_transit","2026-01-06 21:26:09");
INSERT INTO `distribution_tracking` VALUES("158","95","23",NULL,"0","Lat: 2.248454, Lng: 102.312196","Live GPS tracking - Accuracy: 17.7m",NULL,"in_transit","2026-01-06 21:26:10");
INSERT INTO `distribution_tracking` VALUES("159","95","23",NULL,"0","Lat: 2.24855, Lng: 102.312422","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 21:26:12");
INSERT INTO `distribution_tracking` VALUES("160","95","23",NULL,"0","Lat: 2.248121, Lng: 102.312622","Live GPS tracking - Accuracy: 17.7m",NULL,"in_transit","2026-01-06 21:26:14");
INSERT INTO `distribution_tracking` VALUES("161","95","23",NULL,"0","Lat: 2.248558, Lng: 102.312796","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:26:15");
INSERT INTO `distribution_tracking` VALUES("162","95","23",NULL,"0","Lat: 2.248752, Lng: 102.312704","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 21:26:17");
INSERT INTO `distribution_tracking` VALUES("163","95","23",NULL,"0","Lat: 2.248477, Lng: 102.312383","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:26:19");
INSERT INTO `distribution_tracking` VALUES("164","95","23",NULL,"0","Lat: 2.248263, Lng: 102.311947","Live GPS tracking - Accuracy: 26.3m",NULL,"in_transit","2026-01-06 21:26:20");
INSERT INTO `distribution_tracking` VALUES("165","95","23",NULL,"0","Lat: 2.24858, Lng: 102.311834","Live GPS tracking - Accuracy: 17.8m",NULL,"in_transit","2026-01-06 21:26:22");
INSERT INTO `distribution_tracking` VALUES("166","95","23",NULL,"0","Lat: 2.248692, Lng: 102.312152","Live GPS tracking - Accuracy: 25.6m",NULL,"in_transit","2026-01-06 21:26:24");
INSERT INTO `distribution_tracking` VALUES("167","95","23",NULL,"0","Lat: 2.248796, Lng: 102.312423","Live GPS tracking - Accuracy: 21.4m",NULL,"in_transit","2026-01-06 21:26:25");
INSERT INTO `distribution_tracking` VALUES("168","95","23",NULL,"0","Lat: 2.24912, Lng: 102.31286","Live GPS tracking - Accuracy: 17.2m",NULL,"in_transit","2026-01-06 21:26:27");
INSERT INTO `distribution_tracking` VALUES("169","95","23",NULL,"0","Lat: 2.249203, Lng: 102.313155","Live GPS tracking - Accuracy: 18m",NULL,"in_transit","2026-01-06 21:26:29");
INSERT INTO `distribution_tracking` VALUES("170","95","23",NULL,"0","Lat: 2.249457, Lng: 102.312894","Live GPS tracking - Accuracy: 29.8m",NULL,"in_transit","2026-01-06 21:26:30");
INSERT INTO `distribution_tracking` VALUES("171","95","23",NULL,"0","Lat: 2.249405, Lng: 102.312836","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:26:32");
INSERT INTO `distribution_tracking` VALUES("172","95","23",NULL,"0","Lat: 2.249675, Lng: 102.312988","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 21:26:34");
INSERT INTO `distribution_tracking` VALUES("173","95","23",NULL,"0","Lat: 2.249955, Lng: 102.312911","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 21:26:35");
INSERT INTO `distribution_tracking` VALUES("174","95","23",NULL,"0","Lat: 2.250213, Lng: 102.312578","Live GPS tracking - Accuracy: 26.1m",NULL,"in_transit","2026-01-06 21:26:37");
INSERT INTO `distribution_tracking` VALUES("175","95","23",NULL,"0","Lat: 2.250172, Lng: 102.312161","Live GPS tracking - Accuracy: 14.1m",NULL,"in_transit","2026-01-06 21:26:39");
INSERT INTO `distribution_tracking` VALUES("176","95","23",NULL,"0","Lat: 2.250033, Lng: 102.312604","Live GPS tracking - Accuracy: 23.4m",NULL,"in_transit","2026-01-06 21:26:40");
INSERT INTO `distribution_tracking` VALUES("177","95","23",NULL,"0","Lat: 2.249648, Lng: 102.312235","Live GPS tracking - Accuracy: 27.4m",NULL,"in_transit","2026-01-06 21:26:42");
INSERT INTO `distribution_tracking` VALUES("178","95","23",NULL,"0","Lat: 2.25003, Lng: 102.312304","Live GPS tracking - Accuracy: 22.5m",NULL,"in_transit","2026-01-06 21:26:44");
INSERT INTO `distribution_tracking` VALUES("179","95","23",NULL,"0","Lat: 2.249667, Lng: 102.311843","Live GPS tracking - Accuracy: 12.6m",NULL,"in_transit","2026-01-06 21:26:45");
INSERT INTO `distribution_tracking` VALUES("180","95","23",NULL,"0","Lat: 2.24925, Lng: 102.311394","Live GPS tracking - Accuracy: 13.1m",NULL,"in_transit","2026-01-06 21:26:47");
INSERT INTO `distribution_tracking` VALUES("181","95","23",NULL,"0","Lat: 2.249081, Lng: 102.31133","Live GPS tracking - Accuracy: 15.4m",NULL,"in_transit","2026-01-06 21:26:49");
INSERT INTO `distribution_tracking` VALUES("182","95","23",NULL,"0","Lat: 2.249441, Lng: 102.311248","Live GPS tracking - Accuracy: 28.2m",NULL,"in_transit","2026-01-06 21:26:50");
INSERT INTO `distribution_tracking` VALUES("183","95","23",NULL,"0","Lat: 2.249261, Lng: 102.310874","Live GPS tracking - Accuracy: 25.1m",NULL,"in_transit","2026-01-06 21:26:52");
INSERT INTO `distribution_tracking` VALUES("184","95","23",NULL,"0","Lat: 2.249055, Lng: 102.310776","Live GPS tracking - Accuracy: 14.4m",NULL,"in_transit","2026-01-06 21:26:54");
INSERT INTO `distribution_tracking` VALUES("185","95","23",NULL,"0","Lat: 2.249533, Lng: 102.310867","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 21:26:55");
INSERT INTO `distribution_tracking` VALUES("186","95","23",NULL,"0","Lat: 2.249826, Lng: 102.311058","Live GPS tracking - Accuracy: 21.8m",NULL,"in_transit","2026-01-06 21:26:57");
INSERT INTO `distribution_tracking` VALUES("187","95","23",NULL,"0","Lat: 2.249567, Lng: 102.31126","Live GPS tracking - Accuracy: 28.5m",NULL,"in_transit","2026-01-06 21:26:59");
INSERT INTO `distribution_tracking` VALUES("188","95","23",NULL,"0","Lat: 2.249843, Lng: 102.311284","Live GPS tracking - Accuracy: 28.7m",NULL,"in_transit","2026-01-06 21:27:00");
INSERT INTO `distribution_tracking` VALUES("189","95","23",NULL,"0","Lat: 2.249807, Lng: 102.310933","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:27:02");
INSERT INTO `distribution_tracking` VALUES("190","95","23",NULL,"0","Lat: 2.249433, Lng: 102.310529","Live GPS tracking - Accuracy: 26m",NULL,"in_transit","2026-01-06 21:27:05");
INSERT INTO `distribution_tracking` VALUES("191","95","23",NULL,"0","Lat: 2.249046, Lng: 102.310288","Live GPS tracking - Accuracy: 11.8m",NULL,"in_transit","2026-01-06 21:27:06");
INSERT INTO `distribution_tracking` VALUES("192","95","23",NULL,"0","Lat: 2.249121, Lng: 102.310231","Live GPS tracking - Accuracy: 26.4m",NULL,"in_transit","2026-01-06 21:27:07");
INSERT INTO `distribution_tracking` VALUES("193","95","23",NULL,"0","Lat: 2.249184, Lng: 102.30995","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 21:27:10");
INSERT INTO `distribution_tracking` VALUES("194","95","23",NULL,"0","Lat: 2.248793, Lng: 102.310179","Live GPS tracking - Accuracy: 10.1m",NULL,"in_transit","2026-01-06 21:27:11");
INSERT INTO `distribution_tracking` VALUES("195","95","23",NULL,"0","Lat: 2.248842, Lng: 102.310342","Live GPS tracking - Accuracy: 29.8m",NULL,"in_transit","2026-01-06 21:27:12");
INSERT INTO `distribution_tracking` VALUES("196","95","23",NULL,"0","Lat: 2.248756, Lng: 102.310352","Live GPS tracking - Accuracy: 21.2m",NULL,"in_transit","2026-01-06 21:27:15");
INSERT INTO `distribution_tracking` VALUES("197","95","23",NULL,"0","Lat: 2.248266, Lng: 102.310707","Live GPS tracking - Accuracy: 11.1m",NULL,"in_transit","2026-01-06 21:27:16");
INSERT INTO `distribution_tracking` VALUES("198","95","23",NULL,"0","Lat: 2.247777, Lng: 102.310587","Live GPS tracking - Accuracy: 13.7m",NULL,"in_transit","2026-01-06 21:27:17");
INSERT INTO `distribution_tracking` VALUES("199","95","23",NULL,"0","Lat: 2.248182, Lng: 102.31037","Live GPS tracking - Accuracy: 29m",NULL,"in_transit","2026-01-06 21:27:19");
INSERT INTO `distribution_tracking` VALUES("200","96","23",NULL,"0","Lat: 2.209034, Lng: 102.290465","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 21:51:39");
INSERT INTO `distribution_tracking` VALUES("201","96","23",NULL,"0","Lat: 2.209311, Lng: 102.29083","Live GPS tracking - Accuracy: 29.4m",NULL,"in_transit","2026-01-06 21:51:44");
INSERT INTO `distribution_tracking` VALUES("202","96","23",NULL,"0","Lat: 2.209808, Lng: 102.290481","Live GPS tracking - Accuracy: 18.5m",NULL,"in_transit","2026-01-06 21:51:50");
INSERT INTO `distribution_tracking` VALUES("203","96","23",NULL,"0","Lat: 2.209526, Lng: 102.290742","Live GPS tracking - Accuracy: 19.2m",NULL,"in_transit","2026-01-06 21:51:55");
INSERT INTO `distribution_tracking` VALUES("204","96","23",NULL,"0","Lat: 2.209171, Lng: 102.29061","Live GPS tracking - Accuracy: 14.4m",NULL,"in_transit","2026-01-06 21:52:00");
INSERT INTO `distribution_tracking` VALUES("205","96","23",NULL,"0","Lat: 2.208835, Lng: 102.29104","Live GPS tracking - Accuracy: 10.3m",NULL,"in_transit","2026-01-06 21:52:04");
INSERT INTO `distribution_tracking` VALUES("206","95","23",NULL,"0","Lat: 2.24791, Lng: 102.310702","Live GPS tracking - Accuracy: 11.5m",NULL,"in_transit","2026-01-06 21:52:36");
INSERT INTO `distribution_tracking` VALUES("207","95","23",NULL,"0","Lat: 2.248309, Lng: 102.310878","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:52:41");
INSERT INTO `distribution_tracking` VALUES("208","95","23",NULL,"0","Lat: 2.247908, Lng: 102.310424","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 21:52:46");
INSERT INTO `distribution_tracking` VALUES("209","95","23",NULL,"0","Lat: 2.247968, Lng: 102.310305","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 21:52:51");
INSERT INTO `distribution_tracking` VALUES("210","95","23",NULL,"0","Lat: 2.247547, Lng: 102.309909","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:52:56");
INSERT INTO `distribution_tracking` VALUES("211","95","23",NULL,"0","Lat: 2.247272, Lng: 102.309908","Live GPS tracking - Accuracy: 29.6m",NULL,"in_transit","2026-01-06 21:53:01");
INSERT INTO `distribution_tracking` VALUES("212","95","23",NULL,"0","Lat: 2.246855, Lng: 102.310209","Live GPS tracking - Accuracy: 26.4m",NULL,"in_transit","2026-01-06 21:53:06");
INSERT INTO `distribution_tracking` VALUES("213","95","23",NULL,"0","Lat: 2.246368, Lng: 102.310526","Live GPS tracking - Accuracy: 14.6m",NULL,"in_transit","2026-01-06 21:53:11");
INSERT INTO `distribution_tracking` VALUES("214","95","23",NULL,"0","Lat: 2.246419, Lng: 102.310807","Live GPS tracking - Accuracy: 27.5m",NULL,"in_transit","2026-01-06 21:53:16");
INSERT INTO `distribution_tracking` VALUES("215","95","23",NULL,"0","Lat: 2.246152, Lng: 102.311173","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 21:53:21");
INSERT INTO `distribution_tracking` VALUES("216","95","23",NULL,"0","Lat: 2.24606, Lng: 102.310784","Live GPS tracking - Accuracy: 22.1m",NULL,"in_transit","2026-01-06 21:53:26");
INSERT INTO `distribution_tracking` VALUES("217","95","23",NULL,"0","Lat: 2.245952, Lng: 102.310804","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 21:53:31");
INSERT INTO `distribution_tracking` VALUES("218","95","23",NULL,"0","Lat: 2.246241, Lng: 102.310594","Live GPS tracking - Accuracy: 14.1m",NULL,"in_transit","2026-01-06 21:53:36");
INSERT INTO `distribution_tracking` VALUES("219","95","23",NULL,"0","Lat: 2.246116, Lng: 102.311019","Live GPS tracking - Accuracy: 14.8m",NULL,"in_transit","2026-01-06 21:53:41");
INSERT INTO `distribution_tracking` VALUES("220","95","23",NULL,"0","Lat: 2.246435, Lng: 102.310622","Live GPS tracking - Accuracy: 18.2m",NULL,"in_transit","2026-01-06 21:53:46");
INSERT INTO `distribution_tracking` VALUES("221","95","23",NULL,"0","Lat: 2.246764, Lng: 102.310518","Live GPS tracking - Accuracy: 16.7m",NULL,"in_transit","2026-01-06 21:53:51");
INSERT INTO `distribution_tracking` VALUES("222","95","23",NULL,"0","Lat: 2.247008, Lng: 102.310125","Live GPS tracking - Accuracy: 26.9m",NULL,"in_transit","2026-01-06 21:53:56");
INSERT INTO `distribution_tracking` VALUES("223","95","23",NULL,"0","Lat: 2.247317, Lng: 102.309786","Live GPS tracking - Accuracy: 20.8m",NULL,"in_transit","2026-01-06 21:54:01");
INSERT INTO `distribution_tracking` VALUES("224","95","23",NULL,"0","Lat: 2.247095, Lng: 102.309884","Live GPS tracking - Accuracy: 10.3m",NULL,"in_transit","2026-01-06 21:54:06");
INSERT INTO `distribution_tracking` VALUES("225","95","23",NULL,"0","Lat: 2.246885, Lng: 102.309938","Live GPS tracking - Accuracy: 19.7m",NULL,"in_transit","2026-01-06 21:54:11");
INSERT INTO `distribution_tracking` VALUES("226","95","23",NULL,"0","Lat: 2.246752, Lng: 102.310021","Live GPS tracking - Accuracy: 24m",NULL,"in_transit","2026-01-06 21:54:16");
INSERT INTO `distribution_tracking` VALUES("227","95","23",NULL,"0","Lat: 2.246416, Lng: 102.309778","Live GPS tracking - Accuracy: 25.7m",NULL,"in_transit","2026-01-06 21:54:21");
INSERT INTO `distribution_tracking` VALUES("228","95","23",NULL,"0","Lat: 2.246117, Lng: 102.310266","Live GPS tracking - Accuracy: 18.8m",NULL,"in_transit","2026-01-06 21:54:26");
INSERT INTO `distribution_tracking` VALUES("229","95","23",NULL,"0","Lat: 2.248689, Lng: 102.310227","Live GPS tracking - Accuracy: 26.8m",NULL,"in_transit","2026-01-06 21:56:07");
INSERT INTO `distribution_tracking` VALUES("230","95","23",NULL,"50","Stopped for traffic","","","departed","2026-01-06 21:56:12");
INSERT INTO `distribution_tracking` VALUES("231","95","23",NULL,"0","Lat: 2.248731, Lng: 102.310384","Live GPS tracking - Accuracy: 29.5m",NULL,"in_transit","2026-01-06 21:56:17");
INSERT INTO `distribution_tracking` VALUES("232","95","23",NULL,"0","Lat: 2.248288, Lng: 102.310532","Live GPS tracking - Accuracy: 22.3m",NULL,"in_transit","2026-01-06 21:56:19");
INSERT INTO `distribution_tracking` VALUES("233","95","23",NULL,"0","Lat: 2.248519, Lng: 102.310608","Live GPS tracking - Accuracy: 15.7m",NULL,"in_transit","2026-01-06 21:56:24");
INSERT INTO `distribution_tracking` VALUES("234","95","23",NULL,"50","Stopped for traffic","","","delayed","2026-01-06 21:56:28");
INSERT INTO `distribution_tracking` VALUES("235","95","23",NULL,"0","Lat: 2.248896, Lng: 102.310898","Live GPS tracking - Accuracy: 29.2m",NULL,"in_transit","2026-01-06 21:56:31");
INSERT INTO `distribution_tracking` VALUES("236","95","23",NULL,"0","Lat: 2.248391, Lng: 102.310567","Live GPS tracking - Accuracy: 24.3m",NULL,"in_transit","2026-01-06 21:56:33");
INSERT INTO `distribution_tracking` VALUES("237","95","23",NULL,"0","Lat: 2.248315, Lng: 102.310918","Live GPS tracking - Accuracy: 23.8m",NULL,"in_transit","2026-01-06 21:56:38");
INSERT INTO `distribution_tracking` VALUES("238","95","23",NULL,"0","Lat: 2.248136, Lng: 102.310929","Live GPS tracking - Accuracy: 17.5m",NULL,"in_transit","2026-01-06 21:56:43");
INSERT INTO `distribution_tracking` VALUES("239","95","23",NULL,"0","Lat: 2.248039, Lng: 102.311255","Live GPS tracking - Accuracy: 18.1m",NULL,"in_transit","2026-01-06 21:56:48");
INSERT INTO `distribution_tracking` VALUES("240","95","23",NULL,"0","Lat: 2.248376, Lng: 102.311317","Live GPS tracking - Accuracy: 12.6m",NULL,"in_transit","2026-01-06 21:56:53");
INSERT INTO `distribution_tracking` VALUES("241","95","23",NULL,"0","Lat: 2.24795, Lng: 102.311483","Live GPS tracking - Accuracy: 29.2m",NULL,"in_transit","2026-01-06 21:56:58");
INSERT INTO `distribution_tracking` VALUES("242","95","23",NULL,"0","Lat: 2.248381, Lng: 102.311973","Live GPS tracking - Accuracy: 11.3m",NULL,"in_transit","2026-01-06 21:57:03");
INSERT INTO `distribution_tracking` VALUES("243","95","23",NULL,"0","Lat: 2.248336, Lng: 102.312141","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 21:57:08");
INSERT INTO `distribution_tracking` VALUES("244","95","23",NULL,"0","Lat: 2.248502, Lng: 102.311941","Live GPS tracking - Accuracy: 25.6m",NULL,"in_transit","2026-01-06 21:57:13");
INSERT INTO `distribution_tracking` VALUES("245","95","23",NULL,"0","Lat: 2.248752, Lng: 102.311553","Live GPS tracking - Accuracy: 27.6m",NULL,"in_transit","2026-01-06 21:57:18");
INSERT INTO `distribution_tracking` VALUES("246","95","23",NULL,"0","Lat: 2.249137, Lng: 102.311268","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:57:23");
INSERT INTO `distribution_tracking` VALUES("247","95","23",NULL,"0","Lat: 2.248982, Lng: 102.311121","Live GPS tracking - Accuracy: 27.8m",NULL,"in_transit","2026-01-06 21:57:28");
INSERT INTO `distribution_tracking` VALUES("248","95","23",NULL,"0","Lat: 2.248868, Lng: 102.311341","Live GPS tracking - Accuracy: 25m",NULL,"in_transit","2026-01-06 21:57:33");
INSERT INTO `distribution_tracking` VALUES("249","95","23",NULL,"0","Lat: 2.248994, Lng: 102.31128","Live GPS tracking - Accuracy: 19.6m",NULL,"in_transit","2026-01-06 21:58:06");
INSERT INTO `distribution_tracking` VALUES("250","95","23",NULL,"0","Lat: 2.249064, Lng: 102.311427","Live GPS tracking - Accuracy: 15.5m",NULL,"in_transit","2026-01-06 21:59:06");
INSERT INTO `distribution_tracking` VALUES("251","95","23",NULL,"0","Lat: 2.249195, Lng: 102.311542","Live GPS tracking - Accuracy: 20.5m",NULL,"in_transit","2026-01-06 21:59:18");
INSERT INTO `distribution_tracking` VALUES("252","95","23",NULL,"0","Lat: 2.249024, Lng: 102.311069","Live GPS tracking - Accuracy: 29.2m",NULL,"in_transit","2026-01-06 21:59:23");
INSERT INTO `distribution_tracking` VALUES("253","95","23",NULL,"0","Lat: 2.24927, Lng: 102.310603","Live GPS tracking - Accuracy: 30m",NULL,"in_transit","2026-01-06 21:59:28");
INSERT INTO `distribution_tracking` VALUES("254","95","23",NULL,"0","Lat: 2.249019, Lng: 102.310169","Live GPS tracking - Accuracy: 15.3m",NULL,"in_transit","2026-01-06 21:59:33");
INSERT INTO `distribution_tracking` VALUES("255","95","23",NULL,"0","Lat: 2.249156, Lng: 102.310345","Live GPS tracking - Accuracy: 29.5m",NULL,"in_transit","2026-01-06 21:59:38");
INSERT INTO `distribution_tracking` VALUES("256","95","23",NULL,"0","Lat: 2.248989, Lng: 102.310179","Live GPS tracking - Accuracy: 14m",NULL,"in_transit","2026-01-06 21:59:43");
INSERT INTO `distribution_tracking` VALUES("257","95","23",NULL,"0","Lat: 2.24905, Lng: 102.310549","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 21:59:48");
INSERT INTO `distribution_tracking` VALUES("258","95","23",NULL,"0","Lat: 2.249454, Lng: 102.310226","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 21:59:53");
INSERT INTO `distribution_tracking` VALUES("259","95","23",NULL,"0","Lat: 2.249361, Lng: 102.310031","Live GPS tracking - Accuracy: 19.9m",NULL,"in_transit","2026-01-06 21:59:58");
INSERT INTO `distribution_tracking` VALUES("260","95","23",NULL,"0","Lat: 2.249278, Lng: 102.310439","Live GPS tracking - Accuracy: 24.5m",NULL,"in_transit","2026-01-06 22:00:03");
INSERT INTO `distribution_tracking` VALUES("261","95","23",NULL,"0","Lat: 2.249234, Lng: 102.31035","Live GPS tracking - Accuracy: 16.9m",NULL,"in_transit","2026-01-06 22:00:08");
INSERT INTO `distribution_tracking` VALUES("262","95","23",NULL,"0","Lat: 2.248151, Lng: 102.31099","Live GPS tracking - Accuracy: 20.6m",NULL,"in_transit","2026-01-06 22:00:09");
INSERT INTO `distribution_tracking` VALUES("263","95","23",NULL,"0","Lat: 2.248126, Lng: 102.310712","Live GPS tracking - Accuracy: 21.6m",NULL,"in_transit","2026-01-06 22:00:13");
INSERT INTO `distribution_tracking` VALUES("264","95","23",NULL,"0","Lat: 2.24777, Lng: 102.310676","Live GPS tracking - Accuracy: 22.4m",NULL,"in_transit","2026-01-06 22:00:14");
INSERT INTO `distribution_tracking` VALUES("265","95","23",NULL,"0","Lat: 2.247866, Lng: 102.310383","Live GPS tracking - Accuracy: 29.3m",NULL,"in_transit","2026-01-06 22:00:18");
INSERT INTO `distribution_tracking` VALUES("266","95","23",NULL,"0","Lat: 2.247409, Lng: 102.310596","Live GPS tracking - Accuracy: 27.8m",NULL,"in_transit","2026-01-06 22:00:19");
INSERT INTO `distribution_tracking` VALUES("267","95","23",NULL,"0","Lat: 2.247557, Lng: 102.310694","Live GPS tracking - Accuracy: 15.5m",NULL,"in_transit","2026-01-06 22:00:23");
INSERT INTO `distribution_tracking` VALUES("268","95","23",NULL,"0","Lat: 2.247731, Lng: 102.310859","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 22:00:24");
INSERT INTO `distribution_tracking` VALUES("269","95","23",NULL,"0","Lat: 2.247533, Lng: 102.310864","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 22:00:28");
INSERT INTO `distribution_tracking` VALUES("270","95","23",NULL,"0","Lat: 2.24757, Lng: 102.310955","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 22:00:29");
INSERT INTO `distribution_tracking` VALUES("271","95","23",NULL,"0","Lat: 2.247591, Lng: 102.311277","Live GPS tracking - Accuracy: 17.3m",NULL,"in_transit","2026-01-06 22:00:33");
INSERT INTO `distribution_tracking` VALUES("272","95","23",NULL,"0","Lat: 2.247328, Lng: 102.310916","Live GPS tracking - Accuracy: 22.8m",NULL,"in_transit","2026-01-06 22:00:34");
INSERT INTO `distribution_tracking` VALUES("273","95","23",NULL,"0","Lat: 2.247667, Lng: 102.31057","Live GPS tracking - Accuracy: 21.5m",NULL,"in_transit","2026-01-06 22:00:38");
INSERT INTO `distribution_tracking` VALUES("274","95","23",NULL,"0","Lat: 2.247327, Lng: 102.310279","Live GPS tracking - Accuracy: 17m",NULL,"in_transit","2026-01-06 22:00:39");
INSERT INTO `distribution_tracking` VALUES("275","95","23",NULL,"0","Lat: 2.247282, Lng: 102.31034","Live GPS tracking - Accuracy: 29.6m",NULL,"in_transit","2026-01-06 22:00:43");
INSERT INTO `distribution_tracking` VALUES("276","95","23",NULL,"0","Lat: 2.24699, Lng: 102.310347","Live GPS tracking - Accuracy: 27.6m",NULL,"in_transit","2026-01-06 22:00:44");
INSERT INTO `distribution_tracking` VALUES("277","95","23",NULL,"0","Lat: 2.247404, Lng: 102.30985","Live GPS tracking - Accuracy: 12m",NULL,"in_transit","2026-01-06 22:00:48");
INSERT INTO `distribution_tracking` VALUES("278","95","23",NULL,"0","Lat: 2.247897, Lng: 102.309876","Live GPS tracking - Accuracy: 29.5m",NULL,"in_transit","2026-01-06 22:00:49");
INSERT INTO `distribution_tracking` VALUES("279","95","23",NULL,"0","Lat: 2.247785, Lng: 102.309439","Live GPS tracking - Accuracy: 15.7m",NULL,"in_transit","2026-01-06 22:00:53");
INSERT INTO `distribution_tracking` VALUES("280","95","23",NULL,"0","Lat: 2.24818, Lng: 102.309797","Live GPS tracking - Accuracy: 18.7m",NULL,"in_transit","2026-01-06 22:00:54");
INSERT INTO `distribution_tracking` VALUES("281","95","23",NULL,"0","Lat: 2.248639, Lng: 102.310025","Live GPS tracking - Accuracy: 12.5m",NULL,"in_transit","2026-01-06 22:00:58");
INSERT INTO `distribution_tracking` VALUES("282","95","23",NULL,"0","Lat: 2.248428, Lng: 102.311021","Live GPS tracking - Accuracy: 13.7m",NULL,"in_transit","2026-01-06 22:01:01");
INSERT INTO `distribution_tracking` VALUES("283","95","23",NULL,"0","Lat: 2.248599, Lng: 102.310719","Live GPS tracking - Accuracy: 23.9m",NULL,"in_transit","2026-01-06 22:01:03");
INSERT INTO `distribution_tracking` VALUES("284","95","23",NULL,"0","Lat: 2.248618, Lng: 102.310664","Live GPS tracking - Accuracy: 29.4m",NULL,"in_transit","2026-01-06 22:01:06");
INSERT INTO `distribution_tracking` VALUES("285","95","23",NULL,"0","Lat: 2.248191, Lng: 102.311009","Live GPS tracking - Accuracy: 21.3m",NULL,"in_transit","2026-01-06 22:01:08");
INSERT INTO `distribution_tracking` VALUES("286","95","23",NULL,"0","Lat: 2.248269, Lng: 102.310727","Live GPS tracking - Accuracy: 24.8m",NULL,"in_transit","2026-01-06 22:01:11");
INSERT INTO `distribution_tracking` VALUES("287","95","23",NULL,"0","Lat: 2.247851, Lng: 102.311026","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 22:01:13");
INSERT INTO `distribution_tracking` VALUES("288","95","23",NULL,"0","Lat: 2.247607, Lng: 102.310867","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 22:01:16");
INSERT INTO `distribution_tracking` VALUES("289","95","23",NULL,"0","Lat: 2.248077, Lng: 102.310733","Live GPS tracking - Accuracy: 20.5m",NULL,"in_transit","2026-01-06 22:01:18");
INSERT INTO `distribution_tracking` VALUES("290","95","23",NULL,"0","Lat: 2.247942, Lng: 102.310792","Live GPS tracking - Accuracy: 29.2m",NULL,"in_transit","2026-01-06 22:01:21");
INSERT INTO `distribution_tracking` VALUES("291","95","23",NULL,"0","Lat: 2.248288, Lng: 102.310364","Live GPS tracking - Accuracy: 14.5m",NULL,"in_transit","2026-01-06 22:01:23");
INSERT INTO `distribution_tracking` VALUES("292","95","23",NULL,"0","Lat: 2.248411, Lng: 102.31061","Live GPS tracking - Accuracy: 22.6m",NULL,"in_transit","2026-01-06 22:01:26");
INSERT INTO `distribution_tracking` VALUES("293","95","23",NULL,"0","Lat: 2.248611, Lng: 102.310207","Live GPS tracking - Accuracy: 23.1m",NULL,"in_transit","2026-01-06 22:01:28");
INSERT INTO `distribution_tracking` VALUES("294","95","23",NULL,"0","Lat: 2.248674, Lng: 102.31023","Live GPS tracking - Accuracy: 13.3m",NULL,"in_transit","2026-01-06 22:01:31");
INSERT INTO `distribution_tracking` VALUES("295","95","23",NULL,"0","Lat: 2.248297, Lng: 102.309754","Live GPS tracking - Accuracy: 28.9m",NULL,"in_transit","2026-01-06 22:01:33");
INSERT INTO `distribution_tracking` VALUES("296","95","23",NULL,"0","Lat: 2.24863, Lng: 102.309733","Live GPS tracking - Accuracy: 19.3m",NULL,"in_transit","2026-01-06 22:01:36");
INSERT INTO `distribution_tracking` VALUES("297","95","23",NULL,"0","Lat: 2.248694, Lng: 102.309802","Live GPS tracking - Accuracy: 17.9m",NULL,"in_transit","2026-01-06 22:01:38");
INSERT INTO `distribution_tracking` VALUES("298","95","23",NULL,"0","Lat: 2.248819, Lng: 102.30967","Live GPS tracking - Accuracy: 14.6m",NULL,"in_transit","2026-01-06 22:01:41");
INSERT INTO `distribution_tracking` VALUES("299","95","23",NULL,"0","Lat: 2.249236, Lng: 102.309902","Live GPS tracking - Accuracy: 25.4m",NULL,"in_transit","2026-01-06 22:01:43");
INSERT INTO `distribution_tracking` VALUES("300","95","23",NULL,"0","Lat: 2.249557, Lng: 102.309833","Live GPS tracking - Accuracy: 18.5m",NULL,"in_transit","2026-01-06 22:01:46");
INSERT INTO `distribution_tracking` VALUES("301","95","23",NULL,"0","Lat: 2.249608, Lng: 102.310199","Live GPS tracking - Accuracy: 16m",NULL,"in_transit","2026-01-06 22:01:48");
INSERT INTO `distribution_tracking` VALUES("302","95","23",NULL,"0","Lat: 2.249775, Lng: 102.310353","Live GPS tracking - Accuracy: 19.1m",NULL,"in_transit","2026-01-06 22:01:51");
INSERT INTO `distribution_tracking` VALUES("303","95","23",NULL,"0","Lat: 2.249863, Lng: 102.309878","Live GPS tracking - Accuracy: 21.6m",NULL,"in_transit","2026-01-06 22:01:53");
INSERT INTO `distribution_tracking` VALUES("304","95","23",NULL,"0","Lat: 2.249433, Lng: 102.30951","Live GPS tracking - Accuracy: 24.3m",NULL,"in_transit","2026-01-06 22:01:56");
INSERT INTO `distribution_tracking` VALUES("305","95","23",NULL,"0","Lat: 2.249261, Lng: 102.30927","Live GPS tracking - Accuracy: 14.4m",NULL,"in_transit","2026-01-06 22:01:58");
INSERT INTO `distribution_tracking` VALUES("306","95","23",NULL,"0","Lat: 2.248814, Lng: 102.309675","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 22:02:01");
INSERT INTO `distribution_tracking` VALUES("307","95","23",NULL,"0","Lat: 2.249249, Lng: 102.309964","Live GPS tracking - Accuracy: 20.3m",NULL,"in_transit","2026-01-06 22:02:03");
INSERT INTO `distribution_tracking` VALUES("308","95","23",NULL,"0","Lat: 2.249288, Lng: 102.30958","Live GPS tracking - Accuracy: 12.8m",NULL,"in_transit","2026-01-06 22:02:06");
INSERT INTO `distribution_tracking` VALUES("309","95","23",NULL,"0","Lat: 2.249169, Lng: 102.309686","Live GPS tracking - Accuracy: 26.5m",NULL,"in_transit","2026-01-06 22:02:08");
INSERT INTO `distribution_tracking` VALUES("310","95","23",NULL,"0","Lat: 2.24912, Lng: 102.309709","Live GPS tracking - Accuracy: 23m",NULL,"in_transit","2026-01-06 22:02:11");
INSERT INTO `distribution_tracking` VALUES("311","95","23",NULL,"0","Lat: 2.248727, Lng: 102.309819","Live GPS tracking - Accuracy: 10.4m",NULL,"in_transit","2026-01-06 22:02:13");
INSERT INTO `distribution_tracking` VALUES("312","95","23",NULL,"0","Lat: 2.249217, Lng: 102.30964","Live GPS tracking - Accuracy: 24.7m",NULL,"in_transit","2026-01-06 22:02:16");
INSERT INTO `distribution_tracking` VALUES("313","95","23",NULL,"0","Lat: 2.249534, Lng: 102.309373","Live GPS tracking - Accuracy: 17.7m",NULL,"in_transit","2026-01-06 22:02:20");
INSERT INTO `distribution_tracking` VALUES("314","95","23",NULL,"0","Lat: 2.249515, Lng: 102.309808","Live GPS tracking - Accuracy: 17.2m",NULL,"in_transit","2026-01-06 22:02:21");
INSERT INTO `distribution_tracking` VALUES("315","95","23",NULL,"0","Lat: 2.249542, Lng: 102.309576","Live GPS tracking - Accuracy: 10.3m",NULL,"in_transit","2026-01-06 22:02:24");
INSERT INTO `distribution_tracking` VALUES("316","95","23",NULL,"0","Lat: 2.249627, Lng: 102.309203","Live GPS tracking - Accuracy: 10.2m",NULL,"in_transit","2026-01-06 22:02:26");
INSERT INTO `distribution_tracking` VALUES("317","95","23",NULL,"0","Lat: 2.250049, Lng: 102.309097","Live GPS tracking - Accuracy: 17.5m",NULL,"in_transit","2026-01-06 22:02:28");
INSERT INTO `distribution_tracking` VALUES("318","95","23",NULL,"0","Lat: 2.249783, Lng: 102.309487","Live GPS tracking - Accuracy: 24.6m",NULL,"in_transit","2026-01-06 22:02:32");
INSERT INTO `distribution_tracking` VALUES("319","95","23",NULL,"0","Lat: 2.250102, Lng: 102.309811","Live GPS tracking - Accuracy: 26.1m",NULL,"in_transit","2026-01-06 22:02:33");
INSERT INTO `distribution_tracking` VALUES("320","95","23",NULL,"0","Lat: 2.250463, Lng: 102.309507","Live GPS tracking - Accuracy: 23.7m",NULL,"in_transit","2026-01-06 22:02:36");
INSERT INTO `distribution_tracking` VALUES("321","95","23",NULL,"0","Lat: 2.25045, Lng: 102.309589","Live GPS tracking - Accuracy: 27.9m",NULL,"in_transit","2026-01-06 22:02:39");
INSERT INTO `distribution_tracking` VALUES("322","95","23",NULL,"0","Lat: 2.250531, Lng: 102.309398","Live GPS tracking - Accuracy: 14.3m",NULL,"in_transit","2026-01-06 22:02:41");
INSERT INTO `distribution_tracking` VALUES("323","95","23",NULL,"0","Lat: 2.250447, Lng: 102.309384","Live GPS tracking - Accuracy: 25.1m",NULL,"in_transit","2026-01-06 22:02:43");
INSERT INTO `distribution_tracking` VALUES("324","95","23",NULL,"0","Lat: 2.250935, Lng: 102.309716","Live GPS tracking - Accuracy: 28m",NULL,"in_transit","2026-01-06 22:02:46");
INSERT INTO `distribution_tracking` VALUES("325","95","23",NULL,"0","Lat: 2.250572, Lng: 102.309458","Live GPS tracking - Accuracy: 28.4m",NULL,"in_transit","2026-01-06 22:02:48");
INSERT INTO `distribution_tracking` VALUES("326","95","23",NULL,"0","Lat: 2.250901, Lng: 102.30959","Live GPS tracking - Accuracy: 18.2m",NULL,"in_transit","2026-01-06 22:02:51");
INSERT INTO `distribution_tracking` VALUES("327","95","23",NULL,"0","Lat: 2.250972, Lng: 102.309551","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 22:02:53");
INSERT INTO `distribution_tracking` VALUES("328","95","23",NULL,"0","Lat: 2.251241, Lng: 102.309774","Live GPS tracking - Accuracy: 10.4m",NULL,"in_transit","2026-01-06 22:02:56");
INSERT INTO `distribution_tracking` VALUES("329","95","23",NULL,"0","Lat: 2.25134, Lng: 102.309601","Live GPS tracking - Accuracy: 23.5m",NULL,"in_transit","2026-01-06 22:02:58");
INSERT INTO `distribution_tracking` VALUES("330","95","23",NULL,"0","Lat: 2.250917, Lng: 102.309333","Live GPS tracking - Accuracy: 17.8m",NULL,"in_transit","2026-01-06 22:03:01");
INSERT INTO `distribution_tracking` VALUES("331","95","23",NULL,"0","Lat: 2.251155, Lng: 102.3095","Live GPS tracking - Accuracy: 16.2m",NULL,"in_transit","2026-01-06 22:03:03");
INSERT INTO `distribution_tracking` VALUES("332","95","23",NULL,"0","Lat: 2.251325, Lng: 102.30938","Live GPS tracking - Accuracy: 11m",NULL,"in_transit","2026-01-06 22:03:06");
INSERT INTO `distribution_tracking` VALUES("333","95","23",NULL,"0","Lat: 2.251503, Lng: 102.3098","Live GPS tracking - Accuracy: 12m",NULL,"in_transit","2026-01-06 22:03:08");
INSERT INTO `distribution_tracking` VALUES("334","95","23",NULL,"0","Lat: 2.251124, Lng: 102.310151","Live GPS tracking - Accuracy: 21.7m",NULL,"in_transit","2026-01-06 22:03:11");
INSERT INTO `distribution_tracking` VALUES("335","95","23",NULL,"0","Lat: 2.251367, Lng: 102.309947","Live GPS tracking - Accuracy: 23.7m",NULL,"in_transit","2026-01-06 22:03:13");
INSERT INTO `distribution_tracking` VALUES("336","95","23",NULL,"0","Lat: 2.251499, Lng: 102.310389","Live GPS tracking - Accuracy: 29.1m",NULL,"in_transit","2026-01-06 22:04:06");
INSERT INTO `distribution_tracking` VALUES("337","95","23",NULL,"0","Lat: 2.251427, Lng: 102.310125","Live GPS tracking - Accuracy: 27.1m",NULL,"in_transit","2026-01-06 22:04:06");
INSERT INTO `distribution_tracking` VALUES("338","95","23",NULL,"0","Lat: 2.251366, Lng: 102.309954","Live GPS tracking - Accuracy: 20.7m",NULL,"in_transit","2026-01-06 22:04:15");
INSERT INTO `distribution_tracking` VALUES("339","95","23",NULL,"0","Lat: 2.251693, Lng: 102.310386","Live GPS tracking - Accuracy: 15.9m",NULL,"in_transit","2026-01-06 22:04:16");
INSERT INTO `distribution_tracking` VALUES("340","95","23",NULL,"0","Lat: 2.251623, Lng: 102.310344","Live GPS tracking - Accuracy: 13.5m",NULL,"in_transit","2026-01-06 22:04:16");
INSERT INTO `distribution_tracking` VALUES("341","95","23",NULL,"0","Lat: 2.251163, Lng: 102.309877","Live GPS tracking - Accuracy: 16.2m",NULL,"in_transit","2026-01-06 22:04:18");
INSERT INTO `distribution_tracking` VALUES("342","95","23",NULL,"0","Lat: 2.251517, Lng: 102.310252","Live GPS tracking - Accuracy: 15m",NULL,"in_transit","2026-01-06 22:04:21");
INSERT INTO `distribution_tracking` VALUES("343","95","23",NULL,"0","Lat: 2.251792, Lng: 102.31039","Live GPS tracking - Accuracy: 24.4m",NULL,"in_transit","2026-01-06 22:04:23");
INSERT INTO `distribution_tracking` VALUES("344","95","23",NULL,"0","Lat: 2.251865, Lng: 102.310792","Live GPS tracking - Accuracy: 10.7m",NULL,"in_transit","2026-01-06 22:04:26");
INSERT INTO `distribution_tracking` VALUES("345","95","23",NULL,"0","Lat: 2.252029, Lng: 102.310584","Live GPS tracking - Accuracy: 11.8m",NULL,"in_transit","2026-01-06 22:04:28");
INSERT INTO `distribution_tracking` VALUES("346","95","23",NULL,"0","Lat: 2.252288, Lng: 102.310863","Live GPS tracking - Accuracy: 27.4m",NULL,"in_transit","2026-01-06 22:04:31");
INSERT INTO `distribution_tracking` VALUES("347","95","23",NULL,"0","Lat: 2.252685, Lng: 102.31083","Live GPS tracking - Accuracy: 10.7m",NULL,"in_transit","2026-01-06 22:04:33");
INSERT INTO `distribution_tracking` VALUES("348","95","23",NULL,"0","Lat: 2.252313, Lng: 102.310443","Live GPS tracking - Accuracy: 24.4m",NULL,"in_transit","2026-01-06 22:04:36");
INSERT INTO `distribution_tracking` VALUES("349","95","23",NULL,"0","Lat: 2.251964, Lng: 102.310847","Live GPS tracking - Accuracy: 17.2m",NULL,"in_transit","2026-01-06 22:04:38");
INSERT INTO `distribution_tracking` VALUES("350","95","23",NULL,"0","Lat: 2.252313, Lng: 102.310657","Live GPS tracking - Accuracy: 21.2m",NULL,"in_transit","2026-01-06 22:04:41");
INSERT INTO `distribution_tracking` VALUES("351","95","23",NULL,"0","Lat: 2.252276, Lng: 102.310609","Live GPS tracking - Accuracy: 28.4m",NULL,"in_transit","2026-01-06 22:04:43");
INSERT INTO `distribution_tracking` VALUES("352","95","23",NULL,"0","Lat: 2.252488, Lng: 102.310509","Live GPS tracking - Accuracy: 24.5m",NULL,"in_transit","2026-01-06 22:04:46");
INSERT INTO `distribution_tracking` VALUES("353","95","23",NULL,"0","Lat: 2.252342, Lng: 102.311001","Live GPS tracking - Accuracy: 23m",NULL,"in_transit","2026-01-06 22:04:48");
INSERT INTO `distribution_tracking` VALUES("354","95","23",NULL,"0","Lat: 2.252048, Lng: 102.311385","Live GPS tracking - Accuracy: 26.7m",NULL,"in_transit","2026-01-06 22:04:51");
INSERT INTO `distribution_tracking` VALUES("355","95","23",NULL,"0","Lat: 2.251869, Lng: 102.311031","Live GPS tracking - Accuracy: 15.5m",NULL,"in_transit","2026-01-06 22:04:53");
INSERT INTO `distribution_tracking` VALUES("356","95","23",NULL,"0","Lat: 2.25189, Lng: 102.311332","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 22:04:56");
INSERT INTO `distribution_tracking` VALUES("357","95","23",NULL,"0","Lat: 2.251714, Lng: 102.311591","Live GPS tracking - Accuracy: 26.9m",NULL,"in_transit","2026-01-06 22:04:58");
INSERT INTO `distribution_tracking` VALUES("358","95","23",NULL,"0","Lat: 2.251717, Lng: 102.31162","Live GPS tracking - Accuracy: 19.7m",NULL,"in_transit","2026-01-06 22:05:01");
INSERT INTO `distribution_tracking` VALUES("359","95","23",NULL,"0","Lat: 2.25132, Lng: 102.311592","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-06 22:05:03");
INSERT INTO `distribution_tracking` VALUES("360","95","23",NULL,"0","Lat: 2.251517, Lng: 102.311191","Live GPS tracking - Accuracy: 24.7m",NULL,"in_transit","2026-01-06 22:05:06");
INSERT INTO `distribution_tracking` VALUES("361","95","23",NULL,"0","Lat: 2.251087, Lng: 102.310889","Live GPS tracking - Accuracy: 14.2m",NULL,"in_transit","2026-01-06 22:05:08");
INSERT INTO `distribution_tracking` VALUES("362","95","23",NULL,"0","Lat: 2.251217, Lng: 102.311245","Live GPS tracking - Accuracy: 25.2m",NULL,"in_transit","2026-01-06 22:05:11");
INSERT INTO `distribution_tracking` VALUES("363","95","23",NULL,"0","Lat: 2.251194, Lng: 102.311093","Live GPS tracking - Accuracy: 28.4m",NULL,"in_transit","2026-01-06 22:05:13");
INSERT INTO `distribution_tracking` VALUES("364","95","23",NULL,"0","Lat: 2.250852, Lng: 102.31133","Live GPS tracking - Accuracy: 29.3m",NULL,"in_transit","2026-01-06 22:06:06");
INSERT INTO `distribution_tracking` VALUES("365","95","23",NULL,"0","Lat: 2.250525, Lng: 102.311324","Live GPS tracking - Accuracy: 17.8m",NULL,"in_transit","2026-01-06 22:06:06");
INSERT INTO `distribution_tracking` VALUES("366","95","23",NULL,"0","Lat: 2.250827, Lng: 102.311101","Live GPS tracking - Accuracy: 25.8m",NULL,"in_transit","2026-01-06 22:07:06");
INSERT INTO `distribution_tracking` VALUES("367","95","23",NULL,"0","Lat: 2.2511, Lng: 102.311484","Live GPS tracking - Accuracy: 18.5m",NULL,"in_transit","2026-01-06 22:07:06");
INSERT INTO `distribution_tracking` VALUES("368","95","23",NULL,"0","Lat: 2.250696, Lng: 102.311935","Live GPS tracking - Accuracy: 28.3m",NULL,"in_transit","2026-01-06 22:08:06");
INSERT INTO `distribution_tracking` VALUES("369","95","23",NULL,"0","Lat: 2.25118, Lng: 102.311925","Live GPS tracking - Accuracy: 19m",NULL,"in_transit","2026-01-06 22:08:06");
INSERT INTO `distribution_tracking` VALUES("370","95","23",NULL,"0","Lat: 2.2512, Lng: 102.311846","Live GPS tracking - Accuracy: 18.9m",NULL,"in_transit","2026-01-06 22:09:06");
INSERT INTO `distribution_tracking` VALUES("371","95","23",NULL,"0","Lat: 2.251458, Lng: 102.312327","Live GPS tracking - Accuracy: 13.6m",NULL,"in_transit","2026-01-06 22:09:06");
INSERT INTO `distribution_tracking` VALUES("372","95","23",NULL,"0","Lat: 2.251922, Lng: 102.312094","Live GPS tracking - Accuracy: 12.9m",NULL,"in_transit","2026-01-06 22:10:06");
INSERT INTO `distribution_tracking` VALUES("373","95","23",NULL,"0","Lat: 2.251571, Lng: 102.311649","Live GPS tracking - Accuracy: 21m",NULL,"in_transit","2026-01-06 22:10:06");
INSERT INTO `distribution_tracking` VALUES("374","95","23",NULL,"0","Lat: 2.252039, Lng: 102.311428","Live GPS tracking - Accuracy: 11.9m",NULL,"in_transit","2026-01-06 22:10:09");
INSERT INTO `distribution_tracking` VALUES("375","95","23",NULL,"0","Lat: 2.251657, Lng: 102.311755","Live GPS tracking - Accuracy: 18m",NULL,"in_transit","2026-01-06 22:10:11");
INSERT INTO `distribution_tracking` VALUES("376","95","23",NULL,"0","Lat: 2.251294, Lng: 102.311884","Live GPS tracking - Accuracy: 13.5m",NULL,"in_transit","2026-01-06 22:10:13");
INSERT INTO `distribution_tracking` VALUES("377","95","23",NULL,"0","Lat: 2.25092, Lng: 102.312149","Live GPS tracking - Accuracy: 10.1m",NULL,"in_transit","2026-01-06 22:10:16");
INSERT INTO `distribution_tracking` VALUES("378","95","23",NULL,"0","Lat: 2.251254, Lng: 102.311718","Live GPS tracking - Accuracy: 20.5m",NULL,"in_transit","2026-01-06 22:10:18");
INSERT INTO `distribution_tracking` VALUES("379","95","23",NULL,"0","Lat: 2.251385, Lng: 102.31185","Live GPS tracking - Accuracy: 10.3m",NULL,"in_transit","2026-01-06 22:10:21");
INSERT INTO `distribution_tracking` VALUES("380","95","23",NULL,"0","Lat: 2.251064, Lng: 102.311832","Live GPS tracking - Accuracy: 25.6m",NULL,"in_transit","2026-01-06 22:10:23");
INSERT INTO `distribution_tracking` VALUES("381","95","23",NULL,"0","Lat: 2.250819, Lng: 102.31214","Live GPS tracking - Accuracy: 23m",NULL,"in_transit","2026-01-06 22:10:26");
INSERT INTO `distribution_tracking` VALUES("382","95","23",NULL,"0","Lat: 2.251039, Lng: 102.312134","Live GPS tracking - Accuracy: 23.1m",NULL,"in_transit","2026-01-06 22:10:28");
INSERT INTO `distribution_tracking` VALUES("383","95","23",NULL,"0","Lat: 2.25142, Lng: 102.312081","Live GPS tracking - Accuracy: 21.6m",NULL,"in_transit","2026-01-06 22:10:31");
INSERT INTO `distribution_tracking` VALUES("384","95","23",NULL,"0","Lat: 2.251885, Lng: 102.312207","Live GPS tracking - Accuracy: 15.4m",NULL,"in_transit","2026-01-06 22:10:33");
INSERT INTO `distribution_tracking` VALUES("385","95","23",NULL,"0","Lat: 2.251918, Lng: 102.312516","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 22:10:36");
INSERT INTO `distribution_tracking` VALUES("386","95","23",NULL,"0","Lat: 2.252389, Lng: 102.312781","Live GPS tracking - Accuracy: 22.2m",NULL,"in_transit","2026-01-06 22:10:38");
INSERT INTO `distribution_tracking` VALUES("387","95","23",NULL,"0","Lat: 2.252696, Lng: 102.312873","Live GPS tracking - Accuracy: 22m",NULL,"in_transit","2026-01-06 22:10:41");
INSERT INTO `distribution_tracking` VALUES("388","95","23",NULL,"0","Lat: 2.252255, Lng: 102.31257","Live GPS tracking - Accuracy: 28.9m",NULL,"in_transit","2026-01-06 22:10:43");
INSERT INTO `distribution_tracking` VALUES("389","95","23",NULL,"0","Lat: 2.252029, Lng: 102.312079","Live GPS tracking - Accuracy: 25.5m",NULL,"in_transit","2026-01-06 22:10:46");
INSERT INTO `distribution_tracking` VALUES("390","95","23",NULL,"0","Lat: 2.252477, Lng: 102.312171","Live GPS tracking - Accuracy: 25.4m",NULL,"in_transit","2026-01-06 22:10:48");
INSERT INTO `distribution_tracking` VALUES("391","95","23",NULL,"0","Lat: 2.252123, Lng: 102.312541","Live GPS tracking - Accuracy: 17.2m",NULL,"in_transit","2026-01-06 22:10:51");
INSERT INTO `distribution_tracking` VALUES("392","95","23",NULL,"0","Lat: 2.251728, Lng: 102.31221","Live GPS tracking - Accuracy: 14.3m",NULL,"in_transit","2026-01-06 22:10:53");
INSERT INTO `distribution_tracking` VALUES("393","95","23",NULL,"0","Lat: 2.25133, Lng: 102.312065","Live GPS tracking - Accuracy: 13.7m",NULL,"in_transit","2026-01-06 22:10:56");
INSERT INTO `distribution_tracking` VALUES("394","95","23",NULL,"0","Lat: 2.251502, Lng: 102.312324","Live GPS tracking - Accuracy: 14.5m",NULL,"in_transit","2026-01-06 22:10:58");
INSERT INTO `distribution_tracking` VALUES("395","95","23",NULL,"0","Lat: 2.251499, Lng: 102.311953","Live GPS tracking - Accuracy: 16.9m",NULL,"in_transit","2026-01-06 22:11:01");
INSERT INTO `distribution_tracking` VALUES("396","95","23",NULL,"0","Lat: 2.251984, Lng: 102.312102","Live GPS tracking - Accuracy: 20.6m",NULL,"in_transit","2026-01-06 22:11:03");
INSERT INTO `distribution_tracking` VALUES("397","95","23",NULL,"0","Lat: 2.251627, Lng: 102.312466","Live GPS tracking - Accuracy: 27.6m",NULL,"in_transit","2026-01-06 22:11:06");
INSERT INTO `distribution_tracking` VALUES("398","95","23",NULL,"0","Lat: 2.251894, Lng: 102.312378","Live GPS tracking - Accuracy: 18.4m",NULL,"in_transit","2026-01-06 22:11:08");
INSERT INTO `distribution_tracking` VALUES("399","95","23",NULL,"0","Lat: 2.251885, Lng: 102.312822","Live GPS tracking - Accuracy: 17.4m",NULL,"in_transit","2026-01-06 22:11:11");
INSERT INTO `distribution_tracking` VALUES("400","95","23",NULL,"0","Lat: 2.251801, Lng: 102.313021","Live GPS tracking - Accuracy: 10.9m",NULL,"in_transit","2026-01-06 22:11:13");
INSERT INTO `distribution_tracking` VALUES("401","95","23",NULL,"0","Lat: 2.251774, Lng: 102.312914","Live GPS tracking - Accuracy: 28.7m",NULL,"in_transit","2026-01-06 22:11:16");
INSERT INTO `distribution_tracking` VALUES("402","95","23",NULL,"0","Lat: 2.251586, Lng: 102.31315","Live GPS tracking - Accuracy: 11.5m",NULL,"in_transit","2026-01-06 22:11:18");
INSERT INTO `distribution_tracking` VALUES("403","95","23",NULL,"0","Lat: 2.251138, Lng: 102.31291","Live GPS tracking - Accuracy: 29.1m",NULL,"in_transit","2026-01-06 22:11:21");
INSERT INTO `distribution_tracking` VALUES("404","95","23",NULL,"0","Lat: 2.251504, Lng: 102.312453","Live GPS tracking - Accuracy: 28.9m",NULL,"in_transit","2026-01-06 22:11:23");
INSERT INTO `distribution_tracking` VALUES("405","95","23",NULL,"0","Lat: 2.251138, Lng: 102.312489","Live GPS tracking - Accuracy: 14.8m",NULL,"in_transit","2026-01-06 22:11:26");
INSERT INTO `distribution_tracking` VALUES("406","95","23",NULL,"0","Lat: 2.251583, Lng: 102.312459","Live GPS tracking - Accuracy: 26.3m",NULL,"in_transit","2026-01-06 22:11:28");
INSERT INTO `distribution_tracking` VALUES("407","95","23",NULL,"0","Lat: 2.25172, Lng: 102.31215","Live GPS tracking - Accuracy: 25.7m",NULL,"in_transit","2026-01-06 22:11:31");
INSERT INTO `distribution_tracking` VALUES("408","95","23",NULL,"0","Lat: 2.251997, Lng: 102.311927","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 22:11:33");
INSERT INTO `distribution_tracking` VALUES("409","95","23",NULL,"0","Lat: 2.252053, Lng: 102.311933","Live GPS tracking - Accuracy: 14m",NULL,"in_transit","2026-01-06 22:11:36");
INSERT INTO `distribution_tracking` VALUES("410","95","23",NULL,"0","Lat: 2.252469, Lng: 102.312036","Live GPS tracking - Accuracy: 13.9m",NULL,"in_transit","2026-01-06 22:11:38");
INSERT INTO `distribution_tracking` VALUES("411","95","23",NULL,"0","Lat: 2.252022, Lng: 102.312499","Live GPS tracking - Accuracy: 18.1m",NULL,"in_transit","2026-01-06 22:11:41");
INSERT INTO `distribution_tracking` VALUES("412","95","23",NULL,"0","Lat: 2.252105, Lng: 102.312164","Live GPS tracking - Accuracy: 20.7m",NULL,"in_transit","2026-01-06 22:11:43");
INSERT INTO `distribution_tracking` VALUES("413","95","23",NULL,"0","Lat: 2.252446, Lng: 102.311766","Live GPS tracking - Accuracy: 15.1m",NULL,"in_transit","2026-01-06 22:11:46");
INSERT INTO `distribution_tracking` VALUES("414","95","23",NULL,"0","Lat: 2.252479, Lng: 102.311986","Live GPS tracking - Accuracy: 17.7m",NULL,"in_transit","2026-01-06 22:11:48");
INSERT INTO `distribution_tracking` VALUES("415","95","23",NULL,"0","Lat: 2.252507, Lng: 102.312199","Live GPS tracking - Accuracy: 16.8m",NULL,"in_transit","2026-01-06 22:11:51");
INSERT INTO `distribution_tracking` VALUES("416","95","23",NULL,"0","Lat: 2.252169, Lng: 102.312567","Live GPS tracking - Accuracy: 10.8m",NULL,"in_transit","2026-01-06 22:11:53");
INSERT INTO `distribution_tracking` VALUES("417","95","23",NULL,"0","Lat: 2.251962, Lng: 102.312404","Live GPS tracking - Accuracy: 24.5m",NULL,"in_transit","2026-01-06 22:11:56");
INSERT INTO `distribution_tracking` VALUES("418","95","23",NULL,"0","Lat: 2.252065, Lng: 102.311971","Live GPS tracking - Accuracy: 17m",NULL,"in_transit","2026-01-06 22:11:58");
INSERT INTO `distribution_tracking` VALUES("419","95","23",NULL,"0","Lat: 2.251964, Lng: 102.311832","Live GPS tracking - Accuracy: 17.2m",NULL,"in_transit","2026-01-06 22:12:01");
INSERT INTO `distribution_tracking` VALUES("420","95","23",NULL,"0","Lat: 2.251729, Lng: 102.312259","Live GPS tracking - Accuracy: 17.1m",NULL,"in_transit","2026-01-06 22:12:03");
INSERT INTO `distribution_tracking` VALUES("421","95","23",NULL,"0","Lat: 2.251593, Lng: 102.312733","Live GPS tracking - Accuracy: 19m",NULL,"in_transit","2026-01-06 22:12:06");
INSERT INTO `distribution_tracking` VALUES("422","95","23",NULL,"0","Lat: 2.251624, Lng: 102.312521","Live GPS tracking - Accuracy: 19.9m",NULL,"in_transit","2026-01-06 22:12:08");
INSERT INTO `distribution_tracking` VALUES("423","95","23",NULL,"0","Lat: 2.251975, Lng: 102.312255","Live GPS tracking - Accuracy: 18.4m",NULL,"in_transit","2026-01-06 22:12:11");
INSERT INTO `distribution_tracking` VALUES("424","95","23",NULL,"0","Lat: 2.251496, Lng: 102.312052","Live GPS tracking - Accuracy: 15.3m",NULL,"in_transit","2026-01-06 22:12:13");
INSERT INTO `distribution_tracking` VALUES("425","95","23",NULL,"0","Lat: 2.251344, Lng: 102.312314","Live GPS tracking - Accuracy: 15.7m",NULL,"in_transit","2026-01-06 22:12:16");
INSERT INTO `distribution_tracking` VALUES("426","95","23",NULL,"0","Lat: 2.251572, Lng: 102.312128","Live GPS tracking - Accuracy: 22.6m",NULL,"in_transit","2026-01-06 22:12:18");
INSERT INTO `distribution_tracking` VALUES("427","95","23",NULL,"0","Lat: 2.251708, Lng: 102.312426","Live GPS tracking - Accuracy: 14m",NULL,"in_transit","2026-01-06 22:12:21");
INSERT INTO `distribution_tracking` VALUES("428","95","23",NULL,"0","Lat: 2.251843, Lng: 102.312363","Live GPS tracking - Accuracy: 26.2m",NULL,"in_transit","2026-01-06 22:12:23");
INSERT INTO `distribution_tracking` VALUES("429","95","23",NULL,"0","Lat: 2.251634, Lng: 102.312709","Live GPS tracking - Accuracy: 18.2m",NULL,"in_transit","2026-01-06 22:12:26");
INSERT INTO `distribution_tracking` VALUES("430","95","23",NULL,"0","Lat: 2.252045, Lng: 102.312316","Live GPS tracking - Accuracy: 22.8m",NULL,"in_transit","2026-01-06 22:12:28");
INSERT INTO `distribution_tracking` VALUES("431","95","23",NULL,"0","Lat: 2.251905, Lng: 102.311822","Live GPS tracking - Accuracy: 15.2m",NULL,"in_transit","2026-01-06 22:12:31");
INSERT INTO `distribution_tracking` VALUES("432","95","23",NULL,"0","Lat: 2.252273, Lng: 102.311325","Live GPS tracking - Accuracy: 27.4m",NULL,"in_transit","2026-01-06 22:12:33");
INSERT INTO `distribution_tracking` VALUES("433","95","23",NULL,"0","Lat: 2.252432, Lng: 102.311162","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 22:12:36");
INSERT INTO `distribution_tracking` VALUES("434","95","23",NULL,"0","Lat: 2.252915, Lng: 102.310879","Live GPS tracking - Accuracy: 27.9m",NULL,"in_transit","2026-01-06 22:12:38");
INSERT INTO `distribution_tracking` VALUES("435","95","23",NULL,"0","Lat: 2.252484, Lng: 102.310423","Live GPS tracking - Accuracy: 11.8m",NULL,"in_transit","2026-01-06 22:12:41");
INSERT INTO `distribution_tracking` VALUES("436","95","23",NULL,"0","Lat: 2.252062, Lng: 102.310595","Live GPS tracking - Accuracy: 13.7m",NULL,"in_transit","2026-01-06 22:12:43");
INSERT INTO `distribution_tracking` VALUES("437","95","23",NULL,"0","Lat: 2.251641, Lng: 102.310919","Live GPS tracking - Accuracy: 28.6m",NULL,"in_transit","2026-01-06 22:12:46");
INSERT INTO `distribution_tracking` VALUES("438","95","23",NULL,"0","Lat: 2.252018, Lng: 102.311102","Live GPS tracking - Accuracy: 23m",NULL,"in_transit","2026-01-06 22:12:48");
INSERT INTO `distribution_tracking` VALUES("439","95","23",NULL,"0","Lat: 2.252389, Lng: 102.311017","Live GPS tracking - Accuracy: 13.9m",NULL,"in_transit","2026-01-06 22:12:51");
INSERT INTO `distribution_tracking` VALUES("440","95","23",NULL,"0","Lat: 2.25226, Lng: 102.310906","Live GPS tracking - Accuracy: 26m",NULL,"in_transit","2026-01-06 22:12:53");
INSERT INTO `distribution_tracking` VALUES("441","95","23",NULL,"0","Lat: 2.252531, Lng: 102.310712","Live GPS tracking - Accuracy: 26.2m",NULL,"in_transit","2026-01-06 22:12:56");
INSERT INTO `distribution_tracking` VALUES("442","95","23",NULL,"0","Lat: 2.252413, Lng: 102.311099","Live GPS tracking - Accuracy: 12.7m",NULL,"in_transit","2026-01-06 22:12:58");
INSERT INTO `distribution_tracking` VALUES("443","95","23",NULL,"0","Lat: 2.252051, Lng: 102.310707","Live GPS tracking - Accuracy: 16.9m",NULL,"in_transit","2026-01-06 22:13:01");
INSERT INTO `distribution_tracking` VALUES("444","95","23",NULL,"0","Lat: 2.251744, Lng: 102.310646","Live GPS tracking - Accuracy: 26.2m",NULL,"in_transit","2026-01-06 22:13:03");
INSERT INTO `distribution_tracking` VALUES("445","95","23",NULL,"0","Lat: 2.251942, Lng: 102.31101","Live GPS tracking - Accuracy: 12.2m",NULL,"in_transit","2026-01-06 22:13:06");
INSERT INTO `distribution_tracking` VALUES("446","95","23",NULL,"0","Lat: 2.252404, Lng: 102.310732","Live GPS tracking - Accuracy: 22.3m",NULL,"in_transit","2026-01-06 22:13:08");
INSERT INTO `distribution_tracking` VALUES("447","95","23",NULL,"0","Lat: 2.252777, Lng: 102.310579","Live GPS tracking - Accuracy: 20.8m",NULL,"in_transit","2026-01-06 22:13:11");
INSERT INTO `distribution_tracking` VALUES("448","95","23",NULL,"0","Lat: 2.25248, Lng: 102.310486","Live GPS tracking - Accuracy: 17.4m",NULL,"in_transit","2026-01-06 22:13:13");
INSERT INTO `distribution_tracking` VALUES("449","95","23",NULL,"0","Lat: 2.252279, Lng: 102.310566","Live GPS tracking - Accuracy: 24.4m",NULL,"in_transit","2026-01-06 22:13:16");
INSERT INTO `distribution_tracking` VALUES("450","95","23",NULL,"0","Lat: 2.251907, Lng: 102.310242","Live GPS tracking - Accuracy: 20.7m",NULL,"in_transit","2026-01-06 22:13:18");
INSERT INTO `distribution_tracking` VALUES("451","95","23",NULL,"0","Lat: 2.251826, Lng: 102.309913","Live GPS tracking - Accuracy: 24.6m",NULL,"in_transit","2026-01-06 22:13:21");
INSERT INTO `distribution_tracking` VALUES("452","95","23",NULL,"0","Lat: 2.252282, Lng: 102.309542","Live GPS tracking - Accuracy: 26.1m",NULL,"in_transit","2026-01-06 22:13:23");
INSERT INTO `distribution_tracking` VALUES("453","95","23",NULL,"0","Lat: 2.252695, Lng: 102.309606","Live GPS tracking - Accuracy: 10.4m",NULL,"in_transit","2026-01-06 22:13:26");
INSERT INTO `distribution_tracking` VALUES("454","95","23",NULL,"0","Lat: 2.252676, Lng: 102.309119","Live GPS tracking - Accuracy: 13.4m",NULL,"in_transit","2026-01-06 22:13:28");
INSERT INTO `distribution_tracking` VALUES("455","95","23",NULL,"0","Lat: 2.252838, Lng: 102.3093","Live GPS tracking - Accuracy: 15.8m",NULL,"in_transit","2026-01-06 22:13:31");
INSERT INTO `distribution_tracking` VALUES("456","95","23",NULL,"0","Lat: 2.253118, Lng: 102.309333","Live GPS tracking - Accuracy: 26.3m",NULL,"in_transit","2026-01-06 22:13:33");
INSERT INTO `distribution_tracking` VALUES("457","95","23",NULL,"0","Lat: 2.253198, Lng: 102.309528","Live GPS tracking - Accuracy: 11m",NULL,"in_transit","2026-01-06 22:13:36");
INSERT INTO `distribution_tracking` VALUES("458","95","23",NULL,"0","Lat: 2.252724, Lng: 102.309923","Live GPS tracking - Accuracy: 23.5m",NULL,"in_transit","2026-01-06 22:13:38");
INSERT INTO `distribution_tracking` VALUES("459","95","23",NULL,"0","Lat: 2.252747, Lng: 102.310422","Live GPS tracking - Accuracy: 13.9m",NULL,"in_transit","2026-01-06 22:13:41");
INSERT INTO `distribution_tracking` VALUES("460","95","23",NULL,"0","Lat: 2.25318, Lng: 102.310264","Live GPS tracking - Accuracy: 22.9m",NULL,"in_transit","2026-01-06 22:13:43");
INSERT INTO `distribution_tracking` VALUES("461","95","23",NULL,"0","Lat: 2.253557, Lng: 102.309781","Live GPS tracking - Accuracy: 18.1m",NULL,"in_transit","2026-01-06 22:13:46");
INSERT INTO `distribution_tracking` VALUES("462","95","23",NULL,"0","Lat: 2.253909, Lng: 102.309847","Live GPS tracking - Accuracy: 23.3m",NULL,"in_transit","2026-01-06 22:13:48");
INSERT INTO `distribution_tracking` VALUES("463","95","23",NULL,"0","Lat: 2.254238, Lng: 102.310079","Live GPS tracking - Accuracy: 11.7m",NULL,"in_transit","2026-01-06 22:13:51");
INSERT INTO `distribution_tracking` VALUES("464","95","23",NULL,"0","Lat: 2.254564, Lng: 102.31043","Live GPS tracking - Accuracy: 25.2m",NULL,"in_transit","2026-01-06 22:13:53");
INSERT INTO `distribution_tracking` VALUES("465","95","23",NULL,"0","Lat: 2.254651, Lng: 102.310714","Live GPS tracking - Accuracy: 21.2m",NULL,"in_transit","2026-01-06 22:13:56");
INSERT INTO `distribution_tracking` VALUES("466","95","23",NULL,"0","Lat: 2.254267, Lng: 102.310989","Live GPS tracking - Accuracy: 16.2m",NULL,"in_transit","2026-01-06 22:13:58");
INSERT INTO `distribution_tracking` VALUES("467","95","23",NULL,"0","Lat: 2.253808, Lng: 102.310713","Live GPS tracking - Accuracy: 16.9m",NULL,"in_transit","2026-01-06 22:14:01");
INSERT INTO `distribution_tracking` VALUES("468","95","23",NULL,"0","Lat: 2.254234, Lng: 102.310616","Live GPS tracking - Accuracy: 16.2m",NULL,"in_transit","2026-01-06 22:14:03");
INSERT INTO `distribution_tracking` VALUES("469","95","23",NULL,"0","Lat: 2.254304, Lng: 102.310121","Live GPS tracking - Accuracy: 19.1m",NULL,"in_transit","2026-01-06 22:14:06");
INSERT INTO `distribution_tracking` VALUES("470","95","23",NULL,"0","Lat: 2.253913, Lng: 102.309663","Live GPS tracking - Accuracy: 28.9m",NULL,"in_transit","2026-01-06 22:14:08");
INSERT INTO `distribution_tracking` VALUES("471","95","23",NULL,"0","Lat: 2.254137, Lng: 102.3096","Live GPS tracking - Accuracy: 15.4m",NULL,"in_transit","2026-01-06 22:14:11");
INSERT INTO `distribution_tracking` VALUES("472","95","23",NULL,"0","Lat: 2.254385, Lng: 102.309348","Live GPS tracking - Accuracy: 28.6m",NULL,"in_transit","2026-01-06 22:14:13");
INSERT INTO `distribution_tracking` VALUES("473","95","23",NULL,"0","Lat: 2.254251, Lng: 102.308858","Live GPS tracking - Accuracy: 16.4m",NULL,"in_transit","2026-01-06 22:14:16");
INSERT INTO `distribution_tracking` VALUES("474","95","23",NULL,"0","Lat: 2.254178, Lng: 102.308832","Live GPS tracking - Accuracy: 25.5m",NULL,"in_transit","2026-01-06 22:14:18");
INSERT INTO `distribution_tracking` VALUES("475","95","23",NULL,"0","Lat: 2.254152, Lng: 102.309007","Live GPS tracking - Accuracy: 20.1m",NULL,"in_transit","2026-01-06 22:14:21");
INSERT INTO `distribution_tracking` VALUES("476","95","23",NULL,"0","Lat: 2.253995, Lng: 102.308836","Live GPS tracking - Accuracy: 11.4m",NULL,"in_transit","2026-01-06 22:14:23");
INSERT INTO `distribution_tracking` VALUES("477","95","23",NULL,"0","Lat: 2.253559, Lng: 102.30881","Live GPS tracking - Accuracy: 21.2m",NULL,"in_transit","2026-01-06 22:14:26");
INSERT INTO `distribution_tracking` VALUES("478","95","23",NULL,"0","Lat: 2.253696, Lng: 102.309198","Live GPS tracking - Accuracy: 12.1m",NULL,"in_transit","2026-01-06 22:14:28");
INSERT INTO `distribution_tracking` VALUES("479","95","23",NULL,"0","Lat: 2.254116, Lng: 102.30924","Live GPS tracking - Accuracy: 19.5m",NULL,"in_transit","2026-01-06 22:14:31");
INSERT INTO `distribution_tracking` VALUES("480","95","23",NULL,"0","Lat: 2.254064, Lng: 102.308968","Live GPS tracking - Accuracy: 17.6m",NULL,"in_transit","2026-01-06 22:14:33");
INSERT INTO `distribution_tracking` VALUES("481","95","23",NULL,"0","Lat: 2.254048, Lng: 102.309232","Live GPS tracking - Accuracy: 22.6m",NULL,"in_transit","2026-01-06 22:14:36");
INSERT INTO `distribution_tracking` VALUES("482","95","23",NULL,"0","Lat: 2.253892, Lng: 102.308979","Live GPS tracking - Accuracy: 16.3m",NULL,"in_transit","2026-01-06 22:14:38");
INSERT INTO `distribution_tracking` VALUES("483","95","23",NULL,"0","Lat: 2.253467, Lng: 102.30873","Live GPS tracking - Accuracy: 29.9m",NULL,"in_transit","2026-01-06 22:14:41");
INSERT INTO `distribution_tracking` VALUES("484","95","23",NULL,"0","Lat: 2.253548, Lng: 102.308388","Live GPS tracking - Accuracy: 14.4m",NULL,"in_transit","2026-01-06 22:14:43");
INSERT INTO `distribution_tracking` VALUES("485","95","23",NULL,"0","Lat: 2.25328, Lng: 102.308081","Live GPS tracking - Accuracy: 16m",NULL,"in_transit","2026-01-06 22:14:46");
INSERT INTO `distribution_tracking` VALUES("486","95","23",NULL,"0","Lat: 2.252946, Lng: 102.30849","Live GPS tracking - Accuracy: 24.6m",NULL,"in_transit","2026-01-06 22:14:49");
INSERT INTO `distribution_tracking` VALUES("487","95","23",NULL,"0","Lat: 2.253062, Lng: 102.308941","Live GPS tracking - Accuracy: 27.1m",NULL,"in_transit","2026-01-06 22:14:51");
INSERT INTO `distribution_tracking` VALUES("488","95","23",NULL,"50","Stopped for traffic","","","delayed","2026-01-06 22:14:52");
INSERT INTO `distribution_tracking` VALUES("489","95","23",NULL,"0","Lat: 2.253315, Lng: 102.309141","Live GPS tracking - Accuracy: 15.7m",NULL,"in_transit","2026-01-06 22:14:58");
INSERT INTO `distribution_tracking` VALUES("490","95","23",NULL,"0","Lat: 2.253619, Lng: 102.309348","Live GPS tracking - Accuracy: 23.7m",NULL,"in_transit","2026-01-06 22:14:58");
INSERT INTO `distribution_tracking` VALUES("491","95","23",NULL,"0","Lat: 2.253821, Lng: 102.309146","Live GPS tracking - Accuracy: 12.1m",NULL,"in_transit","2026-01-06 22:14:58");
INSERT INTO `distribution_tracking` VALUES("492","95","23",NULL,"50","Stopped for traffic","","","delayed","2026-01-06 22:20:15");
INSERT INTO `distribution_tracking` VALUES("493","95","23",NULL,"0","Lat: 2.248664, Lng: 102.310912","Live GPS tracking - Accuracy: 28.9m",NULL,"in_transit","2026-01-06 22:20:29");
INSERT INTO `distribution_tracking` VALUES("494","95","23",NULL,"0","Lat: 2.249068, Lng: 102.311267","Live GPS tracking - Accuracy: 27m",NULL,"in_transit","2026-01-06 22:20:34");
INSERT INTO `distribution_tracking` VALUES("495","95","23",NULL,"0","Lat: 2.24911, Lng: 102.311279","Live GPS tracking - Accuracy: 24.3m",NULL,"in_transit","2026-01-06 22:20:39");
INSERT INTO `distribution_tracking` VALUES("496","95","23",NULL,"0","Lat: 2.249497, Lng: 102.311215","Live GPS tracking - Accuracy: 23.2m",NULL,"in_transit","2026-01-06 22:20:44");
INSERT INTO `distribution_tracking` VALUES("497","95","23",NULL,"0","Lat: 2.249769, Lng: 102.311507","Live GPS tracking - Accuracy: 29.7m",NULL,"in_transit","2026-01-06 22:20:49");
INSERT INTO `distribution_tracking` VALUES("498","95","23",NULL,"0","Lat: 2.249298, Lng: 102.311144","Live GPS tracking - Accuracy: 15.3m",NULL,"in_transit","2026-01-06 22:20:54");
INSERT INTO `distribution_tracking` VALUES("499","95","23",NULL,"0","Lat: 2.249467, Lng: 102.311085","Live GPS tracking - Accuracy: 13m",NULL,"in_transit","2026-01-06 22:20:59");
INSERT INTO `distribution_tracking` VALUES("500","95","23",NULL,"0","Lat: 2.249545, Lng: 102.311017","Live GPS tracking - Accuracy: 18.4m",NULL,"in_transit","2026-01-06 22:21:05");
INSERT INTO `distribution_tracking` VALUES("501","95","23",NULL,"0","Lat: 2.249742, Lng: 102.310652","Live GPS tracking - Accuracy: 18.3m",NULL,"in_transit","2026-01-06 22:21:09");
INSERT INTO `distribution_tracking` VALUES("502","95","23",NULL,"50","Stopped for traffic","","","delayed","2026-01-06 22:21:11");
INSERT INTO `distribution_tracking` VALUES("503","95","23",NULL,"0","Lat: 2.250096, Lng: 102.310709","Live GPS tracking - Accuracy: 25.5m",NULL,"in_transit","2026-01-06 22:21:15");
INSERT INTO `distribution_tracking` VALUES("504","95","23",NULL,"50","Stopped for traffic","","","delayed","2026-01-06 22:31:49");
INSERT INTO `distribution_tracking` VALUES("505","95","23",NULL,"50","Main Highway - En route","h","40","in_transit","2026-01-06 22:35:18");
INSERT INTO `distribution_tracking` VALUES("506","95","23",NULL,"50","Near the affected area","","40","delayed","2026-01-06 22:36:16");
INSERT INTO `distribution_tracking` VALUES("507","95","23",NULL,"50","Near the affected area","","40","delayed","2026-01-06 22:36:28");
INSERT INTO `distribution_tracking` VALUES("508","95","23",NULL,"50","Near the affected area","","40","delayed","2026-01-06 22:36:53");
INSERT INTO `distribution_tracking` VALUES("509","95","23",NULL,"0","Lat: 2.248442, Lng: 102.310695","Live GPS tracking - Accuracy: 27.2m",NULL,"in_transit","2026-01-06 22:36:57");
INSERT INTO `distribution_tracking` VALUES("510","95","23",NULL,"0","Lat: 2.248659, Lng: 102.310699","Live GPS tracking - Accuracy: 25.3m",NULL,"in_transit","2026-01-06 22:37:02");
INSERT INTO `distribution_tracking` VALUES("511","95","23",NULL,"0","Lat: 2.248399, Lng: 102.311113","Live GPS tracking - Accuracy: 19.7m",NULL,"in_transit","2026-01-06 22:37:07");
INSERT INTO `distribution_tracking` VALUES("512","95","23",NULL,"0","Lat: 2.248492, Lng: 102.310648","Live GPS tracking - Accuracy: 13.2m",NULL,"in_transit","2026-01-06 22:37:13");
INSERT INTO `distribution_tracking` VALUES("513","95","23",NULL,"0","Lat: 2.248395, Lng: 102.310998","Live GPS tracking - Accuracy: 12.4m",NULL,"in_transit","2026-01-06 22:37:18");
INSERT INTO `distribution_tracking` VALUES("514","95","23",NULL,"0","Lat: 2.248722, Lng: 102.311489","Live GPS tracking - Accuracy: 13.2m",NULL,"in_transit","2026-01-06 22:37:23");
INSERT INTO `distribution_tracking` VALUES("515","95","23",NULL,"0","Lat: 2.248554, Lng: 102.311901","Live GPS tracking - Accuracy: 25.2m",NULL,"in_transit","2026-01-06 22:37:28");
INSERT INTO `distribution_tracking` VALUES("516","95","23",NULL,"0","Lat: 2.248431, Lng: 102.312339","Live GPS tracking - Accuracy: 25.3m",NULL,"in_transit","2026-01-06 22:37:32");
INSERT INTO `distribution_tracking` VALUES("517","95","23",NULL,"0","Lat: 2.248093, Lng: 102.312497","Live GPS tracking - Accuracy: 24.8m",NULL,"in_transit","2026-01-06 22:37:38");
INSERT INTO `distribution_tracking` VALUES("518","95","23",NULL,"0","Lat: 2.247737, Lng: 102.312033","Live GPS tracking - Accuracy: 17.8m",NULL,"in_transit","2026-01-06 22:37:43");
INSERT INTO `distribution_tracking` VALUES("519","95","23",NULL,"50","Near the affected area","","40","delayed","2026-01-06 22:37:51");
INSERT INTO `distribution_tracking` VALUES("520","96","23",NULL,"66","Near the affected area - Approaching disaster zone","","40","in_transit","2026-01-06 23:08:56");
INSERT INTO `distribution_tracking` VALUES("521","95","23",NULL,"50","Utem","","","arrived","2026-01-06 23:44:39");
INSERT INTO `distribution_tracking` VALUES("522","95","23",NULL,"50","Utem","","","arrived","2026-01-06 23:48:15");
INSERT INTO `distribution_tracking` VALUES("523","95","23",NULL,"50","Utem","","","arrived","2026-01-07 00:01:01");
INSERT INTO `distribution_tracking` VALUES("524","95","23",NULL,"50","Utem","","","arrived","2026-01-07 00:04:24");
INSERT INTO `distribution_tracking` VALUES("525","95","23",NULL,"50","University Teknikal Malaysia Melaka","","","in_transit","2026-01-07 00:25:02");
INSERT INTO `distribution_tracking` VALUES("526","95","23",NULL,"50","Taman Ayer Keroh Height, Ayer Keroh, Hang Tuah Jaya Municipal Council, Central Malacca, Malacca, 75450, Malaysia","","40","delayed","2026-01-07 00:43:22");
INSERT INTO `distribution_tracking` VALUES("527","95","23",NULL,"50","Taman Ayer Keroh Height, Ayer Keroh, Hang Tuah Jaya Municipal Council, Central Malacca, Malacca, 75450, Malaysia","","40","delayed","2026-01-07 00:55:11");
INSERT INTO `distribution_tracking` VALUES("528","95","23",NULL,"50","Taman Ayer Keroh Height, Ayer Keroh, Hang Tuah Jaya Municipal Council, Central Malacca, Malacca, 75450, Malaysia","","40","delayed","2026-01-07 00:55:23");
INSERT INTO `distribution_tracking` VALUES("529","95","23",NULL,"50","Taman Ayer Keroh Height, Ayer Keroh, Hang Tuah Jaya Municipal Council, Central Malacca, Malacca, 75450, Malaysia","","40","delayed","2026-01-07 01:00:39");
INSERT INTO `distribution_tracking` VALUES("530","95","23",NULL,"50","Ayer Keroh, Malaysia","","40","in_transit","2026-01-07 01:15:28");
INSERT INTO `distribution_tracking` VALUES("531","95","23",NULL,"50","Malacca, Malaysia","","40","in_transit","2026-01-07 01:45:44");
INSERT INTO `distribution_tracking` VALUES("532","104","23",NULL,"81","Malacca, Malaysia","","40","in_transit","2026-01-07 03:05:11");
INSERT INTO `distribution_tracking` VALUES("533","104","23",NULL,"81","Japan","","","in_transit","2026-01-07 03:08:14");
INSERT INTO `distribution_tracking` VALUES("534","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","","","in_transit","2026-01-07 03:08:32");
INSERT INTO `distribution_tracking` VALUES("535","104","23",NULL,"81","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-07 03:08:50");
INSERT INTO `distribution_tracking` VALUES("536","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","","","in_transit","2026-01-07 03:24:50");
INSERT INTO `distribution_tracking` VALUES("537","95","23",NULL,"50","Malacca, Malaysia","","40","departed","2026-01-07 03:51:10");
INSERT INTO `distribution_tracking` VALUES("538","95","23",NULL,"50","Malacca, Malaysia","","40","delayed","2026-01-07 03:51:43");
INSERT INTO `distribution_tracking` VALUES("539","95","23",NULL,"50","Malacca, Malaysia","","40","delayed","2026-01-07 03:51:58");
INSERT INTO `distribution_tracking` VALUES("540","95","23",NULL,"50","Malacca, Malaysia","","40","delayed","2026-01-07 03:52:07");
INSERT INTO `distribution_tracking` VALUES("541","95","23",NULL,"50","Malacca, Malaysia","","40","delayed","2026-01-07 03:52:24");
INSERT INTO `distribution_tracking` VALUES("542","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","hh","40","delayed","2026-01-07 03:52:51");
INSERT INTO `distribution_tracking` VALUES("543","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","hh","40","delayed","2026-01-07 03:53:07");
INSERT INTO `distribution_tracking` VALUES("544","104","23",NULL,"81","Main Highway - Heading towards destination","","","in_transit","2026-01-07 03:53:17");
INSERT INTO `distribution_tracking` VALUES("545","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","","","in_transit","2026-01-07 03:53:51");
INSERT INTO `distribution_tracking` VALUES("546","104","23",NULL,"81","田沢（赤面）林道, Kiryu, Gunma Prefecture, Japan","","","delayed","2026-01-07 03:54:00");
INSERT INTO `distribution_tracking` VALUES("547","104","23",NULL,"0","Lat: 36.597889, Lng: 99.84965","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 03:54:12");
INSERT INTO `distribution_tracking` VALUES("548","104","23",NULL,"81","Gonghe County, Hainan, Qinghai, 813000, China","","","delayed","2026-01-07 03:54:15");
INSERT INTO `distribution_tracking` VALUES("549","104","23",NULL,"0","Lat: 24.53361, Lng: 42.19614","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 03:54:34");
INSERT INTO `distribution_tracking` VALUES("550","104","23",NULL,"81","Afif, Riyadh Region, 00966, Saudi Arabia","dd","40","in_transit","2026-01-07 03:54:41");
INSERT INTO `distribution_tracking` VALUES("551","96","23",NULL,"0","Lat: 2.211349, Lng: 102.28956","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 15:55:47");
INSERT INTO `distribution_tracking` VALUES("552","96","23",NULL,"0","Lat: 2.211199, Lng: 102.29752","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 15:55:48");
INSERT INTO `distribution_tracking` VALUES("553","96","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 15:56:03");
INSERT INTO `distribution_tracking` VALUES("554","96","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 15:56:09");
INSERT INTO `distribution_tracking` VALUES("555","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-07 23:32:00");
INSERT INTO `distribution_tracking` VALUES("556","95","23",NULL,"0","Lat: 2.277318, Lng: 102.340741","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:04:32");
INSERT INTO `distribution_tracking` VALUES("557","95","23",NULL,"0","Lat: 2.266617, Lng: 102.316612","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:05:09");
INSERT INTO `distribution_tracking` VALUES("558","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:05:29");
INSERT INTO `distribution_tracking` VALUES("559","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:10:27");
INSERT INTO `distribution_tracking` VALUES("560","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:10:43");
INSERT INTO `distribution_tracking` VALUES("561","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:11:00");
INSERT INTO `distribution_tracking` VALUES("562","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:14:57");
INSERT INTO `distribution_tracking` VALUES("563","95","23",NULL,"0","Lat: 2.310968, Lng: 102.31464","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:15:29");
INSERT INTO `distribution_tracking` VALUES("564","95","23",NULL,"0","Lat: 5.798598, Lng: 102.517962","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:33:20");
INSERT INTO `distribution_tracking` VALUES("565","95","23",NULL,"0","Lat: 2.984555, Lng: 101.799135","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 00:47:36");
INSERT INTO `distribution_tracking` VALUES("566","95","23",NULL,"0","Lat: 2.580571, Lng: 102.205912","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 01:25:56");
INSERT INTO `distribution_tracking` VALUES("567","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 01:26:24");
INSERT INTO `distribution_tracking` VALUES("568","113","24",NULL,"40","NGO Prihatin Melaka - Bukit Beruang, Melaka","","30","departed","2026-01-08 01:59:13");
INSERT INTO `distribution_tracking` VALUES("569","113","24",NULL,"40","Distribution Center","Items loaded and ready for delivery",NULL,"departed","2026-01-08 01:59:38");
INSERT INTO `distribution_tracking` VALUES("570","113","24",NULL,"40","NGO Prihatin Melaka - Bukit Beruang, Melaka","","30","in_transit","2026-01-08 01:59:44");
INSERT INTO `distribution_tracking` VALUES("571","95","23",NULL,"50","KlebangTeam - klebang , melaka , 4261o","","40","in_transit","2026-01-08 10:09:40");
INSERT INTO `distribution_tracking` VALUES("572","104","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 23:09:48");
INSERT INTO `distribution_tracking` VALUES("573","104","23",NULL,"81","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-08 23:09:55");
INSERT INTO `distribution_tracking` VALUES("574","104","23",NULL,"0","Lat: 2.311775, Lng: 102.282144","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-08 23:16:43");
INSERT INTO `distribution_tracking` VALUES("575","104","23",NULL,"81","Durian Tunggal, Alor Gajah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, 76100, Malaysia","","40","delayed","2026-01-08 23:16:48");
INSERT INTO `distribution_tracking` VALUES("576","104","23",NULL,"81","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 00:07:11");
INSERT INTO `distribution_tracking` VALUES("577","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:06:12");
INSERT INTO `distribution_tracking` VALUES("578","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:06:19");
INSERT INTO `distribution_tracking` VALUES("579","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:07:41");
INSERT INTO `distribution_tracking` VALUES("580","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:07:57");
INSERT INTO `distribution_tracking` VALUES("581","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:08:02");
INSERT INTO `distribution_tracking` VALUES("582","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:08:44");
INSERT INTO `distribution_tracking` VALUES("583","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:09:08");
INSERT INTO `distribution_tracking` VALUES("584","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:09:21");
INSERT INTO `distribution_tracking` VALUES("585","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:09:26");
INSERT INTO `distribution_tracking` VALUES("586","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:24:46");
INSERT INTO `distribution_tracking` VALUES("587","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 01:26:50");
INSERT INTO `distribution_tracking` VALUES("588","95","23",NULL,"0","Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:27:11");
INSERT INTO `distribution_tracking` VALUES("589","95","23",NULL,"50","Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","in_transit","2026-01-09 01:27:15");
INSERT INTO `distribution_tracking` VALUES("590","95","23",NULL,"0","Lat: 2.302382, Lng: 102.275837","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:30:41");
INSERT INTO `distribution_tracking` VALUES("591","95","23",NULL,"0","Lat: 2.205909, Lng: 102.265203","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 01:30:52");
INSERT INTO `distribution_tracking` VALUES("592","113","24","Stadium Hang Jebat",NULL,"Distribution Center","Items loaded and ready for delivery to shelter",NULL,"departed","2026-01-09 22:36:18");
INSERT INTO `distribution_tracking` VALUES("593","113","24","Stadium Hang Jebat",NULL,"Lat: 2.250073, Lng: 102.255587","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:36:45");
INSERT INTO `distribution_tracking` VALUES("594","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Distribution Center","Items loaded and ready for delivery to shelter",NULL,"departed","2026-01-09 22:38:53");
INSERT INTO `distribution_tracking` VALUES("595","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.20805, Lng: 102.273361","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:39:13");
INSERT INTO `distribution_tracking` VALUES("596","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Malacca City, Malaysia","","40","delayed","2026-01-09 22:39:17");
INSERT INTO `distribution_tracking` VALUES("597","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Malacca City, Malaysia","","40","delayed","2026-01-09 22:39:34");
INSERT INTO `distribution_tracking` VALUES("598","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:39:42");
INSERT INTO `distribution_tracking` VALUES("599","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 22:39:50");
INSERT INTO `distribution_tracking` VALUES("600","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:40:49");
INSERT INTO `distribution_tracking` VALUES("601","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","30","delayed","2026-01-09 22:40:54");
INSERT INTO `distribution_tracking` VALUES("602","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:45:25");
INSERT INTO `distribution_tracking` VALUES("603","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","in_transit","2026-01-09 22:45:32");
INSERT INTO `distribution_tracking` VALUES("604","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","in_transit","2026-01-09 22:52:58");
INSERT INTO `distribution_tracking` VALUES("605","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 22:53:18");
INSERT INTO `distribution_tracking` VALUES("606","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 22:53:25");
INSERT INTO `distribution_tracking` VALUES("607","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 23:00:29");
INSERT INTO `distribution_tracking` VALUES("608","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 23:00:39");
INSERT INTO `distribution_tracking` VALUES("609","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 23:00:48");
INSERT INTO `distribution_tracking` VALUES("610","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 23:43:26");
INSERT INTO `distribution_tracking` VALUES("611","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.310659, Lng: 102.320416","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-09 23:43:38");
INSERT INTO `distribution_tracking` VALUES("612","95","23","Melaka Tengah Emergency Shelter 1",NULL,"Bulatan Laman Hikmah, Hang Tuah Jaya Municipal Council, Alor Gajah, Malacca, Malaysia","","40","delayed","2026-01-09 23:43:43");
INSERT INTO `distribution_tracking` VALUES("613","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Distribution Center","Items loaded and ready for delivery to shelter",NULL,"departed","2026-01-10 15:11:26");
INSERT INTO `distribution_tracking` VALUES("614","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.246125, Lng: 102.232927","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:11:41");
INSERT INTO `distribution_tracking` VALUES("615","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.257112, Lng: 102.253188","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:11:45");
INSERT INTO `distribution_tracking` VALUES("616","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.250926, Lng: 102.254133","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:11:48");
INSERT INTO `distribution_tracking` VALUES("617","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.250749, Lng: 102.254209","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:11:59");
INSERT INTO `distribution_tracking` VALUES("618","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Jalan Tunku Abdul Rahman, Batu Berendam, Hang Tuah Jaya Municipal Council, Central Malacca, Malacca, 75350, Malaysia","","40","delayed","2026-01-10 15:12:03");
INSERT INTO `distribution_tracking` VALUES("619","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.245695, Lng: 102.290792","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:53:37");
INSERT INTO `distribution_tracking` VALUES("620","113","24","Melaka Tengah Emergency Shelter 1",NULL,"Lat: 2.245438, Lng: 102.283838","Live GPS tracking - Accuracy: 10m",NULL,"in_transit","2026-01-10 15:53:38");
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
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `distribution_volunteer` VALUES("10","8","27","Packer","Assigned","2025-12-10 15:47:23",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("11","11","31","Packer","Assigned","2025-12-11 01:09:30",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("12","15","31","Distributor","Assigned","2025-12-11 01:09:30",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("13","13","31","Driver","Assigned","2025-12-11 01:09:30",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("14","1","31","Coordinator","Completed","2025-12-11 01:09:30","2025-12-25 11:32:38","2026-01-10 16:37:42");
INSERT INTO `distribution_volunteer` VALUES("15","12","32","Packer","Assigned","2025-12-11 07:59:07",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("16","14","32","Coordinator","Assigned","2025-12-11 07:59:07",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("17","2","33","Driver","Assigned","2025-12-11 09:40:15",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("18","3","33","Distributor","Assigned","2025-12-11 09:40:15",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("38","1","47","Distributor","Completed","2025-12-25 00:21:12",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("48","1","50","Coordinator","Completed","2025-12-25 12:22:42","2025-12-30 23:37:12","2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("53","1","52","Distributor","Completed","2025-12-25 12:51:21","2025-12-27 17:13:59","2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("54","1","53","Coordinator","Completed","2025-12-25 13:13:04",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("55","1","54","Packer","Completed","2025-12-25 13:45:02",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("56","1","55","Distributor","Completed","2025-12-25 13:46:24","2025-12-25 15:33:02","2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("57","1","56","Distributor","Completed","2025-12-25 14:50:29","2025-12-25 15:32:17","2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("58","1","57","Distributor","Completed","2025-12-25 15:38:45","2025-12-25 16:10:40","2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("89","22","87","Rescue","Assigned","2026-01-04 02:15:51",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("92","23","95","Rescue","Active","2026-01-04 04:38:49",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("93","23","96","Rescue","Active","2026-01-04 20:43:50",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("95","23","98","Rescue","Completed","2026-01-05 00:43:51",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("106","23","104","Rescue","Active","2026-01-07 02:45:36",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("108","24","113","Medical","Active","2026-01-08 01:31:30",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("115","24","114","Medical","Assigned","2026-01-08 10:56:47",NULL,"2026-01-10 14:57:26");
INSERT INTO `distribution_volunteer` VALUES("118","23","116","General Volunteer","Assigned","2026-01-09 19:45:12",NULL,"2026-01-10 14:57:26");
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
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `live_tracking` VALUES("1","23","95","2.24833698","102.31023121","17.0227",NULL,NULL,NULL,"100","1","2026-01-06 21:17:23");
INSERT INTO `live_tracking` VALUES("2","23","95","2.24804796","102.31027261","19.4344",NULL,NULL,NULL,"100","1","2026-01-06 21:17:25");
INSERT INTO `live_tracking` VALUES("3","23","95","2.24792214","102.31059186","20.7917",NULL,NULL,NULL,"100","1","2026-01-06 21:17:28");
INSERT INTO `live_tracking` VALUES("4","23","95","2.24767181","102.31071874","27.1704",NULL,NULL,NULL,"100","1","2026-01-06 21:17:30");
INSERT INTO `live_tracking` VALUES("5","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:17:32");
INSERT INTO `live_tracking` VALUES("6","23","95","2.24756209","102.31081828","29.0273",NULL,NULL,NULL,"100","1","2026-01-06 21:17:33");
INSERT INTO `live_tracking` VALUES("7","23","95","2.24786700","102.31128276","17.9148",NULL,NULL,NULL,"100","1","2026-01-06 21:17:35");
INSERT INTO `live_tracking` VALUES("8","23","95","2.24777068","102.31117617","13.5765",NULL,NULL,NULL,"100","1","2026-01-06 21:17:38");
INSERT INTO `live_tracking` VALUES("9","23","95","2.24780447","102.31105269","27.4586",NULL,NULL,NULL,"100","1","2026-01-06 21:17:40");
INSERT INTO `live_tracking` VALUES("10","23","95","2.24749560","102.31130114","14.1518",NULL,NULL,NULL,"100","1","2026-01-06 21:17:43");
INSERT INTO `live_tracking` VALUES("11","23","95","2.24725664","102.31121849","17.9689",NULL,NULL,NULL,"100","1","2026-01-06 21:17:45");
INSERT INTO `live_tracking` VALUES("12","23","95","2.24716890","102.31083213","10.7611",NULL,NULL,NULL,"100","1","2026-01-06 21:17:48");
INSERT INTO `live_tracking` VALUES("13","23","95","2.24708963","102.31116585","22.5661",NULL,NULL,NULL,"100","1","2026-01-06 21:17:50");
INSERT INTO `live_tracking` VALUES("14","23","95","2.24749491","102.31110684","14.7701",NULL,NULL,NULL,"100","1","2026-01-06 21:17:53");
INSERT INTO `live_tracking` VALUES("15","23","95","2.24779454","102.31115259","12.9778",NULL,NULL,NULL,"100","1","2026-01-06 21:17:55");
INSERT INTO `live_tracking` VALUES("16","23","95","2.24800146","102.31078165","28.9719",NULL,NULL,NULL,"100","1","2026-01-06 21:17:58");
INSERT INTO `live_tracking` VALUES("17","23","95","2.24752650","102.31039172","19.3957",NULL,NULL,NULL,"100","1","2026-01-06 21:18:00");
INSERT INTO `live_tracking` VALUES("18","23","95","2.24767193","102.31083988","10.0774",NULL,NULL,NULL,"100","1","2026-01-06 21:18:03");
INSERT INTO `live_tracking` VALUES("19","23","95","2.24717897","102.31132341","10.939",NULL,NULL,NULL,"100","1","2026-01-06 21:18:05");
INSERT INTO `live_tracking` VALUES("20","23","95","2.24753173","102.31173503","13.5872",NULL,NULL,NULL,"100","1","2026-01-06 21:18:08");
INSERT INTO `live_tracking` VALUES("21","23","95","2.24754703","102.31128477","29.6129",NULL,NULL,NULL,"100","1","2026-01-06 21:18:10");
INSERT INTO `live_tracking` VALUES("22","23","95","2.24760672","102.31130992","25.0396",NULL,NULL,NULL,"100","1","2026-01-06 21:18:13");
INSERT INTO `live_tracking` VALUES("23","23","95","2.24808003","102.31110917","13.6128",NULL,NULL,NULL,"100","1","2026-01-06 21:18:15");
INSERT INTO `live_tracking` VALUES("24","23","95","2.24857113","102.31128148","20.0768",NULL,NULL,NULL,"100","1","2026-01-06 21:18:18");
INSERT INTO `live_tracking` VALUES("25","23","95","2.24807944","102.31107846","14.9345",NULL,NULL,NULL,"100","1","2026-01-06 21:18:20");
INSERT INTO `live_tracking` VALUES("26","23","95","2.24762297","102.31098326","11.2572",NULL,NULL,NULL,"100","1","2026-01-06 21:18:23");
INSERT INTO `live_tracking` VALUES("27","23","95","2.24724452","102.31103541","11.9",NULL,NULL,NULL,"100","1","2026-01-06 21:18:25");
INSERT INTO `live_tracking` VALUES("28","23","95","2.24678552","102.31134537","16.1689",NULL,NULL,NULL,"100","1","2026-01-06 21:18:28");
INSERT INTO `live_tracking` VALUES("29","23","95","2.24702394","102.31095288","16.3015",NULL,NULL,NULL,"100","1","2026-01-06 21:18:30");
INSERT INTO `live_tracking` VALUES("30","23","95","2.24751252","102.31091021","26.3591",NULL,NULL,NULL,"100","1","2026-01-06 21:18:33");
INSERT INTO `live_tracking` VALUES("31","23","95","2.24741613","102.31070324","25.5674",NULL,NULL,NULL,"100","1","2026-01-06 21:18:35");
INSERT INTO `live_tracking` VALUES("32","23","95","2.24776419","102.31088042","10.0901",NULL,NULL,NULL,"100","1","2026-01-06 21:18:38");
INSERT INTO `live_tracking` VALUES("33","23","95","2.24799896","102.31079988","14.9061",NULL,NULL,NULL,"100","1","2026-01-06 21:18:40");
INSERT INTO `live_tracking` VALUES("34","23","95","2.24845142","102.31083338","19.0502",NULL,NULL,NULL,"100","1","2026-01-06 21:18:43");
INSERT INTO `live_tracking` VALUES("35","23","95","2.24800922","102.31071156","13.4167",NULL,NULL,NULL,"100","1","2026-01-06 21:18:45");
INSERT INTO `live_tracking` VALUES("36","23","95","2.24844946","102.31038502","12.1907",NULL,NULL,NULL,"100","1","2026-01-06 21:18:48");
INSERT INTO `live_tracking` VALUES("37","23","95","2.24866898","102.31060263","16.5386",NULL,NULL,NULL,"100","1","2026-01-06 21:18:50");
INSERT INTO `live_tracking` VALUES("38","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:18:51");
INSERT INTO `live_tracking` VALUES("39","23","95","2.24867848","102.31016449","29.8225",NULL,NULL,NULL,"100","1","2026-01-06 21:18:53");
INSERT INTO `live_tracking` VALUES("40","23","95","2.24846358","102.30969881","11.4296",NULL,NULL,NULL,"100","1","2026-01-06 21:18:55");
INSERT INTO `live_tracking` VALUES("41","23","95","2.24833768","102.31008670","12.5828",NULL,NULL,NULL,"100","1","2026-01-06 21:18:58");
INSERT INTO `live_tracking` VALUES("42","23","95","2.24864105","102.31028992","12.5269",NULL,NULL,NULL,"100","1","2026-01-06 21:19:00");
INSERT INTO `live_tracking` VALUES("43","23","95","2.24872911","102.30990105","11.2918",NULL,NULL,NULL,"100","1","2026-01-06 21:19:03");
INSERT INTO `live_tracking` VALUES("44","23","95","2.24845487","102.31007637","11.486",NULL,NULL,NULL,"100","1","2026-01-06 21:19:08");
INSERT INTO `live_tracking` VALUES("45","23","95","2.24838928","102.31027268","27.1493",NULL,NULL,NULL,"100","1","2026-01-06 21:19:13");
INSERT INTO `live_tracking` VALUES("46","23","95","2.24869755","102.31034904","27.788",NULL,NULL,NULL,"100","1","2026-01-06 21:19:20");
INSERT INTO `live_tracking` VALUES("47","23","95","2.24848071","102.31039999","24.3178",NULL,NULL,NULL,"100","1","2026-01-06 21:19:22");
INSERT INTO `live_tracking` VALUES("48","23","95","2.24878417","102.31049408","17.1272",NULL,NULL,NULL,"100","1","2026-01-06 21:19:27");
INSERT INTO `live_tracking` VALUES("49","23","95","2.24866470","102.31028813","27.3089",NULL,NULL,NULL,"100","1","2026-01-06 21:19:32");
INSERT INTO `live_tracking` VALUES("50","23","95","2.24900758","102.31071421","18.3168",NULL,NULL,NULL,"100","1","2026-01-06 21:19:37");
INSERT INTO `live_tracking` VALUES("51","23","95","2.24894716","102.31044031","26.6529",NULL,NULL,NULL,"100","1","2026-01-06 21:19:42");
INSERT INTO `live_tracking` VALUES("52","23","95","2.24887571","102.31057434","22.1963",NULL,NULL,NULL,"100","1","2026-01-06 21:19:47");
INSERT INTO `live_tracking` VALUES("53","23","95","2.24854697","102.31061361","17.0302",NULL,NULL,NULL,"100","1","2026-01-06 21:19:52");
INSERT INTO `live_tracking` VALUES("54","23","95","2.24844406","102.31020730","24.6084",NULL,NULL,NULL,"100","1","2026-01-06 21:19:57");
INSERT INTO `live_tracking` VALUES("55","23","95","2.24819082","102.31027093","14.0386",NULL,NULL,NULL,"100","1","2026-01-06 21:20:02");
INSERT INTO `live_tracking` VALUES("56","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:20:05");
INSERT INTO `live_tracking` VALUES("57","23","95","2.24770153","102.31056714","23.9398",NULL,NULL,NULL,"100","1","2026-01-06 21:20:07");
INSERT INTO `live_tracking` VALUES("58","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:20:08");
INSERT INTO `live_tracking` VALUES("59","23","95","2.24806014","102.31023249","18.4665",NULL,NULL,NULL,"100","1","2026-01-06 21:20:12");
INSERT INTO `live_tracking` VALUES("60","23","95","2.24816585","102.31010922","29.1224",NULL,NULL,NULL,"100","1","2026-01-06 21:20:17");
INSERT INTO `live_tracking` VALUES("61","23","95","2.24775117","102.31035958","10.5664",NULL,NULL,NULL,"100","1","2026-01-06 21:20:22");
INSERT INTO `live_tracking` VALUES("62","23","95","2.24795611","102.31064117","29.4454",NULL,NULL,NULL,"100","1","2026-01-06 21:20:27");
INSERT INTO `live_tracking` VALUES("63","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:20:31");
INSERT INTO `live_tracking` VALUES("64","23","95","2.24754685","102.31069196","10.274",NULL,NULL,NULL,"100","1","2026-01-06 21:20:32");
INSERT INTO `live_tracking` VALUES("65","23","95","2.25320000","102.31560000","5",NULL,NULL,NULL,"100","1","2026-01-06 21:20:32");
INSERT INTO `live_tracking` VALUES("66","23","95","2.24717550","102.31113624","18.115",NULL,NULL,NULL,"100","1","2026-01-06 21:20:37");
INSERT INTO `live_tracking` VALUES("67","23","95","2.24739029","102.31154788","20.8703",NULL,NULL,NULL,"100","1","2026-01-06 21:20:42");
INSERT INTO `live_tracking` VALUES("68","23","95","2.24727631","102.31112564","22.2524",NULL,NULL,NULL,"100","1","2026-01-06 21:20:47");
INSERT INTO `live_tracking` VALUES("69","23","95","2.24703984","102.31155793","25.1348",NULL,NULL,NULL,"100","1","2026-01-06 21:20:52");
INSERT INTO `live_tracking` VALUES("70","23","95","2.24744043","102.31153828","27.9887",NULL,NULL,NULL,"100","1","2026-01-06 21:20:57");
INSERT INTO `live_tracking` VALUES("71","23","95","2.24749284","102.31112816","18.9988",NULL,NULL,NULL,"100","1","2026-01-06 21:21:02");
INSERT INTO `live_tracking` VALUES("72","23","95","2.24752150","102.31143485","16.4913",NULL,NULL,NULL,"100","1","2026-01-06 21:21:07");
INSERT INTO `live_tracking` VALUES("73","23","95","2.24747384","102.31125684","14.6889",NULL,NULL,NULL,"100","1","2026-01-06 21:21:12");
INSERT INTO `live_tracking` VALUES("74","23","95","2.24750930","102.31138789","11.3227",NULL,NULL,NULL,"100","1","2026-01-06 21:21:17");
INSERT INTO `live_tracking` VALUES("75","23","95","2.24736797","102.31172704","24.5247",NULL,NULL,NULL,"100","1","2026-01-06 21:21:22");
INSERT INTO `live_tracking` VALUES("76","23","95","2.24741418","102.31196012","25.4627",NULL,NULL,NULL,"100","1","2026-01-06 21:21:27");
INSERT INTO `live_tracking` VALUES("77","23","95","2.24728056","102.31204036","17.9264",NULL,NULL,NULL,"100","1","2026-01-06 21:21:32");
INSERT INTO `live_tracking` VALUES("78","23","95","2.24767676","102.31195895","24.6589",NULL,NULL,NULL,"100","1","2026-01-06 21:21:37");
INSERT INTO `live_tracking` VALUES("79","23","95","2.24732510","102.31235396","16.353",NULL,NULL,NULL,"100","1","2026-01-06 21:21:42");
INSERT INTO `live_tracking` VALUES("80","23","95","2.24701916","102.31222803","17.459",NULL,NULL,NULL,"100","1","2026-01-06 21:21:47");
INSERT INTO `live_tracking` VALUES("81","23","95","2.24713820","102.31226849","12.7626",NULL,NULL,NULL,"100","1","2026-01-06 21:21:52");
INSERT INTO `live_tracking` VALUES("82","23","95","2.24688273","102.31214173","13.1859",NULL,NULL,NULL,"100","1","2026-01-06 21:21:57");
INSERT INTO `live_tracking` VALUES("83","23","95","2.24680591","102.31264159","10.8592",NULL,NULL,NULL,"100","1","2026-01-06 21:22:02");
INSERT INTO `live_tracking` VALUES("84","23","95","2.24682822","102.31273407","18.8832",NULL,NULL,NULL,"100","1","2026-01-06 21:22:07");
INSERT INTO `live_tracking` VALUES("85","23","95","2.24692735","102.31246155","10.6164",NULL,NULL,NULL,"100","1","2026-01-06 21:22:12");
INSERT INTO `live_tracking` VALUES("86","23","95","2.24737660","102.31292336","25.5052",NULL,NULL,NULL,"100","1","2026-01-06 21:22:17");
INSERT INTO `live_tracking` VALUES("87","23","95","2.24740457","102.31249684","11.6998",NULL,NULL,NULL,"100","1","2026-01-06 21:22:22");
INSERT INTO `live_tracking` VALUES("88","23","95","2.24772972","102.31225690","12.7453",NULL,NULL,NULL,"100","1","2026-01-06 21:22:27");
INSERT INTO `live_tracking` VALUES("89","23","95","2.24780301","102.31245555","23.1082",NULL,NULL,NULL,"100","1","2026-01-06 21:22:32");
INSERT INTO `live_tracking` VALUES("90","23","95","2.24767159","102.31197318","21.2582",NULL,NULL,NULL,"100","1","2026-01-06 21:22:37");
INSERT INTO `live_tracking` VALUES("91","23","95","2.24737417","102.31184401","25.0672",NULL,NULL,NULL,"100","1","2026-01-06 21:22:42");
INSERT INTO `live_tracking` VALUES("92","23","95","2.24725337","102.31154493","10.156",NULL,NULL,NULL,"100","1","2026-01-06 21:22:47");
INSERT INTO `live_tracking` VALUES("93","23","95","2.24679901","102.31169179","21.1878",NULL,NULL,NULL,"100","1","2026-01-06 21:22:52");
INSERT INTO `live_tracking` VALUES("94","23","95","2.24631595","102.31142580","25.4354",NULL,NULL,NULL,"100","1","2026-01-06 21:22:57");
INSERT INTO `live_tracking` VALUES("95","23","95","2.24650237","102.31111936","10.6419",NULL,NULL,NULL,"100","1","2026-01-06 21:23:06");
INSERT INTO `live_tracking` VALUES("96","23","95","2.24673761","102.31119581","20.2285",NULL,NULL,NULL,"100","1","2026-01-06 21:23:07");
INSERT INTO `live_tracking` VALUES("97","23","95","2.24651246","102.31131228","15.1524",NULL,NULL,NULL,"100","1","2026-01-06 21:23:12");
INSERT INTO `live_tracking` VALUES("98","23","95","2.24687379","102.31115654","21.7194",NULL,NULL,NULL,"100","1","2026-01-06 21:23:17");
INSERT INTO `live_tracking` VALUES("99","23","95","2.24731156","102.31132121","13.8722",NULL,NULL,NULL,"100","1","2026-01-06 21:23:22");
INSERT INTO `live_tracking` VALUES("100","23","95","2.24752309","102.31152281","15.6993",NULL,NULL,NULL,"100","1","2026-01-06 21:23:27");
INSERT INTO `live_tracking` VALUES("101","23","95","2.24801462","102.31131597","12.0951",NULL,NULL,NULL,"100","1","2026-01-06 21:23:32");
INSERT INTO `live_tracking` VALUES("102","23","95","2.24781288","102.31150108","28.6782",NULL,NULL,NULL,"100","1","2026-01-06 21:23:37");
INSERT INTO `live_tracking` VALUES("103","23","95","2.24790136","102.31197946","15.8554",NULL,NULL,NULL,"100","1","2026-01-06 21:23:42");
INSERT INTO `live_tracking` VALUES("104","23","95","2.24785445","102.31187109","13.975",NULL,NULL,NULL,"100","1","2026-01-06 21:23:47");
INSERT INTO `live_tracking` VALUES("105","23","95","2.24832210","102.31233470","17.6956",NULL,NULL,NULL,"100","1","2026-01-06 21:23:52");
INSERT INTO `live_tracking` VALUES("106","23","95","2.24788053","102.31274933","14.9724",NULL,NULL,NULL,"100","1","2026-01-06 21:23:57");
INSERT INTO `live_tracking` VALUES("107","23","95","2.24777261","102.31238010","26.7977",NULL,NULL,NULL,"100","1","2026-01-06 21:24:02");
INSERT INTO `live_tracking` VALUES("108","23","95","2.24790948","102.31277447","14.7002",NULL,NULL,NULL,"100","1","2026-01-06 21:24:07");
INSERT INTO `live_tracking` VALUES("109","23","95","2.24774020","102.31262214","26.5232",NULL,NULL,NULL,"100","1","2026-01-06 21:24:12");
INSERT INTO `live_tracking` VALUES("110","23","95","2.24775606","102.31237312","18.0866",NULL,NULL,NULL,"100","1","2026-01-06 21:24:17");
INSERT INTO `live_tracking` VALUES("111","23","95","2.24763049","102.31227858","22.9481",NULL,NULL,NULL,"100","1","2026-01-06 21:24:22");
INSERT INTO `live_tracking` VALUES("112","23","95","2.24770073","102.31261838","10.5244",NULL,NULL,NULL,"100","1","2026-01-06 21:24:27");
INSERT INTO `live_tracking` VALUES("113","23","95","2.24738000","102.31247443","27.5147",NULL,NULL,NULL,"100","1","2026-01-06 21:24:32");
INSERT INTO `live_tracking` VALUES("114","23","95","2.24784829","102.31199778","20.8429",NULL,NULL,NULL,"100","1","2026-01-06 21:24:37");
INSERT INTO `live_tracking` VALUES("115","23","95","2.24760721","102.31240232","21.001",NULL,NULL,NULL,"100","1","2026-01-06 21:24:42");
INSERT INTO `live_tracking` VALUES("116","23","95","2.24749246","102.31245661","21.8875",NULL,NULL,NULL,"100","1","2026-01-06 21:24:47");
INSERT INTO `live_tracking` VALUES("117","23","95","2.24783419","102.31292739","11.8986",NULL,NULL,NULL,"100","1","2026-01-06 21:24:52");
INSERT INTO `live_tracking` VALUES("118","23","95","2.24820599","102.31304836","25.5756",NULL,NULL,NULL,"100","1","2026-01-06 21:24:57");
INSERT INTO `live_tracking` VALUES("119","23","95","2.24827534","102.31349030","29.5905",NULL,NULL,NULL,"100","1","2026-01-06 21:25:06");
INSERT INTO `live_tracking` VALUES("120","23","95","2.24832736","102.31361287","19.8352",NULL,NULL,NULL,"100","1","2026-01-06 21:25:13");
INSERT INTO `live_tracking` VALUES("121","23","95","2.24809285","102.31409453","22.6522",NULL,NULL,NULL,"100","1","2026-01-06 21:25:17");
INSERT INTO `live_tracking` VALUES("122","23","95","2.24766421","102.31433400","27.6951",NULL,NULL,NULL,"100","1","2026-01-06 21:25:22");
INSERT INTO `live_tracking` VALUES("123","23","95","2.24863605","102.31026852","20.987",NULL,NULL,NULL,"100","1","2026-01-06 21:25:25");
INSERT INTO `live_tracking` VALUES("124","23","95","2.24880300","102.30986023","11.8791",NULL,NULL,NULL,"100","1","2026-01-06 21:25:27");
INSERT INTO `live_tracking` VALUES("125","23","95","2.24849477","102.31104856","11.384",NULL,NULL,NULL,"100","1","2026-01-06 21:25:29");
INSERT INTO `live_tracking` VALUES("126","23","95","2.24815576","102.31138585","15.3092",NULL,NULL,NULL,"100","1","2026-01-06 21:25:30");
INSERT INTO `live_tracking` VALUES("127","23","95","2.24801414","102.31134709","11.4468",NULL,NULL,NULL,"100","1","2026-01-06 21:25:32");
INSERT INTO `live_tracking` VALUES("128","23","95","2.24845385","102.31143036","15.5513",NULL,NULL,NULL,"100","1","2026-01-06 21:25:35");
INSERT INTO `live_tracking` VALUES("129","23","95","2.24816780","102.31183646","17.333",NULL,NULL,NULL,"100","1","2026-01-06 21:25:36");
INSERT INTO `live_tracking` VALUES("130","23","95","2.24848218","102.31195480","10.8363",NULL,NULL,NULL,"100","1","2026-01-06 21:25:37");
INSERT INTO `live_tracking` VALUES("131","23","95","2.24863312","102.31149152","14.2461",NULL,NULL,NULL,"100","1","2026-01-06 21:25:40");
INSERT INTO `live_tracking` VALUES("132","23","95","2.24913018","102.31118157","23.866",NULL,NULL,NULL,"100","1","2026-01-06 21:25:40");
INSERT INTO `live_tracking` VALUES("133","23","95","2.24876901","102.31075771","21.5195",NULL,NULL,NULL,"100","1","2026-01-06 21:25:42");
INSERT INTO `live_tracking` VALUES("134","23","95","2.24836251","102.31110932","19.1004",NULL,NULL,NULL,"100","1","2026-01-06 21:25:44");
INSERT INTO `live_tracking` VALUES("135","23","95","2.24871646","102.31108564","24.1049",NULL,NULL,NULL,"100","1","2026-01-06 21:25:45");
INSERT INTO `live_tracking` VALUES("136","23","95","2.24878587","102.31143810","28.6945",NULL,NULL,NULL,"100","1","2026-01-06 21:25:47");
INSERT INTO `live_tracking` VALUES("137","23","95","2.24886000","102.31182283","15.5056",NULL,NULL,NULL,"100","1","2026-01-06 21:25:49");
INSERT INTO `live_tracking` VALUES("138","23","95","2.24846216","102.31222530","24.8292",NULL,NULL,NULL,"100","1","2026-01-06 21:25:50");
INSERT INTO `live_tracking` VALUES("139","23","95","2.24841262","102.31232429","23.6277",NULL,NULL,NULL,"100","1","2026-01-06 21:25:52");
INSERT INTO `live_tracking` VALUES("140","23","95","2.24833090","102.31246602","28.644",NULL,NULL,NULL,"100","1","2026-01-06 21:25:54");
INSERT INTO `live_tracking` VALUES("141","23","95","2.24819230","102.31289600","13.4373",NULL,NULL,NULL,"100","1","2026-01-06 21:25:55");
INSERT INTO `live_tracking` VALUES("142","23","95","2.24769699","102.31244409","21.9808",NULL,NULL,NULL,"100","1","2026-01-06 21:25:57");
INSERT INTO `live_tracking` VALUES("143","23","95","2.24802616","102.31238936","24.7621",NULL,NULL,NULL,"100","1","2026-01-06 21:25:59");
INSERT INTO `live_tracking` VALUES("144","23","95","2.24814334","102.31256871","12.1932",NULL,NULL,NULL,"100","1","2026-01-06 21:26:00");
INSERT INTO `live_tracking` VALUES("145","23","95","2.24833400","102.31222985","13.1799",NULL,NULL,NULL,"100","1","2026-01-06 21:26:02");
INSERT INTO `live_tracking` VALUES("146","23","95","2.24874423","102.31244960","12.7113",NULL,NULL,NULL,"100","1","2026-01-06 21:26:04");
INSERT INTO `live_tracking` VALUES("147","23","95","2.24835692","102.31226424","23.3283",NULL,NULL,NULL,"100","1","2026-01-06 21:26:05");
INSERT INTO `live_tracking` VALUES("148","23","95","2.24797818","102.31215925","10.3936",NULL,NULL,NULL,"100","1","2026-01-06 21:26:07");
INSERT INTO `live_tracking` VALUES("149","23","95","2.24805242","102.31261954","25.8821",NULL,NULL,NULL,"100","1","2026-01-06 21:26:09");
INSERT INTO `live_tracking` VALUES("150","23","95","2.24845435","102.31219636","17.7303",NULL,NULL,NULL,"100","1","2026-01-06 21:26:10");
INSERT INTO `live_tracking` VALUES("151","23","95","2.24854966","102.31242201","22.1605",NULL,NULL,NULL,"100","1","2026-01-06 21:26:12");
INSERT INTO `live_tracking` VALUES("152","23","95","2.24812084","102.31262188","17.6813",NULL,NULL,NULL,"100","1","2026-01-06 21:26:14");
INSERT INTO `live_tracking` VALUES("153","23","95","2.24855819","102.31279635","13.5956",NULL,NULL,NULL,"100","1","2026-01-06 21:26:15");
INSERT INTO `live_tracking` VALUES("154","23","95","2.24875153","102.31270443","17.8859",NULL,NULL,NULL,"100","1","2026-01-06 21:26:17");
INSERT INTO `live_tracking` VALUES("155","23","95","2.24847705","102.31238319","11.4342",NULL,NULL,NULL,"100","1","2026-01-06 21:26:19");
INSERT INTO `live_tracking` VALUES("156","23","95","2.24826341","102.31194734","26.2975",NULL,NULL,NULL,"100","1","2026-01-06 21:26:20");
INSERT INTO `live_tracking` VALUES("157","23","95","2.24857963","102.31183387","17.8382",NULL,NULL,NULL,"100","1","2026-01-06 21:26:22");
INSERT INTO `live_tracking` VALUES("158","23","95","2.24869180","102.31215228","25.5561",NULL,NULL,NULL,"100","1","2026-01-06 21:26:24");
INSERT INTO `live_tracking` VALUES("159","23","95","2.24879638","102.31242303","21.3952",NULL,NULL,NULL,"100","1","2026-01-06 21:26:25");
INSERT INTO `live_tracking` VALUES("160","23","95","2.24911990","102.31285986","17.1967",NULL,NULL,NULL,"100","1","2026-01-06 21:26:27");
INSERT INTO `live_tracking` VALUES("161","23","95","2.24920331","102.31315491","17.9608",NULL,NULL,NULL,"100","1","2026-01-06 21:26:29");
INSERT INTO `live_tracking` VALUES("162","23","95","2.24945689","102.31289439","29.7618",NULL,NULL,NULL,"100","1","2026-01-06 21:26:30");
INSERT INTO `live_tracking` VALUES("163","23","95","2.24940497","102.31283595","11.373",NULL,NULL,NULL,"100","1","2026-01-06 21:26:32");
INSERT INTO `live_tracking` VALUES("164","23","95","2.24967473","102.31298838","21.7164",NULL,NULL,NULL,"100","1","2026-01-06 21:26:34");
INSERT INTO `live_tracking` VALUES("165","23","95","2.24995517","102.31291061","21.7161",NULL,NULL,NULL,"100","1","2026-01-06 21:26:35");
INSERT INTO `live_tracking` VALUES("166","23","95","2.25021346","102.31257800","26.1414",NULL,NULL,NULL,"100","1","2026-01-06 21:26:37");
INSERT INTO `live_tracking` VALUES("167","23","95","2.25017211","102.31216105","14.1247",NULL,NULL,NULL,"100","1","2026-01-06 21:26:39");
INSERT INTO `live_tracking` VALUES("168","23","95","2.25003267","102.31260380","23.4054",NULL,NULL,NULL,"100","1","2026-01-06 21:26:40");
INSERT INTO `live_tracking` VALUES("169","23","95","2.24964804","102.31223511","27.4312",NULL,NULL,NULL,"100","1","2026-01-06 21:26:42");
INSERT INTO `live_tracking` VALUES("170","23","95","2.25002983","102.31230360","22.4858",NULL,NULL,NULL,"100","1","2026-01-06 21:26:44");
INSERT INTO `live_tracking` VALUES("171","23","95","2.24966722","102.31184339","12.5877",NULL,NULL,NULL,"100","1","2026-01-06 21:26:45");
INSERT INTO `live_tracking` VALUES("172","23","95","2.24925007","102.31139443","13.1424",NULL,NULL,NULL,"100","1","2026-01-06 21:26:47");
INSERT INTO `live_tracking` VALUES("173","23","95","2.24908126","102.31132967","15.4229",NULL,NULL,NULL,"100","1","2026-01-06 21:26:49");
INSERT INTO `live_tracking` VALUES("174","23","95","2.24944140","102.31124832","28.1914",NULL,NULL,NULL,"100","1","2026-01-06 21:26:50");
INSERT INTO `live_tracking` VALUES("175","23","95","2.24926114","102.31087400","25.0925",NULL,NULL,NULL,"100","1","2026-01-06 21:26:52");
INSERT INTO `live_tracking` VALUES("176","23","95","2.24905459","102.31077578","14.4375",NULL,NULL,NULL,"100","1","2026-01-06 21:26:54");
INSERT INTO `live_tracking` VALUES("177","23","95","2.24953301","102.31086687","21.6821",NULL,NULL,NULL,"100","1","2026-01-06 21:26:55");
INSERT INTO `live_tracking` VALUES("178","23","95","2.24982591","102.31105811","21.8438",NULL,NULL,NULL,"100","1","2026-01-06 21:26:57");
INSERT INTO `live_tracking` VALUES("179","23","95","2.24956746","102.31125989","28.5105",NULL,NULL,NULL,"100","1","2026-01-06 21:26:59");
INSERT INTO `live_tracking` VALUES("180","23","95","2.24984343","102.31128420","28.7017",NULL,NULL,NULL,"100","1","2026-01-06 21:27:00");
INSERT INTO `live_tracking` VALUES("181","23","95","2.24980727","102.31093296","12.6999",NULL,NULL,NULL,"100","1","2026-01-06 21:27:02");
INSERT INTO `live_tracking` VALUES("182","23","95","2.24943292","102.31052883","25.9988",NULL,NULL,NULL,"100","1","2026-01-06 21:27:05");
INSERT INTO `live_tracking` VALUES("183","23","95","2.24904584","102.31028814","11.8292",NULL,NULL,NULL,"100","1","2026-01-06 21:27:06");
INSERT INTO `live_tracking` VALUES("184","23","95","2.24912139","102.31023077","26.4122",NULL,NULL,NULL,"100","1","2026-01-06 21:27:07");
INSERT INTO `live_tracking` VALUES("185","23","95","2.24918352","102.30994997","17.8701",NULL,NULL,NULL,"100","1","2026-01-06 21:27:10");
INSERT INTO `live_tracking` VALUES("186","23","95","2.24879337","102.31017941","10.0501",NULL,NULL,NULL,"100","1","2026-01-06 21:27:11");
INSERT INTO `live_tracking` VALUES("187","23","95","2.24884187","102.31034204","29.7843",NULL,NULL,NULL,"100","1","2026-01-06 21:27:12");
INSERT INTO `live_tracking` VALUES("188","23","95","2.24875566","102.31035249","21.1504",NULL,NULL,NULL,"100","1","2026-01-06 21:27:15");
INSERT INTO `live_tracking` VALUES("189","23","95","2.24826589","102.31070655","11.1447",NULL,NULL,NULL,"100","1","2026-01-06 21:27:16");
INSERT INTO `live_tracking` VALUES("190","23","95","2.24777723","102.31058699","13.7129",NULL,NULL,NULL,"100","1","2026-01-06 21:27:17");
INSERT INTO `live_tracking` VALUES("191","23","95","2.24818232","102.31036997","28.971",NULL,NULL,NULL,"100","1","2026-01-06 21:27:19");
INSERT INTO `live_tracking` VALUES("192","23","96","2.20903417","102.29046472","17.9287",NULL,NULL,NULL,"100","1","2026-01-06 21:51:39");
INSERT INTO `live_tracking` VALUES("193","23","96","2.20931120","102.29082989","29.3885",NULL,NULL,NULL,"100","1","2026-01-06 21:51:44");
INSERT INTO `live_tracking` VALUES("194","23","96","2.20980825","102.29048139","18.4654",NULL,NULL,NULL,"100","1","2026-01-06 21:51:50");
INSERT INTO `live_tracking` VALUES("195","23","96","2.20952613","102.29074238","19.1847",NULL,NULL,NULL,"100","1","2026-01-06 21:51:55");
INSERT INTO `live_tracking` VALUES("196","23","96","2.20917132","102.29061009","14.3534",NULL,NULL,NULL,"100","1","2026-01-06 21:52:00");
INSERT INTO `live_tracking` VALUES("197","23","96","2.20883528","102.29104025","10.3302",NULL,NULL,NULL,"100","1","2026-01-06 21:52:04");
INSERT INTO `live_tracking` VALUES("198","23","95","2.24790955","102.31070219","11.5144",NULL,NULL,NULL,"100","1","2026-01-06 21:52:36");
INSERT INTO `live_tracking` VALUES("199","23","95","2.24830885","102.31087795","12.673",NULL,NULL,NULL,"100","1","2026-01-06 21:52:41");
INSERT INTO `live_tracking` VALUES("200","23","95","2.24790781","102.31042353","22.2024",NULL,NULL,NULL,"100","1","2026-01-06 21:52:46");
INSERT INTO `live_tracking` VALUES("201","23","95","2.24796832","102.31030528","11.4248",NULL,NULL,NULL,"100","1","2026-01-06 21:52:51");
INSERT INTO `live_tracking` VALUES("202","23","95","2.24754686","102.30990885","12.7212",NULL,NULL,NULL,"100","1","2026-01-06 21:52:56");
INSERT INTO `live_tracking` VALUES("203","23","95","2.24727167","102.30990816","29.6336",NULL,NULL,NULL,"100","1","2026-01-06 21:53:01");
INSERT INTO `live_tracking` VALUES("204","23","95","2.24685535","102.31020949","26.3683",NULL,NULL,NULL,"100","1","2026-01-06 21:53:06");
INSERT INTO `live_tracking` VALUES("205","23","95","2.24636777","102.31052614","14.5587",NULL,NULL,NULL,"100","1","2026-01-06 21:53:11");
INSERT INTO `live_tracking` VALUES("206","23","95","2.24641878","102.31080684","27.4755",NULL,NULL,NULL,"100","1","2026-01-06 21:53:16");
INSERT INTO `live_tracking` VALUES("207","23","95","2.24615213","102.31117309","21.7211",NULL,NULL,NULL,"100","1","2026-01-06 21:53:21");
INSERT INTO `live_tracking` VALUES("208","23","95","2.24605959","102.31078407","22.0726",NULL,NULL,NULL,"100","1","2026-01-06 21:53:26");
INSERT INTO `live_tracking` VALUES("209","23","95","2.24595239","102.31080352","10.8913",NULL,NULL,NULL,"100","1","2026-01-06 21:53:31");
INSERT INTO `live_tracking` VALUES("210","23","95","2.24624088","102.31059364","14.0521",NULL,NULL,NULL,"100","1","2026-01-06 21:53:36");
INSERT INTO `live_tracking` VALUES("211","23","95","2.24611627","102.31101902","14.7856",NULL,NULL,NULL,"100","1","2026-01-06 21:53:41");
INSERT INTO `live_tracking` VALUES("212","23","95","2.24643454","102.31062248","18.1643",NULL,NULL,NULL,"100","1","2026-01-06 21:53:46");
INSERT INTO `live_tracking` VALUES("213","23","95","2.24676391","102.31051825","16.6984",NULL,NULL,NULL,"100","1","2026-01-06 21:53:51");
INSERT INTO `live_tracking` VALUES("214","23","95","2.24700816","102.31012461","26.9098",NULL,NULL,NULL,"100","1","2026-01-06 21:53:56");
INSERT INTO `live_tracking` VALUES("215","23","95","2.24731737","102.30978597","20.769",NULL,NULL,NULL,"100","1","2026-01-06 21:54:01");
INSERT INTO `live_tracking` VALUES("216","23","95","2.24709531","102.30988371","10.2907",NULL,NULL,NULL,"100","1","2026-01-06 21:54:06");
INSERT INTO `live_tracking` VALUES("217","23","95","2.24688502","102.30993841","19.6948",NULL,NULL,NULL,"100","1","2026-01-06 21:54:11");
INSERT INTO `live_tracking` VALUES("218","23","95","2.24675173","102.31002123","23.9601",NULL,NULL,NULL,"100","1","2026-01-06 21:54:16");
INSERT INTO `live_tracking` VALUES("219","23","95","2.24641571","102.30977844","25.6801",NULL,NULL,NULL,"100","1","2026-01-06 21:54:21");
INSERT INTO `live_tracking` VALUES("220","23","95","2.24611750","102.31026623","18.845",NULL,NULL,NULL,"100","1","2026-01-06 21:54:26");
INSERT INTO `live_tracking` VALUES("221","23","95","2.24868909","102.31022673","26.823",NULL,NULL,NULL,"100","1","2026-01-06 21:56:07");
INSERT INTO `live_tracking` VALUES("222","23","95","2.24873147","102.31038444","29.4748",NULL,NULL,NULL,"100","1","2026-01-06 21:56:17");
INSERT INTO `live_tracking` VALUES("223","23","95","2.24828771","102.31053217","22.3457",NULL,NULL,NULL,"100","1","2026-01-06 21:56:19");
INSERT INTO `live_tracking` VALUES("224","23","95","2.24851852","102.31060799","15.6878",NULL,NULL,NULL,"100","1","2026-01-06 21:56:24");
INSERT INTO `live_tracking` VALUES("225","23","95","2.24889586","102.31089757","29.1551",NULL,NULL,NULL,"100","1","2026-01-06 21:56:31");
INSERT INTO `live_tracking` VALUES("226","23","95","2.24839108","102.31056729","24.251",NULL,NULL,NULL,"100","1","2026-01-06 21:56:33");
INSERT INTO `live_tracking` VALUES("227","23","95","2.24831460","102.31091773","23.8216",NULL,NULL,NULL,"100","1","2026-01-06 21:56:38");
INSERT INTO `live_tracking` VALUES("228","23","95","2.24813634","102.31092907","17.4632",NULL,NULL,NULL,"100","1","2026-01-06 21:56:43");
INSERT INTO `live_tracking` VALUES("229","23","95","2.24803856","102.31125512","18.1423",NULL,NULL,NULL,"100","1","2026-01-06 21:56:48");
INSERT INTO `live_tracking` VALUES("230","23","95","2.24837588","102.31131724","12.6432",NULL,NULL,NULL,"100","1","2026-01-06 21:56:53");
INSERT INTO `live_tracking` VALUES("231","23","95","2.24794968","102.31148328","29.2315",NULL,NULL,NULL,"100","1","2026-01-06 21:56:58");
INSERT INTO `live_tracking` VALUES("232","23","95","2.24838102","102.31197345","11.3034",NULL,NULL,NULL,"100","1","2026-01-06 21:57:03");
INSERT INTO `live_tracking` VALUES("233","23","95","2.24833648","102.31214141","22.1623",NULL,NULL,NULL,"100","1","2026-01-06 21:57:08");
INSERT INTO `live_tracking` VALUES("234","23","95","2.24850226","102.31194136","25.5907",NULL,NULL,NULL,"100","1","2026-01-06 21:57:13");
INSERT INTO `live_tracking` VALUES("235","23","95","2.24875238","102.31155298","27.592",NULL,NULL,NULL,"100","1","2026-01-06 21:57:18");
INSERT INTO `live_tracking` VALUES("236","23","95","2.24913681","102.31126817","13.5996",NULL,NULL,NULL,"100","1","2026-01-06 21:57:23");
INSERT INTO `live_tracking` VALUES("237","23","95","2.24898165","102.31112110","27.7536",NULL,NULL,NULL,"100","1","2026-01-06 21:57:28");
INSERT INTO `live_tracking` VALUES("238","23","95","2.24886819","102.31134112","25.0094",NULL,NULL,NULL,"100","1","2026-01-06 21:57:33");
INSERT INTO `live_tracking` VALUES("239","23","95","2.24899432","102.31127970","19.5829",NULL,NULL,NULL,"100","1","2026-01-06 21:58:06");
INSERT INTO `live_tracking` VALUES("240","23","95","2.24906384","102.31142675","15.5018",NULL,NULL,NULL,"100","1","2026-01-06 21:59:06");
INSERT INTO `live_tracking` VALUES("241","23","95","2.24919485","102.31154166","20.5052",NULL,NULL,NULL,"100","1","2026-01-06 21:59:18");
INSERT INTO `live_tracking` VALUES("242","23","95","2.24902368","102.31106887","29.177",NULL,NULL,NULL,"100","1","2026-01-06 21:59:23");
INSERT INTO `live_tracking` VALUES("243","23","95","2.24926996","102.31060258","29.9647",NULL,NULL,NULL,"100","1","2026-01-06 21:59:28");
INSERT INTO `live_tracking` VALUES("244","23","95","2.24901945","102.31016903","15.3292",NULL,NULL,NULL,"100","1","2026-01-06 21:59:33");
INSERT INTO `live_tracking` VALUES("245","23","95","2.24915576","102.31034519","29.488",NULL,NULL,NULL,"100","1","2026-01-06 21:59:38");
INSERT INTO `live_tracking` VALUES("246","23","95","2.24898888","102.31017909","13.9916",NULL,NULL,NULL,"100","1","2026-01-06 21:59:43");
INSERT INTO `live_tracking` VALUES("247","23","95","2.24904977","102.31054941","12.694",NULL,NULL,NULL,"100","1","2026-01-06 21:59:48");
INSERT INTO `live_tracking` VALUES("248","23","95","2.24945395","102.31022582","13.5694",NULL,NULL,NULL,"100","1","2026-01-06 21:59:53");
INSERT INTO `live_tracking` VALUES("249","23","95","2.24936077","102.31003060","19.9127",NULL,NULL,NULL,"100","1","2026-01-06 21:59:58");
INSERT INTO `live_tracking` VALUES("250","23","95","2.24927753","102.31043903","24.5197",NULL,NULL,NULL,"100","1","2026-01-06 22:00:03");
INSERT INTO `live_tracking` VALUES("251","23","95","2.24923359","102.31034960","16.8814",NULL,NULL,NULL,"100","1","2026-01-06 22:00:08");
INSERT INTO `live_tracking` VALUES("252","23","95","2.24815068","102.31098991","20.5718",NULL,NULL,NULL,"100","1","2026-01-06 22:00:09");
INSERT INTO `live_tracking` VALUES("253","23","95","2.24812643","102.31071227","21.6253",NULL,NULL,NULL,"100","1","2026-01-06 22:00:13");
INSERT INTO `live_tracking` VALUES("254","23","95","2.24777025","102.31067580","22.3723",NULL,NULL,NULL,"100","1","2026-01-06 22:00:14");
INSERT INTO `live_tracking` VALUES("255","23","95","2.24786583","102.31038317","29.2823",NULL,NULL,NULL,"100","1","2026-01-06 22:00:18");
INSERT INTO `live_tracking` VALUES("256","23","95","2.24740919","102.31059641","27.7726",NULL,NULL,NULL,"100","1","2026-01-06 22:00:19");
INSERT INTO `live_tracking` VALUES("257","23","95","2.24755722","102.31069443","15.4864",NULL,NULL,NULL,"100","1","2026-01-06 22:00:23");
INSERT INTO `live_tracking` VALUES("258","23","95","2.24773073","102.31085904","21.7027",NULL,NULL,NULL,"100","1","2026-01-06 22:00:24");
INSERT INTO `live_tracking` VALUES("259","23","95","2.24753320","102.31086377","12.2278",NULL,NULL,NULL,"100","1","2026-01-06 22:00:28");
INSERT INTO `live_tracking` VALUES("260","23","95","2.24756966","102.31095516","10.9351",NULL,NULL,NULL,"100","1","2026-01-06 22:00:29");
INSERT INTO `live_tracking` VALUES("261","23","95","2.24759067","102.31127741","17.3096",NULL,NULL,NULL,"100","1","2026-01-06 22:00:33");
INSERT INTO `live_tracking` VALUES("262","23","95","2.24732757","102.31091608","22.8443",NULL,NULL,NULL,"100","1","2026-01-06 22:00:34");
INSERT INTO `live_tracking` VALUES("263","23","95","2.24766747","102.31056987","21.5357",NULL,NULL,NULL,"100","1","2026-01-06 22:00:38");
INSERT INTO `live_tracking` VALUES("264","23","95","2.24732717","102.31027922","16.9642",NULL,NULL,NULL,"100","1","2026-01-06 22:00:39");
INSERT INTO `live_tracking` VALUES("265","23","95","2.24728245","102.31034030","29.5526",NULL,NULL,NULL,"100","1","2026-01-06 22:00:43");
INSERT INTO `live_tracking` VALUES("266","23","95","2.24698991","102.31034709","27.5685",NULL,NULL,NULL,"100","1","2026-01-06 22:00:44");
INSERT INTO `live_tracking` VALUES("267","23","95","2.24740422","102.30985007","11.9906",NULL,NULL,NULL,"100","1","2026-01-06 22:00:48");
INSERT INTO `live_tracking` VALUES("268","23","95","2.24789705","102.30987649","29.4974",NULL,NULL,NULL,"100","1","2026-01-06 22:00:49");
INSERT INTO `live_tracking` VALUES("269","23","95","2.24778464","102.30943944","15.6655",NULL,NULL,NULL,"100","1","2026-01-06 22:00:53");
INSERT INTO `live_tracking` VALUES("270","23","95","2.24818010","102.30979651","18.6726",NULL,NULL,NULL,"100","1","2026-01-06 22:00:54");
INSERT INTO `live_tracking` VALUES("271","23","95","2.24863949","102.31002452","12.4524",NULL,NULL,NULL,"100","1","2026-01-06 22:00:58");
INSERT INTO `live_tracking` VALUES("272","23","95","2.24842840","102.31102138","13.7084",NULL,NULL,NULL,"100","1","2026-01-06 22:01:01");
INSERT INTO `live_tracking` VALUES("273","23","95","2.24859902","102.31071945","23.8747",NULL,NULL,NULL,"100","1","2026-01-06 22:01:03");
INSERT INTO `live_tracking` VALUES("274","23","95","2.24861780","102.31066402","29.3892",NULL,NULL,NULL,"100","1","2026-01-06 22:01:06");
INSERT INTO `live_tracking` VALUES("275","23","95","2.24819141","102.31100878","21.2745",NULL,NULL,NULL,"100","1","2026-01-06 22:01:08");
INSERT INTO `live_tracking` VALUES("276","23","95","2.24826924","102.31072665","24.7838",NULL,NULL,NULL,"100","1","2026-01-06 22:01:11");
INSERT INTO `live_tracking` VALUES("277","23","95","2.24785106","102.31102551","11.3615",NULL,NULL,NULL,"100","1","2026-01-06 22:01:13");
INSERT INTO `live_tracking` VALUES("278","23","95","2.24760739","102.31086661","22.2368",NULL,NULL,NULL,"100","1","2026-01-06 22:01:16");
INSERT INTO `live_tracking` VALUES("279","23","95","2.24807699","102.31073255","20.4934",NULL,NULL,NULL,"100","1","2026-01-06 22:01:18");
INSERT INTO `live_tracking` VALUES("280","23","95","2.24794188","102.31079237","29.2016",NULL,NULL,NULL,"100","1","2026-01-06 22:01:21");
INSERT INTO `live_tracking` VALUES("281","23","95","2.24828802","102.31036387","14.5237",NULL,NULL,NULL,"100","1","2026-01-06 22:01:23");
INSERT INTO `live_tracking` VALUES("282","23","95","2.24841098","102.31060990","22.5803",NULL,NULL,NULL,"100","1","2026-01-06 22:01:26");
INSERT INTO `live_tracking` VALUES("283","23","95","2.24861059","102.31020692","23.0778",NULL,NULL,NULL,"100","1","2026-01-06 22:01:28");
INSERT INTO `live_tracking` VALUES("284","23","95","2.24867355","102.31023032","13.2774",NULL,NULL,NULL,"100","1","2026-01-06 22:01:31");
INSERT INTO `live_tracking` VALUES("285","23","95","2.24829655","102.30975361","28.9298",NULL,NULL,NULL,"100","1","2026-01-06 22:01:33");
INSERT INTO `live_tracking` VALUES("286","23","95","2.24863007","102.30973335","19.343",NULL,NULL,NULL,"100","1","2026-01-06 22:01:36");
INSERT INTO `live_tracking` VALUES("287","23","95","2.24869403","102.30980173","17.9206",NULL,NULL,NULL,"100","1","2026-01-06 22:01:38");
INSERT INTO `live_tracking` VALUES("288","23","95","2.24881909","102.30966973","14.5902",NULL,NULL,NULL,"100","1","2026-01-06 22:01:41");
INSERT INTO `live_tracking` VALUES("289","23","95","2.24923636","102.30990155","25.3726",NULL,NULL,NULL,"100","1","2026-01-06 22:01:43");
INSERT INTO `live_tracking` VALUES("290","23","95","2.24955710","102.30983324","18.4806",NULL,NULL,NULL,"100","1","2026-01-06 22:01:46");
INSERT INTO `live_tracking` VALUES("291","23","95","2.24960837","102.31019908","16.0487",NULL,NULL,NULL,"100","1","2026-01-06 22:01:48");
INSERT INTO `live_tracking` VALUES("292","23","95","2.24977534","102.31035290","19.1399",NULL,NULL,NULL,"100","1","2026-01-06 22:01:51");
INSERT INTO `live_tracking` VALUES("293","23","95","2.24986260","102.30987837","21.6133",NULL,NULL,NULL,"100","1","2026-01-06 22:01:53");
INSERT INTO `live_tracking` VALUES("294","23","95","2.24943293","102.30950984","24.2604",NULL,NULL,NULL,"100","1","2026-01-06 22:01:56");
INSERT INTO `live_tracking` VALUES("295","23","95","2.24926134","102.30926966","14.3688",NULL,NULL,NULL,"100","1","2026-01-06 22:01:58");
INSERT INTO `live_tracking` VALUES("296","23","95","2.24881442","102.30967469","10.9372",NULL,NULL,NULL,"100","1","2026-01-06 22:02:01");
INSERT INTO `live_tracking` VALUES("297","23","95","2.24924933","102.30996417","20.2954",NULL,NULL,NULL,"100","1","2026-01-06 22:02:03");
INSERT INTO `live_tracking` VALUES("298","23","95","2.24928834","102.30957975","12.7863",NULL,NULL,NULL,"100","1","2026-01-06 22:02:06");
INSERT INTO `live_tracking` VALUES("299","23","95","2.24916932","102.30968583","26.5287",NULL,NULL,NULL,"100","1","2026-01-06 22:02:08");
INSERT INTO `live_tracking` VALUES("300","23","95","2.24911966","102.30970900","22.9805",NULL,NULL,NULL,"100","1","2026-01-06 22:02:11");
INSERT INTO `live_tracking` VALUES("301","23","95","2.24872716","102.30981888","10.4264",NULL,NULL,NULL,"100","1","2026-01-06 22:02:13");
INSERT INTO `live_tracking` VALUES("302","23","95","2.24921716","102.30963963","24.6858",NULL,NULL,NULL,"100","1","2026-01-06 22:02:16");
INSERT INTO `live_tracking` VALUES("303","23","95","2.24953398","102.30937340","17.727",NULL,NULL,NULL,"100","1","2026-01-06 22:02:20");
INSERT INTO `live_tracking` VALUES("304","23","95","2.24951479","102.30980798","17.1726",NULL,NULL,NULL,"100","1","2026-01-06 22:02:21");
INSERT INTO `live_tracking` VALUES("305","23","95","2.24954246","102.30957613","10.3436",NULL,NULL,NULL,"100","1","2026-01-06 22:02:24");
INSERT INTO `live_tracking` VALUES("306","23","95","2.24962665","102.30920337","10.1886",NULL,NULL,NULL,"100","1","2026-01-06 22:02:26");
INSERT INTO `live_tracking` VALUES("307","23","95","2.25004933","102.30909745","17.4745",NULL,NULL,NULL,"100","1","2026-01-06 22:02:28");
INSERT INTO `live_tracking` VALUES("308","23","95","2.24978350","102.30948733","24.614",NULL,NULL,NULL,"100","1","2026-01-06 22:02:32");
INSERT INTO `live_tracking` VALUES("309","23","95","2.25010238","102.30981139","26.0694",NULL,NULL,NULL,"100","1","2026-01-06 22:02:33");
INSERT INTO `live_tracking` VALUES("310","23","95","2.25046313","102.30950748","23.6552",NULL,NULL,NULL,"100","1","2026-01-06 22:02:36");
INSERT INTO `live_tracking` VALUES("311","23","95","2.25044979","102.30958946","27.8896",NULL,NULL,NULL,"100","1","2026-01-06 22:02:39");
INSERT INTO `live_tracking` VALUES("312","23","95","2.25053079","102.30939796","14.3149",NULL,NULL,NULL,"100","1","2026-01-06 22:02:41");
INSERT INTO `live_tracking` VALUES("313","23","95","2.25044684","102.30938364","25.134",NULL,NULL,NULL,"100","1","2026-01-06 22:02:43");
INSERT INTO `live_tracking` VALUES("314","23","95","2.25093485","102.30971633","27.9804",NULL,NULL,NULL,"100","1","2026-01-06 22:02:46");
INSERT INTO `live_tracking` VALUES("315","23","95","2.25057188","102.30945784","28.4429",NULL,NULL,NULL,"100","1","2026-01-06 22:02:48");
INSERT INTO `live_tracking` VALUES("316","23","95","2.25090086","102.30959011","18.2254",NULL,NULL,NULL,"100","1","2026-01-06 22:02:51");
INSERT INTO `live_tracking` VALUES("317","23","95","2.25097195","102.30955097","11.3932",NULL,NULL,NULL,"100","1","2026-01-06 22:02:53");
INSERT INTO `live_tracking` VALUES("318","23","95","2.25124065","102.30977443","10.4418",NULL,NULL,NULL,"100","1","2026-01-06 22:02:56");
INSERT INTO `live_tracking` VALUES("319","23","95","2.25134041","102.30960099","23.4567",NULL,NULL,NULL,"100","1","2026-01-06 22:02:58");
INSERT INTO `live_tracking` VALUES("320","23","95","2.25091675","102.30933296","17.8163",NULL,NULL,NULL,"100","1","2026-01-06 22:03:01");
INSERT INTO `live_tracking` VALUES("321","23","95","2.25115502","102.30950013","16.2254",NULL,NULL,NULL,"100","1","2026-01-06 22:03:03");
INSERT INTO `live_tracking` VALUES("322","23","95","2.25132521","102.30938002","11.0282",NULL,NULL,NULL,"100","1","2026-01-06 22:03:06");
INSERT INTO `live_tracking` VALUES("323","23","95","2.25150349","102.30979960","11.953",NULL,NULL,NULL,"100","1","2026-01-06 22:03:08");
INSERT INTO `live_tracking` VALUES("324","23","95","2.25112386","102.31015094","21.7411",NULL,NULL,NULL,"100","1","2026-01-06 22:03:11");
INSERT INTO `live_tracking` VALUES("325","23","95","2.25136650","102.30994740","23.7335",NULL,NULL,NULL,"100","1","2026-01-06 22:03:13");
INSERT INTO `live_tracking` VALUES("326","23","95","2.25149886","102.31038875","29.1475",NULL,NULL,NULL,"100","1","2026-01-06 22:04:06");
INSERT INTO `live_tracking` VALUES("327","23","95","2.25142746","102.31012464","27.1364",NULL,NULL,NULL,"100","1","2026-01-06 22:04:06");
INSERT INTO `live_tracking` VALUES("328","23","95","2.25136644","102.30995449","20.6927",NULL,NULL,NULL,"100","1","2026-01-06 22:04:15");
INSERT INTO `live_tracking` VALUES("329","23","95","2.25169348","102.31038619","15.9021",NULL,NULL,NULL,"100","1","2026-01-06 22:04:16");
INSERT INTO `live_tracking` VALUES("330","23","95","2.25162274","102.31034425","13.4964",NULL,NULL,NULL,"100","1","2026-01-06 22:04:16");
INSERT INTO `live_tracking` VALUES("331","23","95","2.25116306","102.30987660","16.1933",NULL,NULL,NULL,"100","1","2026-01-06 22:04:18");
INSERT INTO `live_tracking` VALUES("332","23","95","2.25151710","102.31025208","15.0277",NULL,NULL,NULL,"100","1","2026-01-06 22:04:21");
INSERT INTO `live_tracking` VALUES("333","23","95","2.25179229","102.31039020","24.4484",NULL,NULL,NULL,"100","1","2026-01-06 22:04:23");
INSERT INTO `live_tracking` VALUES("334","23","95","2.25186481","102.31079185","10.6621",NULL,NULL,NULL,"100","1","2026-01-06 22:04:26");
INSERT INTO `live_tracking` VALUES("335","23","95","2.25202919","102.31058436","11.8045",NULL,NULL,NULL,"100","1","2026-01-06 22:04:28");
INSERT INTO `live_tracking` VALUES("336","23","95","2.25228773","102.31086276","27.4066",NULL,NULL,NULL,"100","1","2026-01-06 22:04:31");
INSERT INTO `live_tracking` VALUES("337","23","95","2.25268545","102.31083039","10.707",NULL,NULL,NULL,"100","1","2026-01-06 22:04:33");
INSERT INTO `live_tracking` VALUES("338","23","95","2.25231319","102.31044271","24.4422",NULL,NULL,NULL,"100","1","2026-01-06 22:04:36");
INSERT INTO `live_tracking` VALUES("339","23","95","2.25196421","102.31084741","17.1699",NULL,NULL,NULL,"100","1","2026-01-06 22:04:38");
INSERT INTO `live_tracking` VALUES("340","23","95","2.25231345","102.31065708","21.1773",NULL,NULL,NULL,"100","1","2026-01-06 22:04:41");
INSERT INTO `live_tracking` VALUES("341","23","95","2.25227554","102.31060942","28.4178",NULL,NULL,NULL,"100","1","2026-01-06 22:04:43");
INSERT INTO `live_tracking` VALUES("342","23","95","2.25248771","102.31050920","24.5476",NULL,NULL,NULL,"100","1","2026-01-06 22:04:46");
INSERT INTO `live_tracking` VALUES("343","23","95","2.25234170","102.31100051","23.0422",NULL,NULL,NULL,"100","1","2026-01-06 22:04:48");
INSERT INTO `live_tracking` VALUES("344","23","95","2.25204775","102.31138542","26.6865",NULL,NULL,NULL,"100","1","2026-01-06 22:04:51");
INSERT INTO `live_tracking` VALUES("345","23","95","2.25186883","102.31103095","15.4774",NULL,NULL,NULL,"100","1","2026-01-06 22:04:53");
INSERT INTO `live_tracking` VALUES("346","23","95","2.25188978","102.31133248","12.7113",NULL,NULL,NULL,"100","1","2026-01-06 22:04:56");
INSERT INTO `live_tracking` VALUES("347","23","95","2.25171433","102.31159059","26.9362",NULL,NULL,NULL,"100","1","2026-01-06 22:04:58");
INSERT INTO `live_tracking` VALUES("348","23","95","2.25171724","102.31161956","19.6887",NULL,NULL,NULL,"100","1","2026-01-06 22:05:01");
INSERT INTO `live_tracking` VALUES("349","23","95","2.25132013","102.31159250","10.0283",NULL,NULL,NULL,"100","1","2026-01-06 22:05:03");
INSERT INTO `live_tracking` VALUES("350","23","95","2.25151717","102.31119092","24.6551",NULL,NULL,NULL,"100","1","2026-01-06 22:05:06");
INSERT INTO `live_tracking` VALUES("351","23","95","2.25108741","102.31088935","14.2025",NULL,NULL,NULL,"100","1","2026-01-06 22:05:08");
INSERT INTO `live_tracking` VALUES("352","23","95","2.25121655","102.31124463","25.2459",NULL,NULL,NULL,"100","1","2026-01-06 22:05:11");
INSERT INTO `live_tracking` VALUES("353","23","95","2.25119361","102.31109317","28.4264",NULL,NULL,NULL,"100","1","2026-01-06 22:05:13");
INSERT INTO `live_tracking` VALUES("354","23","95","2.25085232","102.31133044","29.3492",NULL,NULL,NULL,"100","1","2026-01-06 22:06:06");
INSERT INTO `live_tracking` VALUES("355","23","95","2.25052522","102.31132411","17.8279",NULL,NULL,NULL,"100","1","2026-01-06 22:06:06");
INSERT INTO `live_tracking` VALUES("356","23","95","2.25082740","102.31110124","25.7855",NULL,NULL,NULL,"100","1","2026-01-06 22:07:06");
INSERT INTO `live_tracking` VALUES("357","23","95","2.25110027","102.31148382","18.5364",NULL,NULL,NULL,"100","1","2026-01-06 22:07:06");
INSERT INTO `live_tracking` VALUES("358","23","95","2.25069569","102.31193477","28.2568",NULL,NULL,NULL,"100","1","2026-01-06 22:08:06");
INSERT INTO `live_tracking` VALUES("359","23","95","2.25117985","102.31192477","18.9768",NULL,NULL,NULL,"100","1","2026-01-06 22:08:06");
INSERT INTO `live_tracking` VALUES("360","23","95","2.25120034","102.31184623","18.8857",NULL,NULL,NULL,"100","1","2026-01-06 22:09:06");
INSERT INTO `live_tracking` VALUES("361","23","95","2.25145847","102.31232726","13.5917",NULL,NULL,NULL,"100","1","2026-01-06 22:09:06");
INSERT INTO `live_tracking` VALUES("362","23","95","2.25192174","102.31209399","12.884",NULL,NULL,NULL,"100","1","2026-01-06 22:10:06");
INSERT INTO `live_tracking` VALUES("363","23","95","2.25157058","102.31164902","21.0261",NULL,NULL,NULL,"100","1","2026-01-06 22:10:06");
INSERT INTO `live_tracking` VALUES("364","23","95","2.25203891","102.31142812","11.8894",NULL,NULL,NULL,"100","1","2026-01-06 22:10:09");
INSERT INTO `live_tracking` VALUES("365","23","95","2.25165653","102.31175457","18.0413",NULL,NULL,NULL,"100","1","2026-01-06 22:10:11");
INSERT INTO `live_tracking` VALUES("366","23","95","2.25129415","102.31188446","13.4926",NULL,NULL,NULL,"100","1","2026-01-06 22:10:13");
INSERT INTO `live_tracking` VALUES("367","23","95","2.25092043","102.31214943","10.1072",NULL,NULL,NULL,"100","1","2026-01-06 22:10:16");
INSERT INTO `live_tracking` VALUES("368","23","95","2.25125449","102.31171752","20.5183",NULL,NULL,NULL,"100","1","2026-01-06 22:10:18");
INSERT INTO `live_tracking` VALUES("369","23","95","2.25138461","102.31185035","10.2719",NULL,NULL,NULL,"100","1","2026-01-06 22:10:21");
INSERT INTO `live_tracking` VALUES("370","23","95","2.25106390","102.31183220","25.6229",NULL,NULL,NULL,"100","1","2026-01-06 22:10:23");
INSERT INTO `live_tracking` VALUES("371","23","95","2.25081904","102.31213978","23.0219",NULL,NULL,NULL,"100","1","2026-01-06 22:10:26");
INSERT INTO `live_tracking` VALUES("372","23","95","2.25103906","102.31213437","23.0516",NULL,NULL,NULL,"100","1","2026-01-06 22:10:28");
INSERT INTO `live_tracking` VALUES("373","23","95","2.25142041","102.31208059","21.6104",NULL,NULL,NULL,"100","1","2026-01-06 22:10:31");
INSERT INTO `live_tracking` VALUES("374","23","95","2.25188539","102.31220745","15.395",NULL,NULL,NULL,"100","1","2026-01-06 22:10:33");
INSERT INTO `live_tracking` VALUES("375","23","95","2.25191757","102.31251602","12.1755",NULL,NULL,NULL,"100","1","2026-01-06 22:10:36");
INSERT INTO `live_tracking` VALUES("376","23","95","2.25238894","102.31278050","22.1728",NULL,NULL,NULL,"100","1","2026-01-06 22:10:38");
INSERT INTO `live_tracking` VALUES("377","23","95","2.25269641","102.31287307","21.988",NULL,NULL,NULL,"100","1","2026-01-06 22:10:41");
INSERT INTO `live_tracking` VALUES("378","23","95","2.25225544","102.31256951","28.8531",NULL,NULL,NULL,"100","1","2026-01-06 22:10:43");
INSERT INTO `live_tracking` VALUES("379","23","95","2.25202855","102.31207948","25.5298",NULL,NULL,NULL,"100","1","2026-01-06 22:10:46");
INSERT INTO `live_tracking` VALUES("380","23","95","2.25247742","102.31217139","25.446",NULL,NULL,NULL,"100","1","2026-01-06 22:10:48");
INSERT INTO `live_tracking` VALUES("381","23","95","2.25212258","102.31254103","17.2428",NULL,NULL,NULL,"100","1","2026-01-06 22:10:51");
INSERT INTO `live_tracking` VALUES("382","23","95","2.25172774","102.31220989","14.3137",NULL,NULL,NULL,"100","1","2026-01-06 22:10:53");
INSERT INTO `live_tracking` VALUES("383","23","95","2.25133032","102.31206475","13.7404",NULL,NULL,NULL,"100","1","2026-01-06 22:10:56");
INSERT INTO `live_tracking` VALUES("384","23","95","2.25150201","102.31232432","14.4962",NULL,NULL,NULL,"100","1","2026-01-06 22:10:58");
INSERT INTO `live_tracking` VALUES("385","23","95","2.25149859","102.31195327","16.8988",NULL,NULL,NULL,"100","1","2026-01-06 22:11:01");
INSERT INTO `live_tracking` VALUES("386","23","95","2.25198363","102.31210243","20.6431",NULL,NULL,NULL,"100","1","2026-01-06 22:11:03");
INSERT INTO `live_tracking` VALUES("387","23","95","2.25162726","102.31246579","27.628",NULL,NULL,NULL,"100","1","2026-01-06 22:11:06");
INSERT INTO `live_tracking` VALUES("388","23","95","2.25189414","102.31237845","18.4147",NULL,NULL,NULL,"100","1","2026-01-06 22:11:08");
INSERT INTO `live_tracking` VALUES("389","23","95","2.25188456","102.31282197","17.3717",NULL,NULL,NULL,"100","1","2026-01-06 22:11:11");
INSERT INTO `live_tracking` VALUES("390","23","95","2.25180083","102.31302086","10.941",NULL,NULL,NULL,"100","1","2026-01-06 22:11:13");
INSERT INTO `live_tracking` VALUES("391","23","95","2.25177370","102.31291436","28.6955",NULL,NULL,NULL,"100","1","2026-01-06 22:11:16");
INSERT INTO `live_tracking` VALUES("392","23","95","2.25158571","102.31314985","11.49",NULL,NULL,NULL,"100","1","2026-01-06 22:11:18");
INSERT INTO `live_tracking` VALUES("393","23","95","2.25113846","102.31290984","29.1356",NULL,NULL,NULL,"100","1","2026-01-06 22:11:21");
INSERT INTO `live_tracking` VALUES("394","23","95","2.25150379","102.31245312","28.9287",NULL,NULL,NULL,"100","1","2026-01-06 22:11:23");
INSERT INTO `live_tracking` VALUES("395","23","95","2.25113751","102.31248910","14.7678",NULL,NULL,NULL,"100","1","2026-01-06 22:11:26");
INSERT INTO `live_tracking` VALUES("396","23","95","2.25158316","102.31245870","26.3291",NULL,NULL,NULL,"100","1","2026-01-06 22:11:28");
INSERT INTO `live_tracking` VALUES("397","23","95","2.25171979","102.31214970","25.7198",NULL,NULL,NULL,"100","1","2026-01-06 22:11:31");
INSERT INTO `live_tracking` VALUES("398","23","95","2.25199707","102.31192730","12.1805",NULL,NULL,NULL,"100","1","2026-01-06 22:11:33");
INSERT INTO `live_tracking` VALUES("399","23","95","2.25205303","102.31193267","13.9764",NULL,NULL,NULL,"100","1","2026-01-06 22:11:36");
INSERT INTO `live_tracking` VALUES("400","23","95","2.25246899","102.31203584","13.9128",NULL,NULL,NULL,"100","1","2026-01-06 22:11:38");
INSERT INTO `live_tracking` VALUES("401","23","95","2.25202210","102.31249887","18.138",NULL,NULL,NULL,"100","1","2026-01-06 22:11:41");
INSERT INTO `live_tracking` VALUES("402","23","95","2.25210508","102.31216388","20.7225",NULL,NULL,NULL,"100","1","2026-01-06 22:11:43");
INSERT INTO `live_tracking` VALUES("403","23","95","2.25244589","102.31176566","15.0789",NULL,NULL,NULL,"100","1","2026-01-06 22:11:46");
INSERT INTO `live_tracking` VALUES("404","23","95","2.25247864","102.31198643","17.7012",NULL,NULL,NULL,"100","1","2026-01-06 22:11:48");
INSERT INTO `live_tracking` VALUES("405","23","95","2.25250681","102.31219887","16.8015",NULL,NULL,NULL,"100","1","2026-01-06 22:11:51");
INSERT INTO `live_tracking` VALUES("406","23","95","2.25216925","102.31256748","10.8459",NULL,NULL,NULL,"100","1","2026-01-06 22:11:53");
INSERT INTO `live_tracking` VALUES("407","23","95","2.25196214","102.31240379","24.4938",NULL,NULL,NULL,"100","1","2026-01-06 22:11:56");
INSERT INTO `live_tracking` VALUES("408","23","95","2.25206530","102.31197106","16.9716",NULL,NULL,NULL,"100","1","2026-01-06 22:11:58");
INSERT INTO `live_tracking` VALUES("409","23","95","2.25196375","102.31183161","17.2088",NULL,NULL,NULL,"100","1","2026-01-06 22:12:01");
INSERT INTO `live_tracking` VALUES("410","23","95","2.25172929","102.31225892","17.0541",NULL,NULL,NULL,"100","1","2026-01-06 22:12:03");
INSERT INTO `live_tracking` VALUES("411","23","95","2.25159316","102.31273312","18.9659",NULL,NULL,NULL,"100","1","2026-01-06 22:12:06");
INSERT INTO `live_tracking` VALUES("412","23","95","2.25162411","102.31252133","19.8724",NULL,NULL,NULL,"100","1","2026-01-06 22:12:08");
INSERT INTO `live_tracking` VALUES("413","23","95","2.25197480","102.31225503","18.3769",NULL,NULL,NULL,"100","1","2026-01-06 22:12:11");
INSERT INTO `live_tracking` VALUES("414","23","95","2.25149579","102.31205193","15.2843",NULL,NULL,NULL,"100","1","2026-01-06 22:12:13");
INSERT INTO `live_tracking` VALUES("415","23","95","2.25134365","102.31231439","15.6551",NULL,NULL,NULL,"100","1","2026-01-06 22:12:16");
INSERT INTO `live_tracking` VALUES("416","23","95","2.25157241","102.31212820","22.6407",NULL,NULL,NULL,"100","1","2026-01-06 22:12:18");
INSERT INTO `live_tracking` VALUES("417","23","95","2.25170808","102.31242556","13.9775",NULL,NULL,NULL,"100","1","2026-01-06 22:12:21");
INSERT INTO `live_tracking` VALUES("418","23","95","2.25184287","102.31236257","26.235",NULL,NULL,NULL,"100","1","2026-01-06 22:12:23");
INSERT INTO `live_tracking` VALUES("419","23","95","2.25163412","102.31270861","18.2162",NULL,NULL,NULL,"100","1","2026-01-06 22:12:26");
INSERT INTO `live_tracking` VALUES("420","23","95","2.25204480","102.31231588","22.7911",NULL,NULL,NULL,"100","1","2026-01-06 22:12:28");
INSERT INTO `live_tracking` VALUES("421","23","95","2.25190477","102.31182244","15.1985",NULL,NULL,NULL,"100","1","2026-01-06 22:12:31");
INSERT INTO `live_tracking` VALUES("422","23","95","2.25227256","102.31132527","27.3661",NULL,NULL,NULL,"100","1","2026-01-06 22:12:33");
INSERT INTO `live_tracking` VALUES("423","23","95","2.25243199","102.31116166","12.1832",NULL,NULL,NULL,"100","1","2026-01-06 22:12:36");
INSERT INTO `live_tracking` VALUES("424","23","95","2.25291512","102.31087950","27.9082",NULL,NULL,NULL,"100","1","2026-01-06 22:12:38");
INSERT INTO `live_tracking` VALUES("425","23","95","2.25248407","102.31042273","11.8015",NULL,NULL,NULL,"100","1","2026-01-06 22:12:41");
INSERT INTO `live_tracking` VALUES("426","23","95","2.25206182","102.31059453","13.7462",NULL,NULL,NULL,"100","1","2026-01-06 22:12:43");
INSERT INTO `live_tracking` VALUES("427","23","95","2.25164056","102.31091852","28.6083",NULL,NULL,NULL,"100","1","2026-01-06 22:12:46");
INSERT INTO `live_tracking` VALUES("428","23","95","2.25201846","102.31110209","22.9811",NULL,NULL,NULL,"100","1","2026-01-06 22:12:48");
INSERT INTO `live_tracking` VALUES("429","23","95","2.25238908","102.31101724","13.9002",NULL,NULL,NULL,"100","1","2026-01-06 22:12:51");
INSERT INTO `live_tracking` VALUES("430","23","95","2.25225982","102.31090581","25.9757",NULL,NULL,NULL,"100","1","2026-01-06 22:12:53");
INSERT INTO `live_tracking` VALUES("431","23","95","2.25253147","102.31071223","26.1572",NULL,NULL,NULL,"100","1","2026-01-06 22:12:56");
INSERT INTO `live_tracking` VALUES("432","23","95","2.25241278","102.31109883","12.6653",NULL,NULL,NULL,"100","1","2026-01-06 22:12:58");
INSERT INTO `live_tracking` VALUES("433","23","95","2.25205133","102.31070742","16.9117",NULL,NULL,NULL,"100","1","2026-01-06 22:13:01");
INSERT INTO `live_tracking` VALUES("434","23","95","2.25174401","102.31064600","26.2408",NULL,NULL,NULL,"100","1","2026-01-06 22:13:03");
INSERT INTO `live_tracking` VALUES("435","23","95","2.25194221","102.31101007","12.2175",NULL,NULL,NULL,"100","1","2026-01-06 22:13:06");
INSERT INTO `live_tracking` VALUES("436","23","95","2.25240440","102.31073196","22.3485",NULL,NULL,NULL,"100","1","2026-01-06 22:13:08");
INSERT INTO `live_tracking` VALUES("437","23","95","2.25277688","102.31057906","20.7609",NULL,NULL,NULL,"100","1","2026-01-06 22:13:11");
INSERT INTO `live_tracking` VALUES("438","23","95","2.25247953","102.31048637","17.4483",NULL,NULL,NULL,"100","1","2026-01-06 22:13:13");
INSERT INTO `live_tracking` VALUES("439","23","95","2.25227894","102.31056622","24.3934",NULL,NULL,NULL,"100","1","2026-01-06 22:13:16");
INSERT INTO `live_tracking` VALUES("440","23","95","2.25190744","102.31024221","20.7123",NULL,NULL,NULL,"100","1","2026-01-06 22:13:18");
INSERT INTO `live_tracking` VALUES("441","23","95","2.25182586","102.30991296","24.5744",NULL,NULL,NULL,"100","1","2026-01-06 22:13:21");
INSERT INTO `live_tracking` VALUES("442","23","95","2.25228159","102.30954170","26.146",NULL,NULL,NULL,"100","1","2026-01-06 22:13:23");
INSERT INTO `live_tracking` VALUES("443","23","95","2.25269494","102.30960631","10.3529",NULL,NULL,NULL,"100","1","2026-01-06 22:13:26");
INSERT INTO `live_tracking` VALUES("444","23","95","2.25267595","102.30911868","13.3976",NULL,NULL,NULL,"100","1","2026-01-06 22:13:28");
INSERT INTO `live_tracking` VALUES("445","23","95","2.25283828","102.30930034","15.8356",NULL,NULL,NULL,"100","1","2026-01-06 22:13:31");
INSERT INTO `live_tracking` VALUES("446","23","95","2.25311790","102.30933250","26.3424",NULL,NULL,NULL,"100","1","2026-01-06 22:13:33");
INSERT INTO `live_tracking` VALUES("447","23","95","2.25319774","102.30952767","11.0471",NULL,NULL,NULL,"100","1","2026-01-06 22:13:36");
INSERT INTO `live_tracking` VALUES("448","23","95","2.25272430","102.30992273","23.4579",NULL,NULL,NULL,"100","1","2026-01-06 22:13:38");
INSERT INTO `live_tracking` VALUES("449","23","95","2.25274740","102.31042156","13.8758",NULL,NULL,NULL,"100","1","2026-01-06 22:13:41");
INSERT INTO `live_tracking` VALUES("450","23","95","2.25318004","102.31026361","22.8935",NULL,NULL,NULL,"100","1","2026-01-06 22:13:43");
INSERT INTO `live_tracking` VALUES("451","23","95","2.25355652","102.30978112","18.0965",NULL,NULL,NULL,"100","1","2026-01-06 22:13:46");
INSERT INTO `live_tracking` VALUES("452","23","95","2.25390928","102.30984660","23.2597",NULL,NULL,NULL,"100","1","2026-01-06 22:13:48");
INSERT INTO `live_tracking` VALUES("453","23","95","2.25423766","102.31007876","11.6504",NULL,NULL,NULL,"100","1","2026-01-06 22:13:51");
INSERT INTO `live_tracking` VALUES("454","23","95","2.25456353","102.31043016","25.2108",NULL,NULL,NULL,"100","1","2026-01-06 22:13:53");
INSERT INTO `live_tracking` VALUES("455","23","95","2.25465077","102.31071433","21.189",NULL,NULL,NULL,"100","1","2026-01-06 22:13:56");
INSERT INTO `live_tracking` VALUES("456","23","95","2.25426665","102.31098905","16.2255",NULL,NULL,NULL,"100","1","2026-01-06 22:13:58");
INSERT INTO `live_tracking` VALUES("457","23","95","2.25380800","102.31071285","16.9058",NULL,NULL,NULL,"100","1","2026-01-06 22:14:01");
INSERT INTO `live_tracking` VALUES("458","23","95","2.25423353","102.31061583","16.1708",NULL,NULL,NULL,"100","1","2026-01-06 22:14:03");
INSERT INTO `live_tracking` VALUES("459","23","95","2.25430434","102.31012051","19.0795",NULL,NULL,NULL,"100","1","2026-01-06 22:14:06");
INSERT INTO `live_tracking` VALUES("460","23","95","2.25391346","102.30966334","28.8618",NULL,NULL,NULL,"100","1","2026-01-06 22:14:08");
INSERT INTO `live_tracking` VALUES("461","23","95","2.25413653","102.30959950","15.4392",NULL,NULL,NULL,"100","1","2026-01-06 22:14:11");
INSERT INTO `live_tracking` VALUES("462","23","95","2.25438456","102.30934817","28.5631",NULL,NULL,NULL,"100","1","2026-01-06 22:14:13");
INSERT INTO `live_tracking` VALUES("463","23","95","2.25425113","102.30885837","16.4421",NULL,NULL,NULL,"100","1","2026-01-06 22:14:16");
INSERT INTO `live_tracking` VALUES("464","23","95","2.25417794","102.30883173","25.4912",NULL,NULL,NULL,"100","1","2026-01-06 22:14:18");
INSERT INTO `live_tracking` VALUES("465","23","95","2.25415232","102.30900653","20.1132",NULL,NULL,NULL,"100","1","2026-01-06 22:14:21");
INSERT INTO `live_tracking` VALUES("466","23","95","2.25399497","102.30883631","11.3767",NULL,NULL,NULL,"100","1","2026-01-06 22:14:23");
INSERT INTO `live_tracking` VALUES("467","23","95","2.25355949","102.30881046","21.2493",NULL,NULL,NULL,"100","1","2026-01-06 22:14:26");
INSERT INTO `live_tracking` VALUES("468","23","95","2.25369568","102.30919806","12.0529",NULL,NULL,NULL,"100","1","2026-01-06 22:14:28");
INSERT INTO `live_tracking` VALUES("469","23","95","2.25411612","102.30924031","19.5289",NULL,NULL,NULL,"100","1","2026-01-06 22:14:31");
INSERT INTO `live_tracking` VALUES("470","23","95","2.25406370","102.30896820","17.5697",NULL,NULL,NULL,"100","1","2026-01-06 22:14:33");
INSERT INTO `live_tracking` VALUES("471","23","95","2.25404805","102.30923211","22.633",NULL,NULL,NULL,"100","1","2026-01-06 22:14:36");
INSERT INTO `live_tracking` VALUES("472","23","95","2.25389214","102.30897891","16.3355",NULL,NULL,NULL,"100","1","2026-01-06 22:14:38");
INSERT INTO `live_tracking` VALUES("473","23","95","2.25346730","102.30873020","29.8898",NULL,NULL,NULL,"100","1","2026-01-06 22:14:41");
INSERT INTO `live_tracking` VALUES("474","23","95","2.25354768","102.30838845","14.3997",NULL,NULL,NULL,"100","1","2026-01-06 22:14:43");
INSERT INTO `live_tracking` VALUES("475","23","95","2.25328009","102.30808097","16.0473",NULL,NULL,NULL,"100","1","2026-01-06 22:14:46");
INSERT INTO `live_tracking` VALUES("476","23","95","2.25294593","102.30848963","24.6413",NULL,NULL,NULL,"100","1","2026-01-06 22:14:49");
INSERT INTO `live_tracking` VALUES("477","23","95","2.25306178","102.30894051","27.1206",NULL,NULL,NULL,"100","1","2026-01-06 22:14:51");
INSERT INTO `live_tracking` VALUES("478","23","95","2.25331480","102.30914126","15.7386",NULL,NULL,NULL,"100","1","2026-01-06 22:14:58");
INSERT INTO `live_tracking` VALUES("479","23","95","2.25361910","102.30934842","23.7266",NULL,NULL,NULL,"100","1","2026-01-06 22:14:58");
INSERT INTO `live_tracking` VALUES("480","23","95","2.25382136","102.30914552","12.1294",NULL,NULL,NULL,"100","1","2026-01-06 22:14:58");
INSERT INTO `live_tracking` VALUES("481","23","95","2.24866435","102.31091172","28.9463",NULL,NULL,NULL,"100","1","2026-01-06 22:20:29");
INSERT INTO `live_tracking` VALUES("482","23","95","2.24906805","102.31126680","27.05",NULL,NULL,NULL,"100","1","2026-01-06 22:20:34");
INSERT INTO `live_tracking` VALUES("483","23","95","2.24910999","102.31127930","24.3478",NULL,NULL,NULL,"100","1","2026-01-06 22:20:39");
INSERT INTO `live_tracking` VALUES("484","23","95","2.24949745","102.31121527","23.1676",NULL,NULL,NULL,"100","1","2026-01-06 22:20:44");
INSERT INTO `live_tracking` VALUES("485","23","95","2.24976857","102.31150729","29.6855",NULL,NULL,NULL,"100","1","2026-01-06 22:20:49");
INSERT INTO `live_tracking` VALUES("486","23","95","2.24929821","102.31114426","15.2989",NULL,NULL,NULL,"100","1","2026-01-06 22:20:54");
INSERT INTO `live_tracking` VALUES("487","23","95","2.24946682","102.31108516","13.0054",NULL,NULL,NULL,"100","1","2026-01-06 22:20:59");
INSERT INTO `live_tracking` VALUES("488","23","95","2.24954457","102.31101727","18.4174",NULL,NULL,NULL,"100","1","2026-01-06 22:21:05");
INSERT INTO `live_tracking` VALUES("489","23","95","2.24974229","102.31065151","18.3243",NULL,NULL,NULL,"100","1","2026-01-06 22:21:09");
INSERT INTO `live_tracking` VALUES("490","23","95","2.25009555","102.31070949","25.5459",NULL,NULL,NULL,"100","1","2026-01-06 22:21:15");
INSERT INTO `live_tracking` VALUES("491","23","95","2.24844227","102.31069493","27.1589",NULL,NULL,NULL,"100","1","2026-01-06 22:36:57");
INSERT INTO `live_tracking` VALUES("492","23","95","2.24865900","102.31069879","25.3332",NULL,NULL,NULL,"100","1","2026-01-06 22:37:02");
INSERT INTO `live_tracking` VALUES("493","23","95","2.24839927","102.31111287","19.7335",NULL,NULL,NULL,"100","1","2026-01-06 22:37:07");
INSERT INTO `live_tracking` VALUES("494","23","95","2.24849189","102.31064818","13.1502",NULL,NULL,NULL,"100","1","2026-01-06 22:37:13");
INSERT INTO `live_tracking` VALUES("495","23","95","2.24839450","102.31099802","12.3825",NULL,NULL,NULL,"100","1","2026-01-06 22:37:18");
INSERT INTO `live_tracking` VALUES("496","23","95","2.24872232","102.31148929","13.161",NULL,NULL,NULL,"100","1","2026-01-06 22:37:23");
INSERT INTO `live_tracking` VALUES("497","23","95","2.24855390","102.31190082","25.2303",NULL,NULL,NULL,"100","1","2026-01-06 22:37:28");
INSERT INTO `live_tracking` VALUES("498","23","95","2.24843146","102.31233940","25.2638",NULL,NULL,NULL,"100","1","2026-01-06 22:37:32");
INSERT INTO `live_tracking` VALUES("499","23","95","2.24809283","102.31249656","24.7719",NULL,NULL,NULL,"100","1","2026-01-06 22:37:38");
INSERT INTO `live_tracking` VALUES("500","23","95","2.24773723","102.31203341","17.8219",NULL,NULL,NULL,"100","1","2026-01-06 22:37:43");
INSERT INTO `live_tracking` VALUES("501","23","95","2.24799043","102.31241944","23.4365",NULL,NULL,NULL,"100","1","2026-01-06 22:37:48");
INSERT INTO `live_tracking` VALUES("502","23","95","2.24750378","102.31245986","26.4617",NULL,NULL,NULL,"100","1","2026-01-06 22:37:55");
INSERT INTO `live_tracking` VALUES("503","23","96","2.20883528","102.29104025","10",NULL,NULL,NULL,NULL,NULL,"2026-01-06 23:08:56");
INSERT INTO `live_tracking` VALUES("504","23","95","2.25410228","102.30485983","10",NULL,NULL,NULL,NULL,NULL,"2026-01-06 23:44:40");
INSERT INTO `live_tracking` VALUES("505","23","95","2.25410228","102.30485983","10",NULL,NULL,NULL,NULL,NULL,"2026-01-06 23:48:15");
INSERT INTO `live_tracking` VALUES("506","23","95","2.25410228","102.30485983","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:01:01");
INSERT INTO `live_tracking` VALUES("507","23","95","2.25410228","102.30485983","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:04:24");
INSERT INTO `live_tracking` VALUES("508","23","95","2.25410228","102.30485983","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:25:02");
INSERT INTO `live_tracking` VALUES("509","23","95","2.25796483","102.28786469","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:43:22");
INSERT INTO `live_tracking` VALUES("510","23","95","2.25796483","102.28786469","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:55:11");
INSERT INTO `live_tracking` VALUES("511","23","95","2.25796483","102.28786469","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 00:55:23");
INSERT INTO `live_tracking` VALUES("512","23","95","2.25796483","102.28786469","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 01:00:39");
INSERT INTO `live_tracking` VALUES("513","23","95","2.25037775","102.28293016","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 01:15:29");
INSERT INTO `live_tracking` VALUES("514","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 01:45:44");
INSERT INTO `live_tracking` VALUES("515","23","104","2.25477057","102.25776048","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:05:11");
INSERT INTO `live_tracking` VALUES("516","23","104","2.25477057","102.25776048","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:08:14");
INSERT INTO `live_tracking` VALUES("517","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:08:32");
INSERT INTO `live_tracking` VALUES("518","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:24:50");
INSERT INTO `live_tracking` VALUES("519","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:51:10");
INSERT INTO `live_tracking` VALUES("520","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:51:43");
INSERT INTO `live_tracking` VALUES("521","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:51:58");
INSERT INTO `live_tracking` VALUES("522","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:52:07");
INSERT INTO `live_tracking` VALUES("523","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:52:24");
INSERT INTO `live_tracking` VALUES("524","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:52:51");
INSERT INTO `live_tracking` VALUES("525","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:53:07");
INSERT INTO `live_tracking` VALUES("526","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:53:17");
INSERT INTO `live_tracking` VALUES("527","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:53:52");
INSERT INTO `live_tracking` VALUES("528","23","104","36.57484410","139.23941790","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:54:00");
INSERT INTO `live_tracking` VALUES("529","23","104","36.59788913","99.84965015","10",NULL,NULL,NULL,"80","0","2026-01-07 03:54:12");
INSERT INTO `live_tracking` VALUES("530","23","104","36.59788913","99.84965015","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:54:15");
INSERT INTO `live_tracking` VALUES("531","23","104","24.53360953","42.19613951","10",NULL,NULL,NULL,"80","0","2026-01-07 03:54:34");
INSERT INTO `live_tracking` VALUES("532","23","104","24.53360953","42.19613951","10",NULL,NULL,NULL,NULL,NULL,"2026-01-07 03:54:41");
INSERT INTO `live_tracking` VALUES("533","23","96","2.21134928","102.28956006","10",NULL,NULL,NULL,"80","0","2026-01-07 15:55:47");
INSERT INTO `live_tracking` VALUES("534","23","96","2.21119926","102.29751967","10",NULL,NULL,NULL,"80","0","2026-01-07 15:55:48");
INSERT INTO `live_tracking` VALUES("535","23","96","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-07 15:56:03");
INSERT INTO `live_tracking` VALUES("536","23","96","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-07 15:56:09");
INSERT INTO `live_tracking` VALUES("537","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-07 23:32:00");
INSERT INTO `live_tracking` VALUES("538","23","95","2.27731849","102.34074145","10",NULL,NULL,NULL,"80","0","2026-01-08 00:04:32");
INSERT INTO `live_tracking` VALUES("539","23","95","2.26661714","102.31661194","10",NULL,NULL,NULL,"80","0","2026-01-08 00:05:09");
INSERT INTO `live_tracking` VALUES("540","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 00:05:29");
INSERT INTO `live_tracking` VALUES("541","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 00:10:27");
INSERT INTO `live_tracking` VALUES("542","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 00:10:43");
INSERT INTO `live_tracking` VALUES("543","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 00:11:00");
INSERT INTO `live_tracking` VALUES("544","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 00:14:57");
INSERT INTO `live_tracking` VALUES("545","23","95","2.31096770","102.31463990","10",NULL,NULL,NULL,"80","0","2026-01-08 00:15:29");
INSERT INTO `live_tracking` VALUES("546","23","95","5.79859840","102.51796180","10",NULL,NULL,NULL,"80","0","2026-01-08 00:33:20");
INSERT INTO `live_tracking` VALUES("547","23","95","2.98455512","101.79913529","10",NULL,NULL,NULL,"80","0","2026-01-08 00:47:36");
INSERT INTO `live_tracking` VALUES("548","23","95","2.58057086","102.20591208","10",NULL,NULL,NULL,"80","0","2026-01-08 01:25:56");
INSERT INTO `live_tracking` VALUES("549","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 01:26:24");
INSERT INTO `live_tracking` VALUES("550","24","113","2.25140000","102.27590000","10",NULL,NULL,NULL,NULL,NULL,"2026-01-08 01:59:13");
INSERT INTO `live_tracking` VALUES("551","24","113","2.25140000","102.27590000","10",NULL,NULL,NULL,NULL,NULL,"2026-01-08 01:59:44");
INSERT INTO `live_tracking` VALUES("552","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-08 10:09:40");
INSERT INTO `live_tracking` VALUES("553","23","104","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-08 23:09:48");
INSERT INTO `live_tracking` VALUES("554","23","104","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-08 23:09:55");
INSERT INTO `live_tracking` VALUES("555","23","104","2.31177520","102.28214380","10",NULL,NULL,NULL,"80","0","2026-01-08 23:16:43");
INSERT INTO `live_tracking` VALUES("556","23","104","2.31177520","102.28214380","10",NULL,NULL,NULL,NULL,NULL,"2026-01-08 23:16:48");
INSERT INTO `live_tracking` VALUES("557","23","104","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 00:07:11");
INSERT INTO `live_tracking` VALUES("558","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 01:06:12");
INSERT INTO `live_tracking` VALUES("559","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:06:19");
INSERT INTO `live_tracking` VALUES("560","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:07:41");
INSERT INTO `live_tracking` VALUES("561","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 01:07:57");
INSERT INTO `live_tracking` VALUES("562","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:08:02");
INSERT INTO `live_tracking` VALUES("563","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:08:44");
INSERT INTO `live_tracking` VALUES("564","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:09:08");
INSERT INTO `live_tracking` VALUES("565","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 01:09:21");
INSERT INTO `live_tracking` VALUES("566","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:09:27");
INSERT INTO `live_tracking` VALUES("567","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:24:46");
INSERT INTO `live_tracking` VALUES("568","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:26:50");
INSERT INTO `live_tracking` VALUES("569","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 01:27:11");
INSERT INTO `live_tracking` VALUES("570","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 01:27:15");
INSERT INTO `live_tracking` VALUES("571","23","95","2.30238219","102.27583741","10",NULL,NULL,NULL,"80","0","2026-01-09 01:30:40");
INSERT INTO `live_tracking` VALUES("572","23","95","2.20590869","102.26520272","10",NULL,NULL,NULL,"80","0","2026-01-09 01:30:52");
INSERT INTO `live_tracking` VALUES("573","24","113","2.25007258","102.25558729","10",NULL,NULL,NULL,"80","0","2026-01-09 22:36:45");
INSERT INTO `live_tracking` VALUES("574","23","95","2.20805033","102.27336134","10",NULL,NULL,NULL,"80","0","2026-01-09 22:39:13");
INSERT INTO `live_tracking` VALUES("575","23","95","2.20805033","102.27336134","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:39:17");
INSERT INTO `live_tracking` VALUES("576","23","95","2.20805033","102.27336134","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:39:34");
INSERT INTO `live_tracking` VALUES("577","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 22:39:42");
INSERT INTO `live_tracking` VALUES("578","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:39:50");
INSERT INTO `live_tracking` VALUES("579","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 22:40:49");
INSERT INTO `live_tracking` VALUES("580","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:40:54");
INSERT INTO `live_tracking` VALUES("581","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 22:45:25");
INSERT INTO `live_tracking` VALUES("582","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:45:32");
INSERT INTO `live_tracking` VALUES("583","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:52:58");
INSERT INTO `live_tracking` VALUES("584","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 22:53:18");
INSERT INTO `live_tracking` VALUES("585","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 22:53:25");
INSERT INTO `live_tracking` VALUES("586","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 23:00:29");
INSERT INTO `live_tracking` VALUES("587","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 23:00:39");
INSERT INTO `live_tracking` VALUES("588","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 23:00:48");
INSERT INTO `live_tracking` VALUES("589","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 23:43:26");
INSERT INTO `live_tracking` VALUES("590","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,"80","0","2026-01-09 23:43:38");
INSERT INTO `live_tracking` VALUES("591","23","95","2.31065850","102.32041620","10",NULL,NULL,NULL,NULL,NULL,"2026-01-09 23:43:43");
INSERT INTO `live_tracking` VALUES("592","24","113","2.24612538","102.23292738","10",NULL,NULL,NULL,"80","0","2026-01-10 15:11:41");
INSERT INTO `live_tracking` VALUES("593","24","113","2.25711249","102.25318849","10",NULL,NULL,NULL,"80","0","2026-01-10 15:11:45");
INSERT INTO `live_tracking` VALUES("594","24","113","2.25092617","102.25413346","10",NULL,NULL,NULL,"80","0","2026-01-10 15:11:48");
INSERT INTO `live_tracking` VALUES("595","24","113","2.25074910","102.25420920","10",NULL,NULL,NULL,"80","0","2026-01-10 15:11:59");
INSERT INTO `live_tracking` VALUES("596","24","113","2.25074910","102.25420920","10",NULL,NULL,NULL,NULL,NULL,"2026-01-10 15:12:03");
INSERT INTO `live_tracking` VALUES("597","24","113","2.24569547","102.29079206","10",NULL,NULL,NULL,"80","0","2026-01-10 15:53:37");
INSERT INTO `live_tracking` VALUES("598","24","113","2.24543795","102.28383804","10",NULL,NULL,NULL,"80","0","2026-01-10 15:53:38");
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `shelter_coordinates` VALUES("1","Stadium Hang Jebat","Alor Gajah","2.25640000","102.27780000","2026-01-09 19:47:04");
INSERT INTO `shelter_coordinates` VALUES("2","Melaka Tengah Emergency Shelter 1","Alor Gajah","2.20000000","102.25000000","2026-01-09 22:15:49");
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `status_changes` VALUES("1","99","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-06 14:44:59");
INSERT INTO `status_changes` VALUES("2","102","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-06 14:45:27");
INSERT INTO `status_changes` VALUES("17","103","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-07 02:41:50");
INSERT INTO `status_changes` VALUES("18","104","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-07 03:03:14");
INSERT INTO `status_changes` VALUES("19","112","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-08 01:47:38");
INSERT INTO `status_changes` VALUES("20","113","Assigned","In Progress","volunteer","Volunteer confirmed assignment","2026-01-08 01:58:50");
INSERT INTO `status_changes` VALUES("21","114","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-08 10:51:15");
INSERT INTO `status_changes` VALUES("22","111","Assigned","Volunteer Needed","system","All volunteers declined/cancelled","2026-01-09 20:40:10");
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
) ENGINE=InnoDB AUTO_INCREMENT=230 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `victim_approvals` VALUES("1","74","5","Approved","2025-12-29 01:18:45","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("2","76","5","Approved","2025-12-29 01:58:55","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("3","96","5","Approved","2025-12-29 01:58:55","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("4","97","5","Approved","2025-12-29 20:38:12","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("5","102","5","Approved","2025-12-29 20:38:12","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("6","71","5","Approved","2026-01-02 00:41:44","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("7","72","5","Approved","2026-01-07 01:18:30","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("8","73","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("9","75","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("10","77","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("11","78","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("12","79","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("13","80","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("14","95","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("15","98","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("16","99","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("17","100","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("18","101","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("19","103","5","Approved","2026-01-08 01:28:05","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("20","104","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("21","105","5","Approved","2025-12-29 02:08:51","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("22","107","5","Approved","2025-12-29 02:08:51","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("23","108","5","Approved","2025-12-29 02:08:51","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("24","81","5","Approved","2025-12-29 02:08:51","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("25","82","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("26","84","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("27","85","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("28","86","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("29","88","5","Approved","2026-01-07 02:37:19","2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("30","89","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("31","90","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("32","91","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("33","93","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("34","94","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("35","83","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("36","87","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("37","92","5","Pending",NULL,"2025-12-29 00:13:39");
INSERT INTO `victim_approvals` VALUES("38","39","3","Approved","2025-12-29 02:09:26","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("39","43","3","Approved","2025-12-29 02:09:26","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("40","49","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("41","51","3","Rejected","2026-01-04 23:14:26","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("42","54","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("43","59","3","Approved","2025-12-29 02:09:26","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("44","63","3","Approved","2026-01-09 02:24:58","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("45","69","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("46","70","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("47","36","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("48","37","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("49","38","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("50","40","3","Approved","2026-01-05 21:17:20","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("51","41","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("52","42","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("53","44","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("54","45","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("55","46","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("56","47","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("57","48","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("58","50","3","Approved","2026-01-04 03:39:59","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("59","52","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("60","53","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("61","55","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("62","56","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("63","57","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("64","58","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("65","60","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("66","61","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("67","62","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("68","64","3","Approved","2026-01-09 02:26:05","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("69","65","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("70","66","3","Approved","2026-01-04 20:42:09","2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("71","67","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("72","68","3","Pending",NULL,"2025-12-29 00:14:02");
INSERT INTO `victim_approvals` VALUES("73","110","7","Pending",NULL,"2025-12-29 22:50:33");
INSERT INTO `victim_approvals` VALUES("74","112","7","Approved","2026-01-02 21:47:14","2025-12-30 00:12:37");
INSERT INTO `victim_approvals` VALUES("75","109","2","Approved","2025-12-30 21:19:24","2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("76","5","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("77","11","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("78","15","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("79","19","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("80","21","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("81","25","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("82","29","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("83","35","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("84","2","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("85","3","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("86","4","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("87","6","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("88","7","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("89","8","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("90","9","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("91","10","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("92","12","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("93","13","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("94","14","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("95","16","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("96","17","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("97","18","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("98","20","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("99","22","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("100","23","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("101","24","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("102","26","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("103","27","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("104","28","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("105","30","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("106","31","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("107","32","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("108","33","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("109","34","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("110","1","2","Pending",NULL,"2025-12-30 00:12:50");
INSERT INTO `victim_approvals` VALUES("111","116","2","Pending",NULL,"2026-01-05 21:16:14");
INSERT INTO `victim_approvals` VALUES("112","121","7","Approved","2026-01-08 10:28:26","2026-01-07 16:25:01");
INSERT INTO `victim_approvals` VALUES("113","118","7","Pending",NULL,"2026-01-07 16:25:01");
INSERT INTO `victim_approvals` VALUES("114","119","7","Pending",NULL,"2026-01-07 16:25:01");
INSERT INTO `victim_approvals` VALUES("115","123","7","Pending",NULL,"2026-01-08 09:50:44");
INSERT INTO `victim_approvals` VALUES("116","130","7","Approved","2026-01-08 09:57:10","2026-01-08 09:55:43");
INSERT INTO `victim_approvals` VALUES("117","131","5","Pending",NULL,"2026-01-08 10:27:59");
INSERT INTO `victim_approvals` VALUES("118","132","12","Approved","2026-01-08 10:33:35","2026-01-08 10:33:17");
INSERT INTO `victim_approvals` VALUES("119","1","1","Pending",NULL,"2026-01-09 00:48:05");
INSERT INTO `victim_approvals` VALUES("120","71","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("121","72","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("122","73","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("123","74","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("124","75","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("125","76","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("126","77","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("127","78","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("128","79","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("129","80","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("130","81","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("131","82","3","Pending",NULL,"2026-01-09 02:23:25");
INSERT INTO `victim_approvals` VALUES("132","83","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("133","84","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("134","85","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("135","86","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("136","87","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("137","88","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("138","89","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("139","90","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("140","91","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("141","92","3","Pending",NULL,"2026-01-09 02:23:26");
INSERT INTO `victim_approvals` VALUES("142","93","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("143","94","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("144","95","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("145","96","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("146","97","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("147","98","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("148","99","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("149","100","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("150","101","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("151","102","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("152","103","4","Approved","2026-01-09 19:32:02","2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("153","104","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("154","105","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("155","106","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("156","107","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("157","108","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("158","109","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("159","110","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("160","111","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("161","112","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("162","113","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("163","114","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("164","115","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("165","116","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("166","117","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("167","118","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("168","119","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("169","120","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("170","121","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("171","122","4","Pending",NULL,"2026-01-09 19:31:51");
INSERT INTO `victim_approvals` VALUES("172","2","1","Approved","2026-01-09 19:35:09","2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("173","3","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("174","4","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("175","5","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("176","6","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("177","7","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("178","8","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("179","9","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("180","10","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("181","11","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("182","12","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("183","13","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("184","14","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("185","15","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("186","16","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("187","17","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("188","18","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("189","19","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("190","20","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("191","21","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("192","22","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("193","23","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("194","24","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("195","25","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("196","26","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("197","27","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("198","28","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("199","29","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("200","30","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("201","31","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("202","32","1","Pending",NULL,"2026-01-09 19:34:48");
INSERT INTO `victim_approvals` VALUES("203","36","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("204","37","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("205","38","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("206","39","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("207","40","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("208","41","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("209","42","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("210","43","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("211","44","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("212","45","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("213","46","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("214","47","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("215","48","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("216","49","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("217","50","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("218","51","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("219","52","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("220","53","2","Rejected","2026-01-09 23:31:39","2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("221","54","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("222","55","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("223","56","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("224","57","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("225","58","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("226","59","2","Approved","2026-01-09 20:21:25","2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("227","60","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("228","61","2","Pending",NULL,"2026-01-09 20:16:08");
INSERT INTO `victim_approvals` VALUES("229","62","2","Pending",NULL,"2026-01-09 20:16:08");
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

INSERT INTO `victim_coordinates` VALUES("1","50","2.25320000","102.31560000","2026-01-06 20:35:54");
INSERT INTO `victim_coordinates` VALUES("2","66","2.21390000","102.29560000","2026-01-06 21:51:36");
INSERT INTO `victim_coordinates` VALUES("3","81","2.25690000","102.26440000","2026-01-07 03:03:36");
INSERT INTO `victim_coordinates` VALUES("4","40","2.27400000","102.34040000","2026-01-08 01:58:54");
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `volunteer_alerts` VALUES("1","23","104","You have confirmed your assignment for Distribution #104. You can now start the distribution when ready.","1","2026-01-07 03:03:14");
INSERT INTO `volunteer_alerts` VALUES("2","24","113","You have confirmed your assignment for Distribution #113. You can now start the distribution when ready.","1","2026-01-08 01:58:50");
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `volunteer_distribution_assignments` VALUES("2","116","23","General Volunteer","Assigned","2026-01-10 15:08:52","2026-01-10 15:40:29");
