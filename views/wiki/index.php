<?php require_once APP_PATH . '/includes/header.php'; ?>
<?php require_once APP_PATH . '/includes/sidebar-v2.php'; ?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0"><?php echo __('wiki.page_title'); ?></h1>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <p class="text-muted mb-3"><?php echo __('wiki.page_subtitle'); ?></p>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-12">
          <div class="input-group">
            <input type="text" id="wikiSearch" class="form-control" placeholder="<?php echo __('wiki.search_placeholder'); ?>" oninput="wikiFilter(this.value)">
            <div class="input-group-append">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
          </div>
          <small class="text-muted"><?php echo __('wiki.tip'); ?> <?php echo __('wiki.tip_search'); ?></small>
        </div>
      </div>

      <div class="row">
        <!-- TOC Sidebar -->
        <div class="col-lg-3">
          <div class="wiki-toc-card sticky-top">
            <div class="wiki-card-header">
              <strong><?php echo __('wiki.contents'); ?></strong>
            </div>
            <div class="wiki-toc-scroll">
              <a href="#wiki-overview" class="wiki-toc-link"><?php echo __('wiki.section_getting_started'); ?></a>
              <a href="#wiki-roles" class="wiki-toc-link"><?php echo __('wiki.section_roles'); ?></a>
              <a href="#wiki-dashboard" class="wiki-toc-link"><?php echo __('wiki.section_dashboard'); ?></a>
              <a href="#wiki-websites" class="wiki-toc-link"><?php echo __('wiki.section_websites'); ?></a>
              <a href="#wiki-hosting" class="wiki-toc-link"><?php echo __('wiki.section_hosting'); ?></a>
              <a href="#wiki-hosting_acc" class="wiki-toc-link"><?php echo __('wiki.section_hosting_acc'); ?></a>
              <a href="#wiki-providers" class="wiki-toc-link"><?php echo __('wiki.section_providers'); ?></a>
              <a href="#wiki-diagnostics" class="wiki-toc-link"><?php echo __('wiki.section_diagnostics'); ?></a>
              <a href="#wiki-messaging" class="wiki-toc-link"><?php echo __('wiki.section_messaging'); ?></a>
              <a href="#wiki-comms" class="wiki-toc-link"><?php echo __('wiki.section_comms'); ?></a>
              <a href="#wiki-reports" class="wiki-toc-link"><?php echo __('wiki.section_reports'); ?></a>
              <a href="#wiki-automation" class="wiki-toc-link"><?php echo __('wiki.section_automation'); ?></a>
              <a href="#wiki-cron" class="wiki-toc-link"><?php echo __('wiki.section_cron'); ?></a>
              <a href="#wiki-import_export" class="wiki-toc-link"><?php echo __('wiki.section_import_export'); ?></a>
              <a href="#wiki-wordpress_int" class="wiki-toc-link"><?php echo __('wiki.section_wordpress_int'); ?></a>
              <a href="#wiki-smtp" class="wiki-toc-link"><?php echo __('wiki.section_email_setup'); ?></a>
              <a href="#wiki-site_settings" class="wiki-toc-link"><?php echo __('wiki.section_site_settings'); ?></a>
              <a href="#wiki-users" class="wiki-toc-link"><?php echo __('wiki.section_users'); ?></a>
              <a href="#wiki-license" class="wiki-toc-link"><?php echo __('wiki.section_license'); ?></a>
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
          <!-- Getting Started -->
          <div id="wiki-overview" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_getting_started'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.getting_started_intro'); ?></p>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.gs_track'); ?></li>
                <li><?php echo __('wiki.gs_alerts'); ?></li>
                <li><?php echo __('wiki.gs_monitor'); ?></li>
                <li><?php echo __('wiki.gs_messages'); ?></li>
                <li><?php echo __('wiki.gs_log'); ?></li>
                <li><?php echo __('wiki.gs_reports'); ?></li>
                <li><?php echo __('wiki.gs_automation'); ?></li>
                <li><?php echo __('wiki.gs_import'); ?></li>
              </ul>
            </div>
          </div>

          <!-- Roles -->
          <div id="wiki-roles" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_roles'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.roles_intro'); ?></p>
              <table class="wiki-table">
                <thead>
                  <tr>
                    <th><?php echo __('wiki.role_viewer'); ?></th>
                    <td><?php echo __('wiki.role_viewer_desc'); ?></td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th><?php echo __('wiki.role_manager'); ?></th>
                    <td><?php echo __('wiki.role_manager_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.role_admin'); ?></th>
                    <td><?php echo __('wiki.role_admin_desc'); ?></td>
                  </tr>
                </tbody>
              </table>
              <p class="mt-3"><small><?php echo __('wiki.role_permission_denied'); ?></small></p>
            </div>
          </div>

          <!-- Dashboard -->
          <div id="wiki-dashboard" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_dashboard'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.dashboard_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.dashboard_what_see'); ?></h6>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.dashboard_summary_cards'); ?></li>
                <li><?php echo __('wiki.dashboard_expiring'); ?></li>
                <li><?php echo __('wiki.dashboard_wp_warnings'); ?></li>
                <li><?php echo __('wiki.dashboard_notifications'); ?></li>
              </ul>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.dashboard_what_do'); ?></h6>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.dashboard_click_domain'); ?></li>
                <li><?php echo __('wiki.dashboard_click_eye'); ?></li>
                <li><?php echo __('wiki.dashboard_sidebar'); ?></li>
              </ul>
            </div>
          </div>

          <!-- Websites -->
          <div id="wiki-websites" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_websites'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.websites_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.websites_add_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.websites_add_1'); ?></li>
                <li><?php echo __('wiki.websites_add_2'); ?></li>
                <li><?php echo __('wiki.websites_add_3'); ?></li>
                <li><?php echo __('wiki.websites_add_4'); ?></li>
                <li><?php echo __('wiki.websites_add_5'); ?></li>
              </ol>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.websites_edit_heading'); ?></h6>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.websites_edit_pencil'); ?></li>
                <li><?php echo __('wiki.websites_edit_eye'); ?></li>
              </ul>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.websites_renew_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.websites_renew_1'); ?></li>
                <li><?php echo __('wiki.websites_renew_2'); ?></li>
              </ol>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.websites_status_heading'); ?></h6>
              <table class="wiki-table">
                <tbody>
                  <tr>
                    <th><?php echo __('wiki.websites_status_active'); ?></th>
                    <td><?php echo __('wiki.websites_status_active_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.websites_status_expiring'); ?></th>
                    <td><?php echo __('wiki.websites_status_expiring_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.websites_status_expired'); ?></th>
                    <td><?php echo __('wiki.websites_status_expired_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.websites_status_warning'); ?></th>
                    <td><?php echo __('wiki.websites_status_warning_desc'); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Hosting Clients -->
          <div id="wiki-hosting" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_hosting'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.hosting_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.hosting_add_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.hosting_add_1'); ?></li>
                <li><?php echo __('wiki.hosting_add_2'); ?></li>
                <li><?php echo __('wiki.hosting_add_3'); ?></li>
                <li><?php echo __('wiki.hosting_add_4'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.hosting_view'); ?></p>
            </div>
          </div>

          <!-- Hosting Accounts -->
          <div id="wiki-hosting_acc" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_hosting_acc'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.hosting_acc_intro'); ?></p>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.hosting_acc_1'); ?></li>
                <li><?php echo __('wiki.hosting_acc_2'); ?></li>
                <li><?php echo __('wiki.hosting_acc_3'); ?></li>
                <li><?php echo __('wiki.hosting_acc_4'); ?></li>
                <li><?php echo __('wiki.hosting_acc_5'); ?></li>
              </ol>
              <p class="mt-3"><small><?php echo __('wiki.hosting_acc_note'); ?></small></p>
            </div>
          </div>

          <!-- Providers -->
          <div id="wiki-providers" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_providers'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.providers_intro'); ?></p>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.providers_1'); ?></li>
                <li><?php echo __('wiki.providers_2'); ?></li>
                <li><?php echo __('wiki.providers_3'); ?></li>
                <li><?php echo __('wiki.providers_4'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.providers_toggle'); ?></p>
            </div>
          </div>

          <!-- Diagnostics -->
          <div id="wiki-diagnostics" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_diagnostics'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.diagnostics_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.diagnostics_score_heading'); ?></h6>
              <table class="wiki-table">
                <tbody>
                  <tr>
                    <th><?php echo __('wiki.diagnostics_grade_a'); ?></th>
                    <td><?php echo __('wiki.diagnostics_grade_a_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.diagnostics_grade_b'); ?></th>
                    <td><?php echo __('wiki.diagnostics_grade_b_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.diagnostics_grade_c'); ?></th>
                    <td><?php echo __('wiki.diagnostics_grade_c_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.diagnostics_grade_d'); ?></th>
                    <td><?php echo __('wiki.diagnostics_grade_d_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.diagnostics_grade_f'); ?></th>
                    <td><?php echo __('wiki.diagnostics_grade_f_desc'); ?></td>
                  </tr>
                </tbody>
              </table>
              <p class="mt-3"><small><?php echo __('wiki.diagnostics_score_only_after'); ?></small></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.diagnostics_checks_heading'); ?></h6>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.diagnostics_check_ssl'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_wp'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_plugins'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_wordfence'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_debug'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_backup'); ?></li>
                <li><?php echo __('wiki.diagnostics_check_speed'); ?></li>
              </ul>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.diagnostics_run_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.diagnostics_run_1'); ?></li>
                <li><?php echo __('wiki.diagnostics_run_2'); ?></li>
                <li><?php echo __('wiki.diagnostics_run_3'); ?></li>
              </ol>
              <p class="mt-3"><small><?php echo __('wiki.diagnostics_note'); ?></small></p>
            </div>
          </div>

          <!-- Messaging -->
          <div id="wiki-messaging" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_messaging'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.messaging_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.messaging_read_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.messaging_read_1'); ?></li>
                <li><?php echo __('wiki.messaging_read_2'); ?></li>
              </ol>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.messaging_compose_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.messaging_compose_1'); ?></li>
                <li><?php echo __('wiki.messaging_compose_2'); ?></li>
                <li><?php echo __('wiki.messaging_compose_3'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.messaging_groups'); ?></p>
            </div>
          </div>

          <!-- Communications -->
          <div id="wiki-comms" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_comms'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.comms_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.comms_log_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.comms_log_1'); ?></li>
                <li><?php echo __('wiki.comms_log_2'); ?></li>
                <li><?php echo __('wiki.comms_log_3'); ?></li>
                <li><?php echo __('wiki.comms_log_4'); ?></li>
                <li><?php echo __('wiki.comms_log_5'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.comms_history'); ?></p>
            </div>
          </div>

          <!-- Reports -->
          <div id="wiki-reports" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_reports'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.reports_intro'); ?></p>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.reports_1'); ?></li>
                <li><?php echo __('wiki.reports_2'); ?></li>
                <li><?php echo __('wiki.reports_3'); ?></li>
                <li><?php echo __('wiki.reports_4'); ?></li>
              </ol>
            </div>
          </div>

          <!-- Automation -->
          <div id="wiki-automation" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_automation'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.automation_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.automation_trigger_expiry'); ?></h6>
              <p><?php echo __('wiki.automation_trigger_expiry_desc'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.automation_trigger_health'); ?></h6>
              <p><?php echo __('wiki.automation_trigger_health_desc'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.automation_create_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.automation_create_1'); ?></li>
                <li><?php echo __('wiki.automation_create_2'); ?></li>
                <li><?php echo __('wiki.automation_create_3'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.automation_manage'); ?></p>
            </div>
          </div>

          <!-- Cron -->
          <div id="wiki-cron" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_cron'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.cron_intro'); ?></p>
              <table class="wiki-table">
                <tbody>
                  <tr>
                    <th><?php echo __('wiki.cron_job_expiry'); ?></th>
                    <td><?php echo __('wiki.cron_job_expiry_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.cron_job_wp'); ?></th>
                    <td><?php echo __('wiki.cron_job_wp_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.cron_job_sheets'); ?></th>
                    <td><?php echo __('wiki.cron_job_sheets_desc'); ?></td>
                  </tr>
                </tbody>
              </table>
              <p class="mt-3"><?php echo __('wiki.cron_manage'); ?></p>
              <p class="mt-3"><small><?php echo __('wiki.cron_note'); ?></small></p>
            </div>
          </div>

          <!-- Import Export -->
          <div id="wiki-import_export" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_import_export'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.import_export_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.import_export_export_heading'); ?></h6>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.import_export_export_hosting'); ?></li>
                <li><?php echo __('wiki.import_export_export_notifications'); ?></li>
              </ul>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.import_export_import_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.import_export_import_1'); ?></li>
                <li><?php echo __('wiki.import_export_import_2'); ?></li>
                <li><?php echo __('wiki.import_export_import_3'); ?></li>
              </ol>
              <p class="mt-3"><small><?php echo __('wiki.import_export_note'); ?></small></p>
            </div>
          </div>

          <!-- WordPress Integration -->
          <div id="wiki-wordpress_int" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_wordpress_int'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.wordpress_int_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.wordpress_int_steps_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.wordpress_int_1'); ?></li>
                <li><?php echo __('wiki.wordpress_int_2'); ?></li>
                <li><?php echo __('wiki.wordpress_int_3'); ?></li>
                <li><?php echo __('wiki.wordpress_int_4'); ?></li>
                <li><?php echo __('wiki.wordpress_int_5'); ?></li>
              </ol>
              <p class="mt-3"><?php echo __('wiki.wordpress_int_manage'); ?></p>
            </div>
          </div>

          <!-- Email Setup -->
          <div id="wiki-smtp" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_email_setup'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.email_setup_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.email_setup_config_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.email_setup_1'); ?></li>
                <li><?php echo __('wiki.email_setup_2'); ?></li>
                <li><?php echo __('wiki.email_setup_3'); ?></li>
              </ol>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.email_setup_templates_heading'); ?></h6>
              <p><?php echo __('wiki.email_setup_templates_desc'); ?></p>
              <ul class="wiki-ul">
                <li><?php echo __('wiki.email_setup_placeholder_domain'); ?></li>
                <li><?php echo __('wiki.email_setup_placeholder_expiry'); ?></li>
                <li><?php echo __('wiki.email_setup_placeholder_days'); ?></li>
                <li><?php echo __('wiki.email_setup_placeholder_client'); ?></li>
              </ul>
              <p class="mt-3"><small><?php echo __('wiki.email_setup_note'); ?></small></p>
            </div>
          </div>

          <!-- Site Settings -->
          <div id="wiki-site_settings" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_site_settings'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.site_settings_intro'); ?></p>
              <table class="wiki-table">
                <tbody>
                  <tr>
                    <th><?php echo __('wiki.site_settings_name'); ?></th>
                    <td><?php echo __('wiki.site_settings_name_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.site_settings_logo'); ?></th>
                    <td><?php echo __('wiki.site_settings_logo_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.site_settings_lang'); ?></th>
                    <td><?php echo __('wiki.site_settings_lang_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.site_settings_tz'); ?></th>
                    <td><?php echo __('wiki.site_settings_tz_desc'); ?></td>
                  </tr>
                  <tr>
                    <th><?php echo __('wiki.site_settings_expiry_days'); ?></th>
                    <td><?php echo __('wiki.site_settings_expiry_days_desc'); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Users -->
          <div id="wiki-users" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_users'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.users_intro'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.users_add_heading'); ?></h6>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.users_add_1'); ?></li>
                <li><?php echo __('wiki.users_add_2'); ?></li>
                <li><?php echo __('wiki.users_add_3'); ?></li>
              </ol>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.users_password_heading'); ?></h6>
              <p><?php echo __('wiki.users_password_desc'); ?></p>
              <h6 style="font-weight:600; margin-top:15px;"><?php echo __('wiki.users_forgot_heading'); ?></h6>
              <p><?php echo __('wiki.users_forgot_desc'); ?></p>
            </div>
          </div>

          <!-- License -->
          <div id="wiki-license" class="wiki-section">
            <div class="wiki-card-header">
              <h6 class="wiki-h6"><?php echo __('wiki.section_license'); ?></h6>
            </div>
            <div class="wiki-body">
              <p><?php echo __('wiki.license_intro'); ?></p>
              <ol class="wiki-ol">
                <li><?php echo __('wiki.license_1'); ?></li>
                <li><?php echo __('wiki.license_2'); ?></li>
                <li><?php echo __('wiki.license_3'); ?></li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .wiki-toc-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin-bottom: 20px;
  }

  .wiki-toc-scroll {
    overflow-y: auto;
    flex: 1;
    padding: 0;
  }

  @media (max-width: 991px) {
    .wiki-toc-card {
      position: static;
      max-height: 280px;
      margin-bottom: 20px;
    }
  }

  .wiki-card-header {
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 12px 15px;
  }

  .wiki-body {
    padding: 20px;
    font-size: 13.5px;
    line-height: 1.7;
  }

  .wiki-h6 {
    font-weight: 700;
    margin-top: 18px;
    margin-bottom: 12px;
    color: #2d3748;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 8px;
  }

  .wiki-table {
    font-size: 12.5px;
    margin-top: 10px;
  }

  .wiki-table th {
    background: #f8f9fa;
    font-weight: 600;
    padding: 10px;
    border: 1px solid #ddd;
  }

  .wiki-table td {
    padding: 10px;
    border: 1px solid #ddd;
  }

  .wiki-ul, .wiki-ol {
    margin-left: 20px;
    margin-top: 8px;
    margin-bottom: 12px;
  }

  .wiki-ul li, .wiki-ol li {
    margin-bottom: 6px;
  }

  .wiki-section {
    border-left: 4px solid #007bff;
    margin-bottom: 30px;
    background: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  }

  .wiki-section:nth-child(3n+1) {
    border-left-color: #6f42c1;
  }

  .wiki-section:nth-child(3n+2) {
    border-left-color: #28a745;
  }

  .wiki-section:nth-child(3n+3) {
    border-left-color: #fd7e14;
  }

  .wiki-section.wiki-hidden {
    display: none;
  }

  .wiki-toc-link {
    display: block;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    border-left: 3px solid transparent;
    font-size: 13px;
  }

  .wiki-toc-link:hover {
    background: #f5f5f5;
    border-left-color: #007bff;
  }

  .wiki-toc-link.active-toc {
    background: #f0f7ff;
    border-left-color: #007bff;
    color: #0056b3;
    font-weight: 600;
  }
</style>

<script>
  function wikiFilter(q) {
    const sections = document.querySelectorAll('.wiki-section');
    const lowerQ = q.toLowerCase();
    sections.forEach(sec => {
      if (sec.textContent.toLowerCase().includes(lowerQ)) {
        sec.classList.remove('wiki-hidden');
      } else {
        sec.classList.add('wiki-hidden');
      }
    });
  }

  document.querySelectorAll('.wiki-toc-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector(link.getAttribute('href'));
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });

  window.addEventListener('scroll', () => {
    let current = '';
    document.querySelectorAll('.wiki-section').forEach(sec => {
      if (window.scrollY >= sec.offsetTop - 100) {
        current = sec.getAttribute('id');
      }
    });
    document.querySelectorAll('.wiki-toc-link').forEach(link => {
      link.classList.remove('active-toc');
      if (link.getAttribute('href') === '#' + current) {
        link.classList.add('active-toc');
        link.scrollIntoView({ block: 'nearest' });
      }
    });
  }, { passive: true });
</script>

<?php require_once APP_PATH . '/includes/footer.php'; ?>
