CREATE TABLE IF NOT EXISTS `PREFIX_pricemonitor_scan` (
    `id_scan` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(10) UNSIGNED NOT NULL,
    `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `our_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
    `min_price` DECIMAL(20,6) DEFAULT NULL,
    `max_price` DECIMAL(20,6) DEFAULT NULL,
    `avg_price` DECIMAL(20,6) DEFAULT NULL,
    `competitor_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'unknown',
    `diff_percent` DECIMAL(10,2) DEFAULT NULL,
    `search_query` VARCHAR(512) DEFAULT NULL,
    `date_scan` DATETIME NOT NULL,
    PRIMARY KEY (`id_scan`),
    KEY `idx_product` (`id_product`),
    KEY `idx_product_date` (`id_product`, `date_scan`),
    KEY `idx_status` (`status`),
    KEY `idx_date` (`date_scan`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_pricemonitor_competitor` (
    `id_competitor` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_scan` INT(10) UNSIGNED NOT NULL,
    `id_product` INT(10) UNSIGNED NOT NULL,
    `competitor_name` VARCHAR(255) NOT NULL,
    `competitor_price` DECIMAL(20,6) NOT NULL,
    `competitor_shipping` DECIMAL(20,6) NOT NULL DEFAULT 0,
    `competitor_total_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
    `competitor_url` TEXT DEFAULT NULL,
    `competitor_title` VARCHAR(512) DEFAULT NULL,
    `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_competitor`),
    KEY `idx_scan` (`id_scan`),
    KEY `idx_product` (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_pricemonitor_log` (
    `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(20) NOT NULL DEFAULT 'info',
    `message` TEXT NOT NULL,
    `id_product` INT(10) UNSIGNED DEFAULT NULL,
    `api_credits_used` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `products_scanned` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `execution_time` DECIMAL(10,2) DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_type` (`type`),
    KEY `idx_date` (`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_pricemonitor_exclusion` (
    `id_exclusion` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(10) UNSIGNED NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_exclusion`),
    UNIQUE KEY `idx_product` (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;
