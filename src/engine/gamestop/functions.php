<?php

// refs #2043, gamestop

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGamestop extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.gamestop.com/account/';

    private $sensorDataUrl;

    private $userData;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->attempt == 2) {
            $this->http->SetProxy($this->proxyAustralia());
        } else {
            $this->setProxyBrightData();
        }

        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Valid email required", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.gamestop.com/');

        if (
            $this->http->Response['code'] == 403
            || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream') !== false
        ) {
            $this->markProxyAsInvalid();
            $this->setProxyBrightData(true);
            $this->http->removeCookies();
            $this->http->GetURL('https://www.gamestop.com/');

            if (
                $this->http->Response['code'] == 403
                || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream') !== false
            ) {
                $this->markProxyAsInvalid();
                $this->setProxyGoProxies();
                $this->http->removeCookies();
                $this->http->GetURL('https://www.gamestop.com/');
            }
        }

        if ($this->http->Response['code'] !== 200) {
            if (
                $this->http->Response['code'] == 403
                || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream') !== false
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $this->selenium();

        return true;

        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);
        $this->sensorDataUrl = $sensorDataUrl;

        $this->http->GetURL('https://www.gamestop.com/login/');
        /*
        $this->http->GetURL('https://www.gamestop.com/on/demandware.store/Sites-gamestop-us-Site/default/Account-Header', ["X-Requested-With" => "XMLHttpRequest"]);
        */

        $csrf_token = $this->http->FindPreg('/name="csrf_token"[\n\s]+?value="(.+?)"/');

        if (!$csrf_token) {
            $this->logger->error("csrf_token not found");

            return false;
        }

        $retry = false;
        $key = $this->sendSensorData($this->sensorDataUrl);

        $data = [
            "loginEmail"      => $this->AccountFields['Login'],
            "loginPassword"   => $this->AccountFields['Pass'],
            "csrf_token"      => $csrf_token,
            "loginRememberMe" => true,
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, * /*; q=0.01",
            "ADRUM"            => "isAjax:true",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Referer"          => "https://www.gamestop.com/",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.gamestop.com/on/demandware.store/Sites-gamestop-us-Site/default/Account-Login', $data, $headers);
        $this->http->RetryCount = 2;

        /*
        if ($this->http->Response['code'] == 403) {
            sleep(5);
            $this->sendStatistic(false, $retry, $key);
            $retry = true;

            $key = $this->sendSensorData($this->sensorDataUrl);

            $this->http->PostURL('https://www.gamestop.com/on/demandware.store/Sites-gamestop-us-Site/default/Account-Login', $data, $headers);

            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = 'need to upd sensor_data';

                if (in_array($this->AccountFields['Login'], [
                    'nayrma@gmail.com',
                    'mason.lee.hood@gmail.com'
                ])) {
                    throw new CheckException('Invalid login credentials. Remember that password is case-sensitive. Please check the login email or password and try again.', ACCOUNT_INVALID_PASSWORD);
                }

                throw new CheckRetryNeededException();

                return false;
            }
        }
        */

        $this->sendStatistic(true, $retry, $key);

        return true;
    }

    public function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            // null, - not working
            // 0
            "7a74G7m23Vrp0o5c9282841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,401325,7968161,1536,871,1536,960,1536,473,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8978,0.374374689187,815543984080,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1631087968160,-999999,17448,0,0,2908,0,0,4,0,0,CE79CBA81F8314E8C9D342A854363196~-1~YAAQxmAZuOEvJsR7AQAAVT5rxAZfFwfdHNITW09yGzGmh5+CIGieUjY4nsD8az7D885u1cI8ZZXiKl2iXvQxz/Njq4CPFyfEs0lsKHW0qmhDyomJdztz+LDQaL66ZBZ1KX1JgDvs8Rc7XmREJIocS0ddRDUVPFLjU8lkR6TX9GQoymnwpxLQEU4WMcBs1rKnRAaqcSZYr1Y6Qg/5zabed/eUSfxS1VfpfiTF0VT+029aM7lvK4YhuVHs5Hx5BZhaDwkZxsijFzTC6m/tg32tjLJnI/hC6XppN3b/D0TAVtn5e/SF7xQ36VQoOLqFr5buoYveQ8BoGhLk3ISwZ4lv0HfCiXH2SMF9gWzjSfZwHW47a7vvFprDn6vkvV5BthznkMpmXaeP26tu+HLM~-1~-1~1631091549,37510,-1,-1,30261693,PiZtE,74597,63-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,215140245-1,2,-94,-118,96436-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9282951.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,401325,8038954,1536,871,1536,960,1536,473,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8978,0.435175105217,815544019476.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1631088038953,-999999,17448,0,0,2908,0,0,3,0,0,CE79CBA81F8314E8C9D342A854363196~-1~YAAQlgrGF+CqIb57AQAAEglsxAZCnZFs+Abdlq2k6LdKtoZDCzDixLrDtr5tvKBjLQLLykzSPI4Aw0tgXx0rB43cTcGFi90XoM52Ip7eKCoznnaAK1mPf1mmckRwAbuRFCpCWMqYiDKxXFNuQvzyFc1uun3gdWkYQ08veWsMvIwaU5P+YRa1ihiEFU2xaYJZwomN9Az12KYN9WKvm5+zrmG/GI3GFVZKdsx3k+AqPxkh9Wx74jttAl5xXJbq4zmgrQuzXHo5bR0HruLY1kwGJFoQ2lVQW7y0YMEW9fBG8BHznkybqJt3Lnic64ewf7OlYLlBLfEQoEXH/2RBtFWDfn1eWFg2YnrcdQSCdBz7YdoALQ/wN13kg0JtcXOkIF4GEa9uefc2d9mJiXiqJAQ3+38hUgmOSMdHCr+BHvT7alGqA62h9J2v~-1~-1~1631091614,40626,-1,-1,30261693,PiZtE,88118,83-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1085259210-1,2,-94,-118,99586-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9282841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401325,7683113,1536,871,1536,960,1536,450,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6004,0.401067852200,815543841556.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1631087683113,-999999,17448,0,0,2908,0,0,3,0,0,7D6391FB566ED621BF633A3BC19AEA2D~-1~YAAQxmAZuI2rJcR7AQAAFNRmxAZ/5w+09+cS4GO/wBSaDea0Z0X8Ly1RfwD9NaNiCkxKhJaaLXgD/EGccGeeuTby9jyfCZBW0hH6p0EVxz/7CXMm2/UXan/SefjE2qiL2G2LSOp+fX8qIuAEx322zB/UwTQ6zZwxYrxRGujmSFFcD2+aeEHb7PhLL3mXCLefaOd+EjCkEmCigSbKIzoP5LjQ7V1Z/Ky3KsmBG1ZQnUx71du4V31YQuytIMAkj8MN912/AgW8EFRhhM3bDogyu3YQyGDxrw4KgIB4srComZRIpPyQhCeg5G4pMs9p1JM7t/GprcszG9Ookz8XKcuJXlCVGP1/OORhml6fBCKq8rVQYWWN4yjdq9K0sU9WgrUHekJw4aqwwvJvQyDMzw==~-1~-1~1631091258,37381,-1,-1,26067385,PiZtE,99306,96-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,207443943-1,2,-94,-118,93542-1,2,-94,-129,-1,2,-94,-121,;10;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9282841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401325,7915972,1536,871,1536,960,1536,450,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6004,0.229557928114,815543957986,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1631087915972,-999999,17448,0,0,2908,0,0,3,0,0,DE1D6828D7B5F7103FCF44A253E38D94~-1~YAAQlgrGF8WoIb57AQAAGnFqxAaDgtC2RLtA5MyW/CfBphkw7l2wfRvAsoC3EzcXGl1UXA5zG3oenRGTmWUCIqMEo4R0wL7aYsjiG15AaZdSPgEcR3cK/IARtDqmciYiKoyGK6HNfSSVFbf8JbBs0fh7Zh+pJhvoBaITtxehJKY2++JDn5LOSQE5Q494rQFVcDZ2G4keLDc6KgbqvxPhqS5+/GMw6TBhbEHl9MNnn3bSjMg3ZVTXZduXl0nLbHPD3RFnJBGG3I2VRSDxsc3bbHINof94s42XttHWvHFCV2K6bvABggkpcfmVKCx2TFLFw8OUY0vMwZVlOc6PznE05a6ONMNRZ0IoQ4D2w/nq8xH55sYGMZJH1WxEHkNjoJe7QoH0+QZ33nfps+EAeA==~-1~-1~1631091398,36862,-1,-1,26067385,PiZtE,99056,69-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,23747937-1,2,-94,-118,93004-1,2,-94,-129,-1,2,-94,-121,;8;-1;0",
        ];

        $secondSensorData = [
            // null,
            // 0
            "7a74G7m23Vrp0o5c9282951.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,401325,7968161,1536,871,1536,960,1536,473,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8978,0.730386356365,815543984080,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,2,1,0,1583,1025,0;1,2,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,0,1,46930,91,0,2,1025;1,1,47148,67,0,2,1025;2,2,47366,-2,0,0,1025;3,1,48135,91,0,2,1025;4,1,48271,86,0,2,1025;5,2,48580,-2,0,0,1025;-1,2,-94,-110,0,1,44246,534,300;1,1,44471,535,300;2,1,44477,536,302;3,1,44485,537,303;4,1,44493,539,306;5,1,44501,540,307;6,1,44508,542,309;7,1,44516,544,313;8,1,44524,546,316;9,1,44532,547,319;10,1,44540,549,322;11,1,44548,550,326;12,1,44559,552,330;13,1,44565,553,334;14,1,44572,554,339;15,1,44580,555,344;16,1,44589,556,350;17,1,44598,556,356;18,1,44607,556,362;19,1,44613,556,367;20,1,44621,556,374;21,1,44630,556,380;22,1,44636,555,386;23,1,44644,553,393;24,1,44652,551,399;25,1,44662,549,406;26,1,44669,547,413;27,1,44678,546,420;28,1,44685,544,426;29,1,44694,542,433;30,1,44702,542,439;31,1,44710,541,444;32,1,44718,541,448;33,1,44725,541,451;34,1,44910,539,452;35,1,44917,535,456;36,1,44926,530,460;37,1,44933,523,465;38,1,44942,515,470;39,1,46191,435,460;40,1,46191,435,460;41,1,46198,462,441;42,1,46206,486,424;43,1,46214,498,417;44,1,46222,519,403;45,1,46230,539,391;46,1,46239,558,379;47,1,46246,573,369;48,1,46254,579,365;49,1,46263,589,356;50,1,46272,598,350;51,1,46279,604,343;52,1,46287,610,338;53,1,46294,614,332;54,1,46304,618,328;55,1,46310,620,324;56,1,46318,622,320;57,1,46327,624,315;58,1,46335,625,312;59,1,46343,626,307;60,1,46350,627,303;61,1,46360,627,299;62,1,46369,628,295;63,1,46376,628,291;64,1,46384,629,288;65,1,46392,629,284;66,1,46400,630,280;67,1,46409,631,277;68,1,46415,631,273;69,1,46423,632,270;70,1,46432,632,268;71,1,46440,632,265;72,1,46447,633,262;73,1,46455,633,261;74,1,46465,633,259;75,1,46471,633,257;76,1,46479,633,256;77,1,46488,633,255;78,1,46496,633,255;79,1,46504,633,254;80,1,46511,633,254;81,1,46520,633,254;82,1,46528,633,254;83,1,46534,633,253;84,1,46542,633,252;85,1,46551,633,251;86,1,46561,633,251;87,1,46568,633,249;88,1,46576,633,247;89,1,46584,634,245;90,1,46591,634,243;91,1,46602,635,240;92,1,46607,636,237;93,1,46615,637,235;94,1,46624,638,232;95,1,46631,639,229;96,1,46639,641,225;97,1,46649,641,222;98,1,46656,643,219;99,1,46664,644,216;110,3,46759,649,204,1025;112,4,46871,649,203,1025;113,2,46871,649,203,1025;161,3,51360,600,261,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,0,50461,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,359;2,361;2,361;2,362;2,362;2,362;2,362;2,362;2,362;2,362;2,362;2,363;2,363;0,13047;1,43657;3,46757;3,46758;0,49266;2,49486;1,50437;3,50999;-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,292943,4864308,32,0,50461,0,5207679,51360,0,1631087968160,28,17448,6,162,2908,3,0,51361,5101161,0,CE79CBA81F8314E8C9D342A854363196~-1~YAAQlgrGF9KqIb57AQAAYfhrxAamLs2k5sGNmNF9mzRcneJ1mFRlktkdbAciCUt+1J+7CR6v4TPLCqP9LDtixIL9JArWOTMPHBATpQDTli1qe69lCJeYnQEkkTFiGsFeSCst0pFpJduVyy+Zj9fHEL9HTlU5BwgZq65ys7iwUaDDoaw62b0uqyc4XxtjTyDOjL5ucD03FizKmIXCCydm8Isy3JjUwKfhx8jgakjjZVjnOj7/JevZB3i5mUy7x1rOIgQMj6fLgo4Q0vuyWgV+2f45FqYdwwv08+6xdg5ytaiVVGsLgczo4KXZjYypF+QDxA+wyevEK056Y+cdAQ7g0ZMzi76JCOaDVwZn+FPpBi5f1T82dQVQnBXEkt8dT4CgaAMpJ0mn1FB9EjxEibtiYyIdXuJLFvbcMyWYhuWmjIsyiA57neH6~-1~-1~1631091577,40945,517,1001297395,30261693,PiZtE,32360,43-1,2,-94,-106,1,3-1,2,-94,-119,0,20,20,20,20,20,20,20,20,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,215140245-1,2,-94,-118,224310-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;5;8;0",
            // 1
            "7a74G7m23Vrp0o5c9282951.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,401325,8038954,1536,871,1536,960,1536,473,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8978,0.784196950392,815544019476.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,2,1,0,1583,1025,0;1,2,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,0,1,22897,-2,0,0,1025;1,3,22897,-2,0,0,1025;2,1,22943,-2,0,0,1025;3,3,22944,-2,0,0,1025;4,2,23101,-2,0,0,1025;5,2,23131,-2,0,0,1025;6,1,23231,-2,0,0,1025;7,3,23231,-2,0,0,1025;8,2,23374,-2,0,0,1025;-1,2,-94,-110,0,1,561,462,224;1,1,566,463,225;2,1,573,466,227;3,1,581,468,229;4,1,589,471,231;5,1,597,474,233;6,1,606,477,236;7,1,613,481,237;8,1,631,488,241;9,1,638,491,242;10,1,645,495,243;11,1,653,499,245;12,1,661,503,246;13,1,670,507,248;14,1,677,513,251;15,1,687,518,253;16,1,693,523,256;17,1,703,530,260;18,1,709,534,263;19,1,724,540,265;20,1,726,542,266;21,1,734,548,270;22,1,1160,546,275;23,1,1167,542,286;24,1,1175,537,297;25,1,1182,533,308;26,1,1190,527,320;27,1,1199,520,332;28,1,1206,514,344;29,1,1214,509,356;30,1,1224,497,382;31,1,1247,470,439;32,1,1255,460,459;33,1,21275,363,460;34,1,21275,363,460;35,1,21282,378,448;36,1,21290,394,437;37,1,21298,407,427;38,1,21307,419,419;39,1,21314,424,416;40,1,21322,435,411;41,1,21330,445,407;42,1,21339,453,404;43,1,21346,461,402;44,1,21354,466,402;45,1,21362,471,402;46,1,21384,476,402;47,1,21390,478,405;48,1,21394,480,409;49,1,21688,478,400;50,1,21694,475,390;51,1,21702,474,382;52,1,21710,473,375;53,1,21718,473,369;54,1,21726,473,364;55,1,21733,474,360;56,1,21743,477,354;57,1,21750,481,350;58,1,21757,486,340;59,1,21766,493,332;60,1,21774,499,325;61,1,21781,506,316;62,1,21789,512,307;63,1,21798,518,300;64,1,21805,521,295;65,1,21814,526,288;66,1,21821,532,280;67,1,21829,537,274;68,1,21837,541,268;69,1,21845,546,263;70,1,21855,551,259;71,1,21862,555,255;72,1,21871,559,251;73,1,21878,562,249;74,1,21886,566,246;75,1,21894,571,244;76,1,21904,574,241;77,1,21910,578,240;78,1,21918,580,238;79,1,21926,584,235;80,1,21935,587,233;81,1,21943,588,232;82,1,21951,590,230;83,1,21959,593,227;84,1,21966,594,226;85,1,21975,597,223;86,1,21982,598,222;87,1,21990,599,221;88,1,21998,601,220;89,1,22006,602,218;90,1,22015,604,217;91,1,22022,605,216;92,1,22031,606,216;93,1,22038,608,214;94,1,22046,609,213;95,1,22054,609,213;96,1,22062,610,212;97,1,22069,611,212;98,1,22078,611,211;99,1,22086,611,211;108,3,22164,612,208,1025;110,4,22317,612,208,1025;111,2,22323,612,208,1025;239,3,23844,630,279,1388;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,0,19510,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,247;2,249;2,249;2,249;2,249;2,249;2,249;2,249;2,249;2,250;2,250;2,250;2,250;0,5787;1,19487;3,22163;-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,217011,1666033,32,0,19510,0,1902521,23844,0,1631088038953,15,17448,9,240,2908,3,0,23845,1802815,0,CE79CBA81F8314E8C9D342A854363196~-1~YAAQ5h/JF9AbYrZ7AQAA2qxsxAbHdQlg3AreofWkZ360T0JO3cGi+629ZpxCUtZcDyENc2kuw8gyPFQ+78Y/If6iCnR71bEiLUZ6uqq/DJkwg7Jsg+N2E40jC+uZhdLjwekOjpZZNIcmuepY6TzM/6pcxsSJqmmFEOoXQ4dSks3wpDLvN++YEI/wc4T0p3BK69h1sZIkCVBs/7tQ4knL4w5fyQ6q/aCTwcwOZC3789TGfPIb15Ee78h/ijs+bTaW6VJ61Cl231sojrQVItc8G7uMeusMy6mrDbXlz5a6uHgnST6WnUBaYjFcZDPFFLZ/50lZLX1aP5CBiy7hp9y1qzEhJiodpGqcEdLoq48DN4q2y2S2HGSoBV+bYDmjaJq/fQ4TaVfBBqh4f18QNy/WX0zyOb+JrF7qZD5O5/YGBa8+PfDYuHdo~-1~-1~1631091595,39539,269,-313172954,30261693,PiZtE,86329,71-1,2,-94,-106,1,3-1,2,-94,-119,0,20,0,20,20,20,20,0,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,1085259210-1,2,-94,-118,220624-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;5;7;0",
            // 2
            "7a74G7m23Vrp0o5c9282841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401325,7683113,1536,871,1536,960,1536,450,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6004,0.486488059243,815543841556.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,2,1,0,1583,1025,0;1,2,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,0,1,82784,16,0,8,-1;1,1,82785,17,0,12,-1;2,2,91065,9,0,4,-1;3,2,91108,17,0,0,-1;4,1,91672,224,0,2,1025;5,1,91763,86,0,2,1025;6,2,91892,-2,0,0,1025;7,1,92228,17,0,4,1025;8,1,92229,16,0,12,1025;9,2,94141,9,0,4,1025;10,2,94178,17,0,0,1025;-1,2,-94,-110,0,1,22552,628,269;1,1,22560,627,271;2,1,22574,626,273;3,1,22590,623,277;4,1,22611,614,287;5,1,22629,591,308;6,1,22647,571,326;7,1,22664,547,346;8,1,22679,520,368;9,1,22695,488,392;10,1,22712,459,411;11,1,22728,431,429;12,1,22745,413,439;13,1,22754,403,444;14,1,26541,962,430;15,1,26549,960,420;16,1,26562,956,401;17,1,26578,953,377;18,1,26595,949,356;19,1,26611,949,347;20,1,26627,948,340;21,1,26644,948,337;22,1,26654,948,337;23,1,26660,948,336;24,1,39120,622,179;25,1,39130,622,177;26,1,39147,623,172;27,1,39165,627,158;28,1,39180,631,140;29,1,39198,636,103;30,1,39215,641,61;31,1,39231,646,28;32,1,39820,548,7;33,1,39838,524,28;34,1,39854,500,45;35,1,39871,484,56;36,1,39888,466,66;37,1,39906,454,72;38,1,39919,451,74;39,1,39936,448,76;40,1,39952,448,77;41,1,40194,447,77;42,1,40203,445,77;43,1,40219,442,79;44,1,40237,437,82;45,1,40277,398,106;46,1,40286,398,106;47,1,40287,388,111;48,1,82422,522,11;49,1,82439,529,61;50,1,82457,534,113;51,1,82474,536,148;52,1,82491,537,191;53,1,82507,538,232;54,1,82524,539,269;55,1,82541,539,314;56,1,82557,536,338;57,1,82929,536,333;58,1,82942,536,331;59,1,82952,532,321;60,1,91013,232,105;61,1,91029,240,105;62,1,91046,246,105;63,1,91063,296,112;64,1,91079,368,137;65,1,91097,424,163;66,1,91110,453,177;67,1,91114,480,193;68,1,91130,516,216;69,1,91148,554,244;70,1,91164,571,261;71,1,91180,587,284;72,1,91394,588,283;73,1,91398,590,281;74,1,91414,591,279;75,1,91429,597,272;76,1,91446,604,263;77,1,91464,612,253;78,1,91480,622,237;79,1,91496,628,227;80,1,91513,633,218;81,1,91530,635,213;82,1,91547,639,207;83,1,91563,641,204;84,1,91579,642,202;85,1,91597,643,202;86,1,91613,643,202;87,3,91615,643,202,1025;88,1,91629,643,201;89,4,91725,643,201,1025;90,2,91725,643,201,1025;91,1,91967,642,202;92,1,91976,639,203;93,1,91985,635,204;94,1,92001,626,208;95,1,92020,609,214;96,1,92036,578,220;97,1,92054,539,225;98,1,92070,481,226;99,1,92085,450,226;100,1,92102,410,222;101,1,92119,387,219;102,1,92135,372,216;132,3,94697,691,265,1388;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,65;2,65;2,65;2,65;2,65;2,65;2,65;2,65;2,65;2,65;2,66;2,66;2,68;2,68;2,69;2,69;2,69;2,69;2,70;2,70;2,70;2,70;2,70;2,70;2,1342;0,40550;3,81992;1,81993;2,82935;0,83200;1,90965;3,90984;2,92339;0,92605;1,94052;3,94073;-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,1003562,6647923,32,0,0,0,7651452,94697,0,1631087683113,7,17448,11,133,2908,3,0,94697,7555891,0,7D6391FB566ED621BF633A3BC19AEA2D~-1~YAAQxmAZuIvXJcR7AQAAG05oxAZM1bf2hFT6df9rtTfXvmz9wZqv00ud5GHB9KjicLHawoPK80tdeEGjcTIkfIech9Jk1h0R+zFs4fhygw8ON+of4A4uUlVXvo2b0GZFeaXFfUy60GrGUeorZTxQAyh9nYBy4t9co1Qi0drwIxfEnPRLEk2sH7NYqbWHwSj3i1IefYF32ZudfPivWgJYBtFbFvm6bCHz/XAl8DtwTeeIW2dqMQCmeJZBZjT++TlAYnGUx3yWRUq8WHH+RwjJ2TkSHtQmCQofQE+t1VYnH/+JdB0mUd9Wx6wmeQTKoyiEHyXK760hmH8aEUVNJeiR2GpV/U6iaCOVVoXmBi7/4UDVHElbXjl79YlAL0Qp7ns9jF9uuWT9ZvttWNkjVEGf0phVdSAlL/3jv5f2XJI/OSJim9mZ3EIcfA==~-1~-1~1631091292,40777,481,-1728970723,26067385,PiZtE,69108,81-1,2,-94,-106,1,3-1,2,-94,-119,200,200,0,0,200,0,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,207443943-1,2,-94,-118,224428-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;5;11;0",
            // 3
            "7a74G7m23Vrp0o5c9282841.69-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,401325,7915972,1536,871,1536,960,1536,450,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6004,0.298913741149,815543957986,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1583,1025,0;1,-1,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-102,0,2,1,0,1583,1025,0;1,2,0,0,1946,1388,0;0,-1,0,0,2040,1025,0;0,-1,0,1,2015,3433,0;0,-1,0,1,2021,3317,0;0,-1,0,1,2034,3002,0;-1,2,-94,-108,0,2,21998,9,0,4,-1;1,2,22007,17,0,0,-1;2,1,22912,224,0,2,1025;3,1,22996,86,0,2,1025;4,2,23089,-2,0,0,1025;5,1,23418,16,0,8,1025;6,1,23425,17,0,12,1025;7,2,24544,17,0,0,1025;8,2,24563,9,0,0,1025;-1,2,-94,-110,0,1,43,422,527;1,1,49,422,538;2,1,63,422,577;3,1,86,422,577;4,1,18738,718,563;5,1,18746,715,563;6,1,18758,710,565;7,1,18777,691,573;8,1,18792,672,578;9,1,18809,649,585;10,1,18825,621,589;11,1,18841,595,592;12,1,18858,563,593;13,1,18875,552,593;14,1,18891,524,587;15,1,18907,515,585;16,1,18924,501,583;17,1,18941,494,583;18,1,18959,488,585;19,1,18975,482,590;20,1,18991,475,597;21,1,19008,466,605;22,1,19024,458,612;23,1,19041,451,621;24,1,19488,496,604;25,1,19496,513,583;26,1,19515,552,535;27,1,19529,577,504;28,1,19548,622,458;29,1,19564,635,446;30,1,19582,643,439;31,1,19597,648,434;32,1,19613,649,433;33,1,19925,649,432;34,1,19947,648,421;35,1,19965,648,412;36,1,19982,648,398;37,1,19999,648,379;38,1,20015,648,358;39,1,20031,650,335;40,1,20048,652,319;41,1,20066,655,299;42,1,20081,659,283;43,1,20103,667,268;44,1,20122,671,266;45,1,20261,671,266;46,1,20283,668,262;47,1,20300,664,256;48,1,20316,658,252;49,1,20333,649,247;50,1,20352,634,242;51,1,20367,622,240;52,1,20384,605,238;53,1,20395,597,237;54,1,20400,589,237;55,1,20417,575,237;56,1,20435,568,237;57,1,20450,560,238;58,1,20467,559,241;59,1,21945,233,281;60,1,21962,242,279;61,1,21981,249,278;62,1,21999,328,271;63,1,22015,384,271;64,1,22031,452,271;65,1,22049,519,276;66,1,22067,565,283;67,1,22081,641,298;68,1,22100,671,307;69,1,22115,695,315;70,1,22134,716,324;71,1,22148,723,328;72,1,22329,723,326;73,1,22334,723,324;74,1,22349,723,321;75,1,22367,724,315;76,1,22382,725,306;77,1,22399,727,294;78,1,22414,728,268;79,1,22431,728,253;80,1,22448,728,245;81,1,22465,728,237;82,1,22482,728,234;83,1,22498,727,232;84,1,22515,726,231;85,1,22531,724,230;86,1,22549,722,229;87,1,22566,720,228;88,1,22581,718,227;89,1,22598,717,226;90,1,22615,716,226;91,1,22632,715,224;92,1,22648,714,223;93,1,22664,713,221;94,1,22682,712,219;95,1,22698,712,218;96,1,22715,711,217;97,1,22732,711,216;98,3,22742,711,216,1025;99,1,22752,711,216;100,4,22875,711,216,1025;101,2,22876,711,216,1025;102,1,22937,711,218;149,3,25168,698,283,1388;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,74;2,77;2,77;2,77;2,77;2,77;2,77;2,78;2,78;2,78;2,78;2,78;2,78;0,20780;1,21889;3,21914;2,23516;0,23784;1,24452;3,24470;-1,2,-94,-112,https://www.gamestop.com/login/-1,2,-94,-115,216597,2198317,32,0,0,0,2414881,25168,0,1631087915972,4,17448,9,150,2908,3,0,25169,2299850,0,DE1D6828D7B5F7103FCF44A253E38D94~-1~YAAQlgrGFyupIb57AQAAaM1qxAbFW4j7Fy8KNwTX60NjwraRCAD81CEMmuPQV/1MLYj9W9LHt9kzAbpODsFIH5qiGMxOZnOlyytFTFr49hQtgVPgJil5IRU91PPU+XZBZTPpyctbA85C+X8kJ2VaGZu6GoH6hKSZa817qiZmZskHlI7PErQ1z/QMUPPOIZb9Ia1ldjxA/tKiwuCTwT8nwmiwrnrP0CkL6DqgRF4A0zLakbNYlA4WviKny53WjyxFaaMOcu8X+ELWMhf/aH8dGUpNKGNHdiSmq66+m421nCk36Objf4TIwb2X1hyeUWRAk0l3Y4Or6zFdLVE4GJT7JTDsToo2saee6ExFDo2VRkzFbQJ/CocJLkU9Tg1JZJM7xk5tMLroqCfTk0LkAI9uKBQhiPVISzCf9aZO0x219IND6asNE5MEPw==~-1~-1~1631091438,40161,54,-1830677770,26067385,PiZtE,47545,90-1,2,-94,-106,1,3-1,2,-94,-119,200,0,200,0,0,200,0,200,0,0,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,23747937-1,2,-94,-118,219692-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;6;11;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // GameStop.com will be back soon. Thank you for your patience.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'GameStop.com will be back soon.')]
                | //p[contains(text(), 'GameStop.com is temporarily unavailable while we undergo scheduled maintenance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $loginStatus = $response->gtmSessionData->user->loginStatus ?? null;

        if ($loginStatus === 'authenticated') {
            $this->userData = $response->gtmSessionData->user;
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return $this->loginSuccessful();
        }

        $message = $response->error[0] ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                in_array($message, [
                    "Invalid login credentials. Remember that password is case-sensitive. Please check the login email or password and try again.",
                    "Your email or password was incorrect. Please try again.",
                ])
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'We made some upgrades to the GameStop.com site to help you enjoy your experience more than ever and as part of this improvement, for safety and security reasons, we need to reset your password.')
            ) {
                throw new CheckException("We made some upgrades to the GameStop.com site to help you enjoy your experience more than ever and as part of this improvement, for safety and security reasons, we need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($message)

        // AccountID: 5950432
        if (isset($response->redirectUrl) && $response->redirectUrl == 'https://www.gamestop.com/create-account/?enableStoreAccount=01') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // For technical reasons, your request could not be handled properly at this time. We apologize for any inconvenience.
        if ($message = $this->http->FindSingleNode("//p[normalize-space() = 'For technical reasons, your request could not be handled properly at this time. We apologize for any inconvenience.']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//header[contains(@class, "my-account-header--") and @data-points]/@data-points'));
        // Member ID
        $this->SetProperty("PowerUpRewardsId", $this->http->FindSingleNode('//span[normalize-space() = "Member ID"]/following-sibling::p'));
        // Lifetime Points (not visible)
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode('//header[contains(@class, "my-account-header--") and @data-lifetimepoints]/@data-lifetimepoints'));
        // GameStop Pro Member
        $this->SetProperty("Membership",
            $this->userData->memberType
            ?? $this->http->FindSingleNode('//div[@class="dash-user__info"]/p', null, true, '/GameStop (\w+) Member/'));
        // Expires
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//span[normalize-space() = "Expires"]/following-sibling::p'));

        $this->http->GetURL('https://www.gamestop.com/profile/');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('#<div class="field-label">Name</div>\s+<div class="field-value">([\w ]+)</div>#')));
        // Member Since (not visible)
        $this->SetProperty('MemberSince', $this->userData->memberSinceDate ?? null);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                $this->http->FindSingleNode('//div[contains(text(), "Begin your journey & reap the rewards")]')
                || $this->http->FindPreg("/:\{\"userType\":\"customer\",\"custKey\":\"\d+\",\"memberType\":\"Non Member\"\}/")
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->logger->info("Offers", ['Header' => 3]);
        $this->http->GetURL('https://www.gamestop.com/active-offers/');
        $offers = $this->http->XPath->query('//div[contains(@class, "-tile-container")]');
        $this->logger->debug("Total {$offers->length} offers were found");

        foreach ($offers as $offer) {
            $displayName = $this->http->FindSingleNode('.//div[contains(@class, "-title")]', $offer, true, "/\*?(.+)/");
            $exp = $this->http->FindPreg('#-Expires (\d{1,2}/\d{1,2}/\d{4})#', false, $displayName)
                ?? $this->http->FindSingleNode('.//span[@class = "expiry-date"]', $offer);
            $displayName = preg_replace('#-Expires \d{1,2}/\d{1,2}/\d{4}#', '', $displayName);
            $code = $this->http->FindSingleNode('.//input[@data-code]/@data-code', $offer);
            $orderID = $this->http->FindSingleNode('.//a[@data-order-id]/@data-order-id', $offer);

            if ($displayName && ($code ?? $orderID)) {
                $subacc = [
                    'Code'           => 'gamestopOffer' . ($code ?? $orderID),
                    'DisplayName'    => isset($code) ? "$displayName #$code" : $displayName,
                    'Balance'        => null,
                    'OfferCode'      => $code,
                ];

                if ($exp) {
                    $subacc['ExpirationDate'] = strtotime($exp);
                }
                $this->AddSubAccount($subacc);
            }// if ($displayName && $code && $exp)
        }// foreach ($offers as $offer)

        // Expiration Date  // refs #12157
        if ($this->Balance <= 0) {
            return;
        }
        $this->http->GetURL("https://www.gamestop.com/card-activity/");
        $this->logger->info("Expiration Date", ['Header' => 3]);
        $transactions = $this->http->XPath->query('//table[contains(@class, "activity-table")]//tr[td]');
        $this->logger->debug("Total {$transactions->length} transactions were found");

        foreach ($transactions as $transaction) {
            $date = $this->http->FindSingleNode('td[3]', $transaction);
            $activity = $this->http->FindSingleNode('td[2]', $transaction);

            if (
                !strstr($activity, 'Sales Transaction')
                && !strstr($activity, 'Member Renewed')
                && !strstr($activity, 'Member Upgraded')
            ) {
                $this->logger->debug("[Skip]: {$date} - {$activity}");

                continue;
            }

            // Last Activity
            $this->SetProperty("LastActivity", $date);

            if ($exp = strtotime($date)) {
                $this->SetExpirationDate(strtotime("+12 months", $exp));
            }

            break;
        }// foreach ($transactions as $transaction)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//div[contains(@class, "membership-number")]')
            || $this->http->FindNodes('//a[@data-action="logout"]')
            || $this->http->FindSingleNode('
                //div[contains(text(), "Begin your journey & reap the rewards")]
                | //span[contains(text(), "Unlock your next achievement with personalized offers – don’t miss out!")]
            ')
        ) {
            return true;
        }

        return false;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("gamestop sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();

            $resolutions = [
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            /*
            $selenium->setScreenResolution($resolutions[array_rand($resolutions)]);

            $selenium->useGoogleChrome();
            $selenium->disableImages();

            $selenium->http->setRandomUserAgent(5);
            */

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            /*
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumRequest->setOs("mac");
            $selenium->http->setUserAgent(null);
            */

//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.gamestop.com");
            $selenium->http->GetURL("https://www.gamestop.com/account/");
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'login-form-email']"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'login-form-password']"), 3);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'sign-in-submit')]"), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("set Login");
            $login->sendKeys($this->AccountFields['Login']);
            $this->logger->debug("click pass");
            $pass->click();
            $this->logger->debug("set pass");
            $pass->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/default\/Account-Login/g.exec(url)) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
            sleep(1);

            $this->logger->debug("click btn");
            $btn->click();

            $resultXpath = "
                //a[contains(@class, 'logout-anchor')]
                | //div[contains(@class, 'membership-number')]
                | //div[contains(text(), 'CURRENT POINTS')]
                | //p[contains(text(), 'Account Setting')]
            ";
            $this->logger->debug("wait result");
            $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 35, false);
            $this->saveToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'login-form-email']"), 0)) {
                $this->saveToLogs($selenium);

                try {
                    $btn->click();
                    sleep(5);
                } catch (UnrecognizedExceptionException $e) {
                    $this->logger->error("UnrecognizedExceptionException exception: " . $e->getMessage());
                    sleep(5);
                    $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'login-form-email']"), 0);
                    $this->saveToLogs($selenium);
                }

                if ($selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                    $selenium->waitFor(function () use ($selenium) {
                        $this->saveToLogs($selenium);

                        return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                    }, 120);

                    $btn->click();
                    sleep(5);
                }

                $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 2, false);
                $this->saveToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->saveToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);
            }
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo
        }

        return $result;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
