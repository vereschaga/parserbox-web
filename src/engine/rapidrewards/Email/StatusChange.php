<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatusChange extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-67390427.eml, rapidrewards/it-76970171.eml, rapidrewards/it-132850096.eml, rapidrewards/it-134828324.eml";
    private $lang = '';
    private $reFrom = ['@aom.southwest.com'];
    private $reProvider = ['Southwest Airlines'];

    private $reSubject = [
        // en
        'Status Change from Southwest Airlines',
        'Changes to your Southwest trip',
        ' has a new departure time',
        'Passenger(s) cleared from the Standby List for',
        // es
        'tiene nueva hora de salida',
    ];
    private $reBody = [
        'en' => [
            'This is a flight status change regarding your trip.',
            'You have been rebooked to',
            'We know delays are frustrating, and we apologize',
            'We have moved you from the standby list to the flight list for Flight',
        ],
        'es' => ['Sabemos que los retrasos pueden ser frustrantes y te pedimos disculpas'],
    ];
    private static $dictionary = [
        'en' => [
            'and refer to record locator' => ['and refer to record locator', '/rebook3 and use'],
        ],
        'es' => [
            'We wanted to let you know that your upcoming Southwest flight, Flight' => 'Queremos informarte que tu próximo vuelo con Southwest con el número',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('StatusChange' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        /*
            Type 1. Cancelled Itinerary (examples: it-67390427.eml, it-76970171.eml)
        */

        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('and refer to record locator'))}]");
        $text = str_ireplace(['&zwnj;', '&8203;', '​'], '', $text);

        if (preg_match('/^\s*(Southwest Airlines) Flight (\d+) on (.+?) from ([A-Z]{3}) has been (cancell?ed)\./', $text, $m)) {
            /*
                Southwest Airlines Flight 1903 on March 13 from DEN has been cancelled.
            */

            if (preg_match("/{$this->opt($this->t('and refer to record locator'))}\s+([A-Z\d]{5,})\./", $text, $matches)) {
                $f->general()->confirmation($matches[1]);
            }

            $s = $f->addSegment();

            $s->airline()->name($m[1])->number($m[2]);

            $dateDep = EmailDateHelper::calculateDateRelative($this->normalizeDate($m[3]), $this, $parser, '%D% %Y%');

            $s->departure()
                ->noDate()
                ->day($dateDep)
                ->code($m[4])
            ;

            $s->arrival()->noCode()->noDate();

            $s->extra()->cancelled();

            return;
        }

        /*
            Type 2. Changed Itinerary (examples: it-132850096.eml, it-134828324.eml)
        */

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation #'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation #'))})[\s:：]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You have been rebooked to'))}]");

        if (preg_match("/{$this->opt($this->t('You have been rebooked to'))}\s+(?<codeArr>[A-Z]{3})\s+on\s+(?<airline>.+?)\s+Flight\s+(?<flightNumber>\d+)\s+departing on\s+(?<dateDep>.+?)\s+from\s+(?<codeDep>[A-Z]{3})\s+at\s+(?<timeDep>{$patterns['time']})(?:,\s+connecting to Flight\s+(?<flightNumberMiddle>\d+)\s+in\s+(?<codeMiddle>[A-Z]{3})\s+at\s+(?<timeMiddle>{$patterns['time']}))?\./", $text, $m)) {
            /*
                You have been rebooked to LAS on Southwest Flight 2315 departing on January 5 from DAL at 8:35PM.
                    [or]
                You have been rebooked to OGG on Southwest Flight 1847 departing on January 9 from DEN at 6:15AM, connecting to Flight 644 in OAK at 9:20AM.
            */

            $date = EmailDateHelper::calculateDateRelative($this->normalizeDate($m['dateDep']), $this, $parser, '%D% %Y%');

            $s = $f->addSegment();

            $s->airline()->name($m['airline'])->number($m['flightNumber']);

            $s->departure()->code($m['codeDep']);

            if ($date) {
                $s->departure()->date(strtotime($m['timeDep'], $date));
            }

            if (!empty($m['flightNumberMiddle'])) {
                // it-134828324.eml
                $s->arrival()->code($m['codeMiddle'])->noDate();

                $s = $f->addSegment();

                $s->airline()->name($m['airline'])->number($m['flightNumberMiddle']);

                $s->departure()->code($m['codeMiddle'])->date(strtotime($m['timeMiddle'], $date));
            }

            $s->arrival()->code($m['codeArr'])->noDate();
        }

        // Type 3. New departure time
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We wanted to let you know that your upcoming Southwest flight, Flight'))}]");

        if (
            // en
            preg_match("/{$this->opt($this->t('We wanted to let you know that your upcoming Southwest flight, Flight'))}\s+(?<flightNumber>\d+)\s+on\s+(?<dateDep>.+?)\s+from\s+(?<codeDep>[A-Z]{3}), has a new departure time of\s+(?<timeDep>{$patterns['time']})\./", $text, $m)
            // es
            || preg_match("/{$this->opt($this->t('We wanted to let you know that your upcoming Southwest flight, Flight'))}\s+(?<flightNumber>\d+)\s*, el\s+(?<dateDep>.+?)\s*, saliendo desde\s+(?<codeDep>[A-Z]{3}), tiene una nueva hora de salida a las\s+(?<timeDep>{$patterns['time']})\./", $text, $m)
        ) {
            /*
                We wanted to let you know that your upcoming Southwest flight, Flight 3398 on July 23 from LAS, has a new departure time of 8:50 AM.
            */
            $f->general()
                ->noConfirmation();

            $date = EmailDateHelper::calculateDateRelative($this->normalizeDate($m['dateDep']), $this, $parser, '%D% %Y%');

            $s = $f->addSegment();

            $s->airline()->name('Southwest Airlines')->number($m['flightNumber']);

            $s->departure()->code($m['codeDep']);

            if ($date) {
                $s->departure()->date(strtotime($m['timeDep'], $date));
            }

            $s->arrival()->noCode()->noDate();
        }

        // Type 4. Moved you from the standby list to the flight list
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('moved you from the standby list to the flight list for Flight'))}]");
        if (preg_match("/{$this->opt($this->t('moved you from the standby list to the flight list for Flight'))}\s+\#(?<flightNumber>\d+)\s+from\s+(?<codeDep>[A-Z]{3})\s+to\s+(?<codeArr>[A-Z]{3}), departing at\s+(?<timeDep>{$patterns['time']})\s*$/", $text, $m)) {
            /*
                We have moved you from the standby list to the flight list for Flight #1426 from PNS to DAL, departing at 2:30 p.m.
            */

            $date = null;
            if (preg_match("/from the Standby List for (?<month>\d{2})\\/(?<day>\d{2}) .* trip (?<conf>[A-Z\d]{5,7})\.\s*$/", $parser->getSubject(), $mat)) {
                $f->general()
                    ->confirmation($mat['conf']);
                $date = EmailDateHelper::calculateDateRelative($mat['day'].'.'.$mat['month'], $this, $parser, '%D%.%Y%');
            }

            $s = $f->addSegment();

            $s->airline()->name('Southwest')->number($m['flightNumber']);

            $s->departure()->code($m['codeDep']);

            if ($date) {
                $s->departure()->date(!empty($date) ? strtotime(str_replace('.', '', $m['timeDep']), $date) : null);
            }

            $s->arrival()->code($m['codeArr'])->noDate();
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
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
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date): string
    {
//        $this->logger->debug('$date in: ' . $date);

        $in = [
            // January 5
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/u",
            // 3 de set
            "/^\s*(\d{1,2})\s+de\s+([[:alpha:]]+)\s*$/u",
        ];
        $out = [
            "$2 $1",
            "$1 $2",
        ];
        $date = preg_replace($in, $out, $date);
        if (preg_match("#^\s*\d{1,2}\s+([[:alpha:]]+)$#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang))
                $date = str_replace($m[1], $en, $date);
        }

        return $date;
    }
}
