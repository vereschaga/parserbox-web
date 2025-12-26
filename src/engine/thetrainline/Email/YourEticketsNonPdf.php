<?php

namespace AwardWallet\Engine\thetrainline\Email;

// use AwardWallet\Common\Parser\Util\EmailDateHelper;
// use AwardWallet\Engine\MonthTranslate;
// use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourEticketsNonPdf extends \TAccountChecker
{
    public $mailFiles = "thetrainline/it-72603221.eml, thetrainline/it-73568895.eml";

    public static $dictionary = [
        'en' => [
            //            ' to ' => '',
            'View tickets -' => 'View tickets -',
            'btnSubname'     => ['Railcard'],
        ],
        'it' => [
            ' to '           => ' a ',
            'View tickets -' => 'Visualizza biglietti -',
            // 'btnSubname' => '',
        ],
    ];

    private $detectSubject = [
        // en
        'Your etickets to',
        // it
        'I tuoi e-ticket per',
    ];

    private $detectBody = [
        'en' => ['Your etickets for your trip to'],
        'it' => ['Trovi il link degli e-ticket per'],
    ];

    private $lang = '';
    private $region = '';
    // private $date;

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]thetrainline\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"thetrainline.com")]')->length == 0) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['View tickets -']) && $this->http->XPath->query("//*[" . $this->starts($dict['View tickets -']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        // $this->date = strtotime($parser->getDate());
        $this->parseHtml($email);

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

    private function assignRegion(string $nameStation): void
    {
        // added region for google, to help find correct address of stations
        if (preg_match("/\bParis\b/i", $nameStation)) {
            $this->region = 'France';
        } elseif (preg_match("/(?:\bLondon\b|\bOxford\b|\bManchester\b)/i", $nameStation)) {
            $this->region = 'United Kingdom';
        } elseif ($this->region === '') {
            $this->region = 'Europe';
        }
    }

    private function parseHtml(Email $email): void
    {
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();

        // Segments
        $xpath = "//text()[{$this->starts($this->t('View tickets -'))}]/preceding::text()[normalize-space() and not({$this->starts($this->t('View tickets -'))}) and not({$this->contains($this->t('btnSubname'))})][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('Segments not found by xpath: ' . $xpath);

            return;
        }

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            if (preg_match("/(.{3,}){$this->opt($this->t(' to '))}(.{3,})/", $this->http->FindSingleNode('.', $root), $m)) {
                $this->assignRegion($m[1]);
                $s->departure()
                    ->noDate()
                    ->name(implode(', ', [$m[1], $this->region]))
                ;
                $this->assignRegion($m[2]);
                $s->arrival()
                    ->noDate()
                    ->name(implode(', ', [$m[2], $this->region]))
                ;

                $s->extra()->noNumber();
            }
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /*
    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Mon, May 20
            '#^(\w+),\s*(\w+)\s+(\d+)\s*$#u',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
    */

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
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
