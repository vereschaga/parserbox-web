<?php

namespace AwardWallet\Engine\traveluro\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "traveluro/it-637636870.eml, traveluro/it-735041928.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Dear'                               => ['Dear', 'Hi'],
            'that your Traveluro reservation at' => ['that your Traveluro reservation at', 'Your reservation at'],
            'with trip ID'                       => 'with trip ID',
        ],
    ];

    private $detectFrom = "donotreply@traveluro.com";
    private $detectSubject = [
        // en
        'Your Reservation Has Been Cancelled - Trip number : #',
    ];
    private $detectBody = [
        'en' => [
            'is cancelled.', 'is canceled.',
            'has been cancelled.', 'has been canceled.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]traveluro\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.traveluro.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Traveluro Team', 'seeing you again on Traveluro.'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email, $parser->getSubject());

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["that your Traveluro reservation at"])
                && $this->http->XPath->query("//*[{$this->contains($dict['that your Traveluro reservation at'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email, string $subject): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $h->general()->traveller($traveller);

        // it-637636870.eml
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('that your Traveluro reservation at'))}]/ancestor-or-self::*[{$this->contains($this->t('with trip ID'))}][1]");

        if (preg_match("/{$this->opt($this->t('that your Traveluro reservation at'))}\s+(.+?)[\s,]+{$this->opt($this->t('with trip ID'))}\s*(\d{5,})\b/", $text, $m)) {
            // Travel Agency
            $email->ota()
                ->confirmation($m[2]);

            // General
            $h->general()
                ->noConfirmation()
                ->cancelled()
                ->status('Cancelled')
            ;

            // Hotel
            $h->hotel()
                ->name($m[1]);

            return;
        }

        // it-735041928.eml
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('that your Traveluro reservation at'))}]");

        if (preg_match("/{$this->opt($this->t('that your Traveluro reservation at'))}\s+(?<name>.{2,75}?)\s+{$this->opt($this->t('is cancelled'))}[\s.;!]*$/", $text, $m)) {
            $h->hotel()->name($m['name']);

            $h->general()
                ->noConfirmation()
                ->cancelled()
                ->status('Cancelled')
            ;
        }

        if (preg_match("/({$this->opt($this->t('Trip number'))})[:\s]+#?\s*(\d{5,})$/", $subject, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
