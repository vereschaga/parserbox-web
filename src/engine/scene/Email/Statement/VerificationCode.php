<?php

namespace AwardWallet\Engine\scene\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "scene/statements/it-148692764.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@news.scene.ca') !== false || stripos($from, '@news.sceneplus.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'here is your Scene+ verification code') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".scene.ca/") or contains(@href,"news.scene.ca") or contains(@href,".sceneplus.ca/") or contains(@href,"news.sceneplus.ca")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"SCENE IP LP. All rights reserved") or contains(normalize-space(),"Scene IP LP. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('One-time code not found!');

            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $verificationCode = $this->http->FindSingleNode('.', $root, true, '/^\d{3,}$/');

        if ($verificationCode !== null) {
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);
        }

        $name = null;
        $names = array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'please enter this verification code')]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*,[ ]*please enter this verification code/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if ($name) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        } elseif ($verificationCode !== null) {
            $st->setMembership(true);
        }
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[contains(normalize-space(),'please enter this verification code')]/following-sibling::*[normalize-space()][1][starts-with(translate(normalize-space(),'0123456789','dddddddddd'),'ddd')]");
    }
}
