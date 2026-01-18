

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `pid` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(100) NOT NULL,
  `quantity` int(100) NOT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `message` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `email` varchar(100) NOT NULL,
  `method` varchar(50) NOT NULL,
  `address` varchar(500) NOT NULL,
  `total_products` varchar(1000) NOT NULL,
  `total_price` int(100) NOT NULL,
  `placed_on` varchar(50) NOT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(20) NOT NULL,
  `details` varchar(500) NOT NULL,
  `price` int(100) NOT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `user_type` varchar(20) NOT NULL DEFAULT 'user',
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `pid` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(100) NOT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;
COMMIT;


--
-- Changes for panei logimit
--
USE `shop_db`;

-- ------------------------------------------------------------
-- 1) Create auth tables if not exist
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(100) NULL,
  `email_entered` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(100) NULL, -- e.g. invalid_password, blocked, no_user
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email_time` (`email_entered`, `attempted_at`),
  KEY `idx_login_attempts_user_time` (`user_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auth_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(100) NULL,
  `event` VARCHAR(50) NOT NULL, -- LOGIN_SUCCESS, LOGIN_FAIL, LOGIN_BLOCKED, LOGOUT, IDLE_LOGOUT, REMEMBER_LOGIN
  `email_entered` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `details` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auth_logs_user_time` (`user_id`, `created_at`),
  KEY `idx_auth_logs_event_time` (`event`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(100) NOT NULL,
  `selector` CHAR(24) NOT NULL,
  `validator_hash` CHAR(64) NOT NULL, -- SHA-256 hash
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  KEY `idx_remember_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(100) NOT NULL,
  `token_hash` CHAR(64) NOT NULL, -- SHA-256 hash
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_user_time` (`user_id`, `created_at`),
  KEY `idx_pwreset_expires` (`expires_at`),
  KEY `idx_pwreset_used` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2) Add UNIQUE(users.email) only if missing
-- ------------------------------------------------------------

SET @db := DATABASE();

SELECT COUNT(*) INTO @has_unique_email
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'email'
  AND NON_UNIQUE = 0;

SET @sql := IF(@has_unique_email = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_email` (`email`);',
  'SELECT "users.email unique already exists" as info;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3) (Optional) make password longer (safe-ish)
-- NOTE: Only do this if you want. 255 is safer for hashes.
-- ------------------------------------------------------------

-- SET @has_len := (
--   SELECT CHARACTER_MAXIMUM_LENGTH
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = @db AND TABLE_NAME='users' AND COLUMN_NAME='password'
-- );
-- SET @sql2 := IF(@has_len < 255,
--   'ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL;',
--   'SELECT "users.password length already ok" as info;'
-- );
-- PREPARE stmt2 FROM @sql2;
-- EXECUTE stmt2;
-- DEALLOCATE PREPARE stmt2;

ALTER TABLE `orders`
  ADD COLUMN `paypal_order_id` VARCHAR(100) NULL AFTER `payment_status`,
  ADD COLUMN `paypal_capture_id` VARCHAR(100) NULL AFTER `paypal_order_id`,
  ADD COLUMN `paid_at` DATETIME NULL AFTER `paypal_capture_id`;