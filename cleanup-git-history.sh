#!/bin/bash

# Git History Cleanup Script
# This script removes the exposed APP_KEY from Git history

set -e

echo "🔒 Git History Cleanup Script"
echo "================================"
echo ""
echo "This will remove the exposed Laravel APP_KEY from your Git history."
echo ""

# The exposed secret
OLD_KEY="base64:wp+z+gBCMqVDOOC4gcc/MLdaRm4oTMeAPT0goJ4hDDM="

echo "⚠️  IMPORTANT: Before running this script:"
echo "1. Make sure you have added the new APP_KEY to GitHub Secrets"
echo "   Go to: https://github.com/mustafa-eid/subscription_engine/settings/secrets/actions"
echo "   Secret name: APP_KEY"
echo "   Secret value: base64:Q3zsygEmuclEyp/pzISpd2w7tppxkVFy0QA/ZtWXRPI="
echo ""
echo "2. This will rewrite Git history and require a force push"
echo "3. All collaborators will need to re-clone the repository"
echo ""

read -p "Do you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Aborted"
    exit 1
fi

echo ""
echo "📋 Checking for required tools..."

# Check if git-filter-repo is available
if ! command -v git-filter-repo &> /dev/null; then
    echo "⚠️  git-filter-repo not found. Installing..."
    pip3 install git-filter-repo --user
fi

echo ""
echo "🧹 Cleaning Git history..."

# Create a temporary file with the replacement
REPLACEMENT_FILE=$(mktemp)
echo "${OLD_KEY}==>REMOVED_SECRET" > "$REPLACEMENT_FILE"

# Use git filter-repo to remove the key from all files
git filter-repo --replace-text "$REPLACEMENT_FILE" --force

# Clean up temp file
rm "$REPLACEMENT_FILE"

echo ""
echo "✅ Git history cleaned successfully!"
echo ""
echo "📤 Now you need to force push to GitHub:"
echo "   git push --force --all"
echo "   git push --force --tags"
echo ""
echo "⚠️  WARNING: This will rewrite history on GitHub!"
echo "   All collaborators must re-clone the repository."
echo ""
echo "📝 After pushing, you should also:"
echo "   1. Close the GitGuardian alert"
echo "   2. Monitor for any unauthorized access"
echo "   3. Check if the old key was used in production"
echo ""
