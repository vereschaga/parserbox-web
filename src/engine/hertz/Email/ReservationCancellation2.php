<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationCancellation2 extends \TAccountChecker
{
    public $mailFiles = "hertz/it-215137972.eml, hertz/it-215328374.eml, hertz/it-216302005.eml, hertz/it-216436057.eml, hertz/it-221621777.eml, hertz/it-231512069.eml";
    public $subjects = [
        'Hertz Reservation Cancellation',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            // 'VEHICLE CLASS'                         => '',
            'Confirmation Number:'                  => ['Confirmation Number:', 'Your cancellation number is:'],
            ', Your Reservation has been cancelled' => [', Your Reservation has been cancelled', ', Your Reservation has been Cancelled',
                ', your reservation has been cancelled', ],
            'Pickup Location'                       => ['Pickup Location', 'Pick Up Location'],
            'Pickup Time'                           => ['Pickup Time', 'Pick Up time'],
        ],

        "pt" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['Número de confirmação:'],
            ', Your Reservation has been cancelled' => [', Sua reserva foi cancelada.',
                ', sua reserva foi cancelada.', ],
            'Pickup Location'                       => ['Loja de Retirada:'],
            'Pickup Time'                           => ['Retirada'],
            'Member Number'                         => 'Número de membro',
        ],

        "fr" => [
            // 'VEHICLE CLASS'                         => '',
            'Confirmation Number:'                  => ['Numéro de réservation initial :'],
            ', Your Reservation has been cancelled' => [', Votre réservation a bien été annulée...',
                ', votre réservation a bien été annulée...', ],
            'Pickup Location'                       => ['Lieu de départ :'],
            'Pickup Time'                           => ['Date de départ :'],
            //'Member Number' => ''
        ],

        "es" => [
            'VEHICLE CLASS'                         => 'CLASE DE VEHÍCULO',
            'Confirmation Number:'                  => ['Confirmación #'],
            ', Your Reservation has been cancelled' => [', La reserva ha sido cancelada.',
                ', la reserva ha sido cancelada.', ],
            'Pickup Location'                       => ['Localidad de Recogida'],
            'Pickup Time'                           => ['Recogida'],
            'Member Number'                         => 'Número de miembro',
        ],

        "de" => [
            'VEHICLE CLASS'                         => 'FAHRZEUGKLASSE',
            'Confirmation Number:'                  => ['Reservierungsnummer'],
            ', Your Reservation has been cancelled' => [', Ihre Reservierung wurde storniert',
                ', ihre reservierung wurde storniert', ],
            'Pickup Location'                       => ['Anmietstation'],
            'Pickup Time'                           => ['Anmietung'],
            'Member Number'                         => 'Mitgliedsnummer',
        ],

        "it" => [
            'VEHICLE CLASS'                         => 'CLASSE DEL VEICOLO',
            'Confirmation Number:'                  => ['Numero di Conferma:'],
            ', Your Reservation has been cancelled' => [', La Prenotazione è stata annullata'],
            'Pickup Location'                       => ['Agenzia di ritiro'],
            'Pickup Time'                           => ['Ritiro'],
            'Member Number'                         => 'Numero membro',
        ],
        "ko" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['예약 번호:'],
            ', Your Reservation has been cancelled' => [', 귀하의 예약이 취소되었습니다'],
            'Pickup Location'                       => ['임차 영업소'],
            'Pickup Time'                           => ['차량 인수'],
            //'Member Number'                         => '',
        ],
        "zh" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['确认号码：'],
            ', Your Reservation has been cancelled' => [', 您的预订已取消'],
            'Pickup Location'                       => ['取车门店'],
            'Pickup Time'                           => ['取车'],
            'Member Number'                         => '会员编号',
        ],
        "no" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['Bekreftelsesnummer:'],
            ', Your Reservation has been cancelled' => [', din reservasjon er blitt kansellert'],
            'Pickup Location'                       => ['Hentelokasjon'],
            'Pickup Time'                           => ['Hente dato'],
            // 'Member Number'                         => '',
        ],
        "sv" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['Bokningsnummer:'],
            ', Your Reservation has been cancelled' => [', din bokning har tagits bort'],
            'Pickup Location'                       => ['Hämtas'],
            'Pickup Time'                           => ['Upphämtningstid :'],
            // 'Member Number'                         => '',
        ],
        "pl" => [
            'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['Numer potwierdzenia:'],
            ', Your Reservation has been cancelled' => [', twoja rezerwacja została anulowana'],
            'Pickup Location'                       => ['Punkt odbioru'],
            'Pickup Time'                           => ['Odbiór'],
            // 'Member Number'                         => '',
        ],
        "nl" => [
            'VEHICLE CLASS'                         => ['VEHICLE CLASS', 'VOERTUIG'],
            'Confirmation Number:'                  => ['BEVESTIGINGSNUMMER', 'Bevestigingsnummer'],
            ', Your Reservation has been cancelled' => [', je reservering is geannuleerd'],
            'Pickup Location'                       => ['OPHAALLOCATIE'],
            'Pickup Time'                           => ['Ophaalgegevens'],
            // 'Member Number'                         => '',
        ],
        "ja" => [
            // 'VEHICLE CLASS'                         => 'VEHICLE CLASS',
            'Confirmation Number:'                  => ['予約番号：'],
            ', Your Reservation has been cancelled' => [', 予約がキャンセルされました。'],
            'Pickup Location'                       => ['借り出し場所'],
            'Pickup Time'                           => ['借り出し時間'],
            // 'Member Number'                         => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@emails.hertz.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains('The Hertz Corporation')}]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emails\.hertz\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();

        $travellers = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', Your Reservation has been cancelled'))}]", null, true, "/(\D*)\s*{$this->opt($this->t(', Your Reservation has been cancelled'))}/");

        if (!empty($travellers)) {
            $r->general()
                ->traveller($travellers);
        }

        $confirmatioNumner = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{8,})$/");

        if (!empty($confirmatioNumner)) {
            $r->general()
                ->confirmation($confirmatioNumner);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t(', Your Reservation has been cancelled'))}]")->length > 0) {
            $r->general()
                ->cancelled();
        }

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Pickup Location'))}\s*(.+)/s"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Time'))}][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Pickup Time'))}\s*(.+)/")));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('VEHICLE CLASS'))}]/ancestor::tr[1]",
                null, true, "/{$this->opt($this->t('VEHICLE CLASS'))}\s*(.+)/s"), true, true);

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Number'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Member Number'))}\s*(\d{5,})/");

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseCar($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug($str);
        $in = [
            "#^\w+\.?\,\s*([[:alpha:]]+)\s*(\d+)\.?\,\s*(\d{4})\s*(?:\,|at|um|às|à|a)\s*([\d\:]+\s*(?:[AP]M)?)$#u", //Fri, Nov 25, 2022, 10:00 AM
            "#^\w+\.?\,\s*(\d+)\s*([[:alpha:]]+)\.?\,\s*(\d{4})\s*(?:\,|at|um|às|à|a la\(s\)|a|时间|til|för| w |om)\s*([\d\:]+\s*(?:[AP]M)?)$#u", //Sat, 05 Nov, 2022 at 16:00
            "#^\w+\,\s*(\d+)\s*[[:alpha:]]+\s*(\d+)\,\s*(\d{4})\s*[@]\s*([\d\:]+)$#u", //일, 10월 30, 2022 @ 10:30
            // 木, 31 8, 2023 at 21:30
            "#^\s*\w\,\s*(\d+)\s+(\d+),\s*(\d{4})\s+at\s+([\d\:]+)$#u",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2.$1.$3, $4",
            "$3-$2-$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict[', Your Reservation has been cancelled']) && !empty($dict['Pickup Location'])
                && $this->http->XPath->query("//text()[{$this->contains($dict[', Your Reservation has been cancelled'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Pickup Location'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
