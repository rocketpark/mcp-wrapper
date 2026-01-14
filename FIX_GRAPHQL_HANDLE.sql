-- SQL script to fix the invalid GraphQL handle "2024OBCCompliant_Submission"
-- This handle starts with a number, which violates GraphQL naming rules
-- Run this on the jensenhughes database

-- Step 1: Find the invalid handle in matrix block types
SELECT id, fieldId, handle, name, 'matrixtypes' as table_name
FROM matrixtypes 
WHERE handle LIKE '2024%';

-- Step 2: Find in entry types
SELECT id, sectionId, handle, name, 'entrytypes' as table_name
FROM entrytypes 
WHERE handle LIKE '2024%';

-- Step 3: Find in fields
SELECT id, handle, name, 'fields' as table_name
FROM fields 
WHERE handle LIKE '2024%';

-- Step 4: Find in table field columns (JSON stored)
SELECT id, handle, name, settings, 'fields (table columns)' as table_name
FROM fields 
WHERE type = 'craft\\fields\\Table' 
  AND settings LIKE '%2024OBC%';

-- ============================================
-- AFTER FINDING THE RECORD, UPDATE IT:
-- ============================================

-- For matrix block type (if found):
-- UPDATE matrixtypes SET handle = 'obcCompliant2024_Submission' WHERE handle = '2024OBCCompliant_Submission';

-- For entry type (if found):
-- UPDATE entrytypes SET handle = 'obcCompliant2024_Submission' WHERE handle = '2024OBCCompliant_Submission';

-- For field (if found):
-- UPDATE fields SET handle = 'obcCompliant2024_Submission' WHERE handle = '2024OBCCompliant_Submission';

-- After updating, clear Craft CMS caches:
-- php craft clear-caches/all
-- php craft project-config/rebuild
