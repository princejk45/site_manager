Website Management System workflow

1. Login
2. Manage websites: this page allows users to import data from an xls or excel sheet in bulk for the table containing website name, hosting server, email server, expiry date, site status. The table will additionally contain an edit button, send mail button and delete button. The page would also contain a separate button to export the table into excel file.
3. The send mail button will open a page that would prompt: send expiry reminder for expiry dates within 2 months and also send site status.
4. The edit button will allow to edit the site credentials.
5. A settings page for smtp using phpmailer credentials.
6. Admin password change page.
7. Manage hosting page: all the hosting servers and the expiry date can be computed here and assigned to domains. This means that the domain can only be assigned to existing hosting plans where necessary.
   The entire idea is to document domain and hosting plans whereby if a domain has a hosting, it is assigned to the hosting. Therefore by the help of cron job, a domain that is soon to expire will send an automatic email to the assigned email of that domain, one month, two weeks and a day before expiration if the status of the domain is expiring soon. There will be 3 statuses; Active, expiring soon and expired. And the admin can be able to change the status by editing the expiry date of the site in the table. Also he can use the send email button to prompt a notification at anytime to the assigned email.

This is the structure.

/site_manager/
├── .htaccess
├── assets/
│ ├── css/
│ │ └── styles.css
│ ├── js/
│ │ └── scripts.js
│ └── images/
├── config/
│ ├── auth.php
│ ├── bootstrap.php
│ ├── constants.php
| ├── database.php
│ └── mailer.php
├── controllers/
│ ├── AuthController.php
│ ├── DashboardController.php
│ ├── EmailController.php
│ ├── HostingController.php
│ ├── SettingsController.php
│ └── WebsiteController.php
├── models/
│ ├── Email.php
│ ├── Hosting.php
│ ├── User.php
│ └── Website.php
├── views/
│ ├── auth/
│ │ └── login.php
│ ├── dashboard/
│ │ └── index.php
│ ├── errors/
│ │ └── 404.php
│ ├── hosting/
│ │ ├── index.php
│ │ └── create.php
│ ├── settings/
│ │ ├── password.php
│ │ └── smtp.php
│ └── websites/
│ ├── index.php
│ └── form.php
├── includes/
│ ├── footer.php
│ ├── header.php
│ └── sidebar.php
├── cron/
│ ├── expiry_checker.php
│ └── status_updater.php
└── index.php

CRON JOB:

# Run daily at 9 AM

0 9 \* \* \* /usr/bin/php /path/to/your/site_manager/cron/expiry_notifier.php

DB STRUCTURE

-- Database: website_manager

-- Users table for admin accounts
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) NOT NULL UNIQUE,
email VARCHAR(100) NOT NULL UNIQUE,
password_hash VARCHAR(255) NOT NULL,
last_login DATETIME,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hosting plans table
CREATE TABLE hosting_plans (
id INT AUTO_INCREMENT PRIMARY KEY,
server_name VARCHAR(255) NOT NULL,
provider VARCHAR(255),
email_address VARCHAR(100),
ip_address VARCHAR(45),
expiry_date DATE,
renewal_cost DECIMAL(10,2),
notes TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Websites table
CREATE TABLE websites (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(255) NOT NULL,
domain VARCHAR(255) NOT NULL UNIQUE,
hosting_id INT,
email_server VARCHAR(255),
expiry_date DATE NOT NULL,
status ENUM('active', 'expiring_soon', 'expired') DEFAULT 'active',
assigned_email VARCHAR(255) NOT NULL,
notes TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (hosting_id) REFERENCES hosting_plans(id) ON DELETE SET NULL
);

-- SMTP settings table
CREATE TABLE smtp_settings (
id INT AUTO_INCREMENT PRIMARY KEY,
host VARCHAR(255) NOT NULL,
port INT NOT NULL,
username VARCHAR(255) NOT NULL,
password VARCHAR(255) NOT NULL,
encryption VARCHAR(10) DEFAULT 'tls',
from_email VARCHAR(255) NOT NULL,
from_name VARCHAR(255) NOT NULL,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email logs table
CREATE TABLE email_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
website_id INT,
email_type ENUM('expiry_reminder', 'status_notification', 'manual'),
sent_to VARCHAR(255) NOT NULL,
subject VARCHAR(255) NOT NULL,
body TEXT,
sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
status ENUM('sent', 'failed') NOT NULL,
error_message TEXT,
FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- Audit logs table
CREATE TABLE audit_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT,
action VARCHAR(50) NOT NULL,
entity_type VARCHAR(50) NOT NULL,
entity_id INT,
old_values TEXT,
new_values TEXT,
ip_address VARCHAR(45),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
