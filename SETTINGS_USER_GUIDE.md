# Site Settings & Email Templates - User Guide

## Access Instructions

### How to Access Site Settings
1. Log in to the application as a **Super Admin**
2. In the sidebar, click on **Settings** (the gear icon dropdown)
3. You will see two new menu items:
   - **Site Settings** - For managing general site configuration
   - **Email Templates** - For managing email templates

### Site Settings Management

#### Available Settings

| Setting | Current Value | Purpose |
|---------|---------------|---------|
| Site Name | Fullmidia Web | Displayed in page header and browser title |
| Site Slogan | Gestione Siti Web e Hosting | Tagline for the application |
| Logo Path | assets/images/logo.png | Path to site logo image |
| Favicon Path | assets/images/favicon.png | Path to browser favicon |
| Company Name | Fullmidia | Official company name for emails/documents |
| Company Address | (empty) | Business address for signatures |
| Company Phone | (empty) | Contact phone number |
| Company Email | info@fullmidia.it | Primary contact email |
| Header BG Color | #1f2732 | Email header background color |
| Footer BG Color | #1f2732 | Email footer background color |
| Highlight Color | #f39200 | Brand accent color |

#### How to Edit Settings
1. Click **Site Settings** from the Settings menu
2. You will see a form with all 11 configuration fields
3. Make your desired changes
4. Click the **Save Settings** button at the bottom
5. You will see a success message confirming the update

#### Color Picker
For the three color settings (Header, Footer, Highlight), a color picker tool is provided:
- Click the color square to open the color picker
- Select your desired color
- The color code (hex value) will update automatically

### Email Templates Management

#### Available Templates
The system comes pre-configured with 4 email templates:

| # | Template Name | Slug | Purpose |
|---|---------------|------|---------|
| 1 | Website Expiry Notification | website_expiry | Sent when a domain is about to expire |
| 2 | Website Status Notification | website_status | Sent to report service status changes |
| 3 | Website Renewal Notification | website_renewal | Sent when a domain is successfully renewed |
| 4 | Message Notification | message_notification | Sent when users receive messages |

#### How to Edit a Template
1. Click **Email Templates** from the Settings menu
2. You will see a table listing all templates
3. Click the **Edit** (pencil) icon next to the template you want to modify
4. The template editor will open with:
   - **Template Name** - The display name (editable)
   - **Slug** - The system identifier (read-only)
   - **Subject** - Email subject line with variables
   - **Body** - Email HTML content
   - **Description** - Optional notes about the template
   - **Status** - Active or Inactive

#### Template Variables

Variables can be used in both the subject and body by using curly braces:

**Common Variables:**
- `{domain}` - Domain name (e.g., example.com)
- `{days}` - Number of days until expiration
- `{status_content}` - Service status details
- `{new_expiry}` - New expiration date
- `{subject}` - Message subject
- `{content}` - Message body content

**Example Subject:**
```
Domain Expiry Alert: {domain} expires in {days} days
```

**Example Body:**
```html
<h2>Important Notice</h2>
<p>Your domain <strong>{domain}</strong> will expire in <strong>{days}</strong> days.</p>
<p>Please renew as soon as possible to avoid service interruption.</p>
```

#### HTML Support
Email templates support full HTML formatting:
- `<h1>` to `<h6>` - Headings
- `<p>` - Paragraphs
- `<strong>` - Bold text
- `<em>` - Italic text
- `<br>` - Line breaks
- `<ul>`, `<li>` - Lists
- `<a href="">` - Links
- Any standard HTML tags

#### Saving Template Changes
1. Make your edits in the form fields
2. Click the **Save Template** button
3. You will be redirected to the templates list with a success message
4. Your changes take effect immediately

#### Template Status
- **Active** - Template will be used for sending emails
- **Inactive** - Template is disabled and won't be used

## Important Notes

### Security
- Only users with **Super Admin** role can access these settings
- All changes are logged with timestamps
- Settings are validated before saving

### Database Backup
Before making bulk changes to settings or templates, consider:
- Taking a database backup
- Testing changes in a development environment first

### Default Values
All settings are pre-populated with existing application defaults:
- Site configuration from `config/constants.php`
- Email templates from `models/Email.php`

You can restore to these defaults anytime by re-running the migration.

### Performance
Site settings are cached in memory for performance. Changes take effect immediately in most cases. If a change isn't reflected, try:
- Logging out and logging back in
- Refreshing the page
- Clearing your browser cache

## Future Enhancements

The following features are planned for future releases:
- Email template preview/test functionality
- Template duplication for quick setup
- Create new custom templates
- Delete templates with confirmation
- Search and filter functionality
- Bulk update operations
- Template history/version control

## Troubleshooting

**Q: I don't see Site Settings or Email Templates in my menu**
A: You need to be logged in as a Super Admin user. Check your user role in User Management.

**Q: My changes don't appear after saving**
A: Try:
1. Refreshing the page (Ctrl+R or Cmd+R)
2. Clearing your browser cache
3. Logging out and back in
4. Checking that the save message appeared

**Q: Can I delete templates?**
A: Currently, templates can be set to Inactive status. Full deletion functionality will be added in a future update.

**Q: What happens if I make a template Inactive?**
A: That template will no longer be used for sending emails, but it will still be stored in the database for reference.

---

**For more technical details, see: SETTINGS_IMPLEMENTATION.md**
