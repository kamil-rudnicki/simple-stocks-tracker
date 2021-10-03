-- -------------------------------------------------------------
-- TablePlus 4.2.0(388)
--
-- https://tableplus.com/
--
-- Database: Stocks.sqlite3
-- Generation Time: 2021-10-03 08:45:39.1750
-- -------------------------------------------------------------


CREATE TABLE "transactions" ("id" integer,"date" datetime,"type" text,"stock" text,"price" num,"amount" num,"commision" num,"currency" text, "system" text, PRIMARY KEY (id));

INSERT INTO "transactions" ("id", "date", "type", "stock", "price", "amount", "commision", "currency", "system") VALUES
('1', '2021-02-12', 'buy', 'ASAN.US', '40.53', '34', '7.5', 'USD', 'bossa'),
('2', '2021-06-21', 'sell', 'ASAN.US', '57.5', '34', '7.5', 'USD', 'bossa'),
('3', '2021-02-12', 'buy', 'SNOW.US', '300.88', '4', '7.5', 'USD', 'bossa');
