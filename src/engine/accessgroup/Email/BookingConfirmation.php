<?php

namespace AwardWallet\Engine\accessgroup\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "accessgroup/it-755987774.eml, accessgroup/it-762631766.eml";
    public $subjects = [
        '| Booking Confirmation (',
    ];

    public $date;

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Booking reference:' => ['Booking reference:', 'Reference code:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@partners.collinsbookings.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[(starts-with(normalize-space(), 'Your booking at') or starts-with(normalize-space(), 'Thank you, your enquiry')) and 
                        (contains(normalize-space(), 'is now') 
                        or contains(normalize-space(), 'is coming up') 
                        or contains(normalize-space(), 'has been'))]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Booking reference: DMN-') or starts-with(normalize-space(), 'Reference code: DMN-')]")->length > 0
            && ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Information from') and contains(normalize-space(), 'about your booking')]")->length > 0
            || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Booking type:')]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]partners\.collinsbookings\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->Event($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $travellers = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your booking at')]/following::text()[normalize-space()][1][not(contains(normalize-space(), '}'))]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/"));

        if (count($travellers) === 0) {
            $travellers = [$this->http->FindSingleNode("//text()[contains(normalize-space(), 'people on')]/preceding::text()[normalize-space()][1]")];
        }

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference:'))}]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*(DMN\-\d{10,})$/"))
            ->travellers(array_filter($travellers));

        $eventNameText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking at')]");

        if (empty($eventNameText)) {
            $eventNameText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you, your enquiry')]");
        }

        if (empty($eventNameText)) {
            $eventNameText = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your booking at')]")[0];
        }

        if (preg_match("/^Your booking at\s*(?<eName>.+)\s+(?:is now|has been)\s+(?<eStatus>\w+)\.?$/", $eventNameText, $m)
         || preg_match("/^Your booking at\s*(?<eName>.+)\s+is coming up/", $eventNameText, $m)) {
            $e->setName($m['eName']);

            if (isset($m['eStatus']) && !empty($m['eStatus'])) {
                $e->setStatus($m['eStatus']);
            }

            if ($m['eStatus'] == 'cancelled') {
                $e->setCancelled(true);
            }
        } elseif (preg_match("/has been\s*(?<eStatus>\w+)\.?$/", $eventNameText, $m)) {
            if ($m['eStatus'] == 'cancelled') {
                $e->setCancelled(true);
            }
        }

        $address = $this->http->FindSingleNode("//img[contains(@src, 'map-marker')]/following::text()[normalize-space()][1]");

        if (!empty($address)) {
            $e->setAddress($address);
        }

        $dateStartText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'people on')]");

        if (preg_match("/^(?<guests>\d+)\s+people on\s+(?<date>(?:\w+\s*\w+\s*\w+|\w+\s*\d+\s*\w+\s*\d{4}))\s+(?<startTime>[\d\:]+a?p?m)\s+to\s+(?<endTime>[\d\:]+a?p?m)\.?$/", $dateStartText, $m)) {
            if ($m['endTime'] == '12am') {
                $m['endTime'] = '11:59pm';
            }

            $e->setGuestCount($m['guests'])
                ->setStartDate($this->normalizeDate($m['date'] . ', ' . $m['startTime']));

            $endDate = $this->normalizeDate($m['date'] . ', ' . $m['endTime']);

            if ($e->getStartDate() > $endDate) {
                $e->setEndDate(strtotime('+1 day', $endDate));
            } else {
                $e->setEndDate($endDate);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Friday 8 December, 7pm
            "#^(\w+\s*\d+\s*\w+)\,\s*([\d\:]+a?p?m)$#i",
        ];
        $out = [
            "$1 $year, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
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
