<?php

class TAccountCheckerValoriza extends TAccountChecker
{
    use SeleniumCheckerHelper;
    private $loginId;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->loginId = str_replace(['-', '.'], '', $this->AccountFields['Login']);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->GetURL("https://login.vivo.com.br/loginmarca/appmanager/marca/publico#");

        if ($this->http->Response['code'] == 404) {
            sleep(5);
            $this->http->removeCookies();
            $this->http->GetURL("https://login.vivo.com.br/loginmarca/appmanager/marca/publico#");
        }

        if (!$this->http->ParseForm("loginConvergenteForm")) {
//            if ($this->http->Response['code'] == 404) {
//                $this->DebugInfo = 404;
//                throw new CheckRetryNeededException(3, 15, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//            }

            return false;
        }
        $this->http->FormURL = 'https://login.vivo.com.br/loginmarca/br/com/vivo/marca/portlets/loginunificado/doLoginConvergente.do';
        $this->http->SetInputValue("origem", "null");
        $this->http->SetInputValue("cpf", $this->loginId);
        $this->http->SetInputValue("senha", $this->AccountFields['Pass']);
        $this->http->SetInputValue("associaMobileConnect", "false");

        /*$this->http->PostURL('https://login.vivo.com.br/loginmarca/br/com/vivo/marca/portlets/loginunificado/doLoginConvergente.do', [
            'associaMobileConnect' => 'false',
            'cpf' => $this->AccountFields['Login'],
            'origem' => 'null',
            'senha' => $this->AccountFields['Pass'],
        ], [
            'Accept' => 'application/json, text/javascript, **; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest'
        ]);*/

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg["CookieURL"] = "http://www.vivo.com.br/portalweb/appmanager/env/web#";
        $arg["SuccessURL"] = "https://meuvivo.vivo.com.br/meuvivo/appmanager/portal/vivo?_nfpb=true&_nfls=false&_pageLabel=vcMVMHomePosPage&pFlutua=true#";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        $result = $this->http->JsonLog();

        if (!$result) {
            $result = $this->http->JsonLog($this->http->FindPreg("/(\{[^\}]+\})/"));
        }

        if (!isset($result->message)) {
            return false;
        }

        if ($result->message == 'REDIRECT') {
            if (isset($result->requestURL)
                && ($result->requestURL == '/loginmarca/appmanager/marca/publico?_nfpb=true&_nfls=false&_pageLabel=pages_publico_primeiroAcesso_page')
                    || ($result->requestURL == '/loginmarca/appmanager/marca/publico?_nfpb=true&_nfls=false&_pageLabel=pages_publico_acesso_page&troca_senha=true')) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }// if ($result->message == 'REDIRECT')

        if (strtolower($result->message) !== 'success') {
            throw new CheckException($result->message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->PostURL("https://login.vivo.com.br/saml2/idp/sso/initiator", ['RequestURL '=> $result->requestURL, 'SPName' => $result->spName]);

        if (!$this->http->ParseForm("ssoForm")) {
            return false;
        }
        // provider error
        if ($this->http->FindSingleNode("//title[contains(text(), 'Error 403--Forbidden')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->PostForm()) {
            // AccountID: 380429
            if ($this->http->Response['code'] == 404 && $this->AccountFields['Login'] == '94124477287') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        // Um problema ocorreu e não foi possível inicializar seu acesso. Por favor tente novamente.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Um problema ocorreu e não foi possível inicializar seu acesso. Por favor tente novamente.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // O Meu Vivo está em manutenção
        // = My Vivo is under maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'O Meu Vivo está em manutenção')]")) {
            throw new CheckException('O Meu Vivo está em manutenção. Por favor, tente novamente mais tarde.', ACCOUNT_PROVIDER_ERROR);
        }
        // Unable to log in. Please try again later.
        if ($this->http->FindPreg("/mensagemAlert = \'N\&atilde;o foi poss\&iacute;vel realizar o login\. Por favor tente novamente mais tarde\.\'/")) {
            throw new CheckException("Não foi possível realizar o login. Por favor tente novamente mais tarde.", ACCOUNT_PROVIDER_ERROR);
        }
        // Definir Produto Principal
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Definir Produto Principal')]")
            // Confirmação de Titularidade
            || $this->http->FindSingleNode("//h2[contains(text(), 'Confirmação de Titularidade')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Sair') or @title = 'Sair']/@href)[1]")) {
            return true;
        }
        // new term and conditions
        if ($this->http->FindPreg("/Estou de acordo com os&nbsp;<a[^>]+><strong>termos de uso<\/strong><\/a>&nbsp;do Conta Online para ativá-lo em minha linha/")
            || $this->http->FindPreg("/msgAlert\(\"Para acessar o Meu Vivo você precisa vincular o\(s\) produto\(s\) Vivo que você deseja acessar\.\"/ims")
            // Complete seu cadastro
            || ($this->http->FindPreg("/Preencha os campos abaixo para se cadastrar no Meu Vivo\./ims")
                && $this->http->FindPreg("/\(\'\.meus_dados\'\)\.text\(\'Complete seu cadastro\'\)\;/ims")
                && stristr($this->http->currentUrl(), '_pageLabel=pages_publico_primeiroAcesso_page&origemCadastro=loginMeuVivo&'))) {
            $this->throwProfileUpdateMessageException();
        }

        $this->http->GetURL("https://meuvivo.vivo.com.br/meuvivo/appmanager/portal/vivo?_nfpb=true&_pageLabel=vcMovCtrlExtratoPontoPage&_nfls=false");

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Sair') or @title = 'Sair']/@href)[1]")) {
            return true;
        }

        if (
            in_array($this->AccountFields['Login'], [
                '03663579999',
                '26108738839',
                '00989097463',
                '74860135334',
                '70002576104',
                '90834046768',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Vivo Valoriza (== Total)
        $this->SetBalance($this->http->FindSingleNode("//div[@id = 'pontos_vv_home']/strong/span"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'nomeUsuario']/span")));
        // Plano
        $this->SetProperty("ServiceDescription", $this->http->FindPreg("/var\s*nomePlano\s*=\s*'([^\']+)/"));
        // Telefone
        if (!stristr($this->http->currentUrl(), 'pageLabel=vcMeuVivoIPTVBook')) {
            $this->SetProperty("PhoneNumber", $this->http->FindSingleNode("//span[@id = 'produtoSelecionado']"));

            if (empty($this->Properties['PhoneNumber'])) {
                $this->SetProperty("PhoneNumber", $this->http->FindSingleNode("//a[@id = 'produtoSelecionado' and @class=\"selected-text\"]"));
            }
        }// if (!stristr($this->http->currentUrl(), 'pageLabel=vcMeuVivoIPTVBook'))
        // Sua categoria
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(text(), 'Sua categoria:')]/following-sibling::strong"));

        // deprecated
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($this->http->FindSingleNode("//div[@id = 'session_vivo_valoriza_vv']//a[contains(@title, 'Clique aqui e cadastre-se grátis')]")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            } else {
                $valorizaPage = $this->http->FindSingleNode("//a[contains(text(), 'Vivo Valoriza')]/@href");

                if (!$valorizaPage) {
                    $valorizaPage = $this->http->FindPreg("/href=\"([^\"]+)\" title=\"\">\s*&nbsp;Vivo Valoriza\s*<\/a>/");
                }
                // AccountID: 3088433, 3800956
                if (!$valorizaPage && ($urlAplicacao = $this->http->FindPreg("/var urlAplicacao = '([^\']+)/"))
                    && (strstr($this->http->currentUrl(), 'pageLabel=vcMVFixoVivo2Book&pFlutua=true'))
                    // AccountID: 3739524, 3309922
                    || strstr($this->http->currentUrl(), 'pageLabel=vcMeuVivoTVVivo2Book&pFlutua=true')
                    || strstr($this->http->currentUrl(), 'pageLabel=vcMVInternetVivo2Book&pFlutua=true')) {
                    $valorizaPage = '/portal/site/meuvivo/vivovaloriza?page=main';
                }

                if (!$valorizaPage
                    && (strstr($this->http->currentUrl(), 'pageLabel=vcMeuVivoMovAmDocs&pFlutua=true'))
                    || strstr($this->http->currentUrl(), '&pagename=MeuVivoFixo%2FPage%2FTemplateGlobal&rendermode=preview&token=')) {
                    $valorizaPage = 'https://hub.programadepontosvivo.com.br/oam/experienciadigital';
                }

                if ($valorizaPage) {
                    $this->logger->notice('New design');
                    $this->http->NormalizeURL($valorizaPage);
                    $this->http->GetURL($valorizaPage);
                    // TODO: Estamos atualizando o Vivo Valoriza, por isso o sistema ficará indisponível de 01/04/19 a 05/04/19.
                    if ($src = $this->http->FindSingleNode("//iframe[@id='idFrameVivoValoriza' and @src='https://hub.programadepontosvivo.com.br/oam/experienciadigital']/@src")) {
                        $this->http->GetURL($src);

                        if ($message = $this->http->FindSingleNode("
                                //p[contains(text(),'Estamos atualizando o Vivo Valoriza, por isso o sistema ficar')]
                            ")
                        ) {
                            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                        }
                    }

                    if ($message = $this->http->FindSingleNode("
                            //p[contains(text(),'Estamos atualizando o Vivo Valoriza e, para isso, o programa ficar')]
                        ")
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    // not a member
                    if ($this->http->FindSingleNode("//div[@id = 'onboardCarousel']//p[contains(text(), 'Só de participar do Vivo Valoriza você tem descontos em restaurantes, cinemas, teatros, ingressos de show e muitos mais.')]")) {
                        $this->SetWarning(self::NOT_MEMBER_MSG);
                    }

                    $this->http->RetryCount = 0;
                    $this->http->unsetDefaultHeader("X-Requested-With");

                    $this->http->setCookie("5685fceffbc17265d", base64_encode($this->loginId), "hub.programadepontosvivo.com.br");
                    $this->http->setCookie("9y8o79q5s4f5ef4b6", "W3sia2V5IjoidXNlcl9hZ2VudCIsInZhbHVlIjoiTW96aWxsYS81LjAgKE1hY2ludG9zaDsgSW50ZWwgTWFjIE9TIFggMTAuMTM7IHJ2OjU1LjApIEdlY2tvLzIwMTAwMTAxIEZpcmVmb3gvNTUuMCJ9LHsia2V5IjoibGFuZ3VhZ2UiLCJ2YWx1ZSI6ImVuLVVTIn0seyJrZXkiOiJjb2xvcl9kZXB0aCIsInZhbHVlIjoyNH0seyJrZXkiOiJkZXZpY2VfbWVtb3J5IiwidmFsdWUiOi0xfSx7ImtleSI6ImhhcmR3YXJlX2NvbmN1cnJlbmN5IiwidmFsdWUiOjh9LHsia2V5IjoicmVzb2x1dGlvbiIsInZhbHVlIjpbMTQ0MCw5MDBdfSx7ImtleSI6ImF2YWlsYWJsZV9yZXNvbHV0aW9uIiwidmFsdWUiOlsxNDQwLDgzMF19LHsia2V5IjoidGltZXpvbmVfb2Zmc2V0IiwidmFsdWUiOi0zMDB9LHsia2V5Ijoic2Vzc2lvbl9zdG9yYWdlIiwidmFsdWUiOjF9LHsia2V5IjoibG9jYWxfc3RvcmFnZSIsInZhbHVlIjoxfSx7ImtleSI6ImluZGV4ZWRfZGIiLCJ2YWx1ZSI6MX0seyJrZXkiOiJjcHVfY2xhc3MiLCJ2YWx1ZSI6InVua25vd24ifSx7ImtleSI6Im5hdmlnYXRvcl9wbGF0Zm9ybSIsInZhbHVlIjoiTWFjSW50ZWwifSx7ImtleSI6InJlZ3VsYXJfcGx1Z2lucyIsInZhbHVlIjpbIlNob2Nrd2F2ZSBGbGFzaDo6U2hvY2t3YXZlIEZsYXNoIDMwLjAgcjA6OmFwcGxpY2F0aW9uL3gtc2hvY2t3YXZlLWZsYXNofnN3ZixhcHBsaWNhdGlvbi9mdXR1cmVzcGxhc2h+c3BsIl19LG51bGwsbnVsbCx7ImtleSI6IndlYmdsX3ZlbmRvciIsInZhbHVlIjoiTlZJRElBIENvcnBvcmF0aW9ufk5WSURJQSBHZUZvcmNlIEdUIDc1ME0gT3BlbkdMIEVuZ2luZSJ9LHsia2V5IjoiYWRibG9jayIsInZhbHVlIjpmYWxzZX0seyJrZXkiOiJoYXNfbGllZF9sYW5ndWFnZXMiLCJ2YWx1ZSI6ZmFsc2V9LHsia2V5IjoiaGFzX2xpZWRfcmVzb2x1dGlvbiIsInZhbHVlIjpmYWxzZX0seyJrZXkiOiJoYXNfbGllZF9vcyIsInZhbHVlIjpmYWxzZX0seyJrZXkiOiJoYXNfbGllZF9icm93c2VyIiwidmFsdWUiOmZhbHNlfSx7ImtleSI6InRvdWNoX3N1cHBvcnQiLCJ2YWx1ZSI6WzAsZmFsc2UsZmFsc2VdfSxudWxsXQ==", "hub.programadepontosvivo.com.br");
                    $this->http->setCookie("13e5rge4ru8kf46e4", "90feb8af7291b2f09832263c613e5a20", "hub.programadepontosvivo.com.br");
                    $this->http->PostURL('https://hub.programadepontosvivo.com.br/oam/experienciadigital/transition', [
                        "ha3762v4ajhg3fas4" => "aHR0cHM6Ly9odWIucHJvZ3JhbWFkZXBvbnRvc3Zpdm8uY29tLmJyL29hbS9leHBlcmllbmNpYWRpZ2l0YWw=",
                    ]);
                    $this->http->GetURL("https://app.programadepontosvivo.com.br/#/authenticate");
                    $global = $this->http->FindPreg("#(public/app/js/global\.js\?v=\d+)#");

                    if (!$global) {
                        $this->http->GetURL("https://meuvivo.vivo.com.br/meuvivo/appmanager/portal/vivo?_nfpb=true&_pageLabel=vcMVMPosHomeVivoValoriza&_nfls=false");

                        if (
                            !empty($this->Properties['Name'])
                            && !empty($this->Properties['PhoneNumber'])
                            && (
                                $this->http->FindSingleNode('//p[contains(text(), "O seu programa de relacionamento da Vivo mudou. Agora você acessa pelo app Meu Vivo Móvel e pode ganhar mais prêmios e benefícios.")]')
                                || $this->http->currentUrl() == 'https://meuvivo.vivo.com.br:443/meuvivo/appmanager/portal/vivo?_nfpb=true&_nfls=false&_pageLabel=vcMVMovelPos_book&pFlutua=true'
                                || $this->http->currentUrl() == 'https://login.vivo.com.br/loginmarca/appmanager/marca/publico?erroLogin=erro.geral'
                                || $this->http->ParseForm(null, 1, true, '//form[@action = "https://login.vivo.com.br/saml2/idp/sso/post"]')
                            )
                        ) {
                            $this->SetBalanceNA();
                        }

                        return;
                    }
                    $this->http->NormalizeURL($global);
                    $this->http->GetURL($global);
                    $ocpApimSubscriptionKey = $this->http->FindPreg("/https:\/\/adminapi\.programadepontosvivo\.com\.br\/v1\/api',\s*ocpApimSubscriptionKey:\s*'([^']+)/");

                    if (!$ocpApimSubscriptionKey) {
                        return;
                    }
                    $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
                    $this->http->setDefaultHeader("Ocp-Apim-Subscription-Key", $ocpApimSubscriptionKey);
                    $this->http->setDefaultHeader("Origin", "https://app.programadepontosvivo.com.br");
                    $this->http->setDefaultHeader("Referer", "https://app.programadepontosvivo.com.br/");

                    $this->http->GetURL("https://apiman.programadepontosvivo.com.br/adminapi/v1/api/reward/redirect/", ["Referer" => "https://app.programadepontosvivo.com.br/", "Accept-Encoding" => "gzip, deflate, br", "User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:55.0) Gecko/20100101 Firefox/55.0"]);
                    $response = $this->http->JsonLog();
                    // PhoneNumber
                    if (empty($this->Properties["PhoneNumber"]) && isset($response->LineNumber)) {
                        $this->SetProperty("PhoneNumber", $response->LineNumber);
                    }
                    // Status - Sua categoria é: Platinum
                    if (isset($response->Segment)) {
                        $this->SetProperty("Status", $response->Segment);
                    }
                    // Name
                    if (isset($response->Name)) {
                        $this->SetProperty("Name", beautifulName($response->Name));
                    }

                    $this->http->GetURL("https://apiman.programadepontosvivo.com.br/adminapi/v1/api/participants/{$this->loginId}/balance/app");
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog();
                    // Balance - 5.530 pts
                    if (isset($response->Balance)) {
                        $this->SetBalance($response->Balance);
                    } elseif (
                        in_array($this->loginId, [
                            '00369754190',
                            '04734345635',
                            '35949621808',
                            '93637853191',
                            '26782312832',
                            '98061194287',
                            '40659551810',
                            '26994295893',
                            '40284346888',
                            '34467589848',
                            '34331151840',
                            '25514104877',
                            '28442065814',
                            '00357236378',
                        ])
                        && isset($response->Message) && $response->Message == 'Authorization has been denied for this request.'
                    ) {
                        $this->SetWarning(self::NOT_MEMBER_MSG);
                    }

                    return;
                }// if ($valorizaPage)
            }

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                // AccountID: 2580548, 3792254, 3941184
                if (!empty($this->Properties['Name']) && !empty($this->Properties['ServiceDescription']) && !empty($this->Properties['PhoneNumber'])
                    && $this->http->currentUrl() == 'https://meuvivo.vivo.com.br:443/meuvivo/appmanager/portal/vivo?_nfpb=true&_nfls=false&_pageLabel=vcMeuVivoMovPreLogBook&pFlutua=true') {
                    $this->SetBalanceNA();

                    return;
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
