# [CrowdSec Extension](https://www.mediawiki.org/wiki/Extension:CrowdSec)
This extension serves as a CrowdSec bouncer for MediaWiki.

## Notes
### **This extension is highly experimental. Use at your own risk.**
- There is no challenge method implemented. You can treat 'captcha' decisions as bans using `$wgCrowdSecTreatTypesAsBan`.
  - Still recommended to use [ConfirmEdit](https://www.mediawiki.org/wiki/Extension:ConfirmEdit) as it won't block all malicious actors.
- This extension has been tested on MediaWiki 1.43. The minimum required version is **1.39+**. It may work on older versions.

## Configuration 
Add to your `LocalSettings.php`:
```php
// Load the extension
wfLoadExtension( 'CrowdSec' );

// Enable the extension (set to false to disable)
$wgCrowdSecEnable = true;

// Your CrowdSec LAPI address
$wgCrowdSecAPIUrl = 'http://localhost:8080';

// Mandatory: Set your bouncer key from cscli, e.g., `cscli bouncers add mediawiki-bouncer`
$wgCrowdSecAPIKey = '';

// Recommended for performance
$wgCrowdSecCache = true;

// Cache TTL in seconds. Defaults to 7 days, but consider setting to 2 hours (default CAPI pull interval) if possible
$wgCrowdSecCacheTTL = 604800;

// Fallback action when LAPI throws an error: 'ban', 'captcha', or 'ok'. Default is 'ok'
$wgCrowdSecFallback = 'ok';

// Use at your own risk: Blocks all access for users listed in CrowdSec
$wgCrowdSecRestrictRead = false;

// Use at your own risk: Treat specified decision types as bans. Since there is no challenge integration, 'captcha' decisions are passed by default (use ConfirmEdit instead). To block 'captcha', add 'captcha' to this array.
$wgCrowdSecTreatTypesAsBan = [];

// Report only mode: Does not block users, for debugging purposes
$wgCrowdSecReportOnly = false;

// For debugging:
// $wgDebugLogGroups['CrowdSec'] = '/var/log/mediawiki/crowdsec.log'; // Hooks
// $wgDebugLogGroups['CrowdSecLocalAPI'] = '/var/log/mediawiki/crowdsec.log'; // LAPIClient
```

You should also set up CrowdSec, the CrowdSec LAPI (Local API), and their configurations.
It is highly recommended to register with the CAPI (Central API) to pull blocklists from the central repository.

## User Rights
- `crowdsec-bypass`: Allows users to bypass the CrowdSec check.

## AbuseFilter Integration
This extension integrates with [AbuseFilter](https://www.mediawiki.org/wiki/Extension:AbuseFilter). The variable `crowdsec_decision` represents the CrowdSec decision:
- `ok`: The user is allowed to proceed.
- `ban`: The user is banned according to LAPI.
- `error`: The LAPI request failed, or failed to retrieve the user's IP.
- ... and various (custom) types from CrowdSec, including `captcha`.

## Thanks
- The main method for blocking users is based on the [StopForumSpam Extension](https://mediawiki.org/wiki/Extension:StopForumSpam).
- The caching method is based on the [AWS Extension](https://github.com/edwardspec/mediawiki-aws-s3).
- [CrowdSec](https://crowdsec.net) itself.

## Development Setup
1. Install Node.js, npm, and PHP Composer.
2. Change to the extension's directory.
3. Run `npm install`.
4. Run `composer install`.
