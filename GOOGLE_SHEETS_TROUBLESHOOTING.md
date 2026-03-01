# Google Sheets Integration - Quick Troubleshooting Guide

## Before You Export/Import

### Checklist:
- ✅ Google Sheet ID is filled in (find in sheet URL: `/spreadsheets/d/{SHEET_ID}/edit`)
- ✅ Sheet Name is filled in (the tab name at the bottom of your Google Sheet, e.g., "TEST")
- ✅ Service Account Credentials JSON is pasted correctly
- ✅ "Enable Google Sync" toggle is turned ON
- ✅ The sheet exists in your Google Drive with the exact name you entered

## Common Issues & Solutions

### 1. "Sheet 'TEST' not found" Error
**What it means:** The sheet name you entered doesn't exist in your Google Sheet

**How to fix:**
1. Open your Google Sheet
2. Look at the sheet tab names at the bottom
3. Copy the EXACT name of the sheet tab (case-sensitive)
4. Paste it into the "Sheet Name" field
5. Try export/import again

### 2. Alert: "Sheet ID is required"
**What it means:** The Sheet ID field is empty

**How to fix:**
1. Open your Google Sheet in your browser
2. Look at the URL bar
3. Find the part that looks like: `/spreadsheets/d/1ABCDEFGHIJKLMNOPQRSTUVWXYZabc123`
4. Copy only the ID part (between `/d/` and `/edit`): `1ABCDEFGHIJKLMNOPQRSTUVWXYZabc123`
5. Paste it into the Sheet ID field
6. Try again

### 3. Alert: "Sheet Name is required"
**What it means:** The Sheet Name field is empty

**How to fix:**
1. Enter the name of the sheet tab you want to use (e.g., "Sheet1", "TEST", "Data")
2. Make sure it matches the actual sheet tab name exactly
3. Try again

### 4. Merge Result Shows JSON Code
This has been fixed! Merge results now display in a readable format like:
```
Merge completed successfully:
Records Updated: 27
Records Added: 5
```

Instead of:
```
Merge completed: {"updated":27,"errors":[]}
```

### 5. Export Doesn't Have Headers or Styling
**What it means:** The headers and styling weren't applied

**How to check:**
1. Export to Google Sheets
2. Look at rows 1-2 - they should have:
   - Row 1: Category headers with colors (Clienti, Informazioni per il cliente, Informazioni sul servizio)
   - Row 2: Column headers (Nome, Indirizzo, Email, P.IVA, etc.)
3. Data should start from row 3
4. Headers should have colored backgrounds

**If missing:**
1. Make sure you're looking at the correct sheet (Sheet Name field)
2. Try exporting again - styling is applied automatically
3. Check browser console (F12) for any JavaScript errors

### 6. Import Not Bringing Data
**How to fix:**
1. Make sure the data in Google Sheet starts at row 3 (after headers)
2. Ensure your data matches the expected format (17 columns: A-Q)
3. Check the Sheet Name matches exactly what you entered
4. Look at the top of the page for error messages

## Export/Import Column Order

The system expects data in this order (columns A-Q):

| Col | Header | Col | Header | Col | Header |
|-----|--------|-----|--------|-----|--------|
| A | Nome | G | Email Assegnata | M | Direct DNS A |
| B | Indirizzo | H | Propietario | N | User Name cpanel |
| C | Email | I | Registrante | O | Email panel |
| D | P.IVA | J | Scadenza | P | Bug report |
| E | Tipologia di Servizi | K | Costo Server | Q | Notes |
| F | Dettaglio Servizi | L | Prezzo di vendita | | |

## Still Having Issues?

1. **Clear your browser cache** (Ctrl+Shift+Delete or Cmd+Shift+Delete)
2. **Check the error message** at the top of the page carefully
3. **Verify credentials** are valid by testing SMTP first if available
4. **Check the sheet name** - copy/paste it directly from the sheet tab
5. **Ensure sync is enabled** - toggle "Enable Google Sync" on

## Merge Strategies Explained

### Forward (Google → Database)
- Takes data from Google Sheets
- Updates or creates records in your database
- Use when Google Sheet is the "source of truth"

### Backward (Database → Google)
- Takes data from your database
- Updates Google Sheets
- Use when your database is the "source of truth"

### Together (Both Ways)
- First updates database from Google Sheets
- Then updates Google Sheets from database
- Use for complete synchronization

## Notes

- Headers are automatically applied on export (rows 1-2)
- Data must start from row 3 in the sheet
- Sheet names are case-sensitive
- The system automatically formats and colors headers
- All 17 columns must be present for proper alignment
