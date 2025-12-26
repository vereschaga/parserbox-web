<?php

namespace AwardWallet\Engine\megabus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It2065227 extends \TAccountChecker
{
    public $mailFiles = "megabus/it-2065227.eml, megabus/it-2307426.eml, megabus/it-664286417.eml, megabus/it-663695175-cancelled.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject'])
            && preg_match('/\bFrom megabus\.com\s*:\s*Your reservations? (?:have|has) been (?:made|amended)/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(.,'megabus.com')]")->length > 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for making a reservation with') or contains(normalize-space(),'This reservation has been cancelled')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@megabus.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'date' => '\b[[:alpha:]]+\s+\d{1,2}[,\s]+\d{4}\b', // October 18, 2014
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 9:30 PM
        ];

        $bus = $email->add()->bus();
        $segments = $this->http->XPath->query("//text()[normalize-space()='Reservation Number']/following::p[normalize-space()][1]");

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Reservation summary for order')]", null, true, "/^Reservation summary for order (\w+)$/i")
        ?? $this->http->FindSingleNode("//node()[ normalize-space() and preceding-sibling::node()[normalize-space()][1][contains(normalize-space(),'your reservation')] and following-sibling::node()[normalize-space()][1][starts-with(normalize-space(),'This reservation')] ]", null, true, "/^[-\/A-Z\d]{5,}$/i") // cancelled
        ;
        $bus->general()->confirmation($confirmation);

        if ($this->http->XPath->query("//*[contains(normalize-space(),'This email confirms your reservation')]")->length > 0) {
            $bus->general()->status('confirmed');
        } elseif ($this->http->XPath->query("//*[normalize-space()='This reservation has been cancelled.']")->length > 0 && $segments->length === 0) {
            $bus->general()->status('cancelled')->cancelled();

            return $email;
        }

        foreach ($segments as $root) {
            $s = $bus->addSegment();

            $date = strtotime($this->http->FindSingleNode("descendant::text()[normalize-space()='Date:']/following::text()[normalize-space()][1]", $root, true, "/^{$patterns['date']}$/u"));
            $fromVal = $this->http->FindSingleNode("descendant::text()[normalize-space()='From:']/following::text()[normalize-space()][1]", $root);
            $toVal = $this->http->FindSingleNode("descendant::text()[normalize-space()='To:']/following::text()[normalize-space()][1]", $root);

            if (preg_match($pattern = "/^(?<name>.{3,}?)\s*\(\s*(?<time>{$patterns['time']})\s*\)/", $fromVal, $matches)) {
                if (preg_match_all("/\sat\s/", $matches['name'], $atMatches) && count($atMatches[0]) === 1
                    && preg_match("/^(?<name>.{3,}?)\s+at\s+(?<address>.{3,})$/", $matches['name'], $m)
                ) {
                    $matches['name'] = $m['name'];
                    $s->departure()->address($m['address']);
                }

                $s->departure()->name($this->normalizeNameStation($matches['name']))->date(strtotime($matches['time'], $date));
            }

            if (preg_match($pattern, $toVal, $matches)) {
                if (preg_match_all("/\sat\s/", $matches['name'], $atMatches) && count($atMatches[0]) === 1
                    && preg_match("/^(?<name>.{3,}?)\s+at\s+(?<address>.{3,})$/", $matches['name'], $m)
                ) {
                    $matches['name'] = $m['name'];
                    $s->arrival()->address($m['address']);
                }

                $dateArr = strtotime($matches['time'], $date);

                if (!empty($s->getDepDate()) && !empty($dateArr) && $s->getDepDate() > $dateArr) {
                    // for overnight trips
                    $dateArr = strtotime('+1 days', $dateArr);
                }

                $s->arrival()->name($this->normalizeNameStation($matches['name']))->date($dateArr);
            }

            $seatsVal = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Seat(s):')]", $root, true, "/:\s*([A-Z\d][-,;A-Z\d\s]*)$/");

            if ($seatsVal) {
                $s->extra()->seats(preg_split('/(\s*[,;]+\s*)+/', $seatsVal));
            }
        }

        $currency = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1][normalize-space()='Total Paid:'] ]/*[normalize-space()][2]", null, true, '/^[^\-\d)(]+$/');
        $totalPriceAmount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1][normalize-space()='Total Paid:'] ]/*[normalize-space()][3]", null, true, '/^\d[,.‘\'\d ]*$/');

        if ($totalPriceAmount !== null) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $bus->price()->currency($currency)->total(PriceHelper::parse($totalPriceAmount, $currencyCode));
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function normalizeNameStation(?string $s): ?string
    {
        $s = preg_replace([
            // Philadelphia, PA, 30th St Station    ->    30th St Station, Philadelphia, PA
            '/^(.+?\S\s*,\s*[A-Z]{2})\s*,\s*(.{3,})$/',
        ], [
            '$2, $1',
        ], $s);

        return $s;
    }
}
