-- ============================================
-- DATABASE BACKUP
-- Created: 2026-08-03 04:25:00
-- Tables: 45
-- ============================================

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `admin_notifications`;
CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `type` enum('order','delivery','payment','product','user','system','alert') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` enum('database','files','full') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `status` enum('pending','running','completed','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`backup_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `backup_logs` VALUES
('35', 'backup_2026-08-03_04-24-39.zip', 'full', 'backups/backup_2026-08-03_04-24-39.zip', '14.69 KB', 'completed', NULL, '1', '2026-08-03 05:24:42', '2026-08-03 05:24:42');

DROP TABLE IF EXISTS `business_notifications`;
CREATE TABLE `business_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','inventory','review','payment','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_biz_notif_business` (`business_id`),
  KEY `idx_biz_notif_type` (`type`),
  KEY `idx_biz_notif_read` (`is_read`),
  CONSTRAINT `fk_biz_notif_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `business_notifications` VALUES
('133', '7', 'Order Status Updated', 'Order 89 status changed from Pending to Accepted.', 'order', '1', '2026-07-12 02:37:52'),
('134', '7', 'Order Status Updated', 'Order 89 status changed from Accepted to Confirmed. Note: confirmed', 'order', '1', '2026-07-12 02:45:19'),
('135', '7', 'Order Status Updated', 'Order 89 status changed from Confirmed to Preparing.', 'order', '1', '2026-07-12 03:15:28'),
('136', '7', 'Order Status Updated', 'Order 89 status changed from Preparing to Ready.', 'order', '1', '2026-07-12 03:15:39'),
('137', '7', 'Stock Updated', 'Product \'Dressings Table\' stock changed from 8 to 11. Reason: stock adjustment', 'inventory', '1', '2026-07-12 03:17:01'),
('138', '7', 'Stock Updated', 'Product \'Dressings Table\' stock changed from 9 to 11. Reason: stock adjustment', 'inventory', '1', '2026-07-12 03:17:11'),
('139', '7', 'Order Status Updated', 'Order 88 status changed from Pending to Accepted.', 'order', '1', '2026-07-12 03:18:25'),
('140', '7', 'Order Status Updated', 'Order 88 status changed from Accepted to Confirmed.', 'order', '0', '2026-07-13 23:19:24'),
('141', '7', 'Stock Updated', 'Product \'Dressings Table\' stock changed from 10 to 8. Reason: Manual adjustment', 'inventory', '0', '2026-07-19 18:22:07'),
('142', '7', 'Stock Updated', 'Product \'Dressings Table\' stock changed from 8 to 0. Reason: Manual adjustment', 'inventory', '0', '2026-07-19 18:26:19'),
('143', '7', 'New Order', 'Order 90 from Twaha Mohamed - TSh 184,500', 'order', '0', '2026-08-03 03:03:52'),
('144', '7', 'Order Status Updated', 'Order 90 status changed from Pending to Accepted.', 'order', '0', '2026-08-03 03:14:02'),
('145', '7', 'Order Status Updated', 'Order 90 status changed from Accepted to Confirmed.', 'order', '0', '2026-08-03 03:14:16'),
('146', '7', 'Order Status Updated', 'Order 90 status changed from Confirmed to Preparing.', 'order', '0', '2026-08-03 03:17:03'),
('147', '7', 'Order Status Updated', 'Order 90 status changed from Preparing to Ready.', 'order', '0', '2026-08-03 03:17:14'),
('148', '7', 'New Order', 'Order 91 from Twaha Mohamed - TSh 184,500', 'order', '0', '2026-08-03 04:51:06'),
('149', '7', 'Order Status Updated', 'Order 91 status changed from Pending to Accepted.', 'order', '0', '2026-08-03 05:01:12');

DROP TABLE IF EXISTS `business_settings`;
CREATE TABLE `business_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `notification_settings` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `uk_business_settings_business` (`business_id`),
  CONSTRAINT `fk_business_settings_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `business_settings` VALUES
('1', '7', '{\"email\":1,\"sms\":0,\"orders\":1,\"low_stock\":1}', '2026-07-12 02:41:36', '2026-07-12 02:41:36');

DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `businesses` (
  `business_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `business_hours` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `total_reviews` int(11) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `payment_methods` text DEFAULT NULL,
  `nida_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`business_id`),
  UNIQUE KEY `uk_businesses_user` (`user_id`),
  UNIQUE KEY `uk_businesses_registration` (`registration_number`),
  UNIQUE KEY `uk_businesses_nida` (`nida_number`),
  KEY `idx_businesses_name` (`business_name`),
  KEY `idx_businesses_verified` (`is_verified`),
  KEY `idx_businesses_active` (`is_active`),
  KEY `idx_businesses_city` (`city`),
  CONSTRAINT `fk_businesses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `businesses` VALUES
('7', '19', 'Nyoni Collection', 'BLA-2024-00123', 'Welcome', 'Kariakoo', 'Kariakoo-Msimbazi', '-6.79240000', '39.20830000', 'TIN-12345678', 'assets/uploads/businesses/1780414954_6a1ef9eab354b.jpeg', '{\"monday\":\"9:00 AM - 6:00 PM\",\"tuesday\":\"9:00 AM - 6:00 PM\",\"wednesday\":\"9:00 AM - 6:00 PM\",\"thursday\":\"9:00 AM - 6:00 PM\",\"friday\":\"9:00 AM - 6:00 PM\",\"saturday\":\"10:00 AM - 4:00 PM\",\"sunday\":\"Closed\"}', '0.0', '0', '1', '1', '2026-06-01 00:32:03', 'Dar es Salaam', '0767708012', 'cash,mobile_money', '19871104171020000120'),
('10', '28', 'Mquraysh Tech', 'BLA-2024-00130', 'Welcome', 'Kariakoo', 'Kariakoo-Msimbazi', '-6.82250000', '39.26970000', 'TIN-12345630', 'assets/uploads/businesses/1781815352_6a34583817335.jpg', 'Monday-Saturday: 8am-8pm', '0.0', '0', '1', '1', '2026-06-03 03:22:59', 'Dar es Salaam', '0799051862', 'cash,mobile_money', '20041104171020000120'),
('11', '31', 'Fashion shop', 'BLA-2026-00123', 'WELCOME', 'kariakoo', 'Kariakoo-Msimbazi', '-6.82250000', '39.26970000', 'TIN-12345', 'assets/uploads/businesses/1781822754_6a347522b2af7.jpg', 'Monday-Saturday: 8am-8pm', '0.0', '0', '1', '1', '2026-06-10 16:26:43', 'Dar es Salaam', '0764080102', 'cash', '20071104171020000120');

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`),
  UNIQUE KEY `uk_cart_customer_product` (`customer_id`,`product_id`),
  KEY `idx_cart_customer` (`customer_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`category_id`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_active` (`is_active`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` VALUES
('1', 'Electronics', 'Smartphones, laptops, TVs, audio devices, cameras, and accessories.', NULL, '1', '2026-05-26 18:29:35', '1'),
('2', 'Fashion & Clothing', 'Men’s, women’s, and children’s clothing, shoes, bags, jewelry, and watches.', NULL, '2', '2026-05-26 18:29:35', '1'),
('3', 'Home & Living', 'Furniture, kitchenware, home decor, bedding, appliances, and DIY tools.', NULL, '3', '2026-05-26 18:29:35', '1'),
('4', 'Food & Beverages', 'Fresh groceries, packaged foods, beverages, snacks, and bakery items.', NULL, '4', '2026-05-26 18:29:35', '1'),
('5', 'Health & Beauty', 'Skincare, makeup, hair care, fragrances, personal care, and supplements.', NULL, '5', '2026-05-26 18:29:35', '1'),
('6', 'Automotive', 'Car and motorcycle parts, accessories, tyres, and garage tools.', NULL, '6', '2026-05-26 18:29:35', '1'),
('7', 'Baby & Kids', 'Baby clothing, gear, diapers, toys, and baby food.', NULL, '7', '2026-05-26 18:29:35', '1'),
('8', 'Sports & Outdoors', 'Exercise equipment, outdoor gear, team sports, camping and hiking gear.', NULL, '8', '2026-05-26 18:29:35', '1'),
('10', 'Services', 'Repair services (phones, electronics), tailoring, cleaning, delivery, IT support.', NULL, '10', '2026-05-26 18:29:35', '1'),
('67', 'Mobile Phones', 'Smartphones, feature phones, and mobile accessories', '1', '1', '2026-05-26 19:29:48', '1'),
('68', 'Laptops & Computers', 'Notebooks, desktops, tablets, and computer peripherals', '1', '2', '2026-05-26 19:29:48', '1'),
('69', 'Audio & Headphones', 'Headphones, earphones, speakers, and home audio systems', '1', '3', '2026-05-26 19:29:48', '1'),
('70', 'TV & Home Theatre', 'Televisions, projectors, soundbars, and home cinema systems', '1', '4', '2026-05-26 19:29:48', '1'),
('71', 'Cameras & Photography', 'DSLR, mirrorless cameras, lenses, tripods, and camera bags', '1', '5', '2026-05-26 19:29:48', '1'),
('72', 'Accessories & Cables', 'Chargers, power banks, cables, adapters, and screen protectors', '1', '6', '2026-05-26 19:29:48', '1'),
('73', 'Men\'s Clothing', 'Shirts, trousers, jeans, jackets, suits, and traditional wear', '2', '1', '2026-05-26 19:29:48', '1'),
('74', 'Women\'s Clothing', 'Dresses, blouses, skirts, trousers, hijabs, and traditional wear', '2', '2', '2026-05-26 19:29:48', '1'),
('75', 'Children\'s Clothing', 'Clothes for boys and girls of all ages (0-16 years)', '2', '3', '2026-05-27 02:29:48', '1'),
('76', 'Shoes', 'Sneakers, formal shoes, sandals, slippers, and boots for all genders', '2', '4', '2026-05-27 02:29:48', '1'),
('77', 'Bags & Luggage', 'Handbags, backpacks, travel bags, school bags, and wallets', '2', '5', '2026-05-27 02:29:48', '1'),
('78', 'Jewelry & Watches', 'Necklaces, rings, earrings, bracelets, and wristwatches', '2', '6', '2026-05-27 02:29:48', '1'),
('79', 'Furniture', 'Sofas, beds, tables, chairs, wardrobes, and office furniture', '3', '1', '2026-05-27 02:29:48', '1'),
('80', 'Kitchen & Dining', 'Cookware, utensils, dinner sets, glassware, and kitchen appliances', '3', '2', '2026-05-27 02:29:48', '1'),
('81', 'Bedding & Bath', 'Bed sheets, blankets, pillows, towels, and bathroom accessories', '3', '3', '2026-05-27 02:29:48', '1'),
('82', 'Home Decor', 'Wall art, curtains, vases, candles, mirrors, and decorative items', '3', '4', '2026-05-27 02:29:48', '1'),
('83', 'Appliances', 'Refrigerators, microwaves, washing machines, fans, and air conditioners', '3', '5', '2026-05-27 02:29:48', '1'),
('84', 'Tools & DIY', 'Hand tools, power tools, paint, hardware, and home improvement supplies', '3', '6', '2026-05-27 02:29:48', '1'),
('85', 'Fresh Fruits & Vegetables', 'Locally grown and imported fresh produce', '4', '1', '2026-05-27 02:29:48', '1'),
('86', 'Meat & Seafood', 'Beef, chicken, goat, fish, and other seafood products', '4', '2', '2026-05-27 02:29:48', '1'),
('87', 'Dairy & Eggs', 'Milk, cheese, yoghurt, butter, and fresh eggs', '4', '3', '2026-05-27 02:29:48', '1'),
('88', 'Bakery', 'Bread, cakes, pastries, cookies, and baked snacks', '4', '4', '2026-05-27 02:29:48', '1'),
('89', 'Beverages', 'Soft drinks, juices, bottled water, tea, coffee, and energy drinks', '4', '5', '2026-05-27 02:29:48', '1'),
('90', 'Snacks & Sweets', 'Chips, biscuits, chocolates, candies, and traditional snacks', '4', '6', '2026-05-27 02:29:48', '1'),
('91', 'Skincare', 'Moisturizers, cleansers, serums, sunscreen, and face masks', '5', '1', '2026-05-27 02:29:48', '1'),
('92', 'Makeup', 'Foundation, lipstick, eyeshadow, mascara, and makeup tools', '5', '2', '2026-05-27 02:29:48', '1'),
('93', 'Hair Care', 'Shampoo, conditioner, oils, styling products, and hair tools', '5', '3', '2026-05-27 02:29:48', '1'),
('94', 'Fragrances', 'Perfumes, deodorants, and body sprays for men and women', '5', '4', '2026-05-27 02:29:48', '1'),
('95', 'Personal Care', 'Soap, toothpaste, razors, sanitary products, and hand sanitizers', '5', '5', '2026-05-27 02:29:48', '1'),
('96', 'Vitamins & Supplements', 'Multivitamins, protein powders, herbal supplements, and health boosters', '5', '6', '2026-05-27 02:29:48', '1'),
('97', 'Car Parts', 'Engine parts, brakes, filters, batteries, and lights', '6', '1', '2026-05-27 02:29:48', '1'),
('98', 'Motorcycle Parts', 'Spare parts, chains, brakes, tyres, and engine components for bikes', '6', '2', '2026-05-27 02:29:48', '1'),
('99', 'Car Accessories', 'Seat covers, steering wheels, car mats, air fresheners, and phone holders', '6', '3', '2026-05-27 02:29:48', '1'),
('100', 'Tyres & Wheels', 'Car and motorcycle tyres, rims, and wheel alignment services', '6', '4', '2026-05-27 02:29:48', '1'),
('101', 'Tools & Garage', 'Mechanics tools, jacks, diagnostic equipment, and garage storage', '6', '5', '2026-05-27 02:29:48', '1'),
('102', 'Baby Clothing', 'Onesies, rompers, pyjamas, and outfits for newborns and infants', '7', '1', '2026-05-27 02:29:48', '1'),
('103', 'Baby Gear', 'Strollers, car seats, baby carriers, walkers, and high chairs', '7', '2', '2026-05-27 02:29:48', '1'),
('104', 'Diapers & Wipes', 'Disposable and cloth diapers, baby wipes, and changing mats', '7', '3', '2026-05-27 02:29:48', '1'),
('105', 'Baby Food', 'Formula, purees, cereals, and snacks for babies and toddlers', '7', '4', '2026-05-27 02:29:48', '1'),
('106', 'Toys', 'Educational toys, dolls, action figures, puzzles, and outdoor play equipment', '7', '5', '2026-05-27 02:29:48', '1'),
('124', 'curtains', NULL, '8', '0', '2026-08-03 05:20:53', '1');

DROP TABLE IF EXISTS `customer_notifications`;
CREATE TABLE `customer_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','delivery','promo','system') DEFAULT 'system',
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_cust_notif_customer` (`customer_id`),
  KEY `idx_cust_notif_type` (`type`),
  KEY `idx_cust_notif_read` (`is_read`),
  CONSTRAINT `fk_cust_notif_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `customer_notifications` VALUES
('64', '5', ' Order 89 -  Delivered', 'Your order from \'Nyoni Collection\' (TSh 184,500) is now  Delivered.', 'order', '89', '0', '2026-07-13 23:28:18'),
('65', '5', ' Order 88 -  Confirmed', 'Your order from \'Nyoni Collection\' (TSh 294,500) is now  Confirmed.', 'order', '88', '0', '2026-07-13 23:28:18'),
('66', '5', ' Delivery 14 -  Delivered', 'Delivery for order 89 from \'Nyoni Collection\' is now  Delivered. Agent: Mohamed Ajemy', 'delivery', '14', '0', '2026-07-13 23:28:18'),
('67', '5', ' Order 89 -  Delivered', 'Your order from \'Nyoni Collection\' (TSh 184,500) is now  Delivered.', 'order', '89', '0', '2026-07-15 09:52:42'),
('68', '5', ' Order 88 -  Confirmed', 'Your order from \'Nyoni Collection\' (TSh 294,500) is now  Confirmed.', 'order', '88', '0', '2026-07-15 09:52:42'),
('69', '5', ' Delivery 14 -  Delivered', 'Delivery for order 89 from \'Nyoni Collection\' is now  Delivered. Agent: Mohamed Ajemy', 'delivery', '14', '0', '2026-07-15 09:52:42'),
('70', '5', ' Delivery 14 -  Delivered', 'Delivery for order 89 from \'Nyoni Collection\' is now  Delivered. Agent: Mohamed Ajemy', 'delivery', '14', '0', '2026-08-02 16:36:35'),
('71', '5', ' Order 90 -  Pending', 'Your order from \'Nyoni Collection\' (TSh 184,500) is now  Pending.', 'order', '90', '0', '2026-08-03 03:13:18'),
('72', '5', ' Delivery 14 -  Delivered', 'Delivery for order 89 from \'Nyoni Collection\' is now  Delivered. Agent: Mohamed Ajemy', 'delivery', '14', '0', '2026-08-03 03:13:18'),
('73', '5', ' Order 91 -  Pending', 'Your order from \'Nyoni Collection\' (TSh 184,500) is now  Pending.', 'order', '91', '0', '2026-08-03 04:54:59'),
('74', '5', ' Order 90 -  In Transit', 'Your order from \'Nyoni Collection\' (TSh 184,500) is now  In Transit.', 'order', '90', '0', '2026-08-03 04:54:59'),
('75', '5', ' Delivery 16 - ', 'Delivery for order 90 from \'Nyoni Collection\' is now . Agent: Mohamed Ajemy', 'delivery', '16', '0', '2026-08-03 04:54:59'),
('76', '5', ' Delivery 14 -  Delivered', 'Delivery for order 89 from \'Nyoni Collection\' is now  Delivered. Agent: Mohamed Ajemy', 'delivery', '14', '0', '2026-08-03 04:54:59');

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `saved_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_preference` varchar(50) DEFAULT 'cash',
  `profile_image` varchar(255) DEFAULT NULL,
  `payment_pref` varchar(50) DEFAULT 'cash_on_delivery',
  `delivery_latitude` decimal(10,8) DEFAULT NULL,
  `delivery_longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uk_customers_user` (`user_id`),
  KEY `idx_customers_city` (`city`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `customers` VALUES
('5', '20', 'Twaha', 'Mohamed', 'Mabibo', '', 'uploads/customers/customer_20_1780466370.webp', '2026-06-01 00:50:58', 'cash', 'assets/uploads/customers/customer_20_1785722196.png', 'cash_on_delivery', '-6.79240000', '39.20830000');

DROP TABLE IF EXISTS `deliveries`;
CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `pickup_address` varchar(255) NOT NULL,
  `delivery_address` varchar(255) NOT NULL,
  `status` enum('pending','assigned','picked_up','in_transit','delivered','failed') DEFAULT 'pending',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `estimated_distance` decimal(10,2) DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rating` tinyint(1) DEFAULT NULL COMMENT '1-5 stars',
  `rating_comment` text DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_status` enum('pending','verified','expired') DEFAULT 'pending',
  `otp_confirmed` tinyint(1) DEFAULT 0,
  `otp_confirmed_at` datetime DEFAULT NULL,
  `otp_generated_at` datetime DEFAULT NULL,
  `otp_attempts` int(11) DEFAULT 0,
  PRIMARY KEY (`delivery_id`),
  UNIQUE KEY `uk_deliveries_order` (`order_id`),
  KEY `idx_deliveries_agent` (`agent_id`),
  KEY `idx_deliveries_status` (`status`),
  KEY `idx_deliveries_status_agent` (`status`,`agent_id`),
  KEY `idx_deliveries_created` (`created_at`),
  KEY `idx_otp_code` (`otp_code`),
  CONSTRAINT `fk_deliveries_agent` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`agent_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_deliveries_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `deliveries` VALUES
('14', '89', '4', '', '', 'delivered', '2026-07-12 02:52:36', NULL, '2026-07-14 00:07:41', NULL, NULL, '9500.00', '2026-07-12 02:52:36', '2026-08-02 16:35:40', '3', 'goods', '2026-08-02 16:35:40', NULL, 'pending', '0', NULL, NULL, '0'),
('16', '90', '4', '', '', '', '2026-08-03 03:15:18', NULL, NULL, NULL, NULL, '9500.00', '2026-08-03 03:15:18', '2026-08-03 03:20:09', NULL, NULL, NULL, NULL, 'pending', '0', NULL, NULL, '0');

DROP TABLE IF EXISTS `delivery_agents`;
CREATE TABLE `delivery_agents` (
  `agent_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_registration` varchar(50) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `vehicle_model` varchar(100) DEFAULT NULL,
  `vehicle_color` varchar(50) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(11) DEFAULT 0,
  PRIMARY KEY (`agent_id`),
  UNIQUE KEY `uk_agents_user` (`user_id`),
  KEY `idx_agents_available` (`is_available`),
  KEY `idx_agents_status` (`status`),
  CONSTRAINT `fk_agents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_agents` VALUES
('4', '25', 'Mohamed', 'Ajemy', 'Motorcycle', 'T123ABC', '2004110417102000120', 'DL123456', '1', '-6.79240000', '39.20830000', '2026-06-02 22:11:43', '0617666478', 'Kariakoo-Msimbazi', 'active', 'Boxer', 'Red', '2035-02-22', 'assets/uploads/profiles/delivery_25_1782743335.jpg', '3.00', '1');

DROP TABLE IF EXISTS `delivery_history`;
CREATE TABLE `delivery_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  CONSTRAINT `delivery_history_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `delivery_notifications`;
CREATE TABLE `delivery_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `notification_type` enum('sms','email','in_app','push') DEFAULT 'in_app',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `agent_id` (`agent_id`),
  KEY `idx_delivery_notification` (`delivery_id`),
  KEY `idx_customer_notification` (`customer_id`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `delivery_notifications_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_notifications_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_notifications_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`agent_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `delivery_otp_logs`;
CREATE TABLE `delivery_otp_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `delivery_agent_id` int(11) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','verified','expired') NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`log_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_delivery_agent_id` (`delivery_agent_id`),
  KEY `idx_otp_code` (`otp_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `delivery_otp_verifications`;
CREATE TABLE `delivery_otp_verifications` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`verification_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_otp_verify` (`otp_code`),
  KEY `idx_delivery_otp` (`delivery_id`),
  CONSTRAINT `delivery_otp_verifications_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_otp_verifications_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `delivery_rates`;
CREATE TABLE `delivery_rates` (
  `rate_id` int(11) NOT NULL AUTO_INCREMENT,
  `min_distance` decimal(10,2) NOT NULL,
  `max_distance` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rate_id`),
  KEY `idx_rates_distance` (`min_distance`,`max_distance`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_rates` VALUES
('1', '0.00', '1.00', '2500.00', 'Within 2km', '2026-06-07 08:30:16', '1'),
('2', '1.10', '2.00', '3500.00', '2-3km', '2026-06-07 08:30:16', '1'),
('3', '2.10', '3.00', '4500.00', '5-8km', '2026-06-07 08:30:16', '1'),
('4', '3.10', '4.00', '5500.00', '8-11km', '2026-06-07 08:30:16', '1'),
('6', '4.10', '5.00', '6500.00', '', '2026-06-29 13:13:12', '1'),
('7', '5.10', '6.00', '7500.00', '', '2026-07-11 00:47:19', '1'),
('8', '6.10', '7.00', '8500.00', '', '2026-07-11 00:49:17', '1'),
('9', '7.10', '8.00', '9500.00', '', '2026-07-11 00:50:17', '1'),
('10', '8.10', '9.00', '10500.00', '', '2026-07-11 00:51:18', '1'),
('11', '9.10', '10.00', '11500.00', '', '2026-07-11 00:51:49', '1');

DROP TABLE IF EXISTS `delivery_ratings`;
CREATE TABLE `delivery_ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `delivery_ratings_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_ratings` VALUES
('5', '14', '5', '4', '89', '3', 'goods', '2026-08-02 16:35:40');

DROP TABLE IF EXISTS `delivery_tracking`;
CREATE TABLE `delivery_tracking` (
  `tracking_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`tracking_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  CONSTRAINT `delivery_tracking_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_tracking` VALUES
('117', '16', 'assigned', NULL, '2026-08-03 03:18:26', '-6.82189972', '39.27970584'),
('118', '16', 'assigned', NULL, '2026-08-03 03:18:30', '-6.82189972', '39.27970584'),
('119', '16', 'assigned', NULL, '2026-08-03 03:18:37', '-6.82189972', '39.27970584'),
('120', '16', 'in_transit', NULL, '2026-08-03 03:19:53', '-6.82189972', '39.27970584');

DROP TABLE IF EXISTS `delivery_updates`;
CREATE TABLE `delivery_updates` (
  `update_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `update_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`update_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_update_time` (`update_time`),
  CONSTRAINT `delivery_updates_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_updates` VALUES
('3', '14', 'picked_up', 'Status updated by agent to picked up', '2026-07-12 03:25:28'),
('4', '14', 'in_transit', 'Status updated by agent to in transit', '2026-07-12 03:29:29'),
('5', '14', 'delivered', 'Status updated by agent to delivered', '2026-07-12 03:29:43'),
('7', '16', 'picked_up', 'Status updated by agent to picked up', '2026-08-03 03:18:56'),
('8', '16', 'in_transit', 'Status updated by agent to in transit', '2026-08-03 03:19:25');

DROP TABLE IF EXISTS `earnings`;
CREATE TABLE `earnings` (
  `earning_id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`earning_id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `earnings_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`agent_id`) ON DELETE CASCADE,
  CONSTRAINT `earnings_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `earnings_ibfk_3` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_recipient` (`recipient`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_key` varchar(100) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `template_key` (`template_key`),
  KEY `idx_template_key` (`template_key`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `email_templates` VALUES
('8', 'order_confirmation', 'Order Confirmation', 'Order Confirmation #{order_id}', 'Dear {customer_name},\n\nThank you for your order #{order_id}.\n\nOrder Details:\n{order_details}\n\nTotal: {total_amount}\n\nWe will notify you when your order is shipped.\n\nThank you for shopping with us!', 'Sent when a customer places an order', 'customer_name, order_id, order_details, total_amount', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('9', 'order_shipped', 'Order Shipped', 'Your order #{order_id} has been shipped', 'Dear {customer_name},\n\nYour order #{order_id} has been shipped!\n\nDelivery Agent: {agent_name}\nTracking: {tracking_link}\n\nEstimated Delivery: {estimated_delivery}', 'Sent when an order is shipped', 'customer_name, order_id, agent_name, tracking_link, estimated_delivery', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('10', 'order_delivered', 'Order Delivered', 'Your order #{order_id} has been delivered', 'Dear {customer_name},\n\nYour order #{order_id} has been delivered successfully.\n\nWe hope you enjoy your purchase!\n\nPlease leave a review: {review_link}', 'Sent when an order is delivered', 'customer_name, order_id, review_link', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('11', 'password_reset', 'Password Reset', 'Reset your password', 'Dear {user_name},\n\nYou requested to reset your password.\n\nClick the link below to reset your password:\n{reset_link}\n\nThis link will expire in 24 hours.\n\nIf you did not request this, please ignore this email.', 'Sent when a user requests password reset', 'user_name, reset_link', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('12', 'welcome_email', 'Welcome Email', 'Welcome to UNK System!', 'Welcome to UNK System!\n\nDear {user_name},\n\nThank you for registering with us.\n\nYou can now start shopping and exploring our marketplace.\n\nVisit: {login_link}', 'Sent when a new user registers', 'user_name, login_link', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('13', 'business_approval', 'Business Approval', 'Your business has been approved!', 'Dear {business_name},\n\nYour business has been approved!\n\nYou can now start listing products and selling on UNK System.\n\nLogin to your dashboard: {dashboard_link}', 'Sent when a business is approved', 'business_name, dashboard_link', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44'),
('14', 'delivery_assigned', 'Delivery Assigned', 'New delivery assigned to you', 'Dear {agent_name},\n\nYou have been assigned a new delivery.\n\nOrder ID: {order_id}\nPickup: {pickup_address}\nDelivery: {delivery_address}\n\nPlease login to accept this delivery.', 'Sent when a delivery is assigned to an agent', 'agent_name, order_id, pickup_address, delivery_address', '1', '2026-07-14 00:08:44', '2026-07-14 00:08:44');

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notif_id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_order` (`order_id`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `fk_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` VALUES
('93', '20', '89', 'Order 89 Updated', 'Order status changed from Pending to Accepted. ', '0', '2026-07-12 02:37:52'),
('94', '20', '89', 'Order 89 Updated', 'Order status changed from Accepted to Confirmed. ', '0', '2026-07-12 02:45:19'),
('95', '20', '89', 'Order 89 Updated', 'Order status changed from Confirmed to Preparing. ', '0', '2026-07-12 03:15:28'),
('96', '20', '89', 'Order 89 Updated', 'Order status changed from Preparing to Ready. ', '0', '2026-07-12 03:15:39'),
('97', '20', '88', 'Order 88 Updated', 'Order status changed from Pending to Accepted. ', '0', '2026-07-12 03:18:24'),
('98', '20', '88', 'Order 88 Updated', 'Order status changed from Accepted to Confirmed. ', '0', '2026-07-13 23:19:24'),
('99', '20', '90', 'Order 90 Updated', 'Order status changed from Pending to Accepted. ', '0', '2026-08-03 03:14:02'),
('100', '20', '90', 'Order 90 Updated', 'Order status changed from Accepted to Confirmed. ', '0', '2026-08-03 03:14:16'),
('101', '20', '90', 'Order 90 Updated', 'Order status changed from Confirmed to Preparing. ', '0', '2026-08-03 03:17:03'),
('102', '20', '90', 'Order 90 Updated', 'Order status changed from Preparing to Ready. ', '0', '2026-08-03 03:17:14'),
('103', '20', '91', 'Order 91 Updated', 'Order status changed from Pending to Accepted. ', '0', '2026-08-03 05:01:12');

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `order_items` VALUES
('94', '88', '61', '1', '285000.00', '285000.00'),
('95', '89', '65', '1', '175000.00', '175000.00'),
('96', '90', '65', '1', '175000.00', '175000.00'),
('97', '91', '65', '1', '175000.00', '175000.00');

DROP TABLE IF EXISTS `order_logs`;
CREATE TABLE `order_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `order_logs_ibfk_1` (`order_id`),
  CONSTRAINT `order_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `order_status_history`;
CREATE TABLE `order_status_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `old_payment` varchar(20) DEFAULT NULL,
  `new_payment` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `idx_osh_order` (`order_id`),
  KEY `idx_osh_created_by` (`created_by`),
  KEY `idx_osh_created` (`created_at`),
  CONSTRAINT `fk_osh_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `order_status_history` VALUES
('105', '89', 'pending', 'accepted', '', '19', '2026-07-12 02:37:52', 'pending', 'pending'),
('106', '89', 'accepted', 'confirmed', 'confirmed', '19', '2026-07-12 02:45:19', 'pending', 'pending'),
('107', '89', 'confirmed', 'preparing', '', '19', '2026-07-12 03:15:28', 'pending', 'pending'),
('108', '89', 'preparing', 'ready', '', '19', '2026-07-12 03:15:39', 'pending', 'pending'),
('109', '88', 'pending', 'accepted', '', '19', '2026-07-12 03:18:24', 'pending', 'pending'),
('110', '88', 'accepted', 'confirmed', '', '19', '2026-07-13 23:19:24', 'pending', 'pending'),
('111', '90', 'pending', 'accepted', '', '19', '2026-08-03 03:14:01', 'pending', 'pending'),
('112', '90', 'accepted', 'confirmed', '', '19', '2026-08-03 03:14:16', 'pending', 'pending'),
('113', '90', 'confirmed', 'preparing', '', '19', '2026-08-03 03:17:03', 'pending', 'pending'),
('114', '90', 'preparing', 'ready', '', '19', '2026-08-03 03:17:14', 'pending', 'pending'),
('115', '91', 'pending', 'accepted', '', '19', '2026-08-03 05:01:12', 'pending', 'pending');

DROP TABLE IF EXISTS `order_tracking`;
CREATE TABLE `order_tracking` (
  `tracking_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tracking_id`),
  KEY `idx_order_tracking_order` (`order_id`),
  KEY `idx_order_tracking_status` (`status`),
  CONSTRAINT `fk_order_tracking_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `agent_id` int(11) DEFAULT NULL,
  `grand_total` decimal(10,2) NOT NULL,
  `status` enum('pending','accepted','confirmed','preparing','ready','picked_up','in_transit','delivered','cancelled') DEFAULT 'pending',
  `delivery_otp` varchar(10) DEFAULT NULL,
  `delivery_otp_expires` timestamp NULL DEFAULT NULL,
  `delivery_otp_verified` tinyint(1) NOT NULL DEFAULT 0,
  `delivery_completed_at` timestamp NULL DEFAULT NULL,
  `payment_method` enum('cash','mobile_money','card') DEFAULT 'cash',
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `delivery_address` text NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `estimated_delivery_time` datetime DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_business` (`business_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_date` (`order_date`),
  KEY `idx_orders_agent` (`agent_id`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_customer_status` (`customer_id`,`status`),
  KEY `idx_orders_business_status` (`business_id`,`status`),
  KEY `idx_orders_date_status` (`order_date`,`status`),
  KEY `idx_orders_grand_total` (`grand_total`),
  CONSTRAINT `fk_orders_agent` FOREIGN KEY (`agent_id`) REFERENCES `delivery_agents` (`agent_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `orders` VALUES
('88', '5', '7', '2026-07-12 02:15:36', '285000.00', '9500.00', '4', '294500.00', 'in_transit', NULL, NULL, '0', NULL, 'cash', 'pending', 'Mabibo', '[Payment: Cash]', NULL),
('89', '5', '7', '2026-07-12 02:19:03', '175000.00', '9500.00', '4', '184500.00', 'delivered', NULL, NULL, '0', NULL, 'cash', 'pending', 'Mabibo', 'Call me 0615215404\n[Payment: Cash]', NULL),
('90', '5', '7', '2026-08-03 03:03:52', '175000.00', '9500.00', '4', '184500.00', 'in_transit', NULL, NULL, '0', NULL, 'cash', 'pending', 'Mabibo', '[Payment: Cash]', NULL),
('91', '5', '7', '2026-08-03 04:51:06', '175000.00', '9500.00', NULL, '184500.00', 'accepted', NULL, NULL, '0', NULL, 'cash', 'pending', 'Mabibo', '[Payment: Cash]', NULL);

DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE `otp_verifications` (
  `otp_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` enum('login','admin_login','registration','2fa','order_verification','payment_verification','business_verification','email_change','phone_verification','delete_account','delivery_verification','customer_password_reset','business_password_reset','delivery_password_reset') NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'Order ID, Business ID, etc.',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`otp_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_email` (`email`),
  KEY `idx_otp_code` (`otp_code`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_is_used` (`is_used`),
  KEY `idx_purpose` (`purpose`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `otp_verifications` VALUES
('131', NULL, NULL, 'admin@unksystem.com', '453841', 'admin_login', NULL, '2026-07-15 11:35:51', '1', '0', '2026-07-15 12:25:51', '2026-07-15 12:26:12'),
('132', NULL, NULL, 'admin@unksystem.com', '596650', 'admin_login', NULL, '2026-07-15 11:36:24', '1', '0', '2026-07-15 12:26:24', '2026-07-15 12:26:42'),
('134', NULL, NULL, 'albinokh425@gmail.com', '416011', 'customer_password_reset', NULL, '2026-07-15 11:38:39', '1', '0', '2026-07-15 12:28:39', '2026-07-15 12:28:59'),
('136', NULL, NULL, 'admin@unksystem.com', '420944', 'admin_login', NULL, '2026-07-15 12:29:50', '1', '0', '2026-07-15 13:19:50', '2026-07-15 13:20:45'),
('137', NULL, NULL, 'admin@unksystem.com', '400562', 'admin_login', NULL, '2026-07-15 12:33:07', '1', '0', '2026-07-15 13:23:07', '2026-07-15 13:23:29'),
('138', NULL, NULL, 'admin@unksystem.com', '692769', 'admin_login', NULL, '2026-07-15 12:33:38', '1', '0', '2026-07-15 13:23:38', '2026-07-15 13:23:58'),
('139', NULL, NULL, 'admin@unksystem.com', '328695', 'admin_login', NULL, '2026-07-15 12:34:08', '1', '1', '2026-07-15 13:24:08', '2026-07-15 13:24:34'),
('141', NULL, NULL, 'admin@unksystem.com', '515296', 'admin_login', NULL, '2026-07-15 13:13:15', '1', '1', '2026-07-15 14:03:15', '2026-08-03 05:14:47'),
('146', NULL, NULL, 'admin@unksystem.com', '354244', '', NULL, '2026-07-19 17:38:58', '0', '0', '2026-07-19 18:28:58', '2026-07-19 18:28:58'),
('158', NULL, NULL, 'albinokh425@gmail.com', '898008', 'login', NULL, '2026-08-03 03:56:20', '1', '1', '2026-08-03 04:46:20', '2026-08-03 04:47:48'),
('159', NULL, NULL, 'mwaminnyoni@gmail.com', '731060', 'login', NULL, '2026-08-03 04:07:04', '1', '0', '2026-08-03 04:57:04', '2026-08-03 04:57:33'),
('160', NULL, NULL, 'mohamed@gmail.com', '523409', 'login', NULL, '2026-08-03 04:20:23', '1', '0', '2026-08-03 05:10:23', '2026-08-03 05:10:45'),
('161', NULL, NULL, 'admin@unksystem.com', '415968', '', NULL, '2026-08-03 04:24:19', '0', '0', '2026-08-03 05:14:19', '2026-08-03 05:14:19'),
('162', NULL, NULL, 'admin@unksystem.com', '894677', 'admin_login', NULL, '2026-08-03 04:24:54', '1', '0', '2026-08-03 05:14:54', '2026-08-03 05:15:21');

DROP TABLE IF EXISTS `price_alerts`;
CREATE TABLE `price_alerts` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `desired_price` decimal(10,2) NOT NULL,
  `status` enum('active','triggered','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`alert_id`),
  KEY `idx_alerts_customer` (`customer_id`),
  KEY `idx_alerts_product` (`product_id`),
  KEY `idx_alerts_status` (`status`),
  CONSTRAINT `fk_alerts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_alerts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `price_history`;
CREATE TABLE `price_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_price_history_product` (`product_id`,`recorded_at`),
  CONSTRAINT `fk_price_history_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `business_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity_in_stock` int(11) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL,
  `min_order` int(11) DEFAULT 1,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `style_category` varchar(50) DEFAULT 'General',
  `discount_percent` int(11) DEFAULT 0,
  `discount_start_date` date DEFAULT NULL,
  `discount_end_date` date DEFAULT NULL,
  `is_on_sale` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`product_id`),
  KEY `idx_products_business` (`business_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_name` (`name`(100)),
  KEY `idx_products_available` (`is_available`),
  KEY `idx_products_business_available` (`business_id`,`is_available`),
  KEY `idx_products_category_price` (`category_id`,`price`),
  KEY `idx_products_price_range` (`price`),
  KEY `idx_products_name_search` (`name`(50)),
  CONSTRAINT `fk_products_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` VALUES
('26', '10', '67', 'Samsung S10 plus', 'Samsung s10 plus RAM 12 GB 256', '370000.00', '22', 'piece', '1', 'assets/uploads/products/1780413814_6a1ef576b24f2.webp', '1', '12', '2026-06-03 04:23:34', '2026-06-08 01:16:25', NULL, 'General', '0', NULL, NULL, '0'),
('36', '10', '67', 'samsung s10 pro', '', '280000.00', '21', 'piece', '1', 'assets/uploads/products/1780546795_6a20fceb50ac4.webp', '1', '10', '2026-06-04 07:19:55', '2026-06-30 03:07:17', NULL, 'General', '0', NULL, NULL, '0'),
('37', '10', '67', 'samsung s10 pro', '', '280000.00', '29', 'piece', '1', 'assets/uploads/products/1780548688_6a2104504d910.webp', '1', '10', '2026-06-04 07:51:28', '2026-06-11 21:11:52', NULL, 'General', '0', NULL, NULL, '0'),
('38', '10', '67', 'samsung s21 ultra', '', '750000.00', '21', 'piece', '1', 'assets/uploads/products/1780548759_6a2104974ab0c.webp', '1', '5', '2026-06-04 07:52:39', '2026-07-02 13:37:53', NULL, 'General', '0', NULL, NULL, '0'),
('39', '10', '67', 'samsung note10 plus', '', '500000.00', '13', 'piece', '1', 'assets/uploads/products/1780548841_6a2104e90866f.webp', '1', '4', '2026-06-04 07:54:01', '2026-06-10 19:48:17', NULL, 'General', '0', NULL, NULL, '0'),
('40', '10', '67', 'samsung s22 ultra', '', '870000.00', '13', 'piece', '1', 'assets/uploads/products/1780548932_6a2105449b491.webp', '1', '6', '2026-06-04 07:55:32', '2026-06-11 19:54:01', NULL, 'General', '0', NULL, NULL, '0'),
('41', '10', '67', 'samsung s10 pro', '', '270000.00', '12', 'piece', '1', 'assets/uploads/products/1780548997_6a210585b2c5b.webp', '1', '11', '2026-06-04 07:56:37', '2026-06-27 18:08:09', NULL, 'General', '0', NULL, NULL, '0'),
('42', '10', '67', 'samsung s10 pro', 'used RAM 8 GB 128, no finger plint', '260000.00', '21', 'piece', '1', 'assets/uploads/products/1780549107_6a2105f38e310.webp', '1', '23', '2026-06-04 07:58:27', '2026-08-03 04:41:12', NULL, 'General', '0', NULL, NULL, '0'),
('43', '7', '82', 'curtains', 'Darkblue and White', '50000.00', '21', 'piece', '1', 'assets/uploads/products/1780549697_6a21084173a28.jpeg', '1', '20', '2026-06-04 08:08:17', '2026-08-03 04:45:37', NULL, 'General', '0', NULL, NULL, '0'),
('44', '7', '82', 'curtains', 'Curtains For home, office and school', '50000.00', '18', 'piece', '1', 'assets/uploads/products/1780549793_6a2108a1422fd.jpeg', '1', '11', '2026-06-04 08:09:53', '2026-07-02 13:49:11', NULL, 'General', '0', NULL, NULL, '0'),
('45', '7', '82', 'curtains', 'Curtains For home, office and school', '35000.00', '12', 'piece', '1', 'assets/uploads/products/1780549867_6a2108eb9b7b0.jpeg', '1', '8', '2026-06-04 08:10:31', '2026-07-02 13:49:49', NULL, 'General', '0', NULL, NULL, '0'),
('46', '7', '82', 'curtains', 'Curtains For home, office and school', '50000.00', '11', 'piece', '1', 'assets/uploads/products/1780549916_6a21091c8bfd1.jpeg', '1', '18', '2026-06-04 08:11:56', '2026-07-10 12:59:02', NULL, 'General', '0', NULL, NULL, '0'),
('47', '7', '82', 'curtains', '0', '45000.00', '11', 'piece', '1', 'assets/uploads/products/1780550005_6a2109754664d.jpeg', '1', '20', '2026-06-04 08:13:25', '2026-07-02 13:51:58', NULL, 'General', '0', NULL, NULL, '0'),
('48', '7', '82', 'curtains', 'Curtains for home, office and others best for price', '50000.00', '11', 'piece', '1', 'assets/uploads/products/1780609524_6a21f1f4bec24.jpeg', '1', '72', '2026-06-05 00:45:24', '2026-07-13 23:33:10', NULL, 'General', '0', NULL, NULL, '0'),
('49', '7', '82', 'curtains', 'white and black curtains', '50000.00', '15', 'piece', '1', 'assets/uploads/products/1780609600_6a21f2402c79b.jpeg', '1', '65', '2026-06-05 00:46:40', '2026-07-18 13:28:32', NULL, 'General', '0', NULL, NULL, '0'),
('51', '10', '68', 'Hp pc', '', '370000.00', '13', 'piece', '1', 'assets/uploads/products/1781179469_6a2aa44dac528.jpg', '1', '7', '2026-06-11 15:04:29', '2026-07-09 10:07:35', NULL, 'General', '0', NULL, NULL, '0'),
('52', '11', '78', 'Watch', 'Gold watch', '35000.00', '16', 'piece', '1', 'assets/uploads/products/1781220872_6a2b46085fcc8.webp', '1', '1', '2026-06-12 02:34:32', '2026-06-12 02:57:53', NULL, 'General', '0', NULL, NULL, '0'),
('53', '11', '78', 'Tissot-Chrono-XL-1-1024x667 watch', 'Tissot-Chrono-XL-1-1024x667 watch', '45000.00', '11', 'piece', '1', 'assets/uploads/products/1781220975_6a2b466fb9ce7.jpg', '1', '3', '2026-06-12 02:36:15', '2026-06-30 03:07:24', NULL, 'General', '0', NULL, NULL, '0'),
('54', '11', '78', 'pexels-fernando-arcos-watch', 'pexels-fernando-arcos-190819-scaled watch for Women & Men', '35000.00', '13', 'piece', '1', 'assets/uploads/products/1781221136_6a2b4710d19be.jpg', '1', '10', '2026-06-12 02:38:56', '2026-07-06 13:53:32', NULL, 'General', '0', NULL, NULL, '0'),
('55', '11', '78', 'pexels-fernando-arcos-watch', 'pexels-fernando-arcos-190819-scaled-e1602768420525', '35000.00', '14', 'piece', '1', 'assets/uploads/products/1781221233_6a2b4771e9b97.jpg', '1', '6', '2026-06-12 02:40:33', '2026-07-10 13:33:57', NULL, 'General', '0', NULL, NULL, '0'),
('56', '11', '78', 'OIP women watch', 'OIP women watch', '50000.00', '14', 'piece', '1', 'assets/uploads/products/1781221299_6a2b47b3922ae.webp', '1', '8', '2026-06-12 02:41:39', '2026-07-19 18:33:34', NULL, 'General', '0', NULL, NULL, '0'),
('57', '11', '78', 'Tissot-Chrono-XL-1-1024x667 watch', 'Tissot-Chrono-XL-1-1024x667 watch', '35000.00', '17', 'piece', '1', 'assets/uploads/products/1781221365_6a2b47f58ca02.jpg', '1', '16', '2026-06-12 02:42:45', '2026-07-15 15:34:42', NULL, 'General', '0', NULL, NULL, '0'),
('58', '11', '78', 'pin de R. H. en Audemars Piguet watch', 'pin de R. H. en Audemars Piguet for women and Men', '25000.00', '8', 'piece', '1', 'assets/uploads/products/1781221479_6a2b48679cf97.webp', '1', '20', '2026-06-12 02:44:39', '2026-07-17 21:45:05', NULL, 'General', '0', NULL, NULL, '0'),
('59', '7', '79', 'Dressing Table', 'usafiri ni bure kwa wakazi wa Dar es Salaam', '235000.00', '11', 'piece', '1', 'assets/uploads/products/1782625615_6a40b54fdbc32.jpg', '1', '8', '2026-06-28 08:46:55', '2026-06-28 22:19:24', NULL, 'General', '0', NULL, NULL, '0'),
('60', '7', '79', 'Dressings Table', 'Dressings Table', '200000.00', '15', 'piece', '1', 'assets/uploads/products/1782625807_6a40b60f0c98f.jpg', '1', '9', '2026-06-28 08:50:07', '2026-08-03 04:33:38', NULL, 'General', '0', NULL, NULL, '0'),
('61', '7', '79', 'Dressings Table', '', '285000.00', '12', 'piece', '1', 'assets/uploads/products/1782626150_6a40b766488b1.jpg', '1', '13', '2026-06-28 08:55:50', '2026-08-03 04:53:25', NULL, 'General', '0', NULL, NULL, '0'),
('63', '7', '79', 'Dressings Table', '', '175000.00', '0', 'piece', '1', 'assets/uploads/products/1782626509_6a40b8cd5140d.jpg', '1', '3', '2026-06-28 09:01:49', '2026-07-19 18:26:19', NULL, 'General', '0', NULL, NULL, '0'),
('64', '7', '79', 'Dressings Table', '', '175000.00', '11', 'piece', '1', 'assets/uploads/products/1782626558_6a40b8fe94870.jpg', '1', '7', '2026-06-28 09:02:38', '2026-07-12 03:20:58', NULL, 'General', '0', NULL, NULL, '0'),
('65', '7', '79', 'Dressings Table', '0', '175000.00', '13', 'piece', '1', 'assets/uploads/products/1782626615_6a40b937e2088.jpg', '1', '44', '2026-06-28 09:03:35', '2026-08-03 05:18:23', NULL, 'General', '10', '2026-07-07', '2026-07-17', '1');

DROP TABLE IF EXISTS `return_requests`;
CREATE TABLE `return_requests` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `return_number` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `order_id` (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `ticket_id` (`ticket_id`),
  CONSTRAINT `return_requests_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_ibfk_3` FOREIGN KEY (`ticket_id`) REFERENCES `customer_support_tickets` (`ticket`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `review_responses`;
CREATE TABLE `review_responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`response_id`),
  KEY `idx_responses_review` (`review_id`),
  KEY `idx_responses_business` (`business_id`),
  CONSTRAINT `fk_responses_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_responses_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`review_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `helpful_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uk_reviews_customer_product` (`customer_id`,`product_id`),
  KEY `idx_reviews_product` (`product_id`),
  KEY `idx_reviews_status` (`status`),
  KEY `idx_reviews_rating` (`rating`),
  CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_reviews_rating` CHECK (`rating` >= 1 and `rating` <= 5)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `reviews` VALUES
('1', '5', '48', '3', 'goods', 'approved', '0', '2026-06-07 21:21:59', '2026-06-07 21:24:25'),
('2', '5', '43', '3', 'good', 'approved', '0', '2026-06-07 21:22:28', '2026-06-07 21:24:20'),
('3', '5', '45', '3', 'good', 'approved', '0', '2026-06-11 00:18:28', '2026-06-11 18:51:17'),
('4', '5', '56', '3', 'goods performance, looking and better product', 'approved', '0', '2026-06-18 23:41:18', '2026-06-18 23:45:40'),
('5', '5', '42', '3', 'goods', 'approved', '0', '2026-06-19 15:36:15', '2026-06-19 15:43:01'),
('6', '5', '41', '3', 'goods', 'pending', '0', '2026-06-19 15:37:11', '2026-06-19 15:37:11');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_key_group` (`setting_key`,`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` VALUES
('1', 'smtp_host', 'smtp.gmail.com', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('2', 'smtp_port', '587', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('3', 'smtp_encryption', 'tls', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('4', 'smtp_username', 'admin@unksystem.com', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('5', 'smtp_password', 'Twaha@2004', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('6', 'from_email', 'noreply@unksystem.com', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('7', 'from_name', 'UNK System', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('8', 'enable_notifications', '1', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('9', 'order_confirmation', '1', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('10', 'delivery_notification', '1', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('11', 'payment_receipt', '1', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('12', 'welcome_email', '1', 'email', '2026-06-21 20:13:11', '2026-06-21 20:13:11'),
('13', 'password_reset', '1', 'email', '2026-06-21 20:13:12', '2026-06-21 20:13:12');

DROP TABLE IF EXISTS `stock_history`;
CREATE TABLE `stock_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `action_type` varchar(50) DEFAULT 'update',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_stock_product` (`product_id`),
  KEY `idx_stock_business` (`business_id`),
  KEY `idx_stock_created` (`created_at`),
  CONSTRAINT `fk_stock_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_history` VALUES
('6', '65', '7', '8', '11', '3', 'manual_update', 'stock adjustment', '2026-07-12 03:17:01'),
('7', '64', '7', '9', '11', '2', 'manual_update', 'stock adjustment', '2026-07-12 03:17:11'),
('8', '63', '7', '10', '8', '-2', 'manual_update', '', '2026-07-19 18:22:07'),
('9', '63', '7', '8', '0', '-8', 'manual_update', '', '2026-07-19 18:26:19');

DROP TABLE IF EXISTS `support_replies`;
CREATE TABLE `support_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `reply_by_type` enum('customer','business','delivery','admin') NOT NULL,
  `reply_by_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `support_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_no` varchar(20) NOT NULL,
  `created_by_type` enum('customer','business','delivery','admin') NOT NULL,
  `created_by_id` int(11) NOT NULL,
  `assigned_to_type` enum('customer','business','delivery','admin') DEFAULT NULL,
  `assigned_to_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_no` (`ticket_no`),
  KEY `idx_created` (`created_by_type`,`created_by_id`),
  KEY `idx_assigned` (`assigned_to_type`,`assigned_to_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `support_tickets_temp`;
CREATE TABLE `support_tickets_temp` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `customer_id` (`customer_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `support_tickets_temp_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `support_tickets_temp_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','business','customer','delivery') DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user_type` (`user_type`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_logs` VALUES
('1', '1', 'admin', 'view', 'Viewed system logs page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-15 14:14:08'),
('2', '1', 'admin', 'view', 'Viewed system logs page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-15 14:19:01'),
('3', '1', 'admin', 'view', 'Viewed system logs page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-08-02 16:25:00'),
('4', '25', 'delivery', 'view', 'Viewed delivery dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:14:09'),
('5', '25', 'delivery', 'view', 'Viewed delivery dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:14:57'),
('6', '25', 'delivery', 'view', 'Viewed delivery dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:15:21'),
('7', '1', 'admin', 'view', 'Viewed system logs page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:16:31'),
('8', '25', 'delivery', 'view', 'Viewed delivery dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:17:58'),
('9', '25', 'delivery', 'view', 'Viewed delivery dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-03 00:18:24');

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `setting_type` enum('text','number','boolean','json','textarea','select') DEFAULT 'text',
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` VALUES
('1', 'site_name', 'UNK System', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:00'),
('2', 'site_tagline', 'Ulipo ni Kariakoo', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:00'),
('3', 'site_email', 'info@unksystem.com', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:00'),
('4', 'site_phone', '+255 615 215 404', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:00'),
('5', 'site_address', 'Kariakoo Market, Dar es Salaam, Tanzania', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:01'),
('6', 'site_currency', 'TSh', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:01'),
('7', 'timezone', 'Africa/Dar_es_Salaam', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:01'),
('8', 'date_format', 'Y-m-d', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:01'),
('9', 'time_format', 'H:i:s', 'general', 'text', '0', '2026-06-21 19:01:17', '2026-07-01 02:45:01'),
('82', 'maintenance_mode', '0', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('83', 'allow_registration', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('84', 'require_email_verification', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('85', 'require_phone_verification', '0', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('86', 'enable_guest_checkout', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('87', 'enable_reviews', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:39'),
('88', 'enable_wishlist', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('89', 'enable_comparison', '1', 'system', 'boolean', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('90', 'max_upload_size', '5', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('91', 'allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx', 'system', 'text', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('92', 'image_quality', '80', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('93', 'thumbnail_width', '300', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('94', 'thumbnail_height', '300', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('95', 'items_per_page', '15', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('96', 'max_cart_items', '50', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('97', 'session_timeout', '3600', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:40'),
('98', 'max_login_attempts', '5', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:41'),
('99', 'lockout_time', '15', 'system', 'number', '0', '2026-06-21 19:11:02', '2026-06-21 19:14:41'),
('100', 'enable_ssl', '1', 'system', 'boolean', '0', '2026-06-21 19:11:03', '2026-06-21 19:14:41'),
('101', 'enable_cache', '1', 'system', 'boolean', '0', '2026-06-21 19:11:03', '2026-06-21 19:14:41'),
('102', 'cache_duration', '3600', 'system', 'number', '0', '2026-06-21 19:11:03', '2026-06-21 19:14:41'),
('103', 'enable_debug_mode', '0', 'system', 'boolean', '0', '2026-06-21 19:11:03', '2026-06-21 19:14:41'),
('104', 'log_errors', '1', 'system', 'boolean', '0', '2026-06-21 19:11:03', '2026-06-21 19:14:41'),
('105', 'auto_backup_enabled', '1', 'backup', 'text', '0', '2026-06-21 19:27:51', '2026-07-15 13:53:06'),
('106', 'backup_frequency', 'daily', 'backup', 'text', '0', '2026-06-21 19:27:51', '2026-07-15 13:53:06'),
('107', 'backup_time', '13:47', 'backup', 'text', '0', '2026-06-21 19:27:51', '2026-07-15 13:53:06'),
('108', 'backup_type', 'full', 'backup', 'text', '0', '2026-06-21 19:27:51', '2026-07-15 13:53:06'),
('109', 'max_backups', '10', 'backup', 'text', '0', '2026-06-21 19:27:51', '2026-07-15 13:53:06'),
('110', 'backup_retention_days', '30', 'backup', 'text', '0', '2026-06-21 19:27:52', '2026-07-15 13:53:06'),
('111', 'backup_location', '../backups/', 'backup', 'text', '0', '2026-06-21 19:27:52', '2026-07-15 13:53:06'),
('112', 'compress_backups', '0', 'backup', 'text', '0', '2026-06-21 19:27:52', '2026-07-15 13:53:06'),
('113', 'email_backup_notifications', '1', 'backup', 'text', '0', '2026-06-21 19:27:52', '2026-07-15 13:53:06'),
('114', 'backup_email', 'admin@unksystem.com', 'backup', 'text', '0', '2026-06-21 19:27:52', '2026-07-15 13:53:06'),
('143', 'last_auto_backup', '2026-07-15 12:46:33', 'backup', 'text', '0', '2026-07-15 13:46:33', '2026-07-15 13:46:33');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('admin','business','customer','delivery') NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES
('1', 'Mohamed Mussa', 'admin@unksystem.com', '$2y$10$kHxvkyOoMhmfEV.5ukaRY.zIoJgsBIOilkWwCNBxE1KArZzXYpQ2O', '0615215404', 'admin', 'active', '2026-08-03 05:15:21', '2026-06-07 08:30:16', '2026-08-03 05:15:21'),
('19', 'Mwamini Nyoni', 'mwaminnyoni@gmail.com', '$2y$10$DYckD.D8TJJKuYWmeHXpCOLwujtzrltDpoA0.q6n6yD1WGwQE6EhS', '0767708012', 'business', 'active', '2026-08-03 04:57:33', '2026-06-01 00:32:03', '2026-08-03 04:57:33'),
('20', 'Twaha Mohamed', 'albinokh425@gmail.com', '$2y$10$y1AWlxkc8JQxSaQl4xBaf.cYCpazly9i1ysu627lQlMYLsfScQhlG', '0617666477', 'customer', 'active', '2026-08-03 04:47:48', '2026-06-01 00:50:57', '2026-08-03 04:47:48'),
('25', 'Mohamed Ajemy', 'mohamed@gmail.com', '$2y$10$KNaIDvEIxOThE9YSqOYO1OlgroGuwaPem5HH.E0MnvxSFsjHaoa9C', '0617666478', 'delivery', 'active', '2026-08-03 05:10:45', '2026-06-02 22:11:43', '2026-08-03 05:10:45'),
('28', 'Mohamed Twaha', 'twahakh425@gmail.com', '$2y$10$A8e4KVoDKqrwAPjS.G.u7usx2PwfhL/3fccfnncjjZ.wQ5sXLEsBG', '0799051862', 'business', 'active', '2026-07-02 14:26:27', '2026-06-03 03:22:59', '2026-07-02 14:26:27'),
('31', 'Paulo Sanii', 'paulohhari991@gmail.com', '$2y$10$eIB8CwJjL93KCdAVpRCTfOh7qs9oJKJOZYc17VXuSWJaBJHH1XXgO', '0764080102', 'business', 'active', '2026-07-02 13:58:20', '2026-06-10 16:26:43', '2026-07-02 13:58:20');

DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `uk_wishlist_customer_product` (`customer_id`,`product_id`),
  KEY `idx_wishlist_customer` (`customer_id`),
  KEY `idx_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
