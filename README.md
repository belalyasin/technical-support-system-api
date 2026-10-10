# Technical Support System API

REST API for a technical support chat system, built with Laravel 11. Customers open conversations, support staff answer them, and staff keep a library of predefined messages.

The Angular front end lives in [technical-support-system-frontend](https://github.com/belalyasin/technical-support-system-frontend).

## Features

- Token authentication with Laravel Sanctum
- Three roles: `user` (customer), `admin` and `super admin` (support staff)
- Conversations between a customer and support staff, with unread counts per user
- Predefined messages, organised as a tree, managed by staff
- User management for staff
- Password reset, email change and phone change confirmed by a 4-digit code sent by email

## Tech stack

PHP 8.2+, Laravel 11, Laravel Sanctum, Spatie Laravel Permission, MySQL

## Getting started

```bash
git clone https://github.com/belalyasin/technical-support-system-api.git
cd technical-support-system-api
composer install
cp .env.example .env      # set the DB_* and MAIL_* values
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeding creates the three roles and a first super admin: `admin@example.com` with the password `password`. Change that password before using the system for real.

Browser requests are accepted from `http://localhost:4200` (the Angular front end in development). Add other origins in `config/cors.php`.

With `MAIL_MAILER=log`, verification codes are written to `storage/logs/laravel.log` instead of being emailed, which is handy during development.

## Tests

```bash
php artisan test
```

The tests run against an in-memory SQLite database and cover authentication, conversations, messages, user management and predefined messages.

## API

All routes are under `/api`. Send `Accept: application/json`. Routes other than the public ones need an `Authorization: Bearer <token>` header.

### Public

| Method | Route | Body |
| --- | --- | --- |
| POST | `/register` | `name`, `email`, `phone`, `password`. Always creates a customer account |
| POST | `/login` | `email`, `password`. Returns `token` and `roles` |
| POST | `/forgot-password` | `email`. Emails a reset code |
| POST | `/reset-password` | `email`, `code`, `password`, `password_confirmation` |

### Account

| Method | Route | Body |
| --- | --- | --- |
| GET | `/current-user` | Returns the signed-in user with their `roles` |
| POST | `/logout` | |
| PUT | `/change-password` | `password`, `new_password`, `new_password_confirmation` |
| POST | `/initiateEmailChange` | `new_email`. Emails a code to the new address |
| POST | `/confirmEmailChange` | `new_email`, `code` |
| POST | `/initiatePhoneChange` | `new_phone`. Emails a code to the account's address |
| POST | `/confirmPhoneChange` | `new_phone`, `code` |

### Conversations

Customers see the conversations they take part in. Staff see all of them. A conversation is addressed by its `uuid`.

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/conversations` | Most recently active first, with `unread_count` and `latest_message` |
| POST | `/conversations` | `body` (first message), optional `name`. Staff can add `user_id` to open one with a customer |
| GET | `/conversations/{uuid}` | Includes `participants` |
| POST | `/conversations/{uuid}/read` | Marks the conversation as read |
| GET | `/conversations/{uuid}/messages` | Oldest first, 50 per page. Marks the conversation as read for a participant |
| POST | `/conversations/{uuid}/messages` | `body`. Staff who reply join the conversation |
| DELETE | `/conversations/{uuid}` | Staff only. Deletes the conversation and its messages |

### Predefined messages

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/default-messages`, `/default-messages/{id}` | Any signed-in user |
| POST | `/default-messages` | Staff only. `body`, optional `parent_id` |
| PUT | `/default-messages/{id}` | Staff only |
| DELETE | `/default-messages/{id}` | Staff only. Refused while the message has children |

### Users (staff only)

| Method | Route | Notes |
| --- | --- | --- |
| GET | `/users` | Filter with `?role=` and `?search=` |
| POST | `/users` | `name`, `email`, `phone`, `password`, `role` |
| GET | `/users/{id}` | |
| PUT | `/users/{id}` | Any of `name`, `email`, `phone`, `password`, `role` |
| DELETE | `/users/{id}` | Refused for your own account and for users who replied in other people's conversations |

Only a super admin can create, change or delete a super admin.

## Data model

`users`, `conversations`, `messages`, `conversation_user` (participants and when each last read the conversation), `default_messages`, `details`, plus the Spatie roles and permissions tables.
