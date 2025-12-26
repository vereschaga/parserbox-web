<?php

namespace AwardWallet\Engine\turkish\Email;

class TurkishAirPassPdf2015En extends \TAccountChecker
{
    public $mailFiles = "turkish/it-10465124.eml, turkish/it-4907718.eml, turkish/it-4907718.eml";
    public $result;

    public function increaseDate($date, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($date));
        $arrDate = strtotime($arrTime, $depDate);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*boardingpass_\d+\.pdf');

        if (empty($pdfs)) {
            $this->logger->info('Pdf is not found or is empty!');

            return false;
        }

        $pdfText = '';

        foreach ($pdfs as $pdf) {
            $pdfText .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }
        $this->parseReservations($pdfText);

        return [
            'emailType'  => 'TurkishAirPassPdf2015En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@thy.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@thy.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*boardingpass_\d+\.pdf');

        if (empty($pdf)) {
            return false;
        }
        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return stripos($pdfText, 'This boarding pass will not be valid in case') !== false
                && stripos($pdfText, 'BOARDING PASS') !== false;
    }

    protected function parseReservations($text)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your reservation code')]/following::text()[normalize-space(.)!=''][1]");

        if (empty($this->result['RecordLocator'])) {
            $this->result['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $resv = join($this->findСutSectionAll($text, 'BOARDING PASS', ['Flight No']));

        if (preg_match_all('#Class\s+(.+?)\s+(\d+/\w+)\s+([A-Z])#u', $resv, $matches)) {
            $this->result['Passengers'] = array_values(array_unique($matches[1]));
            $this->result['TicketNumbers'] = array_values(array_unique($matches[2]));
        }

        $this->parseSegments($this->findСutSectionAll($text, 'Flight No', ['Security No']));
    }

    protected function parseSegments($array)
    {
        foreach ($array as $text) {
            $this->result['TripSegments'][] = $this->parseSement($text);
        }
        $this->result['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $this->result['TripSegments'])));
    }

    protected function parseSement($text)
    {
        $segment = [];
        $regular = '/([A-Z\d]{2})?\s*(\d+).+?Date\s+\d+ \w+ \d+\s+';
        $regular .= '(.+?)\s*\(([A-Z]{3})\)\s+(.+?)\s*\(([A-Z]{3})\)\s+';
        $regular .= 'Tarih\s+(\d+ \w+ \d+)\s+(\d+:\d+)\s+(\d+:\d+)/su';

        if (preg_match($regular, $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
            $segment['DepName'] = $matches[3];
            $segment['DepCode'] = $matches[4];
            $segment['ArrName'] = $matches[5];
            $segment['ArrCode'] = $matches[6];
            $segment += $this->increaseDate($matches[7], $matches[8], $matches[9]);
        }

        return $segment;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findСutSectionAll($input, $searchStart, $searchFinish)
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }
}
