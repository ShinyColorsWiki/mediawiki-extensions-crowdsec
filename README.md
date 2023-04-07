# [CrowdSec Extension](https://www.mediawiki.org/wiki/Extension:CrowdSec)
This extension is does a job for [CrowdSec](https://crowdsec.net) bouncer for mediawiki.

## Note
### **This extension is highly experimental. Use at your own risk.**
 * There's no challenge method. You can block the 'captcha' type using `$wgCrowdSecTreatTypesAsBan`.
    - Recommended to use with [ConfirmEdit](https://www.mediawiki.org/wiki/Extension:ConfirmEdit). It blocks some kind of things.
 * This extension is tested on Mediawiki 1.39. but it may work with lower version of Mediawiki too.

## Configuration 
in `LocalSettings.php`
```php
wfLoadExtension( 'CrowdSec' ); // Load Extension

$wgCrowdSecEnable = true; // Set false to disable

$wgCrowdSecAPIUrl = "http://localhost:8080"; // your crowdsec lapi address
$wgCrowdSecAPIKey = ""; // !mandatory! Set your bouncer key from cscli. eg. `cscli bouncers add mediawiki-bouncer`

$wgCrowdSecCache = true; // Recommended to use this for perfomance.
$wgCrowdSecCacheTTL = 604800; // Cache TTL. In seconds. Default to 7 days but it's nice to set 2 hours if can handle it. (2 hours is default CAPI pull interval)

$wgCrowdSecFallbackBan = false; // If LAPI request failed, `true` will block all user. Not recommended to set `true`.
$wgCrowdSecRestrictRead = false; // Use at your own risk. This will block the site at all who listed on CrowdSec
$wgCrowdSecTreatTypesAsBan = []; // Use at your own risk. Since there's no challenge integration, `captcha` will be passed too(Use ConfirmEdit instead). If you want to block `captcha` type user, then add `"captcha"` to this array.

$wgCrowdSecReportOnly = false; // This Doesn't block the user. for debug purpose.

// Debug purpose
#$wgDebugLogGroups['CrowdSec'] = '/var/log/mediawiki/crowdsec.log'; // Hooks
#$wgDebugLogGroups['CrowdSecLocalAPI'] = '/var/log/mediawiki/crowdsec.log'; // LAPIClient
```

You should setup CrowdSec and CrowdSec LAPI, Configurations too.
Also highly recommend to register CAPI(Central API) for pull blocklist from central.

## User rights
* `crowdsec-bypass`: allows users to bypass crowdsec check.

## AbuseFilter
There's [AbuseFilter](https://www.mediawiki.org/wiki/Extension:AbuseFilter) integration. The variable `crowdsec_blocked` is representing...
* `false`: LAPI Request was failed. or failed to get user ip.
* `ok`: This user is ok to process.
* `ban`: This user is reported for "ban" from LAPI.
* ... and various (custom) types via CrowdSec. including `captcha`.

## Thanks
* main method for block user is based on [StopForumSpam Extension](https://mediawiki.org/wiki/Extension:StopForumSpam).
* Cache method is based on [AWS Extension](https://github.com/edwardspec/mediawiki-aws-s3)
* [CrowdSec](https://crowdsec.net) itself.

## Development setup
1. install nodejs, npm, and PHP composer
2. change to the extension's directory
3. `npm install`
4. `composer install`
