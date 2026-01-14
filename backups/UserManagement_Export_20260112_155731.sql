-- ========================================
-- UserManagement Database Export
-- Export Date: 2026-01-12 15:57:31
-- Database: UserManagement
-- ========================================

USE [UserManagement];
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
GO


-- ========================================
-- Table: News
-- ========================================

-- DELETE existing data
DELETE FROM [News];
GO

-- INSERT new data
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (5, 'AT 2008 HAS', 'MUUUMUMUMUMUMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM', 'uploads/news/69482a0ebd2e3_1766337038.png', '2025-12-22 01:10:38', 'kota kinabalu');
INSERT INTO [News] ([NewsID], [Title], [Description], [ImageURL], [CreatedAt], [CreatedBy]) VALUES (7, 'beach cleanup', 'today ...............................................................................................................................', 'uploads/news/695f0e47dbf1c_1767837255.png', '2026-01-08 09:54:15', 'melaka tengah');
GO


-- ========================================
-- Table: NGO
-- ========================================

-- DELETE existing data
DELETE FROM [NGO];
GO

-- INSERT new data
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (5, 'Green Future', 'REG005', 'wanitasejahtera@example.com', '011-2233445', 'Dewan Muslimat PAS Melaka, Jalan Kota, 75000 Melaka', '1234', '2025-11-17 22:23:58', 'active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (20, 'melakacentral', 'REG006', 'melaka@gmal.com', '0183632483', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$10$ekqERmldXxxf4QU5jqbO5eZVz9NFXhtj/57OYDpHYHA0WmyGP6Ze2', '2025-12-14 19:44:51', 'active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (22, 'melaka  tengah batu', 'REG007', 'kk@gmail.com', '0112233446', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$10$kThHauZ6veBScx2eTDuckO.j0SzqjDH3FVUh6yiVWInT0InnpVg1e', '2025-12-16 21:37:41', 'active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (23, 'KlebangTeam', 'REG008', 'klebangTeam@gmail.com', '0183632483', 'klebang , melaka , 4261o', '$2y$10$F.c4hvUZpvqek//BDS0znOpBcHwfr9mYzhY711x/clsslOdo09S0q', '2025-12-23 22:31:25', 'Approved');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1023, 'Kualalegit', 'REG009', 'Kualalegit@gmail.com', '0178900010', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$10$/Fkh4UJ7qbCtSW4DKeF.cOrQDeyJh0o6YNAFQQ8CuTqjrU0zZScqC', '2026-01-02 00:05:40', 'Pending');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1024, 'Pertubuhan Wanita Sejahtera Melaka', 'REG001', 'pwsmelaka@gmail.com', '0112233445', 'Bandar Hilir, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1025, 'Melaka Care Foundation', 'REG002', 'melakacare@gmail.com', '0123344556', 'Ayer Keroh, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1026, 'NGO Prihatin Melaka', 'REG003', 'ngoprihatinmelaka@gmail.com', '0134455667', 'Bukit Beruang, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1027, 'Central Melaka Relief Team', 'REG004', 'centralmelaka@gmail.com', '0145566778', 'Melaka Tengah, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1028, 'Jasin Community Help', 'REG005', 'jasinhelp@gmail.com', '0156677889', 'Jasin, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1029, 'Alor Gajah Volunteer Group', 'REG006', 'agvolunteer@gmail.com', '0167788990', 'Alor Gajah, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1030, 'Melaka Flood Aid', 'REG007', 'melakafloodaid@gmail.com', '0178899001', 'Klebang, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1031, 'Klebang Relief Team', 'REG008', 'klebangrelief@gmail.com', '0189900112', 'Klebang Besar, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1032, 'Ayer Keroh Care', 'REG009', 'ayerkerohcare@gmail.com', '0191011122', 'Ayer Keroh, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1033, 'Melaka Youth Volunteers', 'REG010', 'melakayouth@gmail.com', '0112021223', 'Durian Tunggal, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1034, 'UTeM Volunteer Network', 'REG011', 'utemvolunteer@gmail.com', '0123031334', 'Durian Tunggal, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1035, 'Melaka Medical Aid', 'REG012', 'melakamedical@gmail.com', '0134041445', 'Cheng, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1036, 'Cheng Community Support', 'REG013', 'chengsupport@gmail.com', '0145051556', 'Cheng, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1037, 'Masjid Relief Melaka', 'REG014', 'masjidrelief@gmail.com', '0156061667', 'Tanjung Minyak, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1038, 'Melaka Disaster Response Team', 'REG015', 'mdrtmelaka@gmail.com', '0167071778', 'Paya Rumput, Melaka', '1234', '2026-01-02 17:50:02', 'Active');
INSERT INTO [NGO] ([NGOID], [NGOName], [RegistrationNo], [Email], [Phone], [Address], [PasswordHash], [CreatedAt], [Status]) VALUES (1039, 'melaka', 'REG016', 'melaka@gmail.com', '0183632483', 'no 10, bandar saujana putran , jenajrom , 42610', '$2y$10$9U1Y2MNF5aw3TLPFsXKy2.iEXViWbzUlMCpgnko/DR1wWjnHDPy6C', '2026-01-08 09:43:13', 'Approved');
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
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1, 'Aiman Razak', 'aiman@example.com', '0134455667', 'Kuala Terengganu', 20, '$2y$10$ltxg/pHqpMHtVauKKxNEIur45Itf5FTa71Z7xk.RjKqVDvRG2cp/O', 'Food Supply', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (7, 'kk', 'kk@1', '19876', 'yy', NULL, 'yy', NULL, 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (9, 'yana', 'yana123@student', '888764', 'bb', 5, '123', 'Rescue', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (11, 'syakinah', 'syakinah@gmai.com', '0194456789', 'no 10, bandar saujana putran , jenajrom , 42610', 22, '$2y$10$ae0fTfP8a5881ZNCpS/ukOl2hOSl6/5S4VrcFnaf17zQrAl2IIFkC', 'Helper', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (12, 'yaya', 'yaya@gmail.com', '0194456783', 'melaka tengah', 22, '$2y$10$FDZpNx2w/z3mVEqASMUzKeaK9wJ4e0gdIBhnptAd7e5YRZx00f94q', 'Communication, General Volunteer', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (13, 'Ahmad Firdaus', 'firdaus@gmail.com', '0121111111', 'Melaka Tengah', 5, 'hash1', 'Logistics', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (14, 'Aina Sofea', 'aina@gmail.com', '0125555555', 'Masjid Tanah', 5, 'hash5', 'Counselling', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (15, 'Nabila Farah', 'nabila@gmail.com', '0130000000', 'Bachang', 5, 'hash10', 'Counselling', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (16, 'Siti Aisyah', 'aisyah@gmail.com', '0122222222', 'Jasin', 20, 'hash2', 'Medical', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (17, 'Daniel Hakim', 'daniel@gmail.com', '0126666666', 'Durian Tunggal', 20, 'hash6', 'Logistics', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (18, 'Haziq Aiman', 'haziq@gmail.com', '0129999999', 'Cheng', 20, 'hash9', 'Food Distribution', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (19, 'Nur Iman', 'iman@gmail.com', '0123333333', 'Alor Gajah', 22, '$2y$10$rbGM2J2sHkMjf5hMqLkCZOT4QyIkzHNq5oojh5mxM9d9FdW/eFJIS', 'Food Distribution', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (20, 'Nur Syafiqah', 'syafiqah@gmail.com', '0127777777', 'Ayer Keroh', 22, 'hash7', 'Medical', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (21, 'Muhammad Akmal', 'akmal@gmail.com', '0124444444', 'Bukit Katil', 23, 'hash4', 'Rescue', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (22, 'Amirul Hadi', 'amirul@gmail.com', '0128888888', 'Pantai Kundor', 23, 'hash8', 'Rescue', 'Active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (23, 'Ikmal Daniel', 'imadaniel2004@gmail.com', '01117845504', 'Durian Tunggal, Melaka', 23, '$2y$10$wP8C1vcU9kzj46JJpUjOhOTDhc.SMNSUE3T7Ys63Nxmwn9MLH86XC', 'General Volunteer', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (24, 'Frero', 'aj@gmail.com', '0123445554', 'University Teknikal Malaysia Melaka', 1026, '$2y$10$NGb7UhfTgwxaPL/yAZ61gOIhVcMhGewFkS9vrum7etqDskSPNeYMu', 'Medical', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (25, 'vanisha', 'vanisha@gmail.com', '0112233448', 'no 10, bandar saujana putran , jenajrom , 42610', 1034, '$2y$10$8VQrTX5ZILUxNAMA7mcHzOmF3pDTa2P6XUO8W9mmIB.zROfWQZU9e', 'Helper', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1025, 'darmen', 'darmen@gmail.com', '0112233446', 'no 10, bandar saujana putran , jenajrom , 42610', 22, '$2y$10$db89NvgtvFf9m3vP1auegOayTM5dWhuVTHVm9O6/XinItywa34gze', 'Medical / First Aid, Logistics / Supplies', 'active');
INSERT INTO [Volunteer] ([VolunteerID], [FullName], [Email], [Phone], [Address], [AssignedNGO], [PasswordHash], [SkillCategory], [Status]) VALUES (1026, 'Daniel', 'ikmal@gmail.com', '01117845504', 'University Teknikal Malaysia Melaka', 1030, '$2y$10$UrEpnn.JzMampMNcfnTH3uC0.JpFZhkVe6V9TZpDgYlGhg7Yqc3ty', 'Logistics / Supplies, Food Services', 'active');
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

