# Testing Guide - Email Template Integration

## Pre-Testing Checklist

- [ ] All migration files have been executed
- [ ] Database columns added (header, footer)
- [ ] Web server is running
- [ ] You have admin account access
- [ ] Email system is configured in settings

---

## Test 1: Site Settings Reflection

**Objective:** Verify that site name and logo changes immediately reflect in sidebar

### Steps
1. Open **Settings > Site Settings** in admin panel
2. Change "Site Name" to something different (e.g., "My Test Site")
3. Click **Save Site Settings**
4. Refresh any page in the application
5. Check sidebar - site name should show your new value

**Expected Result:** ✅ Sidebar shows updated site name

### Steps (Logo Test)
1. Open **Settings > Site Settings**
2. In "Logo Path", enter a path like `assets/images/my-logo.png`
3. Watch the preview area - it should show the image (or broken image if file doesn't exist)
4. Click **Save Site Settings**
5. Go back and refresh - logo in sidebar should use new path

**Expected Result:** ✅ Logo preview updates as you type, sidebar uses new path

---

## Test 2: Email Template Visual Editor

**Objective:** Verify WYSIWYG editor works and saves HTML correctly

### Steps
1. Open **Settings > Email Templates**
2. Click **Edit** on any template (e.g., "Message Notification")
3. Verify you see the TinyMCE editor (visual editor with formatting toolbar)
4. Click in the "Email Content" section
5. Type some text and apply formatting:
   - Make text **bold** using toolbar
   - Make text *italic*
   - Create a list
   - Add a link
6. Click **Save Template**
7. Open the template again and verify formatting is preserved

**Expected Result:** ✅ WYSIWYG editor saves HTML, formatting preserved on reload

---

## Test 3: Header and Footer Editing

**Objective:** Verify header and footer fields save independently

### Steps
1. Open **Settings > Email Templates > Edit Message Notification**
2. Scroll to "Email Header (HTML)" section
3. Click in header field and add: `<p>This is a header</p>`
4. Scroll to "Email Footer (HTML)" section
5. Click in footer field and add: `<p>This is a footer</p>`
6. Verify both are filled in
7. Click **Save Template**
8. Open the template again
9. Verify header and footer still contain your text

**Expected Result:** ✅ Header and footer saved and retrieved correctly

---

## Test 4: Template Variable System

**Objective:** Verify template variables are documented and available

### Steps
1. Open **Settings > Email Templates > Edit Message Notification**
2. Look at right sidebar under "Template Guide"
3. Verify you see "Available Variables:" list including:
   - {sender_name}
   - {subject}
   - {content}
   - {thread_link}
   - {domain}
4. Click in "Email Content" field
5. Add text: `Message from {sender_name} about {subject}`
6. Click **Save Template**

**Expected Result:** ✅ Variables documented and placeholders are visible in form

---

## Test 5: Email Sending Uses Templates

**Objective:** Verify that when emails are sent, they actually use template content

### Steps
1. **Setup:**
   - Ensure SMTP is configured in settings
   - Have two test user accounts
   
2. **Send a Message:**
   - Login as User A
   - Go to Messaging > Compose
   - Send message to User B
   
3. **Check Email:**
   - Login to User B's email (or check spam folder)
   - Look for "New message:" email
   - Check if email has:
     - Subject from template
     - Header content (if you added in Test 3)
     - Message content
     - Footer content (if you added in Test 3)
     - Proper formatting

**Expected Result:** ✅ Email received contains template structure with variables replaced

---

## Test 6: Template Status Control

**Objective:** Verify Active/Inactive status works

### Steps
1. Open **Settings > Email Templates > Edit any template**
2. Change "Status" to "Inactive"
3. Click **Save Template**
4. Send a message (trigger that email type)
5. Check if email sends with fallback or not

**Expected Result:** ✅ Inactive templates don't prevent emails, use fallback system

---

## Test 7: No "Create Template" Button

**Objective:** Verify that template creation UI was removed

### Steps
1. Open **Settings > Email Templates**
2. Look at the top of the list (card header)
3. Verify there is NO "Create Template" or "Add Template" button
4. Only list of existing templates should be visible

**Expected Result:** ✅ No create button visible, only edit buttons

---

## Test 8: Database Columns Verification

**Objective:** Verify database schema has the new columns

### Steps
1. Use phpMyAdmin or MySQL client
2. Navigate to `email_templates` table
3. Check columns exist:
   - `header` (LONGTEXT)
   - `footer` (LONGTEXT)
4. Verify they are NULL by default

**Expected Result:** ✅ Columns exist with correct types

---

## Test 9: Sidebar Integration

**Objective:** Verify sidebar properly loads database settings

### Steps
1. Check `/includes/sidebar.php` includes:
   ```php
   require_once APP_PATH . '/models/SiteSettings.php';
   $siteSettings = new SiteSettings($GLOBALS['pdo']);
   ```
2. Verify variables are used:
   - `$siteName = $siteSettings->getSetting('site_name', ...)`
   - `$logoPath = $siteSettings->getSetting('logo_path', ...)`
3. Check sidebar HTML uses variables:
   - `<?= htmlspecialchars($siteName) ?>`
   - `<?= htmlspecialchars($logoPath) ?>`

**Expected Result:** ✅ Sidebar loads from database with proper escaping

---

## Test 10: MessagingController Integration

**Objective:** Verify messaging controller uses EmailTemplate

### Steps
1. Check `/controllers/MessagingController.php` has:
   ```php
   require_once APP_PATH . '/models/EmailTemplate.php';
   $this->emailTemplate = new EmailTemplate($db);
   ```
2. Verify `sendEmailNotifications()` method:
   - Calls `$this->emailTemplate->renderTemplate()`
   - Uses returned template data
   - Falls back gracefully if template not found

**Expected Result:** ✅ Code properly integrated

---

## Troubleshooting

### WYSIWYG Editor Not Appearing
- Check browser console for JavaScript errors
- Verify TinyMCE CDN is accessible (https://cdn.tiny.cloud)
- Clear browser cache and reload

### Templates Not Saving
- Check database user has UPDATE permissions
- Verify all required fields are filled
- Check PHP error logs

### Sidebar Not Updating
- Clear browser cache
- Check database connection in bootstrap.php
- Verify SiteSettings model can read from database

### Emails Not Using Templates
- Check template slug matches what system is looking for
- Verify template status is "Active"
- Check email logs for errors
- Verify database connection passed to controller

---

## Success Criteria

✅ All tests pass
✅ No console errors
✅ No PHP warnings
✅ Site name/logo changes immediately visible
✅ Email templates render with proper formatting
✅ Headers/footers display in emails
✅ Variables substituted correctly
✅ System falls back gracefully if template missing

---

## Testing Reports

Use this format to document your testing:

```
Test Name: _________________
Date: ______________________
Tester: ____________________
Result: [ ] PASS [ ] FAIL
Issues: _____________________
______________________________
Notes: ______________________
______________________________
```

---

## Contact Support

If tests fail, check:
1. Migration files were executed
2. File permissions are correct
3. Database user has proper privileges
4. PHP error logs for detailed errors
5. Browser console for JavaScript errors
