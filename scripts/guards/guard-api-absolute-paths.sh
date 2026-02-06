#!/bin/bash
# Guard: Ensure all API files use absolute paths for require statements
# Usage: ./scripts/guards/guard-api-absolute-paths.sh

set -e

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$REPO_ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🔍 Checking for relative require paths in API files..."

# Check for relative paths without __DIR__
ISSUES_FOUND=0

# Pattern 1: require '../ or require './
if grep -r "require.*'\.\./" api/ --include="*.php" | grep -v "__DIR__" > /dev/null 2>&1; then
    echo -e "${RED}❌ Found relative require paths with single quotes:${NC}"
    grep -rn "require.*'\.\./" api/ --include="*.php" | grep -v "__DIR__"
    ISSUES_FOUND=1
fi

if grep -r "require.*'\./" api/ --include="*.php" | grep -v "__DIR__" | grep -v "'/config.php'" > /dev/null 2>&1; then
    echo -e "${RED}❌ Found relative require paths with ./ pattern:${NC}"
    grep -rn "require.*'\./" api/ --include="*.php" | grep -v "__DIR__" | grep -v "'/config.php'"
    ISSUES_FOUND=1
fi

# Pattern 2: require "../ or require "./
if grep -r 'require.*"\.\.' api/ --include="*.php" | grep -v "__DIR__" > /dev/null 2>&1; then
    echo -e "${RED}❌ Found relative require paths with double quotes:${NC}"
    grep -rn 'require.*"\.\.' api/ --include="*.php" | grep -v "__DIR__"
    ISSUES_FOUND=1
fi

if grep -r 'require.*"\.\/' api/ --include="*.php" | grep -v "__DIR__" | grep -v '"/config.php"' > /dev/null 2>&1; then
    echo -e "${RED}❌ Found relative require paths with ./ pattern:${NC}"
    grep -rn 'require.*"\.\/' api/ --include="*.php" | grep -v "__DIR__" | grep -v '"/config.php"'
    ISSUES_FOUND=1
fi

if [ $ISSUES_FOUND -eq 1 ]; then
    echo ""
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}GUARD FAILED: Relative paths detected in API files${NC}"
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${YELLOW}Why this matters:${NC}"
    echo "  When router.php includes API files via CGI, relative paths fail"
    echo "  because the current working directory is the project root, not api/"
    echo ""
    echo -e "${YELLOW}How to fix:${NC}"
    echo "  Replace:  require_once '../includes/file.php';"
    echo "  With:     require_once __DIR__ . '/../includes/file.php';"
    echo ""
    echo "  Replace:  require_once './config.php';"
    echo "  With:     require_once __DIR__ . '/config.php';"
    echo ""
    exit 1
fi

echo -e "${GREEN}✅ All API files use absolute paths${NC}"
exit 0
