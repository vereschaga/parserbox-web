<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedStatementPlain extends \TAccountCheckerExtended
{
    // United personal statement, saved from site and sent by email to AW
    public $mailFiles = "mileageplus/statements/st-2605630.eml, mileageplus/statements/st-2615020.eml, mileageplus/statements/st-6240098.eml";

    public function ParseStatement(\PlancakeEmailParser $parser)
    {
        $result = [];
        $body = $this->http->XPath->query("/*")->item(0)->nodeValue;
        $pos = strpos($body, 'Most recent account activity');

        if (false === $pos) {
            return $result;
        }
        $body = CleanXMLValue(substr($body, 0, $pos));

        $fields = [
            '/My MileagePlus account\s*(?<Name>[\w\s]+)\s*(?<Number>[A-Z]{2}\d{6})/' => ['Name', 'Number'],
            '/Mileage balance\s*(?<Balance>[\d\,]+)/'                                => ['Balance'],
            '/Mileage expiration\s*(?<AccountExpirationDate>\d+\/\d+\/\d{4})/'       => ['AccountExpirationDate'],
            '/YTD Premier qualifying miles:\s*(?<EliteMiles>[\d\.\,]+)/'             => ['EliteMiles'],
            '/YTD Premier qualifying segments:\s*(?<EliteSegments>[\d\.\,]+)/'       => ['EliteSegments'],
            '/YTD Premier qualifying dollars:\s*(?<EliteDollars>[\d\.\,]+)/'         => ['EliteDollars'],
            '/Lifetime flight miles:\s*(?<LifetimeMiles>[\d\.\,]+)/'                 => ['LifetimeMiles'],
        ];

        foreach ($fields as $regexp => $fs) {
            if (preg_match($regexp, $body, $m)) {
                $result = array_merge($result, array_intersect_key($m, array_flip($fs)));
            }
        }

        if (!empty($result['AccountExpirationDate'])) {
            $expDate = strtotime($result['AccountExpirationDate']);

            if (!$expDate || $expDate < strtotime('01/01/2010')) {
                unset($expDate);
            }
        }

        if (isset($expDate)) {
            $result['AccountExpirationDate'] = $expDate;
        } else {
            unset($result['AccountExpirationDate']);
        }

        if (!empty($result['Number'])) {
            $result['Login'] = $result['Number'];
        }

        if ($result['Balance']) {
            $result['Balance'] = str_replace(',', '', $result['Balance']);
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $props = $this->ParseStatement($parser);

        return [
            'parsedData' => ['Properties' => $props],
            'emailType'  => 'SavedStatements',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->FindPreg('#My\s+MileagePlus\s+account#i')
                    and $this->http->FindPreg('#Premier\s+status\s+qualification\s+information#i');
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }
}
