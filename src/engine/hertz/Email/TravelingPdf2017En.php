<?php

// bcdtravel

namespace AwardWallet\Engine\hertz\Email;

class TravelingPdf2017En extends \TAccountChecker
{
    private $date = 0;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Hertz Rent a Car - Save More on your Next Rental.pdf
        $pdf = $parser->searchAttachmentByName('Hertz Rent a Car - .+?\.pdf');

        if (!empty($pdf)) {
            $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

            return stripos($text, 'Thanks for Traveling at the Speed of Hertz') !== false;
        } else {
            return false;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hertz.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $pdf = $parser->searchAttachmentByName('Hertz Rent a Car - .+?\.pdf');

        if (empty($pdf)) {
            return [];
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        $its[] = $this->parseEmail($this->strCut($text, null, ['AVAILABLE OPTIONAL ITEMS', 'Book Another Car']));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TravelingPdf2017En',
        ];
    }

    private function parseEmail($text)
    {
        $result = ['Kind' => 'L'];
        $result['Number'] = $this->match('/Your Confirmation Number is:\s*([\w-]+)\n/', $text);
        $result += $this->matchSubpattern('/Thanks for Traveling at the(?<RentalCompany>.+?)®(?<RenterName>.+?)!\n/', $text);

        if (preg_match('/Total\s+([\d.,]+)\s*([A-Z]{3})\n/', $text, $matches)) {
            $result['TotalCharge'] = (float) str_replace(',', '', $matches[1]);
            $result['Currency'] = $matches[2];
        }

        /*
          Pickup Location
          MCO - Orlando International Airport Details
          Return Location
          Sanford (Florida) Airport
         */
        $result += $this->matchSubpattern('/Pickup Location\s+Details(?<PickupLocation>.+?)'
                . 'Return Location\s+Details(?<DropoffLocation>.+?)'
                . 'Pickup Time(?<PickupDatetime>.+?)Return Time(?<DropoffDatetime>.+?)\n/s', $text);

        if (isset($result['PickupDatetime'])) {
            $result['PickupDatetime'] = strtotime(str_replace(' at ', ', ', $result['PickupDatetime']), false);
            $result['DropoffDatetime'] = strtotime(str_replace(' at ', ', ', $result['DropoffDatetime']), false);
        }

        $result += $this->matchSubpattern('/YOUR CAR\s+(?<CarType>.+?)\n(?<CarModel>.+?)Details/s', $text);

        return $result;
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
    private function matchSubpattern($pattern, $text)
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

    private function match($pattern, $text, $allMatches = false)
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

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
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
    private function strCut($input, $searchStart, $searchFinish = null)
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
}
