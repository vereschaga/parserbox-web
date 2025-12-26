<?php

// refs #5932, 7086
use AwardWallet\Engine\ProxyList;

class TAccountCheckerJclub extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://hotel.bestwehotel.com/api/member/queryMemberInfo';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36');
    }

    /*
    * refs #18925
    *
    function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->PostURL(self::REWARDS_PAGE_URL, []);
        $this->http->RetryCount = 2;
        $resp = $this->http->JsonLog(null, true, true);
        if (isset($resp['data']['token'])) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://en.bestwehotel.com/login?from=/');

        $encryptedPass = $this->encrypt($this->AccountFields['Pass']);
//        $encryptedPass = $this->encrypt('pass');//todo
        $verify = $this->parseVerifyCaptcha($this->AccountFields['Login']);

        $data = [
            "groupTypeId"   => 2,
            "type"          => 1,
            "mobile"        => $this->AccountFields['Login'],
            "verifyCode"    => $verify,
            "password"      => $encryptedPass,
            "rememberMe"    => true,
            "channelCode"   => "CA00091",
            "language"      => "LANG_EN",
            "TDFingerprint" => "qwehWPHY1703833583AKkWANKDNQ3",
            "blackBoxMd5"   => "qwe9173f3c73302b3b830f9dd38afec6fc2",
            "did"           => "qwee1a8ce38ac9af371292a1e8ae77eb979",
            "deviceInfo"    => [
                "fingerPrintJs" => "58566c1d61eb62b5893ce294a507ec92",
                "userAgent"     => $this->http->userAgent,
                "platform"      => "Linux x86_64",
            ],
            "sw" => "5899f754c586ec2f05096f46e994895b",
        ];

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $resultExecutor = $jsExecutor->executeString(/** @lang JavaScript */ "
            var MD5 = function(d){var r = M(V(Y(X(d),8*d.length)));return r.toLowerCase()};function M(d){for(var _,m='0123456789ABCDEF',f='',r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _='',m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_}
            let mobile = '{$this->AccountFields['Login']}';
            let fingerPrintJs = '{$data['deviceInfo']['fingerPrintJs']}';
            let n = function () {
                for (var e = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', t = '', a = 0; a < 6; a++) {
                    var n = Math.floor(Math.random() * e.length);
                    t += e.charAt(n)
                }
                return t
            }();
            let result = {'n': n, sw: MD5(n + mobile + fingerPrintJs)}
            sendResponseToPhp(JSON.stringify(result));
        ");
        $resultExecutor = $this->http->JsonLog($resultExecutor);

        $data['sw'] = $resultExecutor->sw;
        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
            'Origin'       => 'https://en.bestwehotel.com',
            'rw'           => $resultExecutor->n,
            'X-WE-SDK'     => '1.5.5',
        ];
        $this->http->PostURL('https://hotel.bestwehotel.com/api/member/login', json_encode($data), $headers);
        $resp = $this->http->JsonLog(null, 3, true);
        $success = $resp['success'] ?? null;

        if (!isset($resp['data']['token']) || $success == false) {
            $this->checkErrors($resp);

            return true;
        }
        $token = $resp['data']['token'];
        $this->http->GetURL(sprintf('http://hotel.bestwehotel.com/api/member/ssologin?token=%s&jsonpCall=jsonCallBack0', $token));
        $this->http->GetURL('https://hotel.bestwehotel.com/');
        $this->http->setCookie('wehotel_sso_token', $token);

        return true;
    }

    public function Login()
    {
        if ($this->http->getCookieByName('wehotel_sso_token')) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $resp = $this->http->JsonLog(null, 0, true);

        if (isset($resp['success'], $resp['message']) && $resp['success'] === false) {
            if ($resp['message'] == '密码错误') {
                throw new CheckException($resp['message'], ACCOUNT_INVALID_PASSWORD);
            }

            if ($resp['message'] == 'Mailbox login only') {
                throw new CheckException($resp['message'], ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->PostURL(self::REWARDS_PAGE_URL, []);
        }
        $resp = $this->http->JsonLog(null, 3, true);

        if (!isset($resp['data'])) {
            return;
        }
        $memberInfo = $resp['data'];
        // AccountNumber
        $this->SetProperty('AccountNumber', ArrayVal($memberInfo, 'cardNo'));
        // Balance
        $this->SetBalance(ArrayVal($memberInfo, 'score'));
        // Name
        $this->SetProperty('Name', ArrayVal($memberInfo, 'memberName'));
        // Status
        $status = ArrayVal($memberInfo, 'memberLevel');
        $this->setStatus($status);

        $this->http->PostURL('http://hotel.bestwehotel.com/api/member/queryMemberExtInfo?channelCode=CA00046', []);
        $resp = $this->http->JsonLog(null, 3, true);

        if (!isset($resp['data'])) {
            return;
        }
        $memberInfoExt = $resp['data'];
        // Count (progress bar)
        $this->SetProperty('QualifyingPoints', ArrayVal($memberInfoExt, 'growth'));
        // Points (progress bar)
        $this->SetProperty('QualifyingStays', ArrayVal($memberInfoExt, 'waitCheckInCount'));
        // Validity
        $this->SetProperty('StatusExpiration', ArrayVal($memberInfoExt, 'endTime'));
    }

    private function encrypt($message)
    {
        $this->logger->notice(__METHOD__);
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $encrypted = $jsExecutor->executeString("
            var padding = {
              pad: function(e, t) {
                var a = 4 * t;
                e.clamp(),
                e.sigBytes += a - (e.sigBytes % a || a);
              },
              unpad: function(e) {
                for (var t = e.words, a = e.sigBytes - 1; !(t[a >>> 2] >>> 24 - a % 4 * 8 & 255); )
                    a--;
                e.sigBytes = a + 1;
              }
            };

            var key = CryptoJS.enc.Latin1.parse('h5LoginKey123456');
            var iv = CryptoJS.enc.Latin1.parse('h5LoginIv1234567');
            var message = '$message';

            var encrypted = CryptoJS.AES.encrypt(message, key, {
                iv: iv,
                padding: padding,
                mode: CryptoJS.mode.CBC
            });
            sendResponseToPhp(encrypted.toString());
        ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js']);

        return $encrypted;
    }

    private function parseVerifyCaptcha($login)
    {
        $this->logger->notice(__METHOD__);
        $link = sprintf('https://en.bestwehotel.com/api/safeverify/getImageVerify?mobile=%s&verifyImageKey=0.16127125152455224', $login);
        $this->logger->debug("Download Image by URL");
        $http2 = clone $this->http;
        $file = $http2->DownloadFile($link, "gif");

        if (!isset($file)) {
            return false;
        }
        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function checkErrors($resp)
    {
        $this->logger->notice(__METHOD__);
        // 用户名或密码错误 - wrong user name or password
        // 会员服务验证失败 - wrong user name or password
        if (
            $resp['message'] == '用户名或密码错误'
            || $resp['message'] == '会员服务验证失败'
            || $resp['message'] == '会员没找到'
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($resp['message'], ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $resp['message'] == '验证码不正确'
            || $resp['message'] == '图形验证码不正确'
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 0, $resp['message']);
        }

        if (
            $resp['message'] == "Cannot invoke method getAt() on null object"
            && isset($resp['http_status_code'])
            && $resp['http_status_code'] == 500
        ) {
            throw new CheckException('登录密码请输入6-16位字母或数字', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    private function setStatus($status)
    {
        $this->logger->notice(__METHOD__);

        switch ($status) {
            case 1:
                $this->SetProperty('Status', 'We普卡');

                break;

            case 2:
                $this->SetProperty('Status', 'We银卡');

                break;

            case 5:
                $this->SetProperty('Status', 'We金卡');

                break;

            case 6:
                $this->SetProperty('Status', 'We白金卡');

                break;

            case 8:
                $this->SetProperty('Status', 'We黑卡');

                break;

            case 9:
                $this->SetProperty('Status', '普通用户');

                break;

            default:
                $this->sendNotification("Unknown status: {$status}");

                break;
        }
    }
}
