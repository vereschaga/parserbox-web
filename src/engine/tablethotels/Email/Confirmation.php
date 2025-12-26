<?php

namespace AwardWallet\Engine\tablethotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "tablethotels/it-143891762.eml, tablethotels/it-148960147.eml, tablethotels/it-3937994.eml, tablethotels/it-41673817.eml, tablethotels/it-43003793.eml, tablethotels/it-799245892.eml";

    public $reFrom = "customerservice@tablethotels.com";
    public $reSubject = [
        "en" => "Reservation Confirmation for",
    ];

    public static $dictionary = [
        "en" => [
            'confirmation'                 => ['CONFIRMATION NUMBER', 'Confirmation number', 'Confirmation number:', 'CONFIRMATION NUMBER:'],
            'traveller'                    => ['GUEST :', 'Guest :'],
            'cancellation'                 => ['CANCELLATION', 'Cancellation'],
            'hotelName'                    => ['HOTEL:', 'Hotel:'],
            'hotelInfo'                    => ['HOTEL INFO', 'Hotel Info'],
            'guests'                       => ['TOTAL GUESTS:', 'Total Guests:'],
            'dates'                        => ['DATES:', 'Dates:'],
            'tax'                          => ['TAXES & FEES', 'Taxes & Fees'],
            'total'                        => ['TOTAL', 'Total'],
            'You will be paying in'        => ['You will be paying in'],
            'roomType'                     => ['ROOMS', 'Rooms'],
            'Reservation Cancelled'        => ['Reservation Cancelled', 'Your reservation was cancelled on'],
        ],

        "fr" => [
            'confirmation'           => ['Numéro de confirmation:'],
            'traveller'              => ['Client :'],
            'kids'                   => ['Enfants'],
            'cancellation'           => ["Annulation sans frais en cas d’annulation avant"],
            'cancellation-starts'    => ["Veuillez noter que le montant total de la réservation"],
            'hotelName'              => ['Hôtel:'],
            'hotelInfo'              => ['Hôtel'],
            'guests'                 => ['Nombre Total de Clients:'],
            'dates'                  => ['Dates:'],
            'tax'                    => ['Taxes & Frais'],
            'total'                  => ['Total'],
            'You will be paying in'  => ["Payable à l'hôtel lors du check-out"],
            //'This room features' => [""],
            'roomType' => ['TYPE DE CHAMBRE', 'Type de Chambre'],
            //'Reservation Cancelled' => '',
        ],

        "pt" => [
            'confirmation'          => ['Número de confirmação:'],
            'traveller'             => ['Hóspede :'],
            'kids'                  => ['Crianças'],
            'cancellation'          => ["Não haverá cobrança de multa se o cancelamento for solicitado até as"],
            'hotelName'             => ['Hotel:'],
            'hotelInfo'             => ['Hotel'],
            'guests'                => ['Total de hóspedes::'],
            'dates'                 => ['Datas:'],
            'tax'                   => ['Impostos e taxas'],
            'total'                 => ['Total'],
            //'You will be paying in' => [""],
            //'This room features' => [""],
            'roomType' => ['TIPO DE APARTAMENTO', 'Tipo de Apartamento'],
            //'Reservation Cancelled' => '',
        ],
    ];

    public $lang = "en";

    public $detectLang = [
        'en' => ['Thank you for booking', 'All room charges', 'Your reservation was cancelled on'],
        'fr' => ["Merci d'avoir réservé avec"],
        'pt' => ["Número de confirmação"],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains('Tablet')}]")->length === 0
            && $this->http->XPath->query("//a[{$this->contains('.tablethotels.com/', '@href')}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $this->assignLang();

        $this->parseHtml($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        $total = $this->getField('Total Price:');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $total, $matches)
        ) {
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        return $email;
    }

    private function parseHtml(Email $email): void
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($confNo = $this->getField("confirmation"))
            ->traveller($this->getField("traveller"));

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Cancelled'))}]")->length > 0) {
            $r->general()
                ->cancelled();
        }

        $cancellation = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('cancellation'))}]/ancestor::td[1]/descendant::text()[normalize-space()!='']"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Annulation sans frais en cas d’annulation avant')]/ancestor::p[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Não haverá cobrança de multa se o cancelamento for solicitado até as')]/ancestor::p[1]");
        }

        if (preg_match("#^{$this->opt($this->t('cancellation'))}\s+(.+?)\-[ ]*\n{$confNo}#s", $cancellation, $m)
         || preg_match("#^{$this->opt($this->t('cancellation'))}\s+(.+?)\.#s", $cancellation, $m)) {
            $cancellation = str_replace("\n", '; ', $m[1]);
        } else {
            $cancellation = $this->getField("cancellation");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('cancellation-starts'))}]");
        }

        $r->general()
            ->cancellation($cancellation);

        $address = implode(" ",
            array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('hotelInfo'))}]/ancestor::h5[1]/following-sibling::*[1]/descendant::text()[string-length(normalize-space(.))>1][not(position()=1) and not(position()=last())]")));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Hotel:']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]");
        }

        $r->hotel()
            ->name($this->getField("hotelName"))
            ->address($address);

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('hotelInfo'))}]/ancestor::h5[1]/following-sibling::*[1]/descendant::text()[string-length(normalize-space(.))>1][last()]");

        if (!empty($phone)) {
            $r->hotel()
                ->phone($phone);
        }

        $maxGuests = $this->http->FindSingleNode("//text()[contains(., 'Occupancy:')]", null, true, "#Occupancy:\s*(\d+)\s+guests#");
        $node = $this->getField('guests');

        if (preg_match("#(\d+)\s+(?:ADULT|ADULTS|Adults|Adultes|Adultos),\s+(\d+)\s+(?:CHILDREN|Child|Enfants|Crianças)#i", $node, $m)) {
            $r->booked()
                ->guests($m[1])
                ->kids($m[2]);
        } elseif (!empty($maxGuests)) {
            $r->booked()
                ->guests($maxGuests);
        }
        $r->booked()
            ->checkIn($this->normalizeDate($this->re("#(.*?)\s+-#", $this->getField("dates"))))
            ->checkOut($this->normalizeDate($this->re("#-\s+(.+)#", $this->getField("dates"))));

        $roomType = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'This room features')]", null, true,
            "#This room features\s+(.*?)\s+and is approximately#");

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('roomType'))}]/following::text()[normalize-space()][1]");
        }

        if (!empty($roomType)) {
            $room = $r->addRoom();
            $room->setType(trim($roomType, '-'));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if ($totalPrice === null
            && preg_match("/ in (?<currency>[A-Z]{3})\s*\(\D*(?<amount>\d[,.\'\d ]*)\D*\)\./", $this->http->FindSingleNode("//text()[{$this->contains($this->t('You will be paying in'))}]"), $m)
        ) {
            $totalPrice = $m['currency'] . ' ' . $m['amount'];
        }

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $totalPrice, $matches)
        ) {
            // $1,768    |    R$1.914    |    34,09 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->http->FindSingleNode("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('tax'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $taxes, $m)
            ) {
                $r->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $this->detectDeadLine($r);
    }

    private function getField($field): ?string
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($this->t($field))}]/following::text()[normalize-space(.)!=''][1]");
    }

    private function assignLang(): bool
    {
        /*if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confirmation']) || empty($phrases['hotelName']) || empty($phrases['dates'])) {
                continue;
            }

            $this->logger->error("//node()[{$this->contains($phrases['confirmation'])}]");
            $this->logger->error("//node()[{$this->contains($phrases['hotelName'])}]");
            $this->logger->error("//node()[{$this->contains($phrases['dates'])}]");

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confirmation'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['hotelName'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['dates'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;*/

        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//node()[{$this->contains($word)}]")->length > 0) {
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
            return null;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field))
            . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s*[ap]m)$#i",
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",
        ];

        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'BRL' => ['R$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'INR' => ['₹'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/ Cancellations or changes made after (?<time>\d+:\d+\s*[ap]m) \(.+\) on (?<date>.+? \d{4}) are subject to a \d+ Night/ui",
            $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));

            return;
        }

        if (preg_match("/Free Cancellation by (?<date>.+)\;/ui", $cancellationText, $m)
            || preg_match("/Free Cancellation by (?<date>.+)$/ui", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m['date']));

            return;
        }

        if (preg_match("/([\d\:]+) heure locale (\d+) jours avant l’arrivée$/ui", $cancellationText, $m)
        || preg_match("/([\d\:]+) \(horário local do hotel\) e a (\d+) dias da data de chegada$/ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[2] . ' day', $m[1]);

            return;
        }

        $h->booked()
            ->parseNonRefundable("#^If cancelled .+? will charge a cancellation#u")
            ->parseNonRefundable("#non-refundable#i")
            ->parseNonRefundable("#Veuillez noter que le montant total de la réservation sera débité en cas d'annulation, de modification ou de non-présentation#i");
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
}
