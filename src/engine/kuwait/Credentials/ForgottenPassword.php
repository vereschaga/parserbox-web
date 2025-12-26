<?php

namespace AwardWallet\Engine\kuwait\Credentials;

class ForgottenPassword extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'oasisclub@kuwaitairways.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Forgotten Password',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Password',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Password'] = re('#Your\s+password\s+is\s+(\S+)\.\s+Best#i', $this->text());
        $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*?),\s+#i', $this->text());

        return $result;
    }

    public function getRetrieveFields()
    {
        return [
            "Login",
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL("https://oasisclub.kuwaitairways.com/home_pwd_forgotten.aspx?Active_Card_Number=&From=Master");
        $res = $this->http->ParseForm("aspnetForm");

        if (!$res) {
            $this->http->Log('Failed to parse form', LOG_LEVEL_ERROR);

            return false;
        }

        /* CAPTCHA */
        $imageLocation = $this->http->FindSingleNode("//img[contains(@src, 'SkywardsImage.aspx')]/@src");
        $this->http->NormalizeURL($imageLocation);

        if (!$file = $this->http->DownloadFile($imageLocation, "jpg")) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $captcha = trim($recognizer->recognizeFile($file));
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // retries
            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
            }

            return false;
        }
        /* \CAPTCHA */

        $this->http->SetInputValue('ctl00$homecontent$btnSubmit.x', rand(44, 54));
        $this->http->SetInputValue('ctl00$homecontent$btnSubmit.y', rand(4, 16));
        $this->http->SetInputValue('ctl00$homecontent$txtSkyNumber', $data['Login']);
        $this->http->SetInputValue('ctl00$homecontent$CaptchaControl1', $captcha);

        $res = $this->http->PostForm();

        if (!$res) {
            $this->http->Log('Failed to post form', LOG_LEVEL_ERROR);

            return false;
        }

        if ($this->http->FindPreg("#Your\s+password\s+has\s+been\s+sent\s+to\s+the\s+email\s+address#")) {
            return true;
        } else {
            return false;
        }
    }
}
