<?php

namespace AwardWallet\Engine\lner\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Train extends \TAccountChecker
{
    public $mailFiles = "lner/it-26941902.eml, lner/it-36863103.eml";

    private $subjects = [
        'en' => ['Train ticket collection details', 'Order Confirmation'],
    ];

    private $detects = [
        'Thank you for buying your train journey at',
        'Please take your printable confirmation (or your confirmation email) with you',
    ];

    private static $provs = [
        'virgin' => [
            'Virgin Trains',
        ],
        'ctraveller' => [
            'Corporate Traveller',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$provs as $prov => $detects) {
            foreach ($detects as $detect) {
                if (false !== stripos($parser->getHTMLBody(), $detect)) {
                    $email->setProviderCode($prov);

                    break 2;
                }
            }
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'railblazers') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $anchor = false;

        foreach (self::$provs as $prov) {
            foreach ($prov as $p) {
                if (false !== stripos($body, $p)) {
                    $anchor = true;
                }
            }
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) && $anchor) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:virgintrains|trainsfares)\.co\.uk/', $from) > 0;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$provs);
    }

    private function parseEmail(Email $email): Email
    {
        $email->obtainTravelAgency();

        if ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Order Item Ref')][1]", null, true, '/Order Item Ref(?:erence)?[ ]*\:[ ]*(\d+)/')) {
            $email->ota()->confirmation($conf, "Order Item Ref");
        }

        $t = $email->add()->train();

        if ($pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'No. of passengers')]/following-sibling::text()[normalize-space(.)][1]")) {
            $t->addTraveller($pax);
        }

        $paxs = $this->http->FindNodes("//text()[normalize-space() = 'Traveller Details']/following::tr[td[1][normalize-space() = 'Name']][1]/following-sibling::tr[normalize-space()][count(td)>2]/td[1]");

        if (!empty($paxs)) {
            $t->general()->travellers($paxs);
        }

        if ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Ticket Collection Reference')]/following::text()[normalize-space(.)][1]", null, true, '/^\s*([A-Z\d]{5,9})\s*$/')) {
            $t->general()->confirmation($conf, "Ticket Collection Reference");
        } elseif (empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Ticket Collection')][1]"))) {
            $t->general()->noConfirmation();
        }

        $dateStr = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order Date:')]", null, true, "#Order Date\s*:\s*(.+)#");

        if (empty($dateStr)) {
            $dateStr = $this->http->FindSingleNode("//text()[normalize-space() = 'Order Date:']/following::text()[normalize-space()][1]");
        }

        if (!empty($dateStr) && ($date = strtotime($dateStr))) {
            $t->general()->date($date);
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Order Item Cost')][1]");

        if (preg_match('/Order Item Cost\s*\:\s*(\D+)\s*([\d\.]+)\s*$/', $total, $m)) {
            $t->price()
                ->total($m[2])
                ->currency(str_replace(['£'], ['GBP'], $m[1]));
        } else {
            $total = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Total:'][1]/following::text()[normalize-space()][1]");

            if (preg_match('/^\s*(\D+)\s*([\d\.]+)\s*$/', $total, $m)) {
                $t->price()
                    ->total($m[2])
                    ->currency(str_replace(['£'], ['GBP'], $m[1]));
            }
        }

        $xpath = "//table[contains(., 'Station') and not(.//table)]/descendant::tr[not(contains(., 'Service')) and not(contains(., 'Arrive'))]";
        $nodes = $this->http->XPath->query($xpath);
        $segs = [];

        foreach ($nodes as $node) {
            $seg = [];
            $date = strtotime($this->http->FindSingleNode("(preceding::text()[starts-with(normalize-space(.), 'Date of travel')][1])[1]", $node, true, '/\:\s*(\d{1,2} \w+ \d{2,4})/'));

            if (empty($date)) {
                $date = strtotime($this->http->FindSingleNode("(preceding::text()[contains(normalize-space(.), 'Journey -')][1])[1]", $node, true, '/Journey -\s*[^\d\s]+\s+(\d{1,2} \w+ \d{2,4})/'));
            }
            $seg['DepName'] = $this->http->FindSingleNode('td[1]', $node);
            $seg['DepDate'] = (!empty($date)) ? strtotime($this->http->FindSingleNode('td[3]', $node), $date) : null;
            $seg['ArrName'] = $this->http->FindSingleNode('following-sibling::tr[2]/td[1]', $node);
            $seg['ArrDate'] = (!empty($date)) ? strtotime($this->http->FindSingleNode('following-sibling::tr[2]/td[2]', $node), $date) : null;
//            $seg['Operator'] = $this->http->FindSingleNode('td[4]', $node);
            $seg['Seat'] = $this->http->FindSingleNode('td[normalize-space()][last()]', $node, true, '/^[(\s]*(?:Reserved\s*\:)?\s*([A-Z\d]{1,5})[\s)]*$/');

            if ($seg['DepName'] && $seg['ArrName'] && $seg['DepDate'] && $seg['ArrDate']) {
                // for google, to help find correct address of stations
                if ($this->http->XPath->query("//node()[contains(.,'@virgintrains.co.uk')]")->length > 0
                    || $this->http->XPath->query("//node()[contains(.,'@corptraveller.co.uk') or contains(.,'@uk.fcm.travel')]")->length > 0
                ) {
                    // https://en.wikipedia.org/wiki/Virgin_Trains
                    // https://www.corptraveller.co.uk/who-we-are
                    $region = ', UK';
                    $seg['DepName'] .= $region;
                    $seg['ArrName'] .= $region;
                }
                $segs[] = $seg;
            }
        }

        foreach ($segs as $seg) {
            $s = $t->addSegment();

            $s->departure()
                ->name($seg['DepName'])
                ->date($seg['DepDate']);

            $s->arrival()
                ->name($seg['ArrName'])
                ->date($seg['ArrDate']);

            $s->extra()->noNumber();

            if ($seg['Seat']) {
                $s->extra()->seat($seg['Seat']);
            }
        }

        return $email;
    }
}
