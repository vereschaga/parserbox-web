<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

class It5545467 extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-2813039.eml, jetblue/it-2813179.eml, jetblue/it-2868828.eml, jetblue/it-3011093.eml, jetblue/it-3100021.eml, jetblue/it-3135252.eml, jetblue/it-3282823.eml, jetblue/it-4115553.eml, jetblue/it-4186622.eml, jetblue/it-4213024.eml, jetblue/it-5545467.eml, jetblue/it-5545485.eml, jetblue/it-5885068.eml, jetblue/it-6240892.eml";

    public $reSubject = [
        'es' => ['Tu próximo viaje'],
        'en' => ['Your itinerary for your upcoming trip'],
    ];

    public $langDetectors = [
        'es' => ['Tu numero de confirmación es'],
        'en' => ['Your confirmation code is'],
    ];

    public static $dictionary = [
        'es' => [
            'Your confirmation code is' => 'Tu numero de confirmación es',
            'ARRIVES'                   => 'LLEGA',
            'FLIGHT'                    => 'VUELO',
            'FREQUENT'                  => 'FRECUENTE',
            'to'                        => 'to',
            'Ticket number(s)'          => 'Numero(s) de boleto',
        ],
        'en' => [],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'nameCode' => '/^(?<name>.*\w.*?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/u', // LONG BEACH, CA (LGB)
            'code'     => '/\(\s*([A-Z]{3})\s*\)$/u', // (LGB)
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your confirmation code is"), null, "#^([A-Z\d]{5,7})(?:\s*\[checkin\.jetblue\.com\])?$#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='This is not your boarding pass.']/preceding::text()[normalize-space()][1]", null, true, "#^([A-Z\d]{5,7})(?:\s*\[checkin\.jetblue\.com\])?$#");
        }

        // Passengers
        $it['Passengers'] = array_unique(array_filter($this->http->FIndNodes("//text()[normalize-space(.)='" . $this->t("ARRIVES") . "']/ancestor::tr[contains(., '" . $this->t("FLIGHT") . "')][1]/following::table[.//td[2]][1]//tr/td[9]/descendant::td[normalize-space(.)][1]//text()[normalize-space(.)]", null, "#.{4,}#")));

        // TicketNumbers
        $it['TicketNumbers'] = array_unique(array_filter($this->http->FIndNodes("//text()[normalize-space(.)='" . $this->t("Ticket number(s)") . "']/following::table[normalize-space(.)][1]//td[2]", null, "#^[\d ]{4,}$#")));

        // AccountNumbers
        $it['AccountNumbers'] = array_unique(array_filter($this->http->FIndNodes("//text()[normalize-space(.)='" . $this->t("ARRIVES") . "']/ancestor::tr[contains(., '" . $this->t("FREQUENT") . "')][1]/following::table[.//td[2]][1]//tr/td[9]/descendant::td[normalize-space(.)][3]//text()[normalize-space(.)]", null, "#^([A-Z\d\s+]+)$#")));

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode("//text()[normalize-space(.)='TOTAL']/following::tr[normalize-space(.) and ./td[3]][1]/td[last()]");

        if (preg_match('/^([A-Z]{3})(\d[,.\d]*)/', $payment, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->amount($matches[2]);
            // Tax
            $tax = $this->http->FindSingleNode("//text()[normalize-space(.)='TOTAL']/following::tr[normalize-space(.) and ./td[3]][1]/td[normalize-space(.)][last()-1]");

            if (preg_match('/^' . preg_quote($matches[1], '/') . '(\d[,.\d]*)/', $tax, $m)) {
                $it['Tax'] = $this->amount($m[1]);
            }
            // BaseFare
            $fare = $this->http->FindSingleNode("//text()[normalize-space(.)='TOTAL']/following::tr[normalize-space(.) and ./td[3]][1]/td[normalize-space(.)][last()-2]");

            if (preg_match('/^' . preg_quote($matches[1], '/') . '(\d[,.\d]*)/', $fare, $m)) {
                $it['BaseFare'] = $this->amount($m[1]);
            }
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t("ARRIVES") . "']/ancestor::tr[contains(., '" . $this->t("FLIGHT") . "')][1]/following::table[.//td[2]][1]//tr[./td[9]]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]", $root));

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode('./td[7]/descendant::text()[normalize-space(.)][1]', $root, true, '/^(\d+)$/');

            // AirlineName
            $airline = $this->http->FindSingleNode('./td//img/@alt', $root);

            if ($airline) {
                $itsegment['AirlineName'] = trim(str_replace('®', '', $airline));
            } elseif (!empty($itsegment['FlightNumber'])) {
                $itsegment['AirlineName'] = 'JetBlue';
            }

            $routeTexts = $this->http->FindNodes('./td[5]/descendant::text()[normalize-space(.)]', $root);
            $routeText = implode(' ', $routeTexts);

            if ($routeText) {
                $routes = preg_split('/\s*' . $this->t('to') . '\s*/', $routeText);
                // DepName
                // DepCode
                if (preg_match($patterns['nameCode'], $routes[0], $matches)) {
                    $itsegment['DepName'] = $matches['name'];
                    $itsegment['DepCode'] = $matches['code'];
                } elseif (preg_match($patterns['code'], $routes[0], $matches)) {
                    $itsegment['DepCode'] = $matches[1];
                } else {
                    $itsegment['DepName'] = $routes[0];
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                // ArrName
                // ArrCode
                if (preg_match($patterns['nameCode'], $routes[1], $matches)) {
                    $itsegment['ArrName'] = $matches['name'];
                    $itsegment['ArrCode'] = $matches['code'];
                } elseif (preg_match($patterns['code'], $routes[1], $matches)) {
                    $itsegment['ArrCode'] = $matches[1];
                } else {
                    $itsegment['ArrName'] = $routes[1];
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            // DepDate
            if (!empty($date)) {
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root), $date);
            }

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode('./td[9]/descendant::td[normalize-space(.)][1]/following-sibling::td[last()]', $root);

            if ($terminalDep) {
                $itsegment['DepartureTerminal'] = $terminalDep;
            }

            // ArrDate
            if (!empty($date)) {
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $date);
            }

            // Operator
            $operator = $this->http->FindSingleNode('.//text()[starts-with(normalize-space(.),"Operated by")]', $root, true, '/Operated by\s+(.+)/');

            if ($operator) {
                $itsegment['Operator'] = $operator;
            }

            // Seats
            $seatTexts = $this->http->FindNodes('./td[9]/descendant::td[normalize-space(.)][1]/following-sibling::td[normalize-space(.)][1]//text()[normalize-space(.)]', $root, '/^\d{1,3}[A-Z]$/');
            $seatValues = array_values(array_filter($seatTexts));

            if (empty($seatValues[0])) {
                $seatTexts = $this->http->FindNodes('./td[9]/descendant::td[normalize-space(.)][1]/following-sibling::td[normalize-space(.)][2]//text()[normalize-space(.)]', $root, '/^\d{1,3}[A-Z]$/');
                $seatValues = array_values(array_filter($seatTexts));
            }

            if (empty($seatValues[0])) {
                $seatTexts = $this->http->FindNodes("./td[9]/descendant::td[normalize-space(.)][1]//text()[normalize-space(.)]", $root, '/^\d{1,3}[A-Z]$/');
                $seatValues = array_values(array_filter($seatTexts));
            }

            if (!empty($seatValues[0])) {
                $itsegment['Seats'] = $seatValues;
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'JetBlue Reservations') !== false
            || stripos($from, '@email.jetblue.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"All travel on JetBlue") or contains(normalize-space(.),"Download the JetBlue mobile app") or contains(.,"@jetblue.com") or contains(.,"www.jetblue.com") or contains(.,"www.JetBlue.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//email.jetblue.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'YourItinerary' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        if (preg_match('#^([^\d\s]+),\s+([^\d\s]+)\s+(\d+)$#', $str, $m)) {
            $dayOfWeekInt = WeekTranslate::number1(trim($m[1]));
            $str = EmailDateHelper::parseDateUsingWeekDay($m[3] . ' ' . $m[2] . ' ' . date("Y", $this->date), $dayOfWeekInt);
        }

        return $str;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
