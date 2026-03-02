# SUMIT CRM Resources

This directory contains Filament v4 resources for SUMIT CRM integration.

## Resources

### 1. CrmFolders
**Location**: `CrmFolders/`
**Navigation**: SUMIT CRM → CRM Folders
**Purpose**: Manage CRM folder schemas from SUMIT

**Features**:
- List all synced folders
- View folder details and field definitions
- Actions:
  - **Sync All Folders** (header): Runs `php artisan crm:sync-folders`
  - **Sync Schema** (row): Syncs field definitions for a single folder
  - **Sync Entities** (row): Syncs all entities in a folder

**Files**:
- `CrmFolderResource.php` - Main resource
- `Schemas/CrmFolderForm.php` - Read-only form schema
- `Tables/CrmFoldersTable.php` - Table with sync actions
- `Pages/ListCrmFolders.php`, `CreateCrmFolder.php`, `EditCrmFolder.php`

---

### 2. CrmEntities
**Location**: `CrmEntities/`
**Navigation**: SUMIT CRM → CRM Entities
**Purpose**: Manage CRM entities (contacts, leads, companies, deals)

**Features**:
- **Dynamic forms** based on folder field definitions
- Support for 12 field types (text, number, date, select, email, etc.)
- Custom field storage via EAV pattern
- Entity filtering by folder, owner, assigned user
- Soft delete support

**Files**:
- `CrmEntityResource.php` - Main resource with `canCreate()` check
- `Schemas/CrmEntityForm.php` - **Dynamic form builder** (230 lines!)
  - `makeFieldComponent()` - Creates form fields based on folder field type
  - `parseOptions()` - Parses JSON options for select fields
- `Tables/CrmEntitiesTable.php` - Table with sync and filters
- `Pages/CreateCrmEntity.php` - Handles custom field saving
- `Pages/EditCrmEntity.php` - Handles custom field loading and updating
- `Pages/ListCrmEntities.php`

**Dynamic Form Features**:
- Folder selection triggers dynamic field loading
- Standard fields: entity_name, notes, owner, assigned_to
- Custom fields loaded from `CrmFolderField` definitions
- Field values stored in `CrmEntityField` (EAV)
- Supports required validation, descriptions, placeholders

---

### 3. CrmActivities
**Location**: `CrmActivities/`
**Navigation**: SUMIT CRM → Activities
**Purpose**: View activity timeline (read-only)

**Features**:
- Read-only activity log
- Timeline view with activity details
- Filter by type, entity
- No create/edit capabilities (activities created programmatically)

**Files**:
- `CrmActivityResource.php` - Main resource with `canCreate() = false`
- `Schemas/CrmActivityForm.php` - Empty (not used)
- `Schemas/CrmActivityInfolist.php` - Timeline view schema
- `Tables/CrmActivitiesTable.php` - Table with type badges
- `Pages/ListCrmActivities.php`, `ViewCrmActivity.php`

---

## Auto-Discovery

Resources are automatically discovered by Filament using the admin panel's `discoverResources()` configuration.

**Admin Panel** (in main app):
```php
->discoverResources(
    in: app_path('Filament/Resources'), 
    for: 'App\\Filament\\Resources'
)
```

**Package Resources** are also auto-discovered when the package is registered.

---

## Navigation Structure

```
SUMIT CRM (Navigation Group)
├── CRM Folders (#1)
├── CRM Entities (#2)
└── Activities (#3)
```

---

## Testing Checklist

- [ ] Sync folders from SUMIT (`crm:sync-folders`)
- [ ] View folder list with field counts
- [ ] Click "Sync Schema" on a folder
- [ ] Click "Sync Entities" on a folder
- [ ] Create new entity (select folder → see dynamic fields)
- [ ] Edit entity (custom fields load correctly)
- [ ] Test different field types (text, date, select, etc.)
- [ ] View activities timeline
- [ ] Check entity-activity relationships

---

## Database Tables Used

- `officeguy_crm_folders` - Folder definitions
- `officeguy_crm_folder_fields` - Field definitions (per folder)
- `officeguy_crm_entities` - Entity records
- `officeguy_crm_entity_fields` - Custom field values (EAV)
- `officeguy_crm_entity_relations` - Entity relationships
- `officeguy_crm_activities` - Activity log
- `officeguy_crm_views` - Saved views

---

## Related Services

- `CrmSchemaService` - Sync folders and fields from SUMIT
- `CrmDataService` - CRUD operations for entities
- Command: `php artisan crm:sync-folders`

---

---

## Installation & Setup

### Step 1: Run Migrations
```bash
php artisan migrate --force
```

This will create 7 CRM tables:
- `officeguy_crm_folders`
- `officeguy_crm_folder_fields`
- `officeguy_crm_entities`
- `officeguy_crm_entity_fields`
- `officeguy_crm_entity_relations`
- `officeguy_crm_activities`
- `officeguy_crm_views`

### Step 2: Verify Resources are Discovered
```bash
php artisan route:list --path=admin/crm
```

Should show 8 routes for CRM resources.

### Step 3: Sync Folders from SUMIT
```bash
php artisan crm:sync-folders
```

Or use the "Sync All Folders" button in the admin panel at `/admin/crm-folders`.

---

## Troubleshooting

### Error: "Class not found"
**Problem**: CRM models or resources not loading.
**Solution**: Run `composer update officeguy/laravel-sumit-gateway` to pull latest package version.

### Error: "Table doesn't exist"
**Problem**: Migrations haven't run yet.
**Solution**: Run `php artisan migrate --force`

### No folders showing after sync
**Problem**: SUMIT credentials not configured or API unreachable.
**Solution**:
1. Check SUMIT credentials in `/admin/office-guy-settings` → Credentials tab
2. Test connection: `php artisan sumit:health-check`
3. Check logs: `tail -f storage/logs/laravel-$(date +%Y-%m-%d).log`

---

**Created**: 2025-12-01
**Filament Version**: v4.1.10
**Laravel Version**: v12.37
**Package Version**: v1.2.0+
