<?php
/*
 * bcdtravel,
 * Unique colors: #62b4e8, #e4f0fa
 */

namespace AwardWallet\Engine\klm\Email;

// TODO: merge with parsers klm/ConfirmationBillet (in favor of klm/ConfirmationBillet)

class BookingHtml2017Nl extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();
        $this->parseReservation($this->htmlToText($this->findCutSection($text, null, ['Dit document is GEEN ticket,']), true));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'info@service.airfrance.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Bevestigende e-mail van Flying Blue') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'KLM en wij danken u daarvoor') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@service.airfrance.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['nl'];
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseReservation($text)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->match('/Boekingsnummer:?\s*:\s*([A-Z\d]+)/', $text);
        $this->result['Status'] = $this->match('/Status\s*:\s*(\w+)/', $text);
        $this->result['TicketNumbers'] = $this->http->FindNodes('//*[contains(text(), "Ticketnummer")]/ancestor::td[1]/following-sibling::td[1]', null, '/\d+/');
        $this->result['Passengers'] = $this->http->FindNodes('//*[contains(text(), "Ticketnummer")]/ancestor::table[1]/preceding-sibling::table[1]', null, '/[[:alpha:]\s]+/');
        $this->result['AccountNumbers'] = $this->http->FindNodes('//*[contains(text(), "Frequent Flyer kaart")]/ancestor::td[1]/following-sibling::td[1]', null, '/[\w\s]+/');
        $this->result['SpentAwards'] = $this->match('/Prijs van uw ticket\s*:\s*(\d+\s*Miles)/', $text);

        if (preg_match('/Totaal online betaalde bedrag\s*:\s*([\d.,]+)\s*([A-Z]{3})/', $text, $matches)) {
            $this->result['TotalCharge'] = $this->isSum($matches[1]);
            $this->result['Currency'] = $matches[2];
        }

        $this->parseSegments($this->findCutSection($text, 'Gekozen vluchten', ['Passagiers']));
    }

    protected function parseSegments($text)
    {
        // Uw vlucht  : Amsterdam - Krakau
        foreach (preg_split('/Uw vlucht\s*:\s*[[:alpha:]\s]+\s+-\s+[[:alpha:]\s]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $text) {
            $segment = [];

            $date = $this->translateMonth($this->match('/^\s*\d+ \w+ \d{4}/', $text));

            // 16:15	Krakau, John Paul II - Balice (KRK), POLEN
            if (!empty($date) && preg_match_all('/(\d+:\d+)\s+(.+?)\s+\(([A-Z]{3})\)/s', $text, $matches, PREG_SET_ORDER)) {
                $segment['DepDate'] = strtotime($date . ', ' . $matches[0][1]);
                $segment['DepName'] = $this->normalizeText($matches[0][2]);
                $segment['DepCode'] = $matches[0][3];

                $segment['ArrDate'] = strtotime($date . ', ' . $matches[1][1]);
                $segment['ArrName'] = $this->normalizeText($matches[1][2]);
                $segment['ArrCode'] = $matches[1][3];
            }

            // (KRK), POLEN	KL1995 (Embraer 190)
            if (preg_match('/\([A-Z]{3}\).+?([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+\((.+?)\)/s', $text, $matches)) {
                $segment['AirlineName'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
                $segment['Aircraft'] = trim($matches[3]);
            }

            if (preg_match('/Reisklasse:\s*(\w+)\s*Duur:\s*([\w\s]+)\s*Maaltijd\(\w+\) aan boord:\s*(\w+)/', $text, $matches)) {
                $segment['Cabin'] = $matches[1];
                $segment['Duration'] = $this->normalizeText($matches[2]);
                $segment['Meal'] = $matches[3];
            }

            if (preg_match('/Uitgevoerd door\s*:\s*(\w+)\s*Terminal\s*:\s*(.*?)\s*Bagagevrijstelling\s*:/', $text, $matches)) {
                $segment['Operator'] = $matches[1];
                $segment['DepartureTerminal'] = trim($matches[2]);
            }

            if (!empty($segment)) {
                $this->result['TripSegments'][] = $segment;
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function translateMonth($string)
    {
        return $date = str_ireplace(['Januari', 'Februari', 'Februari', 'April', 'Mei', 'Juni', 'Juni', 'Augustus', 'September', 'Oktober', 'Oktober', 'December'], ['january', 'february', 'march', 'april', 'may', 'june', 'luly', 'august', 'september', 'october', 'november', 'december'], $string);
    }

    protected function isSum($string)
    {
        if (!empty($string)) {
            $num = trim(preg_replace('/[^\d.\s]+/', '', str_replace(',', '.', $string)));

            if (is_numeric($num)) {
                return (float) $num;
            }
        }
    }

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function htmlToText($string, $viewNormal = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($viewNormal) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }
}
