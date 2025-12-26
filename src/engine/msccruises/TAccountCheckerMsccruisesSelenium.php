<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\MonthTranslate;

class TAccountCheckerMsccruisesSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    private $lang = 'en';
    private $preload;
    private $host;
    private $path;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->useSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->disableImages();
        $this->http->saveScreenshots = true;
        $this->logger->notice("Login2: {$this->AccountFields['Login2']}");

        // TODO, $this->preload - You can go to this page from the main page, at the top right, otherwise it will not work

        switch ($this->AccountFields['Login2']) {
            case 'au':
                $this->preload = '/manage-booking/manage-your-booking';
                $this->host = 'https://www.msccruises.com.au';
                $this->path = '/my-msc/msc-voyager-club';

                break;

            case 'be':
                // TODO: Not Accounts
                $this->host = 'https://www.msccruises.be';

                break;

            case 'br':
                $this->lang = 'pt'; // use itinerary
                $this->preload = '/gerenciar-reserva/gerenciar-sua-reserva';
                $this->host = 'https://www.msccruzeiros.com.br';
                $this->path = '/minha-msc/msc-voyager-club';

                break;

            case 'de':
                $this->lang = 'de'; // use itinerary
                $this->preload = '/buchung-verwalten/buchung-verwalten';
                $this->host = 'https://www.msccruises.de';
                $this->path = '/persoenlicher-bereich/msc-voyager-club';

                break;

            case 'it':
                $this->lang = 'it'; // use itinerary
                $this->preload = '/la-mia-prenotazione/gestisci-prenotazione';
                $this->host = 'https://www.msccrociere.it';
                $this->path = '/my-area/msc-voyager-club';

                break;

            case 'es':
                $this->lang = 'es';
                $this->preload = '/mi-reserva/mi-crucero';
                $this->host = 'https://www.msccruceros.es';
                $this->path = '/my-msc/msc-voyager-club';

                break;

            case 'uk':
                $this->preload = '/manage-booking/manage-your-booking';
                $this->host = 'https://www.msccruises.co.uk';
                $this->path = '/my-msc/msc-voyager-club';

                break;

            case 'us':
            default:
                $this->preload = '/manage-booking/manage-your-booking';
                $this->host = 'https://www.msccruisesusa.com';
                $this->path = '/my-msc/msc-voyager-club';

                break;
        }
        $this->logger->notice("Lang: {$this->lang}");
    }

    public function IsLoggedIn()
    {
        $path = urlencode($this->path);

        try {
            $this->http->GetURL($this->host . "/Account/SignIn?ReturnUrl={$path}&CancelUrl={$path}");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: {$e->getMessage()}");
        }

        if ($this->waitForElement(WebDriverBy::id('signoutUrl'), 5)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $path = urlencode($this->path);
        $this->logger->debug($this->http->currentUrl());
        $this->logger->debug($this->path);
        $this->logger->debug($path);

        if (stripos($this->http->currentUrl(), $path) === false) {
            if (isset($this->preload)) {
                try {
                    $this->http->GetURL($this->host . $this->preload);
                } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                }
            }
            $this->http->GetURL($this->host . "/Account/SignIn?ReturnUrl={$path}&CancelUrl={$path}");
        }

        $login = $this->waitForElement(WebDriverBy::id('signInName'), 7);
        $pass = $this->waitForElement(WebDriverBy::id('password'), 0);
        $button = $this->waitForElement(WebDriverBy::id('next'), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::id('signoutUrl'), 0)) {
                return true;
            }

            return $this->checkErrors();
        }
        $login->click();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->click();
        $password = $this->AccountFields['Pass'];
        $pass->sendKeys($password);
        //$this->saveResponse();
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our apologies but we have encountered a problem.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//div[@class = 'error pageLevel']/p[@role = 'alert'] | //div[@class = 'error itemLevel']/p[normalize-space()!=''][1] | //*[@id = 'signoutUrl']"), 10);
        //$this->saveResponse();
        $this->logger->debug("CurrentUrl: " . $this->http->currentUrl());

        if ($error = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'error pageLevel']/p[@role = 'alert'] | //div[@class = 'error pageLevel']/p[normalize-space()!=''][1] | //div[@class = 'error itemLevel']/p[normalize-space()!=''][1]"), 0)) {
            $message = Html::cleanXMLValue($error->getText());
            $this->logger->error("[Error]: " . $message);

            if (
                strstr($message, "Your password is incorrect")
                || strstr($message, 'Due to new data protection regulations, to continue please change your password by clicking on "Change/Reset your password"')
                || strstr($message, "Geben Sie eine gültige E-Mail Adresse an")
                || strstr($message, "We can't seem to find your account")
                || strstr($message, "Please enter a valid email address.")
                || strstr($message, "Details entered are not recognised, please check and try again. If your account was created more than 30 days ago, please sign-up again.")
                || strstr($message, "Detalhes inseridos não foram reconhecidos, por favor confira e tente novamente. Se sua conta foi criada mais de 30 dias atrás, por favor, faça um novo cadastro.")
                || strstr($message, "I dettagli inseriti non sono stati riconosciuti, per favore controlla e riprova. Se il tuo account è stato creato più di 30 giorni fa, ti preghiamo di registrarti di nuovo.")
                || strstr($message, "Sua senha está incorreta, por favor tente novamente ou clique em alterar/ criar nova senha abaixo")
                || $message == "Por favor insira um endereço de e-mail válido"
                || $message == "Ihr Passwort ist falsch, bitte versuchen Sie es erneut oder lassen Sie Ihr Passwort zurücksetzen"
                || $message == "Si prega di inserire un indirizzo email valido."
                || $message == "Voer een geldige e-mailadres in"
                || $message == "Devido ao novo regulamento de proteção de dados, por favor, avance para alterar sua senha clicando em \"Alterar/Criar nova senha\""
                || $message == "La password non è corretta, riprova o fai clic su Reimposta la password qui sotto"
                || $message == "Leider können wir Ihren MSC Account nicht finden. Bitte überprüfen Sie Ihre Angaben und versuchen es erneut. Falls Ihr MSC Account vor mehr als 30 Tage erstellt wurde, bitte HIER einloggen"
                || $message == "Uw wachtwoord is onjuist. Probeer het opnieuw of klik hieronder op Uw wachtwoord wijzigen/opnieuw instellen"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        if ($this->waitForElement(WebDriverBy::xpath("//div[@class='modal-body' and contains(.,'LABEL_VoyagerClubNumberTextError')]"), 0)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        $this->saveResponse();
        // Accept All Cookies
        $next = $this->waitForElement(WebDriverBy::xpath("//*[@id='onetrust-accept-btn-handler' or @id='didomi-notice-agree-button']"), 0);

        if ($next) {
//            $next->click();
            $this->logger->notice("accept cookies");
            $this->driver->executeScript("document.querySelector(\"[id='onetrust-accept-btn-handler'], [id='didomi-notice-agree-button']\").click();");
            sleep(2);
            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::id('signoutUrl'), 0)) {
            return true;
        }

        if (
            strstr($this->http->currentUrl(), '/account/messages/user-country-is-not-allowed')
            || strstr($this->http->currentUrl(), '/conta/messages/user-not-enabled')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if (strstr($this->http->currentUrl(), '/Account/Registration?context=')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // AccountID: 6612696
            strstr($this->http->currentUrl(), 'message=System.InvalidOperationExcepti&description=UserId+not+found.')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Complete sua conta MSC')]"), 0)) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'An error occured, please try again later')]
                | //span[contains(text(), 'Our website is temporarily offline for planned maintenance')]
                | //p[contains(text(), 'Sorry you do not have access to this page')]
                | //p[contains(text(), 'Er is helaas iets misgegaan, probeer het later nogmaals')]
                | //p[contains(normalize-space(text()), 's email has not been validated yet.')]
            "), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - points
        $name = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'nameMember' and normalize-space()!='ModelLabel_CustomerDisplayName'] | //div[contains(@class, 'my-voyager-club__card-info')]/span[1]/span"), 30);
        $this->saveResponse();

        if ($name) {
            $balance = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'points'] | //span[contains(@class, 'points-label')]"), 10);
            $cardNumber = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'card-number'] | //div[contains(@class, 'my-voyager-club__card-info')]/span[2]/span"), 3);
            $eliteLevel = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'typeMember']/span[1] | //span[contains(@class, 'card-level')]"), 3);
            $this->SetBalance($this->http->FindPreg('/^\s*([\d.,]+)\s+/', false, $balance->getText()));
            // Name
            $this->SetProperty('Name', beautifulName($name->getText()));
            // Card #
            $this->SetProperty('Number', $cardNumber ? $cardNumber->getText() : null);
            // MEMBERSHIP
            $this->SetProperty('EliteLevel', $eliteLevel ? $eliteLevel->getText() : null);
            // Points to Next Level
            $val = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'level']/div[@class = 'pointLevel'] | //div[contains(@class, 'my-voyager-club__points-scale-wrap')]/span/b"), 0);

            if ($val) {
                $this->SetProperty('PointsNextLevel', $this->http->FindPreg(self::BALANCE_REGEXP, false, $val->getText()));
            }
            // refs# 18750 Expiration date
            $exp = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'date-points']/div[@class = 'date'] | //div[contains(@class, 'my-voyager-club__card-info')]/span[3]/span"), 0);

            if (isset($balance, $exp) && $this->Balance > 0) {
                if ($exp = strtotime($exp->getText(), false)) {
                    $this->SetExpirationDate($exp);
                }
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//label[
                    contains(text(), "Are you already a MSC Voyager Club Member?")
                    or contains(text(), "Sei già socio MSC Voyager Club?")
                    or contains(text(), "Sind Sie schon MSC Voyagers Club Mitglied?")
                    or contains(text(), "Você já é um membro do MSC Voyager Club?")
                ]
                | //a[contains(text(), "Torne-se um membro")]
                | //h3[
                    contains(text(), "Já é Membro do MSC Voyager Club?")
                ]
            ') ||
                $this->http->FindSingleNode('//p[
                    contains(text(), "Se um membro do MSC Voyagers Club significa entrar em um mundo de privilégios que vai continuar a crescer cada vez que você viajar com a MSC.")
                    or contains(text(), "Ein MSC Voyagers Club-Mitglied zu sein bedeutet, in eine Welt der Privilegien einzutreten, die umso größer werden, je mehr Sie mit uns reisen.")
                    or contains(text(), "Being an MSC Voyagers Club member means entering a world of privileges that will continue to grow the more you cruise with us.")
                ]')
            ) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }

            // AccountID: 4977862, 5463815
            if ($message = $this->http->FindSingleNode('//div[
                    contains(text(), "There is a problem with your data. Please check and try again.")
                    or contains(text(), "Es liegt ein Problem mit Ihren Daten vor. Bitte überprüfen Sie und versuchen Sie es erneut.")
                    or contains(text(), "Há um problema com seus dados. Por favor verifique e tente novamente.")
                ]')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveResponse();

            if ($this->AccountFields['Login2'] == 'us') {
                if ($this->waitForElement(WebDriverBy::xpath("//h3[@class = 'membership-title' and normalize-space()='ModelLabel_MembershipTitle']"), 0)) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $urlCruises = $this->host . "/my-area/all-my-cruises";
        $this->http->GetURL($urlCruises);

        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[starts-with(normalize-space(),'Dear Guest, for this reservation we kindly invite you to call')]"),
            0)
        ) {
            $this->logger->error($message->getText());

            return $result;
        }

        $xpathNoIts = "//h1[
                    contains(normalize-space(.), 'non sono al momento disponibili. Se hai già una crociera prenotata, associa il numero di prenotazione al tuo account')
                    or contains(normalize-space(.), 'Gentile Cliente, potrebbero essere necessarie fino a 24 ore dalla prenotazione per rendere disponibili i dettagli della tua crociera')
                    or contains(text(), 'please if you have booked a cruise link your Booking ID to your account')
                    or contains(text(), 'Lieber Gast, ab der Buchung kann es bis zu 24 Stunden dauern bis Sie alle Details für Ihre Kreuzfahrt einsehen können.')
                    or contains(text(), 'Prezado hóspede, pode levar até 24 horas para que os detalhes da reserva de seu cruzeiro fiquem disponíveis.')
                    or contains(text(), 'Estimado pasajero, los detalles del crucero pueden tardar hasta 24h en ser visibles.')
        ]";
        $this->waitForElement(WebDriverBy::xpath("
                {$xpathNoIts}
                | (//*[@class='tile--mymsc-cruises__details'])[1]
        "), 15);

        if ($this->AccountFields['Login2'] == 'es') {
            $this->saveResponse();
        }

        // Accept All Cookies
        if ($next = $this->waitForElement(WebDriverBy::id("onetrust-accept-btn-handler"), 0)) {
            $next->click();
        }

        if ($this->waitForElement(WebDriverBy::xpath($xpathNoIts), 0)) {
            return $this->noItinerariesArr();
        }

        if ($this->AccountFields['Login2'] == 'es') {
            $this->sendNotification('es it // MI');
        }
        // title: Tutte le mie crociere | All my cruises
        if (!$this->waitForElement(WebDriverBy::xpath("(//*[@class='tile--mymsc-cruises__details'])[1]"), 0)) {
            return $result;
        }

        if (!in_array($this->AccountFields['Login'], ['srakos@web.de', 'pascal.langenstein@gmail.com'])
            && !in_array($this->AccountFields['Login2'], ['uk', 'us', 'it', 'br', 'de', 'au'])) {
            $this->sendNotification($this->AccountFields['Login2'] . ' - new itineraries // MI');
        }

        $xpath = "
            //section[@class='tile-container--mymsc']
            | //button[
                normalize-space(text()) = 'See details'
                or normalize-space(text()) = 'Vedi dettagli' 
                or normalize-space(text()) = 'Ver detalhes'
                or normalize-space(text()) = 'Details ansehen'
            ]
        ";
        $elements = $this->driver->findElements(WebDriverBy::xpath($xpath));
        $this->logger->debug($xpath);
        /*if (!empty($elements) && (!empty($this->http->FindNodes("//section[@class='mycruise--all-cruises']")))) {
            $this->driver->executeScript("$('section.mycruise--all-cruises span a').get(0).click()");
            $this->waitForElement(WebDriverBy::xpath($xpath), 1);
            $urlCruises = $this->http->currentUrl();
            $elements = $elements = $this->driver->findElements(WebDriverBy::xpath($xpath));
        }*/
        if (!empty($elements)) {
            $this->logger->debug("Total " . count($elements) . " itineraries were found");

            foreach ($elements as $i => $element) {
                // Dear Guest, for this reservation we kindly invite you to call our contact center at 1-877-665-4655 specifying your booking ID in order
                // to reserve another cruise or fill in this form to be called back. We look forward to welcoming you on board again.
                $this->increaseTimeLimit(180);
                $section = $this->waitForElement(WebDriverBy::xpath(
                    "(//section[@class='tile-container--mymsc'])[" . ($i + 1) . "]//div[@class='tile--mymsc-cruises__title']"), 1);

                if (!$section) {
                    continue;
                }
                $this->driver->executeScript("$('section.tile-container--mymsc button.button').eq({$i}).get(0).click()");

                if ($this->waitForElement(WebDriverBy::xpath("//ul[@class='my-itinerary__list']/li[contains(@class,'my-itinerary__box') and contains(@class,'active')]"), 15)) {
                    $this->parseItinerary();
                    // $selenium->driver->executeScript("window.history.go(-1)");
                    $this->logger->notice("Back URL: " . $urlCruises);
                    $this->http->GetURL($urlCruises);
                } else {
                    $this->logger->error("Segment not detected");
                }
            }
        }

        return $result;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $c = $this->itinerariesMaster->add()->cruise();
        $this->saveResponse();
        $text = $this->waitForElement(WebDriverBy::xpath("//*[@class='tile--mymsc-cruises__details']
        /span[contains(text(), 'Booking ID:') or contains(text(), 'Número da reserva:') or contains(text(), 'Buchungsnummer:')]"), 0);

        if (!$text) {
            $text = $this->http->FindSingleNode("//*[@class='tile--mymsc-cruises__details']/span[1]");
        } else {
            $text = $text->getText();
        }

        if ($text) {
            $confNo = $this->http->FindPreg('/:\s*(\w+)/', false, $text);
            $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
            $c->general()->confirmation($confNo);
        } elseif (!$text) {
            return false;
        }

        // Close Popup
        $closePopup = $this->waitForElement(WebDriverBy::xpath("//*[@class='modal-header']/div[2]/*[1]"), 0);

        if ($closePopup) {
            $closePopup->click();
        }
        $dates = $this->waitForElement(WebDriverBy::xpath("//span[@class='tile--mymsc-cruises__from-port']"), 0);

        if ($dates) {
            // 7 Dec 2019 - 21 Dec 2019
            // 28 dic 2019 - 4 gen 2020
            // 24 set 2020 - 30 set 2020 - br
            // 16 Apr. 2021 - 18 Apr. 2021 - de
            if (empty($dateStartTxt = $this->http->FindPreg('/\s*(\d+ \w+\.? \d{4})\s*-/', false, $dates->getText()))) {
                // Jan 2 2021 - Jan 9 2021
                $dateStartTxt = preg_replace("/(\w+) (\d+) (\d{4})/", '$2 $1 $3',
                    $this->http->FindPreg('/\s*(\w+ \d+ \d{4})\s*-/', false, $dates->getText()));
            }
            $dateStart = $this->dateStringToEnglish($dateStartTxt, $this->lang);
            $this->logger->notice("Date Start: {$dateStart}");
            $dateStart = strtotime($dateStart);
        } else {
            $this->logger->notice('Skip item: not date');

            return false;
        }

        $this->driver->executeScript("scroll(0, 500)");
        $days = 0;
        $duration = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class,'tile--mymsc-cruises__duration')]"), 0);

        if ($duration) {
            $days = $this->http->FindPreg('/(\d+) [[:alpha:]]+/', false, $duration->getText()) + 1;
        }

        if ($days == 0) {
            return false;
        }

        if ($days > 30) {
            $this->increaseTimeLimit(380);
        } elseif ($days > 20) {
            $this->increaseTimeLimit(250);
        } elseif ($days > 10) {
            $this->increaseTimeLimit(100);
        }
        $this->logger->debug("Found {$days} days");
        $date = null;

        for ($i = 0; $i <= $days; $i++) {
            $xpath = "//ul[@class='my-itinerary__list']/li[contains(@class,'my-itinerary__box') and contains(@class,'active')]";
            $element = $this->waitForElement(WebDriverBy::xpath($xpath));

            if (empty($element)) {
                $this->logger->debug('Skip item');

                continue;
            }
            $box = $element->getText();
            $this->logger->debug($box);

            // DAY 7 - JAN 8 Ocean Cay Arrival 09:00 AM Departure 06:00 PM
            // DIA 3 - 26 SET Barcelona Chegada 07:00 Saída 19:00
            // TAG 1 - 16 APR. Marseille Ihre Einschiffungszeit wird bald bekannt gegeben Abfahrt 18:00
            if (preg_match('/(?:DAY|GIORNO|DIA|TAG) \d+ - (?<date>\d+ \w+\.?|\w+ \d+)\s+(?<port>.+?)\s+'
                . '(?:Arrival|Departure|Arrivo|Partenza|Chegada|Saída|Ankunft|Abfahrt|Ihre Einschiffungszeit)/us', $box, $matches)) {
                $this->logger->debug(var_export($matches, true));

                if (isset($date)) {
                    $prevDate = $date;
                }
                $matches['date'] = preg_replace("/(\w+) (\d+)/", '$2 $1', $matches['date']);
                $date = $this->dateStringToEnglish($matches['date'], $this->lang);
                $this->logger->notice("Date: {$date}");
                $date = strtotime($date, $dateStart);

                if (isset($prevDate)) {
                    if ($prevDate > $date) {
                        $date = strtotime('+1 year', $date);
                    }
                }
                $s = $c->addSegment();
                $s->setName($matches['port']);
            } else {
                $this->logger->notice('Skip item: not segment');
            }

            if (isset($s) && ($time = $this->http->FindPreg('/(?:Arrival|Arrivo|Chegada|Ankunft) (\d+:\d+(?:\s*[AP]M)?)/', false, $box))) {
                $s->setAshore(strtotime($time, $date));
            }

            if (isset($s) && ($time = $this->http->FindPreg('/(?:Departure|Partenza|Saída|Abfahrt) (\d+:\d+(?:\s*[AP]M)?)/', false, $box))) {
                $s->setAboard(strtotime($time, $date));
            }

            if ($next = $this->waitForElement(WebDriverBy::xpath("//ul[@class='my-itinerary__list']/li[contains(@class,'my-itinerary__box')]/following-sibling::li[contains(@class,'my-itinerary__arrow') and not(contains(@class,'disabled'))][last()]"), 0)) {
                $next->click();
            //$this->saveResponse();
            } else {
                break;
            }
        }

        $urlCabin = "{$this->host}/my-msc/cruise-details#/cabins";
        $urlPrice = "{$this->host}/my-msc/cruise-details#/price-details-payment";

        switch ($this->AccountFields['Login2']) {
            case 'br':
                $urlCabin = "{$this->host}/minha-msc/cruise-details#/cabins";
                $urlPrice = "{$this->host}/minha-msc/cruise-details#/price-details-payment";

                break;

            case 'it':
                $urlCabin = "{$this->host}/my-area/cruise-details#/cabins";
                $urlPrice = "{$this->host}/my-area/cruise-details#/price-details-payment";

                break;

            case 'de':
                $urlCabin = "{$this->host}/persoenlicher-bereich/cruise-details#/cabins";
                $urlPrice = "{$this->host}/persoenlicher-bereich/cruise-details#/price-details-payment";

                break;
        }
        $this->http->GetURL($urlCabin);

        if ($deck = $this->waitForElement(WebDriverBy::xpath("//li/strong[contains(text(),'Deck:') or contains(text(),'Ponte:') or contains(text(),'Andar:')]/following-sibling::span"), 1)) {
            $c->details()->deck($deck->getText(), true, false);
        }

        if ($roomNumber = $this->waitForElement(WebDriverBy::xpath("//li/strong[contains(text(),'Cabin:') 
        or contains(text(),'Cabina:') or contains(text(),'Cabine:') or contains(text(),'Kabine:')]/following-sibling::span"), 0)) {
            $c->details()->room($roomNumber->getText());
        }

        $xpath = "//li[h3[contains(text(),'Passengers:') or contains(text(),'Passeggeri:') or contains(text(),'Hóspedes:') or contains(text(),'Passagiere:')]]/following-sibling::li";
        $elements = $this->driver->findElements(WebDriverBy::xpath($xpath), 0);

        foreach ($elements as $i => $element) {
            $c->general()->traveller(beautifulName($element->getText()));
        }

        if ($class = $this->waitForElement(WebDriverBy::xpath("//li/strong[contains(text(),'Type:') or contains(text(),'Tipologia:') 
        or contains(text(),'Tipo:') or contains(text(),'Art:')]/following-sibling::span"), 0)) {
            $c->details()->roomClass($class->getText());
        }

        if (!$urlPrice) {
            return true;
        }
        $this->http->GetURL($urlPrice);
        $this->saveResponse();

        if ($total = $this->waitForElement(WebDriverBy::xpath("//p[
        contains(text(),'Total booking value:') 
        or contains(text(),'Valore totale della prenotazione:') 
        or contains(text(),'Valor total da reserva:') or contains(text(),'Gesamter Reisepreis:')]/following-sibling::p"), 5)) {
            $total = $this->getTotalCurrency($total->getText());
            $this->logger->debug(var_export($total, true));
            $c->price()->total($total['TotalCharge']);
            $c->price()->currency($total['Currency']);
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);

        return true;
    }

    private function dateStringToEnglish($date, $lang = 'en')
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        // R$ 8.568,18
        $node = str_replace([
            "€", "£", "$", "R$", "₹",
        ], [
            "EUR", "GBP", "USD", "BRL", "INR",
        ], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            // € 21,21
            if (in_array($this->lang, ['pt', 'it', 'de'])) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            } else {
                $tot = PriceHelper::cost($m['t']);
            }
        }

        return ['TotalCharge' => $tot, 'Currency' => $cur];
    }
}
