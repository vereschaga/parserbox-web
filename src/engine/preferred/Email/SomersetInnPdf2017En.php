<?php

namespace AwardWallet\Engine\preferred\Email;

// bcdtravel
class SomersetInnPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Conf.+?\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $result[] = $this->parseReservation($this->findCutSection($text, null, ['Toll Free Reservations:']));

        return [
            'parsedData' => ['Itineraries' => $result],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Conf.+?\.pdf');

        foreach ($pdf as $value) {
            $text = \PDF::convertToText($parser->getAttachmentBody($value));

            if (stripos($text, 'Rate/Stay Summary') !== false && stripos($text, 'Somerset') !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
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
        $it['Kind'] = 'R';

        $main = $this->match('/^(.+?)\s{3,}(.+?)\s*Phone:\s*([)(\s\d-]+)\s+(?:Fax:\s*([)(\s\d-]+))?/s', $text, true);

        if (!empty($main)) {
            $it['HotelName'] = $main[0];
            $it['Address'] = $main[1];
            $it['Phone'] = $main[2];

            if (isset($main[3])) {
                $it['Fax'] = $main[3];
            }
        }

        $it['ReservationDate'] = strtotime($this->match('#DateSent:\s*(\d+/\d+/\d{4})#', $text));
        $it['ConfirmationNumber'] = $this->match('/Confirmation#\s*(\w+)/', $text);
        $it['GuestNames'][] = $this->match('/\n\s*([[:alpha:]\s]+)Home#:/', $text);

        // Arrive: Tuesday, February 07, 2017
        $it['CheckInDate'] = strtotime($this->match('/Arrive:\s*\w+, (\w+ \d+, \d+)/', $text));
        $it['CheckOutDate'] = strtotime($this->match('/Depart:\s*\w+, (\w+ \d+, \d+)/', $text));

        $it['RoomType'] = $this->match('/Rm Type:\s*(.+?)\s{2,}/', $text);
        $it['Rooms'] = (int) $this->match('/# of Rms:\s*(\d+)/', $text);

        $sum = $this->match('/Total Room:\s*(\D+)\s*([\d.]+)\s*Total Tax:\D*([\d.]+)/', $text, true);

        if (!empty($sum)) {
            $it['Currency'] = preg_replace(['/^\$$/'], ['USD'], $sum[0]);
            $it['Total'] = (float) $sum[1];
            $it['Taxes'] = (float) $sum[2];
        }

        $it['CancellationPolicy'] = $this->match('/Check in time is.+?be applied\./s', $text);

        return $it;
    }

    //========================================
    // Auxiliary methods
    //========================================

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
}
