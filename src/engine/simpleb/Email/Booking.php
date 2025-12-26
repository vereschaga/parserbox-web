<?php

namespace AwardWallet\Engine\simpleb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "simpleb/it-278702188.eml, simpleb/it-284961014.eml, simpleb/it-310844254.eml";
    public $subjects = [
        '- Booking Confirmation - ',
    ];

    public $lang = '';
    public $date;
    public $headerStatus = false;

    public $detectLang = [
        "it" => ["Nominativo:"],
        "en" => ["Name:", ''],
    ];

    public $reSubject;

    public static $dictionary = [
        "en" => [
            'Booking N.:'              => ['Booking N.:', 'Booking Nr:'],
        ],
        "it" => [
            'Booking online service - SimpleBooking' => 'Servizio di prenotazione online - SimpleBooking',
            'Receipt of reservation N°'              => 'Conferma prenotazione N°',
            'Room type:'                             => 'Tipo Camera:',

            'Free cancellation until:' => 'Cancellazione gratuita fino al:',
            'Booking N.:'              => ['N. prenotazione:'],
            'Reservation made for:'    => 'Prenotazione a nome di:',
            'Name:'                    => 'Nominativo:',
            'Cancellation policies:'   => 'Termini di cancellazione:',
            'Booking Confirmation'     => 'Conferma prenotazione',
            'Hotel:'                   => 'Nome:',
            'Address:'                 => 'Indirizzo:',
            'City:'                    => 'Località:',
            //'Tel.:' => '',
            //'Fax.:' => '',
            'Arrival:'       => 'Data Arrivo:',
            'Departure:'     => 'Data Partenza:',
            'persons:'       => 'persone:',
            'Total amount :' => 'Importo globale:',
            //'Total additional services' => '',
            'Total Rooms' => 'Totale Camere',
            'Adults'      => 'Adulti',
            'Price'       => 'Importo',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@simplebooking.it') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking online service - SimpleBooking'))}]")->length > 0
        || $this->http->XPath->query("//img[contains(@src, 'email_icons/simplebooking')]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('Receipt of reservation N°'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation made for:'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Room type:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]simplebooking\.it$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking N.:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{10,})$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Receipt of reservation N°'))}]", null, true, "/^{$this->opt($this->t('Receipt of reservation N°'))}\s*(\d{10,})$/");
        }
        $h->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation made for:'))}]/following::text()[{$this->eq($this->t('Name:'))}][1]/following::text()[normalize-space()][1]", null, true, "/^(\D+)$/");
        $h->general()
            ->traveller($traveller, true);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policies:'))}]/following::text()[normalize-space()][1]/ancestor::div[1]");

        if (strlen($cancellation) > 2000) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policies:'))}]/following::p[1]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $hotelName = $this->re("/^\s*(.+)\s*\-\s*{$this->opt($this->t('Booking Confirmation'))}/", $this->reSubject);

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);
        }

        $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()][1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[2]/descendant::text()[{$this->eq($this->t('Address:'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here\'s a summary:'))}]/following::text()[{$this->eq($this->t('Address:'))}][1]/following::text()[normalize-space()][1]");
        }

        $city = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('City:'))}]/following::text()[normalize-space()][1]");

        if (empty($city)) {
            $city = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[2]/descendant::text()[{$this->eq($this->t('City:'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($city)) {
            $city = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here\'s a summary:'))}]/following::text()[{$this->eq($this->t('City:'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($address) && !empty($city)) {
            $h->hotel()
                ->address($city . ', ' . $address);
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Tel.:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([+\d\(\)\s]+)\s*$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Here\'s a summary:'))}]/following::text()[{$this->eq($this->t('Tel:'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $fax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel:'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Fax.:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([+\d\(\)\s]+)\s*$/");

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Arrival:'))}]")->length > 0) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4})$/");

            if (!empty($checkIn)) {
                $h->booked()->checkIn(strtotime($checkIn));
            }

            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\s*\w+\s*\d{4})$/");

            if (!empty($checkOut)) {
                $h->booked()->checkOut(strtotime($checkOut));
            }
        } elseif ($this->http->XPath->query("//tr[{$this->starts($this->t('Room type:'))}]/descendant::td[4][{$this->eq($this->t('Period'))}]")->length > 0
                && $this->headerStatus == true) {
            $periodText = $this->http->FindNodes("//tr[{$this->starts($this->t('Room type:'))}]/descendant::td[4][{$this->eq($this->t('Period'))}]/ancestor::tr[1]/following-sibling::tr/td[4][{$this->contains($this->t('night'))}]");

            if (preg_match("/^(\d+\s*\w+)\s*\-/u", $periodText[0], $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]));
            }

            if (preg_match("/^(\d+\s*\w+)\s*\-\s*(\d+)\s*\w+/u", array_pop($periodText), $m)) {
                $h->booked()
                    ->checkOut(strtotime($m[2] . ' days', $this->normalizeDate($m[1])));
            }
        }

        if (empty($guestsInfo)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('persons:'))}]", null, true, "/^{$this->opt($this->t('persons:'))}\s*(\d+)$/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }
        }

        $this->detectDeadLine($h);

        $priceText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount :'))}]/ancestor::tr[1]");

        if (preg_match("/{$this->opt($this->t('Total amount :'))}\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\(*.*$/", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total additional services')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total additional services'))}\s*([\d\.\,]+)/");

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Rooms'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Rooms'))}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
        }

        $roomNodes = $this->http->XPath->query("//tr[{$this->starts($this->t('Room type:'))}]/descendant::td[5][{$this->eq($this->t('Price'))}]/ancestor::tr[1]/following-sibling::tr/td[5]");

        foreach ($roomNodes as $roomRoot) {
            $room = $h->addRoom();

            $room->setRate($this->http->FindSingleNode(".", $roomRoot) . ' / night')
                ->setType($this->http->FindSingleNode("./preceding::td[4]", $roomRoot));
        }

        if (count($roomNodes) > 0) {
            $h->booked()
                ->rooms(count($roomNodes));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->assignLang();

        if ($this->detectEmailByHeaders($parser->getHeaders()) == true) {
            $this->headerStatus = true;
        }

        $this->reSubject = $parser->getSubject();

        $this->ParseHotel($email);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (!empty($cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Free cancellation until:'))}]"))) {
            $cancellationText = $cancellation;
        }

        if (preg_match("/^{$this->opt($this->t('Free cancellation until:'))}\s*(\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\:\d+$/",
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match('/^(\d+\.\d+\.\d{4}\s*[\d\:]+)$/',
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match('/You can cancel your reservation without any cost until ([\d\.]+\s*A?P?M) \(Rome local time\) of the day before your arrival/u',
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        }

        if (preg_match('/Le cancellazioni o le modifiche effettuate fino a (\d+) giorni prima della data prevista di arrivo non comportano alcun costo/u',
            $cancellationText, $m)
        || preg_match('/In case of cancellation or modification prior to (\d+) days before arrival date, no penalty will be charged/u',
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            //26 Jul
            "#^(\d+\s*\w+)$#iu",
        ];
        $out = [
            "$1 $year",
        ];
        $str = preg_replace($in, $out, $str);

        $this->logger->debug($str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        return strtotime($str);
    }
}
