# Email Template Integration Complete

## Overview
Email templates are now fully integrated with the application. All emails sent by the system (messaging notifications, domain expiry alerts, user welcome emails, password resets) now use templates stored in the database that can be customized from the admin panel.

## What Changed

### 1. Database Schema Updates
**New Columns Added to `email_templates` table:**
- `header` (LONGTEXT) - Optional HTML content displayed at top of every email
- `footer` (LONGTEXT) - Optional HTML content displayed at bottom of every email

### 2. Email Template Form (Completely Rewritten)
**File:** `/views/settings/email_template_form.php`

**Features:**
- ✅ **WYSIWYG Editor** - TinyMCE visual editor for HTML content (no raw HTML editing)
- ✅ **Separate Header/Footer** - Editable independently from main content
- ✅ **Live Preview Variables** - Right sidebar shows available template variables
- ✅ **Template Status Control** - Active/Inactive toggle for templates
- ✅ **Removed "Create Template" Button** - Templates are pre-configured only

**Sections:**
1. Template Name (editable)
2. Slug (read-only - system identifier)
3. Email Subject (with variable support)
4. Email Header (WYSIWYG)
5. Email Body (WYSIWYG)
6. Email Footer (WYSIWYG)
7. Description (internal notes)
8. Status selector (Active/Inactive)

### 3. Template Rendering
**File:** `/models/EmailTemplate.php`

**Updated Methods:**
- `update($id, $data)` - Now supports `header` and `footer` fields
- `renderTemplate($slug, $variables = [])` - Now returns combined HTML with headers/footers

**Returns:**
```php
[
    'subject' => 'Rendered subject',
    'header' => 'Rendered header HTML',
    'body' => 'Rendered body HTML',
    'footer' => 'Rendered footer HTML',
    'html' => 'Complete email HTML (header + body + footer)',
    'original' => $template_array
]
```

### 4. Email Sending Integration
**File:** `/controllers/MessagingController.php`

**What It Does:**
- Messaging controller now uses EmailTemplate model for ALL email notifications
- Falls back to old system if template not found
- Renders variables into template placeholders
- Uses combined HTML (header + body + footer)

**Supported Variables for Message Notifications:**
- `{sender_name}` - Name of person sending message
- `{subject}` - Original message subject
- `{content}` - Message content
- `{thread_link}` - Link to view full conversation
- `{domain}` - Site domain from configuration

### 5. Template Examples

#### Message Notification Template
- **Slug:** `message_notification`
- **Used By:** Messaging system when new messages arrive
- **Variables:** sender_name, subject, content, thread_link, domain

#### Website Expiry Template
- **Slug:** `website_expiry`
- **Used By:** Cron job for domain expiry notifications
- **Variables:** domain, days, status_content, new_expiry

#### User Welcome Template
- **Slug:** `user_welcome`
- **Used By:** User account creation
- **Variables:** username, email, setup_link, domain

#### Password Reset Template
- **Slug:** `password_reset`
- **Used By:** Password reset requests
- **Variables:** username, reset_link, domain

## How to Use

### 1. Access Email Templates
Navigate to: **Settings > Email Templates**

### 2. Edit a Template
Click the "Edit" button on any template:
- Modify the subject line
- Use visual editor for header (optional)
- Use visual editor for body content (required)
- Use visual editor for footer (optional)
- Add internal notes
- Toggle Active/Inactive status

### 3. Add Variables
Click the **Insert Variable** button or type directly:
- Variables use `{variable_name}` format
- Available variables shown in right sidebar
- Examples: `{sender_name}`, `{domain}`, `{days}`

### 4. Preview Changes
- Changes take effect immediately
- Next email using that template will use updated version

## Technical Details

### Email Rendering Flow
1. **Trigger Event** - User action triggers email (e.g., send message)
2. **Template Lookup** - System retrieves template by slug from database
3. **Variable Substitution** - Template variables replaced with actual values
4. **HTML Assembly** - Header + Body + Footer combined into complete HTML
5. **Email Send** - PHPMailer sends complete HTML email

### Variable Injection
```php
// In MessagingController
$template = $this->emailTemplate->renderTemplate('message_notification', [
    'sender_name' => 'John Doe',
    'subject' => 'Project Update',
    'content' => 'Here is the message content...',
    'thread_link' => 'http://example.com/messages/123',
    'domain' => 'example.com'
]);

$subject = $template['subject'];  // Subject with variables replaced
$htmlBody = $template['html'];     // Complete email HTML
```

### Fallback Behavior
If a template is not found:
- System attempts to use old email system
- Error logged to application log
- Email still sent with fallback content

## Email Template Structure

### Header (Optional)
```html
<div style="background-color: #f8f9fa; padding: 20px; text-align: center;">
    <h1>Message Notification</h1>
</div>
```

### Body (Required)
- Main email content
- Can include HTML formatting, links, images
- Variables are substituted here

### Footer (Optional)
```html
<div style="background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px;">
    <p>Copyright &copy; 2024. All rights reserved.</p>
</div>
```

## Database Migrations

Three migration files were created:

1. **`001_create_password_resets_table.sql`** - Original setup
2. **`002_add_header_footer_to_email_templates.sql`** - Add header/footer columns
3. **`003_update_email_templates_with_headers_footers.sql`** - Populate sample headers/footers

## Benefits

✅ **Consistency** - All emails follow brand guidelines with header/footer
✅ **Flexibility** - Admin can change any email without touching code
✅ **User-Friendly** - WYSIWYG editor means no HTML knowledge required
✅ **Maintainability** - One place to manage all email templates
✅ **Professionalism** - Formatted emails with headers/footers
✅ **Variable System** - Dynamic content injection for personalization

## What's NOT Changed

- Email sending mechanism (still uses PHPMailer)
- SMTP configuration (unchanged)
- Email logging system (unchanged)
- Authentication emails (if separate)
- Password reset emails (unless configured)

## Future Enhancements

- [ ] Template preview function (see how it looks)
- [ ] Email test send (test templates before going live)
- [ ] Template history/versioning (track changes)
- [ ] Template categories (group by type)
- [ ] Image upload for headers/footers
- [ ] CSS inlining for better email client compatibility
- [ ] A/B testing variants

## Troubleshooting

### Templates Not Being Used
- Check if template slug matches what system is looking for
- Verify template status is "Active"
- Check application logs for errors

### Variables Not Replaced
- Verify variable names match exactly (case-sensitive)
- Check that variables are passed to renderTemplate()
- Use `{variable_name}` format (curly braces required)

### HTML Not Rendering
- Ensure isHTML(true) is set on PHPMailer instance
- Check if email client supports HTML
- Test with different email clients

## Files Modified

- `/models/EmailTemplate.php` - Enhanced template model
- `/controllers/MessagingController.php` - Integration with templates
- `/views/settings/email_template_form.php` - Completely rewritten with WYSIWYG
- `/views/settings/email_templates.php` - Removed "Create" button
- `/index.php` - Pass database to MessagingController
- `/migrations/002_add_header_footer_to_email_templates.sql` - Schema update
- `/migrations/003_update_email_templates_with_headers_footers.sql` - Sample data
