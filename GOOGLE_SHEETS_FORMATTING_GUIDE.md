# Google Sheets Formatting Implementation Guide

## Overview
This document describes the Google Sheets formatting features implemented in the site manager application, including column width management, bold text formatting, and cell color preservation.

## Features Implemented

### 1. Column Width Management

#### Column Width Configuration
Column widths are managed in the `mergeWithGoogleSheets()` function in [SettingsController.php](controllers/SettingsController.php#L1220):

**Normal Width Columns** (200 pixels):
- `Tipologia`
- `Registrante`
- `Proprietario`
- `Scadenza`
- `Costo`
- `Prezzo`
- `DNS`

**Wide Width Columns** (300 pixels):
- `Nome` (Client name)
- `Indirizzo` (Address)
- `Email`
- `P.IVA` (Tax ID)
- `Città` (City)
- `CAP` (ZIP Code)
- `Telefono` (Phone)
- `Sito` (Website)

#### Implementation Details
```php
// Width mapping defined for each column
$columnWidths = [
    'A' => 300, // Nome - Wide (client names)
    'B' => 300, // Indirizzo - Wide
    'C' => 300, // Email - Wide
    'D' => 200, // P.IVA - Normal
    'E' => 300, // Città - Wide
    'F' => 300, // CAP - Wide
    'G' => 300, // Telefono - Wide
    'H' => 300, // Sito - Wide
    'I' => 200, // Tipologia - Normal
    'J' => 200, // Registrante - Normal
    'K' => 200, // Proprietario - Normal
    'L' => 200, // Scadenza - Normal
    'M' => 200, // Costo - Normal
    'N' => 200, // Prezzo - Normal
    'O' => 200  // DNS - Normal
];
```

The formatting uses Google Sheets API with `pixelSize` property for precise width control.

### 2. Bold Text Formatting

#### Column A (Nome) Bold Formatting
The first column (Nome) containing client names is automatically formatted as bold:

- **Location**: [SettingsController.php](controllers/SettingsController.php#L1418)
- **Range**: A2:A (all rows except header)
- **Format**: Bold text for emphasis

```php
// Apply bold formatting to column A
$requests[] = [
    'repeatCell' => [
        'range' => [
            'sheetId' => $sheetId,
            'startRowIndex' => 1, // Skip header
            'endColumnIndex' => 1  // Only column A
        ],
        'cell' => [
            'userEnteredFormat' => [
                'textFormat' => [
                    'bold' => true
                ]
            ]
        ],
        'fields' => 'userEnteredFormat.textFormat.bold'
    ]
];
```

### 3. Cell Color Preservation

#### Color Tracking System
Colors are preserved during the merge process:

**Implementation Points**:
1. **During Export** ([models/SiteSettings.php](models/SiteSettings.php#L450)):
   - RGB color values extracted from database records
   - Format: `rgb(r, g, b)`

2. **During Merge** ([SettingsController.php](controllers/SettingsController.php#L1330)):
   - Colors applied via Google Sheets API `batchUpdate`
   - Each row with color data gets a background color request

```php
// Color data example from database
$colorData = $row['color']; // Format: "rgb(255, 200, 150)"

// Applied to Google Sheets
'backgroundColor' => [
    'red' => $colorComponents['r'] / 255,
    'green' => $colorComponents['g'] / 255,
    'blue' => $colorComponents['b'] / 255,
    'alpha' => 1
]
```

#### Color Categories
Colors can represent:
- **Client Status**: Different status levels
- **Domain Categories**: Type classifications
- **Custom Tags**: User-defined color groupings

## API Requests Used

### Google Sheets API Methods
1. **batchUpdate()**: Applies multiple formatting requests
   - Column width updates
   - Text formatting (bold)
   - Background colors

2. **appendValues()**: Adds data to sheet
   - Uses existing data structure

### Request Types
1. `updateDimensionProperties`: Sets column widths
2. `repeatCell`: Applies bold formatting
3. `updateCells`: Applies background colors

## User Workflow

### Exporting Data to Google Sheets
1. User navigates to Settings > Export/Merge
2. Click "Export to Google Sheets"
3. System processes data:
   - Prepares columns and data
   - Sets column widths (300px for wide, 200px for normal)
   - Applies bold formatting to Nome column
   - Preserves color information

### Merging Google Sheets Data
1. User uploads CSV from Google Sheets
2. System imports data:
   - Reads color values
   - Maps to corresponding database records
   - Updates with formatting

## Technical Details

### Database Schema
Color storage in `site_settings` table:
```sql
ALTER TABLE site_settings ADD COLUMN color VARCHAR(50);
-- Format: "rgb(r, g, b)"
```

### Google Sheets API Integration
- **File**: [config/mailer.php](config/mailer.php) - Google API configuration
- **Client Library**: `google/apiclient-services-sheets`
- **Authentication**: Service account with Sheets API access

### Column Mapping
Column positions map to spreadsheet columns A-O:
- Determined by `array_keys()` of prepared data
- Consistent across export and import
- Header row index: 1 (0-based indexing)

## Maintenance & Troubleshooting

### Common Issues

**Issue**: Column widths not applying
- **Solution**: Verify `batchUpdate` request includes `fields: 'pixelSize'`
- **Check**: Ensure sheet ID is correctly retrieved from API response

**Issue**: Bold formatting not visible
- **Solution**: Confirm row index starts at 1 (skipping header)
- **Check**: Verify `textFormat.bold` is in request body

**Issue**: Colors not preserved
- **Solution**: Verify RGB values in database
- **Check**: Ensure color format is "rgb(r, g, b)" with valid 0-255 ranges

### Debugging
Enable detailed logging:
```php
error_log("Sheet ID: " . $sheetId);
error_log("Column widths applied: " . json_encode($columnWidths));
error_log("Requests sent: " . json_encode($requests));
```

## Future Enhancements

### Potential Improvements
1. **Font Size Variation**: Different sizes for headers vs. data
2. **Cell Alignment**: Center alignment for dates/prices
3. **Number Formatting**: Currency format for Costo/Prezzo
4. **Conditional Formatting**: Rules based on cell values
5. **Freezing Rows**: Keep header row visible when scrolling

## API Documentation References
- [Google Sheets API - Dimensions](https://developers.google.com/sheets/api/reference/rest/v1/spreadsheets/batchupdate#UpdateDimensionPropertiesRequest)
- [Google Sheets API - Formatting](https://developers.google.com/sheets/api/reference/rest/v1/spreadsheets/batchupdate#RepeatCellRequest)
- [Google Sheets API - Color](https://developers.google.com/sheets/api/reference/rest/v1/Color)

---

**Last Updated**: 2024
**Status**: Fully Implemented
**Test Status**: Ready for Testing
