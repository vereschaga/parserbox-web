<?php

namespace AwardWallet\Engine\pegasus\Email;

class ETicketInfo extends \TAccountChecker
{
    public $mailFiles = "pegasus/it-4048229.eml, pegasus/it-4062816.eml, pegasus/it-4099443.eml, pegasus/it-4320643.eml, pegasus/it-5456513.eml, pegasus/it-5457906.eml, pegasus/it.eml";

    protected $lang = null;

    protected $langDetectors = [
        'de' => [
            'Pegasus–Informationen zum elektronischen Ticket',
        ],
        'it' => [
            'Informazioni sul biglietto elettronico Pegasus',
        ],
        'tr' => [
            'Pegasus Elektronik Bilet Bilgisi',
        ],
        'da' => [
            'Oplysninger om elektroniske billetter fra Pegasus',
        ],
        'en' => [
            'Pegasus Electronic Ticket Information',
        ],
    ];

    protected $dict = [
        'Vielen Dank' => [
            'it' => 'Grazie per',
            'tr' => 'Pegasus Hava',
            'da' => 'Tak, fordi',
            'en' => 'Thank you for',
        ],
        'Der Reservierungscode' => [
            'it' => 'Il codice',
            'tr' => 'Detaylari asagida',
            'da' => 'Din reservationskode',
            'en' => 'Your reservation code',
        ],
        'Gastinformation' => [
            'it' => 'Informazioni sul passeggero',
            'tr' => 'Misafir Bilgileri',
            'da' => 'Passageroplysninger',
            'en' => 'Guest Information',
        ],
        'Datum' => [
            'it' => 'Data del volo',
            'tr' => 'Uçus Tarihi',
            'da' => 'Dato for flyvning',
            'en' => 'Flight Date',
        ],
        'Abflug' => [
            'it' => 'Ora part.',
            'tr' => 'Kalkis Saati',
            'da' => 'Afr. tidspunkt',
            'en' => 'Dep.Time',
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'reservation@flypgs.com') !== false
            || isset($headers['subject'])
                && (preg_match('/Flugbestätigung.+Pegasus\s+Airlines/i', $headers['subject'])
                    || preg_match('/Ticket.+Pegasus\s+Airlines/i', $headers['subject'])
                    || preg_match('/Pegasus\s+Havayollari\s+Bilet\s+Bilginiz/i', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->detectLang();

        return $this->http->XPath->query('//a[contains(@href,"//www.flypgs.com")]')->length > 0
            && isset($this->lang);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flypgs.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ETicketInfo',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['de', 'it', 'tr', 'da', 'en'];
    }

    public static function getEmailTypesCount()
    {
        return 5;
    }

    protected function detectLang()
    {
        unset($this->lang);

        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($this->http->Response['body'], $line) !== false) {
                    $this->lang = $lang;

                    return;
                }
            }
        }
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function ParseEmail()
    {
        $this->detectLang();
        $it = [];
        $it['Kind'] = 'T';                                                                                                                                                                                                       //*=strong
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[starts-with(normalize-space(.),"' . $this->translate('Vielen Dank') . '") and contains(.,"' . $this->translate('Der Reservierungscode') . '") and not(.//td)]//*[string-length(text())=6]', null, true, '/([A-Z\d]{6})/');
        $rows = $this->http->XPath->query('//tr[contains(.,"' . $this->translate('Datum') . '") and contains(.,"' . $this->translate('Abflug') . '") and not(.//tr)]/following-sibling::tr');

        foreach ($rows as $row) {
            $seg = [];
            $date = $this->http->FindSingleNode('./td[2]', $row, true, '/(\d{2}\/\d{2}\/\d{2,4})/');
            $timeDep = $this->http->FindSingleNode('./td[6]', $row, true, '/(\d{2}:\d{2})/');
            $timeArr = $this->http->FindSingleNode('./td[7]', $row, true, '/(\d{2}:\d{2})/');

            if ($date && $timeDep && $timeArr) {
                $date = strtotime(str_replace('/', '.', $date));
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }
            $flight = $this->http->FindSingleNode('./td[3]', $row);

            if (preg_match('/^([^\d\s]{2,3})\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $seg['DepName'] = $this->http->FindSingleNode('./td[4]', $row);
            $seg['ArrName'] = $this->http->FindSingleNode('./td[5]', $row);
            $seg['Cabin'] = $this->http->FindSingleNode('./td[8]', $row, true, '/^(\w{1})$/');
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }
        $it['Passengers'] = [];
        $passengers = $this->http->XPath->query('//tr[contains(.,"' . $this->translate('Gastinformation') . '") and not(.//tr)]/following-sibling::tr');

        foreach ($passengers as $p) {
            $it['Passengers'][] = $this->http->FindSingleNode('./td[2]', $p) . ' ' . $this->http->FindSingleNode('./td[3]', $p);
        }

        return $it;
    }
}
