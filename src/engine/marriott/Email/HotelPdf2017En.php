<?php

// bcdtravel
// Strong parameters are given to get more examples with this pdf

namespace AwardWallet\Engine\marriott\Email;

class HotelPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // TODO: Specially made a hard pattern!
        $pdf = $parser->searchAttachmentByName('Mr\s+\w+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $its[] = $this->parseHotel($this->findCutSection($text, null, ['ARRIVAL POLICY:']));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'HotelPdf2017En',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // TODO: Specially made a hard pattern!
        $pdf = $parser->searchAttachmentByName('Mr\s+\w+\.pdf');

        foreach ($pdf as $value) {
            $text = \PDF::convertToText($parser->getAttachmentBody($value));

            if (stripos($text, 'Marriott') && $this->strpos($text, ['CHECK-IN DATE'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
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

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];
        $i['ConfirmationNumber'] = $this->match('/Reservation Confirmation:\s*(\d+)\n/', $text);
        $i['Status'] = stripos($text, 'is confirmed and all your') !== false ? 'confirmed' : null;
        $i['GuestNames'][] = $this->match('/Dear\s+(.+?),\n/', $text);
        $i += $this->matchSubpattern('/^(?<HotelName>.+?Hotel.+?)\n(?<Address>.+?)Tel:(?<Phone>[\d\s()-]+), Fax:(?<Fax>[\d\s()-]+)\n/s', $text);

        // TODO: carefully not the American format
        $checkInDate = join('-', array_reverse($this->match('#CHECK-IN DATE\s*(\d+)/(\d+)/(\d{2})\b#', $text, true)));
        $i['CheckInDate'] = strtotime($checkInDate . ',' . $this->match('/CHECK-IN TIME\s*([\d:]+(?:\s*[ap.]m)?)\b/i', $text), false);

        $checkOutDate = join('-', array_reverse($this->match('#CHECK-OUT DATE\s*(\d+)/(\d+)/(\d{2})\b#', $text, true)));
        $i['CheckOutDate'] = strtotime($checkOutDate . ',' . $this->match('/CHECK-OUT TIME\s*([\d:]+(?:\s*[ap.]m)?)\b/i', $text), false);

        $i += $this->matchSubpattern('/ROOM TYPE\s+(?<RoomType>.+?)RATE DESCRIPTION\s+(?<RateType>.+?)\n/', $text);
        $i += $this->matchSubpattern('#ADULTS / CHILDREN\s+(?<Guests>\d+)/(?<Kids>\d+)#', $text);
        $i['Rate'] = $this->match('/DAILY RATE INFO\s+([A-Z]{3}\s*[\d,.\s]+)\n/', $text);

        $i['CancellationPolicy'] = $this->match('/CANCELLATION POLICY:\s+(.+?\.)\n/s', $text);

        return $i;
    }

    //========================================
    // Auxiliary methods
    //========================================

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    protected function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
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
        } elseif ($allMatches) {
            return [];
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function strpos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
