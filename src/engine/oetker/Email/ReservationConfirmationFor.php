<?php

namespace AwardWallet\Engine\oetker\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "oetker/it-295840221.eml, oetker/it-296363708.eml, oetker/it-366441362.eml, oetker/it-392264995.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            "Your Booking Confirmation" => ['Your Booking Confirmation', 'Reservation confirmation', 'Reservation Confirmation',
                'CANCELLATION CONFIRMATION', 'RESERVATION CONFIRMATION', ],
            "cancelledText"       => ['CANCELLATION CONFIRMATION', 'Cancellation number'],
            "Confirmation number" => ['Confirmation number', 'Confirmation Number', 'Confirmation number:', 'Confirmation Number:', 'Confirmation number :', 'Confirmation Number :', 'CONFIRMATION'],
            "Cancellation number" => ['Cancellation number', 'Cancellation Number', 'Cancellation number:', 'Cancellation Number:', 'Cancellation number :', 'Cancellation Number :'],
            "Guest name:"         => ['Guest name', 'Guest Name', 'Guest name:', 'Guest Name:', 'Guest name :', 'Guest Name :'],
            "Accompany name:"     => ['Accompany name:', 'Accompany Name:', 'Accompany name :', 'Accompany Name :', 'Accompany name', 'Accompany Name'],
            "guestNameFilters"    => [
                "Would it be possible to have all the guest's full names",
                'Could you please advise us with the name of all guests',
            ],
            "Arrival date:"       => ['Arrival date', 'Arrival Date', 'Arrival date:', 'Arrival Date:', 'Arrival date :', 'Arrival Date :', 'ARRIVAL DATE'],
            "Departure date:"     => ['Departure date', 'Departure Date', 'Departure date:', 'Departure Date:', 'Departure date :', 'Departure Date :', 'DEPARTURE DATE'],
            "No. of Persons:"     => [
                'No. of persons', 'No. of Persons', 'No. of persons:', 'No. of Persons:', 'No. of persons :', 'No. of Persons :',
                'Number of guests', 'Number of Guests', 'Number of guests:', 'Number of Guests:', 'Number of guests :', 'Number of Guests :',
                'Adults / children', 'Adults / children:', 'Adults / children :', 'N. OF GUESTS',
            ],
            //            "adult" => '',
            "child"                                => ['child', 'chidl'],
            'Room type:'                           => ['Room type', 'Room Type', 'Room type:', 'Room Type:', 'Room type :', 'Room Type :', 'Accommodation', 'Accommodation:', 'Accommodation :', 'ROOM TYPE'],
            'Daily room rate:'                     => [
                'Average daily room rate', 'Average Daily Room Rate', 'Average daily room rate:', 'Average Daily Room Rate:', 'Average daily room rate :', 'Average Daily Room Rate :',
                'Daily room rate', 'Daily Room Rate', 'Daily room rate:', 'Daily Room Rate:', 'Daily room rate :', 'Daily Room Rate :',
                'Daily rate', 'Daily Rate', 'Daily rate:', 'Daily Rate:', 'Daily rate :', 'Daily Rate :',
            ],
            'Total stay:'                          => [
                'Total stay', 'Total Stay', 'Total stay:', 'Total Stay:', 'Total stay :', 'Total Stay :',
                'Total cost', 'Total Cost', 'Total cost:', 'Total Cost:', 'Total cost :', 'Total Cost :', 'TOTAL COST',
            ],
            'Check-in:'                            => ['Check-in:', 'Check-in :', 'Check-in time is:', 'Check-in time is :', 'CHECK-IN :'],
            'Check-out:'                           => ['Check-out:', 'Check-out :', 'Check-out time is:', 'Check-out time is :', 'CHECK-OUT :'],
            "Cancellation Policy"                  => [
                'Cancellation policy', 'Cancellation Policy',
                'Cancellation policy:', 'Cancellation Policy:',
                'Cancellation policy :', 'Cancellation Policy :', 'CANCELLATION POLICY:',
            ],
            "contact our reservations department:" => [
                'contact our reservations department:', 'contact our reservations department :',
                'please do not hesitate to contact us under:', 'please do not hesitate to contact us under :',
                'Should you require further assistance please contact our reservation department:',
            ],
            'Reservation confirmation for' => ['Reservation confirmation for', 'Your Confirmation'],
        ],
        'pt' => [
            "Your Booking Confirmation" => 'Confirmação de reserva',
            //            "cancelledText" => [''],
            "Confirmation number" => [
                'Código de confirmação', 'Código de Confirmação', 'Código de confirmação:', 'Código de Confirmação:', 'Código de confirmação :', 'Código de Confirmação :',
                'Número de confirmação', 'Número de Confirmação', 'Número de confirmação:', 'Número de Confirmação:', 'Número de confirmação :', 'Número de Confirmação :',
            ],
            //            "Cancellation number" => [''],
            "Guest name:" => ['Nome do hóspede', 'Nome do Hóspede', 'Nome do hóspede:', 'Nome do Hóspede:', 'Nome do hóspede :', 'Nome do Hóspede :'],
            //            "Accompany name:" => [],
            // "guestNameFilters" => [''],
            "Arrival date:"                        => ['Data de chegada', 'Data de Chegada', 'Data de chegada:', 'Data de Chegada:', 'Data de chegada :', 'Data de Chegada :'],
            "Departure date:"                      => ['Data de saída', 'Data de Saída', 'Data de saída:', 'Data de Saída:', 'Data de saída :', 'Data de Saída :'],
            "No. of Persons:"                      => [
                'No. de pessoas', 'No. de pessoas', 'No. de pessoas:', 'No. de pessoas:', 'No. de pessoas :', 'No. de pessoas :',
                'Adultos / crianças', 'Adultos / crianças:', 'Adultos / crianças :',
            ],
            "adult"                                => 'adulto',
            "child"                                => 'crianç',
            'Room type:'                           => ['Tipo de acomodação', 'Tipo de Acomodação', 'Tipo de acomodação:', 'Tipo de Acomodação:', 'Tipo de acomodação :', 'Tipo de Acomodação :'],
            'Daily room rate:'                     => [
                'Tarifa', 'Tarifa:', 'Tarifa :',
                'Tarifa do apartamento', 'Tarifa do Apartamento', 'Tarifa do apartamento:', 'Tarifa do Apartamento:', 'Tarifa do apartamento :', 'Tarifa do Apartamento :',
                'Tarifa diária', 'Tarifa Diária', 'Tarifa diária:', 'Tarifa Diária:', 'Tarifa diária :', 'Tarifa Diária :',
            ],
            'Total stay:'                          => [
                'Tarifa total com taxas', 'Tarifa Total Com Taxas', 'Tarifa total com taxas:', 'Tarifa Total Com Taxas:', 'Tarifa total com taxas :', 'Tarifa Total Com Taxas :',
                'Total da estada', 'Total da Estada', 'Total da estada:', 'Total da Estada:', 'Total da estada :', 'Total da Estada :',
            ],
            'Check-in:'                            => [
                'Horário de check in', 'Horário de check in:', 'Horário de check in :',
                'Horário de check-in', 'Horário de check-in:', 'Horário de check-in :',
            ],
            'Check-out:'                           => [
                'Horário de check out', 'Horário de check out:', 'Horário de check out :',
                'Horário de check-out', 'Horário de check-out:', 'Horário de check-out :',
            ],
            "Cancellation Policy"                  => [
                'Política de cancelamento', 'Política de Cancelamento', 'Política de cancelamento:', 'Política de Cancelamento:', 'Política de cancelamento :', 'Política de Cancelamento :',
            ],
            "contact our reservations department:" => ['contato com nosso departamento de reservas:', 'contato com nosso departamento de reservas :'],
        ],
    ];

    private $detectFrom = ['@oetkercollection.com', '.oetkercollection.com', '@lanesborough.com'];
    private $detectSubject = [
        // en
        ' - Reservation confirmation for',
        ' - Cancellation confirmation for',
        // pt
        ' - Confirmação de Reserva para',
    ];
    private $detectBody = [
        'en' => [
            'Your Booking Confirmation',
            'Reservation confirmation',
            'Reservation Confirmation',
            'CANCELLATION CONFIRMATION',
            'look forward to welcoming your guests to Le Bristol',
        ],
        'pt' => [
            'Confirmação de reserva',
        ],
    ];

    private $subject;

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['oetkercollection.'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Oetker Hotel Management'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->subject = $parser->getSubject();
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]] ?\.?', // Mr. Hao-Li Huang
        ];

        $cond = '';

        if (count($this->http->FindNodes("//text()[{$this->eq('Powered By NextGuest CRM')}]")) > 1) {
            $cond = "[not(preceding::text()[{$this->eq('Powered By NextGuest CRM')}])]";
        }

        $h = $email->add()->hotel();

        // General
        $conf = $this->nextSibling($this->t("Confirmation number"), $cond);

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number'))} and not({$this->eq($this->t('Confirmation number'))})]{$cond}",
                null, true, "/{$this->opt($this->t('Confirmation number'))}\s*([\w\-]+)\s*$/u");
        }
        $conf = array_filter(preg_split("/(?:\s*\\/\s*|\s*&\s*|\s+)/", $conf));

        foreach ($conf as $c) {
            $h->general()
                ->confirmation($c);
        }

        $guestName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest name:'))}] ]{$cond}/*[normalize-space()][2]", null, true, "/^(.*?)[.\s]*$/");

        $guestName = trim(preg_replace("/{$this->opt($this->t('guestNameFilters'))}/", '', $guestName), '- .;!?');
        $travellers = array_merge(preg_split('/(?:&|\s+and\s+|Mrs\s)/i', $guestName), preg_split('/(?:&|\s+and\s+|Mrs\s)/i', $this->nextSibling($this->t("Accompany name:"), $cond)));
        $travellers = array_filter($travellers, function ($item) use ($patterns) {
            return preg_match("/^{$patterns['travellerName']}$/u", $item) > 0;
        });

        if (count($travellers) === 0 && preg_match("/{$this->opt($this->t('Reservation confirmation for'))}\s*(\D+)\|/", $this->subject, $m)) {
            $travellers = [$m[1]];
        }

        $travellers = preg_replace(["/^\s*(Mr|Ms|Mrs|Miss|Mstr|Dr|Sr)[.\s]+/i", "/\s*\(.*\)\s*$/"], '', $travellers);

        $h->general()->travellers($travellers);

        if ($this->http->XPath->query("//*[{$this->contains($this->t("cancelledText"))}]{$cond}")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');

            if (empty($h->getConfirmationNumbers())) {
                $h->general()
                    ->noConfirmation();
            }

            $h->general()
                ->cancellationNumber($this->nextSibling($this->t("Cancellation number"), $cond));
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Booking Confirmation"))}]{$cond}/following::text()[normalize-space()][1]"));

        if (!empty($date)) {
            $h->general()
                ->date($date);
        }

        // Hotel
        $hotelInfo = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t("contact our reservations department:"))}]{$cond}/following::text()[normalize-space()][1]/ancestor::td[1]"));
        $hotelInfo = preg_replace("/^.*{$this->opt($this->t("contact our reservations department:"))}\s*(.+)$/s", '$1', $hotelInfo);
//        $this->logger->debug($hotelInfo);

        if (preg_match("/Email:[ ]*\S*[ ]*\n+[ ]*(?<name>.{2,}?)[ ]*(?:,|[ ]+-[ ]+|\n+)[ ]*(?<address>.{3,}?)[ ]*(?:\n|$)/i", $hotelInfo, $m)) {
            $h->hotel()->name($m['name'])->address($m['address']);
        }

        if (preg_match("/^[ ]*Tel:[ ]*(?<tel>{$patterns['phone']})[ ]*(?:\||$)/m", $hotelInfo, $m)) {
            $h->hotel()->phone($m['tel']);
        }

        if (preg_match("/^[ ]*Fax:[ ]*(?<fax>{$patterns['phone']})[ ]*(?:\||$)/m", $hotelInfo, $m)) {
            $h->hotel()->fax($m['fax']);
        }

        // Booked
        $noOfPersons = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('No. of Persons:'))}] ]{$cond}/*[normalize-space()][2]"));

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t("adult"))}?/i", $noOfPersons, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})(?:\s*\([^)(]*\))?\s*{$this->opt($this->t("child"))}/i", $noOfPersons, $m)) {
            $h->booked()->kids($m[1]);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->nextSibling($this->t("Arrival date:"), $cond)))
            ->checkOut($this->normalizeDate($this->nextSibling($this->t("Departure date:"), $cond, "/^(.*?\d.*?)\s*(?:[*]|$)/")))
        ;

        $checkInTime = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Check-in:')) . ']' . $cond,
            null, false, '/' . $this->opt($this->t('Check-in:')) . '\s*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i');

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Check-in:')) . ']' . $cond . '/following::text()[normalize-space()][1]',
                null, false, '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i');
        }

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($this->normalizeTime($checkInTime), $h->getCheckInDate()));
        }
        $checkOutTime = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Check-out:')) . ']' . $cond,
            null, false, '/' . $this->opt($this->t('Check-out:')) . '\s*\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i');

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Check-out:')) . ']' . $cond . '/following::text()[normalize-space()][1]',
                null, false, '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i');
        }

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($this->normalizeTime($checkOutTime), $h->getCheckOutDate()));
        }

        // Room
        $roomTypeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('ROOM TYPE'))}]/ancestor::tr[1]/descendant::strong");

        if ($roomTypeNodes->length > 0) {
            foreach ($roomTypeNodes as $roomTypeRoot) {
                $room = $h->addRoom();

                $room->setType($this->http->FindSingleNode(".", $roomTypeRoot));

                $rate = $this->http->FindSingleNode("./following::text()[normalize-space()][not(contains(normalize-space(), 'bed'))][1][contains(normalize-space(), 'daily rate of')]", $roomTypeRoot);

                if (!empty($rate)) {
                    $room->setRate($rate);
                }
            }
        } else {
            $dailyRate = null;
            $type = $this->nextSibling($this->t("Room type:"), $cond);

            if (preg_match("/^(.{2,}?)\s+at the daily rate of\s+(\d[,.\'\d]* [^\-\d)(]+?)\s+each$/i", $type, $m)) {
                $type = $m[1];
                $dailyRate = $m[2];
            }
            $type = preg_replace('/\s+upgraded from.+/', '', $type);

            $dailyRate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Daily room rate:'))} and not({$this->eq($this->t('Daily room rate:'))})]{$cond}", null, true, "/^{$this->opt($this->t('Daily room rate:'))}[:\s]*(.*\d.*)$/")
                ?? $this->nextSibling($this->t("Daily room rate:"), $cond, "/^.*\d.*$/")
                ?? $dailyRate
            ;

            if (!empty($type) || $dailyRate !== null) {
                $r = $h->addRoom();

                if (!empty($type)) {
                    $r->setType($type);
                }

                if ($dailyRate !== null) {
                    $r->setRate($dailyRate);
                }
            }
        }

        if ($h->getCancelled()) {
            return;
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total stay:'))} and not({$this->eq($this->t('Total stay:'))})]{$cond}", null, true, "/{$this->opt($this->t('Total stay:'))}[:\s]*(.+)/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total stay:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{3}\s*[\d\.\,\']+)$/");
        }

        if (preg_match("/^\s*(?<currency>[^\d)(]{1,5}?)[ ]*(?<amount>\d[,.\'\d ]*?)\s*$/", $total, $m)) {
            // EUR 9,985.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $h->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        $cancellation = $this->nextSibling($this->t("Cancellation Policy"), $cond);

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Cancellation Policy"))}]{$cond}/ancestor::*[{$this->starts($this->t("Cancellation Policy"))} and not({$this->eq($this->t("Cancellation Policy"))})][last()]");
        }

        $h->general()->cancellation($cancellation);

        if (preg_match("/Please (?i)kindly note that in the event of a cancell?ation within\s+(?<prior>\d{1,3} hours?)\s+prior to arrival \(?[ ]*(?<hour>{$patterns['time']})\s+local time[ ]*\)? or non-arrival, one-night fee will be charged\s*(?:\.|$)/", $cancellation, $m)
            || preg_match("/Your (?i)reservation can be cancell?ed free of charge until\s+(?<hour>{$patterns['time']})(?:\s*\(?\s*local time\s*\)?\s*)?\s+(?<prior>\d{1,3} hours?) prior to your arrival date\./", $cancellation, $m)
            || preg_match("/Please kindly note that in the event of a cancellation within (?<prior>\d{1,3} days?) prior to arrival \((?<hour>{$patterns['time']}) local time\) or non\-arrival/", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], $m['hour']);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextSibling($field, $cond = '', $regexp = null): ?string
    {
        return $this->http->FindSingleNode("(//*[{$this->eq($field)}]{$cond}/following-sibling::*[normalize-space(.)!=''][1])[1]",
            null, true, $regexp);
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // 24 de setembro de 2022
            //sexta-feira 23 setembro 2022
            '/^\s*(?:[[:alpha:]\-]+ +)?(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\s*$/ui',
            // Terça-feira 11/10/2022
            '/^\s*(?:[[:alpha:]\-]+ +)?(\d{2})\\/(\d{2})\\/(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
            '$1.$2.$3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function normalizeTime($string): string
    {
        if (preg_match('/^12\s*noon$/i', $string)
            || preg_match('/^\s*noon\s*$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25

        return $string;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
