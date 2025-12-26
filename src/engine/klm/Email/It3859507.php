<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It3859507 extends \TAccountChecker
{
    public $mailFiles = "klm/it-1748899.eml, klm/it-1994558.eml, klm/it-2569023.eml, klm/it-27853341.eml, klm/it-4778193.eml, klm/it-4789882.eml, klm/it-4886608.eml, klm/it-5.eml, klm/it-5015192.eml, klm/it-5162785.eml, klm/it-5717828.eml"; // +1 bcdtravel(html)[da]

    public $reSubject = [
        "en" => "My booking information",
        "ru" => "Инофрмация о моем бронировании",
        "de" => "Min bestillingsinformasjon",
        "da" => "Min bookinginformation",
        "fr" => "Données de ma réservation",
        'es' => 'Información de mi reserva',
        'pt' => 'Informações da “Minha reserva',
        'ko' => '나의 예약 정보',
        'it' => 'Informazioni sulla mia prenotazione',
        'no' => 'Min bestillingsinformasjon',
    ];

    public $reBody2 = [
        "en" => "My Trip",
        "nl" => "Rapporteer misbruik",
        "ru" => "Мое путешествие",
        "de" => "Meine Reise",
        "da" => "Min Rejse",
        "fr" => "Mon Voyage",
        'es' => 'Mi Viaje',
        'pt' => 'Minha Viagem',
        'ko' => '나의 예약',
        'it' => 'Il Mio Viaggio',
        'no' => 'Min Reise',
    ];

    public static $dictionary = [
        "en" => [],
        "nl" => [
            "Name:"          => "Naam:",
            "Booking status:"=> "Boekingsstatus:",
            "Flight number:" => "Vluchtnummer:",
        ],
        "ru" => [
            "Name:"          => "Имя:",
            "Booking status:"=> "Статус бронирования:",
            "Flight number:" => "Номер рейса:",
        ],
        "de" => [
            "Name:"          => "Name:",
            "Booking status:"=> "Buchungsstatus:",
            "Flight number:" => "Flugnummer:",
        ],
        "da" => [
            "Name:"          => "Navn:",
            "Booking status:"=> "Status for booking:",
            "Flight number:" => "Flynummer:",
        ],
        "fr" => [
            "Name:"          => "Nom:",
            "Booking status:"=> "Statut de réservation:",
            "Flight number:" => "Numéro de vol:",
        ],
        'es' => [
            'Name:'           => 'Nombre:',
            'Booking status:' => 'Estado de la reserva:',
            'Flight number:'  => 'Número de vuelo:',
        ],
        'pt' => [
            'Name:'           => 'Nome:',
            'Booking status:' => 'Estado da reserva:',
            'Flight number:'  => 'Número do voo:',
        ],
        'ko' => [
            'Name:'           => '이름:',
            'Booking status:' => '예약 현황:',
            'Flight number:'  => '항공편 번호:',
        ],
        'it' => [
            'Name:'           => 'Nome:',
            'Booking status:' => 'Stato prenotazione:',
            'Flight number:'  => 'Numero volo:',
        ],
        'no' => [
            'Name:'           => 'Navn:',
            'Booking status:' => 'Bestillingsstatus:',
            'Flight number:'  => 'Rutenummer:',
        ],
    ];

    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'donotreply@klm.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"KLM.com")]')->length === 0) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query('//node()[contains(.,"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $body = preg_replace('/\s+/', ' ', $parser->getHTMLBody());
        $this->http->SetEmailBody($body);

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseEmail($email);
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

    protected function ParseEmail(Email $email)
    {
        $flight = $email->add()->flight();

        $flight->general()
            ->noConfirmation()
            ->travellers(array_unique(array_filter($this->http->FindNodes("//*[normalize-space(text())='" . $this->t("Name:") . "']/ancestor::td[normalize-space(.)='" . $this->t("Name:") . "'][last()]/../following-sibling::tr/td[2]//text()[normalize-space(.)]"))), true)
            ->status($this->getField($this->t("Booking status:")));

        $xpath = "//*[contains(text(),'" . $this->t("Flight number:") . "')]/ancestor::td[normalize-space(.)='" . $this->t("Flight number:") . "'][last()]/../following-sibling::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->alert('Segments root not found: ' . $xpath);
        }

        foreach ($segments as $root) {
            $segment = $flight->addSegment();
            $segment->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)])[1]", $root));

            $patterns = [
                'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後
            ];

            // DepDate
            $dateDep = '';
            $dateDepText = $this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)])[2]", $root);

            if ($dateDepText) {
                $dateDep = $this->normalizeDate($dateDepText);
            }
            $timeDep = $this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)])[3]", $root, true, "/^{$patterns['time']}$/");

            if ($dateDep && $timeDep) {
                $segment->departure()
                    ->date(strtotime($dateDep . ' ' . $timeDep));
            }

            $segment->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("(./td[4]//text()[normalize-space(.)])[1]", $root));

            // ArrDate
            if ($this->http->XPath->query('(./td[4]//text()[normalize-space(.)])[2]', $root)->length > 0) {
                $dateArr = '';
                $dateArrText = $this->http->FindSingleNode('(./td[4]//text()[normalize-space(.)])[2]', $root);

                if ($dateArrText) {
                    $dateArr = $this->normalizeDate($dateArrText);
                }
                $timeArr = $this->http->FindSingleNode('(./td[4]//text()[normalize-space(.)])[3]', $root, true, "/^{$patterns['time']}$/");

                if ($dateArr && $timeArr) {
                    $segment->arrival()
                        ->date(strtotime($dateArr . ' ' . $timeArr));
                }
            } else {
                $segment->arrival()
                    ->noDate();
            }

            // AirlineName
            // FlightNumber
            $flights = $this->http->FindSingleNode("./td[5]", $root);

            if (preg_match('/(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)/', $flights, $matches)) {
                $segment->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }
        }

        return true;
    }

    private function getField($field, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1])[{$n}]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^[^\d\W]{2,}\.?\s+(\d{1,2})\s+([^\d\W]{3,})\.?\s+(\d{4})$/u', $string, $matches)) {
            // mer. 6 janv. 2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^[^\d\W]+\s+(\d{1,2})\s+(\d{1,2})[^\d\W]+\s+(\d{4})$/u', $string, $matches)) {
            // 목 15 11월 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }
}
