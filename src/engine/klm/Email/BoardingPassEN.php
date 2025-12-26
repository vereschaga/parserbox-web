<?php

namespace AwardWallet\Engine\klm\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class BoardingPassEN extends \TAccountChecker
{
    public $mailFiles = "klm/it-10313239.eml, klm/it-11472447.eml, klm/it-8291533.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $search = $parser->searchAttachmentByName('(?:Mobile-boarding-pass|Electronic boarding pass)-[A-Z\d]+-\d+ \w+\.?\.png');

        if (count($search) === 0) {
            $this->http->Log(sprintf('Invalid number of pdf attachments %d', count($search)));

            return [];
        }

        $date = strtotime("-10 day", strtotime($parser->getDate()));

        foreach ($search as $item) {
            $bp = [];
            $name = $parser->getAttachmentHeader($item, 'Content-Type');
            // Electronic boarding pass-KL0539-20 feb..png
            if (!$name || !preg_match('/name="(?<name>(?:Mobile-boarding-pass|Electronic boarding pass)-[A-Z\d]{2}(?<number>\d+)-(?<date>\d+ \w+)\.?\.png)/', $name, $m)) {
                $this->http->Log('invalid filename');

                continue;
            }
            $bp['AttachmentFileName'] = $m['name'];
            $bp['FlightNumber'] = $m['number'];
            $bp['DepDate'] = EmailDateHelper::parseDateRelative($m['date'], $date);
            $result[] = $bp;
        }

        return [
            'parsedData' => [
                'BoardingPass' => $result,
            ],
            'emailType' => 'boardingPassENPdf',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return count($parser->searchAttachmentByName('(?:Mobile-boarding-pass|Electronic boarding pass)-[A-Z\d]+-\d+ \w+\.?\.png')) > 0 && stripos($parser->getHTMLBody(), 'KLM') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'yourboardingpass@klm.com') !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Your KLM boarding document(s)') !== false
        || isset($headers['subject']) && stripos($headers['subject'], 'Uw KLM-boardingdocument(en) op') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm.com') !== false;
    }
}
