<?php

namespace AwardWallet\Engine\wayfair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "wayfair/statements/it-197701718.eml, wayfair/statements/it-72548793.eml";

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Your verification code is') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".wayfair.com/") or contains(@href,"www.wayfair.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"See wayfair.com") or contains(normalize-space(),"Download the Wayfair App") or contains(normalize-space(),"Wayfair Inc., 4 Copley Place, Floor 7, Boston, MA 02116")]')->length === 0
        ) {
            return false;
        }

        return $this->findLogin()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wayfair\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $login = null;
        $loginTexts = [];
        $loginNodes = $this->findLogin();

        foreach ($loginNodes as $root) {
            $loginTexts[] = $this->http->FindSingleNode('.', $root, true, '/^.+?\s+?(\S+?@\S+?)[,.;:?!\s]*$/');
        }
        $loginTexts = array_filter($loginTexts);

        if (count(array_unique($loginTexts)) === 1) {
            $login = array_shift($loginTexts);
        }

        if ($login !== null) {
            $st = $email->add()->statement();
            $st->setLogin($login);
            $st->setNoBalance(true);
        }

        $verificationCode = $this->http->FindSingleNode("//*[normalize-space()='Verification Code']/following-sibling::*[normalize-space()][1]", null, true, "/^\d{3,}$/");

        if ($verificationCode !== null) {
            // it-197701718.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);

            if ($login === null) {
                $st = $email->add()->statement();
                $st->setMembership(true);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findLogin(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[not(.//tr) and starts-with(normalize-space(),'This message was sent to')]");
    }
}
