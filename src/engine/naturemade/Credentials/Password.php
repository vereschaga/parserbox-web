<?php

namespace AwardWallet\Engine\naturemade\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'NatureMadeinfo@naturemade.com',
            'naturemadeinfo@naturemade.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Here is the information you requested",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Password'] = re('#Wellness\s+Rewards\s+account\s+is:\s+(\S+)#i', $text);
        $result['Name'] = beautifulName(re('#Hello\s+(\w+\s+\w+)#i', $text));

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.naturemade.com/forgot-password');

        $http = clone $this->http;

        /* CAPTCHA */
        $imageLocation = "/captcha/image";
        $http->NormalizeURL($imageLocation);

        if (!$file = $http->DownloadFile($imageLocation, "jpg")) {
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
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);
                $this->retry(5);
            }

            return false;
        }
        /* \CAPTCHA */

        if (!$this->http->ParseForm("form1")) {
            return false;
        }

        $this->http->SetInputValue('pagecolumns_0$contentmaincolumn_0$ctl00$textEmail', $data["Email"]);
        $this->http->SetInputValue('pagecolumns_0$contentmaincolumn_0$ctl00$captcha$captchaText', $captcha);
        $this->http->SetInputValue('__EVENTTARGET', 'pagecolumns_0$contentmaincolumn_0$ctl00$button');

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Thank you. We will contact you shortly.')]")) {
            return true;
        } else {
            return false;
        }
    }
}
