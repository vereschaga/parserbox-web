<?php

require_once __DIR__ . '/../hongkongairlines/functions.php';

class TAccountCheckerHainan extends TAccountCheckerHongkongairlines
{
    /**
     * @var $recognizer CaptchaRecognizer
//            'Accept' => 'application/json, text/javascript, *
    /*; q=0.01',
            throw new CheckRetryNeededException(2, 10);

        /*$this->http->removeCookies();
        // Balance - Points Management
        $this->SetBalance($this->http->FindSingleNode("//td[strong[contains(text(), 'Points balance:')]]/following-sibling::td"));* /

        // Member No.
        $this->SetProperty("MemberNo", $http->FindSingleNode("//li[contains(text(), 'Member No.：')]", null, true, "/：\s*([^<]+)/"));
        // Balance - Points Management
        $this->SetBalance($http->FindSingleNode("//li[contains(text(), 'Points Management：')]", null, true, "/：\s*([^<]+)/"));
        // Exp date - Points validity
        $exp = $this->http->FindSingleNode("//td[strong[contains(text(), 'Points validity')]]/following-sibling::td[1]");
        $this->logger->debug("Exp: ".$exp." length: ".strlen($exp));
        if (strlen($exp) == 8) {
            $part = str_split($exp, 2);
            if (isset($part[3])) {
                $exp = $part[3].'.'.$part[2].'.'.$part[0].$part[1];
                $this->logger->debug("Exp: ".$exp);
                if (strtotime($exp))
                    $this->SetExpirationDate(strtotime($exp));
            }// if (isset($part[3]))
        }// if (strlen($exp) == 8)
        elseif (strstr($exp, '/')) {
            $this->logger->debug("Exp date: {$exp}/ ".strtotime($exp)." ");
            if (strtotime($exp))
                $this->SetExpirationDate(strtotime($exp));
        }// elseif (strstr($exp, '/'))
    }*/
}
