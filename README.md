# Site Manager

**Version:** 1.0.0  
**Developer:** Princewill Okiriguo

A comprehensive website and domain management system built with PHP. This application provides complete management of websites, hosting information, user access control, email templates, and automated notifications.

## Overview

Site Manager is a multi-user web application designed to manage multiple websites and their associated hosting details. It provides role-based access control, customizable email templates, and automated notification systems for domain expiry and renewal management.

## Core Features

### Website Management
- Create, read, update, and delete website records
- Manage domain names and hosting information
- Track website expiry dates with automatic reminders
- Bulk operations for managing multiple websites
- Import and export website data
- Domain renewal tracking and notifications

### User Management
- Role-based access control with three permission levels:
  - Viewer: Read-only access to dashboard and websites
  - Manager: Full write access to website data
  - Admin: System-wide administrative control
- User authentication with secure password handling
- Password reset functionality via email
- User activity and access control

### Email System
- Customizable email templates with headers and footers
- WYSIWYG editor for template content
- Variable substitution in templates for dynamic content
- HTML email rendering with proper formatting
- Multiple email templates for different notifications
- Template management interface

### Site Settings
- Centralized configuration management
- Site branding (name, logo, email)
- Database-driven settings with caching
- Settings management interface for administrators

### Messaging and Notifications
- Email notification system for website expiry
- Automated cron jobs for scheduled notifications
- Thread-based messaging system
- Message groups for bulk communications
- Email delivery tracking

### Multi-Language Support
- Built-in language files for internationalization
- Support for English, French, and Italian
- Easy extension for additional languages
- Localized UI and notifications

### Import and Export
- Export website data to spreadsheet formats
- Import website data from CSV/Excel files
- Data validation during import
- Bulk data operations

### Google Sheets Integration
- Export data to Google Sheets
- Automated row formatting and styling
- Data synchronization capabilities

## System Requirements

- PHP 7.4 or higher
- SQLite or MySQL database
- Composer for dependency management
- Web server (Apache, Nginx, etc.)

## Dependencies

- PHPMailer/PHPMailer (^6.6) - Email sending
- PHPOffice/PHPSpreadsheet (^4.1) - Spreadsheet operations
- Google/APIClient (^2.0) - Google Sheets integration

## Installation

1. Clone the repository to your web server directory
2. Install dependencies using Composer:
   ```
   composer install
   ```
3. Configure your database connection in `config/database.php`
4. Run database migrations in the `migrations` folder
5. Set up environment variables in `.env`
6. Configure email settings in `config/mailer.php`

## Directory Structure

- `/config` - Application configuration and bootstrap
- `/controllers` - Request handlers and business logic
- `/models` - Data models and database interactions
- `/views` - User interface templates
- `/assets` - CSS, JavaScript, and image files
- `/lang` - Language files for localization
- `/migrations` - Database migration scripts
- `/cron` - Automated scheduled tasks
- `/logs` - Application logs
- `/uploads` - User file uploads
- `/vendor` - Composer dependencies

## Configuration

### Database
Configure your database connection in `config/database.php`. The system supports both SQLite and MySQL databases.

### Email Settings
Set up email configuration in `config/mailer.php` including:
- SMTP server details
- Email credentials
- Sender information

### Site Settings
Configure site-wide settings through the application interface:
- Site name and branding
- Logo path
- Default email address
- Language settings

## Database Models

The application uses the following main models:
- User - User accounts and authentication
- Website - Website information and metadata
- Hosting - Hosting provider and server details
- EmailTemplate - Customizable email templates
- Message - User messages and threads
- SiteSettings - Global application settings
- Email - Email sending functionality

## Controllers

- AuthController - Authentication and login management
- DashboardController - Dashboard and statistics
- WebsiteController - Website CRUD operations
- HostingController - Hosting information management
- EmailController - Email sending and templates
- SettingsController - Application settings
- MessagingController - Messages and notifications

## API Routes

The application uses a URL-based routing system with the following structure:
```
index.php?action={action}&do={subaction}&id={id}
```

Common routes:
- `?action=login` - User authentication
- `?action=dashboard` - Main dashboard
- `?action=websites` - Website list and management
- `?action=settings` - Application settings
- `?action=messaging` - Messages and notifications

## Security Features

- Role-based access control enforcement
- Session management and authentication
- SQL injection prevention through prepared statements
- Password hashing and secure storage
- CORS and security headers
- Input validation and sanitization

## Automation

Automated tasks are handled through cron jobs:
- Website expiry notifications
- Automatic renewal reminders
- Scheduled email sending
- Database maintenance

Run cron scripts from the `/cron` directory on a scheduled basis.

## Localization

The system supports multiple languages through language files in `/lang`:
- `en.php` - English
- `fr.php` - French
- `it.php` - Italian

To add a new language, create a new language file following the existing structure.

## Support and Development

For issues, feature requests, or contributions, refer to the project repository documentation and development guidelines.
