# trendier-bot
Alice skill + Telegram-bot that shows which of two words is more popular according to the Google Trends data.

Links
----
1. [Bot itself](https://t.me/TrendierBot)
2. [Author's channel](https://t.me/FilteredInternet)

Usage
---
Send message to bot that contains two words / names divided by «или» e.g. «арбуз или дыня» and it will send you back more popular word.

Setup
---
Requires PHP 8.5 with curl, mbstring and pdo_mysql, and MySQL / MariaDB. No Composer or third-party packages, just upload the files.

1. `cp .env.example .env` and fill it in.
2. Create the tables from `schema.sql`.
3. Point the webhooks at `telegram.php` (Telegram) and `alice.php` (Yandex Dialogs).
   `.htaccess` keeps everything else, `.env` included, closed from the web.

To protect the Telegram webhook, set a random `TELEGRAM_SECRET` in `.env` and pass the same value to Telegram:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook" -d url=https://example.com/telegram.php -d secret_token=<TELEGRAM_SECRET>
```

The bot answers right in the webhook response, so the bot token itself isn't needed on the server.
