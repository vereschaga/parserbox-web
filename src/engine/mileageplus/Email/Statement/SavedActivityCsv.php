<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class SavedActivityCsv extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/st-52226192.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = $this->ParseEmail($parser);

        return [
            'parsedData' => $result,
            'emailType'  => 'SavedActivityCvs',
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*\.csv') as $idx) {
            $this->http->SetEmailBody($parser->getAttachmentBody($idx));

            if ($this->unitDetectByBody()) {
                return true;
            }
        }

        return false;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $activity = [];

        foreach ($parser->searchAttachmentByName('.*\.csv') as $i) {
            $att = $parser->getAttachmentBody($i);
            $this->http->SetEmailBody($att);

            if (!$this->unitDetectByBody()) {
                continue;
            }

            if (preg_match_all("#^([\'\"])([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1, *\\1([^\\1]+?)\\1#m",
                $this->http->Response['body'], $m, PREG_SET_ORDER)) {
                foreach ($m as $i => $row) {
                    if ($i == 0) {
                        if ($row[2] == 'Transaction Date' && $row[3] == 'Activity Type' && $row[4] == 'Description'
                            && $row[5] == 'PQF' && $row[6] == 'PQP' && $row[7] == 'PQM' && $row[8] == 'PQS' && $row[9] == 'PQD' && $row[10] == 'Miles'
                        ) {
                            continue;
                        } else {
                            break;
                        } //other format
                    }
                    $date = $row[2];

                    if (!isset($date) || !strtotime($date)) {
                        continue;
                    }
                    $new = [];
                    $new['Activity Type'] = $row[3];
                    $new['Activity Date'] = strtotime($date);
                    $new['Description'] = $row[4];
                    $new['Award Miles'] = $row[10];
                    $new['Premier Qualifying / Miles'] = $row[7];
                    $new['Premier Qualifying / Segments'] = $row[8];
                    $new['Premier Qualifying / Dollars'] = $row[9];
                    $activity[] = $new;
                }
            }
        }

        return ['Activity' => $activity];
    }

    private function unitDetectByBody()
    {
        $cmp = ["Transaction Date", "Activity Type", "Description", "PQF", "PQP", "PQM", "PQS", "PQD", "Miles"];
        $r = false;

        if (!empty($str = strstr($this->http->Response['body'], "\n", true)) && ($arr = str_getcsv(trim($str))) && (count($arr) === count($cmp))) {
            $r = true;

            foreach ($cmp as $i => $str) {
                if ($str !== $arr[$i]) {
                    $r = false;
                }
            }
        }

        return $r;
    }
}
