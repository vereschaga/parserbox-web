<?php

namespace AwardWallet\Engine\googlefl\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class SharedTrip extends \TAccountChecker
{
    public $mailFiles = "googlefl/it-201060786.eml, googlefl/it-202647062.eml, googlefl/it-202817493.eml, googlefl/it-203426644.eml, googlefl/it-215762877.eml";
    public $detectSubject = [
        // en
        "\'s trip:",
        // hu
        " utazása: ",
    ];
    public $detectBody = [
        'en' => [
            ['You received this message because', 'has shared a trip with you'],
        ],
        'hu' => [
            ['Azért kapta ezt az üzenetet, mert', 'utazást osztott meg Önnel'],
        ],
    ];
    public $year;
    public $lang = "en";

    private static $dict = [
        'en' => [
//            'has shared a trip with you' => '', // from begging of email
//            'Confirmation' => '',
            // flight, train
//            'Depart' => '',
//            'Arrive' => '',
//            'Overnight' => '',
            // hotel
            'Check in' => ['Check in at', 'Check in'],
            'Check out' => ['Check out at', 'Check out'],
            // event restaurant
//            'Reservation at:' => '',
            // event event
//            'Starts at' => '',
        ],
        'hu' => [
            'has shared a trip with you' => 'has shared a trip with you', // from begging of email
            'Confirmation' => 'Visszaigazolás',
            // flight, train
            'Depart' => 'indulás innen:',
            'Arrive' => 'érkezés ide:',
            'Overnight' => 'Éjszakai járat',
            // hotel
            'Check in' => ['Bejelentkezés:'],
            'Check out' => ['Kijelentkezés:'],
            // event restaurant
//            'Reservation at:' => '',
            // event event
//            'Starts at' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@gmail.com')) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img/@*[contains(., 'googlelogo')]/ancestor::*[normalize-space()][1]")->length === 0) {
            return false;
        }
        foreach ($this->detectBody as $lang => $bodies) {
            foreach ($bodies as $body) {
                if (isset($body[0], $body[1]) && $this->http->XPath->query("//node()[{$this->starts($body[0])} and {$this->contains($body[1])} and .//a[contains(@href, '@gmail.com')]]")->length > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $bodies) {
            foreach ($bodies as $body) {
                if (isset($body[0], $body[1]) && $this->http->XPath->query("//node()[{$this->starts($body[0])} and {$this->contains($body[1])} and .//a[contains(@href, '@gmail.com')]]")->length > 0) {
                    $this->lang = $lang;
                    break;
                }
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $email->obtainTravelAgency();

        $xpath = "//tr[*[not(normalize-space())]//img and count(*[normalize-space()]) = 1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $node = $this->http->XPath->query($xpath);

        $this->year = $this->http->FindSingleNode("//text()[{$this->contains($this->t("has shared a trip with you"))}]/following::text()[normalize-space()][2]",
            null, true, "/\b(\d{4})\b/");
        if (empty($this->year)) {
            $this->year = $this->http->FindSingleNode("preceding::text()[normalize-space()][2]",
                $node[0], true, "/\b(\d{4})\b/");
        }
        $allHotels = [];
        foreach ($node as $i => $root) {

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::td[not(.//img)][not(ancestor::tr[*[not(normalize-space())]//img and count(*[normalize-space()]) = 1])][1]",
                $root, null, "/^(.+?)(?:\–|$)/"));

            $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
//            $this->logger->debug('$text = '.print_r( $text,true));

            if (preg_match("/{$this->opt($this->t("Depart"))}\s*.+\s+[A-Z]{3}\s+/", $text)) {

                // FLIGHT
                if (!isset($f)) {
                    $f = $email->add()->flight();

                    $f->general()->noConfirmation();
                }

                $s = $f->addSegment();
                $re = "/^\s*(?<al>.+?)\s+(?<fn>\d{1,5})\s*\n\s*(?<dTime>\d{1,2}:\d{2}(?: *[APap][Mm])?)\s*[-\–]\s*{$this->opt($this->t("Depart"))}\s+(?<dName>.+)\s+(?<dCode>[A-Z]{3})\s*\n\s*(?<aTime>\d{1,2}:\d{2}(?: *[APap][Mm])?)\s*[-\–]\s*{$this->opt($this->t("Arrive"))}\s+(?<aName>.+)\s+(?<aCode>[A-Z]{3})/u";
//                $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $text, $m)) {

                    if (preg_match("/^\s*".$this->opt($this->t("Overnight"))."\s*$/", $m['al'])) {
                        $s->airline()
                            ->noName();
                    } else {
                        $s->airline()
                            ->name($m['al']);
                    }
                    $s->airline()
                        ->number($m['fn'])
                    ;
                    $s->departure()
                        ->name($m['dName'])
                        ->code($m['dCode'])
                        ->date(!empty($date)? strtotime($m['dTime'], $date) :  null)
                    ;
                    $s->arrival()
                        ->name($m['aName'])
                        ->code($m['aCode'])
                        ->date(!empty($date)? strtotime($m['aTime'], $date) :  null)
                    ;
                }

            } elseif (preg_match("/{$this->opt($this->t("Depart"))}\s+/", $text)) {

                // TRAIN
                if (!isset($t)) {
                    $t = $email->add()->train();

                    $t->general()->noConfirmation();
                }

                $s = $t->addSegment();
                $re = "/^\s*(?<name>[^\d\n]+?\s*\n\s*)?(?<dTime>\d{1,2}:\d{2}(?: *[APap][Mm])?)\s*[-\–]\s*{$this->opt($this->t("Depart"))}\s+(?<dName>.+)\s*\n\s*(?<aTime>\d{1,2}:\d{2}(?: *[APap][Mm])?)\s*[-\–]\s*{$this->opt($this->t("Arrive"))}\s+(?<aName>.+)/u";
//                $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $text, $m)) {

                    $s->departure()
                        ->name($m['dName'])
                        ->date(!empty($date)? strtotime($m['dTime'], $date) :  null)
                    ;
                    $s->arrival()
                        ->name($m['aName'])
                        ->date(!empty($date)? strtotime($m['aTime'], $date) :  null)
                    ;
                    $s->extra()
                        ->noNumber()
                        ->service($m['name'], true, true)
                    ;
                }
            } elseif (preg_match("/{$this->opt($this->t("Check in"))}/", $text)) {

                // HOTEL
                $h = $email->add()->hotel();

                $h->general()->noConfirmation();

                $re = "/^\s*(?<name>.+)(?<address>\s*\n\s*[\s\S]+?)?(?:\s*,?\s*\n\s*(?<phone>[\d\(\)\+\- \.]{6,}))?\s+{$this->opt($this->t("Check in"))}\s*(?<ci>[\s\S]+)\s+{$this->opt($this->t("Check out"))}\s*(?<co>[\S\s]+?)\s*(\n\s*{$this->opt($this->t("Confirmation"))}|$)/";
//                $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $text, $m)) {
                    if (isset($allHotels[trim($m['name']).trim($m['ci']).trim($m['co'])])) {
                        $email->removeItinerary($h);
                        continue;
                    }
                    $allHotels[trim($m['name']).trim($m['ci']).trim($m['co'])] = true;

                    $h->hotel()
                        ->name(trim($m['name']))
                        ->address(preg_replace(["/\s*,\s*/", "/\s+/"], [', ', ' '], trim($m['address'])))
                        ->phone(trim($m['phone'] ?? ''), true, true)
                    ;

                    $h->booked()
                        ->checkIn($this->normalizeDate($m['ci']))
                        ->checkOut($this->normalizeDate($m['co']))
                    ;
                }
            } elseif (preg_match("/{$this->opt($this->t("Reservation at:"))}/", $text)) {

                // EVENT RESTAURANT
                $ev = $email->add()->event();

                $ev->general()->noConfirmation();

                $re = "/^\s*(?<name>.+)(?<address>\s*\n\s*[\s\S]+?)?\s+{$this->opt($this->t("Reservation at:"))}\s*(?<time>.+)/";
//                $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $text, $m)) {

                    $ev->place()
                        ->name($m['name'])
                        ->type(Event::TYPE_RESTAURANT);

                    if (!empty($m['address'])) {
                        $ev->place()
                            ->address(preg_replace(["/\s*,\s*/", "/\s+/"], [', ', ' '], trim($m['address'], ',')));
                    }

                    $ev->booked()
                        ->start(!empty($date) ? strtotime($m['time'], $date) : null)
                        ->noEnd();
                }
            } elseif (preg_match("/{$this->opt($this->t("Starts at"))}/", $text)) {

                // EVENT EVENT
                $ev = $email->add()->event();

                $ev->general()->noConfirmation();

                $re = "/^\s*(?<name>.+)(?<address>\s*\n\s*[\s\S]+?)?\s+{$this->opt($this->t("Starts at"))}\s*(?<time>.+)/";
                $this->logger->debug('$re = '.print_r( $re,true));
                if (preg_match($re, $text, $m)) {

                    $ev->place()
                        ->name($m['name'])
                        ->type(Event::TYPE_EVENT)
                    ;

                    if (!empty($m['address'])) {
                        $ev->place()
                            ->address(preg_replace(["/\s*,\s*/", "/\s+/"], [', ', ' '], trim($m['address'])))
                        ;
                    }

                    $ev->booked()
                        ->start(!empty($date)? strtotime($m['time'], $date) :  null)
                        ->noEnd()
                    ;
                }

            } elseif (preg_match("/^[^\d\n]*car[^\d\n]*\s+\d{1,2}:\d{2}.*\s*-\s*\D*{$this->opt($this->t("Confirmation"))}/i", $text)) {
                continue;
            } else {
                $email->add()->flight();
                $this->logger->debug('Segment type not detected = '.print_r( $date,true));
            }
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Wed, Nov 16
            '/^\s*([[:alpha:]]+)(?:\s*,\s*|\s+)([[:alpha:]]+)\s+(\d{1,2})\s*$/u',
            // Thu 6 Oct
            '/^\s*([[:alpha:]]+)(?:\s*,\s*|\s+)(\d{1,2})\s+([[:alpha:]]+)\s*$/u',
            // okt. 30., V
            '/^\s*([[:alpha:]]{3,})\.\s+(\d{1,2})\.[\s,]+([[:alpha:]]+)\s*$/u',
        ];
        $out = [
            "$1, $3 $2 $this->year",
            "$1, $2 $3 $this->year",
            "$3, $2 $1 $this->year",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str 2 = '.print_r( $str,true));

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
}
