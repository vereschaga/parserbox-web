<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "tcase/it-349622880.eml, tcase/it-39620837.eml, tcase/it-58016151.eml, tcase/it-625225476.eml, tcase/it-628568527.eml, tcase/it-6718533.eml"; // +1 bcdtravel(html)[en]

    public $reSubject = [
        'es'  => 'Itinerario del viaje',
        'es2' => ' ha cambiado',
        'pt'  => 'Itinerário da viagem',
        'en'  => 'Itinerary for',
        'ja'  => 'の旅程',
        'zh'  => '旅程行程',
    ];

    public $langDetectors = [
        'es' => ['compartido un viaje', "habido cambios en el viaje "],
        'en' => ['sharing a trip', 'has changed'],
        'pt' => ['compartilhando uma viagem com você', 'para ver todos os detalhes.'],
        'ja' => ['あなたと旅行情報を共有しています'],
        'zh' => ['與您分享了一個旅程'],
    ];

    public static $dict = [
        'en' => [
            //			'is sharing a trip with you' => '',
            //			'to' => '',
            //			'Check-in' => '',
            //			'Check-out' => '',
        ],
        'es' => [
            'is sharing a trip with you' => 'ha compartido un viaje contigo',
            'to'                         => 'a',
            'Check-out'                  => ['Check-out', 'Check out'],
        ],

        'pt' => [
            'is sharing a trip with you' => 'está compartilhando uma viagem com você',
            'to'                         => 'para',
            'Check-out'                  => ['Check-out', 'Check out'],
        ],
        'ja' => [
            'is sharing a trip with you' => 'あなたと旅行情報を共有しています',
            'to'                         => 'a',
            'Check-in'                   => 'チェックイン',
            'Check-out'                  => 'チェックアウト',
        ],
        'zh' => [
            'is sharing a trip with you' => '與您分享了一個旅程',
            'to'                         => '至',
            'Check-in'                   => '入住',
            'Check-out'                  => '退房',
        ],
    ];

    private $lang = '';
    private $year = '';
    private $xpathFragmentDate;
    private $travelerName;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getDate()));

        if ($this->assignLang() === false) {
            return false;
        }

        $email->setType('AirTicket' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'TripCase') !== false
            || stripos($from, '@tripcase.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"to receive emails from TripCase") or contains(normalize-space(.),"All rights reserved.TripCase") or contains(normalize-space(.),"Sign up for TripCase") or contains(normalize-space(.),"View trip in TripCase")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tripcase.com") or contains(@href,"//travel.tripcase.com") or contains(@href,"//services.tripcase.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    private function parseFlight(Email $email, $flights)
    {
        $xpathFragmentDate = $this->xpathFragmentDate;
        $r = $email->add()->flight();

        $r->general()->noConfirmation();

        if (empty($this->travelerName)) {
            $this->travelerName = $this->getTravelerName();
        }

        if (!empty($this->travelerName)) {
            $r->general()->traveller($this->travelerName);
        }

        $xpath = "//img[contains(@src, 'tripcase/ico-plane')]/ancestor::tr[1]";

        if ($flights->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return null;
        }

        foreach ($flights as $root) {
            $s = $r->addSegment();

            if (preg_match('/(.+) ' . $this->t("to") . ' (.+)/',
                $this->http->FindSingleNode('td[2]/descendant::span[1]', $root), $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            $airlineInfo = $this->http->FindSingleNode('td[2]/descendant::span[2]', $root);

            if (empty($airlineInfo)) {
                $airlineInfo = $this->http->FindSingleNode('./td[2]/descendant::span[normalize-space()][2]', $root);
            }

            if (preg_match('/(.+)\s+(\d+)/', $airlineInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $s->arrival()
                    ->noDate()
                    ->noCode();
                $s->departure()->noCode();
            }

            $depTime = $this->http->FindSingleNode('ancestor::tr[1]/td[1]', $root);

            $date = $this->http->FindSingleNode($xpathFragmentDate, $root);

            if (!empty($depTime) && !empty($date)) {
                $s->departure()->date($this->normalizeDate($date . ' ' . $this->year . ', ' . $depTime));
            }

            $segments = $r->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize($segment->toArray()) === serialize($s->toArray())) {
                        $r->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function parseHotel(Email $email, $hotels)
    {
        $xpathFragmentDate = $this->xpathFragmentDate;

        $hotelsSegment = [];

        for ($i = 0; $i < $hotels->length; $i++) {
            $seg = [];
            $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Check-in'))} or {$this->starts($this->t('Check-out'))}]/preceding::node()[normalize-space(.)][1]",
                $hotels->item($i));

            if (!empty($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Check-in'))}]", $hotels->item($i)))) {
                $seg['type'] = 'checkIn';
            } elseif (!empty($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Check-out'))}]", $hotels->item($i)))) {
                $seg['type'] = 'checkOut';
            } else {
                $seg['type'] = 'unknown';
            }

            $date = $this->http->FindSingleNode($xpathFragmentDate, $hotels->item($i));
            $time = $this->http->FindSingleNode("ancestor::tr[1]/td[1]", $hotels->item($i));

            $seg['date'] = null;

            if (!empty($date) && !empty($time)) {
                $seg['date'] = $this->normalizeDate($date . ' ' . $this->year . ', ' . $time);
            }
            $hotelsSegment[$hotelName][] = $seg;
        }

        foreach ($hotelsSegment as $hotelName => $segments) {
            $segments = array_values(array_map('unserialize', array_unique(array_map('serialize', $segments))));

            if (count($segments) % 2 == 0) {
                for ($i = 0; $i < count($segments); $i = $i + 2) {
                    $r = $email->add()->hotel();

                    if (empty($this->travelerName)) {
                        $this->travelerName = $this->getTravelerName();
                    }

                    if (!empty($this->travelerName)) {
                        $r->general()->traveller($this->travelerName);
                    }

                    $r->general()->noConfirmation();

                    $r->hotel()->name($hotelName);
                    $r->hotel()->noAddress();

                    if ($segments[$i]['type'] === 'checkIn' && $segments[$i + 1]['type'] === 'checkOut') {
                        $r->booked()->checkIn($segments[$i]['date']);
                        $r->booked()->checkOut($segments[$i + 1]['date']);
                    }
                }
            } else {
                $r = $email->add()->hotel();
            }
        }
    }

    private function parseCar(Email $email, $cars)
    {
        $xpathFragmentDate = $this->xpathFragmentDate;

        if ($cars->length === 2) {
            $r = $email->add()->rental();

            $r->general()->noConfirmation();

            if (empty($this->travelerName)) {
                $this->travelerName = $this->getTravelerName();
            }

            if (!empty($this->travelerName)) {
                $r->general()->traveller($this->travelerName);
            }

            $pickupDate = $this->http->FindSingleNode($xpathFragmentDate, $cars->item(0));
            $dropoffDate = $this->http->FindSingleNode($xpathFragmentDate, $cars->item(1));

            if (!empty($pickupDate) && !empty($dropoffDate)) {
                if ($pickupTime = $this->http->FindSingleNode("ancestor::tr[1]/td[1]", $cars->item(0))) {
                    $r->pickup()->date($this->normalizeDate($pickupDate . ' ' . $this->year . ', ' . $pickupTime));
                }

                if ($dropoffTime = $this->http->FindSingleNode("ancestor::tr[1]/td[1]", $cars->item(1))) {
                    $r->dropoff()->date($this->normalizeDate($dropoffDate . ' ' . $this->year . ', ' . $dropoffTime));
                }
            }

            $xpathFragmentLocation = '[ normalize-space(.) and ./td[3] ][1]/descendant::img[contains(@src,"tripcase/ico-plane")]/ancestor::tr[1]/td[2]/descendant::span[1]';

            $node = $this->http->FindSingleNode('./ancestor::tr[1]/preceding-sibling::tr' . $xpathFragmentLocation,
                $cars->item(0));

            if (preg_match('/.+ ' . $this->t("to") . ' (.+)/', $node, $m)) {
                $r->pickup()->location($m[1]);
            }

            $node = $this->http->FindSingleNode('./ancestor::tr[1]/following-sibling::tr' . $xpathFragmentLocation,
                $cars->item(1));

            if (preg_match('/(.+) ' . $this->t("to") . ' .+/', $node, $m)) {
                $r->dropoff()->location($m[1]);
            }
        }
    }

    private function parseEvent(Email $email, $event)
    {
        $xpathFragmentDate = $this->xpathFragmentDate;

        if (!empty($event)) {
            $r = $email->add()->event();

            if (empty($this->travelerName)) {
                $this->travelerName = $this->getTravelerName();
            }

            if (!empty($this->travelerName)) {
                $r->general()->traveller($this->travelerName);
            }

            $eventDateStart = $this->http->FindSingleNode($xpathFragmentDate, $event->item(0));
            $eventTimeStart = $this->http->FindSingleNode('ancestor::tr[1]/td[1]', $event->item(0));

            $r->booked()->noEnd();
            $r->general()->noConfirmation();

            if (!empty($eventDateStart) && !empty($eventTimeStart)) {
                $r->booked()->start($this->normalizeDate($eventDateStart . ' ' . $this->year . ', ' . $eventTimeStart));
            }

            $r->place()->name($this->http->FindSingleNode("td[1]/following::span[1]", $event->item(0)));
            $r->place()->address($this->http->FindSingleNode("td[1]/following::span[1]", $event->item(0)));
            $r->place()->type(EVENT_EVENT);
        }
    }

    private function getTravelerName()
    {
        $travelerName = $this->http->FindSingleNode('//*[@id="itinerary_traveler_name"]', null, true,
            '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (!$travelerName) {
            $travelerName = $this->http->FindSingleNode("//tr[contains(normalize-space(),'{$this->t("is sharing a trip with you")}') and not(.//tr)]",
                null, true, "/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*{$this->t("is sharing a trip with you")}/u");
        }

        return $travelerName;
    }

    private function parseEmail(Email $email)
    {
        $this->xpathFragmentDate = './ancestor::tr[1]/preceding-sibling::tr[ normalize-space(.) and not(./td[3]) ][1]';

        $flights = $this->http->XPath->query("//img[contains(@src, 'tripcase/ico-plane')]/ancestor::tr[1]");

        if ($flights->length !== 0) {
            $this->parseFlight($email, $flights);
        }
        $hotels = $this->http->XPath->query("//img[contains(@src, 'tripcase/ico-bed')]/ancestor::tr[1]");

        if ($hotels->length !== 0) {
            $this->parseHotel($email, $hotels);
        }
        $cars = $this->http->XPath->query("//img[contains(@src, 'tripcase/ico-car')]/ancestor::tr[1]");

        if ($cars->length !== 0) {
            $this->parseCar($email, $cars);
        }
        $event = $this->http->XPath->query("//img[contains(@src, 'tripcase/ico-activity')]/ancestor::tr[1]");

        if ($event->length !== 0) {
            $this->parseEvent($email, $event);
        }
    }

    private function normalizeDate($dateStr)
    {
        // $this->logger->debug('$dateStr = '.print_r( $dateStr,true));

        $dateStr = str_replace(chr(226) . chr(128) . chr(140), ' ', $dateStr);
        $in = [
            '/^\s*(\w+),\s*(\w+)\s+(\d{1,2})\s+(\d{4}),\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            '/^\s*([\w\-]+)\,?\s+(\d{1,2})\s+de\s+(\w+)\s+(\d{4}),\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
            //日曜日, ‌8月 ‌4 2019, ‌12:00 午後
            '/^\s*([\w\-]+)曜日,\s+(\d+)月\s+(\d+)\s+(\d{4}),\s+(\d+:\d+)\s*午後\s*$/iu',
        ];
        $out = [
            '$1, $3 $2 $4, $5',
            '$1, $2 $3 $4, $5',
            '$1, $4-$2-$3, $5 PM',
        ];
        $dateStr = preg_replace($in, $out, $dateStr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $dateStr, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $dateStr = str_replace($m[1], $en, $dateStr);
            }
        }

        if (preg_match("/^(?<week>[\w\-]+), (?<date>\d+ \w+ (?<year>20\d{2})\b.*)/u", $dateStr, $m)
            || preg_match("/^(?<week>[\w\-]+), (?<date>(?<year>20\d{2})-\d{1,2}-\d{1,2}\b.*)/u", $dateStr, $m)
        ) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $this->year = $m['year'];

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ (?<year>20\d{2})(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $dateStr, $m)) {
            $this->year = $m['year'];

            return strtotime($dateStr);
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
