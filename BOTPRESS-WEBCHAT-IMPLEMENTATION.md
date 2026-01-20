# Botpress Webchat Implementation Plan
## Jensen Hughes Website Integration

### Overview
This document outlines the step-by-step process to add the Botpress AI chatbot to the Jensen Hughes website as a webchat widget.

---

## Implementation Options

### Option 1: Floating Chat Bubble (RECOMMENDED)
**Best for:** General website visitors, unobtrusive, always accessible

**Pros:**
- ✅ Quick to implement (copy/paste embed code)
- ✅ Non-intrusive - appears as small bubble in corner
- ✅ Works across all pages automatically
- ✅ Users can open/close on demand
- ✅ Industry standard UX pattern
- ✅ No custom styling required initially

**Cons:**
- Limited control over appearance and position
- Fixed behavior (bubble toggle)

---

### Option 2: Embedded in Specific Element
**Best for:** Dedicated "Contact Us" or "Help" page with always-visible chat

**Pros:**
- ✅ Full control over placement and size
- ✅ Can be integrated into specific page layouts
- ✅ No bubble UI - direct chat interface
- ✅ Can have multiple instances on different pages

**Cons:**
- Requires more setup (HTML element + configuration)
- Not accessible from every page unless added to layout
- Requires custom styling to match site design

---

## Recommended Approach: Floating Chat Bubble

For Jensen Hughes, **Option 1 (Floating Chat Bubble)** is recommended because:
1. Fastest implementation - add once, works everywhere
2. Users familiar with this pattern from other websites
3. Doesn't disrupt existing page layouts
4. Can be styled to match brand colors later
5. Accessible from any page on the site

---

## Step-by-Step Implementation

### Step 1: Get Your Botpress Embed Code

1. **Navigate to Botpress Dashboard:**
   - Go to: https://studio.botpress.cloud/
   - Log in to your workspace
   - Select the **"Jensen Hughes AI Helper"** bot

2. **Access Webchat Settings:**
   - In the left sidebar, click **Integrations**
   - Find and click **Webchat**
   - Click **Deploy Settings** or **Configuration**

3. **Copy the Embed Code:**
   - Look for the **"Embed code"** section
   - The code will look something like this:
   
   ```html
   <script src="https://cdn.botpress.cloud/webchat/v3/webchat.js"></script>
   <script>
     window.botpress = {
       webchatConfig: {
         botId: 'YOUR_BOT_ID_HERE',
         clientId: 'YOUR_CLIENT_ID_HERE'
       }
     };
   </script>
   ```
   
   - Click **Copy** to copy the full embed code
   - **IMPORTANT:** Keep this code safe - it contains your bot's unique identifiers

---

### Step 2: Add Embed Code to Website

**File to Edit:** `/Users/elizabethstein/Herd/jensenhughes/templates/_meta_header.twig`

**Where to Add:** Just before the closing `</head>` tag (around line 50-59)

**Implementation:**

1. **Open the file:**
   ```bash
   code /Users/elizabethstein/Herd/jensenhughes/templates/_meta_header.twig
   ```

2. **Add the Botpress embed code:**
   
   Add this at the end of the file (before the final `%}`):

   ```twig
   {# Botpress AI Chatbot #}
   {% if not craft.app.config.general.devMode %}
       {# Production: Add Botpress webchat #}
       <script src="https://cdn.botpress.cloud/webchat/v3/webchat.js"></script>
       <script>
         window.botpress = {
           webchatConfig: {
             botId: 'YOUR_BOT_ID_HERE',
             clientId: 'YOUR_CLIENT_ID_HERE',
             hostUrl: 'https://cdn.botpress.cloud/webchat/v3',
             messagingUrl: 'https://messaging.botpress.cloud',
             // Optional: Customize appearance
             stylesheet: '/assets/css/botpress-custom.css', // If you create custom styles
             closeOnEscape: true,
             showBotInfoPage: true,
             useSessionStorage: true
           }
         };
       </script>
   {% else %}
       {# Dev Mode: Add Botpress with dev settings #}
       <script src="https://cdn.botpress.cloud/webchat/v3/webchat.js"></script>
       <script>
         window.botpress = {
           webchatConfig: {
             botId: 'YOUR_BOT_ID_HERE',
             clientId: 'YOUR_CLIENT_ID_HERE',
             hostUrl: 'https://cdn.botpress.cloud/webchat/v3',
             messagingUrl: 'https://messaging.botpress.cloud'
           }
         };
       </script>
   {% endif %}
   ```

3. **Replace placeholders:**
   - Replace `YOUR_BOT_ID_HERE` with actual bot ID from Botpress
   - Replace `YOUR_CLIENT_ID_HERE` with actual client ID from Botpress

---

### Step 3: Customize Appearance (Optional but Recommended)

**Create Custom Stylesheet:** `/Users/elizabethstein/Herd/jensenhughes/web/assets/css/botpress-custom.css`

```css
/* Botpress Webchat Custom Styles for Jensen Hughes */

/* Position the chat bubble */
#bp-web-widget {
  bottom: 20px !important;
  right: 20px !important;
}

/* Brand colors - Jensen Hughes Orange */
#bp-web-widget .bpw-header-container {
  background-color: #e57200 !important; /* Jensen Hughes orange */
}

/* Chat bubble color */
#bp-web-widget .bpw-floating-button {
  background-color: #e57200 !important;
}

#bp-web-widget .bpw-floating-button:hover {
  background-color: #cc6600 !important;
}

/* User message bubbles */
#bp-web-widget .bpw-from-user .bpw-chat-bubble {
  background-color: #e57200 !important;
}

/* Bot message bubbles - keep default or customize */
#bp-web-widget .bpw-from-bot .bpw-chat-bubble {
  background-color: #f5f5f5 !important;
  color: #333333 !important;
}

/* Link colors */
#bp-web-widget a {
  color: #e57200 !important;
}

/* Button colors */
#bp-web-widget button {
  background-color: #e57200 !important;
  border-color: #e57200 !important;
}

#bp-web-widget button:hover {
  background-color: #cc6600 !important;
  border-color: #cc6600 !important;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  #bp-web-widget {
    bottom: 10px !important;
    right: 10px !important;
  }
}
```

---

### Step 4: Configure Botpress Studio Settings

1. **Open Botpress Studio:**
   - Go to your bot → Webchat → Deploy Settings

2. **Configure Display Settings:**
   - **Chat Interface:** Select **"Floating"** (not Embedded)
   - **Widget Position:** Bottom Right
   - **Welcome Message:** Enable and set to:
     ```
     Hi! I'm the Jensen Hughes AI Assistant. I can help you learn about our services, find office locations, and connect you with our experts. How can I assist you today?
     ```
   - **Avatar:** Upload Jensen Hughes logo
   - **Bot Name:** "Jensen Hughes Assistant"
   - **Primary Color:** `#e57200` (Jensen Hughes orange)

3. **Configure Privacy Settings:**
   - Enable **"Show privacy policy link"**
   - Set privacy policy URL: `https://www.jensenhughes.com/privacy-policy`

4. **Save Configuration:**
   - Click **"Save configuration"** at the bottom

---

### Step 5: Test the Implementation

#### Local Testing (Development Environment)

1. **Clear Craft CMS caches:**
   ```bash
   cd /Users/elizabethstein/Herd/jensenhughes
   php craft clear-caches/all
   ```

2. **Visit your local site:**
   ```bash
   open http://jensenhughes.test
   ```

3. **Check for chat bubble:**
   - Look for orange chat bubble in bottom-right corner
   - Click to open chat interface
   - Test basic queries:
     - "Where is your California office?"
     - "Tell me about fire protection services"
     - "Show me team members"

4. **Check browser console for errors:**
   - Open Developer Tools (F12)
   - Look for any JavaScript errors related to Botpress

#### Production Testing Checklist

1. **Deploy to Production:**
   ```bash
   cd /Users/elizabethstein/Herd/jensenhughes
   git add templates/_meta_header.twig web/assets/css/botpress-custom.css
   git commit -m "Add Botpress webchat widget to site"
   git push origin staging3  # Or your production branch
   ```

2. **Verify on Production:**
   - Visit: https://jensenhughes3.on-forge.com
   - Wait 30-60 seconds for deployment
   - Clear browser cache (Cmd+Shift+R / Ctrl+Shift+F5)
   - Look for chat bubble

3. **Test Functionality:**
   - Click chat bubble to open
   - Verify welcome message appears
   - Test office location queries
   - Test service queries
   - Test team member queries
   - **CRITICAL:** Test for privacy - verify NO email addresses appear

4. **Test Across Devices:**
   - Desktop Chrome/Safari/Firefox
   - Mobile Safari (iOS)
   - Mobile Chrome (Android)
   - Tablet devices

5. **Test Across Pages:**
   - Homepage
   - Services pages
   - Office locations pages
   - About pages
   - Contact page

---

## Troubleshooting

### Issue: Chat Bubble Not Appearing

**Possible Causes & Solutions:**

1. **Embed code not loaded:**
   - Check browser console for 404 errors
   - Verify script URL is correct: `https://cdn.botpress.cloud/webchat/v3/webchat.js`
   - Check network tab for failed requests

2. **Bot ID or Client ID incorrect:**
   - Double-check IDs in Botpress Dashboard
   - Ensure no extra spaces or characters
   - Re-copy embed code from Botpress

3. **Caching issues:**
   - Clear Craft CMS cache: `php craft clear-caches/all`
   - Clear browser cache: Cmd+Shift+R (Mac) or Ctrl+Shift+F5 (Windows)
   - Try incognito/private browsing window

4. **JavaScript conflicts:**
   - Check browser console for errors
   - Disable other plugins temporarily to test
   - Check for jQuery version conflicts

### Issue: Chat Opens but Shows Error

**Possible Causes & Solutions:**

1. **MCP integration not connected:**
   - Go to Botpress Studio → Integrations → MCP
   - Verify server URL: `https://jensenhughes3.on-forge.com/mcp/MCPSchema`
   - Test connection button
   - Check that token is valid

2. **Network/CORS issues:**
   - Verify Craft CMS site is publicly accessible
   - Check that MCP endpoint returns valid JSON
   - Test endpoint directly:
     ```bash
     curl -s "https://jensenhughes3.on-forge.com/mcp/MCPSchema" \
       -H "Content-Type: application/json" \
       -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
     ```

### Issue: Styling Not Applied

**Possible Causes & Solutions:**

1. **CSS file not loaded:**
   - Check that `/assets/css/botpress-custom.css` exists
   - Verify file path in embed code is correct
   - Check browser network tab for 404 errors

2. **CSS specificity issues:**
   - Add `!important` to custom styles if needed
   - Use more specific selectors
   - Check that Botpress classes haven't changed

3. **Caching:**
   - Force refresh: Cmd+Shift+R
   - Check if Mix versioning is affecting file path
   - Add `?v=1.0` to CSS filename to bypass cache

### Issue: Bot Responses Are Slow

**Possible Causes & Solutions:**

1. **MCP query performance:**
   - Check Craft CMS performance
   - Verify database queries are optimized
   - Check server resources (CPU, memory)

2. **Network latency:**
   - Test from different locations
   - Check Botpress Cloud status page
   - Verify no firewall blocking

3. **Large result sets:**
   - Review bot instructions for excessive data queries
   - Add `limit` parameters to queries
   - Optimize GraphQL queries in MCP wrapper

---

## Advanced Configuration Options

### Option: Embedded Chat (Alternative Implementation)

If you want to embed the chat directly on a specific page (e.g., Contact page):

1. **Set Element ID in Botpress:**
   - Go to Webchat → Deploy Settings
   - Under **Chat Interface**, select **"Embedded"**
   - Set **Element ID**: `bp-embedded-webchat`
   - Save configuration

2. **Add HTML element to template:**
   
   Edit: `/Users/elizabethstein/Herd/jensenhughes/templates/pages/_types/_contactPage.twig`
   
   ```html
   {# Embedded Botpress Chat #}
   <section class="contact-chat">
       <div class="container">
           <h2>Chat with Our AI Assistant</h2>
           <div id="bp-embedded-webchat" style="width: 100%; height: 600px;"></div>
       </div>
   </section>
   ```

3. **Update embed code:**
   - Add `embedded: true` to webchatConfig
   - Set `elementId: 'bp-embedded-webchat'`

---

## Monitoring & Analytics

### Track Chat Usage

1. **Botpress Analytics:**
   - Dashboard → Analytics
   - Monitor: Conversations, Messages, User engagement
   - Track: Most common queries, Success rate

2. **Google Analytics Integration:**
   - Add event tracking to Botpress
   - Track: Chat opens, Messages sent, Queries by type

### Performance Monitoring

1. **Page Load Impact:**
   - Monitor page load times before/after
   - Webchat should load asynchronously (no blocking)

2. **Bot Response Times:**
   - Track in Botpress Analytics
   - Monitor MCP endpoint response times

---

## Maintenance Checklist

### Weekly
- ✅ Review bot conversations for errors or confusion
- ✅ Check for any JavaScript console errors
- ✅ Verify MCP integration still working

### Monthly
- ✅ Review analytics for usage patterns
- ✅ Update bot instructions based on common questions
- ✅ Test critical flows (office search, service queries)
- ✅ Check for Botpress platform updates

### Quarterly
- ✅ Review and update sensitive field filtering
- ✅ Audit privacy compliance
- ✅ Update styling if brand guidelines change
- ✅ Performance optimization review

---

## Security Considerations

### Already Implemented ✅
- Field-level filtering for sensitive data
- No email addresses exposed
- No internal notes or admin comments
- MCP token authentication

### Additional Recommendations
1. **Rate Limiting:**
   - Consider adding rate limiting to MCP endpoint
   - Prevent abuse or excessive queries

2. **User Authentication (Optional):**
   - For logged-in users, can pass user context to bot
   - Allows personalized responses

3. **Content Security Policy:**
   - Ensure CSP headers allow Botpress CDN
   - Add to web server config if needed:
     ```
     script-src 'self' https://cdn.botpress.cloud;
     connect-src 'self' https://messaging.botpress.cloud;
     ```

---

## Next Steps

1. **Get embed code from Botpress:**
   - Log into Botpress Studio
   - Navigate to Webchat settings
   - Copy full embed code with bot ID and client ID

2. **Implement in local environment:**
   - Add code to `_meta_header.twig`
   - Test locally at http://jensenhughes.test
   - Verify functionality

3. **Create custom styles:**
   - Add `botpress-custom.css` file
   - Match Jensen Hughes branding
   - Test on multiple pages

4. **Deploy to production:**
   - Commit changes to git
   - Push to staging3 branch
   - Wait for auto-deploy
   - Test thoroughly

5. **Monitor and iterate:**
   - Watch analytics
   - Gather feedback from users
   - Refine bot instructions
   - Adjust styling as needed

---

## Support Resources

- **Botpress Documentation:** https://botpress.com/docs
- **Webchat Embed Guide:** https://botpress.com/docs/webchat/get-started
- **Craft CMS Twig Docs:** https://craftcms.com/docs/5.x/reference/twig
- **MCP Wrapper Repo:** https://github.com/rocketpark/mcp-wrapper

---

## Questions?

If you encounter issues not covered in this guide:

1. Check Botpress Studio logs (Logs tab)
2. Check browser console for JavaScript errors
3. Test MCP endpoint directly with curl
4. Review Craft CMS logs: `storage/logs/web.log`
5. Contact Botpress support if platform-related

---

**Document Version:** 1.0  
**Last Updated:** January 20, 2026  
**Author:** GitHub Copilot AI Assistant
