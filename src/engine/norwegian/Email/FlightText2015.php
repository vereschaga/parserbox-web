<?php

namespace AwardWallet\Engine\norwegian\Email;

class FlightText2015 extends \TAccountChecker
{
    public $mailFiles = "norwegian/it-4653551.eml, norwegian/it-4653563.eml, norwegian/it-5157533.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->dateLetter = strtotime($parser->getDate());

        $this->result['Kind'] = 'T';

        $text = $this->htmlToText($this->findCutSectionToWords($parser->getHTMLBody(), ['We hope the offer', 'OFFERTEN', 'Deadline to make']));

        if (preg_match('/Bookingref:\s*([A-Z\d]{5,6})/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/(?:included tax:|avgifter blir|Totalprice inkl\. taxes:)\s*([A-Z]{3})\s*([\d,.\s]+)/', $text, $matches)) {
            $this->result['Currency'] = $matches[1];
            $this->result['TotalCharge'] = (float) str_replace([',', ' '], '', $matches[2]);
        }

        $this->parseSegments($text);

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'grupper@norwegian.com') !== false
                && isset($headers['subject'])
                && preg_match('/\(NorwRef:\s*\d+\)\s+Group:\s+/u', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Totalfare included tax:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@norwegian.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'sv', 'no'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    //====================================
    // Auxiliary methods
    //====================================

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
    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = '';
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
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseSegments($text)
    {
        $text = $this->findСutSection($text, 'Bookingref:', ['---------']);

        $reg = '([A-Z\d]{2})\s*(\d+)\s+(\d+\w+)\s+([A-Z]{3})\s*([A-Z]{3})';
        $reg .= '\s+(\d{4})\s+(\d{4})';

        if (preg_match_all("/{$reg}/", $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $value) {
                $segment = [];
                $segment['AirlineName'] = $value[1];
                $segment['FlightNumber'] = $value[2];
                $segment['DepCode'] = $value[4];
                $segment['ArrCode'] = $value[5];
                $segment += $this->increaseDate($this->dateLetter, $value[3], $value[6], $value[7]);
                $this->result['TripSegments'][] = $segment;
            }
        }
    }

    /**
     * <pre>Example:
     * findCutSectionToWords('start cut text', ['cut'])
     * // start cut
     * </pre>.
     */
    protected function findCutSectionToWords($input, $searchFinish = [])
    {
        foreach ($searchFinish as $value) {
            $pos = strpos($input, $value);

            if ($pos !== false) {
                return mb_substr($input, 0, $pos);
            }
        }

        return false;
    }

    protected function htmlToText($string)
    {
        return preg_replace('/<[^>]+>/', "\n", str_replace([" ", '<br>', '<br/>', '<br />', '&nbsp;'], " ", $string));
    }

    protected function increaseDate($dateLetter, $dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($dateSegment, $dateLetter));

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => strtotime($arrTime, $depDate),
        ];
    }
}
