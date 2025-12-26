<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerIsay extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $newSite = [
        "Canada",
        "Deutschland",
        "United Kingdom",
        "France",
        "United States",
        "en-au",
        "en-ca",
        "en-US",
        "en-us",
        "United States",
        "中國香港特別行政區",
        "India",
        "pt-br",
        "fr-fr",
        "it-it",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->UseSelenium();
//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);

        $this->http->saveScreenshots = true;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = [
            ''      => 'Select a country',
            'it-it' => 'Italia (Italiano)',
            'es-ar' => 'Argentina (Español)',
            'en-au' => 'Australia (English)',
            'fr-be' => 'Belgique (Français)',
            'nl-be' => 'België (Nederlands)',
            'pt-br' => 'Brasil (Português)',
            'en-ca' => 'Canada (English)',
            'fr-ca' => 'Canada (Français)',
            'es-cl' => 'Chile (Español)',
            'zh-cn' => '中国 (简体中文)',
            'es-co' => 'Colombia (Español)',
            'es-cr' => 'Costa Rica (Español)',
            'es-ec' => 'Ecuador (Español)',
            'en-eg' => 'Egypt (English)',
            'ar-eg' => 'مصر (عربي)',
            'en-hk' => 'Hong Kong SAR China (English)',
            'zh-hk' => '中國香港特別行政區 (繁體中文)',
            'en-in' => 'India (English)',
            'hi-in' => 'भ रत (ह ंद )',
            'id-id' => 'Indonesia (Bahasa Indonesia)',
            'ar-iq' => 'العراق (عربي)',
            'ku-iq' => 'عێراق (کوردی)',
            'ja-jp' => '日本 (日本語)',
            'en-jo' => 'Jordan (English)',
            'ar-jo' => 'الأردن (عربي)',
            'en-kw' => 'Kuwait (English)',
            'ar-kw' => 'الكويت (عربي)',
            'ar-lb' => 'لبنان (عربي)',
            'en-lb' => 'Lebanon (English)',
            'ms-my' => 'Malaysia (Bahasa Melayu)',
            'en-my' => 'Malaysia (English)',
            'zh-my' => '马来西亚 (简体中文)',
            'es-mx' => 'México (Español)',
            'fr-ma' => 'Maroc (Français)',
            'ar-ma' => 'المغرب (عربي)',
            'en-nz' => 'New Zealand (English)',
            'es-pa' => 'Panamá (Español)',
            'es-pe' => 'Perú (Español)',
            'en-ph' => 'Philippines (English)',
            'fil'   => 'Pilipinas (Filipino)',
            'pl-pl' => 'Polska (Polski)',
            'es-pr' => 'Puerto Rico (Español)',
            'en-qa' => 'Qatar (English)',
            'ar-qa' => 'قطر (عربي)',
            'ru-ru' => 'Россия (русский)',
            'fr-re' => 'La Réunion (Français)',
            'en-sa' => 'KSA (English)',
            'ar-sa' => 'السعودية (عربي)',
            'en-sg' => 'Singapore (English)',
            'zh-sg' => '新加坡 (简体中文)',
            'en-za' => 'South Africa (English)',
            'ko-kr' => '한국 (한국어)',
            'th-th' => 'ไทย (ภาษาไทย)',
            'en-ae' => 'UAE (English)',
            'ar-ae' => 'الإمارات (عربي)',
            'en-us' => 'United States (English)',
            'es-uy' => 'Uruguay (Español)',
            'vi-vn' => 'Việt Nam (Tiếng Việt)',
            'ro'    => 'România (Română)',
            'en'    => 'United Kingdom (English)',
            'hu-hu' => 'Magyarország (Magyar)',
            'es-es' => 'España (Español)',
            'tr-tr' => 'Türkiye (Türkçe)',
            'fr-fr' => 'France (Français)',
            'da-dk' => 'Danmark (Dansk)',
            'de-de' => 'Deutschland (Deutsch)',
            'nb-no' => 'Norge (Bokmål)',
            'sv-se' => 'Sverige (Svenska)',
            'de-ch' => 'Schweiz (Deutsch)',
            'fr-ch' => 'Suisse (Français)',
            'nl-nl' => 'Nederland (Nederlands)',
        ];
    }

    public function IsLoggedIn()
    {
//        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
        if (empty($this->State['REWARDS_PAGE_URL'])) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($this->State['REWARDS_PAGE_URL'], [], 20);
        $this->http->RetryCount = 2;
        sleep(3);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
//        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://social.i-say.com/surveys", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/Sign Out|Ausloggen|Se déconnecter|Afmelden|Esci/ims")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $updateLogin2Exception = new CheckException('To update this Ipsos (i-Say) account you need to select your country. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.', ACCOUNT_PROVIDER_ERROR);

        if ($this->AccountFields['Login2'] == '') {
            throw $updateLogin2Exception;
        }

        // turns region name to locale, for example 'United States' into 'en_us'
        if (strlen($this->AccountFields['Login2']) > 5) {
            $arFields = [];
            self::TuneFormFields($arFields);
            $countries = $arFields['Login2']['Options'];

            if (!$rightLogin2 = preg_grep("/.*{$this->AccountFields['Login2']}.*/", $countries)) {
                throw $updateLogin2Exception;
            }
            $this->AccountFields['Login2'] = array_key_first($rightLogin2);
        }

        $this->http->removeCookies();
        $this->AccountFields['Login2'] = strtolower($this->AccountFields['Login2']);
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://www.ipsosisay.com/' . $this->AccountFields['Login2'] . "/user/login");

        $this->logger->info('search for login form');

        $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-name"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 10);

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->saveResponse();
        }

        if ($acceptCookies = $this->waitForElement(WebDriverBy::xpath('//div[@id = "popup-buttons"]//button[contains(@class, "cookie-compliance-default-button")]'), 0)) {
            $acceptCookies->click();
            $this->saveResponse();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-name"]'), 10);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-pass"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-submit"]'), 0);
        $this->saveResponse();

        if (!isset($login, $pwd, $btn)) {
            return $this->checkErrors();
        }

        $this->logger->info('filling login form');
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();
        sleep(3);
        $this->saveResponse();
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-submit"]'), 0);

        if ($btn) {
            $this->logger->debug('retry click');

            $btn->click();
        }

        /*
        if (!in_array($this->AccountFields['Login2'], ['', 'en-US', 'United States'])) {
            switch ($this->AccountFields['Login2']) {
                case 'en-GB':
                    $this->AccountFields['Login2'] = "United Kingdom";

                    break;

                case 'en-CA':
                    $this->AccountFields['Login2'] = "Canada";

                    break;

                case 'fr-FR':
                    $this->AccountFields['Login2'] = "France";

                    break;

                case 'de-CH':
                    $this->AccountFields['Login2'] = "Schweiz";

                    break;

                case 'nl-NL':
                    $this->AccountFields['Login2'] = "Nederland";

                    break;

                case 'en':
                    $this->AccountFields['Login2'] = "United States";

                    break;
            }

//            if (!isset(TAccountCheckerIsay::$countrySettings[$this->AccountFields['Login2']])) {
//                $this->logger->error("wrong country settings");
//                return false;
//            }
        }

        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
            return $this->LoadLoginFormDotCom();
        }

        $this->http->GetURL("https://social.i-say.com/login");

        $this->checkErrors();

        if (!$this->switchCountry()) {
            return $this->checkErrors();
        }

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'loginForm')]")) {
            return $this->checkErrors();
        }
//        $this->http->FormURL = $this->http->currentUrl().'-1.IBehaviorListener.0-loginForm-submit';
        $this->logger->debug("{$this->http->FormURL}");
        //$this->http->NormalizeURL($this->http->FormURL);
        //$this->http->FormURL = 'https://social.i-say.com/login?0-2.IFormSubmitListener-loginForm';
        //$this->logger->debug("{$this->http->FormURL}");
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('passwordContainer:password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember', 'on');
        $this->http->SetInputValue('p::submit', 'x');
        $this->http->unsetInputValue('ide_hf_0');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            // Oops! An error has occurred!
            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Oops! An error has occurred!')]")) {
                throw new CheckRetryNeededException(3, 10, $message);
            }

            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        */

        return true;
    }

    public function LoadLoginFormDotCom()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.ipsosisay.com/en-gb");

        if (!$this->http->ParseForm("user-login-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('name', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass', $this->AccountFields['Pass']);
        $this->http->SetInputValue('op', "Log+in");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Site under maintenance
        if ($this->http->currentUrl() == 'https://social.i-say.com/pages/maintenance.php'
            && $this->http->FindSingleNode("//h1[contains(text(),'Site under maintenance')]")) {
            throw new CheckException('Site under maintenance', ACCOUNT_PROVIDER_ERROR);
        }
        // Site Unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'site is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Oops! An error has occurred!
        if ($message = $this->http->FindSingleNode("//h1[
                contains(text(), 'Oops! An error has occurred!')
                or contains(text(), 'Our systems are currently unavailable')
            ]
            | //h4[contains(text(), 'Our website is currently down for maintenance, please come back later.')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($message = $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//input[@data-drupal-selector = "edit-logout"] | //div[contains(@class, "messages--error")] | //div[contains(@class, "alert-error alert-danger")] | //span[@class = "exchange_points_and_new"]/a'), 10);
        $this->saveResponse();

        /*
        $headers = [
            "Wicket-Ajax"         => "true",
            "Wicket-Ajax-BaseURL" => "login", //$this->http->FindPreg('/(login\?\d+)/', $this->http->currentUrl()),
            "X-Requested-With"    => "XMLHttpRequest",
        ];

        if (!$this->http->PostForm()) {
            // retries
            if ($this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(3, 7);
            }

            // AccountID: 4658686
            if (
                $this->http->Response['code'] == 500
                && $this->http->currentUrl() == 'https://www.ipsosisay.com/en-gb/intro'
                && $this->http->FindPreg("/^The website encountered an unexpected error. Please try again later.<br \/>$/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        */

//        if (in_array($this->AccountFields['Login2'], $this->newSite)) {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "messages--error")]/div/text()[2] | //div[contains(@class, "alert-error alert-danger")]/text()[2]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Sorry! Your username or password was incorrect.')
                || strstr($message, 'Seu nome de usuário ou senha estavam incorretos.')
                || strstr($message, 'Извините! Ваши имя пользователя или пароль неверны.')
                || strstr($message, 'К сожалению, для этой учетной записи было предпринято более 3 неудачных попыток входа в систему')
                || strstr($message, 'Désolés, votre nom d\'utilisateur ou mot de passe est incorrect')
                || strstr($message, 'Siamo spiacenti! Nome utente o password errati')
                || strstr($message, 'Entschuldigung! Ihr Benutzername oder Passwort war falsch')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // AccountID: 5894060
        if (in_array($this->AccountFields['Login'], [
            'nickhperry@gmail.com',
            'mlist76@live.com',
            'russjr63@gmail.com',
            'dbagirl78@gmail.com',
            'me@henryyeh.com',
            'dannileifer@yahoo.com',
            'scottyj96@aol.com',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // We updated Ipsos iSay policies. // AccountID: 5154878
        if (
                strstr($this->http->currentUrl(), 'https://surveys.ipsosisay.com/')
                && strstr($this->http->currentUrl(), '?invite=')
            ) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
//        }

        if (
            !$this->providerRedirect()
            && !$this->http->FindPreg('/<span wicket:id="message">[^<]+/')
            && !$this->http->FindPreg("/wicket:message key=\"logout\"/ims")
            && !strstr($this->http->currentUrl(), '/static/maintenance')
        ) {
            $this->http->GetURL("https://social.i-say.com/login");
        }

//        if ($this->http->FindPreg("/Sign Out|Ausloggen|Se déconnecter|Afmelden|Esci|Cerrar sesión/ims"))
        if ($this->http->FindPreg("/wicket:message key=\"logout\"/ims")) {
            return true;
        }
        // The email or password you entered is not valid, please try again
        if ($message = $this->http->FindSingleNode("//span[
                contains(text(), 'The email or password you entered is not valid, please try again')
                or contains(text(), 'pas valide')
                or contains(text(), 'Die E-Mail oder das von Ihnen eingegebene Passwort ist nicht gültig, versuchen Sie es bitte noch einmal.')
                or contains(text(), 'Girdiğiniz e-posta veya parola geçerli değil, lütfen tekrar deneyin.')
                or contains(text(), 'Den mejladress eller det lösenord du angav gäller inte. Försök igen')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "exchange_points_and_new"]/a'), 5);
        $this->saveResponse();

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[@class="fa-circle-user"]')));

        /*
        if (empty($this->Properties['Name'])) {
            $this->sendNotification('refs #24818 - need to check name // IZ');
        }
        */

        // if (in_array($this->AccountFields['Login2'], $this->newSite)) {
        // Balance - points
        $this->SetBalance($this->http->FindSingleNode('//span[@class = "exchange_points_and_new"]/a'));

        if ($this->ErrorCode === ACCOUNT_CHECKED) {
            $this->State['REWARDS_PAGE_URL'] = $this->http->currentUrl();
        }

        $locale = $this->http->FindPreg("/https:\/\/www\.ipsosisay\.com\/(\w{2}\-\w{2})/", false, $this->http->currentUrl());

        if (!$locale) {
            $this->sendNotification("locale not found");

            return;
        }

        /*
        $this->http->GetURL("https://www.ipsosisay.com/{$locale}/user");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@class = "study-body"]/p[2]/b')));

        if (empty($this->Properties['Name'])) {
            $this->sendNotification('refs #24818 - need to check name // IZ');
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[@class="fa-circle-user"]')));
        }
        */

        return;
        // }

        // Balance - points
        $this->SetBalance($this->http->FindSingleNode('//input[@id="hdnPoints"]/@value | (//a[@class = \'points-link\']/span)[1]'));

        $link = $this->http->FindPreg("/url=(\/GiftArea\/tabid\/\d+\/Default\.aspx)/ims");

        if (!$link) {
            $link = $this->http->FindPreg("/\"([^\"]+\/GiftArea\/tabid\/\d+\/Default\.aspx)/ims");
        }

        if ($link) {
            $this->logger->debug("[Link]: {$link}");

            if (strpos($link, '//') == 0) {
                $link = 'https:' . $link;
            } else {
                $this->http->NormalizeURL($link);
            }
            $this->logger->debug("[Link]: {$link}");
            $this->http->setMaxRedirects(10);
            $this->http->GetURL($link);
            $this->http->setMaxRedirects(5);
            // Amount in cart
            $this->SetProperty("AmountInCart", $this->http->FindSingleNode("//span[contains(@id, 'lblAmountValue')]"));
            // Balance after redemption
            $this->SetProperty("BalanceAfterRedemption", $this->http->FindSingleNode("//span[contains(@id, 'lblAfterValue')]"));
        }// if ($link)

        $profileURL = "/myaccount/public-profile";
        $this->http->NormalizeURL($profileURL);
        $this->http->GetURL($profileURL);

        if (strstr($this->http->currentUrl(), '.com/add/avatar')) {
            $this->http->GetURL($profileURL);
        }
        // Name
        $xpath = "//label[contains(text(), 'Name') or contains(text(), 'Nom') or contains(text(), 'Naam') or contains(text(), 'Nome')]/following-sibling::p[1]";
        $name = Html::cleanXMLValue($this->http->FindSingleNode("{$xpath}/span[1]") . ' ' . $this->http->FindSingleNode("{$xpath}/span[2]"));

        if (empty($name)) {
            $name = Html::cleanXMLValue($this->http->FindSingleNode("//label[contains(., 'First name')]/following-sibling::input/@value") . ' ' . $this->http->FindSingleNode("//label[contains(., 'Last name')]/following-sibling::input/@value"));
        }

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg["NoCookieURL"] = true;
        $arg["PreloadAsImages"] = true;

        return $arg;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[contains(@action, 'loginForm')]//button[@id = 'recaptcha-submit']/@data-sitekey");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function switchCountry()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://social.i-say.com/language");

//        $contintentList = TAccountCheckerIsay::$countrySettings[$this->AccountFields['Login2']]['contintentList'];
//        $countryListList = TAccountCheckerIsay::$countrySettings[$this->AccountFields['Login2']]['countryListList'];
//        $countryList = TAccountCheckerIsay::$countrySettings[$this->AccountFields['Login2']]['countryList'];

        if ($this->AccountFields['Login2'] == 'India') {
            $this->AccountFields['Login2'] = 'भारत';
        }

        $headers = [
            "Accept"                  => "application/xml, text/xml, */*; q=0.01",
            "Wicket-Ajax"             => "true",
            "Wicket-Ajax-BaseURL"     => "language", //$this->http->FindPreg('/(language\?.+)/', false, $this->http->currentUrl()),
            "Wicket-FocusedElementId" => $this->http->FindSingleNode("//span[contains(text(), '{$this->AccountFields['Login2']}')]/following-sibling::span[1]/a[1]/@id"),
            "X-Requested-With"        => "XMLHttpRequest",
            "Referer"                 => str_replace('http:/social.i-say.com/login', 'http://social.i-say.com/login', $this->http->currentUrl()),
        ];
//        $this->http->GetURL($this->http->FindPreg('/(.+language\?\d+)/', $this->http->currentUrl())."-1.IBehaviorListener.0-contintentList-{$contintentList}-countryListList-{$countryListList}-countryList-{$countryList}-languages-0-languageLink&backto=http://social.i-say.com/login&_=".time().date("B"), $headers);

        $currentURL = $this->http->FindSingleNode("//span[contains(text(), '{$this->AccountFields['Login2']}')]/preceding-sibling::img/@onerror", null, true, "/Wicket.Ajax.ajax\(\{\"u\":\"([^\"]+)/");
        $this->http->NormalizeURL($currentURL);
        $this->logger->debug("url: {$currentURL}");
        $contintentList = $this->http->FindPreg("/contintentList\-(\d+)\-/", false, $currentURL);
        $countryListList = $this->http->FindPreg("/countryListList\-(\d+)\-/", false, $currentURL);
        $countryList = $this->http->FindPreg("/countryList\-(\d+)\-/", false, $currentURL);
        $this->http->GetURL($this->http->FindPreg('/(.+language\?\d+)/', false, $currentURL) . "-1.IBehaviorListener.0-contintentList-{$contintentList}-countryListList-{$countryListList}-countryList-{$countryList}-languages-0-languageLink&backto=http://social.i-say.com/login&_=" . time() . date("B"), $headers);

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $this->providerRedirect();

        $this->http->GetURL("https://social.i-say.com/login");

        return true;
    }

    private function providerRedirect()
    {
        $this->logger->notice(__METHOD__);

        if ($redirect = $this->http->FindPreg("/redirect><!\[CDATA\[\.?([^\]]+)/ims")) {
            $this->logger->debug("Redirect to {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);

            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm("talk-api-user-logout-form")) {
            return true;
        }

        if ($this->http->FindSingleNode('//a[@href="/en-us/user/logout"]')) {
            return true;
        }

        if ($this->http->FindSingleNode('//span[@class = "exchange_points_and_new"]/a')) {
            return true;
        }

        return false;
    }
}
