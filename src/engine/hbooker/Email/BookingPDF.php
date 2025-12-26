<?php

namespace AwardWallet\Engine\hbooker\Email;

class BookingPDF extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "hotelbooker.org";
    public $reSubject = [
        "de" => "BIT.Flow | Buchung abgeschlossen:",
    ];
    public $reBody = 'hotelbooker.org';
    public $reBody2 = [
        "de" => "Buchungsnummer:",
    ];

    public static $dictionary = [
        "de" => [
        ],
    ];

    public $lang = "";

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $html = null;

                if (($html = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    foreach ($this->reBody2 as $lang => $re) {
                        if (strpos($html, $re) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                    $its = $this->parseEmail($html);

                    foreach ($its as $it) {
                        $itineraries[] = $it;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($body, $this->reBody) !== false) {
                foreach ($this->reBody2 as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function parseEmail($plainText)
    {
        $it = [];

        $it['Kind'] = "R";

        $subtext = $this->findСutSection($plainText, $this->t('RESERVIERUNG'), $this->t('Hoteldaten'));

        $it['ConfirmationNumber'] = $this->re("#Buchungsnummer:\s*([A-Z\d]+)#", $subtext);
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Buchungsdatum:\s*(.*?)\s*Stornierungscode#s", $subtext)));

        $subtext = $this->findСutSection($plainText, $this->t('Hoteldaten'), $this->t('Ihre Buchungsdaten'));

        $it['HotelName'] = $this->re("#Hotelname:\s*(.*?)\n#s", $subtext);
        $it['Address'] = $this->re("#Straße:\s*(.*?)\n#s", $subtext) . ', ' . $this->re("#PLZ\/Ort:\s*(.*?)\n#s", $subtext);
        $it['Phone'] = $this->re("#Telefon:\s*(.*?)\n#s", $subtext);
        $it['Fax'] = $this->re("#Fax:\s*(.*?)\n#s", $subtext);

        $subtext = $this->findСutSection($plainText, 'Ihre Buchungsdaten', 'Übernachtung');

        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Anreise:\s*(.*?)\n#s", $subtext)));
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Abreise:\s*(.*?)\n#s", $subtext)));
        $it['Guests'] = $this->re("#Personen:\s*(.*?)\n#s", $subtext);
        $node = $this->getTotalCurrency($this->re("#Preis:\s*(.*?)\n#s", $subtext));
        $it['Total'] = $node['Total'];
        $it['Currency'] = $node['Currency'];

        $subtext = $this->findСutSection($plainText, 'Übernachtung', 'Ratenbeschreibung');

        $it['Rooms'] = $this->re("#Zimmer:\s*Zimmer:\s*.+?\d{4}\n(\d+)#s", $subtext);
        $it['RoomType'] = trim($this->re("#Zimmerbeschreibung:\s*(.+)#s", $subtext));

        $subtext = $this->findСutSection($plainText, 'Anreisende Personen', 'Angaben zum Auftraggeber');

        if (preg_match_all("#Nachname:\s*(.*?)\n#s", $subtext, $sname) && preg_match_all("#Vorname:\s*(.*?)\n#s", $subtext, $name)) {
            if (count($sname[1]) === count($name[1])) {
                foreach ($sname[1] as $i => $m) {
                    $it["GuestNames"][] = $m . ' ' . $name[1][$i];
                }
            }
        }

        $itineraries[] = $it;

        return $itineraries;
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
        $in = [
            "#^(\d+\s+\S+\s+\d{4})$#",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
