<?php

namespace AwardWallet\Engine\rewardsnet\Credentials;

class Skymiles extends \TAccountCheckerExtended
{
    public $mailFiles = ['rewardsnet/scanner/Credentials2.eml'];

    public function getCredentialsImapFrom()
    {
        return [
            "skymiles@rewardsnetwork.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Requested Login ID",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#\sDear\s+(.+?)\s*,#i', $text)) {
            $result['Name'] = $s;
        }

        if ($s = re('#\slogin\s+ID\s*is\s*:?\s*(\S+)#i', $text)) {
            $result['Login'] = $s;
        }

        return $result;
    }

    // Retrive
    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://skymiles.rewardsnetwork.com/remindLoginId.htm');

        if (preg_match("/^(.+?\/\/[^\/]+)\//", $this->http->currentUrl(), $m)) {
            $host = $m[1];
        }
        $this->http->SetInputValue("callCount", 1);
        $this->http->SetInputValue("c0-scriptName", "__System");
        $this->http->SetInputValue("c0-methodName", "generateId");
        $this->http->SetInputValue("c0-id", 0);
        $this->http->SetInputValue("batchId", 0);
        $this->http->SetInputValue("instanceId", 0);
        $this->http->SetInputValue("page", "%2FremindLoginId.htm");
        $this->http->SetInputValue("scriptSessionId", "");
        $this->http->SetInputValue("windowName", "");
        $this->http->FormURL = $host . "/dwr/call/plaincall/__System.generateId.dwr";

        if (!$this->http->PostForm()) {
            return false;
        }

        $sessionId = $this->http->FindPreg("/allback\(.+?,\s*['\"]([^,)]+?)['\"]\s*\);/");

        if (!$sessionId) {
            return false;
        }

        $this->http->setCookie("DWRSESSIONID", $sessionId);

        $this->http->SetInputValue("c0-param0", "string:" . urlencode($data['Email']));
        $this->http->SetInputValue("callCount", 1);
        $this->http->SetInputValue("windowName", "");
        $this->http->SetInputValue("nextReverseAjaxIndex", 0);
        $this->http->SetInputValue("c0-scriptName", "UserService");
        $this->http->SetInputValue("c0-methodName", "getLoginIdOfMember");
        $this->http->SetInputValue("c0-id", 0);
        $this->http->SetInputValue("c0-param1", "string:skymiles.rewardsnetwork");
        $this->http->SetInputValue("c0-param2", "string:http%3A%2F%2Fcontent.idine.com%2Fz%2Fdl%2Fd%2Fi%2F");
        $this->http->SetInputValue("c0-param3", "boolean:false");
        $this->http->SetInputValue("batchId", 1);
        $this->http->SetInputValue("instanceId", 0);
        $this->http->SetInputValue("page", "%2FremindLoginId.htm");
        // Generating a key for opening access to reminding of the Login
        $rand01 = sin(rand(0, 1000));
        $rand01 = ($rand01 < 0) ? -$rand01 : $rand01;
        $tokenId = $this->tokenify(time() * 1000 + 3) . "-" . $this->tokenify($rand01 * 1E+16);
        $this->http->SetInputValue("scriptSessionId", $sessionId . "/" . $tokenId);

        $this->http->FormURL = $host . "/dwr/call/plaincall/UserService.getLoginIdOfMember.dwr";

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/status:\s*\"SUCCESS\"/i")) {
            return true;
        } else {
            return false;
        }
    }

    // Copied from skymiles.rewardsnetwork.com (from js-scripts) and adapted
    protected function tokenify($number)
    {
        $tokenbuf = [];
        $charmap = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ*$";
        $remainder = $number;

        while ($remainder > 0) {
            $tokenbuf[] = substr($charmap, ($remainder & 0x3F), 1);
            $remainder = floor($remainder / 64);
        }

        return implode("", $tokenbuf);
    }
}
