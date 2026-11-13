# Preparing a release

For each version:

- Summarise the problem addressed, the change made and any known issues.
- Run the PHP and JavaScript syntax checks.
- Check checkout and paid actions with the relevant JetFormBuilder and PHP versions.
- Exercise callback timing, duplicate notifications and payment recovery when those paths change.
- Keep credentials and customer data out of examples and downloads.
- Retain the bundled dependency licenses.
- Tag the matching source and provide release notes.

## Current follow-up work

The maintainer reports successful live-payment testing. Further work covers the callback and verification findings listed in the README, a wider compatibility test matrix, and choosing a license for the project's own code.
