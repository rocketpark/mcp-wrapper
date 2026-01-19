# Field Data Privacy & Exclusion Guide

## The Problem

Your bot is displaying sensitive information like internal contact emails that should be kept private:

**Example from Anaheim Office:**
- Shows internal staff emails: `vlanda@jensenhughes.com`, `ktourney@jensenhughes.com`, etc.
- These should NOT be exposed to public chatbot users

---

## Solutions (Multiple Approaches)

### ✅ Recommended: Approach 1 - GraphQL Schema Configuration (Cleanest)

**What:** Configure your GraphQL schema in Craft CMS to exclude sensitive fields  
**Why:** Data never reaches the bot in the first place  
**Security:** Best - data is filtered at the source

#### How to Implement:

1. **Go to Craft CMS Admin Panel**
   - Navigate to: **GraphQL → Schemas**
   - Select your schema (e.g., "ai" or "jensenhughes")

2. **Configure Scope for Office Locations Section**
   - Find "Office Locations" (or whatever your section is called)
   - Click to expand field permissions

3. **Uncheck Sensitive Fields**
   - ✅ Keep: `title`, `address`, `phone`, `services`, `city`, `state`
   - ❌ Uncheck: `contactEmails`, `internalContacts`, `staffEmails`

4. **Save Schema**

**Result:** When the bot queries for office data, it simply won't receive email fields at all.

#### Testing:
```graphql
# This query will no longer return contactEmails field
query {
  officeLocations {
    title
    address
    phone
    contactEmails  # ← This will return null or be undefined
  }
}
```

---

### Approach 2 - Bot Instructions (Quick Fix)

**What:** Tell the bot not to display certain fields  
**Why:** Fast to implement, no code changes  
**Security:** Medium - data is still accessible but bot won't display it

#### Implementation:

Add this to your Botpress Autonomous Node instructions:

```markdown
## Privacy & Data Protection Rules

### NEVER Display These Fields:
- Email addresses (any field containing @)
- Internal contact information
- Staff emails or personal contact details
- Phone numbers marked as "internal" or "direct"

### When Displaying Office Information:
✅ DO show:
- Office address
- Main office phone number (if marked as public)
- Office services
- General office hours

❌ DO NOT show:
- Individual staff emails
- Direct contact emails
- Internal phone extensions
- Personal contact information

### How to Handle Contact Requests:
Instead of showing emails, respond with:
"For inquiries about the [City] office, please use our general contact form 
at [contact page URL] or call our main office at [main number]."
```

**Pros:** 
- Quick to implement
- No code changes needed

**Cons:**
- Data is still technically accessible to the bot
- Relies on bot following instructions (could be bypassed)

---

### Approach 3 - MCP Configuration (Code-Based Filtering)

**What:** Add field blacklist to MCP wrapper config  
**Why:** Centralized control, applies to all queries  
**Security:** High - data is filtered before reaching the bot

#### Implementation:

1. **Update `config/mcpwrapper.php`:**

```php
<?php

return [
    'schemas' => [
        'ai' => getenv('GQL_AI_TOKEN'),
        'frontend' => getenv('GQL_FRONTEND_TOKEN'),
    ],
    
    'security' => [
        'allowedIps' => [
            '127.0.0.1',
            '::1',
        ],
        'requireAuth' => true,
        'enableDangerousTools' => false,
        'disabledTools' => [],
    ],
    
    // NEW: Field exclusion rules
    'privacy' => [
        'excludedFields' => [
            // Global exclusions (apply to all sections)
            'internalNotes',
            'adminOnly',
            'privateData',
            
            // Contact information
            'contactEmails',
            'staffEmails',
            'internalContacts',
            'directPhone',
            'personalEmail',
            
            // Sensitive data
            'ssn',
            'taxId',
            'bankInfo',
            'password',
            'apiKey',
        ],
        
        // Section-specific exclusions
        'sectionExclusions' => [
            'officeLocations' => [
                'contactEmails',
                'internalContacts',
                'staffDirectory',
            ],
            'teamMembers' => [
                'personalEmail',
                'directPhone',
                'homeAddress',
            ],
        ],
    ],
];
```

2. **Create Field Filter Service:**

Create new file: `src/services/FieldFilterService.php`

```php
<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;

class FieldFilterService extends Component
{
    /**
     * Filter sensitive fields from GraphQL response data
     */
    public function filterSensitiveData(array $data, ?string $section = null): array
    {
        $config = Craft::$app->config->getConfigFromFile('mcpwrapper');
        $excludedFields = $config['privacy']['excludedFields'] ?? [];
        
        // Add section-specific exclusions
        if ($section && isset($config['privacy']['sectionExclusions'][$section])) {
            $excludedFields = array_merge(
                $excludedFields,
                $config['privacy']['sectionExclusions'][$section]
            );
        }
        
        return $this->recursiveFilter($data, $excludedFields);
    }
    
    /**
     * Recursively filter fields from nested data
     */
    private function recursiveFilter(array $data, array $excludedFields): array
    {
        foreach ($data as $key => $value) {
            // Remove excluded fields
            if (in_array($key, $excludedFields, true)) {
                unset($data[$key]);
                continue;
            }
            
            // Recursively filter nested arrays
            if (is_array($value)) {
                $data[$key] = $this->recursiveFilter($value, $excludedFields);
            }
        }
        
        return $data;
    }
    
    /**
     * Check if a field should be excluded
     */
    public function isFieldExcluded(string $fieldHandle, ?string $section = null): bool
    {
        $config = Craft::$app->config->getConfigFromFile('mcpwrapper');
        $excludedFields = $config['privacy']['excludedFields'] ?? [];
        
        if ($section && isset($config['privacy']['sectionExclusions'][$section])) {
            $excludedFields = array_merge(
                $excludedFields,
                $config['privacy']['sectionExclusions'][$section]
            );
        }
        
        return in_array($fieldHandle, $excludedFields, true);
    }
}
```

3. **Register Service in Plugin:**

Update `src/McpWrapper.php`:

```php
public function init()
{
    parent::init();
    
    // ... existing code ...
    
    $this->setComponents([
        'manifestBuilder' => ManifestBuilderService::class,
        'mcpServer' => McpServerService::class,
        'toolRegistry' => ToolRegistryService::class,
        'promptRegistry' => PromptRegistryService::class,
        'resourceRegistry' => ResourceRegistryService::class,
        'fieldFilter' => FieldFilterService::class, // NEW
    ]);
}
```

4. **Use in GraphQL Queries:**

Update `src/services/McpServerService.php` to filter responses:

```php
private function executeGraphQL(string $query, array $variables = []): array
{
    // ... existing GraphQL execution code ...
    
    $result = $gql->executeQuery($schema, $query, null, null, $variables);
    $data = $result->toArray();
    
    // NEW: Filter sensitive fields
    $fieldFilter = Craft::$app->getModule('mcpwrapper')->get('fieldFilter');
    $data = $fieldFilter->filterSensitiveData($data);
    
    return $data;
}
```

---

### Approach 4 - Data Transformation Layer

**What:** Transform data before sending to bot, replacing sensitive info with safe alternatives  
**Why:** Maintains data structure but sanitizes content  
**Security:** High

#### Example Transformation:

```php
// In your field filter or tool
private function sanitizeOfficeData(array $office): array
{
    // Replace individual emails with general contact
    if (isset($office['contactEmails'])) {
        $office['contactEmails'] = ['info@jensenhughes.com']; // General contact
    }
    
    // Replace direct phones with main number
    if (isset($office['directPhone'])) {
        unset($office['directPhone']);
        $office['phone'] = $office['mainPhone'] ?? null;
    }
    
    // Add contact CTA instead
    $office['contactInfo'] = [
        'message' => 'For inquiries, please use our contact form or call our main office.',
        'contactFormUrl' => 'https://jensenhughes.com/contact',
        'mainPhone' => $office['mainPhone'] ?? null,
    ];
    
    return $office;
}
```

---

## Recommended Implementation Strategy

### Phase 1: Immediate (Quick Fix) ✅
**Use Approach 1 (GraphQL Schema Configuration)**
- Takes 5 minutes
- Secure and effective
- No code changes needed

**Steps:**
1. Go to Craft CMS → GraphQL → Schemas → [Your Schema]
2. Expand "Office Locations" section
3. Uncheck "contactEmails" and any other sensitive fields
4. Save
5. Test bot - emails should no longer appear

---

### Phase 2: Long-term (Robust Solution) ✅
**Implement Approach 3 (Code-Based Filtering)**
- Centralized configuration
- Applies to all data sources
- Easy to maintain

**Benefits:**
- Add new exclusions without touching code
- Section-specific rules
- Audit trail of what's excluded

---

## Bot Instruction Update

**Add this section to your Autonomous Node instructions:**

```markdown
## Privacy & Contact Information Rules

### Contact Information Display Policy

**NEVER display:**
- Individual staff email addresses
- Internal/direct contact emails  
- Personal phone numbers
- Any email addresses except general company contacts

**When users ask for contact information:**

❌ DON'T say:
"Contact vlanda@jensenhughes.com or ktourney@jensenhughes.com"

✅ DO say:
"For inquiries about the Anaheim office, please:
• Visit our contact form: [link]
• Call the main office: [main phone]
• Email: info@jensenhughes.com"

**Office Information - Safe to Display:**
✅ Office address
✅ Main office phone (public-facing)
✅ Office hours
✅ Services offered at that location
✅ General email (info@, hello@, contact@)

**Office Information - DO NOT Display:**
❌ Individual staff emails
❌ Direct/internal phone extensions
❌ Personal contact details
❌ Private notes or internal communications
```

---

## Testing Your Privacy Controls

### Test Queries:

```bash
# 1. Query for office with sensitive fields
curl -X POST http://localhost/mcp/ai \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"tools/call",
    "params":{
      "name":"query_officeLocations",
      "arguments":{"search":"Anaheim"}
    },
    "id":1
  }'
```

**Expected Result:**
- ✅ Should show: address, phone, services
- ❌ Should NOT show: contactEmails, staffEmails

### Test Bot Conversation:

**Test 1:**
- User: "Give me the contact info for Anaheim office"
- Expected: General contact form/phone, NOT individual emails

**Test 2:**
- User: "Who should I email about fire protection in Anaheim?"
- Expected: General contact info, NOT staff emails

**Test 3:**
- User: "What's the email for Anaheim?"
- Expected: General company email or contact form, NOT staff directory

---

## Configuration Examples

### Conservative (Most Private):
```php
'excludedFields' => [
    // All contact fields
    'email', 'contactEmail', 'staffEmail', 'internalEmail',
    'phone', 'mobile', 'directPhone', 'extension',
    
    // All personal data
    'personalInfo', 'homeAddress', 'ssn', 'dob',
    
    // All internal fields
    'internalNotes', 'privateNotes', 'adminOnly',
],
```

### Balanced (Recommended):
```php
'excludedFields' => [
    // Individual contacts only
    'contactEmails', 'staffEmails', 'directPhone',
    
    // Sensitive data
    'internalNotes', 'privateData', 'adminOnly',
],

'sectionExclusions' => [
    'officeLocations' => ['contactEmails', 'staffDirectory'],
    'teamMembers' => ['personalEmail', 'directPhone'],
],
```

### Minimal (Allow Most Data):
```php
'excludedFields' => [
    // Critical data only
    'password', 'apiKey', 'ssn', 'taxId',
    'internalNotes', 'adminOnly',
],
```

---

## Quick Implementation Checklist

- [ ] **Step 1:** Update GraphQL schema to exclude `contactEmails` field (5 min)
- [ ] **Step 2:** Add privacy section to bot instructions (10 min)
- [ ] **Step 3:** Test bot with "contact info" questions (5 min)
- [ ] **Step 4:** (Optional) Implement code-based filtering for long-term (2-3 hours)
- [ ] **Step 5:** Document which fields are excluded (10 min)
- [ ] **Step 6:** Train team on privacy policy (15 min)

---

## Summary

**Problem:** Bot exposing internal contact emails  
**Immediate Fix:** Configure GraphQL schema to exclude sensitive fields (5 minutes)  
**Long-term Solution:** Add field filtering service with config-based exclusions  
**Additional Safety:** Update bot instructions to avoid displaying personal contact info

**Recommended Action:** Start with GraphQL schema configuration (Approach 1) today, then implement code-based filtering (Approach 3) for comprehensive protection.

