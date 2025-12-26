<?php

namespace AwardWallet\Engine\motelone\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingHtml extends \TAccountChecker
{
    public $mailFiles = "motelone/it-38102652.eml";

    public $reFrom = ["noreply@motel-one.com"];
    public $reBody = [
        'en' => ['have a great stay at Motel One'],
        'de' => ['einen schönen Aufenthalt bei Motel One'],
        'nl' => [' reis en een prettig verblijf bij MotelOne'],
    ];
    public $reSubject = [
        'Your booking at Motel One',
        'Ihre Buchung im Motel One',
        'Hartelijk dank voor uw boeking bij Motel One',
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'ADRESSE AND CONTACT' => 'ADRESSE AND CONTACT',
            'YOUR BOOKING'        => 'YOUR BOOKING',
            'checkinout'          => [
                'in' => [
                    '#^Check in is available from (\d+\s*[ap]m) on your arrival date$#',
                    '#^Check out is available until (\d+\s*[ap]m) on your departure date$#',
                ],
                'out' => [
                    '$1',
                    '$1',
                ],
            ],
            'adult'        => ['adult', 'adults'],
            'Total price:' => ['Total price:', 'Total price'],
        ],
        'de' => [
            'ADRESSE AND CONTACT' => 'ADRESSE UND KONTAKT',
            'CHECK IN/OUT'        => 'CHECK-IN/OUT',
            'checkinout'          => [
                'in' => [
                    '#^Ab (\d+:\d+) Uhr am Anreisetag$#',
                    '#^Bis (\d+:\d+) Uhr am Abreisetag$#',
                ],
                'out' => [
                    '$1',
                    '$1',
                ],
            ],
            'CANCELLATION POLICY' => 'STORNIERUNGSBEDINGUNGEN',
            'YOUR BOOKING'        => ['IHRE BUCHUNG', 'Ihre Buchung'],
            'ROOM'                => 'ZIMMER',
            'GUEST'               => 'GAST',
            'Room price'          => 'Preis Zimmer',
            'Total price:'        => ['Gesamtpreis:', 'Gesamtpreis'],
            'adult'               => 'Erwachsene',
            'FROM'                => 'VON',
        ],
        'nl' => [
            'ADRESSE AND CONTACT' => 'ADRES EN CONTACT',
            'CHECK IN/OUT'        => 'CHECK-IN/OUT',
            'checkinout'          => [
                'in' => [
                    '#^Vanaf (\d+:\d+) uur op de dag van aankomst$#',
                    '#^Vanaf (\d+:\d+) uur op de dag van vertrek$#',
                ],
                'out' => [
                    '$1',
                    '$1',
                ],
            ],
            'CANCELLATION POLICY' => 'ANNULERINGSVOORWAARDEN',
            'YOUR BOOKING'        => ['UW BOEKING', 'Uw Boeking'],
            'ROOM'                => 'KAMER',
            'GUEST'               => 'GAST',
            'Room price'          => 'Kamerprijs',
            'Total price:'        => ['Totaalprijs:', 'Totaalprijs'],
            'adult'               => 'volwassenen',
        ],
    ];
    private $keywordProv = 'Motel One';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Motel One' or contains(@src,'.motel-one.com')] | //a[contains(@href,'.motel-one.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//text()[translate(translate(normalize-space(.),'0123456789','dddddddddd'),' ','')='dd.dd.dddd-dd.dd.dddd']/ancestor::table[1][{$this->starts(['Motel', 'MOTEL'])}]";

        if (($node = $this->http->XPath->query($xpath))->length === 1) {
            $root = $node->item(0);
            $pax = [];

            $r = $email->add()->hotel();

            // confirmation, cancellation
            $r->general()
                ->confirmation($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root,
                    false, "#{$this->opt($this->t('YOUR BOOKING'))}\s+(.+)#"))
                ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY'))}]/following::*[normalize-space()!=''][1]"));

            // hotel Info
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ADRESSE AND CONTACT'))}]/following::text()[normalize-space()!=''][1]");
            $r->hotel()
                ->name($hotelName);
            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->eq($this->t('ADRESSE AND CONTACT'))}]/ancestor::td[contains(.,'@')][1]//text()[normalize-space()!='']"));

            if (preg_match("#{$hotelName}\s+(.+?)\n([+][\-\d \(\)]+)\n#is", $node, $m)) {
                $r->hotel()
                    ->address(trim(preg_replace("#\s+#", ' ', $m[1])))
                    ->phone(trim($m[2]));
            }

            // rooms, guests
            $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('ROOM'))}][./following::*[normalize-space()!=''][{$this->starts($this->t('GUEST'))}]])[1]");

            if (empty($node)) {
                $node = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('ROOM'))} and {$this->contains($this->t('FROM'))}])[1]");
            }

            $rooms = $this->re("#{$this->opt($this->t('ROOM'))}\s+\d+\s*.+?\s*(\d+)\s*$#", $node);

            if (empty($rooms)) {
                $rooms = $this->re("/{$this->opt($this->t('ROOM'))} \d+ {$this->opt($this->t('FROM'))} (\d{1,2})/",
                    $node);
            }

            $r->booked()
                ->rooms($rooms);
            $node = $this->http->FindSingleNode("./descendant::*[normalize-space()!=''][last()]", $root, false,
                "#(\d+)\s*{$this->opt($this->t('adult'))}#");
            $r->booked()
                ->guests($node);

            // check-in, check-out
            $node = $this->http->FindSingleNode("./descendant::text()[string-length(normalize-space())>2][2]", $root);

            if (preg_match("#^(\d+\.\d+\.\d{4})\s*\-\s*(\d+\.\d+\.\d{4})$#", $node, $m)) {
                $r->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime($m[2]));
            }

            $in = $this->t('checkinout')['in'];
            $out = $this->t('checkinout')['out'];

            $nodes = $this->http->FindNodes("//text()[{$this->eq($this->t('CHECK IN/OUT'))}]/ancestor::td[1][count(./descendant::text()[normalize-space()!=''])=3]/descendant::text()[normalize-space()!=''][position()>1]");

            $time = preg_replace($in, $out, $nodes[0]);

            if (!empty($time)) {
                $r->booked()
                    ->checkIn(strtotime($time, $r->getCheckInDate()));
            }

            $time = preg_replace($in, $out, $nodes[1]);

            if (!empty($time)) {
                $r->booked()
                    ->checkOut(strtotime($time, $r->getCheckOutDate()));
            }

            // rooms, travellers
            $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('ROOM'))}]/following::*[normalize-space()!=''][{$this->starts($this->t('GUEST'))}]/following::tr[1]");

            foreach ($nodes as $rootRoom) {
                $room = $r->addRoom();
                $setType = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][1][not(contains(normalize-space(), 'breakfast'))]", $rootRoom);

                if (empty($setType)) {
                    $setType = $this->http->FindSingleNode("./preceding::tr[starts-with(normalize-space(), 'ROOM')][1]/following::text()[contains(normalize-space(), 'room') or contains(normalize-space(), 'Room') or contains(normalize-space(), 'ROOM')][1]", $rootRoom);
                }

                if (empty($setType)) {
                    $setType = $this->http->FindSingleNode("./preceding::tr[{$this->starts($this->t('ROOM'))}][1]/following::td[contains(normalize-space(), 'Erwachsene')][1]/descendant::text()[normalize-space()][1]", $rootRoom);
                }
                $room->setType($setType);
                $pax[] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()!=''][1]", $rootRoom);
            }
            $r->general()
                ->travellers(array_unique($pax), true);

            // sums
            $cost = 0.0;
            $nodes = $this->http->FindNodes("//text()[{$this->starts($this->t('Room price'))}]/following::text()[normalize-space()!=''][1]");

            foreach ($nodes as $node) {
                $sum = $this->getTotalCurrency($node);

                if (!empty($sum['Total'])) {
                    $cost += $sum['Total'];
                } else {
                    $cost = null;

                    break;
                }
            }

            if (!empty($cost)) {
                $r->price()
                    ->cost($cost);
            }

            $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price:'))}]//ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");
            $sum = $this->getTotalCurrency($node);

            if (!empty($sum['Total']) && !empty($sum['Currency'])) {
                $r->price()
                    ->total($sum['Total'])
                    ->currency($sum['Currency']);
            }

            $this->detectDeadLine($r);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/A cancellation may be made free of charge until (?<date>.+) o[’']clock \(hotel[’']s local time\)\./iu",
                $cancellationText, $m)
            || preg_match("/Eine kostenfreie Stornierung ist bis (?<date>.+) Uhr \(Ortszeit des Hotels\) möglich\./iu",
                $cancellationText, $m)
            || preg_match("/Gratis annuleren is mogelijk tot (?<date>.+) uur \(lokale tijd van het hotel\)\./iu",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date']));

            return;
        }

        if (preg_match("/^Stornierung bei Nichtanreise nicht erforderlich, da das Zimmer automatisch nur bis (?<time>\d+:\d+) Uhr \(Ortszeit des Hotels\) gehalten wird\./iu",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);

            return;
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['ADRESSE AND CONTACT'], $words['YOUR BOOKING'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['ADRESSE AND CONTACT'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['YOUR BOOKING'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
