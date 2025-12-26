<?php

namespace AwardWallet\Engine\goldcrown\Email;

class BookingHotelDe extends \TAccountChecker
{
    use \DateTimeTools;
    public const monthsNumber = 12;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookingHotelDe',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'bestwestern.dresden@macranderhotels.de') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'bestwestern.dresden@macranderhotels.de') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query(".//*[contains(normalize-space(.), 'Sehr geehrter Herr Qvaeschning, wir freuen uns sehr, dass Sie sich für das BEST WESTERN Macrander Hotel Dresden')]/span")->length > 0;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'R'];
        $it['HotelName'] = $this->getNode('Name');
        $it['ConfirmationNumber'] = $this->getNode('Reservierungsnummer');
        $it['CheckInDate'] = strtotime($this->getDate($this->getNode('Anreise')));
        $it['CheckOutDate'] = strtotime($this->getDate($this->getNode('Abreise')));
        $rate = $this->getNode('Preis');

        if (preg_match("#(\D{3}) (\S+) .+#", $rate, $math)) {
            $it['Currency'] = $math[1];
            $it['Rate'] = $math[2];
        }
        $it['Guests'] = $this->getNode('Gäste', "#(\d+) .+#");
        $it['Address'] = $this->http->FindSingleNode(".//td/p/b/span[contains(normalize-space(.), ' Hotel ')]/ancestor::p/span/text()[1]");
        $phoneFazNumber = $this->http->FindSingleNode(".//td/p/b/span[contains(normalize-space(.), ' Hotel ')]/ancestor::p/span/text()[2]");

        if (preg_match("#T: ([\S\s]+) F: ([\S\s]+)#", $phoneFazNumber, $m)) {
            $it['Phone'] = $m[1];
            $it['Fax'] = $m[2];
        }

        return [$it];
    }

    private function getNode($str, $regExp = null)
    {
        if (!$regExp) {
            return $this->http->FindSingleNode(".//span[contains(normalize-space(.), '{$str}:')]/ancestor::td[1]/following-sibling::td/p/span");
        } else {
            return $this->http->FindSingleNode(".//span[contains(normalize-space(.), '{$str}:')]/ancestor::td[1]/following-sibling::td/p/span", null, true, "{$regExp}");
        }
    }

    private function getDate($date)
    {
        $elems = $this->monthNames['de'];
        $langEn = $this->monthNames['en'];
        preg_match("#[\S\w\s]+ (?<day>\d{2})\s*\S*\s+(?<month>\D+) (?<year>\d{4})#", $date, $v);

        for ($j = 0; $j < self::monthsNumber; $j++) {
            if ($elems[$j] == $v['month']) {
                $month = preg_replace('#[\S\w]+#', $langEn[$j], $v['month']);
                $date = $month . ' ' . $v['day'] . ' ' . $v['year'];
            }
        }

        return $date;
    }
}
