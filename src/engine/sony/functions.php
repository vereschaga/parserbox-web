<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerSony extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const CLIENT_ID = '8c52bc6a-4ad1-43fb-bd63-4465cf818937';
    private const ORIGIN_CLIENT_ID = '0469e9c8-8e79-4b1b-bd1f-6bbfdb113a26';
    private const REWARDS_PAGE_URL = 'https://www.rewards.sony.com/PointsActivity?expandOnMobile=true';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->disableOriginHeader();
        // $this->setProxyGoProxies();
        $this->http->setRandomUserAgent();
        //$this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36');
    }

    public function IsLoggedIn()
    {
        return false;
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.rewards.sony.com/UID-Start");

        if (!$this->http->ParseForm("email-verify")) {
            return $this->checkErrors();
        }
        //$this->selenium();
        $captcha = $this->parseReCaptcha($this->http->FindSingleNode("//form[@id = 'email-verify']//div[@class = 'g-recaptcha']/@data-sitekey"));

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('dwfrm_uidloginemail_email', $this->AccountFields['Login']);
        $this->http->SetInputValue('dwfrm_uidloginemail_send', 'Next');
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // https://www.sonyrewards.com/elements/error404/?aspxerrorpath=/en/login
        if ($this->http->Response['code'] == 500 && $this->http->FindPreg('#/elements/error404/#', false, $this->http->currentUrl())) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Recent activity is unavailable. Please check back soon.
        if ($message = $this->http->FindSingleNode("//div[contains(text(),'Recent activity is unavailable. Please check back soon.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/The site is down temporarily but/ims")) {
            throw new CheckException("The site is down temporarily but we're working on it! Please be patient while we fix problems and make improvements.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")) {
            $this->http->GetURL("http://www.sonyrewards.com/");

            if ($message = $this->http->FindPreg("/(The site is down temporarily but we\'re working on it! Please be patient while we fix problems and make improvements\.)/ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // An error condition occurred while reading data from the network. Please retry your request.
        if ($message = $this->http->FindPreg("/(An error condition occurred while reading data from the network.\s*Please retry your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Service Unavailable - Zero size object
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // The requested URL was rejected. Please consult with your administrator.
            || $this->http->FindPreg("/(The requested URL was rejected\.)/ims")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")
            // Failed to establish a secure connection to www.sonyrewardsVIP
            || $this->http->FindPreg("/(Failed to establish a secure connection to www\.sonyrewardsVIP)/ims")
            // The requested URL could not be retrieved
            || ($this->http->FindPreg("/(<h1>ERROR<\/h1>\s*<h2>The requested URL could not be retrieved<\/h2>)/ims") && $this->http->Response['code'] == 503)
            || ($this->http->FindPreg("/(The server is temporarily unable to service your request\.)/ims") && $this->http->Response['code'] == 503)
            || $this->http->currentUrl() == 'https://www.sonyrewards.com/elements/error404/?aspxerrorpath=/en/login'
            // Service Unavailable
            /*|| $this->http->FindSingleNode('//h2[contains(text(), "Service Unavailable")]')*/) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $this->http->setDefaultHeader('Accept', '*/*');
        $this->http->setDefaultHeader('Content-Type', 'application/x-www-form-urlencoded');
//        $this->http->setDefaultHeader('X-Origin-ClientId', self::ORIGIN_CLIENT_ID);
//        $this->http->setDefaultHeader('X-CorrelationId', $this->http->FindPreg('/cid=(.+?)&/', false, $this->http->currentUrl()));
//        $this->http->setDefaultHeader('X-Referer-Info', $this->http->currentUrl());
        $this->http->setDefaultHeader('Origin', 'https://my.account.sony.com');
        /*
        if (
            $this->http->FindSingleNode('//div[contains(text(), "Log in with your Sony account")]')
            || strstr($this->http->currentUrl(), 'https://my.account.sony.com/sonyacct/signin')
        ) {
            $this->captchaReporting($this->recognizer);
            $params = http_build_query([
                'login_hint'    => $this->AccountFields['Login'],
                'client_id'     => $this->http->getDefaultHeader('X-Origin-ClientId'),
                'redirect_uri'  => 'https://www.rewards.sony.com/UID-PostResponse',
                'response_type' => 'code',
                'scope'         => 'user:sony.account.get',
            ]);
            $this->http->GetURL('https://ca.account.sony.com/api/v1/oauth/authorize?' . $params);
        }
        */

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);
        } else {
            $this->logger->error("sensorDataUrl not found");
            $sensorPostUrl = "https://my.account.sony.com/G6JJHwzL0jHO2/wzFVOjREwPe90/k/YLiurJJrrDVYYY/eRoPLk4C/HjFFBz14/OXY";
        }

        $currentUrl = $this->http->currentUrl();

        if (
            stripos($this->http->currentUrl(), 'https://my.account.sony.com/central/signin/?') !== false
            || stripos($this->http->currentUrl(), 'https://my.account.sony.com/sonyacct/signin/?') !== false
        ) {
            if ($sensorPostUrl) {
                $this->sendSensorData($sensorPostUrl);
            }
            /*$responseData = $this->selenium($currentUrl);
            $response = $this->http->JsonLog($responseData);*/

            /*
            $captcha = $this->parseReCaptcha('6Le-UyUUAAAAAIqgW-LsIp5Rn95m_0V0kt_q0Dl5');
            if ($captcha === false) {
                return false;
            }
            */
            $headers = [
                "Accept"               => "*/*",
                "Content-type"         => "application/x-www-form-urlencoded",
                "Referer"              => "https://my.account.sony.com/",
                "X-Psn-Correlation-Id" => "529ddc10-f18c-410b-a0ae-257aea2486e6",
                "X-Psn-Request-Id"     => "cbb4ad4f-07e7-4df3-b9dc-689b459faa7b",
                "X-Psn-Sampled"        => 0,
                "X-Psn-Span-Id"        => "69322ebd2ad24747",
                "X-Psn-Trace-Id"       => "9d7eff0046a047df",
            ];
            $data = [
                'grant_type'       => 'client_credentials',
                'scope'            => 'oauth:passkey_challenge user:account.passkey.get oauth:authenticate_with_ticket',
                'valid_for'        => $this->AccountFields['Login'],
                'client_id'        => '1771bcc2-da45-4aea-9272-c617b31b4097',
                'client_secret'    => 'RDGla45lkgrzhpkU',
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://ca.account.sony.com/api/authz/v3/oauth/token', $data, $headers);

            $data = [
                'bda'                 => 'eyJjdCI6IjhMS3Zmbko4Q2N0YWQrWG50T1d5UkVUQnNaRjF5eGREQlQ0SGl5N2JiSVpXR2hpREVVZGZWMlhHNUlZQ3IzNGE4MmdXZng5Qytab3QzRk4wSnd6d01xYzMyK1ZWdWtTYTg0b2NsQ2FtMGZQaFloMGdxazBhK3FhT1NSSjloVSs4MEFLbFpXV1ltRDB3YTZvcW42dmpOVURxVS9lVzEzbjFTWDEwdFVjSG1tbWRNbExTWllMQldPdnl0OUZNbnJQendNNGx2ZXp6b1RhUFdpVmVaZ05DZGl6a2QxaEIvZGFhQTFWYWRwUWZHckg0Q0NYUjZmRXJrMVkyczZIbFZlTW0rRUdLdHh4U1NQd203WkdrUXZ3QmhBQ2pPWnZXV21DK3hBd1AvQ2VmYTliMjhRaXUxMzBWK2VvNVpUWUhTeTNPQ3Y3TFQvZHd0ZnJWb0RKamducE5nWGhJdFZrRFB1WGh4MzVTK2RmUy91a1NiUkVQbjE1bURPUlJqdjhxaEF6QmkzR1V4eUNDMFowMSs0ZFdvajhZdnF6bWpXM2hpYTczOXl5bFQyZVhCODlxM2FPd20zdnM5R0hENVVSKzlDY0ZMeFFyaVlJYTRJRnk0Y2U4aFhSOUs1ZE1Zem1kK3RNN2Y0dXJ1dldOV29PNmY3WEN3LzV5QWloZytiSXBLNXFzS2NQWVlGTGlvWkdJaUtoR0ZWTFJqSW9yb1Nwd2dsQXUyYkxuWURVc3ZJTTF3a1dyT2x4bkx1YjcwUlphYUxleENNQ1QvSTg1aWZaRWRVdFRib1B0T1AwN1lFaHFLWDg3bTJGT3lqRUJwZ2hkU2tDekx4SXV6dkMvQ1k2UzFDTXpPVnM2RUZzYWNFVEQ5S2FRcHl5NnpCaDFsNm85Vk5ucDFwY1VRTDMzRUdtdEhIby9LdWxZVlFwWVlmZUhBekcxT2NuZWFOMzRHaUM2NnpRRzIxbG9LaWd1dlJCSlpiVEFBaVhqRFNWb1l0ekhMdTZ3MWpMVURqMjFsNlZBVEF4ZE9rWmRWQTJhbGIwK0p3YkFleXc3c1Z1Ty9wYS9lQUNBaFA3aTdtMWY1REpiaDBlT3N4S2JaNTVTQXpEMjZaOVVWNlQ0RnJQT1dhSmZ0VU5oYjRrVElERU9XOTdoL1o4Tm43dXRkclhmRTR1MW9lYUJXRXlWSmtzZEhyWVcvZmk3dnIvNWlLZUtqVGdiSFBUQlloaFd6Njc0R05pNHFZaFJLeDdlM0Y1LzBjckRtZk1qRktVdmR1T3g1bUR4Wm02NG9IZXQrY0lRRllsby90U0ZLTnVHZWQydWg4NW9oUis5akJDa3R6OENXeEx1M3g4d2p4bkx2c2VSWWtQSXZNcmtKd0Rlam9Eek5zakxySlFFOGlCTlRhek1IanpRSENlNnFxSDBReThUcjBTNHJIOXZKWkxQaVRkc0pDSkZ6QW5rN3JaMyt3NkdDSjhBdWpQSXlnQXFWMDM1Q1hNcEFLUkVmUmNlOVNFeXR2TVJVbTRUdXBlVm1qR2tPZ0ZUNUpFby8vR3dQd3JHZStXVkNzSWpFOTdjYkd3YUFBbzQrM2hXQm9ub1haMXdwQlFWUFE1bkxRUDlKUmFCM2ppdmVXK3QwZ3NhQXM3K3R0YkFRNzJLZUM1YzI1OHlyMEJYTUhUL0o1bTVSTFJ1V1U0YmVrcm42WVIrWTlXbVY2ME8vRVBKYnpDbUJ1MzJmUlJDSHFCUkxlWEpNTzlDRUY2MFJKQUtaRmdqVDZYQ3RNM1dOTXg1OXJFZGluT2JPN2liMlVtV3p6ZTNvZ1ZCSHU3NHRJZ3djM3ZYSGtQT25DZWYydE5qazVLV1hWTmx0bzVKY2h0UzVrQ1I1K3FmMGRtQks2d1VSUjZIYTJZVWVsRmErck5GY2Z5K3hZWCtGaFJLN0lDa0t4cGw0ZkJjMXpkOE5Mdiswa2xvYXJtRVNBRVgyZDEvOTAybi9PV1RxaTZyeEpVRWJYUDRDZkMzb0ZpekQrODZydWRvRXpLYWUzVzRaUUx6emZMV01FQVl4THJYNjBzbkZOMmJmWVlsdG81QkIyY2t5M0lxWndUSWdJdk1lSmZKbVU3TmtQOVN5VnlUMU5oUFhQcG0xUDgwOHcwV1VZYmx0MENSYmVhc2tubzQvOG9abkFBR0lmZFJEM3pKczQrcEdGejAwUjdJTlZMYXQ2VVpRYTBsYlNrZllibU5HTElLbW5Eb1JtWW5jU1lpSzBqSU9oVk91VUZWZHlRbHlOZ1YyWjlrSjR5cXF4eUpTaVJSem8rbFRnOFpuWmVqOUF2cG8weGRXWnRBRkhpQnkzYnNNc3hjeDA3WXIzM1Q4VVpFcTBYRGZhWU9va003bW54QlFMUVRwVlpBSW9GeGJBWDJiSzFXMUpIdjkyZGQzaURoNXlRZVMzTVRRdlpaNFpoWkRqbjdDRkZEZUlwMklYaC8yYUJsVUlVYm53ZFFEOSt2NHcxaHk3UDMwQVNacXlVVVI1a3EzZTV3RHI1dUNGTjJaVHAwdE8ydUxtVS80OWRVdFZkcE14YmhHbXN6ZFdBUkg4dURyMnlFOHZqUllKeEJOaVVzQ0E0KzhVNE1IOWN1WUlWbkVqbzRjVFpJMGp5M0ZKK1o4TFBRZm81SEswTEVJRVB3ZWlLUnRnbkVPNC9DMVpPUEhJa2JtRHk0TWFDSnBBdy9FbHpNYjNWa0gxS2lLb0lZWmdpU0hWMlZoYUFQakdsdVBhK1lZMmp3WEM0TkFZaFBXOVRIWnlzeEVpUEFMRDZHcVpja0RKK0laMkpldzFSa2J2SFhmdE5HSkNDYUtPUGpFOWZRZkZQZnIvQ1BzUEp1YmVtWDVULzZqYmNlek1LYlYzY2drYkNhYmY5WnRNR01NbTIyZEs4Ujl6aTF4LytrMFZYKy8yOVJwUXQrby9EQzNFS0ZtL3BvSzFTejhtelNUNXY2Mm5KcnU2dk1McWNUUzliWXhTa3RHWit3Ym5FS0p1aWJaTFErU1BmUkRFdmlFdVRWT0Z4WDkxQlc4NzhQcjdOa054dFltci9TclV2TGFpSEJQdnZrQ1RLVjBoczRqcjI5V3RKeTRwb0ZFVTFHa0M2cEUvdDJQWWJUS2hoS2ZVUVM5UnBGR2JjcTBVOWl5empkUjNZRzhuclcvQVB4NWZWWGZkVzdkMmhkZFpBeWh0bUlQL3BRMElQeDU4RWw2L2pZamZGOXhjcGQzN2Y3b0szY0RtOWV2cVh3VTB0SXJMa1pFUlE4MW0rQ3VacnRacThBM2pVS3RKT29ac1dKWnlZdVU1eE92YkJnZlFWelJXbW1MUExvVGZaMTRsYWExZWl0TTRNalRNUHB0UXNtOHo2UEhZeG9ReTVEOFJocnhXNHRVNlVLNHFmcEp6cXd3dWgrcFlKdGsvL2xjRzh4RUdleGFiVXl2cGZNREo3b3RpODVCZUIvZDFpek5oU3Z5Y09FTGUzWUU5NU1pS09xWkQ1c2pIMXJaUFF0VUVNUldsUStQZ1FvN3pjRjd2RFU5ZGY2Z0pYM0RyUFlveXIxZ0lzSjZjVHVNNlhoOVR6R1p6YlBJcWxlMXk0NnpLSVVjM25IQ0RGc2dJakNPMXl4bS9EbUwwb3NIYTQ3SmhwZCtqS1dpUzhkdisyUFJ6MXVZV1g3dGxnaGkzenZUQmR4WXNLblpURlJ4ZHdNZlNrR1BiVEgyRm44dGNlQnRtamRiR2dqN1JCdmhCb29iZy9BL0dsbU0vUTBHNVdsN0tpTk9OL2tYanRmRmhtWExERk1xcjBSUWE3cnBkNXBxNUtCc0Z0Zy9KUTFPV2MzOGNYYWdkRnZtU01rclN4MWxRRU9Gc0lINnZjMzlSekhyL1RjbUZRVCtGSVE4WW9xclNNL2hxUU1UV3kyUjUwUGl1WHJPVzNaYVk3TnhYbkpwNkpMNyttbjQ5RCtxOU9hWnBHbm5kdENnZG9xNVF4NWNQSWpQTTlPQmJ0MmFKOEY4bFVVbVFxRWlzelAraEFPZmtBOTdROFZWd21VN1hyN0dTRkdHSGRtTUJqckVWS0Zwb0Z4UkNmbGhSWHI5WjdQSVRvTjlnS0lESWFsclcvRm1jMEFId1RqbUV3eFZlR0VlYndiSkdEOTg0VGpWQ3NBdlhtT3B1Q3pXSXE0Um1JTHNGbEJPTVZKdFJqdUtVRWJ4VG5KUDZ2eTVRblNqQktyOTdSSWxFTm14SFNTdnhjbGNueW80amtFVFhYY3RzRmtLUlBoak5UR0hxdU81RzhPOFRmWEZ0cHpmaTdLZkphbW8vQUtYa2kwRUxWcFQ0T1Rka0RLMjNTOTUwcWdUMis0Z2RDdTM2ZktvT3hZTzlBZmhULzVqOXNGdmhiMFd3OC9xMTJLbjFmcFl3VEJGb1ZrUDhzSEFZd0FZYU8wYkcvTDFxWisrU1pkWFJBTXFaQjB2Z2F1SmtSaVY0TjU0SGN1a1YrVzNFcHVTSVVBUUNnWTRQZnF6OFhxOWt3dDRkelVSNU9PVHpKRXYyWEt0MGpUTEVrenhFSU9jbW00R2tmaTRjVXFQQXdyVFE4bjluV3E4Z3pWQnpYOTk0dkpJdVNFQVhvbUNxaXZsLzdCZW9obUhYblRPTzdRRmlCbW1Wa0xPN1FIZWhEdlhoSE9TMzVDVUhmYUM1Q2ZQZDgwdjY0bFhyK202Tm1YTUNhYldneHN5RTYySWp3SjZXUk9zUjNqVk1WYS9mZGlZQXJSU1IyOTZVZUhNT2dYc24yNnhyR0VmVlUrN3JWSmhzYitPdTNuL0xHNEtVVlBSYzQybnRyT2JUNFdzSXQ1VkEzZ0ZXNlpkNnAzTVlIaTRVMmZLeXhXOHdwRDZEZ3N6LzVZbG1VZ3Q3M0lmRk8ydU9pVC9xTlBINkxBNCtOODZveEZCYkcrdFFYREJrQ0JMazM3MVE1TDVlMG5jYWt0M0dFcXREbExrdW8wSU9TR1hNNENsR1NHbGxDWGpRMGVJaDl0elQ5eUZKRy9Ra21iTzJnMEVEQmdaYzBMT1h6N0tqaGxyZm5lQS81Ym01ZTRaREZWK2d4TmpHcjlBSUNaM0didnNsRUxqR2ltejE5OWgyRUFsRWpRdFpIZ3lHY21hMGloblNFajloZTJ0NmtmUVFWZ1RNR1VBNkZhb2ZvRk9IUUVNaG5TeWU3TTVHQVNYVFd2dGZURC9iRGNHRUhnZGk1WVB0SXNKU1lyeUprRjErWHV4aFhsdHBXWUtOQUpEeW8relhyM1lPVThHYmJiTW1jeDREQU91K29SY2RVbkZlNjZqMkQ0SVMxc3dwZWZBREZ6dElyUmVIalZpaVBxZmcycGlWK0RpOE9FVjRIS2NrWGZ1dlBGYzlFdnRFd3RLTHZMaGM5M2ZNUTZwY05VTjB4NHRMZlhkVjZHdGpqVFRKRS9IbnpZMVlGK040cTdlR0Q0VVh6aHdQTmRKSFN6ZThxL1FjRzJmVmM5ajBVZTk1Qmt0M3ZZdTc3MlhRN2N6RVFtbTQxSXBzdmI5aHlqRXduZjdpK1MwYzRETWJtTS9LTVM1c1JTaElCckR0L2FIK3p6Zm1GMzlQbU1iMDhYcnJvUENlYzJGUDJrOG5jbFJSbXZvdGRQa2pNRVBNdExiaHJZVjFsdW9kdUYra1g0RlpNN29JMjM0UmFXQ3JzOGw2RmVGUVZLNjFkUmFNcG16VzFaOWl4aGpDUlVqRUx5NVM0WWNoSGlkZ21SamgrNHVQMUsvNGgrelhvMFNONEgyRDgxd0RQUjN4Z1k1R3pkSk9jQTkwS0Zzd3haQll3S1cyc3h4Vm9tOVRyY2ZZTUQwdENyRElwRTlkZVFUUnZGVTRsQXE0aHRISFFJVDYzR3FOWUMvQkV4S3laL0xRK1FSak5ocURMdHpMamxyMHZXTDJIUUdsRjdZaTEyd0ZldmQrYU1RSmEwa2RiZEVaOWNGSVZVdkdXSHZHeUhqZ0loSVM4WFVqcm9BZzQyQlMrbnp2cC9pZE9IdG9sRGdRKzBjV2pDVFAyQWRJYjZxalZHME1zblRicTlseFE4WTRVNTUrM0pJeUlEcmFxbnByWndJSG54b0orRHJwaERNdWsyS093S0hmSytpcWNQMFlxcVRnbjZWbFVacitURDM1c05QOURjRTBvbS9lZUMrN01VVXUvUHk0dVdIa1JZZ2dYRldFY1Zham5xc2UrdFlDWmVxSXQwYTI4MUZvTjNNeUhSbTFKelJVelJDd0NhSGJiSDA2TWdKZ3d6eW9zV2ozRjVmbXN5d3oxNGQxUitreDhFVXJWc2owMzBWaytjaFdzRDgyMytYbmJPT3h2eWhLaGovclBLcUVDUjhOT0Z5RG1JQVprNW0vVTFkdFoyUzVGY1RpNHNvTWRwK0RBdGR3WGE0eDZpZCttaWk0WFNkY0lKOWI3VWRUbFBWR0UwTzg3WUMxbjFrK2pnN1IyR1A2U3BvY1lwVndVR2kyb2pUc3pUYUc4YStLYWUxTkdmWktsck56U2FTQ3VybWpabE5FUlE1d0kyRHJxYVcwMFF4Z282YzVUWXVGWS9pUjMzdGhDRU41bkUwL2VubC9VL2wvbHRxQ0lVanhqSllvc2tzdFFhcnRNN2Jka0x1c0daa0ZBRi80dFFweWNVZ0dWZnk5ZmhzM0ZsZGJEdWJiS1NxMHNTbVVaa21Zd2RIOEdYRnpNTHJuWUZYVVFERVpETHBiUVFmNVZ3bUpNeWRsZ29hYkRORGtWTnF3YTd0K1plclc1VTZ5Ny9ZZCtLZXlwandMdVBtaHg2RmJyQnFicnhlWDlGUTZEZGhqTVAyU3JqcWxnYkpLZlRFZUVKM21VOXNuTGFPaEhQL3E0dDZGYmhsOVBORUszSS9abHhaaGlTVmxuMkdQOFphZVIzblZVOVhIakNrZTdPQWo2WlFBbHQyd25EdExwLzdwUkxxLzNZY1M5SFNVS25KTWVBYmRNWVdtdC9zVVVCS2xURUFHT3pGdEpkUjd5emR3dEpGT2FjYWJBZjE1WUxhMnByOGN2K0kycUtkSXZHOVhLSnM1Q0JyaDh4Zy9vYzJEOUlMKzRwM1hLTERycDZnWlYrQkdORDJ6V0x2U1FBZUphaDgwYW81WVgwOFVwV0RhdWQ2ZjlYR3ZsbEdPbjVwOGJYRkt1bmVMbDdVQVdDYkh2K1k3NWZDM3JRTHdkN1NPS3B6QUJjWnEyNEZPTUo0cTdKWEpEU1RhY1FhZVFEU1VDSTdWM01Eb1l0K201Vk9lNVFEVkFPZ1U4U29YakVOTmtadFVRZHJSYTdLYTE5ZVVHOFkybHZtclZJV2FUbDZ5YlIzV2JYZkNSQy9vcFIvWU1wcDNOT1hVcGcrVFcwSkxJdy9GRVdiTDlsRlNQejVSUEdmNVI0TWdRUXd3ZjhzcVByK1d3SkVRMUxMSlhjRkp0akZ2akJQZnJhZHFQRnZLS0V5U2JuUDBzeE1PQ3dXNzY1blMzZVJOU3NudzB4dStWckc2OHlJcGdQRklEQzVOZ0NqNThFSVRrZUJKakJBMmNWa0VMbnkzeTdDVjlocG80NUZKSVNsRkQ3all0MW81Q2o0bEhHNmZ3TEhOV1JVWlBRTmpaaGJ0L08wN3gvM3JlWnkvYjBpYWlsblFRR2VhZldCWnVNM1MyRVMvWGVta216QjU0anFQUDV2YWlYU3NlNEd0bjFEK3haYjY2WFpNUUNDRlhiQnF1M20yNXpFSUZyaG5ubjh4cGN6bzhCaDF3VkNVTHVLaHRrWnV0VXpiSGt5Ry8xOWJZYVFVMTdUUzU3eHlia29hZDZtR0tkTDJVY1pDbzhxMUQ0bTBjSUlzT1hSOTNsUW9KRVJRbFdCSll6MTdMUDlTazlzQUlxZXFvelRoUG1PYVRJTStpbUxsVkJBUFBxWGlOR25rNkJENS9WQ0hyUENaQ2xkUk1YSDZLL3hUR01jQjN5UkY0b3FDOElodEIwRVNHeVM1QW1FaDNUVktiNk90a3lGMkNucHVBT1hsTERKNVlUdVYxRWFuNkUxOEVZWm9EcnFTYW5sZU5aUndsWndDUmFpMk9EODkzOThLTURNbWpaaWZId09wWU54U1o1VVlhNWRxa2hzek5qdUdkYjlhYWhnZnptaEUvWG9WK204MzZDVk5NaERuVUg5YjFldm43WDllUkc2YW0xdGNDOGRtMWhoQnhtMzY0ZFJESElBMzdqaHIrSlM1YUM0eGhDOFQya3VWRlVJNXBhRHRXYjdtWHVnUnNlY0tpR1ZHY2lqSFZ2NHNNSTdWTTUvRTFwbGFpOEhyK1AwRGpyN3JuTlpkQ2xRYTZyQS9FNFdsMEdGc1I2RG5MQlZPbHdoTC9LbjdWL25JUlZWbDFQT2dkbjg5clB3Q1ZRa2MwTlZHd3FEWUd5SXZidHovMWQ1OWRRemk5RmtaMktWOG9qeW83OHVYK0J5RDYrcU13UFY1anNTcXZZcjhTa0tiSHFKeHlrVHVITmx6SHI5WEUwOGlKTzg3MHVxaVdwNlhVT0Y5YmJJaU9lU21sNnhDU1p2ZmJCTEZPUWJkcEkvVjIxWEpHN2cxSlZzdVpRNDcvTjlGUm1vTGJhZGtZaTh4bmxqazVCdEdVSlJBcDlMWWo2WHJjaGlIdEZodStUK1NzMW8wK1pHNVBITlBJTUU3dTRqUXg3RVU4Mjk0OXZpNVlvR3YrcjRCTmM4SG9obDl0c3R6Z0Y0bklSVDRhQlA1elRNTExZY2lDOFpnTmJPbEltMlRoNC9vRTBaQUZPc252cmhZdDlIN1B0WnhTWEtDdmgrK3VJUkUxMDd0VllDbUpUaUk2VzRaaTFCdUVZQmVVWVVzV0l0TFNEbVVMZEwxN0NaT0JoOW5XYkRrZ2ZqcHp3eGVRQWF1Vi9maGRSSUZKcXprRVUzbjhPZFNYYmdQZVlXQ2dFOVRka29lQWIrRkpySmd4RmFjN1BpVllXV21NZHVIa3hSQ3k2SjErYXVkRG0yQzhCY2ZQNXpFWEtDcW1UWmh1M3ZFVmtCUFZrS25pYUp3Qjl3VXIzcmNPalBkUStEZjFYblRIdmNlaDh1eHBaM2t5SlovVW5KS1d2NyttNzJCRitWN0NHQlc1SUpGZ2thdUU2R05DZm1IT2MvakpPYzZ6YWluMC9wb0tTa0J6UkFOeTdsYU5zL29lU1U0dG8yTDB6enpFQ3RvSUY5bjJNVEhOTDlnK29Mdk1VcVkrYmUwWjBvZ0N3V0NFTkM0eVoyaDdNYkM2UkpkNytGakNvVWJqcE5uR3dHMjZyd2JzQ3hNVGFBN2lWVXVUQlNaR3NuQjZVN3pLVk5oYy9vOHR5dllrZUJOdTBPSHFhdW45QVBEbS9IU3dhRVRBRzQ4MVhrVW5CSTgrK2tIUmc0dEJkdEd4RllkZERERFVvbXVzeE9ZU3cwYUJCMWNGNDJ1TnV5VHZTcTRpSGlpQ2hUd2hyZGcxV2NqM0ZtSGNRR0phS01vN2ZjbDNseDhPL1AraU91cy9sM2ZRbVl3c2RDMDBjengrSE45YXpKRkxkanpSOHZYY2kwSnlIa3dramdtdERsWWZzUU5JQ0x5SEJIQ0xnWlppcmtjMFZjeGNyUnVSL09kc2xWWVAwd2ROUGRkMkViZTgwa0Jkc1ZVb3ZQYWxvUUhYa2FCZjBUcXBqeEtzUXF5UEJ4OXdRNVd6VXVGVDN3blI1ZnpZV3ZDQllwOUplV0V6WjNIdit5OVZ4dkxLaDhXeW5oUkZJN1l3MzdDLytuTUtEa3pCODFVNTdrSnUzSHVCQTQ4eEJtckJvNGVhS0dONWI5RDNkT1JoQmVaNC9aSy9lRjcxT0dJUWFsa3huMjZyWGdUTlVPT1Y2UVFYYXVrQTYwNk4zVUZGV09TRlVyV0I4bzZJQXNiTWx6TWt0dWRRQjhXNUp5djRmTnEzWDhQYVI1SENsL2JiZW82WXdkTUVTUklkbmN0ZFpaWnlEOFh4d0V6UXhtMkZmRjBSREV2U0lTSzNRS2xFYSsyR3pDLzhhRTlqSko4UXFwTlVKTWlub09Ua2ZxZk9hNkJUemNQRUtqa2NFcDdLa2k2SGlDai9tRG54bDg4SjhCZTFXcmVGdDVaR1IzRmk3NDVYcjdoRkRvSUVSMlAwNjRrTUhhWEFKUnNveVJyRkRvOWRDYWJobTkyaUVrUnMzMzdiMHI5R2lnL0EyNnRxaTd6d1RkZVVIOWU4eFRDdmxTR2tXMzdxQVpjK3dvaStNSEtJdm5XWFJJOVp1Wkt2aGZPaEs4Rk9YUjh3bWFMTTRRYmNoMmdYc05VcURTM2xTRFZZUk9GWHVaUGU3TGhKeVNBbDM1OW55bmROcUtMYlp1L3JnVTJrTHh5d1FpMnRnNXR0TkZ5VUd3TXZMcGxPa1NpdUtCNVZGeGFINWdEd0tkRElsYzdRb2U3R2dyWWc5MGtwTmExNUk4eDFFUjVnZFZmTmQ4ZEJCczNVdjExWTlHeUtlQkVSTUdyT1hYL2NDTTNTT3pFYjFJYmMwVk91T3Z1alc0TGMyV0NEbWhveDNQUVZKQVNCUTZNcElTUWxUSG82YVQ4djN4R01OYWhZUlpGWEYvU2hoQVR5anNKeUNsT3IxVE1jZFEzcU5DdXZ6dzVFR0F1aUZtbnJuOTNNTlV4MlE4azBtYUF3T3ovYkdEMytHZzF3eUU1cDRKRW80bGNQOUhjczFEdGFsemFDVnN1RjlqSFlVS3ZPUndiRlJBdmtLWHR1aExYUkhvUzEvTnVWNnNhKzhXYWc3WDE4ZThNcENSTmRoVS9FMnBRMEQxWW9GbENvNDBoODdyREVNbjhwbEFsb0llU00zc0Q4NlU3Y3EvQ0hib3IxOWlQUlkwSHVUYVhlV2ZseDcwNFZSL2lOTEhoc3llUXplODRIZTlxeWJqZHlQVXFnSzVIczg1K1V3c3RxVUtrd21GRkU3OG9BR3RHK3hpeHMrazk3T0pXaXBUUi9HeDljUmd0QjFIYzF5TENrbGNnVDdQd1dUMjdJdHNQOGZwdDRONVkwNHVFMEs4Qnlsc3dRT2NMczVUWlhyMld3d3ZqU21VWThqYU16Zmc2ZW9hTGJMdFM5d1A2enczb0p2cnVTUk9jY3NGUHh1MWtweXlUZjhBdlNleG1SejcrckNhSk5qVzRMRmJvNE9mOEljaEFabVhQZE9Hc015Q2J4ek1UOXA0RHA5T1U1bEhyZmFVT0pFSUZ0WjhOZXJOY0d5MTlCdW91K0hYem1VaXgyRVIrUlA0NUN4MmNQTnNLa3Y0a2REM3hqYXRPVmgzQkFSam9LTlp2bWNzSGJ5UkVmaDZJSDRZTVF0R1JENW52YjlKL1V3U2taLzJNT2M1a2hsSWozNTc5VFpjN0xBelNaMXZSemk3Q1o4bDkwaFJOaml1dUg4SzFWOTBPSGxGdjF0OFZacVQ4a1N3eU91Z0tLNUlkb0tLVFlnanBmMDU5b0o4bDA3K1FUSVpKNWgvWUQvSW85eGZLOGpVeWpIU21jVVI0K2RwVUNvaXJhTTFCcTNjWVZnbnBEcmY4Vzd6N0lPaVZiNmlTcFBNU1dtQTVDUHlWWmJRYUFJdEpCTTJ5MlVJdkpPKzRlVTdsTFFibkx1RTBOZi8rcEhMUUNzZFJFczFxd2JvK2dmb0EvVlBONmZEYjh6Skt1WVN5cHlLUGw0ZUxvd2IzWWUrb3FlejVuaVJzVEtmSHNoM00yK3Rwc3pmcWN4WWFZRGE4T1pzeGxDNTJWY3psamFKS1Q0b3czUTFBbzA5NHNJbkdJcnFtK1lCdnNjUnNoc1FvVWlCbTd4NmNiWVVuS3BKM08ycGRWWkEvVHl5SU42QnNCYlRxUW5nWjVRT0lHOHdGMFVidWZ0SWE1aFRIWjdQQjJpS0U5bWh5VDZVVUVERGRXME9lNDlRTUtUWUJDSXFhay82dytmV1g5QVdYTUE0VWxpSDhRZFQwRmFBYkxhcmtsR29WeWFpa0NZUWtQeXQ0R0dwRWZMcVBRZHdEZmlCajhoaHlZTzNnVHMrelA5NXl4cHUvQkZMTUNkRVBsM3NURTJzUkFLWWxMRlpDcDE1VEpweHAvbnFCeTZHWStqaGx1OUMxTlY5bzF6YmJuaU1oQXZUVTVVUmJ6NXpVcGwzTkJJYWE1RnRNM0pRTWJMRXFZS1E0UGhWR3lIeHdHZ3NSQjhUV3lEWEtlWGVSMzRadjdXRUk1Y1lRL0xyVURnemxuSWdZYUZuRnY4UFZURGt2VWFhRGFQaDU1dVB4cm03RW9Vd3gwMloyR1JSVGh3aFFYeTdHN29pK0xjTmdIclRKM0QzSTM2L0tFckh5cnhFOVlaL1VQcnpWVXN1NDlBRGFBQWhqcmVUVDl6REY1L0tOOU10M0NsSm5KNGhaMkY0am5HN09iTVVKOUhveW5Pd25ZRCsrRVQ3RU9ESlYwbUJQNDN5WWlRRm9kUFAzaG9NeDBaWmNZMkwwRHFSVHR2eDBOSXpSZHI3WXpCY1ZiSCtIWFl2cXhjUGV1ZVNZZHR3VDdBN1Y0aHZ4aU9UR1NsTlU1SmU1MnZrdWpObklpWExUa1lEQU1VQmVJZTl4eXVmYlgwM3dhWUtZZ0VteFRTT3B6VzBPekFBdDJodkxkNUZob0wyU21pOEl4N1JWMnY2RUVwSXZRZzZ1WDlaam85K0MwSVVTdFBHMHpBSEFRU1o4dTNDVnkwV1Axa012bGJTb1J6ZjJleVZvWGFuTkF2SHlLMnJQSDBqTkJBYW1Nci9WY3pRRlg2ZHF4RWhXMlh5N09UNXFubXpVVjZ1QVlzdkVnS0ExR0drN2tFbThVbUZtdTRQQzZLaTZreGFYbVM0TTRKWXNZZlhocmhMT2x0Yzl0RndVVVI0UDBqTndrL1lkSGpWM3ZOMGVrOENGeVA1bWFKaEhrMDNralRmeFJVSnBYcXZiWVJpYjRmTmp6NXQrTDI3R2YxdjVwN1VwZzdvbkEwMWRVVmRaV3RFS2dJeVZ6dFlIeDBqdkVsMDN0cHZqa0w1QmlRT1JJeForelZDSU9ZQW84SWZPTzFDMEFSK0Erc0ZEbGlxTDV1VFVpTk1kZHFQalZ4RjFIeU5NU2pmVy9EMVMydmMwTUpoMUJZVXVxdlNneFkvR2VoUFBhVkI2QmRwSFZMWFIvbVA1RE5CNDhnZmtOeDlFaSs1dXlBMEpTNWwvc2FJSUl6R1BGeHB4eDg1SUo2aHpZWkJtd3A2Zlc0eVNWbEdmQ0JPK0pnbm91ZDg1YWF1ZXJpRkpaQmtPeGFGaTJ2dzJRa2kzL21lSTFnZzc3NGZMUzR4VDlhNjlScHptc0tBTzdnVUxqckJMVENtMXNRNHJjYkNJSzlUaE1qb0J6UmdvRjZXWWtBb2pnU3NjSjlxaTR3UjdWVGx6anZUaTJKR2tId1pXODFJSHhXMjc2TmExbStjang0U2sxdytNalczWER1dzk5UzgrZFVlVzU4Qko0RTR2NWFMSUVtZmRsbCtDVVJPZHBvVjZ3bzQ0aXZSTGZCcDNacEtNWkpNWEFDS29LWDVoR29NTXN5Q3greHIxK1NQdWsxV2ViRG5OZnkrOFdJSGw4QzluUzlDWUU2SHN3QVdkVjR1UFpqZGZWSGJjcHJIcWNWbmhoQWs3WkpaMklDSkJZNmErbThYK3c4MHRjMWQ4cVpTTjJpSEY0enFRbHJvdEhiSWlPYTMwRSt3TDdKK1BuWUMwbndTRG5DS25ya0lIODI3TE8wNGUva01tSGE4OTk4ZkkwQXl3dmttNFZDYTQ0cENEWDhBZzd6NEhsNHVZZXpEYUxLYkFTM3hQZkNFeGlDM2dFMHJxcTN4Q0l5MTlUcWR5UWhXYzczZzdsN0h2a3hsVnJJQnl1RGpqUzJGT2RObnVEdUxuNVhHUC83V1ZnTzV1TEhOSk01NkY1MW1iOWQydlAxZ0k2TjU1NW9McjFQNEpTdkFDcU1Ha2h4L2RxdjN0R1FNRnFyUU9KZTc1OVllYyt4RmZncmJseldITGdvU0FDSnRSdDlCK1QwM1JoYUcrWnVadERNOVdESmxTLzE1b0liV1JBY1FjblMwb1Q0MkxITkhYSTJsUlJkWkNTNFFaZHdKYXpsdjBGOUl5aWk4em8zWTdOL2prUjJ0M2NHbGZhU2ZnekNoMUNrQm9Zd2JEL1J3THdzL1JBRGY3cG9FMHA0OWZjcSs5NEZIckIxaytBZWc2Vy9yVzl0b1ZKTWJsWUlJckxRZGR0ZEYrQzQzQk9TNTRpOHozZVc0THRxWEx5eG1jNmlrb1dEVlJhWDRTbEp4SEw1K3d2Znp2THg4SkVyRjcwRjk2SEJBQXE0a3BtTHNaYzRqL1pWVG5NRUtLMXFtQndYbGFaRVZHR2JTQkpNSXcvclZOeUNpRzB1YVJuK0ZsYXo0OUJCRks3REp1Rkx2Z2NSSjlWTnFnd1lZdjZUelhpZE15Tk9ZRzVQME5tM3BYM1o1dm9oTmhBVkFnbllPbXVaODVzeTc0K0dPMWpGeGVWZTJmZWQvMUl1Vjd6cTJjb3djVEMreTFMN2xLNVpHM0VzMlc5ZG9McUdPaVU5Y01weC85UFdsb01wV1JKYmY2ZkI2TmhSamNRZm8zZjVkWDBSeWs1eHRwZnp3RGtyeXI3ZTN6RStZZGRndlJxRDZtRFQ0WkpWSGg4dGNGK1Rnb3JESXRreHdRcm5YYm9RQk1tU2tNMFFJT3FURkhCYk14Tk9iR3liLzdjWC9BOERWSUVhb0wwVTE2bmJZYnE0azk1dVBvMmlpQkszYktTcDJuRlBTRE8ydVNyanNCYzBxMFlTUkRIckEremhFcitPL0hlRzBwSjdmQWNISmVvQ2l6S2JLbXpuWnB1TTRKTTExVTVjUTExV2JKUHRzSlhha2NLaTVWMFNFajNnVXgxbjY5ejhmWThmT2JTWXREMWIreUcrZUVJc2lNRmZ5UUdsWVVEblpYeWI5RkgwT3Nmd1lJVGk4b2JLclVkeDNYQk9Nc1NjL05TcUs4bE0wTEpKeGZwK2hINVJrOG13QWxQM0MzR0RFRk82bDVnQzBiS2R0V2RBdEFlVm1IekNHSnhKY2NZeGtrRDhnbjU1R3Y1QlYrTnkzQUZxWFdoWkl3K3JTM0l1bGZUVVFydVg5MnM0SjdVZzBPdGJ2VjhKVTBjUDNoSVNwM3Y5OUp2RjVpYmRqNGppMklnWmxScUdGSVVZWG5qWEtzNW82M3pjYTF2QXFQb2k3aUhWZVhXcCtoaFNyd0RoM1RFcVhvQlo4NXh4WDcvSnlNRGYzNXhXaTREcm1LVVphUkpYV29BUGFxb2w3RkRGTGJuNTJINXkyUTROWmRvblowU2VVTnVzb2VyTTVvQVVrUWZpVGpzN0gwNFBvSU1vcE1nQnpEVGo0aEVjaVJxcW9xaTNlWU56ckVqeUNYSUpnSzl5blJXeGdZVmc2MDBJMUV1VUQ0L0pwNnNxZlFRMytxbXZEV0VpNklNNzZmQTh4R1p0d2x2bWd4a3NqajFkd3JXbnlYc3hic1JBcHFKOU5HNCtFL2ZiMFBReW5Ua0VmQjdNLytQWXMvT0lWbUMrSXgvb3g5b3Z1RnI0RUpreTRIdU9QVkZDTUVuMFR0VXpqMEh0ZkhuR3VnTVh5cURZcjVFeTBIVDVIcnAzTENrUUVhWU1sUG5tRjZVZnBCSVRNcTBHVGk3NnQwV1RqZ002OUxOeFNkNE95blNXY3kxa3B1Vkx4OGFjV2dOUVZqR2dwb1ZUVFVRSms1TTdvVGFiZktsdVlzNWU3REZLUUZzbk9Lc0NQRk1tallzWmpFY0F0VGI5bG9vb0psTmxPNmZYL2pYUWZaWXc0eFo0RXUvVk1KOVZyV1phMWtTRWdpNXlzVmxkcVhob0pNWVhkTytES2hweGVNZllFTFNhTVZkOE92a2dGTGhPbzl6aWhWc3lqS1Y2K1RScXV5MGlSZGw0d3BzT2g1d3h0OGtoenMxUmgraVF4bm52VVpkemVCenJUcVJhOWhoZUF1V0FlVUF5RFFFSVNDaWhPcGF5SjY5ZVBSM0ZnVDE0b1l5NFpTU2RWc3dvSXpkeUlmYWFReTlESnlMMjVHOHd2K2RLcHdUUm1PTHpRK2lLWFBKTjRZNkhSYkhPVFNyWEdWaVZuZmlsd3ZCUi90TGR5ZDR3dHhSalBhSTVJWlhYenRsbnVRMkZ1ZDhXSE5ycG80TWJQaWQ3dnNJalI5RUFybTg2cG0rRFkyVXArcjhDd0dPbVF6WkZnRUdodkY1NjkwSXRoSUFwZklUbGgvOWVIbzRKcHFiaHRFUlorTkExUkNGNnRUczFkV2xhTFdlYzNWZlBncm9TNWFhMmczL1BqUDA2SFowZThYckxTeXR2THBlRUFsN2toN2tHbElWeGJFL0Z3Q2ZEQmlzZjdWMkQ2Yzl2Ti8wZ0V3dEExSmdvUUh0bnBlelNUeXArSjZCazgyRWkrWjMrVEpycDQvS3c2bWNDM21LOGF4WURvKzYvSDFGS2VLV1Y4VFBHNHpzYXE5MzUrditnbElXZ0tmQndqRzRaTEtianRqVXpRQmNDWllqbkZac1dmYTcvaXNaaitsT2k3T1JRWWw5clg1cHB3NEt2WEc5a0p4QjV0RVRxYmkxRFN4SkJ1c0JEcmFjeGhZeDNMZFIyNjVVcTZWQkNvVW84VWZpQ0VGcElRSk9zN1N0MWFHUmFJcVFEUVpoa1VkQ1dpeFdmRmdGMGpUUUtFc1F0UDk0bXRobCtMMHBnVDdRSGoxYk1zNWNML3RVLzNhalhtamdiWnZKV1dXT1pBdWVXU1BZSGtLa1dpKzJrUWNUeEtCcUo1OTA2SDlqVmY5MlQ1YzR5TzNUeDR4UElSTmlJUUhUZDBVbm9yTVl1RUlHNTdDclFNS1gydk56SW5vQkpXcmtHU3g1L1dla0FPZWxOUnI2S2xtS0wzblhqK2Nla05tOG1oOEduNHJKcmRLY21pV3laWWdsUmZMSm8vWkJRd296aDFzUTRHTDJ2RUxFVWNwSWFCZVNleTdSdVNyeHF5bjB0QUdyTVZEM1MzSG1OUmE1bm1PUDh5RzRvM0xBVkluWGhXMEpxQmNrOE1DZVZvcktRSUdzZHIwZTVuUTJBOTYwV3ovdmM0aVdsWWFtTFA5S21LTGloTm9vQ2orUDZSb2R6SFVEa2NxNkl5K1lNUlIwSDdkdERIdEtyaGpNRXpxd3JlRmhoSkZLcXZ5SkdUTy9NNmRycVRGUW9VVzJPRTdUZDNxMlUycjc4cDRJMTJyT05DYU56bCtrUmxJbHB0RGwxbFZuREQ1QUNPZFJwV2g0S2gvWjg4VWhzVnZJR0tIa2xUc05BMUs4Tm9MZHFEd0Znd0tjSmNtR0ZYRkVJUFFhY1U0blZocjVTd09nRW1WcUlzR1JqcmtJSzhEK2F2Q0xtNmlEc1Yyb1piV2dOSFNYWnQySEczdkJOOGwyTlZvV3cwai9aN1kvay9hQUV1VVRGUE1IQ05Bc3F2WXRyYVAvcTdXSGk4aU55aCtXWXJyZno5bzJZSjg0S0lRN2plZ3VPTjdqWjFnRmhmaE1rWmVKKzVCcWY1UlZyM1BSVGpuWTFiV0lzVFFpQ0R4bWk5UFdOOUs3TytaY2NPUDhyMUQ2L3RmVVBkWEVWQU9GTSs5RngrWFFrVWt1MmZEODJyRzhFZDVFUUYwVS8rQUdCWWJGNTZDK2JNTzhxeHIyaWVEV3dmaTMvckFUaVUwc0xCNEZzOUc3WXdTYldMWndmWDNWbjh6RDZaN3RXV01ObUxvOHV4b3pucXNubGZHeDBDblY1NUxwSnhOLzc4WkcwM2xmaiszMzgyRE1NdWpldWlXcWRzeEZ2aVpwdjVZWE5SOG5oMXVhSzcwM0piR0I1VFJyMXhhMWprN1lKS3RPRFp6OHV6OXFSQXFibzVNVWJoVmJ2OTVYV0J0TXVjbXNCZmtITWtaVGZuQUNVRUdJTU5HNElVK2Fld0xBdXVuYlIxbHVRWCs1S2w3M1VTWmVnZ3RIeFpTZTRFbklIYk45bmpGWnJ6Rk1oZEEzTFhmTU5XVnd0c0hZcTNjZzJMRWFCTlp2ak1DQlUzZmUwL0RzTkdEUXdmTzB0VDlqdkoxOTJOc0MzeXZ6djlQRTNZSnovUUN4cW5zMmpaQUNOM2EwTjF2QllYZVpWdGFMU05Rckt2cjZFYnB2Y0Z1NUxrZ3pyR0N6Mk5xeG5sT1NYOFA4VVVYekxIRDFqVGI1V3k5S3hYcmhsQ0hDbHJtbmVkT2QxZnM0RXRPdHErcjV5RnZsQXdVdnJ6UXFudzZGcGhFbWNxRTdER2hDMlZjSDNiSm5Tc1FFL25XTFJnOWZJZnZxY3VXT05PYlc5ejdYRHpjeFBzeVpjRmFYTUduc08zTU5jSG9JMzNJWnlwcnNUeDRHR1JtaVJ1TWd1U1NTdkpQNEZzVlJDdWVteVJuRm1UblZ1QW8zNk1uY2JXV3E3MXJIYUZZQlpIMWlzODdDS080MGVaZGxMWXZIemxEbXR4Skx0RFowOGlMQmVZS2swYTZUU2l1RjAvRmZKalpVb0RKYXdwQlVRR1ZVZDZNQ2l2Qjh4RlRPd1ZSVXlqRFZrVXlGNTFkTFRKTW0wL01PZ0xCeDF2eW5SaitYdzBOYUJ3QXRJZzdyMEpndnpiWENQNFBJK3JhcmZ6bXFycFZPYy9jN1BkT3ZBL1gvbzYwMW1LcnVqbEFoN1JHWmNPWmFteGVSK29pSlhhTkpiWklGV0tjZHlOTjQ0TmhNb0kyMWFubnBqOStIa2VmM1c1ZFVWZzlEOG5LYTYxZ2hWdFN2SG1LQVN4SCs0QW5ET3ZkMFBUb2ZyeHdIZmlrU0hOdkxDd0xkTCtLMHk5SEZ1TU1YTnZQMVk1d25qVlFtTk1uRnREN0taNWs1VUYvMXhaakFKTldjeCtuR0ptWU1pUTlXb0VIeUIvSTZLQlJvU29rd2lQU1NScUowcFVwbkoxWE5iTnNuNUMxTE5Wb3NXWjh5Q3ovNzVmRnk3bEJiSGNPV1dnZDhxV1ZBckR4YnM4WkhJMS9ydXVMakIyLzVML1hMRHZ0V052UXFKU2prL0ZmWHhPREJXZk5mRW5pRnVuV3dENHZhTysrcnFtZFU2WEhVc1dRTjh1VWd2VTdFZllXR2kyZUZMK1UxTjV3VGYzZ3RUKzN6akZmQTBNRHVkdDdPT2JqaloxWG5vQmhNTHpnemdkamlsKzJOZGxSeGVOYi9tN0UvOFNMT0wzWnBWYldxRVVJQ2tiV1RFUEg3TEF0M1pRZGw1a2pNd0IxajJWNG1SUkZkeVlKenM3d0ZrRktJaFNoeEdDbW1IUHpWRm5wcElqb29OQ1B3dUNjQWRrZlU0TG1DT0dadDU3VDNsdXk5QXJ1Mm5UdUh4NXNXeWdlOWd4WHMwSW5nUmhOb3Z5ckVWVmNUdk93OVJOVjVtM3hwR3NMUmFiMGQvamFZVGZrblBuMVQ4dzhrcStyYUwyQm0yZ1BhZTQyMGl6V1V2NkJRNloraEdUeE5BdFlocTMvVkxkUHRCM2E3Q3RQblU0UncxUFl0OE8zcmY1TEcyUUkrNVNIREkzZVZ0SEhBTmZ0aHMxSVBoR3dHdXZRTWJuMFlLWkZMVFQ1U1B3a0tIM25SdjY1NGVMa0VpdVVuQXowbmpncTJtYXVlRGxZcXBrZnBSZTkxU1JhRXE4dm1rOFVMcXJPT1g5UG1VT015akU4c3ZoeGJieC92RkxXRkQ1ZDBCZzZxVzhiczVvaFhkK0g5MVVET2NoQjZGREEzamhBc0d1QXZ0L0YyZFBWMCtTSkJsS3ZEckNqa0F1NTZTSHlpOVB0QmVwZnI4QWhXaGhtRFplQmdRWWdFK1V3djVDdmpXZThRSGRUelk4OENuSWx0TEZ3Q0ZybzBwdjdvZndPK3VycXJnTFM5OXdsS0NtYUdrZzFsa0tHcnBlR3FxM2tyYitoS2VyV1o4Q2IrQU5ldW9sVHdUazNlYmhLUGdqRWJ0cXozaXF4YmZJbXdYZFV5cHVxVE1YV2hYZEdyWG9tdXpIRU9LK1F5SDJRajNKaU41UmdKYXlWZEFGSDdTcFZ4Qnd4b2pVVGYrV240dDhiWEdUL2pQZWJLT1pNeHlFa01mU2JDaURtWnZ4aVpXODRzcDBjTHZuN0ZNMDlBekJhRTg0S3Y4YTZYZEZTNFJFb0dQL0dsditVZXkzdnQvZHdqSVFzcDNreTYxTTlIekhPZytXdnVKSW05clE3ZlpzV3dQUHROSFBIRUVNSFl2dmFtT05FQVh1cElodUs5cWY4R1ZSRDQwSkc1L21yaitTV0VqUHNTVlJYZUFqM2k4SHFaR0lENGNCMmE3cjd0Umw1SWtINktiSkNYQVNmVzdjMTRZL1RYV3lSVXdlLzRHTll0RVJiZjd2YnhBdlQzaGZvTTZ0RFpDbzdUWWlQekVFc0NjNm55OGMrWkJqblY2bDI4NDVlNm9oZnhQSEdFdm0weDdaNDVha3RHM1J3WkNOOHkrSnNGYnV0T0VTSjNjYysxYk85TEJtOEY4RHFDK3VUZzR0ajVwZWtTM2g4TFFwci9DOGFZR1JPbnNTSjNzNktvZ2MxcEZVYXI4MCs1b1gxWXNRWTJYaWtWbHZLOFBSc0doeTEzZ3FKYzYrZXdqWnBZcFFpUDNMekhsRHVuY2dnUW5VdU1obDN3MStiMHd3aTFDQVFpNy9Kd25EUW9nVklwalZObGU0M2s1ZFRtUW45NFBzdHhPQS9GUWw2YUdMVDVNdzN3RHJ4cnMxaWNXUHBMdDFnRmFKTEdaZjB6T3cxczFHNEpXYkFhc2tKbURPbkZYTk4rb00vOTlsNXlWNzhJdTNkemhPRVhtT0gwZEpuRXZhSVZ4Y3plSEpocExCNW13NzJ5ZklIdTdLTFZROEFiVUFoSXp1cjkwMTMrdGNHaUF2dWRHcnRZZUpGbTFybzBYUHNWNEtxNEo0dXA2WTl3eDROU1pRelhtaERoODloaU9MWmF1U0xEeCtwWHBrREJDUVB5b3U4NG16bkF5ZjQwOFdDRjVpUVN6NENYSHVsdWUrUW9laktTdmVUdllrYVdaWEN5V2RUMmhDU1lHMU1nNTcxdnkrRnFzTUNsS3NVdE5GVGR0Mm9zVG5QR3UrdlZJbmJaME9ZQWdlenlha2dhQWRDc2dPV3FJcXRuNk9hRENmOE54d0F3VHgwOEhwdS94YVE5SHVtOWU5NUZiMHVvVHJPWElZWUI5K285M3RlNitqYVR0ZDlxK1NML3hyN0c4Q0ZZQnp2TERGMHNiQStPbm5Gc1ZrUXZxRm5FVURoRWJ1SHZVeWJhVWtRamlDQWdvTW9iUkliakJXL3BQWUtuZFhvcWlxcFJHR0NkZVNNRnp3MHY0S3dTTVN2c2JJejRuWWN2SStneEtZQUlnTlJYM1V3UXptMEhZdXNCVEFBb1lRVGo3QnEyem42eXd5L1lMa2Fwd2d1cmY5cGxBS3RGQkovcDdwNDEvdmJmektBNmthWkxSbmtoeTdMTXM4VlJrS293VTZ4aEVVT05tdHJMemszL0tNSmNDR0pqVHYzbStNRlhhRVY1a1czZ3BtbEVCOU0zRE91bDg5KzQ1ZjdZS1FWNEJOZXEvVXVhMGN1TFZkUFVhWmcrRFNqQUxob3EwT0dFdWErakx3MHl5c3RUdVdHckxJanpOY3BVMDJtaW9MdEpHUTVtVzFyM2hkMUdIOFRJVWpxd3RWNnhUSGFlWEUramFkVnlmQVVNV3hvM2Q1TnFBV21CZDc4OE1NOWNaQUsvelNaR3djcVZINzN0MHVBeFk2L05yeThMRmg0S0RVcGN1SStZdmxrR1ZRYWRBaXFvNU5nd2VLL3hjRldOT2QrTHY4cFJOZ2hQekJUVVcrdHh4SU5hSm5zVlZsVmhidCswVnk5TXcyM1ZvUFhzSzlzdjhDRVMzYzI3YnU1NWVyNE81aEJ2YWY3Qi94N21HTm5FUk1jeENkeG5xcitXVzNycE5Ea1E4RWxadjczRGFhNlM2d1RFZUFDTUZodlhSbk04dVpEdkVwcDNZTHBSV2MreWRJL2FGbXY1b3RUMHZqSG8rWFBhT2VaK2RJV1FxbWZMMDZhYkxrdm9zNW9KRURuQ2R5S3EzYm94MXRJTmoycUlJdmY5aHhITzhVV2JtZzFKNm15d1hYbUEvaFlNMFBGT3NETEllQnMzM0ppWEFBbkFUR2xNOHBLc3hWZ1pXaEJkK3Foa0FGR3BTMTNOczJBTTdLQmdQVjhsUkJaNjVFM3RuMGFRdGhyVXZsYmR6aHEzbk95R2ptbzVVWWV4TnhHVEY3VENQTzhSYmc5VjU2cHdvM0JickZwUzhBZVZJSFg1VVMyYTBsdEQvNzdxeUJsQ1JxaW9wOGVTNHJvdm95bWIraUVUaG1kU2VFUStUS2JINVVqaW43T2R3T2dDQ3NZbzNxQTZINElGS0pxVFF0VjkwYUZrWVlKbTVLandBYmdsd2tlRGM0eTZYbjRRRG1iMGFHNUgwMnRoc2pzNENzcWZ6Z1FBTHpac3hZUkJWMVpSK3BKMGtGTzNFdlU3cXJXaTZlT3Y3eEk0VGROZVJLWHczQlo5VDg1YmtzUzBFVVJ3L1A3WElySHA2YW90dTZIbzZQZ1RENlVTb21KMWhycDZqbkozNE0xTGxESmVJTjhqcG5jWnkwRUIrejVnK1JWSXhOWkZ0L1dHcVY5ZHU3TEIyWjc2UXI3VFg0ODJNaDdlakJTT3JsdGtiNEx3UDllL3ozTzVDdE5UMzYzSzhidzlKUjVjMzZKUHhRU01BYVZ0bmxGSzRldnJ4c2FzWUhwVUFObm5tWXpZdHlLcXpHZHpSTlNrT1d1eExOS0wxSHpxWkZQM2o4VmZjZEF2YWVkOFNWOGpNQTZ3QWgwN2I4c1h0Y1lJUjFrZnh6ejN4L0RCdnBCKy8yRDdqcUhRUFZxZjJjQlBUbWNCMklJb0o3V25KUGt4NS9HWkNXd0hwNVVoQ2g2SjBVLzVTVW5VdUhhbHREM0lkYUJXUFhKc3VtZTJRbUowYVBzb1lXVHV5YjVvZmxEeTVzQ3JERWZPVUVhS3JmYXRSb2EvS3BXcHlVdkYza0YwenFGUkt0YXpOaVNBME42amZOdEI3M2tIL0Y0bUdZTHpnVkxrOWRaeHhYOU5Kb2pGalo2eXZuU05pZm9RZEEzbksyV2RzaTBsZ2djZnBCYUdHMXFRcndHSEFUTEVVNWpwelg2NnVSLy81dGVrZVJKaEo1QWlrUXFTcUh0TnI4N1dnMnNocERDTmpUWGFIakxXbGpEenp2cHBmNitnYS9rTEE5WkVxMWcyN2JKR0thL1dKckRab1BRM2FuOFUzdm9wVFpudm1ER0NJNlNmRmc1bVFTQlNTWnprPSIsInMiOiJjNjQzMTM2ZTdjMDJiZjZjIiwiaXYiOiJiYTFjOTRmY2RkYjE1NWJjNjc0ODU0ZDhhNzJmOTczZiJ9',
                'public_key'          => '7D857050-F609-4F6A-AF63-CD04DE665FFE',
                'site'                => 'https://my.account.sony.com',
                'userbrowser'         => $this->http->userAgent,
                'simulate_rate_limit' => '0',
                'simulated'           => '0',
                'capi_version'        => '2.3.5',
                //'capi_version' => '2.3.5',
                //'capi_mode'    => 'lightbox',
                //'lightbox'     => 'default',
                'rnd'          => '0.21477650373695933',
                'language'     => 'en',
            ];
            $this->http->setCookie('_cfuvid', '_P_l_RNgjjKoSDBticg075gPhNsdJmq9cXDl7VtXxrU-1706765286712-0-604800000');
            $this->http->PostURL('https://client-api.arkoselabs.com/fc/gt2/public_key/7D857050-F609-4F6A-AF63-CD04DE665FFE', $data, [
                'Accept'       => '*/*',
                'Origin'       => 'https://client-api.arkoselabs.com',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Referer'      => 'https://client-api.arkoselabs.com/v2/2.3.5/enforcement.dbdaecd6be139c514b4e57af93809d20.html',
            ]);
            $captcha = $this->http->FindPreg('/"token":"(.+?)",/');
            $this->logger->debug($captcha);

            $provider_key = '7D857050-F609-4F6A-AF63-CD04DE665FFE';
            /*$captcha = $this->parseFunCaptcha($provider_key, $currentUrl);*/

            if ($captcha === false) {
                return false;
            }

            $this->http->RetryCount = 0;
            $data = [
                'grant_type'       => 'captcha',
                //                'captcha_provider' => 'google:recaptcha-invisible',
                'captcha_provider' => 'arkose:challenge',
                'scope'            => 'oauth:authenticate',
                'valid_for'        => $this->AccountFields['Login'],
                'client_id'        => 'd5df3976-b7fa-4651-bcc9-05ac9f0cad47',
                'client_secret'    => 'VF8B50Lt0aqyAZH4',
                'provider_key'     => $provider_key,
                'response_token'   => $captcha,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://ca.account.sony.com/api/v1/oauth/token', $data, [
                'Accept'  => '*/*',
                'Referer' => 'https://my.account.sony.com/',
            ]);
            $this->http->RetryCount = 1;
            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                if (isset($response->error) && $response->error == 'captcha_response_invalid') {
                    $data['response_token'] = str_replace('ap-southeast-1', 'eu-west-1', $captcha);
                    $this->http->RetryCount = 0;
                    $this->http->PostURL('https://ca.account.sony.com/api/v1/oauth/token', $data);
                    $this->http->RetryCount = 1;
                    $response = $this->http->JsonLog();

                    if (!isset($response->access_token)) {
                        if (isset($response->error) && $response->error == 'captcha_response_invalid') {
                            $this->captchaReporting($this->recognizer, false);

                            //throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
                        }
                    }
                } elseif (isset($response->error)) {
                    if ($response->error == 'temporarily_unavailable') {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $response->error;
                }

                return false;
            }

            $this->http->RetryCount = 0;
            $this->logger->debug("X-Referer-Info: {$currentUrl}");
            $headers = [
                'Authorization'  => 'Bearer ' . $response->access_token,
                'Content-Type'   => 'application/json; charset=UTF-8',
                'Referer'        => 'https://my.account.sony.com/',
                'X-Referer-Info' => $currentUrl,
            ];
            $this->logger->debug(var_export($headers, true), ["pre" => true]);
            $this->http->PostURL('https://ca.account.sony.com/api/v1/ssocookie', json_encode([
                'authentication_type' => 'password',
                'username'            => $this->AccountFields['Login'],
                'password'            => $this->AccountFields['Pass'],
                'client_id'           => self::CLIENT_ID,
                'session_id'          => $this->getUuid(),
                'widget_data'         => "{\"jvqtrgQngn\":{\"oq\":\"1920:499:1920:1050:1920:1050\",\"wfi\":\"flap-1\",\"ji\":\"2.3.1\",\"oc\":\"2501pp0s72219oop\",\"fe\":\"1080k1920 24\",\"qvqgm\":\"-240\",\"jxe\":624950,\"syi\":\"snyfr\",\"si\":\"si,btt,zc4,jroz\",\"sn\":\"sn,zcrt,btt,jni\",\"us\":\"8p27p5oq7380sq20\",\"cy\":\"Yvahk k86_64\",\"sg\":\"{\\\"zgc\\\":0,\\\"gf\\\":snyfr,\\\"gr\\\":snyfr}\",\"sp\":\"{\\\"gp\\\":gehr,\\\"ap\\\":gehr}\",\"sf\":\"gehr\",\"jt\":\"1pq946774q7r8s1q\",\"sz\":\"633s3q5o75620rq9\",\"vce\":\"apvc,0,65oo2orn,2,1;fg,0,fvtava-cnffjbeq-vachg-cnffjbeq,0,vachg-899,16,,0;zz,n4rp,33r,17p,;gf,0,n4rp;zzf,3rr,0,n,36 60,r7n 2o20,4sq,52o,-1q9q6,1o138,-408;zzf,3r8,3r8,n,ABC;zzf,3r7,3r7,n,ABC;zzf,3r9,3r9,n,99 110,861 7289,133n,1375,-459rs,2s9sr,172;zzf,3r8,3r8,n,ABC;zzf,3r8,3r8,n,ABC;zzf,3r8,3r8,n,ABC;zzf,3r7,3r7,n,ABC;zzf,3r8,3r8,n,ABC;zzf,3r9,3r9,n,ABC;zzf,2717,2717,32,ABC;gf,0,s319;zzf,2710,2710,32,ABC;zzf,2710,2710,32,ABC;gf,0,14139;zzf,2710,2710,32,ABC;zzf,2710,2710,32,ABC;gf,0,18s59;zz,47q,26q,195,;xx,3p7,0,fvtava-cnffjbeq-vachg-cnffjbeq;ss,1,fvtava-cnffjbeq-vachg-cnffjbeq;zp,14,4sn,114,fvtava-cnffjbeq-vachg-cnffjbeq;xq,r5,0,5;xq,4q,1;xh,4p,0;xh,10,1;zz,or0,4pp,1os,;so,1p6,fvtava-cnffjbeq-vachg-cnffjbeq;zp,27,4s8,155,;\",\"vp\":\"\",\"ns\":\"\",\"qvg\":\"\"},\"jg\":\"1.j-666234.1.2.G8TxeND2rFRHe892IJa6zj,,.bJLzJCiaFGyNSGKYAK66lEGXwDESfutDMrf0eRk6cCjSxBhrOnvQEkOqkYMign0ylPoCf9uKrBo6ozNeZShqFAcvqJNHN1Tv7hNbP3eGflvZnFuNRTIcQlm6wf4efKTxqYb8T_Nsb04CwgvsHDM2qWyArplI2Sy21JIRt_wBiRkcnQxGxgX7K3BHHxvvaI14ObYYPcaFDnJSO4K-eTfmwP09iMH0m7CEWdh0fJrEU8LcLYO-KGlIEZyQ1_PwJ652\"}",
            ]), $headers);
            $this->http->RetryCount = 2;

            $ssocookie = $this->http->JsonLog();
            // 2-step verification is enabled. Check your mobile phone for a text message. Enter that code into the Enter Code field.
            if ($this->http->FindPreg('/"authentication_type":"(?:two_step|rba_code|authenticator_code)"/')) {
                $this->captchaReporting($this->recognizer);
                $this->State['X-CorrelationId'] = $this->http->getDefaultHeader('X-CorrelationId');

                return $this->parseQuestion($ssocookie);
            }

            if (!isset($ssocookie->npsso)) {
                $this->captchaReporting($this->recognizer);
                $errorDescription = $ssocookie->error_description ?? null;

                if ($errorDescription) {
                    // The sign-in ID or password is not correct.
                    if ($errorDescription == "Invalid login") {
                        throw new CheckException('The sign-in ID or password is not correct.', ACCOUNT_INVALID_PASSWORD);
                    }
                    // As a security measure, you need to update your password. - old?
                    // Your account has been locked. Reset your password. // {"error":"invalid_grant","error_description":"Password expired","error_code":100,"docs":"https://auth.api.sonyentertainmentnetwork.com/docs/","parameters":[]}
                    if ($errorDescription == "Password expired") {
                        throw new CheckException('Your account has been locked. Reset your password.', ACCOUNT_LOCKOUT);
                    }

                    if ($errorDescription == 'Failed to sign in') {
                        throw new CheckException('An authentication error has occurred. Reset your password or contact customer support.', ACCOUNT_PROVIDER_ERROR);
                    }
                }

                // TODO: debug
                sleep(5);
                $this->http->PostURL('https://ca.account.sony.com/api/v1/ssocookie', json_encode([
                    'authentication_type' => 'password',
                    'username'            => $this->AccountFields['Login'],
                    'password'            => $this->AccountFields['Pass'],
                    'client_id'           => self::CLIENT_ID,
                    //                 'session_id' => "758cf840-3ca3-4825-be59-53aca5130983",
                    //                 'widget_data' => [],
                ]), $headers);
                $this->http->RetryCount = 2;

                $ssocookie = $this->http->JsonLog();

                return false;
            }

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://ca.account.sony.com/api/v1/oauth/authorizeCheck', json_encode([
                'npsso'     => $ssocookie->npsso,
                'scope'     => 'user:sony.account.get',
                'client_id' => $this->http->getDefaultHeader('X-Origin-ClientId'),
            ]), [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
            $this->http->RetryCount = 2;
            // Errors
            $response = $this->http->JsonLog();
            $message = $response->error_description ?? null;

            if ($message) {
                $this->logger->error("error_description: {$message}");
                // Email verification required
                if ($message == "Email verification required") {
                    $this->captchaReporting($this->recognizer);
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }
            $params = http_build_query([
                'client_id'     => $this->http->getDefaultHeader('X-Origin-ClientId'),
                'redirect_uri'  => 'https://www.rewards.sony.com/UID-PostResponse',
                'response_type' => 'code',
                'scope'         => 'user:sony.account.get',
                'login_hint'    => $this->AccountFields['Login'],
                'cid'           => $this->http->getDefaultHeader('X-CorrelationId'),
            ]);
            $this->http->GetURL('https://ca.account.sony.com/api/v1/oauth/authorize?' . $params);

            if ($this->loginSuccessful()) {
                return true;
            }
            $this->http->RetryCount = 2;
        }
        // The information you entered is incorrect. Please try again.
        if ($error = $this->http->FindSingleNode('//p[contains(text(), "We see that you normally log in to Sony Rewards with a different email address")]/following-sibling::p[contains(text(), "Please Sign In using your Sony Rewards email address.")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We see that you normally log in to Sony Rewards with a different email address. Please Sign In using your Sony Rewards email address.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->http->FindSingleNode('
                //div[contains(text(), "Please enter a valid email address.")]
                | //p[contains(text(), "There was an issue logging you into Sony Rewards. Please check the email you’re using to log in and try again.")]
                | //span[contains(text(), "The information you entered is incorrect.")]
                | //div[@class = "content-asset" and contains(., "Sorry, this does not match our records. Check your email and try again.")]
            ')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Please verify your email address
        if ($error = $this->http->FindSingleNode('
                //div[contains(text(), "Please verify your email address") or contains(text(), "Looks like you,ve previously used Facebook to access Sony Rewards") or contains(text(), "Enter your email for your Sony account")]
                | //div[contains(text(), "Create a new password & security question")]
            ')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        $this->checkProviderError();

        // We're sorry, we cannot process your request.
        if ($error = $this->http->FindSingleNode('
                //span[contains(text(), "re sorry, we cannot process your request.")]
                | //span[contains(text(), "Contact customer care")]
            ')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error = $this->http->FindSingleNode('//span[contains(text(), "Captcha is not valid. Please try again!")]')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
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

    public function checkProviderError()
    {
        $this->logger->notice(__METHOD__);
        // Please agree to our terms & conditions and privacy policy
        if ($this->http->FindSingleNode('//div[contains(text(), "Please agree to our terms & conditions and privacy policy")]')) {
            $this->captchaReporting($this->recognizer);
            $this->throwAcceptTermsMessageException();
        }
    }

    public function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        // https://my.account.sony.com/public/a144f956f149520c1e22e6696da3f
        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            '2;4600117;3159364;16,38,0,5,7,80;UxV@Kr^;+u>QQCi/tCFpzPG-Pz<DSp{pWT47<[hvTILccvb,@MCB2MmEK(TrVCAmZ_UpuOURJSqot^g^|1:En[~1Q3NOc&XqUE$/K$=+^mQ@zz2ZJ1=4(,,ldd{3`X`IIx@>;9ERSLW1u)Wj?:ds*&0fN$M1:r4  ]yPm8KK[]4&=;V})+[d2wz<-O38$Z|* L)G=|DgGMiGh9=ry|L>CJcbZ9%52c0?G;J+cLJrfP4(FfLF5<Yaut=8L(z5SH9AY%F!y@hKn0)s:RP@UDpx<Xa>;/@t#X_.Y:dxH$6J1II)3:-}xXt&_m8y- qh51)H^G@.a665K}yoi46$6hCr3d_y%YzwS:J!q$D_-YSUaE:CqVWe0?Z({4lQ=D!R-C@mO+?yLI? 8A4#k1uZ7^I~},H^8)=w`#nl,|m#eR2Xb!49F hk/M%^=fXf16J!lz1_l7$=HC(r>q)uE<b}cyb4$M)|=X-,{1M?F{Q9|t}0^L;;CZ{rSvX[T|_#^<6 !yhiDE9H4O;`sRm$<]j$Y{mF!{ _Ka~ea$:(!%+0 i7 kqHqzl.;%[U7=h^)o3THRm?-nscsv1z0mVv&iFx4Ij|x%R`zje&y!Pwid]l$).?dE~4;Y^#l}aT$OV^2}dcEFB`AM5%MHho.)@c?8@DhbvC<-o|=kQ6^jO/yII`8cVEsz2JyduBL:Xi^fcwXP*VEfo9A=cg/Nr#J-n4^xw]79)eRs#TH<*!-*ySlc|:TQc[*{kK]z/qyjETatu5/%qPn,jSlv8T-B6(.&FGNm1uOcc}(>GZ,zrwQzpWk!Kw:jdk$kg;2NsoUY18U5V}44ugez[SewJndkUwJ_xyi/eSMo]*1itga(26kB>MAY?jYpV3]~@6-j62#DN|=U~E]i0|XLj_b(c4!{b2O{S 4[=D=d0=U;eD4WOl6jlt7P:u~K/cMh>_%-_`f.=t8#D?5^Tk1r9](!pB>!:OCaU=O7n+)Ci<Jq>-@G{TcBW5HrW;atNO!DH1sgl(8fz;dLOc{FCK9~6J8QICD v@U4l)X3v6Glg*f>-xP9)2$xOB}X_QJGm1#sd4Y)w$F:fez^::y)Ah[]tB9V`TeO`HT,}8fyN!.jJ4*.)GvRrS^gf rWl?YPsbgPbX:5+HlF3)xnYg0d3dRb}[BJ0EDGm_0|&$SPd9gi!udvj=+sL!}~lo}h~o98jWrdkHwms<&XjX?E8FwpJk,4kuB9OPx3kzwv;D=]>p;Ruq{|*vy0Jt2H8N_W!<yB^bg0H1pj)qMO8O=tHdFxOZI(;+/.C_[BJ&NQUD~r/mqlh%);DZHPat:%+Yso9YU>p#Kdg_9a+iYO@$g?=`RJ8.;h2o|/fm.V#9q%H#r94R9GLh/cSgi{[Q@H;.WbOQ{WA_6MYt=VeT:-]*<`h47^LGz-2(Rp5`;t^zbqF06t12BHDo%Lw<IXQ_BpLMS]{N(aV{>Mt%qu#lGovN_9]8L]d[+ln|:br8qO2YqN{vDE$~R@&VpQ83{Cpm*.gW>bHp|crK8M1YbJZ0=N`8pGpkgl]I0q &NuWu[Aw`GBk6G)${!Zqbzi:%@s~N8Yq)gH.qxf]TJ!ha7C(MYJ$jD$W>zHLeaPa{^@@%#xEy[=,;zLC]|G{kI*q~7X=lYB~MYt_szy1lpUNd1{y:Y1kdH0nl:y3hXZ*7*duf{{,w,[`yQODk%fva(%^kG(XnkmJaT?XFn{qM`Fq0Te_*_c:rkqZO=!&>{0X[=m-x8Gictf](qm#[lnM]ZImmh{Kx 0iI>/WLB;JJ`R}G_zGw}!b%MXJX=FDEN)W:i|GAi:1Wqq/A4Zb+b%B`cs #npJAO3>y2!Nt^Gyufb{V701|p1^]b9RD7IJ/FZX.@;l)P S^.1`Bunn;.*RYcJ`) 0jbx;1n-8l|^%N!PI{SHdF&i~}:Jon=3U9w9yUT`24kR^!0T|.o8xrWq4/66mvKKZ{17,Ob KnUxC%7)TK>*(B7..<$?SZ&E{azeUOJz:$Jvc8^V&X<[TpnXMER3MjJDUu{&ik.9]+i-!O}JrXy1~{`e`zP<:<Fw*1Q0CdnF!WJ#*;EO0^H?[n`]GiMhjH{D36QfW] Jp%=506yd|yJuY^Lk%d;I:Xw^Ga@NPd>;9&g/|!9-f!(.p-^t&9J#68T2tlwg.)i>Mrll4mz(nce?}![8 NIDXP?`qqygvt1fg?*IZYRW<5ob]5LVLY.m?z2mK^r^?Lr[y=Wgd6#@$)ev54pA37--X7N5/a9ine/)ak4P$72itF=q%op40iEboQxU~xr;99q5g$ +PpTkS;-Dh>Xd5J(;5:Ed?5JakltA+B0AoIwP;*xEUf>6!4O@eXm>JtF#g&an 8+%(=5=auqR(hyZ3~a9%q;//LQ4o!}F^H syzN=`D4!=*5LU7nB=$SH2g_}WuV4t?sKHk!Nk#MDq3$?/j|~{:pJofWfHeSEPr]=8Y#PxxUMqsl[Wb* M[}k^+g2VI=+(h8<aMr6x!bT}qS&F5eoGLumQea!ID*~:b4c<RT(MZz.gG72Fk=v}0Z`6R[|KDKerm@L$c;w:`HWQq$s0 PqO-LBkim=}>1Hhn0kA+pLvEC.,Zqcz@z/m%`!Rfa%4KF#QR0&^U9!Aw#6/N_[,Xv|I}fHx9=dV}!AiW9-*sJGkOQha.@@Xl(N.kBq9:-![{P%UOUKqt]S%b{*-/6$e%tG5bT*A50)9c&mI<#-}tgaD#9OB9Yi=wX6;YOCie%vp&U@wr`hZjbe8*Dfaat2pA9{qM3nXk2.[qBF4<Z.t6js&!B!jv$4srC#[7yeiy+YJ~dk;!)fpBSM Njw#lIeV10yw2zkjO)(2Y2!k#*=w -QW3Vgri>n`FVqrhuwc@mr&R1;t9_mf(0D8;lRNcCmYCfcpoi1.$|91sk7Ng#U= 5G7C6Ph!V44+3|UQ|0@uBi:|fh#%<05=[{!,mMp%n]?l0%BovfmVWfdM.GQo)]}Pp!Wk)r_!=M~#iaYa$I~;E94%lpNVnA^pj$eC9Y&sU}R}nJ=Nk;RfUMDe*GR$J+ Wu~#B71]$L{:^=+<6RWkXZ&A?9^ay3K$^+gy7)*]^`).0cw&!Q~OJl-:K=e1O)uN$X;^u %N$}V`o`7p4an_G([+A:n%oM~>w3x/iDHxOQpY/bMv9?p@7D/7f`8q,,%VAq&yV^{[:IDl;w`i;{/dz<T4ci~I:A@o#vSjH]lK6CQ*4sR8b<mSSNe:^.zakB#xC{xcaCMEClUpRC:-XDmLSoQ>f~V-^8zdg[l@e%gCxkm4f;U*H@cH[s}Kp;{ZTE6~#JIt;BOM0RoR%qSG32F>m|^^.O8vrt y(-E*VS-Dg1%?`_cq0laS-VRc4RI}.7?OX4{{,mga:o&$TOR=U@mZkFGT<M=@Cveh2S?yO%(bg3%LZW{XDF:RM(18e?=h=FiB>B-|KU1nGIJ}Y4V2VgyEzw7AL]x~?RlvJPo(cR/<i)_Y9709:Yw2_Od*jtCHdx/nEVxw!ZPYrH^oYn 0Ujdc7-DUA(99_lw|AMw(&|vu;y[ag$wBDX[aF&Xg^Bh!Bm=2sXHQaTTP2i^$U-;`Zz;zp_!TC2Y]DheP4d#Dvka2`Ahe|Y}Rf}:9c5sYkYC Et-jrY&B[[*b+%!QMdrNZEwB?8qVUx[$]+dfIs]l36@jB1Z.5b:Y>z+J;:vJPTz4Uej]bB;PecsLTH(NbY2hlj,R1?z>QATenI@(q`31EW!vZgb.vk/pqN<zv:^`HFGkj>:{jQtepM`$YDv `Nq.0?YXfNcpWjEBEQ!A^{DLQhQlQ/yLEQ.zx4Ua:Q>->ZjJkXSRE53qRe} c|5f< bF+u4w.O;5Puo13@>|+yyT8c oq,sGUN!!dRvXx?01nkFKN?F<,mT>zKtvo1J4n`{d-~R6X0zzMxdDVFlb y&}V+!qM!N8pkQ1oi}l9a3acra /!nH6jw36e^qAx7+Af(3R <Tc.0FCAQ8UTn{LsT}|)dfoT,L[V0$z>Aw:G_dG {}/:j|:Cd=Km,-n@$56L7`TZWRxi@AoGS^y/gf3Al`wGjgaDr(x2<;tkCU(m)JCaN7oC%Cv-Y]dOAT}eBK;[i(&J48,9_+Bv=DlxXxU|2e=%p7NYeC6uy=iY[[II>~qg8yY,jvb5?Vouc7_J:SdE|S`r2UZ.Ud${:`4X5:klKW)#z5y)tqP`(m9ho4t~j.wc@P7W>ud.~yZhY .sFqh=]?Rt0SHI:lS2HK(OF{ub7Elr~a-0+y(Ae(-DDgb_2Naw<#mD>xrM2Z(0=rJ`&AA&F)K$Aus|S;Yb*eS*5eZGOi(>wKx&(X#g+lI|Bnp,B2>ef.u1cKC(!W/RWFXSI(G_uqZV.lt,rb$:uK@ .~yWFo(8XGUic>aU4}][l-Zxyb|u_^|gSFL=Dvh(E<=Kl>XSyO|mLKP[cR I~mj^k<RC=Zq=<**xfQ$%X_oAsVq3e8-0Fk6`{z1(=&io>w<gX?+y37*y+GEfABD<gBB9L!74 X.k7;Ar?~jriaJ]?5P%)2tG<0JCi|9BUKG{i`tFy9$UK`X/?ad<K0?qI9.a`q52#RZcoVaxClW`0nr~7:>z+?YipfWb^QB0NGL1w^;2c]S[a-$$WIhC]qZEXNz6ixC>LT %m?H)%>)HEAE&;T}uC7I|9^X4o.Yv[<ZM>i+radt.XL`{p!1PZbc@.0Fo?`=`DEluVp9+z:(Lf+ST5Ko,~VN@SnL;GL]V/y^~V{!A6$Xzpl~JV4W_WX Vv-4@P ddj(LN&Za}w5z?ik-(2OJI P,x|E#,_rs4r?gdlyDNc91PL2A 3ZO',
            // 1
            // 2
        ];

        $secondSensorData = [
            // 0
            '2;4600117;3159364;16,38,0,0,12,80;RqI@GSJ;*q|&`Ci(lpnKSVAn-MJ<+z!yx[Ik$&hy7Mxw56n&CxC-2tAEVEtwKEo;+Y&vw7,p1$qqHNN{5zj.mLPYLLps*/%~tQTD&qFXBCmH|U5XJ2B6/0/amNhc{@-.P2Ajt6GXQL om|]3!j?Xs[eH@A}$:hV.Vy+;(kq7EFz*;n*eU8q>M?U1A}Gu69}%-Be73l;ipoR&;U=w=}#HHO!^ZABBXwj5q,*a~Pe95F:(?^G*97Zw$s;8S.w4M@5PY{?hF$ocwP1m5WTHFEku<Kbjxc?xOm1 3=X0d~_@{a=R2SD8&MxVRm81i;B~6)[(:FZEd`-gy==Ca:=,JRh0[oKRo(j5m_llN-%Byj8Fp:@r>mfB-@<,]!Qh $>c-@Ef,nHXte1%Yumze1FDt|MrXoQ~@)3tUn2Bis,.,T2%=?.C5b`iPV%S:`Hf-3?&g{Gl5yTX0B}C:8.HJsy*c~[6!L1E{0I7pMH-lK-Uv~~W/kCH OphLx#]K%|;bn6#w%61ehNRLH5:ifUrbX_8>4kpV:T@-5UX}_}nV1EWGB9Tfd GTDgza@CDYLC]P*_1-l9%nremtnw0>P}QJHr>|VN3+ |A,>&~WIwJ^]9v$O@;O%H?(tPv,?AT9r:[G0CHQ+VK64RlA+Af@6gpML@]$)/>mX!ISZZfrN)&3VKv?U(JzV[sIpdO[V;i!p}jO%[o2a9)<`3/GQ{3b74Zw?_/2n27z;^fI{|u49U_zy:z >T+ #{p%/,UYkZ[x5B)}3UwLqNm/EII=#M[dN:[?6vSda})kH7Y1};*}C1ePxer8H)Zn|7a)y?S12P-&^~0cwl5b0$rCx<Ro^M%`tqe3[WJZa)-i{6H]T6a?99Ab:!2U3n2m^sdg#!A(4RrK?#-ML%nOR8_3C)pKCrNq/:P<3=CTtP Db{Zh2O(iEGU c7xbS(rLg[7O%J0I/=cea.Lx}(U1LX#p6m/E8=c*!kmW=KK{4l9s6,Tyn&xNKaBib3kTO]JkHY|]7T[2D:FI}4qOBtToPop%tYYBB/#N*o93tFyYs4<G/Y,}fI6GfabhPLD<D=>5q289O`~6zx9uUT!_t1=v<}> wp {2vjR@X|QXm.w`_0qF7qQ,^>U#q0BMv&w4^)k7#TKKteF~R>5vAW_c%USu!7N.4s{1[p~uAoQ w9_p>W6lk^tkC?Ar?=,p>3;.~8%-Rd3;i<Dtz,rX$CC(?@p3n,Uek69ip}tfN<wP/M^;85GBC27$>M]{y~K):FzE~fniL0NM/tpWffyslpl~ gRAE0#F?+CH%[lk]92NTLU<]qX{0$AQMx3gY!Q/J!@p)Q]#luT#tL]f/;]XzjyFLfd~pw)DvNHNd#Cc 6fhzg<cDY{-C#5<Q(D9=2^(O<8=]9>Zj/k6(|3:_sO]Fp7/vF-($HW5]@x_z_v?2,X6%>3N6(Ho#LX)R!UMR~As_a93aRz;B>uOKHhvNF=]eAfx]+hos6f;z-Q2hO}>JW-`ac&Fl<vCr.MWT9{$-c1mC=zrN8W:@P2pr9Mm^l?j6k:mA1z$.m8(m_M>~n;o3F|QRZ:FX e>x?sv%S1Z2f@RKAEvIg{U)!hRSxOTb=$Z@mFKk`P].|SS5wh:CFkd&#3w9go[N&a@KE4Hoff#H(vr(UYjGTSJV3:#4z1q#B%5l<6:]T8o<W_r`)!&{/fb!XYEo0@|%/xhiL)<ymqLauDWdk_?IN/BtJ25`nZ/`LkVSy&RNs%^+%e!}:F^*tp}/fe!aycjWHo>8#mG}x.PB;Y;U-#BdePz4_t|aexg?hxq$fP(fy-bY1}:Hb:+Uo1/slSd-i%=key3fqvU+5o.p~%J]1?j,[tN0 2VK9|qu4TRV5:~*OwX(@;k!O_Lcc$@I$76pX8~99bN) 9gV}:1n2YlqQ#On:NjPDi7%gpr5BYnR:<WD>k.*`RHt*A^|J1z>dge]@T%$&Ue_CJm2_^[~;~5%?7I~T?U}tw}Bl(2Tq<1Y_R4: =78@|ZVk_1[g`{(A]$#^$:b;/k=^r%#)},/Um~TD#l>}Ivqx|kqp;PMtPoBd)s[<;r}%I!i^|1^#c>=djUU6(gMdI,; Jt(>F. b-}[dNiF0I)fiUsLfk+d?|_6V29Jiv`=!)s5_ K6)35t5X=/leWIr3g*|$YBf-o7A?9]1y^foN]In0>^tN{gvglY-Ws;,{8ZR. i#lzVkVeQzqR+D`UG3zm{:m&KL:b+!cdTbDwiGJid8V2Lm<2,GoyF#22XLK0M?!,1EIWufUgj7@Eu3 djDV32on}Q]_..>|2awrX:SY=`8GQ/FUu&6tk/LD:IJR/b^TNx@oe-cKQ:08*!Lw07.C=<l@8yt-h5e_z#>SM7TYJQMfx`F:AHmTXZ;^nO[hD27qayavIVo +XBy/)c]2OCeqogn/4J@N8HKz*d>X]-QO/j^3z[@G%Lfm}2qE)v6@z}3O<+X#[Icy6YDiF9_* !ios?mQIn5{&GoAS2We}V7w$Oz:7|{x8y=CvzwX~GgfRB6jUt@%X(DjY-mF/%fb6&c*5CWQa~ZxYr7}W!_?3_v:B3i.DTV_*U!F5UHF?zUHR(@0&T(dRzQ8KC[7B6_=FlL~5c%v+n/GKYqZ542^l BxT7}O[,&rw{aTYIvFX@$-kFl/>2sV%)Q*.G}a&0cn+#q;rasq9![J.i1,&8)]=4ldFaWIegK(M!rSP$-EHAJj<Q#zg#II6>![wMw{;CtK(t[?(bP>gI<o,2r,FQ6MSaMm*IK[FiH $ZSDhV=KakY;QCv09+x/7*yW%Zo@}&o]BDSJeByG$*?:aOQb0VIytujwzX`Q{<!ylY[B[)h*Xh`Tt[AakvwI2T@9C=QS}ONMP2AC,}x%O`{3`jKsn<hjk{(8=-2W<# aiAy49%X@#5;u_Lce|:&B$f8lK.9J &R NInbmU4g?,;xgik36m}t_)V|=6MaLQ x}*B6ZAT6l4?#){2Ll:J}9ZZF%p/P3YZLf`5GPMMqv4ga?dv6Eq>tA!l sl5l8z/-NV}yc&|gw}@3#JP(K K&D{)>85W)Z_oL=Uu2/[e)N}E?@^fFwC|b2cn82*c-*%09hwNe38=&z~?@rDK^(mD*SWW F(I(A-?f|A33UwI0}P4_Gc&)>~eS0|4M%hG5}z~0mY(=%sYa99gEk/`}g0vjB.`igGwXNc6!#T0b9X(YgC+hDuo[>sx QEpO.mT?DRV=IWmesg0>U3!8LU,Ob^%qhWQ<Fd$4v#+[ap(OF*MJT+lh|R.,uwXkOD+e>C3@;H5|H|k@9eIZqeOo>waXJTApAN<i{Pd=Qsn(qL&-x{2igh~-D<pw~|nL%&5^x>%zH_[ZjSR<H)$i;8A`OJ$45CH]]_CkDjqxC*!SP8D)yjTrADO(U?=DCcj+%:x{O~7>Cf[-b17(z@d+e]>_;B5G(JToFVKP#_qZ.YL^4&:;:Q>u@bPJ,OsAhlvdMo1jG&7L2xgC^re_eaZ,n-1y]De/;Stb_r<#cr_nS;:#9=8Vf4W9k>Zqz>Aep@[~F|Q|wg{>t``O[PODTYH>(!g4%ab&UEIw`iqMy_p2g*3.HT}*AjVAMKpb7x&,rV9]yma!3c*bL>KS-6Nx})@DzM*$p,Ps>uc6]+McbT4JKCF(9J5;[9T!^g=D|7_/m@x+s9o] &m&Gy<;P.kj;;$`^4Wb{PT#IZK1 w[}x2lJL+ln:l^*J}bPF +{[T_B0ZFU3H Ll_AazO=8j!Z::R>[X-^djn[5<Py wriU`|(@|fT5R()_w$,oiEDcxL>Q!>E7*Z@^4D(/o*{@*]8-+kKofdCn)?/r?Vjs)CI(`5`@[4i=W6 D)3bO*}TcH:;bz;HA#|&Z=X&Oq#A9[BJ7vT~f9Q`+VZo-uyUU602X%2P-OoxFC5**3:~xJ4=F[EfhxI@R>7jAg8r+~O43$z3|9_g9~/44ks15X 4fscKjDszx{:cDCU$L!jh&02Rv%kWo5S6@_4&O9B!Bx5R0vx#Q!Mmdvqhy)D>Wp$vZGzxEM_ep9Z#xK^y`~`k>|desbj~9Ym4LSMtmJ?lvDOJ/+kQ^)}Naj+(p%tOb8m)6on?iv([9KOMlT.a=jmt46-Z8C0w%a9;q@j8~`%$AO8-|9P|-6AYM7@D{q|iK0K+2Id9~}Fo7IJ-{IcM4NZj@An}%|/[1M6fFh@[f{xjm=CJ(CW+DgC1x w}}NuQ_-vp8-gPXGD+.YJsq?^;,y8U Ez./c[{)&CWuDVL>ry--0by9?2(-ku@pcF>-?*+gH8 uR;UH*G5QSAkx0J$KEF{; S?sd*ZP}}kZ?lR-gNU}%MWwN0lJz>w09iT&0W/#WgI?)zO+6]G6=I(GS0y46jrZekXB9we@{/ ynE<|hX<2Nl_iU-u+tVLZssh%<&g`I',
            // 1
            // 2
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
    }

    public function parseQuestion($ssocookie)
    {
        $this->logger->debug("parseQuestion");
        //$ssocookie = $this->http->JsonLog();
        if (!isset($ssocookie, $ssocookie->authentication_type, $ssocookie->ticket_uuid) || !in_array($ssocookie->authentication_type, ['two_step', 'rba_code', 'authenticator_code'])) {
            return true;
        }

        if ($ssocookie->challenge_method == 'EMAIL') {
            $question = '2-step verification is enabled. Check your email address for a verification code. Enter that code into the Enter Code field.';
        } elseif ($ssocookie->challenge_method == 'SMS') {
            $question = '2-step verification is enabled. Check your mobile phone for a text message. Enter that code into the Enter Code field.';
        } elseif ($ssocookie->challenge_method == 'AUTHENTICATOR') {
            $question = '2-step verification is enabled. Open your authenticator app and get the verification code. Enter that code here.';
        } else {
            return false;
        }
        $this->State['authentication_type'] = $ssocookie->authentication_type;
        $this->State['ticket_uuid'] = $ssocookie->ticket_uuid;
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return false;
    }

    public function ProcessStep($step)
    {
        $this->http->RetryCount = 0;
        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];
        $data = [
            'authentication_type' => $this->State['authentication_type'],
            'code'                => $this->Answers[$this->Question],
            'ticket_uuid'         => $this->State['ticket_uuid'],
            'client_id'           => self::CLIENT_ID,
        ];
        $this->http->PostURL('https://ca.account.sony.com/api/v1/ssocookie', json_encode($data), $headers);
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);

        if ($message = $this->http->FindPreg('/"error_description":("Invalid two step credentials"|"Invalid authenticator code")/')) {
            $this->AskQuestion($this->Question, 'The verification code is not valid.', 'Question');

            return false;
        }

        $ssocookie = $this->http->JsonLog();

        if (isset($ssocookie->npsso, $this->State['X-CorrelationId'])) {
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://ca.account.sony.com/api/v1/oauth/authorizeCheck', json_encode([
                'npsso'     => $ssocookie->npsso,
                'scope'     => 'user:sony.account.get',
                'client_id' => self::ORIGIN_CLIENT_ID,
            ]), [
                'Content-Type' => 'application/json; charset=UTF-8',
            ]);
            $this->http->RetryCount = 2;

            $params = http_build_query([
                'client_id'     => self::ORIGIN_CLIENT_ID,
                'redirect_uri'  => 'https://www.rewards.sony.com/UID-PostResponse',
                'response_type' => 'code',
                'scope'         => 'user:sony.account.get',
                'login_hint'    => $this->AccountFields['Login'],
                'cid'           => $this->State['X-CorrelationId'],
            ]);
            $this->http->GetURL('https://ca.account.sony.com/api/v1/oauth/authorize?' . $params);
        }

        $this->checkProviderError();

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Points available
        $this->SetBalance($this->http->FindSingleNode('//span[@class="activity-pts-balance"]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@class="firstName"]') . ' ' . $this->http->FindSingleNode('//span[@class="lastName"]')));
        // Points on hold
        $this->SetProperty("PointsOnHold", $this->http->FindSingleNode('//div[contains(@class, "onhold")]//span[@class="activity-pts-onhold-value"]'));
        // Points expire
        $expDate = $this->http->FindSingleNode('//span[@class="activity-pts-expire-date"]');
        $this->logger->debug("Points expire: {$expDate}");

        if ($expDate && $expDate != 'XX/XX/XX') {
            $this->SetExpirationDate(strtotime($expDate));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // AccountId: 3765133
            // Available point balance
            $this->SetBalance($this->http->FindSingleNode("(//div[@class='nav-section']//span[contains(@class,'points loyaltypointstop')]/a/span)[1]"));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->checkErrors();
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//a[contains(@href, "Logout")]')
        ) {
            return true;
        }

        return false;
    }

    private function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseFunCaptcha($key, $currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');

        $postData = array_merge(
            [
                "type"                     => "FunCaptchaTaskProxyless",
                "websiteURL"               => $pageUrl,
                "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                "websitePublicKey"         => $key,
            ],
            []
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($recognizer, $postData, false);

        // RUCAPTCHA version
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $currentUrl ?? $this->http->currentUrl(),
            //"proxy"   => $this->http->GetProxy(),
            //"proxytype" => "HTTP",
            "surl"   => "client-api.arkoselabs.com",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function selenium($currentUrl = null)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $responseToken = $responseCookie = null;

        try {
            $selenium->UseSelenium();
            //$selenium->useFirefox();

            $selenium->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_103);
            $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $this->seleniumOptions->addHideSeleniumExtension = false;
            /*

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
            */

            //$selenium->disableImages();
//            $selenium->useCache();
            //$selenium->usePacFile(false);
//            $selenium->keepCookies(false);
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.rewards.sony.com/UID-Start");
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[contains(@id,'_uidloginemail_email')]"), 10);

            if (!$loginInput) {
                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $captcha = $this->parseReCaptcha('6LfetXYUAAAAAPyFX7vdU1te65jjUhvqiQv1_ITo');

            if ($captcha === false) {
                return false;
            }
            $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
            $selenium->driver->executeScript("$('button[name*=\"_uidloginemail_send\"]').removeClass('status-disable');");

            sleep(1);
            $btnInput = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(@name,'_uidloginemail_send')]"), 0);

            if (!$btnInput) {
                return false;
            }
            $btnInput->click();

//            $selenium->http->GetURL("https://www.rewards.sony.com/UID-Start");
            //$selenium->http->GetURL($currentUrl);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id("signin-password-input-password"), 20);
            $this->savePageToLogs($selenium);

            if (!$passwordInput) {
                return false;
            }
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $btnInput = $selenium->waitForElement(WebDriverBy::id("signin-password-button"), 0);

            if (!$btnInput) {
                return false;
            }
            $btnInput->click();
            sleep(5);

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $auth = null;

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (stristr($xhr->request->getUri(), '/api/v1/oauth/token')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseToken = json_encode($xhr->response->getBody());

                    break;
                }
                // /v1/ssocookie
                if (stristr($xhr->request->getUri(), '/api/v1/ssocookie')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseCookie = json_encode($xhr->response->getBody());

                    break;
                }
            }

            $this->logger->debug("xhr responseData: $responseCookie");
            /*
            if ($pass) {
                $this->logger->debug("activate password filed");
//                $this->logger->error("something went wrong");
//
//                return false;
                $pass->click();
            }
//            $pass->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript("
                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('input[name = \"current-password\"]', '" . str_replace(["'", "\\"], ["\'", "\\\\"], $this->AccountFields['Pass']) . "');
            ");

            sleep(2);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Sign In")] and not(@disabled)]'), 2);

            if ($btn) {
                $btn->click();
            }

            sleep(1);

            // save page to logs
            $this->savePageToLogs($selenium);
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                /*if (!in_array($cookie['name'], [
                    //                    'bm_sz',
                    '_abck',
                ])) {
                    continue;
                }*/
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $result = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $responseToken;
    }
}
