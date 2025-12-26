<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// My Account page with account summary, premier progress and a bit of activity
// Weird parsing line by line bc copypasting butchers html but keeps order of text nodes
class SavedStatement2020 extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-69944395.eml, mileageplus/statements/it-69966387.eml, mileageplus/statements/st-51862817.eml, mileageplus/statements/st-52201932.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $start = $this->http->XPath->query('//text()[contains(., "MileagePlus Number")]');

        if ($start->length !== 1) {
            return;
        }
        $root = $start->item(0);
        $st = $email->createStatement();

        if ($number = $this->http->FindSingleNode('following::text()[normalize-space(.) != ""][1]', $root, true, '/^[A-Z]{2,3}\d{5,10}$/')) {
            $st->addProperty('Number', $number);
            $st->addProperty('Login', $number);
        }
        $name = $this->http->FindSingleNode('//text()[contains(., "MileagePlus Number")]/preceding::text()[normalize-space()!=""][2][starts-with(normalize-space(),"Hello,")]',
            null, false, "/Hello,\s+(.+)/");

        if ($name) {
            $st->addProperty('Name', $name);
        }
        $noProgress = empty($this->http->FindSingleNode("//text()[contains(., 'Your Premier progress')]"));
        $lines = $this->http->FindNodes('//text()[contains(., "MileagePlus Number")]/following::text()[normalize-space(.) != ""][following::text()[contains(., "Your Premier progress")]]');

        if (!$noProgress && (count($lines) < 8 || count($lines) > 55)) {
            return;
        }
        $lines = array_map('strtolower', $lines);
        $lines = array_merge($lines, ['', '', '', '']);

        if (false !== ($i = array_search('miles', $lines)) && preg_match('/^\d{1,3}(,\d{3})*$/', $lines[$i + 1]) > 0 && in_array('buy miles', [$lines[$i + 2], $lines[$i + 3]])) {
            $st->setBalance(str_replace(',', '', $lines[$i + 1]));
        } elseif (false !== ($i = array_search('MILES', $lines)) && preg_match('/^\d{1,3}(,\d{3})*$/', $lines[$i + 1]) > 0 && in_array('Buy miles', [$lines[$i + 2], $lines[$i + 3]])) {
            $st->setBalance(str_replace(',', '', $lines[$i + 1]));
        } elseif ($noProgress) {
            $st->setBalance(str_replace(',', '', $this->http->FindSingleNode("//text()[normalize-space()='Miles never expire']/preceding::text()[normalize-space()!=''][2][normalize-space()='MILES' or normalize-space()='Miles']/following::text()[normalize-space()!=''][1]", null, false, '/^(\d{1,3},\d{3})*$/')));
        }

        if (false !== ($i = array_search('pluspoints', $lines)) && preg_match('/^\d{1,3}(,\d{3})*$/', $lines[$i + 1]) > 0) {
            $st->addProperty('PlusPoints', $lines[$i + 1]);
        }

        if (false !== ($i = array_search('travelbank', $lines)) && preg_match('/^[$]\d{1,3}(,\d{3})*[.]\d{2}$/', $lines[$i + 1]) > 0) {
            $st->addProperty('TravelBank', $lines[$i + 1]);
        } elseif ($noProgress) {
            $st->addProperty('TravelBank', $this->http->FindSingleNode("//a[contains(@href,'buymiles.mileageplus.com/united/united_landing_page/')]/following::text()[normalize-space()='TRAVELBANK' or normalize-space()='TravelBank']/following::text()[normalize-space()!=''][1]", null, false, '/^[$]\d{1,3}(,\d{3})*[.]\d{2}$/'));
        }

        if (false !== ($i = array_search('united club',
                $lines)) && $lines[$i + 1] === 'sm' && $lines[$i + 2] === 'passes' && preg_match('/^\d+$/',
                $lines[$i + 3]) > 0
        ) {
            $st->addProperty('UnitedClubPasses', $lines[$i + 3]);
        } elseif ($noProgress) {
            $st->addProperty('UnitedClubPasses',
                $this->http->FindSingleNode("//a[contains(@href,'www.united.com/ual/en/US/mileageplus/unitedclubpass')]/preceding::text()[normalize-space()!=''][2][contains(normalize-space(),'passes') or contains(normalize-space(),'PASSES')]/following::text()[normalize-space()!=''][1]"));
        }
        $lines = $this->http->FindNodes('//text()[contains(., "Your Premier progress")]/following::text()[normalize-space(.) != ""][following::text()[contains(., "Track your Premier status")]]');

        if (count($lines) < 10 || count($lines) > 50) {
            return;
        }
        $lines = array_map('strtolower', $lines);
        $lines = array_merge($lines, ['', '', '', '']);

        if (false !== ($i = array_search('premier qualifying flights (pqf)', $lines)) && preg_match('/^[\d\.\,]+$/', $lines[$i + 1]) > 0) {
            $st->addProperty('EliteFlights', $lines[$i + 1]);
        }

        if (false !== ($i = array_search('premier qualifying points (pqp)', $lines)) && preg_match('/^[\d\.\,]+$/', $lines[$i + 1]) > 0) {
            $st->addProperty('ElitePoints', $lines[$i + 1]);
        }

        if (false !== ($i = array_search('lifetime flight miles', $lines)) && preg_match('/^\d{1,3}(,\d{3})*$/', $lines[$i + 1]) > 0) {
            $st->addProperty('LifetimeMiles', $lines[$i + 1]);
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//span[text() = "My Account Summary"]')->length > 0
            && ($this->http->XPath->query('//h2[text() = "Your Premier progress"]')->length > 0
                || (
                    $this->http->XPath->query('//text()[normalize-space()="Miles never expire"]/preceding::text()[normalize-space()!=""][2][normalize-space()="MILES" or normalize-space()="Miles"]')->length > 0
                    && $this->http->XPath->query('//a[contains(@href,"buymiles.mileageplus.com/united/united_landing_page/")]/following::text()[normalize-space()="TRAVELBANK" or normalize-space()="TravelBank"]/following::text()[normalize-space()!=""][2]/ancestor::div[1][.//a[contains(.,"Learn more")]]')->length > 0
                )
            );
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
