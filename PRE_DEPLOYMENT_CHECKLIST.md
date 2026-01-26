# Pre-Deployment Checklist

## Database Migrations
- [ ] All three migration files exist:
  - [ ] `001_create_password_resets_table.sql`
  - [ ] `002_add_header_footer_to_email_templates.sql`
  - [ ] `003_update_email_templates_with_headers_footers.sql`
- [ ] Migrations have been executed on target database
- [ ] Verify columns exist: `email_templates.header` and `email_templates.footer`

## File Updates
- [ ] `/models/EmailTemplate.php` - Updated for header/footer
- [ ] `/models/SiteSettings.php` - Verified working
- [ ] `/controllers/MessagingController.php` - Integrated with templates
- [ ] `/controllers/SettingsController.php` - Verified working
- [ ] `/views/settings/email_template_form.php` - Rewritten with WYSIWYG
- [ ] `/views/settings/email_templates.php` - "Create" button removed
- [ ] `/views/settings/site_settings.php` - Simplified to 4 fields
- [ ] `/includes/sidebar.php` - Loads database settings
- [ ] `/index.php` - Passes $pdo to MessagingController

## Configuration
- [ ] Database credentials verified
- [ ] SMTP settings configured
- [ ] File permissions correct (755 for files, 775 for directories)
- [ ] .htaccess configured properly
- [ ] Error logging enabled

## Testing - Site Settings
- [ ] Change site name → verify updates immediately in sidebar
- [ ] Change logo path → verify preview updates in real-time
- [ ] Save changes → verify persist after page refresh
- [ ] Test on multiple pages → branding consistent

## Testing - Email Templates
- [ ] Access template editor (Settings > Email Templates > Edit)
- [ ] Verify WYSIWYG editor loads (TinyMCE toolbar visible)
- [ ] Edit header section → add test content
- [ ] Edit body section → apply formatting (bold, italic, list)
- [ ] Edit footer section → add test content
- [ ] Save template → page refreshes successfully
- [ ] Reload template → verify all changes persisted

## Testing - Email Sending
- [ ] Send test message → email received
- [ ] Check email subject uses template subject
- [ ] Check email contains header HTML (if added)
- [ ] Check email contains body content
- [ ] Check email contains footer HTML (if added)
- [ ] Test variable substitution:
  - [ ] {sender_name} replaced with actual name
  - [ ] {subject} replaced with message subject
  - [ ] {thread_link} replaced with actual link

## Testing - Fallback Behavior
- [ ] Set template status to "Inactive"
- [ ] Send email → system falls back gracefully
- [ ] Check email logs for fallback message
- [ ] System doesn't crash

## Performance
- [ ] Page load times acceptable
- [ ] No N+1 database queries
- [ ] Settings cached properly
- [ ] Email sending completes quickly

## Security
- [ ] Admin-only access to settings
- [ ] Admin-only access to email templates
- [ ] HTML properly escaped in display
- [ ] Variables properly validated
- [ ] SQL injection prevention verified
- [ ] XSS prevention verified

## Browser Compatibility
- [ ] Chrome - WYSIWYG editor works
- [ ] Firefox - Form submission works
- [ ] Safari - Logo preview works
- [ ] Edge - All features functional

## Documentation
- [ ] Updated documentation exists
- [ ] Testing guide accessible
- [ ] Quick reference available
- [ ] Code comments accurate
- [ ] README updated (if applicable)

## Monitoring
- [ ] Error logs monitored
- [ ] Email queue monitored
- [ ] Database performance monitored
- [ ] No PHP warnings in logs
- [ ] No JavaScript errors in console

## Rollback Plan
- [ ] Database backup taken
- [ ] Previous version backed up
- [ ] Rollback procedure documented
- [ ] Team notified of changes

## Post-Deployment
- [ ] Monitor for 24-48 hours
- [ ] Check error logs regularly
- [ ] Verify email delivery ongoing
- [ ] User feedback collected
- [ ] Performance metrics reviewed

---

## Sign-Off

**Deployer Name:** ________________
**Deployment Date:** ________________
**Deployment Time:** ________________
**Environment:** [ ] Development [ ] Staging [ ] Production

**Pre-Deployment Checklist:** [ ] All items completed
**Testing Results:** [ ] All tests passed
**Monitoring:** [ ] Active

**Approved by:** ________________ Date: ________________

---

## Post-Deployment Notes

```
[Space for noting any issues or observations during/after deployment]




```

## Rollback If Needed

If issues occur:

1. **Database Rollback (if needed):**
   ```sql
   -- Run if reverting email_templates changes
   ALTER TABLE email_templates DROP COLUMN header;
   ALTER TABLE email_templates DROP COLUMN footer;
   ```

2. **File Rollback:**
   ```bash
   # Restore previous versions from backup
   git checkout HEAD -- [affected files]
   ```

3. **Clear Cache:**
   ```php
   // Clear application cache if any
   ```

4. **Verify Functionality:**
   - Test messaging system
   - Test settings display
   - Check error logs

---

## Additional Notes

- Document any custom configurations
- Note any third-party dependencies added
- Record any workarounds implemented
- Update team on changes made
