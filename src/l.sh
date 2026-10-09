#!/bin/bash

# Visual color anchors for scannability
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}===============================================${NC}"
echo -e "${CYAN}      RUNNING RECURSIVE PHP SYNTAX LINTER      ${NC}"
echo -e "${CYAN}===============================================${NC}\n"

PASSED_COUNT=0
FAILED_COUNT=0
TOTAL_COUNT=0

# Recursively locate and lint all PHP files from the current folder downward
while IFS= read -r -d '' file; do
    ((TOTAL_COUNT++))
    
    # Run the native PHP linter silently to capture output data cleanly
    LINT_OUTPUT=$(php -l "$file" 2>&1)
    
    if [ $? -eq 0 ]; then
        ((PASSED_COUNT++))
        echo -e "  [${GREEN}OK${NC}]   $file"
    else
        ((FAILED_COUNT++))
        echo -e "  [${RED}FAIL${NC}] $file"
        echo -e "${YELLOW}         ↳ $LINT_OUTPUT${NC}\n"
    fi
done < <(find . -type f -name "*.php" -print0)

echo -e "\n${CYAN}===============================================${NC}"
echo -e "${CYAN}FINAL ANALYSIS SUMMARY:${NC}"
echo -e "  • Total Files Scanned: $TOTAL_COUNT"
echo -e "  • Passed Validation:   ${GREEN}$PASSED_COUNT${NC}"
echo -e "  • Failed Validation:   $( [ $FAILED_COUNT -gt 0 ] && echo -e "${RED}$FAILED_COUNT${NC}" || echo -e "${GREEN}0${NC}" )"
echo -e "${CYAN}===============================================${NC}"

# Exit gracefully with an error code if any compilation bugs are detected
if [ $FAILED_COUNT -gt 0 ]; then
    exit 1
else
    exit 0
fi