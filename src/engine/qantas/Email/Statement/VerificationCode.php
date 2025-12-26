<?php

namespace AwardWallet\Engine\qantas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "qantas/statements/it-100974908.eml, qantas/statements/it-100853620.eml";

    private $detectSubjects = [
        // en
        'is your Qantas verification code', 'is your verification code for Qantas',
    ];

    private $providerCode = '';
    private $lang = '';

    private static $dictionary = [
        'en' => [
            "verificationCode" => [
                "is your Qantas verification code",
                "this code in the verification",
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["verificationCode"])) {
                $code = $this->http->FindSingleNode("//text()[{$this->contains($dict["verificationCode"])}]", null, true, "/^(\d{6})\s*{$this->opt($dict["verificationCode"])}/")
                    ?? $this->http->FindSingleNode("//text()[{$this->contains($dict["verificationCode"])}]/following::text()[normalize-space()][1]", null, true, "/^\d{6}$/") // it-100853620.eml
                ;

                if (!empty($code)) {
                    $this->lang = $lang;
                    $st = $email->add()->statement();

                    // it-100853620.eml
                    $name = $this->http->FindSingleNode("//text()[{$this->starts("Hi ")}]", null, true, "/^{$this->opt("Hi ")}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

                    if ($name) {
                        $st->addProperty('Name', $name);
                    } else {
                        // it-100974908.eml
                        $st->setMembership(true);
                    }

                    $st->setNoBalance(true);

                    $email->add()->oneTimeCode()->setCode($code);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'frequent_flyer@qantas.com.au') !== false
            || stripos($from, 'reply@qantasloyalty.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
            foreach ($this->detectSubjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        foreach (self::$dictionary as $dict) {
            if (isset($dict["verificationCode"])
                && $this->http->XPath->query("//text()[{$this->contains($dict["verificationCode"])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailProviders()
    {
        return ['aquire', 'qantas'];
    }

    private function assignProvider($headers): bool
    {
        if (strpos($headers['from'], 'Qantas Business Rewards') !== false
            || stripos($headers['from'], 'qantasbusinessrewards_noreply@qantasloyalty.com') !== false
            || strpos($headers['subject'], 'Qantas Business Rewards') !== false
            || $this->http->XPath->query('//a[contains(@href,".qantasbusinessrewards.com/") or contains(@href,"www.qantasbusinessrewards.com")]')->length > 0
        ) {
            $this->providerCode = 'aquire';

            return true;
        }

        if (strpos($headers['from'], 'Qantas Frequent Flyer') !== false
            || stripos($headers['from'], 'frequent_flyer@qantas.com.au') !== false
            || $this->http->XPath->query('//a[contains(@href,".qantas.com/") or contains(@href,"www.qantas.com")]')->length > 0
        ) {
            $this->providerCode = 'qantas';

            return true;
        }

        return false;
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
