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
**Problem Description:** -- This is done
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
"Manage Form Display" mein "Data Reference" widget ke liye visibility checkboxes default unchecked the, jis wajah se users ko manually sab enable karna pad raha tha. Saath hi "Display" section ko default unchecked rakhna tha taaki user apni pasand ke columns choose kar sakein. Baad mein ek bug report hua ki agar koi box (Visibility/Display) manually uncheck (0) karke save/update kiya jaye to wo wapas check ho jata tha, aur sirf custom fields (`field_`) by default check hone chahiye.

**Resolution:**
- **Custom Field Logic**: `SimpleDataReferenceWidget.php` aur `monarch_data_entity.module` mein default visibility logic ko change kiya gaya. Ab visibility by default `TRUE` sirf un fields ke liye hoti hai jinka naam `field_` se shuru hota hai (Custom Fields). Base fields by default unchecked rehti hain.
- **Checkbox Persistence Fix**: `SimpleDataReferenceWidget.php` mein ek bug fix kiya gaya jahan explicitly empty/unchecked values save hone ke baad system se `unset` ho jaati thi. Us logic ko hata diya gaya taaki user jo bhi value set kare, chahe wo `0` (unchecked) ho, wo override na ho. Iske alawa, Priority order ko fix karke Widget Settings ko ThirdPartySettings (Field config) se upar rakha gaya.
- **Database Script**: Ek custom PHP script run karke database mein existing fields ki visibility ko update kiya gaya.

---

## Issue 14: Default Options for Data Reference Field (DotSquares)
**Problem Description:** 
Naya Data Reference field banate waqt "Other" (Reference) aur "Data" (Target Type) ko by default select karna tha taaki Efficiency bade aur manual effort kam ho.

**Resolution:**
1. **Server-Side Injection**: `hook_preprocess_input` ka use karke `group_field_options_wrapper` radio buttons mein `entity_reference` (Other) par server-side se hi `checked="checked"` attribute add kiya gaya.
2. **Form Alter**: `hook_form_alter` ke zariye `target_type` dropdown mein by default `data_entity` (Data) select kiya gaya.
3. **Subform Initialization Bug Fix**: Ek problem thi ki jab page pehli baar load hota tha to "Reference Type" mein "Content Type" (Article, Basic page) checkboxes aate the kyunki background form builder `target_type` ko `node` samajh raha tha. Ise fix karne ke liye `hook_entity_prepare_form` add kiya gaya, jo form render hone se theek pehle storage entity ke `target_type` ko backend mein `data_entity` set kar deta hai, jisse default render mein hi "Data Type" ke bundles sahi se show ho jate hain.
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

---

## Issue 16 (Part 2): Cascading Dropdown 500 Error & Duplicate Fields
**Problem Description:** 
Jab user Search Box mein type karta tha to AJAX request crash (500 Server Error) ho rahi thi (`Error: Cannot unset string offsets`). Saath hi, "Brand Name" ke do dropdown aa rahe the jisme se pehla blank hota tha aur user flow break kar raha tha.

**Resolution:**
1. **AJAX String Array Error Fix:** 
   - **File:** `web/modules/custom/monarch_data_entity/src/TokenService.php`
   - **Lines Changed:** 96 to 99
   - **Change:** `TokenService` mein string fallback logic ko change karke ensure kiya gaya ki raw string (jaise "Dell") humesha valid array structure `[['value' => $values]]` mein format ho kar return ho. Pehle ye direct string return karta tha jis wajah se Drupal core ke andar `unset($values['add_more'])` ek array ki jagah string par trigger hokar fatal error deta tha.
2. **Stale Configuration Fatal Error Fix (`getLabel() on null`):** 
   - **Action:** Drush script ka use karke `field_reference` aur `field_laptops_dropdown` FieldConfigs se purane/stale `third_party_settings` (`monarch_data_entity`) ko permanently delete kiya gaya (`unsetThirdPartySetting`). Ye settings purane invalid field names cache karke rakh rahi thin jo widget options render hone ke time null pointer error throw kar rahe the.
3. **Duplicate Field Hide & Token Cascading Link:**
   - **Action:** Drush configuration updates (`core.entity_form_display.node.live_test_page.default`) ke zariye:
     - `field_reference` widget display se "Brand Name" column hata diya gaya taaki pehla khali dropdown hide ho jaye.
     - `field_laptops_dropdown` ke display settings mein `field_brand_new_name` row ka Filter Token directly `[field_reference:label]` set kiya gaya. Isse pehle dropdown ka Label seedha last dropdown filter karta hai.

**Status:** Resolved. AJAX crash fixed and multi-level dynamic token cascading works flawlessly.

---

## Issue 16 (Part 3): Orders Page UI & Missing Plugin Fatal Error
**Problem Description:** 
"Orders Page" par Test 16 run karte waqt 2 problems aayi:
1. Naya Fatal Error: `The "entity:sigmaxim_workflow_order:test_connector_g" plugin does not exist.` jab koi purana order (jaise Order 1) load ho raha tha jiska bundle type delete ho chuka tha.
2. Orders ki form (Test Product) par "Search Box Live", "Label", aur "Laptops" fields add hi nahi the, jis wajah se wahan issue 16 ka cascading test nahi ho paa raha tha.

**Resolution:**
1. **Missing Bundle Restoration (Fatal Error Fix):** 
   - **Action:** Drush `config:import` ke zariye `test_connector_g` bundle ki config ko wapas se install directory (`config/install`) se import kiya gaya, jisse Order 1 load hote waqt plugin missing ka fatal error aana band ho gaya.
2. **Order Entity Field Migration & Form Display Setup:**
   - **Action:** Ek custom Drush PHP script ke zariye `node` se teeno custom field storages (`field_search_box_live`, `field_reference`, `field_laptops_dropdown`) ko `sigmaxim_workflow_order` entity type par copy/migrate kiya gaya.
   - In fields ko Order ke `test_product` bundle mein attach kiya gaya.
   - `core.entity_form_display.sigmaxim_workflow_order.test_product.default` config set ki gayi jisme pehle wale duplicate "Brand Name" ko hide kar diya gaya aur second wale mein `[field_reference:label]` token set kar diya gaya, theek waise hi jaise `live_test_page` mein tha.

**Status:** Resolved. Orders page par fatal error fix ho chuka hai aur cascading fields successfully orders page ke "Test Product" form par add ho chuki hain jisse ab woh bhi filter function seamlessly use kar sakta hai.
