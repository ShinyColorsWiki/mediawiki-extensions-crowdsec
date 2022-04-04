# CrowdSec Extension
This extension is does a job for [CrowdSec](https://crowdsec.net) bouncer for mediawiki.

## Note
### **This extension is highly experimental. Use at your own risk.**
 * There's no challenge method. You can block the 'captcha' type using `$wgCrowdSecTreatCaptchaAsBan`.
    - Recommended to use with ConfirmEdit. It blocks some kind of things.

## Configuration 
in `LocalSettings.php`
```php
wfELoadxtension( 'CrowdSec' ); // Load Extension

$wgCrowdSecEnable = true; // Set false to disable

$wgCrowdSecAPIUrl = "http://localhost:8080"; // your crowdsec lapi address
$wgCrowdSecAPIKey = ""; // !mendatory! Set your bouncer key from cscli. eg. `cscli bouncers add mediawiki-bouncer`

$wgCrowdSecCache = true; // Recommended to use this for perfomance.

$wgCrowdSecFallbackBan = false; // If LAPI request failed, `true` will block all user. Not recommended to set `true`.
$wgCrowdSecTreatCaptchaAsBan = false; // Use at your own risk. There's no challenge. Use with ConfirmEdit instead.
$wgCrowdSecRestrictRead = false; // Use at your own risk. This will block the site at all who listed on CrowdSec

$wgCrowdSecReportOnly = false; // This Doesn't block the user. for debug purpose.
#$wgDebugLogGroups['CrowdSec'] = '/var/log/mediawiki/crowdsec.log'; // for debug purpose.
```

## Thanks
* Main method for block user is based on [StopForumSpam Extension](https://mediawiki.org/wiki/Extension:StopForumSpam).
* Cache method is based on [AWS Extension](https://github.com/edwardspec/mediawiki-aws-s3)
* [CrowdSec](https://crowdsec.net) itself.

## Development setup
1. install nodejs, npm, and PHP composer
2. change to the extension's directory
3. `npm install`
4. `composer install`
