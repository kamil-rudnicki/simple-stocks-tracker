# Simple Stocks Tracker

One file PHP stocks tracker. Because there should be single source of truth (transactions),
and programming language is more powerful than spreadsheet in the end.

## How to configure

1. Create `config.php` with `$config['eodhistoricaldataApiToken'] = "...";`
2. Create `Stocks.sqlite3` with `Stocks.sqlite3.sql`

## Running

* `cd Documents/Stocks/`     
* `php -S localhost:8323`     
* http://localhost:8323/stocks.php

## Todo

* [ ] change over time chart
* [ ] code refactoring
* [ ] what if didn't sell chart
* [ ] ability to have individual account
* [ ] add transactions in web view
* [ ] put in on the Internet for everyone