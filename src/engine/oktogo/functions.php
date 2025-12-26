<?php

/**
 * Class TAccountCheckerOktogo
 * Display name: oktogo.ru
 * Database ID: 1054
 * Author: AKolomiytsev
 * Created: 13.10.2014 10:45.
 */
class TAccountCheckerOktogo extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://oktogo.ru/my");
        $token = $this->http->getCookieByName("ASP.NET_SessionId", ".oktogo.ru");

        if (!$token) {
            return false;
        }
        $data = [
            "email"           => $this->AccountFields['Login'],
            "password"        => $this->AccountFields['Pass'],
            "validationState" => true,
            "error"           => "",
            "visible"         => true,
            "title"           => "Вход на сайт oktogo.ru",
            "token"           => $token,
            "scheme"          => "http",
        ];
        $headers = [
            "Content-Type" => "application/json;charset=utf-8",
            "Accept"       => "application/json, text/plain, */*",
        ];
        $this->http->PostURL("https://account.oktogo.ru/clientaccount/login", json_encode($data), $headers);
//        $this->http->SetInputValue("Email", $this->AccountFields['Login']);
//        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->successRedirrectUrl)) {
            $this->http->GetURL($response->successRedirrectUrl);

            return true;
        }// if (isset($response->successRedirrectUrl))
        // Неправильный логин или пароль. Проверьте правильность введенных данных.
        if (isset($response->errors[0])) {
            throw new CheckException($response->errors[0], ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $json = $this->http->JsonLog($this->http->FindPreg("/userData.set\(([^\)]+)\);/"));
        // Name
        if (isset($json->Client->Firstname, $json->Client->Lastname)) {
            $name = $json->Client->Firstname . ' ' . $json->Client->Lastname;
            $this->SetProperty("Name", beautifulName(trim($name)));
        } else {
            $this->logger->debug("Name not found.");
        }
        // Account balance
        if (isset($json->bonusPoints)) {
            $this->SetBalance($json->bonusPoints);
        } else {
            $this->logger->debug("Account balance not found.");
        }

        // Account status
        $this->http->GetURL("https://oktogo.ru/my");
        $status = $this->http->FindPreg("/\"clientStatus\":([0-3]),/");

        if ($status > 0) {
            $this->sendNotification('User with new status detected - oktogo.ru', 'oktogo');
        }

        $statuses = [
            'Silver',
            'Gold',
            'Platinum',
            'VIP',
        ];

        if ($status !== null) {
            $this->SetProperty("Status", $statuses[$status]);
        }
    }
}
