<?php

namespace AwardWallet\Engine\venere\Email;

use AwardWallet\Engine\MonthTranslate;

class HotelReservation extends \TAccountChecker
{
    use \PriceTools;

    public $mailFiles = "venere/it-4721551.eml";

    public $reBody = [
        'it' => ['Grazie per aver', 'Prenotazione'],
    ];
    public $reSubject = [
        'it' => ['Il tuo taccuino di viaggio', 'Prenotazione'],
    ];
    public $lang = 'it';
    public static $dict = [
        'it' => [
            'ConfirmationNumber' => 'Numero di Prenotazione:',
            'CheckInDate'        => 'Giorno di arrivo:',
            'CheckOutDate'       => 'Giorno di partenza:',
            'Room'               => 'Tipologia di camera:',
            'Total'              => 'Prezzo totale:',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $itineraries = $this->parseEmail();

        return [
            'emailType'  => 'HotelReservation',
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach ($this->reSubject as $ss) {
                if (stripos($headers['subject'], $ss[0]) !== false || stripos($headers['subject'], $ss[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "venere.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'R'];
        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//tr[td[contains(text(),'" . $this->t('ConfirmationNumber') . "')]]", null, true, "#\:\s*(.+)#");
        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'star_')]/preceding::a[1]");
        // Address, Phone
        $it['Address'] = $this->http->FindSingleNode("//img[contains(@src, 'star_')]/ancestor::tr[1]/following-sibling::tr[1]");
        $it['Phone'] = $this->http->FindSingleNode("//img[contains(@src, 'star_')]/ancestor::tr[1]/following-sibling::tr[2]/td[1]/text()[normalize-space(.)]");
        // CheckInDate
        $CheckInDate = $this->getNode('Giorno di arrivo:');
        $it['CheckInDate'] = $this->getDate($CheckInDate);
        // CheckOutDate
        $CheckOutDate = $this->getNode('Giorno di partenza:');
        $it['CheckOutDate'] = $this->getDate($CheckOutDate);

        // GuestNames
        // Guests

        // Rooms
        // Rate
        // RoomType
        $room = $this->getNode('Tipologia di camera:');

        if (preg_match("#(?<rooms>[\d,]+)\s*✕\s*(?<type>.+)#", $room, $m)) {
            $it['Rooms'] = $m['rooms'];
            $it['RoomType'] = $m['type'];
        }
        // Total, Currency
        $Total = $this->getNode('Prezzo totale:');
        $it['Total'] = cost($Total);
        $it['Currency'] = currency($Total);
        // Status

        return [$it];
    }

    protected function getNode($str)
    {
        return $this->http->FindSingleNode("//td[b[contains(.,'$str')]]/following-sibling::td[1]");
    }

    protected function getDate($nodeForDate)
    {
        $res = '';

        if (preg_match("#(?<dayOfWeek>.+)\s+(?<day>[\d]+)\s+(?<month>.+)\s+(?<year>\d{4})#", $nodeForDate, $check)) {
            $res = $check['day'] . ' ' . MonthTranslate::translate($check['month'], $this->lang) . ' ' . $check['year'];
        }

        return $res;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
