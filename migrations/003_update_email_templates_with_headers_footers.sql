-- Update email templates with header and footer support

-- Update message_notification template to include header and footer
UPDATE email_templates SET 
  header = '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-bottom: 1px solid #dee2e6;">
    <h1 style="color: #333; margin: 0; font-size: 24px;">Message Notification</h1>
  </div>',
  footer = '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; font-size: 12px; color: #666;">
    <p>This is an automated message. Please do not reply to this email.</p>
    <p>&copy; 2024 Site Manager. All rights reserved.</p>
  </div>'
WHERE slug = 'message_notification';

-- Update website_expiry template to include header and footer  
UPDATE email_templates SET 
  header = '<div style="background-color: #fff3cd; padding: 20px; text-align: center; border-bottom: 1px solid #ffc107;">
    <h1 style="color: #856404; margin: 0; font-size: 24px;">⚠️ Domain Expiry Notice</h1>
  </div>',
  footer = '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; font-size: 12px; color: #666;">
    <p>This is an automated message. Please take action to renew your domain.</p>
    <p>&copy; 2024 Site Manager. All rights reserved.</p>
  </div>'
WHERE slug = 'website_expiry';

-- Update user_welcome template to include header and footer
UPDATE email_templates SET 
  header = '<div style="background-color: #d4edda; padding: 20px; text-align: center; border-bottom: 1px solid #28a745;">
    <h1 style="color: #155724; margin: 0; font-size: 24px;">Welcome!</h1>
  </div>',
  footer = '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; font-size: 12px; color: #666;">
    <p>If you have any questions, please contact support.</p>
    <p>&copy; 2024 Site Manager. All rights reserved.</p>
  </div>'
WHERE slug = 'user_welcome';

-- Update password_reset template to include header and footer
UPDATE email_templates SET 
  header = '<div style="background-color: #e7d4f5; padding: 20px; text-align: center; border-bottom: 1px solid #6c63ff;">
    <h1 style="color: #4c20d9; margin: 0; font-size: 24px;">Password Reset Request</h1>
  </div>',
  footer = '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; font-size: 12px; color: #666;">
    <p>If you did not request this password reset, please ignore this email.</p>
    <p>&copy; 2024 Site Manager. All rights reserved.</p>
  </div>'
WHERE slug = 'password_reset';
