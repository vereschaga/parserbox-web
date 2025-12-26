<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerUsbank extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const MACHINE_ATTRIBUTE = 'colorDepth=24|width=1440|height=900|availWidth=1440|availHeight=830|platform=MacIntel|javaEnabled=No|userAgent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36';
    public const QUESTION_TSC = "Enter Your Temporary Access Code";
    public const QUESTION_OTP = "Please enter the code we sent to you. It will expire in 15 minutes.";

    private $data = null;
    private $key = null;

    private $accesstoken = null;
    private $aftokenvalue = null;
    private $baseURL = null;

    private $headers = [
        "Accept"              => "*/*",
        "Content-Type"        => "application/json",
        "X-TS-Client-Version" => "4.2.0;[1,2,3,6,7,8,10,11,12,14]",
        "Referer"             => "https://www.usbank.com/index.html",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerUsbankSelenium.php";

        return new TAccountCheckerUsbankSelenium();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && strtolower($properties['Currency']) == 'cash') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['DashboardURL'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($this->State['DashboardURL'], [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        if (
            isset($this->State['accesstoken'], $this->State['aftokenvalue'])
            && $this->redirectToDashboard($this->State['accesstoken'], $this->State['aftokenvalue'])
        ) {
            $this->accesstoken = $this->State['accesstoken'];
            $this->aftokenvalue = $this->State['aftokenvalue'];

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice("start login");

        // AccountID: 5500889
        if (strlen($this->AccountFields['Login']) < 7) {
            throw new CheckException("Enter your username again. Your username must be between 7 and 22 characters with no spaces or special characters.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
//        $this->http->setDefaultHeader("User-Agent", 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:44.0) Gecko/20100101 Firefox/44.0 (AwardWallet Service. For questions please contact us at https://awardwallet.com/contact)');
        $this->http->GetURL("https://www.usbank.com/index.html");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $sensorDataUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($sensorDataUrl) {
            if ($this->attempt == 0) {
                $this->key = $this->sendSensorData("https://www.usbank.com{$sensorDataUrl}");
            } else {
                /*
                sleep(1);
                $this->http->RetryCount = 0;
                $headers = [
                    "Accept"        => "*
                /*",
                    "Content-type"  => "application/json",
                ];
                $data = [
                    'sensor_data' => ($this->attempt == 0) ? null : $this->getSensorDataFromSelenium(),
                ];
                $this->http->PostURL("https://www.usbank.com{$sensorDataUrl}", json_encode($data), $headers);
                $this->http->JsonLog();
                $this->http->RetryCount = 2;
                */
                $this->getSensorDataFromSelenium();
                sleep(1);
            }
        } else {
            $this->logger->error("sensor_data URL not found");
        }

//        if (!$this->http->ParseForm("userForm"))
//            return $this->checkErrors();
        $this->checkErrors();
        $this->http->FormURL = 'https://www.usbank.com/Auth/Login/LoginWidget';
        $this->http->SetInputValue("personalId", $this->AccountFields['Login']);
        $this->http->SetInputValue("userId", $this->AccountFields['Login']);
        $this->http->SetInputValue("RememberUserId", "true");
        $this->http->SetInputValue("ClientName", "Standalone");
        $this->http->SetInputValue("cancelurl", "/Auth/Login");
        sleep(1);

        return true;
    }

    public function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_usbank" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        if (!empty($data) && $this->attempt < 1) {
            return $data;
        }

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            /*
            switch (rand(0, 2)) {
                case 0:
                    $selenium->useFirefox();
                    $selenium->http->setRandomUserAgent(20, true, false, true, false);

                    break;

                case 1:
                    $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
                    $selenium->http->setRandomUserAgent(20, true, false, true, false);

                    break;

                case 2:
                    $selenium->useChromium();
                    $selenium->http->setRandomUserAgent(20, false, true, true, false);

                    break;
            }
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            */
            /*
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);

            if (!isset($this->State['Fingerprint'])) {
                $this->logger->notice("set new Fingerprint");

                $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([\AwardWallet\Common\Selenium\FingerprintRequest::chrome()]);

                if ($fp !== null) {
                    $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                    $this->State['Fingerprint'] = $fp->getFingerprint();
//                    $this->State['UserAgent'] = $fp->getUseragent();
                    $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
                }
            }
            */

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);

            if (!isset($this->State['Fingerprint'])) {
                $this->logger->notice("set new Fingerprint");

                $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([\AwardWallet\Common\Selenium\FingerprintRequest::firefox()]);

                if ($fp !== null) {
                    $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                    $this->State['Fingerprint'] = $fp->getFingerprint();
//                    $this->State['UserAgent'] = $fp->getUseragent();
                    $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
                }
            }

            if (isset($this->State['Fingerprint'])) {
                $this->logger->debug("set fingerprint");
                $selenium->seleniumOptions->fingerprint = $this->State['Fingerprint'];
            }

            $selenium->keepCookies(false);
//            $selenium->useCache();
            $selenium->disableImages();
            $selenium->http->removeCookies();
//            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->driver->manage()->window()->maximize();
                $selenium->http->GetURL("https://www.usbank.com/index.html");
            } catch (UnknownServerException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Username"]'), 10);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 0);
            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "login-button-continue"]'), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            /*
            if ($login) {
                $login->sendKeys($this->AccountFields['Login']);
                $this->saveToLogs($selenium);
            }

            if ($pass) {
                $pass->sendKeys($this->AccountFields['Pass']);
                $this->saveToLogs($selenium);
            }

            if ($loginBtn) {
                $loginBtn->click();

                $question = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "shield-input"]/label'), 10);

                if ($question && isset($this->Answers[$question->getText()])) {
                    $input = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "shield-input"]/input'), 0);
                    $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "shield-continue"]'), 0);

                    $input->sendKeys($this->Answers[$question->getText()]);
                    $this->saveToLogs($selenium);
                    $contBtn->click();

                    $selenium->waitForElement(WebDriverBy::xpath('//*[contains(text(), "Dashboard")]'), 7);
                    $this->saveToLogs($selenium);
                }

                $this->saveToLogs($selenium);
            }
            */

//            if ($login) {
            $this->logger->info("login form loaded");
            $selenium->driver->executeScript("(function(send) {
                        XMLHttpRequest.prototype.send = function(data) {
                          console.log('ajax');
                          console.log(data);
                          localStorage.setItem('sensor_data', data);
                        };
                    })(XMLHttpRequest.prototype.send);");
//                $login->click();
            sleep(1);
            $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
            $this->logger->info("got sensor data: " . $sensor_data);

            if (!empty($sensor_data)) {
                $data = @json_decode($sensor_data, true);

                if (is_array($data) && isset($data["sensor_data"])) {
                    $cache->set($cacheKey, $data["sensor_data"], 1000);

                    return $data["sensor_data"];
                }
            } else {
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
//            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->Data->Redirect, $response->Data->RedirectUrl) && $response->Data->Redirect == 'true') {
            $this->logger->debug("Redirect");
            $this->http->NormalizeURL($response->Data->RedirectUrl);
            $this->http->GetURL($response->Data->RedirectUrl);
        }

        if ($this->parseQuestion($response->Data ?? null)) {
            return false;
        }

        if (!$this->sendPassword($response)) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Account lockout
        if (
            (isset($response->Data->ErrorMessage, $response->Data->ViewName) && $response->Data->ViewName == 'LoginWidget')
            || isset($response->ErrorMessage)
        ) {
            $message = $response->Data->ErrorMessage ?? $response->ErrorMessage ?? null;

            if (
                strstr($message, 'For your security, your Personal ID has been locked.')
            ) {
                throw new CheckException("For your security, your Personal ID has been locked.", ACCOUNT_LOCKOUT);
            }

            switch ($message) {
                case "Hmm. We don’t recognize that ID. Please try again.":
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                    break;

                default:
                    // We're sorry; it looks like your personal ID has been disabled. Please contact 800-987-7237 for help.
                    if (strstr($message, "it looks like your personal ID has been disabled")) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                    $this->logger->error("[Unknown Error]: {$message}");

                    return false;

                    break;
            }// switch ($response->Data->ErrorMessage)
        }// if (isset($response->ErrorMessage, $response->ViewName) && $response->ViewName == 'LoginWidget')

        return $this->checkErrors();
    }

    public function getContextData($response)
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->data)) {
            return $this->data;
        }

        if (isset($response->TransmitURL) && $response->TransmitURL == '/Proxy/TS/api/v2/web/') {
            $this->logger->notice("Version 2");
            $data = "{\"headers\":[{\"type\":\"uid\",\"uid\":\"{$this->AccountFields['Login']}\"}],\"data\":{\"collection_result\":{\"metadata\":{\"timestamp\":" . date("UB") . "},\"content\":{\"device_details\":{\"logged_users\":1,\"device_id\":\"4ba6cd8201f79fbc03ed8753cfd12ddc\",\"os_type\":\"Mac OS\",\"os_version\":\"10.15\",\"device_model\":\"Firefox 91.0\",\"device_platform\":\"MacIntel\",\"tampered\":false,\"timezone_offset\":-300},\"location\":{\"enabled\":false},\"capabilities\":{\"audio_acquisition_supported\":true,\"finger_print_supported\":false,\"image_acquisition_supported\":false,\"persistent_keys_supported\":false,\"face_id_key_bio_protection_supported\":false,\"fido_client_present\":false,\"dyadic_present\":false,\"installed_plugins\":[]},\"collector_state\":{\"accounts\":\"disabled\",\"devicedetails\":\"active\",\"contacts\":\"disabled\",\"owner\":\"disabled\",\"software\":\"disabled\",\"location\":\"disabled\",\"bluetooth\":\"disabled\",\"externalsdkdetails\":\"active\",\"hwauthenticators\":\"active\",\"capabilities\":\"active\",\"fidoauthenticators\":\"disabled\",\"largedata\":\"active\",\"localenrollments\":\"active\"},\"local_enrollments\":{}},\"collector_version\":\"1.0.0\",\"local_ip\":\"732a7da0\",\"cpu_cores\":16,\"fp2_hash\":\"c28a58fc7629c69757fe1540b61072c7\",\"fp2_keys\":{\"user_agent\":\"Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:91.0) Gecko/20100101 Firefox/91.0\",\"language\":\"en-US\",\"color_depth\":24,\"pixel_ratio\":2,\"hardware_concurrency\":16,\"resolution\":[1536,960],\"available_resolution\":[1536,871],\"timezone_offset\":-300,\"session_storage\":1,\"local_storage\":1,\"indexed_db\":1,\"cpu_class\":\"unknown\",\"navigator_platform\":\"MacIntel\",\"do_not_track\":\"1\",\"regular_plugins\":[],\"canvas\":\"canvas winding:yes~canvas fp:data:image/png;base64,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\",\"webgl\":\"data:image/png;base64,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~extensions:ANGLE_instanced_arrays;EXT_blend_minmax;EXT_color_buffer_half_float;EXT_float_blend;EXT_frag_depth;EXT_shader_texture_lod;EXT_sRGB;EXT_texture_compression_rgtc;EXT_texture_filter_anisotropic;OES_element_index_uint;OES_fbo_render_mipmap;OES_standard_derivatives;OES_texture_float;OES_texture_float_linear;OES_texture_half_float;OES_texture_half_float_linear;OES_vertex_array_object;WEBGL_color_buffer_float;WEBGL_compressed_texture_s3tc;WEBGL_compressed_texture_s3tc_srgb;WEBGL_debug_renderer_info;WEBGL_debug_shaders;WEBGL_depth_texture;WEBGL_draw_buffers;WEBGL_lose_context~webgl aliased line width range:[1, 1]~webgl aliased point size range:[1, 255.875]~webgl alpha bits:8~webgl antialiasing:yes~webgl blue bits:8~webgl depth bits:24~webgl green bits:8~webgl max anisotropy:16~webgl max combined texture image units:80~webgl max cube map texture size:8192~webgl max fragment uniform vectors:1024~webgl max render buffer size:8192~webgl max texture image units:16~webgl max texture size:8192~webgl max varying vectors:32~webgl max vertex attribs:16~webgl max vertex texture image units:16~webgl max vertex uniform vectors:1024~webgl max viewport dims:[16384, 16384]~webgl red bits:8~webgl renderer:Mozilla~webgl shading language version:WebGL GLSL ES 1.0~webgl stencil bits:0~webgl vendor:Mozilla~webgl version:WebGL 1.0~webgl unmasked vendor:Intel Inc.~webgl unmasked renderer:Intel(R) HD Graphics 400~webgl vertex shader high float precision:23~webgl vertex shader high float precision rangeMin:127~webgl vertex shader high float precision rangeMax:127~webgl vertex shader medium float precision:23~webgl vertex shader medium float precision rangeMin:127~webgl vertex shader medium float precision rangeMax:127~webgl vertex shader low float precision:23~webgl vertex shader low float precision rangeMin:127~webgl vertex shader low float precision rangeMax:127~webgl fragment shader high float precision:23~webgl fragment shader high float precision rangeMin:127~webgl fragment shader high float precision rangeMax:127~webgl fragment shader medium float precision:23~webgl fragment shader medium float precision rangeMin:127~webgl fragment shader medium float precision rangeMax:127~webgl fragment shader low float precision:23~webgl fragment shader low float precision rangeMin:127~webgl fragment shader low float precision rangeMax:127~webgl vertex shader high int precision:0~webgl vertex shader high int precision rangeMin:24~webgl vertex shader high int precision rangeMax:24~webgl vertex shader medium int precision:0~webgl vertex shader medium int precision rangeMin:24~webgl vertex shader medium int precision rangeMax:24~webgl vertex shader low int precision:0~webgl vertex shader low int precision rangeMin:24~webgl vertex shader low int precision rangeMax:24~webgl fragment shader high int precision:0~webgl fragment shader high int precision rangeMin:24~webgl fragment shader high int precision rangeMax:24~webgl fragment shader medium int precision:0~webgl fragment shader medium int precision rangeMin:24~webgl fragment shader medium int precision rangeMax:24~webgl fragment shader low int precision:0~webgl fragment shader low int precision rangeMin:24~webgl fragment shader low int precision rangeMax:24\",\"adblock\":false,\"has_lied_languages\":false,\"has_lied_resolution\":false,\"has_lied_os\":false,\"has_lied_browser\":false,\"touch_support\":[0,false,false],\"js_fonts\":[\"Andale Mono\",\"Arial\",\"Arial Black\",\"Arial Hebrew\",\"Arial Narrow\",\"Arial Rounded MT Bold\",\"Arial Unicode MS\",\"Comic Sans MS\",\"Courier\",\"Courier New\",\"Geneva\",\"Georgia\",\"Helvetica\",\"Helvetica Neue\",\"Impact\",\"LUCIDA GRANDE\",\"Microsoft Sans Serif\",\"Monaco\",\"Palatino\",\"Tahoma\",\"Times\",\"Times New Roman\",\"Trebuchet MS\",\"Verdana\",\"Wingdings\",\"Wingdings 2\",\"Wingdings 3\"]}},\"policy_request_id\":\"password_login\",\"params\":{\"BlackBoxData\":\"0400b74iVmgtBjeVebKatfMjIOY4Hf+8eMAmC8NEIQ7BDYeuOY+Od8eZK9sk7BG4Kj8tvhm18byEN5VgEp4jEtyXRJRvFCMSbl5xk/Mn+pjQrNiXv1JG60PXtN7z2M2U4qB34yvRhXnDkIuXF1/+13tDista6l/Gk3abdlkQiqR+mrjRWU65e32V+BPV/IQlGUEBR/ki8UI8Bfbmx2vNwg2QepiRF5A4fGmeNDYfz/kHLpVKNXlHr3mNUsMae9Py8dxW+xf3SJdpRcE85Z8QC9jHuKcdvOaikN2qKULPxBs5mS/Sqt+gL94nKN9qycqMlJavjAy8oe1SYVRrRjLkmcdik6wUt+O88Vr/O+47zrvGgMKoPS2/rCynxDyvd8h9m4Tpohl+AdGTCkZphVATY4xCV8Cq9DH/qIFmOg9w7I7EUja2ZJpuz7rCFGK7Kv74IzOoIKwR+cb8pJlDyrXEs0yPFqZzEiBSQqbzYqxuo4+AN/6rK//MyK6aYUzGIUgUy6aKTuft7xEb6jZigpGERGgfzxN2DHMineAGUUsNxcsmfgrnPdmhpGrD/aBWwq9T4dCg6KsvTrSXJ9QfvdldK7rqKmrpp38kc9gWlBGDts4TYO00rfrnFqqoLvX1dIp2xx9ClVNvD2B5vOZme/hJzHwuC1oa52nn2sDB+MTw3KUeezTuvyPSMHyo1WvFnOOCN/8GqL0EeNSIa1in/ZjN/sPWSAQsLD4xQlj1edyPels7jcFHVkFHYgZtJe8mR20/oWqfEHK71L7cSw8wt6O60biHEjLZWhjhc7elKA75SqnFv76xkF+DIhH5fITqGyLXUVSWGTB8Kq+X3ZjEAPkPbRTCjU7MS7h+PwMQU7MoiPNjR8XH7qj2PX6YsSxMz+EwGzS8+Opk5SdmahJ+pgCd2Ux7tphRLwCgRKUCCaui1+e0pjzosS1xgSAgNSaUGABVKw5XLKh8TEcjAN3IbgAcRtTcjKdeuFyc7hSADs7/ShsmpVMxXYd5d+wil70Gqy63hruaImwI4pTgqhbMiYSmM/j3N+shaHYP8Oq6k+cVXJiV2yLuhzmHVhuxKLofPqnqnczact2Z3h4ZoyygZW1jJ4sctGEQkRLFIksxM6m8KZlmvCPmCFaN/UTyJ6/AVGrJ6lBbdf1BXexV0yQk/64lKoqM3wDorceNFA82v7JSfawcIIi+DWK1fKwVCr1/Y9e8eiB48zOQG3Lx3BOuz92cfnBmZ1Qo5fc0YTyTosGVnNFXCIQZWyHUZkZv+/5KAJry89qKjaI6LDJ86pYF6I5OGxfdT/FUanAKc/Gr/Fv8Z2RXmw1a+8GxQj+MNrKI+tnKVfF1Xxh7UMM4/qbgESpCO8tPkhX34m9zV7aRwpVQJJUh1iLaRgGd2sl4NaiOGI85rFBOyzR7++NeG1qzjggnP9sadsSsT4bK4OXZod6WZzCbsg4etZx+JHsZNUEBS4cX36UOHQzvAtqFCqUrvK7umZwqk1e0CNkmNB8S7nSoSdsPtnLz3wafI/WYHEsTCs2TKRSUB860/YtAXmzCSuwj+Acg2f2vFnup4QVZgvPCHCKdA+dFmWyZCltEE4uconR5A1+lIO2l21FAvnUTJkV6aHMpi3gxZ9OrxTyXYYYd70+JJ2lq9/ag67mh6gJthhlo0DnnJibhVEazzgEGTwhplxdbafJ38Ij1wnpJrkHvv89USvya1mx/02Ma5d+vxqFwm8W6xFel0EVGmiJ6T5fPa+B2yQyde/wZQL/K+wggh4PCMZzF+FQCMdGDWUukcf8MrK1k+o0tCDnp9AlFhJKyMgZypm8czElJjtdFVIs6OZ4iyJ1LLDEdUEQDpGdwQTAutMqyKKlvSVsTWZYnn1MSMQRLdDfhjU8cozRGSdYogTGekWE/1vPmh5kD1Mrwba8jMEjL/eL67n2NowYXAgGScxvNxgOyTlTIRRJIT5Np7o1OHWIAe01wK7v/jwZ+lD6aW6RMrshZRQnn4C6A343rdKdLkTDhYcVuQsB1ACr1xnmDb50oA+M0sTlWH9XlL2CUQ/TDv9eOvsyvTBVVqAHV2ycl4jj0nEo3ZjoPoXbIeeya4n9QVLobGZ2KfCxBBp671HWtlz0Giixr6Z828iDVcdu0P/DQ6Vn+Y+46YDI63/6pkKlZ+tn1oGWpgspWAEw2V0ARjP6w35524QBZT3GJUQmIb+OWzcW5k1Dl8VF3UQo/Haf2iI4IB7wQcXYpPgQiEVyidD6uUgxN7BXzBngSaW/U1gdwKLI4UXFQ3eGQIXZCOg8=;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\",\"ActimizeData\":\"\",\"rm\":false,\"routingKey\":\"Prod\",\"appId\":\"react_module_dev\",\"tenantId\":\"USB\",\"channelId\":\"web\",\"appVersion\":\"1\",\"clientId\":\"OLB\",\"visitorId\":\"85170038403658713101730496133385213445\"}}}";
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/authenticate?aid=web", $data, $this->headers);

            if ($this->http->Response['code'] == 403) {
                $this->sendStatistic(false, false, $this->key);

                // Personal ID is 7-22 characters, no spaces or special characters.
                if (strpos($this->AccountFields['Login'], '@')) {
                    throw new CheckException("Personal ID is 7-22 characters, no spaces or special characters.", ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = "Need to update sensor_data";

                throw new CheckRetryNeededException(3, 5);
            }

            $this->sendStatistic(true, false, $this->key);

            $this->http->RetryCount = 2;
            $authData = $this->http->JsonLog();

            if (isset($authData->data->challenge)) {
                $challenge = $authData->data->challenge;
            }

            if (isset($authData->data->control_flow) && is_array($authData->data->control_flow)) {
                foreach ($authData->data->control_flow as $flow) {
//                    if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == $type && !isset($assertion_id))
//                        $assertion_id = $flow->methods[0]->assertion_id;
                    if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld' && !isset($assertion_id)) {
                        $assertion_id_password = $flow->methods[0]->assertion_id;
                    }

                    if (isset($flow->methods[0]->assertion_id, $flow->methods[0]->placeholder_type)
                        && $flow->methods[0]->type == 'placeholder_qa'
                        && $flow->methods[0]->placeholder_type == 'question'
                        && !isset($assertion_question)) {
                        $assertion_question = $flow->methods[0]->assertion_id;
                    }
                    // OTP
                    if (!isset($assertion_id_sms) && isset($flow->methods[1]->assertion_id, $flow->methods[1]->channels)
                        && $flow->methods[1]->type == 'otp') {
                        foreach ($flow->methods[1]->channels as $channel) {
                            if ($channel->type == 'sms') {
                                $assertion_id_sms = $channel->assertion_id;
                                $assertion_id_sms_target = $channel->target;

                                break;
                            }// if ($channel->type == 'sms')
                        }// foreach ($flow->methods[0]->channels as $channel)
                    }// if ($type == 'placeholder_qa' && isset($flow->methods[0]->assertion_id, $flow->methods[0]->channels)...

                    if (isset($flow->type) && $flow->type == 'json_data' && !isset($assert)) {
                        $assert = $flow->assertion_id;
                    } elseif (isset($flow->type) && $flow->type == 'json_data' && isset($assert)) {
                        $assert_qa = $flow->assertion_id;
                    }
                }
            }// if (isset($authData->data->control_flow) && is_array($authData->data->control_flow))

            if (isset($authData->headers) && is_array($authData->headers)) {
                foreach ($authData->headers as $header) {
                    if ($header->type == 'device_id') {
                        $device_id = $header->device_id;
                    }

                    if ($header->type == 'session_id') {
                        $session_id = $header->session_id;
                    }
                }// foreach ($authData->headers as $header)
            }// if (isset($authData->headers) && is_array($authData->headers))

            if (empty($device_id) || empty($session_id) || empty($challenge)) {
                $this->logger->error("something went wrong");
                /**
                 * For your security, your Personal ID has been locked.
                 * To access your account, you'll need to reset your security questions and answers.
                 */
                if (
                    (isset($authData->data->failure_data->reason) && $authData->data->failure_data->reason == 'locked')
                    || (isset($authData->data->failure_data->reason->data->reason) && $authData->data->failure_data->reason->data->reason == 'locked')
                ) {
                    throw new CheckException("For your security, your Personal ID has been locked. To access your account, you'll need to reset your security questions and answers.", ACCOUNT_LOCKOUT);
                }

                return null;
            }

            $this->logger->debug("challenge: $challenge");
            $this->logger->debug("device_id: $device_id");
            $this->logger->debug("session_id: $session_id");

            if (isset($assert)) {
                $this->logger->debug("assert: $assert");
                $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$assert}\",\"action\":\"json_data\",\"fch\":\"{$challenge}\",\"assert\":\"action\"}}";
                $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$device_id}&sid={$session_id}", $data, $this->headers);
                $this->http->JsonLog();
            }

            $this->data = [
                "assert"                  => $assert ?? null,
                "challenge"               => $challenge,
                "device_id"               => $device_id,
                "session_id"              => $session_id,
                "assertion_id"            => $assertion_id ?? null,
                "assertion_question"      => $assertion_question ?? null,
                "assert_qa"               => $assert_qa ?? null,
                "assertion_id_password"   => $assertion_id_password ?? null,
                "assertion_id_sms"        => $assertion_id_sms ?? null,
                "assertion_id_sms_target" => $assertion_id_sms_target ?? null,
            ];

            return $this->data;
        }// if (isset($response->TransmitURL) && $response->TransmitURL == '/Proxy/TS/api/v2/web/')

        return null;
    }

    public function sendPassword($loginResponse)
    {
        $this->logger->notice(__METHOD__);
        // new service
        $response = $this->http->JsonLog();

        if (!$response || isset($response->data->assertions_complete) || (isset($response->ErrorMessage) && $response->ErrorMessage == 'Success')) {
            $response = $loginResponse;
        }

        if (!isset($response->ViewName) && (isset($response->Data->ViewName) || isset($response->Data->TransmitPolicy))) {
            $response = $response->Data;
        }

        if ((isset($response->ViewName) && $response->ViewName == 'Password')
            || (isset($response->TransmitPolicy) && $response->TransmitPolicy == 'login_passwd')
            || (isset($response->ErrorMessage) && $response->ErrorMessage == 'Success' && stristr($this->http->currentUrl(), 'validatestepupquestion'))) {
            if (isset($response->ErrorMessage) && $response->ErrorMessage == 'Success' && stristr($this->http->currentUrl(), 'validatestepupquestion')) {
                $this->sendNotification("usbank - sq. Need to check");
            }

            // new auth
            $version = 1;

            if ($this->data = $this->getContextData($response)) {
                $version = 2;
            }
            // get cookies
            $this->logger->notice("get cookies");
            $this->http->GetURL("https://onlinebanking.usbank.com/Auth/Login/ProtectedResource");
            // setup form
            $this->logger->notice("setup form");

            if (!isset($response->OAMPostUrl)) {
                $this->logger->error("OAMPostUrl not found");
                $this->logger->debug(var_export($this->State, true), ['pre' => true]);

                return false;
            }// if (!isset($response->OAMPostUrl))

            $this->http->Form = [];
            $this->http->FormURL = $response->OAMPostUrl;
            $this->http->NormalizeURL($this->http->FormURL);
            $this->http->SetInputValue("UserId", $this->AccountFields['Login']);
            $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
            sleep(1);
            $this->http->PostForm(["Referer" => "https://www.usbank.com/index.html", "Accept" => "application/json, text/plain, */*"]);
            $response = $this->http->JsonLog();
            $this->logger->debug("Version: {$version}");

            if (isset($loginResponse->Data)) {
                $loginResponse = $loginResponse->Data;
            }

            $this->logger->debug(var_export($loginResponse, true), ['pre' => true]);
            $this->logger->debug(var_export($this->data, true), ['pre' => true]);

            if ($version == 2 && isset($response->Success, $loginResponse->SessionGUID) && $response->Success == true && !empty($this->data)) {
                $this->logger->notice("sendPassword Version 2");
                $this->http->Form = [];
                $this->http->SetInputValue("ActimizeData", "");
                $this->http->SetInputValue("BlackBoxData", "0400b74iVmgtBjeVebKatfMjIOY4Hf+8eMAmC8NEIQ7BDYeuOY+Od8eZK9sk7BG4Kj8tvhm18byEN5VgEp4jEtyXRJRvFCMSbl5xk/Mn+pjQrNiXv1JG60PXtN7z2M2U4qB34yvRhXnDkIuXF1/+13tDista6l/Gk3abdlkQiqR+mrjRWU65e32V+BPV/IQlGUEBR/ki8UI8Bfbmx2vNwg2QepiRF5A4fGmeNDYfz/kHLpVKNXlHr3mNUsMae9Py8dxW+xf3SJdpRcE85Z8QC9jHuKcdvOaikN2qKULPxBs5mS/Sqt+gL94nKN9qycqMlJavjAy8oe1SYVRrRjLkmcdik6wUt+O88Vr/O+47zrvGgMKoPS2/rCynxDyvd8h9m4Tpohl+AdGTCkZphVATY4xCV8Cq9DH/qIFmOg9w7I7EUja2ZJpuz7rCFGK7Kv74IzOoIKwR+cb8pJlDyrXEs0yPFqZzEiBSQqbzYqxuo4+AN/6rK//MyK6aYUzGIUgUy6aKTuft7xEb6jZigpGERGgfzxN2DHMineAGUUsNxcsmfgrnPdmhpGrD/aBWwq9T4dCg6KsvTrSXJ9QfvdldK7rqKmrpp38kc9gWlBGDts4TYO00rfrnFqqoLvX1dIp2xx9ClVNvD2B5vOZme/hJzHwuC1oa52nn2sDB+MTw3KUeezTuvyPSMHyo1WvFnOOCN/8GqL0EeNSIa1in/ZjN/sPWSAQsLD4xQlj1edyPels7jcFHVkFHYgZtJe8mR20/oWqfEHK71L7cSw8wt6O60biHEjLZWhjhc7elKA75SqnFv76xkF+DIhH5fITqGyLXUVSWGTB8Kq+X3ZjEAPkPbRTCjU7MS7h+PwMQU7MoiPNjR8XH7qj2PX6YsSxMz+EwGzS8+Opk5SdmahJ+pgCd2Ux7tphRLwCgRKUCCaui1+e0pjzosS1xgSAgNSaUGABVKw5XLKh8TEcjAN3IbgAcRtTcjKdeuFyc7hSADs7/ShsmpVMxXYd5d+wil70Gqy63hruaImwI4pTgqhbMiYSmM/j3N+shaHYP8Oq6k+cVXJiV2yLuhzmHVhuxKLofPqnqnczact2Z3h4ZoyygZW1jJ4sctGEQkRLFIksxM6m8KZlmvCPmCFaN/UTyJ6/AVGrJ6lBbdf1BXexV0yQk/64lKoqM3wDorceNFA82v7JSfawcIIi+DWK1fKwVCr1/Y9e8eiB48zOQG3Lx3BOuz92cfnBmZ1Qo5fc0YTyTosGVnNFXCIQZWyHUZkZv+/5KAJry89qKjaI6LDJ86pYF6I5OGxfdT/FUanAKc/Gr/Fv8Z2RXmw1a+8GxQj+MNrKI+tnKVfF1Xxh7UMM4/qbgESpCO8tPkhX34m9zV7aRwpVQJJUh1iLaRgGd2sl4NaiOGI85rFBOyzR7++NeG1qzjggnP9sadsSsT4bK4OXZod6WZzCbsg4etZx+JHsZNUEBS4cX36UOHQzvAtqFCqUrvK7umZwqk1e0CNkmNB8S7nSoSdsPtnLz3wafI/WYHEsTCs2TKRSUB860/YtAXmzCSuwj+Acg2f2vFnup4QVZgvPCHCKdA+dFmWyZCltEE4uconR5A1+lIO2l21FAvnUTJkV6aHMpi3gxZ9OrxTyXYYYd70+JJ2lq9/ag67mh6gJthhlo0DnnJibhVEazzgEGTwhplxdbafJ38Ij1wnpJrkHvv89USvya1mx/02Ma5d+vxqFwm8W6xFel0EVGmiJ6T5fPa+B2yQyde/wZQL/K+wggh4PCMZzF+FQCMdGDWUukcf8MrK1k+o0tCDnp9AlFhJKyMgZypm8czElJjtdFVIs6OZ4iyJ1LLDEdUEQDpGdwQTAutMqyKKlvSVsTWZYnn1MSMQRLdDfhjU8cozRGSdYogTGekWE/1vPmh5kD1Mrwba8jMEjL/eL67n2NowYXAgGScxvNxgOyTlTIRRJIT5Np7o1OHWIAe01wK7v/jwZ+lD6aW6RMrshZRQnn4C6A343rdKdLkTDhYcVuQsB1ACr1xnmDb50oA+M0sTlWH9XlL2CUQ/TDv9eOvsyvTBVVqAHV2ycl4jj0nEo3ZjoPoXbIeeya4n9QVLobGZ2KfCxBBp671HWtlz0Giixr6Z828iDVcdu0P/DQ6Vn+Y+46YDI63/6pkKlZ+tn1oGWpgspWAEw2V0ARjP6w35524QBZT3GJUQmIb+OWzcW5k1Dl8VF3UQo/Haf2iI4IB7wQcXYpPgQiEVyidD6uUgxN7BXzBngSaW/U1gdwKLI4UXFQ3eGQIXZCOg8=;0400K7P+3nrtIiYpIfq2LLtL/drur8ua+dwLLZ2rTK5TO/8Hu4mtNODDa1ngMGNUXTtqqlMO3jcDfyM2ptrs+12qqmIcFykOTjbeRR//vqFuzT2eJlIZYYzggoKvVVmohuKr4yvRhXnDkIt6kLDGl0Fh3clggwPVfSeQhhAsONhPJ2evFN4PCW0OhquwYAe+hwRLNzTx97FxuKzmx2vNwg2Qei2/KzsQpYub9/lBa1nusxxUCI4tBnvp+k4Zp6XVTmrf4zzCDRa0F8o3kRiOBWn1QacdvOaikN2qKULPxBs5mS/Sqt+gL94nKN9qycqMlJavjAy8oe1SYVRrRjLkmcdikz4hyZsTrFJyy02BET1V1kRl2Tk9f7v+O6+tryqfrXtgLo7NPqlI1Y+c6C99WtwJTsCq9DH/qIFmOg9w7I7EUja2ZJpuz7rCFGK7Kv74IzOoIKwR+cb8pJlDyrXEs0yPFqZzEiBSQqbzYqxuo4+AN/6rK//MyK6aYUzGIUgUy6aKTuft7xEb6jZigpGERGgfzxN2DHMineAGUUsNxcsmfgrnPdmhpGrD/aBWwq9T4dCg6KsvTrSXJ9QfvdldK7rqKmrpp38kc9gWlBGDts4TYO00rfrnFqqoLvX1dIp2xx9ClVNvD2B5vOZme/hJzHwuC1oa52nn2sDB+MTw3KUeezTuvyPSMHyo1WvFnOOCN/8GqL0EeNSIa1in/ZjN/sPWSAQsLD4xQlj1edyPels7jcFlHVB6chrI7u8mR20/oWqfEHK71L7cSw8wt6O60biHEjLZWhjhc7elKA75SqnFv76xkF+DIhH5fITqGyLXUVSWGTB8Kq+X3ZjEAPkPbRTCjU7MS7h+PwMQU7MoiPNjR8XH7qj2PX6YsSxMz+EwGzS8+Opk5SdmahJ+pgCd2Ux7tphRLwCgRKUCCaui1+e0pjzosS1xgSAgNSaUGABVKw5XLKh8TEcjAN3IbgAcRtTcjKdeuFyc7hSADs7/ShsmpVMxXYd5d+wil70Gqy63hruaImwI4pTgqhbMiYSmM/j3N+shaHYP8Oq6k+cVXJiV2yLuhzmHVhuxKLofPqnqnczact2Z3h4ZoyygZW1jJ4sctGEQkRLFIksxM6m8KZlmvCPmCFaN/UTyJ6/AVGrJ6lBbdf1BXexV0yQk/64lKoqM3wDorceNFA82v7JSfawcIIi+DWK1fKwVCr1/Y9e8eiB48zOQG3Lx3BOuz92cfnBmZ1Qo5fc0YTyTosGVnNFXCIQZWyHUZkZv+/5KAJry89qKjaI6LDJ86pYF6I5OGxfdT/FUanAKc/Gr/Fv8Z2RXmw1a+8GxQj+MNgZPlsojcioEYQGI66jcytq0PVtLGl1WhrFKhhN/4hJ67GQrD3IVZtp8MjUr3b1sVG2ajOAp0OBPyzR7++NeG1qL0M0n4lRpK8TrqzIY+8EFK8e8W29xmz5GxXP6eZzbR0xE7EVNJhS8CcNLBzvhKhTTG3WnqbqQ3oL4UNBbAQEDbJPYW9xvled3xBfNPgHEB9/KUsiAoCUdJndkm9obPrJhuA+XGeonpecwXG0NqHD0+fxC+gBmQejIdLGOu3XjNwdMB87Pnky5fPH0tTzKvOSlO0gIqWJAnyzI3mp0mfYlDaxxWesPJGo01lqJn5tl1WcrwpKAG6akFhJ4cB4oZCM5nyRF6kutTYAovyFGH6xZbbQHuo6qHt8TXWKarogiQVNp13CyamX7MCfCiBpUGmklJILnn8rxELXEc9gpK9mSHjfd9XDhkB7KWq0DpYiwZpxa6WwRUrNJkZDkfk/3teMmD4LFNeOeRSZB8WpPb6rwcXCFiLXg5zjmi9tLaV4dXIaSGs0MJn/VHQWDH0/5lGtupoGbtmhQasDHSJ757gzhVl3dKa2Y3t0Rxp19/1CuDctNtXzAbgkxAneUtAGNC59K3+eX7t4TS2qIZb55lNtw/+UzaewlJEV3lPpQJ0hUKC7yMlG9T7Edil9a5a4EoFgOaAr4exyoGRunxlbxVX8ZvZtsm4vsuddtbEz93SqrdyysIfHq9Iprn8xFoM2QtAITp41dc6NuyCzpOvk/qvsUQIw9YDIaAy5UnRIBevcbl+VTApzB5zrnbSgAfQhpdPgOlGKpjJmI+OQiI9Le2Mc2lodM8zSlPwbQQjf39E88vX7nDutuvmlk7X1p/tAi0QREv+PlAzwvkyiwAzjjy7CWi+3zetmL6pfNpFA4qPHR6R672J+IVGjz/FDfOQ1qv8yocAmKfo1JkVNL/DHYytC2dI1b1r/3BC/vozvP2ldCl3hH3aUJedppwO7WdgijjFKwRgXE/aXVSaRATJGEBJnqHbWPt22U0m0xxTmB5s6iMynYdYchPMHsYPboR4vSu/EZqCktt9s43irKCMBPn2oF8fLaSo6fl+4eWBDhIMXkXf7ksYGcdMg9BMOtlfMoB0k4+h+jdf0sRq5UW3h7y32tHaOWU2n0Niunhih4SFWfpC9E1CHiUl9o7wjSy5BAjfd/ASIUFO9BrPXJXAbdgrl6jzLOJuIssfG4hIcWmdG0Fhfi2KHREKkks+A9qF/MXXT1S0RF8q+oNTBr2UxqGdEfufKodeENIG3t011Pmvjdjosru+Lvt6AQJc9p2Z4O8VgizcUXXT6KPAREN9eVPuo/ec/fp6W44ooq3BMzQP17FpQRDCUKXzOm7JI0GyFD9tRYPkuThuNIkVbNPdhNOuibBkFB0vZlA3w8XPlO2RK1j1Mz7bvobFF8fD6y/7IBMe4zJHwVUpAL3h9qCN09VFpm");
                $this->http->SetInputValue("ContextData", base64_encode("{\"device_id\":\"{$this->data['device_id']}\",\"auth_type\":\"placeholder_password_pld\",\"assertion_id\":\"{$this->data['assertion_id_password']}\",\"challenge\":\"{$this->data['challenge']}\"}"));
                $this->http->SetInputValue("IsOLB", "true");
                $this->http->SetInputValue("SignOnID", $this->AccountFields['Login']);
                $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
                $this->http->SetInputValue("TransactionGUID", $loginResponse->SessionGUID);
                $this->http->SetInputValue("TransmitApplicationId", "web");
                $this->http->SetInputValue("ClientName", "React Auth Modules");
                $this->http->SetInputValue("VersionNumber", "6.0.0");
                $this->http->FormURL = "https://www.usbank.com/api/auth/V1/Password/Validate";

                $headers = [
                    "Accept"       => "application/json, text/plain, */*",
                    "Content-Type" => "application/json",
                    "Referer"      => "https://www.usbank.com/index.html",
                ];

                $this->http->RetryCount = 0;
                $this->http->PostURL($this->http->FormURL, json_encode($this->http->Form), $headers);
//                $this->http->PostForm($headers);
                $this->http->RetryCount = 2;
                $validateResponse = $this->http->JsonLog();

                if (isset($validateResponse->Token)) {
                    $data = "{\"headers\":[{\"type\":\"uid\",\"uid\":\"{$this->AccountFields['Login']}\"}],\"data\":{\"assertion_id\":\"{$this->data['assertion_id_password']}\",\"action\":\"authentication\",\"fch\":\"{$this->data['challenge']}\",\"method\":\"placeholder_password_pld\",\"data\":{\"token\":\"{$validateResponse->Token}\"},\"assert\":\"authenticate\"}}";
                    $this->http->RetryCount = 0;
                    /*
                    $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
                    $this->http->GetURL("https://onlinebanking.usbank.com/auth/signon/signonvalidate", $this->headers);
                    */
                    $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog();

                    if (isset($response->data->token)) {
                        $tokenParts = explode('.', $response->data->token);

                        foreach ($tokenParts as $str) {
                            $str = $this->http->JsonLog(base64_decode($str));
                            $accessToken = $str->accesstoken ?? null;

                            if ($accessToken) {
                                $this->accesstoken = $accessToken;

                                break;
                            }
                        }

                        $this->http->FormURL = 'https://www.usbank.com/Auth/SignOn/SignonWithTransmit';
                        $this->http->Form = [];
                        $this->http->SetInputValue("AppId", "web");
                        $this->http->SetInputValue("DeviceID", $this->data['device_id']);
                        $this->http->SetInputValue("IsTempAccessFlow", "");
                        $this->http->SetInputValue("Policy", "login_passwd");
                        $this->http->SetInputValue("TSParams[0].Key", "ts:userid");
                        $this->http->SetInputValue("TSParams[0].Value", $this->AccountFields['Login']);
                        $this->http->SetInputValue("TSParams[1].Key", "ts:usertkn");
                        $this->http->SetInputValue("TSParams[1].Value", $response->data->token);
                        $this->http->SetInputValue("TSParams[2].Key", "ts:appid");
                        $this->http->SetInputValue("TSParams[2].Value", "web");
                        $this->http->SetInputValue("TSParams[3].Key", "ts:sessionId:" . $this->AccountFields['Login']);
                        $this->http->SetInputValue("TSParams[3].Value", $this->data['session_id']);
                        $this->http->SetInputValue("TSParams[4].Key", "ts:deviceId:" . $this->AccountFields['Login']);
                        $this->http->SetInputValue("TSParams[4].Value", $this->data['device_id']);
                        $this->http->SetInputValue("Token", $response->data->token);
                        $this->http->SetInputValue("UserId", $this->AccountFields['Login']);
                        $form = $this->http->Form;
                        $this->http->PostForm();
                        $responseSignonWithTransmit = $this->http->JsonLog();
                        // redirect on some accounts
                        if (
                            isset($responseSignonWithTransmit->SignOnSuccess, $responseSignonWithTransmit->RedirectUrl, $responseSignonWithTransmit->DCIURedirect)
                            && $responseSignonWithTransmit->SignOnSuccess == false
                            && $responseSignonWithTransmit->DCIURedirect == true
                        ) {
                            $this->logger->debug("DCIU Redirect");
                            $this->http->FormURL = $responseSignonWithTransmit->RedirectUrl;
                            $this->http->NormalizeURL($this->http->FormURL);

                            $this->http->SetInputValue("AppId", "web");
                            $this->http->SetInputValue("DeviceID", $this->data['device_id']);
                            $this->http->SetInputValue("IsTempAccessFlow", "");
                            $this->http->SetInputValue("Policy", "password_login");
                            $this->http->SetInputValue("ClientName", "dotcom");
                            $this->http->SetInputValue("TSParams[0].Key", "ts:sessionId:" . $this->AccountFields['Login']);
                            $this->http->SetInputValue("TSParams[0].Value", $this->data['session_id']);
                            $this->http->SetInputValue("TSParams[1].Key", "ts:userid");
                            $this->http->SetInputValue("TSParams[1].Value", $this->AccountFields['Login']);
                            $this->http->SetInputValue("TSParams[2].Key", "ts:usertkn");
                            $this->http->SetInputValue("TSParams[2].Value", $response->data->token);
                            $this->http->SetInputValue("TSParams[3].Key", "ts:appid");
                            $this->http->SetInputValue("TSParams[3].Value", "web");
                            $this->http->SetInputValue("TSParams[4].Key", "ts:deviceId:" . $this->AccountFields['Login']);
                            $this->http->SetInputValue("TSParams[4].Value", $this->data['device_id']);
                            $this->http->SetInputValue("Token", $response->data->token);
                            $this->http->SetInputValue("UserId", $this->AccountFields['Login']);
                            $this->http->SetInputValue("VersionNumber", "19.9.3");
                            $this->http->SetInputValue("cipherText", $responseSignonWithTransmit->Trusteddata);

                            $headers = [
                                "Referer"         => "https://www.usbank.com/index.html",
                                "Content-Type"    => "application/x-www-form-urlencoded; charset=UTF-8",
                                "Accept"          => "application/json, text/plain, */*",
                                "Accept-Encoding" => "gzip, deflate, br",
                            ];
                            $this->http->PostForm($headers);
                        }
                        $response = $this->http->JsonLog();
                    }// if (isset($response->data->token))
                    elseif (
                        isset($response->error_message, $response->error_code)
                        && $response->error_code == 4001
                        && $this->http->FindPreg("/failure_data\":\{\"source\":\{\"parent\":\{\"action_type\":\"authentication\",\"type\":\"action\"\},\"method\":\"placeholder_password_pld\",\"type\":\"method\"\},\"reason\":\{\"type\":\"locked\",\"data\":\{\"lock_reason\":\"internal\",\"locked\":true,\"lock_type\":\"user\"\}\}\}\},\"headers\":\[\]\}/")
                    ) {
                        // For your security, your Personal ID has been locked. To access your account, you'll need to reset your security questions and answers.
                        throw new CheckException('For your security, your Personal ID has been locked. To access your account, you\'ll need to reset your security questions and answers.', ACCOUNT_LOCKOUT);
                    } elseif ($this->data = $this->getContextData($response)) {
                        $version = 2;
                    }
                }// if (isset($validateResponse->Token))
                elseif ($this->http->Response['code'] == 403) {
                    $this->sendStatistic(false, false, $this->key);

                    $this->DebugInfo = "Need to update sensor_data";

                    if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG && $this->attempt == 1) {
                        $this->logger->error("stop");

                        return false;
                    }

                    throw new CheckRetryNeededException(2, 5);
                }
            }// if ($version == 2 && isset($response->Success) && $response->Success == true)

            if (isset($response->ErrorMessage)
                && (
                    (isset($response->SignOnSuccess) && $response->SignOnSuccess == false)
                    || (isset($response->Success) && $response->Success == false)
                    || (isset($response->IsSuccess) && $response->IsSuccess == false)
                )
            ) {
                $message = $response->ErrorMessage;
                $this->logger->error($message);

                switch ($message) {
                    case "Sorry, our system is currently unavailable. Please try again.":
                        if (isset($response->RedirectUrl) && $response->RedirectUrl == '/USB/SystemUnavailableHNA.aspx') {
                            throw new CheckException("Hmm. We couldn't find any active accounts associated to your Personal ID.", ACCOUNT_INVALID_PASSWORD);
                        }

                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

                        break;

                    case "Hmm. That doesn’t match our records. Please try again.":
                    case "Incorrect password. Remember: passwords are case sensitive.":
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

                        break;

                    default:
                        // Account lockout
                        if (strstr($message, "The password you entered doesn't match our records. <br/>For your security, your account has been locked.")) {
                            throw new CheckException("The password you entered doesn't match our records. For your security, your account has been locked.", ACCOUNT_LOCKOUT);
                        }
                        // For your security, your Personal ID has been locked.
                        if (
                            strstr($message, "For your security, your Personal ID has been locked.")
                        ) {
                            throw new CheckException('For your security, your Personal ID has been locked.', ACCOUNT_LOCKOUT);
                        }
                        // We're sorry; it looks like your personal ID has been disabled. Please contact 800-987-7237 for help.
                        if (strstr($message, "it looks like your personal ID has been disabled")) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->logger->error("[Unknown Error]: {$message}");

                        return false;

                        break;
                }// switch ($response->ErrorMessage)
            }// if (isset($response->SignOnSuccess, $response->ErrorMessage) && $response->SignOnSuccess == false)

            if (!$this->finalRedirect($response)) {
                $this->logger->info('Security Question after password', ['Header' => 2]);

                $authData = $this->http->JsonLog();

                if (isset($authData->data->challenge)) {
                    $challenge = $authData->data->challenge;
                }

                if (isset($authData->data->control_flow) && is_array($authData->data->control_flow)) {
                    foreach ($authData->data->control_flow as $flow) {
//                    if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == $type && !isset($assertion_id))
//                        $assertion_id = $flow->methods[0]->assertion_id;
                        if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld' && !isset($assertion_id)) {
                            $assertion_id_password = $flow->methods[0]->assertion_id;
                        }

                        if (isset($flow->methods[0]->assertion_id, $flow->methods[0]->placeholder_type)
                            && $flow->methods[0]->type == 'placeholder_qa'
                            && $flow->methods[0]->placeholder_type == 'question'
                            && !isset($assertion_question)) {
                            $assertion_question = $flow->methods[0]->assertion_id;
                        }
                        // OTP
                        if (!isset($assertion_id_sms) && isset($flow->methods[1]->assertion_id, $flow->methods[1]->channels)
                            && $flow->methods[1]->type == 'otp') {
                            foreach ($flow->methods[1]->channels as $channel) {
                                if ($channel->type == 'sms') {
                                    $assertion_id_sms = $channel->assertion_id;
                                    $assertion_id_sms_target = $channel->target ?? $channel->targets->{1};

                                    break;
                                }// if ($channel->type == 'sms')
                            }// foreach ($flow->methods[0]->channels as $channel)
                        }// if ($type == 'placeholder_qa' && isset($flow->methods[0]->assertion_id, $flow->methods[0]->channels)...
                        // AccountID: 5794600
                        if (!isset($assertion_id_sms) && isset($flow->methods[0]->channels) && $flow->methods[0]->type == 'otp') {
                            foreach ($flow->methods[0]->channels as $channel) {
                                if ($channel->type == 'sms') {
                                    $assertion_id_sms = $channel->assertion_id;
                                    $assertion_id_sms_target = $channel->target ?? $channel->targets->{1};

                                    break;
                                }// if ($channel->type == 'sms')
                            }// foreach ($flow->methods[0]->channels as $channel)
                        }// if (!isset($assertion_id_sms) && isset($flow->methods[0]->channels) && $flow->methods[0]->type == 'otp'

                        if (isset($flow->type) && $flow->type == 'json_data' && !isset($assert)) {
                            $assert = $flow->assertion_id;
                        } elseif (isset($flow->type) && $flow->type == 'json_data' && isset($assert)) {
                            $assert_qa = $flow->assertion_id;
                        }
                    }
                }// if (isset($authData->data->control_flow) && is_array($authData->data->control_flow))

                $this->data = [
                    "assert"                  => $assert ?? $this->data['assert'] ?? null,
                    "challenge"               => $challenge ?? $this->data['challenge'],
                    "device_id"               => $this->data['device_id'],
                    "session_id"              => $this->data['session_id'],
                    "assertion_id"            => $this->data['assertion_id'] ?? $assertion_id ?? null,
                    "assertion_question"      => $this->data['assertion_question'] ?? $assertion_question ?? null,
                    "assert_qa"               => $this->data['assert_qa'] ?? $assert_qa ?? null,
                    "assertion_id_password"   => $this->data['assertion_id_password'] ?? $assertion_id_password ?? null,
                    "assertion_id_sms"        => $this->data['assertion_id_sms'] ?? $assertion_id_sms ?? null,
                    "assertion_id_sms_target" => $this->data['assertion_id_sms_target'] ?? $assertion_id_sms_target ?? null,
                ];

                if ($this->parseQuestion($loginResponse)) {
                    return false;
                }
            }
            // need to update profile
            if ($this->http->FindSingleNode("//div[contains(text(), 'Protecting your personal information is important to us. ID Shield Questions provide you with an extra layer of security when you access your accounts online.')]")) {
                $this->throwProfileUpdateMessageException();
            }

            return true;
        }// if (isset($response->ViewName))
        else {
            // redirect fix
            $this->http->setMaxRedirects(0);

            if (!$this->http->ParseForm("formPassword")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->PostForm();
            // redirect fix
            $this->http->setMaxRedirects(5);
            $this->http->GetURL("https://onlinebanking.usbank.com/Auth/Signon/Signon");
        }
        // The password you entered doesn't match our records.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "The password you entered doesn\'t match our records.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // To protect your accounts, choose 3 to 5 questions only you can answer
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'To protect your accounts, choose 3 to 5 questions only you can answer.')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->currentUrl() == 'https://onlinebanking.usbank.com/Auth/Login/Password') {
            throw new CheckException("The password you entered doesn't match our records. For your security, your account has been locked. To continue, reset your password.", ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function finalRedirect($response)
    {
        $this->logger->notice(__METHOD__);

        if (
            !isset($response->SignOnSuccess)
            || !isset($response->RedirectUrl)
            || $response->SignOnSuccess != true
        ) {
            return false;
        }

        $this->logger->debug("Redirect");
        $redirectUrl = $response->RedirectUrl;
        $this->aftokenvalue = $this->http->FindPreg("/USB\/([^\/]+)\//", false, $redirectUrl);

        if ($this->attempt > 0) {
            sleep(2);
        } else {
            sleep(1);
        }
        $headers = [
            "Referer"    => "https://www.usbank.com/index.html",
            "User-Agent" => HttpBrowser::PROXY_USER_AGENT,
        ];

        // AccountID: 4817634, 3486720, 5296593, 3202602
        if ($redirectUrl === '') {
            $this->http->GetURL("https://onlinebanking.usbank.com/USB/CustomerDashboard/Index");
            $this->aftokenvalue = $this->http->FindPreg("/USB\/([^\/]+)\//", false, $this->http->currentUrl());
            $redirectUrl = "https://onlinebanking.usbank.com/USB/{$this->aftokenvalue}/Enrollment/Enrollment";
        }

        $this->http->GetURL($redirectUrl, $headers);

        return true;
    }

    public function redirectToDashboard($accesstoken, $aftokenvalue)
    {
        $this->logger->notice(__METHOD__);

        if ($redirect = $this->http->FindPreg("#window\.location\.href\s*=\s*window\.location\.origin\s*\+\s*\"(/digital/servicing/customer-dashboard)\";#")) {
            $this->baseURL = preg_replace("/\/(?:CustomerDashboard.+|USB\/Enrollment\/RedirectToDIYDashboard.+)/ims", '', $this->http->currentUrl());
            $this->logger->debug("[Base URL]: {$this->baseURL}");
            $this->State['DashboardURL'] = $this->http->currentUrl();

            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);

            $headers = [
                "Accept"          => "*/*",
                "aftokenvalue"    => $aftokenvalue,
                "routingKey"      => "",
                "authorization"   => "Bearer {$accesstoken}",
                "Service-Version" => 2,
                "Content-Type"    => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query{getNavigation(types:[NavBar,Header])}","variables":null}', $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            foreach ($response->data->getNavigation as $nav) {
                if ($nav->type == "Header") {
                    foreach ($nav->navigationList as $list) {
                        if ($list->id != 'CustomerName') {
                            continue;
                        }
                        $this->SetProperty("Name", beautifulName($list->metaData->firstName ?? null));

                        return true;
                    }

                    break;
                }
            }// foreach ($response->data->getNavigation as nav)
        }

        return false;
    }

    public function parseQuestion($response = null)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $question = $this->http->FindSingleNode("//input[@name = 'StepUpShieldQuestion.QuetionText']/@value");

        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//b[contains(text(), '" . self::QUESTION_TSC . "')]");
        }
        // new service
        $this->State["NewService"] = false;
        $this->State["Version"] = 1;

        if (!isset($question) && isset($response->IDShieldBaseURL, $response->UserID, $response->SessionGUID) && $response->IDShieldBaseURL == '/api/auth/V1/EAS/') {
            $this->logger->notice("parseQuestion, Version 2");
            $this->State["NewService"] = true;
            $this->State["Version"] = 2;
            $this->State["Response"] = $response;

            $this->data = $this->getContextData($response);
            $this->logger->debug("parseQuestion, \$this->data:");
            $this->logger->debug(var_export($this->data, true), ['pre' => true]);

            if (!$this->data) {
                return false;
            }
            $this->logger->debug("parseQuestion, check auth options");
            $this->State["Data"] = $this->data;

            if (!empty($this->data['assertion_question'])) {
                $this->logger->debug("parseQuestion, auth via questions");
                $this->http->Form = [];
                $this->http->FormURL = "https://www.usbank.com/api/auth/V1/EAS/getstepupquestion";
                $this->http->SetInputValue("SignOnId", $response->UserID);
                $this->http->SetInputValue("TransactionGUID", $response->SessionGUID);
                $this->http->SetInputValue("TransactionId", "login");
                $this->http->PostForm(["Accept" => "application/json, text/plain, */*"]);
                $questionResponse = $this->http->JsonLog();

                if (!isset($questionResponse->QestionText)) {
                    if (
                        isset($questionResponse->ErrorMessage)
                        || strstr($questionResponse->ErrorMessage, 'IG UserID Locked')
                    ) {
                        throw new CheckException('Hmm. That information doesn\'t match what we have on file. To be sure your account is secure, we\'ve locked it for now.', ACCOUNT_LOCKOUT);
                    }

                    return false;
                }
                $question = $questionResponse->QestionText;
                // setup form
                $this->logger->notice("setup form");
                $this->http->Form = [];
                $this->http->SetInputValue("ActimizeData", '%7B%22DeviceID%22%3A%22b2a3639fc026dc3c28cfdff625abbef7c39e6c4aa75ab5a901d2fff2546a17f6%22%2C%22DeviceData%22%3A%7B%22platform%22%3A%22web%22%2C%22version%22%3A%221.0.2%22%2C%22attributes%22%3A%7B%22plugins%22%3A%5B%22Shockwave%20Flash%3BFlash%20Player.plugin%3BShockwave%20Flash%2029.0%20r0%22%5D%2C%22platform%22%3A%22MacIntel%22%2C%22appVersion%22%3A%225.0%20(Macintosh)%22%2C%22cpu%22%3A%22Intel%20Mac%20OS%20X%2010.13%22%2C%22cssVendorPrefix%22%3A%22moz%22%2C%22cookiesEnabled%22%3Atrue%2C%22javaEnabled%22%3Afalse%2C%22flashEnabled%22%3Atrue%2C%22flashVersion%22%3A%2229.0.0%22%2C%22language%22%3A%22en-US%22%2C%22doNotTrack%22%3A%221%22%2C%22timezoneOffset%22%3A300%2C%22width%22%3A1440%2C%22height%22%3A900%2C%22availWidth%22%3A1440%2C%22availHeight%22%3A830%2C%22colorDepth%22%3A24%2C%22localStorage%22%3Atrue%2C%22sessionStorage%22%3Atrue%2C%22indexedDB%22%3Atrue%2C%22fonts%22%3A%5B0%2C1%2C2%2C4%2C11%2C14%2C20%2C21%2C22%2C25%2C26%2C27%2C29%2C30%2C31%2C37%2C38%2C40%2C41%2C43%2C44%2C45%2C48%2C50%2C55%2C58%2C62%2C63%2C64%2C65%2C70%2C71%2C72%2C74%2C77%2C81%2C82%2C84%2C86%2C87%2C88%2C92%2C93%2C94%2C98%2C99%2C100%2C110%2C111%2C113%2C114%2C115%2C116%2C117%2C119%2C120%2C122%2C127%2C128%2C130%2C137%2C139%2C146%2C148%2C149%2C158%2C159%2C165%2C168%2C172%2C183%2C189%2C191%2C193%2C194%2C198%2C199%2C200%2C202%2C203%2C209%2C212%2C216%2C217%2C221%2C222%2C224%2C226%2C227%2C229%2C230%2C233%2C234%2C235%2C236%2C239%2C240%2C251%2C258%2C261%2C270%2C281%2C282%2C283%2C284%2C285%2C286%2C287%2C288%2C289%2C293%2C297%2C298%2C300%2C302%2C303%2C305%2C309%2C310%2C313%2C314%2C315%2C316%2C317%2C319%2C322%2C324%2C325%2C326%2C327%2C329%2C332%2C334%2C336%2C337%2C338%2C339%2C343%2C347%2C348%2C356%2C362%2C364%2C365%2C369%2C370%2C371%2C373%2C375%2C376%2C379%2C380%2C381%2C382%2C386%2C394%2C396%2C400%2C401%2C422%2C425%2C426%2C427%2C429%2C432%2C437%2C444%2C445%2C447%2C448%2C451%2C454%2C455%2C456%2C460%2C461%2C465%2C471%2C476%2C483%2C486%2C487%2C488%2C489%2C493%5D%2C%22canvas%22%3A%2241f794e773e9e95b1321f8854c8922bf8d351a5ffc3b3add031684bb5baf0a6e%22%2C%22webGL%22%3A%5B%224a5aab6eaab1cfe4f3eb8e90ac93811a0d2a180aaa83f1921f9206e6f769b6b2%22%2C%22ANGLE_instanced_arrays%3BEXT_blend_minmax%3BEXT_color_buffer_half_float%3BEXT_frag_depth%3BEXT_sRGB%3BEXT_shader_texture_lod%3BEXT_texture_filter_anisotropic%3BOES_element_index_uint%3BOES_standard_derivatives%3BOES_texture_float%3BOES_texture_float_linear%3BOES_texture_half_float%3BOES_texture_half_float_linear%3BOES_vertex_array_object%3BWEBGL_color_buffer_float%3BWEBGL_compressed_texture_s3tc%3BWEBGL_compressed_texture_s3tc_srgb%3BWEBGL_debug_renderer_info%3BWEBGL_depth_texture%3BWEBGL_draw_buffers%3BWEBGL_lose_context%3BMOZ_WEBGL_lose_context%3BMOZ_WEBGL_compressed_texture_s3tc%3BMOZ_WEBGL_depth_texture%22%2C%22(1%20x%2010)%22%2C%22(1%20x%202047)%22%2C8%2Ctrue%2C8%2C24%2C8%2C16%2C16%2C16384%2C1024%2C16384%2C16%2C16384%2C16%2C16%2C16%2C1024%2C%22(16384%20x%2016384)%22%2C8%2C%22Mozilla%22%2C%22WebGL%20GLSL%20ES%201.0%22%2C0%2C%22Mozilla%22%2C%22WebGL%201.0%22%5D%2C%22javascriptEnabled%22%3Atrue%2C%22webDeviceLocalDateTime%22%3A%225%2F21%2F2018%2C%206%3A45%3A47%20PM%22%2C%22webDeviceNormalizedDateTime%22%3A%22Mon%2C%2021%20May%202018%2013%3A45%3A47%20GMT%22%2C%22jsVersion%22%3A%221.8%22%7D%2C%22webDeviceCollectionResponseCd%22%3A%7B%22browserName%22%3A%7B%7D%2C%22browserVersion%22%3A%7B%7D%2C%22osName%22%3A%7B%7D%2C%22osVersion%22%3A%7B%7D%7D%2C%22deviceIdConfidence%22%3A0.41000000000000003%7D%2C%22Browser%20UserAgent%22%3A%22Mozilla%2F5.0%20(Macintosh%3B%20Intel%20Mac%20OS%20X%2010_12_6)%20AppleWebKit%2F537.36%20(KHTML%2C%20like%20Gecko)%20Chrome%2F62.0.3202.75%20Safari%2F537.36%22%7D');
                $this->http->SetInputValue("ContextData", base64_encode("{\"device_id\":\"{$this->data['device_id']}\",\"auth_type\":\"placeholder_qa\",\"assertion_id\":\"{$this->data['assertion_question']}\",\"challenge\":\"{$this->data['challenge']}\"}"));
                $this->http->SetInputValue("PolicyID", 'login_passwd');
                $this->http->SetInputValue("TransactionGUID", $response->SessionGUID);
                $this->http->SetInputValue("SignOnId", $response->UserID);
                $this->http->SetInputValue("TransmitApplicationId", "web");

                $this->State["FormURL"] = 'https://www.usbank.com/api/auth/V1/EAS/validatestepupquestion';
                $this->State["Form"] = $this->http->Form;
            }// if (!empty($this->data['assertion_question']))
            elseif (!empty($this->data['assertion_id_sms'])) {
                $this->logger->debug("parseQuestion, auth via sms");

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$this->data['assertion_id_sms']}\",\"action\":\"authentication\",\"fch\":\"{$this->data['challenge']}\",\"method\":\"otp\",\"data\":{\"target_id\":\"1\"},\"assert\":\"otp\"}}";
                $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
                $questionResponse = $this->http->JsonLog();
                $question = self::QUESTION_OTP;

                if (!empty($this->data['assertion_id_sms_target'])) {
                    $question = self::QUESTION_OTP . " (was sent to {$this->data['assertion_id_sms_target']})";
                }
            }// if (!empty($this->data['assertion_id_sms']))
        }

        if (!isset($question) && isset($response->StepUpShieldQuestion->QuetionText, $response->ViewName) && $response->ViewName == 'StepUpCheck') {
            $this->State["NewService"] = true;
            $question = $response->StepUpShieldQuestion->QuetionText;
            // setup form
            $this->logger->notice("parseQuestion, setup form");
            $this->http->Form = [];
            $this->http->SetInputValue("MachineAttribute", $response->MachineAttribute ?? null);
            $this->http->SetInputValue("StepUpShieldQuestion.QuetionText", $response->StepUpShieldQuestion->QuetionText ?? null);
            $this->http->SetInputValue("PersonalId", $response->PersonalId ?? null);
            $this->http->SetInputValue("StepUpShieldQuestion.AnswerFormat", $response->StepUpShieldQuestion->AnswerFormat ?? null);
            $this->http->SetInputValue("StepUpShieldQuestion.AnswerMaxLength", $response->StepUpShieldQuestion->AnswerMaxLength ?? null);
            $this->http->SetInputValue("StepUpShieldQuestion.RegisterComputer", "true");

            $this->State["FormURL"] = 'https://onlinebanking.usbank.com/Auth/Login/StepUpCheckWidget';
            $this->State["Form"] = $this->http->Form;

            // question with date
            if ($this->http->Form["StepUpShieldQuestion.AnswerFormat"] == 'DATE6' && $this->http->Form["StepUpShieldQuestion.AnswerMaxLength"] == 6) {
                $question .= ' Enter date as MM/DD/YY';
            }
        }

        if (!isset($question)) {
            $this->logger->error("question not found");

            return false;
        }

        if (!$this->http->ParseForm("formStepUp") && !$this->http->ParseForm("formTempAccessCode") && !$this->State["NewService"]) {
            $this->logger->error("question form not found");

            return false;
        }

        if ($question != self::QUESTION_TSC && !$this->State["NewService"]) {
            $this->http->SetInputValue("MachineAttribute", self::MACHINE_ATTRIBUTE);
            $this->http->SetInputValue("StepUpShieldQuestion.AnswerMaxLength", $this->http->FindSingleNode("//input[@name = 'StepUpShieldQuestion.AnswerMaxLength']/@value"));
            $this->http->SetInputValue("StepUpShieldQuestion.AnswerFormat", $this->http->FindSingleNode("//input[@name = 'StepUpShieldQuestion.AnswerFormat']/@value"));
            $this->http->SetInputValue("StepUpShieldQuestion.QuetionText", $this->http->FindSingleNode("//input[@name = 'StepUpShieldQuestion.QuetionText']/@value"));
            $this->http->SetInputValue("StepUpShieldQuestion.RegisterComputer", "true");
            // question with date
            if ($this->http->Form["StepUpShieldQuestion.AnswerFormat"] == 'DATE6' && $this->http->Form["StepUpShieldQuestion.AnswerMaxLength"] == 6) {
                $question .= ' Enter date as MM/DD/YY';
            }
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        // refs #16554
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if ($this->attempt > 0 && (!isset($this->http->Response['code']) || $this->http->Response['code'] == 400)) {
//            $this->sendNotification("usbank - OTP was entered");
            unset($this->Answers[self::QUESTION_OTP]);

            if ($this->LoadLoginForm() && $this->Login()) {
                return true;
            }

            return $this->checkErrors();
        }// if ($this->attempt > 0 && (!isset($this->http->Response['code']) || $this->http->Response['code'] == 400))

        $headers = [];

        if (strstr($this->Question, self::QUESTION_OTP)) {
            $this->data = $this->State["Data"];
            $this->logger->debug(var_export($this->data, true), ['pre' => true]);

            $this->http->RetryCount = 0;
            $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$this->data['assertion_id_sms']}\",\"action\":\"authentication\",\"fch\":\"{$this->data['challenge']}\",\"method\":\"otp\",\"data\":{\"otp\":\"{$this->Answers[$this->Question]}\"},\"assert\":\"authenticate\"}}";
            $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
            $questionResponse = $this->http->JsonLog();
            unset($this->Answers[$this->Question]);

            $this->logger->debug("challenge: {$this->data['challenge']}");
            $this->logger->debug("device_id: {$this->data['device_id']}");
            $this->logger->debug("session_id: {$this->data['session_id']}");

            if (isset($this->data['assert_qa'])) {
                $this->logger->debug("assert_qa: {$this->data['assert_qa']}");
                $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$this->data['assert_qa']}\",\"action\":\"json_data\",\"fch\":\"{$this->data['challenge']}\",\"assert\":\"action\"}}";
                $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, array_merge($this->headers, ["User-Agent" => HttpBrowser::PROXY_USER_AGENT]));
                $this->http->RetryCount = 2;
                $jsonDataResponse = $this->http->JsonLog();
            } elseif ($questionResponse->data->control_flow && empty($this->data['assertion_id_password'])) {
                $this->logger->notice("set 'assertion_id_password'");

                foreach ($questionResponse->data->control_flow as $flow) {
                    if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld') {
                        $this->data['assertion_id_password'] = $flow->methods[0]->assertion_id;
                        $this->logger->debug("assertion_id_password: {$this->data['assertion_id_password']}");
                    }// if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld')
                }// foreach ($response->data->control_flow as $flow)
            }// elseif ($response->data->control_flow && empty($this->data['assertion_id_password']))

            if (isset($jsonDataResponse->error_message)) {
                if (strstr($jsonDataResponse->error_message, 'Auth session not found for device ')
                    || strstr($jsonDataResponse->error_message, "Invalid UUID field ''did' with value '")) {
                    throw new CheckRetryNeededException(3);
                }
            }// if (isset($jsonDataResponse->error_message))

            if (!$this->checkErrors()) {
                return false;
            }

            if (!$this->sendPassword($this->State["Response"] ?? null)) {
                return false;
            }

            if ($this->loginSuccessful()) {
                return true;
            }
        } elseif (isset($this->State["NewService"]) && $this->State["NewService"] === true) {
            $headers = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json",
            ];
            $data = [
                'sensor_data' => "7a74G7m23Vrp0o5c9091921.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,385299,6753777,1440,829,1440,900,1440,390,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8969,0.241315597120,782978376888.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-102,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;0,0,1,1,1379,1041,0;0,0,1,1,1379,1041,0;1,-1,1,1,1144,883,0;1,-1,1,1,1144,883,0;0,0,0,0,1704,1443,0;-1,2,-94,-108,0,1,2545,91,0,2,1041;1,1,2592,86,0,2,1041;2,2,2730,-2,0,0,1041;3,1,5849,91,0,2,883;4,1,5925,86,0,2,883;5,2,6061,-2,0,0,883;6,1,6779,91,0,2,843;7,1,6883,18,0,3,843;8,1,7111,73,0,3,843;9,2,7300,91,0,1,843;10,2,7315,18,0,0,843;-1,2,-94,-110,0,1,1253,309,91;1,1,1262,315,89;2,1,1271,321,87;3,1,1393,465,99;4,1,1558,478,99;5,1,1572,511,106;6,1,1589,566,117;7,1,1609,697,145;8,1,1622,799,164;9,1,1639,944,195;10,1,1656,1054,228;11,1,1672,1067,236;12,1,1838,1058,232;13,1,1855,1059,232;14,1,1873,1065,232;15,1,1889,1072,235;16,1,1905,1080,239;17,1,1924,1094,247;18,1,1939,1104,255;19,1,1956,1115,266;20,1,1973,1127,278;21,1,1990,1137,290;22,1,2013,1149,304;23,1,2025,1157,312;24,1,2042,1162,316;25,1,2056,1166,320;26,1,2072,1169,323;27,1,2089,1171,324;28,1,2112,1172,325;29,1,2143,1172,325;30,1,2171,1172,326;31,1,2192,1172,326;32,1,2209,1171,326;33,1,2225,1170,327;34,1,2255,1169,327;35,1,2271,1169,327;36,1,2288,1168,328;37,1,2305,1168,328;38,3,2368,1168,328,1041;39,4,2479,1168,328,1041;40,2,2481,1168,328,1041;41,1,5170,721,379;42,1,5172,772,398;43,1,5188,828,414;44,1,5206,886,426;45,1,5223,943,435;46,1,5239,982,441;47,1,5256,1025,446;48,1,5274,1047,448;49,1,5289,1069,450;50,1,5306,1077,450;51,1,5322,1081,450;52,1,5339,1082,450;53,1,5356,1082,450;54,1,5373,1083,450;55,1,5435,1083,450;56,1,5461,1084,450;57,1,5472,1085,450;58,1,5488,1087,451;59,1,5506,1089,451;60,1,5522,1090,452;61,1,5538,1091,452;62,1,5555,1092,452;63,1,5574,1094,452;64,1,5590,1100,451;65,1,5605,1108,451;66,1,5623,1119,451;67,1,5639,1131,451;68,1,5656,1136,452;69,1,5664,1139,453;70,3,5664,1139,453,883;71,4,5762,1139,453,883;72,2,5763,1139,453,883;73,1,6006,1139,457;74,1,6022,1139,469;75,1,6039,1139,482;76,1,6055,1141,494;77,1,6060,1141,503;78,1,6072,1143,513;79,1,6090,1145,537;80,1,6106,1146,545;81,1,6124,1146,551;82,1,6140,1146,555;83,1,6155,1146,556;84,1,6173,1146,557;85,1,6189,1146,558;86,1,6206,1146,558;87,1,6222,1144,559;88,1,6239,1143,560;89,1,6256,1142,560;90,1,6274,1140,560;91,1,6289,1137,560;92,1,6306,1136,560;93,1,6322,1135,559;94,3,6327,1135,559,843;95,4,6442,1135,559,843;96,2,6443,1135,559,843;97,1,7742,1065,389;98,1,7742,1065,389;99,1,7749,1067,389;100,1,7757,1068,389;101,1,7765,1069,389;102,1,7774,1070,389;103,1,7781,1070,389;104,1,7798,1071,389;105,1,7806,1072,389;106,1,7816,1072,389;107,1,8271,1061,389;108,1,8319,694,386;242,3,21444,1208,311,1443;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,3276;1,4594;3,4595;3,7567;3,7567;0,10528;1,13194;3,21443;-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,71806,681559,0,0,0,0,753363,21444,0,1565956753777,18,16752,11,243,2792,7,0,21446,578675,0,4FF83ECD87ABF5E1303308574DC513D7~-1~YAAQb6w4F7Xfqn9sAQAAdQdMmgLRrO4eIYrF3sUYrrrgWMuByf23CnB3YVuADpHEggc6TZBXJxKEVsGkO5Hukyth6JuBdGl2fhinxgDuAa3Q6hAKC6hzbOOtxXmio33s6PveC7/DE3gRmu4CKAwgSz2RilO0lv2o+pe326xwZJ3Vje2x+TaD34m0oaaPVxHVs7GgcsIsVc1XyAnZnncxit4uqAYBX9TYTdF5SQEsqbl8AHN/gM8F2W+/Grked8Fixp5U51lTRKNwMyPz2ICeHId4qdKTLz0+eKCprRtyZzJcRMwj+hx7Q1Gx~-1~-1~-1,29673,374,-52999179,30261693-1,2,-94,-106,1,4-1,2,-94,-119,7,9,9,10,23,21,12,8,8,7,7,6,11,426,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,2130238721;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4989-1,2,-94,-116,6753755-1,2,-94,-118,206422-1,2,-94,-121,;3;13;0",
            ];
            $this->http->PostURL("https://www.usbank.com/assets/49ef690f171747d9bc92943d5cc5", json_encode($data), $headers);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;
            sleep(1);

            $this->http->FormURL = $this->State["FormURL"];
            $this->http->Form = $this->State["Form"];
            $headers = ["Accept" => "application/json, text/plain, */*"];

            if (isset($this->State["Version"]) && $this->State["Version"] == 2) {
                $this->http->SetInputValue("Answer", $this->Answers[$this->Question]);
            } else {
                $this->http->SetInputValue("StepUpShieldQuestion.Answer", $this->Answers[$this->Question]);
            }
        } elseif ($this->Question == self::QUESTION_TSC) {
            $this->logger->notice("sending Temporary Access Code");
            $this->http->SetInputValue("TempAccessCode", $this->Answers[$this->Question]);
        } else {
            $this->logger->notice("sending security answer");
            // date
            if (isset($this->http->Form["StepUpShieldQuestion.AnswerFormat"], $this->http->Form["StepUpShieldQuestion.AnswerMaxLength"])
                && $this->http->Form["StepUpShieldQuestion.AnswerMaxLength"] == 6
                && $this->http->Form["StepUpShieldQuestion.AnswerFormat"] == 'DATE6') {
                $answer = explode('/', $this->Answers[$this->Question]);

                if (isset($answer[0], $answer[1], $answer[2])) {
                    if (strlen($answer[2]) == 4) {
                        $answer[2] = substr($answer[2], 2, 2);
                    }
                    $this->Answers[$this->Question] = $answer[0] . $answer[1] . $answer[2];
                    $this->logger->debug("Answer fix: {$this->Answers[$this->Question]}");
                }// if (isset($answer[0], $answer[1], $answer[2]))
            }// date
            $this->http->SetInputValue("StepUpShieldQuestion.Answer", $this->Answers[$this->Question]);
        }

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->Token, $this->State["Version"]) && $this->State["Version"] == 2) {
            $this->logger->notice("ProcessStep Version: {$this->State["Version"]}");
            $this->data = $this->State["Data"];
            $this->logger->debug(var_export($this->data, true), ['pre' => true]);

            $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$this->data['assertion_question']}\",\"action\":\"authentication\",\"fch\":\"{$this->data['challenge']}\",\"method\":\"placeholder_qa\",\"data\":{\"token\":\"{$response->Token}\"},\"assert\":\"authenticate\"}}";
            $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
            $response = $this->http->JsonLog();

            // send assert_qa
            $this->logger->debug("challenge: {$this->data['challenge']}");
            $this->logger->debug("device_id: {$this->data['device_id']}");
            $this->logger->debug("session_id: {$this->data['session_id']}");

            if (isset($this->data['assert_qa'])) {
                $this->logger->debug("assert_qa: {$this->data['assert_qa']}");
                $data = "{\"headers\":[],\"data\":{\"assertion_id\":\"{$this->data['assert_qa']}\",\"action\":\"json_data\",\"fch\":\"{$this->data['challenge']}\",\"assert\":\"action\"}}";
                $this->http->PostURL("https://www.usbank.com/Proxy/TS/api/v2/web/assert?aid=web&uid={$this->AccountFields['Login']}&did={$this->data['device_id']}&sid={$this->data['session_id']}", $data, $this->headers);
                $jsonDataResponse = $this->http->JsonLog();
            } elseif (isset($response->data->state, $response->data->token) && $response->data->state == 'completed') {
                $this->logger->notice("SignonWithTransmit");
                $userid = strtolower($this->AccountFields['Login']);

                if (isset($response->headers) && is_array($response->headers)) {
                    foreach ($response->headers as $header) {
                        if ($header->type == 'device_id') {
                            $device_id = $header->device_id;
                        }

                        if ($header->type == 'session_id') {
                            $session_id = $header->session_id;
                        }
                    }// foreach ($authData->headers as $header)
                }// if (isset($authData->headers) && is_array($authData->headers))

                if (!isset($device_id)) {
                    return false;
                }

                $data = [
                    "Policy"     => "password_login",
                    "ClientName" => "OLB",
                    "DeviceID"   => $this->data['device_id'],
                    "Token"      => $response->data->token,
                    "UserId"     => $userid,
                    "AppId"      => "web",
                    "TSParams"   => [
                        [
                            "Key"   => "ts:sessionId:{$userid}",
                            "Value" => $this->data['session_id'],
                        ],
                        [
                            "Key"   => "ts:userid",
                            "Value" => $userid,
                        ],
                        [
                            "Key"   => "currentSession",
                            "Value" => "{\\\"session_id\\\":\\\"{$session_id}\\\",\\\"user_name\\\":\\\"{$userid}\\\",\\\"device_id\\\":\\\"{$this->data['device_id']}\\\",\\\"invalidated\\\":false,\\\"user\\\":{\\\"default_auth_id\\\":\\\"placeholder_qa\\\",\\\"device_bound\\\":false,\\\"has_logged_in\\\":false,\\\"device_id\\\":\\\"{$this->data['device_id']}\\\",\\\"last_auth\\\":\\\"null\\\",\\\"guid\\\":\\\"F9C5849FD60E1ED3D3DE6872DFCFB28C\\\",\\\"user_id\\\":\\\"{$userid}\\\",\\\"user_number\\\":\\\"\\\",\\\"schemeVersion\\\":\\\"v0\\\"},\\\"persistUserData\\\":true}",
                        ],
                        [
                            "Key"   => "ts:usertkn",
                            "Value" => $response->data->token,
                        ],
                        [
                            "Key"   => "ts:deviceId:{$userid}",
                            "Value" => $this->data['device_id'],
                        ],
                        [
                            "Key"   => "ts:appid",
                            "Value" => "web",
                        ],
                    ],
                    "IsGenerateDeviceToken" => false,
                ];
                $headers = [
                    "Accept"       => "application/json, text/plain, */*",
                    "Content-Type" => "application/json\"",
                ];
                $this->http->PostURL("https://www.usbank.com/Auth/Signon/SignonWithTransmit", json_encode($data), $headers);
                $response = $this->http->JsonLog();
                $this->finalRedirect($response);
            } elseif (isset($response->data->control_flow) && empty($this->data['assertion_id_password'])) {
                $this->logger->notice("set 'assertion_id_password'");

                foreach ($response->data->control_flow as $flow) {
                    if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld') {
                        $this->data['assertion_id_password'] = $flow->methods[0]->assertion_id;
                        $this->logger->debug("assertion_id_password: {$this->data['assertion_id_password']}");
                    }// if (isset($flow->methods[0]->assertion_id) && $flow->methods[0]->type == 'placeholder_password_pld')
                }// foreach ($response->data->control_flow as $flow)
            }// elseif ($response->data->control_flow && empty($this->data['assertion_id_password']))

            if (isset($jsonDataResponse->error_message)) {
                if (
                    strstr($jsonDataResponse->error_message, "Invalid UUID field ''did' with value '")
                    || strstr($jsonDataResponse->error_message, "Auth session not found for device")
                ) {
                    $this->sendNotification("retry // RR");

                    throw new CheckRetryNeededException(3, 1);
                }
            }// if (isset($jsonDataResponse->error_message))
        }// if (isset($response->Token, $this->State["Version"]) && $this->State["Version"] == 2)

        // Invalid answer
        if ($this->http->FindSingleNode('//input[contains(@value, "The security answer you entered doesn\'t match our records.")]/@value')) {
            $this->parseQuestion();

            return false;
        }
        // Invalid answer
        if (isset($response->ErrorMessage) && $response->ErrorMessage != 'Success') {
            $this->logger->error($response->ErrorMessage);

            if (strstr($response->ErrorMessage, "Hmm. That answer doesn’t match our records. Please try again.") && isset($response->StepUpShieldQuestion->QuetionText)) {
                $this->AskQuestion($response->StepUpShieldQuestion->QuetionText, $response->ErrorMessage);
            }
            // For your security, your Personal ID has been locked.
            if (strstr($response->ErrorMessage, "For your security, your Personal ID has been locked.")) {
                throw new CheckException("For your security, your Personal ID has been locked.", ACCOUNT_LOCKOUT);
            }
            // Unexpected Error Occur.
            if (strstr($response->ErrorMessage, "Unexpected Error Occur.")) {
                throw new CheckException($response->ErrorMessage, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if (isset($response->ErrorMessage) && $response->ErrorMessage != 'Success')

        if (!$this->checkErrors()) {
            return false;
        }
        $this->logger->notice("ProcessStep sendPassword");

        if (!$this->sendPassword($this->State["Response"] ?? null)) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // For your security, your Personal ID has been locked.
        if ($message = $this->http->FindSingleNode("//span[@id = 'ValidationOnSubmitError']", null, true, '/(For your security, your Personal ID has been locked\.)/ims')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We're sorry, our system is temporarily unable to display this information
        if ($message = $this->http->FindSingleNode("//span[@id = 'ValidationOnSubmitError']", null, true, "/(We\'re sorry, our system is temporarily unable to display this information.+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // We're sorry, we're having technical difficulties at the moment. Please try again later. If you're still having trouble call 800-USBANKS (800-872-2657).
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "We\'re sorry, we\'re having technical difficulties at the moment")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // U.S. Bank Online Banking is Currently Unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "U.S. Bank Online Banking is Currently Unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, our system is currently unavailable. Please try again.
        if ($message = $this->http->FindPreg('/errormessage=\"(Sorry, our system is currently unavailable\. Please try again\.)/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Service Unavailable
            ($this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]") && $this->http->Response['code'] == 503)
            || ($this->http->FindPreg("/An error occurred while processing your request\.<p>/") && in_array($this->http->Response['code'], [503, 504]))
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->currentUrl() == 'https://onlinebanking.usbank.com/Auth/AuthError.aspx' && $this->http->Response['code'] == 302) {
            throw new CheckRetryNeededException(3, 15);
        }

        if (strstr($this->http->currentUrl(), ')/UnexpectedError.aspx') && strstr($this->http->currentUrl(), 'https://onlinebanking.usbank.com/USB/af(')) {
            $this->DebugInfo = 'UnexpectedError';

            throw new CheckRetryNeededException(4, 15/*, self::PROVIDER_ERROR_MSG*/);
        }

        return true;
    }

    public function Parse()
    {
        $links = [];
        // Base URL
        if (!isset($this->baseURL)) {
            $this->baseURL = preg_replace("/\/(?:CustomerDashboard.+|USB\/Enrollment\/RedirectToDIYDashboard.+|USB\/([^\/]+)\/AccountDashboard\/Index\/1\/CCD)/ims", '', $this->http->currentUrl());
            $this->State['DashboardURL'] = $this->http->currentUrl();
        }
        $baseURL = $this->baseURL;
        $this->logger->debug("[Base URL]: {$baseURL}");

        if (strstr($this->http->currentUrl(), 'Enrollment/Enrollment') && $this->http->FindSingleNode('//strong[contains(text(), "To accept Terms and Agreements, take the following steps:")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->accesstoken && !strstr($this->http->currentUrl(), '/CustomerDashboard/Index') /* AccountID: 1828038, 4103552, 1828038, 3202602 */) {
            $this->State['accesstoken'] = $this->accesstoken;
            $this->State['aftokenvalue'] = $this->aftokenvalue;

            $this->redirectToDashboard($this->accesstoken, $this->aftokenvalue);
            $this->exportToEditThisCookies();

            $cardsInfo = [];
            $rewardCards = 0;

            if (empty($this->accesstoken)) {
                $this->logger->error("accesstoken not found");

                return;
            }

            $headers = [
                "Accept"          => "*/*",
                "aftokenvalue"    => $this->aftokenvalue,
                "routingKey"      => "",
                "authorization"   => "Bearer {$this->accesstoken}",
                "Service-Version" => 2,
                "Content-Type"    => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query {\n    \n    getGroupedCDAccountSummary{\n        cdGroupedAccountsList{  \n            accountGroup      \n            accountsSummary{        \n                financialInstitute        \n                companyId       \n                accountHeld        \n                accountNumber        \n                accountToken        \n                accountTypeCode        \n                accountType        \n                categoryDescription        \n                nickname       \n                displayName        \n                lastActivityDate        \n                balanceType        \n                accountBalance        \n                adjustedAvailableBalance        \n                currentBalance        \n                availableCredit        \n                minimumAmountDue        \n                nextPaymentDate        \n                productCode        \n                subProductCode\n                travelRewardsIndicator\n                rewardsTypeCode\n                currentRewardsBalance\n                currentRewardsBalancePoints\n                accountSuppressReason  \n                onlineCollectionEligibility\n                autoGraceCode   \n                maturityDate\n                accountIndex \n                nextPaymentAmount\n            }      \n            totalAmount  \n        }\n        cdWealthSummary{\n            assetTotal\n            liabilityTotal\n        }\n    }\n    getCustomerService{\n        customers {\n            customerTypeCode\n        }\n    }\n}"}', $headers);
            $response = $this->http->JsonLog(null, 5, false, "currentRewardsBalancePoints");
            $cdGroupedAccountsList = $response->data->getGroupedCDAccountSummary->cdGroupedAccountsList ?? [];

            foreach ($cdGroupedAccountsList as $accountsList) {
                if ($accountsList->accountGroup != 'creditCards') {
                    continue;
                }
                $accounts = $accountsList->accountsSummary ?? [];
                // only Credit Cards & Credit Lines
                $this->logger->debug("Total " . count($accounts) . " cards were found");

                foreach ($accounts as $account) {
                    $displayName = $account->displayName;
                    $this->logger->info($displayName, ['Header' => 3]);
                    $code = $account->accountNumber;

                    if (!empty($displayName) && !empty($code)) {
                        $this->AddDetectedCard([
                            "Code"            => 'usbank' . $code,
                            "DisplayName"     => $displayName,
                            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                        ]);
                    }

                    $subAcc = [
                        'Code'        => 'usbank' . $code,
                        'DisplayName' => $displayName,
                    ];

                    if ($account->rewardsTypeCode === null || $account->rewardsTypeCode === "NON") {
                        $rewardCards++;
                    } elseif ($account->rewardsTypeCode === "PT") {
                        $subAcc['Balance'] = $account->currentRewardsBalancePoints;
                    }

                    // https://onlinebanking.usbank.com/USB/af(Z0ShOlRuvjxnSRlg70l)/RewardsCenterDashboard/RewardsCenterDashboard.aspx
                    $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query getAccountDetails($token: String!){\n  getAccountDetails(accountToken: $token){\n    locAccount {\n      settlementFlag\n      accountNumber \n      productCode\n      partnerCode \n      subProductCode \n      routingTransitNumber \n      lastStatementDate  \n      availableCredit \n      nextPaymentAmount\n      currentBalanceFromAccountSummary\n      adjustedBalanceAmountFromAccountSummary\n      availableCreditFromAccountSummary\n      accountType \n      nextPaymentDate \n      principalBalance\n      adjustedBalanceAmount  \n      currentBalanceAmount \n      minimumPaymentAmount \n      nickname\n      lastPaymentAmount \n      lastPaymentDate \n      pastDueAmount \n      lastStatementBalance \n      pendingDebits \n      creditLineAmount \n      totalCreditsSinceLastStatement \n      totalDebitsSinceLastStatement \n      nextStatementDate \n      availableCash \n      yearToDateInterestAssessedAmount \n      servicingLinks{ \n        id \n        url \n        clickableType \n        servicingGroupName \n        sequence \n        metaData{\n          key\n          value\n        }\n      }\n      rewardsDetails {\n        programName \n        currentBalanceAmount \n        currentBalanceNumber \n        earnedInCurrentCycleNumber\n        travelRewardsIndicator\n        rewardsTypeCode\n      } \n    }\n  }\n}","variables":{"token":"' . $account->accountToken . '"}}', $headers);
                    $locAccount = $this->http->JsonLog(null, 5);
                    $servicingLinks = $locAccount->data->getAccountDetails->locAccount->servicingLinks ?? [];

                    foreach ($servicingLinks as $servicingLink) {
                        if ($servicingLink->id != "rewardsDetails") {
                            continue;
                        }

                        if ($servicingLink->url === null) {
                            $cardsInfo[] = [
                                'link'       => '{"query":"query getVendorSSO($inputObj: SSOReq!) {  \n  getVendorSSO(inputObj: $inputObj) {     \n    url    \n    action    \n    samlResponse      \n  }\n}","variables":{"inputObj":{"ssoType":"rewards","accountToken":"' . $account->accountToken . '"}}}',
                                'subAccount' => $subAcc,
                            ];
                        } else {
                            $cardsInfo[] = [
                                'link'       => $baseURL . $servicingLink->url,
                                'subAccount' => $subAcc,
                            ];
                        }

                        break;
                    }
                }
                $subAccounts = [];

                foreach ($cardsInfo as $cardInfo) {
                    $this->logger->info("Details for {$cardInfo['subAccount']['DisplayName']}", ['Header' => 3]);
                    $this->logger->notice("[Open link]: {$cardInfo['link']}");
                    $json = false;

                    if (strstr($cardInfo['link'], 'accountToken')) {
                        $json = true;
                        $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", $cardInfo['link'], $headers);
                        $samlInfo = $this->http->JsonLog();

                        if (!isset($samlInfo->data->getVendorSSO->url)) {
                            // AccountID: 1981380, 2044519, 3299404
//                            if (isset($samlInfo->errors[0]->message) && $samlInfo->errors[0]->message == 'Rewards SSO API currently supports External Rewards only. Please input with a valid account token') {
//                            }

                            continue;
                        }
                        $data = [
                            "url"          => $samlInfo->data->getVendorSSO->url,
                            "SAMLResponse" => $samlInfo->data->getVendorSSO->samlResponse,
                        ];
                        $this->http->PostURL($samlInfo->data->getVendorSSO->url, $data);
                    } else {
                        $this->logger->debug(var_export($cardInfo['link'], true), ["pre" => true]);
                        $cardInfo['link'] = str_replace("USB/Enrollment/RewardsCenterDashboard", "USB/{$this->aftokenvalue}/RewardsCenterDashboard", $cardInfo['link']);

                        $this->http->GetURL($cardInfo['link']);
                    }
                    $subAccounts = array_merge($subAccounts, $this->parseCard($cardInfo['link'], $baseURL, true, $json, $cardInfo['subAccount']));
                }// foreach ($links as $link)
                $this->logger->debug("accounts: " . count($accounts));
                $this->logger->debug("rewardCards: " . $rewardCards);

                if (!empty($subAccounts)) {
                    $this->Properties['SubAccounts'] = $subAccounts;
                    $this->SetBalanceNA();
                } elseif (
                    // AccountID: 2044519
                    count($accounts) == $rewardCards
                    // AccountID: 3559394
                    || count($accounts) == 1 && $this->http->FindPreg("/RCDashboardHelper.RewardsDetails = \{\"MyRewardCardDetails\":\[\],\"Success\":false,\"ErrorMsg\":\"Failure\",\"StatusCode\":0,\"ErrorCode\":null\};/")
                ) {
                    $this->SetBalanceNA();
                }
            }

            $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query{getNavigation(types:[NavBar,Header])}","variables":null}', $headers);
            $response = $this->http->JsonLog();

            foreach ($response->data->getNavigation as $nav) {
                if ($nav->type == "Header") {
                    foreach ($nav->navigationList as $list) {
                        if ($list->id != 'CustomerName') {
                            continue;
                        }
                        $this->SetProperty("Name", beautifulName($list->metaData->firstName ?? null));
                    }

                    break;
                }
            }// foreach ($response->data->getNavigation as nav)

            // FICO
            $this->logger->info('FICO® Score', ['Header' => 3]);
            $this->http->PostURL("https://api.usbank.com/customer-management/graphql/v1", '{"query":"query {\n  getCreditScoreService {\n\t\tscoreDifference\n    valCurr\n    isEnrolledForCS\n    enrollmentStatus\n    isDropped\n    scoreTrendPoints {\n      score\n      scoreDate\n    }\n  }\n}\n"}', $headers);
            $ficoInfo = $this->http->JsonLog();

            if (
                isset($ficoInfo->data->getCreditScoreService->isEnrolledForCS, $ficoInfo->data->getCreditScoreService->enrollmentStatus)
                && $ficoInfo->data->getCreditScoreService->isEnrolledForCS == true
                && $ficoInfo->data->getCreditScoreService->enrollmentStatus == 'Enrolled'
            ) {
                $fcioUpdatedOn = null;

                foreach ($ficoInfo->data->getCreditScoreService->scoreTrendPoints as $scoreTrendPoint) {
                    if (!isset($fcioUpdatedOn) || strtotime($fcioUpdatedOn) < strtotime($scoreTrendPoint->scoreDate)) {
                        $fcioUpdatedOn = $scoreTrendPoint->scoreDate;
                    }
                }

                if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                    foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                        if (in_array($key, ['Code', 'DisplayName'])) {
                            continue;
                        } elseif ($key == 'Balance') {
                            $this->SetBalance($value);
                        } elseif ($key == 'ExpirationDate') {
                            $this->SetExpirationDate($value);
                        } else {
                            $this->SetProperty($key, $value);
                        }
                    }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                    unset($this->Properties['SubAccounts']);
                }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
                $this->SetProperty("CombineSubAccounts", false);
                $this->AddSubAccount([
                    "Code"               => "usbankFICO",
                    "DisplayName"        => "VantageScore® 3.0 (TransUnion)",
                    "Balance"            => $ficoInfo->data->getCreditScoreService->valCurr,
                    "FICOScoreUpdatedOn" => $fcioUpdatedOn,
                ]);
            }

            return;
        }

        // Name
        $name = $this->http->FindSingleNode("//div[contains(text(), 'Welcome,')] | //a[contains(text(), 'Hi,')]", null, true, '/\,s*([^<\.]+)/ims');

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // only Credit Cards & Credit Lines
        $accounts = $this->http->XPath->query("//table[@id = 'CreditsTable']//td[contains(@class, 'accountRowFirst') and a]");
        $this->logger->debug("Total {$accounts->length} cards were found");

        for ($i = 0; $i < $accounts->length; $i++) {
            $code = $this->http->FindSingleNode("a", $accounts->item($i), true, '/\-\s*(\d+)\s*$/ims');
            $displayName = $this->http->FindSingleNode("a", $accounts->item($i));

            if (!empty($displayName) && !empty($code)) {
                $this->AddDetectedCard([
                    "Code"            => 'usbank' . $code,
                    "DisplayName"     => $displayName,
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ]);
            }
            // https://onlinebanking.usbank.com/USB/af(rSouW0P2hNnmPQzR6WZW)/AccountDashboard/Index/2/EXL
            $onclick = explode(',', $this->http->FindSingleNode("a/@onclick", $accounts->item($i), true, '/CD_OpenAccountDashboard\(([^\)]+)/ims'));

            if (isset($onclick[0], $onclick[1])) {
                $onclick[1] = trim(str_replace('"', '', $onclick[1]));
                $links[] = str_replace("CustomerDashboard", "AccountDashboard", $this->http->currentUrl()) . "/" . $onclick[0] . "/" . $onclick[1];
            }// if (isset($onclick[0], $onclick[1]))
        }// for ($i = 0; $i < $accounts->length; $i++)
        $subAccounts = [];

        foreach ($links as $link) {
            $this->logger->notice("[Open link]: {$link}");
            $this->http->GetURL($link);
            $subAccounts = array_merge($subAccounts, $this->parseCard($link, $baseURL));
        }// foreach ($links as $link)

        if (empty($subAccounts)) {
            $baseURL = preg_replace("/\/AccountDashboard.+/ims", '', $this->http->currentUrl());
            $this->logger->debug("[Base URL]: {$baseURL}");
            $subAccounts = $this->parseCard($this->http->currentUrl(), $baseURL);
        }

        if (!empty($subAccounts)) {
            $this->Properties['SubAccounts'] = $subAccounts;
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $depositAccounts = $this->http->FindNodes("//table[@id = 'DepositAccountsTable']//td[contains(@class, 'accountRowFirst') and a]");
            $loansAccounts = $this->http->FindNodes("//table[@id = 'LoansLeasesTable']//td[contains(@class, 'accountRowFirst') and span/a]");
            // You don't have any accounts that are eligible for these kinds of transaction.
            $doNotHaveAccounts = $this->http->FindSingleNode('//div[@id = "MMWidgetTransferOneDDATableDiv"]//div[contains(text(), "You don\'t have any accounts that are eligible for these kinds of transaction.")]');
            // AccountID: 78044
            $doNotHaveAccounts2 = $this->http->FindSingleNode('//a[contains(text(), "Click here to review the specific changes being made to your Online and Mobile Financial Services Agreement")]');

            if ((
                    count($links) > 0
                    || count($depositAccounts) > 0
                    || count($loansAccounts) > 0
                    || $doNotHaveAccounts != null
                    || $doNotHaveAccounts2 != null
                    || $this->http->FindSingleNode("//div[@id = 'NickName' and contains(text(), 'Equity Line')]")
                    || $this->http->FindSingleNode("//div[@id = 'NickName' and contains(text(), 'Loan')]")
                    || (
                            !empty($this->Properties['Name'])
                            && empty($this->Properties['SubAccounts'])
                            && !empty($this->Properties['DetectedCards'])
                            && count($this->Properties['DetectedCards']) == 1
                            && $this->http->FindSingleNode("//div[@id = 'NickName']", null, true, "/^\s*(?:Credit Card|Worldperks C-Card \(Credit Card\)|Olympics \(Credit Card\)) - \d+\s*$/")
                    )
                    /**
                     * hard code for error:
                     * "This account is inactive or has been closed. It will be removed from the system shortly.".
                     *
                     * AccountID: 857114
                     */
                    || in_array($this->AccountFields['Login'], [
                        'craiper07',
                        'GrantMestnik',
                    ])
                )
//                && !empty($this->Properties['Name'])
            ) {
                $this->SetBalanceNA();
            }

            if (strstr($this->http->currentUrl(), 'Enrollment/Enrollment')) {
                $this->throwAcceptTermsMessageException();
            }
            // Set up ID Shield Questions
            if (strstr($this->http->currentUrl(), 'EnrollmentDesktop/IDShieldQA')) {
                $this->throwProfileUpdateMessageException();
            }
            // Our system is currently "Read Only" due to a technical issue that we are working to quickly fix.
            if ($maintenance = $this->http->FindSingleNode("//div[not(@style='display: none')]/div[not(contains(@class, 'hide'))]/p[contains(text(), 'Our system is currently \"Read Only\" due to a technical issue that we are working to quickly fix.')]")) {
                throw new CheckException($maintenance, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    public function parseCard($link, $baseURL, $graphql = false, $json = false, $cardInfo = [])
    {
        $this->logger->notice(__METHOD__);
        // Code
        $subAccount["Code"] = $this->http->FindSingleNode("//div[@id = 'NickName']", null, true, '/\-\s*(\d+)\s*$/ims');
        // DisplayName
        $subAccount['DisplayName'] = Html::cleanXMLValue($this->http->FindSingleNode("//div[@id = 'NickName']"));

        $this->logger->info("Details for {$subAccount['DisplayName']}", ['Header' => 3]);

        // Cash balance
        $balance = $this->http->FindSingleNode("//span[contains(text(), 'Rewards Balance')]/following-sibling::span[contains(@class, 'BalanceValue')]", null, true, '/([\$\.\d\-]+)\s*/ims');
        $this->logger->debug(">>> Cash balance: " . $balance);

        if (isset($balance) && strstr($balance, '$')) {
            $subAccount['Currency'] = 'cash';
        }
        $details = strtolower($this->http->FindSingleNode("//a[@id = 'RewardsHistory_Balance']"));
        $this->logger->notice(">>> Details: " . $details);
        $this->logger->debug(var_export($subAccount, true), ["pre" => true]);
        $index = $this->http->FindPreg("/\/(?:AccountDashboard\/Index\/|RewardsCenterDashboard\.aspx\?id=)(\d+)(?:\/|&)/ims", false, $link);

        if (
            ($details == 'rewards details' && isset($index))
            || ($graphql === true)
        ) {
            if ($graphql === false) {
                $this->logger->notice("Get Rewards... Step 1");
                // Get SAMLResponse
                $this->http->setDefaultHeader("Referer", $link);
                $this->http->setDefaultHeader("Content-Type", "application/json; charset=utf-8");
                $this->http->PostURL($baseURL . "/AccountDashboard/GetSelectedAccountInformationByIndex",
                '{"AccountIndex":' . $index . '}');
                $this->logger->notice("Get Rewards... Step 2");
                $this->http->setDefaultHeader("Referer", $link);
                $this->http->setDefaultHeader("Content-Type", "application/json; charset=utf-8");
                $this->http->PostURL($baseURL . "/RewardsCenterDashboard/GetRewardsAccountDetailsByIndex",
                '{"request":{"AccountIndex":' . $index . '}}');

                // for DisplayName
                $this->logger->notice("Get Rewards (Cash)... Step 3");
                $this->http->GetURL($baseURL . "/RewardsCenterDashboard/RewardsCenterDashboard.aspx?id={$index}&tabID=1");
            }
            $body = $this->http->FindPreg("/RCDashboardHelper.RewardsDetails =\s*([^\;]+)/ims");
            $response = $this->http->JsonLog($body, 0);

            if (isset($response->MyRewardCardDetails)) {
                if (
                    $graphql === true
                    && $this->http->FindPreg("/RCDashboardHelper.RewardsDetails = \{\"MyRewardCardDetails\":\[\],\"Success\":false,\"ErrorMsg\":\"Failure\",\"StatusCode\":0,\"ErrorCode\":null\};/")
                ) {
                    return [];
                }

                foreach ($response->MyRewardCardDetails as $card) {
                    if (
                        isset($card->Account->AccountIndex)
                        && (
                            $card->Account->AccountIndex == $index
                            || $graphql === true && count($response->MyRewardCardDetails) == 1// AccountID: 1945086, 5347634
                        )
                    ) {
                        $this->logger->info("Card summary...");
                        $this->logger->debug(var_export($card, true), ["pre" => true]);
                        // DisplayName
                        $subAccount['DisplayName'] = $card->Account->ProductDisplayName;
                        $this->logger->debug("DisplayName: {$subAccount['DisplayName']}");
                        // refs #7629
                        if (strstr($subAccount['DisplayName'], 'Club Carlson')
                            || strstr($subAccount['DisplayName'], 'LANPASS')) {
                            $balance = $card->PendingRewards;
                        }

                        if ($graphql === true) {
                            $subAccount['Code'] = $card->Account->Last4Digits;
                            $balance = $card->CurrentRewardsBalanceString ?? $cardInfo['Balance'];
                        }
                        $this->logger->debug("Pending Balance [Club Carlson, LANPASS]: $balance");
                        // Total Miles Earned Year to Date
                        if (isset($card->CurrentYearEarnedRewards)) {
                            $subAccount['TotalMilesEarnedYTD'] = $card->CurrentYearEarnedRewards;
                        }
                    }// if (isset($card->Account->AccountIndex) && $card->Account->AccountIndex == $index)
                }// foreach ($response->MyRewardCardDetails as $card)
            }// if (isset($response->MyRewardCardDetails))
            // Point Rewards
            if (!isset($balance)) {
                if ($json === false) {
                    $this->logger->notice("Get Rewards (SAMLResponse)... Step 3");
                    $this->http->setDefaultHeader("Referer", $link);
                    $this->http->setDefaultHeader("Content-Type", "application/json; charset=utf-8");
                    $this->http->PostURL($baseURL . "/RewardsCenterDashboard/GetEpsilonSSOUrlByIndex",
                        '{"index":' . $index . ',"returnUrl":"' . $baseURL . '/CustomerDashboard/Index","heartBeat":"' . $baseURL . '/CFWebPay/CompleteConnectVoyagerPing.ashx","errorURL":"' . $baseURL . '/UnexpectedError.aspx","timeoutURL":"' . $baseURL . '/SessionTimeout.aspx","logoutURL":"https://onlinebanking.usbank.com/Auth/LogoutConfirmation"}');
                    $response = $this->http->JsonLog();
                }

                if (
                    isset($response->pingSSOUrl, $response->pingSAMLVal)
                    || $json === true
                ) {
                    if ($json === false) {
                        // Post SAMLResponse
                        $this->logger->notice("Post SAMLResponse...");
                        $this->http->setDefaultHeader("Referer", $link);
                        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded");
                        $this->http->RetryCount = 0;
                        $this->http->PostURL($response->pingSSOUrl, ["SAMLResponse" => $response->pingSAMLVal]);
                        $this->http->RetryCount = 2;
                    } else {
                        $subAccount = $cardInfo;
                    }
                    // AccountID: 1934263
                    if (
                        $this->http->currentUrl() == 'https://rewards.usbank.com/sso-inbound-error.html'
                        && ($message = $this->http->FindSingleNode('//h4[contains(text(), "Something is broken, sorry about that.")]'))
                        /*
                        && in_array($this->AccountFields['Login'], [
                            'lavchik',
                            'karinkarlen',
                            'warobson413',
                            'jkish0824',
                            'scottlehto',
                            'pmarmillot',
                            'dpkievit',
                            'Nasuslow',
                        ])
                        */
                    ) {
                        return [];

                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                    // provider bug fix
                    if ($this->http->Response['code'] == 500) {
                        throw new CheckRetryNeededException(3);
                    }

                    // json
                    $this->logger->notice("Loading rewards from json...");
//                    if (!$this->http->GetURL("https://rewards.usbank.com/Points/GetPointSummary?_a=".$this->random())) {
                    if (!$this->http->GetURL("https://rewards.usbank.com/content/usbankrewards/global/services/profile.member.json")) {
                        $this->logger->notice("Provider error message");
                        $this->http->GetURL("https://rewards.usbank.com/home/GetSonarMessage?Location=HP&_a=" . $this->random());
                        $errorMessage = $this->http->JsonLog();

                        if (isset($errorMessage->StatusMessage)
                            && $errorMessage->StatusMessage == 'Please verify your account information or contact Cardmember Service.') {
                            $this->logger->debug(">>> " . $errorMessage->StatusMessage);
                            $this->SetBalanceNA();
                        }// if (isset($errorMessage->StatusMessage) ...
                    }// if (!$this->http->GetURL("https://rewards.usbank.com/Points/GetPointSummary?_a=0.".$this->random()))
                    $response = $this->http->JsonLog();

                    // Name
                    if (empty($this->Properties['Name']) && !empty($response->name)) {
                        $this->SetProperty("Name", beautifulName($response->name));
                    }

//                    if (isset($response->cardEnding)) {
                    if (isset($response->ccLastFour)) {
                        $this->logger->debug("Card ending: " . $response->ccLastFour);
                        $balance = $response->pointBalance ?? null;

                        $this->logger->debug("balance: " . $balance);
                        $this->logger->debug("Currency: " . $response->unitLabel ?? null);
                        $subAccount["Name"] = beautifulName(Html::cleanXMLValue($response->name ?? null));

                        // exp date // refs #13019
                        $this->logger->notice("Loading exp date from json...");
                        $this->http->GetURL("https://rewards.usbank.com/flextrvrwd/en_us/utility/profile/_jcr_content/root/responsivegrid/points_summary_copy.pointssummary.json?params=%7B%22profileID%22%3A%228985f6a3-4a9b-ef31-e053-61c2ef0a5ddf%22%2C%22displayHouseHold%22%3Afalse%2C%22poolingInd%22%3Afalse%2C%22status%22%3A%22A%22%7D&_=" . date("UB"));
                        $response = $this->http->JsonLog(null, 3, false, 'indPointsExpiryDate');
                        $exp = strtotime($response->processedPointsSummary->indPointsExpiryDate);
                        $this->logger->debug("Set ExpirationDate -> {$response->processedPointsSummary->indPointsExpiryDate} / Points: {$response->processedPointsSummary->indPointsExpiring}");

                        if ($response->processedPointsSummary->indPointsExpiring > 0) {
                            $subAccount["ExpirationDate"] = $exp;
                            $subAccount["ExpiringBalance"] = $response->processedPointsSummary->indPointsExpiring;
                        }// if ($response->processedPointsSummary->indPointsExpiring > 0)
                        /*
                        // Currency
                        if (isset($response->PointsName))
                            $this->logger->debug("Currency: ". $response->PointsName);

                        // exp date // refs #13019
                        $this->logger->notice("Loading exp date from json...");
                        $this->http->GetURL("https://rewards.usbank.com/Points/GetPointsToExpire?_a=".$this->random());
                        $response = $this->http->JsonLog();
                        if (isset($response->AllPointsToExpire)) {
                            foreach ($response->AllPointsToExpire as $pointsToExpire) {
                                if (!isset($pointsToExpire->NumPoints, $pointsToExpire->ExpirationDate)) {
                                    $this->sendNotification("usbank - refs #13019. Exp date not found");
                                    continue;
                                }// if (!isset($pointsToExpire->NumPoints, $pointsToExpire->ExpirationDate))
                                $this->logger->debug("Date: {$pointsToExpire->ExpirationDate} / Points: {$pointsToExpire->NumPoints}");
                                if (!isset($exp) || strtotime($pointsToExpire->ExpirationDate) < $exp) {
                                    $exp = strtotime($pointsToExpire->ExpirationDate);
                                    $this->logger->debug("Set ExpirationDate -> {$pointsToExpire->ExpirationDate} / Points: {$pointsToExpire->NumPoints}");
                                    if ($exp < 2524608000) {
                                        $subAccount["ExpirationDate"] = $exp;
                                        $subAccount["ExpiringBalance"] = $pointsToExpire->NumPoints;
                                    }
                                    else
                                        $this->logger->notice("skip too far exp date");
                                }// if (!isset($exp) || strtotime($pointsToExpire->ExpirationDate) < $exp)
                            }// foreach ($response->AllPointsToExpire as $pointsToExpire)
                        }// if (isset($response->AllPointsToExpire))
                        */
                    }// if (isset($response->cardEnding))
                    /**
                     * We are making enhancements to your Rewards Center.
                     *
                     * The site is currently not available
                     *
                     * We apologize for the inconvenience
                     */
                    elseif ($message = $this->http->FindSingleNode("
                                //div[contains(text(), 'We are making enhancements to your Rewards Center')]
                                | //p[contains(text(), 'We are making enhancements to your Rewards Center')]
                        ")
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                    // return to main page
                    $this->logger->notice("Return to main page");
                    $this->http->GetURL("https://rewards.usbank.com/usbservice/handler/logout?resource=/content/usbankrewards/flex/rps/FLEXTRVRWD/en_us/home&outType=backtoacc");
//                    $this->http->GetURL("https://rewards.usbank.com/Login/SSOLogout");
                }// if (isset($response->pingSSOUrl, $response->pingSAMLVal))
            }// if (!isset($balance))
            // save
            $this->logger->debug("Save subAccounts...");
            $this->logger->debug(var_export($subAccount, true), ["pre" => true]);

            if (isset($balance, $subAccount['Code'], $subAccount['DisplayName'])) {
                if (strstr($balance, '$') || strstr($subAccount['DisplayName'], 'Cash')) {
                    $subAccount['Currency'] = 'cash';
                }
                $subAccount["Balance"] = $balance;
                // Code
                if (!strstr($subAccount['Code'], "usbank")) {
                    $subAccount['Code'] = "usbank" . $subAccount['Code'];
                }
                $subAccounts[] = $subAccount;
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => $subAccount['Code'],
                    "DisplayName"     => $subAccount['DisplayName'],
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ], true);
            }
        }// if (isset($index))
        // for DisplayName
        if (!isset($balance) && isset($subAccount['Code'], $subAccount['DisplayName'])) {
            // Code
            if (!strstr($subAccount['Code'], "usbank")) {
                $subAccount['Code'] = "usbank" . $subAccount['Code'];
            }
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => $subAccount['Code'],
                "DisplayName"     => $subAccount['DisplayName'],
                "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
            ], true);
        }// if (!isset($balance) && isset($subAccount['Code'], $subAccount['DisplayName']))

        return !empty($subAccounts) ? $subAccounts : [];
    }

    public function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9275951.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400705,9380075,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.847115948423,814284690037.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628569380075,-999999,17421,0,0,2903,0,0,2,0,0,D60294FE0FD98032152890CA153ACBEA~-1~YAAQxyQcuG1Dww17AQAAFKNMLga6qhzPZ0mdPZ0HGygES4IoG0Q/wmX3StrqPfIrAmgi5o6VYrhiwuySwtj7XkceT6HQg/nj+nQumxbi1QzOTkfOHnXx7SF8GUf3Wl8ZYVk5OYeVVwbdZ+UkrmSzyACEGBhTGsmFbpQGx4fXXkLoIIM6Uoq89ZoaFzrID2+0gMZDE0HcPsZVKe6sYK4J90xcO4K7DxhyQ8cBm/eoNLBKTr6YfIsf5Q7kQeFbHrBwcTGkmYZG2EPlzfaQVJS0oUomHirtctKztsD/NoB4MacXp1UflTR9UCWFHLNFDASvQpINM1D62PB6nHB0rNqSjVWqhRtNntQVrRDUeFJ8qYx3/xBVxFS4akeXoJlL~-1~-1~-1,35651,-1,-1,26067385,PiZtE,81814,65,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,253261971-1,2,-94,-118,83687-1,2,-94,-129,-1,2,-94,-121,;21;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9275781.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400690,8937976,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.787084990393,814254468987.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628508937975,-999999,17421,0,0,2903,0,0,2,0,0,A38E03D6F7481BFBF0BAF342E3471A14~-1~YAAQHe8uF2A5dA57AQAAOl6yKgbV4BltvwVR4oRugLRN0XNFXjOqXjvoosp0aG9shjTaNKvDqh1lbYEAOGBdukv9devDPWvrSCBQhf8d5ED/yOGKFkmVkbtbY73ftmhVvO8joufTKcl41+c1P5nICCztmPeVw+Y4I2zFKNQ0PT+OHEIJ21qElldjd0hiN0qg6q3Q50gacebS3mEfBmDQWVGvGTl5ztdfyGV2falY9qz7i7dD4WxsxEZo31eySOTIuXBziOH34GzdMfcok+SMhM3HciaxrDCqGiSoNTLc1Q+XrLB2Y94d1334Av1VqdNRsCSYfNJR0rf+UL1s5WXOd1HxXrhQiLqcfCCTZhZIXRHNV4hO/XI2qoAMe0XU~-1~-1~-1,35117,-1,-1,26067385,PiZtE,71716,129,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,26813877-1,2,-94,-118,83286-1,2,-94,-129,-1,2,-94,-121,;17;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9166221.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:76.0) Gecko/20100101 Firefox/76.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,390994,3435098,1536,880,1536,960,1536,474,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6010,0.0128895416,794551717549,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-102,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1589103435098,-999999,16999,0,0,2833,0,0,2,0,1,2,50,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,30915894-1,2,-94,-118,46603-1,2,-94,-121,;2;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9023851.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:70.0) Gecko/20100101 Firefox/70.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,387233,4521686,1440,829,1440,900,1440,448,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,5998,0.785578095392,786907260842.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-102,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,1,0,0,0,0,0,1,0,1573814521685,-999999,16836,0,0,2806,0,0,3,0,0,7F5DADF415C9AD3A3E372EFD1E607B5A~-1~YAAQLWAZuGAEtmduAQAAccenbgLeIdPmzDN1IRJ9zlWJgG0PzvEHiWj6maCiUkf3JvJUwf1bD0XJRWTuqlcv9vbfKjwc+gjS0qxHhS6AJUJ4t3MYx241lEPV5R+BvNyyl8mVknY+yReMlmTk+dYLIpS78wpQniwHNQD415onNNM9TVmxdu7iAWVCDhBT/LZwB5Nso0xC+153wbZ4fYkab7GyhvV1DyvuE/tAXGbjMEUNbqQ0xi5HpYgZajhClAv4TsRPiIym0yYy1+S4mck46WU2XjsfE7MKZ7eAFymhoZWLVZZUUUa1P3Ls~-1~-1~-1,29776,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,40695103-1,2,-94,-118,75241-1,2,-94,-121,;3;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9275611.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400683,2227044,1536,871,1536,960,1536,421,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.957427106478,814241113522,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628482227044,-999999,17421,0,0,2903,0,0,3,0,0,45A16D81F0607B1E293B778131ED5309~-1~YAAQxyQcuNy+pw17AQAAW8UaKQa+E7uwjhGy7VejtCLsF3deXsQTTLjDkjuJeHaAhmnXNVAWqJcgUdUkkkDWcbHOGkJcsEOpzgUyg/P9Bv6Jq83rt3xbJc/X0Yx0eVuo9ITbnyLHkC0k7HLUQN0WzHII/Rvc7yxmbnuKxrfiAxKQ79xYoPKXsyCEtNoB+kkLCnKi91Lg/b/ndTZxMzrGf4zQJGDgQniYhhvJCjK+CE25JYBTTB6ZuqxwJXRfrttrWDYd+EU0iFsePA/Dw+PXYGm+QkoovHh7hOi5FHdzLhoOAd0nUDpkhcQa94tPZsiKl9FerAvNPPM7rEvLBCANvAMPQ2F0nRqLvMoELmvH/3OPSzuRO54vGmkKOR2TzCJMgpp/KStT8LYg8k0=~-1~-1~-1,37432,-1,-1,26067385,PiZtE,69372,86,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,167028267-1,2,-94,-118,85344-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9275961.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,400706,2347047,1536,824,1536,864,1536,391,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:84.80000305175781,vib:1,bat:0,x11:0,x12:1,5569,0.983294714491,814286173523.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1628572347047,-999999,17422,0,0,2903,0,0,5,0,0,ADC937AB81DBF5DC346F02686197BC50~-1~YAAQV/1zPnmLqu96AQAADN55Lgbjn9L6rf4agmHByTCaXt8P71W40Feuhp+VQIge3RGZ+vgHSfzjSgPT2xuvomTBgNsqc+L3p66EaZ+grjM0QOP3xI3sqKiORUQC/o1TZHJO6mRsI0jeu5zWbCEqFFWyhP3DvNiJbwSK3oSnHe3AfNYdMc8fuw8MbUAaxrgVrwznaENm4b3nKZhwRV+ouygBKow5jyvEIdBvOg3kRBcNfSu8AhZAY1vvHT5zJuQnvq0y+UURPZ+VJltq9NNYTa+LkeIXNnkW1q0m5XnU68KA89YIAsmrPNH/rHvioIP0GbcC9C48JQcWe26Tx+InSQyQq0Ln7e98qpM/q1L3OSud2Svrna1Nu+YnoqXSTbx6N0ErsMyL69qAhlQ=~-1~-1~-1,37010,-1,-1,25543097,PiZtE,12437,27,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,176027805-1,2,-94,-118,85346-1,2,-94,-129,-1,2,-94,-121,;12;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9275611.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:56.0) Gecko/20100101 Firefox/56.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400684,2801364,1536,871,1536,960,1536,421,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6003,0.18294444691,814241400682,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,0,0,1628482801364,-999999,17421,0,0,2903,0,0,2,0,0,34A4E2AF478EABC623D8DBDA44B17381~-1~YAAQ1h/JF/ZaMCd7AQAAVIcjKQZ6b/F7afKNRDydyMfvzRlrHKXAeTGyg+UUdL9tL1m51jIxfq4WckBkkECzSjkahGCfW3EEZbJLiRnTOFFjytcZsCUGpTrA0qMKWjxT6Zvp7jjngjoyd9aDNsgY/pOrCheHlgn/viYeM9Eyp+iiWrAx9oZ2dlGHOx7C5J+RYSTmLSZbiZbl8pXxttQP01QKJ1m01YCpOh86DcYCnIXCWYO67bqLUGT02omq755BVwyHgF+MK2qr7eHyMOa2ftnT5zS0qW4rAbuEOsUfxCmoH10/bQ9wXHGS8JB4cM4SC9k43l9sWg0C+EOo4voZRTqnHdgxAgbTDRn4h8d1aAfPDo0nol4Z1gbMyux8nJQAzyMrpTvOXEsbQ6I=~-1~-1~-1,37025,-1,-1,26067385,PiZtE,23373,33,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,42020433-1,2,-94,-118,84923-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9275951.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400705,9425515,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.19979198599,814284712757.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628569425515,-999999,17421,0,0,2903,0,0,2,0,0,6F61CD6A0486232C377AABBA56FCDFC5~-1~YAAQxyQcuChFww17AQAAz1RNLga1DBcUwMXcD/KjpX7QQ2Pf41O3qmKvRnxc8Wxjh9SW/9LgIGKJpQ8g8aqtArd/Uxl+lZApsjVfeyKqckNRRlLmDPXSDzxEZ+IMkxdWvz5mEOkYuCCH0KLdVahsNnvFQtHxn4T06VoQTDq3rdEhL0n7JHGt8O7fAFg9/rb5mquHVUv0wj/lj9tvne6RjtmxVCps4xXbcXxHWZYJnHBZ9ZTkf1KBMxd9dUCIR5laL/IynLyhZotzY2Ezv2tWgnWA5E5vuh+pdS84AKExDU++CcV1f7+XHGU4vHP7GEygwQVd7backvMkE6Hr7mihzCQBT29rCLuBkSDgyfLZBgUEEsLwr85a0AZp8pyJ~-1~-1~-1,35697,-1,-1,26067385,PiZtE,42289,70,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,28276596-1,2,-94,-118,83716-1,2,-94,-129,-1,2,-94,-121,;171;-1;0",
            // 8
            "7a74G7m23Vrp0o5c9275961.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,400706,2497881,1536,824,1536,864,1536,391,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:84.80000305175781,vib:1,bat:0,x11:0,x12:1,5569,0.607848048303,814286248940.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1628572497881,-999999,17422,0,0,2903,0,0,5,0,0,16023C2997538E9008AA4B8C471B4B71~-1~YAAQV/1zPrSTqu96AQAAoyt8LgbLTcpoxVJFVMf+P2t5wx/96vDlaAo20UN793YnkBSFsiwERF+7uVEwHa0fyGJ4fvtkMkwf/WDJCgMrbYJCL9VV7ixLdh5pVdBX5VE98KFk0/jUqwSDfKCp0f9nA9M4zPL5mdk/59eQfowGZPdtHnkvB4cHBOHW28m6qsnEOqPf7UhZP335S/Aedj9FZ2xLvpDvRKa9DOe8lPTElOjkyShWZQNjs3g9sFbyth66o0uoCNhrGyhsDwQpbb0Gf9NID/jVyE+T6WtbjuV4P7s0ze5KszvT+NHHi6goGyoOEYcmJiNhDEdEZt0ehk/mY0DDKNiJKlk7YpICG8KWHf+1B4o+ixJskJLFT/3K33GiMoo4D+dXQUQ4Uzw=~-1~-1~-1,36485,-1,-1,25543097,PiZtE,46378,77,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,606985575-1,2,-94,-118,84871-1,2,-94,-129,-1,2,-94,-121,;24;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9275951.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400705,9380075,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.880594636440,814284690037.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,2992,832,0;1,0,0,0,2835,851,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,535,0,1628569380075,5,17421,0,0,2903,0,0,535,0,0,D60294FE0FD98032152890CA153ACBEA~-1~YAAQxyQcuG1Dww17AQAAFKNMLga6qhzPZ0mdPZ0HGygES4IoG0Q/wmX3StrqPfIrAmgi5o6VYrhiwuySwtj7XkceT6HQg/nj+nQumxbi1QzOTkfOHnXx7SF8GUf3Wl8ZYVk5OYeVVwbdZ+UkrmSzyACEGBhTGsmFbpQGx4fXXkLoIIM6Uoq89ZoaFzrID2+0gMZDE0HcPsZVKe6sYK4J90xcO4K7DxhyQ8cBm/eoNLBKTr6YfIsf5Q7kQeFbHrBwcTGkmYZG2EPlzfaQVJS0oUomHirtctKztsD/NoB4MacXp1UflTR9UCWFHLNFDASvQpINM1D62PB6nHB0rNqSjVWqhRtNntQVrRDUeFJ8qYx3/xBVxFS4akeXoJlL~-1~-1~-1,35651,507,-1964811234,26067385,PiZtE,15817,65,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,200,0,0,200,0,0,0,0,600,400,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,253261971-1,2,-94,-118,88573-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;11;23;0",
            // 1
            "7a74G7m23Vrp0o5c9275781.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400690,8937976,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.15400597177,814254468987.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,559,0,1628508937975,5,17421,0,0,2903,0,0,560,0,0,A38E03D6F7481BFBF0BAF342E3471A14~-1~YAAQHe8uF2A5dA57AQAAOl6yKgbV4BltvwVR4oRugLRN0XNFXjOqXjvoosp0aG9shjTaNKvDqh1lbYEAOGBdukv9devDPWvrSCBQhf8d5ED/yOGKFkmVkbtbY73ftmhVvO8joufTKcl41+c1P5nICCztmPeVw+Y4I2zFKNQ0PT+OHEIJ21qElldjd0hiN0qg6q3Q50gacebS3mEfBmDQWVGvGTl5ztdfyGV2falY9qz7i7dD4WxsxEZo31eySOTIuXBziOH34GzdMfcok+SMhM3HciaxrDCqGiSoNTLc1Q+XrLB2Y94d1334Av1VqdNRsCSYfNJR0rf+UL1s5WXOd1HxXrhQiLqcfCCTZhZIXRHNV4hO/XI2qoAMe0XU~-1~-1~-1,35117,552,922162789,26067385,PiZtE,71724,91,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,200,0,0,0,200,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,26813877-1,2,-94,-118,86019-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;13;19;0",
            // 2
            "7a74G7m23Vrp0o5c9166221.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:76.0) Gecko/20100101 Firefox/76.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,390994,3435098,1536,880,1536,960,1536,474,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6010,0.797296028398,794551717549,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-102,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;0,0,0,1,1473,1041,0;1,-1,0,1,1144,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,1153,0,1589103435098,5,16999,0,0,2833,0,0,1154,0,1,AF399FA048E6E41DE7C769E8048C5318~-1~YAAQb6w4F2g0Vt1xAQAA1h/y/QP93iuDkZ+MJEXDuVdLDenCWuqvf384+7453PBk4hJENlHcRqNKxDDYYjALPRX2BNINwEiApHa9IyAs6nVwhw3iNZjSlmElBPxqQm5NhmUY1bnbArwIeknAK5L0/xG1401j+IZV8Fpo+sAUL634A9LFq6n6SLcKMJzRrd98cn1cFDTMvmS2b+70tHY4lhsKg5TPdrIuLD7vW/v7VXltZ/+L5lHDWAGS/FXaWpwmbbd6cGGZ654Dc0t0dR8/EiycRaD+zQ24Anud9UJl0xaj42rcjRH5zh/tdPz/4/sMEV562cLoTmo=~-1~-1~-1,30048,679,1203104846,26067385-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,0,0,0,0,0,200,0,0,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,30915894-1,2,-94,-118,81823-1,2,-94,-121,;1;4;0",
            // 3
            "7a74G7m23Vrp0o5c9023851.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:70.0) Gecko/20100101 Firefox/70.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,387233,4521686,1440,829,1440,900,1440,448,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,5998,0.614531534307,786907260842.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-102,0,-1,0,1,917,113,0;0,-1,0,1,1517,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,1,0,0,0,0,0,163,0,1573814521685,5,16836,0,0,2806,0,0,163,0,0,7F5DADF415C9AD3A3E372EFD1E607B5A~-1~YAAQLWAZuGAEtmduAQAAccenbgLeIdPmzDN1IRJ9zlWJgG0PzvEHiWj6maCiUkf3JvJUwf1bD0XJRWTuqlcv9vbfKjwc+gjS0qxHhS6AJUJ4t3MYx241lEPV5R+BvNyyl8mVknY+yReMlmTk+dYLIpS78wpQniwHNQD415onNNM9TVmxdu7iAWVCDhBT/LZwB5Nso0xC+153wbZ4fYkab7GyhvV1DyvuE/tAXGbjMEUNbqQ0xi5HpYgZajhClAv4TsRPiIym0yYy1+S4mck46WU2XjsfE7MKZ7eAFymhoZWLVZZUUUa1P3Ls~-1~-1~-1,29776,973,989518528,26067385-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,1241107008;dis;,3;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4835-1,2,-94,-116,40695103-1,2,-94,-118,75561-1,2,-94,-121,;0;4;0",
            // 4
            "7a74G7m23Vrp0o5c9275611.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400683,2227044,1536,871,1536,960,1536,421,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.379540225189,814241113522,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,517,0,1628482227044,9,17421,0,0,2903,0,0,517,0,0,45A16D81F0607B1E293B778131ED5309~-1~YAAQxyQcuNy+pw17AQAAW8UaKQa+E7uwjhGy7VejtCLsF3deXsQTTLjDkjuJeHaAhmnXNVAWqJcgUdUkkkDWcbHOGkJcsEOpzgUyg/P9Bv6Jq83rt3xbJc/X0Yx0eVuo9ITbnyLHkC0k7HLUQN0WzHII/Rvc7yxmbnuKxrfiAxKQ79xYoPKXsyCEtNoB+kkLCnKi91Lg/b/ndTZxMzrGf4zQJGDgQniYhhvJCjK+CE25JYBTTB6ZuqxwJXRfrttrWDYd+EU0iFsePA/Dw+PXYGm+QkoovHh7hOi5FHdzLhoOAd0nUDpkhcQa94tPZsiKl9FerAvNPPM7rEvLBCANvAMPQ2F0nRqLvMoELmvH/3OPSzuRO54vGmkKOR2TzCJMgpp/KStT8LYg8k0=~-1~-1~-1,37432,335,2106491240,26067385,PiZtE,26778,57,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,0,0,0,0,200,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,167028267-1,2,-94,-118,88172-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;488;8;0",
            // 5
            "7a74G7m23Vrp0o5c9275961.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,400706,2347047,1536,824,1536,864,1536,391,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:84.80000305175781,vib:1,bat:0,x11:0,x12:1,5569,0.941835830470,814286173523.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,591,0,1628572347047,14,17422,0,0,2903,0,0,591,0,0,ADC937AB81DBF5DC346F02686197BC50~-1~YAAQV/1zPoyLqu96AQAApOF5Lgbi+wXr/cpJ6/gudHZ4CCCbqS7K7fTgOEdz0ea0N5UVdtvxAHeAJeQd7OonekBfOZ5K0veALoEoNyyfGemnPFRduo0A+5e0SQVSaH9c+PsLUdHVHCnwhDvf6fZupX5H2ajZonpJP9KLCw5cZDLbwSP9nV37eKePxwTL5THHj3EljnWTDJvLdTQI1+rTUGzPMVm9LYlC08xukna2yrqFLWFi78c4pET+rI8N1sRpgOnDilh0FHnTcclzfYIePLvwumzidwqF/fDHSBEtE0l0TErFo9+syDiaetUez/rp1aROgMOV+pLC4fDeIyQ6b4WM5pu2owrLujLsB9ltK/8WzRUnLiG5k4u6Jy8O+IA3gQzVIbPdoYaL6P8=~-1~-1~-1,37101,556,-1928452705,25543097,PiZtE,13911,47,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,200,0,0,0,0,0,0,800,400,400,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,176027805-1,2,-94,-118,88508-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),2669885e0376ed57265f041bfbd5a2174ca5b7a1d3bc28048453faef8450e0ef,26-1,2,-94,-121,;19;15;0",
            // 6
            "7a74G7m23Vrp0o5c9275611.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:56.0) Gecko/20100101 Firefox/56.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400684,2801364,1536,871,1536,960,1536,421,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6003,0.768287974384,814241400682,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,512,0,1628482801364,5,17421,0,0,2903,0,0,513,0,0,34A4E2AF478EABC623D8DBDA44B17381~-1~YAAQ1h/JF/ZaMCd7AQAAVIcjKQZ6b/F7afKNRDydyMfvzRlrHKXAeTGyg+UUdL9tL1m51jIxfq4WckBkkECzSjkahGCfW3EEZbJLiRnTOFFjytcZsCUGpTrA0qMKWjxT6Zvp7jjngjoyd9aDNsgY/pOrCheHlgn/viYeM9Eyp+iiWrAx9oZ2dlGHOx7C5J+RYSTmLSZbiZbl8pXxttQP01QKJ1m01YCpOh86DcYCnIXCWYO67bqLUGT02omq755BVwyHgF+MK2qr7eHyMOa2ftnT5zS0qW4rAbuEOsUfxCmoH10/bQ9wXHGS8JB4cM4SC9k43l9sWg0C+EOo4voZRTqnHdgxAgbTDRn4h8d1aAfPDo0nol4Z1gbMyux8nJQAzyMrpTvOXEsbQ6I=~-1~-1~-1,37025,263,-1379617746,26067385,PiZtE,36331,56,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,0,0,200,0,0,0,0,400,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,42020433-1,2,-94,-118,88049-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;13;6;0",
            // 7
            "7a74G7m23Vrp0o5c9275951.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,400705,9425515,1536,871,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6002,0.405893291202,814284712757.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,685,0,1628569425515,5,17421,0,0,2903,0,0,685,0,0,6F61CD6A0486232C377AABBA56FCDFC5~-1~YAAQxyQcuChFww17AQAAz1RNLga1DBcUwMXcD/KjpX7QQ2Pf41O3qmKvRnxc8Wxjh9SW/9LgIGKJpQ8g8aqtArd/Uxl+lZApsjVfeyKqckNRRlLmDPXSDzxEZ+IMkxdWvz5mEOkYuCCH0KLdVahsNnvFQtHxn4T06VoQTDq3rdEhL0n7JHGt8O7fAFg9/rb5mquHVUv0wj/lj9tvne6RjtmxVCps4xXbcXxHWZYJnHBZ9ZTkf1KBMxd9dUCIR5laL/IynLyhZotzY2Ezv2tWgnWA5E5vuh+pdS84AKExDU++CcV1f7+XHGU4vHP7GEygwQVd7backvMkE6Hr7mihzCQBT29rCLuBkSDgyfLZBgUEEsLwr85a0AZp8pyJ~-1~-1~-1,35697,877,494947617,26067385,PiZtE,33412,29,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,200,0,0,0,0,200,600,400,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,28276596-1,2,-94,-118,86685-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) UHD Graphics 630,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;11;173;0",
            // 8
            "7a74G7m23Vrp0o5c9275961.7-1,2,-94,-100,Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0,uaend,11059,20100101,ru-RU,Gecko,0,0,0,0,400706,2497881,1536,824,1536,864,1536,391,1550,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:84.80000305175781,vib:1,bat:0,x11:0,x12:1,5569,0.710607014355,814286248940.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-102,0,0,0,0,1235,113,0;0,0,0,0,1717,113,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,28;2,117;-1,2,-94,-112,https://www.usbank.com/index.html-1,2,-94,-115,1,32,32,0,0,0,0,582,0,1628572497881,12,17422,0,0,2903,0,0,583,0,0,16023C2997538E9008AA4B8C471B4B71~-1~YAAQV/1zPsKTqu96AQAAWi98LgZz1s/6SYvwRILzihO2iU5grvtPtM4yYUZyS82xxptUcOb9+srAofZ+cBYCXJoLrjSLlFB4HUdYjLXxmV480rqSB3b8OevsoCsFTQ+ne2ZEDjl6fIchr0kz8OL2XYAGo1xWYmRsRVawHdSuPq+/2BahiVcaxkdbth2M7x/5MBTXqib9LPnOFqUUEjT+89df8sThqTHxdo9FOaoaCayHIYBLyms8EzSCxwUGLq+WMjmAMK/YRZQLaZdCe2QHpIjk276HhwZO1lJHY7hg5MBmNptbN5tTJcOOmhQ44Db4DK8QBCLcYOycggLN/i5QktSmOZ1rKRvC1x/eHy7RsqkFsm6ujKXY+50YInbg+UKQUHucgITvG5pFM/s=~-1~-1~-1,37022,884,-1828155162,25543097,PiZtE,96611,78,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,200,0,0,0,200,600,600,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,1436327638;1247333925;dis;;true;true;true;-180;true;24;24;true;false;1-1,2,-94,-80,5343-1,2,-94,-116,606985575-1,2,-94,-118,89105-1,2,-94,-129,5bcc2979186a30ddc6859e7fea6e834a62b2c5aa4a926535706369893555dcd8,1.25,0,Google Inc.,ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0),2669885e0376ed57265f041bfbd5a2174ca5b7a1d3bc28048453faef8450e0ef,26-1,2,-94,-121,;21;26;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
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

    protected function exportToEditThisCookies()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("exportToEditThisCookies", ['Header' => 3]);
        $cookiesArr = [];
        $cookiesArrGeneral = [];
        $domains = [
            ".usbank.com",
            "www.usbank.com",
        ];
        $cookies = [];

        foreach ($domains as $domain) {
            $cookies = array_merge($cookies, $this->http->GetCookies($domain), $this->http->GetCookies($domain, "/", true));
        }
        $i = 1;

        foreach ($cookies as $cookie => $val) {
            $c = [
                "domain"   => ".usbank.com",
                //                "expirationDate" => 1494400127,
                "hostOnly" => false,
                "httpOnly" => false,
                "name"     => $cookie,
                "path"     => "/",
                "secure"   => false,
                "session"  => false,
                "storeId"  => "0",
                "value"    => $val,
            ];
            $cookiesArr[] = $c;
            $cg = "document.cookie=\"{$cookie}=" . str_replace('"', '\"', $val) . "; path=/; domain=.usbank.com\";";
            $cookiesArrGeneral[] = $cg;
            $i++;
        }// foreach ($cookies as $cookie)
        $this->logger->debug("==============================");
        $this->logger->debug(str_replace("\/", "/", json_encode($cookiesArr)));
        $this->logger->debug("==============================");
        $this->logger->debug("===============2==============");
        $this->logger->debug(var_export(implode(' ', $cookiesArrGeneral), true));
        $this->logger->debug("==============================");
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1] | //a[@id = 'spanLogout' and contains(text(), 'Log Out')]")) {
            return true;
        }

        return false;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);

        if ($this->key === null) {
            return;
        }

        StatLogger::getInstance()->info("usbank sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }
}
