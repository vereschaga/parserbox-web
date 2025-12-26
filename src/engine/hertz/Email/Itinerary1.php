<?php

namespace AwardWallet\Engine\hertz\Email;

// TODO: merge with parsers HertzReservation, It1963429, It1617628

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";
    public $processors = [];
    public $reText = null;
    public $reHtml = null;

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            /*
            // Parsed file "hertz/it-1.eml"
            "#Pick Up\s+Location#" => function(&$it, $parser){
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $result["Kind"] = "L";

                // Number
                $result["Number"] = $this->re("#Confirmation Number is:\s*([^\n]+)#", $text);

                // PickupDatetime
                $result["PickupDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//div[contains(text(), 'Pick Up') and not(contains(text(), 'Pick Up Location'))]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")));

                // DropoffDatetime
                $result["DropoffDatetime"] = strtotime(str_replace('at', '',$this->http->FindSingleNode("//div[contains(text(), 'Pick Up') and not(contains(text(), 'Pick Up Location'))]/ancestor::tr[1]/following-sibling::tr[1]/td[2]")));

                // PickupLocation
                $location = $this->xNode("//div[contains(text(), 'Pick Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
                $result["PickupLocation"] = $this->re("#^(.*?)(\s+Location Type:\s*.*?)*(\s+Hours of Operation:\s*(.*?))*(\s+Phone Number:\s*(.*?))*(\s+Fax Number:\s*([\d\-+\s]+))*$#ims", $location);
                $result["PickupHours"] = $this->re(4);
                $result["PickupPhone"] = $this->re(6);
                $result["PickupFax"] = $this->re(8);

                // DropoffLocation
                $location = $this->xNode("//div[contains(text(), 'Pick Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");
                $result["DropoffLocation"] = $this->re("#^(.*?)(\s+Location Type:\s*.*?)*(\s+Hours of Operation:\s*(.*?))*(\s+Phone Number:\s*(.*?))*(\s+Fax Number:\s*([\d\-+\s]+))*$#ims", $location);
                $result["DropoffHours"] = $this->re(4);
                $result["DropoffPhone"] = $this->re(6);
                $result["DropoffFax"] = $this->re(8);

                // RenterName
                $result['RenterName'] = beautifulName($this->re("#Thanks,\s*(.*?)\. Your Reservation has been made.#", $text));
                // CarType
                $result["CarType"] = trim($this->xNode("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]//span/following-sibling::text()"));
                // CarModel
                $result["CarModel"] = $this->glue($this->xNode("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]//span"), ' ');
                // CarImageUrl
                $result["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')]/@src");
                ## TotalCharge
                $result['TotalCharge'] = $this->http->FindSingleNode("//span[contains(text(), 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                ## Currency
                $result['Currency'] = $this->http->FindSingleNode("//span[contains(text(), 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/([A-Z]{3})/');
                // TotalTaxAmount
                $result['TotalTaxAmount'] = $this->http->FindSingleNode("//span[contains(text(), 'Taxes')]/ancestor::td[1]/following-sibling::td[1]", null, false, "/(\d+\.\d+)/ims");
                // ServiceLevel
                $result["ServiceLevel"] = CleanXMLValue($this->http->FindPreg("/Service Type\s*:\s*([^<]+)/ims"));
                if (strstr($result["ServiceLevel"], 'is not available'))
                    unset ($result["ServiceLevel"]);
                // Discounts
                $discounts = $this->http->XPath->query("//div[contains(text(), 'Discounts')]/ancestor::tr[1]/following-sibling::tr[1]/td[2][contains(text(), ':')]/text()");
                $discount = [];
                for ($i = 0; $i < $discounts->length; $i++) {
                    $code = $discounts->item($i)->nodeValue;
                    if (preg_match('/([^\:]*):(.*)/ims', trim($code), $mc))
                        $discount[] = array("Code" => CleanXMLValue($mc[1]), "Name" => CleanXMLValue($mc[2]));
                }
                $result["Discounts"] = $discount;
                // Discount
                $result["Discount"] = $this->http->FindSingleNode("//span[contains(text(), 'Price Details for Your Quote')]/ancestor::tr[1]/following-sibling::tr[3]/td[2]", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                // Fees
                $fee = [];
                for ($i = 1; $i < 10; $i++) {
                    $tr = $this->http->XPath->query("//span[contains(text(), 'Total Approximate Charge')]/ancestor::tr[3]/following-sibling::tr[$i]");
                    if (!$tr->length) break;
                    $tr = $tr->item(0);

                    $cost = $this->mkCost($this->http->FindSingleNode("td[2]", $tr));
                    if ($cost > 0){
                        $fee[] = array(
                            "Name" => $this->http->FindSingleNode("preceding-sibling::tr[1]/td[1]", $tr).' ('.$this->http->FindSingleNode("td[1]", $tr).')',
                            "Charge" => $cost
                        );
                    }
                }
                $result["Fees"] = $fee;
                $it = $result;
            },
*/
            // Parsed file "hertz/it-4.eml"
            "#Pickup and Return Location\s*<br\s*/*>#i" => function (&$it, $parser) {
                $this->logger->debug('Processors Function #1');
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $result["Kind"] = "L";
                // Number
                $result["Number"] = $this->http->FindSingleNode("//div[contains(text(), 'Confirmation Number')]/span");
                // PickupDatetime
                $result["PickupDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//tr[td[label[contains(text(), 'Pickup Time')]]]/following-sibling::tr[1]/td[1]")));
                // DropoffDatetime
                $result["DropoffDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//tr[td[label[contains(text(), 'Pickup Time')]]]/following-sibling::tr[1]/td[2]")));
                // PickupLocation, DropoffLocation
                $result["PickupLocation"] = '';
                $nodes = $this->http->XPath->query("//div[contains(text(), 'Pickup and Return Location')]/following-sibling::table//tr[1]/td[1]/text()");

                for ($i = 0; $i < $nodes->length; $i++) {
                    $node = CleanXMLValue($nodes->item($i)->nodeValue);

                    if (!empty($node)) {
                        if ($result['PickupLocation'] != '') {
                            $result['PickupLocation'] .= ', ';
                        }
                        $result["PickupLocation"] .= $node;
                    }
                }
                $result["DropoffLocation"] = $result["PickupLocation"];
                // PickupHours, DropoffHours
                $result["PickupHours"] = $result["DropoffHours"] = CleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Hours of Operation')]/parent::*", null, true, '/:([^>]+)/ims'));
                // RenterName
                $result['RenterName'] = beautifulName(CleanXMLValue($this->http->FindSingleNode("//td[contains(text(), 'Thanks for Traveling at the speed')]", null, true, '/,([^<]+)/ims')));
                //  PickupFax
                $result["PickupFax"] = CleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Fax Number:')]/parent::*", null, true, '/:([^<]+)/ims'));
                //# PickupPhone
                $result["PickupPhone"] = CleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Phone Number:')]/parent::*", null, true, '/:([^<]+)/ims'));
                // CarType
                $result["CarType"] = CleanXMLValue(implode(' ', $this->http->FindNodes("//td[img[contains(@src, 'vehicles')]]/following-sibling::td[1]/div/div/text()")));
                // CarModel
                $result["CarModel"] = CleanXMLValue(implode(' ', $this->http->FindNodes("//td[img[contains(@src, 'vehicles')]]/following-sibling::td[1]/div/div/span")));
                // CarImageUrl
                $result["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')]/@src");
                //# TotalCharge
                $result['TotalCharge'] = $this->http->FindSingleNode("//td[contains(text(), 'Total Approximate Charge')]/following-sibling::td", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                //# Currency
                $result['Currency'] = $this->http->FindSingleNode("//td[contains(text(), 'Total Approximate Charge')]/following-sibling::td", null, true, '/([A-Z]{3})/');
                // TotalTaxAmount
                $result['TotalTaxAmount'] = $this->http->FindSingleNode("//td[contains(text(), 'Taxes')]/following-sibling::td", null, false, "/(\d+\.\d+)/ims");
                // ServiceLevel
                $result["ServiceLevel"] = CleanXMLValue($this->http->FindPreg("/Service Type\s*:\s*([^<]+)/ims"));
                // Discounts
                $discounts = $this->http->XPath->query("//div[contains(text(), 'Discounts')]/following-sibling::div[contains(text(), ':')]");
                $discount = [];

                for ($i = 0; $i < $discounts->length; $i++) {
                    $code = $discounts->item($i)->nodeValue;

                    if (preg_match('/([^\:]*):(.*)/ims', trim($code), $mc)) {
                        $discount[] = ["Code" => CleanXMLValue($mc[1]), "Name" => CleanXMLValue($mc[2])];
                    }
                }
                $result["Discounts"] = $discount;
                // Discount
                $result["Discount"] = $this->http->FindSingleNode("//div[contains(text(), 'Discounts')]/following-sibling::table[1]//td[2]", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                // Fees
                $fees = $this->http->XPath->query("//div[contains(text(), 'Discounts')]/following-sibling::table[2]//tr");
                $fee = [];

                for ($i = 0; $i < $fees->length; $i++) {
                    $cells = $this->http->XPath->query("td", $fees->item($i));

                    if ($cells->length == 2) {
                        $fee[] = [
                            "Name"   => CleanXMLValue($cells->item(0)->nodeValue),
                            "Charge" => CleanXMLValue(preg_replace('/[^\d\,\.]/ims', '', $cells->item(1)->nodeValue)), ];
                    }
                }
                $result["Fees"] = $fee;
                $it = $result;
            },

            // Parsed file "hertz/it-5.eml"
            "#Pick.Up\s+Location#" => function (&$it, $parser) {
                $this->logger->debug('Processors Function #2');
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $result["Kind"] = "L";
                // Number
                $result["Number"] = $this->http->FindSingleNode("//b[span[contains(text(), 'Confirmation Number:')]]/following-sibling::b[1]");
                // PickupDatetime
                $result["PickupDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Date')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")));
                // DropoffDatetime
                $result["DropoffDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Date')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]")));
                // PickupLocation
                $result["PickupLocation"] = preg_replace('/(,\s*Phone.+)/', '', CleanXMLValue(implode(', ', $this->http->FindNodes("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//span/text()"))));
                // DropoffLocation
                $result["DropoffLocation"] = preg_replace('/(,\s*Phone.+)/', '', CleanXMLValue(implode(', ', $this->http->FindNodes("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//span/text()"))));
                // PickupHours
                $result["PickupHours"] = CleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, '/Hours of Operation:([^>]+)(?:Location)/ims'));
                // DropoffHours
                $result["DropoffHours"] = CleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, '/Hours of Operation:([^>]+)(?:Location)/ims'));
                // RenterName
                $result['RenterName'] = beautifulName(CleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Thank you for your reservation')]", null, true, '/,([^<]+)/ims')));
                //  PickupFax
                $result["PickupFax"] = CleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, '/Fax:([^>]+)(?:Hours)/ims'));
                //# PickupPhone
                $result["PickupPhone"] = CleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Pick-Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, '/Phone:([^>]+)(?:Fax)/ims'));
                // CarType
                $result["CarType"] = CleanXMLValue(implode(' ', $this->http->FindNodes("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]/div/p/span/text()")));
                // CarModel
                $result["CarModel"] = CleanXMLValue(str_replace($result['CarType'], '', implode(' ', $this->http->FindNodes("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]/div/p"))));
                // CarImageUrl
                $result["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')]/@src");
                //# TotalCharge
                $result['TotalCharge'] = $this->http->FindSingleNode("//span[contains(text(), 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                //# Currency
                $result['Currency'] = $this->http->FindSingleNode("//span[contains(text(), 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/([A-Z]{3})/');
                // TotalTaxAmount
                $result['TotalTaxAmount'] = $this->http->FindSingleNode("//span[contains(text(), 'TAXES')]/ancestor::td[1]/following-sibling::td[1]", null, false, "/(\d+\.\d+)/ims");
                // ServiceLevel
                $result["ServiceLevel"] = CleanXMLValue($this->http->FindPreg("/Service Type\s*:\s*([^<]+)/ims"));

                if (strstr($result["ServiceLevel"], 'is not available')) {
                    unset($result["ServiceLevel"]);
                }
                // Discounts
                $discounts = $this->http->XPath->query("//span[contains(text(), 'Discounts')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//span[contains(text(), ':')]/text()");
                $discount = [];

                for ($i = 0; $i < $discounts->length; $i++) {
                    $code = $discounts->item($i)->nodeValue;

                    if (preg_match('/([^\:]*):(.*)/ims', trim($code), $mc)) {
                        $discount[] = ["Code" => CleanXMLValue($mc[1]), "Name" => CleanXMLValue($mc[2])];
                    }
                }
                $result["Discounts"] = $discount;
                // Discount
                $result["Discount"] = $this->http->FindSingleNode("//span[contains(text(), 'Price Details for Your Quote')]/ancestor::tr[1]/following-sibling::tr[3]/td[2]", null, true, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                // Fees
                $fee = [];

                for ($i = 1; $i < 10; $i++) {
                    $f = $this->http->XPath->query("//span[contains(text(), 'Total approximate price includes:')]/ancestor::tr[1]/following-sibling::tr[$i]");

                    if ($f->length == 0 || $f->item(0)->getAttribute('style')) {
                        break;
                    }
                    $fee[] = [
                        "Name"   => CleanXMLValue($this->http->FindSingleNode("td[1]", $f->item(0))),
                        "Charge" => CleanXMLValue(preg_replace('/[^\d\,\.]/ims', '', $this->http->FindSingleNode("td[2]", $f->item(0)))), ];
                }
                $result["Fees"] = $fee;
                $it = $result;
            },

            // Parsed file "hertz/it-6.eml"
            "#Pickup Location\s*<br\s*/*>#i" => function (&$it, $parser) {
                $this->logger->debug('Processors Function #3');
                $result["Kind"] = "L";
                // Number
                $result["Number"] = $this->http->FindSingleNode("//text()[contains(., 'Confirmation Number is:')]/following::text()[normalize-space()][1]");
                // PickupDatetime
                $result["PickupDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//tr[td[contains(., 'Pickup Time') and not(.//td)]]/following-sibling::tr[1]/td[1]")));

                if (!$result["PickupDatetime"]) {
                    $result["PickupDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//div[contains(text(),'Pick Up') and not(contains(text(), 'Pick Up Location'))]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")));
                }

                // DropoffDatetime
                $result["DropoffDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//tr[td[contains(., 'Return Time') and not(.//td)]]/following-sibling::tr[1]/td[2]")));

                if (!$result["DropoffDatetime"]) {
                    $result["DropoffDatetime"] = strtotime(str_replace('at', '', $this->http->FindSingleNode("//div[contains(text(),'Return') and not(contains(text(), 'Return Location'))]/ancestor::tr[1]/following-sibling::tr[1]/td[2]")));
                }

                // PickupLocation
                $result["PickupLocation"] = $this->http->FindSingleNode("//div[contains(text(),'Pick Up Location')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

                if (!$result["PickupLocation"]) {
                    $result["PickupLocation"] = trim($this->http->FindSingleNode("//span[contains(text(), 'Pickup Location')]/ancestor::b/following-sibling::span[1]") . ', ' . CleanXMLValue(implode(', ', $this->http->FindNodes("//span[contains(text(), 'Pickup Location')]/ancestor::td[1]/table//tr[1]/td[1]/p/span/text()"))), ', ');

                    if (empty($result["PickupLocation"])) {
                        $result["PickupLocation"] = implode(', ', array_filter(array_merge(
                            [$this->http->FindSingleNode("//text()[contains(., 'Pickup Location')]/following::text()[normalize-space()][1]")],
                            $this->http->FindNodes("//text()[contains(., 'Pickup Location')]/ancestor::td[1]//text()[contains(., 'Address')]/ancestor::td[1]/text()[normalize-space()]")
                        ), 'strlen'));
                    }
                }

                // PickupHours
                $result["PickupHours"] = CleanXMLValue($this->http->FindSingleNode("//text()[contains(., 'Pickup Location')]/ancestor::td[1]/table//tr[3]/td[1]//text()[normalize-space()][last()]"));

                // DropoffLocation
                $result["DropoffLocation"] = $this->http->FindSingleNode("//tr[td[contains(.,'Return Location')]]/following-sibling::tr[1]/td[2]");

                if (!$result["DropoffLocation"]) {
                    $result["DropoffLocation"] = trim($this->http->FindSingleNode("//span[contains(text(), 'Return Location')]/ancestor::b/following-sibling::span[1]") . ', ' . CleanXMLValue(implode(', ', $this->http->FindNodes("//span[contains(text(), 'Return Location')]/ancestor::td[1]/table//tr[1]/td[1]/p/span/text()"))), ', ');

                    if (empty($result["DropoffLocation"])) {
                        $result["DropoffLocation"] = implode(', ', array_filter(array_merge(
                            [$this->http->FindSingleNode("//text()[contains(., 'Return Location')]/following::text()[normalize-space()][1]")],
                            $this->http->FindNodes("//text()[contains(., 'Return Location')]/ancestor::td[1]//text()[contains(., 'Address')]/ancestor::td[1]/text()[normalize-space()]")
                        ), 'strlen'));
                    }
                }

                // DropoffHours
                $result["DropoffHours"] = CleanXMLValue($this->http->FindSingleNode("//text()[contains(., 'Return Location')]/ancestor::td[1]/table//tr[3]/td[1]//text()[normalize-space()][last()]"));

                // RenterName
                $result['RenterName'] = beautifulName(CleanXMLValue($this->http->FindSingleNode("//text()[contains(., 'Thanks') and (contains(., 'Speed') or contains(., 'speed'))]", null, true, '/,([^<]+)/ims')));

                foreach ([['Pickup', 'Pickup'], ['Dropoff', 'Return']] as $pair) {
                    [$Pickup, $Pickup_] = $pair;
                    $result["{$Pickup}Phone"] = CleanXMLValue($this->http->FindSingleNode("//text()[contains(., '{$Pickup_} Location')]/ancestor::td[1]/table//tr[4]/td[1]//text()[normalize-space()][last()]"));
                    $result["{$Pickup}Fax"] = CleanXMLValue($this->http->FindSingleNode("//text()[contains(., '{$Pickup_} Location')]/ancestor::td[1]/table//tr[5]/td[1]//text()[normalize-space()][last()]"));
                }
                // CarType
                $result["CarType"] = CleanXMLValue(implode(' ', $this->http->FindNodes("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()][1]")));
                // CarModel
                $result["CarModel"] = CleanXMLValue(implode(' ', $this->http->FindNodes("//img[contains(@src, 'vehicles')]/ancestor::td[1]/following-sibling::td[1]//span/text()")));
                // CarImageUrl
                $result["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')]/@src");
                //# TotalCharge
                $result['TotalCharge'] = $this->http->FindSingleNode("//text()[contains(., 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/(\d+.\d+|\d+)/');
                //# Currency
                $result['Currency'] = $this->http->FindSingleNode("//text()[contains(., 'Total Approximate Charge')]/ancestor::td[1]/following-sibling::td[1]", null, true, '/([A-Z]{3})/');
                // TotalTaxAmount
                $result['TotalTaxAmount'] = $this->http->FindSingleNode("//text()[normalize-space(.) = 'Taxes']/ancestor::td/following-sibling::td", null, false, "/(\d+.\d+|\d+)/ims");
                // ServiceLevel
                $result["ServiceLevel"] = CleanXMLValue($this->http->FindPreg("/Service Type\s*:\s*([^<]+)/ims"));
                // Discounts
                $discounts = $this->http->XPath->query("//span[contains(text(), 'Discounts')]/ancestor::div[1]/following-sibling::div[p/span[contains(text(), ':')]]");
                $discount = [];

                for ($i = 0; $i < $discounts->length; $i++) {
                    $code = $discounts->item($i)->nodeValue;

                    if (preg_match('/([^\:]*):(.*)/ims', trim($code), $mc)) {
                        $discount[] = ["Code" => CleanXMLValue($mc[1]), "Name" => CleanXMLValue($mc[2])];
                    }
                }
                $result["Discounts"] = $discount;
                // Discount
                $discount = $this->http->FindNodes("//span[contains(text(), 'Discounts')]/ancestor::div[1]/following-sibling::table[1]//tr/td[2]", null, '/(\d+[\.\,]\d+[\.\,]\d+|\d+[\.\,]\d+|\d+)/');
                $result["Discount"] = 0;

                foreach ($discount as $key) {
                    $result["Discount"] += $key;
                }
                // Fees
                $fees = $this->http->XPath->query("//span[normalize-space(text()) = 'Included']/ancestor::div[1]/following-sibling::table[1]//tr");
                $fee = [];

                for ($i = 0; $i < $fees->length; $i++) {
                    $name = CleanXMLValue($this->http->FindSingleNode("td[1]", $fees->item($i)));
                    $charge = CleanXMLValue(preg_replace('/[^\d\,\.]/ims', '', $this->http->FindSingleNode("td[2]", $fees->item($i))));

                    if (strtolower($name) != 'taxes' && !empty($charge)) {
                        $fee[] = [
                            "Name"   => $name,
                            "Charge" => $charge, ];
                    }
                }
                $result["Fees"] = $fee;
                $it = $result;
            },

            // Parsed files: hertz/it-7.eml, hertz/it-8.eml
            "#My Hertz Reservation#" => function (&$it, $parser) {
                $this->logger->debug('Processors Function #4');
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                $result["Kind"] = "L";
                // Number
                $result["Number"] = CleanXMLValue($this->http->FindPreg("/Confirmation Number:\s([^<]+)/ims"));       // PickupDatetime

                if (!$result["Number"]) {
                    $result["Number"] = $this->http->FindSingleNode('//*[contains(text(), "Your Confirmation Number is:")]/following-sibling::*[1]');
                }
                $result["PickupDatetime"] = strtotime(CleanXMLValue(str_replace('at', '', $this->http->FindPreg("/Pickup Time:([^<]+)/ims"))));

                if (!$result["PickupDatetime"]) {
                    $result["PickupDatetime"] = strtotime(str_ireplace('at', ' ', $this->http->FindSingleNode('//*[contains(text(), "Pickup Time")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]')));
                }
                // DropoffDatetime
                $result["DropoffDatetime"] = strtotime(CleanXMLValue(str_replace('at', '', $this->http->FindPreg("/Return Time:([^<]+)/ims"))));

                if (!$result['DropoffDatetime']) {
                    $result['DropoffDatetime'] = strtotime(str_ireplace('at', ' ', $this->http->FindSingleNode('//*[contains(text(), "Pickup Time")]/ancestor::tr[1]/following-sibling::tr[1]/td[2]')));
                }
                // PickupLocation, DropoffLocation
                if ($result["PickupLocation"] = CleanXMLValue(str_replace(['<br><br>', '<br>'], ', ', $this->http->FindPreg("/Pickup and Return Location:([\s\S\w]+)Location Type:/ims")))) {
                    $result["PickupLocation"] = CleanXMLValue(preg_replace(['/^,/', '/,$/'], '', $result["PickupLocation"]));
                    $result["DropoffLocation"] = $result["PickupLocation"];
                } else {
                    $result['PickupLocation'] = implode(', ', $this->http->FindNodes('(
                        //*[contains(text(), "Pickup Location:")]/following-sibling::*[not(self::br) and count(following-sibling::*[contains(., "Location Type:")]) = 2] |
                        //*[contains(text(), "Pickup Location")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::span[last()]/text()[count(following-sibling::node()[contains(., "Location Type:")]) = 1]
                    )'));
                    $result['DropoffLocation'] = implode(', ', $this->http->FindNodes('(
                        //*[contains(text(), "Return Location:")]/following-sibling::*[not(self::br) and count(following-sibling::*[contains(., "Location Type:")]) = 1] |
                        //*[contains(text(), "Return Location")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::span[last()]/text()[count(following-sibling::node()[contains(., "Location Type:")]) = 1]
                    )'));
                }

                // PickupHours, DropoffHours
                $result["PickupHours"] = $result["DropoffHours"] = CleanXMLValue($this->http->FindPreg("/Hours of Operation:([^<]+)/ims"));
                // RenterName
                $result['RenterName'] = beautifulName(CleanXMLValue($this->http->FindPreg("/Thanks for Traveling at the speed[^,]+,([^<]+)/i")));
                //  PickupFax
                $result["PickupFax"] = CleanXMLValue($this->http->FindPreg("/Fax Number:\s*([^><]+)/ims"));
                //# PickupPhone
                $result["PickupPhone"] = CleanXMLValue($this->http->FindPreg("/Phone Number:\s*([^><]+)/ims"));
                // CarType
                $result["CarType"] = CleanXMLValue($this->http->FindPreg("/Your Vehicle\s*<br>\s*[^<]+<br>([^<]+)/ims"));

                if (!$result["CarType"]) {
                    $result["CarType"] = $this->http->FindSingleNode('(
                        //*[contains(text(), "Your Vehicle")]/following-sibling::*[not(self::br)][2] |
                        //*[contains(text(), "Your Vehicle")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//td[2]//span[2]
                    )[1]');
                }
                // CarModel
                $result["CarModel"] = CleanXMLValue($this->http->FindPreg("/Your Vehicle\s*<br>\s*([^<]+)/ims"));

                if (!$result["CarModel"]) {
                    $result["CarModel"] = $this->http->FindSingleNode('(
                        //*[contains(text(), "Your Vehicle")]/following-sibling::*[not(self::br)][1] |
                        //*[contains(text(), "Your Vehicle")]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//td[2]//span[1]
                    )[1]');
                }
                // CarImageUrl
                //        $result["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')]/@src");
                //# TotalCharge
                $result['TotalCharge'] = $this->http->FindPreg('/Total Approximate Charge\s*([\d\.\,]+)/ims');

                if (!$result['TotalCharge']) {
                    $result['TotalCharge'] = $this->http->FindSingleNode('//*[contains(text(), "Total Approximate Charge")]/ancestor::td[1]/following-sibling::td[1]', null, true, '/([\d\.\,]+)/ims');
                }
                //# Currency
                $result['Currency'] = $this->http->FindPreg('/Total Approximate Charge\s*[\d\.\,]+\s*([A-Z]{3})/ims');

                if (!$result['Currency']) {
                    $result['Currency'] = $this->http->FindSingleNode('//*[contains(text(), "Total Approximate Charge")]/ancestor::td[1]/following-sibling::td[1]', null, true, '/([A-Z]{3})/ms');
                }
                // TotalTaxAmount
                //        $result['TotalTaxAmount'] = $this->http->FindSingleNode("//td[contains(text(), 'Taxes')]/following-sibling::td", null, false, "/(\d+\.\d+)/ims");
                // ServiceLevel
                $result["ServiceLevel"] = CleanXMLValue($this->http->FindPreg("/Service Type\s*:\s*([^<]+)/ims"));
                // Discounts
                $discounts = $this->http->FindPreg("/Discounts\s*<span[^>]+>[^<]+<\/span>\s*<br>([<>\s\w=:]+)<br>\s*<span/ims");
                $discount = [];

                if (isset($discounts)) {
                    $disc = explode('<br>', $discounts);

                    if (isset($disc[0])) {
                        foreach ($disc as $code) {
                            if (preg_match('/([^\:]*):(.*)/ims', trim($code), $mc)) {
                                $discount[] = ["Code" => CleanXMLValue($mc[1]), "Name" => CleanXMLValue($mc[2])];
                            }
                        }
                    }
                }
                $result["Discounts"] = $discount;
                // Discount
                $result["Discount"] = $this->http->FindPreg("/at\s*([\d\.\,]+)[^<]+<br><br>ADDITIONAL ITEMS:/ims");
                // Fees
                $fees = $this->http->FindPreg("/ADDITIONAL ITEMS:<br>\s*<br>([<>\s\w\d\.\,=\&\;]+)<br><br>/ims");
                $fee = [];

                if (isset($fees)) {
                    $f = explode('<br>', $fees);

                    if (isset($f[0])) {
                        foreach ($f as $v) {
                            if (preg_match('/([^\d]+)([\d\.\,]+)/ims', trim($v), $mc)) {
                                $fee[] = [
                                    "Name"   => CleanXMLValue($mc[1]),
                                    "Charge" => CleanXMLValue($mc[2]), ];
                            }
                        }
                    }
                }
                $result["Fees"] = $fee;

                $it = $result;
            },
            /**
             * @example hertz/it-12.eml
             * @example hertz/it-13.eml
             * @example hertz/it-23209054.eml
             */
            "#Reservation Confirmation#im" => function (&$it, &$parser) {
                $this->logger->debug('Processors Function #5');
                $trashChars = [chr(0xc2), chr(0xa0)];
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                    $text = str_replace($trashChars, '', $this->mkText($body)); // text representation
                    //$tabbed = $this->mkText($body, true); // text with tabs

                    $result["Kind"] = "L";
                $result['Number'] = preg_match("#Your\s+reservation\s+number\s+is\s+(\S+)#i", $text, $m) ? $m[1] : null;
                $result['RenterName'] = preg_match("#Customer\s+Name:\s*(.+)#i", $text, $m) ? trim($m[1]) : null;
                /* for ($i = 0; $i < strlen($result['RenterName']); $i++)
                    echo ord($result['RenterName'][$i]).','; */
                $result['PickupLocation'] = implode(', ', array_filter([
                    preg_match("#City:(.+)#i", $text, $m),
                    preg_match("#Your\s+reservation\s+number\s+is\s+(\S+)#i", $text, $m),
                ], 'strlen'));

                if (!preg_match('/^[ ]*Renting[ ]*$\s+(?<pickup>.+?)\s+^[ ]*Return[ ]*$\s+(?<dropoff>.+)/ims', $text, $matches)) {
                    $it = $result;

                    return;
                }

                foreach ([1 => 'Pickup', 2 => 'Dropoff'] as $i => $prefix) {
                    $address = [];

                    if (preg_match("#Location:\s*(.+)#i", $matches[$i], $m)) {
                        $address[] = trim($m[1]);
                    }

                    if (preg_match("#Address:\s*(.+)#i", $matches[$i], $m)) {
                        $address[] = trim($m[1]);
                    }

                    if (preg_match("#City:\s*(.+)#i", $matches[$i], $m)) {
                        $address[] = trim($m[1]);
                    }

                    if (!empty($address)) {
                        $result["{$prefix}Location"] = implode(', ', $address);
                    }

                    if (preg_match("#Date/Time:\s*(.+)#i", $matches[$i], $m)) {
                        $result["{$prefix}Datetime"] = strtotime(trim($m[1]));
                    }

                    if (preg_match("#Phone\s+Number:\s*(.+)#i", $matches[$i], $m)) {
                        $result["{$prefix}Phone"] = trim($m[1]);
                    }

                    if (preg_match("#Location\s+Hours:\s*(.+)#i", $matches[$i], $m)) {
                        $result["{$prefix}Hours"] = trim($m[1]);
                    }
                }

                //APPROXIMATE RENTAL CHARGE:                        675.64  USD UNLIMITED FREE  MI
                if (preg_match('#APPROXIMATE\s+RENTAL\s+CHARGE:.*?(\d+.\d+|\d+)\s*([A-Z]{3})?#', $text, $m)) {
                    $result['TotalCharge'] = $m[1];

                    if (!empty($m[2])) {
                        $result['Currency'] = $m[2];
                    }
                }

                $result['CarModel'] = preg_match('#[>]{0,1}(.+)\s+or similar#i', $text, $m) ? trim($m[1]) : null;
                $result['CarType'] = preg_match('#Vehicle:\s*(.+)#i', $text, $m) ? trim($m[1]) : null;
                $it = $result;
            },
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Hertz Reservations') !== false
            || stripos($from, '@hertz.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/\bHertz Reservation\s+[A-Z\d]{5,}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your reservation with Hertz!") or contains(normalize-space(.),"The Hertz Corporation") or contains(.,"@hertz.com") or contains(.,"www.hertz.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".hertz.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach (['Your details are as follows:'] as $phrase) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                return true;
            }
        }

        if ($this->http->XPath->query("//td[contains(.,'Pickup Location')]//td[contains(.,'Address')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        if (!$this->detectEmailByBody($parser)) {
            return [];
        }

        //$this->processors[array_keys($this->processors)[3]]($itineraries, $parser);
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $this->http->SetEmailBody($body = $parser->getPlainBody());
        }

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $body)) {
                $processor($itineraries, $parser);

                break;
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\.#", $value)) { // like 1,299.99
            $value = preg_replace("#,#", '', $value);
        }

        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    public function mkDate($date, $reltime = null)
    {
        if (!$reltime) {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        $unix = is_numeric($date) ? $date : strtotime($this->glue($this->mkText($date), ' '));

        if ($unix) {
            $guessunix = strtotime(date('Y-m-d', $unix) . ' ' . $reltime);

            if ($guessunix < $unix) {
                $guessunix += 60 * 60 * 24;
            } // inc day

            return $guessunix;
        }

        return null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {return $m[1]; }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace('/<br\b.*?\/?>/i', "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    public function xHtml($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindHTMLByXpath($path);
    }

    public function xNode($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }
        $nodes = $instance->FindNodes($path);

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNode($path);
    }

    public function xText($path, $preserveCaret = false, $instance = null)
    {
        if ($preserveCaret) {
            return $this->mkText($this->xHtml($path, $instance));
        } else {
            return $this->xNode($path, $instance);
        }
    }

    public function mkImageUrl($imgTag)
    {
        if (preg_match("#src=(\"|'|)([^'\"]+)(\"|'|)#ims", $imgTag, $m)) {
            return $m[2];
        }

        return null;
    }

    public function glue($str, $with = ", ")
    {
        return implode($with, explode("\n", $str));
    }

    public function re($re, $text = false, $index = 1)
    {
        if (is_numeric($re) && $text == false) {
            return ($this->lastRe && isset($this->lastRe[$re])) ? $this->lastRe[$re] : null;
        }

        $this->lastRe = null;

        if (is_callable($text)) { // we have function
            // go through the text using replace function
            return preg_replace_callback($re, function ($m) use ($text) {
                return $text($m);
            }, $index); // index as text in this case
        }

        if (preg_match($re, $text, $m)) {
            $this->lastRe = $m;

            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    public function mkNice($text, $glue = false)
    {
        $text = $glue ? $this->glue($text, $glue) : $text;

        $text = $this->mkText($text);
        $text = preg_replace("#,+#ms", ',', $text);
        $text = preg_replace("#\s+,\s+#ms", ', ', $text);
        $text = preg_replace_callback("#([\w\d]),([\w\d])#ms", function ($m) {return $m[1] . ', ' . $m[2]; }, $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#\\$#", $text)) {
            return 'USD';
        }

        if (preg_match("#£#", $text)) {
            return 'GBP';
        }

        if (preg_match("#€#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bCAD\b#i", $text)) {
            return 'CAD';
        }

        if (preg_match("#\bEUR\b#i", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        if (preg_match("#\bBRL\b#i", $text)) {
            return 'BRL';
        }

        if (preg_match("#\bCHF\b#i", $text)) {
            return 'CHF';
        }

        if (preg_match("#\bHKD\b#i", $text)) {
            return 'HKD';
        }

        if (preg_match("#\bSEK\b#i", $text)) {
            return 'SEK';
        }

        if (preg_match("#\bZAR\b#i", $text)) {
            return 'ZAR';
        }

        if (preg_match("#\bIN(|R)\b#i", $text)) {
            return 'INR';
        }

        return null;
    }

    public function arrayTabbed($tabbed, $divRowsRe = "#\n#", $divColsRe = "#\t#")
    {
        $r = [];

        foreach (preg_split($divRowsRe, $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (preg_split($divColsRe, $line) as $item) {
                $arr[] = trim($item);
            }
            $r[] = $arr;
        }

        return $r;
    }

    public function arrayColumn($array, $index)
    {
        $r = [];

        foreach ($array as $in) {
            $r[] = $in[$index] ?? null;
        }

        return $r;
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) == 'array' || gettype($array[$i]) == 'object') && sizeof($array[$i]) > 0) || $i == $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function mkClear($re, $text, $by = '')
    {
        return preg_replace($re, $by, $text);
    }

    public function grep($pattern, $input, $flags = 0)
    {
        if (gettype($flags) == 'function') {
            $r = [];

            foreach ($input as $item) {
                $res = preg_replace_callback($pattern, $flags, $item);

                if ($res !== false) {
                    $r[] = $res;
                }
            }

            return $r;
        }

        return preg_grep($pattern, $input, $flags);
    }

    public function xPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function correctByDate($date, $anchorDate)
    {
        // $anchorDate should be earlier than $date
        // not implemented yet
        return $date;
    }
}
