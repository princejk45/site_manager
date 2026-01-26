# Integration Guide: Using Site Settings & Email Templates

This guide shows how to integrate the new Site Settings and Email Templates system throughout the application.

## 1. Accessing Site Settings in Views/Controllers

### In Controllers
```php
<?php
require APP_PATH . '/models/SiteSettings.php';
$siteSettings = new SiteSettings($GLOBALS['pdo']);

// Get a single setting
$siteName = $siteSettings->getSetting('site_name');
$logoPath = $siteSettings->getSetting('logo_path');

// Get multiple settings at once
$settings = $siteSettings->getMultiple(['site_name', 'logo_path', 'company_email']);

// Get all settings
$allSettings = $siteSettings->getAllSettings();

// Use in output
echo $siteName; // "Fullmidia Web"
?>
```

### In Views (PHP files)
```php
<?php
// Initialize if not already done
if (!isset($siteSettings)) {
    require APP_PATH . '/models/SiteSettings.php';
    $siteSettings = new SiteSettings($GLOBALS['pdo']);
}

// Use in HTML
?>
<header>
    <h1><?= $siteSettings->getSetting('site_name') ?></h1>
    <p><?= $siteSettings->getSetting('site_slogan') ?></p>
    <img src="<?= $siteSettings->getSetting('logo_path') ?>" alt="Logo">
</header>
```

## 2. Using Email Templates

### Getting a Template
```php
<?php
require APP_PATH . '/models/EmailTemplate.php';
$emailTemplate = new EmailTemplate($GLOBALS['pdo']);

// Get template by slug
$template = $emailTemplate->getBySlug('website_expiry');
echo $template['subject']; // "Avviso di Scadenza - {domain}"
echo $template['body'];    // HTML content
?>
```

### Rendering with Variables
```php
<?php
$variables = [
    'domain' => 'example.com',
    'days' => 15,
];

$rendered = $emailTemplate->renderTemplate('website_expiry', $variables);
echo $rendered['subject']; // "Avviso di Scadenza - example.com"
echo $rendered['body'];    // HTML with variables substituted
?>
```

## 3. Replacing Hardcoded Values

### Example: Update includes/header.php
**Current (Hardcoded):**
```php
<?php
// OLD WAY - Hardcoded
?>
<a href="index.php" class="brand-link">
    <img src="assets/images/logo.png" alt="Logo" class="brand-image">
    <span class="brand-text">Fullmidia Web</span>
</a>
```

**New (Using Database):**
```php
<?php
// NEW WAY - From Database
require APP_PATH . '/models/SiteSettings.php';
$siteSettings = new SiteSettings($GLOBALS['pdo']);
?>
<a href="index.php" class="brand-link">
    <img src="<?= $siteSettings->getSetting('logo_path') ?>" alt="Logo" class="brand-image">
    <span class="brand-text"><?= $siteSettings->getSetting('site_name') ?></span>
</a>
```

### Example: Update Email.php getEmailTemplate()
**Current (Hardcoded HTML):**
```php
<?php
public function getEmailTemplate($domain, $days, $type = 'expiry') {
    $headerBgColor = '#1f2732';
    $footerBgColor = '#1f2732';
    $highlightColor = '#f39200';
    
    // ... hardcoded HTML template
    return $html;
}
?>
```

**New (Using Database):**
```php
<?php
public function getEmailTemplate($domain, $days, $templateSlug = 'website_expiry') {
    require_once APP_PATH . '/models/EmailTemplate.php';
    require_once APP_PATH . '/models/SiteSettings.php';
    
    $emailTemplate = new EmailTemplate($this->db);
    $siteSettings = new SiteSettings($this->db);
    
    // Get template and settings
    $template = $emailTemplate->renderTemplate($templateSlug, [
        'domain' => $domain,
        'days' => $days,
    ]);
    
    $headerBgColor = $siteSettings->getSetting('header_bg_color', '#1f2732');
    $footerBgColor = $siteSettings->getSetting('footer_bg_color', '#1f2732');
    $highlightColor = $siteSettings->getSetting('highlight_color', '#f39200');
    $companyName = $siteSettings->getSetting('company_name', 'Company');
    
    $html = "
    <html>
        <body style=\"background: #f5f5f5; font-family: Arial;\">
            <div style=\"background: {$headerBgColor}; color: white; padding: 20px; text-align: center;\">
                <h1>{$companyName}</h1>
            </div>
            <div style=\"background: white; padding: 20px; margin: 10px;\">
                {$template['body']}
            </div>
            <div style=\"background: {$footerBgColor}; color: white; padding: 20px; text-align: center;\">
                <p>&copy; " . date('Y') . " {$companyName}</p>
            </div>
        </body>
    </html>";
    
    return $html;
}
?>
```

## 4. Sending Emails with Templates

### Example: Update sendExpiryNotification()
**Before:**
```php
<?php
public function sendExpiryNotification($userEmail, $domain, $daysUntilExpiry) {
    $subject = "Avviso di Scadenza - $domain";
    $body = $this->getEmailTemplate($domain, $daysUntilExpiry, 'expiry');
    $this->sendEmail($userEmail, $subject, $body);
}
?>
```

**After:**
```php
<?php
public function sendExpiryNotification($userEmail, $domain, $daysUntilExpiry) {
    require_once APP_PATH . '/models/EmailTemplate.php';
    $emailTemplate = new EmailTemplate($this->db);
    
    $template = $emailTemplate->renderTemplate('website_expiry', [
        'domain' => $domain,
        'days' => $daysUntilExpiry,
    ]);
    
    $body = $this->getEmailTemplate($domain, $daysUntilExpiry, 'website_expiry');
    $this->sendEmail($userEmail, $template['subject'], $body);
}
?>
```

## 5. Available Database Settings Reference

### Site Settings Table
All values are stored as strings in `site_settings` table:

```
site_name           (string) - Site name in header
site_slogan         (string) - Tagline
logo_path           (string) - Path to logo image
favicon_path        (string) - Path to favicon
company_name        (string) - Company legal name
company_address     (string) - Business address
company_phone       (string) - Contact phone
company_email       (string) - Contact email
header_bg_color     (string) - Hex color code
footer_bg_color     (string) - Hex color code
highlight_color     (string) - Hex color code
```

### Email Templates Table
```
id              (int)           - Primary key
name            (varchar 100)   - Display name
slug            (varchar 100)   - System identifier (UNIQUE)
subject         (varchar 200)   - Email subject
body            (longtext)      - Email HTML body
description     (text)          - Internal notes
status          (enum)          - 'active' or 'inactive'
created_at      (timestamp)     - Creation date
updated_at      (timestamp)     - Last modified date
```

## 6. Performance Considerations

### Caching
The `SiteSettings` model includes in-memory caching:

```php
<?php
// First call - queries database
$name = $siteSettings->getSetting('site_name'); // DB query

// Subsequent calls - returns from cache
$name = $siteSettings->getSetting('site_name'); // No query
?>
```

### Clear Cache When Needed
```php
<?php
SiteSettings::clearCache(); // Clear all cached settings
?>
```

### Batch Retrieval
For better performance, get multiple settings at once:

```php
<?php
// BAD - Multiple queries
$name = $siteSettings->getSetting('site_name');
$email = $siteSettings->getSetting('company_email');
$phone = $siteSettings->getSetting('company_phone');

// GOOD - Single query
$company = $siteSettings->getMultiple([
    'site_name',
    'company_email',
    'company_phone'
]);
?>
```

## 7. Migration Strategy

### Phase 1: Setup (COMPLETE ✅)
- ✅ Create database tables
- ✅ Pre-populate with existing values
- ✅ Create models and controllers
- ✅ Create UI for management

### Phase 2: Integration (NEXT)
1. Update `includes/header.php` to use SiteSettings
2. Update `config/constants.php` to reference database
3. Update `Email.php` getEmailTemplate() method
4. Update all email sending methods

### Phase 3: Testing
1. Verify all pages load correctly
2. Test email sending with template variables
3. Verify settings persist across page reloads
4. Test performance with cache

### Phase 4: Deployment
1. Backup existing database
2. Run migration on production
3. Test on live server
4. Update documentation

## 8. Common Integration Patterns

### Pattern 1: Global Settings Helper Function
```php
<?php
// Add to config/bootstrap.php
function getSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        require_once APP_PATH . '/models/SiteSettings.php';
        $settings = new SiteSettings($GLOBALS['pdo']);
    }
    return $settings->getSetting($key, $default);
}

// Usage anywhere
echo getSetting('site_name'); // "Fullmidia Web"
?>
```

### Pattern 2: Email Template Helper
```php
<?php
// Add to models/Email.php or utility file
function getEmailTemplate($slug, $variables = []) {
    static $emailTemplate = null;
    if ($emailTemplate === null) {
        $emailTemplate = new EmailTemplate($GLOBALS['pdo']);
    }
    return $emailTemplate->renderTemplate($slug, $variables);
}

// Usage
$email = getEmailTemplate('website_expiry', [
    'domain' => 'example.com',
    'days' => 15
]);
?>
```

### Pattern 3: View Helper in Controller
```php
<?php
public function someAction() {
    $siteSettings = new SiteSettings($this->db);
    
    // Pass to view
    require APP_PATH . '/views/some_view.php';
}

// In view: $siteSettings->getSetting('site_name')
?>
```

## 9. Debugging

### Check if Settings Exist
```php
<?php
$setting = $siteSettings->getSetting('site_name');
if ($setting === null) {
    error_log("Setting not found: site_name");
}
?>
```

### Get All Settings for Inspection
```php
<?php
$allSettings = $siteSettings->getAllSettings();
var_dump($allSettings); // Debug output
?>
```

### Check Template Status
```php
<?php
$template = $emailTemplate->getBySlug('website_expiry');
if (!$template) {
    error_log("Template not found: website_expiry");
} elseif ($template['status'] !== 'active') {
    error_log("Template is inactive");
}
?>
```

---

## Summary

The new Site Settings and Email Templates system provides a flexible, database-driven configuration system that replaces hardcoded values throughout the application. Follow this guide to gradually migrate existing hardcoded content to use the new system while maintaining backward compatibility during the transition.

**Key Benefits:**
- ✅ No code changes needed to modify settings
- ✅ Settings persist across updates
- ✅ Email templates easily customizable
- ✅ Centralized configuration management
- ✅ Audit trail with timestamps
- ✅ Performance optimized with caching
