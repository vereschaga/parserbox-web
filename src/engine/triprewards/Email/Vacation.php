<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: triprewards/WorldMarkConfirmation(object)

class Vacation extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-35293988.eml, triprewards/it-70125127.eml, triprewards/it-70645166.eml, triprewards/it-71051905.eml, triprewards/it-71074300.eml, triprewards/it-715450196.eml, triprewards/it-717785493.eml";

    public $reFrom = "wyndham";
    public $reBody = [
        'en'    => ['we are delighted to confirm your reservation', 'Resort Information'],
        'en2'   => ['we are pleased to confirm your reservation at', 'Resort Information'],
        'en3'   => ['Your vacation is booked', 'RESORT FEATURES'],
        'en4'   => ['Thank you for using RCI Points for your vacation plans', 'RESORT FEATURES'],
        'en5'   => ['we are delighted to confirm your reservation', 'RESORT INFORMATION'],
        'en6'   => ['Please present this confirmation, along with your identification, to the front desk staff upon your arrival', 'RESORT INFORMATION'],
        'en7'   => ['look forward to welcoming you to', 'Important Information'],
        'en8'   => ['Your Reservation Is Booked', 'Resort Profile'],
        'en9'   => ['Cancelled:', 'Vacation Cancellation'],
        'en10'  => ['Please present this document', 'Vacation Confirmation'],
        'en11'  => ['We are pleased to confirm your vacation', 'Vacation Confirmation'],
        'en12'  => ['Resort Profile', 'Transaction Date:'],
        'en13'  => ['We are delighted to confirm that your reservation', 'Resort Information'],
        'pt'    => ['Reserva de Férias Adicionais', 'Prezado/a:'],
        'es'    => ['Nos complace confirmarte que tus vacaciones', 'Fecha de Transacción:'],
        'es2'   => ['Confirmación de tus vacaciones', 'Fecha de Transacción:'],
    ];
    public $reSubject = [
        'Your Vacation is Confirmed',
        'Your Reservation Has Been Canceled',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation'          => ['Confirmation', 'Relation Number:', 'Reservation Number:'],
            'Vacation Confirmation' => ['Vacation Confirmation', 'Your Vacation Confirmation'],
            'Resort Information'    => ['Resort Information', 'Resort Profile', 'RESORT INFORMATION'],
            'Unit Type'             => ['Unit Type', 'Suite Type', 'Suite Type'],
            'Check In'              => ['Check In', 'Check in:'],
            'Check Out'             => ['Check Out', 'Check out:'],
            'Points Used'           => ['Points Used', 'Points Redeemed', 'Exchange Value'],
            'Cancellation Policy'   => ['Cancellation Policy', 'Cancelling a reservation:'],
            'Resort Phone'          => ['Resort Phone', 'Phone', 'Phone:'],
        ],
        'pt' => [
            'Confirmation'          => ['Reserva #:'],
            //'Vacation Confirmation' => [''],
            'Your Vacation Is Confirmed' => 'Suas férias estão confirmadas',
            'Resort Information'         => ['Informações Gerais'],
            'Unit Type'                  => ['Unit types'],
            'Check In'                   => ['Data de Entrada:'],
            'Check Out'                  => ['Data de Saída:'],
            //'Points Used'           => [''],
            'Cancellation Policy'   => ['Cancelamento de uma Reserva:'],
            'Dear:'                 => 'Prezado/a:',
            'Resort ID:'            => 'ID Hotel:',
            'Resort Phone'          => 'Telefone',
            'Room:'                 => 'Quarto:',
            'Transaction Date:'     => 'Data da Transação:',
        ],
        'es' => [
            'Confirmation'          => ['Reservación #', 'Nº de relación:'],
            //'Vacation Confirmation' => '',
            //'Your Vacation Is Confirmed' => '',
            'Resort Information'         => ['Información del Hotel'],
            'Unit Type'                  => ['Unit types'],
            'Check In'                   => ['Fecha de Entrada:'],
            'Check Out'                  => ['Fecha de Salida:'],
            //'Points Used'           => [''],
            //'Cancellation Policy'   => [''],
            'Dear:'                 => ['Estimado/a:', 'Estimado:'],
            'Resort ID:'            => 'ID del Hotel:',
            'Resort Phone'          => ['Teléfono', 'Teléfono:'],
            'Room:'                 => 'Cocina:',
            'Transaction Date:'     => 'Fecha de Transacción:',
        ],
    ];
    public static $providers = [
        'rcitravel' => [
            '@mail.rci.com',
            'www.RCI.com',
            '@rci.com',
            'RCI Guide',
        ],
        'triprewards' => [],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $this->parseEmail($email);

        foreach (self::$providers as $code => $conditions) {
            if ($this->http->XPath->query("//node()[{$this->contains($conditions)}]")->length > 0) {
                $email->setProviderCode($code);
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='CLUB WYNDHAM' or contains(@src, 'wyndhamvo') or contains(@src, 'WMPalmSpring')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Wyndham Vacation Resorts')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'RCI, LLC') or contains(normalize-space(), 'RCI Guide') or contains(normalize-space(), 'www.rci.com')]")->length > 0
        ) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($this->reSubject)) {
            if (stripos($headers['from'], $this->reFrom) !== false && stripos($headers["from"], 'rci@mail.rci.com') !== false) {
                return true;
            }

            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        $conf = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation'))}])[1]/following::*[normalize-space(.)][1]");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vacation Confirmation'))}]/following::text()[{$this->starts($this->t('Confirmation'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($conf) && stripos($conf, '/') == false) {
            $h->addConfirmationNumber($conf);
        } elseif (!empty($conf) && stripos($conf, '/') !== false) {
            $h->general()
                ->noConfirmation();
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation'))}]")->length == 0) {
            $h->general()
                ->noConfirmation();
        }

        //status
        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your Vacation Is Confirmed'))}]")->length > 0) {
            $h->setStatus('Confirmed');
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(.),'Your vacation is booked')]")->length > 0) {
            $h->setStatus('Booked');
        }

        if ($this->http->XPath->query("//text()[{$this->eq(['Vacation Cancellation', 'CANCELLATION'])} or {$this->contains(['reservation has officially been canceled'])}]")->length > 0) {
            $h->general()
                ->status('canceled')
                ->cancelled()
            ;
        }

        //account
        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Member ID:'))}]", null, true, "/{$this->opt($this->t('Your Member ID:'))}\s*([\d\-]+)/");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        //travellers
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('we are delighted to confirm'))}]", null, true, "/^([A-Z\s]+)\,/u");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller, true);
        }
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Dear:'))}]/following::text()[normalize-space()][1]/ancestor::*[1]/descendant::text()[not({$this->contains($this->t('Dear:'))})][normalize-space()]");

        if (count($travellers) > 0) {
            $h->general()
                ->travellers($travellers, true);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Resort Information'))}]/following::text()[normalize-space(.)][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Resort Profile'))}]/following::text()[normalize-space(.)][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Resort ID:'))}]/preceding::text()[normalize-space(.)][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]/following::text()[{$this->starts($this->t('Resort ID:'))}][1]/preceding::text()[normalize-space(.)][1]");
        }

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);
            $node = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'{$hotelName}')]/following::*[normalize-space(.)][1])[last()]");

            if (preg_match("#^\s*(.+?)\s+ph[\s:]+([+]?\d+[\d\-\(\) ]+?)\s*$#u", $node, $m)) {
                $h->hotel()
                    ->address($m[1])
                    ->phone($m[2]);
            }

            if (empty($h->getAddress()) && (strlen($node) < 5 || stripos($node, $hotelName) === false)) {
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]/following::text()[{$this->starts($hotelName)}][1]/following::text()[normalize-space()][3]/ancestor::th[1]");
            }

            if (preg_match("#{$this->opt($this->t('Resort ID:'))}\s*[A-Z\d]+\s+(.+)\s*{$this->opt($this->t('Resort Phone'))}[\s:]+([+]?\(?\d+\)?\s*[\d\-/]+)#", $node, $m)) {
                $h->hotel()
                    ->address($m[1])
                    ->phone($m[2]);
            } else {
                $phone = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'{$hotelName}')]/following::*[normalize-space(.)][{$this->starts($this->t('Resort Phone'))}][1])[last()]", null, true, "/{$this->opt($this->t('Resort Phone'))}[ ]*\:[ ]*([\d \-]+)/");

                if (!empty($phone)) {
                    $h->hotel()
                        ->address($node)
                        ->phone($phone);
                }
            }

            if (empty($h->getAddress())) {
                $node = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Resort Profile'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]//text()[normalize-space()]"));

                if (empty($node)) {
                    $node = implode("\n",
                        $this->http->FindNodes("//text()[{$this->eq($this->t('Resort ID:'))}]/ancestor::*[not({$this->eq($this->t('Resort ID:'))})][1]//text()[normalize-space()]"));
                }

                if (preg_match("/" . preg_quote($hotelName, '/') . "\s+{$this->opt($this->t('Resort ID:'))}\s*[\dA-Z]{4}\s*(?<address>[\s\S]+?)\s+{$this->opt($this->t('Resort Phone'))}\:?\s*(?<phone>[\d\-\/\ ]{4,})(\n|$)/u", $node, $m)) {
                    $h->hotel()
                        ->address(preg_replace('/\s+/', ' ', $m['address']))
                        ->phone($m['phone']);
                }
            }
        }

        $checkInDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In'))}]/following::*[normalize-space(.)][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check In'))}\s*(\d+\/\d+\/\d{4}\s*a?t?\s*[\d\:]+\s*A?\.?P?\.?M?\.?)/i");

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Check In')]/following::*[normalize-space(.)][1]");
        }

        $checkOutDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out'))}]/following::*[normalize-space(.)][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Check Out'))}\s*(\d+\/\d+\/\d{4}\s*a?t?\s*[\d\:]+\s*A?\.?P?\.?M?\.?)/i");

        if (empty($checkOutDate)) {
            $checkOutDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out'))}]/following::*[normalize-space(.)][1]");
        }

        if (preg_match("#^(?<date>\d+\/\d+\/\d{4})\s+(?<time>\d+\:\d+)\s*$#", $checkInDate, $matchIn) && preg_match("#^(?<date>\d+\/\d+\/\d{4})\s+(?<time>\d+\:\d+)\s*$#", $checkOutDate, $matchOut)) {
            $dt = $this->DateFormatForHotels($matchIn['date'], $matchOut['date']);
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($dt[0] . ' ' . $matchIn['time'])))
                ->checkOut(strtotime($this->normalizeDate($dt[1] . ' ' . $matchOut['time'])));
        } else {
            if (!empty($checkInDate)) {
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($checkInDate)));
            }

            if (!empty($checkOutDate)) {
                $h->booked()
                    ->checkOut(strtotime($this->normalizeDate($checkOutDate)));
            }
        }

        if ($h->getCancelled()) {
            return;
        }

        $r = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Unit Type'))}]/following::*[normalize-space(.)][1]");

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Transaction Date:'))}]/preceding::text()[{$this->contains($this->t('Bedroom'))}][1]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Check Out'))}\s*\d+\/\d+\/\d{4}\s*a?t?\s*[\d\:]+\s*A?\.?P?\.?M?\.?\s*(.+)\s*(?:{$this->opt($this->t('Points Used'))}|Transaction)/");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Transaction Date:'))}]/preceding::text()[{$this->starts($this->t('Room:'))}][1]");
        }

        if (!empty($roomType)) {
            $r->setType($roomType);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Used'))}]/following::*[normalize-space(.)][1]");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        $cancel = $this->http->FindSingleNode("//node()[{$this->starts($this->t('Cancellation Policy'))}]/following-sibling::text()[normalize-space(.)][string-length()<2000][1]");

        if (empty($cancel)) {
            $cancel = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'RESORT FEATURES')]/following::text()[{$this->contains($this->t('Cancellation Policy'))}][1]/following::text()[normalize-space()][1]");
        }

        if (empty($cancel)) {
            $cancel = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]");
        }

        if (!empty($cancel)) {
            $h->setCancellation($cancel);
        }

        if (preg_match('/Reservations may be cancelled without penalty up to twenty-four \((\d{1,2})\) hours prior to scheduled check\-in/', $cancel, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours', '00:00');
        } elseif (preg_match("/Todas as transações são finais e definitivas, por esse motivo não aplica a devolução por cancelamento ou mudança/u", $cancel)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN '.$date);
        $in = [
            //09/26/2017at 10 a.m.
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*at\s+(\d+)\s+([ap])\.?(m)\.?\s*$#i',
            // Monday, May 20, 2019 - 4:00 PM
            '/^\w+, (\w+) (\d{1,2}),? (\d{2,4})[ ]*\-[ ]*(\d{1,2}:\d{2} [AP]M)$/',
            //15/12/2024 11:00
            '#^(\d+)\/(\d+)\/(\d{4})\s+(\d+\:\d+)\s*$#u',
        ];

        $out = [
            '$3-$1-$2 $4:00 $5$6',
            '$2 $1 $3, $4',
            '$1.$2.$3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        //$this->logger->debug('OUT '.$str);
        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function DateFormatForHotels($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
