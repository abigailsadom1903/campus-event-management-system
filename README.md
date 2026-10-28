# Campus Event Management System (CEMS)

A PHP and MySQL web application for publishing, discovering and registering for university events, with role-based access for students, staff and the public. Developed as an individual **Web Engineering** assignment at Universiti Malaysia Sabah (UMS).

---

## Table of Contents

- [Project Overview](#project-overview)
- [Problem Statement](#problem-statement)
- [Features](#features)
- [User Roles](#user-roles)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Architecture](#architecture)
- [Database](#database)
- [Key Implementation Decisions](#key-implementation-decisions)
- [Installation](#installation)
- [Security Notes](#security-notes)
- [Known Limitations](#known-limitations)
- [Documentation](#documentation)
- [Author](#author)

---

## Project Overview

CEMS gives a campus one place to list events (workshops, sports, competitions and more), let people register, and let organisers manage what they have created. Users sign up, log in to a dashboard, browse upcoming events, register or cancel, and review their own registrations. Staff and organisers can create, update and remove events.

## Problem Statement

Campus events are typically announced through posters, messaging groups and social media. Information is scattered, registration is handled by hand, and organisers have no reliable view of who has signed up or how many places remain.

CEMS centralises event information and registration so that participants can find events and sign up easily, and organisers can manage capacity, fees and registration deadlines in one system.

## Features

- **Account management:** registration, login, logout and profile updates.
- **Role-aware dashboard** for public users, students and staff.
- **Event creation and editing** with name, description, organiser and contact details, event type, venue, date and time.
- **Event modes:** physical, online or hybrid.
- **Capacity and deadlines:** maximum participants and a registration closing date.
- **Free and paid events** with a configurable fee amount.
- **Event registration** and cancellation, with a duplicate-registration guard (one registration per user per event).
- **My Registrations** page listing the events a user has signed up for.
- **Event management page** for organisers to review and delete events.
- **Event status** (active or inactive) to control visibility.
- **Password hashing** for stored credentials.

## User Roles

| Role | Typical use |
| --- | --- |
| Public | Browse events and register |
| Student | Browse events, register, manage own registrations |
| Staff | Everything above, plus create and manage events |

## Technology Stack

| Layer | Technology |
| --- | --- |
| Language | PHP 8 |
| Database | MySQL / MariaDB |
| Frontend | HTML5, CSS3, JavaScript |
| Local server | XAMPP (Apache, MariaDB, phpMyAdmin) |
| Version control | Git and GitHub |

## Project Structure

```
campus-event-management-system/
├── css/                     # Stylesheets
├── data/                    # Supporting data files
├── img/                     # Images
├── config.php               # Database connection settings
├── index.php                # Landing page
├── login.php / logout.php   # Authentication
├── register.php             # Sign-up form
├── register_user.php        # Sign-up handler
├── dashboard.php            # User dashboard
├── createEvent.php          # Event creation form
├── save_event.php           # Event save handler
├── manage.php               # Event management
├── delete_event.php         # Event deletion
├── event_register.php       # Event registration page
├── register_for_event.php   # Registration handler
├── my_registrations.php     # A user's registrations
├── update_profile.php       # Profile update
├── cemsindv.sql             # Database schema and sample data
├── check_tables.php, check_columns.php, create_tables.php, debug_events.php
│                            # Development helper scripts
└── INDIVIDUAL WEB ENGINEERING.pdf
```

## Architecture

CEMS is a **page-per-action PHP application** with no framework. Each `.php` file at the project root is either a page (`index.php`, `login.php`, `dashboard.php`, `manage.php`, `my_registrations.php`) or an action handler that processes a form and redirects (`register_for_event.php`, `save_event.php`, `delete_event.php`, `update_profile.php`).

`config.php` is the shared core that every page includes. It provides:

- the MySQL connection (`$conn`)
- session configuration and the login helpers (`isLoggedIn`, `getCurrentUser`, `requireAuth`, `requireRole`)
- flash-message helpers, input sanitising, and formatting helpers (dates, times, RM currency)

Typical request flow:

```
Browser ──▶ page / handler (e.g. register_for_event.php)
              │  require config.php  (session, $conn, helpers)
              │  check login + validate input
              ▼
           MySQL (cemsindv)
              │
              ▼
   set flash message ──▶ redirect (PRG pattern) ──▶ dashboard.php / index.php
```

Handlers follow the Post/Redirect/Get pattern, so refreshing a page after submitting does not resubmit the form.

## Database

Database name: `cemsindv`

| Table | Purpose |
| --- | --- |
| `users` | Accounts: name, email (unique), hashed password, role (`public`, `student`, `staff`), organisation, phone |
| `events` | Event details, mode, capacity, fee, registration deadline, status, creator |
| `event_registrations` | Which user registered for which event, with status (`registered`, `cancelled`, `pending_payment`) and a unique (event, email) constraint |
| `registrations` | Additional registration and payment-tracking table |

Events are linked to their creator, and registrations to both event and user, with cascading foreign keys.

### Design notes

- **ENUM columns** keep role, mode, fee and status values consistent at the database level.
- **Foreign keys with `ON DELETE CASCADE`** stop orphaned events and registrations when a user or event is removed.
- **A unique key on (`event_id`, `user_email`)** backs up the application's duplicate-registration check.
- **Seat availability is computed** from the number of `registered` rows rather than stored as a counter, so it cannot drift out of sync with the registrations table.
- **`registrations`** has no foreign keys; the registration handler uses `event_registrations`.

## Key Implementation Decisions

- **Prepared statements wherever user input reaches SQL** (`mysqli::prepare` + `bind_param`) to prevent SQL injection.
- **`password_hash()` / `password_verify()`** for credentials, with `session_regenerate_id(true)` on login.
- **Session hardening** in `config.php`: `httponly` cookies, `use_only_cookies`, `SameSite=Lax`, a 24-hour session lifetime and periodic ID regeneration.
- **Ordered registration checks** in `register_for_event.php`: event exists, not already registered, registration still open, seats remaining, then insert. Each failure returns a specific message.
- **Output escaping** with `htmlspecialchars` when echoing user-supplied text.
- **A single `getCurrentUser()` source of truth** reloads the user from the database, so role changes take effect and a deleted user's session is cleared.

## Installation

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or any stack with PHP 8, Apache and MySQL/MariaDB)
- Git

### Steps

1. **Clone the repository**

   ```bash
   git clone https://github.com/abigailsadom1903/campus-event-management-system.git
   ```

2. **Copy the project into your web root**, for example:

   ```
   C:\xampp\htdocs\campus-event-management-system
   ```

3. **Start Apache and MySQL** from the XAMPP Control Panel.

4. **Create and import the database**
   - Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
   - Create a database named `cemsindv`.
   - Select it, choose **Import**, and upload `cemsindv.sql`.

5. **Check the connection settings.** Open `config.php` and confirm the host, database name, username and password match your local setup. XAMPP defaults are usually `localhost`, `root` and an empty password.

6. **Open the application**

   [http://localhost/campus-event-management-system/](http://localhost/campus-event-management-system/)

7. **Sign in.** The sample database includes demo student, staff and public accounts. You can also create a new account from the registration page.

## Security Notes

- The sample SQL file contains demo accounts for local testing. Remove or change them, and use strong passwords, before any real deployment.
- Do not commit real database credentials to the repository.
- The helper scripts (`check_tables.php`, `check_columns.php`, `create_tables.php`, `debug_events.php`) are for development only and should be removed or protected in production.

## Known Limitations

This is a coursework project intended for local use. Beyond the notes above, be aware of the following before any real deployment:

- **Demo accounts are created automatically** whenever the login page loads, using a documented password. Remove `createDemoAccounts()` in `login.php`.
- **Error display is on** (`display_errors = 1`) and the session cookie `secure` flag is off (suitable for `localhost` only). Turn error display off, enable the flag and serve over HTTPS in production.
- **Seat checking is not atomic.** The capacity check and the insert are separate queries, so two simultaneous registrations for the last seat could both succeed. A transaction with row locking would fix this.
- **CSRF protection is incomplete.** A token generator exists in `config.php`, but `register_for_event.php` does not verify a token yet (SameSite cookies give partial protection).
- **Payments are modelled but not processed.** Fee and `pending_payment` fields exist; no payment gateway is integrated.
- **`isEventOrganizer()` in `config.php` queries an `organizer_id` column**, while the `events` table uses `created_by`. Align them before relying on that helper.

## Documentation

The assignment report is included in the repository: [`INDIVIDUAL WEB ENGINEERING.pdf`](<INDIVIDUAL WEB ENGINEERING.pdf>).

## Author

**Abigail Alau R. Sadom**
Bachelor of Computer Science (Software Engineering), Universiti Malaysia Sabah
GitHub: [@abigailsadom1903](https://github.com/abigailsadom1903)
