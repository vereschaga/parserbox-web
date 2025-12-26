<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourUpcomingStay extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-177642913.eml, hhonors/it-79593408.eml, hhonors/it-79595569.eml, hhonors/it-881538859.eml";

    private $detectFrom = ['.hilton.com'];
    private $detectSubject = [
        // en
        'Your upcoming ',
        // zh
        '您即將在',
        '您即将在',
        // de
        'Ihr bevorstehendes ',
        // es
        'Su próximo ',
        // pt
        'Seu próximo ',
        // ja
        'ご滞在について',
        // fr
        'Votre prochain ',
        // pl
        'Twoja nadchodząca',
        // it
        'Il tuo prossimo',
    ];

    private static $dictionary = [
        'en' => [
            'view booking details' => ['view booking details', 'Check In Now', 'Download the App', 'Add to Calendar'],
            //            'unsubscribe' => '',
            'Add to Calendar' => 'Add to Calendar',
        ],
        'zh' => [
            'view booking details' => ['查看預訂詳情', '查看预订详情'],
            'unsubscribe'          => ['點選此處停止訂閱。', '请点击此处取消订阅。'],
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'de' => [
            'view booking details' => 'Buchungsdetails anzeigen',
            'unsubscribe'          => 'Klicken Sie hier, um sich abzumelden',
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'es' => [
            'view booking details' => 'Ver detalles de la reserva',
            'unsubscribe'          => 'Haga clic aquí para cancelar suscripción.',
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'pt' => [
            'view booking details' => 'Exibir detalhes da reserva',
            'unsubscribe'          => 'Clique aqui para cancelar o recebimento',
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'ja' => [
            'view booking details' => '予約内容を表示する',
            'unsubscribe'          => '配信停止をご希望の場合は、こちらをクリックしてください。',
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'fr' => [
            'view booking details' => 'Voir les détails de la réservation',
            'unsubscribe'          => 'désabonner',
            'Add to Calendar'      => 'Ajouter au calendrier',
            'Guests:'              => 'Invités:',
            'Adults'               => 'Adulte',
            'Room plan:'           => 'Plan de la chambre:',
            'Rate per night'       => 'Tarif par nuit',
            'CostText'             => 'Total du séjour par tarif de chambre',
            'TaxText'              => 'Impôts',
            'Total'                => 'Total du séjour',
            'Guest Name:'          => 'Nom de l\'invité:',
        ],
        'pl' => [
            'view booking details' => 'Zobacz szczegóły rezerwacji',
            'unsubscribe'          => 'Kliknij tutaj, aby zrezygnować z subskrypcji.',
            'Add to Calendar'      => 'Add to Calendar',
        ],
        'it' => [
            'view booking details' => 'Visualizza i dettagli della prenotazione',
            'unsubscribe'          => 'Fai clic qui',
            'Add to Calendar'      => 'Add to Calendar',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmail($email);

        $this->parseStatement($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->strContains($from, $this->detectFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if ($this->strContains($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        if ($this->strContains($headers['subject'], $this->detectSubject) === true) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['.hilton.com/', 'www.hilton.com', 'h1.hilton.com', 'h4.hilton.com', 'h6.hilton.com'], '@href') . "]")->length < 4) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $traveller = implode(' ', [$this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_FNAME', 'mi_fname']) . ")[1]"), "/\Wmi_FNAME=(.*?)(?:&|$)/i"),
            $this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_LNAME', 'mi_lname']) . ")[1]"), "/\Wmi_LNAME=(.*?)(?:&|$)/i"), ]);

        if (empty(trim($traveller))) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Guest Name:'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        $h->general()
            ->traveller($traveller);

        $confirmatrionNumber = $this->reL($this->http->FindSingleNode("(" . $this->getLink(['confirmationNumber']) . ")[1]"), "/\WconfirmationNumber=(\d{5,})(?:&|$)/");

        if (empty($confirmatrionNumber)) {
            $confirmatrionNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation #')]", null, true, "/{$this->opt($this->t('Confirmation #'))}\s*(\d{5,})/");
        }

        if (!empty($confirmatrionNumber)) {
            $h->general()
                ->confirmation($confirmatrionNumber);
        } else {
            $h->general()
                ->noConfirmation();
        }

        //  	Homewood Suites by Hilton Austin - Arboretum/NW
//        10925 Stonelake Boulevard Austin TX 78759, USA
        //  	contact us 	  	+1 5123499966

        //hotel_name=Homewood%20Suites%20by%20Hilton%20Austin%20-%20Arboretum/NW
        //&hotel_address_line_1=10925%20Stonelake%20Boulevard
        //&hotel_city=Austin
        //&hotel_state=TX
        //&hotel_postal_code=78759
        //&hotel_country=USA

        // Hotel
        // img "add to calendar"
        $name = $this->reL($this->http->FindSingleNode("(" . $this->getLink('hotel_name') . ")[1]"), "/\Whotel_name=(.*?)(?:&|$)/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//img[contains(@src, 'Location_Icon')]/preceding::a[normalize-space()][1]");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//img[contains(@alt, 'Plans et directions')]/preceding::a[normalize-space()][1]");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation #')]/following::img[1]/ancestor::tr[1]");
        }

        $address = '';
        $addressUrl = urldecode($this->http->FindSingleNode("(" . $this->getLink('hotel_address_line_1') . ")[1]"));

        $addressParts = [];

        if (preg_match("/(?:[?]|&)hotel_address_line_1=(.*?)(?:&|$)/i", $addressUrl, $m)) {
            $addressParts[] = $m[1];
        }

        if (preg_match("/(?:[?]|&)hotel_city=(.*?)(?:&|$)/i", $addressUrl, $m)) {
            $addressParts[] = $m[1];
        }

        if (preg_match("/(?:[?]|&)hotel_state=(.*?)(?:&|$)/i", $addressUrl, $m)) {
            $addressParts[] = $m[1];
        }

        if (preg_match("/(?:[?]|&)hotel_postal_code=(.*?)(?:&|$)/i", $addressUrl, $m)) {
            $addressParts[] = $m[1];
        }

        if (preg_match("/(?:[?]|&)hotel_country=(.*?)(?:&|$)/i", $addressUrl, $m)) {
            $addressParts[] = $m[1];
        }

        $addressParts = array_filter($addressParts);

        if (count($addressParts) > 0) {
            $address = implode(', ', $addressParts);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@src, 'Location_Icon')]/following::td[string-length()>5][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation #')]/following::img[2]/ancestor::tr[1]");
        }

        $phone = $this->http->FindSingleNode("//img[" . $this->contains('phone_icon.png', '@src') . "]/following::text()[normalize-space()][1]", null, true, "/^[ \d\+\-\(\)]{5,}$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation #')]/following::img[3]/ancestor::tr[1]", null, true, "/^[ \d\+\-\(\)]{5,}$/");
        }

        $h->hotel()
            ->name($name)
            ->address($address)
            ->phone($phone, true, true);

        // Booked
        $checkInDate = $this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_check_in', 'mi_check_in_date', 'check_out_date']) . ")[1]"), "/\Wmi_check_in(?:_date)?=(.+?)(?:%20|&|$)/");
        $checkInTime = $this->reL($this->http->FindSingleNode("(" . $this->getLink('mi_check_in_t') . ")[1]"), "/\Wmi_check_in_t=(.*?)(?:&|$)/");

        if (empty($checkInTime)) {
            $checkInTimeA = array_filter($this->resL($this->http->FindNodes($this->getLink(['mi_check_in', 'mi_check_in_date'])), "/\Wmi_check_in(?:_date)?=[^&]+?(?:%20| )(\d{2}:\d{2}):\d{2}/"));

            if (!empty($checkInTimeA)) {
                $checkInTime = array_shift($checkInTimeA);
            }
        }

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[contains(normalize-space(), ':')][1]", null, true, "/^([\d\:]+\s*a?p?m?)$/i");
        }

        if (!empty($checkInDate) && !empty($checkInTime)) {
            $h->booked()
                ->checkIn($this->normalizeDate($checkInDate . ', ' . $checkInTime));
        } elseif (!empty($checkInDate) && empty($checkInTime)) {
            $h->booked()
                ->checkIn($this->normalizeDate($checkInDate));
        }

        $checkOutDate = $this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_check_out', 'mi_check_out_date']) . ")[1]"), "/\Wmi_check_out(?:_date)?=(.+?)(?:%20|&|$)/");

        $checkOutTime = $this->reL($this->http->FindSingleNode("(" . $this->getLink('mi_check_out_t') . ")[1]"), "/\Wmi_check_out_t=(.*?)(?:&|$)/");

        if (empty($checkOutTime)) {
            $checkOutTimeA = array_filter($this->resL($this->http->FindNodes($this->getLink(['mi_check_out', 'mi_check_out_date'])), "/\Wmi_check_out(?:_date)?=[^&]+?(?:%20| )(\d{2}:\d{2}):\d{2}/"));

            if (!empty($checkOutTimeA)) {
                $checkOutTime = array_shift($checkOutTimeA);
            }
        }

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[contains(normalize-space(), ':')][2]", null, true, "/^([\d\:]+\s*a?p?m?)$/i");
        }

        $h->booked()
            ->checkOut($this->normalizeDate($checkOutDate . ', ' . $checkOutTime));

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<guests>\d+)\s+{$this->opt($this->t('Adults'))}/", $guests, $m)) {
            $h->setGuestCount($m['guests']);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room plan:'))}]/ancestor::tr[1]/descendant::td[2]");
        $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate per night'))}]/ancestor::table[1]", null, true, "/{$this->opt($this->t('Rate per night'))}\s+(.+[A-Z]{3})\s+{$this->opt($this->t('CostText'))}/");

        if (!empty($rate) || !empty($roomType)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})/", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CostText'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,\']+)\s+/");

            if ($cost !== null) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TaxText'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,\']+)\s+/");

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        return true;
    }

    private function parseStatement(Email $email)
    {
        $number = $this->http->FindSingleNode("(" . $this->getLink('mi_num=') . ")[1]", null, true, "/\Wmi_num=(\d{5,})(?:&|$)/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("(" . $this->getLink('mi_u=') . ")[1]", null, true, "/\Wmi_u=(\d{5,})(?:&|$)/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//a[" . $this->eq($this->t("unsubscribe")) . "]/@href", null, true,
                "/&x=[^%]+@[^%]+\.[^%]+%7c[^%]+%7c[^%]+%7c(\d{5,})$/");
        }

        if (empty($number)) {
            //safelinks.protection.outlook.com
            $number = $this->http->FindSingleNode("//a[" . $this->eq($this->t("unsubscribe")) . "]/@href", null, true,
                "/%26x%3D[^%]+%40[^%]+\.[^%]+%257c[^%]+%257c[^%]+%257c(\d{5,})&data=/");
        }

        if (!empty($number)) {
            $st = $email->add()->statement();

            $st
                ->setNumber($number)
                ->setLogin($number)
                ->setNoBalance(true)
            ;

            $traveller = implode(' ', [$this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_FNAME', 'mi_fname']) . ")[1]"), "/\Wmi_FNAME=(.*?)(?:&|$)/i"),
                $this->reL($this->http->FindSingleNode("(" . $this->getLink(['mi_LNAME', 'mi_lname']) . ")[1]"), "/\Wmi_LNAME=(.*?)(?:&|$)/i"), ]);

            if (empty(trim($traveller))) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Guest Name:'))}]/ancestor::tr[1]/descendant::td[2]");
            }

            $st->addProperty("Name", $traveller);

            $status = $this->http->FindSingleNode("(" . $this->getLink('mi_tier') . ")[1]", null, true, "/\Wmi_tier=([A-Z]+)(?:&|$)/");

            if (empty($status)) {
                $status = $this->http->FindSingleNode("(" . $this->getLink('mi_brand_code') . ")[1]", null, true, "/\Wmi_brand_code=([A-Z]+)(?:&|$)/");
            }

            switch ($status) {
                case 'D':
                    $st->addProperty('Status', 'Diamond');

                    break;

                case 'CI':
                    $st->addProperty('Status', 'Diamond');

                    break;

                case 'G':
                    $st->addProperty('Status', 'Gold');

                    break;

                case 'S':
                    $st->addProperty('Status', 'Silver');

                    break;

                case 'B':
                    $st->addProperty('Status', 'Member');

                    break;
            }
        }
    }

    private function getLink($containedText)
    {
        return "//*[self::a or self::img][" . $this->contains($containedText, '@href') . " or " . $this->contains($containedText, '@src') . "]/attribute::*[name() = 'src' or name()='href']";
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['view booking details'])) {
                continue;
            }

            if ($this->http->XPath->query("//a[{$this->eq($phrases['view booking details'])}] | //img[{$this->eq($phrases['view booking details'], '@alt')}]")->length > 0
                || $this->http->XPath->query("//a[{$this->eq($phrases['Add to Calendar'])}] | //img[{$this->eq($phrases['Add to Calendar'], '@alt')}]")->length > 0
                || $this->http->FindSingleNode("//node()[{$this->contains($phrases['view booking details'])}]", null, true, "/<a .+>.*{$this->opt($phrases['view booking details'])}.*<\/a>/") !== null
            ) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->warning($str);
        $in = [
            // 2021-02-26, 00:00
            "#^\s*(\d{4})-(\d{2})-(\d{2}), (\d{2}:\d{2})\s*$#i",
            //2022-07-21 00:00:00.000, 00:00
            "#^(\d{4})\-(\d+)\-(\d+)\s*[\d\:\.]+\,\s*([\d\:]+)$#",
        ];
        $out = [
            "$3.$2.$1, $4",
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
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

    private function reL($str, $re, $c = 1)
    {
        preg_match($re, urldecode($str), $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function resL($array, $re, $c = 1)
    {
        $result = [];

        foreach ($array as $str) {
            preg_match($re, urldecode($str), $m);
            $result[] = $m[$c] ?? null;
        }

        return $result;
    }

    private function strContains(?string $text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
