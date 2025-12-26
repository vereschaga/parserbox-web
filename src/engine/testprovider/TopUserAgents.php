<?php

namespace AwardWallet\Engine\testprovider;

use AwardWallet\Engine\ProxyList;
use Cache;
use SeleniumCheckerHelper;
use TAccountChecker;

class TopUserAgents extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome();
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function Parse()
    {
        $cache = Cache::getInstance();

        if ($cache->add("updating_top_user_agents", true, 600)) {
            $this->logger->debug("getFingerprints");
            $fingerprints = $this->db->getFingerprints();
            $totalFingerprints = count($fingerprints);
            $this->logger->debug("Total " . $totalFingerprints . " fingerprints were found");
            $rows = [];

            foreach ($fingerprints as $fingerprint) {
                $userAgent = json_decode($fingerprint['Fingerprint'], true)['fp2']['userAgent'];

                if (isset($rows[$userAgent])) {
                    ++$rows[$userAgent];

                    continue;
                }

                $rows[$userAgent] = 1;
            }

            $totalRows = count($rows);
            $this->logger->debug("Total " . $totalRows . " user-agents were found");
            $agents = [];
            array_multisort($rows, SORT_DESC);
//            $this->logger->debug(var_export($rows, true), ['pre' => true]);

            if ($totalRows > 10) {
                foreach ($rows as $ua => $count) {
                    $agent = [
                        "percent"   => (float) number_format($count / $totalFingerprints * 100, 2),
                        "userAgent" => $ua,
                    ];

                    if ($agent["percent"] <= 0 || stripos($agent["userAgent"], "Mozilla") === false) {
                        continue;
                    }
                    $agents[] = $agent;
                }
                $this->SetBalance(count($agents));
                $this->logger->debug("downloaded " . count($agents) . " top useragents");
                $cache->set("top_user_agents", $agents, 3600 * 48);
            } else {
                $this->sendNotification("ALARM! top_user_agents not found");
            }

            /*
            $this->http->GetURL("https://techblog.willshouse.com/2012/01/03/most-common-user-agents/");
            $this->waitForElement(\WebDriverBy::xpath('//table[contains(@class, "make-html-table most-common-user-agents")]/tbody/tr'), 10);
            $this->saveResponse();
            $rows = $this->http->XPath->query("//table[@class = 'make-html-table most-common-user-agents']/tbody/tr");
            $this->logger->debug("Total {$rows->length} user-agents were found");
            $agents = [];

            if ($rows->length > 10) {
                foreach ($rows as $row) {
                    /** @var DOMNode $row * /
                    $agent = [
                        "percent"   => (float) str_replace('%', '', $row->childNodes->item(0)->nodeValue),
                        "userAgent" => $this->http->FindSingleNode("td[2]", $row),
                    ];

                    if ($agent["percent"] <= 0 || stripos($agent["userAgent"], "Mozilla") === false) {
                        continue;
                    }
                    $agents[] = $agent;
                }
                $this->SetBalance(count($agents));
                $this->logger->debug("downloaded " . count($agents) . " top useragents");
                $cache->set("top_user_agents", $agents, 3600 * 48);
            } else {
                $this->sendNotification("ALARM! top_user_agents not found");
            }
            */
        } else {
            $this->logger->notice("You can get top_user_agents from cache");
            $this->SetBalanceNA();
            $agents = $cache->get("top_user_agents");
        }

        $this->logger->debug(var_export($agents, true), ['pre' => true]);
    }
}
