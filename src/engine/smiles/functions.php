<?php

class TAccountCheckerSmiles extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $cardPrefix = substr($this->AccountFields['Login'], 0, 6);

        if (!in_array($cardPrefix, ['636172', '627262', '627476', '306005']) || strlen($this->AccountFields['Login']) != 17) {
            throw new CheckException("Les informations saisies sont incorrectes et ne nous permettent pas de vous identifier. Veuillez les saisir à nouveau", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.rubriquefidelite.com/minisite/Framework");

        if (!$this->http->ParseForm("iden_carte_form")) {
            return false;
        }
        $this->http->SetInputValue("num_iden", $this->AccountFields['Login']);
        $password = trim($this->AccountFields['Pass'], "a..zA..Z/.-_");
        $this->http->SetInputValue("password", $password);
        $this->http->SetInputValue("password_renamed", preg_replace('/./i', '*', $password));

        if ($this->AccountFields['Login'] == '62726281080951311') {
            $this->http->SetInputValue("password", "");
            $this->http->SetInputValue("password_renamed", str_replace('/', '', $password));
        }

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[@id = 'disconnect_All']")) {
            return true;
        }
        // To identify yourself, please confirm your details
        if ($this->http->FindSingleNode("//div[contains(text(), 'Pour vous identifier, merci de confirmer vos coordonnées :')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/Le n\&deg; de carte que vous avez renseign\&eacute; est erron\&eacute;/")) {
            throw new CheckException("Le n° de carte que vous avez renseigné est erroné.", ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($this->AccountFields['Login'], ['62726281079943725', '62747630021775939'])) {
            throw new CheckException("Numéro de carte invalide : Veuillez insérer votre numéro de carte", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'nom_prenom']")));
        // Balance - Vous détenez
        $this->SetBalance($this->http->FindPreg("/Vous\s*d\&eacute;tenez\s*<span[^>]+>([^<]+) S/"));
        // Expiration Date
        $expNodes = $this->http->FindPregAll('/"solde">(?<expBalance>([^<]+))<\/span>\s*arrivent\s*&agrave;\s*p\&eacute;remption\s*le\s*(?<expDate>[\/\d]+)/ims', $this->http->Response['body'], PREG_SET_ORDER, true);

        foreach ($expNodes  as $expNode) {
            $this->logger->debug("Date: {$expNode['expDate']} - {$expNode['expBalance']}");
            $expDate = $this->ModifyDateFormat($expNode['expDate']);

            if (!isset($exp) || $exp > strtotime($expDate)) {
                $exp = strtotime($expDate);
                // Expiration Date
                $this->SetExpirationDate($exp);
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $expNode['expBalance']);
            }// if (!isset($exp) || $exp > strtotime($expDate))
        }// foreach ($expNodes  as $expNode)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = 'https://www.rubriquefidelite.com/minisite/Framework';

        return $arg;
    }
}
