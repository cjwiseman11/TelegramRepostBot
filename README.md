# TelegramRepostBot

This is my first attempt at a Telegram bot. My webhost does not have SSL so I could not use the webhook method.

##How it works

The bot will read any new messages and if it detects a URL within the message, it will save it to a MySQL database.

If it finds a URL which already exists in the database, it will send a message stating it is a "repost" and save who reposted it. It will also quote the original message so the original poster is notified.

You can type /repoststats to get a list of people and how many times they have reposted.
