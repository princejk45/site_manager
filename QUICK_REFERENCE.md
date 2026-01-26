# Quick Reference: Site Settings & Email Templates

## Quick Start

### Get Site Settings
```php
$settings = new SiteSettings($pdo);
$siteName = $settings->getSetting('site_name', 'Default Name');
$logoPath = $settings->getSetting('logo_path', 'assets/images/logo.png');
```

### Get and Render Email Template
```php
$template = new EmailTemplate($pdo);

// Get template by slug
$emailTemplate = $template->getBySlug('message_notification');

// Render with variables
$rendered = $template->renderTemplate('message_notification', [
    'sender_name' => 'John Doe',
    'subject' => 'Project Update',
    'content' => 'Message body...',
    'thread_link' => 'http://example.com/messages/123',
    'domain' => 'example.com'
]);

// Use rendered content
$subject = $rendered['subject'];      // With variables replaced
$emailBody = $rendered['html'];       // Complete HTML (header + body + footer)
$header = $rendered['header'];        // Just header
$body = $rendered['body'];            // Just body
$footer = $rendered['footer'];        // Just footer
```

### Send Email with Template
```php
$template = new EmailTemplate($pdo);
$rendered = $template->renderTemplate('message_notification', $variables);

if ($rendered) {
    $mail->Subject = $rendered['subject'];
    $mail->Body = $rendered['html'];
    $mail->isHTML(true);
    $mail->send();
}
```

## Available Site Settings

| Key | Description | Example |
|-----|-------------|---------|
| `site_name` | Site display name | "Fullmidia Web" |
| `logo_path` | Path to logo image | "assets/images/logo.png" |
| `company_name` | Company legal name | "Fullmidia" |
| `company_email` | Contact email | "info@fullmidia.it" |

**Note:** Previously included `site_slogan`, `favicon_path`, `company_address`, `company_phone`, and color settings have been removed as they were not actively used in the application.

## Available Email Templates

| Slug | Name | Purpose | Variables |
|------|------|---------|-----------|
| `message_notification` | Message Notification | New message alert | sender_name, subject, content, thread_link, domain |
| `website_expiry` | Website Expiry Notification | Domain expiration notice | domain, days, status_content, new_expiry |
| `user_welcome` | User Welcome | Account creation | username, email, setup_link, domain |
| `password_reset` | Password Reset | Password reset request | username, reset_link, domain |

## Template Structure

Each template now includes:
- **Header** (optional) - HTML displayed at top of email
- **Subject** - Email subject line (with variables)
- **Body** - Main email content (with variables)
- **Footer** (optional) - HTML displayed at bottom of email

Variables in `{}` are replaced with actual values:
```
Subject: New message from {sender_name} about {subject}
Body: Hi there! You have received a message from {sender_name}...
Footer: © 2024 {domain}. All rights reserved.
```

## Rendered Template Output

The `renderTemplate()` method returns an array:
```php
[
    'subject' => 'Subject with variables replaced',
    'header' => 'Rendered header HTML or empty string',
    'body' => 'Rendered body HTML',
    'footer' => 'Rendered footer HTML or empty string',
    'html' => 'Complete email HTML (header + body + footer)',
    'original' => [original template data from database]
]
```

Use `$rendered['html']` for PHPMailer body to include header and footer.

- `{subject}` - Message subject
- `{content}` - Message body

## API Reference

### SiteSettings Model

```php
// Get single setting
$value = $settings->getSetting($key, $default = null);

// Get multiple settings
$values = $settings->getMultiple(['key1', 'key2']);

// Get all settings
$all = $settings->getAllSettings();

// Update setting
$settings->updateSetting($key, $value, $description = null);

// Delete setting
$settings->deleteSetting($key);

// Clear cache
SiteSettings::clearCache();
```

### EmailTemplate Model

```php
// Get template by slug
$template = $emailTemplate->getBySlug($slug);

// Get template by ID
$template = $emailTemplate->getById($id);

// List all templates
$templates = $emailTemplate->getAll($activeOnly = true);

// Get active templates only
$active = $emailTemplate->getAll(true);

// Get all including inactive
$all = $emailTemplate->getAll(false);

// Create template
$emailTemplate->create([
    'name' => 'My Template',
    'slug' => 'my_template',
    'subject' => 'Subject line',
    'body' => '<h2>Body</h2>',
    'description' => 'Description',
    'status' => 'active'
]);

// Update template
$emailTemplate->update($id, [
    'name' => 'Updated name',
    'subject' => 'New subject',
    'body' => 'New body',
    'status' => 'active'
]);

// Delete template
$emailTemplate->delete($id);

// Render template with variables
$rendered = $emailTemplate->renderTemplate($slug, [
    'domain' => 'example.com',
    'days' => 15
]);
// Returns: ['subject' => 'rendered subject', 'body' => 'rendered HTML', 'original' => $template]

// Duplicate template
$emailTemplate->duplicate($id, $newName = null);
```

## Usage Examples

### Example 1: Show Site Name in Header
```php
<?php
$settings = new SiteSettings($GLOBALS['pdo']);
$siteName = $settings->getSetting('site_name', 'Web Manager');
?>
<h1><?= htmlspecialchars($siteName) ?></h1>
```

### Example 2: Send Email with Template
```php
<?php
$template = new EmailTemplate($GLOBALS['pdo']);
$rendered = $template->renderTemplate('website_expiry', [
    'domain' => 'example.com',
    'days' => 30
]);

$mailer->Subject = $rendered['subject'];
$mailer->Body = $rendered['body'];
// ... send email
?>
```

### Example 3: Get Multiple Company Info
```php
<?php
$settings = new SiteSettings($GLOBALS['pdo']);
$company = $settings->getMultiple([
    'company_name',
    'company_email',
    'company_phone'
]);

echo $company['company_name']; // Fullmidia
echo $company['company_email']; // info@fullmidia.it
?>
```

### Example 4: Cache Performance
```php
<?php
$settings = new SiteSettings($GLOBALS['pdo']);

// First call - queries database
$name = $settings->getSetting('site_name');

// Subsequent calls - returns from cache (no DB query)
$name = $settings->getSetting('site_name');
$name = $settings->getSetting('site_name');

// After making changes somewhere, clear cache
SiteSettings::clearCache();

// Next call queries database again
$name = $settings->getSetting('site_name');
?>
```

### Example 5: Conditional Template Selection
```php
<?php
$template = new EmailTemplate($GLOBALS['pdo']);

$slug = $type === 'expiry' ? 'website_expiry' : 'website_status';
$rendered = $template->renderTemplate($slug, $data);

if (!$rendered) {
    error_log("Template not found: $slug");
    return false;
}

echo $rendered['subject'];
echo $rendered['body'];
?>
```

## Integration Points

### In Controllers
```php
require APP_PATH . '/models/SiteSettings.php';
require APP_PATH . '/models/EmailTemplate.php';

$settings = new SiteSettings($pdo);
$templates = new EmailTemplate($pdo);
```

### In Views
```php
<?php
if (!isset($siteSettings)) {
    require APP_PATH . '/models/SiteSettings.php';
    $siteSettings = new SiteSettings($GLOBALS['pdo']);
}

echo $siteSettings->getSetting('site_name');
?>
```

## Database Queries

### Check if Setting Exists
```sql
SELECT COUNT(*) FROM site_settings WHERE setting_key = 'site_name';
```

### Update All Settings
```sql
UPDATE site_settings SET setting_value = 'new_value' WHERE setting_key LIKE 'company_%';
```

### Get Active Templates
```sql
SELECT * FROM email_templates WHERE status = 'active' ORDER BY name;
```

### Find Templates with Variable
```sql
SELECT * FROM email_templates WHERE body LIKE '%{domain}%';
```

## Performance Tips

1. **Use getMultiple() for multiple settings**
   - Instead of calling getSetting() 5 times, use `getMultiple(['key1', 'key2', ...])`

2. **Cache static data in controller**
   - Create settings once, pass to view instead of creating in each view

3. **Check template status**
   - Always verify `status == 'active'` before using templates

4. **Clear cache after updates**
   - Call `SiteSettings::clearCache()` after updating settings

## Debugging

### Log Settings
```php
error_log(print_r($settings->getAllSettings(), true));
```

### Check Template
```php
$t = $template->getBySlug('website_expiry');
error_log(json_encode($t));
```

### Verify Connection
```php
try {
    $test = $settings->getSetting('site_name');
    echo "Database OK";
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage();
}
```

## Common Errors

| Error | Solution |
|-------|----------|
| Settings null | Check setting_key exists in database |
| Template empty | Verify template slug and status='active' |
| Cache issue | Call SiteSettings::clearCache() |
| Database error | Check PDO connection in bootstrap.php |
| Model not found | Verify autoloader in bootstrap.php |

## File Locations

```
models/SiteSettings.php ......... Site settings model
models/EmailTemplate.php ........ Email templates model
controllers/SettingsController.php Settings controller (3 methods added)
views/settings/site_settings.php. Settings form view
views/settings/email_templates.php Templates list view
views/settings/email_template_form.php Template editor view
migrations/002_* ............... Database migration
```

---

**For detailed documentation, see:**
- INTEGRATION_GUIDE.md - Full integration examples
- SETTINGS_USER_GUIDE.md - User documentation
- SETTINGS_IMPLEMENTATION.md - Technical reference
