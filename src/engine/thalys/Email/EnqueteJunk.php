<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Schema\Parser\Email\Email;

class EnqueteJunk extends \TAccountChecker
{
    public $mailFiles = "thalys/it-65881648.eml, thalys/it-65919483.eml, thalys/it-65926735.eml, thalys/it-65927664.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = 'enquete@thalys.com';
    private $detectSubjects = [
        // en
        "We need you again!",
        "Your opinion matters!",
        // de
        "Wir möchten wieder Ihre Meinung einholen!",
        // nl
        "We hebben u opnieuw nodig!",
        // fr
        "Nous avons encore besoin de vous !",
    ];

    private $detectBody = [
        'en' => [
            'Your answers are valuable to us',
        ],
        'de' => [
            'Wir wissen Ihre Antworten sehr zu schätzen',
        ],
        'nl' => [
            'Uw antwoorden zijn waardevol',
        ],
        'fr' => [
            'Vos réponses sont précieuses',
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        if ($this->http->XPath->query("//a[contains(@href,'http://surveys.automatesurvey.com/s')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->setIsJunk(true);

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
