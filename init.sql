CREATE DATABASE IF NOT EXISTS banking;
USE banking;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `transaction`;
DROP TABLE IF EXISTS `account`;
DROP TABLE IF EXISTS `currency`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `currency` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `account` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tax_id` VARCHAR(16) NOT NULL UNIQUE,
  `owner_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_currency` INT NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_account_currency`
    FOREIGN KEY (`id_currency`) REFERENCES `currency`(`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `transaction` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_account` INT NOT NULL,
  `type` ENUM('DEPOSIT','WITHDRAWAL') NOT NULL,
  `amount` DECIMAL(17,2) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `balance_after` DECIMAL(17,2) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_transaction_account_created` (`id_account`, `created_at`, `id`),
  CONSTRAINT `fk_transaction_account`
    FOREIGN KEY (`id_account`) REFERENCES `account`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_transaction_amount_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO currency (name) VALUES
('AUD'), ('BRL'), ('CAD'), ('CHF'), ('CNY'),
('CZK'), ('DKK'), ('HKD'), ('HUF'), ('IDR'),
('ILS'), ('INR'), ('ISK'), ('JPY'), ('KRW'),
('MXN'), ('MYR'), ('NOK'), ('NZD'), ('PHP'),
('PLN'), ('RON'), ('SEK'), ('SGD'), ('THB'),
('TRY'), ('ZAR'), ('EUR'), ('GBP'), ('USD');


INSERT INTO account (id, tax_id, owner_name, created_at, id_currency)
VALUES
(1, 'IT12345678901234', 'Mario Rossi', '2025-01-10 09:15:00',
 (SELECT id FROM currency WHERE name = 'AUD')),

(2, 'US00000000001234', 'Alice Johnson', '2025-02-20 11:30:00',
 (SELECT id FROM currency WHERE name = 'BRL')),

(3, 'GB99999999991234', 'John Smith', '2025-03-05 08:00:00',
 (SELECT id FROM currency WHERE name = 'CAD')),

(4, 'IT98765432109876', 'Luisa Bianchi', '2025-04-12 16:45:00',
 (SELECT id FROM currency WHERE name = 'AUD'));

INSERT INTO `transaction` (id_account, type, amount, description, created_at, balance_after)
VALUES
(1, 'DEPOSIT', 1000.00, 'Initial deposit', '2025-01-10 09:16:00', 1000.00),
(1, 'WITHDRAWAL', 200.00, 'ATM withdrawal', '2025-01-15 10:00:00', 800.00),
(1, 'DEPOSIT', 50.00, 'Salary adjustment', '2025-01-31 12:00:00', 850.00),

(2, 'DEPOSIT', 5000.00, 'Initial funding', '2025-02-20 11:31:00', 5000.00),
(2, 'WITHDRAWAL', 1250.50, 'Online purchase', '2025-02-25 14:20:00', 3749.50),

(3, 'DEPOSIT', 250.00, 'Gift', '2025-03-05 08:05:00', 250.00),

(4, 'DEPOSIT', 100.00, 'Initial deposit', '2025-04-12 16:50:00', 100.00),
(4, 'WITHDRAWAL', 20.00, 'Coffee shop', '2025-04-13 09:10:00', 80.00);