-- ========================================
-- UserManagement Database Export
-- Export Date: 2026-01-14 07:18:41
-- Database: UserManagement
-- ========================================

USE [UserManagement];
GO


-- ========================================
-- Table: AccountLocks
-- ========================================

-- DELETE existing data
DELETE FROM [AccountLocks];
GO

-- INSERT new data
GO


-- ========================================
-- Table: Admin
-- ========================================

-- DELETE existing data
DELETE FROM [Admin];
GO

-- INSERT new data
INSERT INTO [Admin] ([AdminID], [FullName], [Email], [PasswordHash], [Phone], [Role], [CreatedAt]) VALUES (4, 'yana syakinahs', 'niklyana12@gmail.com', '$2y$10$F0FOlGjz/76.usrjrfti.ORwKhtPKeoy0MZyj1LsDpthcHFpynANW', '0183632489', 'admin', '2025-12-05 21:31:48');
INSERT INTO [Admin] ([AdminID], [FullName], [Email], [PasswordHash], [Phone], [Role], [CreatedAt]) VALUES (5, 'hariz', 'hariz@gmail.com', '$2y$10$Zz9wzqaObi.gbzpMYo4XuuDrWpauo6Pu6PU7qBoljhGSBfWMHTnca', '0183632488', 'Admin', '2025-12-10 15:24:28');
GO


-- ========================================
-- Table: Audit_Log
-- ========================================

-- DELETE existing data
DELETE FROM [Audit_Log];
GO

-- INSERT new data
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (1, 'NGO', 5, 'UPDATE', 'NGOName: Pertubuhan Wanita Sejahtera Melaka, RegistrationNo: REG005, Email: wanitasejahtera@example.com, Phone: 011-2233445, Address: Dewan Muslimat PAS Melaka, Jalan Kota, 75000 Melaka, PasswordHash: 1234, CreatedAt: 2025-11-17 22:23:58, Status: active', 'NGOName: Green Future, RegistrationNo: REG005, Email: wanitasejahtera@example.com, Phone: 011-2233445, Address: Dewan Muslimat PAS Melaka, Jalan Kota, 75000 Melaka, PasswordHash: 1234, CreatedAt: 2025-11-17 22:23:58, Status: active', '2026-01-11 22:29:18', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (2, 'NGO', 22, 'UPDATE', 'NGOName: melaka tengah, RegistrationNo: REG007, Email: kk@gmail.com, Phone: 0112233446, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$kThHauZ6veBScx2eTDuckO.j0SzqjDH3FVUh6yiVWInT0InnpVg1e, CreatedAt: 2025-12-16 21:37:41, Status: active', 'NGOName: melaka  tengah batu, RegistrationNo: REG007, Email: kk@gmail.com, Phone: 0112233446, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$kThHauZ6veBScx2eTDuckO.j0SzqjDH3FVUh6yiVWInT0InnpVg1e, CreatedAt: 2025-12-16 21:37:41, Status: active', '2026-01-11 22:30:39', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (3, 'Volunteer', 12, 'UPDATE', 'FullName: yaya yana, Email: yaya@gmail.com, Phone: 0194456783, Address: melaka tengah, AssignedNGO: 22, PasswordHash: $2y$10$FDZpNx2w/z3mVEqASMUzKeaK9wJ4e0gdIBhnptAd7e5YRZx00f94q, SkillCategory: Communication, General Volunteer, Status: active', 'FullName: yaya, Email: yaya@gmail.com, Phone: 0194456783, Address: melaka tengah, AssignedNGO: 22, PasswordHash: $2y$10$FDZpNx2w/z3mVEqASMUzKeaK9wJ4e0gdIBhnptAd7e5YRZx00f94q, SkillCategory: Communication, General Volunteer, Status: active', '2026-01-11 22:33:30', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (4, 'News', 1007, 'INSERT', NULL, 'Title: hi, Description: okokokokakkakaakksssssssssssssssssssssssssssssssssxkkkkkkkkkkkkkkkkkkkkkkkkkkk, ImageURL: , CreatedAt: 2026-01-11 22:40:55, CreatedBy: melaka  tengah batu', '2026-01-11 22:40:55', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (5, 'News', 1007, 'DELETE', 'Title: hi, Description: okokokokakkakaakksssssssssssssssssssssssssssssssssxkkkkkkkkkkkkkkkkkkkkkkkkkkk, ImageURL: , CreatedAt: 2026-01-11 22:40:55, CreatedBy: melaka  tengah batu', NULL, '2026-01-11 22:40:59', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (6, 'Volunteer', 1026, 'INSERT', NULL, 'FullName: Daniel, Email: ikmal@gmail.com, Phone: 01117845504, Address: University Teknikal Malaysia Melaka, AssignedNGO: 1030, PasswordHash: $2y$10$UrEpnn.JzMampMNcfnTH3uC0.JpFZhkVe6V9TZpDgYlGhg7Yqc3ty, SkillCategory: Logistics / Supplies, Food Services, Status: active', '2026-01-12 15:21:24', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (7, 'Admin', 4, 'UPDATE', 'FullName: yana syakinah, Email: niklyana12@gmail.com, Phone: 0183632488, Role: admin, CreatedAt: 2025-12-05 21:31:48', 'FullName: yana syakinah, Email: niklyana12@gmail.com, Phone: 0183632489, Role: admin, CreatedAt: 2025-12-05 21:31:48', '2026-01-12 15:29:30', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (8, 'Admin', 4, 'UPDATE', 'FullName: yana syakinah, Email: niklyana12@gmail.com, Phone: 0183632489, Role: admin, CreatedAt: 2025-12-05 21:31:48', 'FullName: yana syakinahs, Email: niklyana12@gmail.com, Phone: 0183632489, Role: admin, CreatedAt: 2025-12-05 21:31:48', '2026-01-12 15:30:04', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (9, 'News', 7, 'DELETE', 'Title: beach cleanup, Description: today ..............................................................................................................................., ImageURL: uploads/news/695f0e47dbf1c_1767837255.png, CreatedAt: 2026-01-08 09:54:15, CreatedBy: melaka tengah', NULL, '2026-01-13 14:52:30', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (10, 'News', 5, 'DELETE', 'Title: AT 2008 HAS, Description: MUUUMUMUMUMUMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM, ImageURL: uploads/news/69482a0ebd2e3_1766337038.png, CreatedAt: 2025-12-22 01:10:38, CreatedBy: kota kinabalu', NULL, '2026-01-13 14:52:34', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (11, 'Volunteer', 23, 'DELETE', 'FullName: Ikmal Daniel, Email: imadaniel2004@gmail.com, Phone: 01117845504, Address: Durian Tunggal, Melaka, AssignedNGO: 23, PasswordHash: $2y$10$wP8C1vcU9kzj46JJpUjOhOTDhc.SMNSUE3T7Ys63Nxmwn9MLH86XC, SkillCategory: General Volunteer, Status: active', NULL, '2026-01-13 14:54:27', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (12, 'Volunteer', 22, 'DELETE', 'FullName: Amirul Hadi, Email: amirul@gmail.com, Phone: 0128888888, Address: Pantai Kundor, AssignedNGO: 23, PasswordHash: hash8, SkillCategory: Rescue, Status: Active', NULL, '2026-01-13 14:54:27', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (13, 'Volunteer', 21, 'DELETE', 'FullName: Muhammad Akmal, Email: akmal@gmail.com, Phone: 0124444444, Address: Bukit Katil, AssignedNGO: 23, PasswordHash: hash4, SkillCategory: Rescue, Status: Active', NULL, '2026-01-13 14:54:27', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (14, 'NGO', 23, 'DELETE', 'NGOName: KlebangTeam, RegistrationNo: REG008, Email: klebangTeam@gmail.com, Phone: 0183632483, Address: klebang , melaka , 4261o, PasswordHash: $2y$10$F.c4hvUZpvqek//BDS0znOpBcHwfr9mYzhY711x/clsslOdo09S0q, CreatedAt: 2025-12-23 22:31:25, Status: Approved', NULL, '2026-01-13 14:54:27', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (15, 'Volunteer', 1025, 'DELETE', 'FullName: darmen, Email: darmen@gmail.com, Phone: 0112233446, Address: no 10, bandar saujana putran , jenajrom , 42610, AssignedNGO: 22, PasswordHash: $2y$10$db89NvgtvFf9m3vP1auegOayTM5dWhuVTHVm9O6/XinItywa34gze, SkillCategory: Medical / First Aid, Logistics / Supplies, Status: active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (16, 'Volunteer', 20, 'DELETE', 'FullName: Nur Syafiqah, Email: syafiqah@gmail.com, Phone: 0127777777, Address: Ayer Keroh, AssignedNGO: 22, PasswordHash: hash7, SkillCategory: Medical, Status: Active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (17, 'Volunteer', 19, 'DELETE', 'FullName: Nur Iman, Email: iman@gmail.com, Phone: 0123333333, Address: Alor Gajah, AssignedNGO: 22, PasswordHash: $2y$10$rbGM2J2sHkMjf5hMqLkCZOT4QyIkzHNq5oojh5mxM9d9FdW/eFJIS, SkillCategory: Food Distribution, Status: Active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (18, 'Volunteer', 12, 'DELETE', 'FullName: yaya, Email: yaya@gmail.com, Phone: 0194456783, Address: melaka tengah, AssignedNGO: 22, PasswordHash: $2y$10$FDZpNx2w/z3mVEqASMUzKeaK9wJ4e0gdIBhnptAd7e5YRZx00f94q, SkillCategory: Communication, General Volunteer, Status: active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (19, 'Volunteer', 11, 'DELETE', 'FullName: syakinah, Email: syakinah@gmai.com, Phone: 0194456789, Address: no 10, bandar saujana putran , jenajrom , 42610, AssignedNGO: 22, PasswordHash: $2y$10$ae0fTfP8a5881ZNCpS/ukOl2hOSl6/5S4VrcFnaf17zQrAl2IIFkC, SkillCategory: Helper, Status: active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (20, 'NGO', 22, 'DELETE', 'NGOName: melaka  tengah batu, RegistrationNo: REG007, Email: kk@gmail.com, Phone: 0112233446, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$kThHauZ6veBScx2eTDuckO.j0SzqjDH3FVUh6yiVWInT0InnpVg1e, CreatedAt: 2025-12-16 21:37:41, Status: active', NULL, '2026-01-13 14:54:37', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (21, 'Volunteer', 18, 'DELETE', 'FullName: Haziq Aiman, Email: haziq@gmail.com, Phone: 0129999999, Address: Cheng, AssignedNGO: 20, PasswordHash: hash9, SkillCategory: Food Distribution, Status: Active', NULL, '2026-01-13 14:54:43', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (22, 'Volunteer', 17, 'DELETE', 'FullName: Daniel Hakim, Email: daniel@gmail.com, Phone: 0126666666, Address: Durian Tunggal, AssignedNGO: 20, PasswordHash: hash6, SkillCategory: Logistics, Status: Active', NULL, '2026-01-13 14:54:43', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (23, 'Volunteer', 16, 'DELETE', 'FullName: Siti Aisyah, Email: aisyah@gmail.com, Phone: 0122222222, Address: Jasin, AssignedNGO: 20, PasswordHash: hash2, SkillCategory: Medical, Status: Active', NULL, '2026-01-13 14:54:43', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (24, 'Volunteer', 1, 'DELETE', 'FullName: Aiman Razak, Email: aiman@example.com, Phone: 0134455667, Address: Kuala Terengganu, AssignedNGO: 20, PasswordHash: $2y$10$ltxg/pHqpMHtVauKKxNEIur45Itf5FTa71Z7xk.RjKqVDvRG2cp/O, SkillCategory: Food Supply, Status: active', NULL, '2026-01-13 14:54:43', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (25, 'NGO', 20, 'DELETE', 'NGOName: melakacentral, RegistrationNo: REG006, Email: melaka@gmal.com, Phone: 0183632483, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$ekqERmldXxxf4QU5jqbO5eZVz9NFXhtj/57OYDpHYHA0WmyGP6Ze2, CreatedAt: 2025-12-14 19:44:51, Status: active', NULL, '2026-01-13 14:54:44', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (26, 'Volunteer', 15, 'DELETE', 'FullName: Nabila Farah, Email: nabila@gmail.com, Phone: 0130000000, Address: Bachang, AssignedNGO: 5, PasswordHash: hash10, SkillCategory: Counselling, Status: Active', NULL, '2026-01-13 14:54:48', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (27, 'Volunteer', 14, 'DELETE', 'FullName: Aina Sofea, Email: aina@gmail.com, Phone: 0125555555, Address: Masjid Tanah, AssignedNGO: 5, PasswordHash: hash5, SkillCategory: Counselling, Status: Active', NULL, '2026-01-13 14:54:48', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (28, 'Volunteer', 13, 'DELETE', 'FullName: Ahmad Firdaus, Email: firdaus@gmail.com, Phone: 0121111111, Address: Melaka Tengah, AssignedNGO: 5, PasswordHash: hash1, SkillCategory: Logistics, Status: Active', NULL, '2026-01-13 14:54:48', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (29, 'Volunteer', 9, 'DELETE', 'FullName: yana, Email: yana123@student, Phone: 888764, Address: bb, AssignedNGO: 5, PasswordHash: 123, SkillCategory: Rescue, Status: active', NULL, '2026-01-13 14:54:48', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (30, 'NGO', 5, 'DELETE', 'NGOName: Green Future, RegistrationNo: REG005, Email: wanitasejahtera@example.com, Phone: 011-2233445, Address: Dewan Muslimat PAS Melaka, Jalan Kota, 75000 Melaka, PasswordHash: 1234, CreatedAt: 2025-11-17 22:23:58, Status: active', NULL, '2026-01-13 14:54:48', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (31, 'NGO', 1039, 'UPDATE', 'NGOName: melaka, RegistrationNo: REG016, Email: melaka@gmail.com, Phone: 0183632483, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$9U1Y2MNF5aw3TLPFsXKy2.iEXViWbzUlMCpgnko/DR1wWjnHDPy6C, CreatedAt: 2026-01-08 09:43:13, Status: Approved', 'NGOName: melaka, RegistrationNo: REG016, Email: melaka@gmail.com, Phone: 0183632483, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$9U1Y2MNF5aw3TLPFsXKy2.iEXViWbzUlMCpgnko/DR1wWjnHDPy6C, CreatedAt: 2026-01-08 09:43:13, Status: Rejected', '2026-01-13 14:55:55', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (32, 'NGO', 1039, 'DELETE', 'NGOName: melaka, RegistrationNo: REG016, Email: melaka@gmail.com, Phone: 0183632483, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$10$9U1Y2MNF5aw3TLPFsXKy2.iEXViWbzUlMCpgnko/DR1wWjnHDPy6C, CreatedAt: 2026-01-08 09:43:13, Status: Rejected', NULL, '2026-01-13 14:55:59', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (33, 'NGO', 1032, 'UPDATE', 'NGOName: Ayer Keroh Care, RegistrationNo: REG009, Email: ayerkerohcare@gmail.com, Phone: 0191011122, Address: Ayer Keroh, Melaka, PasswordHash: 1234, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: Ayer Keroh Care, RegistrationNo: REG009, Email: ayerkerohcare@gmail.com, Phone: 0191011122, Address: Ayer Keroh, Melaka, PasswordHash: $2y$10$wM9NkCENPvsGQNZlr8jxNOu5W2poyws2lpbsjCrSM7M4CayIC.kwu, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 14:57:00', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (34, 'NGO', 1032, 'UPDATE', 'NGOName: Ayer Keroh Care, RegistrationNo: REG009, Email: ayerkerohcare@gmail.com, Phone: 0191011122, Address: Ayer Keroh, Melaka, PasswordHash: $2y$10$wM9NkCENPvsGQNZlr8jxNOu5W2poyws2lpbsjCrSM7M4CayIC.kwu, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: Ayer Keroh Care, RegistrationNo: REG009, Email: ayerkerohcare@gmail.com, Phone: 0191011122, Address: Ayer Keroh, Melaka, PasswordHash: $2y$10$O.1yLsT5OMRzFxkv4d8QJOdeDfJIxRgsoKtLxG4XrARYGFWtLMYR., CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 14:58:57', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (35, 'News', 1008, 'INSERT', NULL, 'Title: Program Gotong-Royong Komuniti, Description: A community clean-up program was successfully conducted at Batu Berendam to promote environmental awareness and maintain public cleanliness. Volunteers from the local community worked together to clean public areas, remove waste, and clear clogged drains.

The main challenge faced was the large amount of rubbish accumulated after recent heavy rain. Despite this, the team managed to collect several bags of waste and restore the cleanliness of the area.

This initiative helped strengthen community cooperation and improved the overall environment for residents. The program also encouraged the public to take responsibility for maintaining a clean and healthy living area., ImageURL: uploads/news/6965f0549b4fd_1768288340.jpg, CreatedAt: 2026-01-13 15:12:20, CreatedBy: Ayer Keroh Care', '2026-01-13 15:12:20', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (36, 'News', 1008, 'DELETE', 'Title: Program Gotong-Royong Komuniti, Description: A community clean-up program was successfully conducted at Batu Berendam to promote environmental awareness and maintain public cleanliness. Volunteers from the local community worked together to clean public areas, remove waste, and clear clogged drains.

The main challenge faced was the large amount of rubbish accumulated after recent heavy rain. Despite this, the team managed to collect several bags of waste and restore the cleanliness of the area.

This initiative helped strengthen community cooperation and improved the overall environment for residents. The program also encouraged the public to take responsibility for maintaining a clean and healthy living area., ImageURL: uploads/news/6965f0549b4fd_1768288340.jpg, CreatedAt: 2026-01-13 15:12:20, CreatedBy: Ayer Keroh Care', NULL, '2026-01-13 15:13:32', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (37, 'News', 1009, 'INSERT', NULL, 'Title: Community Clean-Up Program at Batu Berendam, Description: On 12 January 2026, from 8:00 AM to 12:00 PM, a community clean-up program was successfully conducted at Batu Berendam to promote environmental awareness and maintain public cleanliness.

Volunteers from the local community worked together to clean public areas, remove waste, and clear clogged drains. The main challenge faced was the large amount of rubbish accumulated after recent heavy rain.

Despite this, the team managed to collect several bags of waste and restore the cleanliness of the area.

This initiative helped strengthen community cooperation and improved the overall environment for residents. The program also encouraged the public to take responsibility for maintaining a clean and healthy living area., ImageURL: uploads/news/6965f11fc9d96_1768288543.jpg, CreatedAt: 2026-01-13 15:15:43, CreatedBy: Ayer Keroh Care', '2026-01-13 15:15:43', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (38, 'NGO', 1034, 'UPDATE', 'NGOName: UTeM Volunteer Network, RegistrationNo: REG011, Email: utemvolunteer@gmail.com, Phone: 0123031334, Address: Durian Tunggal, Melaka, PasswordHash: 1234, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: UTeM Volunteer Network, RegistrationNo: REG011, Email: utemvolunteer@gmail.com, Phone: 0123031334, Address: Durian Tunggal, Melaka, PasswordHash: $2y$10$KWoY0B8CjX.pLTlgnWBSrOr4Hu5aG.UEq0bUQsvPRMVpOAHHaIY7S, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 15:16:11', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (39, 'News', 1010, 'INSERT', NULL, 'Title: Road Safety Awareness Program in Melaka Tengah, Description: On 13 January 2026, from 9:00 AM to 11:30 AM, a road safety awareness program was organized in Melaka Tengah to educate the public on safe driving practices.

The program focused on the importance of wearing helmets, obeying traffic rules, and reducing speeding. Officers and volunteers shared safety information with road users and distributed educational materials.

Some challenges included reaching younger road users who often ignore traffic rules.

The program successfully raised awareness and encouraged safer behaviour among the community, contributing to a safer road environment., ImageURL: uploads/news/6965f19465431_1768288660.jpg, CreatedAt: 2026-01-13 15:17:40, CreatedBy: UTeM Volunteer Network', '2026-01-13 15:17:40', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (40, 'NGO', 1037, 'UPDATE', 'NGOName: Masjid Relief Melaka, RegistrationNo: REG014, Email: masjidrelief@gmail.com, Phone: 0156061667, Address: Tanjung Minyak, Melaka, PasswordHash: 1234, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: Masjid Relief Melaka, RegistrationNo: REG014, Email: masjidrelief@gmail.com, Phone: 0156061667, Address: Tanjung Minyak, Melaka, PasswordHash: $2y$10$ziLIuc0gCOnvcTcNy1ZgruZMevkyzMyN3YLpLAqvWaExo.JloXhoK, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 15:18:32', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (41, 'News', 1011, 'INSERT', NULL, 'Title: Community Aid Distribution in Melaka Tengah, Description: On 14 January 2026, from 10:00 AM to 1:00 PM, a community aid distribution program was carried out in Melaka Tengah to support families in need.

Essential items such as food supplies and basic necessities were distributed to selected households. The main challenge was ensuring that assistance reached the right recipients on time.

With good coordination, the team successfully delivered the aid.

This program helped reduce the burden on low-income families and strengthened the spirit of unity within the community., ImageURL: uploads/news/6965f20c9f97a_1768288780.jpg, CreatedAt: 2026-01-13 15:19:40, CreatedBy: Masjid Relief Melaka', '2026-01-13 15:19:40', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (42, 'NGO', 1030, 'UPDATE', 'NGOName: Melaka Flood Aid, RegistrationNo: REG007, Email: melakafloodaid@gmail.com, Phone: 0178899001, Address: Klebang, Melaka, PasswordHash: 1234, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: Melaka Flood Aid, RegistrationNo: REG007, Email: melakafloodaid@gmail.com, Phone: 0178899001, Address: Klebang, Melaka, PasswordHash: $2y$10$/XW8o5P8RoDHQyiMfQqJ1O4FRkx9UfQBazAyHFzNMswXFAlCUIjSK, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 15:20:16', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (43, 'News', 1012, 'INSERT', NULL, 'Title: Blood Donation Campaign in Melaka Tengah, Description: On 15 January 2026, from 9:00 AM to 2:00 PM, a blood donation campaign was organized in Melaka Tengah to help maintain sufficient blood supply for local hospitals.

Members of the public participated actively despite the hot weather. The main challenge was encouraging first-time donors who were nervous about the process.

Overall, the campaign was successful and contributed to saving lives through community involvement., ImageURL: uploads/news/6965f28296d6e_1768288898.jpeg, CreatedAt: 2026-01-13 15:21:38, CreatedBy: Melaka Flood Aid', '2026-01-13 15:21:38', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (44, 'NGO', 1036, 'UPDATE', 'NGOName: Cheng Community Support, RegistrationNo: REG013, Email: chengsupport@gmail.com, Phone: 0145051556, Address: Cheng, Melaka, PasswordHash: 1234, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: Cheng Community Support, RegistrationNo: REG013, Email: chengsupport@gmail.com, Phone: 0145051556, Address: Cheng, Melaka, PasswordHash: $2y$10$RubF/RR88O3SxY5VHnJ8JeH78rqSC8xuCLqQ6zvVNZXT3or4dQHsW, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 15:22:11', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (45, 'News', 1013, 'INSERT', NULL, 'Title: chengsupport@gmail.com, Description: On 16 January 2026, from 8:30 AM to 11:30 AM, a health awareness program was conducted to educate residents about healthy lifestyles and disease prevention.

Health screenings such as blood pressure and BMI checks were provided. Some participants required further medical advice.

The program helped increase awareness of personal health and encouraged early detection of health issues., ImageURL: uploads/news/6965f2d267adf_1768288978.jpg, CreatedAt: 2026-01-13 15:22:58, CreatedBy: Cheng Community Support', '2026-01-13 15:22:58', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (46, 'News', 1014, 'INSERT', NULL, 'Title: chengsupport@gmail.com, Description: On 16 January 2026, from 8:30 AM to 11:30 AM, a health awareness program was conducted to educate residents about healthy lifestyles and disease prevention.

Health screenings such as blood pressure and BMI checks were provided. Some participants required further medical advice.

The program helped increase awareness of personal health and encouraged early detection of health issues., ImageURL: uploads/news/6965f491a13c8_1768289425.jpg, CreatedAt: 2026-01-13 15:30:26, CreatedBy: Cheng Community Support', '2026-01-13 15:30:27', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (47, 'News', 1014, 'DELETE', 'Title: chengsupport@gmail.com, Description: On 16 January 2026, from 8:30 AM to 11:30 AM, a health awareness program was conducted to educate residents about healthy lifestyles and disease prevention.

Health screenings such as blood pressure and BMI checks were provided. Some participants required further medical advice.

The program helped increase awareness of personal health and encouraged early detection of health issues., ImageURL: uploads/news/6965f491a13c8_1768289425.jpg, CreatedAt: 2026-01-13 15:30:26, CreatedBy: Cheng Community Support', NULL, '2026-01-13 15:31:52', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (48, 'NGO', 2039, 'INSERT', NULL, 'NGOName: Pusat Pemulihan Dalam Komuniti (PDK) Seri Utama, RegistrationNo: REG016, Email: PDK@gmail.com, Phone: 0112233446, Address: no 10, bandar saujana putran , jenajrom , 42610, PasswordHash: $2y$12$fIyz2zolgbqe3JOEHSkND.tFMrEZ/WRtKIZ8xEQmgtTY2ZUQkXWOm, CreatedAt: 2026-01-13 15:48:06, Status: Pending', '2026-01-13 15:48:06', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (49, 'Volunteer', 1027, 'INSERT', NULL, 'FullName: Sufiana, Email: sufiana@gmail.com, Phone: 0194456783, Address: no 9, bandar saujana putran , jenajrom , 42610, AssignedNGO: 1024, PasswordHash: $2y$12$iaU/2pJs7wlkNEiBC/FjHOLEatUIf1yc8u0XdAWv/18HAxPKfYl36, SkillCategory: Technical Support, Counseling / Support, Status: active', '2026-01-13 16:39:59', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (50, 'Volunteer', 1042, 'INSERT', NULL, 'FullName: Faris Akmal, Email: faris@gmail.com, Phone: 0118899001, Address: Ayer Keroh, Melaka, AssignedNGO: 2039, PasswordHash: $2y$12$8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A, SkillCategory: Community Outreach, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (51, 'Volunteer', 1041, 'INSERT', NULL, 'FullName: Nur Syuhada, Email: syuhada@gmail.com, Phone: 0117788990, Address: Melaka Tengah, AssignedNGO: 1036, PasswordHash: $2y$12$HQwZ3B2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5, SkillCategory: Counseling, Support, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (52, 'Volunteer', 1040, 'INSERT', NULL, 'FullName: Arif Haziq, Email: arif@gmail.com, Phone: 0116677889, Address: Ayer Keroh, Melaka, AssignedNGO: 1035, PasswordHash: $2y$12$C1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9u, SkillCategory: Rescue Team, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (53, 'Volunteer', 1039, 'INSERT', NULL, 'FullName: Amirah Sofia, Email: amirah@gmail.com, Phone: 0115566778, Address: Bukit Baru, Melaka, AssignedNGO: 1034, PasswordHash: $2y$12$B2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3, SkillCategory: Food Distribution, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (54, 'Volunteer', 1038, 'INSERT', NULL, 'FullName: Hafiz Ridzuan, Email: hafiz@gmail.com, Phone: 0114455667, Address: Ayer Keroh, Melaka, AssignedNGO: 1033, PasswordHash: $2y$12$ZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tO, SkillCategory: Driving, Logistics, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (55, 'Volunteer', 1037, 'INSERT', NULL, 'FullName: Nabila Zahirah, Email: nabila@gmail.com, Phone: 0113344556, Address: Melaka Tengah, AssignedNGO: 1032, PasswordHash: $2y$12$TtCkR1V9yN5HQwZ3B2S5E1r8A7xM6FJ0PZL4, SkillCategory: Public Relations, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (56, 'Volunteer', 1036, 'INSERT', NULL, 'FullName: Syafiq Azman, Email: syafiq@gmail.com, Phone: 0112233445, Address: Ayer Keroh, Melaka, AssignedNGO: 1031, PasswordHash: $2y$12$QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0, SkillCategory: Technical Support, IT, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (57, 'Volunteer', 1035, 'INSERT', NULL, 'FullName: Aisyah Amirah, Email: aisyah@gmail.com, Phone: 0190123456, Address: Bukit Katil, Melaka, AssignedNGO: 1030, PasswordHash: $2y$12$5E1r8A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3B2S, SkillCategory: Child Care, Teaching, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (58, 'Volunteer', 1034, 'INSERT', NULL, 'FullName: Muhammad Izzat, Email: izzat@gmail.com, Phone: 0189012345, Address: Ayer Keroh, Melaka, AssignedNGO: 1029, PasswordHash: $2y$12$R3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7s, SkillCategory: Security Support, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (59, 'Volunteer', 1033, 'INSERT', NULL, 'FullName: Farah Nabila, Email: farahnabila@gmail.com, Phone: 0178901234, Address: Batu Berendam, Melaka, AssignedNGO: 1028, PasswordHash: $2y$12$A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3B2S5E1r8, SkillCategory: Logistics, Packing Aid, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (60, 'Volunteer', 1032, 'INSERT', NULL, 'FullName: Aiman Roslan, Email: aimanroslan@gmail.com, Phone: 0167890123, Address: Ayer Keroh, Melaka, AssignedNGO: 1027, PasswordHash: $2y$12$K2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1v, SkillCategory: Search & Rescue, Physical Labour, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (61, 'Volunteer', 1031, 'INSERT', NULL, 'FullName: Siti Balqis, Email: sitibalqis@gmail.com, Phone: 0156789012, Address: Durian Tunggal, Melaka, AssignedNGO: 1026, PasswordHash: $2y$12$9yN5HQwZ3B2S5E1r8A7xM6FJ0PZL4TtCkR1V, SkillCategory: Counseling / Support, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (62, 'Volunteer', 1030, 'INSERT', NULL, 'FullName: Daniel Hakim, Email: danielhakim@gmail.com, Phone: 0145678901, Address: MITC, Melaka, AssignedNGO: 1025, PasswordHash: $2y$12$FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8, SkillCategory: Technical Support, Admin / Office Work, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (63, 'Volunteer', 1029, 'INSERT', NULL, 'FullName: Nur Aina, Email: nuraina@gmail.com, Phone: 0134567890, Address: Bukit Beruang, Melaka, AssignedNGO: 1024, PasswordHash: $2y$12$QwZ3B9K2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5H, SkillCategory: Food Services, Communication, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (64, 'Volunteer', 1028, 'INSERT', NULL, 'FullName: Ahmad Firdaus, Email: ahmad.firdaus@gmail.com, Phone: 0123456789, Address: Ayer Keroh, Melaka, AssignedNGO: 1023, PasswordHash: $2y$12$Yw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1K, SkillCategory: Medical / First Aid, Driving, Status: Active', '2026-01-13 16:47:35', 'yanadb');
INSERT INTO [Audit_Log] ([AuditID], [TableName], [RecordID], [ActionType], [OldData], [NewData], [ActionDate], [ActionBy]) VALUES (65, 'NGO', 1034, 'UPDATE', 'NGOName: UTeM Volunteer Network, RegistrationNo: REG011, Email: utemvolunteer@gmail.com, Phone: 0123031334, Address: Durian Tunggal, Melaka, PasswordHash: $2y$10$KWoY0B8CjX.pLTlgnWBSrOr4Hu5aG.UEq0bUQsvPRMVpOAHHaIY7S, CreatedAt: 2026-01-02 17:50:02, Status: Active', 'NGOName: UTeM Volunteer Network, RegistrationNo: REG011, Email: utemvolunteer@gmail.com, Phone: 0123031334, Address: Durian Tunggal, Melaka, PasswordHash: $2y$10$dLzzFgHUks5PHOCzkTKFmeP2rRUDA5kzJbGNzM6v0VweSm0A4DSuC, CreatedAt: 2026-01-02 17:50:02, Status: Active', '2026-01-13 16:56:11', 'yanadb');
GO


-- ========================================
-- Table: IPBlocks
-- ========================================

-- DELETE existing data
DELETE FROM [IPBlocks];
GO

-- INSERT new data
INSERT INTO [IPBlocks] ([id], [ip_address], [block_until], [reason], [created_at]) VALUES (1, '127.0.0.1', '2026-01-13 16:21:29', 'Too many failed login attempts', '2026-01-13 16:06:29');
INSERT INTO [IPBlocks] ([id], [ip_address], [block_until], [reason], [created_at]) VALUES (2, '127.0.0.1', '2026-01-13 16:21:29', 'Too many failed login attempts', '2026-01-13 16:06:29');
INSERT INTO [IPBlocks] ([id], [ip_address], [block_until], [reason], [created_at]) VALUES (3, '127.0.0.1', '2026-01-13 16:37:06', 'Too many failed login attempts', '2026-01-13 16:22:06');
INSERT INTO [IPBlocks] ([id], [ip_address], [block_until], [reason], [created_at]) VALUES (4, '127.0.0.1', '2026-01-13 16:37:06', 'Too many failed login attempts', '2026-01-13 16:22:06');
GO


-- ========================================
-- Table: LoginAttempts
-- ========================================

-- DELETE existing data
DELETE FROM [LoginAttempts];
GO

-- INSERT new data
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (1, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:17', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (2, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:17', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (3, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:24', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (4, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:24', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (5, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:29', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (6, 'ayerkerohcare@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:06:29', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (7, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:01', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (8, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:01', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (9, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:01', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (10, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:06', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (11, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:06', 0);
INSERT INTO [LoginAttempts] ([id], [email], [ip_address], [user_agent], [attempt_time], [success]) VALUES (12, 'niklyana12@gmail.com', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-13 16:22:06', 0);
GO


-- ========================================
-- Table: LoginLocks
-- ========================================

-- DELETE existing data
DELETE FROM [LoginLocks];
GO

-- INSERT new data
INSERT INTO [LoginLocks] ([id], [email], [failed_attempts], [lock_until], [last_attempt]) VALUES (1, 'ayerkerohcare@gmail.com', 3, '2026-01-13 17:01:23', '2026-01-13 16:46:23');
INSERT INTO [LoginLocks] ([id], [email], [failed_attempts], [lock_until], [last_attempt]) VALUES (2, 'niklyana12@gmail.com', 0, NULL, '2026-01-13 16:47:03');
GO


-- ========================================
-- Table: News
-- ========================================

-- DELETE existing data
DELETE FROM [News];
GO

-- INSERT new data
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (1009, 'Community Clean-Up Program at Batu Berendam', 'On 12 January 2026, from 8:00 AM to 12:00 PM, a community clean-up program was successfully conducted at Batu Berendam to promote environmental awareness and maintain public cleanliness.

Volunteers from the local community worked together to clean public areas, remove waste, and clear clogged drains. The main challenge faced was the large amount of rubbish accumulated after recent heavy rain.

Despite this, the team managed to collect several bags of waste and restore the cleanliness of the area.

This initiative helped strengthen community cooperation and improved the overall environment for residents. The program also encouraged the public to take responsibility for maintaining a clean and healthy living area.', 'uploads/news/6965f11fc9d96_1768288543.jpg', '2026-01-13 15:15:43', 'Ayer Keroh Care');
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (1010, 'Road Safety Awareness Program in Melaka Tengah', 'On 13 January 2026, from 9:00 AM to 11:30 AM, a road safety awareness program was organized in Melaka Tengah to educate the public on safe driving practices.

The program focused on the importance of wearing helmets, obeying traffic rules, and reducing speeding. Officers and volunteers shared safety information with road users and distributed educational materials.

Some challenges included reaching younger road users who often ignore traffic rules.

The program successfully raised awareness and encouraged safer behaviour among the community, contributing to a safer road environment.', 'uploads/news/6965f19465431_1768288660.jpg', '2026-01-13 15:17:40', 'UTeM Volunteer Network');
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (1011, 'Community Aid Distribution in Melaka Tengah', 'On 14 January 2026, from 10:00 AM to 1:00 PM, a community aid distribution program was carried out in Melaka Tengah to support families in need.

Essential items such as food supplies and basic necessities were distributed to selected households. The main challenge was ensuring that assistance reached the right recipients on time.

With good coordination, the team successfully delivered the aid.

This program helped reduce the burden on low-income families and strengthened the spirit of unity within the community.', 'uploads/news/6965f20c9f97a_1768288780.jpg', '2026-01-13 15:19:40', 'Masjid Relief Melaka');
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (1012, 'Blood Donation Campaign in Melaka Tengah', 'On 15 January 2026, from 9:00 AM to 2:00 PM, a blood donation campaign was organized in Melaka Tengah to help maintain sufficient blood supply for local hospitals.

Members of the public participated actively despite the hot weather. The main challenge was encouraging first-time donors who were nervous about the process.

Overall, the campaign was successful and contributed to saving lives through community involvement.', 'uploads/news/6965f28296d6e_1768288898.jpeg', '2026-01-13 15:21:38', 'Melaka Flood Aid');
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (1013, 'chengsupport@gmail.com', 'On 16 January 2026, from 8:30 AM to 11:30 AM, a health awareness program was conducted to educate residents about healthy lifestyles and disease prevention.

Health screenings such as blood pressure and BMI checks were provided. Some participants required further medical advice.

The program helped increase awareness of personal health and encouraged early detection of health issues.', 'uploads/news/6965f2d267adf_1768288978.jpg', '2026-01-13 15:22:58', 'Cheng Community Support');
GO


-- ========================================
-- Table: NGO
-- ========================================

-- DELETE existing data
DELETE FROM [NGO];
GO

-- INSERT new data
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1023, 'Kualalegit', 'REG009', 'Kualalegit@gmail.com', '0178900010', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$10$/Fkh4UJ7qbCtSW4DKeF.cOrQDeyJh0o6YNAFQQ8CuTqjrU0zZScqC', '2026-01-02 00:05:40', 'Pending');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1024, 'Pertubuhan Wanita Sejahtera Melaka', 'REG001', 'pwsmelaka@gmail.com', '0112233445', 'Bandar Hilir, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1025, 'Melaka Care Foundation', 'REG002', 'melakacare@gmail.com', '0123344556', 'Ayer Keroh, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1026, 'NGO Prihatin Melaka', 'REG003', 'ngoprihatinmelaka@gmail.com', '0134455667', 'Bukit Beruang, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1027, 'Central Melaka Relief Team', 'REG004', 'centralmelaka@gmail.com', '0145566778', 'Melaka Tengah, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1028, 'Jasin Community Help', 'REG005', 'jasinhelp@gmail.com', '0156677889', 'Jasin, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1029, 'Alor Gajah Volunteer Group', 'REG006', 'agvolunteer@gmail.com', '0167788990', 'Alor Gajah, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1030, 'Melaka Flood Aid', 'REG007', 'melakafloodaid@gmail.com', '0178899001', 'Klebang, Melaka', '$2y$10$/XW8o5P8RoDHQyiMfQqJ1O4FRkx9UfQBazAyHFzNMswXFAlCUIjSK', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1031, 'Klebang Relief Team', 'REG008', 'klebangrelief@gmail.com', '0189900112', 'Klebang Besar, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1032, 'Ayer Keroh Care', 'REG009', 'ayerkerohcare@gmail.com', '0191011122', 'Ayer Keroh, Melaka', '$2y$10$O.1yLsT5OMRzFxkv4d8QJOdeDfJIxRgsoKtLxG4XrARYGFWtLMYR.', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1033, 'Melaka Youth Volunteers', 'REG010', 'melakayouth@gmail.com', '0112021223', 'Durian Tunggal, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1034, 'UTeM Volunteer Network', 'REG011', 'utemvolunteer@gmail.com', '0123031334', 'Durian Tunggal, Melaka', '$2y$10$dLzzFgHUks5PHOCzkTKFmeP2rRUDA5kzJbGNzM6v0VweSm0A4DSuC', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1035, 'Melaka Medical Aid', 'REG012', 'melakamedical@gmail.com', '0134041445', 'Cheng, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1036, 'Cheng Community Support', 'REG013', 'chengsupport@gmail.com', '0145051556', 'Cheng, Melaka', '$2y$10$RubF/RR88O3SxY5VHnJ8JeH78rqSC8xuCLqQ6zvVNZXT3or4dQHsW', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1037, 'Masjid Relief Melaka', 'REG014', 'masjidrelief@gmail.com', '0156061667', 'Tanjung Minyak, Melaka', '$2y$10$ziLIuc0gCOnvcTcNy1ZgruZMevkyzMyN3YLpLAqvWaExo.JloXhoK', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1038, 'Melaka Disaster Response Team', 'REG015', 'mdrtmelaka@gmail.com', '0167071778', 'Paya Rumput, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (2039, 'Pusat Pemulihan Dalam Komuniti (PDK) Seri Utama', 'REG016', 'PDK@gmail.com', '0112233446', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$12$fIyz2zolgbqe3JOEHSkND.tFMrEZ/WRtKIZ8xEQmgtTY2ZUQkXWOm', '2026-01-13 15:48:06', 'Pending');
GO


-- ========================================
-- Table: opportunity
-- ========================================

-- DELETE existing data
DELETE FROM [opportunity];
GO

-- INSERT new data
INSERT INTO [opportunity] ([opportunity_id], [ngo_id], [title], [description], [location], [event_date], [slots], [status], [created_at]) VALUES (3, 20, 'food helper', 'okay', 'kl , melaka', '2025-12-30 00:00:00', 15, 'Open', '2025-12-16 14:16:10');
INSERT INTO [opportunity] ([opportunity_id], [ngo_id], [title], [description], [location], [event_date], [slots], [status], [created_at]) VALUES (4, 22, 'beach cleanup', 'at klebang , time -', 'kl , melaka', '2025-12-25 00:00:00', 10, 'Open', '2025-12-16 21:39:17');
INSERT INTO [opportunity] ([opportunity_id], [ngo_id], [title], [description], [location], [event_date], [slots], [status], [created_at]) VALUES (5, 22, 'hihihihi', 'jjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj', 'melaka sungai jerai', '2025-12-31 00:00:00', 10, 'Open', '2025-12-22 01:31:11');
GO


-- ========================================
-- Table: opportunity_volunteer
-- ========================================

-- DELETE existing data
DELETE FROM [opportunity_volunteer];
GO

-- INSERT new data
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (1, 1, 1, '2025-12-15 23:01:57');
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (2, 2, 1, '2025-12-16 14:18:04');
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (3, 4, 1, '2025-12-17 15:38:23');
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (4, 4, 12, '2025-12-22 13:41:28');
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (5, 5, 23, '2026-01-02 00:26:56');
INSERT INTO [opportunity_volunteer] ([id], [opportunity_id], [volunteer_id], [assigned_at]) VALUES (6, 5, 12, '2026-01-02 00:35:29');
GO


-- ========================================
-- Table: sysdiagrams
-- ========================================

-- DELETE existing data
DELETE FROM [sysdiagrams];
GO

-- INSERT new data
GO


-- ========================================
-- Table: Volunteer
-- ========================================

-- DELETE existing data
DELETE FROM [Volunteer];
GO

-- INSERT new data
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (7, 'kk', 'kk@1', '19876', 'yy', NULL, 'yy', NULL, 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (24, 'Frero', 'aj@gmail.com', '0123445554', 'University Teknikal Malaysia Melaka', 1026, '$2y$10$NGb7UhfTgwxaPL/yAZ61gOIhVcMhGewFkS9vrum7etqDskSPNeYMu', 'Medical', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (25, 'vanisha', 'vanisha@gmail.com', '0112233448', 'no 10, bandar saujana putran , jenajrom , 42610', 1034, '$2y$10$8VQrTX5ZILUxNAMA7mcHzOmF3pDTa2P6XUO8W9mmIB.zROfWQZU9e', 'Helper', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1026, 'Daniel', 'ikmal@gmail.com', '01117845504', 'University Teknikal Malaysia Melaka', 1030, '$2y$10$UrEpnn.JzMampMNcfnTH3uC0.JpFZhkVe6V9TZpDgYlGhg7Yqc3ty', 'Logistics / Supplies, Food Services', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1027, 'Sufiana', 'sufiana@gmail.com', '0194456783', 'no 9, bandar saujana putran , jenajrom , 42610', 1024, '$2y$12$iaU/2pJs7wlkNEiBC/FjHOLEatUIf1yc8u0XdAWv/18HAxPKfYl36', 'Technical Support, Counseling / Support', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1028, 'Ahmad Firdaus', 'ahmad.firdaus@gmail.com', '0123456789', 'Ayer Keroh, Melaka', 1023, '$2y$12$Yw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1K', 'Medical / First Aid, Driving', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1029, 'Nur Aina', 'nuraina@gmail.com', '0134567890', 'Bukit Beruang, Melaka', 1024, '$2y$12$QwZ3B9K2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5H', 'Food Services, Communication', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1030, 'Daniel Hakim', 'danielhakim@gmail.com', '0145678901', 'MITC, Melaka', 1025, '$2y$12$FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8', 'Technical Support, Admin / Office Work', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1031, 'Siti Balqis', 'sitibalqis@gmail.com', '0156789012', 'Durian Tunggal, Melaka', 1026, '$2y$12$9yN5HQwZ3B2S5E1r8A7xM6FJ0PZL4TtCkR1V', 'Counseling / Support', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1032, 'Aiman Roslan', 'aimanroslan@gmail.com', '0167890123', 'Ayer Keroh, Melaka', 1027, '$2y$12$K2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1v', 'Search & Rescue, Physical Labour', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1033, 'Farah Nabila', 'farahnabila@gmail.com', '0178901234', 'Batu Berendam, Melaka', 1028, '$2y$12$A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3B2S5E1r8', 'Logistics, Packing Aid', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1034, 'Muhammad Izzat', 'izzat@gmail.com', '0189012345', 'Ayer Keroh, Melaka', 1029, '$2y$12$R3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7s', 'Security Support', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1035, 'Aisyah Amirah', 'aisyah@gmail.com', '0190123456', 'Bukit Katil, Melaka', 1030, '$2y$12$5E1r8A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3B2S', 'Child Care, Teaching', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1036, 'Syafiq Azman', 'syafiq@gmail.com', '0112233445', 'Ayer Keroh, Melaka', 1031, '$2y$12$QkX9A8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0', 'Technical Support, IT', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1037, 'Nabila Zahirah', 'nabila@gmail.com', '0113344556', 'Melaka Tengah', 1032, '$2y$12$TtCkR1V9yN5HQwZ3B2S5E1r8A7xM6FJ0PZL4', 'Public Relations', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1038, 'Hafiz Ridzuan', 'hafiz@gmail.com', '0114455667', 'Ayer Keroh, Melaka', 1033, '$2y$12$ZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tO', 'Driving, Logistics', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1039, 'Amirah Sofia', 'amirah@gmail.com', '0115566778', 'Bukit Baru, Melaka', 1034, '$2y$12$B2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5HQwZ3', 'Food Distribution', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1040, 'Arif Haziq', 'arif@gmail.com', '0116677889', 'Ayer Keroh, Melaka', 1035, '$2y$12$C1vK2B6S5E1KYw8r1Z5b0QkX9A8FZQJ2tOZQeJkP7sR3xLwM9u', 'Rescue Team', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1041, 'Nur Syuhada', 'syuhada@gmail.com', '0117788990', 'Melaka Tengah', 1036, '$2y$12$HQwZ3B2S5E1r8A7xM6FJ0PZL4TtCkR1V9yN5', 'Counseling, Support', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1042, 'Faris Akmal', 'faris@gmail.com', '0118899001', 'Ayer Keroh, Melaka', 2039, '$2y$12$8FZQJ2tOZQeJkP7sR3xLwM9uC1vK2B6S5E1KYw8r1Z5b0QkX9A', 'Community Outreach', 'Active');
GO


-- ========================================
-- Stored Procedures
-- ========================================

-- Procedure: sp_alterdiagram

	CREATE PROCEDURE dbo.sp_alterdiagram
	(
		@diagramname 	sysname,
		@owner_id	int	= null,
		@version 	int,
		@definition 	varbinary(max)
	)
	WITH EXECUTE AS 'dbo'
	AS
	BEGIN
		set nocount on
	
		declare @theId 			int
		declare @retval 		int
		declare @IsDbo 			int
		
		declare @UIDFound 		int
		declare @DiagId			int
		declare @ShouldChangeUID	int
	
		if(@diagramname is null)
		begin
			RAISERROR ('Invalid ARG', 16, 1)
			return -1
		end
	
		execute as caller;
		select @theId = DATABASE_PRINCIPAL_ID();	 
		select @IsDbo = IS_MEMBER(N'db_owner'); 
		if(@owner_id is null)
			select @owner_id = @theId;
		revert;
	
		select @ShouldChangeUID = 0
		select @DiagId = diagram_id, @UIDFound = principal_id from dbo.sysdiagrams where principal_id = @owner_id and name = @diagramname 
		
		if(@DiagId IS NULL or (@IsDbo = 0 and @theId <> @UIDFound))
		begin
			RAISERROR ('Diagram does not exist or you do not have permission.', 16, 1);
			return -3
		end
	
		if(@IsDbo <> 0)
		begin
			if(@UIDFound is null or USER_NAME(@UIDFound) is null) -- invalid principal_id
			begin
				select @ShouldChangeUID = 1 ;
			end
		end

		-- update dds data			
		update dbo.sysdiagrams set definition = @definition where diagram_id = @DiagId ;

		-- change owner
		if(@ShouldChangeUID = 1)
			update dbo.sysdiagrams set principal_id = @theId where diagram_id = @DiagId ;

		-- update dds version
		if(@version is not null)
			update dbo.sysdiagrams set version = @version where diagram_id = @DiagId ;

		return 0
	END
	
GO

-- Procedure: sp_creatediagram

	CREATE PROCEDURE dbo.sp_creatediagram
	(
		@diagramname 	sysname,
		@owner_id		int	= null, 	
		@version 		int,
		@definition 	varbinary(max)
	)
	WITH EXECUTE AS 'dbo'
	AS
	BEGIN
		set nocount on
	
		declare @theId int
		declare @retval int
		declare @IsDbo	int
		declare @userName sysname
		if(@version is null or @diagramname is null)
		begin
			RAISERROR (N'E_INVALIDARG', 16, 1);
			return -1
		end
	
		execute as caller;
		select @theId = DATABASE_PRINCIPAL_ID(); 
		select @IsDbo = IS_MEMBER(N'db_owner');
		revert; 
		
		if @owner_id is null
		begin
			select @owner_id = @theId;
		end
		else
		begin
			if @theId <> @owner_id
			begin
				if @IsDbo = 0
				begin
					RAISERROR (N'E_INVALIDARG', 16, 1);
					return -1
				end
				select @theId = @owner_id
			end
		end
		-- next 2 line only for test, will be removed after define name unique
		if EXISTS(select diagram_id from dbo.sysdiagrams where principal_id = @theId and name = @diagramname)
		begin
			RAISERROR ('The name is already used.', 16, 1);
			return -2
		end
	
		insert into dbo.sysdiagrams(name, principal_id , version, definition)
				VALUES(@diagramname, @theId, @version, @definition) ;
		
		select @retval = @@IDENTITY 
		return @retval
	END
	
GO

-- Procedure: sp_dropdiagram

	CREATE PROCEDURE dbo.sp_dropdiagram
	(
		@diagramname 	sysname,
		@owner_id	int	= null
	)
	WITH EXECUTE AS 'dbo'
	AS
	BEGIN
		set nocount on
		declare @theId 			int
		declare @IsDbo 			int
		
		declare @UIDFound 		int
		declare @DiagId			int
	
		if(@diagramname is null)
		begin
			RAISERROR ('Invalid value', 16, 1);
			return -1
		end
	
		EXECUTE AS CALLER;
		select @theId = DATABASE_PRINCIPAL_ID();
		select @IsDbo = IS_MEMBER(N'db_owner'); 
		if(@owner_id is null)
			select @owner_id = @theId;
		REVERT; 
		
		select @DiagId = diagram_id, @UIDFound = principal_id from dbo.sysdiagrams where principal_id = @owner_id and name = @diagramname 
		if(@DiagId IS NULL or (@IsDbo = 0 and @UIDFound <> @theId))
		begin
			RAISERROR ('Diagram does not exist or you do not have permission.', 16, 1)
			return -3
		end
	
		delete from dbo.sysdiagrams where diagram_id = @DiagId;
	
		return 0;
	END
	
GO

-- Procedure: sp_helpdiagramdefinition

	CREATE PROCEDURE dbo.sp_helpdiagramdefinition
	(
		@diagramname 	sysname,
		@owner_id	int	= null 		
	)
	WITH EXECUTE AS N'dbo'
	AS
	BEGIN
		set nocount on

		declare @theId 		int
		declare @IsDbo 		int
		declare @DiagId		int
		declare @UIDFound	int
	
		if(@diagramname is null)
		begin
			RAISERROR (N'E_INVALIDARG', 16, 1);
			return -1
		end
	
		execute as caller;
		select @theId = DATABASE_PRINCIPAL_ID();
		select @IsDbo = IS_MEMBER(N'db_owner');
		if(@owner_id is null)
			select @owner_id = @theId;
		revert; 
	
		select @DiagId = diagram_id, @UIDFound = principal_id from dbo.sysdiagrams where principal_id = @owner_id and name = @diagramname;
		if(@DiagId IS NULL or (@IsDbo = 0 and @UIDFound <> @theId ))
		begin
			RAISERROR ('Diagram does not exist or you do not have permission.', 16, 1);
			return -3
		end

		select version, definition FROM dbo.sysdiagrams where diagram_id = @DiagId ; 
		return 0
	END
	
GO

-- Procedure: sp_helpdiagrams

	CREATE PROCEDURE dbo.sp_helpdiagrams
	(
		@diagramname sysname = NULL,
		@owner_id int = NULL
	)
	WITH EXECUTE AS N'dbo'
	AS
	BEGIN
		DECLARE @user sysname
		DECLARE @dboLogin bit
		EXECUTE AS CALLER;
			SET @user = USER_NAME();
			SET @dboLogin = CONVERT(bit,IS_MEMBER('db_owner'));
		REVERT;
		SELECT
			[Database] = DB_NAME(),
			[Name] = name,
			[ID] = diagram_id,
			[Owner] = USER_NAME(principal_id),
			[OwnerID] = principal_id
		FROM
			sysdiagrams
		WHERE
			(@dboLogin = 1 OR USER_NAME(principal_id) = @user) AND
			(@diagramname IS NULL OR name = @diagramname) AND
			(@owner_id IS NULL OR principal_id = @owner_id)
		ORDER BY
			4, 5, 1
	END
	
GO

-- Procedure: sp_renamediagram

	CREATE PROCEDURE dbo.sp_renamediagram
	(
		@diagramname 		sysname,
		@owner_id		int	= null,
		@new_diagramname	sysname
	
	)
	WITH EXECUTE AS 'dbo'
	AS
	BEGIN
		set nocount on
		declare @theId 			int
		declare @IsDbo 			int
		
		declare @UIDFound 		int
		declare @DiagId			int
		declare @DiagIdTarg		int
		declare @u_name			sysname
		if((@diagramname is null) or (@new_diagramname is null))
		begin
			RAISERROR ('Invalid value', 16, 1);
			return -1
		end
	
		EXECUTE AS CALLER;
		select @theId = DATABASE_PRINCIPAL_ID();
		select @IsDbo = IS_MEMBER(N'db_owner'); 
		if(@owner_id is null)
			select @owner_id = @theId;
		REVERT;
	
		select @u_name = USER_NAME(@owner_id)
	
		select @DiagId = diagram_id, @UIDFound = principal_id from dbo.sysdiagrams where principal_id = @owner_id and name = @diagramname 
		if(@DiagId IS NULL or (@IsDbo = 0 and @UIDFound <> @theId))
		begin
			RAISERROR ('Diagram does not exist or you do not have permission.', 16, 1)
			return -3
		end
	
		-- if((@u_name is not null) and (@new_diagramname = @diagramname))	-- nothing will change
		--	return 0;
	
		if(@u_name is null)
			select @DiagIdTarg = diagram_id from dbo.sysdiagrams where principal_id = @theId and name = @new_diagramname
		else
			select @DiagIdTarg = diagram_id from dbo.sysdiagrams where principal_id = @owner_id and name = @new_diagramname
	
		if((@DiagIdTarg is not null) and  @DiagId <> @DiagIdTarg)
		begin
			RAISERROR ('The name is already used.', 16, 1);
			return -2
		end		
	
		if(@u_name is null)
			update dbo.sysdiagrams set [name] = @new_diagramname, principal_id = @theId where diagram_id = @DiagId
		else
			update dbo.sysdiagrams set [name] = @new_diagramname where diagram_id = @DiagId
		return 0
	END
	
GO

-- Procedure: sp_upgraddiagrams

	CREATE PROCEDURE dbo.sp_upgraddiagrams
	AS
	BEGIN
		IF OBJECT_ID(N'dbo.sysdiagrams') IS NOT NULL
			return 0;
	
		CREATE TABLE dbo.sysdiagrams
		(
			name sysname NOT NULL,
			principal_id int NOT NULL,	-- we may change it to varbinary(85)
			diagram_id int PRIMARY KEY IDENTITY,
			version int,
	
			definition varbinary(max)
			CONSTRAINT UK_principal_name UNIQUE
			(
				principal_id,
				name
			)
		);


		/* Add this if we need to have some form of extended properties for diagrams */
		/*
		IF OBJECT_ID(N'dbo.sysdiagram_properties') IS NULL
		BEGIN
			CREATE TABLE dbo.sysdiagram_properties
			(
				diagram_id int,
				name sysname,
				value varbinary(max) NOT NULL
			)
		END
		*/

		IF OBJECT_ID(N'dbo.dtproperties') IS NOT NULL
		begin
			insert into dbo.sysdiagrams
			(
				[name],
				[principal_id],
				[version],
				[definition]
			)
			select	 
				convert(sysname, dgnm.[uvalue]),
				DATABASE_PRINCIPAL_ID(N'dbo'),			-- will change to the sid of sa
				0,							-- zero for old format, dgdef.[version],
				dgdef.[lvalue]
			from dbo.[dtproperties] dgnm
				inner join dbo.[dtproperties] dggd on dggd.[property] = 'DtgSchemaGUID' and dggd.[objectid] = dgnm.[objectid]	
				inner join dbo.[dtproperties] dgdef on dgdef.[property] = 'DtgSchemaDATA' and dgdef.[objectid] = dgnm.[objectid]
				
			where dgnm.[property] = 'DtgSchemaNAME' and dggd.[uvalue] like N'_EA3E6268-D998-11CE-9454-00AA00A3F36E_' 
			return 2;
		end
		return 1;
	END
	
GO

