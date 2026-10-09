# Technical Support System API

REST API for a technical support chat system, built with Laravel 11. Customers talk to support agents in conversations, and agents can answer with predefined messages.

The Angular front end lives in [technical-support-system-frontend](https://github.com/belalyasin/technical-support-system-frontend).

## Status

In progress.

- Done: authentication, account management, predefined (default) messages, database schema for conversations and messages, roles and permissions tables
- Next: conversation and message endpoints, user management endpoints

## Tech stack

PHP 8.2, Laravel 11, Laravel Sanctum, Spatie Laravel Permission, MySQL

## Getting started

```bash
git clone https://github.com/belalyasin/technical-support-system-api.git
cd technical-support-system-api
composer install
cp .env.example .env      # set the DB_* values
php artisan key:generate
php artisan migrate
php artisan serve
```

## API

All routes are under `/api`. Routes marked with a lock need an `Authorization: Bearer <token>` header (Sanctum).

| Method | Route | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/register` | | Create an account |
| POST | `/login` | | Log in and get a token |
| GET | `/current-user` | lock | Current user |
| POST | `/logout` | lock | Revoke the token |
| POST | `/forgot-password` | lock | Send a 4-digit reset code |
| POST | `/reset-password` | lock | Reset the password with the code |
| PUT | `/change-password` | lock | Change password |
| POST | `/initiateEmailChange`, `/confirmEmailChange` | lock | Change email with confirmation |
| POST | `/initiatePhoneChange`, `/confirmPhoneChange` | lock | Change phone number with confirmation |
| GET, POST | `/default-messages` | | List and create predefined messages |
| GET, PUT, DELETE | `/default-messages/{id}` | | Read, update and delete a predefined message |

## Data model

`users`, `conversations`, `messages`, `conversation_user` (participants and read state), `details`, `default_messages`, plus the Spatie roles and permissions tables.
