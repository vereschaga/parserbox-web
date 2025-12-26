<?php

namespace AwardWallet\Engine\velocity\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser flightcentre/FlightChange (in favor of velocity/It2818504)

class It2818504 extends \TAccountChecker
{
    public $mailFiles = "velocity/it-2818504.eml, velocity/it-707578308.eml";

    public $subjects = [
        'Flight Change Notification for Reservation',
        'Flight Change Notification for Booking Reference',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'hello'                                   => ['Hello', 'Dear'],
            'Booking Reference'                       => ['Booking Reference', 'Reservation Number'],
            'statusPhrases'                           => ['Your flight has'],
            'statusVariants'                          => ['changed'],
            'The new flight times are detailed below' => ['The new flight times are detailed below', 'THE NEW FLIGHT TIMES ARE DETAILED BELOW'],
            'Your new itinerary'                      => ['Your new itinerary', 'YOUR NEW ITINERARY'],
            'new'                                     => ['new', 'New', 'NEW'],
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'virginaustralia.com/images/VA_logo_velocity')]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['This email is being sent to you by Virgin Australia', 'This email is being sent to you by Velocity Frequent Flyer'])}]")->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('The new flight times are detailed below'))} or {$this->contains($this->t('Your new itinerary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:virginaustralia|velocityfrequentflyer)\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference'))}\s*([A-Z\d]{5,})$/"))
            ->traveller(preg_replace("/^(?:Miss|Mrs|Mr|Ms|Dr)[.\s]+(.{2,})$/i", '$1', $this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]", null, true, "/^{$this->opt($this->t('hello'))}\s*(.{2,}?)\s*,/")));

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        // it-2818504.eml
        $segments = $this->http->XPath->query("//text()[{$this->starts($this->t('Your new itinerary'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");
        $shift = 0;

        if ($segments->length === 0) {
            // it-707578308.eml
            $segments = $this->http->XPath->query("//text()[{$this->starts($this->t('The new flight times are detailed below'))}]/following::tr[ *[normalize-space()][1][{$this->eq($this->t('new'))}] and *[4] ]");
            $shift = 1;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("*[1+{$shift}]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("*[2+{$shift}]", $root, true, "/\(\s*([A-Z]{3})\s*\)/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[2+{$shift}]", $root, true, "/{$patterns['time']}.{3,}$/u")));

            $s->arrival()
                ->code($this->http->FindSingleNode("*[3+{$shift}]", $root, true, "/\(\s*([A-Z]{3})\s*\)/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[3+{$shift}]", $root, true, "/{$patterns['time']}.{3,}$/u")));
        }

        // Remove duplicate segments

        $flightArr = $f->toArray();
        $segmentsArrSrc = array_key_exists('segments', $flightArr) ? $flightArr['segments'] : [];

        if (!is_array($segmentsArrSrc) || count($segmentsArrSrc) === 0) {
            $this->logger->debug('Flight segments not found!');

            return;
        }

        $segmentsArrSerialize = array_map('serialize', $segmentsArrSrc);
        $segmentsArrUnique = array_values(array_unique($segmentsArrSerialize));

        $segmentsDiff = count($segmentsArrSrc) - count($segmentsArrUnique);

        if ($segmentsDiff < 1) {
            $this->logger->debug('Duplicate segments not found.');

            return;
        }

        $email->removeItinerary($f);

        if (count($email->getItineraries()) === 0) {
            $email->clearItineraries(); // for reset array indexes
        }

        $flightArr['segments'] = array_map('unserialize', $segmentsArrUnique);
        $f = $email->add()->flight();
        $f->fromArray($flightArr);
        $this->logger->debug('Success removed ' . $segmentsDiff . ' duplicate segments.');
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            //13:40 Fri, Jan 20
            "#^([\d\:]+)\s*(\w+)\,\s*(\w+)\s*(\d+)$#i",
        ];
        $out = [
            "$2, $4 $3 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
