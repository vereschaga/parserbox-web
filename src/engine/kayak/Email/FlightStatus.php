<?php

namespace AwardWallet\Engine\kayak\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightStatus extends \TAccountChecker
{
    public $mailFiles = "kayak/it-308588337.eml, kayak/it-313117752.eml, kayak/it-313123692.eml, kayak/it-313223328.eml, kayak/it-313223682.eml, kayak/it-313244741.eml, kayak/it-403537965.eml";

    public $detectFrom = "noreply-trips@message.kayak.com";

    public $detectSubject = [
        // en
        'Check in to your', //  Check in to your IAH-LAS flight now
        'Updated departure time for',
        ' gate change to ',
        ' assigned to gate ',
        ' on time',
        // fr
        ' - à l’heure',
        'Heure de départ mise à jour pour le vol',
        '- changement de porte',
        // pt
        'Faça agora o check-in do seu voo',
        ' na hora prevista',
        'Novo horário de partida do voo',
        'foi designado para o portão ',
        'O portão do voo',
        'pode ter sido cancelado',
        // es
        'Realiza ahora la facturación para tu vuelo',
        ': en hora',
        ': a tiempo',
        'Horario de salida actualizado para el vuelo ',
        'tiene asignada la puerta',
        // de
        'pünktlich',
        'zugeteilt',
        //pl
        ' - planowo',
        // it
        'assegnato al gate',
        'Effettua ora il check-in per il volo',
        'in orario',
        // ja
        '行きにチェックイン',
        // nl
        'Gate-wijziging',
        // tr
        'uçuşunun kapısı',
        'için güncel kalkış saati:',
        // sv
        'har tilldelats gate',
        // ro
        'din OTP:',
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Trip status" => "Trip status",
            //            "Canceled" => "",
            "Departure time"           => "Departure time",
            "Arrival time"             => "Arrival time",
            "Flight"                   => 'Flight',
            "Flight confirmation"      => "Flight confirmation",
            "Add manually"             => "Add manually",
            "Operated by"              => 'Operated by',
            "Check airport departures" => 'Check airport departures',
        ],
        "fr" => [
            "Trip status"              => "Statut de «",
            "Canceled"                 => "Annulé",
            "Departure time"           => "Heure de départ",
            "Arrival time"             => "Heure d’arrivée",
            "Flight"                   => 'Vol',
            "Flight confirmation"      => "Confirmation de vol",
            "Add manually"             => "Ajouter manuellement",
            "Operated by"              => 'Exploité par',
            "Check airport departures" => 'Consulter les infos de l’aéroport',
        ],
        "pt" => [
            "Trip status"              => ["Status: '", "Estado de Viagem"],
            "Canceled"                 => "Cancelado",
            "Departure time"           => ["Horário de partida", "Hora de partida"],
            "Arrival time"             => ["Horário de chegada", "Hora de chegada"],
            "Flight"                   => 'Voo',
            "Flight confirmation"      => ["Confirmação de voo", "Confirmação do voo"],
            "Add manually"             => "Adicionar manualmente",
            "Operated by"              => 'Operado por',
            "Check airport departures" => 'Verifique as informações do aeroporto',
        ],
        "es" => [
            "Trip status"         => "Estado de",
            "Canceled"            => "Cancelado",
            "Departure time"      => "Hora de salida",
            "Arrival time"        => "Hora de llegada",
            "Flight"              => 'Vuelo',
            "Flight confirmation" => "Confirmación de vuelo",
            //            "Add manually" => "Adicionar manualmente",
            "Operated by" => 'Operado por',
            //            "Check airport departures" => '',
        ],
        "de" => [
            "Trip status"         => "Status für",
            "Canceled"            => "Storniert",
            "Departure time"      => "Abflugzeit",
            "Arrival time"        => "Ankunftszeit",
            "Flight"              => 'Flug',
            "Flight confirmation" => "Flugbestätigung",
            "Add manually"        => "Manuell hinzufügen",
            "Operated by"         => 'Durchgeführt von',
            //            "Check airport departures" => '',
        ],
        "pl" => [
            "Trip status" => "Status podróży",
            //            "Canceled" => "Storniert",
            "Departure time"      => "Godzina wylotu",
            "Arrival time"        => "Godzina przylotu",
            "Flight"              => 'Lot',
            "Flight confirmation" => "Potwierdzenie lotu",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => 'Obsługuje',
            //            "Check airport departures" => '',
        ],
        "it" => [
            "Trip status"         => "Stato di",
            "Canceled"            => "Annullata",
            "Departure time"      => "Orario di partenza",
            "Arrival time"        => "Orario di arrivo",
            "Flight"              => 'Volo',
            "Flight confirmation" => "Conferma del volo",
            "Add manually"        => "Aggiungi manualmente",
            "Operated by"         => 'Operato da',
            //            "Check airport departures" => '',
        ],
        "ja" => [
            "Trip status" => "最新情報",
            //            "Canceled" => "",
            "Departure time"      => "出発時刻",
            "Arrival time"        => "到着時刻",
            "Flight"              => 'フライト',
            "Flight confirmation" => "フライト確認",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => '運航会社：',
            //            "Check airport departures" => '',
        ],
        "nl" => [
            "Trip status"         => "Status Trip:",
            // "Canceled"            => "Annullata",
            "Departure time"      => "Vertrektijd",
            "Arrival time"        => "Aankomsttijd",
            "Flight"              => 'Vlucht',
            "Flight confirmation" => "Vluchtbevestiging",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => 'Uitgevoerd door',
            //            "Check airport departures" => '',
        ],
        "tr" => [
            "Trip status"         => "Seyahati durumu",
            // "Canceled"            => "Annullata",
            "Departure time"      => "Kalkış saati",
            "Arrival time"        => "Varış saati",
            "Flight"              => 'Uçuş',
            "Flight confirmation" => "Uçuş onayı",
            "Add manually"        => "Manuel olarak ekleyin",
            "Operated by"         => 'Düzenleyen',
            //            "Check airport departures" => '',
        ],
        "sv" => [
            "Trip status"         => "Status för", // Status för Do domu czerwiec 2023
            // "Canceled"            => "Annullata",
            "Departure time"      => "Avgångstid",
            "Arrival time"        => "Ankomsttid",
            "Flight"              => 'Flight',
            "Flight confirmation" => "Flygbekräftelse",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => 'Flygs av',
            //            "Check airport departures" => '',
        ],
        "ro" => [
            "Trip status"         => "Statusul", // Status för Do domu czerwiec 2023
            // "Canceled"            => "Annullata",
            "Departure time"      => "Ora plecării",
            "Arrival time"        => "Ora sosirii",
            "Flight"              => 'Zbor',
            "Flight confirmation" => "Confirmare zbor",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => 'Operat de',
            //            "Check airport departures" => '',
        ],
        "cs" => [
            "Trip status"         => "Aktuální stav ", // Status för Do domu czerwiec 2023
            // "Canceled"            => "Annullata",
            "Departure time"      => "Čas odletu",
            "Arrival time"        => "Čas příletu",
            "Flight"              => 'Let',
            "Flight confirmation" => "Potvrzení letu",
            //            "Add manually" => "Manuell hinzufügen",
            "Operated by" => 'Dopravce:',
            //            "Check airport departures" => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.kayak.')]")->length < 3) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Trip status']) && !empty($dict['Departure time']) && !empty($dict['Arrival time'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Trip status'])}]")->length > 0
                && $this->http->XPath->query("//*[descendant::text()[normalize-space()][1][{$this->eq($dict['Departure time'])}]]/following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($dict['Arrival time'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Trip status']) && !empty($dict['Departure time'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Trip status'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Departure time'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight confirmation'))}] and count(descendant::text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through') or self::s])]) = 2]/descendant::text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through') or self::s])][2]",
            null, true, "/^\s*([A-Z\d, \-]{5,})\s*$/");

        if (empty($conf) && $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight confirmation'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Add manually'))}]")) {
            $f->general()
                ->noConfirmation();
        } else {
            $conf = array_filter(preg_split("/\s*,\s*/", $conf));

            foreach ($conf as $c) {
                $f->general()
                    ->confirmation($c);
            }
        }

        // Segments

        $s = $f->addSegment();

        $status = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Trip status'))}]/following::text()[{$this->contains($this->t('Canceled'))}][following::text()[{$this->eq($this->t('Departure time'))}]])[1]");

        if (!empty($status)) {
            $s->extra()
                ->status($status)
                ->cancelled();
        }

        // Airline
        $flight = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight'))}] and count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]");

        if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;
        }

        $operator = $this->http->FindSingleNode("//td[descendant::text()[normalize-space()][1][{$this->eq($this->t('Operated by'))}] and count(descendant::text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]");

        if (preg_match("/.+ ([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?(\d{1,5})\s*$/", $operator, $m)) {
            $s->airline()
                ->carrierName($m[1])
                ->carrierNumber($m[2]);
        } elseif (!empty($operator)) {
            $s->airline()->operator($operator);
        }

        // Route
        $airports = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Departure time'))}]/preceding::tr[not(.//tr)][2]/ancestor-or-self::*[count(.//text()[normalize-space()]) = 4 and count(.//img) = 1][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*(?<depCode>[A-Z]{3})\n(?<arrCode>[A-Z]{3})\n(?<depName>.+)\n(?<arrName>.+)$/", $airports, $m)) {
            $s->departure()
                ->code($m['depCode'])
                ->name($m['depName']);

            $s->arrival()
                ->code($m['arrCode'])
                ->name($m['arrName']);
        }

        $date = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::*[not({$this->contains($this->t('Arrival time'))})][last()]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));

        if (preg_match("/^\s*{$this->preg_implode($this->t('Departure time'))}\s*(?:\n.+){0,2}\n(.*\d{4}.*\n\s*\d{1,2}[:.]\d{2}.*)\s*$/ui", $date, $m)) {
            // Departure time
            // Thu Mar 16 2023
            // 9:55 am CDT
            $s->departure()
                ->date($this->normalizeDate($m[1]));
        }

        $date = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Arrival time'))}]/ancestor::*[not({$this->contains($this->t('Departure time'))})][last()]//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]"));

        if (preg_match("/^\s*{$this->preg_implode($this->t('Arrival time'))}\s*(?:\n.+){0,2}\n(.*\d{4}.*\n\s*\d{1,2}[:.]\d{2}.*)$/ui", $date, $m)) {
            $s->arrival()
                ->date($this->normalizeDate($m[1]));
        } elseif (preg_match("/^\s*{$this->preg_implode($this->t('Arrival time'))}\s*(?:\n.+){0,2}\n{$this->preg_implode($this->t('Check airport departures'))}\n.+$/ui", $date, $m)) {
            $s->arrival()
                ->noDate();
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('normalizeDate $str = '.print_r( $str,true));
        // mar jun. 13 2023  2:45 p. m. IST  ->  mar jun. 13 2023  2:45 pm IST
        $str = preg_replace("/ ([apAP])\. ?[mP]\.(?:\s*[A-Z]{3,4})?\s*$/", '$1m', $str);
        $in = [
            // Thu Mar 16 2023  9:55 am
            // mar jun. 13 2023   2:45 p. m. IST
            "/^\s*[[:alpha:]]+\s+([[:alpha:]]+)[.]?\s+(\d+)\s+(\d{4})\s+(\d+)[:.](\d+(?:\s*[ap][m])?)(\s+[A-Z]{3,4})?\s*$/ui",
            // mer. 15 mars 2023 14:25
            // sex 17 mar 2023  18:40 BRT
            // Di. 14. März 2023  13:30 CET
            // niedz., 5 mar 2023   09:50 CET
            "/^\s*[[:alpha:]]+[.,]*\s+(\d+)\.?\s+([[:alpha:]]+)\.?\s+(\d{4})\s+(\d+)[:.](\d+(?:\s*[ap]\.?[m]\.?)?)(?:\s+[A-Z]{3,4})?\s*$/ui",
            // 2023年3月9日 木 07:40 JST
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s+[[:alpha:]]+\s+(\d+:\d+(?:\s*[ap]\.?[m]\.?)?)(?:\s+[A-Z]{3,4})?\s*$/ui",
        ];
        $out = [
            "$2 $1 $3, $4:$5",
            "$1 $2 $3, $4:$5",
            "$1-$2-$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace('.', '', $str);

        // $this->logger->debug('normalizeDate $str 2 = '.print_r( $str,true));

        if (preg_match("#^\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (
            preg_match("/^\d{1,2}\s+[[:alpha:]]+\s+\d{4}\s*,\s*\d{1,2}:\d{2}(?:\s*[ap]m)?$/i", $str)
            || preg_match("/^\d{4}-\d{1,2}-\d{1,2}\s*,\s*\d{1,2}:\d{2}(?:\s*[ap]m)?$/i", $str)
        ) {
            return strtotime($str);
        }

        return null;
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

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
