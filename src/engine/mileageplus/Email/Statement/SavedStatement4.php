<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatement4 extends \TAccountCheckerExtended
{
    // United personal statement v4, saved from site and sent by email to AW
    public $mailFiles = "mileageplus/it-28259216.eml, mileageplus/it-28297578.eml, mileageplus/it-28308264.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];

        $searchFieldsTd = [
            'Number' => [
                '//text()[contains(normalize-space(),"MileagePlus Number")]/following::text()[normalize-space()][1]',
                '#^\w+$#',
            ],
            'Balance' => [
                '//text()[contains(normalize-space(),"Mileage balance")]/following::text()[normalize-space()][1]',
                '#^[\d,]+$#', // hotfix
            ],
            'AccountExpirationDate' => [
                '//text()[contains(normalize-space(),"Mileage balance")]/following::text()[normalize-space()][2]/ancestor::*[not(normalize-space()="Exp.")][1][starts-with(normalize-space(),"Exp.")]',
                '#^Exp\.\s*(\d+/\d+/\d+)$#',
            ],
            'EliteMiles' => [
                '//text()[contains(normalize-space(),"Premier Qualifying Miles (PQM):")]/following::text()[normalize-space()][1]',
                '#^[\d,]+$#i',
            ],
            'EliteSegments' => [
                '//text()[contains(normalize-space(),"Premier Qualifying Segments (PQS):")]/following::text()[normalize-space()][1]',
                '#^[\d.]+$#i',
            ],
            'EliteDollars' => [
                '//text()[contains(normalize-space(),"Premier Qualifying Dollars (PQD):")]/following::text()[normalize-space()][1]/ancestor::*[not(normalize-space()="$")][1][starts-with(normalize-space(),"$")]',
                '#^\$[\d,.]+$#i',
            ],
            'LifetimeMiles' => [
                '//text()[contains(normalize-space(),"Lifetime flight miles:")]/following::text()[normalize-space()][1]',
                '#^[\d,.]+$#i',
            ],
        ];

        foreach ($searchFieldsTd as $key => [$xpath, $regex]) {
            $value = $this->http->FindSingleNode($xpath, null, false, $regex);
            $result[$key] = $value;
        }

        if (!empty($result['Number'])) {
            $result['Login'] = $result['Number'];
        }

        if (isset($result['Balance'])) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }

        if (isset($result['AccountExpirationDate'])) {
            $dateStr = $result['AccountExpirationDate'];
            $result['AccountExpirationDate'] = strtotime($result['AccountExpirationDate']);

            if (!$result['AccountExpirationDate']) {//xz how get write
                $result['AccountExpirationDate'] = strtotime($this->ModifyDateFormat($dateStr));
            }
        }

        $statusImage = $this->http->FindSingleNode('//*[@id="myAccountID" or normalize-space()="MileagePlus Number"]/preceding::*[contains(@style,"/241a9afbe4acec7c0002304735e96d66.")][1]/@style');
        // TODO: need add extended statuses
        $statusByPosition = [
            '-410px -554px' => 'Member',
            '-113px -181px' => 'Silver',
            '-817px -258px' => 'Gold',
            '-817px -134px' => 'Platinum',
            '-11px -181px'  => '1K',
            '-417px -10px'  => 'Global Services',
        ];

        foreach ($statusByPosition as $position => $status) {
            if (stripos($statusImage, $position) !== false) {
                $result['MemberStatus'] = $status;
            }
        }

        return array_filter($result, function ($s) {return $s !== null; });
    }

    public function ParseActivity(\PlancakeEmailParser $parser)
    {
        $result = [];

        $xpath = "//text()[contains(normalize-space(),\"Recent MileagePlus activity\")]/following::text()[normalize-space()='Description']/ancestor::*[contains(.,'Miles Earned')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $infoText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()!='']", $root));

            if (preg_match("/^(\d+\/\d+\/\d+)\s+Description\s+(.+)\s+Miles Earned\s+(\d[\d\.\,]+\s+miles)/m", $infoText, $info)) {
                $new = [];

                if (preg_match("/[A-Z]{3}\s*\-\s*[A-Z]{3}/", $info[2])) {
                    $new['Activity Type'] = 'Airline';
                }
                $new['Activity Date'] = strtotime($info[1]);
                $new['Description'] = $info[2];
                $new['Award Miles'] = preg_replace("/\s+/", ' ', $info[3]);
                $result[] = $new;
            } else {
                $this->logger->debug('other format activity');

                return [];
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement($parser);
        //		$activity = $this->ParseActivity($parser); refs #17109-note 3
        return [
            'parsedData' => [
                'Properties' => $props,
                //                'Activity' => $activity
            ],
            'emailType' => 'SavedStatement4',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();

        return stripos($text, 'MileagePlus number') !== false
                    and stripos($text, 'Mileage balance') !== false
                        and stripos($text, 'Recent MileagePlus activity') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
