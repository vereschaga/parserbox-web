<?php

namespace AwardWallet\Engine\nordic\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "nordic/it-850032118.eml, nordic/statements/it-604223099.eml, nordic/statements/it-604247537.eml, nordic/statements/it-610479287.eml";
    private $detectSubjects = [
        // en, da
        ', here is your verification code for login',
        'Your verification code for login',
        // no
        ', her er din verifiseringskode for å logge inn',
        // sv
        ', här är din verifieringskod för inloggning',
    ];

    private $detectSubjectsPassword = [
        // en, da
        'here is your verification code to create a new password',
        // no
        'her er din verifiseringskode for å opprette et nytt password',
        // sv
        'här är din verifieringskod för att skapa nytt lösenord',
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [
            "Here is your verification code:" => ["Here is your verification code:", "here is your verification code:"],
            "providerDetect"                  => ["Nordic Choice Commercial Services AB"],
            //"delete" => "",
        ],
        'no' => [
            "Here is your verification code:" => "Her er din verifiseringskode:",
            "providerDetect"                  => ["Nordic Choice Commercial Services AB"],
            //"delete" => "",
        ],
        'sv' => [
            "Here is your verification code:" => "Här är din verifieringskod:",
            "providerDetect"                  => ["Nordic Choice Commercial Services AB"],
            //"delete" => "",
        ],
        'da' => [
            "Here is your verification code:" => "Her er din verifikationskode:",
            "providerDetect"                  => ["Nordic Choice Commercial Services AB"],
            //"delete" => "",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Here is your verification code:"]) && $this->http->XPath->query("//text()[{$this->contains($dict["Here is your verification code:"])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach ($this->detectSubjectsPassword as $subject) {
            if (stripos($parser->getSubject(), $subject) !== false) {
                $email->setIsJunk(true);

                return $email;
            }
        }
        $detectedSubject = false;

        foreach ($this->detectSubjects as $subject) {
            if (stripos($parser->getSubject(), $subject) !== false) {
                $detectedSubject = true;

                break;
            }
        }

        if ($detectedSubject === false) {
            return $email;
        }

        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here is your verification code:'))}]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{6})\s*$/u");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Here is your verification code:'))}]/following::text()[normalize-space()][not(contains(normalize-space(), 'delete'))][1]", null, true,
                "/^\s*(\d{6})\s*$/u");
        }

        if (!empty($code)) {
            $email->add()->oneTimeCode()->setCode($code);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]strawberry\.[a-z]{2,3}\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from'])) {
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
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict["providerDetect"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["providerDetect"]) . "]")->length > 0
                && !empty($dict["Here is your verification code:"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["Here is your verification code:"]) . "]")->length > 0
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
