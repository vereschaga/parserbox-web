<?php

class TAccountCheckerPluna extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://flyclub.flypluna.com/index.php?fuseaction=Publico.Login");

        if (!$this->http->ParseForm('form1')) {
            return false;
        }

        $this->http->SetInputValue('inputIdentificador', $this->AccountFields['Login']);
        $this->http->SetInputValue('inputContrasenia', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'Sesion.Salir')]")) {
            return true;
        }

        if (strpos($this->http->Response['url'], 'error') !== false && $message = $this->http->FindSingleNode("//h3[contains(text(), 'Hola')]/following::p[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->ParseForm('consulta');
        $this->http->SetInputValue('inputFechaDesde', date('d/m/Y', strtotime('-5 years')));
        $this->http->PostForm();

        // Bienvenido
        $this->SetProperty("Name", $this->http->FindSingleNode("//em[contains(text(), 'Bienvenid')]", null, true, '/^Bienvenid[a-z],\s+(.+)/ims'));

        // Total cobrado
        $this->SetProperty("TotalCharged", $this->http->FindSingleNode("//strong[contains(text(), 'Total cobrado')]/following::strong[1]"));
        // Total movimientos Partner
        $this->SetProperty("TotalPartnerChanges", $toExpiryPartners = $this->http->FindSingleNode("//strong[contains(text(), 'Total movimientos Partner')]/following::strong[1]"));
        // Total otros movimientos
        $this->SetProperty("TotalOtherChanges", $toExpiry = $this->http->FindSingleNode("//strong[contains(text(), 'Total otros movimientos')]/following::strong[1]"));

        // Total acumulado actual
        if ($balance = $this->http->FindSingleNode("//strong[contains(text(), 'Total acumulado actual')]/following::strong[1]")) {
            $this->SetBalance($balance);
        }
        //# if activity not found
        elseif ($this->http->FindPreg("/No existen movimientos para el rango de fecha proporcionado/ims")) {
            $this->SetBalanceNA();
        }

        // Vencimientos de Flydollars
        if (($toExpiry > 0 || $toExpiryPartners > 0)
                && $expirydate = $this->http->FindSingleNode("//b[contains(text(), 'Flydollars generados por vuelo')]", null, true, '#(\d+/\d+/\d+)$#ims')) {
            [$d, $m, $y] = explode('/', $expirydate);
            $expiry = mktime(0, 0, 0, $m, $d, $y);

            if ($expirydate = $this->http->FindSingleNode("//b[contains(text(), 'Flydollars generados por partners')]", null, true, '#(\d+/\d+/\d+)$#ims')) {
                [$d, $m, $y] = explode('/', $expirydate);
                $expiryPartners = mktime(0, 0, 0, $m, $d, $y);

                if ($expiryPartners < $expiry) {
                    $expiry = $expiryPartners;
                    $toExpiry = $toExpiryPartners;
                }
            }
            $this->SetExpirationDate($expiry);
            $this->SetProperty("PointsToExpire", $toExpiry);
        }

        $this->http->GetURL("https://flyclub.flypluna.com/index.php?fuseaction=Cliente.ActualizarDatos");
        //# Identificador
        $this->SetProperty("Identificador", $this->http->FindSingleNode("//td[label/strong[contains(text(), 'Identificador')]]/following-sibling::td[1]"));
    }
}
