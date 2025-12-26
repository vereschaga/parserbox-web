<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\Settings;

class TAccountCheckerHotels extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL_V1 = 'https://www.hotels.com/account/hotelscomrewards.html';
    private const REWARDS_PAGE_URL_V2 = 'https://www.hotels.com/account/rewards';
    private const KEY_CAPTCHA = '9AEE61AB-B252-7ACB-A029-7626DD912364';

    private bool $isGoToPassword = true;
    public string $host;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                case 'SGD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "S$%0.2f");

                case 'RUB':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f₽");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""                => "Select your region",
//            'AR' => 'Argentina',
//            'AU' => 'Australia',
//            'AT' => 'Austria',
//            'BE' => 'Belgium',
            //'BR' => 'Brazil',
            //'CA' => 'Canada',
//            'CL' => 'Chile',
//            'CN' => 'China',
//            'CO' => 'Colombia',
//            'CR' => 'Costa Rica',
//            'DK' => 'Denmark',
//            'EG' => 'Egypt',
//            'EU' => 'Euro',
//            'FI' => 'Finland',
//            'FR' => 'France',
//            'DE' => 'Germany',
//            'HK' => 'Hong Kong SAR',
//            'IN' => 'India',
//            'ID' => 'Indonesia',
//            'IE' => 'Ireland',
//            'IT' => 'Italy',
//            'JP' => 'Japan',
//            'MY' => 'Malaysia',
//            'MX' => 'Mexico',
//            'NL' => 'Netherlands',
//            'NZ' => 'New Zealand',
//            'NO' => 'Norway',
//            'PE' => 'Peru',
//            'PH' => 'Philippines',
//            'SA' => 'Saudi Arabia',
//            'SG' => 'Singapore',
//            'KR' => 'South Korea',
//            'ES' => 'Spain',
//            'SE' => 'Sweden',
//            'CH' => 'Switzerland',
//            'TW' => 'Taiwan',
//            'TH' => 'Thailand',
//            'AE' => 'United Arab Emirates',
            //'UK' => 'United Kingdom',
            'US' => 'United States',
            //'VN' => 'Vietnam',
            'OTHER' => 'Other'
        ];
    }

    public static function GetAccountChecker($accountInfo)
    {
//        if (in_array($accountInfo["Login"],
//            ['iomarkus@protonmail.com', 'iormark@yandex.by', 'iormark@yandex.ru', 'iormark@ya.ru'])) {
            require_once __DIR__ . "/TAccountCheckerHotelsSelenium.php";

            return new TAccountCheckerHotelsSelenium();
//        }

        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyDOP(array_merge(Settings::DATACENTERS_USA, Settings::DATACENTERS_NORTH_AMERICA))); // TODO: prevent getting alien user data
        $this->setProxyGoProxies();

        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.hotels.com/profile/settings.html', [], 30);
        $this->http->RetryCount = 2;

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        // Enter a valid email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        if ($this->getCookiesFromSelenium() === true) {
            return false;
        }
        $message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner-description')]");
        if (
            $message
            && !strstr($message, 'Explore more in 2023. Soon you’ll be able to sign in and book with one')
            && !strstr($message, "2023'te daha fazla yer keşfedin. Yakında Expedia, Hotels.com")
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Email and password don't match. Please try again."
                || $message == "E-posta ve şifre eşleşmiyor. Lütfen tekrar deneyin."
                || $message == "Your account was paused in a routine security check. Your info is safe, but a password reset is required."
                || $message == "Hesabınız, rutin bir güvenlik kontrolü sırasında duraklatıldı. Bilgileriniz güvende ancak şifrenizi sıfırlamanız gerekiyor."
                || $message == "Enter a valid email."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "Sorry, something went wrong on our end. Please wait a moment and try again."
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        } else {
            $this->http->GetURL("https://www.hotels.com/login");
        }

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrfEmail = $this->http->FindPreg("#.\"loginemail.\":.\"([^\\\"]+).\"#");
        $csrfPassword = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");
        $this->State['csrf'] = $this->http->FindPreg("#\,.\"verifyotpsignin.\":.\"([^\\\]+).\",.\"#");
        $this->logger->debug("csrfEmail: $csrfEmail");
        $this->logger->debug("csrfPassword: $csrfPassword");

        $headers = [
            "Accept"               => "application/json",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-b3-traceid"         => $this->getUuid(),
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => "undefined",
            "x-user-agent"         => "undefined",
            "x-xss-protection"     => '1; mode=block',
            'Referer'              => 'https://www.hotels.com/enterpassword?scenario=SIGNIN&path=email',
        ];

        if (!$csrfPassword || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }
        $keys = $this->http->FindPregAll('/pkey=([^&\\\]+)/');
//        print_r($keys);
//        $keys = array_unique($keys);
//        $this->logger->debug(var_export($keys, true));
        $captcha = $this->parseFunCaptcha(self::KEY_CAPTCHA);

        if ($captcha === false) {
            return false;
        }

        /*$data = [
            'channelType'   => 'WEB',
            'email'         => $this->AccountFields['Login'],
            'username'      => $this->AccountFields['Login'],
            'resend'        => false,
            'scenario'      => 'SIGNIN',
            'atoShieldData' => [
                'atoTokens' => new stdClass(),
                'devices' => [
                    [
                        "payload" => 'eyJjb25maWd1cmVkRGVwZW5kZW5jaWVzIjpbeyJuYW1lIjoiaW92YXRpb25uYW1lIiwicGF5bG9hZCI6IjA0MDBGMHNhZTY2UGtJbVZlYkthdGZNaklOU09UUzY4RXFSVFMvTmtFR3VGMVNRWTdsa0NJdWhudEZyV0hiaXJpK05FdmppRGF2dUgwS2c0bTQ1Tk55ZWV2NVJ2RkNNU2JsNXhBZ3NzTVBVRDlUdUdmbUlnNCs5ejFTSVdsTU9wY2lBcUlpN1NvVWV2T1JyZ0djL3FabW5jazQ4Vi9uWWhnd2o4Vjc0QlkxY1c3ZGJ5dzNxTkk5OGdIV3FlUCtRNHhOdjJoRFR0TUpEVnhVRVhGcnhMREhYZHZqQ05oTzVOb1R2N3I5b2RYZWhUWm54aTl2RmJlS3VuWVp5b3gwS24yclQ0MzNncFVaNkdPNXNlbS9kWUV0VmZsa0EyUG5NSDNMUy94N3lhY1dJK1BTS1JlS0FwVHFYZzFmOXFQMDE4YnZqUXBadjByRWd4QnV2VDdXK2JkOXNQaXVBWnorcG1hZHlUanhYK2RpR0RDUHhYdmdGalZ4YnQxdkxEZW8wajN5QWRhcDQvNURqRTIvYUVOTzB3a05YRlFjcC9hR0hDcXkwblQ0M1I1aitvOHc2UllDNG02RDhZVmU1azhnWnJwdlZJbWliTXErWG5XT3FydWxyR2ZGZ29mNFFic3BiMHo0UE9BQmJudEN5aGFrZlh1RlVrZXdydHh6Qmx3WS9KcDBxTEtQRVhCUnlURVVZdlphamNiYTM5V1VNbzNWc0xRYWd5a1hBSWI3N082V1BOSzlBelNzLzN3ZFRhVFhaNUpkZEZZV3lRTW5ScVZiVjh5V1NxWFZwME4vMEJDSkN5NUhQOEhnZXJrYzg5OXNKam42c1NnTFFlVEpyVGd5RG9rQUhBVFY5NFdTTFpab2N5V3BBWFhZc1ZaZUVtQjNSK1JCdEJMZzgxRlNmaFd4dVpqbzQ5N3d1bkVveGFId3oyUHBydml1a2dqU2xncE5HcURFSEJ4ME1hZ2dyZG9zZFdXYzMrUGxqdWJRT3laK2xkeEdOR3loVVVVWXVvS21MTjdzV2NUd01TWUlIZmhpM3g1UnRqY1c1alhnbzVWTk12YURYZnFYTUVOTGlwSDRnYU1Wb3VlR2dWaXBLeEZvOHBzTndnc0J4NncxdHgwbU1aejdIdG1TUXJLdUh0MzlidnhFODZlVmFZSkVGMDBNMmRtd3BGSk1mNGczcHZBKy9DUWtjenYrRDZFMTdvYS9SVlBEWUxpaERhbnJ5RzFqSkg3VW9pQlFoTlYwWHpmaDJkdFJFK0s0V2gyUFdBdUdjbWp3OEU2TEV0Y1lFZ0lEVW1sQmdBVlNzT1Y5SXBHLzk0ZFhkQ0p1aGN1N0QwdWJvUnFTc1JrYzh0NXU4NmlwN2dZOVh2Unc2TkwyR0F3dCtVd3lTd1BuRE5pQm5MSmhIUFc5SGlweWR2aG9xeTNXUkVNRmEvc1dVajJkd1RpZG5lWjVXZWR2cDNFT0tpU3g3dUhHT2pMZlZxVVdhVUVjcWlucHJpYWsxSndVcjFHTERjRTRuWjNtZVZubmI2ZHhEaW9rc2U3aHhqb3kzMWFsRm1sQkhLb3A2YTRtcE5TY0ZLOVJpdzNCT0oyZDVubFo1MituY1E0cUpMSGs1enJhV3hIV3FMeEtaRms1WnpJdVlPSnBtQ3NWVzBhNzlNT25SVE10aExPeWUwYXM2dU02R0pLU0lkd085TzVVVmFpbUsvVkdPVGtDRHp3MlRua04vZE9JT3R1eW5Hci95Yk0wby9DS3o2dWtiNnJpUEZBN002eVUycHVaVjhROFM4MGtqTmEzMnVPQnE0MVg1ekYrOU1SVjBZbVhFSEFKenhweHR3cW9DNVovdUNpN01FOGxvUXdMTFN6ZUZFdVZmZTRlZFArWXlyRXNwNm5TQUNKQ2J2T29xZTRHUFY3N3V2TnZFZlJrSnVwQnkzWjhoU0VNSEw5a2FMODVselU5WVBVU1QzZ1RZbmVBL3ByendGdldldm96ZkM0dndidnFMQmxaelJWd2lFR1ZzaDFHWkdiL3YrU2dDYTh2UGFpbnRjeUp6ellOb09CZWlPVGhzWDNVOGNDY3JJSzRpSzBoaG1oczFRbEFPYW9JVnNDenEveTUrZTdiZEhxeXBYcWt6cCtuUWtNTEx2dzZIQWF6WDVJekpxelZGd0xzVmFZeGpKOXNJd1UzY1pQbG5MTWdiL2Nmd3poVlByNjhHcUEyWHg4bkFRLzJCS3p0QWJvSE15T2cwVTZtMEhrY0Y3UnRCNmNObUpKNkhMRFV3elE3a3BXUHNybFhMWEtXVmRvRXhucWdqdnFGUk1hTmMzWkJXZ0g4bDFDNG5lNHFVY253bHNYd3BaMnh1Q2Q4UVh6VDRCeEFmVitWVHhJSk0zR3laM1pKdmFHejZ5Y0NOcmd6WGZRQUhGci9YZEVTS3RpZzRjaGtwcHJJRjlKWTZnNVQ5VnZPQnRuMnNRR1F4QkVKWHdCbGxuSkhQYVZWZktkS2NLRE9JcFVxdTlpUXRqY3licGRUanBSMDNDWXFiOGQxMU94VmE0SE9tamhuZkhuS3MrVVFlKytXaWdJeWFDSXd2algwMTN4UTBXWVZnYW5BSnRoaGxvMERubmp5NGZobmhMNmlqNlJqY2V0djNPOW9Wak92VjVBOGpMMGlzc292MU1IWHZiNmtkWlZrRHc0ZDBLOUpJY1BXWk5iZXlrVmN6NzlVVzhVeTZxTHc2aDNBbmlyK1JaUGF4dFpCUEluSlo0OHZIMnQyVjdpSk1sTVd0QlE1SGg0cm9WK28wdENEbnA5QWxGaEpLeU1nWnlwbThjekVsSmp0ZEZEbFJsWmxIT0lJbml3aGNlT2gxdHhKWnE4aTY0UDNHa0VxN1R3aW5VeFVWSTNuMVNPaFphbDNiZHoyQ1FIUm51U2RZb2dUR2VrV0UvMXZQbWg1a0QxRHNTVTdjV3FFTE9NUTBRanhPWCt4UlVHb3ZtcHlZZHRjOGxSQXpuSDMxZGtMWTNVQVM5TC9QZmVzUThBQ3UwMVFaK2xENmFXNlJNQ0VtMVhJVGp2dG1CaTB5ZnhSRE5mcVd4RHFLbk9XSXJlcTl1QS9PUlg4bkxpMTV6M2d5M2JlUjdUeW1uRVJCMXY5ZU92c3l2VEJWUGNIbGk3ZlBxQ3pqMG5FbzNaam9QRDFBZnhLUi9UNkNiem9HdUdOUElvVGdROVFjYWgxcytpanNRUGpxNTlGaEcrY2dsQmtmVFN5RUtiSERQSkw5VTdqdU5FK2NmYTYzN0VnYWRIYzd4YlQxdnBFTVdSaTE4RGMxS1lrTVJBS2xMazcvQUo4UkVZdVdjT1NkUlRCL05tMXQrdU5wOVlBM1ZGZy9VeFFUeTlNTmx6WC9sanBkVzczcDhncjJId1RzM2h5Ti9iWFlRNXhZWkVZbnluUmJoT3JJSWJDWFYwR05URWRPN3RWUlNkZkFMSW9ha2hSZE0xZkdWa0hja0JheDZVeENuSC9uVTUzbVpab1hGb3VMdldGQi9RZ1NqNjdJTGJRbTRBYzZ6QW1tSmEyUEsrOVV4U3VCcGJuSWdmN1FRWEd5dXE3cVhMNnNJOWFQSlhiWlZTWWFUcXp6MDRodzlENWk3T3pqRDRnYy9WYmhNYnU3WS9mM3Y1MGRMazgyaXhYZk9SMVIvT0kwMXFUdHhrckV5c0FzNVpqQXpYVkhwYkkxNVZ5SkxYNGJPU295TW8wUFlrS1I2Sm9OY3BLWDM3RUxLL2FqeVRZVFZPbllmSlVKWmtHZFNycVZLMEdyRHUzRkNpR0xMUmdrTW1CYk5BcTd1RnFUVk9kYTVTUlVKYldrYXE3eFNkTCtiZ3hGakNwOWhjWWNxUWRoKy9GWURvaDlqZmFSemg2K3pNQ21uYjJFL2FWQnRNV2VubFRMdGlHMlMzNHZmVm9aTmFwTk8xVy95djJNRUN4ajhJbTNKLzlSVlFKdVJKcVV3eDZUaVZTTnlvNFNYQitsVW5QdkVhbHJJMVJHNUp3R1VSNkpkeEtSekZwR2RwVmRWWjlOSXdvWTh5ZTJ1MHFKVEJibEZieFlNU2dlcjRHYzVPOGc2WWpBMUFMNU0yR1R5Rnhsd1ZrM2dqTTdMZG12bTAvT0xlQ05qZEp1TTZWMnA1STZWaVFmVkxrcEswY0pRTmZsWkxCS0lYLzJ1Y0ZqKzBibDFxcWxOKzk4T2pqVTErUEMxY0xJRDJGSjZYM0NHZjVUci9DQUh5NlJNK3lRZmZENWtyc2NMRytESVVLUUFiZHYrSlVNNGxjVUFvVFU3NlQyTytySU0raDM1UFhRcVNId2FHcFhaSllzVTJIL2V1UU1vQXJvNlhOd1ozMi85RE1uNGluUlpBbEllRGFnYmZwaXFLUG9YMlVzUlRRPT07MDQwMHhkRlEwYXFXWWN3cElmcTJMTHRML1NsTjNEUlgxR0RCaDBiMndTV2VlcDI4bWNUNG5OdWlqbUNUR3JGNGNOMWV2djd2Nk1DZ0ZWTGhKQm1kbkJYS0R3RGlvN01pS0dySUx6WU4zMFNmTXQyYjl0R2JrV3hzRUx0RDJ3MXliaGltWVR0VU5vNWxZSHp1WHZQL2crOW93c1ZLWXFTMFFGN2IyUCtmU2JJTmx2TEtmMmhod3FzdEowK04wZVkvcVBNT2tXQXVKdWcvR0ZYdVpQSUdhNmIxU0pvbXpLdmw1MWpxcTdwYXhueFlLSCtFRzdLVzlNK0R6Z0FXNTdRc29XcEgxN2hWSkhzSzdjY3daY0dQeWFkS2l5anhGd1Vja3hGR0wyV28zRzJ0L1ZsREtOMWJDMEdvTXBGd0NHKyt6dWxqelN2UU0wclA5OEhVMmsxMmVTWFhSV0Zza0RKMGFsVzFmTWxrcWwxYWREZjlBUWlRc3VSei9CNEhxNUhQUGZiQ1k1K3JFb0MwSGt5YTA0TWc2SkFCd0UxZmVGa2kyV2FIcFp3MzJRTW1UUklWOVR3SmhGdk04cStSRVRvSkVYNXpteG1GQU95RWRLOEhvR2EyQ1VLNVhKR3JoK1FxUUFUNFZIWjZhL1hBVkF0b3hkT1hJTkV0djZmOW1NMyt3OVpJQkN3c1BqRkNXUFY1M0k5Nld6dU53V1VkVUhweUdzanU3eVpIYlQraGFwOGY2Y0drWWNqNEJEQzNvN3JSdUljUzJ2TWpWYWVtOTVjb0R2bEtxY1cvdnFmand5MTBudWY5R2NzbUVjOWIwZUs0eEkxUlRJWUM4b3VENzFxQ0tjbVpxYStjNVVNZmRMTlhxTHorMXZscVVBcjlkRTJqY2ZsMHdncm9RQmZweXVJQm9adUxta1NMSWdEbHcybFA1VzdhVklBVzd1RFFqWE1zUGlaWGROQ3FmS0FiendPcnRPSXRUSEEybGFiYUwyMG1JTnNKOVZsTVlHVFgyM0kyenVEekJERkxITSs1RzNKeHZRZmtpTWVRSndwS3pncFZnb0hpQWwzUDY5SE1UUW5VSGhzQW5nZUMzblRjcGlFNXhFTGw2SndUNVE4YTRXalFiZUYyZHk1VmVOY2lQVFFhRlF6bTdvb1A4Q25qTmZCbWxCSEtvcDZhNG1wTlNjRks5Uml3M0JPSjJkNW5sWjUyK25jUTRxSkxIdTRjWTZNdDlXcFJacFFSeXFLZW11SnFUVW5CU3ZVWXNOd1RpZG5lWjVXZWR2cDNFT0tpU3g3dUhHT2pMZlZxVVdhVUVjcWlucHJpNlVaY2d1U1VNaFVKdXZLNzBid0JwSEdMOVNCQUVmUEh3WFlxd1pzbnc1WERtczFHR013VytWTEI2TTVmbDJFb1ppdXk3WGV0emhIVmhnaFBMTkhDLytNMXhBZlpGMmlaaEhHSytZTnVaVTR6cWJ3cG1XYThJekhaZEFJalhOdFhTamFzZWpNUkQrNlMrbDVnUmg1eE03em9LbGNVTE5Hd0ZtUVdwcFJsNkJ2NHBwNDhCMlBSMExVTTZSbjNKdEhFZkY5aFhkWjREUlJpd3htWlZqbDlJeDg2RjlzTVVFSDhOODlwOWttbHFoV2c1ZFJiR1dQSThCV2x5cXJlUitZL3M2QkpEUVhOK1Z4clZiR0puVnJUUFg4aGhRMisra1BKVUdTTDBNb1lhVk5GYlpVMUpTTWU1bnhnUFZqNGVOL05WU1ZJaXo5Qmd5clVYWks1V0hFcllMUWpoQVJQb1l6M2NZSUpwN3ZzYk9JSE5pc2RTeTg1SUxPSUVHUlZ0bW8weUhTVnI5Q0lFMkpzaXBDRG5YRXUxK0IrWkU2Ukh4ZzJlak85VitWMitjK1JUTENsNHJHTXpXdlFvcHpKSXpGTmVGRHFBRk9lK1ZoMTJkZXJ6akFEN0d6VDVYSTVTNmpKbGgxLzBmOCtUOTNHVEVqWUwrQTRwanY2RStGZmVsVDJRckgxdlZBbC85dXUycVJncWtveWFDWFV3UTkyd3ZOL1drSHVFL0pjaXZoTEgyYVJDSVdnd2svdVdla0NPc1plQ1ZzV0lyYWw2WFNkalFOT2R3WHM0djlJS1RqYi9uSXhBaGF3ZVNTQUdyQityZ1JiYThUcU5hUktUdVlQSDE0aVhIY3FwakVoK2ZBYkN4bEk0V0xEMXozRHNPWGZ3bHJodkVHcTJIUU9OYjBwd1lHQWJtRFZUZXg4eHJCeldrQjFpanNRUGpxNTlGZzJyY2g0L2QyMC90VjZtaFhzNVpyNmhTYjhQbXozR1ZQZUpLN2VHMXVoOGU5TTBNenlNb09adXdRKzVCR1dqRllLQlVJU0s3WUFVZEIyVFMxSmluY0g3WU44TXBWd1ZyWWlJaEVndGpSVldmYnhPN0o4cHJwa24vTXI4SFBlUWtKQXVkYUYwOVBxb21BbVJzdGxLNUdNWU0yNHFua1ByZnNObGdjTXVlaXhZZVRWNWU0N3VBWUpSK0JSbTRzVGJFSUJna0dpWkYxT3h1SWlGb1FaeVhOZU1RejEzaTJvNzdUZGttUVc0S3drWi9Ka3JvRkJYbk9ycmFoQ1hqQXdQYWt0MnRja3pPUzczZHVCTE9ONkR0WG9Hb3N4SVoxRm02TC80S3Jtb1FZWkt5cFBZTnBmWEc0NnVwYW85cFlZdGxheTRiMUpVWldySWtkODdYck5aU1BDWHoxNEVDbm1IenhzUlFWWTBnUlFka1ZJVTZld3Q4Y1pLN245TmYvc3N6YkR4MWVsdy9ub0tTeDN3cXZaeDNuTXhEOHdpd1J0anV5bDlNOXZrMThjekJ1bnhsYnhWWDhaSTgyOUNpajIyajdJeGZwWjdncitseEVraHVpNVFSTmo2L29nRDBmZGl5ZStPOC9BdVVuZU5HRkR6Vjc5cVQraXpyVHFUcFYrMHUwSFlZTmR5T21DR2VLemVxbzVCc3V3aVFWYUJsWWtWbHV5TWdjZzlNa0xBTmNBbmNrNGhic1dEenVRaFJtVEZyd3c5THROa0RTMG9oZis2MEMzSm5IMDNCTWNsaUx4YUNGZnZqbHpPQVVUVHluV0pHczZuWDNvN1pSdEo3dytwQUtuVEFXbkVmY040eG1KMDkxTWFHWm5SZElSMmwyTmFlRlJpRmVvZEZFcVI3ZnFwNDViMnVubEhNZ0RGdzZSTkcrcmZzNjRaRmFORGEyeVdYUWJsQTB6SUZxaUEvWGE3RXpKN1hVOVBBeGlzY0ppOGxkSm5JMTRqU0dmRDhid3FudGpJeEptc1hoZCtZV001M1VqY24vblpUSjNsVjY3UG1CN0tibzJSeldvWUJpbkFIc0Mza21scTFmUmxYcUx6akhva3Exa2g2ZGZrclo0aHVGN3AyM0RBNThkdklvaFpMaWlqK0Q3dUFDQzh0N0JCaHQ0ajdJYkF0RW9lTjRkYmwyZmpONWxXNFRoWmJrd2NHREF6bDVtS3dWL0czT2pvQjNWZTRxRzJQUVZWVVVmMU9GSjlyclNvK0owVHFuN3RrK2R2Mk9IYkl1U01IcG1uK09sV2Rab2ZTOXlLZS9LcFY0eDVrdEJrTHpoUHFNR2oyanp3Z0hyZ3kvNStORTIyV2xhR3orVHVGYWYvbGxRUktXanVGdXIiLCJzdGF0dXMiOiJDT01QTEVURSJ9XSwiZGlhZ25vc3RpY3MiOnsidGltZVRvUHJvZHVjZVBheWxvYWQiOjEwOTEsImVycm9ycyI6WyJDMTEwNCIsIkMxMDAwIiwiQzEwMDQiXSwiZGVwZW5kZW5jaWVzIjpbeyJ0aW1lVG9Qcm9kdWNlUGF5bG9hZCI6Njk5NjY4MCwiZXJyb3JzIjpbXSwibmFtZSI6ImlvdmF0aW9ubmFtZSJ9XX0sImV4ZWN1dGlvbkNvbnRleHQiOnsicmVwb3J0aW5nU2VnbWVudCI6IjMwMDEsd3d3LmhvdGVscy5jb20sSG90ZWxzLFVMWCIsInJlcXVlc3RVUkwiOiJodHRwczovL3d3dy5ob3RlbHMuY29tL2xvZ2luPyZ1dXJsPWUzaWQlM0RyZWRyJTI2cnVybCUzRCUyRiIsInVzZXJBZ2VudCI6Ik1vemlsbGEvNS4wIChYMTE7IExpbnV4IHg4Nl82NCkgQXBwbGVXZWJLaXQvNTM3LjM2IChLSFRNTCwgbGlrZSBHZWNrbykgQ2hyb21lLzEwOS4wLjAuMCBTYWZhcmkvNTM3LjM2IiwicGxhY2VtZW50IjoiTE9HSU4iLCJwbGFjZW1lbnRQYWdlIjoiMzAwMSIsInNjcmlwdElkIjoiNjhhMDBjMDMtZTA2NS00N2QzLWIwOTgtZGQ4NjM4YjRiMzllIiwidmVyc2lvbiI6IjEuMCIsInRydXN0V2lkZ2V0U2NyaXB0TG9hZFVybCI6Imh0dHBzOi8vd3d3LmV4cGVkaWEuY29tL3RydXN0UHJveHkvdHcucHJvZC51bC5taW4uanMifSwic2l0ZUluZm8iOnt9LCJzdGF0dXMiOiJDT01QTEVURSIsInBheWxvYWRTY2hlbWFWZXJzaW9uIjoxfQ',
                        'type'    => 'TRUST_WIDGET',
                    ],
                ],
                'csrfToken' => $csrfEmail,
                'placement' => 'loginemail',
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hotels.com/identity/email/otp/send', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        if ($this->parseQuestion()) {
            $this->State['fc-token'] = $captcha;
            $this->State['headers'] = $headers;

            return false;
        }
        return false;*/

        $data = [
            'channelType'   => 'WEB',
            'email'         => $this->AccountFields['Login'],
            'username'      => $this->AccountFields['Login'],
            'passCode'      => $this->AccountFields['Pass'],
            'password'      => $this->AccountFields['Pass'],
            'rememberMe'    => true,
            'scenario'      => 'SIGNIN',
            'atoShieldData' => [
                'atoTokens' => [
                    'email'      => $this->AccountFields['Login'],
                    'fc-token'   => $captcha,
                    'rememberMe' => '',
                ],
                'devices' => [
                    [
                        "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":2038,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":9237,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"3001,www.hotels.com,Hotels,ULX","requestURL":"https://www.hotels.com/login?&uurl=e3id%3Dredr%26rurl%3D%2F","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"3001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                        'type'    => 'TRUST_WIDGET',
                    ],
                ],
                'csrfToken' => $csrfPassword,
                'placement' => 'login',
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hotels.com/identity/user/password/verify', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        /*if ((isset($response->identifier->flow) && $response->identifier->flow == 'BETA_CONSENT' && $scenario == 'SIGNIN')
            || (isset($response->cmsToken) && $response->cmsToken == 'NotNeeded' && $response->failure->field == 'ato')) {
            $captcha = $this->parseFunCaptcha($keys);

            if ($captcha === false) {
                return false;
            }
            $data = [
                'channelType'   => 'WEB',
                'email'         => $this->AccountFields['Login'],
                'username'      => $this->AccountFields['Login'],
                'resend'        => false,
                'scenario'      => 'SIGNIN',
                'atoShieldData' => [
                    'atoTokens' => [
                        'email'      => $this->AccountFields['Login'],
                        'fc-token'   => $captcha,
                        'rememberMe' => '',
                    ],
                    'devices' => [
                        [
                            "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1638,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":164795,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"3001,www.hotels.com,Hotels,ULX","requestURL":"https://www.hotels.com/login?path=email&scenario=SIGNIN","userAgent":"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36","placement":"LOGIN","placementPage":"3001","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                            'type'    => 'TRUST_WIDGET',
                        ],
                    ],
                    'csrfToken' => $csrfPassword,
                    'placement' => 'loginemail',
                ],
            ];

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.hotels.com/identity/email/otp/send', json_encode($data), $headers);
            $this->http->RetryCount = 2;

            if ($this->parseQuestion()) {
                $this->State['fc-token'] = $captcha;
                $this->State['headers'] = $headers;

                return false;
            }
        }*/

        return true;
    }

    public function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/(Our apologies\.\s*We are currently making improvements to our site and it is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently making improvements to our site
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently making improvements to our site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are having technical issues
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, we weren\'t able to show your welcomerewards")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are having technical issues
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are having technical issues')]/following-sibling::p[contains(text(), 'We hope you will come back to try again later')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal server error
        if ($message = $this->http->FindPreg("/(Error 500 - Internal server error)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we're taking too long to respond to your request
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, we\'re taking too long to respond to your request")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Please check your email address and password are correct
        if ($message = $this->http->FindSingleNode('//li[contains(text(), "Please check your email address and password are correct")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($this->http->Response['code'])) {
            if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/","identifier":{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_2"},"csrfData":null,"failure":null,"scenario":"SIGNIN"}/')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->isGoToPassword === false && $this->http->Response['code'] == 401 && $this->http->FindPreg('/"placement":"login"\},"status":false,"failure":null,"requestId":null\}/')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->Response['code'] == 401 && $this->http->FindPreg('/"failure":\{"field":"ato","message":"Validation Result Denied","case":23/')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->Response['code'] == 404 && in_array($this->AccountFields['Login'], ['marielenmf@poli.ufrj.br'])) {
                $this->throwProfileUpdateMessageException();
            }
        }// if (isset($this->http->Response['code']))

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*$this->http->GetURL("https://{$this->host}");
        $oldDesign = false;
        if ($this->http->FindSingleNode("//a[contains(@href,'/account/hotelscomrewards.html')]")) {
            $oldDesign = true;
        }*/
        $this->http->disableOriginHeader();
        $this->http->setDefaultHeader('Referer', "https://{$this->host}/");
        $this->http->GetURL("https://{$this->host}/account");
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');
        $tuid = $this->http->FindPreg('#\\\\"tuid\\\\":(\d+),\\\\"#');
        $expUserId = $this->http->FindPreg('#\\\\"expUserId\\\\":(\d+),\\\\"#');
        $clientInfo = $this->http->FindPreg('#\\\\"clientInfo\\\\":\\\\"([.,\-\w]+)\\\\"#');
        $pageId = $this->http->FindPreg('#\\\\"pageId\\\\":\\\\"([.,\-\w]+)\\\\"#');

        if (!isset($duaid, $tuid)) {
            $this->ParseV1();
            return;
        }

        if ($this->host != 'www.hotels.com') {
            $this->logger->notice('check other region // MI');
            $this->ParseOther($duaid, $tuid, $expUserId, $clientInfo, $pageId);
            return;
        }

         $headers = [
            'Accept' => '*/*',
            'client-info' => $clientInfo,
            //"universal-profile-ui,ac8c4771192a3af0ece3620679b108d70e7e1e30,us-west-2",
            'Content-Type' => 'application/json',
            'Origin' => "https://{$this->host}",
            'x-page-id' => $pageId,
        ];
        $data = '[{"operationName":"LoyaltyAccountSummary","variables":{"context":{"siteId":300000001,"locale":"en_US","eapid":1,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":null,"tuid":null,"authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}},"viewId":null,"strategy":"SHOW_TRAVELER_INFO_AND_REWARDS_LINK"},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"13b36850033f8a79773188e181b7ae251e2a09b725cd319cc850e74bd5791047"}}}]';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // Balance - OneKeyCash TM
        $this->SetBalance($this->http->FindPreg('/"rewardsAmount":"(.+?)","/'));
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->Response['code'] == 200 /*&& $this->http->FindPreg('/"availableValueSection":null/')*/) {
            $this->sendNotification('not balance // MI');
            $this->ParseV1();
            return;
        }
        // Currency
        $this->SetProperty('Currency', $this->http->getCookieByName('currency'));

        // Name
        $this->SetProperty('Name', $this->http->FindPreg('/"LoyaltyAccountTraveler","title":"Hi, (.+?)",/'));
        // Status
        $this->SetProperty('Status', $this->http->FindPreg('/"theme":"global-lowtier","size":"large","text":"([\w\s]+)"},/'));

         $headers = [
             'Accept' => '*/*',
             'client-info' => $clientInfo, //"universal-profile-ui,ac8c4771192a3af0ece3620679b108d70e7e1e30,us-west-2",
             'Content-Type' => 'application/json',
             'origin' => 'https://www.hotels.com',
             'x-page-id' => 'page.User.Rewards',
         ];
        $data = '[{"operationName":"LoyaltyTierProgressionQuery","variables":{"context":{"siteId":300000001,"locale":"en_US","eapid":1,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":"'.$expUserId.'","tuid":"'.$tuid.'","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}}},"query":"query LoyaltyTierProgressionQuery($context: ContextInput!) {\n  loyaltyTierProgression(context: $context) {\n    __typename\n    sections {\n      ...LoyaltyTierProgressionSectionFragment\n      __typename\n    }\n  }\n}\n\nfragment LoyaltyTierProgressionSectionFragment on LoyaltyTierProgressionSection {\n  __typename\n  content {\n    ...LoyaltyTierProgressionSectionContentTypesFragment\n    __typename\n  }\n}\n\nfragment LoyaltyTierProgressionSectionContentTypesFragment on LoyaltyTierProgressionSectionContent {\n  ...EGDSBasicSectionHeadingFragment\n  ...LoyaltyTierProgressionDetailsFragment\n  __typename\n}\n\nfragment EGDSBasicSectionHeadingFragment on EGDSBasicSectionHeading {\n  __typename\n  heading {\n    ...EGDSHeadingFragment\n    __typename\n  }\n  subheading\n}\n\nfragment EGDSHeadingFragment on EGDSHeading {\n  text\n  __typename\n}\n\nfragment LoyaltyTierProgressionDetailsFragment on LoyaltyTierProgressionDetails {\n  __typename\n  title\n  tierProgressionDetails {\n    ...EGDSProgressBarFragment\n    __typename\n  }\n  description\n}\n\nfragment EGDSProgressBarFragment on EGDSProgressBar {\n  percent\n  accessibilityLabel\n  __typename\n}\n"}]';
        $this->http->PostURL("https://{$this->host}/graphql", $data, $headers);
        $response = $this->http->JsonLog();

        // Trips collected to next status - "0 of 5 trip elements collected to reach Silver"
        $tripToNextStatus = $this->http->FindPreg('#"(\d+) of \d+ trip elements collected to reach \w+"#');
        $this->SetProperty('TripToNextStatus', $tripToNextStatus);
        // Trip elements reset date - "Your trip elements reset to 0 on December 31, 2023."
        $tripResetDate = $this->http->FindPreg('#"Your trip elements reset to \d+ on (\w+ \d+, \d{4})\."#');
        $this->SetProperty('TripResetDate', $tripResetDate);
    }

    public function ParseOther($duaid, $tuid, $expUserId, $clientInfo, $pageId) {
        $this->logger->notice(__METHOD__);
        $appVersion = $this->http->FindPreg('#."app_version.":."([^"]+)."#');

        $headers = [
            'Accept' => '*/*',
            'client-info' => $clientInfo,
            'Content-Type' => 'application/json',
            'Origin' => "https://{$this->host}",
            'x-page-id' => $pageId,
            'Client-Info' => "blossom-flex-ui,$appVersion,us-east-1"
        ];
        $data = '[{"operationName":"MemberWalletQuery","variables":{"context":{"siteId":300000005,"locale":"en_GB","eapid":5,"currency":"GBP","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":null,"tuid":null,"authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"adc0521e69bbc1b11f8dedb043f9141de11efb1c4ed2bad578cd7f6292fa32f1"}}}]';
        //$data = '[{"operationName":"MemberWalletQuery","variables":{"context":{"siteId":300000001,"locale":"en_US","eapid":3,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":"'.$expUserId.'","tuid":"'.$tuid.'","authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"adc0521e69bbc1b11f8dedb043f9141de11efb1c4ed2bad578cd7f6292fa32f1"}}}]';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        foreach ($response[0]->data->memberWallet->details->items as $item) {
            if (in_array($item->label, ['Collected stamps', 'Selos juntados'])) {
                // Selos juntados
                $this->SetBalance($item->text);
            }
            elseif (in_array($item->label, ['Reward nights', 'Noites de recompensa'])) {
                // Noites de recompensa
                $this->SetProperty('RewardsNights', $item->text);
            }
        }
        foreach ($response[0]->data->memberWallet->info->items as $item) {
            if (isset($item->theme) && $item->theme == 'LOYALTY_HIGH_TIER') {
                switch ($item->text) {
                    case "Associado Gold":
                    case "Gold Member":
                        $this->SetProperty('Status', 'Gold');

                        break;
                    default:
                        $this->sendNotification("new status: {$item->text} // MI");
                }
            }
        }


        $this->http->GetURL("https://{$this->host}/account/hotelscomrewards.html");
        //$this->SetProperty('Name', $this->http->FindSingleNode("//div[@class='banner-content']/p"));
        // 11 selos juntados
        $this->SetProperty('StampsCollected', $this->http->FindSingleNode("//span[contains(text(),' selos juntados')]/em"));
        // Junte mais 19 selos até 12 jan 2024 para manter o seu status Gold no próximo ano.
        $this->SetProperty('StampsToMaintainCurrentTier', $this->http->FindSingleNode("//div[@class='progress-message' and contains(.,'Junte mais') and contains(.,'selos até')]/strong[1]", null, false, '/^(\d+)$/'));

        $this->ParseV1();

    }

    public function ParseV1()
    {
        $this->http->FilterHTML = false;
        $this->logger->notice(__METHOD__);
        $this->http->GetURL(self::REWARDS_PAGE_URL_V1);

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'membership-name']")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'name']")));
        }

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='item-member']/div[@title]/@title")));
        }

        // Membership number
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Membership Number')]", null, true, "/Number:\s*([^<]+)/ims"));

        if (!isset($this->Properties['Number'])) {
            $this->SetProperty("Number", $this->http->FindSingleNode("//span[@id = 'membership-number-value']"));
        }

        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//span[@class = 'membership-tier']", null, true, '/Hotels.com(?:®|™)\s*Rewards\s*([^<]+)/ims'));

        if (!isset($this->Properties['Status'])) {
            if ($status =
                    $this->http->FindSingleNode("//div[@class = 'banner-content']//img[@alt = 'Hotels.com® Rewards']/@src")
                    ?? $this->http->FindSingleNode("//div[@class = 'loyalty-lockup']/img[@alt = 'Hotels.com® Rewards']/@src")
            ) {
                $status = basename($status);
                $this->logger->debug(">>> Status " . $status);

                switch ($status) {
                    case strstr($status, 'rewards-logo-white-no-moon-en_'):
                    case 'rewards-logo-purple-moon-en_US.png':
                        $this->SetProperty("Status", "Member");

                        break;

                    case strstr($status, 'rewards-logo-white-silver-no-moon-en_'):
                    case 'rewards-logo-silver-moon-en_US.png':
                        $this->SetProperty("Status", "Silver");

                        break;

                    case strstr($status, 'rewards-logo-white-gold-no-moon-en_'):
                    case 'rewards-logo-gold-moon-en_US.png':
                        $this->SetProperty("Status", "Gold");

                        break;

                    default:
                        if (!empty($status) && $this->ErrorCode === ACCOUNT_CHECKED) {
                            $this->sendNotification("Unknown status: $status");
                        }
                }// switch ($status)
            }// if ($status = $this->browser->FindSingleNode("//img[@alt = 'Hotels.com® Rewards']/@src"))
        }// if (empty($this->Properties['Status']))

        // Nights needed to next level
        $this->SetProperty('NightsNeededToNextLevel',
            $this->http->FindPreg("/Stay (\d+) more (?:night|stamp)s? by\s*(?:\d\d\/\d\d\/\d\d|[\w\d\,\s]+)?\s*to reach Hotels.com® Rewards/ims")
            ?? $this->http->FindPreg("/Collect\s*<strong>(\d+)<\/strong>\s*(?:more\s*|)(?:night|stamp)s?\s*by\s*<strong>[^<]+<\/strong>\s*to\s*become\s*a/ims")
        );
        // Nights needed to maintain level
        $this->SetProperty('NightsNeededToMaintainLevel', $this->http->FindPreg("/Stay (\d+) more (?:night|stamp)s? by\s*(?:\d\d\/\d\d\/\d\d|[\w\d\,\s]+)?\s*to maintain your Hotels.com® Rewards/ims"));
        // nights collected (Nights during current membership year)
        $this->SetProperty("NightsDuringCurrentMembershipYear",
            $this->http->FindPreg("/You have stayed (\d+) nights? during your current membership year/ims")
            ?? $this->http->FindSingleNode("//div[@class = 'collected-night-count']/span[contains(text(), 'collected')]/em")
        );

        // Balance - You’ve collected ... nights / stamps towards your free night. Keep going!
        if (!$this->SetBalance($this->http->FindPreg("/<span class=\"squircle\"><\/span>(\d+) (?:night|stamp)s? collected</"))) {
            // Start collecting towards your next free night\
            if (
                $this->http->FindPreg("/(?:Start collecting towards your next free (?:night|stamp)\.|(?:Night|Stamp)s will appear in your account up to 72 hours after you check out\.)/ims")
                || $this->http->FindSingleNode('//div[contains(@class, "aside")]//div[contains(@class, "punchcard-container")]//p[@class = "explanation"]', null, true, "/Collect 10 (?:more\s*|\s*)(?:night|stamp)s?(?:,|\s*to) get (?:another|1) reward\* night/")
                || $this->http->FindSingleNode("//p[contains(text(),'Collect 10 stamps, get 1 reward* night')]")
            ) {
                $this->SetBalance(0);
            } elseif (
                $this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')
                && count($this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')) > 0
            ) {
                $this->SetBalance(count($this->http->FindSingleNode('//div[contains(@class, "aside")]//li[@class = "night-icon earned"]')));
            } else {
                $this->notAMember();
            }
        }

        // Last activity
        $lastActivity = $this->http->FindSingleNode("//div[contains(text(), 'Most recent activity')]", null, true, '/activity\s*([a-z]{3}\s*\d+\,\s*\d{4})/ims');

        if (!$lastActivity) {
            $lastActivity = $this->http->FindSingleNode("//span[@id = 'membership-last-activity-value']");
        }
        $this->SetProperty("LastActivity", $lastActivity);
        // Expiration Date  // refs #4738
        if (isset($lastActivity) && strtotime($lastActivity)) {
            $exp = strtotime("+12 month", strtotime($lastActivity));
            $this->SetExpirationDate($exp);
        }

        if ($se = $this->http->FindPreg("/You can enjoy your membership benefits until ([^\.]+)./ims")) {
            $this->SetProperty('StatusExpiration', $se);
        }

        if ($se = $this->http->FindPreg("/membership until <strong>([^<]+)<\/strong>\.\s*Collect/ims")) {
            $this->SetProperty('StatusExpiration', $se);
        }

        $this->logger->info('Free nights', ['Header' => 3]);
        // Number of Free Night
        $freeNights =
            $this->http->FindSingleNode("//h3[contains(@class,'with-icon') and contains(., 'reward*') and contains(., 'night')]", null, false, "/(\d+)\s*reward\*\s*night/")
            ?? $this->http->FindSingleNode("//h3[contains(@class,'with-icon') and contains(., ' collected') and contains(., 'night')]", null, false, "/(\d+)\s*night/");
        // Next Free Nights
        $this->SetProperty("UntilNextFreeNight", $this->http->FindSingleNode('//div[contains(@class, "aside")]//div[contains(@class, "punchcard-container")]//p[@class = "explanation"]', null, true, "/Collect (\d+) (?:more\s*|\s*)(?:night|stamp)s?(?:,|\s*to) get (?:another|1) reward\* night/"));

        $this->logger->debug("Free nights: {$freeNights}");

        if (isset($freeNights) && $freeNights > 0) {
            $this->SetProperty("CombineSubAccounts", false);
            // SubAccounts Properties
            // Expiration Date  // refs #18483
            $expDate =
                $this->http->FindSingleNode('//div[@id = "collected-nights"]//div[contains(@class, "expiry-info")]/strong')
                ?? $this->http->FindSingleNode('//div[@id = "collected-nights"]//div[contains(@class, "expiry-info")]//strong', null, true, "/extended until\s*(.+)/")
            ;
            $expDate = strtotime($expDate);
            $freeNightList = $this->http->FindNodes('//div[contains(@class, "free-night-details")]//p[contains(@class, "price")]');
            $subAccounts = [];

            foreach ($freeNightList as $worth) {
                $subAccount = [
                    'Code'        => 'hotelsFreeNight' . md5($worth),
                    'DisplayName' => sprintf('Free Night Up To %s', $worth),
                    'Balance'     => null,
                    // Free Night Up To     // refs #14472
                    'FreeNightUpTo'  => $worth,
                    'ExpirationDate' => $expDate ?: $exp ?? false,
                ];

                // refs #21579
                if (isset($subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']])) {
                    $this->logger->debug("such subAcc already exist: +1");

                    if ($subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'] == null) {
                        $subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'] = 1;
                    }

                    ++$subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']]['Balance'];

                    continue;
                }

                $subAccounts[$subAccount['Code'] . '-' . $subAccount['ExpirationDate']] = $subAccount;
            }
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        // Retries
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // You are not a member of this loyalty program
            if ($this->http->FindSingleNode("//h2[contains(text(), 'We are having technical issues.')]")) {
                if ($link = $this->http->FindSingleNode("//a[contains(text(), 'Welcome Rewards®')]/@href")) {
                    $this->http->GetURL($link);
                }

                if ($this->http->FindSingleNode("//a[contains(@href, 'https://www.hotels.com/account/welcomerewards.html?enroll=true')]/@href")
                    || $this->http->FindSingleNode("(//a[contains(@href, 'https://www.hotels.com/profile/signup.html?wrEnrollment=true')]/@href)[1]")
                ) {
                    $this->SetWarning(self::NOT_MEMBER_MSG);
                }
            }// if ($this->browser->FindSingleNode("//h2[contains(text(), 'We are having technical issues.')]"))
            // AccountID: 1559035, 894961, 2611724 and other
            elseif ($this->http->Response['code'] == 500) {
                $this->logger->notice("Provider bug, try to parse properties from profile page");
                $this->http->GetURL("https://www.hotels.com/profile/summary.html");
                // Balance - You’ve collected ... nights towards your free night. Keep going!
                $this->SetBalance(count($this->http->FindNodes("//div[@class = 'card']/ul/li[@class = 'earned']")));
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'membership-name']")));
                // Account number
                $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Membership Number')]", null, true, "/Number:\s*([^<]+)/ims"));
                //# Status
                $this->SetProperty('Status', $this->http->FindSingleNode("//span[@class = 'membership-tier']", null, true, '/Hotels.com(?:®|™)\s*Rewards\s*([^<]+)/ims'));

                $rewardsNights = $this->http->FindSingleNode("//p[@class = 'redeem']/span");
                $subAccounts = [];

                if (isset($rewardsNights) && $rewardsNights > 0) {
                    $this->sendNotification("hotels - refs #13880. New subacc logic");
                    $this->SetProperty("CombineSubAccounts", false);
                    // SubAccounts Properties
                    $subAccounts[] = [
                        'Code'        => 'hotelsRewardsNights',
                        'DisplayName' => 'Rewards Nights',
                        'Balance'     => $rewardsNights,
                    ];
                    // Set SubAccounts Properties
                    $this->SetProperty("SubAccounts", [$subAccounts]);
                }// if (isset($rewardsNights) && $rewardsNights > 0)
            }// elseif ($this->browser->Response['code'] == 500)
            else {
                $currentUrl = $this->http->currentUrl();
                $this->logger->debug($currentUrl);

                if ($currentUrl == 'https://www.hotels.com/profile/landing.html') {
                    if ($this->http->FindPreg("/Sorry, we weren’t able to show your Hotels\.com® Rewards activities due to a technical issue/is")) {
                        $this->SetBalanceNA();
                    }
                }

                $this->http->GetURL("https://www.hotels.com/hotel-rewards-pillar/hotelscomrewards.html?intlid=ACCOUNT_SUMMARY+%3A%3A+header_main_section");
                // Your Welcome Rewards® account has been deactivated.
                if ($message = $this->http->FindPreg("/(Your (?:Welcome Rewards®|Hotels.com® Rewards) account has been deactivated\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->notAMember();

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->http->GetURL('https://www.hotels.com/');
                    $this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Collected stamps')]/../preceding-sibling::div/div", null, false, '/^\d+$/'));
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function notAMember()
    {
        $this->logger->notice(__METHOD__);
        // We are having technical issues
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Unlock Secret Prices")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Instant savings. Reward* nights. And more")]/following-sibling::div[a[contains(text(), "Join Now")]]')
            || ($this->http->FindPreg('/By enrolling, I agree to the full <a href="http:\/\/www\.hotels\.com\/customer_care\/terms_conditions.html" rel="nofollow" class="hcomPopup" target="_blank">\s*Terms and Conditions<\/a> of the program./ims')
                && $this->http->FindSingleNode('(//p[contains(text(), "Sorry, we weren\'t able to show your Welcome Rewards")])[1]'))
            || ($this->http->FindPreg("/Our loyalty program is now called Hotels.com® Rewards\. <a href=\"\/hotel-rewards-pillar\/hotelscomrewards\.html\">Enjoy free\* nights and Secret Prices<\/a> that are so low/ims")
                && $this->http->FindSingleNode('//p[contains(text(), "Sorry, we weren’t able to show your Hotels.com® Rewards")]'))
            || $this->http->FindPreg('/(By enrolling I agree to the full \&lt;a href=.+&gt;Terms and Conditions\&lt;\/a&gt; of the program.)/ims')
            || $this->http->FindPreg('/(By enrolling, I agree to the full <a href=."\/customer_care\/terms_conditions\.html.">Terms and Conditions<\/a> of the program\.)/ims')
            || $this->http->FindPreg('/(By enrolling, I agree to the full \&lt;a href=."\/customer_care\/terms_conditions\.html."\&gt;Terms and Conditions\&lt;\/a\&gt; of the program\.)/ims')
            // AccountID: 5593514
            /*
            || $this->http->FindSingleNode("//a[contains(text(), 'Start earning today')]")
            */
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
    }

    public function ParseItineraries($providerHost = 'www.expedia.com', $ParsePastIts = false)
    {
        //$this->http->GetURL("https://{$this->host}/trips");

        $expedia = $this->getExpedia();

        return $expedia->ParseItineraries($this->host, $this->ParsePastIts);
    }

    public function GetConfirmationFields()
    {
        return [
            "Email" => [
                "Caption"  => "Email address",
                "Type"     => "string",
                "Size"     => 40,
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.hotels.com/trips/booking-search?view=SEARCH_BY_ITINERARY_NUMBER_AND_EMAIL";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('check confirmation // MI');
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');

        if (!isset($duaid)) {
            return [];
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'client-info'          => 'trips-pwa,4484d3137c7eb9c7e64b788b606f32fdb276b64b,us-west-2',
            'Origin'               => 'https://www.hotels.com',
        ];
        $data = '[{"operationName":"TripSearchBookingQuery","variables":{"viewType":"SEARCH_RESULT","context":{"siteId":69,"locale":"en_US","eapid":0,"currency":"BRL","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","expUserId":"-1","tuid":"-1","authState":"ANONYMOUS"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}},"searchInput":[{"key":"EMAIL_ADDRESS","value":"' . $arFields['Email'] . '"},{"key":"ITINERARY_NUMBER","value":"' . $arFields['ConfNo'] . '"}]},"query":"query TripSearchBookingQuery($context: ContextInput!, $searchInput: [GraphQLPairInput!], $viewType: TripsSearchBookingView!) {\n  trips(context: $context) {\n    searchBooking(searchInput: $searchInput, viewType: $viewType) {\n      ...TripsViewFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsViewFragment on TripsView {\n  __typename\n  ...TripsViewContentFragment\n  floatingActionButton {\n    ...TripsFloatingActionButtonFragment\n    __typename\n  }\n  ...TripsDynamicMapFragment\n  pageTitle\n  contentType\n  tripsSideEffects {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentFragment on TripsView {\n  __typename\n  header {\n    ...ViewHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsSectionContainerFragment\n    ...TripsFormContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPageBreakFragment\n    ...TripsContainerDividerFragment\n    ...TripsLodgingUpgradesPrimerFragment\n    ...TripItemContextualCardsPrimerFragment\n    __typename\n  }\n  notifications: customerNotifications {\n    ...TripsCustomerNotificationsFragment\n    __typename\n  }\n  toast {\n    ...TripsToastFragment\n    __typename\n  }\n  contentType\n}\n\nfragment ViewHeaderFragment on TripsViewHeader {\n  __typename\n  primary\n  secondaries\n  toolbar {\n    ...ToolbarFragment\n    __typename\n  }\n  signal {\n    type\n    reference\n    __typename\n  }\n}\n\nfragment TripsTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsLinkActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsLinkActionFragment on TripsLinkAction {\n  __typename\n  resource {\n    value\n    __typename\n  }\n  target\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n}\n\nfragment ClickstreamAnalyticsFragment on ClickstreamAnalytics {\n  event {\n    clickstreamTraceId\n    eventCategory\n    eventName\n    eventType\n    eventVersion\n    __typename\n  }\n  payload {\n    ... on TripRecommendationModule {\n      title\n      responseId\n      recommendations {\n        id\n        position\n        priceDisplayed\n        currencyCode\n        name\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment MapDirectionsActionFragment on TripsMapDirectionsAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsWriteToClipboardActionFragment on CopyToClipboardAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  value\n}\n\nfragment TripsVirtualAgentInitActionFragment on TripsVirtualAgentInitAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  applicationName\n  pageName\n  clientOverrides {\n    enableAutoOpenChatWidget\n    enableProactiveConversation\n    subscribedEvents\n    conversationProperties {\n      launchPoint\n      pageName\n      skipWelcome\n      __typename\n    }\n    intentMessage {\n      ... on VirtualAgentCancelIntentMessage {\n        action\n        intent\n        emailAddress\n        orderLineId\n        orderNumber\n        product\n        __typename\n      }\n      __typename\n    }\n    intentArguments {\n      id\n      value\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenDialogActionFragment on TripsOpenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  modalDialog {\n    ...TripsModalDialogFragment\n    __typename\n  }\n}\n\nfragment TripsModalDialogFragment on TripsModalDialog {\n  __typename\n  heading\n  buttonLayout\n  buttons {\n    ...TripsDialogPrimaryButtonFragment\n    ...TripsDialogSecondaryButtonFragment\n    ...TripsDialogTertiaryButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsDialogPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogActionFragment on TripsCloseDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsDeleteTripActionFragment on TripsDeleteTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  overview {\n    tripViewId\n    filter\n    __typename\n  }\n}\n\nfragment TripsCancelCarActionFragment on TripsCancelCarAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumbers\n}\n\nfragment TripsCancelInsuranceActionFragment on TripsCancelInsuranceAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumber\n}\n\nfragment TripsCancelActivityActionFragment on TripsCancelActivityAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  activityOrderLineNumbers: orderLineNumbers\n  orderNumber\n}\n\nfragment TripsCancellationActionFragment on TripsCancellationAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  itemToCancel: item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  cancellationType\n  cancellationAttributes {\n    orderNumber\n    orderLineNumbers\n    refundAmount\n    penaltyAmount\n    __typename\n  }\n}\n\nfragment TripsUnsaveItemFromTripActionFragment on TripsUnsaveItemFromTripAction {\n  __typename\n  tripEntity\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItem {\n    tripItemId\n    tripViewId\n    filter\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsEmitSignalFragment on TripsEmitSignal {\n  signal {\n    type\n    reference\n    __typename\n  }\n  values {\n    key\n    value {\n      ...TripsSignalFieldIdValueFragment\n      ...TripsSignalFieldIdExistingValuesFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSignalFieldIdExistingValuesFragment on TripsSignalFieldIdExistingValues {\n  ids\n  prefixes\n  __typename\n}\n\nfragment TripsSignalFieldIdValueFragment on TripsSignalFieldIdValue {\n  id\n  __typename\n}\n\nfragment TripsEmbeddedContentListFragment on TripsEmbeddedContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsEmbeddedContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsEmbeddedContentLineItemFragment on TripsEmbeddedContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsEmbeddedContentItemFragment\n  }\n}\n\nfragment TripsEmbeddedContentItemFragment on TripsEmbeddedContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsOpenFullScreenDialogActionFragment on TripsOpenFullScreenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  dialog {\n    ...TripsFullScreenDialogFragment\n    __typename\n  }\n}\n\nfragment TripsFullScreenDialogFragment on TripsFullScreenDialog {\n  __typename\n  heading\n  closeButton {\n    ...TripsCloseDialogButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogButtonFragment on TripsCloseDialogButton {\n  __typename\n  primary\n  icon {\n    __typename\n    id\n    description\n    title\n  }\n  action {\n    __typename\n    analytics {\n      __typename\n      referrerId\n      linkName\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n    }\n  }\n}\n\nfragment TripsEmbeddedContentCardFragment on TripsEmbeddedContentCard {\n  __typename\n  primary\n  items {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsOpenMenuActionFragment on TripsOpenMenuAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  floatingMenu {\n    ...TripsFloatingMenuFragment\n    __typename\n  }\n}\n\nfragment TripsFloatingMenuFragment on TripsFloatingMenu {\n  items {\n    ...TripsMenuTitleFragment\n    ...TripsMenuListItemFragment\n    ...TripsMenuListTitleFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListItemFragment on TripsMenuListItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenChangeDatesDatePickerActionFragment\n    ...TripsOpenEmailDrawerActionFragment\n    ...TripsOpenEditTripDrawerActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsOpenSaveToTripDrawerActionFragment\n    ...TripsCustomerNotificationOpenInAppActionFragment\n    __typename\n  }\n}\n\nfragment TripsNavigateToViewActionFragment on TripsNavigateToViewAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItemId\n  tripViewId\n  viewFilter {\n    filter\n    __typename\n  }\n  viewType\n  viewUrl\n}\n\nfragment TripsOpenChangeDatesDatePickerActionFragment on TripsOpenChangeDatesDatePickerAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  attributes {\n    ...TripsListDatePickerAttributesFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListDatePickerAttributesFragment on TripsDatePickerAttributes {\n  analytics {\n    closeAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  buttonText\n  changeDatesAction {\n    analytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    item {\n      filter\n      tripItemId\n      tripViewId\n      __typename\n    }\n    tripEntity\n    __typename\n  }\n  maxDateRange\n  maxDateRangeMessage\n  calendarSelectionType\n  daysBookableInAdvance\n  itemDates {\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  productId\n  __typename\n}\n\nfragment TripsListDateFragment on Date {\n  day\n  month\n  year\n  __typename\n}\n\nfragment TripsOpenEmailDrawerActionFragment on TripsOpenEmailDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    __typename\n    filter\n    tripItemId\n    tripViewId\n  }\n}\n\nfragment TripsOpenEditTripDrawerActionFragment on TripsOpenEditTripDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenMoveTripItemDrawerActionFragment on TripsOpenMoveTripItemDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsOpenSaveToTripDrawerActionFragment on TripsOpenSaveToTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  input {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsListSaveItemAttributesFragment on TripsSaveItemAttributes {\n  ...TripsListSaveStayAttributesFragment\n  ...TripsListSaveActivityAttributesFragment\n  ...TripsSaveFlightSearchAttributesFragment\n  __typename\n}\n\nfragment TripsListSaveActivityAttributesFragment on TripsSaveActivityAttributes {\n  regionId\n  dateRange {\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListSaveStayAttributesFragment on TripsSaveStayAttributes {\n  checkInDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  checkoutDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  regionId\n  roomConfiguration {\n    numberOfAdults\n    childAges\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSaveFlightSearchAttributesFragment on TripsSaveFlightSearchAttributes {\n  searchCriteria {\n    primary {\n      journeyCriterias {\n        arrivalDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        departureDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        destination\n        destinationAirportLocationType\n        origin\n        originAirportLocationType\n        __typename\n      }\n      searchPreferences {\n        advancedFilters\n        airline\n        cabinClass\n        __typename\n      }\n      travelers {\n        age\n        type\n        __typename\n      }\n      tripType\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationOpenInAppActionFragment on TripsCustomerNotificationOpenInAppAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  notificationAttributes {\n    notificationLocation\n    xPageID\n    optionalContext {\n      tripItemId\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListTitleFragment on TripsMenuListTitle {\n  __typename\n  primary\n}\n\nfragment TripsMenuTitleFragment on TripsMenuTitle {\n  __typename\n  primary\n}\n\nfragment ClientSideImpressionAnalyticsFragment on ClientSideImpressionAnalytics {\n  uisPrimeAnalytics {\n    ...ClientSideAnalyticsFragment\n    __typename\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment ClientSideAnalyticsFragment on ClientSideAnalytics {\n  eventType\n  linkName\n  referrerId\n  uisPrimeMessages {\n    messageContent\n    schemaName\n    __typename\n  }\n  __typename\n}\n\nfragment TripsOpenInviteDrawerActionFragment on TripsOpenInviteDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerActionFragment on TripsOpenCreateNewTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerForItemActionFragment on TripsOpenCreateNewTripDrawerForItemAction {\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  createTripMetadata {\n    moveItem {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    saveItemInput {\n      itemId\n      pageLocation\n      attributes {\n        ...TripsListSaveItemAttributesFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInviteActionFragment on TripsInviteAction {\n  __typename\n  inputIds\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsSaveNewTripActionFragment on TripsSaveNewTripAction {\n  __typename\n  inputIds\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormActionFragment on TripsFormAction {\n  __typename\n  validatedInputIds\n  type\n  formData {\n    ...TripsFormDataFragment\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormDataFragment on TripsFormData {\n  __typename\n  ...TripsCreateTripFromMovedItemFragment\n  ...TripsInviteFragment\n  ...TripsSendItineraryEmailFragment\n  ...TripsUpdateTripFragment\n  ...TripsCreateTripFromItemFragment\n}\n\nfragment TripsCreateTripFromMovedItemFragment on TripsCreateTripFromMovedItem {\n  __typename\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsInviteFragment on TripsInvite {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsSendItineraryEmailFragment on TripsSendItineraryEmail {\n  __typename\n  item {\n    tripViewId\n    tripItemId\n    filter\n    __typename\n  }\n}\n\nfragment TripsUpdateTripFragment on TripsUpdateTrip {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsCreateTripFromItemFragment on TripsCreateTripFromItem {\n  __typename\n  input {\n    itemId\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    pageLocation\n    __typename\n  }\n}\n\nfragment ToolbarFragment on TripsToolbar {\n  __typename\n  primary\n  secondaries\n  accessibility {\n    label\n    __typename\n  }\n  actions {\n    primary {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    secondaries {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsCarouselContainerFragment on TripsCarouselContainer {\n  __typename\n  heading\n  subheading {\n    ...TripsCarouselSubHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    __typename\n  }\n  accessibility {\n    ... on TripsCarouselAccessibilityData {\n      nextButton\n      prevButton\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsContentCardFragment on TripsContentCard {\n  __typename\n  primary\n  secondaries\n  rows {\n    __typename\n    ...ContentColumnsFragment\n    ...TripsViewContentListFragment\n    ...EmblemsInlineContentFragment\n  }\n}\n\nfragment ContentColumnsFragment on TripsContentColumns {\n  __typename\n  primary\n  columns {\n    __typename\n    ...TripsViewContentListFragment\n  }\n}\n\nfragment TripsViewContentListFragment on TripsContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsViewContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentLineItemFragment on TripsViewContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsViewContentItemFragment\n  }\n}\n\nfragment TripsViewContentItemFragment on TripsViewContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n}\n\nfragment EmblemsInlineContentFragment on TripsEmblemsInlineContent {\n  __typename\n  primary\n  secondaries\n  emblems {\n    ...TripsEmblemFragment\n    __typename\n  }\n}\n\nfragment TripsEmblemFragment on TripsEmblem {\n  ...BadgeFragment\n  ...EGDSMarkFragment\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment BadgeFragment on TripsBadge {\n  accessibility\n  text\n  tripsBadgeTheme: theme\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  __typename\n}\n\nfragment UIGraphicFragment on UIGraphic {\n  ...EGDSIconFragment\n  ...EGDSMarkFragment\n  ...EGDSIllustrationFragment\n  __typename\n}\n\nfragment EGDSIconFragment on Icon {\n  description\n  id\n  size\n  theme\n  title\n  withBackground\n  __typename\n}\n\nfragment EGDSMarkFragment on Mark {\n  description\n  id\n  markSize: size\n  url {\n    ... on HttpURI {\n      __typename\n      relativePath\n      value\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSIllustrationFragment on Illustration {\n  id\n  description\n  link: url\n  __typename\n}\n\nfragment EGDSStandardBadgeFragment on EGDSStandardBadge {\n  accessibility\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSLoyaltyBadgeFragment on EGDSLoyaltyBadge {\n  accessibility\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSProgramBadgeFragment on EGDSProgramBadge {\n  accessibility\n  text\n  theme\n  __typename\n}\n\nfragment TripsFullBleedImageCardFragment on TripsFullBleedImageCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  icons {\n    id\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSBadgeFragment on EGDSBadge {\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment TripsImageTopCardFragment on TripsImageTopCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...MapDirectionsActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSlimCardFragment on TripsSlimCard {\n  graphic {\n    ...EGDSIconFragment\n    ...EGDSMarkFragment\n    ...EGDSIllustrationFragment\n    __typename\n  }\n  primary\n  secondaries\n  subTexts {\n    ...TripsTextFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsTextFragment on TripsText {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment EGDSGraphicTextFragment on EGDSGraphicText {\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  __typename\n}\n\nfragment EGDSPlainTextFragment on EGDSPlainText {\n  text\n  __typename\n}\n\nfragment NavigateToManageBookingActionFragment on TripsNavigateToManageBookingAction {\n  __typename\n  item {\n    __typename\n    tripItemId\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsCarouselSubHeaderFragment on TripsCarouselSubHeader {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment TripsSectionContainerFragment on TripsSectionContainer {\n  ...TripsInternalSectionContainerFragment\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsMediaGalleryFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsImageSlimCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsFlightMapCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPricePresentationFragment\n    ...TripsSubSectionContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsServiceRequestsButtonPrimerFragment\n    ...TripsSlimCardContainerFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFittedImageCardFragment on TripsFittedImageCard {\n  primary\n  secondaries\n  img: image {\n    url\n    description\n    aspectRatio\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  imageType\n  __typename\n}\n\nfragment TripsMapCardFragment on TripsMapCard {\n  primary\n  secondaries\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  image {\n    url\n    description\n    __typename\n  }\n  action {\n    ...MapActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment MapActionFragment on TripsMapAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsImageSlimCardFragment on TripsImageSlimCard {\n  ...TripsInternalImageSlimCardFragment\n  signal {\n    type\n    reference\n    __typename\n  }\n  cardIcon {\n    ...TripsIconFragment\n    __typename\n  }\n  primaryAction {\n    ...TripsLinkActionFragment\n    ...TripsMoveItemToTripActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  itemPricePrimer {\n    ... on TripsSavedItemPricePrimer {\n      ...TripsSavedItemPricePrimerFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalImageSlimCardFragment on TripsImageSlimCard {\n  primary\n  secondaries\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  thumbnail {\n    aspectRatio\n    description\n    url\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    hint\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsIconFragment on TripsIcon {\n  action {\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    title\n    theme\n    __typename\n  }\n  label\n  __typename\n}\n\nfragment TripsSaveItemToTripActionFragment on TripsSaveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  tripId\n}\n\nfragment TripsSavedItemPricePrimerFragment on TripsSavedItemPricePrimer {\n  tripItem {\n    ...TripItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripItemFragment on TripItem {\n  filter\n  tripItemId\n  tripViewId\n  __typename\n}\n\nfragment TripsMoveItemToTripActionFragment on TripsMoveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  data {\n    item {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    toTripId\n    toTripName\n    __typename\n  }\n}\n\nfragment TripsIllustrationCardFragment on TripsIllustrationCard {\n  primary\n  secondaries\n  illustration {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPrimaryButtonFragment on TripsPrimaryButton {\n  ...TripsInternalPrimaryButtonFragment\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsSendItineraryEmailActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsUpdateTripActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsInviteAcceptActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsAcceptInviteAndNavigateToOverviewActionFragment\n    ...TripsCreateTripFromItemActionFragment\n    __typename\n  }\n  width\n  __typename\n}\n\nfragment TripsInternalPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n}\n\nfragment TripsSendItineraryEmailActionFragment on TripsSendItineraryEmailAction {\n  __typename\n  inputIds\n  item {\n    __typename\n    tripViewId\n    tripItemId\n    filter\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsUpdateTripActionFragment on TripsUpdateTripAction {\n  __typename\n  inputIds\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsInviteAcceptActionFragment on TripsInviteAcceptAction {\n  __typename\n  inviteId\n  analytics {\n    __typename\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsAcceptInviteAndNavigateToOverviewActionFragment on TripsAcceptInviteAndNavigateToOverviewAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    tripViewId\n    filter\n    inviteId\n    __typename\n  }\n  overviewUrl\n}\n\nfragment TripsCreateTripFromItemActionFragment on TripsCreateTripFromItemAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  inputIds\n}\n\nfragment TripsSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsFlightMapCardFragment on TripsFlightPathMapCard {\n  primary\n  secondaries\n  image {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPricePresentationFragment on TripsPricePresentation {\n  __typename\n  pricePresentation {\n    __typename\n    ...PricePresentationFragment\n  }\n}\n\nfragment PricePresentationFragment on PricePresentation {\n  title {\n    primary\n    __typename\n  }\n  sections {\n    ...PricePresentationSectionFragment\n    __typename\n  }\n  footer {\n    header\n    messages {\n      ...PriceLineElementFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSectionFragment on PricePresentationSection {\n  header {\n    name {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  subSections {\n    ...PricePresentationSubSectionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSubSectionFragment on PricePresentationSubSection {\n  header {\n    name {\n      primaryMessage {\n        __typename\n        ... on PriceLineText {\n          primary\n          __typename\n        }\n        ... on PriceLineHeading {\n          primary\n          __typename\n        }\n      }\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  items {\n    ...PricePresentationLineItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemFragment on PricePresentationLineItem {\n  enrichedValue {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  name {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...PriceLineElementFragment\n    __typename\n  }\n  secondaryMessages {\n    ...PriceLineElementFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PriceLineElementFragment on PricePresentationLineItemMessage {\n  __typename\n  ...PriceLineTextFragment\n  ...PriceLineHeadingFragment\n  ...PriceLineBadgeFragment\n  ...InlinePriceLineTextFragment\n}\n\nfragment PriceLineTextFragment on PriceLineText {\n  __typename\n  theme\n  primary\n  weight\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n}\n\nfragment PricePresentationAdditionalInformationFragment on PricePresentationAdditionalInformation {\n  ...PricePresentationAdditionalInformationDialogFragment\n  ...PricePresentationAdditionalInformationPopoverFragment\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFragment on PricePresentationAdditionalInformationDialog {\n  closeAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  footer {\n    ...PricePresentationAdditionalInformationDialogFooterFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  openAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterFragment on EGDSDialogFooter {\n  ... on EGDSInlineDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  ... on EGDSStackedDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterButtonsFragment on EGDSButton {\n  accessibility\n  disabled\n  primary\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationPopoverFragment on PricePresentationAdditionalInformationPopover {\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverFragment on AdditionalInformationPopover {\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverSectionFragment on AdditionalInformationPopoverSection {\n  __typename\n  ... on AdditionalInformationPopoverTextSection {\n    ...AdditionalInformationPopoverTextSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverListSection {\n    ...AdditionalInformationPopoverListSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverGridSection {\n    ...AdditionalInformationPopoverGridSectionFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverTextSectionFragment on AdditionalInformationPopoverTextSection {\n  __typename\n  text {\n    text\n    ...EGDSStandardLinkFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverListSectionFragment on AdditionalInformationPopoverListSection {\n  __typename\n  content {\n    __typename\n    items {\n      text\n      __typename\n    }\n  }\n}\n\nfragment AdditionalInformationPopoverGridSectionFragment on AdditionalInformationPopoverGridSection {\n  __typename\n  subSections {\n    header {\n      name {\n        primaryMessage {\n          ...AdditionalInformationPopoverGridLineItemMessageFragment\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    items {\n      name {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      enrichedValue {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverGridLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  secondaryMessages {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverGridLineItemMessageFragment on PricePresentationLineItemMessage {\n  ... on PriceLineText {\n    __typename\n    primary\n  }\n  ... on PriceLineHeading {\n    __typename\n    tag\n    size\n    primary\n  }\n  __typename\n}\n\nfragment PriceLineHeadingFragment on PriceLineHeading {\n  __typename\n  primary\n  tag\n  size\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n}\n\nfragment PriceLineBadgeFragment on PriceLineBadge {\n  __typename\n  badge {\n    accessibility\n    text\n    theme\n    __typename\n  }\n}\n\nfragment InlinePriceLineTextFragment on InlinePriceLineText {\n  __typename\n  inlineItems {\n    ...PriceLineTextFragment\n    __typename\n  }\n}\n\nfragment EGDSStandardLinkFragment on EGDSStandardLink {\n  action {\n    ...ActionFragment\n    __typename\n  }\n  standardLinkIcon: icon {\n    ...EGDSIconFragment\n    __typename\n  }\n  iconPosition\n  size\n  text\n  __typename\n}\n\nfragment ActionFragment on UILinkAction {\n  accessibility\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  resource {\n    value\n    __typename\n  }\n  target\n  __typename\n}\n\nfragment TripsSubSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsSlimCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    __typename\n  }\n}\n\nfragment TripsInternalSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  subheadings\n  tripsListSubTexts: subTexts\n  theme\n}\n\nfragment TripsSlimCardContainerFragment on TripsSlimCardContainer {\n  heading\n  subHeaders {\n    ...TripsTextFragment\n    __typename\n  }\n  slimCards {\n    ...TripsSlimCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListFlexContainerFragment on TripsFlexContainer {\n  ...TripsInternalFlexContainerFragment\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsAvatarGroupFragment\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalFlexContainerFragment on TripsFlexContainer {\n  __typename\n  alignItems\n  direction\n  justifyContent\n  wrap\n  elements {\n    ...TripsInternalFlexContainerItemFragment\n    __typename\n  }\n}\n\nfragment TripsInternalFlexContainerItemFragment on TripsFlexContainerItem {\n  grow\n  __typename\n}\n\nfragment TripsAvatarGroupFragment on TripsAvatarGroup {\n  avatars {\n    ...TripsAvatarFragment\n    __typename\n  }\n  avatarSize\n  showBorder\n  action {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsAvatarFragment on TripsAvatar {\n  name\n  url\n  __typename\n}\n\nfragment TripsServiceRequestsButtonPrimerFragment on TripsServiceRequestsButtonPrimer {\n  buttonStyle\n  itineraryNumber\n  lineOfBusiness\n  orderLineId\n  __typename\n}\n\nfragment TripsMediaGalleryFragment on TripsMediaGallery {\n  __typename\n  accessibilityHeadingText\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  nextButtonText\n  previousButtonText\n  mediaGalleryId: egdsElementId\n  media {\n    ...TripsMediaFragment\n    __typename\n  }\n  mediaGalleryDialogToolbar {\n    ...TripsMediaGalleryDialogFragment\n    __typename\n  }\n}\n\nfragment TripsMediaGalleryDialogFragment on TripsToolbar {\n  primary\n  secondaries\n  actions {\n    primary {\n      icon {\n        description\n        id\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMediaFragment on TripsMedia {\n  media {\n    ... on Image {\n      url\n      description\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFormContainerFragment on TripsFormContainer {\n  __typename\n  formTheme\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsContentCardFragment\n    ...TripsValidatedInputFragment\n    __typename\n  }\n}\n\nfragment TripsValidatedInputFragment on TripsValidatedInput {\n  egdsElementId\n  instructions\n  label\n  placeholder\n  required\n  value\n  inputType\n  leftIcon {\n    __typename\n    leftIconId: id\n    title\n    description\n  }\n  rightIcon {\n    __typename\n    rightIconId: id\n    title\n    description\n  }\n  validations {\n    ...EGDSMaxLengthInputValidationFragment\n    ...EGDSMinLengthInputValidationFragment\n    ...EGDSRegexInputValidationFragment\n    ...EGDSRequiredInputValidationFragment\n    ...EGDSTravelersInputValidationFragment\n    ...MultiEmailValidationFragment\n    ...SingleEmailValidationFragment\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSMaxLengthInputValidationFragment on EGDSMaxLengthInputValidation {\n  __typename\n  errorMessage\n  maxLength\n}\n\nfragment EGDSMinLengthInputValidationFragment on EGDSMinLengthInputValidation {\n  __typename\n  errorMessage\n  minLength\n}\n\nfragment EGDSRegexInputValidationFragment on EGDSRegexInputValidation {\n  __typename\n  errorMessage\n  pattern\n}\n\nfragment EGDSRequiredInputValidationFragment on EGDSRequiredInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment EGDSTravelersInputValidationFragment on EGDSTravelersInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment MultiEmailValidationFragment on MultiEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment SingleEmailValidationFragment on SingleEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment TripsPageBreakFragment on TripsPageBreak {\n  __typename\n  _empty\n}\n\nfragment TripsContainerDividerFragment on TripsContainerDivider {\n  divider\n  __typename\n}\n\nfragment TripsLodgingUpgradesPrimerFragment on TripsLodgingUpgradesPrimer {\n  itineraryNumber\n  __typename\n}\n\nfragment TripItemContextualCardsPrimerFragment on TripItemContextualCardsPrimer {\n  tripViewId\n  tripItemId\n  placeHolder {\n    url\n    description\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationsFragment on TripsCustomerNotificationQueryParameters {\n  funnelLocation\n  notificationLocation\n  optionalContext {\n    itineraryNumber\n    journeyCriterias {\n      dateRange {\n        start {\n          day\n          month\n          year\n          __typename\n        }\n        end {\n          day\n          month\n          year\n          __typename\n        }\n        __typename\n      }\n      destination {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      origin {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      tripScheduleChangeStatus\n      __typename\n    }\n    tripId\n    tripItemId\n    __typename\n  }\n  xPageID\n  __typename\n}\n\nfragment TripsToastFragment on TripsToast {\n  ...TripsInfoToastFragment\n  ...TripsInlineActionToastFragment\n  ...TripsStackedActionToastFragment\n  __typename\n}\n\nfragment TripsInfoToastFragment on TripsInfoToast {\n  primary\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInlineActionToastFragment on TripsInlineActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsToastButtonFragment on TripsButton {\n  __typename\n  primary\n  action {\n    ...TripsNavigateToViewActionFragment\n    ...TripsDismissActionFragment\n    __typename\n  }\n}\n\nfragment TripsDismissActionFragment on TripsDismissAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsStackedActionToastFragment on TripsStackedActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFloatingActionButtonFragment on TripsFloatingActionButton {\n  __typename\n  action {\n    ...TripsVirtualAgentInitActionFragment\n    __typename\n  }\n}\n\nfragment TripsDynamicMapFragment on TripsView {\n  egTripsMap {\n    ...DynamicMapFragment\n    __typename\n  }\n  egTripsCards {\n    ...TripsDynamicMapCardContentFragment\n    __typename\n  }\n  __typename\n}\n\nfragment DynamicMapFragment on EGDSBasicMap {\n  label\n  initialViewport\n  center {\n    latitude\n    longitude\n    __typename\n  }\n  zoom\n  bounds {\n    northeast {\n      latitude\n      longitude\n      __typename\n    }\n    southwest {\n      latitude\n      longitude\n      __typename\n    }\n    __typename\n  }\n  computedBoundsOptions {\n    coordinates {\n      latitude\n      longitude\n      __typename\n    }\n    gaiaId\n    lowerQuantile\n    upperQuantile\n    marginMultiplier\n    minMargin\n    minimumPins\n    interpolationRatio\n    __typename\n  }\n  config {\n    ... on EGDSDynamicMapConfig {\n      accessToken\n      egdsMapProvider\n      externalConfigEndpoint {\n        value\n        __typename\n      }\n      mapId\n      __typename\n    }\n    __typename\n  }\n  markers {\n    ... on EGDSMapFeature {\n      id\n      description\n      markerPosition {\n        latitude\n        longitude\n        __typename\n      }\n      type\n      markerStatus\n      qualifiers\n      text\n      clientSideAnalytics {\n        linkName\n        referrerId\n        __typename\n      }\n      onSelectAccessibilityMessage\n      onEnterAccessibilityMessage\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsDynamicMapCardContentFragment on EGDSImageCard {\n  id\n  description\n  image {\n    aspectRatio\n    description\n    thumbnailClickAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    url\n    __typename\n  }\n  title\n  __typename\n}\n"}]';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.hotels.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'OVERVIEW') {
                    $viewUrl = $element->action->viewUrl;

                    break 2;
                } elseif(isset($element->heading)) {
                    $this->logger->debug($element->heading);
                }
            }
        }

        if (!isset($viewUrl)) {
            return null;
        }
        $this->http->GetURL($viewUrl);
        $providerHost = "https://www.hotels.com";
        $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');
        $expedia = $this->getExpedia();

        foreach ($its as $it) {
            $this->http->GetURL($it);

            if ($expedia->ParseItineraryDetectType($providerHost, $arFields) === false) {
                //$this->delay();
                $this->http->GetURL($it);
                $this->increaseTimeLimit();
                $expedia->ParseItineraryDetectType($providerHost, $arFields);
            }
        }

        $this->logger->debug("Parsed data: " . var_export($this->itinerariesMaster->toArray(), true));

        return null;
    }

    public function ProcessStep($step): bool
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->sendNotification('check 2fa // MI');

        $captcha = $this->parseFunCaptcha(self::KEY_CAPTCHA, false, 'https://www.hotels.com/verifyotp?scenario=SIGNIN&path=email');

        if ($captcha === false) {
            return false;
        }
        $data = [
            'channelType'   => 'WEB',
            'email'         => $this->AccountFields['Login'],
            'username'      => $this->AccountFields['Login'],
            'passCode'      => $answer,
            'rememberMe'    => false,
            'scenario'      => 'SIGNIN',
            'atoShieldData' => [
                'atoTokens' => [
                    'fc-token'   => $captcha, //$this->State['fc-token'],
                    'code'       => $answer,
                ],
                'devices' => [
                    [
                        "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;0400LFvRnkBPGLK5mZwvTfjFe8DIejUQnwyr1gq69qqam6HDXO0fgXrC6I1KtGYeaD1eSrNJO9XS1gxUkwXgypKbJublb+zE9pLqtC8ufkKGWOHZXXGJTTBqE7LIyACkFDsfuJBsZbc5KTU09EGjF7mhegWAMQ+/uOi7Vbyeo7qBQvvvUSbJ+Ltz3R3+1lLYqHZEoh+4LtM7fx71iuYLfHbITVPzJ6LD0dHJb+O5jfppV2+w/6WKkpgGPoFxEHl0LyEwVhsmEzAPljdECCQraS7CFs03LA66yR+f10WsmcVbTTPWJNImN9XDyQkkWkfc8gPJtdZZDVcNwc57/h64k00M2ETPcxabrjpWpYsVrHEkTWjUB/w5mIGvs3S3/YVJTCZLHkEQNaBImA6qORRslV1jqFaLumuV7FltWrUhOJaEul4WkZ2lV1Vn00jChjzJ7a7So4SXB+lUnPvcEvS4velrztiLOt/ZJQDNDRYQW/1fOlUL1G1zSnesIGV0oDZ/IAHjVfr34+MzOahbvXp9aSPukrPgPahfzF10EHtG8toiPcsIgLZAX23/tMUUcQU9wxtsZbuY7ArtYNxaFsOI4iMIZF5q5wtN9U6Q0LVQmADUFBkeOXlaNyb7iN04g627KcavK2EaL6dpEzFI185O+yC2f3lLEO0Tay66NZEyiLNePemJKSIdwO9O5ZtntuUkG6NTUNqjV7CWFCQGLDM4fZkS+HhVKFtj4RUEzX0hM3tAJxtauODchk2WrH2LOmVQKLUdqHV8lCsVIFbWfvi2lw6riA1/InDlj2XMuF6OiUfqPLjYdgXmjJkcl4nbgdpTLCsZCTlzv30WEE36yc/35JaHDOM1xAfZF2iZhHGK+YNuZU5h5Nwu1x6PIDsDsHfN+ugZgRgN+Z8HAFhGFzd/m6snVgtcnU5pFekiB8n/NyT5AQJEWh+JVbpQPIEYDfmfBwBYRhc3f5urJ1YLXJ1OaRXpIgfJ/zck+QECRFofiVW6UDyBGA35nwcAWEYXN3+bqydWtp0PYHSzkHaNc557XJoPFyQZ+QQTVZZa96FOpPevDWtQv9D0MFSvQHxfYV3WeA0UPPK1E1+GGd8FkSmHWcad45L6XmBGHnEzmAfK3q8mseAtFVgYgbKYaXrhgCEbsnC9/H8neKkenzpHQnH/l0ey3BKePA1UZdKAZDwic+y5/r+SkyAbziDM7k8xAXTS4l7D1erHMnjL6rir3JhLDLnhgOB4gTqSk7BNTMj6Y82LrlflYU9iUMb03QtuHmc6Pe7cNepEKIoJSuxTQRdWXuxQ4XcoQPasyUmHkNrAXEvbWw5xi/UgQBHzx/F41BBObOFw6ZhPvPB88usL1G1zSnesIGV0oDZ/IAHjBesMEnAumSbMo6UW8YjioPg2gD0cN/wGECHSEPVc6ROetAtsi0+zEnNxiBmQs+dNXKPV5WsEjaBgWYiecIn+kamlkio4tFPBpCpjew4KL9figw2kblSw9UdiKruR7ZVqeEbUcR5tVcLJTxKqWyRuWEM3L3wcM/MiWQ9+Ohu+WNpLUNHa/XnhE7Sr/aHivJZGolL0uHivYTx3NX2VAVNnYw1GRJ7fL2Anmo0BmhYmkC68QarYdA41vSnBgYBuYNVN7HzGsHNaQHWKOxA+Orn0WDatyHj93bT+1XqaFezlmvqFJvw+bPcZU94krt4bW6Hx70zQzPIyg5m7BD7kEZaMVudxzwyf7Q4HUfJgJhENivruL+yRK5WA44XDXFmcGK4XS8scxbGd6PPEsl0w33BtZoD9SQixzL+1Oa0zmfmv6akXT69RwiLH2SyLTM8DVzjuUnvXqhD1feGxbdvTZvRNMERvvdzDSZczdrljKC+pMH1hQBlZbAyHTGyKSNJjGJaYr00nYIZDIkCw7f2CWQuvFqOlsGp9rJVl0Xm4PWKIyavglgCyFqUNTr/+TbVq1OYWD8kXtDce4SasJReF8nEvQRoIhifSbaJKGA7YnCitSqrTEwrQi1E2MXlTogj+LvJUf0VhvrH/SjQbzIJV8JY3xGNvz18mVrvIlSsA5cVGPiFkStZAZEWRtm5jlZ5dpxWVXz+yM+Nga6Cte81uArxWrFjuVsAEsQ2qs6eICSyqprfmFGmDE0eVOjl0NHZELEDzkYHukN0rN6mz4D2oX8xddPVLREXyr6g1MGvZTGoZ0R+sfBole2sQb+5EZmxfNqaLcoxqWILbmEzcPlZPhNwqdNM+EzbzTVMx+WYC2fLsU//Mlp7wxoArFpIivgjLgeBScLAHel6Aj8xsFHw/B8YvlWhzeZgE7y7jHnqqjYpXvbsgrSgDoaZTqBU7195PP1W0HP49dJg+BZ5F3Sbr9XA4x87Qjl059xYGnYleIDCWb8QQCUySKoT7WcjHDq2T3chiv+qNKyx5d9Uul2P8+3fSzdOJOzhZy93phs6ZTqAeTOAusXGpErjvH2ON93JRQBHe0wnEnuvngl01foPIcqAqT5/PbIB9Dw+uhaAh8k8A/4321kzef4H/OlYBreG9kCek70Lm7HS1KVXWOj84Qou5x5txOqmYKsBov2A+38Lb374alq6kKmQAuROD+I34ynLhpJ5JZhEj95PUmPx+XFbV1FjIvUpD9r9Z/5M+S3jc6JnTJfmyPxDeP5mAH2sOR93nm3K730c+WK/ydO9WCUEjVDHmS0GQvOE+7G0i4Ea1TnweGqzk/GteKpHLwRqhgWWOWSwSiF/9rnDS3oczEPeIEebSYZmurdXkjU4xpFRX74ZkBGTmPVp+f6HxwmeURf4cDoK2Oozpsq6kAG3b/iVDOKnkS5fKRtzB7MXiRVqC55PazSdTy5y6/0tcrEaFe55x","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1210,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":181823,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"3101,uk.hotels.com,Hotels,ULX","requestURL":"https://uk.hotels.com/login?&uurl=e3id%3Dredr%26rurl%3D%2F","userAgent":"'.$this->http->userAgent.'","placement":"LOGIN","placementPage":"3101","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                        'type'    => 'TRUST_WIDGET',
                    ],
                ],
                'csrfToken' => $this->State['csrf'],
                'placement' => 'verifyotpsignup',
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.hotels.com/identity/email/otp/verify', json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (empty($response) || $this->http->Response['code'] == 401) {
            return false;
        }
        if (!property_exists($response, 'failure')
            || $response->failure !== null
            || (property_exists($response, 'status') && $response->status !== true)
        ) {
            $message = $response->failure->message ?? null;

            if (stripos($message, 'OTP verification failed, provided OTP is invalid') !== false) {
                $this->AskQuestion($this->Question, 'Invalid code, please try again', 'Question');

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }
        if ($this->http->FindPreg('/"identifier":\{"flow":"PASSWORD","variant":"PASSWORD_3"\},"csrfData":/') &&
            $this->http->FindPreg('/"placement":"verifyotpsignin"\},"failure":null,"scenario":"SIGNUP"/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg('/"identifier":\{"flow":"PROFILE_DETAIL","variant":"PROFILE_DETAIL_\d+"\}/')
            || $this->http->FindPreg('/"identifier":\{"flow":"ONE_IDENTITY","variant":"ONE_IDENTITY_\d+"\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->loginSuccessful();
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->http->FindSingleNode("//img[@id = 'nucaptcha-media']/@src");

        if (!isset($img)) {
            return false;
        }
        $file = $this->http->DownloadFile($img, "gif");
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function parseQuestion(): bool
    {
        $this->logger->notice(__METHOD__);
        //$response = $this->http->JsonLog();

        $question = $this->http->FindSingleNode("//div[contains(text(),'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.')]");
        if (!$question) {
            return false;
        }
        /*if (empty($response->csrfData->csrfToken)
            || empty($response->identifier->variant)
            || $response->identifier->variant != 'OTP_1'
            || !property_exists($response, 'failure')
            || $response->failure !== null
        ) {
            return false;
        }*/

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrfEmail = $this->http->FindPreg("#.\"loginemail.\":.\"([^\\\"]+).\"#");
        $csrfPassword = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");
        $verifyotpsignin = $this->http->FindPreg("#\,.\"verifyotpsignin.\":.\"([^\\\]+).\",.\"#");
        $this->logger->debug("verifyotpsignin: $verifyotpsignin");

        $headers = [
            "Accept"               => "application/json",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-b3-traceid"         => '07aa7e37-3536-4e9f-9a32-9d5adc614fe3',
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => "undefined",
            "x-user-agent"         => "undefined",
            "x-xss-protection"     => '1; mode=block',
            'Referer'              => 'https://www.hotels.com/verifyotp?uurl=e3id%3Dredr%26rurl%3D%2F&scenario=SIGNIN&path=email',
        ];

        if (!$verifyotpsignin || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }
        $this->State['csrf'] = $verifyotpsignin;
        $this->State['headers'] = $headers;

        //$this->State['csrf'] = $response->csrfData->csrfToken;
        //$question = 'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.';
        $this->logger->debug("question: $question");
        $this->AskQuestion($question, null, 'Question');

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//h1[contains(text(),'Update your account settings')]")
            && $this->http->FindSingleNode("//li[@id='email']//h3[contains(text(),'Email address')]")
            && (
                stristr($this->http->Response['body'], $this->AccountFields['Login'])// TODO: prevent getting alien user data
                || ($this->AccountFields['Login'] == 'jacklau@ymail.com' && stristr($this->http->Response['body'], "iamjacklau@gmail.com"))// TODO: prevent getting alien user data
            )
        ) {
            return true;
        }

        if (
            // example: 4573525
            stristr($this->http->currentUrl(), '/account')
            || $this->http->FindPreg('/"identifier":\{"flow":"ORIGIN","variant":"ORIGIN"\},"csrfData":null,"failure":null/')
            || $this->http->FindPreg("/\"authState.\":.\"AUTHENTICATED/")
        ) {
            return true;
        }

        /* $this->http->RetryCount = 0;
         $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
         $this->http->RetryCount = 2;
         if ($this->http->FindSingleNode("//a[@id='ln-activity']") (
             stristr($this->http->Response['body'], $this->AccountFields['Login'])
         )) {
             return true;
         }*/

        return false;
    }

    /** @return TAccountCheckerExpediaSelenium */
    private function getExpedia()
    {
        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpediaSelenium();
            $this->expedia->AccountFields = $this->AccountFields;
            $this->expedia->http = $this->http;
            $this->expedia->logger = $this->logger;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
            $this->expedia->globalLogger = $this->globalLogger; // fixed notifications
        }

        return $this->expedia;
    }

    private function parseFunCaptcha($key, $retry = true, $url = 'https://www.hotels.com/login')
    {
        $this->logger->notice(__METHOD__);
        //$keys = $this->http->FindPregAll('/pkey=([^&\\\]+)/');
        if (!$key) {
            return false;
        }

//        $key = $keys[1];

        if ($this->attempt == 0) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $url, //'https://www.hotels.com/login', //$this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $url, //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function getRetrieveNotificationBody($arFields)
    {
        $this->logger->notice(__METHOD__);
        $body = [];

        foreach ($arFields as $key => $value) {
            $line = $value;

            if ($key === 'ConfNo') {
                $line = sprintf("ConfNo: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo=%s'>%s</a>", $value, $value);
            }
            $body[] = $line;
        }
        $body = implode('<br/>', $body);

        return $body;
    }

    private function retrieveNotification($arFields)
    {
        $this->logger->notice(__METHOD__);
        $body = $this->getRetrieveNotificationBody($arFields);
        $this->sendNotification('hotels - failed to retrieve itinerary by conf #', 'all', true, $body);
    }

    private function getTotalNumber($totalInfo, $currency)
    {
        $this->logger->notice(__METHOD__);
        $res = null;
        $total = $this->http->FindPreg('/([\d.,]+)/', false, $totalInfo);

        if ($this->http->FindPreg('/CAD$/', false, $totalInfo)) {
            $res = PriceHelper::cost($total, ' ', '.');
        }

        if (!$res) {
            if (in_array($currency, ['BRL', 'EUR', 'DKK', 'NOK', 'IDR', 'COP', 'CAD', 'VND'])) {
                $res = PriceHelper::cost($total, '.', ',');
            } elseif (in_array($currency, ['CZK', 'HUF', 'UAH'])) {
                $res = PriceHelper::cost($total, ' ', ',');
            } else {
                $res = PriceHelper::cost($total);
            }
        }

        if (!$res) {
            if (in_array($currency, ['COP', 'CAD'])) {
                $res = PriceHelper::cost($total);
            }
        }

        $this->logger->debug("totalInfo: {$totalInfo}");
        $this->logger->debug("currency: {$currency}");
        $this->logger->debug("total: {$total}");
        $this->logger->debug("res: {$res}");

        if (!preg_match('/^\d{1,9}([.]\d{1,3})?$/', $res)) {
            $this->sendNotification('hotels - check total regexp 2 // MI');

            return null;
        }

        if ($total && $res === null) {
            $this->sendNotification('hotels - check total');
        }

        return $res;
    }

    private function getCurrency($totalInfo)
    {
        $this->logger->notice(__METHOD__);
        $res = null;

        if ($this->http->FindPreg('/￥/u', false, $totalInfo)) {
            $res = 'JPY';
        } elseif ($this->http->FindPreg('/₩/u', false, $totalInfo)) {
            $res = 'KRW';
        } elseif ($this->http->FindPreg('/^-?RM\d/u', false, $totalInfo)) {
            $res = 'MYR';
        } elseif ($this->http->FindPreg('/^Rs\d/u', false, $totalInfo)) {
            $res = 'INR';
        } elseif ($this->http->FindPreg('/^-?P\d/u', false, $totalInfo)) {
            $res = 'PHP';
        } elseif ($this->http->FindPreg('/₫$/u', false, $totalInfo)) {
            $res = 'VND';
        } elseif ($this->http->FindPreg('/^COP/u', false, $totalInfo)) {
            $res = 'COP';
        } elseif ($this->http->FindPreg('/^AED/u', false, $totalInfo)) {
            $res = 'AED';
        } elseif ($this->http->FindPreg('/^ZAR/u', false, $totalInfo)) {
            $res = 'ZAR';
        } elseif ($this->http->FindPreg('/^NZ\$/u', false, $totalInfo)) {
            $res = 'NZD';
        }

        if (!$res) {
            $res = $this->currency($totalInfo);
        }

        if ($totalInfo && !$res) {
            $this->sendNotification('hotels - check currency');
        }

        return $res;
    }

    private function parseReservation()
    {
        $this->logger->notice(__METHOD__);

        $itineraryNumber = $this->http->FindSingleNode('//div[normalize-space(text()) = "Confirmation number"]/following-sibling::div[1]');

        if (!$itineraryNumber) {
            $itineraryNumber = $this->http->FindSingleNode('//div[normalize-space(text()) = "Hotels.com confirmation number"]/following-sibling::div[1]');
        }

        if (!$itineraryNumber) {
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, we are currently experiencing technical difficulties.')]")) {
                $this->logger->error($message);

                return;
            }

            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, no reservations were found for the information you provided.')]")) {
                $this->logger->error($message);

                return;
            }
        }

        $this->logger->info("Parse Itinerary #{$itineraryNumber}", ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();

        $h->ota()
            ->confirmation($itineraryNumber, "Confirmation number");

        if ($phone = $this->http->FindSingleNode('//div[@id = "header-bar"]//span[contains(@class, "phone")]/text()[1]')) {
            if (strpos($phone, 'Toll Free') !== false) {
                $phone = $this->http->FindPreg("/(.+?)\s*Toll Free/", false, $phone);
            }

            if (strpos($phone, '(domestic)') !== false) {
                $phone = $this->http->FindPreg("/(.+?)\s*\(domestic\)/", false, $phone);
            }
            $h->program()
                ->phone($phone, 'Customer support', true);
        }

        // Currency
        $totalInfo = $this->http->FindSingleNode('(//div[normalize-space(text()) = "Total amount"]/following-sibling::div[1])[1]');

        if (!$totalInfo) {
            $totalInfo = $this->http->FindSingleNode('(//div[contains(@class, "total-amount-item")])[1]/div[1]');
        }

        if (!$totalInfo) {
            $totalInfo = trim($this->http->FindSingleNode('(//div[contains(@class, "all-in-native-price")])[last()]'), '()');
        }

        if (!$totalInfo) {
            $totalInfo = $this->http->FindSingleNode('(//div[contains(@class, "all-in-display-price")])[last()]');
        }
        $totalInfo = str_replace("approx. ", '', $totalInfo);
        $currency = $this->getCurrency($totalInfo);
        // Total
        $total = $this->getTotalNumber($totalInfo, $currency);
        $h->price()
            ->total($total)
            ->currency($currency);

        $travelers = explode(',', $this->http->FindSingleNode('//div[normalize-space(text()) = "Your room"]/following-sibling::div[1]/p[1]', null, false));
        $travelers = array_merge($travelers, $this->http->FindNodes('//div[contains(@class, "detail-title") and (contains(text(), "Room ") or contains(text(), "Part") or contains(text(), "Your unit") or contains(text(), "Your room") or contains(text(), "Unit details") or contains(text(), "Unit ") or contains(./span[1]/text(), "Room ") or contains(./span[1]/text(), "Your room") or contains(./span[1]/text(), "Your unit") or contains(./span[1]/text(), "Unit ") or contains(./span[1]/text(), "Part") or contains(./span[1]/text(), "Family Room") or contains(./span[1]/text(), "Private Vacation Home") or contains(./span[1]/text(), "Room,"))]/following-sibling::div[1]/p[1]'));
        $travelers = array_values(array_unique(array_filter($travelers)));

        if (!$travelers) {
            $travelers = $this->http->FindPreg('/"guestNames":"(.+?)"/');
            $travelers = explode(', ', $travelers);
        }
        $travelers = array_map(function ($item) {
            return beautifulName($item);
        }, $travelers);
        $h->general()
            ->noConfirmation()
            ->cancellation($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]'))
            ->travellers($travelers, true);

        $h->hotel()
            ->name(preg_replace('/\{.+?\}/', '', $this->http->FindSingleNode('//div[contains(@class, "hotel-details")]/h2[1]')))
            ->address($this->http->FindSingleNode('//div[contains(@class, "hotel-details")]/div[contains(@class, "hotel-address")]'));

        $hotelPhone = $this->http->FindSingleNode('//div[contains(@class, "hotel-details")]/span[contains(@class, "hotel-phone")]');

        if ($hotelPhone && !in_array($hotelPhone, [
            '+1',
            '+34',
        ])
        ) {
            $h->hotel()->phone(preg_replace('/\(\w+\)\s*$/', '', $hotelPhone));
        }

        $checkIn = $this->http->FindSingleNode('//div[
            normalize-space(text()) = "Check in" or
            normalize-space(text()) = "Check-in"
        ]/following-sibling::div[1]');
        $checkIn = $this->http->FindPreg('/^(.+?)\s*\(/', false, $checkIn) ?: $checkIn;
        $checkOut = $this->http->FindSingleNode('//div[
            normalize-space(text()) = "Check out" or
            normalize-space(text()) = "Check-out"
        ]/following-sibling::div[1]');
        $checkOut = $this->http->FindPreg('/^(.+?)\s*\(/', false, $checkOut) ?: $checkOut;

        $adults = $this->http->FindNodes('//div[contains(@class, "detail-description")]/p[contains(text(), "adult")]', null, '/(\d+)\s+adult/im');
        $countOfAdults = 0;

        foreach ($adults as $adult) {
            $countOfAdults += intval($adult);
        }
        $kids = $this->http->FindNodes('//div[contains(@class, "detail-description")]/p[contains(text(), "childr")]', null, '/(\d+)\s+childr/im');
        $countOfKids = 0;

        foreach ($kids as $kid) {
            $countOfKids += intval($kid);
        }

        $h->booked()
            ->checkIn2($checkIn)
            ->checkOut2($checkOut)
            ->guests($countOfAdults)
            ->kids($countOfKids)
            ->rooms(intval($this->http->FindSingleNode('//div[contains(text(), "Your stay")]/following-sibling::div[1]', null, true, '/(\d+)\s+room/im')));

        $deadline = $this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]', null, true, "/Free cancellation until([^\(]+)/i");

        if ($deadline) {
            $this->logger->debug($deadline);
            $deadline = str_replace(',', '', $deadline);
            $this->logger->debug($deadline);
            $h->booked()->deadline2($deadline);
        }

        if ($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]', null, false, '/(Non-refundable reservation)/i')) {
            $h->booked()->nonRefundable();
        }

        $rooms = $this->http->XPath->query("//div[contains(@id, 'room-details-')]");
        $this->logger->debug("Total {$rooms->length} rooms were found");
        $roomData = $this->getRoomData();

        if (is_array($roomData) && $rooms->length != count($roomData)) {
            $this->sendNotification('check room json // MI');
        }

        if ($type = $this->http->FindSingleNode('//div[normalize-space(text()) = "Your room"]/following-sibling::div[1]/p[2]')) {
            $r = $h->addRoom();
            // type
            $type = preg_replace("/( <31sqm>)$/", "", $type);
            $r->setType($type);
            // description
            $description = $this->http->FindNodes("//div[contains(@class, 'detail-description')]/ul/li");

            if ($description) {
                $r->setDescription(implode(", ", $description));
            }
        } elseif ($rooms->length) {
            foreach ($rooms as $i => $room) {
                $r = $h->addRoom();
                // type
                if (isset($roomData[$i])) {
                    $data = $roomData[$i];
                    $type = ArrayVal($data, 'description');
                }

                if (!$type) {
                    $type = $this->http->FindSingleNode('.//div[contains(@class, "detail-title") and (contains(text(), "Room ") or contains(text(), "Part") or contains(text(), "Your unit") or contains(text(), "Your room") or contains(text(), "Unit details") or contains(text(), "Unit ") or contains(./span[1]/text(), "Room ") or contains(./span[1]/text(), "Your room") or contains(./span[1]/text(), "Your unit") or contains(./span[1]/text(), "Unit ") or contains(./span[1]/text(), "Part") or contains(./span[1]/text(), "Family Room") or contains(./span[1]/text(), "Private Vacation Home") or contains(./span[1]/text(), "Room,"))]/following-sibling::div[1]/p[2]', $room);
                }
                $type = preg_replace("/( <31sqm>)$/", "", $type);
                $type = preg_replace("/^\[Free Upgrade\]/", "Free Upgrade: ", $type);
                $r->setType($type, true);
                // description
                $description = $this->http->FindNodes(".//div[contains(@class, 'detail-description')]/ul/li", $room);

                if ($description) {
                    $r->setDescription(implode(", ", $description));
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function getRoomData()
    {
        $this->logger->notice(__METHOD__);
        $clientData = $this->http->FindPreg('/var hcomClientData = (\{.+?\});/s');
        $clientData = $this->http->JsonLog($clientData, 1, true, 'BOOKING_DETAILS_HERO');
        $rooms = $this->arrayVal($clientData, ['BOOKING_DETAILS_HERO', 'details', 'rooms']);

        return $rooms;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.hotels.com/login");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$loginButton) {
                $this->logger->error('Failed to find login button');

                if (
                    $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                    || $this->http->FindPreg("/Access to <span jscontent=\"hostName\"[^>]+>www\.hotels\.com<\/span> was denied/")
                ) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);
            $loginButton->click();

            $goToPassword = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'passwordButton']"), 5);
            $this->savePageToLogs($selenium);

            if (!$goToPassword) {
                $this->logger->error('Failed to find "goToPassword" input');
                $cookies = $selenium->driver->manage()->getCookies();
                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                $this->isGoToPassword = false;
                return $this->parseQuestion();
            }

            $goToPassword->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'enterPasswordFormPasswordInput']"), 5);
            $this->savePageToLogs($selenium);

            if (!$passwordInput) {
                $this->logger->error('Failed to find "password" input');

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            sleep(5);
            $passwordButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'enterPasswordFormSubmitButton' and not(@disabled)]"), 2);
            $this->savePageToLogs($selenium);

            if (!$passwordButton) {
                $this->logger->error('Failed to find "password" input');

                return false;
            }

            $passwordButton->click();
            /*
            $selenium->driver->executeScript("document.querySelector('#enterPasswordFormSubmitButton').click();");
            sleep(2);
            $selenium->driver->executeScript("
                try {
                    document.querySelector('#enterPasswordFormSubmitButton').click();
                } catch (e) {}
            ");
            */

            sleep(3);
            $selenium->waitForElement(WebDriverBy::xpath("
                //*[contains(text(), 'Get early access to a more rewarding experience')] 
                | //div[contains(@id, 'gc-custom-header-nav-bar-acct-menu')] 
            "), 15);
             //div[@aria-hidden='false']//iframe

            if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Get early access to a more rewarding experience')]"), 0)) {
                $notNow = $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Not now')]"), 0);
                if ($notNow) {
                    $notNow->click();
                    sleep(10);
                }
            }

            $this->savePageToLogs($selenium);

            $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe"), 0);
            if ($captchaFrame) {
                $this->markProxyAsInvalid();
                $retry = true;
                return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
