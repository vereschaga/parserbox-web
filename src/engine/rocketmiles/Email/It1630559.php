<?php

namespace AwardWallet\Engine\rocketmiles\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It1630559 extends \TAccountChecker
{
    public $mailFiles = "rocketmiles/it-100492968.eml, rocketmiles/it-1630559.eml, rocketmiles/it-1782404.eml, rocketmiles/it-1797945.eml, rocketmiles/it-1798658.eml, rocketmiles/it-1886680.eml, rocketmiles/it-1924397.eml, rocketmiles/it-1924398.eml, rocketmiles/it-2008753.eml, rocketmiles/it-2009522.eml, rocketmiles/it-2620550.eml, rocketmiles/it-57454409.eml, rocketmiles/it-6662686.eml, rocketmiles/it-77221039.eml";
    public $lang = '';
    public static $dict = [
        'en' => [
            //            "has been " => "",
            //            "cancelled" => "",
            //            "we have CANCELLED the reservation" => "",
            "Confirmation Code"          => ["Reservation Number", "Booking Number", "Confirmation Code", "confirmation code", "Rocket Travel Confirmation Number", "Booking number"],
            "Guest Name"                 => ["Guest Name", "Guest name"],
            "Check In"                   => ["Check In", "Check in", "Check-in"],
            "Check Out"                  => ["Check Out", "Check out", "Check-out"],
            "Number of Guests"           => ["Number of Guests", "Number of guests", "Number of adults"],
            "Cancellation Policy"        => ["Cancellation Policy", "Cancellation policy"],
            "Room Type"                  => ["Room Type", "Room description", "Room type"],
            "Grand Total"                => ["Grand Total", "Total charges", "Total refunded", "Total Charges", "Total paid", "Grand total"],
            "Taxes & Fees"               => ["Taxes & Fees", "Taxes and fees", "Taxes"],
            'Manage Reservation'         => ['Manage Reservation'],
            'View Reservation'           => ['View Reservation'],
            'contact the hotel directly' => ["contact the hotel directly", "contact them directly", "reservation, contact us at", "hotel directly at"],
            'Account number'             => ["Account number", "Account Number"],
            "Total Miles Earned"         => ["Total Miles Earned", "Points Earned"],
            "Total Miles Deducted"       => ["Total Miles Deducted", "Total Skywards Miles Deducted", "Total Points Deducted"],
            //            "Total Miles Deducted" => "",
        ],
        'es' => [
            "has been "                         => ["ha sido ", "Se Ha "],
            "cancelled"                         => "Cancelado",
            "we have CANCELLED the reservation" => ["hemos CANCELADO tu reserva"],
            "Confirmation Code"                 => ["Código de confirmación", "Código de Confirmación"],
            "Guest Name"                        => ["Nombre del cliente"],
            "Check In"                          => ["Entrada"],
            "Check Out"                         => ["Salida"],
            "Number of Guests"                  => ["Número de huéspedes"],
            "Cancellation Policy"               => ["Condiciones de cancelación"],
            "Room Type"                         => ["Tipo de habitación"],
            "Grand Total"                       => ["Importe total cargado"],
            //"Taxes & Fees" => [],
            //'Manage Reservation' => [],
            //'View Reservation' => [],
            'contact the hotel directly' => ["directamente con el hotel en el"],
            //'Account number' => [],
            "Total Miles Earned"                          => "Total de Millas descontados",
            "Total Miles Deducted"                        => "Total de Puntos descontados",
            "Remaining balance (after hotel reservation)" => "Saldo restante (después de la reserva de hotel)",
        ],
        'pt' => [
            "has been " => "foi ",
            //            "cancelled" => "",
            //            "we have CANCELLED the reservation" => "",
            "Confirmation Code"   => ["Código de confirmação", "Código de Confirmação de Reserva de Hotel", "Número da reserva", "Número de confirmação Rocket Travel"],
            "Guest Name"          => ["Nome do hóspede", "Nome do hóspede"],
            "Check In"            => ["Check In", "Fecha de entrada"],
            "Check Out"           => ["Check Out", "Fecha de salida"],
            "Number of Guests"    => ["Número de hóspedes"],
            "Cancellation Policy" => ["Política de cancelamento"],
            "Room Type"           => ["Tipo de quarto"],
            "Grand Total"         => ["Valor Total", "Valor total cobrado", "Total pago"],
            "Taxes & Fees"        => ["Taxas"],
            'Manage Reservation'  => ['Administre sua reseva'],
            //            'View Reservation' => [''],
            'contact the hotel directly' => ["contato com o hotel diretamente em"],
            'Account number'             => ["Account number", "Account Number"],
            "Total Miles Earned"         => "Total de Milhas Ganhas",
            "Total Miles Deducted"       => "Total de Milhas deduzidos",
        ],
    ];

    private static $providers = [
        'aeromexico'    => '@clubpremier.com',
        'rapidrewards'  => '@southwesthotels.com',
        'hotels'        => '@hotelstorm.com',
        'alaskaair'     => '@hotels.alaskaair.com',
        'kayak'         => 'hotels@opentable.kayak.com',
        'aa'            => '@bookaahotels.com',
        'lanpass'       => 'latampass@rocketmiles.com',
        'golair'        => ['smiles@rocketmiles.com', '@smiles.com.br', 'www.smiles.com.br'],
        'aviancataca'   => ['LifeMileshotels@rocketmiles.com'],
        'skywards'      => ['emiratesskywardshotels@rocketmiles.com'],
        //        'marriott'      => ['rockettravelhotels.com'],
        'rocketravel'   => ['@rockettravelhotels.com'],
        'rocketmiles'   => ['@rocketmiles.com', 'Rocket Travel'], // always last
    ];

    private $body = [
        'en' => ['Your reservation at', 'Your reservation has been', 'Your Booking Confirmation', 'We’ve cancelled your booking'],
        'es' => ['Su Confirmación del Hotel', 'Se Ha Cancelado Tu Reserva'],
        'pt' => ['Sua confirmação do hotel', 'A sua reserva'],
    ];
    private $subject = [
        // en
        ' Reservation Confirmation, ',
        // es
        ' confirmación de la reserva, ',
        ' cancelación de la reserva, ',
        // pt
        ' confirmação da reserva, ',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $this->assignLang();
        $email->setType($class . ucfirst($this->lang));
        $email->setProviderCode($this->getProviderCode($parser->getCleanFrom() . "\n" . $parser->getHTMLBody()));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->arripos($parser->getHTMLBody(), self::$providers) !== false && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && isset($headers["subject"])
            && $this->arripos($headers["from"], self::$providers) !== false && $this->arripos($headers["subject"], $this->subject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arripos($from, 'rocketmiles.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 3;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        // it-2009522.eml
        $hotelRoots = $this->http->XPath->query("descendant::div[ descendant::td[div[{$this->starts($this->t("Check In"))}] and div[{$this->starts($this->t("Check Out"))}]] ]");
        $rootMain = $hotelRoots->length > 0 ? $hotelRoots->item(0) : null;

        // Travel agency
        $conf = $this->nextNode($this->t("Confirmation Code"), $rootMain, "/^\s*[A-Z\d]{5,}\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Confirmation Code"))}]/following::text()[normalize-space()][1]", $rootMain, true, "/^\s*[A-Z\d]{5,}\s*$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("we have CANCELLED the reservation")) . "])[1]/ancestor::*[1]",
                null, true, "/" . $this->opt($this->t('we have CANCELLED the reservation')) . " ([A-Z\d]{5,}) /");
            $h->general()->cancelled();
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('We’ve cancelled your booking'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $email->ota()
            ->confirmation($conf);

        // Hotel
        $hotelName = $address = null;
        $xpathHotel = "//text()[{$this->starts($this->t('Guest Name'))}]/preceding::tr[ count(*)=2 and *[1][normalize-space()='' and descendant::img] ]/*[2]/descendant-or-self::*[ tr[normalize-space()][2] ]";

        if ($this->http->XPath->query($xpathHotel)->length === 1) {
            $this->logger->debug('Found hotelName & address format: 1');
            $hotelName = $this->http->FindSingleNode($xpathHotel . "/tr[normalize-space()][1]");
            $address = $this->http->FindSingleNode($xpathHotel . "/tr[normalize-space()][2]");
        } elseif ($this->http->XPath->query("//a[contains(.//img/@src,'view_res_red.png') and contains(@href,'.rocketmiles.com')]")->length > 0) {
            // it-1630559.eml
            $this->logger->debug('Found hotelName & address format: 2');
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "][1]/ancestor::tr[1]/preceding::tr[.//strong][1]");
            $address = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]/ancestor::tr[1]/preceding::tr[.//strong][1]/following-sibling::tr[1]");
        } elseif ($this->http->XPath->query("//a[" . $this->eq($this->t("Manage Reservation")) . "]")->length > 0) {
            $hotelName = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Manage Reservation")) . " and preceding-sibling::td[2]//img][1]/preceding-sibling::td[1]//tr[1]");
            $address = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Manage Reservation")) . " and preceding-sibling::td[2]//img][1]/preceding-sibling::td[1]//tr[2]");
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Your reservation at'))}]/ancestor::tr[1]")->length > 0) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Your reservation at']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your reservation at'))}\s+(.+)\s+{$this->opt($this->t('has been'))}/");
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Guest name']/preceding::text()[{$this->eq($hotelName)}][1]/following::text()[normalize-space()][1]");
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('As requested, we have cancelled your upcoming hotel reservation at'))}]/ancestor::tr[1]")->length > 0) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='As requested, we have cancelled your upcoming hotel reservation at']/following::text()[normalize-space()][1]", null, true, "/^(.+)\./");
        }

        if (empty($address) && $h->getCancelled() === true) {
            $h->hotel()
                ->noAddress();
        } else {
            $h->hotel()
                ->address($address);
        }

        $h->hotel()
            ->name($hotelName)
            ->phone($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("contact the hotel directly"))}]", $rootMain, true, "#{$this->opt($this->t("contact the hotel directly"))}\s*([+(\d][- \d)(]{5,}[\d)])(?:[,.]|$)#"), false, true);

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->nextNode($this->t("Guest Name")), true)
        ;
        $status = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("has been ")) . "])[1]", null, true, "#" . $this->opt($this->t("has been ")) . "(\w+)\s*(?:\.|$)#u");

        if (!empty($status)) {
            $h->general()->status($status);
        }

        if (!empty($h->getStatus()) && preg_match("/^\s*" . $this->opt($this->t("cancelled")) . "\s*$/i", $h->getStatus())) {
            $h->general()->cancelled();
        }
        // Program
        $account = $this->nextNode($this->t("Account number"), null, "#^\s*[A-Z\d]{5,}\s*$#");

        if (!empty($account)) {
            $email->ota()
                ->account($account, false);
        }

        // Price
        $total = $this->nextNode($this->t("Taxes & Fees"), null, "#.*\d+.*#");

        if (!empty($total)) {
            $h->price()
                ->tax($this->amount($total))
                ->currency($this->currency($total));
        }

        $total = $this->nextNode($this->t("Grand Total"), null, "#.*\d+.*#");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Grand Total")) . "]/ancestor::td[2]/following-sibling::td[not(.//td)][1]");
        }

        if (!empty($total)) {
            $h->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        if ($miles = $this->nextNode($this->t("Total Miles Deducted"))) {
            $h->price()->spentAwards($miles);
        } else {
            $miles = $this->http->FindSingleNode("//*[" . $this->starts($this->t("Total Miles Deducted")) . "]/following::td[2]/span", null, true, '/.*\d+[,.\d]+.*/');

            if (!empty($miles)) {
                $h->price()->spentAwards($miles);
            }
        }

        if ($miles = $this->nextNode($this->t("Total Miles Earned"))) {
            $email->ota()->earnedAwards($miles);
        } else {
            $miles = $this->http->FindSingleNode("//*[" . $this->starts($this->t("Total Miles Earned")) . "]/following::td[2]/span", null, true, '/.*\d+[,.\d]+.*/');

            if (empty($miles)) {
                $miles = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation earns you a total of'))}]", null, true, "/{$this->opt($this->t('Your reservation earns you a total of'))}\s*([\d\.\,\']+.*)/");
            }

            if (!empty($miles)) {
                $email->ota()->earnedAwards($miles);
            }
        }

        if ($miles = $this->nextNode($this->t("Miles Earned"))) {
            $milesValues = explode('+', preg_replace('/[^\d.,+]/', '', str_replace(',', '', $miles)));
            $email->ota()->earnedAwards(array_sum($milesValues) . ' ' . preg_replace('/\d[ \d.,+]+/', '', $miles));
        } else {
            $points = $this->http->FindSingleNode("(//*[" . $this->starts($this->t("Points Earned")) . "]/ancestor::*[1]/following-sibling::*[1])[2]");

            if (!empty($points)) {
                $email->ota()->earnedAwards($points);
            }
        }

        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        // Booked
        $h->booked()
            ->checkIn2($this->normalizeDate($this->nextNode($this->t("Check In"))))
            ->checkOut2($this->normalizeDate($this->nextNode($this->t("Check Out"))))
            ->guests($this->nextNode($this->t("Number of Guests")), true, true);

        $kids = $this->nextNode($this->t("Number of Guests"));

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        // cancellation
        $cancel = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("Cancellation Policy")) . "])[1]", null, true, "#" . $this->opt($this->t("Cancellation Policy")) . "\W*(.+)#");

        if (empty($cancel)) {
            $cancel = $this->http->FindSingleNode("(//*[self::div or self::p][" . $this->starts($this->t("Cancellation Policy")) . "]/following-sibling::div[1])[1]");
        }

        if (!empty($cancel)) {
            $h->general()->cancellation($cancel);
        }

        // deadline
        if (!empty($cancel) && !empty($h->getCheckInDate())) {
            if (preg_match("/^This booking will be 100% refundable if cancelled (?:before|by) (?<time>{$patterns['time']}) local time\s*(?:on)?\s*(?<date>.{3,}?)\.(?:\s|$)/", $cancel, $m) // en
                || preg_match("/^Esta reserva se reembolsa al 100% si se cancela antes de las (?<time>{$patterns['time']}) hora local el (?<date>.{3,}?)\.(?:\s|$)/u", $cancel, $m) // es
                || preg_match("/reembolsável se cancelada até\s*(?<time>{$patterns['time']})\s*do horário local em\s*(?<date>.{3,}?)\.(?:\s|$)/u", $cancel, $m) // es
            ) {
                $dateDeadline = EmailDateHelper::parseDateRelative($this->normalizeDate($m['date']), $h->getCheckInDate(), false);

                if ($dateDeadline) {
                    $h->booked()->deadline(strtotime($m['time'], $dateDeadline));
                }
            }
        }

        // nonRefundable
        if (!empty($cancel)) {
            if (
                   preg_match("/esta reserva no será reembolsable y no se podrá cambiar ni cancelar/i", $cancel) // es
                || preg_match("/essa reserva é totalmente não-reembolsável e não pode ser alterada ou cancelada/i", $cancel) // pt
                || preg_match("/this booking is completely non-refundable and cannot be changed or cancelled/i", $cancel) // pt
                || preg_match("/this reservation cannot be cancelled, changed, or refunded for any reason/i", $cancel) // pt
            ) {
                $h->booked()->nonRefundable();
            }
        }

        // Room
        $roomCount = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'room for')]", null, true, "/(\d+)\s*room/");

        if (!empty($roomCount)) {
            $h->booked()
                ->rooms($roomCount);
        }

        $roomType = $this->nextNode($this->t("Room Type"));

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'room for')]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Remaining balance (after hotel reservation)'))}]")->length > 0) {
            $st = $email->add()->statement();
            $st->setBalance(str_replace(',', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Remaining balance (after hotel reservation)'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\,\.]+)\s*Puntos/")));
            $st->addProperty('Name', trim($h->getTravellers()[0][0]));
        }
    }

    private function assignLang()
    {
        if (isset($this->body)) {
            foreach ($this->body as $lang => $body) {
                if ($this->http->XPath->query("//*[{$this->contains($body)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Friday, July 25, 2014
            '#^\s*\w+\s*,\s*(\w+)\s+(\d+)\s*,\s*(\d{4})\s*$#u',
            // Sunday, May 25, 2014 @ 3:00 pm
            '#^\s*\w+\s*,\s*(\w+)\s+(\d+)\s*,\s*(\d{4})\s*@\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
            // sábado 2 de noviembre de 2019
            '/^[[:alpha:]\-]{2,}[,\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})(?:\s+de)?\s+(\d{4})$/u',
            // 29 de octubre
            '/^(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})$/u',
        ];
        $out = [
            '$2 $1 $3',
            '$2 $1 $3, $4',
            '$1 $2 $3',
            '$1 $2',
        ];
        $date = preg_replace($in, $out, $date);
        $date = $this->dateStringToEnglish($date);

        return $date;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function nextNode($field, $root = null, $regexp = null)
    {
        $text = $this->http->FindSingleNode("descendant::text()[" . $this->starts($field) . "]/ancestor::*[1]/following-sibling::*[1]", $root, true, $regexp);

        if (empty($text)) {
            $text = $this->http->FindSingleNode("descendant::text()[" . $this->starts($field) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
        }
        // forwarded
        if (empty($text)) {
            $text = $this->http->FindSingleNode("descendant::text()[" . $this->starts($field) . "]/ancestor::*[self::div or self::p][position() < 3]/following-sibling::p[1]", $root, true, $regexp);
        }

        return $text;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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

    private function contains($field, $node = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ',"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getProviderCode($text)
    {
        $provider = $this->arripos($text, self::$providers);

        return $provider ?? '';
    }

    private function arripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $key => $needle) {
            if (is_string($needle) && stripos($haystack, $needle) !== false) {
                return $key;
            } elseif (is_array($needle)) {
                foreach ($needle as $n) {
                    if (stripos($haystack, $n) !== false) {
                        return $key;
                    }
                }
            }
        }

        return false;
    }

    private function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }

        return null;
    }
}
