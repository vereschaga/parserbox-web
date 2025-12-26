<?php

require_once __DIR__ . '/../tamair/functions.php';

//class TAccountCheckerTamair extends TAccountChecker{

class TAccountCheckerTamair extends TAccountCheckerMultiplus
{
    public function InitBrowser()
    {
        parent::InitBrowser();

//        $this->AccountFields['Login'] = $this->AccountFields['Login3'];
//        $this->AccountFields['Pass'] = $this->AccountFields['Login2'];

        if (strlen($this->AccountFields['Login']) < 11) {
            $this->ErrorMessage = "Please note that LAN and TAM announced the adoption of a new, single brand called LATAM. TAM Fidelidade services, such as checking your Multiplus Points balance, etc., now can be found on the Multiplus website. Currently you can access both Multiplus and TAM websites with your CPF a single password. Please set a single password for both websites and update your login information. In order to do it, please click the “Edit” button related to this account.";
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

            return false;
        }
    }

    /*private $domain = 'http://www.tam.com.br';
    private $link = null;
    private $balanceLink = null;

    public function TuneFormFields(&$arFields, $values = NULL) {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Required"] = false;
        $arFields["Login2"]["InputType"] = 'password';
        $arFields["Login2"]["Note"] = 'You need to fill in this information if you want AwardWallet to track your Expiration Date for this program.';/*review* /
        ArrayInsert($arFields, "Login2", true, array("Login3" => array(
            "Type" => "string",
            "Required" => false,
            "Caption" => "CPF # (Optional)",
            "Note" => 'You need to fill in this information if you want AwardWallet to track your Expiration Date for this program.'/*review* /
        )));
    }

    function LoadLoginForm(){

//        throw new CheckException("We are moving to continue our journey together. Starting on December 12, 2015, LAN and TAM will have just one website in the USA, at LAN.com. This means that TAM services will temporarily be available on this new portal in the English language only, while the Portuguese and Spanish languages are also integrated over the next few months.", ACCOUNT_PROVIDER_ERROR);

        $this->http->removeCookies();
        $this->http->LogHeaders = true;
//		$this->http->GetURL($this->domain);
        // cookies
        $this->http->GetURL("http://www.tam.com.br/b2c/vgn/jsp/indexNH.jsp?v-locale=en_US?v-pais=US");
        $this->link = $this->http->currentUrl();
        // getting JSESSIONID
        $this->http->PostURL("http://www.tam.com.br/b2c/jsp/criaUsuarioBean.jsp?combo_pais=US", array());
        $this->checkErrors();

        $this->http->GetURL($this->link);

        if (!$this->http->ParseForm("formLogin"))
            return $this->checkErrors();
        $this->http->FormURL = 'http://www.tam.com.br/b2c/jsp/login.jhtml';
        $this->http->Form["HOME_NOVA_LOGIN"] = 'true';
        $this->http->Form["combo_pais"] = 'BR';
        $this->http->Form["hasTemplate"] = 'true';
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("senha", $this->AccountFields['Pass']);

        return true;
    }

    function checkErrors() {
        // Service in maintenance
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Site em Manutenção')]/@alt", null, true, null, 0))
            throw new CheckException("Some areas of our website are currently undergoing maintenance.", ACCOUNT_PROVIDER_ERROR);
        ## Website Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'home_manutencao_01')]/@src", null, true, null, 0))
            throw new CheckException("Website Temporarily Unavailable", ACCOUNT_PROVIDER_ERROR);
        // provider error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    function Login() {
        // Balance URL
        $this->balanceLink = $this->http->currentUrl();
        $this->http->Log("[Balance URL: $this->balanceLink]");
        // for Elite Level
        $this->link = $this->http->FindSingleNode("(//a[contains(text(), 'My Loyalty') or contains(@href, 'CarregaDadosCadastro')]/@href)[1]");
        if (!empty($this->link))
            $this->http->NormalizeURL($this->link);
        $this->http->Log("[Information: $this->link]");

        sleep(1);

        if (!$this->http->PostForm())
            return $this->checkErrors();

        ## Balance is unavailable in current time
        if ($this->http->FindPreg("/Consulta de saldo .+ no momento/ims"))
            throw new CheckException('Consulta de saldo indisponivel no momento. Tente mais tarde, por favor.', ACCOUNT_PROVIDER_ERROR);

        if ($auth_token = $this->http->FindPreg("/auth_token=([^\;]+)/")) {
            $this->http->setCookie("auth_token", $auth_token, ".tam.com.br");
            return true;
        }
        // Para ter acesso ao Minha Conta TAM e habilitar seu login por CPF, clique aqui e cadastre uma nova senha.
        if ($message = $this->http->FindPreg("#Para ter acesso ao Minha Conta TAM e habilitar seu login por CPF,  <a href='[^\']+'>clique aqui<\/a> e cadastre uma nova senha\.#"))
            throw new CheckException(str_replace('/b2c/', 'http://www.tam.com.br/b2c/', $message), ACCOUNT_INVALID_PASSWORD);
        ## Electronic signature is not valid
        if ($this->http->FindSingleNode("//b[contains(text(),'Digite sua assinatura')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'Invalid Password')]"))
            throw new CheckException("Invalid Password. Type your electronic signature (registered on the website) not your password for Fidelity points rescue. (sent by Multiplusl). Type again respecting capital and lowercase letters, accents and spaces.", ACCOUNT_INVALID_PASSWORD);

        ## The number of its loyalty card is not registered in site
        if ($this->http->FindSingleNode("//b/u[contains(text(), 'pontos e zeros na esquerda. Cart')]")
            || $this->http->FindPreg("/Your Fidelidade Card Number is not registrated in our website/ims"))
            throw new CheckException("Your Fidelidade Card Number is not registrated in our website. Type your Fidelidade number without spaces, dashes, dots and zeros on the left.", ACCOUNT_INVALID_PASSWORD);

        ## Your address is not registered in our database or validity expired
        if ($message = $this->http->FindPreg("/(Seu endereço não está cadastrado em nosso banco de dados ou a validade expirou. Entre em contato com nossa central de atendimento \( veja os números no menu \"contatos\"\) para atualização\.)\(F/ims"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        ## Check the highlighted fields and Resubmit
        if ($message = $this->http->FindPreg("/(Verifique os campos assinalados e Envie novamente\.)/ims"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        ## You entered a card number canceled
        if ($message = $this->http->FindPreg("/(Você digitou um número de cartão cancelado\.)/ims"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Your address is not recorded in our database, or its validity has expired
        if ($message = $this->http->FindPreg("/(Your address is not recorded in our database, or its validity has expired\.)/ims"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Sua Conta TAM está pendente de ativação. Para ativá-la, acesse o email informado no cadastro.
        if ($message = $this->http->FindPreg("/(Sua Conta TAM est.+ pendente de ativa.+o\.\s*Para ativ.+-la, acesse o email informado no cadastro\.)/ims"))
            throw new CheckException("Sua Conta TAM está pendente de ativação. Para ativá-la, acesse o email informado no cadastro.", ACCOUNT_PROVIDER_ERROR);
        // profile update
        if ($message = $this->http->FindPreg("/(window\.location=\"\/b2c\/jsp\/cadastrese\/loginExtrato\.jsp\?vgnextoid=[^\"]+)/ims"))
            throw new CheckException("TAM Airlines (Fidelidade) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        // You entered the number of a cancelled card.
        if ($refresh = $this->http->FindPreg('/<meta http-equiv="refresh" content="0;URL=(\/b2c\/jsp\/erro\.jhtml\?vgnextoid=[^\"]+)/ims')) {
            $this->http->NormalizeURL($refresh);
            $this->http->GetURL($refresh);
            // retries
//            if ($this->http->FindPreg("/null (INT9999-9999)/"))
                throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);

//            throw new CheckException("You entered the number of a cancelled card.", ACCOUNT_INVALID_PASSWORD);
        }
        // Access blocked due to excess number of incorrect attempts.
        if ($message = $this->http->FindPreg('/(?:Access blocked due to excess number of incorrect attempts\.|Access blocked for security reasons\.)/ims'))
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        // USER AND/OR PASSWORD INVALID
        if ($message = $this->http->FindPreg("/(\{\"error\":\"LOGIN_INVALIDO\"\})/ims"))
            throw new CheckException("USER AND/OR PASSWORD INVALID", ACCOUNT_INVALID_PASSWORD);
        // provider error
        if ($message = $this->http->FindPreg("/(\{\"error\":\"SANITIZAR\"\})/ims"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

        if ($message = $this->http->FindPreg("/(\{\"error\":\"CAPTCHA\"\})/ims")) {
            // redirect
//            http://www.tam.com.br/b2c/jsp/home/redirectLogin.jsp
            // catpcha image
//            http://www.tam.com.br/b2c/jsp/ShowCaptcha.jhtml?hasTemplate=true
            throw new CheckException("TAM Airlines (Fidelidade) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    function Parse() {
        // Name
        $this->SetProperty("Name", beautifulName(CleanXMLValue($this->http->FindPreg("/<FIRST_NAME_1>([^<]+)<\/FIRST_NAME_1>/ims")." ".$this->http->FindPreg("/<LAST_NAME_1>([^<]+)<\/LAST_NAME_1>/ims"))));
        $statusLevel = $this->http->FindPreg("/<TYPE_CARD>.*?([0-9\.]+).*?<\/TYPE_CARD>/ims");

        // Balance
        $this->http->Log("Get Balance URL");
        $this->http->GetURL($this->balanceLink);
        // Balance - You have accrued ... Multiplus points
        $balance = $this->http->FindSingleNode("//input[@name = 'saldoPontosMultiplus']/@value");
        if (!$this->SetBalance($balance))
            // Balance is unavailable at this time. Please try again later.
            if ($message = $this->http->FindPreg("/Balance is unavailable at this time\. Please try again later\./"))
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        if (!empty($this->link))
            $this->http->GetURL($this->link);

        // Expiration date  // refs #6852
        $cpf = $this->http->FindSingleNode("//tr[@id = 'CPF2']/td[1]");
        $this->http->Log("[CPF]: $cpf");

        if ($link = $this->http->FindSingleNode('//a[contains(text(), "My Fidelidade") or contains(@href, "CarregaDadosCadastro")]/@href')) {
            $this->http->GetURL($this->domain . $link);
            $link = $this->http->FindSingleNode('//a[contains(text(), "Movimentações TAM Fidelidade") or contains(text(), "Points statement")]/@href', null, true, "/javascript:redirectLoginInterno\(\'([^<\']+)\'\)/ims");
        }
        if (empty($link)) {
            $link = $this->http->FindSingleNode('//a[contains(text(), "Points statement") or contains(text(), "Solicitação de pontos")]/@href');
        }
        if (!empty($link)) {
            $this->http->Log("Get URL for Elite Level");
            $this->http->Log("$link");
            $this->http->GetURL($this->domain . $link);
            // Points Accumulates
            $this->SetProperty('PointsAccumulated', $this->http->FindSingleNode('//td[contains(text(), "Points balance accumulated for upgrade") or contains(text(), "Pontos válidos para upgrade")]/following-sibling::td[1]'));
            // Points Missing Upgrade
            $this->SetProperty('PointsMissingForUpgrade', $this->http->FindSingleNode('//td[contains(text(), "Amount of points missing for class upgrade") or contains(text(), "Pontos faltantes para upgrade")]/following-sibling::td[1]'));
        }
        // Elite Level
        $eliteLevel = $this->http->FindSingleNode("(//img[contains(@src, '/b2c/vgn/img/nb_fotos/')]/@src)[1]", null, true, '/_([a-zA-Z]+)\.gif/ims');
        if (empty($eliteLevel)) {
            $eliteLevel = $statusLevel;
        }
        switch ($eliteLevel) {
            case '1':
            case 'branco':
                $this->SetProperty("EliteLevel", "White");
                break;
            case '2':
            case 'azul':
                $this->SetProperty("EliteLevel", "Blue");
                break;
            case '3':
            case 'vermelho':
                $this->SetProperty("EliteLevel", "Red");
                break;
            case '5':
            case 'vermelho_plus':
            case 'plus':
                $this->SetProperty("EliteLevel", "Red Plus");
                break;
            case '4':
            case 'preto':
                $this->SetProperty("EliteLevel", "Black");
                break;
            default:
                $this->http->Log("[@src: $eliteLevel]");
        }// switch ($eliteLevel)
        // Level valid till
        if (isset($this->Properties['EliteLevel']) && $this->Properties['EliteLevel'] != "White")
            $this->SetProperty("LevelValidUntil", $this->http->FindSingleNode("//div[contains(text(), 'Valid until:')]", null, true, "/:\s*([^<]+)/"));

        /*
         * Please check multiplus authorization
         * /

        // Expiration date  // refs #6852
        $balance = str_replace('.', '', $balance);
        if (!isset($cpf) && !empty($this->AccountFields['Login3']) && is_numeric($this->AccountFields['Login3']))
            $cpf = $this->AccountFields['Login3'];
        if (isset($cpf) && $balance > 0 && !empty($this->AccountFields['Login2'])) {
            $this->http->setMaxRedirects(7);
            $this->http->GetURL("https://www.pontosmultiplus.com.br/login");
            if (!$this->http->ParseForm("frm-login"))
                return $this->checkErrors();
            $this->http->SetInputValue("user", $cpf);
            $this->http->SetInputValue("password", $this->AccountFields['Login2']);
            $this->http->PostForm();

            $this->http->GetURL("https://portal.pontosmultiplus.com.br/portal/pages/home.html");
            // Pontos a Vencer
            $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//span[@id = 'lblPontosVencer']"));
            // Expiration date
            $exp = $this->http->FindSingleNode("//b[span[contains(text(), 'Pontos a Vencer')]]/following-sibling::span", null, true, "/Em\s*([^<]+)/ims");
            if (isset($exp))
                $exp = $this->ModifyDateFormat($exp);
            if ($exp = strtotime($exp))
                $this->SetExpirationDate($exp);
        }// if (isset($cpf) && $balance > 0)
    }

    function GetRedirectParams($targetURL = NULL){
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = $this->domain;
        return $arg;
    }*/

    public function GetConfirmationFields()
    {
        // <form action="https://book.tam.com.br/TAM/dyn/air/servicing/retrievePNR;jsessionid=XcpsSTTLMPhSXjPPZ3M0RWC68rXd4l11NvYKy19Lmnv1L994J0hH!1170386837!959131373" method="post" name="MANAGE_BOOKING_FORM" id="MANAGE_BOOKING_FORM" target="_top">       <input name="SITE" value="JJBKJJBK" type="hidden"> <input name="SWITCH_TO_SSL" value="true" type="hidden"> <input name="LANGUAGE" value="GB" type="hidden"> <input name="WDS_MARKET" value="OC" type="hidden"> <input name="ACTION" value="MODIFY" type="hidden"> <input name="DIRECT_RETRIEVE" value="TRUE" type="hidden"> <input name="REFRESH" value="0" type="hidden"> <legend class="fl br mr"><span>Search your flight</span></legend> <ul class="default"> <li>Check your reservation and edit details.</li> </ul> <p class="br"></p> <p class="wrap br"> <label class="fl grid3" for="search_code">Booking Reference Code&nbsp;</label> <input class="fl input" id="search_code" name="REC_LOC" size="14" autocomplete="off" maxlength="6" tabindex="30" type="text"> </p> <p class="wrap"> <label class="fl grid3" for="search_family">Last name&nbsp;</label> <input class="fl input" name="DIRECT_RETRIEVE_LASTNAME" id="search_family" size="14" autocomplete="off" maxlength="40" tabindex="31" type="text"> </p> <footer class="tr br"> <button class="main right" id="manageBookingSubmitButton" type="Submit" tabindex="32">Review</button> </footer> </form>
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference Code",
                "Type"     => "string",
                "Size"     => 6,
                "Cols"     => 6,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Cols"     => 40,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "http://www.tam.com.br/b2c/vgn/v/index.jsp?vgnextoid=318eecacb8a8a210VgnVCM1000009508020aRCRD";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->PostURL('https://book.tam.com.br/TAM/dyn/air/servicing/retrievePNR',
            ['SITE'                        => 'JJBKJJBK',
                'SWITCH_TO_SSL'            => 'true',
                'LANGUAGE'                 => 'GB',
                'WDS_MARKET'               => 'OC',
                'ACTION'                   => 'MODIFY',
                'DIRECT_RETRIEVE'          => 'TRUE',
                'REFRESH'                  => '0',
                'REC_LOC'                  => $arFields['ConfNo'],
                'DIRECT_RETRIEVE_LASTNAME' => strtoupper($arFields['LastName']),
            ]
        );
        $recordLocator = $this->http->FindSingleNode("//strong[@id = 'recLoc']");

        if (!$recordLocator) {
            $recordLocator = $this->http->FindSingleNode("//h2[contains(text(), 'Your booking reference number is')]", null, true, "/number\s*is\s*([^<]+)/");
        }

        if ($recordLocator) {
            $it = $this->ParseConfirmation($recordLocator);
        }
        // Error: We cannot locate the request indicated.
        elseif ($messages = $this->http->FindSingleNode("//li[@class = 'em icob']")) {
            return $messages;
        } else {
            $this->sendNotification("tamair - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");
        }

        return null;
    }

    public function ParseConfirmation($recordLocator)
    {
        $result = [
            "Kind"          => "T",
            "RecordLocator" => $recordLocator,
        ];
        // Passengers
        $result['Passengers'] = $this->http->FindNodes("//dd[@class = 'paxNameFields']/strong");
        // Currency
        $result['Currency'] = $this->http->FindSingleNode('(//strong[contains(text(), "Total:")])[1]', null, true, '/Total:\s+(.*?)\s+(.*)/ims');
        $result['TotalCharge'] = $this->http->FindSingleNode('(//strong[contains(text(), "Total:")])[last()]', null, true, '/Total:\s+(.+)/ims');

        // Air trip segments

        $seats = $this->http->FindNodes("//table[@class = 'default boxw br seatPanel']/descendant::tbody/descendant::tr[1]/following-sibling::tr[1]/descendant::td[1]");
        $this->http->Log("Total " . count($seats) . " seats were found");

        $segments = $this->http->XPath->query("//div[@class = 'wrap' or @class = 'wrap br']");
        $this->http->Log("Total {$segments->length} segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = [];
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode("descendant-or-self::a[@class='linkFlif em']", $segments->item($i));
            // DepName
            $segment['DepName'] = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Outbound:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i));
            // DepCode
            $depName = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Outbound:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i), true, "/([^\,]+)/");
            $code = $this->http->FindPreg("/{$depName}(?:\s*|&nbsp;)\(([A-Z]{3})\)/");

            if (empty($code)) {
                if ($lookup = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Outbound:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i), true, '/.*?,\s(.*)/ims')) {
                    $lookup = str_replace(' International', '', $lookup);
                    $this->http->Log("Lookup: {$lookup}");
                    $code = $this->findAirCode($lookup);
                }// if ($lookup = ...
            }// if (empty($code))
            $segment['DepCode'] = $code;
            // DepDate
            $fromDate = $this->http->FindSingleNode("descendant-or-self::p[@class = 'boxw tc']", $segments->item($i));
            $fromTime = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Outbound:')]/following-sibling::dd[2]/descendant::strong", $segments->item($i));
            $tt = strtotime($fromDate . ' ' . date("Y", time()) . ' ' . $fromTime);
            $nextYear = 0;

            if ($tt < time()) {
                $tt = strtotime($fromDate . ' ' . (date("Y", time()) + 1) . ' ' . $fromTime);
                $nextYear = 1;
            }
            $segment['DepDate'] = $tt;
            // ArrName
            $segment['ArrName'] = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Arrival:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i));
            // ArrCode
            $arrName = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Arrival:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i), true, "/([^\,]+)/");
            $code = $this->http->FindPreg("/{$arrName}(?:\s*|&nbsp;)\(([A-Z]{3})\)/");

            if (empty($code)) {
                $code = null;

                if ($lookup = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Arrival:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i), true, '/.*?,\s(.*)/ims')) {
                    $lookup = str_replace(' International', '', $lookup);
                    $this->http->Log("Lookup: {$lookup}");
                    $code = $this->findAirCode($lookup);
                }// if ($lookup = ...
            }// if (empty($code))
            $segment['ArrCode'] = $code;
            // ArrDate
            $toTime = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Arrival:')]/following-sibling::dd[2]/descendant::strong[1]", $segments->item($i));
            $tt2 = strtotime($fromDate . ' ' . (date("Y", time()) + $nextYear) . ' ' . $toTime);

            if ($tt2 < $tt) {
                $tt2 = strtotime($this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Arrival:')]/following-sibling::dd[2]/descendant::strong[2]", $segments->item($i)) . ' ' . (date("Y", time()) + $nextYear) . ' ' . $toTime);
            }
            $segment['ArrDate'] = $tt2;
            // Aircraft
            $segment['Aircraft'] = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Aircraft:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i));
            // Duration
            $segment['Duration'] = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Duration:') or contains(text(), 'journey duration')]/following-sibling::dd[1]/descendant::strong", $segments->item($i));
            // Cabin
            $segment['Cabin'] = $this->http->FindSingleNode("descendant-or-self::dt[contains(text(), 'Cabin:')]/following-sibling::dd[1]/descendant::strong", $segments->item($i));
            // Seats
            $segment['Seats'] = (isset($seats[$i])) ? $seats[$i] : '';

            $result['TripSegments'][] = $segment;
        }// for ($i = 0; $i < $segments->length; $i++)

        return $result;
    }

    protected function findAirCode($lookup)
    {
        $criteria = ['AirName' => addslashes(CleanXMLValue($lookup))];
        $airport = $this->db->getAirportBy($criteria);
        $code = ArrayVal($airport, 'AirCode');
        $this->logger->info('getAirportBy:');
        $this->logger->info(var_export([
            'criteria' => $criteria, 'code' => $code,
        ], true), ['pre' => true]);

        if (empty($code)) {
            $airport = $this->db->getAirportBy($criteria, true);
            $code = ArrayVal($airport, 'AirCode');
            $this->logger->info('getAirportBy:');
            $this->logger->info(var_export([
                'criteria' => $criteria, 'partial' => true, 'code' => $code,
            ], true), ['pre' => true]);
        }// if (empty($code))

        return $code;
    }
}
