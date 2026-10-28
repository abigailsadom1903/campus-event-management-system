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
- [Database](#database)
- [Installation](#installation)
- [Security Notes](#security-notes)
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

## Database

Database name: `cemsindv`

| Table | Purpose |
| --- | --- |
| `users` | Accounts: name, email (unique), hashed password, role (`public`, `student`, `staff`), organisation, phone |
| `events` | Event details, mode, capacity, fee, registration deadline, status, creator |
| `event_registrations` | Which user registered for which event, with status (`registered`, `cancelled`, `pending_payment`) and a unique (event, email) constraint |
| `registrations` | Additional registration and payment-tracking table |

Events are linked to their creator, and registrations to both event and user, with cascading foreign keys.

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

## Documentation

The assignment report is included in the repository: [`INDIVIDUAL WEB ENGINEERING.pdf`](<INDIVIDUAL WEB ENGINEERING.pdf>).

## Author

**Abigail Alau R. Sadom**
Bachelor of Computer Science (Software Engineering), Universiti Malaysia Sabah
GitHub: [@abigailsadom1903](https://github.com/abigailsadom1903)
