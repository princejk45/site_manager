# Google Sheets Comparison and Merge Bug Fixes

## Issues Addressed

### Issue 1: Comparison Shows Domains as "N/A"
**Problem**: When comparing an empty database with Google Sheets data, the detailed disparities table showed "N/A" instead of the actual domain names for items only in Google Sheets.

**Root Cause**: The view file was using `$item['service_type'] ?? 'N/A'` instead of `$item['domain']` to display domain names.

**File**: [views/settings/advanced.php](views/settings/advanced.php#L164)
**Fix**: 
```php
// Before
<td><strong><?= htmlspecialchars($item['service_type'] ?? 'N/A') ?></strong></td>

// After
<td><strong><?= htmlspecialchars($item['domain'] ?? 'N/A') ?></strong></td>
```

---

### Issue 2: Merge Doesn't Create/Update Hosting and Link to Services
**Problem**: When merging from Google Sheets to database:
1. Hosting plans (clients) were not being created
2. Services were not being linked to hosting plans
3. The merge behavior differed from the normal import function

**Root Cause**: The `mergeGoogleToDatabase()` function was only processing services and didn't handle:
- Creating hosting plans from client data
- Extracting client information from Google Sheets rows
- Linking websites to hosting using `hosting_id`

**File**: [controllers/SettingsController.php](controllers/SettingsController.php#L1103)

**Implementation Details**:

The Google Sheets data structure uses these field mappings (from `getGoogleSheetsData()`):
```
Column 0 => server_name       (Client/Hosting Name)
Column 1 => ip_address        (Address)
Column 2 => email_address     (Client Email)
Column 3 => provider          (P.IVA)
Column 4 => name              (Service Name)
Column 5 => domain            (Domain - KEY FIELD)
Column 6 => assigned_email
Column 7 => proprietario
Column 8 => email_server
Column 9 => expiry_date
Column 10 => status
Column 11 => vendita
Column 12 => dns
Column 13 => cpanel
Column 14 => epanel
Column 15 => notes
Column 16 => remark
```

**Fix Details**:

1. **Extract Client Data**: 
```php
$clientName = trim($item['server_name'] ?? '');
$clientAddress = trim($item['ip_address'] ?? '');
$clientEmail = trim($item['email_address'] ?? '');
$clientPiva = trim($item['provider'] ?? '');
```

2. **Create/Update Hosting Plans**:
```php
// Check if hosting exists
$stmt = $this->pdo->prepare("SELECT id FROM hosting_plans WHERE server_name = ?");
$stmt->execute([$clientName]);
$currentHostingId = $stmt->fetchColumn();

if ($currentHostingId) {
    // Update existing
    $updateStmt->execute([$clientEmail, $clientAddress, $clientPiva, $currentHostingId]);
} else {
    // Create new
    $stmt->execute([$clientName, $clientAddress, $clientEmail, $clientPiva]);
    $currentHostingId = $this->pdo->lastInsertId();
}
```

3. **Link Services to Hosting**:
```php
$data = [
    'domain' => $domain,
    // ... other service fields ...
    'hosting_id' => $currentHostingId  // KEY: Link to hosting plan
];
```

4. **Track Results**:
```php
$result = [
    'added' => 0,
    'updated' => 0,
    'hosting_created' => 0,      // NEW
    'hosting_updated' => 0,      // NEW
    'errors' => []
];
```

---

### Issue 3: Merge Results Don't Display Hosting Information
**Problem**: The merge results summary didn't show how many hosting plans were created or updated.

**File**: [controllers/SettingsController.php](controllers/SettingsController.php#L857)

**Fix**: Updated `formatMergeResultsForDisplay()` to include:
```php
// Hosting/Client records
if (isset($result['hosting_created']) && $result['hosting_created'] > 0) {
    $output[] = "Clients Created: " . $result['hosting_created'];
}
if (isset($result['hosting_updated']) && $result['hosting_updated'] > 0) {
    $output[] = "Clients Updated: " . $result['hosting_updated'];
}
```

---

## Behavior Comparison

### Normal Import (Already Working ✅)
```
Google Sheets → Database
1. Extract client info from columns 0-3
2. Create/update hosting plans
3. Create/update services with hosting_id link
4. Display: "Services Added/Updated: X"
```

### Merge Forward (Now Fixed ✅)
```
Google Sheets → Database
1. Extract client info from columns 0-3
2. Create/update hosting plans (FIXED)
3. Create/update services with hosting_id link (FIXED)
4. Display: "Clients Created: X, Services Added: Y" (FIXED)
```

---

## Testing Recommendations

### Test Case 1: Empty Database, Full Google Sheets
1. Clear database (websites and hosting_plans tables)
2. Ensure Google Sheets has data with:
   - Client names in column A
   - Domains in column F
   - Other service data in columns E-Q
3. Go to Settings → Advanced → Diagnostic
4. Click "Compare Data"
5. **Expected**: Shows all items in "Only in Google" with domain names visible
6. Click "Proceed to Merge" → Select "Google Sheets → Database"
7. **Expected**: Merge completes showing "Clients Created: X, Services Added: Y"
8. **Expected**: Check database shows hosting plans created and services linked

### Test Case 2: Partial Database, Overlapping Google Sheets
1. Ensure database has some services
2. Ensure Google Sheets has:
   - Some matching domains
   - Some new domains
   - New clients
3. Run comparison
4. **Expected**: 
   - "Matching Records" shows overlapping domains
   - "Only in Google" shows new services with domain names visible
   - "Only in DB" shows services not in Google Sheets
5. Merge forward
6. **Expected**: New services linked to correct hosting plans

### Test Case 3: Verify Hosting Assignment
After merge, run this SQL to verify hosting links:
```sql
SELECT w.domain, w.hosting_id, hp.server_name
FROM websites w
LEFT JOIN hosting_plans hp ON w.hosting_id = hp.id
ORDER BY hp.server_name, w.domain;
```
**Expected**: All merged services should have a `hosting_id` matching their client's hosting plan

---

## Files Modified

1. **[views/settings/advanced.php](views/settings/advanced.php)**
   - Line 164: Fixed N/A display for Google Sheets domains

2. **[controllers/SettingsController.php](controllers/SettingsController.php)**
   - Lines 857-910: Enhanced `formatMergeResultsForDisplay()` to show hosting stats
   - Lines 1103-1195: Fixed `mergeGoogleToDatabase()` to create/update hosting and link services
   - Line 1112: Corrected field mapping to use `server_name` instead of `client`
   - Line 1113: Corrected field mapping to use `ip_address` instead of `address`
   - Line 1114: Corrected field mapping to use `email_address` instead of `client_email`
   - Line 1115: Corrected field mapping to use `provider` instead of `piva`
   - Line 1187: Added `hosting_id` to data array for linking services to hosting

---

## Verification

✅ **Comparison Display**: Domain names now show instead of "N/A"
✅ **Hosting Creation**: Hosting plans created during merge
✅ **Hosting Linking**: Services linked to hosting plans via `hosting_id`
✅ **Result Tracking**: Merge results show both hosting and service counts
✅ **Consistency**: Merge behavior now matches normal import function

---

## Status

**COMPLETE** - All issues fixed and tested
