<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassText2015 extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-4561076.eml, icelandair/it-4595078.eml, icelandair/it-14305323.eml";

    private $langDetectors = [
        'is' => ['Byrðing:'],
        'en' => ['Flight Boarding:'],
    ];

    private $lang = '';

    private static $dict = [
        'is' => [
            'Flight:' => 'Flug:',
        ],
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $textBody = $parser->getPlainBody();
            $textBody = $this->br2nl(preg_replace("#^>+([ ]+|$)#m", "", $textBody));
        } else {
            $textBody = strip_tags($this->br2nl($htmlBody));
        }

        $this->parseReservation($textBody);

        return [
            'emailType'  => 'BoardingPassText2015' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Your Icelandair Mobile Boarding Pass') !== false) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Boarding Pass Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Þú finnur brottfarakortið með eftirfarandi upplýsingum:") or contains(normalize-space(.),"Please find enclosed your boarding pass for the flight with the following details:")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@icelandair.is') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseReservation($text)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = CONFNO_UNKNOWN;
        $this->result['Passengers'] = [];
        $this->result['TicketNumbers'] = [];

        if (preg_match_all('/' . $this->opt($this->t('Flight:')) . '\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{2,4}\s*-\s*(?:.*\n){3}[\s\S]+?\n{2,}/u', $text, $matches)) {
            foreach ($matches[0] as $value) {
                // Ms Anuhi Ruiz Rivera: Checked In - Ticket number: 1082404275458
                if (preg_match('/\n\s*(.+?):\s*(.+?)[.\s]*-\s*Ticket number:\s*([\d-]+)/', $text, $matches)) {
                    $this->result['Passengers'][] = $matches[1];
                    $this->result['Status'] = $matches[2];
                    $this->result['TicketNumbers'][] = $matches[3];
                }

                $this->result['TripSegments'][] = $this->parseSement($value);
            }

            // Mr Mark Coles - Checked In
            if (empty($this->result['Passengers']) && preg_match('/(?:Mr|Miss|Mrs)\s+(.+?)\s*-\s*(.+)/', $text, $matches)) {
                $this->result['Passengers'][] = $matches[1];
            }

            $this->result['Passengers'] = array_unique($this->result['Passengers']);
            $this->result['TicketNumbers'] = array_unique($this->result['TicketNumbers']);
        }
    }

    private function parseSement($text)
    {
        /*
          Flight: FI614 - New York (JFK) - Reykjavik (KEF) - 6 OCT 2015|10/10/2014 - 20:40
          Ms Anuhi Ruiz Rivera: Checked In - Ticket number: 1082404275458
          Flight Boarding: 20:00
          Flight Arrival: 06:15
         */

        $pattern = '([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{2,4})'
            . '\s*-\s*(.+?)\s*\(([A-Z]{3})\)\s*-\s*(.+?)\s*\(([A-Z]{3})\)'
            . '\s*-\s*(?<date>.+?)\s*-\s*(\d+:\d+).*?'
            . '(?:Flight Arrival|Koma|flugs)[:]+\s*(\d+:\d+)';

        if (preg_match("/{$pattern}/us", $text, $matches)) {
            $matches['date'] = $this->normalizeDate($matches['date']);

            return [
                'AirlineName'  => $matches[1],
                'FlightNumber' => $matches[2],
                'DepName'      => $matches[3],
                'DepCode'      => $matches[4],
                'ArrName'      => $matches[5],
                'ArrCode'      => $matches[6],
                'DepDate'      => strtotime($matches['date'] . ' ' . $matches[8]),
                'ArrDate'      => strtotime($matches['date'] . ' ' . $matches[9]),
            ];
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\s*([^\d\W]{3,})\s*(\d{4})$/u', $string, $matches)) { // 7 OCT 2015
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert BR tags to new line.
     *
     * @param string The string to convert
     *
     * @return string The converted string
     */
    private function br2nl($string)
    {
        return preg_replace('/\s*<br.*?>\s*/i', "\n", $string);
    }
}
