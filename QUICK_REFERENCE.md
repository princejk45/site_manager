# Phase 1: Dashboard 2.0 - Quick Reference Guide

## 🚀 Getting Started

Dashboard 2.0 is **enabled by default** and active immediately after login.

### Test Dashboard 2.0
```
1. Open browser and navigate to: http://localhost/fullmidia/site_manager
2. Log in with your credentials
3. You should see the modern new dashboard
4. Try the dark mode toggle (button in top-right)
```

---

## 📂 Key Files & Locations

### User-Facing Assets
```
assets/css/dashboard-v2.css     Modern styling (14.3 KB)
assets/js/dashboard-v2.js       Interactive features (7.1 KB)
```

### Views & Components
```
views/dashboard/dashboard-v2.php     Modern dashboard layout
includes/sidebar-v2.php              New navigation structure
```

### Configuration
```
config/dashboard-config.php          Version management functions
config/bootstrap.php                 Initializes dashboard_version session
```

### Documentation
```
PHASE1_DASHBOARD_V2.md               Complete feature documentation
PHASE1_COMPLETION_REPORT.md          Detailed completion report
verify-phase1.php                    Verification script
```

---

## 🎯 Dashboard Components

### 1. KPI Cards (5 metrics)
```
- Active Services       → Total websites/hosting services
- Expiring Soon         → Services expiring in 30 days
- Services with Issues  → Health check failures
- Overall Health Score  → Combined system percentage (0-100%)
- Total Clients         → Total hosting accounts
```

**Click any KPI card to navigate to that section:**
- Services → Websites list
- Expiring → Websites sorted by expiry date
- Issues → Diagnostics page
- Health → Diagnostics page
- Clients → Hosting list

### 2. Alert Panel
```
- Shows up to 4 urgent alerts
- Each alert links to relevant action
- Examples:
  • "5 services expiring in 7 days" → Renewals section
  • "2 services offline" → Diagnostics
  • "Cron sync failed" → Cron scheduler
```

### 3. Service Status Grid
```
Shows real-time overview of services:
- Domain name
- Status badge (Healthy/Warning/Critical)
- Days until expiry
- Last check timestamp
- Quick action links (View/Diagnose)
```

### 4. Health Gauge
```
Visual health score with stats:
- Circular progress ring (0-100%)
- Percentage and label
- Quick stats: Uptime, Response Time, DB Status, Cron Jobs
```

### 5. Quick Actions (6 buttons)
```
- 🔍 Run Diagnostics     → System health check
- 🔄 Check Renewals      → Service renewals due
- 💬 View Messages       → Messaging center
- ⚡ Automation          → Automation rules
- 📊 Export Data         → Data export tools
- ⚙️  Settings           → System configuration
```

---

## 🌙 Dark Mode

### Enable/Disable
```
Click "Dark" button in top-right corner of dashboard header
```

### Automatic Persistence
- Dark mode preference saved to browser's localStorage
- Automatically reapplied on next login
- Works across all pages using Dashboard v2

### Manual Toggle (JavaScript Console)
```javascript
dashboard.toggleDarkMode();
dashboard.getRefreshInterval();
dashboard.setRefreshInterval(30000); // 30 seconds
```

---

## 🔧 Switch Between Versions

### Use Modern Dashboard (v2)
```php
$_SESSION['dashboard_version'] = 'v2';
```

### Use Legacy Dashboard (v1)
```php
$_SESSION['dashboard_version'] = 'v1';
```

### Change Programmatically
```php
// In any controller
$_SESSION['dashboard_version'] = 'v2'; // or 'v1'
```

---

## 🔄 Auto-Refresh Configuration

### Default Behavior
- Dashboard data auto-refreshes every 60 seconds
- Only refreshes if user hasn't closed the page

### Change Refresh Interval
```javascript
// JavaScript console in browser
dashboard.setRefreshInterval(30000);  // 30 seconds
dashboard.setRefreshInterval(120000); // 2 minutes
dashboard.setRefreshInterval(0);      // Disable auto-refresh
```

### Check Current Interval
```javascript
dashboard.getRefreshInterval(); // Returns milliseconds
```

---

## 🧪 Verification

### Verify All Components
```bash
php verify-phase1.php
```

### Expected Output
```
✅ ALL PHASE 1 COMPONENTS VERIFIED - READY FOR USE
```

### What Gets Checked
1. CSS Framework file exists
2. JavaScript Framework file exists
3. Dashboard view exists
4. Sidebar navigation exists
5. Config file exists
6. CSS linked in header
7. JavaScript linked in footer
8. Controller routing configured
9. Documentation exists

---

## 🎨 Customization

### Change Primary Color
Edit `assets/css/dashboard-v2.css`:
```css
:root {
    --primary: #667eea;      /* Change this hex code */
    --secondary: #764ba2;
    /* ... other colors ... */
}
```

### Adjust Component Styling
```css
.kpi-card {
    padding: 20px;           /* Adjust padding */
    border-radius: 12px;     /* Adjust border radius */
}
```

### Modify Dark Mode Colors
```css
body.dark-mode {
    --bg-primary: #1a202c;   /* Background color */
    --text-primary: #e2e8f0; /* Text color */
}
```

---

## ⚙️ Configuration Functions

### In PHP Code

```php
// Get current dashboard version
$version = getDashboardVersion(); // Returns 'v1' or 'v2'

// Check if v2 is enabled
if (isDashboardV2Enabled()) {
    // Dashboard 2.0 specific code
}

// Get appropriate view file
$viewFile = getDashboardView();

// Get appropriate sidebar file
$sidebarFile = getSidebarView();

// Switch versions
switchDashboardVersion('v2');
```

### In JavaScript Code

```javascript
// Initialize dashboard (auto-runs on page load)
window.dashboard = new DashboardV2();

// Show notification
dashboard.showToast('Save successful', 'success', 3000);

// Refresh data manually
dashboard.refreshDashboard();

// Expand service card
dashboard.expandServiceCard(element);
```

---

## 📋 Sidebar Navigation Map

### All Sections (Updated Structure)
```
📊 Dashboard              → index.php?action=dashboard
├─ 📦 Services
│  ├─ Websites           → index.php?action=websites
│  └─ Hosting            → index.php?action=hosting
├─ 👥 Clients
│  ├─ Portfolio (Soon)   → [Placeholder]
│  └─ Groups             → [Link TBD]
├─ ⚙️ Operations (NEW)
│  ├─ Cron (Soon)        → [Phase 2]
│  ├─ Diagnostics        → [Phase 2]
│  ├─ Automation         → [Phase 2]
│  ├─ Import/Export (Soon)
│  └─ Task Queue (Soon)
├─ 🔗 Integrations
│  ├─ Connected Apps     → [TBD]
│  └─ API Keys (Soon)    → [Phase 3]
├─ 💬 Communications
│  ├─ Messaging          → index.php?action=messaging
│  └─ Reports (Soon)     → [Phase 4]
└─ ⚡ System (Super Admin Only)
   ├─ Settings           → index.php?action=settings
   ├─ Users              → [Users management]
   └─ License            → [License management]
```

---

## 🐛 Common Issues & Solutions

### Dark Mode Not Saving
**Problem**: Dark mode doesn't persist after page refresh
**Solution**: 
```javascript
// Check localStorage
console.log(localStorage.getItem('darkMode'));
// Clear and retry
localStorage.clear();
```

### Dashboard Not Updating KPIs
**Problem**: KPI values not showing latest data
**Solution**: 
```javascript
// Manually refresh dashboard
dashboard.refreshDashboard();
// Check browser console for errors
console.log('Dashboard state:', dashboard);
```

### Sidebar Not Showing New Items
**Problem**: Sidebar still shows old navigation
**Solution**: 
```
1. Clear browser cache (Ctrl+Shift+Delete)
2. Refresh page (Ctrl+F5)
3. Verify sidebar-v2.php is being loaded
```

### CSS Not Loading
**Problem**: Dashboard looks broken/unstyled
**Solution**:
```
1. Check Network tab in DevTools
2. Verify assets/css/dashboard-v2.css exists
3. Check BASE_PATH constant is correct
4. Clear browser cache and reload
```

---

## 📞 Support & Documentation

### Quick References
- User Guide: `PHASE1_DASHBOARD_V2.md`
- Completion Report: `PHASE1_COMPLETION_REPORT.md`
- This Guide: `QUICK_REFERENCE.md` (You are here)

### Verification Script
```bash
php verify-phase1.php
```

### Browser Console Commands
```javascript
// Show toast notification
dashboard.showToast('Hello!', 'info');

// Toggle dark mode
dashboard.toggleDarkMode();

// Check dashboard version
console.log(document.body.classList.contains('dark-mode'));

// Refresh data
dashboard.refreshDashboard();
```

---

## ✅ Phase 1 Checklist

Before moving to Phase 2, verify:

- ✅ Dashboard loads on login
- ✅ KPI cards show correct values
- ✅ Alert panel displays appropriately  
- ✅ Dark mode toggle works
- ✅ Dark mode preference persists
- ✅ Sidebar navigation works
- ✅ Quick action buttons navigate correctly
- ✅ Responsive design on mobile/tablet
- ✅ No console errors in DevTools
- ✅ All 9 components verified with script

---

## 🚀 Next Phase Preview

**Phase 2: Diagnostics Center** (Ready to begin)

What's Coming:
- Real-time service monitoring
- Advanced health analytics
- Performance metrics
- Automated health checks
- Historical trend charts

Status: Foundation ready, awaiting implementation

---

## 📞 Quick Help

**View any section**: Open that section in the left sidebar
**Check system health**: Click "Overall Health Score" KPI card
**Renew services**: Click "Expiring Soon" KPI card
**Run diagnostics**: Click "Run Diagnostics" quick action button
**Toggle dark mode**: Click "Dark" button in top-right
**Enable/disable auto-refresh**: `dashboard.setRefreshInterval(ms)`

---

**Happy dashboarding! 🎉**

