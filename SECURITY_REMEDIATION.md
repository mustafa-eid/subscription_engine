# Security Incident Remediation

## Issue: Laravel APP_KEY Exposed on GitHub

**Detected by:** GitGuardian  
**Date:** April 6, 2026  
**Secret Type:** Laravel APP_KEY

### What Happened
The Laravel APP_KEY was accidentally committed to the repository in:
- `.env.example` file
- `.github/workflows/ci-cd.yml` workflow file

### Immediate Actions Taken

1. ✅ **Generated new APP_KEY**: The APP_KEY in `.env` has been rotated
2. ✅ **Removed from `.env.example`**: APP_KEY value is now empty (placeholder only)
3. ✅ **Removed from CI/CD workflow**: Now uses `${{ secrets.APP_KEY }}` instead of hardcoded value
4. ✅ **Updated `.gitignore`**: Ensured `.env` files are properly excluded

### Required Actions

#### 1. Add GitHub Secret

You need to add the APP_KEY as a GitHub repository secret:

1. Go to: https://github.com/mustafa-eid/subscription_engine/settings/secrets/actions
2. Click "New repository secret"
3. Name: `APP_KEY`
4. Value: `base64:Q3zsygEmuclEyp/pzISpd2w7tppxkVFy0QA/ZtWXRPI=`
5. Click "Add secret"

#### 2. Remove APP_KEY from Git History

**IMPORTANT**: The old APP_KEY still exists in your Git history. You need to remove it:

```bash
# Install BFG Repo-Cleaner (if not already installed)
# Using Homebrew: brew install bfg
# Or download from: https://rtyley.github.io/bfg-repo-cleaner/

# Clone a fresh copy of your repo (bare mirror)
git clone --mirror git@github.com:mustafa-eid/subscription_engine.git

# Run BFG to remove the old APP_KEY
cd subscription_engine.git
bfg --replace-text passwords.txt

# Where passwords.txt contains:
# base64:wp+z+gBCMqVDOOC4gcc/MLdaRm4oTMeAPT0goJ4hDDM==>REMOVED_SECRET

# Force push the cleaned history
git push --force --mirror

# Clean up
cd ..
rm -rf subscription_engine.git
```

**Alternative using git filter-repo:**

```bash
# Install git-filter-repo
pip install git-filter-repo

# In your repo
git filter-repo --invert-paths --path .env.example --force

# Then force push
git push --force --all
git push --force --tags
```

#### 3. Invalidate Old APP_KEY

Since the old APP_KEY was exposed:
- Any encrypted data using the old key cannot be decrypted with the new key
- If you were using the old key in production, you'll need to:
  1. Re-encrypt all data with the new key, OR
  2. Keep the old key for decrypting old data (not recommended if truly compromised)

#### 4. Monitor for Unauthorized Access

- Check your application logs for any suspicious activity
- Monitor for any unauthorized password reset attempts
- Review user sessions and invalidate if necessary

### Prevention

To prevent future secret exposure:

1. **Use pre-commit hooks**: Install tools like `git-secrets` or `detect-secrets`
2. **Review PRs carefully**: Check for any accidental secret commits
3. **Use environment variables**: Never commit `.env` files
4. **Use GitHub Secrets**: For CI/CD pipelines
5. **Enable GitGuardian**: Continue monitoring for exposed secrets

### References

- [GitGuardian Secret Remediation Guide](https://github.com/GitGuardian/ggshield)
- [Laravel Encryption Documentation](https://laravel.com/docs/encryption)
- [GitHub Actions Secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets)
