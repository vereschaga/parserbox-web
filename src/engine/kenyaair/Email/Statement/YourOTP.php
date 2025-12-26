<?php

namespace AwardWallet\Engine\kenyaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourOTP extends \TAccountChecker
{
    public $mailFiles = "kenyaair/statements/it-650456248.eml";

    private $subjects = [
        'en' => ['One time Password'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".kenya-airways.com/") or contains(@href,"www.kenya-airways.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Kenya Airways. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]kenya-airways\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('OTP not found!');

            return $email;
        }
        $root = $roots->item(0);

        $customer = null;
        $customerNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Dear')]", null, "/^Dear[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($customerNames)) === 1) {
            $customer = array_shift($customerNames);
        }

        $otpValue = $this->http->FindSingleNode('.', $root, true, "/Your OTP password is[:\s]+(\d{3,})(?:\s*[,.;:!?]|$)/i");

        if ($customer || $otpValue !== null) {
            $st = $email->add()->statement();

            if ($customer) {
                $st->addProperty('Name', preg_replace('/^(?:Mr|Ms|Mrs|Miss|Dr)[.\s]+([[:alpha:]].+)$/u', '$1', $customer));
                $st->setNoBalance(true);
            } elseif ($otpValue !== null) {
                $st->setMembership(true);
            }

            if ($otpValue !== null) {
                $otp = $email->add()->oneTimeCode();
                $otp->setCode($otpValue);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Your OTP password is')]");
    }
}
