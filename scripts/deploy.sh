#!/bin/bash
#
# MCP Wrapper - Automated Deployment Script
# Version: v2.7.0
# 
# This script automates the deployment of MCP Wrapper updates
# Usage: ./deploy-mcp-wrapper.sh [--skip-backup] [--force]
#

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="rocket-park/mcp-wrapper"
BACKUP_DIR="storage/backups/mcp-wrapper"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Flags
SKIP_BACKUP=false
FORCE_UPDATE=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --skip-backup)
            SKIP_BACKUP=true
            ;;
        --force)
            FORCE_UPDATE=true
            ;;
        *)
            echo "Unknown argument: $arg"
            echo "Usage: $0 [--skip-backup] [--force]"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}MCP Wrapper Deployment Script v2.7.0${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if we're in a Craft project
if [ ! -f "craft" ]; then
    echo -e "${RED}Error: Not in a Craft CMS project directory${NC}"
    echo "Please run this script from the Craft project root"
    exit 1
fi

# Check if mcp-wrapper is installed
if ! composer show $PLUGIN_NAME &>/dev/null; then
    echo -e "${RED}Error: $PLUGIN_NAME is not installed${NC}"
    echo "Install it first with: composer require $PLUGIN_NAME"
    exit 1
fi

# Get current version
CURRENT_VERSION=$(composer show $PLUGIN_NAME | grep "^versions" | awk '{print $3}')
echo -e "Current version: ${YELLOW}$CURRENT_VERSION${NC}"
echo ""

# Step 1: Backup (unless skipped)
if [ "$SKIP_BACKUP" = false ]; then
    echo -e "${GREEN}[1/8]${NC} Creating backups..."
    
    mkdir -p "$BACKUP_DIR/$TIMESTAMP"
    
    # Backup database
    echo "  → Backing up database..."
    php craft db/backup --overwrite || {
        echo -e "${RED}Database backup failed${NC}"
        exit 1
    }
    
    # Backup config
    if [ -f "config/mcpwrapper.php" ]; then
        echo "  → Backing up config..."
        cp config/mcpwrapper.php "$BACKUP_DIR/$TIMESTAMP/mcpwrapper.php.backup"
    fi
    
    # Backup vendor code
    if [ -d "vendor/rocket-park/mcp-wrapper" ]; then
        echo "  → Backing up vendor code..."
        cp -r vendor/rocket-park/mcp-wrapper "$BACKUP_DIR/$TIMESTAMP/mcp-wrapper.backup"
    fi
    
    echo -e "  ${GREEN}✓${NC} Backups created in $BACKUP_DIR/$TIMESTAMP"
else
    echo -e "${YELLOW}[1/8]${NC} Skipping backups (--skip-backup flag)"
fi
echo ""

# Step 2: Update composer package
echo -e "${GREEN}[2/8]${NC} Updating composer package..."
if [ "$FORCE_UPDATE" = true ]; then
    composer update $PLUGIN_NAME --no-cache || {
        echo -e "${RED}Composer update failed${NC}"
        exit 1
    }
else
    composer update $PLUGIN_NAME || {
        echo -e "${RED}Composer update failed${NC}"
        exit 1
    }
fi

NEW_VERSION=$(composer show $PLUGIN_NAME | grep "^versions" | awk '{print $3}')
echo -e "  ${GREEN}✓${NC} Updated to version: ${YELLOW}$NEW_VERSION${NC}"
echo ""

# Step 3: Clear Craft caches
echo -e "${GREEN}[3/8]${NC} Clearing Craft caches..."
php craft clear-caches/all || {
    echo -e "${YELLOW}Warning: Some caches failed to clear${NC}"
}
echo -e "  ${GREEN}✓${NC} Craft caches cleared"
echo ""

# Step 4: Clear MCP manifest cache
echo -e "${GREEN}[4/8]${NC} Clearing MCP manifest cache..."
if [ -d "storage/runtime/mcp" ]; then
    rm -rf storage/runtime/mcp/manifest-*.json
    echo -e "  ${GREEN}✓${NC} MCP manifest cache cleared"
else
    echo -e "  ${YELLOW}!${NC} MCP cache directory not found (first deployment?)"
fi
echo ""

# Step 5: Apply project config
echo -e "${GREEN}[5/8]${NC} Syncing project config..."
php craft project-config/apply || {
    echo -e "${YELLOW}Warning: Project config apply had issues${NC}"
}
echo -e "  ${GREEN}✓${NC} Project config synced"
echo ""

# Step 6: Verify GraphQL schemas
echo -e "${GREEN}[6/8]${NC} Verifying GraphQL schemas..."
SCHEMA_COUNT=$(php craft graphql/list-schemas | grep -c "Handle:" || true)
if [ "$SCHEMA_COUNT" -gt 0 ]; then
    echo -e "  ${GREEN}✓${NC} Found $SCHEMA_COUNT GraphQL schema(s)"
else
    echo -e "  ${YELLOW}!${NC} No GraphQL schemas found"
fi
echo ""

# Step 7: Test health endpoint
echo -e "${GREEN}[7/8]${NC} Testing health endpoint..."
# Try to detect site URL
if [ -f ".env" ]; then
    SITE_URL=$(grep "^PRIMARY_SITE_URL=" .env | cut -d '=' -f2 | tr -d '"')
    if [ -n "$SITE_URL" ]; then
        # Remove trailing slash
        SITE_URL=${SITE_URL%/}
        
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/mcp/health" || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            echo -e "  ${GREEN}✓${NC} Health endpoint responding (HTTP $HTTP_CODE)"
        else
            echo -e "  ${YELLOW}!${NC} Health endpoint returned HTTP $HTTP_CODE (expected 200)"
            echo "    URL tested: $SITE_URL/mcp/health"
        fi
    else
        echo -e "  ${YELLOW}!${NC} Could not determine site URL from .env"
    fi
else
    echo -e "  ${YELLOW}!${NC} .env file not found, skipping health check"
fi
echo ""

# Step 8: Rebuild manifest
echo -e "${GREEN}[8/8]${NC} Rebuilding manifests..."
if [ -n "$SITE_URL" ]; then
    # Get schema handles from config
    if [ -f "config/mcpwrapper.php" ]; then
        SCHEMAS=$(php -r "include 'config/mcpwrapper.php'; if(is_array(\$config = include 'config/mcpwrapper.php')) { foreach(\$config['schemas'] ?? [] as \$handle => \$token) { echo \$handle . ' '; } }")
        
        if [ -n "$SCHEMAS" ]; then
            for SCHEMA in $SCHEMAS; do
                HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/mcp/manifest/$SCHEMA?force=1" || echo "000")
                if [ "$HTTP_CODE" = "200" ]; then
                    echo -e "  ${GREEN}✓${NC} Rebuilt manifest for schema: $SCHEMA"
                else
                    echo -e "  ${YELLOW}!${NC} Failed to rebuild manifest for $SCHEMA (HTTP $HTTP_CODE)"
                fi
            done
        else
            echo -e "  ${YELLOW}!${NC} No schemas configured in mcpwrapper.php"
        fi
    fi
else
    echo -e "  ${YELLOW}!${NC} Skipping manifest rebuild (site URL unknown)"
fi
echo ""

# Summary
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Version: ${YELLOW}$CURRENT_VERSION${NC} → ${GREEN}$NEW_VERSION${NC}"
if [ "$SKIP_BACKUP" = false ]; then
    echo -e "Backups: ${YELLOW}$BACKUP_DIR/$TIMESTAMP${NC}"
fi
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Review configuration: config/mcpwrapper.php"
echo "2. Verify deployment: ./verify-deployment.sh"
echo "3. Check dashboard: /admin/utilities/mcp-analytics"
echo "4. Monitor logs: tail -f storage/logs/mcp-requests.log"
echo ""

# Check if verification script exists
if [ -f "verify-deployment.sh" ]; then
    read -p "Run verification checks now? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        ./verify-deployment.sh
    fi
else
    echo -e "${YELLOW}Tip:${NC} Create verify-deployment.sh for automated verification"
fi
