<?php

class TAccountCheckerMexicana extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.frecuenta.com/");

        if (!$this->http->ParseForm("formLogin")) {
            if ($this->http->FindPreg("/Conscientes de que Mexicana dispone de las fortalezas que requiere para salir adelante/ims")) {
                $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
                $this->ErrorMessage = 'Mexicana suspended all operations at noon CDT on August 28, 2010';

                return false;
            }

            return false;
        }
        $this->http->FormURL = 'http://www.frecuenta.com/fcta_login/';
        $this->http->Form["fcta_numero"] = $this->AccountFields['Login'];
        $this->http->Form["password"] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        if (preg_match("/<h5 class=\"error\">No pudo iniciar/ims", $this->http->Response['body'], $matches)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = "Invalid user name or bad password";

            return false;
        }
        $this->http->GetURL("http://www.frecuenta.com/en/tucuenta_fcta/");

        return true;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindPreg("/<p class=\"puntos\"><span>Miles <strong>([^<]+)/ims"));
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[@class = 'usuario']"));
        $this->SetProperty("EnrollmentDate", $this->http->FindPreg("/<td>Enrollment Date<\/td>\s*<td class=\"par\">([^<]+)/ims"));
        //		$this->SetProperty("MemberSince", $this->http->FindPreg("/<span>Member since<\/span>([^<]+)/ims"));
        $this->SetProperty("AccountNumber", $this->AccountFields['Login']);
    }
}
