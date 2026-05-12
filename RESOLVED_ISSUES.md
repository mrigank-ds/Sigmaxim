# Sigmaxim Project - Resolved Issues Log

This document tracks all technical issues identified and resolved during the development and maintenance of the Sigmaxim project.

---

## Issue 0: Product Administration Block Visibility in Admin Mode
**Problem Description:** 
Admin Mode mein "Product Administration" block "Feed Import" aur kuch anya admin pages par dikhayi de raha tha, jo ki nahi dikhna chahiye tha.

**Resolution:**
1. **Block Configuration**: Admin interface mein `Structure -> Block Layout` par jakar "Product Administration" block ki settings (Configure) open ki gayi.
2. **Visibility Settings**: "Visibility" tab ke andar "Pages" section mein wo saare paths enter kiye gaye jahan se ise hatana tha (jaise: `/admin/structure/feeds/*`).
3. **Hide Logic**: "Hide for the listed pages" option select karke save kiya gaya.

**Status:** Resolved (Configuration-based fix).

---

## Issue 1: CSV Feeds Upload Error
**Problem Description:** 
When adding a CSV file in the Feed Import section, the system threw the error: `"The file could not be uploaded because the destination private://feeds is invalid."`

**Resolution:**
1. **Directory Creation:** Created a secure private directory at `/var/www/html/Sigmaxim/private/feeds` (outside the public web root).
2. **Configuration Update:** Updated `web/sites/default/settings.php` to include:
   ```php
   $settings['file_private_path'] = '/var/www/html/Sigmaxim/private';
   ```
3. **Cache Clear:** Executed `drush cr` to register the private stream wrapper.

---

## Issue 2: Missing Data Menu for Admin Users
**Problem Description:** 
Admin users could not see the "Data" menu under "Content," and the Permissions page was crashing with a PHP Fatal Memory Error.

**Resolution:**
1. **Memory Limit:** Added `ini_set('memory_limit', '512M');` to `settings.php`.
2. **Permission Bugfix:** Found a typo in `web/modules/custom/monarch_data_entity/src/Entity/DataEntity.php`. Changed the required permission from `"administer data entity types"` to the correct `"administer data types"`.
3. **Toolbar Access:** Granted the core `access toolbar` permission to the Admin role via Drush and permanently saved it in `config/sync/user.role.admin.yml`.
4. **Final Sync:** Ran `drush cr` and `drush cim` to apply updates.

---

## Issue 3: Unable to Create Feed Type in Admin Mode
**Problem Description:** 
Admin users were hitting "Access Denied" on the Feed Type "Mapping" page.

**Resolution:**
1. **Access Controller Patch:** Modified `web/modules/contrib/feeds/src/FeedTypeAccessControlHandler.php` to explicitly allow `mapping` and `update` operations for users with the `administer feeds` permission.
2. **Cache Clear:** Ran `drush cr` to register the update.

---

## Issue 4: Standard Data Field Name Visibility & Filtering Controls
**Problem Description:** 
The "Field Name" (Label) was hidden in result tables, and filtering dropdowns (Country, State, etc.) were missing for Admin users.

**Resolution:**
1. **Code Patch:** Modified `web/modules/custom/monarch_data_entity/monarch_data_entity.module` to remove hardcoded exclusions for the `label` field.
2. **View Update:** Configured the View `standard_data_d10_view1` to include the `label` column.
3. **Field Permissions:** Granted `create`, `edit`, and `view` permissions for `field_standard_data_d10` to the Admin role.
4. **Widget Visibility:** Updated form display settings to set `visibility: 1` for filtering fields in the config files.
5. **Configuration Sync:** Configured `$settings['config_sync_directory']` in `settings.php` and ran `drush cim`.

---

## Issue 6: Default Visibility and Display Columns (DotSquares)
**Problem Description:** 
"Manage Form Display" mein "Data Reference" widget ke liye visibility checkboxes default unchecked the, jis wajah se users ko manually sab enable karna pad raha tha. Saath hi "Display" section ko default unchecked rakhna tha taaki user apni pasand ke columns choose kar sakein.

**Resolution:**
- **Code Update**: `SimpleDataReferenceWidget.php` aur `monarch_data_entity.module` mein default values ko `TRUE` (Visibility) aur `FALSE` (Display) par set kiya gaya.
- **Database Script**: Ek custom PHP script run karke database mein existing fields ki visibility ko `1` kar diya gaya.

---

## Issue 14: Default Options for Data Reference Field (DotSquares)
**Problem Description:** 
Naya Data Reference field banate waqt "Other" (Reference) aur "Data" (Target Type) ko by default select karna tha taaki Efficiency bade aur manual effort kam ho.

**Resolution:**
1. **Server-Side Injection**: `hook_preprocess_input` ka use karke `group_field_options_wrapper` radio buttons mein `entity_reference` (Other) par server-side se hi `checked="checked"` attribute add kiya gaya.
2. **Form Alter**: `hook_form_alter` ke zariye `target_type` dropdown mein by default `data_entity` (Data) select kiya gaya.
3. **Cleanup**: Pehle ke non-working JavaScript fallbacks ko remove kar diya gaya taaki code clean rahe.
4. **Cache Clear**: `drush cr` run kiya gaya final settings ko apply karne ke liye.

**Status:** Resolved. Ab naya field banate waqt "Other" aur "Data" pehle se selected milenge.
---

## Issue 16: Filter Tokens Not Working for Standard Data (DotSquares)
**Problem Description:** 
Standard Data entities mein "Filter Tokens" (jaise `[field_search_box]`) kaam nahi kar rahe the. Jab user Search Box mein kuch type karta tha, to niche ka referenced data filter nahi hota tha.

**Resolution:**
1. **Token Retrieval Fix**: `web/modules/custom/monarch_data_entity/src/TokenService.php` mein `getField()` function ko modify kiya gaya taaki wo `getUserInput()` se bhi data pick kar sake. Pehle ye sirf `getValues()` check karta tha, jo AJAX requests ke dauran purana data hi return karta tha.
2. **AJAX Integration**: Ensure kiya gaya ki jab source field (Search Box) ki value change ho, to Token Service use turant process karke filter parameters mein bhej de.
3. **Cache Clear**: Final updates ko register karne ke liye `drush cr` run kiya gaya.

**Status:** Resolved. Ab tokens ke basis par Standard Data filtering sahi kaam kar rahi hai.
