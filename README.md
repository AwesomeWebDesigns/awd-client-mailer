# AWD Client Mailer

A simple WordPress plugin for sending client emails (news, new services, openings) from WordPress through **SMTP2GO**, with Canada's Anti-Spam Legislation (CASL) handled for you.

Built by [AWD Digital](https://awddigital.ca/). Free version.

## What it does

- **Contacts** with tags, statuses (active, inactive, unsubscribed, bounced), optional consent fields, CSV import and export
- **Compose** emails in the WordPress editor, with `{first_name}` personalization (and fallbacks like `{first_name|there}`)
- **Plain or branded** style per email, with the pros and cons explained in the plugin
- **Test sends**, a review step showing exactly who will receive the email, and a CASL confirmation
- **One email per recipient**, sent gradually (default 20 per minute) through the SMTP2GO API
- **CASL footer** on every email: business name, mailing address, contact line, reason for receiving and an unsubscribe link
- **Unsubscribe** page plus RFC 8058 one-click unsubscribe headers (the "Unsubscribe" button in Gmail and Outlook)
- **SMTP2GO webhook**: hard bounces, spam complaints and unsubscribes update contacts automatically
- **Consent and activity log** per contact, kept even if the contact is deleted, so someone who unsubscribed is never re-added by mistake
- **Optional consent expiry**: automatically stop emailing implied-consent contacts 2 years after their last transaction (off by default)

## Requirements

- WordPress 6.0+
- PHP 7.4+
- An SMTP2GO account with a verified sender domain

## Setup (per client site)

1. Upload and activate the plugin.
2. In SMTP2GO, verify the sending domain (Sending > Verified Senders) and add the CNAME records to DNS.
3. Create an API key in SMTP2GO (Sending > API Keys) with permission to send email.
4. Go to **Client Mailer > Settings** and fill in:
   - API key (or define `AWDCM_SMTP2GO_API_KEY` in `wp-config.php`, recommended)
   - From name, from email (on the verified domain) and reply-to (the client's normal inbox)
   - Business name and mailing address (required by CASL)
   - Logo and brand colour for branded emails
5. In SMTP2GO, add the webhook address shown on the Settings page (Settings > Webhooks) and select the **bounce**, **spam** and **unsubscribe** events.
6. Check DNS: one SPF record covering all senders, DMARC (start with `p=none`), and DKIM in Microsoft 365 if the client uses Outlook.
7. Import contacts (Client Mailer > Contacts > Import), send a test to [mail-tester.com](https://www.mail-tester.com/), then send.

## Updates

Sites check this GitHub repository for new versions, which then show up in **Plugins** like any other update. Each plugin row also gets a "Check for updates" link.

**Releasing a new version**

1. Bump the version in `awd-client-mailer.php` (header and `AWDCM_VERSION`) and the `Stable tag` in `readme.txt`. Add a changelog entry.
2. Commit and push to `main`.
3. On GitHub, go to **Releases > Draft a new release**, create the tag `vX.Y.Z` (matching the version) on `main`, and publish.
4. The **Build release zip** workflow checks that the tag matches the version, builds `awd-client-mailer.zip` and attaches it to the release. Sites see the update within about 12 hours, or right away with "Check for updates".

Do not mark the release as a pre-release unless you want sites to ignore it.

**Private repository:** while this repository is private, each site needs a read-only token. Create a fine-grained personal access token limited to this repository with **Contents: Read-only**, then add it to the site's `wp-config.php`:

```php
define( 'AWDCM_GITHUB_TOKEN', 'github_pat_...' );
```

If the repository is made public, no token is needed.

## Configuration constants (wp-config.php)

```php
define( 'AWDCM_SMTP2GO_API_KEY', 'api-XXXXXXXX' ); // Keeps the key out of the database.
define( 'AWDCM_CAP', 'manage_options' );           // Capability required to use the plugin.
define( 'AWDCM_GITHUB_TOKEN', 'github_pat_...' );  // Only while the GitHub repository is private.
define( 'AWDCM_DISABLE_UPDATES', true );           // Turn off update checks on this site.
```

## Filters

```php
// Hide the AWD Digital "Looking to add more features?" banner.
add_filter( 'awdcm_show_upgrade_banner', '__return_false' );

// Change where the banner's "contact us" button points.
add_filter( 'awdcm_contact_url', fn() => 'https://awddigital.ca/contact/' );

// Adjust the SMTP2GO request payload before sending.
add_filter( 'awdcm_smtp2go_payload', function ( $payload ) { return $payload; } );
```

## How sending works

When an email is sent, the eligible recipients are copied into a queue. The queue is processed in batches:

- while the progress screen is open, through AJAX every few seconds
- in the background through WP-Cron, every minute

On low-traffic sites WP-Cron only runs when someone visits. For reliable background sending, set up a real cron job that calls `wp-cron.php` every minute and add `define( 'DISABLE_WP_CRON', true );`.

Each recipient is checked again just before sending, so anyone who unsubscribes mid-send is skipped. Temporary errors are retried up to 3 times. Errors that affect every recipient (invalid API key, unverified sender) pause the send so it can be fixed and resumed.

## Data and privacy

- Tables: `{prefix}awdcm_contacts`, `awdcm_tags`, `awdcm_contact_tags`, `awdcm_campaigns`, `awdcm_queue`, `awdcm_log`
- Nothing is exposed through the REST API except the webhook endpoint, which requires a secret key.
- Unsubscribe links use signed per-contact tokens (HMAC-SHA256).
- Data is kept on uninstall unless "Delete all data" is ticked in Settings.

## Security notes

- All admin screens require `manage_options` (or `AWDCM_CAP`), and every form and action uses a nonce.
- All database queries use prepared statements, and all output is escaped.
- CSV exports guard against spreadsheet formula injection.

## Changelog

### 1.0.1
- Automatic updates from the AWD Digital GitHub repository.
- The AWD Digital banner now sits at the top of each Client Mailer screen.

### 1.0.0
- First release.

## Credits

Updates use [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by Janis Elsts (MIT licence), bundled in `lib/`.

## Licence

GPL-2.0-or-later. Copyright AWD Digital.
