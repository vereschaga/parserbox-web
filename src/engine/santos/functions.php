<?php

class TAccountCheckerSantos extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.hsantos.es/en/signup/");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'principal')]")) {
            return false;
        }
        $this->http->SetInputValue("login", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.h-santos.es/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/div\s+id\s*=\s*\"fd_cerrar\"/i")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@id='contenidos']/h1")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.h-santos.es/fidelizacion/client/saldo-de-puntos.php');

        // Saldo de puntos -
        $this->SetBalance($this->http->FindSingleNode("//div[@class='fd_cuadropuntos']/p[3]", null, true, '/\d+/i'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id='fd_nompoint']", null, true, "/\/[^\s]+\s+([^\/]+)\/\s+/i")));
        // Puntos obtenidos: 500 - 'Lifetime earned'
        $this->SetProperty("LifetimeEarned", $this->http->FindSingleNode("//div[@class='fd_cuadropuntos']/p[1]", null, true, "/\d+/i"));
        //Puntos utilizados: 0 - 'Points used'
        $this->SetProperty("PointsUsed", $this->http->FindSingleNode("//div[@class='fd_cuadropuntos']/p[2]", null, true, "/\d+/i"));
    }
}
